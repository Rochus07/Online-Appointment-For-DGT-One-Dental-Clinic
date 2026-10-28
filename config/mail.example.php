<?php
/**
 * Outbound e-mail (SMTP) settings — used by includes/mailer.php for the
 * password-reset link, e-mail verification, and any future notification e-mails.
 *
 * This is a TEMPLATE. Copy it to `config/mail.php` (which is .gitignore'd — never
 * commit real credentials) and fill in a real account:
 *   1. PHPMailer is already vendored at includes/vendor/PHPMailer/src/ (no composer
 *      needed) — phpmailerAvailable() in includes/mailer.php loads it directly.
 *   2. Fill in real SMTP credentials below and set 'enabled' => true.
 *      For Gmail: turn on 2-Step Verification, then create an App Password at
 *      myaccount.google.com/apppasswords — use that 16-character code as
 *      'password', NOT the account's normal login password.
 *
 * While disabled (or if the SMTP send throws), mailer.php writes each message to
 * logs/mail.log instead, and on localhost the password-reset endpoint returns the
 * link directly in the JSON (`dev_link`), and the e-mail-verification endpoint
 * returns the 6-digit code (`dev_otp`), so both flows stay testable without email.
 */
declare(strict_types=1);

return [
    'enabled'    => false,
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',          // 'tls' | 'ssl' | ''
    'username'   => '',
    'password'   => '',
    'from'       => 'no-reply@dgtonedentalph.com',
    'from_name'  => 'DGT One Dental Center - Bulacan',
];
