<?php
/**
 * Minimal outbound-mail helper.
 *
 * Sends via PHPMailer/SMTP when config/mail.php is enabled AND PHPMailer is
 * available. Otherwise it falls back to "demo delivery": the message is appended
 * to logs/mail.log and the caller is told it was not really sent.
 */
declare(strict_types=1);

/** Shown when a code e-mail could not be sent on a real host (SMTP failed). */
if (!defined('MAIL_SEND_FAILED_MSG')) {
    define('MAIL_SEND_FAILED_MSG', "We couldn't send the e-mail right now. Please try again later.");
}

/** Public base URL of this install, e.g. http://localhost/dgt_dental_clinic */
function appBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/dgt_dental_clinic';
}

/** SMTP config array from config/mail.php (empty array if the file is missing). */
function mailConfig(): array
{
    $f = __DIR__ . '/../config/mail.php';
    return is_file($f) ? (array) require $f : [];
}

/** Is a usable PHPMailer class loadable (composer autoload, or bundled src/)? */
function phpmailerAvailable(): bool
{
    if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        return true;
    }
    $src = __DIR__ . '/vendor/PHPMailer/src/';
    if (is_file($src . 'PHPMailer.php')) {
        require_once $src . 'Exception.php';
        require_once $src . 'PHPMailer.php';
        require_once $src . 'SMTP.php';
        return class_exists(\PHPMailer\PHPMailer\PHPMailer::class);
    }
    return false;
}

/**
 * @return array{sent: bool, demo: bool}  sent=true only when SMTP accepted the message.
 */
function sendAppMail(string $to, string $subject, string $html, string $text = ''): array
{
    if ($text === '') {
        // Drop the whole <head> (title/meta/<style>) first — strip_tags() only
        // removes tags, not the CSS text sitting inside a <style> block, which
        // would otherwise leak into the plain-text alt body (and the inbox
        // preview snippet clients like Gmail pull from it).
        $bodyOnly = preg_replace('#<head\b[^>]*>.*?</head>#is', '', $html) ?? $html;
        $text = trim(preg_replace('/\n{3,}/', "\n\n", strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $bodyOnly))));
        // Turn &amp; / &#8369; / &nbsp; back into real characters for the
        // plain-text copy (and the inbox preview snippet).
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    $cfg = mailConfig();
    if (!empty($cfg['enabled']) && phpmailerAvailable()) {
        try {
            $m = new \PHPMailer\PHPMailer\PHPMailer(true);
            // PHPMailer defaults to ISO-8859-1, which garbles UTF-8 text
            // (the peso sign showed as "â‚±"); send everything as UTF-8.
            $m->CharSet  = 'UTF-8';
            $m->Encoding = 'base64';
            $m->isSMTP();
            $m->Host       = (string) ($cfg['host'] ?? '');
            $m->Port       = (int) ($cfg['port'] ?? 587);
            $m->SMTPAuth   = true;
            $m->Username   = (string) ($cfg['username'] ?? '');
            $m->Password   = (string) ($cfg['password'] ?? '');
            if (!empty($cfg['encryption'])) {
                $m->SMTPSecure = (string) $cfg['encryption'];
            }
            $m->setFrom((string) ($cfg['from'] ?? 'no-reply@localhost'), (string) ($cfg['from_name'] ?? 'DGT One Dental Center - Bulacan'));
            $m->addAddress($to);
            $m->Subject = $subject;
            $m->isHTML(true);
            $m->Body    = $html;
            $m->AltBody  = $text;
            $m->send();
            return ['sent' => true, 'demo' => false];
        } catch (\Throwable $e) {
            error_log('sendAppMail: SMTP send failed — ' . $e->getMessage());
        }
    }

    // Demo fallback — no working transport on this host.
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    @file_put_contents(
        $dir . '/mail.log',
        '[' . date('c') . "] TO: {$to}\nSUBJECT: {$subject}\n\n{$text}\n" . str_repeat('-', 62) . "\n",
        FILE_APPEND
    );
    return ['sent' => false, 'demo' => true];
}

/**
 * Issue a fresh 6-digit e-mail-verification OTP for a user (overwriting any
 * previous one, valid for 10 minutes) and e-mail it. Returns the sendAppMail
 * result plus a 'dev_otp' — the raw code, exposed only on localhost where
 * there is no SMTP so the flow stays testable (same convention as the
 * password-reset endpoint's 'dev_link').
 *
 * @return array{sent: bool, demo: bool, dev_otp: ?string}
 */
function sendVerificationOtp(PDO $pdo, int $userId, string $name, string $email): array
{
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE users SET verify_otp_hash = ?, verify_otp_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE user_id = ?')
        ->execute([hash('sha256', $code), $userId]);

    $res = sendAppMail(
        $email,
        'Your DGT One Dental Center - Bulacan verification code',
        verificationOtpEmailHtml($name, $code)
    );

    $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    return [
        'sent'    => $res['sent'],
        'demo'    => $res['demo'],
        'dev_otp' => (!$res['sent'] && $isLocal) ? $code : null,
    ];
}

