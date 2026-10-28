<?php
/**
 * Tailwind CSS loader (Play CDN) + shared theme config.
 *
 * Included from the <head> of every page as the app migrates off
 * assets/css/style.css. No build step — the CDN compiles utilities in the
 * browser. Keep the colour tokens here in sync with the old :root variables.
 */
declare(strict_types=1);
?>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    /* Class-based dark mode (2026-09) — a `dark` class on <html>, flipped by the
       header's theme toggle (app.js) and applied pre-paint by an inline script
       in layout.php's <head> (localStorage 'dgt_theme', falling back to the OS
       preference) so there is no flash of the light theme on load. Rolling out
       in stages like every other design-system pass: the app shell (this file's
       base styles, header.php) and the admin dashboard are themed first; other
       module pages stay light until converted — see CLAUDE.md → "Dark mode". */
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                navy:   { 900: '#0f1c2e', 800: '#16263c', 700: '#1e3350', 600: '#274566' },
                steel:  { 500: '#48607a', 400: '#6b819a', 300: '#97a9bd', 200: '#c7d2df', 100: '#e6ecf3', 50: '#f4f7fa' },
                accent: { DEFAULT: '#2f80b4', dark: '#226690', soft: '#e2f0f8' },
                teal:   { DEFAULT: '#1a9aa6', dark: '#158390' },
                brand:  { blue: '#2b6cf0', bluedark: '#1f57cc' },
                ok:     { DEFAULT: '#1f9d63', soft: '#e2f4ec' },
                warn:   { DEFAULT: '#d98a10', soft: '#fbf0dd' },
                danger: { DEFAULT: '#d0463b', soft: '#fbe6e4' },
                info:   { DEFAULT: '#3465a4', soft: '#e6eef8' },
                ink: '#23303f', muted: '#5c6b7a', hairline: '#e3e9f0', canvas: '#f6f9fb',

                /* --- DGT brand palette — derived from the clinic logo (blue #006CC0,
                   teal #0086A0, metallic gray). The new design system; rolling out
                   group-by-group. See /DESIGN.md. Blue = primary accent + navy
                   headings/sidebar (900); Teal = CTA / secondary; ink = cool neutral. */
                dgt: {
                    blue: { 50:'#e9f2fb', 100:'#cee3f6', 200:'#a0c8ec', 300:'#66a7e0', 400:'#2683d2', 500:'#006cc0', 600:'#0056a0', 700:'#0a457e', 800:'#0d3a66', 900:'#102f51' },
                    teal: { 50:'#e4f3f5', 100:'#c1e5ea', 200:'#8ad0d9', 300:'#49b4c1', 400:'#1897a8', 500:'#0086a0', 600:'#016f87', 700:'#075b6f', 800:'#0b4a5a', 900:'#0d3b49' },
                    ink:  { 50:'#f5f7f9', 100:'#e9edf1', 200:'#d6dee4', 300:'#b4c1cb', 400:'#8a98a4', 500:'#647380', 600:'#4a5661', 700:'#37424b', 800:'#262e36', 900:'#161d24' },
                },
            },
            fontFamily: {
                sans: ['Segoe UI', 'Roboto', '"Helvetica Neue"', 'Arial', 'sans-serif'],
            },
            screens: {
                nav: '1280px',  // below this the sidebar is an off-canvas drawer (phones + tablets incl. iPad Pro portrait, 2026-10)
            },
        },
    },
};
</script>
<style>
/* Our own show / hide eye (.pw-toggle) is the only one: hide Edge's built-in reveal / clear buttons, which toggle separately and disagree with it. */
input[type="password"]::-ms-reveal, input[type="password"]::-ms-clear { display: none; }
/*
 * The last survivors of assets/css/style.css: CSS custom properties (kept in
 * sync with the Tailwind colour tokens above, for the few inline style="" bits
 * that still use var(--*)) and the two validation marker classes that JS
 * toggles app-wide — wireRefCheck() / wirePhoneField() and the booking / walk-in
 * / billing forms add `.is-invalid` to a field and un-hide a `.field-err`.
 */
:root {
    --navy-900: #0f1c2e; --navy-800: #16263c; --navy-700: #1e3350; --navy-600: #274566;
    --slate-500: #48607a; --slate-400: #6b819a; --slate-300: #97a9bd;
    --slate-200: #c7d2df; --slate-100: #e6ecf3; --slate-50: #f4f7fa;
    --accent: #2f80b4; --accent-dark: #226690; --accent-soft: #e2f0f8;
    --ok: #1f9d63; --ok-soft: #e2f4ec; --warn: #d98a10; --warn-soft: #fbf0dd;
    --danger: #d0463b; --danger-soft: #fbe6e4; --info: #3465a4; --info-soft: #e6eef8;
}
/* Tells the browser to render native controls (scrollbars, date/time pickers,
   the default focus ring) in their dark variant wherever `dark` is set on
   <html> — independent of which Tailwind utilities a given page has been
   converted to use, so even an unconverted page's native form chrome doesn't
   look jarringly light-on-dark. */
