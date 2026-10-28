<?php
/**
 * Public endpoint: verify the Forgot Password modal's 6-digit code and set a
 * new password in one step — the modal never leaves login.php for a separate
 * reset_password.php visit.
 *
 * POST { email, otp, password }
 *   - Looks up an active user by e-mail whose reset_token_hash = SHA-256(otp)
 *     and reset_token_expires_at is still in the future (DB clock, same
 *     convention as api/verify_otp.php).
 *   - On match: sets the new password, clears must_change_password (nothing
 *     left to force) and both reset-token columns (single use), then signs
 *     the account straight in — "Reset Password & Sign In" shouldn't need a
 *     separate manual login step right after.
 *   - On no match: one generic error — never reveals whether the e-mail
 *     exists, the code was wrong, or it expired.
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$in       = requestBody();
$email    = trim((string) ($in['email'] ?? ''));
$otp      = trim((string) ($in['otp'] ?? ''));
$password = (string) ($in['password'] ?? '');
$badCode  = 'Incorrect or expired code. Please check your inbox and try again, or request a new code.';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $otp)) {
    jsonOut(['ok' => false, 'error' => $badCode], 422);
}
// Brute-force lock (2026-10-23): 5 wrong codes for this e-mail in 15 minutes.
$lockLeft = throttleLocked('reset_otp', $email);
if ($lockLeft > 0) {
    jsonOut(['ok' => false, 'error' => 'Too many wrong codes. ' . throttleWaitText($lockLeft) . ' You can request a new code then.'], 429);
}
if (!isStrongPassword($password)) {
    jsonOut(['ok' => false, 'error' => STRONG_PASSWORD_MSG], 422);
}

$stmt = db()->prepare(
    "SELECT * FROM users
     WHERE email = ? AND is_active = 1
       AND reset_token_hash = ? AND reset_token_expires_at > NOW()
     LIMIT 1"
);
$stmt->execute([$email, hash('sha256', $otp)]);
$user = $stmt->fetch();

if (!$user) {
    $triesLeft = throttleFail('reset_otp', $email);
    jsonOut(['ok' => false, 'error' => $triesLeft > 0 ? $badCode : 'Too many wrong codes. ' . throttleWaitText(THROTTLE_WINDOW_MIN * 60) . ' You can request a new code then.'], $triesLeft > 0 ? 422 : 429);
}

throttleClear('reset_otp', $email);
$uid = (int) $user['user_id'];
db()->prepare(
    "UPDATE users
        SET password = ?, must_change_password = 0,
            reset_token_hash = NULL, reset_token_expires_at = NULL
      WHERE user_id = ?"
)->execute([password_hash($password, PASSWORD_DEFAULT), $uid]);

session_regenerate_id(true);
$_SESSION['user_id']   = $uid;
$_SESSION['role']      = $user['role'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['username']  = $user['username'];
logActivity('auth', 'Reset password via e-mailed code and signed in', $user);

$dest = roleHome($user['role']);   // patients straight to their portal
jsonOut(['ok' => true, 'message' => 'Your password has been updated.', 'redirect' => $dest]);
