<?php
/**
 * Scoped CSS for the patient booking calendar (`modules/patient/calendar.php`).
 * A self-contained month-grid widget (`.pcal-*`) that shows per-day slot
 * availability and, on the selected day, a list of bookable time slots.
 * Purpose-built rather than reusing `includes/calendar_style.php` (the staff
 * appointment calendar) because this one is a booking aid, not an agenda.
 *
 * Emitted inside $PAGE_CONTENT so it applies on the full render and the nav.js
 * partial swap. Legacy palette, matching the rest of the patient portal.
 */
declare(strict_types=1);
?>
<style>
.pcal-layout { display: grid; grid-template-columns: 1fr 300px; gap: 16px; align-items: start; }
@media (max-width: 900px) { .pcal-layout { grid-template-columns: 1fr; } }

.pcal-toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.pcal-toolbar h2 { font-size: 16px; color: #0f1c2e; min-width: 168px; text-align: center; }
.pcal-nav-btn {
    background: #e6ecf3; border: 0; width: 30px; height: 30px; border-radius: 999px;
    cursor: pointer; font-size: 16px; line-height: 1; color: #1e3350;
}
.pcal-nav-btn:hover { background: #c7d2df; }
.pcal-today-btn { margin-left: auto; }

.pcal-legend { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 12px; font-size: 11px; color: #5c6b7a; }
.pcal-legend span { display: inline-flex; align-items: center; gap: 5px; }
.pcal-legend i { width: 11px; height: 11px; border-radius: 3px; display: inline-block; }
.pcal-legend .lg-open   { background: #d5efe0; border: 1px solid #1f9d63; }
.pcal-legend .lg-full   { background: #f7dedb; border: 1px solid #d0463b; }
.pcal-legend .lg-closed { background: repeating-linear-gradient(45deg, #f4f7fa, #f4f7fa 4px, #fff 4px, #fff 8px); border: 1px solid #c7d2df; }

.pcal-grid { display: grid; grid-template-columns: repeat(7, 1fr); }
.pcal-dow-row { margin-bottom: 6px; }
.pcal-dow {
    text-align: center; font-size: 12px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .6px; color: #6b819a; padding: 9px 0;
}
.pcal-cell {
    min-height: 128px; width: 100%; border: 1px solid #e6ecf3; margin: -1px 0 0 -1px;
    padding: 11px 13px; display: flex; flex-direction: column; gap: 6px;
    background: #fff; overflow: hidden; text-align: left; font: inherit; color: inherit;
}
.pcal-cell .pcal-date { font-size: 15px; font-weight: 700; color: #1e3350; }
.pcal-cell .pcal-tag {
    margin-top: auto; align-self: flex-start;
    font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
    padding: 3px 8px; border-radius: 999px; background: #eef2f6;
}

.pcal-cell.out { background: #f4f7fa; }
.pcal-cell.out .pcal-date { color: #97a9bd; }
.pcal-cell.today .pcal-date::after { content: " • today"; font-size: 14px; font-weight: 700; color: #2f80b4; }

.pcal-cell.closed { background: repeating-linear-gradient(45deg, #f4f7fa, #f4f7fa 7px, #fff 7px, #fff 14px); }
.pcal-cell.closed .pcal-tag { color: #6b819a; background: #eef2f6; }

.pcal-cell.bookable { cursor: pointer; transition: background-color .12s, box-shadow .12s; }
.pcal-cell.bookable:hover { background: #f0f7fb; box-shadow: inset 0 0 0 1px #bcd7e8; }
.pcal-cell.open  { border-left: 4px solid #1f9d63; }
.pcal-cell.open  .pcal-tag { color: #14663f; background: #dff2e8; }
.pcal-cell.full  { border-left: 4px solid #d0463b; background: #fdf4f3; }
.pcal-cell.full  .pcal-tag { color: #a5352c; background: #f7dedb; }
.pcal-cell.selected { outline: 2px solid #2f80b4; outline-offset: -2px; position: relative; z-index: 1; }

/* Day-detail panel */
.pcal-day-head { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
.pcal-day-head h2 { font-size: 16px; color: #16263c; }
.pcal-day-note { font-size: 12px; color: #5c6b7a; margin-bottom: 12px; }
.pcal-slots { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(168px, 100%), 1fr)); gap: 10px; }
.pcal-slot {
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px;
    min-height: 52px; padding: 12px 8px; border-radius: 8px; border: 1px solid #d6dee4;
    font-size: 13px; font-weight: 600; text-decoration: none; white-space: nowrap;
}
.pcal-slot small { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
/* Not tag-scoped to `a` — the Reschedule modals' day-detail panel renders
   free slots as `<button>`s (they set hidden fields instead of navigating),
   so scoping this to `a.pcal-slot.free` left every free slot in that modal
   uncoloured white (bug found 2026-09). Free slots show only the time — the
   per-slot "N of 5 open" chair-capacity count was removed 2026-09 (the user
   found it confusing next to a plain time); `min-height` above keeps a free
   slot's single-line box the same height as a taken slot's two-line one. */
.pcal-slot.free { color: #14663f; background: #eaf7f0; border-color: #1f9d63; cursor: pointer; }
.pcal-slot.free:hover { background: #1f9d63; color: #fff; }
.pcal-slot.taken { color: #9aa7b2; background: #f4f7fa; border-style: dashed; }
.pcal-slot.taken small { color: #b3423a; }

/* Dark mode (2026-09) — hand-written CSS predating Tailwind, so plain
   `html.dark` overrides rather than `dark:` variants; same dgt-ink-scale
   surfaces/borders/text and translucent-accent-tint chips as
   includes/calendar_style.php (the staff calendar's dark pass) — saturated
   accent colours (open/full border-lefts, the free-slot hover fill) are
   left as-is since they already pop against a dark surface. */
html.dark .pcal-toolbar h2 { color: #e9edf1; }
html.dark .pcal-nav-btn { background: rgba(255,255,255,.08); color: #e9edf1; }
html.dark .pcal-nav-btn:hover { background: rgba(255,255,255,.15); }
html.dark .pcal-legend { color: #8a98a4; }
html.dark .pcal-legend .lg-open { background: rgba(31,157,99,.25); }
html.dark .pcal-legend .lg-full { background: rgba(208,70,59,.25); }
html.dark .pcal-legend .lg-closed { background: repeating-linear-gradient(45deg, #1d232b, #1d232b 4px, #262e36 4px, #262e36 8px); border-color: rgba(255,255,255,.15); }
html.dark .pcal-dow { color: #8a98a4; }
html.dark .pcal-cell { border-color: rgba(255,255,255,.08); background: #262e36; }
html.dark .pcal-cell .pcal-date { color: #e9edf1; }
html.dark .pcal-cell .pcal-tag { background: rgba(255,255,255,.08); }
html.dark .pcal-cell.out { background: #1d232b; }
html.dark .pcal-cell.out .pcal-date { color: #647380; }
html.dark .pcal-cell.closed { background: repeating-linear-gradient(45deg, #1d232b, #1d232b 7px, #262e36 7px, #262e36 14px); }
html.dark .pcal-cell.closed .pcal-tag { color: #8a98a4; background: rgba(255,255,255,.08); }
html.dark .pcal-cell.bookable:hover { background: rgba(0,108,192,.14); box-shadow: inset 0 0 0 1px rgba(102,167,224,.4); }
/* `html.dark .pcal-cell` above sets `border-color` (all four sides), which —
   despite looking less specific than `.pcal-cell.open`/`.full`'s two-class
   `border-left` shorthand — actually wins the cascade: both have 2 classes,
   but the dark rule's extra `html` type selector tips the specificity
   tie-break in its favour (0,2,1 beats 0,2,0), silently overriding the
   green/red border-left-color to near-invisible white on every day cell.
   Same specificity-gotcha class already documented elsewhere in this app
   (Swiper vs. Tailwind's late-injected CDN styles) — fixed the same way,
   with an explicit override that outranks it. */
html.dark .pcal-cell.open { border-left-color: #1f9d63; }
html.dark .pcal-cell.full { border-left-color: #d0463b; }
html.dark .pcal-cell.open .pcal-tag { color: #5fd39c; background: rgba(31,157,99,.22); }
html.dark .pcal-cell.full { background: rgba(208,70,59,.14); }
html.dark .pcal-cell.full .pcal-tag { color: #e8817a; background: rgba(208,70,59,.22); }
html.dark .pcal-day-head h2 { color: #e9edf1; }
html.dark .pcal-day-note { color: #8a98a4; }
html.dark .pcal-slot { border-color: rgba(255,255,255,.12); }
html.dark .pcal-slot.free { color: #5fd39c; background: rgba(31,157,99,.18); }
html.dark .pcal-slot.taken { color: #647380; background: #1d232b; }
html.dark .pcal-slot.taken small { color: #e8817a; }

/* Modal modifier (2026-09) — the same month grid embedded inside a modal
   instead of a full page, used by the receptionist + patient Reschedule
   modals (modules/receptionist/dashboard.php, modules/patient/appointments)
   so a reschedule shows the identical green/red/hatched availability language
   as this Calendar View page. The dialog itself is widened (below) to give it
   room, so this only trims the toolbar/legend slightly — cells and the
   day-detail slot boxes keep close to the full page's own sizing (a first
   pass shrank both hard and read as too cramped). */
#modalRoot > [role="dialog"]:has(.rs-pcal) { width: min(1040px, calc(100% - 32px)) !important; }
/* Phones: the booking wizard / reschedule / walk-in modals become a
   full-screen sheet (edge to edge, full height) instead of a small floating
   card with a short scroll window, so the form and calendar get the room. */
@media (max-width: 639.98px) {
    #modalRoot > [role="dialog"]:has(.rs-pcal),
    #modalRoot > [role="dialog"]:has(.wiz-steps) {
        width: 100% !important; margin: 0 !important; min-height: 100dvh;
        border-radius: 0; box-shadow: none;
    }
    #modalRoot > [role="dialog"]:has(.rs-pcal) .modal-body,
    #modalRoot > [role="dialog"]:has(.wiz-steps) .modal-body {
        max-height: calc(100dvh - 144px); padding: 16px;
    }
    #modalRoot > [role="dialog"]:has(.rs-pcal) .modal-foot,
    #modalRoot > [role="dialog"]:has(.wiz-steps) .modal-foot { padding-bottom: max(12px, env(safe-area-inset-bottom)); }
    #modalRoot > [role="dialog"]:has(.wiz-steps) .modal-foot > * { flex: 1 1 0; }
}
.rs-pcal .pcal-toolbar { margin-bottom: 10px; }
.rs-pcal .pcal-toolbar h2 { font-size: 15px; }
.rs-pcal .pcal-legend { margin-bottom: 10px; }
.rs-pcal .pcal-cell { min-height: 92px; }
.rs-pcal .pcal-slots { grid-template-columns: repeat(auto-fill, minmax(min(150px, 100%), 1fr)); }
.rs-pcal .pcal-slot { cursor: pointer; font-size: 12px; }
/* Picking a slot fills it solid green, the same colour its own hover state
   already uses — a picked slot should read as "chosen", not merely "hovered
   once"; an outline over the pale free-slot tint (the first version) read too
   subtle next to the plain hover. */
.rs-pcal .pcal-slot.free.selected-slot { background: #1f9d63; border-color: #1f9d63; color: #fff; }

/* Phones (2026-10): seven columns must fit a 320px screen. Columns may shrink
   below their content (minmax(0,1fr)), cells get shorter, and the day tag
   shrinks to a coloured dot-sized pill so the date number stays readable. */
.pcal-grid { grid-template-columns: repeat(7, minmax(0, 1fr)); }
.pcal-toolbar { flex-wrap: wrap; }
@media (max-width: 640px) {
    .pcal-toolbar h2, .rs-pcal .pcal-toolbar h2 { min-width: 0; flex: 1; font-size: 15px; }
    .pcal-dow { font-size: 10.5px; letter-spacing: 0; padding: 7px 0; }
    /* Roomy, near-square cells: a comfortable tap target on any phone. */
    .pcal-cell, .rs-pcal .pcal-cell { min-height: 0; aspect-ratio: 1 / 1.5; padding: 9px 6px; gap: 4px; }
    .pcal-cell .pcal-date { font-size: 17px; }
    .pcal-cell.today .pcal-date::after { content: ""; }
    .pcal-cell.today .pcal-date { color: #2f80b4; text-decoration: underline; text-underline-offset: 3px; }
    /* The cell colour + legend already say Available / Fully Booked / Closed. */
    .pcal-cell .pcal-tag { display: none; }
}

/* ===== Restyle (2026-10-04): square cells, soft status fills, dot legend =====
   Overrides everything above. Every day cell is a 1:1 square with a 6px gap;
   status is a soft fill + border (no thick left line): Available = green,
   Fully Booked = rose, Closed = grey with the date struck through, Selected =
   blue. The toolbar, legend and grid share one centred column (max 600px) so
   squares stay a sensible size on a wide screen. */
.pcal-toolbar, .pcal-legend, .pcal-grid { width: 100%; max-width: 600px; margin-left: auto; margin-right: auto; }
.pcal-grid { gap: 6px; }
.pcal-dow-row { margin-bottom: 2px; }
.pcal-dow { padding: 4px 0; letter-spacing: .3px; }
.pcal-cell, .rs-pcal .pcal-cell {
    aspect-ratio: 1 / 1; min-height: 0; margin: 0; padding: 4px;
    border: 1px solid #e2e8f0; border-radius: 10px; background: #fff;
    align-items: center; justify-content: center; gap: 2px; text-align: center;
}
.pcal-cell .pcal-date { font-size: 15px; line-height: 1; }
.pcal-cell .pcal-tag {
    margin-top: 0; align-self: center; padding: 0; background: none !important;
    font-size: 9px; letter-spacing: .2px; line-height: 1.1; white-space: nowrap;
}
.pcal-cell.open  { border: 1px solid #a7f3d0; background: #ecfdf5; }
.pcal-cell.open  .pcal-date, .pcal-cell.open .pcal-tag { color: #065f46; }
.pcal-cell.full  { border: 1px solid #fecdd3; background: #fff1f2; }
.pcal-cell.full  .pcal-date, .pcal-cell.full .pcal-tag { color: #9f1239; }
.pcal-cell.closed { border-color: #e2e8f0; background: #f1f5f9; }
.pcal-cell.closed .pcal-date { color: #94a3b8; text-decoration: line-through; }
.pcal-cell.closed .pcal-tag { color: #94a3b8; }
.pcal-cell.out { border-color: transparent; background: transparent; }
.pcal-cell.out .pcal-date { color: #cbd5e1; text-decoration: none; }
.pcal-cell.bookable:hover { box-shadow: 0 0 0 2px #6ee7b7; background: #d1fae5; }
.pcal-cell.today .pcal-date::after { content: ""; }
.pcal-cell.today .pcal-date { text-decoration: underline; text-decoration-thickness: 2px; text-underline-offset: 4px; }
.pcal-cell.selected, .pcal-cell.selected:hover {
    outline: none; border: 2px solid #3b82f6; background: #eff6ff; box-shadow: none;
}
.pcal-cell.selected .pcal-date, .pcal-cell.selected .pcal-tag { color: #2563eb; }
/* "Today" label under today's date, in every state. */
.pcal-cell .pcal-tag.pcal-tag-today { color: #2563eb !important; font-weight: 700; }
html.dark .pcal-cell .pcal-tag.pcal-tag-today { color: #93c5fd !important; }
@media (min-width: 768px) { .pcal-cell.today .pcal-date { text-decoration: none; } }
/* Every date number sits dead centre: a tag (Today / Closed / Fully Booked)
   hangs just under it instead of pushing the number up. */
.pcal-cell { position: relative; }
.pcal-cell .pcal-tag { position: absolute; left: 2px; right: 2px; top: calc(50% + 13px); margin: 0; overflow: hidden; text-overflow: ellipsis; }
/* Legend: round dots that wrap on small screens. */
.pcal-legend { gap: 8px 16px; font-size: 12px; }
.pcal-legend i { width: 10px; height: 10px; border-radius: 999px; border: 0; }
.pcal-legend .lg-open   { background: #34d399; }
.pcal-legend .lg-full   { background: #fb7185; }
.pcal-legend .lg-closed { background: #cbd5e1; }
@media (max-width: 640px) {
    .pcal-grid { gap: 4px; }
    .pcal-cell, .rs-pcal .pcal-cell { aspect-ratio: 1 / 1; padding: 2px; border-radius: 8px; }
    .pcal-cell .pcal-date { font-size: 15px; }
}
/* Dark mode equivalents. */
html.dark .pcal-cell { border-color: rgba(255,255,255,.08); background: #262e36; }
html.dark .pcal-cell.open { border-color: rgba(52,211,153,.35); background: rgba(16,185,129,.12); }
html.dark .pcal-cell.open .pcal-date, html.dark .pcal-cell.open .pcal-tag { color: #6ee7b7; }
html.dark .pcal-cell.full { border-color: rgba(251,113,133,.35); background: rgba(244,63,94,.12); }
html.dark .pcal-cell.full .pcal-date, html.dark .pcal-cell.full .pcal-tag { color: #fda4af; }
html.dark .pcal-cell.closed { border-color: rgba(255,255,255,.06); background: #1d232b; }
html.dark .pcal-cell.closed .pcal-date, html.dark .pcal-cell.closed .pcal-tag { color: #647380; }
html.dark .pcal-cell.out { border-color: transparent; background: transparent; }
html.dark .pcal-cell.out .pcal-date { color: #4a5661; }
html.dark .pcal-cell.bookable:hover { box-shadow: 0 0 0 2px rgba(52,211,153,.6); background: rgba(16,185,129,.2); }
html.dark .pcal-cell.selected, html.dark .pcal-cell.selected:hover { border: 2px solid #60a5fa; background: rgba(59,130,246,.18); }
html.dark .pcal-cell.selected .pcal-date, html.dark .pcal-cell.selected .pcal-tag { color: #93c5fd; }
html.dark .pcal-legend .lg-open { background: #34d399; }
html.dark .pcal-legend .lg-full { background: #fb7185; }
html.dark .pcal-legend .lg-closed { background: #647380; }

/* Calendar View page shell (2026-10): compact cells inside the centred card.
   The calendar column is capped so cells stay ~48-56px on tablet/desktop,
   the day tags are hidden (the legend explains the colours), and the time
   slots stack in the narrow right column on desktop. */
.pcal-shell .pcal-toolbar, .pcal-shell .pcal-legend, .pcal-shell .pcal-grid { max-width: none; }
.pcal-shell .pcal-cell .pcal-tag { display: none; }
.pcal-shell .pcal-toolbar h2 { min-width: 0; flex: 1; }
.pcal-shell .pcal-legend { font-size: 12px; }
.pcal-shell .pcal-slots { grid-template-columns: repeat(auto-fill, minmax(min(150px, 100%), 1fr)); }
/* Tablets and desktops: a big calendar that fills the page (phones keep the
   compact squares above). Cells become wider than tall, the date grows, and
   the Closed / Fully Booked labels come back under the date. */
@media (min-width: 768px) {
    .pcal-shell .pcal-grid { gap: 8px; }
    .pcal-shell .pcal-cell { aspect-ratio: 4 / 3; border-radius: 12px; gap: 4px; }
    .pcal-shell .pcal-cell .pcal-date { font-size: 20px; }
    .pcal-shell .pcal-cell .pcal-tag { display: block; font-size: 10.5px; }
    .pcal-shell .pcal-dow { font-size: 12.5px; padding: 6px 0; }
    .pcal-shell .pcal-toolbar h2 { font-size: 19px; }
    .pcal-shell .pcal-legend { font-size: 13px; }
    .pcal-shell .pcal-nav-btn { width: 36px; height: 36px; font-size: 18px; }
    .pcal-shell .pcal-slots { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .pcal-shell .pcal-slot { font-size: 14px; min-height: 60px; }
}
/* Tablets held upright (iPad portrait): plenty of height, so square cells
   and bigger time slots fill the screen instead of leaving a blank lower half. */
@media (min-width: 768px) and (max-width: 1279.98px) and (orientation: portrait) {
    .pcal-shell .pcal-cell { aspect-ratio: 1 / 1; }
    .pcal-shell .pcal-cell .pcal-date { font-size: 24px; }
    .pcal-shell .pcal-cell .pcal-tag { font-size: 11.5px; }
    .pcal-shell .pcal-grid { gap: 10px; }
    .pcal-shell .pcal-slot { min-height: 72px; font-size: 15px; }
    .pcal-shell .pcal-day-head h2 { font-size: 18px; }
}
@media (min-width: 1280px) {
    /* Full-width calendar (2026-10-10): the times open in a pop-up, so the
       month uses the whole card; wide, short cells keep it on one screen. */
    .pcal-shell .pcal-cell { aspect-ratio: 2 / 1; }
    .pcal-shell .pcal-day-head h2 { font-size: 18px; }
    .pcal-shell .pcal-slots { grid-template-columns: 1fr; gap: 10px; }
    .pcal-shell .pcal-slot { flex-direction: row; justify-content: space-between; min-height: 56px; padding: 12px 18px; font-size: 15px; }
}
/* Modal calendars (walk-in, reschedule, booking wizard) on tablets and up:
   use the modal's full width with wider cells and bigger dates, like the
   Calendar View page. Phones keep the compact squares. */
@media (min-width: 768px) {
    .rs-pcal .pcal-toolbar, .rs-pcal .pcal-legend, .rs-pcal .pcal-grid { max-width: none; }
    .rs-pcal .pcal-grid { gap: 8px; }
    .rs-pcal .pcal-cell { aspect-ratio: 16 / 10; border-radius: 12px; gap: 4px; }
    .rs-pcal .pcal-cell .pcal-date { font-size: 20px; }
    .rs-pcal .pcal-cell .pcal-tag { font-size: 10.5px; }
    .rs-pcal .pcal-dow { font-size: 12.5px; padding: 6px 0; }
    .rs-pcal .pcal-toolbar h2 { font-size: 18px; }
    .rs-pcal .pcal-legend { font-size: 13px; }
    .rs-pcal .pcal-nav-btn { width: 36px; height: 36px; font-size: 18px; }
    .rs-pcal .pcal-slot { font-size: 14px; min-height: 56px; }
}
@media (min-width: 1536px) {
    .rs-pcal .pcal-cell .pcal-date { font-size: 22px; }
    .pcal-shell .pcal-cell .pcal-date { font-size: 22px; }
    .pcal-shell .pcal-cell .pcal-tag { font-size: 11.5px; }
}
/* ===== Time-slot pop-up + picked date (2026-10-10, adviser feedback) =====
   Clicking a date opens the times in a centred pop-up (window.openSlotPopup
   in app.js). The date the patient picked a time on stays green on the
   calendar with the chosen time under it, so they can see it after closing. */
.slot-pop .pcal-slots { grid-template-columns: repeat(auto-fill, minmax(min(150px, 100%), 1fr)); gap: 8px; }
.slot-pop .pcal-slot { min-height: 56px; font-size: 13.5px; }
.slot-pop .pcal-slot.free.selected-slot { background: #1f9d63; border-color: #1f9d63; color: #fff; }
.slot-pop .pcal-day-note { margin-bottom: 0; }
.pcal-cell.picked, .pcal-cell.picked:hover { border: 2px solid #16a34a; background: #bbf7d0; box-shadow: none; }
.pcal-cell.picked .pcal-date { color: #14532d; font-weight: 800; }
.pcal-cell .pcal-pick {
    position: absolute; left: 2px; right: 2px; top: calc(50% + 11px); display: block;
    font-size: 8.5px; font-weight: 800; line-height: 1.1; letter-spacing: -.2px; color: #15803d;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.pcal-cell .pcal-pick .pk-long { display: none; }
@media (min-width: 768px) {
    .pcal-cell .pcal-pick { font-size: 11px; letter-spacing: 0; top: calc(50% + 14px); }
    .pcal-cell .pcal-pick .pk-long { display: inline; }
    .pcal-cell .pcal-pick .pk-short { display: none; }
}
html.dark .pcal-cell.picked, html.dark .pcal-cell.picked:hover { border-color: #22c55e; background: rgba(34,197,94,.28); }
html.dark .pcal-cell.picked .pcal-date, html.dark .pcal-cell .pcal-pick { color: #86efac; }
/* The green "Selected" summary under the calendar. */
.pcal-picked-box { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; border: 1px solid #86efac; background: #f0fdf4; border-radius: 10px; padding: 10px 12px; color: #14532d; font-size: 13px; }
.pcal-picked-box strong { font-weight: 700; }
html.dark .pcal-picked-box { border-color: rgba(34,197,94,.4); background: rgba(34,197,94,.12); color: #bbf7d0; }
</style>
