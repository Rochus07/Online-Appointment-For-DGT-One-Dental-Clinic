<?php
/**
 * Shared <head> tags for the public pages (2026-10-11): meta description,
 * canonical URL, robots, and the Open Graph / Twitter link-preview tags, all
 * using the branded 1200x630 assets/img/og-image.jpg. index.php has its own
 * copy of the same tags. Pages that only make sense mid-flow (verify,
 * reset password) pass $index = false so search engines skip them.
 */
declare(strict_types=1);

function seoMeta(string $title, string $desc, string $path, bool $index = true): void
{
    $origin = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $url   = $origin . '/dgt_dental_clinic/' . ltrim($path, '/');
    $image = $origin . asset('assets/img/og-image.jpg');
    ?>
<meta name="description" content="<?= e($desc) ?>">
<meta name="robots" content="<?= $index ? 'index, follow' : 'noindex, follow' ?>">
<link rel="canonical" href="<?= e($url) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="DGT One Dental Center - Bulacan">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:image" content="<?= e($image) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="DGT One Dental Center - Bulacan">
<meta property="og:url" content="<?= e($url) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($title) ?>">
<meta name="twitter:description" content="<?= e($desc) ?>">
<meta name="twitter:image" content="<?= e($image) ?>">
<?php
}
