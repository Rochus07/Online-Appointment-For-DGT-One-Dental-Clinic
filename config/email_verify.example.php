<?php
/**
 * Real-time mailbox-existence check — used by includes/email_verify.php for
 * the admin "Add User" modal's and the receptionist Walk-in "New patient"
 * modal's inline email validation, and their server-side enforcement
 * (api/users.php action=add, api/records.php action=create). Validates any
 * domain (Gmail, Outlook, Yahoo, a custom business domain, ...) — this was a
 * Gmail-only check before migration 051 generalized it.
 *
 * This is a TEMPLATE. Copy it to `config/email_verify.php` (which is
 * .gitignore'd — never commit real API keys) and fill in real free-tier keys:
 *   1. Sign up (free, no card) at abstractapi.com — subscribe specifically to
 *      the "Email Reputation" product (NOT "Email Validation and
 *      Verification" — a different product with a different key/endpoint;
 *      this code calls emailreputation.abstractapi.com) — and copy its API
 *      key into 'abstract_api_key' below.
 *   2. Sign up (free) at emailverify.io and copy its API key into
 *      'emailverify_api_key' below — used only if Abstract's own call fails
 *      or is ambiguous.
 *   3. Sign up (free) at apilayer.com for the "Mailboxlayer" product and copy
 *      its API key into 'mailboxlayer_api_key' below — used only if both
 *      Abstract and EmailVerify.io fail or are ambiguous.
 *   4. Set 'enabled' => true.
 *
 * While disabled (or if this file doesn't exist, all three APIs are
 * unreachable, or their monthly quotas are exhausted), verifyEmailExists()
 * always fails OPEN — it treats the address as valid rather than blocking a
 * legitimate account, per the app's explicit "never block on a flaky third
 * party" rule. email_verification_cache (migration 050, renamed by 051) is
 * what keeps real usage well under each provider's free-tier quota.
 */
declare(strict_types=1);

return [
    'enabled'              => false,
    'abstract_api_key'     => '',
    'emailverify_api_key'  => '',
    'mailboxlayer_api_key' => '',
    'timeout_seconds'      => 5,   // per-API cURL timeout, so a slow/down provider never hangs the Add User submit
];
