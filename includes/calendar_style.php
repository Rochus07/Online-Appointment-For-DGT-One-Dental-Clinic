<?php
/**
 * Scoped CSS for the month-grid appointment calendar widget (`.cal-*`).
 * Shared verbatim by the dentist, receptionist and cashier Calendar pages
 * (each `require`s this inside its $PAGE_CONTENT). This is a self-contained
 * visual widget kept as hand-written CSS rather than Tailwind utilities, but
 * scoped to the pages that use it instead of living in the global style.css.
 *
 * A <style> emitted inside $PAGE_CONTENT applies on both the full render and
 * the nav.js partial swap.
 */
declare(strict_types=1);
?>
<style>
/* calendar in column 1 row 1, the day-detail card right below it (row 2); the
   sidebar spans both rows in column 2 so its height never pushes the day card
   down or opens a gap under the calendar. */
.cal-layout { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 16px; align-items: start; }
.cal-main { grid-column: 1; grid-row: 1; }
.cal-side { grid-column: 2; grid-row: 1 / span 2; display: flex; flex-direction: column; gap: 16px; }
#dayCard { grid-column: 1; grid-row: 2; }
#calUpcoming { max-height: 380px; overflow-y: auto; }
.cal-toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.cal-toolbar h2 { font-size: 16px; color: #0f1c2e; min-width: 168px; text-align: center; }
.cal-nav-btn {
    background: #e6ecf3; border: 0; width: 30px; height: 30px; border-radius: 999px;
    cursor: pointer; font-size: 16px; line-height: 1; color: #1e3350;
}
.cal-nav-btn:hover { background: #c7d2df; }
.cal-toolbar .cal-today-btn { margin-left: auto; }

.cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); }
.cal-dow-row { margin-bottom: 4px; }
.cal-dow {
    text-align: center; font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; color: #6b819a; padding: 6px 0;
}
.cal-cell {
    min-height: 94px; border: 1px solid #e6ecf3; margin: -1px 0 0 -1px;
    padding: 5px 6px; display: flex; flex-direction: column; gap: 3px;
    cursor: pointer; background: #fff; overflow: hidden;
}
.cal-cell:hover { background: #f4f7fa; }
.cal-cell.other { background: #f4f7fa; }
.cal-cell.other .cal-date { color: #97a9bd; }
.cal-cell.today { background: #e2f0f8; }
.cal-cell.selected { outline: 2px solid #2f80b4; outline-offset: -2px; position: relative; z-index: 1; }
.cal-cell.closed { background: repeating-linear-gradient(45deg, #f4f7fa, #f4f7fa 6px, #fff 6px, #fff 12px); }
.cal-cell.closed .cal-date { color: #6b819a; }
.cal-closed { font-size: 10px; font-weight: 700; color: #6b819a; text-transform: uppercase; letter-spacing: .5px; }
.cal-date { font-size: 12px; font-weight: 700; color: #1e3350; }
.cal-chip {
    font-size: 10px; line-height: 1.35; padding: 2px 5px; border-radius: 4px;
    background: #e6eef8; color: #3465a4;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.cal-chip.s-approved { background: #e6eef8; color: #3465a4; }
.cal-chip.s-completed { background: #e2f4ec; color: #1f9d63; }
.cal-chip.s-pending { background: #fbf0dd; color: #d98a10; }
.cal-chip.s-cancelled { background: #e6ecf3; color: #6b819a; text-decoration: line-through; }
.cal-chip.s-rescheduled { background: #fae8ff; color: #a21caf; }
.cal-more { font-size: 10px; color: #6b819a; font-weight: 700; }
.cal-count { display: none; }

.cal-stats { display: flex; flex-direction: column; gap: 10px; }
.cal-stat { background: #1e3350; color: #fff; border-radius: 10px; padding: 14px 16px; text-align: center; }
.cal-stat span { display: block; font-size: 26px; font-weight: 700; font-variant-numeric: tabular-nums; }
.cal-stat small { font-size: 10px; text-transform: uppercase; letter-spacing: .6px; opacity: .8; }

.cal-up-item { padding: 10px 0; border-bottom: 1px solid #e6ecf3; }
.cal-up-item:last-child { border-bottom: 0; padding-bottom: 0; }
.cal-up-item b { display: block; font-size: 12px; color: #226690; }
.cal-up-item span { font-size: 13px; color: #16263c; font-weight: 600; }
.cal-up-item small { display: block; color: #6b819a; font-size: 12px; text-transform: capitalize; }

/* Desktop (1280px+): the right column is exactly as tall as the calendar;
   contain:size stops its own content from growing the row, and the Upcoming
   list fills the rest and scrolls. */
@media (min-width: 1280px) {
    .cal-layout { align-items: stretch; }
    .cal-side { grid-row: 1; contain: size; }
    .cal-side > :last-child { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
    #calUpcoming { flex: 1 1 auto; min-height: 0; max-height: none; }
    /* Fill the screen: the calendar card stretches to the bottom of the
       viewport and the six week rows share the height. */
    .cal-layout { min-height: calc(100dvh - 205px); }
    .cal-main { display: flex; flex-direction: column; }
    #calBody { flex: 1 1 auto; grid-auto-rows: minmax(94px, 1fr); }
}

/* Stacked below 1280px (phones + every iPad, incl. iPad Pro): the calendar
   gets the full width instead of leaving a tall empty column beside it. */
@media (max-width: 1279.98px) {
    .cal-layout { grid-template-columns: minmax(0, 1fr); }
    .cal-main, .cal-side, #dayCard { grid-column: 1; grid-row: auto; }
    #calUpcoming { max-height: 320px; }
    .cal-cell { min-height: 68px; }
    /* Stacked (phones + iPads): the three totals sit in one compact row
       instead of three tall navy blocks, and each upcoming visit is a
       date-chip row. */
    .cal-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
    .cal-stat { padding: 12px 6px; }
    .cal-stat span { font-size: 22px; line-height: 1.1; }
    .cal-stat small { display: block; margin-top: 4px; font-size: 9.5px; letter-spacing: .3px; line-height: 1.25; }
    .cal-up-item { display: grid; grid-template-columns: 58px minmax(0, 1fr); column-gap: 12px; align-items: center; }
    .cal-up-item b { grid-row: 1 / span 2; text-align: center; background: #eaf3fb; border-radius: 8px; padding: 6px 4px; line-height: 1.25; font-size: 11.5px; }
    .cal-up-item span, .cal-up-item small { grid-column: 2; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
}
/* Tablets (stacked but wide): Statistics and Upcoming side by side, equal
   height (the three stat bars stretch to fill the card). */
@media (min-width: 641px) and (max-width: 1279.98px) {
    .cal-side { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: stretch; }
    .cal-side > * { display: flex; flex-direction: column; }
    .cal-stats { grid-template-columns: minmax(0, 1fr); flex: 1 1 auto; grid-auto-rows: 1fr; }
    .cal-stat { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; text-align: left; }
    .cal-stat span { order: 2; }
    .cal-stat small { margin-top: 0; font-size: 10.5px; }
    .cal-stat span { font-size: 26px; }
}
/* Tablets held upright: taller day cells so the month fills the screen. */
@media (min-width: 641px) and (max-width: 1279.98px) and (orientation: portrait) {
    .cal-cell { min-height: 112px; }
}

/* Dark mode (2026-09) — this widget predates the Tailwind migration and is
   hand-written CSS, so it can't take a `dark:` utility variant; these are
   plain `html.dark` overrides of the same rules instead, using the dgt-ink
   scale's hex values directly for surfaces/borders/text and a translucent
   tint of each status accent for the chips (mirrors the badge-on-dark-surface
   pattern used everywhere else — accent colour as text, ~20% as the fill). */
html.dark .cal-toolbar h2 { color: #e9edf1; }
html.dark .cal-nav-btn { background: rgba(255,255,255,.08); color: #e9edf1; }
html.dark .cal-nav-btn:hover { background: rgba(255,255,255,.15); }
html.dark .cal-dow { color: #8a98a4; }
html.dark .cal-cell { border-color: rgba(255,255,255,.08); background: #262e36; }
html.dark .cal-cell:hover { background: #2e3742; }
html.dark .cal-cell.other { background: #1d232b; }
html.dark .cal-cell.other .cal-date { color: #647380; }
html.dark .cal-cell.today { background: rgba(0,108,192,.22); }
html.dark .cal-cell.closed { background: repeating-linear-gradient(45deg, #1d232b, #1d232b 6px, #262e36 6px, #262e36 12px); }
html.dark .cal-cell.closed .cal-date { color: #8a98a4; }
html.dark .cal-closed { color: #8a98a4; }
html.dark .cal-date { color: #e9edf1; }
html.dark .cal-chip { background: rgba(52,101,164,.25); color: #8fb4e0; }
html.dark .cal-chip.s-approved { background: rgba(52,101,164,.25); color: #8fb4e0; }
html.dark .cal-chip.s-completed { background: rgba(31,157,99,.22); color: #5fd39c; }
html.dark .cal-chip.s-pending { background: rgba(217,138,16,.22); color: #f0b74a; }
html.dark .cal-chip.s-cancelled { background: rgba(255,255,255,.08); color: #97a9bd; }
html.dark .cal-chip.s-rescheduled { background: rgba(162,28,175,.25); color: #f0abfc; }
html.dark .cal-more { color: #8a98a4; }
html.dark .cal-up-item { border-bottom-color: rgba(255,255,255,.08); }
html.dark .cal-up-item b { color: #6ab0e8; }
@media (max-width: 1279.98px) { html.dark .cal-up-item b { background: rgba(106,176,232,.12); } }
html.dark .cal-up-item span { color: #e9edf1; }
html.dark .cal-up-item small { color: #8a98a4; }

/* Phones (2026-10): keep all seven columns inside a 320px screen. */
.cal-grid { grid-template-columns: repeat(7, minmax(0, 1fr)); }
.cal-toolbar { flex-wrap: wrap; }
@media (max-width: 640px) {
    .cal-toolbar h2 { min-width: 0; flex: 1; font-size: 15px; }
    .cal-cell { min-height: 0; aspect-ratio: 1 / 1.45; padding: 7px 5px; align-items: center; }
    .cal-date { font-size: 15px; }
    /* Name chips are unreadable at this width: show how many appointments
       the day has instead; tapping the day lists them below. */
    .cal-chip, .cal-more { display: none; }
    .cal-count {
        display: grid; place-items: center; margin-top: auto; min-width: 22px; height: 22px; padding: 0 6px;
        border-radius: 999px; background: #2f80b4; color: #fff; font-size: 11.5px; font-weight: 700;
    }
    .cal-closed { display: none; }   /* the hatched cell already says closed */
}
</style>
