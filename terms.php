<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo_meta.php';

require_once __DIR__ . '/includes/legal.php';

// privacy.php sets $LEGAL_DOC = 'privacy' and includes this file.
$LEGAL_DOC   = (isset($LEGAL_DOC) && isset(LEGAL_DOCS[$LEGAL_DOC])) ? $LEGAL_DOC : 'terms';
$LEGAL_TITLE = LEGAL_DOCS[$LEGAL_DOC];
$legal       = legalDoc($LEGAL_DOC);
$TERMS_HTML  = renderLegal($legal['body']);
$updatedOn   = $legal['updated_at'] ? date('F j, Y', strtotime($legal['updated_at'])) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($LEGAL_TITLE) ?> &middot; DGT One Dental Center - Bulacan</title>
<?php seoMeta($LEGAL_TITLE . ' | DGT One Dental Center - Bulacan', $LEGAL_DOC === 'privacy'
    ? 'How DGT One Dental Center - Bulacan collects, uses and protects your personal and health information, in line with the Data Privacy Act of 2012 (RA 10173).'
    : 'The terms for booking and using DGT One Dental Center - Bulacan online, including reservation fees, rescheduling, cancellations and payment rules.', $LEGAL_DOC === 'privacy' ? 'privacy' : 'terms'); ?>
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
<body class="bg-white text-navy-900 dark:bg-dgt-ink-900 dark:text-steel-50">
    <?= AUTH_SQUARES ?>
    <main class="mx-auto w-full max-w-[820px] bg-white px-6 pb-16 pt-12 dark:bg-dgt-ink-900">
        <div class="mb-6 flex justify-center">
            <a href="/dgt_dental_clinic/home">
                <img src="<?= e(asset('assets/img/dgt_logo.jpg')) ?>" alt="DGT One Dental Center - Bulacan"
                     class="h-[72px] w-[72px] rounded-full bg-white object-contain p-1 shadow-[0_2px_12px_rgba(15,28,46,0.18)]">
            </a>
        </div>

        <h1 class="text-center text-[26px] font-bold uppercase tracking-wide text-navy-900 dark:text-steel-50">DGT One Dental Center<br><?= e($LEGAL_TITLE) ?></h1>
        <hr class="my-8 border-steel-200 dark:border-white/15">
        <?php if ($updatedOn): ?>
            <p class="mb-4 text-left text-[13px] text-steel-500 dark:text-steel-400">Last updated: <?= e($updatedOn) ?></p>
        <?php endif; ?>

        <div class="<?= TC_BODY_CLASSES ?> !text-[15px] !leading-7 [&_h3]:!mt-8 [&_h3]:!text-[17px]"><?= $TERMS_HTML ?></div>
        <p class="mt-6 text-center text-[13px] text-steel-500 dark:text-steel-400">
            <?php if ($LEGAL_DOC === 'terms'): ?>
                See also our <a href="<?= e(asset('privacy')) ?>" class="<?= AUTH_LINK ?> underline">Privacy Policy</a>.
            <?php else: ?>
                See also our <a href="<?= e(asset('terms')) ?>" class="<?= AUTH_LINK ?> underline">Terms and Conditions</a>.
            <?php endif; ?>
        </p>

        <hr class="my-8 border-steel-200 dark:border-white/15">
        <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-[13px]">
            <a href="/dgt_dental_clinic/login?mode=register" class="<?= AUTH_LINK ?>">Back to Sign Up</a>
            <a href="<?= e(asset('login')) ?>" class="<?= AUTH_LINK ?>">Return to sign in</a>
        </div>
    </main>
</body>
</html>