/**
 * Shared branded e-mail shell — light DGT-ink canvas, DGT-blue header band,
 * white card, teal CTA button, link-fallback text, and a footer band that
 * sits flush inside the same rounded card (not floating outside it). Matches
 * the app's own "calm, light clinic tool" palette — see the dgt-design-system
 * notes. Table-based markup (not a styled <body>) for reliable Gmail/Outlook
 * rendering. `$bodyHtml` / `$footNoteHtml` may contain pre-escaped inline HTML
 * (e.g. a <strong> around the name); the caller must escape any raw user input
 * before interpolating it.
 */
function mailShell(string $heading, string $bodyHtml, string $ctaLabel, string $link, string $footNoteHtml, string $extraHtml = ''): string
{
    $safeLink = htmlspecialchars($link, ENT_QUOTES);
    // A real, publicly-fetchable URL (not a cid: embed) — an embedded image is
    // technically a MIME attachment, and Gmail's inbox list always shows one as
    // an attachment chip even when it's used inline in the body. A live URL has
    // no attachment part at all. This only actually loads once the app is served
    // from a real public domain — on localhost Gmail can't reach it and the logo
    // shows broken, same as any other image on a dev-only host.
    $logoUrl  = appBaseUrl() . '/assets/img/dgt_logo.jpg';
    $year     = date('Y');
    // A bare table fragment (no <!DOCTYPE>/<head>) lets some clients — notably
    // Gmail when the VIEWER's own app is set to dark mode — auto-remap the
    // colors, ignoring the inline styles below. A full document with explicit
    // "light only" color-scheme meta tags (+ a dark-mode media query that just
    // re-asserts the same light colors, belt-and-braces) stops that override.
    // `bgcolor` attributes are kept alongside every inline background as a
    // second, more dark-mode-resistant way of stating the same color.
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
  :root { color-scheme: light; supported-color-schemes: light; }
  @media (prefers-color-scheme: dark) {
    body, .dgt-bg      { background:#eef1f6 !important; }
    .dgt-card          { background:#ffffff !important; }
    .dgt-head          { background:#006cc0 !important; }
    .dgt-heading       { color:#102f51 !important; }
    .dgt-body, .dgt-hint { color:#5b6b82 !important; }
    .dgt-foot          { background:#f4f6f9 !important; color:#8896a8 !important; }
  }
</style>
</head>
<body style="margin:0;background:#eef1f6;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#eef1f6" class="dgt-bg" style="background:#eef1f6;padding:32px 12px;">
<tr><td align="center">
<table role="presentation" width="480" cellpadding="0" cellspacing="0" bgcolor="#ffffff" class="dgt-card" style="max-width:480px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;font-family:'Segoe UI',Arial,sans-serif;border:1px solid #e1e7f0;">
<tr><td bgcolor="#006cc0" class="dgt-head" style="background:#006cc0;padding:20px 24px;text-align:center;">
  <span style="color:#ffffff;font-size:17px;font-weight:800;letter-spacing:.05em;">DGT ONE DENTAL CENTER</span>
</td></tr>
<tr><td style="padding:38px 34px 30px;text-align:center;">
  <img src="{$logoUrl}" width="56" height="56" alt="DGT One Dental Center" style="width:56px;height:56px;border-radius:50%;display:block;margin:0 auto 22px;background:#ffffff;object-fit:cover;border:1px solid #e1e7f0;">
  <h1 class="dgt-heading" style="margin:0 0 12px;color:#102f51;font-size:20px;font-weight:700;">{$heading}</h1>
  <p class="dgt-body" style="margin:0 0 28px;color:#5b6b82;font-size:14px;line-height:1.7;">{$bodyHtml}</p>
  {$extraHtml}
  <a href="{$safeLink}" style="display:inline-block;background:#0086a0;color:#ffffff;padding:14px 30px;border-radius:8px;font-size:13px;font-weight:700;letter-spacing:.03em;text-decoration:none;">{$ctaLabel}</a>
  <p class="dgt-hint" style="margin:30px 0 6px;color:#8896a8;font-size:12px;">If the button doesn't work, copy and paste this link into your browser:</p>
  <p style="margin:0;word-break:break-all;"><a href="{$safeLink}" style="color:#006cc0;font-size:12px;">{$safeLink}</a></p>
  <hr style="border:none;border-top:1px solid #e1e7f0;margin:28px 0;">
  <p class="dgt-hint" style="margin:0;color:#8896a8;font-size:12px;line-height:1.6;">{$footNoteHtml}</p>
</td></tr>
<tr><td bgcolor="#f4f6f9" class="dgt-foot" style="background:#f4f6f9;padding:16px;text-align:center;border-top:1px solid #e1e7f0;">
  <span style="color:#8896a8;font-size:11px;">© {$year} DGT One Dental Center. All rights reserved.</span>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

/**
 * Same card chrome as mailShell(), but for a message that hands over a
 * one-time code to type in rather than a link to click — no CTA button, no
 * link-fallback paragraph, just the code in a large letter-spaced box.
 */
function mailShellCode(string $heading, string $bodyHtml, string $code, string $footNoteHtml): string
{
    $safeCode = htmlspecialchars($code, ENT_QUOTES);
    $logoUrl  = appBaseUrl() . '/assets/img/dgt_logo.jpg';
    $year     = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
  :root { color-scheme: light; supported-color-schemes: light; }
  @media (prefers-color-scheme: dark) {
    body, .dgt-bg      { background:#eef1f6 !important; }
    .dgt-card          { background:#ffffff !important; }
    .dgt-head          { background:#006cc0 !important; }
    .dgt-heading       { color:#102f51 !important; }
    .dgt-body, .dgt-hint { color:#5b6b82 !important; }
    .dgt-code          { background:#eef1f6 !important; color:#102f51 !important; }
    .dgt-foot          { background:#f4f6f9 !important; color:#8896a8 !important; }
  }
</style>
</head>
<body style="margin:0;background:#eef1f6;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#eef1f6" class="dgt-bg" style="background:#eef1f6;padding:32px 12px;">
<tr><td align="center">
<table role="presentation" width="480" cellpadding="0" cellspacing="0" bgcolor="#ffffff" class="dgt-card" style="max-width:480px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;font-family:'Segoe UI',Arial,sans-serif;border:1px solid #e1e7f0;">
<tr><td bgcolor="#006cc0" class="dgt-head" style="background:#006cc0;padding:20px 24px;text-align:center;">
  <span style="color:#ffffff;font-size:17px;font-weight:800;letter-spacing:.05em;">DGT ONE DENTAL CENTER</span>
</td></tr>
<tr><td style="padding:38px 34px 30px;text-align:center;">
  <img src="{$logoUrl}" width="56" height="56" alt="DGT One Dental Center" style="width:56px;height:56px;border-radius:50%;display:block;margin:0 auto 22px;background:#ffffff;object-fit:cover;border:1px solid #e1e7f0;">
  <h1 class="dgt-heading" style="margin:0 0 12px;color:#102f51;font-size:20px;font-weight:700;">{$heading}</h1>
  <p class="dgt-body" style="margin:0 0 26px;color:#5b6b82;font-size:14px;line-height:1.7;">{$bodyHtml}</p>
  <div class="dgt-code" style="display:inline-block;background:#eef1f6;color:#102f51;padding:16px 28px;border-radius:10px;font-family:'Courier New',Consolas,monospace;font-size:32px;font-weight:800;letter-spacing:.35em;">{$safeCode}</div>
  <hr style="border:none;border-top:1px solid #e1e7f0;margin:30px 0 28px;">
  <p class="dgt-hint" style="margin:0;color:#8896a8;font-size:12px;line-height:1.6;">{$footNoteHtml}</p>
</td></tr>
<tr><td bgcolor="#f4f6f9" class="dgt-foot" style="background:#f4f6f9;padding:16px;text-align:center;border-top:1px solid #e1e7f0;">
  <span style="color:#8896a8;font-size:11px;">© {$year} DGT One Dental Center. All rights reserved.</span>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

/** Build the HTML body of the e-mail-address verification OTP e-mail. */
function verificationOtpEmailHtml(string $name, string $code): string
{
    $safeName = htmlspecialchars($name, ENT_QUOTES);
    return mailShellCode(
        'Verify Your Email Address',
        "Hi <strong style=\"color:#102f51\">{$safeName}</strong>, welcome to DGT One Dental Center! "
            . 'Enter this code on the verification page to activate your account.',
        $code,
        'This code expires in <strong>10 minutes</strong> and can be used once. If you did not create '
            . 'this account, you can safely ignore this e-mail.'
    );
}

/**
 * Build the HTML body of the "confirm this address is real" OTP e-mail used
 * by the email-existence check's OTP fallback (includes/email_verify.php's
 * sendEmailOtpChallenge()) — sent to an address that has no account yet
 * (the admin Add User modal, the receptionist Walk-in "New patient" modal),
 * so unlike the two OTP e-mails below there is no name to greet.
 */
function emailOwnershipOtpEmailHtml(string $code): string
{
    return mailShellCode(
        'Confirm Your Email Address',
        'Please enter this code to confirm this is a real, working email address.',
        $code,
        'This code expires in <strong>10 minutes</strong> and can be used once. If you did not expect '
            . 'this e-mail, you can safely ignore it.'
    );
}

/** Build the HTML body of the password-reset OTP e-mail. */
function passwordResetOtpEmailHtml(string $name, string $code): string
{
    $safeName = htmlspecialchars($name, ENT_QUOTES);
    return mailShellCode(
        'Reset Your Password',
        "Hi <strong style=\"color:#102f51\">{$safeName}</strong>, we received a request to reset the "
            . 'password for your DGT One Dental Center account. Enter this code on the sign-in page to choose a new one.',
        $code,
        'This code expires in <strong>15 minutes</strong> and can be used once. If you did not request '
            . 'this, you can safely ignore this e-mail. Your password stays the same.'
    );
}
