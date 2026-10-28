<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/functions.php';

// Staff go straight to their dashboard; guests and signed-in patients see this page
// (patients get a logged-in nav with a "My Portal" link instead of the CTA button).
$me = (isLoggedIn() && currentUser() !== null) ? currentUser() : null;
if ($me !== null && $me['role'] !== 'patient') {
    header('Location: ' . roleHome($me['role']));
    exit;
}
// A patient account created with a temp password (walk-in / Create Record)
// must set their own before reaching this page too — index.php doesn't go
// through checkAuth(), so it needs its own call to the same guard.
requirePasswordChanged();
$firstName = $me ? trim(explode(' ', trim((string) $me['full_name']))[0]) : '';
$portalUrl = roleHome('patient');
$bookUrl   = $me ? $portalUrl : '/dgt_dental_clinic/login';

$CLINIC_FULL  = 'DGT One Dental Center - Bulacan';
$CLINIC_PHONE = '09764484252';
$CLINIC_EMAIL = 'customerservice@dgtonedentalph.com';
$CLINIC_ADDR  = 'B-6L-47 Delrey Bldg., Bgy. Tungkong Mangga, San Jose Del Monte, Bulacan';

$origin = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$heroImgAbs = $origin . asset('assets/img/hero-a.jpg');
$ogImageAbs = $origin . asset('assets/img/og-image.jpg');   // branded 1200x630 link preview
$metaDesc = 'DGT One Dental Center in San Jose Del Monte, Bulacan. Honest, unhurried dental care '
          . 'for the whole family: check-ups, cleaning, braces, implants, cosmetic work and more. '
          . 'Request an appointment online.';

