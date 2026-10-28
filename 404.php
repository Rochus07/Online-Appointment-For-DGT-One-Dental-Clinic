<?php
declare(strict_types=1);

/**
 * Custom 404 (Not Found) page, wired via .htaccess `ErrorDocument 404`.
 * Standalone — no layout.php (a broken URL may not carry a valid session/role
 * context) — but still DGT-styled to match the rest of the site.
 */

require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/functions.php';

http_response_code(404);

$me       = currentUser();
$homeUrl  = $me ? roleHome($me['role']) : '/dgt_dental_clinic/home';
$homeLbl  = $me ? 'Back to Dashboard' : 'Back to Home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,follow">
<title>Page Not Found &middot; DGT One Dental Center - Bulacan</title>
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
</head>
<body class="flex min-h-screen flex-col items-center justify-center bg-dgt-ink-50 px-5 py-10 font-sans text-dgt-ink-700 antialiased dark:bg-dgt-ink-900 dark:text-dgt-ink-100">

    <a href="/dgt_dental_clinic/home" class="mb-10 flex items-center gap-2.5">
        <img src="<?= e(asset('assets/img/dgt_logo.jpg')) ?>" alt="DGT One Dental Center"
             class="h-10 w-10 rounded-full object-cover ring-1 ring-dgt-ink-200 dark:ring-white/15">
        <span class="text-[15px] font-bold text-dgt-blue-900 dark:text-dgt-ink-50">DGT One Dental Center</span>
    </a>

    <div class="grid h-20 w-20 place-items-center rounded-full bg-dgt-blue-50 text-dgt-blue-700 dark:bg-dgt-blue-500/20 dark:text-dgt-blue-300">
        <svg viewBox="0 0 24 24" class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 5.5c-1.8-2.4-5.6-2.2-6.7.7-1 2.7.4 4.8.9 7.6.4 2.3.2 5.7 1.9 5.7 1.5 0 1.4-2.8 1.9-4.7.3-1.1.8-1.1 1.1 0 .5 1.9.4 4.7 1.9 4.7 1.7 0 1.5-3.4 1.9-5.7.5-2.8 1.9-4.9.9-7.6-1.1-2.9-4.9-3.1-6.7-.7Z"/>
        </svg>
    </div>

    <div class="mt-6 text-center">
        <p class="text-[12px] font-semibold uppercase tracking-[0.22em] text-dgt-teal-600">Error 404</p>
        <h1 class="mt-2 text-[32px] font-bold leading-tight text-dgt-blue-900 dark:text-dgt-ink-50 sm:text-[38px]">Page not found</h1>
        <p class="mx-auto mt-3 max-w-[420px] text-[14px] leading-relaxed text-dgt-ink-500 dark:text-dgt-ink-300">
            The page you're looking for doesn't exist, may have been moved, or the link is out of date.
        </p>
    </div>

    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        <a href="<?= e($homeUrl) ?>"
           class="inline-flex items-center justify-center gap-1.5 rounded-md bg-dgt-teal-600 px-6 py-3 text-[13px] font-semibold text-white hover:bg-dgt-teal-700"><?= e($homeLbl) ?></a>
        <button type="button" onclick="history.back()"
                class="inline-flex items-center justify-center gap-1.5 rounded-md border border-dgt-ink-200 bg-white px-6 py-3 text-[13px] font-semibold text-dgt-blue-800 hover:bg-dgt-ink-50 dark:border-white/15 dark:bg-dgt-ink-800 dark:text-dgt-ink-100 dark:hover:bg-white/5">Go Back</button>
    </div>

</body>
</html>
