<?php
/**
 * Scoped CSS for the cashier Service Invoice (`modules/cashier/billing.php`)
 * and its read-only twin (`modules/shared/receipt.php`) — the `.invoice-sheet`
 * / `.inv-*` / `.or-*` layout plus the landscape one-page `@media print` block.
 *
 * This is the most fragile piece of the old global stylesheet, kept verbatim as
 * hand-written CSS (var(--*) resolved to hex) but scoped to the two pages that
 * render an invoice, instead of living in style.css. Emitted inside
 * $PAGE_CONTENT so it applies on the full render, the nav.js partial swap, and
 * at print time.
 *
 * NOTE: the shell (sidebar/header) is already hidden on print by `print:hidden`
 * utilities in layout.php; the content wrapper gets `print:px-0 print:py-0`.
 */
declare(strict_types=1);
?>
<!-- Phones and iPads have no Arial Narrow / Rockwell: load close web stand-ins so the
     paper-invoice copy keeps its narrow letters on every device. -->
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=Roboto+Slab:wght@800&display=swap">
<style>
/* The invoice must render as plain light paper always — same reasoning in two
   parts:
   1) `html.dark` sets `color-scheme: dark` app-wide so browser chrome
      (scrollbars, date pickers) follows the toggle on ordinary pages — but
      that also means any input/select in here with no explicit background
      (the line-item cells, the raw inline-styled Withholding Tax / Received
      Amount fields) silently renders with the browser's native DARK form
      control skin (black fill, white text). `color-scheme: light` undoes
      that for everything inside the card.
   2) `layout.php`'s `<body>` carries `dark:text-dgt-ink-100` (a pale grey
      meant for a dark background) as its default text colour, and plain
      elements in here that never set their own `color` — line-item values,
      the totals-panel figures — inherit it, reading as washed-out grey text
      on the invoice's white paper. An explicit `color` here resets that
      inheritance for the whole card. This one isn't inside a `html.dark`
      guard on purpose: `@media print` doesn't touch `color` for these plain
      elements either, so leaving it unconditional fixes the printed /
      exported-PDF copy the same way, not just the on-screen view. */
