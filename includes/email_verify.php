<?php
/**
 * Real-time mailbox-existence check for the admin "Add User" flow
 * (modules/admin/users.php's #u_email field) and the receptionist Walk-in
 * "New patient" flow (modules/receptionist/dashboard.php's #w_email field),
 * both via api/verify_email.php and the server-side gate in api/users.php
 * action=add / api/records.php action=create. Generalized 2026-09 (migration
 * 051) from an earlier Gmail-only version — every domain (Gmail, Outlook,
 * Yahoo, a custom business domain, ...) is now looked up the same way.
 *
 * Multi-API fallback chain (Abstract API's Email Reputation product primary,
 * EmailVerify.io secondary, Mailboxlayer tertiary), backed by the
 * email_verification_cache table (migration 050, renamed by 051) so the same
 * address is never re-checked within the cache window — all three free tiers
 * are limited monthly. verifyEmailExists() itself always fails OPEN (treats
 * the address as valid) whenever the config is missing/disabled, all three
 * APIs are unreachable, or every one's answer is ambiguous (including a
 * catch-all domain, where a provider's "exists" signal can't actually
 * distinguish a real mailbox from any other address at that domain) — only a
 * clear "this mailbox doesn't exist" response ever turns the check red here,
 * so a flaky third party can never hard-block account creation anywhere this
 * function runs (including the dentist/receptionist "+ Create Record"
 * modals, via the shared api/records.php action=create — those have no OTP
 * UI to satisfy a stronger gate).
 *
 * On top of that, api/verify_email.php (the live client-side check — Add
 * User + Walk-in "New patient" only, 2026-09) additionally challenges an
 * ambiguous ("fail_open") result with a one-time code sent to the address
 * (sendEmailOtpChallenge() / verifyEmailOtp() below) instead of silently
 * accepting it, since the person being created an account for is normally
 * reachable on the spot in both of those two flows (a walk-in patient in
 * person, or a new hire being onboarded). A correct code writes
 * exists_flag=1, source='otp_verified' straight into
 * email_verification_cache, so verifyEmailExists() needs no change to
 * recognize it afterward. This OTP requirement is a client-side UX gate only
 * (it disables that form's Save/submit button until solved) — it does not
 * change verifyEmailExists()'s own fail-open behavior, so a direct API call
 * still succeeds, matching the fail-open philosophy this whole feature was
 * built with (never a hard server-side block on a merely-inconclusive
 * answer).
 */
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

/** Loads config/email_verify.php, or an empty (disabled) config if it's absent — same fallback shape includes/mailer.php uses for config/mail.php. */
function emailVerifyConfig(): array
{
    $f = __DIR__ . '/../config/email_verify.php';
    return is_file($f) ? (array) require $f : [];
}

/** A real-vs-fail-open cache TTL check: a confirmed API answer is trusted for 30 days, a fail-open placeholder only for 1 hour (so a transient outage doesn't get "confirmed valid" for a month, but also isn't hammered every request while it lasts). */
function emailVerifyCacheFresh(string $source, string $checkedAt): bool
{
    $ttlSeconds = $source === 'fail_open' ? 3600 : (30 * 86400);
    return (time() - strtotime($checkedAt)) < $ttlSeconds;
}

/**
 * cURL a single email-verification API and return its raw decoded JSON body,
 * or null on any transport failure / non-2xx response / timeout.
 */
function emailVerifyCurlGet(string $url, int $timeoutSeconds): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeoutSeconds,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errored = curl_errno($ch) !== 0;
    curl_close($ch);

    if ($errored || $body === false || $status < 200 || $status >= 300) {
        return null;
    }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * The one entry point: verifyEmailExists($email) => ['exists' => bool, 'source' => string].
 * $email must already be lowercased/trimmed by the caller. Works for any
 * domain — there is no Gmail-specific gate any more.
 */
