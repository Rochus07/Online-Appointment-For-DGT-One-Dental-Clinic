<?php
/**
 * Public endpoint: start the "Forgot your password?" flow.
 *
 * POST { email }
 *   - An unregistered (or archived) e-mail gets an error and the modal stays
 *     on step 1 (2026-10-15, at the user's request; this replaced the old
 *     generic "if an account exists" reply, so it does reveal which e-mails
 *     have an account).
 *   - If the e-mail belongs to an active account: generate a 6-digit code,
 *     store SHA-256(code) + a 15-minute expiry on the user row (the same
 *     reset_token_hash / reset_token_expires_at columns the old link-based
 *     flow used — only the value being hashed changed, from a random link
 *     token to a short numeric code), and e-mail it.
 *   - On localhost with no working mail transport, the code is returned in the
 *     response ('dev_otp') so the flow can be exercised without SMTP — same
 *     convention as api/request_verification.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$email   = trim((string) (requestBody()['email'] ?? ''));
$generic = "We've sent a 6-digit code to reset your password.";

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonOut(['ok' => false, 'error' => 'Please enter a valid e-mail address.'], 422);
}

$stmt = db()->prepare(
    "SELECT user_id, full_name FROM users WHERE email = ? AND is_active = 1 LIMIT 1"
);
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // not_registered: no account at all uses this address (not merely an
    // archived one), so the login page can offer a link to sign up.
    $any = db()->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
    $any->execute([$email]);
    jsonOut(['ok' => false, 'error' => 'No account is registered with this e-mail address.',
             'not_registered' => !$any->fetchColumn()], 404);
}

$devOtp = null;

if ($user) {
    $otp  = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = hash('sha256', $otp);

    // Expiry is computed on the DB clock so it matches the NOW() check in
    // api/reset_password_otp.php (PHP and MySQL can sit in different timezones).
    db()->prepare(
        "UPDATE users
            SET reset_token_hash = ?, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
          WHERE user_id = ?"
    )->execute([$hash, (int) $user['user_id']]);

    $result = sendAppMail(
        $email,
        'Reset your DGT One Dental Center - Bulacan password',
        passwordResetOtpEmailHtml((string) $user['full_name'], $otp)
    );

    $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if (!$result['sent']) {
        if (!$isLocal) {
            jsonOut(['ok' => false, 'error' => MAIL_SEND_FAILED_MSG], 503);
        }
        $devOtp = $otp;
    }
}

$out = ['ok' => true, 'message' => $generic];
if ($devOtp !== null) {
    $out['dev_otp']  = $devOtp;
    $out['dev_note'] = 'E-mail is not configured on this host. Use this code to reset your password.';
}
jsonOut($out);
