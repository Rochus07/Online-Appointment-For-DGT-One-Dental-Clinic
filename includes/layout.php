<?php
/**
 * Page shell. A module page sets $me, $PAGE_TITLE, $ACTIVE, $PAGE_CONTENT
 * (and optionally $PAGE_SCRIPT) then require()s this file.
 *
 * Full request  -> renders sidebar + header + content.
 * Partial (AJAX from nav.js) -> renders only the content fragment + script.
 */
declare(strict_types=1);

$PAGE_TITLE   = $PAGE_TITLE   ?? 'Dashboard';
$PAGE_CONTENT = $PAGE_CONTENT ?? '';
$PAGE_SCRIPT  = $PAGE_SCRIPT  ?? '';
$ACTIVE       = $ACTIVE       ?? '';
$PAGE_WIDE    = $PAGE_WIDE    ?? false;   // opt-in wider content column (data-heavy tables)
$PAGE_FULL    = $PAGE_FULL    ?? false;   // opt-in full-bleed column (admin screens) — fills the viewport, no max-width
$pageClass    = 'page ' . ($PAGE_FULL
    ? 'page-wide w-full'
    : 'mx-auto ' . ($PAGE_WIDE ? 'page-wide max-w-[1560px]' : 'max-w-[1180px]'));

if (isPartialRequest()) {
    header('X-Page-Title: ' . $PAGE_TITLE);
    echo '<div class="' . $pageClass . '" data-page-title="' . e($PAGE_TITLE) . '">';
    echo $PAGE_CONTENT;
    echo '</div>';
    if ($PAGE_SCRIPT !== '') {
        echo "\n<script>" . $PAGE_SCRIPT . "</script>";
    }
    return;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($PAGE_TITLE) ?> &middot; DGT One Dental Center - Bulacan</title>
<meta name="description" content="<?= e($PAGE_TITLE) ?> in the DGT One Dental Center - Bulacan clinic portal: appointments, patient records and billing.">
<link rel="canonical" href="<?= e((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . strtok($_SERVER['REQUEST_URI'] ?? '/', '?')) ?>">
<link rel="icon" type="image/x-icon" href="<?= e(asset('assets/img/favicon/favicon.ico')) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('assets/img/favicon/favicon-32x32.png')) ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= e(asset('assets/img/favicon/favicon-16x16.png')) ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('assets/img/favicon/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(asset('assets/img/favicon/site.webmanifest')) ?>">
<meta name="theme-color" content="#102f51">
<script>window.SUNDAY_OPEN = <?= sundayOpen() ? 'true' : 'false' ?>; window.SLOT_CAP = <?= (int) SLOT_CAPACITY ?>;</script>
<script>
(function () {
    // Applied before <body> paints so there is never a flash of the light
    // theme on load — mirrors the sidebar-collapse pre-paint script below.
    // Explicit choice wins; otherwise fall back to the OS preference.
    try {
        var stored = localStorage.getItem('dgt_theme');
        var m = (stored === 'light' || stored === 'dark' || stored === 'system') ? stored : 'system';
        var dark = m === 'dark' || (m === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
        if (dark) document.documentElement.classList.add('dark');
        document.documentElement.setAttribute('data-theme-mode', m);
    } catch (e) {}
})();
</script>
<script>
(function () {
    // Grow the whole app with the screen past a 1366px laptop (see tailwind.php).
    function uiz() {
        var z = Math.min(1.25, Math.max(1, 1 + (window.innerWidth - 1366) * 0.15 / 554));   // 1920px = 1.15, max 1.25
        window.uiZoom = z;
        document.documentElement.style.setProperty('--uiz', String(z));
    }
    uiz();
    window.addEventListener('resize', uiz);
})();
</script>
<?php require __DIR__ . '/tailwind.php'; ?>
</head>
<body class="bg-canvas font-sans text-ink antialiased dark:bg-dgt-ink-900 dark:text-dgt-ink-100" data-user-id="<?= (int) ($me['user_id'] ?? 0) ?>" data-role="<?= e((string) ($me['role'] ?? '')) ?>">
<script>document.body.classList.toggle('sb-collapsed', localStorage.getItem('dgt_sidebar_collapsed') === '1');</script>
<div class="flex min-h-screen">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div id="sbBackdrop" class="fixed inset-0 z-[39] bg-navy-900/40 print:hidden" aria-hidden="true"></div>
    <div class="flex min-w-0 flex-1 flex-col nav:ml-[244px] print:ml-0" id="appShellContent">
        <?php require __DIR__ . '/header.php'; ?>
        <main class="min-w-0 flex-1 px-4 pb-[60px] pt-5 sm:px-5 lg:px-7 lg:pt-[26px] print:p-0" id="app-content">
            <div class="<?= $pageClass ?>" data-page-title="<?= e($PAGE_TITLE) ?>">
                <?= $PAGE_CONTENT ?>
            </div>
        </main>
    </div>
</div>
<div class="modal-root fixed inset-0 z-[100] overflow-y-auto" id="modalRoot" hidden></div>
<div class="toast-root fixed bottom-[18px] right-[18px] z-[200] flex flex-col gap-2.5" id="toastRoot" aria-live="polite" aria-atomic="false"></div>

<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
<script src="<?= e(asset('assets/js/charts.js')) ?>"></script>
<script src="<?= e(asset('assets/js/password-toggle.js')) ?>"></script>
<script src="<?= e(asset('assets/js/password-strength.js')) ?>"></script>
<script src="<?= e(asset('assets/js/theme-menu.js')) ?>"></script>
<script src="<?= e(asset('assets/js/nav.js')) ?>"></script>
<?php if ($PAGE_SCRIPT !== ''): ?>
<script><?= $PAGE_SCRIPT ?></script>
<?php endif; ?>
</body>
</html>