function verifyEmailExists(string $email): array
{
    $pdo = getDB();
    $cached = $pdo->prepare('SELECT exists_flag, source, checked_at FROM email_verification_cache WHERE email = ?');
    $cached->execute([$email]);
    $row = $cached->fetch();
    if ($row && emailVerifyCacheFresh($row['source'], $row['checked_at'])) {
        // Return the cached row's real original source (abstract/emailverify/
        // mailboxlayer/fail_open), not a generic 'cache' label — callers (the
        // green "verified" UI state) need to tell a genuinely-confirmed
        // cached answer apart from a cached fail-open guess.
        return ['exists' => (bool) $row['exists_flag'], 'source' => $row['source']];
    }

    $config = emailVerifyConfig();
    $result = null;

    if (!empty($config['enabled'])) {
        $timeout = (int) ($config['timeout_seconds'] ?? 5);

        if (!empty($config['abstract_api_key'])) {
            // Note: this is Abstract's Email Reputation API (emailreputation.abstractapi.com),
            // not the separate Email Validation product (emailvalidation.abstractapi.com) —
            // the account this key belongs to is only subscribed to Reputation, confirmed by
            // testing both live. Its deliverability status is nested + lowercase, unlike
            // Email Validation's top-level DELIVERABLE/UNDELIVERABLE.
            $data = emailVerifyCurlGet(
                'https://emailreputation.abstractapi.com/v1/?api_key=' . urlencode($config['abstract_api_key']) . '&email=' . urlencode($email),
                $timeout
            );
            $deliverability = $data['email_deliverability']['status'] ?? null;
            $isCatchall = (bool) ($data['email_quality']['is_catchall_email'] ?? false);
            if ($isCatchall) {
                // A catch-all domain accepts mail for any address at it, so
                // "deliverable" here doesn't actually confirm this specific
                // mailbox — treat as ambiguous, fall through to the next
                // provider rather than trusting a signal that can't tell.
            } elseif ($deliverability === 'deliverable') {
                $result = ['exists' => true, 'source' => 'abstract'];
            } elseif ($deliverability === 'undeliverable') {
                $result = ['exists' => false, 'source' => 'abstract'];
            }
            // 'unknown', a missing field, or a failed request all fall through to EmailVerify.io.
        }

        if ($result === null && !empty($config['emailverify_api_key'])) {
            $data = emailVerifyCurlGet(
                'https://app.emailverify.io/api/v1/validate?key=' . urlencode($config['emailverify_api_key']) . '&email=' . urlencode($email),
                $timeout
            );
            $status = $data['status'] ?? null;
            if ($status === 'valid') {
                $result = ['exists' => true, 'source' => 'emailverify'];
            } elseif ($status === 'invalid') {
                $result = ['exists' => false, 'source' => 'emailverify'];
            }
            // 'catch-all', 'unknown', a missing field, or a failed request all fall through to Mailboxlayer.
        }

        if ($result === null && !empty($config['mailboxlayer_api_key'])) {
            // apilayer.net/api/check (the mailboxlayer product). smtp_check is a
            // real boolean (true/false), not a status string, so a strict ===
            // check is what tells "confirmed" apart from a missing/failed
            // response (null). catch_all is also a real boolean — on a
            // catch-all domain smtp_check can't actually distinguish this
            // mailbox from any other address at that domain, so that result is
            // treated as ambiguous (this matters far more now than it did for
            // the Gmail-only version, since gmail.com is never catch-all but
            // plenty of business domains are).
            $data = emailVerifyCurlGet(
                'https://apilayer.net/api/check?access_key=' . urlencode($config['mailboxlayer_api_key']) . '&email=' . urlencode($email),
                $timeout
            );
            $smtpCheck = $data['smtp_check'] ?? null;
            $catchAll  = $data['catch_all'] ?? null;
            if ($catchAll === true) {
                // Ambiguous — fails open below.
            } elseif ($smtpCheck === true) {
                $result = ['exists' => true, 'source' => 'mailboxlayer'];
            } elseif ($smtpCheck === false) {
                $result = ['exists' => false, 'source' => 'mailboxlayer'];
            }
            // A missing field or a failed request fails open below.
        }
    }

    if ($result === null) {
        $result = ['exists' => true, 'source' => 'fail_open'];
    }

    $upsert = $pdo->prepare(
        'INSERT INTO email_verification_cache (email, exists_flag, source, checked_at)
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE exists_flag = VALUES(exists_flag), source = VALUES(source), checked_at = NOW()'
    );
    $upsert->execute([$email, $result['exists'] ? 1 : 0, $result['source']]);

    return $result;
}

/**
 * Sends a fresh 6-digit "confirm you own this address" code to $email
 * (overwriting any still-pending one, valid for 10 minutes) — the OTP
 * fallback api/verify_email.php falls back to when verifyEmailExists()
 * itself can't confirm an address either way (see the file doc-comment
 * above). $email must already be lowercased/trimmed by the caller.
 *
 * @return array{sent: bool, demo: bool, dev_otp: ?string}
 */
function sendEmailOtpChallenge(string $email): array
{
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    getDB()->prepare(
        'INSERT INTO email_otp_challenges (email, otp_hash, expires_at, attempts, created_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, NOW())
         ON DUPLICATE KEY UPDATE otp_hash = VALUES(otp_hash), expires_at = VALUES(expires_at), attempts = 0, created_at = NOW()'
    )->execute([$email, hash('sha256', $code)]);

    $res = sendAppMail(
        $email,
        'Confirm your email address - DGT One Dental Center',
        emailOwnershipOtpEmailHtml($code)
    );

    $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    return [
        'sent'    => $res['sent'],
        'demo'    => $res['demo'],
        'dev_otp' => (!$res['sent'] && $isLocal) ? $code : null,
    ];
}

/**
 * Checks a submitted code against the pending challenge for $email. On a
 * correct, unexpired code (fewer than 5 prior wrong attempts): consumes the
 * challenge and records the address as confirmed in
 * email_verification_cache (source='otp_verified', the same 30-day trust
 * window emailVerifyCacheFresh() already gives a real provider answer).
 *
 * @return array{ok: bool, error: ?string}
 */
function verifyEmailOtp(string $email, string $otp): array
{
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT otp_hash, expires_at, attempts FROM email_otp_challenges WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['ok' => false, 'error' => 'No verification code was sent to this address. Please resend one.'];
    }
    if (strtotime($row['expires_at']) < time()) {
        $pdo->prepare('DELETE FROM email_otp_challenges WHERE email = ?')->execute([$email]);
        return ['ok' => false, 'error' => 'This code has expired. Please resend a new one.'];
    }
    if ((int) $row['attempts'] >= 5) {
        $pdo->prepare('DELETE FROM email_otp_challenges WHERE email = ?')->execute([$email]);
        return ['ok' => false, 'error' => 'Too many incorrect attempts. Please resend a new code.'];
    }
    if (!hash_equals($row['otp_hash'], hash('sha256', trim($otp)))) {
        $pdo->prepare('UPDATE email_otp_challenges SET attempts = attempts + 1 WHERE email = ?')->execute([$email]);
        return ['ok' => false, 'error' => 'Incorrect code. Please try again.'];
    }

    $pdo->prepare('DELETE FROM email_otp_challenges WHERE email = ?')->execute([$email]);
    $pdo->prepare(
        'INSERT INTO email_verification_cache (email, exists_flag, source, checked_at)
         VALUES (?, 1, ?, NOW())
         ON DUPLICATE KEY UPDATE exists_flag = 1, source = VALUES(source), checked_at = NOW()'
    )->execute([$email, 'otp_verified']);

    return ['ok' => true, 'error' => null];
}
