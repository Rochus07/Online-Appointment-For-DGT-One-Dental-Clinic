<?php
/**
 * Public endpoint: (re)send the e-mail-address verification OTP.
 *
 * POST { email }
 *   - A bad format, an unregistered (or archived) e-mail, or an account that
 *     is already verified gets an error instead of a "code sent" message
 *     (2026-10-15, same as the Forgot Password change; this does reveal
 *     which e-mails have an account).
 *   - For an active, still-unverified patient: issue a fresh 6-digit code
 *     (valid 10 minutes) and e-mail it. If the e-mail could not be sent on a
 *     real host, say so instead of claiming it was sent.
 *   - On localhost (no SMTP) the code is returned as 'dev_otp' so the flow can
 *     be exercised without mail, same convention as request_password_reset.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$email = trim((string) (requestBody()['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonOut(['ok' => false, 'error' => 'Please enter a valid e-mail address.'], 422);
}

$stmt = db()->prepare(
    "SELECT user_id, full_name, is_verified FROM users
     WHERE email = ? AND role = 'patient' AND is_active = 1
     ORDER BY is_verified ASC
     LIMIT 1"
);
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    jsonOut(['ok' => false, 'error' => 'No account is registered with this e-mail address.'], 404);
}
if ((int) $user['is_verified'] === 1) {
    jsonOut(['ok' => false, 'error' => 'This e-mail is already verified. You can sign in.', 'verified' => true], 409);
}

$v = sendVerificationOtp(db(), (int) $user['user_id'], (string) $user['full_name'], $email);
if (!$v['sent'] && $v['dev_otp'] === null) {
    jsonOut(['ok' => false, 'error' => MAIL_SEND_FAILED_MSG], 503);
}

$out = ['ok' => true, 'message' => 'A new verification code has been sent to your e-mail.'];
if ($v['dev_otp'] !== null) {
    $out['dev_otp']  = $v['dev_otp'];
    $out['dev_note'] = 'E-mail is not configured on this host. Use this code to verify.';
}
jsonOut($out);
