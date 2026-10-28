<?php
/**
 * Re-sends a fresh code for api/verify_email.php's OTP fallback, without
 * re-running the 3-provider chain (the address was already established as
 * ambiguous by the original verify_email.php call, so there's nothing new to
 * check) — backs the "Resend" control the OTP mini-widget in
 * window.wireEmailCheck() (assets/js/app.js) shows on the admin Add User and
 * receptionist Walk-in "New patient" modals. POST { email }.
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/email_verify.php';

apiAuth(['admin', 'receptionist']);

$in    = requestBody();
$email = strtolower(trim((string) ($in['email'] ?? '')));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonOut(['ok' => false, 'error' => 'Invalid email address.'], 422);
}

$otp = sendEmailOtpChallenge($email);
if (!$otp['sent'] && $otp['dev_otp'] === null) {
    jsonOut(['ok' => false, 'error' => MAIL_SEND_FAILED_MSG], 503);
}
jsonOut(['ok' => true, 'dev_otp' => $otp['dev_otp']]);
