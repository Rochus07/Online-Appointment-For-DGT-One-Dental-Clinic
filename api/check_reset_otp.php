<?php
/**
 * Public endpoint: check whether a 6-digit password-reset code is currently
 * valid for an e-mail — WITHOUT consuming it or changing anything.
 *
 * Powers the Forgot Password modal's two-phase Step 2: the code is verified
 * on its own first (this endpoint), and only once it checks out does the
 * modal reveal the New Password fields. The actual password change + the
 * code's single-use consumption still happen together in
 * api/reset_password_otp.php, which re-validates the same way — this
 * endpoint is a UX gate, not the security boundary.
 *
 * POST { email, otp }
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$in    = requestBody();
$email = trim((string) ($in['email'] ?? ''));
$otp   = trim((string) ($in['otp'] ?? ''));
$badCode = 'Incorrect or expired code. Please check your inbox and try again, or request a new code.';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $otp)) {
    jsonOut(['ok' => false, 'error' => $badCode], 422);
}
// Brute-force lock (2026-10-23): 5 wrong codes for this e-mail in 15 minutes.
$lockLeft = throttleLocked('reset_otp', $email);
if ($lockLeft > 0) {
    jsonOut(['ok' => false, 'error' => 'Too many wrong codes. ' . throttleWaitText($lockLeft) . ' You can request a new code then.'], 429);
}

$stmt = db()->prepare(
    "SELECT 1 FROM users
     WHERE email = ? AND is_active = 1
       AND reset_token_hash = ? AND reset_token_expires_at > NOW()
     LIMIT 1"
);
$stmt->execute([$email, hash('sha256', $otp)]);

if (!$stmt->fetch()) {
    $triesLeft = throttleFail('reset_otp', $email);
    jsonOut(['ok' => false, 'error' => $triesLeft > 0 ? $badCode : 'Too many wrong codes. ' . throttleWaitText(THROTTLE_WINDOW_MIN * 60) . ' You can request a new code then.'], $triesLeft > 0 ? 422 : 429);
}

jsonOut(['ok' => true]);