.inv-card { padding: 28px 34px; color-scheme: light; color: #16263c; }
.invoice-sheet { width: 100%; max-width: none; margin: 0; }
/* A [hidden] element stays hidden everywhere, including under the print rules
   below (e.g. `.inv-grid2 { display: grid !important }`) — the non-selected
   Form-of-Payment panels must never reappear on the printed invoice. */
.invoice-sheet [hidden] { display: none !important; }
.inv-clinic { text-align: center; border-bottom: 1px solid #c7d2df; padding-bottom: 14px; margin-bottom: 16px; }
.inv-clinic h2 { font-size: 15px; letter-spacing: .5px; color: #0f1c2e; text-transform: uppercase; }
.inv-clinic p { font-size: 11px; color: #48607a; margin-top: 2px; line-height: 1.5; }
.inv-clinic .inv-doctitle { margin-top: 10px; font-size: 13px; font-weight: 700; letter-spacing: 2px; color: #16263c; }

/* ---------- "INVOICE" header (2026-09 fifth reformat, layout-only pass) — a
   bigger logo mark top-left; "INVOICE" + a plain metadata list top-right
   (PAID / Invoice ID / Invoice Date / Due Date / Reference / PRC Lic.), each
   row divided by its own solid rule — NOT boxed. A prior pass wrapped this
   list in a bordered card; the user pointed at a full-resolution reference
   screenshot and corrected that ("just lines not in the box") — reverted to
   plain full-width solid dividers, no enclosing border on any side. This
   pass changed only spacing, rule style and sizing (logo, fonts) — no field,
   label or value was added, removed or reworded. The clinic identity block
   still runs full-width below the header (.inv-clinicblock), unchanged from
   the previous pass. The page itself has no top/bottom border either (see
   .inv-mono below) — the reference is a plain, unbordered page. ---------- */
.inv-topgrid { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 10px; flex-wrap: wrap; }
/* Logo alone, top-left — the clinic name reverted back to living in
   .inv-clinicblock, directly above the NON-VAT/TIN line (2026-09, at the
   user's request — a prior pass had moved the name up next to the logo). */
.inv-brand { display: block; text-align: left; }
.inv-brand img { width: 150px; height: 150px; object-fit: contain; }
.inv-brand-name { margin: 0 0 6px; font-size: 16px; letter-spacing: .3px; color: #000; text-transform: uppercase; font-weight: 700; text-align: left; }
.inv-clinicblock { margin-bottom: 10px; text-align: left; }
.inv-clinicblock p { font-size: 11px; color: #000; margin-top: 3px; line-height: 1.5; }
/* Gap between the TIN/Proprietor lines and the street address lines below
   them (2026-09, at the user's request — the two used to run together with
   no visual break). */
.inv-clinicblock p.inv-addr-gap { margin-top: 16px; }
.inv-head-right { text-align: right; flex-shrink: 0; }
.inv-title-inv { font-size: 24px; font-weight: 800; letter-spacing: 2px; color: #000; text-transform: uppercase; margin-bottom: 4px; }
/* Plain solid rule under every row (including the status word, the list's
   own first row) — no enclosing border, no box, no rounded corners. Rows are
   tightly packed (small vertical padding) — 2026-09 sixth reformat, at the
   user's explicit "there's no space, everything is packed" correction
   against a reference screenshot that had almost no breathing room between
   any two lines anywhere in the document. */
.inv-infobox { margin-top: 0; display: block; text-align: left; min-width: 230px; }
/* A real 2-column <table> (2026-09, at the user's explicit request/spec) —
   replaced the earlier stacked "Label: Value" <div> rows, which left every
   value starting at a different x offset since "Invoice ID" and "Reference"
   aren't the same text width. A table's own column-width algorithm is what
   actually guarantees every colon lands on the same vertical line and every
   value starts at the same vertical line, which no amount of padding on
   independent block rows can reliably do when label lengths vary. */
.inv-infobox .inv-meta-table { width: 100%; border-collapse: collapse; }
.inv-infobox .inv-meta-table td { border-top: 1px solid #000; border-bottom: 1px solid #000; font-size: 12px; color: #000; }
/* The "td.inv-meta-label" / "td.inv-meta-value" form (not just ".inv-meta-label")
   is required here — a bare-class selector has lower specificity than the
   ".inv-meta-table td" rule above (which also carries the "td" type
   selector), so a plain ".inv-meta-label { padding-right: 6px }" was silently
   losing to that rule's own padding and rendering with zero left/right gap
   ("Invoice ID:INV-000052" with no space at all) — caught by screenshot.
   Label 35% / value 65% (2026-09, widened from 45/55 at the user's request) —
   "Dr. Maria Theresa G. Tamayo" was still wrapping to two lines at 55%; the
   value column needs real room, and the label side never needs more than a
   short word anyway. */
.inv-infobox td.inv-meta-label { width: 35%; text-align: right; font-weight: 700; white-space: nowrap; padding: 4px 6px 4px 0; }
.inv-infobox .inv-meta-label::after { content: ':'; }
.inv-infobox td.inv-meta-value { width: 65%; text-align: left !important; font-weight: 400; padding: 4px 0 4px 6px; }
/* Plain text, not a colour-filled pill — PAID / PARTIALLY PAID / UNPAID all
   render identically (unboxed, black on white, normal weight — not bold),
   the wording alone carries the meaning. Same font-size as the other rows
   above (12px, not a larger 14px), but this one stays CENTERED — the rest of
   the box's rows (Invoice ID/Date/Reference) are left-aligned, this is the
   one exception (2026-09, at the user's explicit correction: "the unpaid or
   paid middle only"). */
/* No border-bottom of its own — the meta-table's first row already carries a
   border-top (see above), so a separate border here would sit flush against
   it and read as one over-thick doubled line (caught from a screenshot). */
.inv-infobox .inv-status-pill { display: block; text-align: center; padding: 4px 4px; border: 0; border-radius: 0; font-size: 12px; font-weight: 400; letter-spacing: .5px; margin: 0; color: #000 !important; background: none !important; }

/* Plain "Invoice To:" block — inline "Invoice To: <name>" / "Mobile: <phone>"
   on the left, the on-file address on the right; no boxed label/value pairs.
   Exclusive to the print-format doc (buildPrintDoc() / receipt), never
   #invRoot. */
.inv-to { margin-bottom: 10px; }
.inv-to-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #555; margin-bottom: 6px; }
.inv-to-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; flex-wrap: wrap; font-size: 12px; }
.inv-to-left > div { margin-bottom: 4px; }
.inv-to-right { text-align: right; max-width: 260px; line-height: 1.5; }

/* ---------- Transactions history (2026-09) — a solid rule under the header
   row only; no dashed rule and no line under the data row(s), matching the
   reference's plain look. ---------- */
.inv-txns { margin-top: 14px; }
.inv-txns-h { font-size: 13px; font-weight: 700; color: #000; margin-bottom: 6px; }
/* table-layout:fixed + explicit per-column widths, based on the reference
   document's own measured column boundaries (Date/Time/Type/Amount/Method
   ≈ 25/17/18/22/18%) but with Type widened (18% → 27%, borrowed from
   Date/Time) — the reference's own Type values are short ("Payment"), but
   this app's real transaction types ("Reservation Deposit", "Payment
   (Pending)") are much longer and wrapped to two lines at the reference's
   literal width. Plain auto layout (the very first pass) sized each column
   tightly to its own content instead and left them all bunched together on
   the left with the Amount/Method pair crowded on the right. */
.inv-txns table { width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 12px; }
/* box-sizing:border-box is required here — table cells default to content-box,
   so each column's padding would otherwise be ADDED on top of its
   percentage width instead of eating into it, compounding rightward across
   the row and throwing every column after the first out of alignment with
   the measured reference proportions above. */
.inv-txns th, .inv-txns td { box-sizing: border-box; text-align: left; padding: 4px 0; }
.inv-txns th { font-size: 12px; font-weight: 700; color: #000; border-bottom: 1px solid #333; }
.inv-txns td { border-bottom: 0; }
/* A second/third transaction row gets its own divider (2026-09, at the
   user's request) — every row except the last gets a thin rule so multiple
   transactions read as distinct entries, not one merged block. The last row
   deliberately stays without one (`.inv-txns td` above already opts every
   row out by default) since the table's own closing border-bottom just below
   already draws that line — giving the last row one too would double it up
   into the same over-thick line the UNPAID/Transactions borders had before. */
.inv-txns tbody tr:not(:last-child) td { border-bottom: 1px solid #333; }
/* Table closes with its own bottom rule (2026-09, at the user's request) —
   rather than a border on every row (which .inv-txns td deliberately opts
   out of above, matching the reference's plain look with no line between
   data rows), one rule under the whole table replicates the same clean
   divider under the last row. */
.inv-txns table { border-bottom: 1px solid #000; }
/* Column widths + left-alignment (2026-09, at the user's request) — Amount
   was right-aligned before, which pushed it away from Type and read as a
   much wider gap than the reference; every column (Amount included) is now
   forced left-aligned so the gap between Type and Amount matches the
   reference's natural, tight spacing. */
.inv-txns th, .inv-txns td { text-align: left !important; }
.inv-txns th:nth-child(1), .inv-txns td:nth-child(1) { width: 22%; }
.inv-txns th:nth-child(2), .inv-txns td:nth-child(2) { width: 18%; }
.inv-txns th:nth-child(3), .inv-txns td:nth-child(3) { width: 25%; white-space: nowrap; }
.inv-txns th:nth-child(4), .inv-txns td:nth-child(4) { width: 20%; }
.inv-txns th:nth-child(5), .inv-txns td:nth-child(5) { width: 15%; }
.inv-txns .amt { white-space: nowrap; font-variant-numeric: tabular-nums; }

.inv-footer-thanks { text-align: center; margin-top: 8px; font-size: 12px; font-style: italic; color: #000; }

.inv-field { margin-bottom: 12px; }
.inv-field > label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #48607a; margin-bottom: 4px; }
.inv-field input, .inv-field select { width: 100%; padding: 8px 10px; border: 1px solid #c7d2df; border-radius: 6px; font: inherit; background: #fff; }
.inv-field input:read-only { background: #f4f7fa; color: #16263c; }
.inv-field select:disabled { background: #f4f7fa; color: #16263c; }
.inv-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 32px; }

.inv-lineitems { width: 100%; border-collapse: collapse; margin: 10px 0 4px; font-size: 12px; }
.inv-lineitems th { background: #f4f7fa; text-align: left; padding: 7px 8px; font-size: 10px; text-transform: uppercase; letter-spacing: .4px; color: #48607a; border-bottom: 1px solid #c7d2df; }
.inv-lineitems td { padding: 5px 6px; border-bottom: 1px solid #e6ecf3; vertical-align: middle; }
.inv-lineitems input, .inv-lineitems select { width: 100%; padding: 6px 7px; border: 1px solid #c7d2df; border-radius: 6px; font: inherit; font-size: 12px; }
/* Read-only line items (the cashier can't edit what the dentist logged): plain text, no box. */
.inv-lineitems input[readonly] { border-color: transparent; background: transparent; box-shadow: none; outline: none; cursor: default; padding-left: 0; -moz-appearance: textfield; }
.inv-lineitems input[readonly]::-webkit-outer-spin-button, .inv-lineitems input[readonly]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.inv-lineitems .amt { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
/* The Amount heading lines up with the right-aligned peso amounts below it (2026-10-22). */
.inv-lineitems th.col-amt { text-align: right; padding-right: 6px; }
.inv-lineitems .col-qty { width: 80px; } .inv-lineitems .col-unit { width: 140px; }
.inv-lineitems .col-cost { width: 160px; } .inv-lineitems .col-amt { width: 150px; } .inv-lineitems .col-del { width: 44px; }
.inv-row-del { background: #d0463b; color: #fff; border: 0; border-radius: 6px; width: 26px; height: 26px; cursor: pointer; font-weight: 700; line-height: 1; }

.invoice-sheet .check-line { display: flex; align-items: center; gap: 8px; font-size: 13px; white-space: nowrap; }

.or-row { display: flex; justify-content: space-between; gap: 16px; font-size: 13px; margin-bottom: 5px; }
.or-row > span:first-child { color: #48607a; }

.inv-split { display: grid; grid-template-columns: 1fr 1fr; gap: 44px; margin-top: 18px; }
/* A right-aligned totals box with nothing beside it (2026-09 reference-format
   doc) — .inv-split is still used where a left column of payment fields
   genuinely accompanies the totals (e.g. receipt's legacy shape). */
.inv-totals-wrap { display: flex; justify-content: flex-end; margin-top: 18px; }
.inv-totals-wrap .inv-totals { width: 100%; max-width: 380px; align-self: auto; }
.inv-totals { background: #f4f7fa; border: 1px solid #e6ecf3; border-radius: 10px; padding: 14px 16px; align-self: start; }
.inv-totals .or-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; padding: 5px 0; font-size: 13px; margin-bottom: 0; }
/* A long label (e.g. "Less: Reservation Deposit (Paid via GCASH Ref: <13 digits>)")
   must wrap instead of forcing the row past .inv-totals' own max-width — only
   the peso figure needs to stay on one line and pinned to the right edge. */
.inv-totals .or-row > span:first-child { white-space: normal; min-width: 0; }
.inv-totals .or-row > span:last-child { white-space: nowrap; flex-shrink: 0; }
.inv-totals .or-row.muted { color: #48607a; }
.inv-totals .or-row.rec { background: #e2f0f8; margin: 2px -8px; padding: 5px 8px; border-radius: 6px; }
.inv-totals .or-grand { border-top: 2px solid #16263c; margin-top: 6px; padding-top: 8px; font-weight: 700; font-size: 15px; }
.inv-totals .or-grand > span:first-child { color: #16263c; }

/* ---------- Strict monochrome scope (2026-09 second reformat), restyled to a
   plain bordered-metadata-card look (2026-09 fourth reformat, layout-only) —
   added onto the print-format document's own outermost wrapper (billing's
   #invPrintRoot, receipt.php's top .invoice-sheet), never onto #invRoot.
   .inv-lineitems / .inv-totals / .or-row are genuinely SHARED with #invRoot's
   own on-screen editable table+totals, which still keep their existing
   light-gray fills and solid rules — these overrides only strip colour and
   restyle rules inside this one scope, so the cashier's live editing screen
   is completely unaffected. No outer page border this time (the reference is
   a plain, unbordered page) — only the metadata card (.inv-infobox above) is
   boxed. Item-table headers drop the shared class's uppercase transform to
   match the reference's plain title-case wording; rows carry no line at all,
   only the header does. */
.inv-mono {
    color: #000;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 13px;
    line-height: 1.5;
    padding: 4px 4px;
}
/* .inv-lineitems / .inv-totals / .or-row are SHARED with #invRoot's own
   on-screen editable table+totals, which keep their own spacing — these
   overrides (scoped through .inv-mono) give the print-format document its
   own roomier, document-scale spacing without touching #invRoot. */
.inv-mono .inv-lineitems { margin: 8px 0 4px; }
.inv-mono .inv-lineitems th { background: none; border-top: 0; border-bottom: 1px solid #333; color: #000; text-transform: none; font-size: 12px; padding: 7px 6px; }
/* "Services & Procedures Performed" — normal case, not ALL CAPS, and a size
   smaller than the other three (numeric) headers, which stayed readable at
   12px; this one is a much longer phrase and read too large/shouty in all
   caps at the same size (2026-09, reported directly). */
.inv-mono .inv-lineitems th.col-desc { font-size: 10.5px; }
.inv-mono .inv-lineitems td { border-bottom: 1px solid #333; padding: 7px 6px; }
/* Qty / Unit Price / Amount are numeric columns — their values (Unit Price and
   Amount via the shared .amt class; Qty here directly) sit right-aligned, so
   the header word must right-align too, or it reads shifted left of the
   number it's supposedly labelling. Scoped to the print-format document only
   (#invRoot's own live-editable table keeps its original left-aligned
   headers/Qty input, unaffected). white-space:nowrap keeps "Amount (₱)" /
   "Unit Price" on one line — those columns were narrowed (2026-09) to bring
   their values closer together, and without this the headers wrapped to two
   lines at that narrower width. It's scoped to just these three numeric
   headers (not the much wider, left-aligned first column, which may
   legitimately wrap its own longer "Services & Procedures Performed"
   heading). */
.inv-mono .inv-lineitems th.col-qty, .inv-mono .inv-lineitems th.col-cost, .inv-mono .inv-lineitems th.col-amt { text-align: right; white-space: nowrap; }
.inv-mono .inv-lineitems td.col-qty { text-align: right; }

/* ---------- Totals as extra rows of the .inv-lineitems table (2026-09 —
   replaced a separate boxed .inv-totals panel, which sat narrower/indented
   and never lined its peso figures up with the item rows' own Amount column
   above; a reference screenshot showed the totals continuing in the exact
   same column instead). Print-doc-exclusive — #invRoot's own live editable
   screen still uses the separate .inv-totals box (.or-row/.or-grand above),
   untouched. The label cell spans the first 3 columns (Item+Qty+Unit Price)
   and is right-aligned so it reads flush against the Amount column, matching
   the reference; the value cell reuses "col-amt amt" — the exact class/column
   every item row's own amount sits in — so no separate width/padding math is
   needed to keep the two stacks of numbers aligned. */
/* Compact, no rule lines (2026-09, at the user's request — the "Total" row's
   top/bottom border lines plus the item rows' own 7px vertical padding read
   as too loose/spaced out; every total row now packs tighter, and "Total"
   is marked by bold weight alone, no lines). */
.inv-mono .inv-total-row td { border-bottom: 0; color: #000; padding-top: 3px; padding-bottom: 3px; }
.inv-mono .inv-total-row .inv-total-label { text-align: right; padding-right: 10px; }
.inv-mono .inv-total-row.first td { padding-top: 10px; }
.inv-mono .inv-total-row.grand td { font-weight: 700; }

/* Signature row — two signature lines only; the disclaimer is a separate block. */
/* Percentage-width, not fixed-px (2026-09, second refinement) — a fixed
   250px/280px child pair inside a fixed 640px cap still read as too long/
   heavy once actually compared side-by-side against a reference invoice; a
   proportional 82%-wide block (each line ~42%, a spacer in between) scales
   cleanly with the page instead of pinning an oversized absolute width, and
   keeps clear side margins off the outer table edges either way. */
.inv-sign { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px; width: 82%; margin: 45px auto 10px auto; }
.inv-sign .sig-left, .inv-sign .sig-right { flex: 0 1 42%; padding-top: 24px; }
.inv-sign .sig-line { border-top: 1px solid #000; padding-top: 4px; text-align: center; font-size: 11px; color: #48607a; }
/* .inv-mono (the print-format doc / receipt.php) is strictly monochrome — no
   steel-blue text anywhere — so the signature block's label gets the same
   plain-black override every other .inv-mono text element already has. */
.inv-mono .sig-line { color: #000; }
.inv-disclaimer { position: static; display: block; clear: both; width: 100%; text-align: center; margin-top: 20px; font-size: 0.75rem; font-style: italic; color: #666; text-transform: uppercase; }

/* Billing screen on tablets keeps two columns (fields, and payment | totals);
   only phones stack everything (2026-10 responsive pass). */
@media (max-width: 767.98px) {
    .inv-split { grid-template-columns: 1fr; gap: 20px; }
    .inv-sign { justify-content: center; }
}
@media (max-width: 639.98px) {
    .inv-grid2 { grid-template-columns: 1fr; }
}
/* Phones, cashier Billing screen only (#invRoot, never the printout): each
   procedure line is a small block (description on top, then
   "qty unit @ unit cost" and the amount) instead of a squeezed 5-column
   table that scrolls sideways. */
/* Phones + tablets: "Received Amount" on one line, a full-width input under it,
   and the "Exact Amount" checkbox under that, instead of a wrapped label beside
   a small box. */
@media screen and (max-width: 1279.98px) {
    #invRoot #recvRow { flex-direction: column; align-items: stretch; gap: 6px; }
    #invRoot #recvRow > span:first-child { white-space: nowrap; }
    #invRoot #recvRow input { width: 100% !important; box-sizing: border-box; padding: 8px 10px !important; font-size: 14px; }
    #invRoot #exactRow { justify-content: flex-start; padding-top: 0; }
    #invRoot #exactRow > span:first-child { display: none; }
}
@media screen and (max-width: 639.98px) {
    #invRoot select, #invRoot input { max-width: 100%; }
    #invRoot .inv-lineitems thead { display: none; }
    #invRoot .inv-lineitems, #invRoot .inv-lineitems tbody { display: block; width: 100%; }
    /* Second line reads as one phrase, "1 session × ₱500.00", with the amount
       pushed right: the qty / unit / cost columns hug their text instead of
       stretching (read-only inputs would otherwise fill a fixed column). */
    #invRoot .inv-lineitems tr { display: grid; grid-template-columns: auto auto auto minmax(0, 1fr);
        grid-template-areas: "desc desc desc desc" "qty unit cost amt"; align-items: center; column-gap: 5px; row-gap: 2px;
        padding: 10px 0; border-bottom: 1px solid #e6ecf3; }
    #invRoot .inv-lineitems td { display: block; width: auto !important; padding: 0 !important; border: 0 !important; }
    #invRoot .inv-lineitems td:nth-child(1) { grid-area: qty; }
    #invRoot .inv-lineitems td:nth-child(2) { grid-area: unit; }
    #invRoot .inv-lineitems td:nth-child(3) { grid-area: desc; }
    #invRoot .inv-lineitems td:nth-child(4) { grid-area: cost; display: flex; align-items: center; gap: 3px; }
    #invRoot .inv-lineitems td:nth-child(4)::before { content: "× ₱"; color: #6b819a; font-size: 12px; white-space: nowrap; }
    #invRoot .inv-lineitems td:nth-child(5) { grid-area: amt; font-weight: 700; justify-self: end; }
    #invRoot .inv-lineitems td:nth-child(1) input { width: 2.6em; }
    #invRoot .inv-lineitems td:nth-child(2) input { width: 7.5em; }
    #invRoot .inv-lineitems td:nth-child(4) input { width: 5.5em; }
    #invRoot .inv-lineitems td:nth-child(1) input[readonly] { text-align: right; padding-right: 0; }
    @supports (field-sizing: content) {
        #invRoot .inv-lineitems td:nth-child(1) input, #invRoot .inv-lineitems td:nth-child(2) input,
        #invRoot .inv-lineitems td:nth-child(4) input { field-sizing: content; width: auto; min-width: 1ch; }
        #invRoot .inv-lineitems td input[readonly] { padding-right: 0; }
    }
    #invRoot .inv-lineitems td:nth-child(3) input { font-weight: 600; font-size: 14px; }
    #invRoot .inv-lineitems td input { color: #6b819a; }
    #invRoot .inv-lineitems td:nth-child(3) input { color: #16263c; }
    #invRoot .inv-lineitems td[colspan] { grid-column: 1 / -1; grid-area: auto; }
    #invActions > * { flex: 1 1 0; }
}

/* ---------- Printed Service Invoice: a copy of the clinic's own paper pad
   (2026-10-07). Built by billing.php buildPrintDoc() and receipt.php. Sizes
   are in em so the print rule below can shrink the whole sheet at once. ---------- */
.si-doc { font-family: 'Arial Narrow', 'Roboto Condensed', Arial, Helvetica, sans-serif; color: #000; font-size: 13px; line-height: 1.2; box-sizing: border-box; }
.si-doc table { border-collapse: collapse; width: 100%; table-layout: fixed; }
.si-doc b { font-weight: 700; }
/* Condensed slab-serif name with its underline, as on the pad. */
.si-namewrap { height: 3.25em; overflow: hidden; }
.si-name { display: inline-block; font-family: 'Rockwell Extra Bold', 'Rockwell', 'Roboto Slab', Georgia, serif; font-weight: 800; font-size: 3.1em; line-height: .95;
    letter-spacing: -.01em; white-space: nowrap; text-transform: uppercase; border-bottom: 2px solid #000; padding-bottom: .02em;
    transform: scaleX(.72); transform-origin: left top; }
.si-headrow { display: flex; justify-content: space-between; align-items: flex-end; gap: 1em; margin-top: .25em; }
.si-lines { font-family: Arial, Helvetica, sans-serif; font-size: .9em; line-height: 1.25; letter-spacing: .02em; }
.si-title { text-align: right; font-weight: 700; line-height: .95; white-space: nowrap; }
.si-title b { display: block; font-size: 1.35em; }
.si-title b + b { font-size: 1.95em; }
.si-meta { display: flex; align-items: flex-end; gap: .6em; margin-top: .45em; font-weight: 700; font-size: 1.05em; }
.si-chk { display: inline-flex; align-items: center; gap: .25em; }
.si-box { display: inline-flex; align-items: center; justify-content: center; width: 1.25em; height: 1.25em; border: 1px solid #000; font-size: .95em; line-height: 1; }
.si-date { margin-left: auto; display: flex; align-items: baseline; gap: .25em; font-weight: 400; font-size: 1.05em; }
.si-date .si-md { min-width: 0; padding: 0 .6em; text-align: center; }
.si-date .si-yy { min-width: 1.7em; text-align: center; padding: 0 .15em; margin-left: -.25em; }
.si-fill { display: inline-block; min-width: 11em; border-bottom: 1px solid #000; padding: 0 .3em; font-weight: 600; }
.si-sold { display: flex; align-items: flex-end; gap: .35em; margin-top: .25em; font-weight: 700; font-size: 1.45em; }
.si-sold small { font-size: .72em; }
.si-sold .si-fill { flex: 1; font-size: .78em; }
/* Customer box, Nature of Service table and the VAT / totals boxes are
   joined edge to edge, like the printed form; the customer box above them is
   its own closed box. */
/* Customer box: each line starts right after its own label (like the pad);
   the SC/PWD labels sit on the TIN / Bus. Address lines, which run unbroken
   to the right edge. */
.si-cust { margin-top: .3em; margin-bottom: .35em; border: 1px solid #000; padding: .1em .5em .45em; }
.si-crow { display: flex; align-items: flex-end; gap: .35em; min-height: 1.85em; }
.si-clbl { white-space: nowrap; }
.si-cline { flex: 1; display: flex; align-items: flex-end; min-height: 1.3em; border-bottom: 1px solid #000; }
.si-csp { flex: 1; }
.si-csc { width: 10.5em; display: flex; align-items: flex-end; white-space: nowrap; }
.si-csc span { font-size: .7em; font-weight: 700; line-height: 1; }
.si-csc b { font-size: .95em; margin-left: .3em; }
.si-cval { width: 9em; padding-left: .3em; }
.si-items-wrap { display: flex; }
.si-items { border: 1px solid #000; }
.si-items th { background: #d4d4d4; border: 1px solid #000; font-weight: 700; font-size: 1.15em; text-transform: uppercase; padding: .3em .3em; line-height: 1; text-align: center; }
.si-items th.sm { font-size: .85em; }
.si-items td { border: 1px solid #000; padding: .3em .45em; height: 2.6em; vertical-align: middle; font-size: 1.05em; }
.si-items td.c { text-align: center; }
.si-items td.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
.si-bottom { display: grid; grid-template-columns: 46.5% 53.5%; align-items: start; }
.si-left { padding: 0; }
.si-vat { border: 1px solid #000; border-top: 0; border-right: 0; }
/* The VAT box and the totals box share one edge: the totals box draws it. */
.si-vat td:last-child { border-right: 0; }
.si-vat th { width: 55%; text-align: right; font-weight: 700; font-size: 1.05em; padding: .15em .45em; border: 1px solid #000; border-top: 0; line-height: 1.1; }
.si-tot th { width: 55.0%; white-space: normal; overflow-wrap: break-word; text-align: right; font-weight: 400; padding: .15em .45em; border: 1px solid #000; border-top: 0; line-height: 1.1; font-size: 1.05em; }
.si-vat td, .si-tot td { border: 1px solid #000; border-top: 0; padding: .15em .45em; text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
/* Every VAT row and the totals row beside it share one fixed height so
   their lines run straight across, as on the pad. */
.si-vat tr, .si-tot tr:not(.due) { height: 2.5em; }
.si-tot { border: 1px solid #000; border-top: 0; }
.si-tot th small { display: block; font-size: .72em; font-weight: 400; white-space: nowrap; }
.si-tot th small.inl { display: inline-block; text-align: center; line-height: .95; vertical-align: bottom; }
.si-tot tr.due th { white-space: normal; }
.si-tot tr.due th, .si-tot tr.due td { font-weight: 700; font-size: 1.15em; text-align: left; padding-top: .55em; padding-bottom: 1.1em; vertical-align: top; }
.si-tot tr.due td { text-align: right; }
.si-recv { margin-top: .3em; padding-right: .7em; }
.si-recv-h { font-style: italic; font-weight: 700; font-size: 1.05em; }
.si-recv-v { border-bottom: 1px solid #000; min-height: 3.6em; padding: .2em 0; font-size: 1.2em; display: flex; align-items: center; justify-content: center; text-align: center; line-height: 1.2; }
/* Bottom-left, under the "Received the Amount of" line: the BIR-accredited
   printer block. Bottom-right: the cashier signature under the totals. */
.si-pfoot { margin-top: .35em; }
.si-orno { margin-top: .25em; font-family: Arial, Helvetica, sans-serif; font-size: 1.1em; white-space: nowrap; color: #000; }
.si-orno b { font-size: 1.15em; letter-spacing: .04em; }
.si-printer { margin-top: 0; font-family: Arial, Helvetica, sans-serif; font-size: .7em; line-height: 1.2; color: #000; }
.si-sign { margin-top: 3.6em; text-align: center; font-size: 1em; }
.si-sigline { border-top: 1px solid #000; width: 96%; margin: 0 0 3px auto; }
/* On screen the type scales with the sheet's own width (about 44em across, like
   the desktop view), so a phone or iPad shows the same layout, just smaller,
   and no totals label spills into the amount column. */
@media screen {
    .invoice-sheet { container-type: inline-size; }
    .si-doc { font-size: clamp(6px, calc(100cqw / 44), 13px); }
}

/* No dark-mode overrides here, on purpose. The Service Invoice / receipt is a
   real financial document — it must always render as plain white paper with
   dark ink, exactly like its printed form, regardless of the app's dark-mode
   toggle. This is the same exception already granted to the `@media print`
   block below and to the GCash QR code in `patient_booking_style.php`: some
   surfaces are documents, not app chrome, and stay light unconditionally. */

/* ---------- Print: A5 portrait, margin 0 on the page box itself — the 10mm
   whitespace border is instead real padding on .invoice-sheet (2026-09 second
   reformat), so it survives consistently across print engines rather than
   depending on how each one applies an @page margin. ---------- */
@page { size: A5 portrait; margin: 0; }

@media print {
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    html, body { background: #fff !important; font-size: 13px; }

    /* modules/cashier/billing.php only: the live editable form (#invRoot, the
       original Service Invoice look) is never what gets printed or saved as
       PDF — it stays hidden at print time, and the reference-format doc built
       fresh into #invPrintRoot (buildPrintDoc(), plain read-only markup, no
       form controls) is shown in its place. modules/shared/receipt.php has no
       #invRoot/#invPrintRoot pair — it's a single read-only page already, so
       these rules simply don't match there. */
    #invRoot { display: none !important; }
    #invPrintRoot { display: flex !important; flex-direction: column; }

    /* Every non-document control — no button ever prints. Field-validation
       hints (`.field-err`, incl. the green "Reference number available" one
       wireRefCheck adds) are transient UI, never part of the printed invoice. */
    .no-print, .modal-root, .toast-root, button,
    .inv-lineitems .col-del, .field-err { display: none !important; }
    .inv-card { border: 0 !important; box-shadow: none !important; padding: 0 !important; }

    /* Keep the whole invoice together as a full-height flex column so the
       disclaimer can be pushed to the very bottom of whichever page it lands on. */
    .invoice-sheet {
        width: 100% !important; max-width: none !important; margin: 0 !important;
        /* Capped at the A5 height: phone/tablet browsers resolve 100vh against
           the screen, not the printed page, which pushed the bottom half of
           the invoice onto a second page. */
        min-height: min(100vh, 209mm); display: flex; flex-direction: column;
        box-sizing: border-box; padding: 8mm;
    }

    /* Preserve the exact side-by-side grids from the screen. */
    .inv-grid2 { display: grid !important; grid-template-columns: repeat(2, 1fr) !important; gap: 4px 22px !important; }
    .inv-split { display: grid !important; grid-template-columns: 1.15fr 1fr !important; gap: 22px !important; }
    .inv-totals-wrap { display: flex !important; justify-content: flex-end !important; }
    .inv-sign  { display: flex !important; flex-direction: row !important; justify-content: space-between !important; flex-wrap: nowrap !important; }
    .inv-lineitems { width: 100% !important; }

    /* Flatten text fields to plain text — no box edge, no fill, no native chrome
       (select arrow, number spinners, date picker glyph, placeholder). Just the
       value sitting under its label, so the printed invoice reads as a finished
       document rather than a screenshot of a form. Radios / checkboxes keep
       their native look so the chosen Form of Payment and the SC/PWD tick still
       show. */
    input:not([type="radio"]):not([type="checkbox"]), select, textarea {
        border: 0 !important; background: transparent !important;
        padding: 1px 2px !important; box-shadow: none !important;
        -webkit-appearance: none !important; appearance: none !important;
        color: #16263c !important;
    }
    select { background-image: none !important; }
    input::placeholder, textarea::placeholder { color: transparent !important; }
    input[type="date"]::-webkit-calendar-picker-indicator { display: none !important; }
    input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none !important; margin: 0 !important; }

    /* Document-scale spacing for the A5 page — roomier than the old "compact"
       pass (which left dead space at the bottom), but tuned back down from
       the on-screen values above so a plain single-page bill still fits one
       A5 sheet instead of spilling onto a second page: the on-screen
       .inv-mono line-height (1.5) is dialed back for print specifically,
       since compounded across every row it was the single biggest
       contributor to the overflow, more than any individual font-size or
       padding bump. */
    .inv-mono { line-height: 1.2; }
    .inv-clinic { padding-bottom: 6px; margin-bottom: 8px; }
    .inv-clinic h2 { font-size: 12px; }
    .inv-clinic p { font-size: 9.5px; line-height: 1.3; }
    .inv-clinic .inv-doctitle { margin-top: 5px; font-size: 11px; }
    /* A5's content width is narrow enough that the brand column (logo +
       name, stacked) and the "INVOICE" info column can exceed it combined —
       .inv-topgrid's flex-wrap:wrap would then drop the info column onto its
       own line below the brand block instead of staying side-by-side,
       roughly doubling this section's height and pushing the whole document
       onto a second page. Force one row and let the brand column (and its
       name text) shrink/wrap instead. */
    .inv-topgrid { margin-bottom: 8px; flex-wrap: nowrap; }
    .inv-brand { min-width: 0; flex-shrink: 1; }
    .inv-brand img { width: 90px; height: 90px; }
    .inv-brand-name { font-size: 11px; margin: 0 0 4px; }
    .inv-clinicblock { margin-bottom: 9px; }
    .inv-clinicblock p { font-size: 9.5px; line-height: 1.3; }
    .inv-clinicblock p.inv-addr-gap { margin-top: 10px; }
    .inv-title-inv { font-size: 16px; margin-bottom: 6px; }
    .inv-infobox { margin-top: 0; min-width: 0; }
    .inv-infobox .inv-status-pill { font-size: 10px; padding: 4px 8px; }
    .inv-infobox .inv-meta-table td { font-size: 10px; }
    .inv-infobox td.inv-meta-label { padding: 3px 6px 3px 0; }
    .inv-infobox td.inv-meta-value { padding: 3px 0 3px 6px; }
    .inv-to { margin-bottom: 8px; }
    .inv-to-label { font-size: 9.5px; margin-bottom: 4px; }
    .inv-to-row { font-size: 10px; }
    .inv-txns-h { margin-bottom: 5px; font-size: 11px; }
    .inv-txns table { font-size: 10px; }
    .inv-txns th, .inv-txns td { padding: 3px 0; }
    .inv-field { margin-bottom: 4px; }
    .inv-field > label { font-size: 8.5px; margin-bottom: 1px; }
    .inv-field input { font-size: 9.5px; }
    .inv-lineitems { margin: 6px 0 3px; font-size: 11px; }
    .inv-lineitems th, .inv-lineitems td { padding: 3px 5px; }
    .inv-total-row.first td { padding-top: 8px; }
    .inv-total-row .inv-total-label { padding-right: 8px; }
    .inv-txns { margin-top: 10px; }
    /* Signature block pushed down toward the page bottom, close to "Thanks
       for visiting us!" (2026-09) — the push is a flex `margin-top: auto`,
       NOT a fixed pixel value, and it's on .inv-sign specifically (not
       .inv-footer-thanks below, which used to own this trick). A fixed
       55-60px push (as a first, literal reading of the request would do)
       is exactly what caused the earlier "2-page overflow with 4-6 service
       rows" bug this section's own history already fixed once — it adds a
       flat gap regardless of how much room is actually left on the page, so
       it either looks too small on a short invoice or, worse, tips an
       already-fullish one over onto a second page. `margin-top: auto` on a
       flex column child instead claims *only the leftover vertical space
       that genuinely exists* on this one page: on a short invoice (few
       items) there's a lot of free space, so the block visibly sits low,
       near "Thanks for visiting us!" as asked; on a fuller 4-6-item invoice
       there's little or no free space left, so the auto margin naturally
       shrinks toward zero and the block simply follows the content with no
       added height — it can never itself force a second page. */
    .inv-sign {
        margin: 0 auto 8px auto;
        margin-top: auto !important;
    }

    /* "Thanks for visiting us!" is the invoice's last element — a small
       fixed gap below the (now independently pushed-down) signature block,
       not its own separate auto-margin push: giving both .inv-sign and
       .inv-footer-thanks a margin-top:auto would split the leftover space
       between the two, opening a second gap between the signature and
       "Thanks" instead of keeping them close together as asked. This rule
       only affects the print-format doc (#invPrintRoot / receipt); the
       live #invRoot form's own .inv-disclaimer div is unrelated — #invRoot
       is hidden entirely at print time, so it's never subject to this. */
    .inv-footer-thanks {
        position: static !important; display: block !important; width: 100% !important;
        text-align: center !important; clear: both !important;
        margin-top: 10px !important;
        margin-bottom: 0 !important;
        font-size: 0.75rem !important; font-style: italic !important;
        page-break-before: avoid; page-break-after: avoid;
    }

    /* Paper Service Invoice copy: one A5 sheet. */
    /* Fill the A5 page the way the paper pad does: slightly larger type
       (the service rows keep a fixed, compact height), and any small leftover
       height goes above the cashier signature at the page bottom. */
    .si-doc { font-size: 12px; flex: 1 1 auto; display: flex; flex-direction: column; }
    .si-name { transform: scaleX(.67); }
    .si-tot th { width: 55.0%; white-space: normal; }
    .si-tot th small { white-space: nowrap; }
    .si-items td { height: 2.3em; }
    .si-doc .si-items-wrap { flex: 1 1 auto; }
    .si-doc .si-bottom { flex: 1 1 auto; align-items: stretch; }
    .si-right { display: flex; flex-direction: column; }
    .si-sign { margin-top: auto; padding-top: 3.2em; }
    .si-vat tr, .si-tot tr:not(.due) { height: 2.7em; }
    .si-tot tr.due th, .si-tot tr.due td { font-size: 1em; }
    header, footer { display: none !important; }
    .si-items tr, .si-bottom { page-break-inside: avoid; break-inside: avoid; }
    /* More than 3 services: compact rows so it still fits one A5 page;
       10+ services also drop the type size. */
    .si-many .si-items td { height: 1.75em; padding-top: .15em; padding-bottom: .15em; }
    .si-many .si-vat tr, .si-many .si-tot tr:not(.due) { height: 2.15em; }
    .si-many .si-recv-v { min-height: 2.6em; }
    .si-many .si-sign { padding-top: 2.2em; }
    .si-doc.si-dense { font-size: 10px; }
    .si-dense .si-items td { height: 1.55em; }

    .inv-clinic, .inv-topgrid, .inv-clinicblock, .inv-to, .inv-grid2, .inv-split, .inv-totals-wrap, .inv-totals, .inv-txns, .inv-sign, .inv-footer-thanks, .inv-lineitems tr {
        page-break-inside: avoid; break-inside: avoid;
    }
}
</style>