$structuredData = json_encode([
    '@context'  => 'https://schema.org',
    '@type'     => 'Dentist',
    'name'      => $CLINIC_FULL,
    'image'     => $heroImgAbs,
    'url'       => $origin . '/dgt_dental_clinic/home',
    'telephone' => '+63' . ltrim($CLINIC_PHONE, '0'),
    'email'     => $CLINIC_EMAIL,
    'address'   => [
        '@type'           => 'PostalAddress',
        'streetAddress'   => 'B-6L-47 Delrey Bldg., Bgy. Tungkong Mangga',
        'addressLocality' => 'San Jose Del Monte',
        'addressRegion'   => 'Bulacan',
        'addressCountry'  => 'PH',
    ],
    'openingHoursSpecification' => [[
        '@type'     => 'OpeningHoursSpecification',
        'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
        'opens'     => '10:00',
        'closes'    => '17:00',
    ]],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// Live, bookable services — the admin-managed rows the patient "Our Services"
// page and the booking picker also use. Rendered as a staggered card grid.
try {
    $services = getDB()->query(
        'SELECT service_id, service_name, description, short_description, base_price, quantity_unit, duration_minutes, image
         FROM services WHERE is_active = 1 ORDER BY service_name'
    )->fetchAll();
} catch (Throwable $e) {
    $services = [];
}

$heroBg = "url('" . asset('assets/img/hero-a.jpg') . "') center/cover no-repeat,"
        . "linear-gradient(135deg,#102f51,#0d3a66)";

// Section heading — Geist bold, tight tracking, trailing period on the label.
$secH  = 'text-[clamp(2.25rem,5vw,3.6rem)] font-bold leading-[1.02] tracking-[-0.03em] text-dgt-blue-900';
$secHW = 'text-[clamp(2.25rem,5vw,3.6rem)] font-bold leading-[1.02] tracking-[-0.03em] text-white';
$eyebrow = 'text-[11px] font-semibold uppercase tracking-[0.24em] text-dgt-teal-600';
$lede  = 'text-[1.02rem] leading-[1.75] text-dgt-ink-500';

$arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

// "Meet the Team" carousel — reversed 2026-09 from an earlier explicit
// "fixed, hand-maintained roster, not DB-driven like `services`" decision, at
// the user's later direct request: the roster now comes straight from real
// staff accounts, so adding/archiving a Dentist, Receptionist, Cashier or
// Dental Assistant on the admin Users page is reflected here automatically —
// no code change needed per hire. Admin accounts are deliberately excluded
// (not a patient-facing team role). `photo` holds a path relative to the app
// root (fed through `asset()` below for its usual cache-busting), or null for
// the navy-gradient + person-icon placeholder when no avatar is on file yet
// (self-uploaded via each account's own Profile page — there is no admin-side
// avatar upload for any of these roles).
//
// A real person who covers two front-of-house jobs (e.g. reception AND
// cashiering) gets TWO SEPARATE login accounts per the clinic advisor's rule
// (one account can't hold two roles) — so the query below can return the same
// full_name twice, once per role. Those are merged into a single card with a
// combined title (e.g. "Receptionist / Cashier"), same as the section's
// original hand-written roster did for exactly this case, purely a display
// merge — the two accounts underneath stay fully separate for login/
// permissions purposes.
// Each card's popup (below) shows Degree/Specialties/Years of Experience/
// About too, straight off the same account row an admin edits on the Users
// (or Assistants) page — so updating someone's profile there updates their
// public bio here automatically, same as the name/role already did.
try {
    $staffRows = getDB()->query(
        "SELECT full_name AS name, role, doctor_type, avatar, degree, specialties, years_experience, about
         FROM users
         WHERE role IN ('dentist', 'receptionist', 'cashier') AND is_active = 1
         ORDER BY FIELD(role, 'dentist', 'receptionist', 'cashier'), (doctor_type = 'owner') DESC, full_name"
    )->fetchAll();
} catch (Throwable $e) {
    $staffRows = [];
}
try {
    $assistantRows = getDB()->query(
        'SELECT da.full_name AS name, COALESCE(u.avatar, da.avatar) AS avatar, da.years_experience, da.about
         FROM dental_assistants da
         LEFT JOIN users u ON u.user_id = da.user_id
         WHERE da.is_active = 1 ORDER BY da.full_name'
    )->fetchAll();
} catch (Throwable $e) {
    $assistantRows = [];
}
$teamByName = [];
$addTeamRole = function (string $name, string $title, ?string $avatar, ?string $degree, ?string $specialties, $years, ?string $about) use (&$teamByName): void {
    if (!isset($teamByName[$name])) {
        $teamByName[$name] = [
            'name' => $name, 'titles' => [], 'photo' => null,
            'degree' => null, 'specialties' => null, 'years' => null, 'about' => null,
        ];
    }
    $entry = &$teamByName[$name];
    $entry['titles'][] = $title;
    if ($entry['photo'] === null && $avatar) $entry['photo'] = 'uploads/avatars/' . $avatar;
    if ($entry['degree'] === null && $degree) $entry['degree'] = $degree;
    if ($entry['specialties'] === null && $specialties) $entry['specialties'] = $specialties;
    if ($entry['years'] === null && $years !== null && $years !== '') $entry['years'] = (int) $years;
    if ($entry['about'] === null && $about) $entry['about'] = $about;
    unset($entry);
};
foreach ($staffRows as $row) {
    $addTeamRole($row['name'], roleLabel($row['role']), $row['avatar'], $row['degree'], $row['specialties'], $row['years_experience'], $row['about']);
}
foreach ($assistantRows as $row) {
    $addTeamRole($row['name'], 'Assistant Dentist', $row['avatar'], null, null, $row['years_experience'], $row['about']);
}
$team = [];
foreach ($teamByName as $entry) {
    $team[] = [
        'name'        => $entry['name'],
        'title'       => implode(' / ', array_unique($entry['titles'])),
        'photo'       => $entry['photo'],
        'degree'      => $entry['degree'],
        'specialties' => $entry['specialties'] ? specialtyLabels($entry['specialties']) : '',
        'years'       => $entry['years'],
        'about'       => $entry['about'],
    ];
}
$teamFirstName = function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [$name];
    foreach ($parts as $p) {
        $clean = rtrim($p, '.,');
        if (in_array(strtolower($clean), ['dr', 'mr', 'mrs', 'ms'], true)) continue;
        return $clean;
    }
    return $parts[0];
};
// Avatar-placeholder initials (first letter of first name + first letter of
// last name) for a team member with no photo on file, e.g. "Raffy D.G.
// Tamayo" -> "RT". Same title/middle-initial skipping as $teamFirstName.
$teamInitials = function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $clean = [];
    foreach ($parts as $p) {
        $c = rtrim($p, '.,');
        if ($c === '' || in_array(strtolower($c), ['dr', 'mr', 'mrs', 'ms'], true)) continue;
        $clean[] = $c;
    }
    if (!$clean) return '';
    $first = mb_substr($clean[0], 0, 1);
    $last = count($clean) > 1 ? mb_substr($clean[count($clean) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
};

?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
<meta charset="UTF-8">
<script>
(function () {
    // Light by default; Dark / Auto only after the visitor picks one in the Appearance menu (shared with the app).
    try {
        var s = localStorage.getItem('dgt_theme');
        var m = (s === 'light' || s === 'dark' || s === 'system') ? s : 'light';
        if (m === 'dark' || (m === 'system' && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
        document.documentElement.setAttribute('data-theme-mode', m);
    } catch (e) {}
})();
</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($CLINIC_FULL) ?> | Your Complete Dental Care Partner</title>
<meta name="description" content="<?= e($metaDesc) ?>">
<link rel="canonical" href="<?= e($origin) ?>/dgt_dental_clinic/home">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($CLINIC_FULL) ?> | Your Complete Dental Care Partner">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<meta property="og:image" content="<?= e($ogImageAbs) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="<?= e($CLINIC_FULL) ?>">
<meta property="og:site_name" content="<?= e($CLINIC_FULL) ?>">
<meta property="og:url" content="<?= e($origin) ?>/dgt_dental_clinic/home">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="<?= e($ogImageAbs) ?>">
<meta name="robots" content="index, follow">
<link rel="icon" type="image/x-icon" href="<?= e(asset('assets/img/favicon/favicon.ico')) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('assets/img/favicon/favicon-32x32.png')) ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= e(asset('assets/img/favicon/favicon-16x16.png')) ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('assets/img/favicon/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(asset('assets/img/favicon/site.webmanifest')) ?>">
<meta name="theme-color" content="#102f51">
<script type="application/ld+json"><?= $structuredData ?></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700;800&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
<?php require __DIR__ . '/includes/tailwind.php'; ?>
<!-- Swiper (Meet Our Team carousel) — vendored locally, never a CDN link, per
     this project's third-party-JS convention (see the "MEET THE TEAM"
     section comment for the full rationale). -->
<link rel="stylesheet" href="<?= e(asset('assets/css/vendor/swiper-bundle.min.css')) ?>">
<style>
    /* NOTE: this section used to set `html { scrollbar-gutter: stable; }` to
       stop the Swiper "Meet Our Team" carousel redrawing/shifting when a
       modal's scroll-lock made the real scrollbar disappear (reported as
       "the card move own its own"). That fix caused a NEW, worse bug: since
       `scrollbar-gutter: stable` reserves its ~15-17px gutter permanently —
       whether or not a scrollbar is actually rendered there — that reserved
       strip is normally invisible only because a real scrollbar fills it
       (page content usually overflows); the instant a modal locks scroll and
       the real scrollbar disappears, that same reserved strip has nothing
       left to fill it and shows as a raw, uncovered rectangle of the page's
       plain background along the right edge of the viewport — because
       `position:fixed` elements (the modal's own dark backdrop included)
       size themselves against the SAME reduced containing block, so the
       backdrop can never reach far enough right to cover it either.
       Reported directly with a screenshot ("theres a white thing apper on
       right side") on the Services modal, confirmed via
       `getBoundingClientRect()`: the fixed header/backdrop stayed pinned at
       the gutter-reduced width even after the real scrollbar was gone.
       Replaced with the classic, narrower-scoped fix instead: no permanent
       CSS reservation at all — `lockBodyScroll()`/`unlockBodyScroll()`
       (below, shared by #svcModal and #teamModal) measure the *actual*
       scrollbar width only at the moment of locking and add that as
       `body.style.paddingRight` for the lock's duration, which keeps normal
       in-flow content (including Swiper) from shifting without ever
       touching any `position:fixed` element's containing block — so a
       fixed-position modal backdrop always covers the full real window,
       whether or not a scrollbar happens to be showing. */
    /* index.php runs the Geist typeface (Floria-idiom rebuild). Scoped to this
       page via the body cascade — the rest of the app keeps its system stack. */
    body.floria { font-family: 'Geist', ui-sans-serif, 'Segoe UI', Roboto, Arial, sans-serif; }
    .mono { font-family: 'Geist Mono', ui-monospace, SFMono-Regular, Menlo, monospace; }
    /* Serif-italic accent word in a heading — matches Floria's `var(--font-serif)`
       (system serif), used sparingly. */
    .ser { font-family: Georgia, 'Times New Roman', Times, serif; font-style: italic; font-weight: 400; letter-spacing: -0.01em; }

    /* Scroll-reveal (Floria movement): elements start offset + transparent and
       ease into place once, as they scroll into view. Directional variants +
       per-item stagger via inline transition-delay. Easing is Floria's
       cubic-bezier(.16,1,.3,1); ~0.8s. All disabled for reduced motion. */
    .rv, .rv-l, .rv-r, .rv-s {
        opacity: 0; will-change: transform, opacity;
        transition: opacity .8s cubic-bezier(.16,1,.3,1), transform .8s cubic-bezier(.16,1,.3,1);
    }
    .rv   { transform: translateY(38px); }          /* rise up */
    .rv-l { transform: translateX(-52px); }          /* slide in from the left */
    .rv-r { transform: translateX(52px); }           /* slide in from the right */
    .rv-s { transform: scale(.94); }                 /* scale up */
    .rv.in, .rv-l.in, .rv-r.in, .rv-s.in { opacity: 1; transform: none; }
    /* legacy alias so existing markup keeps working */
    .reveal { opacity: 0; transform: translateY(38px);
        transition: opacity .8s cubic-bezier(.16,1,.3,1), transform .8s cubic-bezier(.16,1,.3,1); }
    .reveal.in { opacity: 1; transform: none; }

    /* Pill buttons (Floria). Arrow nudges on hover. */
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: .55rem;
        height: 3.5rem; padding: 0 2rem; border-radius: 9999px; font-size: .95rem; font-weight: 500;
        letter-spacing: -0.01em; white-space: nowrap; text-decoration: none; cursor: pointer;
        transition: background-color .3s ease, color .3s ease, transform .3s ease, box-shadow .3s ease; }
    .btn svg { width: 17px; height: 17px; transition: transform .3s ease; }
    .btn:hover svg, .btn:focus-visible svg { transform: translateX(4px); }
    .btn-light { background: #fff; color: #102f51; box-shadow: 0 12px 30px rgba(0,0,0,.18); }
    .btn-light:hover, .btn-light:focus-visible { background: rgba(255,255,255,.92); transform: translateY(-2px); }
    .btn-ghost { background: transparent; color: #fff; box-shadow: inset 0 0 0 1px rgba(255,255,255,.28); }
    .btn-ghost:hover, .btn-ghost:focus-visible { background: rgba(255,255,255,.1); }
    .btn-dark { background: #102f51; color: #fff; box-shadow: 0 12px 30px rgba(16,47,81,.24); }
    .btn-dark:hover, .btn-dark:focus-visible { background: #0d3a66; transform: translateY(-2px); }
    .btn:active { transform: translateY(0) scale(.985); }

    /* Both the Services card grid and the Team carousel restore keyboard
       focus to the card that opened a modal once it closes (accessibility —
       so a keyboard user doesn't lose their place). With no focus style of
       its own, a plain <button> falls back to the browser's default focus
       ring — visible even after an ordinary mouse click closes the modal,
       which reads as a stray box glued onto the card rather than a deliberate
       UI element. The user asked for it gone outright, not just re-coloured. */
    .svc-card:focus-visible, .team-card:focus-visible { outline: none; }
    /* Same reasoning, different trigger: openSvcModal()/openTeamModal() move
       focus straight to the × button the instant the modal opens (so a
       keyboard user lands inside the dialog, not on whatever was behind it)
       — but that's a script-driven focus, not a Tab press, and the browser's
       default focus ring still renders anyway, reading as "already
       highlighted" the moment the popup appears. */
    #svcModalClose:focus-visible, #teamModalClose:focus-visible { outline: none; }

    /* Header — a 1:1 port of Floria's <Navbar>. Mount = framer-motion spring
       `y: -100 -> 0`, stiffness 100 / damping 20 (mass 1) which is critically
       damped: a fast drop that eases to rest with no bounce. The keyframe below
       samples that spring's closed-form solution y(t) = -100(1+w0 t)e^(-w0 t),
       w0 = 10 rad/s, so the curve matches, not just the feel. Then
       `transition-all duration-500` on scroll past 50px: header padding
       py-8 -> py-4, and the inner pill goes from fully transparent (`px-2 py-2`)
       to a frosted capsule (`px-6 py-3` + bg + blur + hairline + soft shadow). */
    #siteHeader {
        padding-top: 2rem; padding-bottom: 2rem;                 /* py-8 */
        animation: nav-drop .8s linear both;
        transition: padding .5s ease, opacity .25s ease, transform .25s ease;
    }
    @keyframes nav-drop {
        0%   { transform: translateY(-100px); }
        8%   { transform: translateY(-91px); }
        17%  { transform: translateY(-74px); }
        25%  { transform: translateY(-56px); }
        33%  { transform: translateY(-41px); }
        42%  { transform: translateY(-29px); }
        50%  { transform: translateY(-20px); }
        62%  { transform: translateY(-11px); }
        75%  { transform: translateY(-5px); }
        88%  { transform: translateY(-1.5px); }
        100% { transform: translateY(0); }
    }
    #siteHeader.scrolled { padding-top: 1rem; padding-bottom: 1rem; }  /* py-4 */
    /* Hidden while a service/team detail modal is open (z-70) — the fixed
       nav (z-50) would otherwise sit visibly above the modal's own translucent
       backdrop. Toggled by lockBodyScroll()/unlockBodyScroll(), the same
       shared choke point every modal open/close already goes through. */
    #siteHeader.nav-hide { opacity: 0; transform: translateY(-12px); pointer-events: none; }
    .nav-pill {
        padding: .5rem;                                          /* px-2 py-2 */
        background-color: transparent;
        border: 1px solid transparent;
        box-shadow: 0 8px 30px rgba(16,47,81,0);
        backdrop-filter: blur(0px); -webkit-backdrop-filter: blur(0px);
        transition: background-color .5s ease, border-color .5s ease,
                    box-shadow .5s ease, padding .5s ease,
                    backdrop-filter .5s ease, -webkit-backdrop-filter .5s ease;
    }
    #siteHeader.scrolled .nav-pill {
        padding: .75rem 1.5rem;                                  /* px-6 py-3 */
        background-color: rgba(16, 47, 81, .82);
        border-color: rgba(255, 255, 255, .12);
        box-shadow: 0 8px 30px rgba(16, 47, 81, .22);
        backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    }

    /* Phones: a tighter pill so the full clinic name fits beside the buttons
       (it wraps to two lines on the narrowest screens instead of "..."). */
    @media (max-width: 639.98px) {
        #siteHeader.scrolled .nav-pill { padding: .625rem .75rem .625rem 1rem; }
    }

    /* Nav link underline grows from centre. */
    .nav-link { position: relative; color: rgba(255,255,255,.72); transition: color .3s ease; }
    .nav-link::after { content: ""; position: absolute; left: 50%; bottom: -5px; width: 0; height: 1.5px;
        background: #fff; transition: width .3s ease, left .3s ease; }
    .nav-link:hover, .nav-link:focus-visible { color: #fff; }
    .nav-link:hover::after, .nav-link:focus-visible::after { width: 100%; left: 0; }

    /* Footer "Explore" / "Our Services" link underline — adapted from
       Uiverse.io by alexmaracinaru's hover-underline-animation: a full-width
       line sweeps in left-to-right on hover instead of the nav's grow-from-
       centre style above. `currentColor` so the line always matches
       whichever text tone is currently showing (dim white at rest, full
       white on hover, per $footLink's own `hover:text-white`) rather than a
       hardcoded colour. */
    .footer-link-underline { position: relative; display: inline-block; padding-bottom: 1px; }
    .footer-link-underline::after {
        content: ""; position: absolute; left: 0; bottom: -1px; width: 100%; height: 1px;
        background-color: currentColor; transform: scaleX(0); transform-origin: right;
        transition: transform .25s ease-out;
    }
    .footer-link-underline:hover::after, .footer-link-underline:focus-visible::after { transform: scaleX(1); transform-origin: left; }

    /* Hero photo — the moody dental still-life is the hero visual (Floria's
       accent role). A very slow autonomous Ken Burns owns `transform`; the
       interactive bit is Floria's hero-accent hover — `whileHover
       { filter: "brightness(.9) contrast(1.1)" }` — moved onto the photo:
       hovering the hero brightens + firms it (rest is held slightly dark for
       mood). No pointer tracking. */
    .hero-bg { position: absolute; inset: 0; z-index: 0;
        background-size: cover !important; background-position: center 42% !important;
        filter: brightness(.88) contrast(1.02);
        animation: hero-kb 32s ease-in-out infinite alternate; will-change: transform, filter;
        transition: filter .8s cubic-bezier(.16,1,.3,1); }
    @keyframes hero-kb { from { transform: scale(1.03); } to { transform: scale(1.09); } }
    #home:hover .hero-bg, #home:focus-within .hero-bg { filter: brightness(1.08) contrast(1.09); }

    /* Service cards — a static 2-col grid (header row + hairline + photo per
       card); clicking one opens #svcModal with the full description + a Book
       Appointment CTA. Fade/scale transition on the modal itself. */
    #svcModal { transition: opacity .25s ease; opacity: 0; }
    #svcModal.is-in { opacity: 1; }
    #svcModalCard { transition: transform .3s cubic-bezier(.16,1,.3,1), opacity .3s ease; transform: scale(.96) translateY(10px); opacity: 0; }
    #svcModal.is-in #svcModalCard { transform: scale(1) translateY(0); opacity: 1; }
    /* Team member modal — same fade/scale pattern as #svcModal above. */
    #teamModal { transition: opacity .25s ease; opacity: 0; }
    #teamModal.is-in { opacity: 1; }
    #teamModalCard { transition: transform .3s cubic-bezier(.16,1,.3,1), opacity .3s ease; transform: scale(.96) translateY(10px); opacity: 0; }
    #teamModal.is-in #teamModalCard { transform: scale(1) translateY(0); opacity: 1; }


    /* Social dock (2026-10-11), footer bottom bar.
       Each button = .soc-btn (lift, label pill) holding .soc-face, a circle
       with overflow:hidden. .soc-face::before is the brand-colour layer
       parked below the circle at translateY(101%) and slid up to 0 on hover,
       so the colour rises from the bottom and is clipped by the round edge.
       The label pill sits outside .soc-face, so the clipping never cuts it.
       Timing knobs on .soc-btn:
         --soc-fill  the colour rise   cubic-bezier(.4,0,.2,1), .45s
         --soc-pop   the label pill    cubic-bezier(.34,1.56,.64,1) (overshoots
                     ~10% then settles; raise 1.56 for more bounce)
         --soc-lift  icon lift / scale
       --soc-bg is the fill (colour or gradient), --soc the solid brand colour
       for the glow, the pill and its pointer. Instagram keeps its real
       gradient (asked for explicitly). TikTok is near-black with the cyan /
       magenta split glow on the icon and ring. */
    .soc-dock { display: flex; gap: 16px; list-style: none; margin: 0; padding: 0; }
    .soc-btn {
        --soc-fill: .45s cubic-bezier(.4,0,.2,1);
        --soc-pop:  .42s cubic-bezier(.34,1.56,.64,1);
        --soc-lift: .32s cubic-bezier(.34,1.56,.64,1);
        position: relative; display: block; width: 56px; height: 56px; border-radius: 9999px;
        transition: transform var(--soc-lift), box-shadow var(--soc-fill);
    }
    .soc-face {
        position: relative; display: grid; place-items: center; width: 100%; height: 100%;
        border-radius: inherit; overflow: hidden; isolation: isolate;
        color: rgba(255,255,255,.72);
        /* The resting outline is an inset shadow, not a border: the rising
           ::before is painted over it, so a filled circle has no seam or ring
           at its edge (a real border sits outside the clip and showed one). */
        box-shadow: inset 0 0 0 1.5px rgba(255,255,255,.28);
        background: rgba(255,255,255,.06);
        -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);
        transition: color var(--soc-fill);
    }
    .soc-face::before {
        content: ''; position: absolute; inset: 0; z-index: -1;
        background: var(--soc-bg);
        transform: translateY(101%);
        transition: transform var(--soc-fill);
    }
    .soc-face svg { width: 24px; height: 24px; transition: transform var(--soc-lift), filter var(--soc-fill); }
    .soc-tip {
        position: absolute; bottom: calc(100% + 10px); left: 50%; z-index: 2;
        padding: 4px 10px; border-radius: 8px; white-space: nowrap;
        background: var(--soc-bg); color: #fff;
        font-size: 13px; font-weight: 600; letter-spacing: .01em;
        box-shadow: 0 6px 16px rgba(0,0,0,.28);
        opacity: 0; pointer-events: none;
        transform: translate(-50%, 8px) scale(.6); transform-origin: 50% 100%;
        transition: opacity .2s ease, transform var(--soc-pop);
    }
    .soc-tip::after {    /* little pointer under the pill */
        content: ''; position: absolute; top: 100%; left: 50%; margin-left: -5px;
        border: 5px solid transparent; border-top-color: var(--soc);
    }
    .soc-btn:hover, .soc-btn:focus-visible { transform: translateY(-3px); box-shadow: 0 10px 22px -8px var(--soc); }
    .soc-btn:focus-visible { outline: 2px solid #fff; outline-offset: 3px; }
    .soc-btn:hover .soc-face, .soc-btn:focus-visible .soc-face { color: #fff; }
    .soc-btn:hover .soc-face::before, .soc-btn:focus-visible .soc-face::before { transform: translateY(0); }
    .soc-btn:hover svg, .soc-btn:focus-visible svg { transform: scale(1.12); }
    .soc-btn:hover .soc-tip, .soc-btn:focus-visible .soc-tip { opacity: 1; transform: translate(-50%, 0) scale(1); }
    .soc-btn:active { transform: translateY(-1px) scale(.96); }
    /* TikTok: dark fill and a soft cyan / magenta glow underneath; plain white icon. */
    .soc-tiktok:hover, .soc-tiktok:focus-visible {    /* soft glow under the circle, no side crescents */
        box-shadow: 0 12px 24px -10px rgba(0,242,254,.5), 0 12px 24px -10px rgba(254,9,121,.5);
    }
    @media (max-width: 639px) {
        .soc-dock { gap: 14px; }
        .soc-btn { width: 50px; height: 50px; }
        .soc-face svg { width: 22px; height: 22px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .soc-btn, .soc-face, .soc-face::before, .soc-face svg, .soc-tip { transition: opacity .15s ease, color .15s ease; }
        .soc-btn:hover, .soc-btn:focus-visible, .soc-btn:hover svg, .soc-btn:focus-visible svg { transform: none; }
    }

    /* From 768px up the back-to-top button floats above the footer's bottom
       line while it is on screen (body.foot-on, set in the page script). */
    @media (min-width: 768px) { body.foot-on #toTop { bottom: 5.5rem; } }

    /* Dark-section step number badge. */
    .step-badge { font-variant-numeric: tabular-nums; }

    /* Floating "Back to Top" — Uiverse.io/vinodjangid07, recoloured to the DGT
       palette (navy pill, white double border + glyph, no gradient). A 50px
       icon square that widens to a labelled pill on hover; the arrow slides up
       and out while "Back to Top" rises in. Fades in past ~one viewport of
       scroll (JS toggles `.show`). */
    #toTop {
        position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 60;
        width: 50px; height: 50px;
        display: flex; align-items: center; justify-content: center;
        background-color: #102f51;                       /* dgt-blue-900 */
        border: 1px solid rgba(255, 255, 255, .28);
        border-radius: 15px; cursor: pointer; overflow: hidden;
        opacity: 0; visibility: hidden; transform: translateY(8px);
        box-shadow: 0 8px 24px rgba(16, 47, 81, .28);
        transition: opacity .3s ease, visibility .3s ease, transform .3s ease,
                    width .3s ease, background-color .3s ease, bottom .3s ease;
    }
    #toTop.show { opacity: 1; visibility: visible; transform: translateY(0); }
    #toTop svg { width: 12px; transition: transform .3s ease; }
    #toTop svg path { fill: #eef6f9; }
    #toTop::before {
        position: absolute; bottom: -20px; content: "Back to Top";
        color: #eef6f9; font-size: 0; font-weight: 600; white-space: nowrap;
        letter-spacing: .01em;
    }
    /* Expand only on devices that truly hover: on phones/iPads a tap leaves
       :hover stuck, so the pill stayed open instead of going back to the arrow. */
    @media (hover: hover) and (pointer: fine) {
        #toTop:hover { width: 140px; background-color: #0086a0; }  /* dgt-teal-600 */
        #toTop:hover svg { transform: translateY(-200%); }
        #toTop:hover::before { bottom: unset; font-size: 15px; transition: font-size .3s ease; }
    }
    /* Touch screens: a tap opens the pill briefly (.tapped, set by the click
       handler), then it closes back to the arrow on its own. */
    #toTop.tapped { width: 140px; background-color: #0086a0; }
    #toTop.tapped svg { transform: translateY(-200%); }
    #toTop.tapped::before { bottom: unset; font-size: 15px; transition: font-size .3s ease; }

    /* "How it works" backdrop — no JS. The tall image is absolutely positioned
       inside the section (which is `overflow-x-clip`, so it bleeds vertically),
       and the heading column is `lg:sticky`, so as you scroll the section the
       image slides up past the pinned heading — the Floria "Architecture of
       Nature" effect, pure CSS scroll. */

    @media (prefers-reduced-motion: reduce) {
        .rv, .rv-l, .rv-r, .rv-s, .reveal,
        .rv.in, .rv-l.in, .rv-r.in, .rv-s.in, .reveal.in { opacity: 1; transform: none; transition: none; }
        #siteHeader { animation: none !important; transform: none; transition: none; }
        .nav-pill { transition: none; }
        .btn, .btn svg, .nav-link, .nav-link::after { transition: none; }
        .footer-link-underline::after { transition: none; }
        .hero-bg { animation: none !important; transform: scale(1.05) !important; transition: filter .3s ease; }
        #svcModal, #svcModalCard, #teamModal, #teamModalCard { transition: none; }
        html { scroll-behavior: auto; }
        #toTop { transition: opacity .2s ease, visibility .2s ease; }
        #toTop:hover { width: 50px; background-color: #102f51; }
        #toTop:hover svg { transform: none; }
        #toTop:hover::before { font-size: 0; }
    }

    /* ---- Dark mode (visitor-toggled, button beside Sign Up / Log In) ---- */
    .theme-btn .theme-ico { display: none; }
    html[data-theme-mode="light"] .theme-btn .theme-ico-sun,
    html[data-theme-mode="dark"] .theme-btn .theme-ico-moon,
    html[data-theme-mode="system"] .theme-btn .theme-ico-auto { display: block; }
    html.dark { color-scheme: dark; }
    html.dark body.floria { background: #0d1a2a; color: #b4c1cb; }
    html.dark #services, html.dark #about { background: #0d1a2a; }
    html.dark :is(#services, #about, #svcModalCard, #teamModalCard) .text-dgt-blue-900 { color: #e9f2fb; }
    html.dark :is(#services, #about) .ser { color: rgba(233,242,251,.75); }
    html.dark :is(#services, #about, #svcModalCard, #teamModalCard) :is(.text-dgt-ink-500, .text-dgt-ink-600, .text-dgt-ink-700) { color: #b4c1cb; }
    html.dark :is(#services, #about, #svcModalCard, #teamModalCard) .text-dgt-ink-400 { color: #8a98a4; }
    html.dark :is(#services, #about, #svcModalCard, #teamModalCard) .border-dgt-ink-200 { border-color: rgba(255,255,255,.14); }
    html.dark #about .ring-dgt-ink-200 { --tw-ring-color: rgba(255,255,255,.14); }
    html.dark #about img.shadow-\[0_40px_80px_rgba\(16\,47\,81\,0\.2\)\] { box-shadow: 0 40px 80px rgba(0,0,0,.45); }
    html.dark .svc-card { background: #132337; }
    html.dark #svcModalCard, html.dark #teamModalCard { background: #132337; }
    html.dark #svcModalClose, html.dark #teamModalClose { background: #1c3047; color: #e9f2fb; border-color: rgba(255,255,255,.18); }
    html.dark #svcModalClose:hover, html.dark #teamModalClose:hover { background: #254060; }
    html.dark .btn-dark { background: #e9f2fb; color: #102f51; box-shadow: 0 12px 30px rgba(0,0,0,.35); }
    html.dark .btn-dark:hover, html.dark .btn-dark:focus-visible { background: #fff; }
</style>
</head>
<body class="floria bg-white text-dgt-ink-700 antialiased">

<header id="siteHeader" class="fixed inset-x-0 top-0 z-50 flex justify-center px-4 md:px-8">
    <div class="nav-pill flex w-full max-w-[1240px] items-center justify-between gap-2 rounded-full sm:gap-4">
        <a href="#home" class="flex min-w-0 shrink items-center gap-2.5">
            <img src="<?= e(asset('assets/img/dgt_logo.jpg')) ?>" alt="<?= e($CLINIC_FULL) ?> logo"
                 class="h-9 w-9 shrink-0 rounded-lg bg-white object-contain p-[3px]" onerror="this.style.display='none'">
            <span class="text-[13.5px] font-bold leading-tight tracking-tight text-white sm:whitespace-nowrap sm:text-[15px]">DGT One Dental Center</span>
        </a>
        <nav class="hidden items-center gap-8 text-[13.5px] font-medium lg:flex">
            <a href="#home" class="nav-link">Home</a>
            <a href="#services" class="nav-link">Services</a>
            <a href="#process" class="nav-link">How It Works</a>
            <a href="#about" class="nav-link">About Us</a>
            <?php if ($me): ?><a href="<?= e($portalUrl) ?>" class="nav-link">My Portal</a><?php endif; ?>
        </nav>
        <?php if ($me): ?>
            <div class="flex shrink-0 items-center gap-3">
                <button type="button" id="landingThemeToggle" data-theme-menu aria-haspopup="dialog" aria-expanded="false" aria-label="Appearance" title="Appearance"
                        class="theme-btn grid h-10 w-10 shrink-0 cursor-pointer place-items-center rounded-full border border-white/25 bg-white/10 text-white transition hover:bg-white/20">
                    <svg viewBox="0 0 24 24" class="theme-ico theme-ico-sun h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    <svg viewBox="0 0 24 24" class="theme-ico theme-ico-moon h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
                    <svg viewBox="0 0 24 24" class="theme-ico theme-ico-auto h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                </button>
                <span class="hidden items-center gap-1.5 text-[13px] font-semibold text-white sm:inline-flex">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
                    <?= e($firstName) ?>
                </span>
                <a href="/dgt_dental_clinic/logout" class="hidden rounded-full bg-white px-5 py-2.5 text-[13px] font-semibold tracking-tight text-dgt-blue-900 transition hover:bg-white/90 sm:inline-block">Log out</a>
                <button type="button" id="navMenuBtn" aria-label="Open menu" aria-controls="navDrawer" aria-expanded="false"
                        class="grid h-11 w-11 shrink-0 cursor-pointer place-items-center rounded-full border border-white/25 bg-white/10 text-white transition hover:bg-white/20 lg:hidden">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        <?php else: ?>
            <div class="flex shrink-0 items-center gap-2.5">
                <button type="button" id="landingThemeToggle" data-theme-menu aria-haspopup="dialog" aria-expanded="false" aria-label="Appearance" title="Appearance"
                        class="theme-btn grid h-10 w-10 shrink-0 cursor-pointer place-items-center rounded-full border border-white/25 bg-white/10 text-white transition hover:bg-white/20">
                    <svg viewBox="0 0 24 24" class="theme-ico theme-ico-sun h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    <svg viewBox="0 0 24 24" class="theme-ico theme-ico-moon h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
                    <svg viewBox="0 0 24 24" class="theme-ico theme-ico-auto h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                </button>
                <a href="/dgt_dental_clinic/login" class="hidden shrink-0 rounded-full bg-white px-5 py-2.5 text-[13px] font-semibold tracking-tight text-dgt-blue-900 transition hover:bg-white/90 sm:inline-block md:px-6">Sign Up / Log In</a>
                <button type="button" id="navMenuBtn" aria-label="Open menu" aria-controls="navDrawer" aria-expanded="false"
                        class="grid h-11 w-11 shrink-0 cursor-pointer place-items-center rounded-full border border-white/25 bg-white/10 text-white transition hover:bg-white/20 lg:hidden">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        <?php endif; ?>
    </div>
</header>

<!-- Mobile / tablet menu (< 1024px): off-canvas drawer opened by #navMenuBtn. -->
<div id="navDrawerBackdrop" class="fixed inset-0 z-[65] bg-dgt-blue-900/50 opacity-0 transition-opacity duration-300 lg:hidden" hidden></div>
<aside id="navDrawer" role="dialog" aria-modal="true" aria-label="Menu"
       class="fixed inset-y-0 right-0 z-[66] h-[100dvh] [&:not([hidden])]:flex w-[min(320px,86vw)] translate-x-full flex-col bg-dgt-blue-900 px-5 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-5 text-white shadow-2xl transition-transform duration-300 ease-out lg:hidden" hidden>
    <div class="mb-6 flex items-center justify-between">
        <span class="text-[15px] font-bold tracking-tight">DGT One Dental Center</span>
        <button type="button" id="navDrawerClose" aria-label="Close menu"
                class="grid h-11 w-11 cursor-pointer place-items-center rounded-full border border-white/25 bg-white/10 transition hover:bg-white/20">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
    </div>
    <nav class="flex flex-col text-[16px] font-medium [&>a]:flex [&>a]:min-h-[48px] [&>a]:items-center [&>a]:border-b [&>a]:border-white/10 [&>a]:text-white/85 [&>a:hover]:text-white">
        <a href="#home">Home</a>
        <a href="#services">Services</a>
        <a href="#process">How It Works</a>
        <a href="#about">About Us</a>
        <?php if ($me): ?><a href="<?= e($portalUrl) ?>">My Portal</a><?php endif; ?>
    </nav>
    <div class="mt-auto pt-6">
        <?php if ($me): ?>
            <p class="mb-3 text-[13px] text-white/60">Signed in as <span class="font-semibold text-white"><?= e($firstName) ?></span></p>
            <a href="/dgt_dental_clinic/logout" class="flex min-h-[48px] items-center justify-center rounded-full bg-white text-[14px] font-semibold text-dgt-blue-900">Log out</a>
        <?php else: ?>
            <a href="/dgt_dental_clinic/login" class="flex min-h-[48px] items-center justify-center rounded-full bg-white text-[14px] font-semibold text-dgt-blue-900">Sign Up / Log In</a>
        <?php endif; ?>
    </div>
</aside>

<!-- ===================== HERO ===================== -->
<section id="home" class="relative z-20 flex min-h-[100svh] w-full items-center overflow-hidden bg-dgt-blue-900 pb-16 pt-32">
    <div class="absolute inset-0 z-0 overflow-hidden">
        <div class="hero-bg opacity-[0.62]" aria-hidden="true" style="background: <?= e($heroBg) ?>;"></div>
        <!-- Navy scrim, heavy on the left where the headline sits, thin on the
             right so the lit instruments read through (Floria's from-black scrim). -->
        <div class="absolute inset-0 bg-[linear-gradient(100deg,#102f51_0%,rgba(16,47,81,0.92)_30%,rgba(16,47,81,0.55)_62%,rgba(16,47,81,0.25)_100%)]"></div>
    </div>

    <div class="pointer-events-none relative z-10 mx-auto grid w-full max-w-[1240px] grid-cols-1 items-center gap-10 px-5 md:grid-cols-12 md:px-8">
        <div class="pointer-events-auto md:col-span-9 lg:col-span-7">
            <span class="rv mb-7 inline-block rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.22em] text-white/90 backdrop-blur-sm">Bulacan Dental Clinic</span>
            <h1 class="rv text-[clamp(2.55rem,6vw,4.5rem)] font-extrabold leading-[0.98] tracking-[-0.035em] text-white [text-wrap:balance]" style="transition-delay:.08s">
                Your complete dental care <span class="ser text-white/75">partner.</span>
            </h1>
            <p class="rv mt-7 max-w-[40ch] text-[1.05rem] leading-[1.7] text-white/75 [text-wrap:balance]" style="transition-delay:.16s">
                Here for immediate assistance, emergency treatment, or a simple consultation.
            </p>
            <div class="rv mt-10 flex flex-wrap items-center gap-4" style="transition-delay:.24s">
                <a href="<?= e($bookUrl) ?>" class="btn btn-light">Book An Appointment <?= $arrow ?></a>
                <a href="#team" class="btn btn-ghost">Meet our team</a>
            </div>
        </div>
    </div>
</section>

<!-- ===================== SERVICES ===================== -->
<section id="services" class="relative z-30 scroll-mt-24 bg-white px-5 py-24 md:px-8 md:py-32">
    <div class="mx-auto max-w-[1240px]">
        <div class="rv mb-10 text-center md:mb-14">
            <h2 class="<?= $secH ?>">Our <span class="ser text-dgt-blue-900/75">services.</span></h2>
            <p class="mx-auto mt-4 max-w-[46ch] <?= $lede ?>">Everyday dentistry and specialist care under one roof, from routine checkups to advanced treatment.</p>
        </div>

        <?php if (!$services): ?>
            <p class="py-12 text-center text-dgt-ink-400">Our services will be listed here shortly.</p>
        <?php else: ?>
            <?php
            // Card blurb (short_description) and the modal's full description
            // are two independently-written fields — not one truncated into
            // the other — so the card and its popup never repeat each other.
            // Falls back to the description's first sentence only for a
            // service that hasn't had a card blurb written for it yet.
            $svcCardBlurb = function (array $s): string {
                $short = trim((string) ($s['short_description'] ?? ''));
                if ($short !== '') return $short;
                $d = trim((string) ($s['description'] ?? ''));
                $pos = strpos($d, '. ');
                return $pos === false ? $d : substr($d, 0, $pos + 1);
            };
            ?>

            <div class="rv grid grid-cols-1 gap-6 sm:grid-cols-2 md:gap-7 lg:grid-cols-3">
                <?php foreach ($services as $s): $svcImg = !empty($s['image']) ? asset('uploads/services/' . rawurlencode((string) $s['image'])) : ''; $svcBlurb = $svcCardBlurb($s);
                    // Starting (base) price only; the dentist sets the real charge.
                    $bp = (float) $s['base_price'];
                    $svcPrice = $bp > 0 ? 'Starts at ₱' . number_format($bp, fmod($bp, 1) ? 2 : 0) . (!empty($s['quantity_unit']) ? ' per ' . $s['quantity_unit'] : '') : '';
                    $svcTime = fmtDuration($s['duration_minutes'] ?? null, $s['quantity_unit'] ?? null); ?>
                    <button type="button"
                            data-svc-id="<?= (int) $s['service_id'] ?>"
                            data-name="<?= e($s['service_name']) ?>"
                            data-desc="<?= e((string) ($s['description'] ?? '')) ?>"
                            data-image="<?= e($svcImg) ?>"
                            data-price="<?= e($svcPrice) ?>"
                            data-time="<?= e($svcTime) ?>"
                            class="svc-card group scroll-mt-24 flex flex-col overflow-hidden rounded-2xl border border-dgt-ink-200 bg-white text-left transition hover:-translate-y-0.5 hover:border-dgt-teal-500/50 hover:shadow-lg">
                        <div class="flex items-start justify-between gap-4 border-b border-dgt-ink-200 p-6">
                            <div class="min-w-0">
                                <h3 class="text-[1.1rem] font-semibold tracking-tight text-dgt-blue-900"><?= e($s['service_name']) ?></h3>
                                <?php if ($svcBlurb !== ''): ?>
                                    <p class="mt-2 line-clamp-2 min-h-[3rem] text-[0.92rem] leading-[1.6] text-dgt-ink-500"><?= e($svcBlurb) ?></p>
                                <?php endif; ?>
                                <?php if ($svcPrice !== ''): ?>
                                    <p class="mt-3 text-[0.9rem] font-semibold text-dgt-teal-600"><?= e($svcPrice) ?></p>
                                <?php endif; ?>
                                <?php if ($svcTime !== ''): ?>
                                    <p class="mt-1 flex items-center gap-1.5 text-[0.84rem] text-dgt-ink-500"><svg viewBox="0 0 24 24" class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Usual time: <?= e($svcTime) ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-dgt-ink-200 text-dgt-blue-900 transition group-hover:border-dgt-teal-500 group-hover:bg-dgt-teal-500 group-hover:text-white">
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
                            </span>
                        </div>
                        <?php if ($svcImg): ?>
                            <div class="relative aspect-[16/10] w-full shrink-0 overflow-hidden">
                                <img src="<?= e($svcImg) ?>" alt="" loading="lazy"
                                     class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 motion-safe:group-hover:scale-105">
                            </div>
                        <?php else: ?>
                            <div class="flex aspect-[16/10] w-full shrink-0 items-center justify-center bg-[linear-gradient(160deg,#102f51,#0d3a66)] text-white/25">
                                <svg viewBox="0 0 24 24" class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 3c-4 4-7 7-7 10a7 7 0 0 0 14 0c0-3-3-6-7-10Z"/></svg>
                            </div>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="rv mt-14 flex flex-col items-center gap-4 border-t border-dgt-ink-200 pt-12 text-center md:flex-row md:justify-between md:text-left">
                <p class="max-w-[46ch] text-[1.02rem] leading-[1.7] text-dgt-ink-500">Book online in a few minutes, or walk in during clinic hours.</p>
                <a href="<?= e($bookUrl) ?>" class="btn btn-dark shrink-0">Book a visit <?= $arrow ?></a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Service detail modal — opened by clicking any .svc-card above. -->
<div id="svcModal" class="fixed inset-0 z-[70] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="svcModalTitle">
    <!-- Backdrop: a single flat, semi-transparent layer, no blur — this is
         a direct match of how the AMG Dental reference site's own modal
         backdrop actually works (inspected directly: it's plain Bootstrap,
         `.modal-backdrop { position:fixed; inset:0; background:#000;
         opacity:.5 }`, nothing else). No `backdrop-filter`, no extra edge
         layers — a flat colour has no per-pixel sampling behaviour, so
         there's nothing for it to get wrong at the edges the way a blurred
         layer could. It sits inside this modal's own `fixed inset-0`
         wrapper, so it always spans the true viewport regardless of the
         `lockBodyScroll()` body-width workaround below it. -->
    <div id="svcModalBackdrop" class="absolute inset-0 bg-dgt-blue-900/50"></div>
    <div id="svcModalCard" class="relative z-10 w-full max-w-[920px] rounded-[1.75rem] bg-white shadow-2xl">
        <button type="button" id="svcModalClose" aria-label="Close"
                class="absolute -right-3 -top-3 z-10 grid h-10 w-10 place-items-center rounded-full border border-dgt-ink-200 bg-white text-dgt-blue-900 shadow-md transition hover:bg-dgt-ink-100 sm:-right-4 sm:-top-4">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
        <div class="grid grid-cols-1 gap-8 p-6 sm:p-9 md:grid-cols-2 md:items-center md:gap-10">
            <div class="order-2 md:order-1">
                <h3 id="svcModalTitle" class="text-[1.8rem] font-bold leading-[1.08] tracking-tight text-dgt-blue-900 sm:text-[2.1rem]"></h3>
                <p id="svcModalDesc" class="mt-5 text-[1rem] leading-[1.75] text-dgt-ink-600"></p>
                <div id="svcModalPriceWrap" class="mt-5 hidden rounded-xl border border-dgt-ink-200 px-4 py-3">
                    <p id="svcModalPrice" class="text-[1.05rem] font-semibold text-dgt-teal-600"></p>
                    <p id="svcModalTime" class="mt-1 hidden text-[0.92rem] text-dgt-ink-600"></p>
                    <p class="mt-1 text-[0.85rem] leading-[1.6] text-dgt-ink-500">Note: Prices shown are baseline amounts (&quot;starts at&quot;). The final cost may vary based on the actual condition, severity, or additional units required as determined by the dentist during treatment.</p>
                </div>
                <a id="svcModalBook" href="<?= e($bookUrl) ?>" class="btn btn-dark mt-7 inline-flex">Book Appointment <?= $arrow ?></a>
            </div>
            <div class="relative order-1 aspect-[4/3] w-full shrink-0 overflow-hidden rounded-2xl bg-[linear-gradient(160deg,#102f51,#0d3a66)] md:order-2">
                <img id="svcModalImg" src="" alt="" class="absolute inset-0 h-full w-full scale-[1.03] rounded-2xl object-cover">
            </div>
        </div>
    </div>
</div>

<!-- ===================== HOW IT WORKS ===================== -->
<section id="process" class="relative z-20 scroll-mt-24 overflow-x-clip bg-dgt-blue-900 px-5 py-24 text-white md:px-8 md:py-40">
    <div class="pointer-events-none absolute left-1/2 top-1/2 h-[70vw] w-[70vw] -translate-x-1/2 -translate-y-1/2 rounded-full bg-dgt-teal-500/[0.07] blur-[130px]"></div>

    <!-- Backdrop image (Floria's "The Architecture of Nature" treatment): a tall
         image absolutely positioned near the top of the section. The section is
         `overflow-x-clip` so it bleeds above and below; the heading column below
         is `lg:sticky`, so scrolling the section slides this image up past the
         pinned heading — the "falling" scroll effect, pure CSS, no JS.
         The wrapper still slides in from the left as the section enters (`.rv-l`,
         like Floria's `x:-100 -> 0`). Ellipse mask + mix-blend-lighten keep the
         photo edgeless where it overlaps the white sections. -->
    <div class="rv-l pointer-events-none absolute -left-[12%] -top-[28%] z-0 hidden h-[156%] w-[64vw] max-w-[1040px] lg:block [-webkit-mask-image:radial-gradient(ellipse_78%_62%_at_20%_47%,#000_8%,transparent_84%)] [mask-image:radial-gradient(ellipse_78%_62%_at_20%_47%,#000_8%,transparent_84%)]">
        <img id="procImg" loading="lazy" decoding="async" src="<?= e(asset('assets/img/process-bg-web.jpg')) ?>" alt=""
             class="h-full w-full object-cover object-center opacity-[0.55] mix-blend-lighten">
    </div>

    <div class="relative z-10 mx-auto grid max-w-[1240px] grid-cols-1 gap-16 lg:grid-cols-2 lg:gap-24">
        <div class="rv-l self-start [text-shadow:0_2px_18px_rgba(13,26,44,0.8)] lg:sticky lg:top-36">
            <h2 class="text-[clamp(2.4rem,5.6vw,4rem)] font-bold leading-[1.0] tracking-[-0.03em] text-white">How it <span class="ser text-white/70">works.</span></h2>
            <p class="mt-6 max-w-[40ch] text-[1.05rem] leading-[1.75] text-white/60">
                From the first request to your receipt, the whole visit is three steps.
            </p>
            <a href="<?= e($bookUrl) ?>" class="btn btn-light mt-9">Start a request <?= $arrow ?></a>
        </div>

        <div class="flex flex-col gap-12 md:gap-20">
            <?php
            $steps = [
                ['01', 'Request online', 'Pick a service, choose a date and time, and settle the reservation fee. A live status screen tracks the request while the front desk reviews it.'],
                ['02', 'Visit the clinic', 'Come in on your scheduled day. The front desk checks you in, and your dentist goes over your concern before any treatment begins.'],
                ['03', 'Treatment and billing', 'Your dentist carries out and records the work. The cashier prepares the invoice, credits the reservation fee already paid, and issues your receipt.'],
            ];
            foreach ($steps as $i => $st):
            ?>
                <div class="rv relative ml-6 border-l border-white/12 pl-9 md:pl-14 <?= $i === 1 ? 'md:ml-20' : 'lg:ml-0' ?>" style="transition-delay:<?= $i * 0.1 ?>s">
                    <span class="step-badge mono absolute -left-[22px] top-0 grid h-11 w-11 place-items-center rounded-full border border-white/12 bg-dgt-blue-800 text-[13px] text-white/55 md:-left-[26px] md:h-[52px] md:w-[52px]"><?= $st[0] ?></span>
                    <h3 class="text-[1.4rem] font-medium tracking-tight text-white md:text-[1.7rem]"><?= e($st[1]) ?></h3>
                    <p class="mt-4 max-w-[45ch] text-[0.98rem] leading-[1.7] text-white/55"><?= e($st[2]) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===================== ABOUT ===================== -->
<section id="about" class="relative z-30 scroll-mt-24 bg-white px-5 py-24 md:px-8 md:py-36">
    <div class="mx-auto grid max-w-[1240px] grid-cols-1 items-center gap-14 md:grid-cols-2 md:gap-20">
        <div class="rv-l order-2 md:order-1">
            <h2 class="<?= $secH ?>">About <span class="ser text-dgt-blue-900/75">us.</span></h2>
            <p class="mt-6 <?= $lede ?>">
                DGT One Dental Center was established by Maria Theresa G. Tamayo, DMD
                and Raffy D.G. Tamayo, DMD, both driven by a
                vision to provide accessible, high-quality oral healthcare. Beginning as a focused dental practice, the
                clinic steadily built its reputation on modern treatment standards, ethical practice, and transparent
                patient communication.
            </p>
            <p class="mt-4 <?= $lede ?>">
                To keep pace with growing patient demand and advancements in dental technology, the clinic updated its
                clinical equipment and broadened its services, expanding beyond general dentistry into specialized
                fields such as orthodontics, pedodontics, endodontics, prosthodontics, and dental implants. Recognizing
                the need for wider coverage across Metro Manila and nearby provinces, DGT One formally expanded into a
                multi-branch network with locations strategically set up in Marikina, Cainta, Quezon City, Cavite, and
                San Jose Del Monte, Bulacan. In 2022, the practice formalized its growing network by officially
                incorporating under the Securities and Exchange Commission (SEC) as DGT ONE DENTAL CENTER CO. Guided by
                its founders and supported by a team of highly equipped associate dentists and specialists, DGT One
                Dental Center continues to dedicate itself to delivering reliable, patient-centered dental care today,
                tomorrow, and in the future.
            </p>
            <dl class="mt-9 grid grid-cols-3 gap-6 border-t border-dgt-ink-200 pt-7">
                <?php
                $facts = [
                    ['Hours', 'Mon to Sat, 10AM to 5PM'],
                    ['Location', 'San Jose Del Monte, Bulacan'],
                    ['Booking', 'Online · Walk-ins'],
                ];
                foreach ($facts as $f):
                ?>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.16em] text-dgt-ink-400"><?= e($f[0]) ?></dt>
                        <dd class="mt-2 text-[13.5px] leading-snug text-dgt-ink-600"><?= e($f[1]) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>

        <div class="rv-r relative order-1 md:order-2" style="transition-delay:.12s">
            <span class="pointer-events-none absolute -bottom-6 -right-6 hidden h-2/3 w-2/3 rounded-[2rem] bg-dgt-blue-500/12 md:block"></span>
            <img loading="lazy" decoding="async" src="<?= e(asset('assets/img/dgtbulacanbranch-web.jpg')) ?>" alt="DGT One Dental Center's Bulacan branch storefront"
                 class="relative w-full rounded-[2rem] object-cover shadow-[0_40px_80px_rgba(16,47,81,0.2)] ring-1 ring-dgt-ink-200"
                 onerror="this.parentNode.style.display='none'">
        </div>
    </div>

    <div class="mx-auto mt-24 max-w-[1240px] md:mt-32">
        <div class="grid grid-cols-1 gap-x-14 gap-y-10 md:grid-cols-3">
            <?php
            $mvw = [
                ['Our mission', 'To build long-term relationships with our patients and deliver exceptional care, directing treatment to real needs and never recommending unnecessary work. Where treatment is needed, you get a written plan with the costs first.'],
                ['Our vision', 'To be the No. 1 dental clinic in the Philippines for quality dental care and service, so the Filipino community can experience the benefits of great oral health.'],
                ['Our promise', 'Every option explained in plain terms, every precaution taken for your safety, and a front desk that keeps the day running so your time in the chair is calm.'],
            ];
            foreach ($mvw as $j => $col):
            ?>
                <div class="rv border-t border-dgt-ink-200 pt-7" style="transition-delay:<?= $j * 0.1 ?>s">
                    <span class="mono text-[12px] text-dgt-teal-600">0<?= $j + 1 ?></span>
                    <h3 class="mt-3 text-[1.25rem] font-medium tracking-tight text-dgt-blue-900"><?= e($col[0]) ?></h3>
                    <p class="mt-4 text-[0.95rem] leading-[1.8] text-dgt-ink-500"><?= e($col[1]) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===================== MEET THE TEAM ===================== -->
<!-- Restored onto Swiper.js (2026-09) after a brief static-CSS-Grid pass —
     the user wants slider navigation (arrows + pagination dots) kept for
     when the roster grows past a single row, so Swiper is back with the
     same slidesPerView breakpoints as before (1 mobile / 2 tablet / 4
     desktop) and the same "duplicate the roster when it's small enough that
     Swiper would otherwise disable looping" trick (see the PHP comment on
     `$teamRepeats` below) — none of that changed from the earlier build.

     What's different this time is the actual fix for the "clicking a card
     also slides the carousel" bug: a real mouse click is a tiny
     press-then-release, and Swiper's default `threshold: 0` means it starts
     tracking ANY pointer movement — even a stray sub-pixel of hand jitter
     between mousedown and mouseup — as the start of a drag, which could
     occasionally commit to a slide change on release even though nothing
     was meant to be dragged. `threshold: 5` in the init script below tells
     Swiper to ignore movement smaller than 5px entirely, so ordinary click
     jitter never gets far enough to register as a swipe attempt in the
     first place — confirmed against a real mousedown→jitter→mouseup
     sequence via headless Chrome (not a synthetic `.click()`, which never
     touches Swiper's pointer handling at all and so could never have caught
     this). `e.stopPropagation()` in the `.team-card` click handler is kept
     too, as requested and as cheap insurance — it stops the click from
     bubbling to anything else listening higher up, even though Swiper's own
     slide-or-not decision happens inside its own pointer handlers before
     our click listener ever runs, not from a bubbled click event. -->
<?php if (!empty($team)): ?>
<section id="team" class="relative z-30 scroll-mt-24 bg-dgt-blue-900 px-4 py-20 md:px-6 md:py-28">
    <style>
        /* Reskin Swiper's auto-generated pagination bullets to the DGT dark-
           section dot style; Swiper doesn't emit classes a Tailwind
           arbitrary variant could target for the bullet itself, so these
           custom properties + one override are the supported theming path. */
        .team-swiper {
            --swiper-pagination-color: #fff;
            --swiper-pagination-bullet-inactive-color: #fff;
            --swiper-pagination-bullet-inactive-opacity: 0.25;
            --swiper-pagination-bullet-size: 8px;
            --swiper-pagination-bullet-horizontal-gap: 4px;
        }
        /* #teamPagination is a SIBLING of #teamSwiper (both live inside the
           shared `.relative` wrapper below), not a descendant of it, so a
           `.team-swiper .swiper-pagination` descendant selector never
           actually matches — targeting the ID directly is what makes this
           override win (an ID already outranks Swiper's own two-class rule,
           no `!important` needed). */
        #teamPagination.swiper-pagination { position: relative; inset: auto; bottom: auto; left: auto; width: auto; margin-top: 2.25rem; }
        #teamPagination .swiper-pagination-bullet { transition: width .2s ease; border-radius: 9999px; }
        #teamPagination .swiper-pagination-bullet-active { width: 20px; }
        /* Swiper adds `.swiper-button-lock`/`.swiper-pagination-lock` (its
           own `display: none`) once the whole roster already fits in one
           view — looping/navigation should stay forced on regardless, so
           `watchOverflow: false` in the init script stops that lock from
           ever applying; this neutralises it either way in case a future
           Swiper version re-locks under some other condition. Our own
           `flex` Tailwind utility on these elements has equal specificity to
           Swiper's lock classes and is injected later (the Play CDN appends
           its generated <style> after everything already on the page), so
           `!important` is what makes a lock state stick if one ever did
           apply again. */
        #teamPrev.swiper-button-lock,
        #teamNext.swiper-button-lock,
        #teamPagination.swiper-pagination-lock { display: flex !important; }
        /* tailwind.php's app-wide `button:hover` box-shadow "lift" (meant
           for buttons on white cards elsewhere in the app) reads as a wash-
           out on these two translucent white circles against this section's
           own dark navy background — cancel it here rather than touching
           the global rule, which is correct everywhere else. */
        #teamPrev:hover, #teamNext:hover { box-shadow: none !important; }
    </style>
    <div class="mx-auto max-w-[1680px]">
        <div class="rv mb-12 text-center md:mb-16">
            <h2 class="<?= $secHW ?>">Meet Our Team</h2>
        </div>

        <div class="relative">
            <button type="button" id="teamPrev" aria-label="Previous team member"
                    class="absolute left-1 top-1/2 z-10 -translate-y-1/2 flex h-14 w-10 items-center justify-center rounded-xl bg-dgt-blue-900/45 text-white backdrop-blur transition hover:bg-dgt-blue-900/65 md:left-2">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
            </button>
            <button type="button" id="teamNext" aria-label="Next team member"
                    class="absolute right-1 top-1/2 z-10 -translate-y-1/2 flex h-14 w-10 items-center justify-center rounded-xl bg-dgt-blue-900/45 text-white backdrop-blur transition hover:bg-dgt-blue-900/65 md:right-2">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
            </button>

            <div id="teamSwiper" class="team-swiper swiper">
                <div class="swiper-wrapper">
                    <?php
                    // The roster is real, live staff (see the $team query
                    // above) and its size can change any time an admin adds
                    // or archives someone. Swiper's isBeginning/isEnd/
                    // allowSlideNext/allowSlidePrev state is driven purely by
                    // "does the real slide count exceed slidesPerView" — with
                    // 4 or fewer real members that's never true at the
                    // desktop breakpoint (slidesPerView:4), which disables
                    // Next/Prev outright regardless of any loop-related
                    // option. Rendering the roster TWICE as real slides once
                    // it's that small keeps navigation genuinely usable at
                    // every width; once the clinic has more than 4 team
                    // members on file the roster already out-sizes that
                    // breakpoint on its own and doesn't need padding. Only
                    // the first copy is exposed to assistive tech (screen
                    // readers hear each person once, not twice).
                    $teamRepeats = count($team) <= 4 ? 2 : 1;
                    for ($rep = 0; $rep < $teamRepeats; $rep++): $isReal = $rep === 0;
                    ?>
                        <?php foreach ($team as $t): $teamImg = !empty($t['photo']) ? asset($t['photo']) : ''; ?>
                            <div class="swiper-slide !h-auto"<?= $isReal ? '' : ' aria-hidden="true"' ?>>
                                <button type="button"<?= $isReal ? '' : ' tabindex="-1"' ?>
                                        data-team-name="<?= e($t['name']) ?>"
                                        data-team-title="<?= e($t['title']) ?>"
                                        data-team-degree="<?= e((string) $t['degree']) ?>"
                                        data-team-specialties="<?= e((string) $t['specialties']) ?>"
                                        data-team-years="<?= e($t['years'] !== null ? (string) $t['years'] : '') ?>"
                                        data-team-about="<?= e((string) $t['about']) ?>"
                                        data-team-image="<?= e($teamImg) ?>"
                                        class="team-card group block w-full cursor-pointer text-left">
                                    <div class="relative h-[280px] w-full overflow-hidden rounded-md">
                                        <?php if ($teamImg !== ''): ?>
                                            <img loading="lazy" decoding="async" src="<?= e($teamImg) ?>" alt="<?= e($isReal ? $t['name'] : '') ?>"
                                                 class="pointer-events-none absolute inset-0 h-full w-full select-none object-cover object-[50%_12%] transition duration-500 group-hover:scale-105" draggable="false">
                                        <?php else: ?>
                                            <div class="flex h-full w-full items-center justify-center bg-[linear-gradient(160deg,#102f51,#0d3a66)]">
                                                <span class="text-3xl font-semibold tracking-wide text-white/40"><?= e($teamInitials($t['name'])) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="mt-5 text-center font-semibold text-white"><?= e($teamFirstName($t['name'])) ?></h3>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endfor; ?>
                </div>
            </div>

            <div id="teamPagination" class="swiper-pagination flex items-center justify-center gap-1"></div>
        </div>
    </div>
</section>

<!-- Team member detail modal — opened by clicking any .team-card above.
     Fields are whatever that account actually has on file (Degree/
     Specialties only ever apply to a dentist; Years of Experience/About can
     apply to any role) — each stat row and the About paragraph hide
     themselves via JS when that field is empty, rather than printing a
     label next to nothing. -->
<div id="teamModal" class="fixed inset-0 z-[70] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="teamModalName">
    <!-- Same flat, blur-free AMG-style backdrop as #svcModal above. -->
    <div id="teamModalBackdrop" class="absolute inset-0 bg-dgt-blue-900/50"></div>
    <div id="teamModalCard" class="relative z-10 w-full max-w-[920px] rounded-[1.75rem] bg-white shadow-2xl">
        <button type="button" id="teamModalClose" aria-label="Close"
                class="absolute -right-3 -top-3 z-10 grid h-10 w-10 place-items-center rounded-full border border-dgt-ink-200 bg-white text-dgt-blue-900 shadow-md transition hover:bg-dgt-ink-100 sm:-right-4 sm:-top-4">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
        <div class="grid grid-cols-1 gap-8 p-6 sm:p-9 md:grid-cols-2 md:items-center md:gap-10">
            <div class="order-2 md:order-1">
                <h3 id="teamModalName" class="text-[1.8rem] font-bold leading-[1.08] tracking-tight text-dgt-blue-900 sm:text-[2.1rem]"></h3>
                <p id="teamModalTitle" class="mt-1.5 text-[13px] font-semibold uppercase tracking-[0.1em] text-dgt-teal-600"></p>
                <dl id="teamModalFacts" class="mt-6 grid grid-cols-2 gap-x-6 gap-y-4 border-t border-dgt-ink-200 pt-6">
                    <div id="teamModalDegreeRow" class="hidden">
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-dgt-ink-400">Degree</dt>
                        <dd id="teamModalDegree" class="mt-1.5 text-[13.5px] leading-snug text-dgt-ink-700"></dd>
                    </div>
                    <div id="teamModalYearsRow" class="hidden">
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-dgt-ink-400">Years of Experience</dt>
                        <dd id="teamModalYears" class="mt-1.5 text-[13.5px] leading-snug text-dgt-ink-700"></dd>
                    </div>
                    <div id="teamModalSpecialtiesRow" class="col-span-2 hidden">
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-dgt-ink-400">Specialties</dt>
                        <dd id="teamModalSpecialties" class="mt-1.5 text-[13.5px] leading-snug text-dgt-ink-700"></dd>
                    </div>
                </dl>
                <p id="teamModalAbout" class="mt-6 hidden text-[1rem] leading-[1.75] text-dgt-ink-600"></p>
            </div>
            <div class="relative order-1 flex aspect-[4/3] w-full shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-[linear-gradient(160deg,#102f51,#0d3a66)] md:order-2">
                <span id="teamModalInitials" class="text-5xl font-semibold tracking-wide text-white/40"></span>
                <img id="teamModalImg" src="" alt="" class="absolute inset-0 h-full w-full scale-[1.03] rounded-2xl object-cover object-[50%_12%]">
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===================== CLOSING CTA ===================== -->
<!-- Floria's "Join the Studio Archive" treatment: the CTA sits on a rich photo
     inside a large rounded card, darkened for legibility, content centred. No
     newsletter capture (fabricated sign-ups are a slop tell) — just the real
     one-line pitch and the single Book CTA. -->
<section id="closingCta" class="relative z-20 bg-[#16375f] px-4 py-16 sm:px-6 md:px-8 md:py-24">
    <div class="mx-auto w-full max-w-7xl">
        <div class="rv relative isolate flex w-full flex-col items-center justify-center overflow-hidden rounded-[3rem] p-12 text-center shadow-2xl md:p-24">
            <img loading="lazy" decoding="async" src="<?= e(asset('assets/img/cta-clinic.jpg')) ?>" alt="" class="absolute inset-0 -z-10 h-full w-full object-cover object-center" onerror="this.style.display='none'">
            <div class="absolute inset-0 -z-10 bg-[linear-gradient(180deg,rgba(16,47,81,0.82),rgba(13,26,44,0.9))]"></div>
            <h2 class="text-[clamp(2.4rem,6vw,4.4rem)] font-extrabold leading-[1.02] tracking-[-0.03em] text-white [text-wrap:balance]">
                Ready when you <span class="ser text-white/75">are.</span>
            </h2>
            <p class="mx-auto mt-6 max-w-[44ch] text-[1.05rem] leading-[1.7] text-white/70">
                Book online in a few minutes. The front desk confirms your slot, and your reservation fee goes toward the visit.
            </p>
            <a href="<?= e($bookUrl) ?>" class="btn btn-light mx-auto mt-10">Book An Appointment <?= $arrow ?></a>
        </div>
    </div>
</section>

<!-- ===================== FOOTER ===================== -->
<!-- Floria's footer treatment: near-full-bleed faint wordmark watermark behind,
     a big brand block + serif-italic tagline on the left (md:col-span-4), three
     dot-headed link columns on the right, a hairline bottom bar with the
     copyright + social text-links. Kept on navy `dgt-blue-900`, not near-black. -->
<?php
$footHeadCls = 'mb-4 flex items-center gap-2 text-[12.25px] font-bold uppercase tracking-[0.1em] text-white';
$footDot = '<span class="inline-block h-1.5 w-1.5 rounded-full bg-dgt-teal-400"></span>';
$footLink = 'footer-link-underline text-[15px] leading-none text-white/55 transition-colors hover:text-white';
$footQuick = [['#home', 'Home'], ['#services', 'Services'], ['#process', 'How It Works'], ['#about', 'About Us'], ['#team', 'Meet Our Team']];
if ($me) { $footQuick[] = [$portalUrl, 'My Portal']; }
?>
<footer class="relative overflow-hidden border-t border-white/5 bg-dgt-blue-900 px-4 pb-9 pt-14 text-white/70 md:px-8 md:pt-16">
    <span aria-hidden="true" class="absolute inset-x-0 top-0 h-[3px] bg-dgt-teal-500"></span>

    <!-- Footer flower on the right, vertically centred, stem bleeding off the
         right edge (Floria's `hero-accent.webp` role). `footer-flower.png` is the
         user's blue hydrangea with the baked JPG checkerboard keyed out to real
         transparency (headless-canvas pass), so it drops straight onto the navy
         — a light blur + reduced opacity keep it a backdrop, not a focal point. -->
    <div aria-hidden="true" class="pointer-events-none absolute right-[-14%] top-1/2 hidden h-[96%] max-h-[460px] -translate-y-1/2 opacity-[0.32] md:block lg:right-[-7%]">
        <img loading="lazy" decoding="async" src="<?= e(asset('assets/img/footer-flower.png')) ?>" alt="" class="h-full w-auto max-w-none rotate-[8deg] object-contain [filter:blur(2px)_saturate(1.1)]" onerror="this.style.display='none'">
    </div>

    <div class="relative z-10 mx-auto flex w-full max-w-7xl flex-col">
        <div class="mb-12 grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-12 xl:gap-8">
            <div class="flex flex-col text-white sm:col-span-2 lg:col-span-3 xl:col-span-4">
                <div class="mb-4 flex flex-col">
                    <h2 class="mb-2 text-[2.1rem] font-bold leading-[1.15] tracking-tight text-white">DGT One Dental Center</h2>
                    <p class="ser text-[1.35rem] text-dgt-teal-300 opacity-90">Your dental care partner.</p>
                </div>
                <p class="max-w-[38ch] text-[15px] leading-[1.6] text-white/45">
                    Compassionate, honest dental care for the whole family. Clear treatment plans, and no unnecessary work.
                </p>
                <!-- Social dock (2026-10-11): outlined circles; on hover / keyboard
                     focus the brand colour rises from the bottom, the icon lifts and
                     a label pill springs up above it. CSS: .soc-* in the <style>. -->
                <ul class="soc-dock mt-12" aria-label="DGT One Dental Center on social media">
                    <li><a href="https://www.facebook.com/dgtbulacan/" target="_blank" rel="noopener noreferrer" class="soc-btn" style="--soc:#1877f2;--soc-bg:#1877f2" aria-label="Facebook">
                        <span class="soc-tip" aria-hidden="true">Facebook</span>
                        <span class="soc-face" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 8h3V4.5h-3c-2.8 0-4.5 1.8-4.5 4.6V11H7v3.5h2.5V22H13v-7.5h3l.5-3.5H13V9.3c0-.8.4-1.3 1-1.3z"/></svg></span>
                    </a></li>
                    <li><a href="https://www.instagram.com/dgtonedentalcenter/" target="_blank" rel="noopener noreferrer" class="soc-btn" style="--soc:#dc2743;--soc-bg:linear-gradient(45deg,#f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%)" aria-label="Instagram">
                        <span class="soc-tip" aria-hidden="true">Instagram</span>
                        <span class="soc-face" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="0.6" fill="currentColor"/></svg></span>
                    </a></li>
                    <li><a href="https://www.tiktok.com/@dgtonedentalcenter_" target="_blank" rel="noopener noreferrer" class="soc-btn soc-tiktok" style="--soc:#010101;--soc-bg:#010101" aria-label="TikTok">
                        <span class="soc-tip" aria-hidden="true">TikTok</span>
                        <span class="soc-face" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.53.02C13.84 0 15.14.01 16.44 0c.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg></span>
                    </a></li>
                </ul>
            </div>

            <div class="xl:col-span-2 xl:col-start-5">
                <h3 class="<?= $footHeadCls ?>"><?= $footDot ?> Explore</h3>
                <ul class="flex flex-col gap-[10px]">
                    <?php foreach ($footQuick as $q): ?>
                        <li><a href="<?= e($q[0]) ?>" class="<?= $footLink ?>"><?= e($q[1]) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="xl:col-span-3">
                <h3 class="<?= $footHeadCls ?>"><?= $footDot ?> Our Services</h3>
                <?php
                // A curated short list (not all 21 services). Each label opens the
                // matching service's card + popup when that service exists; if it
                // has been renamed or archived the link just scrolls to #services.
                $footSvcIds = [];
                foreach ($services ?: [] as $fs) $footSvcIds[$fs['service_name']] = (int) $fs['service_id'];
                $footSvcPicks = [
                    'General Consultation'             => 'Consult',
                    'Teeth Cleaning (Oral Prophylaxis)' => 'Oral Prophylaxis (Cleaning)',
                    'Orthodontics / Braces'            => 'New Ortho / Braces Installation',
                    'Tooth Extraction (Bunot)'         => 'Extraction (Bunot)',
                    'Tooth Filling (Resto)'            => 'Resto (Pasta)',
                    'Dentures & Veneers'               => 'Dentures',
                ];
                ?>
                <ul class="[&>li]:mb-[10px] [&_a]:whitespace-nowrap">
                    <?php foreach ($footSvcPicks as $label => $svcName): $fsId = $footSvcIds[$svcName] ?? 0; ?>
                        <li><a href="#services"<?= $fsId ? ' data-svc-link="' . $fsId . '"' : '' ?> class="<?= $footLink ?>"><?= e($label) ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="#services" class="<?= $footLink ?> font-semibold text-white">View All Services &rarr;</a></li>
                </ul>
            </div>

            <div class="xl:col-span-3">
                <h3 class="<?= $footHeadCls ?>"><?= $footDot ?> Visit Us</h3>
                <?php
                // Small line icons in front of each contact detail (2026-10-17).
                $visitIco = static fn (string $d): string =>
                    '<svg class="mt-[3px] h-[18px] w-[18px] shrink-0 text-dgt-teal-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
                ?>
                <div class="flex flex-col gap-3 text-[15px] leading-[1.6] text-white/55">
                    <p class="flex items-start gap-3"><?= $visitIco('<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>') ?><span><?= e($CLINIC_ADDR) ?></span></p>
                    <a href="tel:<?= e($CLINIC_PHONE) ?>" class="flex items-start gap-3 transition-colors hover:text-white"><?= $visitIco('<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>') ?><span><?= e($CLINIC_PHONE) ?></span></a>
                    <a href="mailto:<?= e($CLINIC_EMAIL) ?>" class="flex items-start gap-3 transition-colors hover:text-white"><?= $visitIco('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>') ?><span class="whitespace-nowrap tracking-[-0.01em] max-[374px]:text-[13.5px]"><?= e($CLINIC_EMAIL) ?></span></a>
                    <p class="flex items-start gap-3"><?= $visitIco('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>') ?><span>Monday to Saturday, 10:00 AM to 5:00 PM. Closed Sundays.</span></p>
                </div>
            </div>
        </div>

        <div id="footBar" class="flex flex-col items-center justify-between border-t border-white/10 pt-7 text-[13.6px] text-white/40 md:flex-row">
            <p class="whitespace-nowrap text-center text-[clamp(9px,3vw,13.6px)] md:text-left md:text-[13.6px]">&copy; <?= date('Y') ?> <?= e($CLINIC_FULL) ?>. All rights reserved.</p>
            <!-- Right side, ending flush with the line above. The back-to-top
                 button floats up while this bar is on screen (body.foot-on, md+);
                 on phones mb-16 leaves it room below the links instead. -->
            <div class="mb-16 mt-3 flex gap-5 md:mb-0 md:mt-0">
                <a href="<?= e(asset('terms')) ?>" class="transition-colors hover:text-white">Terms &amp; Conditions</a>
                <a href="<?= e(asset('privacy')) ?>" class="transition-colors hover:text-white">Privacy Policy</a>
            </div>
        </div>
    </div>
</footer>

<button id="toTop" type="button" aria-label="Back to top" title="Back to top">
    <svg viewBox="0 0 384 512" aria-hidden="true"><path d="M214.6 41.4c-12.5-12.5-32.8-12.5-45.3 0l-160 160c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L160 141.2V448c0 17.7 14.3 32 32 32s32-14.3 32-32V141.2L329.4 246.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3l-160-160z"/></svg>
</button>

<script src="<?= e(asset('assets/js/vendor/swiper-bundle.min.js')) ?>"></script>
<script>
    (function () {
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // Shared modal scroll-lock — used by both #svcModal and #teamModal.
        // Replaces the old `document.body.style.overflow = 'hidden'` +
        // `html { scrollbar-gutter: stable }` combination (see the long
        // comment in the <head> <style> block for the full story of why that
        // combination itself caused a visible bug — a raw strip of page
        // background on the right whenever a modal opened). This version
        // never adds any PERMANENT CSS reservation: it pulls body out of
        // flow with `position: fixed` at the current scroll offset (so nav.js
        // never re-triggers a native scroll or layout jump).
        //
        // A LATER version of this same function set `left:0; right:0` plus a
        // matching `padding-right` (instead of the explicit `width` below) to
        // stop in-flow content (the Swiper "Meet Our Team" carousel, which
        // measures its own container width) from reflowing once the real
        // scrollbar disappears. That introduced its own, subtler version of
        // the exact same bug: with `left:0;right:0` (an auto-width box),
        // added padding shrinks body's CONTENT box rather than widening the
        // box itself, so body's children stopped ~15px short of the true
        // right edge and body's own white background showed through in that
        // gap — confirmed by locking scroll with no modal open at all and
        // sampling the rendered pixel colour there (pure white). It only ever
        // surfaced visibly on a dark section (Meet Our Team) because a
        // translucent modal backdrop blended with that white sliver, which is
        // invisible against a light section but reads as a stray strip
        // against navy. Setting an explicit pixel `width` — measured before
        // the lock changes anything — sizes body's box directly to its
        // pre-lock content width with no padding trick involved, so there is
        // no leftover box for any background to show through in the first
        // place.
        var scrollLockCount = 0;
        var scrollLockY = 0;
        function lockBodyScroll() {
            if (scrollLockCount === 0) {
                scrollLockY = window.scrollY || window.pageYOffset || 0;
                var contentWidth = document.documentElement.clientWidth;
                document.body.style.position = 'fixed';
                document.body.style.top = (-scrollLockY) + 'px';
                document.body.style.left = '0';
                document.body.style.width = contentWidth + 'px';
                var header = document.getElementById('siteHeader');
                if (header) header.classList.add('nav-hide');
            }
            scrollLockCount++;
        }
        /* Mobile / tablet drawer menu (< 1024px). */
        (function () {
            var btn = document.getElementById('navMenuBtn');
            var drawer = document.getElementById('navDrawer');
            var backdrop = document.getElementById('navDrawerBackdrop');
            if (!btn || !drawer || !backdrop) return;
            var isOpen = false;
            function open() {
                if (isOpen) return;
                isOpen = true;
                drawer.hidden = false; backdrop.hidden = false;
                lockBodyScroll();
                requestAnimationFrame(function () { requestAnimationFrame(function () {
                    drawer.classList.remove('translate-x-full'); backdrop.classList.remove('opacity-0');
                }); });
                btn.setAttribute('aria-expanded', 'true');
                document.getElementById('navDrawerClose').focus();
            }
            function close(restoreFocus) {
                if (!isOpen) return;
                isOpen = false;
                drawer.classList.add('translate-x-full'); backdrop.classList.add('opacity-0');
                btn.setAttribute('aria-expanded', 'false');
                unlockBodyScroll();
                setTimeout(function () { if (!isOpen) { drawer.hidden = true; backdrop.hidden = true; } }, reduce ? 0 : 300);
                if (restoreFocus) btn.focus();
            }
            btn.addEventListener('click', open);
            document.getElementById('navDrawerClose').addEventListener('click', function () { close(true); });
            backdrop.addEventListener('click', function () { close(true); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && isOpen) close(true); });
            // A link closes the drawer first, then the browser follows it.
            drawer.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { close(false); }); });
            // Back / Forward from the browser cache restores the page closed.
            window.addEventListener('pageshow', function (e) {
                if (!e.persisted || !isOpen) return;
                isOpen = false;
                drawer.classList.add('translate-x-full'); backdrop.classList.add('opacity-0');
                drawer.hidden = true; backdrop.hidden = true;
                btn.setAttribute('aria-expanded', 'false');
                unlockBodyScroll();
            });
            // Rotating to a width where the full navbar shows closes the drawer.
            window.matchMedia('(min-width: 1024px)').addEventListener('change', function (m) { if (m.matches) close(false); });
        })();

        function unlockBodyScroll() {
            scrollLockCount = Math.max(0, scrollLockCount - 1);
            if (scrollLockCount === 0) {
                document.body.style.position = '';
                document.body.style.top = '';
                document.body.style.left = '';
                document.body.style.width = '';
                var header = document.getElementById('siteHeader');
                if (header) header.classList.remove('nav-hide');
                // Restore the pre-lock position INSTANTLY. `<html>` carries
                // Tailwind's `scroll-smooth` class for the page's own anchor
                // links/back-to-top button, and the plain two-argument
                // `scrollTo(x, y)` form honours that CSS `scroll-behavior` —
                // so without this override, restoring the scroll position
                // itself visibly animates from wherever the browser collapsed
                // to while body was `position:fixed` (the very top) back down
                // to scrollLockY, reading as "the page auto-scrolled to home"
                // every time a modal closes. A temporary inline override (it
                // wins over the class by specificity) forces this one jump to
                // be instant without touching the page's other smooth scrolls.
                var htmlEl = document.documentElement;
                var prevScrollBehavior = htmlEl.style.scrollBehavior;
                htmlEl.style.scrollBehavior = 'auto';
                window.scrollTo(0, scrollLockY);
                htmlEl.style.scrollBehavior = prevScrollBehavior;
            }
        }

        // Header — the drop-in is a pure CSS animation (nav-drop). JS only
        // toggles `.scrolled` past 50px of scroll (Floria's threshold), which
        // `transition-all duration-500` animates on the header + inner pill.
        var h = document.getElementById('siteHeader');
        if (h) {
            var navRaf = 0;
            function syncNav() { navRaf = 0; h.classList.toggle('scrolled', (window.scrollY || 0) > 50); }
            window.addEventListener('scroll', function () { if (!navRaf) navRaf = requestAnimationFrame(syncNav); }, { passive: true });
            syncNav();
        }

        // Floating "Back to Top" — fades in past ~one viewport of scroll.
        var toTop = document.getElementById('toTop');
        if (toTop) {
            var ttRaf = 0;
            // Lift the back-to-top button above the footer's bottom line while
            // that line is on screen, so it never covers Terms / Privacy.
            (function () {
                var bar = document.getElementById('footBar');
                if (!bar || !('IntersectionObserver' in window)) return;
                new IntersectionObserver(function (en) {
                    document.body.classList.toggle('foot-on', en[0].isIntersecting);
                }).observe(bar);
            })();
            function syncToTop() { ttRaf = 0; toTop.classList.toggle('show', (window.scrollY || 0) > 600); }
            window.addEventListener('scroll', function () { if (!ttRaf) ttRaf = requestAnimationFrame(syncToTop); }, { passive: true });
            syncToTop();
            var ttTap = 0;
            toTop.addEventListener('click', function (e) {
                if (!reduce && e.pointerType !== 'mouse') {
                    toTop.classList.add('tapped');
                    clearTimeout(ttTap);
                    ttTap = setTimeout(function () { toTop.classList.remove('tapped'); }, 900);
                }
                window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
            });
        }

        // Scroll-reveal — .rv (up) / .rv-l (from left) / .rv-r (from right) /
        // .rv-s (scale up), plus the legacy .reveal. Each eases in once on entry;
        // per-item stagger comes from inline transition-delay.
        var RV = '.rv, .rv-l, .rv-r, .rv-s, .reveal';
        var els = document.querySelectorAll(RV);
        if (reduce || !('IntersectionObserver' in window)) {
            els.forEach(function (el) { el.classList.add('in'); });
        } else {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
                });
            // threshold 0 (any visible pixel), not a ratio: on a phone the
            // stacked service grid is many screens tall, so a 10% ratio was
            // never reached and the cards stayed invisible.
            }, { threshold: 0, rootMargin: '0px 0px -8% 0px' });
            els.forEach(function (el) { io.observe(el); });
            requestAnimationFrame(function () {
                document.querySelectorAll('#home ' + RV.split(', ').join(', #home ')).forEach(function (el) { el.classList.add('in'); });
            });
        }

        // "How it works" backdrop scroll effect is pure CSS (sticky heading +
        // absolutely-positioned tall image in an overflow-x-clip section) — no JS.

        // Service detail modal — click a .svc-card to open #svcModal with that
        // service's full description + a Book Appointment CTA. Plain fade/scale,
        // Escape + backdrop-click + the × button all close it, focus returns to
        // whichever card (or footer link) opened it.
        var svcModal     = document.getElementById('svcModal');
        var svcModalCard = document.getElementById('svcModalCard');
        var svcTitle     = document.getElementById('svcModalTitle');
        var svcDesc      = document.getElementById('svcModalDesc');
        var svcImg       = document.getElementById('svcModalImg');
        var svcClose     = document.getElementById('svcModalClose');
        var svcLastTrigger = null;

        function svcModalKeydown(e) {
            if (e.key === 'Escape') closeSvcModal();
        }
        function openSvcModal(trigger) {
            if (!trigger) return;
            svcLastTrigger = trigger;
            svcTitle.textContent = trigger.getAttribute('data-name') || '';
            svcDesc.textContent = trigger.getAttribute('data-desc') || '';
            var price = trigger.getAttribute('data-price') || '';
            document.getElementById('svcModalPrice').textContent = price;
            var time = trigger.getAttribute('data-time') || '';
            var timeEl = document.getElementById('svcModalTime');
            timeEl.textContent = time ? 'Usual time: ' + time : '';
            timeEl.classList.toggle('hidden', !time);
            document.getElementById('svcModalPriceWrap').classList.toggle('hidden', !price && !time);
            var img = trigger.getAttribute('data-image') || '';
            svcImg.src = img;
            svcImg.alt = trigger.getAttribute('data-name') || '';
            svcImg.style.visibility = img ? 'visible' : 'hidden';
            svcModal.classList.remove('hidden');
            svcModal.classList.add('flex');
            lockBodyScroll();
            document.addEventListener('keydown', svcModalKeydown);
            requestAnimationFrame(function () {
                svcModal.classList.add('is-in');
                svcClose.focus();
            });
        }
        function closeSvcModal() {
            if (svcModal.classList.contains('hidden')) return;
            svcModal.classList.remove('is-in');
            unlockBodyScroll();
            document.removeEventListener('keydown', svcModalKeydown);
            var finish = function () {
                svcModal.classList.add('hidden');
                svcModal.classList.remove('flex');
                if (svcLastTrigger) svcLastTrigger.focus();
            };
            if (reduce) { finish(); } else { setTimeout(finish, 260); }
        }
        document.querySelectorAll('.svc-card').forEach(function (c) {
            c.addEventListener('click', function () { openSvcModal(c); });
        });
        svcClose.addEventListener('click', closeSvcModal);
        // The backdrop is now its own layer (see #svcModalBackdrop's markup
        // comment), and it visually covers the entire "outside the card"
        // area, so it — not the outer #svcModal flex wrapper — is what a
        // backdrop click actually lands on.
        document.getElementById('svcModalBackdrop').addEventListener('click', closeSvcModal);

        // Smoothly scrolls `el` into view, then runs `cb` once the scroll has
        // actually arrived — rather than guessing a fixed delay. A fixed
        // delay here previously caused a real bug: from the footer (the
        // bottom of a long page) the smooth scroll up to #services can take
        // over a second to settle, but the modal used to open after a
        // hardcoded 420ms — freezing the page (via lockBodyScroll) mid-
        // scroll, so closing the modal restored to that same mid-scroll
        // position instead of the section's actual resting spot.
        //
        // Polling scrollY until it merely holds steady between two frames
        // (an earlier version of this fix) isn't enough on its own — right
        // after calling scrollIntoView, the browser can take a frame or two
        // before the animation actually starts moving, and scrollY reads as
        // "unchanged" in that gap too, which registered as "already
        // settled" before any scrolling had happened at all. Comparing
        // against a target position computed up front (from the element's
        // current offset, before it starts moving) avoids that false
        // positive: only a genuine arrival counts. A 1500ms backstop guards
        // against scrollY somehow never reaching it (matches this app's
        // other "longer than the expected duration" fallback timers).
        function scrollIntoViewThen(el, cb) {
            if (!el) { cb(); return; }
            if (reduce) { el.scrollIntoView({ behavior: 'auto', block: 'start' }); cb(); return; }
            var rect = el.getBoundingClientRect();
            var marginTop = parseFloat(getComputedStyle(el).scrollMarginTop) || 0;
            var maxY = document.documentElement.scrollHeight - window.innerHeight;
            var targetY = Math.max(0, Math.min(window.scrollY + rect.top - marginTop, maxY));
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            var done = false;
            function finish() { if (done) return; done = true; cb(); }
            function tick() {
                if (done) return;
                if (Math.abs(window.scrollY - targetY) < 2) { finish(); return; }
                requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
            setTimeout(finish, 1500);
        }

        // Footer "Our Services" links — scroll to that specific service's
        // own card in the grid (not just the section heading — the grid can
        // hold far more cards than fit in one screen, so landing on the
        // section top wouldn't actually show which one this link was for),
        // then open its modal.
        document.querySelectorAll('a[data-svc-link]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                var id = a.getAttribute('data-svc-link');
                var card = document.querySelector('.svc-card[data-svc-id="' + id + '"]');
                if (!card) return;
                e.preventDefault();
                scrollIntoViewThen(card, function () { openSvcModal(card); });
            });
        });

        // "Meet Our Team" carousel — Swiper.js (vendored locally, see the
        // markup comment above `#team`). `Swiper` is a global from
        // `assets/js/vendor/swiper-bundle.min.js`, loaded via a <script src>
        // just above this block (guarded here in case that ever fails to
        // load, e.g. a static-asset 404).
        //
        // The "clicking a card also slides the carousel" bug actually had
        // TWO independent causes, found by tracing Swiper's own vendored
        // source rather than guessing:
        //   1. `threshold: 5` — Swiper's default `threshold: 0` means it
        //      starts tracking ANY pointer movement as a potential drag; a
        //      plain click's natural sub-pixel hand jitter between mousedown
        //      and mouseup could register as one. threshold:5 makes it
        //      ignore movement under 5px entirely, so ordinary click jitter
        //      never reaches the drag/swipe code at all.
        //   2. `a11y: { scrollOnFocus: false }` — the bigger one. Swiper's
        //      bundled Accessibility module attaches a `focus` listener to
        //      its root element (confirmed by reading the vendored bundle's
        //      own minified source directly) that, by default
        //      (`scrollOnFocus: true`), calls `slideTo`/`slideToLoop`
        //      whenever a focused descendant sits inside a `.swiper-slide`
        //      that isn't Swiper's own idea of the single "active" slide —
        //      even if that slide is already fully visible on screen, which
        //      it always is here at slidesPerView:4. `closeTeamModal()`
        //      below calls `.focus()` on the just-clicked card to restore
        //      keyboard focus on close (correct, standard modal a11y
        //      practice) — and that focus call is exactly what was
        //      triggering this. Confirmed via headless Chrome by reading
        //      `swiper.translate` directly (not just which slide *looked*
        //      active): before this fix, `translate` visibly jumped one
        //      slide-width the instant each modal closed, even though it
        //      never moved while the modal was open — i.e. the slide itself
        //      never caused the jump, closing the modal did.
        if (window.Swiper && document.getElementById('teamSwiper')) {
            new Swiper('#teamSwiper', {
                loop: true,
                watchOverflow: false,
                slidesPerGroup: 1,
                grabCursor: true,
                threshold: 5,
                a11y: { scrollOnFocus: false },
                speed: reduce ? 0 : 500,
                // Swiper's keyboard module ships in the vendored bundle but
                // is OFF by default — physical Left/Right arrow-key presses
                // do nothing until this is turned on. `onlyInViewport: true`
                // (the default) means the arrows only drive this carousel
                // while it's actually scrolled into view, so they don't
                // hijack Left/Right elsewhere on the page.
                keyboard: { enabled: true, onlyInViewport: true },
                navigation: { nextEl: '#teamNext', prevEl: '#teamPrev', hideOnClick: false },
                pagination: { el: '#teamPagination', clickable: true },
                breakpoints: {
                    0:    { slidesPerView: 1, spaceBetween: 20 },
                    640:  { slidesPerView: 2, spaceBetween: 24 },
                    1024: { slidesPerView: 4, spaceBetween: 30 }
                }
            });
        }

        // Team member detail modal — click any .team-card to open #teamModal
        // with that person's Name/Title/Degree/Specialties/Years of
        // Experience/About, straight off their `data-team-*` attributes
        // (which come from the account row an admin edits on Users/
        // Assistants). Same fade/scale + Escape/backdrop/× pattern as
        // #svcModal above.
        var teamModal      = document.getElementById('teamModal');
        if (teamModal) {
            var teamModalCard        = document.getElementById('teamModalCard');
            var teamName              = document.getElementById('teamModalName');
            var teamTitle             = document.getElementById('teamModalTitle');
            var teamAbout             = document.getElementById('teamModalAbout');
            var teamImg               = document.getElementById('teamModalImg');
            var teamInitialsEl        = document.getElementById('teamModalInitials');
            var teamClose             = document.getElementById('teamModalClose');
            var teamDegreeRow         = document.getElementById('teamModalDegreeRow');
            var teamDegree            = document.getElementById('teamModalDegree');
            var teamYearsRow          = document.getElementById('teamModalYearsRow');
            var teamYears             = document.getElementById('teamModalYears');
            var teamSpecialtiesRow    = document.getElementById('teamModalSpecialtiesRow');
            var teamSpecialties       = document.getElementById('teamModalSpecialties');
            var teamLastTrigger = null;

            function teamModalKeydown(e) {
                if (e.key === 'Escape') closeTeamModal();
            }
            function setFact(row, valueEl, value) {
                if (value) { valueEl.textContent = value; row.classList.remove('hidden'); }
                else { row.classList.add('hidden'); }
            }
            // Mirrors the PHP $teamInitials helper used for the carousel
            // cards, so a member with no photo shows the same "RT"-style
            // initials in this shared modal too.
            function teamInitialsFromName(name) {
                var parts = (name || '').trim().split(/\s+/).filter(Boolean);
                var clean = [];
                for (var i = 0; i < parts.length; i++) {
                    var c = parts[i].replace(/[.,]+$/, '');
                    if (!c || ['dr', 'mr', 'mrs', 'ms'].indexOf(c.toLowerCase()) !== -1) continue;
                    clean.push(c);
                }
                if (!clean.length) return '';
                var first = clean[0].charAt(0);
                var last = clean.length > 1 ? clean[clean.length - 1].charAt(0) : '';
                return (first + last).toUpperCase();
            }
            function openTeamModal(trigger) {
                if (!trigger) return;
                teamLastTrigger = trigger;
                teamName.textContent = trigger.getAttribute('data-team-name') || '';
                teamTitle.textContent = trigger.getAttribute('data-team-title') || '';
                setFact(teamDegreeRow, teamDegree, trigger.getAttribute('data-team-degree'));
                var years = trigger.getAttribute('data-team-years');
                setFact(teamYearsRow, teamYears, years ? years + (years === '1' ? ' year' : ' years') : '');
                setFact(teamSpecialtiesRow, teamSpecialties, trigger.getAttribute('data-team-specialties'));
                var about = trigger.getAttribute('data-team-about') || '';
                teamAbout.textContent = about;
                teamAbout.classList.toggle('hidden', !about);
                var img = trigger.getAttribute('data-team-image') || '';
                teamImg.src = img;
                teamImg.alt = trigger.getAttribute('data-team-name') || '';
                teamImg.style.visibility = img ? 'visible' : 'hidden';
                if (teamInitialsEl) teamInitialsEl.textContent = img ? '' : teamInitialsFromName(trigger.getAttribute('data-team-name'));
                teamModal.classList.remove('hidden');
                teamModal.classList.add('flex');
                lockBodyScroll();
                document.addEventListener('keydown', teamModalKeydown);
                requestAnimationFrame(function () {
                    teamModal.classList.add('is-in');
                    teamClose.focus();
                });
            }
            function closeTeamModal() {
                if (teamModal.classList.contains('hidden')) return;
                teamModal.classList.remove('is-in');
                unlockBodyScroll();
                document.removeEventListener('keydown', teamModalKeydown);
                var finish = function () {
                    teamModal.classList.add('hidden');
                    teamModal.classList.remove('flex');
                    if (teamLastTrigger) teamLastTrigger.focus();
                };
                if (reduce) { finish(); } else { setTimeout(finish, 260); }
            }
            document.querySelectorAll('.team-card').forEach(function (c) {
                c.addEventListener('click', function (e) {
                    e.stopPropagation();
                    openTeamModal(c);
                });
            });
            teamClose.addEventListener('click', closeTeamModal);
            // Same reasoning as #svcModalBackdrop above — the backdrop is its
            // own layer now, so it's what a backdrop click actually lands on.
            document.getElementById('teamModalBackdrop').addEventListener('click', closeTeamModal);
        }

    })();
</script>

<script src="<?= e(asset('assets/js/theme-menu.js')) ?>"></script>

</body>
</html>
