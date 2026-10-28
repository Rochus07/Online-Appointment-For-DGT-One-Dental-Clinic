<?php
/**
 * Real-time "does this mailbox actually exist?" check, used by the admin Add
 * User modal's #u_email field (modules/admin/users.php) and the receptionist
 * walk-in "New patient" modal's #w_email field (modules/receptionist/dashboard.php),
 * both via window.wireEmailCheck in app.js. POST { email } — always ok:true;
 * `valid:false` only on a confirmed non-existent mailbox, everything else
 * (malformed, unknown/ambiguous-but-otp-verified) reports valid:true. Works
 * for any domain (Gmail, Outlook, Yahoo, a custom business domain, ...) —
 * this was Gmail-only before migration 051 generalized it. See
 * includes/email_verify.php for the full multi-API fallback + cache logic
 * this endpoint is a thin wrapper over — the same logic is also enforced
 * server-side in api/users.php action=add and api/records.php action=create,
 * so this endpoint is a live UX hint, not the only enforcement point.
 *
 * OTP fallback (2026-09): when verifyEmailExists() can't confirm the address
 * either way (source='fail_open'), this endpoint — unlike verifyEmailExists()
 * itself, which still fails open everywhere else it's used — challenges the
 * address with a one-time code (sendEmailOtpChallenge()) and reports
 * `otp_required:true` instead of silently reporting valid:true, since the
 * person being created an account for in these two specific flows is
 * normally reachable on the spot to read it back. See api/verify_email_otp.php
 * (checks the code) and api/resend_email_otp.php (re-sends it without
 * re-running the 3-provider chain) — both wired by the OTP mini-widget
 * window.wireEmailCheck() shows in app.js.
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/email_verify.php';

apiAuth(['admin', 'receptionist']);

$in    = requestBody();
$email = strtolower(trim((string) ($in['email'] ?? '')));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonOut(['ok' => true, 'valid' => true, 'source' => 'skip']);
}

$result = verifyEmailExists($email);

if ($result['source'] === 'fail_open') {
    $otp = sendEmailOtpChallenge($email);
    jsonOut([
        'ok'           => true,
        'valid'        => false,
        'source'       => 'otp_required',
        'otp_required' => true,
        'dev_otp'      => $otp['dev_otp'],
        // The code e-mail itself failed on a real host: the widget says so.
        'mail_failed'  => !$otp['sent'] && $otp['dev_otp'] === null,
        'mail_error'   => MAIL_SEND_FAILED_MSG,
    ]);
}

jsonOut(['ok' => true, 'valid' => $result['exists'], 'source' => $result['source']]);
