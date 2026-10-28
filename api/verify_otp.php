<?php
/**
 * Public endpoint: confirm a patient's e-mail address with the 6-digit OTP
 * sent by api/request_verification.php (also issued right after registering).
 *
 * POST { email, otp }
 *   - Looks up an active, still-unverified patient by e-mail whose
 *     verify_otp_hash = SHA-256(otp) and verify_otp_expires_at is still in
 *     the future (checked on the DB clock, same convention as password reset).
 *   - On match: sets is_verified = 1 and clears both OTP columns (single use).
 *   - On no match: one generic error — never reveals whether the e-mail exists,
 *     the code was wrong, or it expired.
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$in    = requestBody();
$email = trim((string) ($in['email'] ?? ''));
$otp   = trim((string) ($in['otp'] ?? ''));
$badCode = 'Incorrect or expired code. Please check your inbox and try again, or request a new code.';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $otp)) {
    jsonOut(['ok' => false, 'error' => $badCode], 422);
}
// Brute-force lock (2026-10-23): 5 wrong codes for this e-mail in 15 minutes.
$lockLeft = throttleLocked('verify_otp', $email);
if ($lockLeft > 0) {
    jsonOut(['ok' => false, 'error' => 'Too many wrong codes. ' . throttleWaitText($lockLeft) . ' You can request a new code then.'], 429);
}

$stmt = db()->prepare(
    "SELECT user_id FROM users
     WHERE email = ? AND role = 'patient' AND is_active = 1 AND is_verified = 0
       AND verify_otp_hash = ? AND verify_otp_expires_at > NOW()
     LIMIT 1"
);
$stmt->execute([$email, hash('sha256', $otp)]);
$user = $stmt->fetch();

if (!$user) {
    $triesLeft = throttleFail('verify_otp', $email);
    jsonOut(['ok' => false, 'error' => $triesLeft > 0 ? $badCode : 'Too many wrong codes. ' . throttleWaitText(THROTTLE_WINDOW_MIN * 60) . ' You can request a new code then.'], $triesLeft > 0 ? 422 : 429);
}

throttleClear('verify_otp', $email);
$uid = (int) $user['user_id'];
db()->prepare(
    'UPDATE users SET is_verified = 1, verify_otp_hash = NULL, verify_otp_expires_at = NULL WHERE user_id = ?'
)->execute([$uid]);

$out = ['ok' => true, 'message' => 'Your e-mail address is verified. You can now sign in.'];

// If this exact browser session is the one that just registered this exact
// account (see login.php's $_SESSION['pending_verify_user_id']), sign them
// straight in — the "Verify & Sign In" button on the registration success
// screen shouldn't need a separate manual login step right after. A visit to
// the standalone verify.php page (no such session flag) still lands on the
// plain "E-mail verified — Sign In" card, unchanged.
if ((int) ($_SESSION['pending_verify_user_id'] ?? 0) === $uid) {
    $full = db()->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1');
    $full->execute([$uid]);
    $u = $full->fetch();
    if ($u) {
        session_regenerate_id(true);
        $_SESSION['user_id']   = (int) $u['user_id'];
        $_SESSION['role']      = $u['role'];
        $_SESSION['full_name'] = $u['full_name'];
        $_SESSION['username']  = $u['username'];
        unset($_SESSION['pending_verify_user_id'], $_SESSION['pending_verify_email']);
        logActivity('auth', 'Signed in', $u);
        $out['signed_in'] = true;
        $out['redirect']  = patientLandingUrl((int) $u['user_id']);   // My Profile first while details are missing
    }
}

jsonOut($out);
