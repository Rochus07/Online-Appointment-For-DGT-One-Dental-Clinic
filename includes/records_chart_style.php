<?php
/**
 * Scoped CSS for the Patient Details modal — the FDI tooth chart, the clinical
 * "rec-*" panels and the header edit-actions row. Shared by the dentist and
 * receptionist Patient Records pages (both `require` this inside their
 * $PAGE_CONTENT). This markup is a self-contained visual widget that is not
 * worth translating to Tailwind utilities; it stays as hand-written CSS but is
 * scoped to the pages that use it instead of living in the global style.css.
 *
 * A <style> emitted inside $PAGE_CONTENT is applied on both the full render and
 * the nav.js partial swap (innerHTML-injected <style> nodes take effect).
 */
declare(strict_types=1);
?>
<style>
.modal-head-actions { display: flex; align-items: center; gap: 8px; }
/* Phones: a patient's details pop-up (any dialog with .rec-section) becomes a
   full-screen sheet; the header buttons drop under the title, the two-column
   contact fields stack, and the Appointment History table turns into cards
   (.rtable, see tailwind.php). */
@media (max-width: 639.98px) {
    html #modalRoot > [role="dialog"]:has(.rec-section) { width: 100% !important; margin: 0 !important; min-height: 100dvh; border-radius: 0; box-shadow: none; }
    #modalRoot > [role="dialog"]:has(.rec-section) .modal-body { max-height: none; padding: 16px; }
    #modalRoot > [role="dialog"]:has(.rec-section) .modal-head { flex-wrap: wrap; row-gap: 10px; }
    #modalRoot > [role="dialog"]:has(.rec-section) .modal-head h3 { flex: 1 1 0; min-width: 0; font-size: 16px; }
    #modalRoot > [role="dialog"]:has(.rec-section) .modal-head .modal-close { order: 2; }
    #modalRoot > [role="dialog"]:has(.rec-section) .modal-head-actions { order: 3; flex: 1 1 100%; flex-wrap: wrap; }
    #modalRoot > [role="dialog"]:has(.rec-section) .modal-head-actions > * { flex: 1 1 0; min-height: 40px; }
    #modalRoot > [role="dialog"]:has(.rec-section) .modal-body .grid.grid-cols-2 { grid-template-columns: minmax(0, 1fr); }
    #modalRoot > [role="dialog"]:has(.rec-section) .modal-body .col-span-2 { grid-column: auto; }
}

