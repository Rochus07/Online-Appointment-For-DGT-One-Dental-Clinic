<?php
/**
 * Scoped CSS for the patient Appointments page — the 3-step booking wizard
 * (`.wiz-*`), the reservation-fee step (`.pay-summary*`, `.pm-*`, `.rcpt-drop*`),
 * the "Appointment Submitted" status card (`.submitted*`, `.spin`) and the
 * appointment-card grid (`.appt-*`, `.tab-n`).
 *
 * This is a large self-contained visual widget kept as hand-written CSS rather
 * than Tailwind utilities, scoped to the one page that uses it. Emitted inside
 * $PAGE_CONTENT so it applies on the full render and the nav.js partial swap.
 */
declare(strict_types=1);
?>
<style>
/* ---- 3-step wizard indicator ---- */
.wiz-steps { display: flex; align-items: flex-start; margin: 2px 0 20px; }
.wiz-step { flex: 1; display: flex; flex-direction: column; align-items: center; position: relative; }
.wiz-step::before {
    content: ""; position: absolute; top: 14px; right: 50%; width: 100%; height: 2px; background: #d6dee4;
}
.wiz-step:first-child::before { display: none; }
.wiz-step .wiz-num {
    width: 30px; height: 30px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; line-height: 1; text-align: center;
    background: #fff; color: #8a98a4;
    border: 2px solid #d6dee4; position: relative; z-index: 1;
}
.wiz-step .wiz-lbl { font-size: 11px; font-weight: 600; color: #8a98a4; margin-top: 6px; text-align: center; }
.wiz-step.on .wiz-num   { background: #006cc0; border-color: #006cc0; color: #fff; }
.wiz-step.on .wiz-lbl   { color: #0056a0; }
.wiz-step.done .wiz-num { background: #1f9d63; border-color: #1f9d63; color: #fff; }
.wiz-step.done .wiz-lbl { color: #1f9d63; }
.wiz-step.on::before, .wiz-step.done::before { background: #006cc0; }

/* ---- Payment step: appointment summary ---- */
.pay-summary {
    background: #f5f7f9; border: 1px solid #e9edf1; border-radius: 8px;
    padding: 12px 14px; display: flex; flex-direction: column; gap: 8px;
}
.pay-summary .row-between { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.pay-summary .row-between span { color: #647380; font-size: 13px; }
.pay-summary-h { font-weight: 700; font-size: 13px; color: #102f51; margin-bottom: 2px; }
.pay-summary-total { border-top: 1px solid #d6dee4; padding-top: 8px; margin-top: 2px; }
.pay-summary-total span, .pay-summary-total strong { font-size: 15px !important; color: #102f51 !important; font-weight: 700; }

/* ---- Payment method picker ---- */
.pm-cards { display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px; }
.pm-card {
    display: flex; align-items: flex-start; gap: 10px; padding: 11px 13px;
    border: 1px solid #d6dee4; border-radius: 8px; cursor: pointer;
}
.pm-card.on { border-color: #006cc0; background: #e9f2fb; }
.pm-card input { margin-top: 2px; }
.pm-card-txt { display: flex; flex-direction: column; }
.pm-card-t { font-weight: 600; font-size: 13px; color: #102f51; }
.pm-card-s { font-size: 12px; color: #647380; }

.pm-box { border: 1px solid #006cc0; border-radius: 8px; background: #fff; padding: 14px 16px; }
.pm-qr { text-align: center; margin-bottom: 10px; }
.pm-qr-cap { font-size: 12px; font-weight: 600; color: #647380; margin-bottom: 6px; }
.pm-qr img { width: 180px; height: 180px; object-fit: contain; border: 1px solid #d6dee4; border-radius: 8px; padding: 6px; background: #fff; margin: 0 auto; }
.pm-qr-empty span { display: block; font-size: 12px; color: #8a98a4; padding: 24px 10px; border: 1px dashed #d6dee4; border-radius: 8px; }
.pm-acct { text-align: center; font-size: 13px; color: #647380; margin-bottom: 10px; }
.pm-acct strong { color: #102f51; }
.pm-bank-acct { border: 1px solid #d6dee4; border-radius: 8px; background: #f5f7f9; padding: 10px 12px; margin-bottom: 10px; }
.pm-bank-row { display: flex; justify-content: space-between; gap: 12px; font-size: 12.5px; padding: 3px 0; }
.pm-bank-row span { color: #8a98a4; }
.pm-bank-row strong { color: #102f51; text-align: right; }
.pm-instructions { background: #e9f2fb; border-radius: 8px; padding: 10px 12px; font-size: 12.5px; color: #102f51; }
.pm-instructions ol { margin: 5px 0 0; padding-left: 20px; line-height: 1.6; list-style: decimal; }
.pm-instructions ol li { list-style: decimal; display: list-item; }

/* ---- Receipt drop area ---- */
.rcpt-drop { border: 1px dashed #b4c1cb; border-radius: 8px; padding: 16px; text-align: center; background: #f5f7f9; }
.rcpt-drop-prev { min-height: 90px; display: grid; place-items: center; color: #102f51; font-size: 13px; font-weight: 500; }
.rcpt-drop-prev img { max-height: 160px; max-width: 100%; border-radius: 8px; border: 1px solid #d6dee4; }

/* ---- Appointment card grid ---- */
.appt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(330px, 100%), 1fr)); gap: 16px; }
.appt-c { border: 1px solid #d6dee4; border-radius: 12px; padding: 18px 20px; background: #fff; box-shadow: 0 1px 2px rgba(16,47,81,.06); }
.appt-c-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 10px; }
.appt-c h3 { font-size: 16px; color: #102f51; font-weight: 600; }
.appt-c-dr { color: #647380; font-size: 13px; margin-top: 2px; }
.appt-c-line { display: flex; align-items: center; gap: 7px; color: #647380; font-size: 13px; margin-top: 8px; }
.appt-c-line svg { width: 15px; height: 15px; flex-shrink: 0; stroke: #8a98a4; }
.appt-c-for { margin-top: 12px; padding-top: 10px; border-top: 1px solid #e9edf1; font-size: 13px; color: #647380; }
.appt-c-reason { margin-top: 12px; font-size: 12.5px; line-height: 1.5; color: #d0463b; background: #fbe6e4; padding: 9px 11px; border-radius: 8px; }
.appt-c-notice { margin-top: 12px; font-size: 12.5px; line-height: 1.5; color: #d98a10; background: #fbf0dd; padding: 9px 11px; border-radius: 8px; }
.appt-c-actions { margin-top: 14px; display: flex; gap: 8px; flex-wrap: wrap; }
.appt-count { color: #8a98a4; font-size: 13px; margin-top: 3px; }
.tab .tab-n { display: inline-block; margin-left: 6px; padding: 0 6px; border-radius: 999px; background: #e9edf1; color: #647380; font-size: 11px; }
.tab.active .tab-n { background: rgba(255,255,255,.25); color: #fff; }

/* ---- "Appointment Submitted" status card ---- */
.submitted { text-align: center; }
.submitted .kv {
    display: grid; grid-template-columns: 150px 1fr; gap: 6px 14px;
    font-size: 13px; text-align: left; margin: 4px 0 0;
}
.submitted .kv dt { color: #8a98a4; }
.submitted .kv dd { color: #102f51; font-weight: 500; }
.submitted .alert { padding: 11px 14px; border-radius: 8px; font-size: 13px; margin: 14px 0 0; }
.submitted .alert-warn  { background: #fbf0dd; color: #d98a10; }
.submitted .alert-ok    { background: #e2f4ec; color: #1f9d63; }
.submitted .alert-error { background: #fbe6e4; color: #d0463b; }
.submitted-sub { color: #647380; font-size: 13px; margin: -6px 0 14px; }
.submitted-icon {
    width: 72px; height: 72px; border-radius: 50%; margin: 0 auto 16px;
    display: grid; place-items: center; background: #fbf0dd; color: #d98a10;
}
.submitted-icon svg { width: 34px; height: 34px; }
.submitted-icon.ok  { background: #e2f4ec; color: #1f9d63; }
.submitted-icon.err { background: #fbe6e4; color: #d0463b; }
.submitted-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.submitted-ref { font-size: 12px; color: #647380; }
.submitted-ref strong { color: #102f51; font-variant-numeric: tabular-nums; }
.submitted-badge {
    display: inline-block; padding: 6px 16px; border-radius: 999px; font-weight: 700; font-size: 13px;
    background: #fbf0dd; color: #d98a10;
}
.submitted-badge.ok { background: #e2f4ec; color: #1f9d63; }
.submitted-badge.err { background: #fbe6e4; color: #d0463b; }
.submitted-card {
  display: flex; align-items: center; gap: 16px; text-align: left;
  background: #102f51; color: #fff; border-radius: 12px; padding: 16px 20px;
  box-shadow: 0 6px 18px rgba(16,47,81,.22);
}
.submitted-card-ico { flex: none; color: #fff; }
.submitted-card-ico svg { width: 34px; height: 34px; display: block; }
.submitted-card dl { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 4px 10px; font-size: 14px; }
.submitted-card dl > div { display: contents; }
.submitted-card dt { font-weight: 700; }
.submitted-card dd { margin: 0; color: rgba(255,255,255,.88); }
.submitted-notes { margin-top: 16px; font-size: 13px; color: #33465b; }
.submitted-notes-h { font-weight: 700; color: #102f51; margin: 0 0 6px; }
.submitted-notes ol { list-style: decimal; text-align: left; display: inline-block; margin: 0 auto; padding-left: 20px; }
@media (min-width: 640px) { .submitted-notes li { white-space: nowrap; } }
.submitted-notes li { margin: 0 0 6px; padding-left: 4px; }
.submitted-callout { margin: 10px 0 0; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; color: #006cc0; font-size: 12.5px; }
.submitted-thanks { margin: 14px 0 0; color: #33465b; }
.submitted-poll { margin-top: 14px; padding: 10px; background: #f5f7f9; border-radius: 8px; }
.submitted-poll-t { font-size: 12px; color: #647380; }
.submitted-poll-s { font-size: 12px; color: #102f51; margin-top: 3px; display: flex; align-items: center; justify-content: center; gap: 6px; }
.spin {
    width: 12px; height: 12px; border: 2px solid #b4c1cb; border-top-color: #006cc0;
    border-radius: 50%; display: inline-block; animation: dgt-spin .8s linear infinite;
}
@keyframes dgt-spin { to { transform: rotate(360deg); } }

/* Dark mode (2026-09) — hand-written CSS predating Tailwind, so plain
   `html.dark` overrides. Saturated accent colours (step dots, card borders,
   the free-slot green, the spinner's active edge) are left as-is — they
   already read fine on a dark surface. The "-soft" tint backgrounds (warn/
   ok/danger alerts and badges) switch to the same accent at ~20% opacity,
   same pattern used everywhere else. `.pm-qr img`'s white background is
   deliberately NOT overridden — a QR code needs a light background with dark
   modules to stay scannable regardless of theme. */
html.dark .wiz-step::before { background: rgba(255,255,255,.15); }
html.dark .wiz-step .wiz-num { background: #262e36; border-color: rgba(255,255,255,.2); }
html.dark .wiz-step.on .wiz-lbl { color: #4d9fe0; }
html.dark .pay-summary { background: #1d232b; border-color: rgba(255,255,255,.1); }
html.dark .pay-summary .row-between span { color: #97a9bd; }
html.dark .pay-summary-h { color: #e9edf1; }
html.dark .pay-summary-total { border-top-color: rgba(255,255,255,.15); }
html.dark .pay-summary-total span, html.dark .pay-summary-total strong { color: #e9edf1 !important; }
html.dark .pm-card { border-color: rgba(255,255,255,.15); }
html.dark .pm-card.on { background: rgba(0,108,192,.16); }
html.dark .pm-card-t { color: #e9edf1; }
html.dark .pm-card-s { color: #97a9bd; }
html.dark .pm-box { background: #1d232b; }
html.dark .pm-qr-cap { color: #97a9bd; }
html.dark .pm-qr img { border-color: rgba(255,255,255,.15); }
html.dark .pm-qr-empty span { border-color: rgba(255,255,255,.2); }
html.dark .pm-acct { color: #97a9bd; }
html.dark .pm-acct strong { color: #e9edf1; }
html.dark .pm-bank-acct { border-color: rgba(255,255,255,.12); background: #1d232b; }
html.dark .pm-bank-row strong { color: #e9edf1; }
html.dark .pm-instructions { background: rgba(0,108,192,.16); color: #9dc2f0; }
html.dark .rcpt-drop { border-color: rgba(255,255,255,.2); background: #1d232b; }
html.dark .rcpt-drop-prev { color: #e9edf1; }
html.dark .rcpt-drop-prev img { border-color: rgba(255,255,255,.15); }
html.dark .appt-c { border-color: rgba(255,255,255,.1); background: #262e36; box-shadow: 0 1px 2px rgba(0,0,0,.35); }
html.dark .appt-c h3 { color: #e9edf1; }
html.dark .appt-c-dr { color: #97a9bd; }
html.dark .appt-c-line { color: #97a9bd; }
html.dark .appt-c-for { border-top-color: rgba(255,255,255,.1); color: #97a9bd; }
html.dark .appt-c-reason { color: #f0a3a9; background: rgba(208,70,59,.2); }
html.dark .appt-c-notice { color: #f0b74a; background: rgba(217,138,16,.2); }
html.dark .tab .tab-n { background: rgba(255,255,255,.1); color: #97a9bd; }
html.dark .submitted .kv dd { color: #e9edf1; }
html.dark .submitted .alert-warn { background: rgba(217,138,16,.2); color: #f0b74a; }
html.dark .submitted .alert-ok { background: rgba(31,157,99,.2); color: #5fd39c; }
html.dark .submitted .alert-error { background: rgba(208,70,59,.2); color: #f0a3a9; }
html.dark .submitted-sub { color: #97a9bd; }
html.dark .submitted-icon { background: rgba(217,138,16,.2); color: #f0b74a; }
html.dark .submitted-icon.ok { background: rgba(31,157,99,.2); color: #5fd39c; }
html.dark .submitted-icon.err { background: rgba(208,70,59,.2); color: #f0a3a9; }
html.dark .submitted-ref { color: #97a9bd; }
html.dark .submitted-ref strong { color: #e9edf1; }
html.dark .submitted-badge { background: rgba(217,138,16,.2); color: #f0b74a; }
html.dark .submitted-badge.ok { background: rgba(31,157,99,.2); color: #5fd39c; }
html.dark .submitted-badge.err { background: rgba(208,70,59,.2); color: #f0a3a9; }
html.dark .submitted-poll { background: #1d232b; }
html.dark .submitted-card { background: #16375f; box-shadow: none; }
html.dark .submitted-notes, html.dark .submitted-thanks { color: #c3cfdb; }
html.dark .submitted-notes-h { color: #e9edf1; }
html.dark .submitted-callout { color: #6cb4ff; }
html.dark .submitted-poll-t { color: #97a9bd; }
html.dark .submitted-poll-s { color: #e9edf1; }
html.dark .spin { border-color: rgba(255,255,255,.2); border-top-color: #006cc0; }
</style>
