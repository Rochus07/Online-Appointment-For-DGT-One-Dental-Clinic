<?php
/**
 * Public endpoint: correct a typo in the e-mail address just entered on
 * login.php's registration success screen, before it has been verified.
 *
 * POST { email }
 *   - Only works for the exact account that THIS browser session just
 *     registered ($_SESSION['pending_verify_user_id'], set by login.php right
 *     after account creation) — knowing the old e-mail address is not enough,
 *     since this changes which address can claim the account.
 *   - Validates the new address, rejects one already in use by another
 *     account, updates users.email, and re-issues a fresh verification OTP to
 *     the corrected address (the old code is implicitly invalidated — a new
 *     hash overwrites it).
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$uid = (int) ($_SESSION['pending_verify_user_id'] ?? 0);
if ($uid <= 0) {
    jsonOut(['ok' => false, 'error' => 'Your session has expired. Please register again.'], 400);
}

$newEmail = trim((string) (requestBody()['email'] ?? ''));
if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    jsonOut(['ok' => false, 'error' => 'Please enter a valid e-mail address.'], 422);
}

$stmt = db()->prepare(
    "SELECT full_name FROM users WHERE user_id = ? AND role = 'patient' AND is_active = 1 AND is_verified = 0 LIMIT 1"
);
$stmt->execute([$uid]);
$user = $stmt->fetch();
if (!$user) {
    jsonOut(['ok' => false, 'error' => 'Your session has expired. Please register again.'], 400);
}

$chk = db()->prepare('SELECT 1 FROM users WHERE email = ? AND user_id <> ? LIMIT 1');
$chk->execute([$newEmail, $uid]);
if ($chk->fetch()) {
    jsonOut(['ok' => false, 'error' => 'That e-mail is already registered.'], 422);
}

db()->prepare('UPDATE users SET email = ? WHERE user_id = ?')->execute([$newEmail, $uid]);
$_SESSION['pending_verify_email'] = $newEmail;

$v = sendVerificationOtp(db(), $uid, (string) $user['full_name'], $newEmail);

$out = ['ok' => true, 'email' => $newEmail, 'message' => 'E-mail updated. A new code has been sent.'];
// The address is saved either way; only the code e-mail failed, so say so.
if (!$v['sent'] && $v['dev_otp'] === null) {
    $out['mail_failed'] = true;
    $out['message']     = "E-mail updated, but we couldn't send the code right now. Please tap Resend in a moment.";
}
if ($v['dev_otp'] !== null) {
    $out['dev_otp'] = $v['dev_otp'];
}
jsonOut($out);