html.dark { color-scheme: dark; }
/* Native dropdown lists don't follow a transparent dark <select>: Chrome drew
   them white with the inherited light text, so options were unreadable.
   Every <option> gets a dark list in dark mode (2026-10-17). */
html.dark select option, html.dark select optgroup { background-color: #16263c; color: #e9edf1; }
/* Tooth drawings (app.js window.toothSvg): body, filling (resto), root-canal
   line, extracted X. Used by the Patient Details chart and the picker below. */
.tooth-svg .tb { fill: #fff; stroke: #8aa0b6; stroke-width: 1.6; stroke-linejoin: round; }
.tooth-svg .tf { fill: #2f6fd0; }
.tooth-svg .tr { fill: none; stroke: #1f9d63; stroke-width: 3; stroke-linecap: round; }
.tooth-svg .tx { fill: none; stroke: #d64550; stroke-width: 3.2; stroke-linecap: round; }
.tooth-svg .tc { fill: #7a4a1e; }
.tp-ico .tooth-svg { width: 100%; height: 100%; max-height: none; overflow: visible; }
.tooth-svg .te { fill: none; stroke: #e0702b; stroke-width: 2.6; stroke-linecap: round; stroke-linejoin: round; }
.t-caries .tooth-svg .tb { fill: #f7ead6; stroke: #c08a3e; }
.t-extract .tooth-svg .tb { fill: #fde6d8; stroke: #e0702b; }
html.dark .t-caries .tooth-svg .tb { fill: #f1dfc2; stroke: #d6a25a; }
html.dark .t-extract .tooth-svg .tb { fill: #f8d8c4; stroke: #f0904f; }
.t-resto .tooth-svg .tb { fill: #e3edfc; stroke: #5d8fd6; }
.t-rct .tooth-svg .tb { fill: #e0f3e8; stroke: #4fae7c; }
.t-missing .tooth-svg .tb { fill: none; stroke: #b9c4d0; stroke-dasharray: 3 2.5; }
html.dark .tooth-svg .tb { fill: #eef2f6; stroke: #8a98a4; }
html.dark .t-resto .tooth-svg .tb { fill: #cfe0fa; stroke: #7ba7e0; }
html.dark .t-rct .tooth-svg .tb { fill: #cdeedc; stroke: #5cbf8b; }
html.dark .t-missing .tooth-svg .tb { fill: none; stroke: #6b7885; }
/* Services Availed tooth picker: the tooth shows its result only once picked
   (aria-pressed), tinted by the service's chart effect (fx-*). */
.svc-tooth .tooth-svg { height: 24px; width: auto; overflow: visible; }
.svc-tooth:not([aria-pressed="true"]) .tooth-svg :is(.tf, .tr, .tx, .tc, .te) { display: none; }
.svc-tooth[aria-pressed="true"].fx-resto { background-color: #eaf2fd !important; border-color: #5d8fd6 !important; color: #2f5fa8 !important; }
.svc-tooth[aria-pressed="true"].fx-rct { background-color: #e8f6ee !important; border-color: #4fae7c !important; color: #1f8a54 !important; }
.svc-tooth[aria-pressed="true"].fx-missing { background-color: #fdecee !important; border-color: #e5888f !important; color: #bf3d47 !important; }
.svc-tooth[aria-pressed="true"].fx-resto .tooth-svg .tb { fill: #e3edfc; stroke: #5d8fd6; }
.svc-tooth[aria-pressed="true"].fx-rct .tooth-svg .tb { fill: #e0f3e8; stroke: #4fae7c; }
.svc-tooth[aria-pressed="true"].fx-missing .tooth-svg .tb { fill: none; stroke: #b9c4d0; stroke-dasharray: 3 2.5; }
html.dark .svc-tooth[aria-pressed="true"].fx-resto { background-color: rgba(123,167,224,.18) !important; color: #9dc2f0 !important; }
html.dark .svc-tooth[aria-pressed="true"].fx-rct { background-color: rgba(92,191,139,.16) !important; color: #6fdba0 !important; }
html.dark .svc-tooth[aria-pressed="true"].fx-missing { background-color: rgba(229,136,143,.16) !important; color: #f0a3a9 !important; }
html.dark select option:disabled { color: #8a98a4; }
/* Form boxes with no dark: classes fell back to the browser's grey dark fill;
   keep them transparent on the card instead (zero specificity, so any
   Tailwind bg utility still wins). */
html.dark :where(textarea, select, input:not([type=checkbox]):not([type=radio]):not([type=range]):not([type=color])) { background-color: transparent; }
/* charts.js draws its chrome (gridlines, the empty-donut ring, axis/centre
   text) as literal SVG attributes, not Tailwind classes, so it can't pick up
   a `dark:` variant directly — these custom properties are the bridge: charts.js
   reads them via `var(--chart-*)`, and they flip live when `.dark` toggles
   (no re-render needed, unlike a JS-computed hex would). */
:root {
    --chart-track: #e9edf1; --chart-center: #102f51; --chart-center-sub: #647380; --chart-axis: #8a98a4;
}
html.dark {
    --chart-track: #37424b; --chart-center: #f5f7f9; --chart-center-sub: #b4c1cb; --chart-axis: #8a98a4;
}
/* Donut-chart arc hover/focus (charts.js's donutChart()) — a small brightness
   bump so a segment reads as interactive, driving the shared tooltip that
   charts.js wires as one document-level delegated listener (the chart markup
   itself is just static SVG with no script of its own). */
.donut-arc { cursor: pointer; transition: filter .15s ease; }
.donut-arc:hover, .donut-arc:focus-visible { filter: brightness(1.15); }
.donut-arc:focus-visible { outline: 2px solid var(--chart-center); outline-offset: 2px; }
@media (prefers-reduced-motion: reduce) {
    .donut-arc { transition: none; }
}
/* Grouped-bar hover/focus (charts.js's groupedBarChart()) — the individual
   bars are only a few px wide (up to ~10 clustered per date/month), so a
   brightness bump alone was too subtle to tell which one the pointer is
   over; a contrasting stroke makes the hovered/focused bar unmistakable
   even at that size, on top of the shared floating tooltip this also drives. */
/* transform-box/transform-origin are set permanently, not just during the
   entrance animation — they're inert until a transform is actually applied
   (default transform: none), and every bar's own bounding box already
   touches the shared baseline at its bottom edge, so `bottom` origin +
   `fill-box` is exactly what window.playGroupedBarChartIntro() needs to grow
   every bar upward from that shared line via a plain scaleY() keyframe. */
.grouped-bar { cursor: pointer; transition: filter .15s ease, stroke-width .15s ease; transform-box: fill-box; transform-origin: bottom; }
.grouped-bar:hover, .grouped-bar:focus-visible { filter: brightness(1.3); stroke: var(--chart-center); stroke-width: 1.5px; paint-order: stroke; }
.grouped-bar:focus-visible { outline: 2px solid var(--chart-center); outline-offset: 2px; }
@media (prefers-reduced-motion: reduce) {
    .grouped-bar { transition: none; }
}
/* A category's own axis label (charts.js's groupedBarChart(), opts.categoryClick)
   — clickable when the chart only shows each date's top-N bars, so the full
   per-service breakdown for that date (including a service with zero visits
   that day) is still one click away. */
.grp-cat-label text { transition: fill .15s ease; }
.grp-cat-label:hover text, .grp-cat-label:focus-visible text { fill: var(--chart-center) !important; font-weight: 700; }
.grp-cat-label:focus-visible { outline: 2px solid var(--chart-center); outline-offset: 2px; }
@media (prefers-reduced-motion: reduce) {
    .grp-cat-label { transition: none; }
}
/* Line-chart node hover/focus (charts.js's lineChart(), the admin dashboard's
   Revenue Trend) — the marker itself is the hit target (no separate invisible
   hit-area layer, same convention as .donut-arc/.grouped-bar above): it grows
   and brightens on hover/focus, which combined with the shared floating
   tooltip is enough to find at this size. transform-box/transform-origin are
   permanent and inert until window.playLineChartIntro()'s entrance animation
   actually applies a transform. */
.line-node { cursor: pointer; transition: r .15s ease, filter .15s ease; transform-box: fill-box; transform-origin: center; }
.line-node:hover, .line-node:focus-visible { r: 6; filter: brightness(1.25); }
/* The crosshair itself needs no hover state of its own — window.wireLineChartHover()
   toggles its opacity attribute directly as the pointer moves. */
.line-crosshair { pointer-events: none; }
.line-node:focus-visible { outline: 2px solid var(--chart-center); outline-offset: 2px; }
@media (prefers-reduced-motion: reduce) {
    .line-node { transition: none; }
}
.field-err { color: #d0463b; font-size: 11px; font-weight: 600; margin-top: 3px; }
.field-err.ok { color: #1f9d63; }
.field-err.checking { color: #6b7280; font-weight: 500; }
/* Guided forms (window.guideForm in app.js, 2026-10-10, adviser feedback):
   the current step's control pulses with an indigo ring (cool like the DGT blues, but not the teal / blue of the buttons), finished fields get a
   green border + a check after their label, later steps stay dimmed. */
@keyframes dgt-guide-pulse {
    0%, 100% { box-shadow: 0 0 0 3px rgba(99,102,241,.55); }
    50%      { box-shadow: 0 0 0 7px rgba(99,102,241,.14); }
}
.guide-active { border-color: #6366f1 !important; animation: dgt-guide-pulse 1.6s ease-in-out infinite; border-radius: 8px; position: relative; z-index: 1; }
button.guide-active, a.guide-active { border-radius: 6px; }
.guide-done-ctl { border-color: #1f9d63 !important; }
.guide-done > label:first-child::after, .guide-done .guide-lbl::after { content: " \2713"; color: #1f9d63; font-weight: 700; }
.guide-locked { opacity: .42; filter: grayscale(.35); transition: opacity .2s ease, filter .2s ease; }
.guide-locked, .guide-locked * { cursor: not-allowed !important; }
button.guide-locked { opacity: .5; }
.guide-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; margin: 0 0 12px; padding: 8px 12px; border-radius: 8px; background: #ecf7f9; border: 1px solid #b6e0e8; color: #0b4f5e; font-size: 12.5px; }
.guide-bar b { font-weight: 700; }
.guide-dots { display: inline-flex; gap: 4px; margin-left: auto; }
.guide-dots i { width: 18px; height: 5px; border-radius: 999px; background: #c8d5df; }
.guide-dots i.on { background: #0086a0; }
.guide-dots i.ok { background: #1f9d63; }
html.dark .guide-bar { background: rgba(0,134,160,.14); border-color: rgba(0,134,160,.4); color: #b9e6ee; }
html.dark .guide-dots i { background: rgba(255,255,255,.15); }
html.dark .guide-done > label:first-child::after, html.dark .guide-done .guide-lbl::after { color: #5fd39c; }
@media (prefers-reduced-motion: reduce) {
    .guide-active { animation: none; box-shadow: 0 0 0 3px rgba(99,102,241,.55); }
}
input.is-invalid, select.is-invalid, textarea.is-invalid {
    border-color: #d0463b !important; background: #fbe6e4;
}
/* Hide the native datalist dropdown arrow on <input list> typeaheads
   (e.g. the Add Doctor "Degree" field) — suggestions still work on typing. */
input[list]::-webkit-calendar-picker-indicator { display: none !important; }
input[list]::-webkit-list-button { display: none !important; }
/*
 * Base interaction feel for every button and link so hover / focus state
 * changes (background, text, border, shadow, brightness) glide instead of
 * snapping, plus a small press on :active — the row-action buttons
 * (Edit / View / Details / Approve / Archive ...) never carry a `transition-*`
 * utility of their own. `.nav-item` (sidebar) and `.tab` opt out of the press.
 */
button, [role="button"], a {
    transition: background-color .16s ease, border-color .16s ease, color .16s ease,
                box-shadow .16s ease, filter .16s ease, transform .12s ease;
}
/* Visible hover feedback for the row-action buttons (Edit / View / Details / ...),
   whose ghost style otherwise only shifts to a near-white fill. Sidebar nav,
   tabs, the modal close-X, the reveal-password eye toggle and the stacked-modal
   "← Back" link opt out — each is a plain icon/text control with no
   border/background of its own, so this lift rendered as a stray box around it
   on hover. */
/* Text-link buttons (anything styled with `underline` / `hover:underline`,
   e.g. Remove QR, Resend it, Change) have no box, so they get no shadow. */
button:not(:disabled):not(.tab):not(.nav-item):not(.modal-close):not(.pw-toggle):not(.modal-back):not([class*="underline"]):hover {
    box-shadow: 0 2px 10px rgba(15, 28, 46, 0.14);
}
button:not(:disabled):not(.tab):not(.nav-item):active,
[role="button"]:not(.tab):not(.nav-item):active {
    transform: translateY(1px) scale(0.985);
}
@media (prefers-reduced-motion: reduce) {
    button, [role="button"], a { transition: none; }
    button:not(:disabled):hover { box-shadow: none; }
    button:active, [role="button"]:active { transform: none; }
    #sidebar, #appShellContent, #sidebar .sb-label, #sidebar .sb-toggle-header { transition: none !important; }
}
/*
 * Desktop sidebar collapse (every role, ChatGPT-style) — `.sb-toggle-header`
 * (sidebar.php, its own slim row under the brand block) flips body.sb-collapsed
 * (app.js; layout.php applies the stored choice from localStorage in an inline
 * <script> right after <body> opens, so there's no flash of the expanded
 * sidebar on load). Guarded to the `nav:` breakpoint (1280px) so it never
 * fights the mobile off-canvas drawer, which already has its own show/hide
 * mechanism via .open. **Re-expanding while collapsed has no separate visible
 * button** — the logo badge itself (`#sbLogoToggle` / `.sb-logo-badge`) doubles
 * as the control: hovering it while collapsed swaps the logo for the same
 * panel glyph (`.sb-logo-img` fades out, `.sb-logo-ico` fades in) and a click
 * expands (wired directly in app.js, a no-op while already expanded so the
 * logo just behaves like a logo then) — matching ChatGPT's own "the brand mark
 * becomes the reopen control" behaviour instead of a second visible button in
 * the rail. The logo badge itself is never wrapped in `.sb-label` — it stays
 * put in both states — so the header's height never changes and the nav list
 * below never shifts when collapsing. Every `.sb-label` (the brand text, each
 * nav item's label, the sidebar-footer name/role, "Logout") fades and
 * collapses via `max-width` + `opacity` rather than a hard `display:none`, so
 * the rail feels like it slides shut instead of snapping — no squish, no
 * line-wrap, no flash.
 *
 * Smoothing notes (2026-09): every moving piece shares one easing curve
 * (`--sb-ease`, a Material-style ease-in-out) and the width/margin-left pair
 * stays on the same duration so the rail and the content column never drift
 * out of sync. The label opacity fade is deliberately shorter than its
 * max-width collapse (160ms vs 300ms, both starting at t=0) so the text is
 * already invisible well before the available box shrinks enough to visibly
 * clip it — the earlier version faded both at once, which read as the text
 * getting chopped mid-transition rather than fading away. `.sb-toggle-header`
 * used to `display:none` the instant the class flipped (an instant pop at the
 * *start* of the collapse, not the end); it now fades the same way as a label.
 * The old collapsed-state rule that forced `justify-content:center` +
 * `padding:0` on the nav rows / brand / footer was **removed** — `justify-
 * content` can't be animated at all, so it applied at t=0, snapping every
 * icon to "centered in the still-mostly-expanded rail" for an instant before
 * the width transition had moved at all (a sideways jump), and `padding:0`
 * on top of that shoved everything the rest of the way to the edge. Icon
 * centering in the 72px rail is done with `padding-left`/`padding-right`
 * instead — an ordinary, fully-animatable numeric property — computed per
 * element so a 20px nav icon / 40px logo badge / 36px avatar lands dead
 * center of the collapsed row (`.nav-item` 12px→16px, `.sidebar-brand`
 * 18px→16px, `.sidebar-user` 8px→6px; the logout row needs no override — its
 * icon is `position:absolute left-4` inside a box that happens to end up 48px
 * wide collapsed, which is already dead center). Both states' padding are
 * real values on the same `--sb-ease` transition as the width, so the icon
 * glides to centered in step with the rail instead of snapping to it.
 * `overflow-x: hidden` on `#sidebar` is a safety net against a sub-pixel
 * scrollbar flash mid-transition.
 */
:root { --sb-ease: cubic-bezier(.4, 0, .2, 1); }
/*
 * Responsive hardening (2026-10). Phones (320-480px), tablets (768-1279px,
 * sidebar is a drawer there) and desktops up to 4K:
 * - no sideways page scroll: wide tables scroll inside their own card instead;
 * - 100dvh so a min-h-screen layout isn't hidden under a mobile address bar;
 * - an sr-only checkbox inside a scrolling row (the Auto-refresh switches) is
 *   anchored to its label, so it can't escape and widen the page;
 * - 44px touch targets on touch screens (pointer: coarse) only, so desktop
 *   tables keep their compact row buttons.
 */
html, body { overflow-x: clip; }
.min-h-screen { min-height: 100vh; min-height: 100dvh; }
label:has(> input.sr-only) { position: relative; }
#sbBackdrop { display: none; }
@media (max-width: 1279.98px) {
    #sidebar.open + #sbBackdrop { display: block; }
    /* Drawer starts hidden from the very first paint. The Tailwind CDN builds
       max-nav:-translate-x-full only after it loads, so on phones / iPads the
       sidebar used to show, then slide shut on its own. */
    #sidebar { transform: translateX(-100%); }
    #sidebar.open { transform: translateX(0); }
}
/* Responsive tables (.rtable, e.g. the receptionist's Appointments for
   Approval / Approved Appointments): below 1280px (phones + every iPad) each
   row becomes a card of "Label  value" lines instead of a table that scrolls
   sideways. Labels are stamped from the <thead> by app.js (data-label). The
   last cell (the action buttons) runs full width under a hairline, except
   on .rt-plain tables, which have no action column (e.g. Patient Records).
   Add .rt-phone to keep the normal table on iPads (cards on phones only),
   or .rt-mini for cards on phones + iPad mini (up to 800px) only. */
<?php
/* The card rules are written once with RT as the table selector and printed
   twice: phones (< 768px) get them for every .rtable; tablets (768-1279px)
   only for tables WITHOUT .rt-phone (.rt-phone = cards on phones only, a
   normal table on iPads, e.g. Patient Records). */
$rtCss = <<<'RTCSS'
    RT, RT tbody { display: block; width: 100%; }
    RT thead { display: none; }
    RT tbody { display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; }
    RT tr { display: flex; flex-direction: column; padding: 12px 14px; border: 1px solid #e3e9ef; border-radius: 10px; background-color: #fff; }
    html.dark RT tr { border-color: rgba(255,255,255,.1); background-color: rgba(255,255,255,.03); }
    RT td { display: block; width: auto !important; position: relative; min-height: 26px; padding: 4px 0 4px 108px !important; border: 0 !important;
        white-space: normal !important; overflow-wrap: anywhere; font-size: 13px; text-align: left !important; }
    RT td::before { content: attr(data-label); position: absolute; left: 0; top: 6px; width: 100px; font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #7b8a99; }
    RT td[data-label=""]::before { content: ""; }
    /* An e-mail stays on one line (ellipsis if it is very long) instead of
       breaking mid-word across two lines. */
    RT td a[href^="mailto:"] { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; overflow-wrap: normal; }
    RT td.rt-email { white-space: nowrap !important; overflow: hidden; text-overflow: ellipsis; overflow-wrap: normal; }
    RT td.rt-email * { white-space: nowrap !important; overflow-wrap: normal !important; max-width: none !important; }
    /* .rt-hide: a cell with nothing useful on a card (row #, N/A, "Self").
       .rt-title: the patient name, moved to the top as the card heading. */
    RT td.rt-hide { display: none; }
    RT td.rt-title { order: -1; padding: 0 0 8px !important; margin-bottom: 6px; font-size: 15px; font-weight: 600;
        border-bottom: 1px solid #e3e9ef !important; color: #102f51; }
    html.dark RT td.rt-title { color: #f1f5f9; border-bottom-color: rgba(255,255,255,.1) !important; }
    RT td.rt-title::before { display: none; }
    RT td.rt-title strong { font-weight: 600; }
    RT:not(.rt-plain) td:last-child:not([colspan]) { margin-top: 8px; padding: 10px 0 0 !important; border-top: 1px solid #e3e9ef !important; }
    html.dark RT:not(.rt-plain) td:last-child:not([colspan]) { border-top-color: rgba(255,255,255,.1) !important; }
    RT:not(.rt-plain) td:last-child:not([colspan])::before { display: none; }
    /* Action buttons: an even 2-column grid of equal-width buttons, so they
       always line up (an odd last button spans the full row) instead of
       wrapping raggedly. */
    RT:not(.rt-plain) td:last-child:not([colspan]) > div { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px !important; }
    RT:not(.rt-plain) td:last-child:not([colspan]) > div > * { width: 100%; min-width: 0 !important; min-height: 40px !important; justify-content: center; text-align: center; white-space: nowrap; }
    RT:not(.rt-plain) td:last-child:not([colspan]) > div > :last-child:nth-child(odd) { grid-column: 1 / -1; }
    RT td[colspan] { padding: 16px 0 !important; text-align: center !important; }
    RT td[colspan]::before { display: none; }
    RT tr:has(> td[colspan]) { border: 0; background: transparent; }
RTCSS;
echo "@media (max-width: 767.98px) {
" . str_replace('RT', 'table.rtable', $rtCss) . "
}
";
echo "@media (min-width: 768px) and (max-width: 1279.98px) {
" . str_replace('RT', 'table.rtable:not(.rt-phone):not(.rt-mini)', $rtCss) . "
"
   . "    table.rtable:not(.rt-phone):not(.rt-mini) tbody { grid-template-columns: repeat(2, minmax(0, 1fr)); }
"
   . "    table.rtable:not(.rt-phone):not(.rt-mini) tr:has(> td[colspan]) { grid-column: 1 / -1; }
}
";
// .rt-mini: cards on phones and iPad mini (up to 800px), a table from iPad Air up.
echo "@media (min-width: 768px) and (max-width: 800px) {
" . str_replace('RT', 'table.rtable.rt-mini', $rtCss) . "
    table.rtable.rt-mini tbody { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    table.rtable.rt-mini tr:has(> td[colspan]) { grid-column: 1 / -1; }
}
";
?>
/* Notification pages (#notifTable on the dentist / receptionist / assistant
   pages) on phones: each row becomes a card instead of a table that scrolls
   sideways. Message + status badge on top, received time + Delete below. */
@media (max-width: 767.98px) {
    div:has(> .overflow-x-auto > #notifTable) { border: 0 !important; padding: 0 !important; background: transparent !important; box-shadow: none !important; }
    #notifTable, #notifTable tbody { display: block; }
    #notifTable thead { display: none; }
    #notifTable tbody { display: flex; flex-direction: column; gap: 10px; }
    #notifTable tr { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; column-gap: 12px; row-gap: 8px;
        padding: 14px 16px; border: 1px solid #e3e9ef; border-radius: 10px; background-color: #fff; box-shadow: 0 1px 2px rgba(16,47,81,.06); }
    html.dark #notifTable tr { border-color: rgba(255,255,255,.1); background-color: #262e36; }
    #notifTable tr.bg-accent-soft { background-color: #e2f0f8; border-color: rgba(47,128,180,.3); }
    html.dark #notifTable tr.bg-accent-soft { background-color: rgba(47,128,180,.2); }
    #notifTable td { display: block; border: 0 !important; padding: 0 !important; white-space: normal !important; }
    #notifTable td:nth-child(1) { grid-column: 1; grid-row: 1; align-self: start; }
    #notifTable td:nth-child(3) { grid-column: 2; grid-row: 1; align-self: start; }
    #notifTable td:nth-child(2) { grid-column: 1; grid-row: 2; }
    #notifTable td:nth-child(4) { grid-column: 2; grid-row: 2; }
    #notifTable td[colspan] { grid-column: 1 / -1; grid-row: 1; text-align: center; }
    #notifTable td:nth-child(4) button { min-height: 32px !important; min-width: 0 !important; }
}
@media (pointer: coarse) {
    #app-content button:not(.pcal-cell):not(.cal-cell):not(.tap-sm):not(.tooth-box), #app-content select, #app-content .tab,
    #app-content input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="file"]):not(.sr-only),
    #modalRoot button:not(.pcal-cell):not(.cal-cell):not(.tap-sm):not(.tooth-box), #modalRoot select,
    #modalRoot input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="file"]):not(.sr-only),
    #topHeader button, #sidebar .nav-item, #sidebar button, #sidebar a.sidebar-logout { min-height: 44px; }
    /* Calendar day cells are sized by the 7-column grid and their own
       calendar CSS, never by the tap rule. .tap-sm opts a deliberately
       sized (36px) card button out, e.g. View / Receipt on billing cards. */
    #app-content button:not(.pcal-cell):not(.cal-cell):not(.tap-sm):not(.tooth-box),
    #modalRoot button:not(.pcal-cell):not(.cal-cell):not(.tap-sm):not(.tooth-box), #topHeader button { min-width: 44px; }
}
#sidebar {
    overflow-x: hidden;
    transition: width .3s var(--sb-ease), transform .3s var(--sb-ease);
    will-change: width;
}
#appShellContent { transition: margin-left .3s var(--sb-ease); will-change: margin-left; }
#sidebar .sb-label {
    overflow: hidden; white-space: nowrap; max-width: 200px; opacity: 1;
    transition: max-width .3s var(--sb-ease), opacity .16s ease;
}
#sidebar .sb-toggle-header { transition: opacity .16s ease; }
#sidebar .sb-logo-img, #sidebar .sb-logo-ico { transition: opacity .15s ease; }
#sidebar .sb-logo-ico { opacity: 0; }
#sidebar .nav-item, #sidebar .sidebar-brand, #sidebar .sidebar-user {
    transition: padding-left .3s var(--sb-ease), padding-right .3s var(--sb-ease);
}
@media (min-width: 1280px) {
    body.sb-collapsed #sidebar { width: 72px; }
    body.sb-collapsed #appShellContent { margin-left: 72px; }
    body.sb-collapsed #sidebar .sb-label { max-width: 0; opacity: 0; }
    body.sb-collapsed #sidebar .sb-toggle-header { opacity: 0; pointer-events: none; }
    body.sb-collapsed #sidebar .sb-logo-badge { cursor: pointer; }
    body.sb-collapsed #sidebar .sb-logo-badge:hover .sb-logo-img { opacity: 0; }
    body.sb-collapsed #sidebar .sb-logo-badge:hover .sb-logo-ico { opacity: 1; }
    body.sb-collapsed #sidebar .nav-item { padding-left: 16px; padding-right: 16px; }
    body.sb-collapsed #sidebar .sidebar-brand { padding-left: 16px; padding-right: 16px; }
    body.sb-collapsed #sidebar .sidebar-user { padding-left: 6px; padding-right: 6px; }
}
/*
 * Modal enter / leave animation. openModal() writes the overlay + dialog with
 * these start states (opacity 0, dialog nudged down & scaled), forces a reflow,
 * then adds `#modalRoot.is-in`; closeModal() removes it and waits for the
 * dialog's transitionend before clearing the markup. Keeps the same
 * `.modal-root` / dialog markup every screen already builds.
 */
#modalRoot > [data-close] {
    opacity: 0;
    transition: opacity .18s ease;
}
#modalRoot > [role="dialog"] {
    opacity: 0;
    transform: translateY(10px) scale(.975);
    transition: opacity .2s ease, transform .24s cubic-bezier(.16, 1, .3, 1);
}
/* Every screen size: a pop-up sits in the middle of the screen; a tall one
   still starts at the top and scrolls (auto margins collapse to 0 when it
   overflows). */
#modalRoot:not([hidden]) { display: flex; flex-direction: column; padding: 12px 0; }
#modalRoot > [role="dialog"] { margin-top: auto; margin-bottom: auto; }
@media (min-width: 640px) {
    #modalRoot:not([hidden]) { padding: 24px 0; }
}
#modalRoot.is-in > [data-close] { opacity: 1; }
#modalRoot.is-in > [role="dialog"] { opacity: 1; transform: none; }
@media (prefers-reduced-motion: reduce) {
    #modalRoot > [data-close], #modalRoot > [role="dialog"] {
        opacity: 1; transform: none; transition: none;
    }
}
/*
 * Async modal-body loading -> content cross-fade (2026-09). A drill-down
 * modal (window.openAsyncModal helpers in app.js / the admin dashboard's own
 * openDrill/openRevTrendDrill/openSvcUsageDrill) opens immediately with a
 * spinner in its body, then window.swapModalBody() cross-fades that body's
 * opacity down, swaps in the fetched HTML, and fades it back up — instead of
 * a raw innerHTML swap, which read as a jarring "double pop" whenever a fast
 * local fetch replaced the spinner within a few milliseconds of it appearing.
 */
.dgt-modal-fade {
    opacity: 1;
    transition: opacity .16s ease;
    will-change: opacity;
}
.dgt-modal-fade.dgt-modal-fade-out { opacity: 0; }
.dgt-modal-loading {
    display: flex; align-items: center; justify-content: center; gap: 10px;
    padding: 28px 0;
}
.dgt-spinner {
    width: 16px; height: 16px; border-radius: 50%;
    border: 2px solid rgb(0 108 192 / .25); border-top-color: #006cc0;
    display: inline-block; animation: dgt-spin .7s linear infinite;
}
@keyframes dgt-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) {
    .dgt-modal-fade, .dgt-modal-fade.dgt-modal-fade-out { opacity: 1; transition: none; }
    .dgt-spinner { animation: none; }
}
/*
 * Dark-mode toggle reveal (app.js's #themeToggle handler) drives its own
 * WAAPI clip-path animation on ::view-transition-new(root) — this disables
 * the View Transitions API's own default cross-fade on both root pseudo-
 * elements so it doesn't play underneath/against the circle, and turns off
 * their default mix-blend-mode (normally "plus-lighter", meant for the
 * cross-fade) so the new-theme snapshot paints as opaque, correctly-coloured
 * pixels instead of blending with the old one.
 */
::view-transition-old(root),
::view-transition-new(root) {
    animation: none;
    mix-blend-mode: normal;
}
/* Whole app grows with the screen (2026-10-18, project rule): on screens wider
   than a 1366px laptop, the page content, top header, pop-ups and toasts are
   zoomed by --uiz (set by the pre-paint script in layout.php: 1 at 1366px and
   below, 1.15 at 1920px, max 1.25 from about 2290px), so text, icons, buttons, tables and
   padding all keep their laptop proportions instead of stretching. The sidebar
   stays its fixed width. vh-based heights inside a zoomed box are divided by
   --uiz so pop-ups still fit the window. Never zoom when printing. */
:root { --uiz: 1; }
#app-content > .page, #topHeader, #modalRoot > [role="dialog"], .slot-pop > div, #toastRoot { zoom: var(--uiz); }
@media (min-width: 1366px) {
  :root #modalRoot .modal-body { max-height: calc(66vh / var(--uiz)); }
  :root .slot-pop > div { max-height: calc((100dvh - 32px) / var(--uiz)); }
}
@media print { #app-content > .page, #topHeader, #modalRoot > [role="dialog"], #toastRoot { zoom: 1 !important; } }

/* Network feedback (2026-10-18, app.js netBusy / busy buttons). */
#netBar { position: fixed; top: 0; left: 0; right: 0; height: 3px; z-index: 200; pointer-events: none;
  overflow: hidden; opacity: 0; transition: opacity .2s ease; }
#netBar.on { opacity: 1; }
#netBar::before { content: ''; position: absolute; top: 0; bottom: 0; left: 0; width: 40%;
  background: #0086a0; border-radius: 0 2px 2px 0; animation: dgt-netbar 1.1s cubic-bezier(.4,0,.2,1) infinite; }
@keyframes dgt-netbar { from { transform: translateX(-100%); } to { transform: translateX(250%); } }
button[aria-busy="true"], [role="button"][aria-busy="true"] { cursor: progress !important; opacity: .8; }
button[aria-busy="true"]::before { content: ''; display: inline-block; flex-shrink: 0; width: 12px; height: 12px;
  margin-right: 6px; vertical-align: -2px; border: 2px solid currentColor; border-right-color: transparent;
  border-radius: 50%; animation: dgt-busy-spin .7s linear infinite; }
@keyframes dgt-busy-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) {
  #netBar::before { animation-duration: 2.4s; }
  button[aria-busy="true"]::before { animation-duration: 1.6s; }
}
</style>