.rec-section { margin: 0 0 12px; padding-bottom: 7px; border-bottom: 1px solid #c7d2df;
    font-size: 14px; color: #0f1c2e; }
.rec-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 22px; margin-bottom: 4px; }
.rec-field { display: flex; flex-direction: column; gap: 4px; }
.rec-field > label { font-size: 12px; font-weight: 700; color: #48607a; }
.rec-value { padding: 8px 11px; min-height: 36px; font-size: 13px; color: #16263c;
    background: #f4f7fa; border: 1px solid #c7d2df; border-radius: 6px; }

/* widen the patient-details modal so the full-width tooth chart has room —
   !important beats the `!w-[min(880px…)]` utility that openModal({wide}) sets. */
#modalRoot > [role="dialog"]:has(.rec-split) { width: min(1180px, calc(100% - 32px)) !important; }
/* Services Availed: the tooth chart is a side panel of the same pop-up.
   The "Tooth Chart" pill in the modal header (#t_chartBtn) toggles
   .proc-wrap.chart-open: the dialog widens and the chart slides out of its
   right inner edge (.t-clip clips the slide). */
#modalRoot > [role="dialog"]:has(.proc-wrap) { transition: width .3s cubic-bezier(.16,1,.3,1); }
#modalRoot > [role="dialog"]:has(.proc-wrap.chart-open) { width: min(1760px, calc(100% - 32px)) !important; }
/* the chart takes just over half the widened pop-up, so it grows on big screens */
.proc-wrap { --tp-w: 52%; }
.proc-wrap .proc-main { transition: margin-right .3s cubic-bezier(.16,1,.3,1); }
.proc-wrap.chart-open .proc-main { margin-right: var(--tp-w); }
.proc-wrap .t-clip { position: absolute; top: 0; right: 0; bottom: 0; width: var(--tp-w); overflow: hidden;
  border-radius: 0 12px 12px 0; pointer-events: none; }
.proc-wrap .t-panel { width: 100%; height: 100%; transform: translateX(100%); visibility: hidden; pointer-events: auto;
  transition: transform .3s cubic-bezier(.16,1,.3,1), visibility 0s linear .3s; }
.proc-wrap.chart-open .t-panel { transform: translateX(0); visibility: visible; transition: transform .3s cubic-bezier(.16,1,.3,1), visibility 0s; }
/* header pill: darker while the chart is open */
#t_chartBtn[aria-expanded="true"] { background: #0a457e; }
/* legend badges, coloured like the chart's tooth boxes */
.t-lg { background: #fff; border-color: #d5dee8; color: #1e3350; }
.t-lg-resto { background: #f2f7fe; border-color: #a9c4ec; color: #2f5fa8; }
.t-lg-rct { background: #f1faf5; border-color: #9fd6b8; color: #1f8a54; }
.t-lg-missing { background: #fdf3f4; border-color: #efb4b9; color: #bf3d47; }
.t-lg-caries { background: #fbf4e8; border-color: #dfb878; color: #8a5414; }
.t-lg-extract { background: #fff1e8; border-color: #f0a274; color: #c2561a; }
.t-lg-note { background: #f5f0ff; border-color: #c9b5f5; color: #5b21b6; }
.t-lg-healthy { background: #eef2f6; border-color: #b4c1cb; color: #37424b; }
.t-lg-healthy .tooth-svg .tb { fill: #fff; stroke: #4a5661; stroke-width: 1.8; }
html.dark .t-lg { background: #262e36; border-color: rgba(255,255,255,.14); color: #e9edf1; }
html.dark .t-lg-resto { background: rgba(123,167,224,.16); border-color: rgba(123,167,224,.5); color: #9dc2f0; }
html.dark .t-lg-rct { background: rgba(92,191,139,.14); border-color: rgba(92,191,139,.5); color: #6fdba0; }
html.dark .t-lg-missing { background: rgba(229,136,143,.14); border-color: rgba(229,136,143,.5); color: #f0a3a9; }
html.dark .t-lg-caries { background: rgba(223,184,120,.14); border-color: rgba(223,184,120,.5); color: #e8c48a; }
html.dark .t-lg-extract { background: rgba(240,162,116,.14); border-color: rgba(240,162,116,.5); color: #f5b38c; }
html.dark .t-lg-note { background: rgba(167,139,250,.16); border-color: rgba(167,139,250,.5); color: #ddd6fe; }
html.dark .t-lg-healthy { background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.28); color: #e9edf1; }
html.dark .t-lg-healthy .tooth-svg .tb { fill: #eef2f6; stroke: #b4c1cb; }
/* Too narrow for both side by side: the open panel covers the form, and the
   tab tucks inside the dialog's right edge (no room outside it on phones). */
@media (max-width: 1023px) {
  .proc-wrap { --tp-w: 100%; }
  .proc-wrap .t-clip { border-radius: 12px; }
  .proc-wrap.chart-open .proc-main { margin-right: 0; }
}
.t-panel { container-type: inline-size; }
/* Teeth fill the panel: as wide as the panel allows, capped by its height so
   the whole chart fits without a vertical scrollbar (cqh = panel body height). */
.t-panel .t-pbody { container-type: size; display: flex; flex-direction: column; }
.t-panel .t-pbody > #t_board { margin-block: auto; }
/* Phones: the pop-up is a flex column so the body only takes the room left
   between the header and the footer (the footer was cut off), the chart
   starts at the top, and the title is a little smaller. */
@media (max-width: 639px) {
  #modalRoot > [role="dialog"]:has(.proc-wrap) { max-height: calc(100dvh - 24px); display: flex; flex-direction: column; }
  #modalRoot > [role="dialog"]:has(.proc-wrap) > .modal-viewport,
  #modalRoot > [role="dialog"]:has(.proc-wrap) .modal-pane:has(.proc-wrap),
  .proc-wrap, .proc-wrap .proc-main { display: flex; flex-direction: column; min-height: 0; flex: 1 1 auto; }
  #modalRoot > [role="dialog"]:has(.proc-wrap) .modal-track { flex: 1 1 auto; min-height: 0; align-items: stretch; }
  .proc-wrap .proc-main .modal-body { max-height: none !important; flex: 1 1 auto; min-height: 0; }
  .proc-wrap .proc-main .modal-head { gap: 8px; }
  .proc-wrap .proc-main .modal-head h3 { font-size: 16px; line-height: 1.3; }
  .t-panel .t-pbody > #t_board { margin-block: 0; }
  .t-panel .tooth-quad-row .tooth-jaw { flex-basis: auto; margin: 0; } /* stacked jaw label: no 34px "width" turned height */
}
@media (min-width: 1600px) { .proc-wrap { --tp-w: 60%; } }
.t-panel .tooth-box { max-width: clamp(26px, calc((100cqh - 170px) / 2.8), 86px); }
.t-panel .tooth-sec.is-primary .tooth-box, .t-panel .tooth-board:has(.is-primary) .tooth-box { max-width: clamp(22px, calc((100cqh - 325px) / 5.6), 86px); }
.t-panel .tooth-svg { width: 72%; }
.t-panel .tooth-band > span { overflow: visible; text-overflow: clip; margin: 0 1px; }
.t-panel .tooth-board { padding: 4px 0; gap: 14px; border: 0; background: transparent; }
.t-panel .tooth-quad-row { gap: 8px; }
.t-panel .tooth-quad { gap: 2px; }
.t-panel .tooth-jaw { flex-basis: 34px; margin-right: -6px; font-size: 9.5px; }
.t-panel .tooth-sec-head { padding-left: 28px; }
@media (prefers-reduced-motion: reduce) {
  #modalRoot > [role="dialog"]:has(.proc-wrap), .proc-wrap .proc-main, .proc-wrap .t-panel { transition: none !important; }
}

/* clinical fields flow in a full-width multi-column grid, then the tooth chart
   sits on its own full-width row below — no tall skinny side column, no gap. */
.rec-split { display: flex; flex-direction: column; gap: 18px; }
.rec-clinical { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px 16px; }
.rec-clinical .full { grid-column: 1 / -1; }
@media (min-width: 861px) { .rec-clinical .rec-span2 { grid-column: span 2; } }
@media (max-width: 860px) { .rec-clinical .rec-span2 { grid-column: 1 / -1; } }
.rec-chart { display: flex; flex-direction: column; gap: 14px; }
/* view-mode inputs read as filled grey boxes, not editable fields */
.rec-split input:disabled, .rec-split select:disabled, .rec-split textarea:disabled {
    background: #f4f7fa; color: #16263c; -webkit-text-fill-color: #16263c; opacity: 1;
}

/* the chart spans the whole modal width; teeth flex to fill it. Teeth are
   drawn as SVG tooth shapes (2026-10-18) so non-dentists can read it. */
.tooth-board { border: 1px solid #c7d2df; border-radius: 10px;
    padding: 16px; display: flex; flex-direction: column; gap: 22px; }
.tooth-hint { margin: 0; font-size: 12px; color: #2f6fa3; background: #eef5fb; border-radius: 6px; padding: 7px 10px; }
.tooth-sec { display: flex; flex-direction: column; gap: 8px; }
.tooth-sec + .tooth-sec { border-top: 1px dashed #d5dee8; padding-top: 18px; }
.tooth-sec-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 12px; padding-left: 50px; }
.tooth-sec-title { font-size: 13px; font-weight: 700; color: #16263c; }
.tooth-sides { flex: 1 1 100%; display: flex; justify-content: space-between; gap: 12px;
    font-size: 11px; font-weight: 600; color: #6b819a; }
.tooth-quad-row { display: flex; gap: 26px; justify-content: center; align-items: center; }
.tooth-jaw { flex: 0 0 44px; margin-right: -20px; font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .04em; color: #6b819a; }
.tooth-quad { flex: 1 1 0; min-width: 0; display: flex; gap: 6px; justify-content: center; }
.tooth-cell { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; align-items: center; gap: 3px; }
.tooth-num { font-size: 11px; font-weight: 700; color: #6b819a; }
.tooth-box {
    position: relative; width: 100%; max-width: 60px; aspect-ratio: 5 / 7; border: 1px solid #d5dee8; border-radius: 8px;
    background: #fff; line-height: 1; color: #1e3350; cursor: pointer; padding: 4px 2px 2px; font-family: inherit;
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1px;
}
.tooth-quad-row.is-primary .tooth-box, .tooth-sec.is-primary .tooth-box { max-width: 64px; }
.tooth-box:disabled { cursor: default; }
.tooth-box:not(:disabled):hover { border-color: #2f80b4; box-shadow: 0 0 0 2px rgba(47,128,180,.15); }
.tooth-svg { width: 78%; height: auto; max-height: 82%; overflow: visible; }
.tooth-tag { min-height: 11px; font-size: 10px; font-weight: 800; letter-spacing: .02em; }
/* tooth drawing colours (.tooth-svg .tb/.tf/.tr/.tx) live in includes/tailwind.php */
.tooth-box.t-resto { background: #f2f7fe; border-color: #a9c4ec; color: #2f5fa8; }
.tooth-box.t-rct { background: #f1faf5; border-color: #9fd6b8; color: #1f8a54; }
.tooth-box.t-missing { background: #fdf3f4; border-color: #efb4b9; color: #bf3d47; }
.tooth-box.t-caries { background: #fbf4e8; border-color: #dfb878; color: #8a5414; }
.tooth-box.t-extract { background: #fff1e8; border-color: #f0a274; color: #c2561a; }
/* a tooth with quick tags or a note (migration 091): small dot, top right */
.tooth-note-dot { position: absolute; top: 3px; right: 3px; width: 7px; height: 7px; border-radius: 50%;
    background: #6d28d9; box-shadow: 0 0 0 2px #fff; }
.tooth-status-legend .tooth-note-dot { position: static; box-shadow: none; }
.tooth-box.t-pending { outline: 2px solid #6366f1; outline-offset: 1px; }
/* tooth-type caption between the upper and lower jaw */
.tooth-band { display: grid; gap: 6px; }
.tooth-band > span { text-align: center; font-size: 10px; font-weight: 600; color: #7d90a6;
    border-top: 1.5px solid #d5dee8; padding-top: 3px; margin: 0 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
/* Phones: 16 teeth across don't fit, so each jaw's two halves stack (8 per
   line) and gaps shrink; the type caption is hidden (tooltips keep it). */
@media (max-width: 639.98px) {
    .tooth-board { padding: 12px 10px; gap: 18px; }
    .tooth-sec-head { padding-left: 0; }
    .tooth-sides { display: none; }
    .tooth-quad-row { flex-direction: column; gap: 6px; align-items: stretch; }
    .tooth-jaw { flex: none; margin: 0; }
    .tooth-band-row { display: none; }
    .tooth-quad { gap: 4px; }
    .tooth-sec.is-primary .tooth-box { max-width: 48px; }
}
/* iPad mini width: keep one line per jaw but tighten the gaps. */
@media (min-width: 640px) and (max-width: 900px) {
    .tooth-quad-row { gap: 14px; }
    .tooth-quad, .tooth-band { gap: 4px; }
    .tooth-jaw { flex-basis: 40px; margin-right: -8px; }
}

.tooth-status-legend { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 18px;
    font-size: 11.5px; color: #48607a; }
.tooth-status-legend strong { font-size: 12px; color: #16263c; }
.tooth-status-legend span { display: inline-flex; align-items: center; gap: 6px; }
.tooth-status-legend .tooth-svg { width: 16px; max-height: none; }

@media (max-width: 860px) { .rec-clinical { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 560px) { .rec-clinical, .rec-info-grid { grid-template-columns: 1fr; } }

/* Dark mode (2026-09) — hand-written CSS predating Tailwind, so plain
   `html.dark` overrides. The tooth-status tints (resto/rct/missing) keep their
   saturated border colour and switch to a translucent tint of the same hue
   for the fill, same pattern as every other badge/chip in the app. */
html.dark .rec-section { border-bottom-color: rgba(255,255,255,.12); color: #e9edf1; }
html.dark .rec-field > label { color: #97a9bd; }
html.dark .rec-value { color: #e9edf1; background: #262e36; border-color: rgba(255,255,255,.1); }
html.dark .rec-split input:disabled, html.dark .rec-split select:disabled, html.dark .rec-split textarea:disabled {
    background: #262e36; color: #e9edf1; -webkit-text-fill-color: #e9edf1;
}
html.dark .tooth-board { border-color: rgba(255,255,255,.12); }
html.dark .tooth-hint { background: rgba(47,128,180,.18); color: #9dc2f0; }
html.dark .tooth-sec + .tooth-sec { border-top-color: rgba(255,255,255,.12); }
html.dark .tooth-sec-title { color: #e9edf1; }
html.dark .tooth-sides, html.dark .tooth-jaw, html.dark .tooth-num { color: #8a98a4; }
html.dark .tooth-band > span { color: #8a98a4; border-top-color: rgba(255,255,255,.15); }
html.dark .tooth-box { border-color: rgba(255,255,255,.14); background: #262e36; color: #e9edf1; }
html.dark .tooth-box.t-resto { background: rgba(123,167,224,.16); border-color: rgba(123,167,224,.5); color: #9dc2f0; }
html.dark .tooth-box.t-rct { background: rgba(92,191,139,.14); border-color: rgba(92,191,139,.5); color: #6fdba0; }
html.dark .tooth-box.t-missing { background: rgba(229,136,143,.14); border-color: rgba(229,136,143,.5); color: #f0a3a9; }
html.dark .tooth-box.t-caries { background: rgba(223,184,120,.14); border-color: rgba(223,184,120,.5); color: #e8c48a; }
html.dark .tooth-box.t-extract { background: rgba(240,162,116,.14); border-color: rgba(240,162,116,.5); color: #f5b38c; }
html.dark .tooth-note-dot { background: #a78bfa; box-shadow: 0 0 0 2px #262e36; }
html.dark .tooth-status-legend { color: #97a9bd; }
html.dark .tooth-status-legend strong { color: #e9edf1; }
</style>
