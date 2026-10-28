<?php
/**
 * Verifies the 6-digit code sent by api/verify_email.php's OTP fallback (see
 * includes/email_verify.php's sendEmailOtpChallenge() / verifyEmailOtp()) —
 * the admin Add User modal's #u_email field and the receptionist Walk-in
 * "New patient" modal's #w_email field only. POST { email, otp }. On a
 * correct, unexpired code the address is recorded as confirmed in
 * email_verification_cache (source='otp_verified'), so a follow-up call to
 * verify_email.php, or the server-side action=add / action=create gate, both
 * see it as confirmed with no other code needing to know an OTP was involved.
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/email_verify.php';

apiAuth(['admin', 'receptionist']);

$in    = requestBody();
$email = strtolower(trim((string) ($in['email'] ?? '')));
$otp   = trim((string) ($in['otp'] ?? ''));

if ($email === '' || $otp === '') {
    jsonOut(['ok' => false, 'error' => 'Missing email or code.'], 422);
}

$result = verifyEmailOtp($email, $otp);
if (!$result['ok']) {
    jsonOut(['ok' => false, 'error' => $result['error']], 422);
}

jsonOut(['ok' => true, 'valid' => true, 'source' => 'otp_verified']);
