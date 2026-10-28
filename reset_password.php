<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo_meta.php';

$pdo = getDB();

/** Look up the (active) user this raw token currently belongs to, if not expired. */
function userForResetToken(PDO $pdo, string $token): ?array
{
    $token = trim($token);
    if ($token === '' || !ctype_xdigit($token)) {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT user_id, full_name FROM users
         WHERE reset_token_hash = ? AND reset_token_expires_at > NOW() AND is_active = 1
         LIMIT 1"
    );
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

$token = (string) ($_REQUEST['token'] ?? '');
$error = '';
$user  = userForResetToken($pdo, $token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass    = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if (!$user) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    } elseif (!isStrongPassword($pass)) {
        $error = STRONG_PASSWORD_MSG;
    } elseif ($pass !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {
        // The user set their own password via a verified e-mail token, so also
        // clear any pending "must change password" flag (nothing left to force).
        $pdo->prepare(
            "UPDATE users
                SET password = ?, must_change_password = 0,
                    reset_token_hash = NULL, reset_token_expires_at = NULL
              WHERE user_id = ?"
        )->execute([password_hash($pass, PASSWORD_DEFAULT), (int) $user['user_id']]);

        header('Location: /dgt_dental_clinic/login?reset=1', true, 302);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password &middot; DGT One Dental Center - Bulacan</title>
<?php seoMeta('Reset Password | DGT One Dental Center - Bulacan', 'Set a new password for your DGT One Dental Center - Bulacan account.', 'reset_password', false); ?>
<link rel="icon" type="image/x-icon" href="<?= e(asset('assets/img/favicon/favicon.ico')) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('assets/img/favicon/favicon-32x32.png')) ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= e(asset('assets/img/favicon/favicon-16x16.png')) ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('assets/img/favicon/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(asset('assets/img/favicon/site.webmanifest')) ?>">
<meta name="theme-color" content="#102f51">
<script>
(function () {
    try {
        var stored = localStorage.getItem('dgt_theme');
        var dark = stored === 'dark' || (stored !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches);
        if (dark) document.documentElement.classList.add('dark');
    } catch (e) {}
})();
</script>
<?php require __DIR__ . '/includes/tailwind.php'; ?>
<?php require __DIR__ . '/includes/auth_style.php'; ?>
</head>
<?php
$fieldCls = AUTH_INPUT;
$btnCls   = AUTH_BTN;
?>
<body class="<?= AUTH_BODY ?>">
    <?= AUTH_SQUARES ?>
    <div class="<?= AUTH_CARD ?>">
        <div class="mb-7 flex justify-center">
            <img src="<?= e(asset('assets/img/pic_login-sm.webp')) ?>" alt="DGT One Dental Center"
                 class="h-16 w-auto max-w-[260px] object-contain">
        </div>

        <h1 class="mb-3.5 text-center text-xl font-bold text-navy-900 dark:text-steel-50">Reset Password</h1>

        <?php if ($error !== ''): ?>
            <div class="mb-3.5 rounded-md bg-[#fbe6e4] px-3.5 py-2.5 text-[13px] text-danger dark:bg-danger/20"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (!$user && $error === ''): ?>
            <div class="mb-3.5 rounded-md bg-[#fbe6e4] px-3.5 py-2.5 text-[13px] text-danger dark:bg-danger/20">
                This reset link is invalid or has expired. Reset links are valid for 15 minutes
                and can be used only once.
            </div>
            <a href="<?= e(asset('login')) ?>" class="<?= $btnCls ?>">Back to Sign In</a>
        <?php elseif ($user): ?>
            <form method="POST" action="reset_password" class="flex flex-col gap-3" autocomplete="off" id="rpForm">
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <p class="text-left text-[12.5px] text-steel-500 dark:text-steel-300">
                    Choose a new password for <strong class="font-bold"><?= e($user['full_name']) ?></strong>.
                </p>

                <div class="pw-field relative">
                    <input type="password" name="password" id="p1" placeholder="New Password"
                           required minlength="8" class="<?= $fieldCls ?> pr-10">
                    <?= pwToggleBtn() ?>
                </div>
                <div class="pw-field relative">
                    <input type="password" name="confirm" id="p2" placeholder="Confirm Password"
                           required minlength="8" class="<?= $fieldCls ?> pr-10">
                    <?= pwToggleBtn() ?>
                </div>
                <p class="hidden text-left text-[12.5px] text-danger" id="matchMsg">The two passwords do not match.</p>

                <button type="submit" class="<?= $btnCls ?>" id="rpBtn">Update Password</button>
            </form>
        <?php endif; ?>
    </div>

    <script src="<?= e(asset('assets/js/password-strength.js')) ?>"></script>
    <script>
        (function () {
            var form = document.getElementById('rpForm');
            if (!form) return;
            var p1 = document.getElementById('p1');
            var p2 = document.getElementById('p2');
            window.attachPwStrength(p1);
            var msg = document.getElementById('matchMsg');
            window.wirePwMatch(p1, p2, msg);
            var btn = document.getElementById('rpBtn');

            function check() {
                var bad = p2.value !== '' && p1.value !== p2.value;
                msg.classList.toggle('hidden', !bad);
                btn.disabled = bad || !window.isStrongPassword(p1.value) || p2.value === '';
            }
            form.addEventListener('input', check);
            check();
        })();
    </script>
    <script src="<?= e(asset('assets/js/password-toggle.js')) ?>"></script>
</body>
</html>
