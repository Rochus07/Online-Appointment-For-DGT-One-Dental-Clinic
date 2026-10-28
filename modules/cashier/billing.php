<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['cashier']);
$PAGE_TITLE = 'Billing';
$ACTIVE = 'transactions';
$PAGE_FULL = true;   // full-bleed content column (matches the admin screens)

$appointmentId = (int) ($_GET['appointment'] ?? 0);

ob_start();
require __DIR__ . '/../../includes/invoice_style.php';
?>

<div class="no-print mb-[22px] flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">Billing</h1>
    </div>
    <a class="inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md border border-steel-200 bg-transparent px-[15px] py-[9px] text-[13px] font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5" href="/dgt_dental_clinic/modules/cashier/transactions" data-nav>&lsaquo; Back to Transaction</a>
</div>

<div class="no-print mb-3" id="orBar"></div>

<div class="inv-card rounded-[10px] border border-steel-100 bg-white shadow-sm">
    <div class="invoice-sheet" id="invRoot"><p class="py-7 text-center text-steel-400">Loading&hellip;</p></div>
    <!-- Print/PDF-only copy of the clinic paper Service Invoice — built fresh by
         buildPrintDoc() right before printing; the live editable form above (#invRoot)
         keeps the original Service Invoice look and is hidden at print time. -->
    <div class="invoice-sheet" id="invPrintRoot" hidden></div>
</div>

<div class="no-print mt-4 flex justify-end gap-2" id="invActions"></div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const APPT_ID = __APPT__;
  const g = (id) => document.getElementById(id);
  const peso2 = (n) => Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const root = g('invRoot');

  const BTN     = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold disabled:opacity-50';
  const BTN_SM  = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold disabled:opacity-50';
  const PRIMARY = ' bg-accent text-white hover:bg-accent-dark';
  const GHOST   = ' border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
  const OK      = ' bg-ok text-white hover:brightness-95';
  const EMPTY   = 'py-7 text-center text-steel-400';

  if (!APPT_ID) { root.innerHTML = '<p class="' + EMPTY + '">No appointment selected. Open Billing from the Transaction table.</p>'; return; }

  // Clinic identity exactly as printed on the clinic's own paper Service
  // Invoice pad (2026-10-07). modules/shared/receipt.php carries the same lines.
  const CLINIC_NAME = 'DGT ONE DENTAL CENTER CO.';
  const CLINIC_TOP_LINES = [
    'VAT Reg TIN: 608-196-530-00009',
  ];
  // BIR-accredited printer details from the bottom-left of the paper pad.
  const SI_PRINTER = "<b>F.G. VITUG <i>Social &amp; Commercial Printers</i></b><br>7 Encarnita St., Richland Subd., Sauyo, Nova., Q.C.<br>Printer's VAT Reg. TIN: 132-436-565-00000<br>50 bklts. (50x2) #000001-002500<br>BIR ATP No. OCN: 25BAU20260000010863<br>Date of ATP: <b>May 25, 2026</b>";
  const CLINIC_ADDRESS_LINES = [
    '2nd Flr. Unit F & G Del Rey Bldg., Gov. F. Halili Ave.,',
    'Tungkong Mangga, 3023 San Jose Del Monte, Bulacan, Phils.',
  ];

  // The counter payment actually being applied right now — read from the LIVE
  // form state (current Form of Payment radio + its amount field(s)), not the
  // persisted a.amount_cash/gcash/bank columns. Deliberate: Print Preview must
  // reflect whatever is currently on screen, including an in-progress,
  // not-yet-saved payment — and after a real settle, render() itself
  // repopulates these same live fields from the persisted row anyway (see its
  // own comment), so reading the live form is correct in both cases. Per-method
  // detail (which of cash/GCash/bank) isn't broken out here any more — the
  // Transactions table below already has its own Method column for that.
  function totalPaidNow() {
    const pmEl = document.querySelector('input[name="pm"]:checked');
    const mode = pmEl ? pmEl.value : 'cash';
    if (mode === 'split') {
      return ['sp_cash', 'sp_gcash', 'sp_bank'].reduce((s, id) => s + Math.max(window.moneyVal(g(id)) || 0, 0), 0);
    }
    return Math.max(window.moneyVal(g('f_recv')) || 0, 0);
  }

  // "Oct. 07, 2026" — the date style written on the clinic's paper invoice.
  const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  function dateParts(s) {
    const [y, m, d] = String(s || '').slice(0, 10).split('-').map(Number);
    return (y && m && d) ? { y, m, d } : null;
  }
  // Paper pad style: "Oct. 07" on the long line, then ", 20" + "26".
  function paperDate(s) {
    const p = dateParts(s);
    if (!p) return { md: '', yy: '' };
    const m = MONTHS_SHORT[p.m - 1];
    return { md: m + (m === 'May' ? ' ' : '. ') + String(p.d).padStart(2, '0'), yy: String(p.y).slice(-2) };
  }

  /* ---------- number -> words ("Twenty Four Thousand Pesos Only") ---------- */
  function pesosToWords(n) {
    n = Math.floor(Math.abs(+n || 0));
    if (n === 0) return 'Zero Pesos Only';
    const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
      'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    const below1000 = (x) => {
      let s = '';
      if (x >= 100) { s += ones[Math.floor(x / 100)] + ' Hundred'; x %= 100; if (x) s += ' '; }
      if (x >= 20) { s += tens[Math.floor(x / 10)]; if (x % 10) s += ' ' + ones[x % 10]; }
      else if (x > 0) s += ones[x];
      return s;
    };
    const scales = [[1e9, 'Billion'], [1e6, 'Million'], [1e3, 'Thousand'], [1, '']];
    const parts = [];
    for (const [v, name] of scales) {
      if (n >= v) { const c = Math.floor(n / v); n %= v; parts.push((below1000(c) + (name ? ' ' + name : '')).trim()); }
    }
    const w = parts.filter(Boolean).join(' ');
    return w + ' Peso' + (w === 'One' ? '' : 's') + ' Only';
  }

  // The "Received the Amount of" line (mirrors formatReceivedAmountText() in
  // api/_bootstrap.php). pieces = [{amount, method, ref}]:
  //   Five Hundred Pesos Only (₱500.00) via GCash (Ref: 123456789)
  //   Five Hundred Pesos Only (₱500.00: ₱200 Cash, ₱300 via GCash Ref: 123456789)
  function formatReceivedAmountText(pieces) {
    pieces = pieces.filter((p) => p.amount > 0.005);
    if (!pieces.length) return '';
    const total = Math.round(pieces.reduce((s, p) => s + p.amount, 0) * 100) / 100;
    const head = pesosToWords(total) + ' (₱' + peso2(total);
    if (pieces.length === 1) {
      const p = pieces[0];
      return head + ') via ' + p.method + (p.ref ? ' (Ref: ' + p.ref + ')' : '');
    }
    const short = (n) => Number(n).toLocaleString('en-US', { minimumFractionDigits: Math.round(n * 100) % 100 ? 2 : 0, maximumFractionDigits: 2 });
    return head + ': ' + pieces.map((p) => '₱' + short(p.amount) + (p.method === 'Cash' ? ' Cash' : ' via ' + p.method + (p.ref ? ' Ref: ' + p.ref : ''))).join(', ') + ')';
  }
  const RESV_LABEL = { gcash: 'GCash', bank: 'Bank Transfer', cash: 'Cash', paymaya: 'Maya' };

  // Figures for the printed invoice (mirrors invoicePrintFigures() in
  // api/_bootstrap.php). useLast = print the bill's latest saved receipt;
  // otherwise the payment being entered on screen right now.
  function printFigures(totalDue, useLast) {
    const a = DATA.appointment;
    const r2 = (n) => Math.round(n * 100) / 100;
    const resv = Number(a.reservation_fee_paid) || 0;
    const cumul = r2((Number(a.amount_cash) || 0) + (Number(a.amount_gcash) || 0) + (Number(a.amount_bank) || 0));
    const lr = DATA.last_receipt;
    let split, prior, gref, bref;
    if (useLast) {
      split = lr ? { cash: +lr.cash || 0, gcash: +lr.gcash || 0, bank: +lr.bank || 0 }
                 : { cash: Number(a.amount_cash) || 0, gcash: Number(a.amount_gcash) || 0, bank: Number(a.amount_bank) || 0 };
      prior = lr ? Math.max(r2(cumul - split.cash - split.gcash - split.bank), 0) : 0;
      gref = a.gcash_reference_number || ''; bref = a.bank_reference || '';
    } else {
      prior = cumul;
      const due = Math.max(r2(totalDue - resv - prior), 0);
      const pmEl = document.querySelector('input[name="pm"]:checked');
      const mode = pmEl ? pmEl.value : 'cash';
      if (mode === 'split') {
        split = { cash: window.moneyVal(g('sp_cash')), gcash: window.moneyVal(g('sp_gcash')), bank: window.moneyVal(g('sp_bank')) };
      } else {
        const amt = Math.min(totalPaidNow(), due);
        split = { cash: mode === 'cash' ? amt : 0, gcash: mode === 'gcash' ? amt : 0, bank: mode === 'bank' ? amt : 0 };
      }
      gref = g('f_gref') ? g('f_gref').value.trim() : ''; bref = g('f_bref') ? g('f_bref').value.trim() : '';
    }
    const today = r2(split.cash + split.gcash + split.bank);
    const remaining = Math.max(r2(totalDue - resv - prior - today), 0);
    const pieces = [];
    if (prior <= 0.005 && resv > 0) pieces.push({ amount: resv, method: a.reservation_method_label || RESV_LABEL[a.reservation_method] || 'Cash', ref: a.reservation_reference || '' });
    if (split.cash > 0) pieces.push({ amount: split.cash, method: 'Cash', ref: '' });
    if (split.gcash > 0) pieces.push({ amount: split.gcash, method: 'GCash', ref: gref });
    if (split.bank > 0) pieces.push({ amount: split.bank, method: 'Bank Transfer', ref: bref });
    // Excess reservation handed back as change (saved by settle, or what Pay will return now).
    const refund = useLast ? (Number(a.reservation_refund) || 0) : Math.max(r2(resv + prior - totalDue), 0);
    return { cashSale: remaining <= 0.005, resv, prior, today, remaining, refund, pieces };
  }

  // "LastName,FirstName_MM-DD-YY" (today's date) — used by the print-title trick below.
  function invoiceFilename() {
    const name = (DATA && DATA.appointment && DATA.appointment.patient_name || 'Patient').trim();
    const parts = name.split(/\s+/);
    const first = parts[0] || 'Patient';
    const last = parts.length > 1 ? parts[parts.length - 1] : first;
    const d = new Date();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const yy = String(d.getFullYear()).slice(-2);
    return last + ',' + first + '_' + mm + '-' + dd + '-' + yy;
  }

  // Chrome's own "Save as PDF" print destination takes its suggested Save-As
  // filename from document.title as it stands when the dialog opens — set it
  // right before printing, then restore the page's real title once the dialog
  // closes. NOTE: a real printer driver (e.g. "Microsoft Print to PDF") does
  // NOT forward document.title into its own Save dialog — that's a Chromium/
  // Windows print-pipeline limitation no page JS can reach.
  // Builds the printed Service Invoice as a copy of the clinic's own paper pad
  // (2026-10-07): name + VAT TIN + address, Cash / Charge Sales boxes, Date,
  // Sold To, the customer box, Nature of Service / Qty / Unit Price / Amount,
  // the VAT box, "Received the Amount of", the totals column and the cashier
  // signature. Values are read off the live form (#invRoot), unsaved edits
  // included. VAT lines and Withholding Tax print blank, as on the paper copy.
  // ---- Senior citizen / PWD discount (2026-10-18) ----
  // Senior Citizen only for patients 60+; PWD for any age. Patterns match
  // validTin() / validPwdId() / validOscaId() in includes/functions.php.
  const seniorOk = () => (+(DATA && DATA.appointment && DATA.appointment.patient_age) || 0) >= 60;
  const SC_RE = {
    tin: /^\d{3}-\d{3}-\d{3}(-\d{3}|-\d{5})?$/,
    pwd: /^\d{2}-\d{4}-\d{3}-(\d{7}|\d{3})$/,
    osca: /^[A-Za-z0-9][A-Za-z0-9-]{2,19}$/,
  };
  function syncSc() {
    const on = g('t_sc').checked, t = g('f_sctype').value;
    g('scOscaWrap').hidden = !(on && t === 'senior');
    g('scTinWrap').hidden = !(on && t === 'senior');
    g('scPwdWrap').hidden = !(on && t === 'pwd');
  }
  function scIdValue() {
    return (g('f_sctype').value === 'pwd' ? g('f_pwd') : g('f_osca')).value.trim();
  }
  function scFieldErr(el, msg) {
    const e = g('e_' + el.id.slice(2));
    if (e) { e.textContent = msg; e.hidden = !msg; }
    el.classList.toggle('is-invalid', !!msg);
  }
  // Returns an error message ('' when fine) and marks the bad field.
  function scError() {
    if (!g('t_sc').checked) return '';
    if (g('f_sctype').value === 'senior') {
      if (!seniorOk()) return 'The senior citizen discount is only for patients 60 years old and above.';
      const id = g('f_osca'), tin = g('f_srtin');
      if (!SC_RE.osca.test(id.value.trim())) { const m = 'Enter the OSCA / Senior Citizen ID No. (3 to 20 letters, digits or hyphens).'; scFieldErr(id, m); id.focus(); return m; }
      if (tin.value.trim() && !SC_RE.tin.test(tin.value.trim())) { const m = 'The TIN must look like 123-456-789, 123-456-789-000 or 123-456-789-00000.'; scFieldErr(tin, m); tin.focus(); return m; }
      return '';
    }
    const pwd = g('f_pwd');
    if (!SC_RE.pwd.test(pwd.value.trim())) { const m = 'The PWD ID No. must look like 13-7405-000-0001234.'; scFieldErr(pwd, m); pwd.focus(); return m; }
    return '';
  }

  function buildPrintDoc(useLast) {
    recalc();   // make sure t_sales/t_disc/t_promo/t_bal are current
    const a = DATA.appointment;
    const items = collectItems();
    const num = (s) => Math.abs(parseFloat(String(s || '').replace(/[^0-9.]/g, '')) || 0);
    const sales = num(g('t_sales').textContent);
    const scOn = g('t_sc').checked;
    const promoOn = g('promoRow') && !g('promoRow').hidden;
    const last = useLast === undefined ? paid : useLast;
    const fig = printFigures(num(g('t_due').textContent), last);
    const cashSale = fig.cashSale;
    const receivedText = formatReceivedAmountText(fig.pieces);
    const dateBasis = a.payment_date || todayISO();
    const scId = scOn ? scIdValue() : '';
    const scTin = scOn && g('f_sctype').value === 'senior' ? g('f_srtin').value.trim() : '';
    // "Last, First" from the patient record; a name the cashier retyped in
    // "Received from" prints as typed.
    const typed = g('f_from').value.trim();
    const soldTo = (typed && typed !== String(a.patient_name || '').trim()) ? typed : (DATA.sold_to || typed);
    const box = (on) => '<span class="si-box">' + (on ? '&#10003;' : '') + '</span>';
    const val = (n) => n > 0 ? peso2(n) : '';

    const rows = items.map((it) => {
      const amt = (it.qty || 1) * (it.unit_cost || 0);
      return '<tr><td>' + esc(it.description || '') + '</td>' +
        '<td class="c">' + esc(it.qty || 1) + '</td>' +
        '<td class="num">' + peso2(it.unit_cost || 0) + '</td>' +
        '<td class="num">' + peso2(amt) + '</td></tr>';
    });
    // More than 3 services: tighten the rows so the invoice stays on one A5 page.
    const density = rows.length > 9 ? ' si-many si-dense' : (rows.length > 3 ? ' si-many' : '');
    while (rows.length < 3) rows.push('<tr><td></td><td></td><td></td><td></td></tr>');

    const tot = (label, value, cls) => '<tr' + (cls ? ' class="' + cls + '"' : '') + '><th>' + label + '</th><td>' + value + '</td></tr>';

    return '<div class="si-doc' + density + '">' +
      '<div class="si-namewrap"><div class="si-name">' + esc(CLINIC_NAME) + '</div></div>' +
      '<div class="si-headrow"><div class="si-lines">' +
      CLINIC_TOP_LINES.concat(CLINIC_ADDRESS_LINES).map((l) => '<div>' + esc(l) + '</div>').join('') +
      '</div><div class="si-title"><b>SERVICE</b><b>INVOICE</b></div></div>' +

      '<div class="si-meta">' +
      '<span class="si-chk">' + box(cashSale) + 'CASH SALES</span>' +
      '<span class="si-chk">' + box(!cashSale) + 'CHARGE SALES</span>' +
      '<span class="si-date">Date: <span class="si-fill si-md">' + esc(paperDate(dateBasis).md) + '</span>, 20<span class="si-fill si-yy">' + esc(paperDate(dateBasis).yy) + '</span></span>' +
      '</div>' +
      '<div class="si-sold"><span>S<small>OLD</small> T<small>O</small>:</span><span class="si-fill">' + esc(soldTo) + '</span></div>' +

      '<div class="si-cust"><div class="si-crow"><span class="si-clbl">Registered Name</span><span class="si-cline"></span></div><div class="si-crow"><span class="si-clbl">TIN</span><span class="si-cline"><span class="si-csp">' + esc(scTin) + '</span><span class="si-csc"><span>SC/PWD/NAAC/<br>MOV/Solo Parent</span><b>ID #</b></span><span class="si-cval">' + esc(scId) + '</span></span></div><div class="si-crow"><span class="si-clbl">Bus. Address:</span><span class="si-cline"><span class="si-csp"></span><span class="si-csc"><span>SC/PWD/NAAC/<br>MOV/Solo Parent</span><b>SIGNATURE</b></span><span class="si-cval"></span></span></div></div>' +

      '<div class="si-items-wrap"><table class="si-items"><colgroup><col style="width:54%"><col style="width:8%"><col style="width:14%"><col style="width:24%"></colgroup>' +
      '<thead><tr><th>Nature of Service</th><th>Qty</th><th class="sm">Unit<br>Price</th><th>Amount</th></tr></thead>' +
      '<tbody>' + rows.join('') + '</tbody></table></div>' +

      '<div class="si-bottom"><div class="si-left">' +
      '<table class="si-vat"><tbody>' +
      tot('VATable Sales', '') + tot('VAT-Exempt Sales', '') + tot('Zero-Rated Sales', '') + tot('VAT Amount', '') +
      '</tbody></table>' +
      '<div class="si-recv"><div class="si-recv-h">Received the Amount of</div>' +
      '<div class="si-recv-v">' + esc(receivedText) + '</div></div>' +
      '<div class="si-pfoot"><div class="si-printer">' + SI_PRINTER + '</div>' +
      (printOrNo(last) ? '<div class="si-orno">OR No. <b>' + esc(printOrNo(last)) + '</b></div>' : '') + '</div>' +
      '</div>' +
      '<div class="si-right">' +
      '<table class="si-tot"><tbody>' +
      tot('<b>TOTAL TREATMENT:</b> <small class="inl">VAT<br>Inclusive</small>', val(sales)) +
      tot('<b>Less:</b> VAT', '') +
      tot('<b>AMT.:</b> Net of VAT', '') +
      tot('<b>Less Discount:</b><small>(SC/PWD/NAAC/MOV/Solo Parent)</small>', scOn ? val(num(g('t_disc').textContent)) : '') +
      (promoOn ? tot('<b>Less:</b> ' + esc(g('promoLbl').textContent.replace(/^Less:\s*/, '')), val(num(g('t_promo').textContent))) : '') +
      tot('<b>Add:</b> VAT 12%', '') +
      tot('<b>Less:</b> Withholding Tax', '') +
      (fig.resv > 0.005 ? tot('<b>Less:</b> Reservation Deposit', val(fig.resv)) : '') +
      (fig.prior > 0.005 ? tot('<b>Less:</b> Previous Payments', val(fig.prior)) : '') +
      tot('<b>Less:</b> Payments Received Today', val(fig.today)) +
      (fig.refund > 0.005 ? tot('<b>Less:</b> Excess Reservation Returned (Change)', val(fig.refund)) : '') +
      tot('<b>TOTAL AMOUNT PAID: &#8369;</b>', peso2(fig.resv + fig.prior + fig.today - fig.refund), cashSale ? 'due' : '') +
      // Installment only: a balance is still owed.
      (cashSale ? '' : tot('<b>REMAINING BALANCE (TOTAL DUE): &#8369;</b>', peso2(fig.remaining), 'due')) +
      '</tbody></table>' +
      '<div class="si-sign"><div class="si-sigline"></div>Cashier/Authorized Signature</div>' +
      '</div></div>' +
      '</div>';
  }

  // useLast === true prints the receipt just saved (right after Pay);
  // otherwise a settled bill prints its last receipt and an open one the
  // payment being entered.
  function printInvoice(useLast) {
    if (g('invPrintRoot')) g('invPrintRoot').innerHTML = buildPrintDoc(useLast === true ? true : undefined);

    const prevTitle = document.title;
    let restored = false;
    const restore = () => {
      if (restored) return;
      restored = true;
      document.title = prevTitle;
      window.removeEventListener('afterprint', restore);
    };
    window.addEventListener('afterprint', restore);
    setTimeout(restore, 10000);   // backstop — some print paths never fire 'afterprint'

    document.title = invoiceFilename();
    // A single tick (or even a short setTimeout) isn't always enough for the
    // title change to actually commit before the dialog reads it — this loses
    // the race for a real printer driver, not just Chrome's own "Save as PDF".
    // Two animation frames force at least one full paint cycle to happen first.
    requestAnimationFrame(() => requestAnimationFrame(() => window.print()));
  }

  let DATA = null;
  const SPR = 0.20;
  let paid = false;

  async function boot() {
    try {
      const inv = await api('payments.php?action=invoice&id=' + APPT_ID);
      DATA = inv;
      promoInit = (inv.applied_promo && inv.applied_promo.id) ? String(inv.applied_promo.id) : '';
      paid = inv.appointment.payment_status === 'fully_paid';
      render();
      renderOrBar();
    } catch (e) { root.innerHTML = '<p class="' + EMPTY + '">' + esc(e.message) + '</p>'; }
  }

  // The dentist's logged lines (services, quantities, prices) are read-only;
  // api/payments.php rebuilds the invoice from the procedure log on settle,
  // plus any services the cashier adds below (addExtraRow()).
  function rowHtml(it) {
    const kind = it.kind === 'service' ? 'service' : 'addon';
    return '<tr data-kind="' + kind + '" data-service-id="' + esc(it.service_id || '') + '">' +
      '<td class="col-qty"><input type="number" class="ii-qty" value="' + esc(it.qty || 1) + '" readonly tabindex="-1"></td>' +
      '<td class="col-unit"><input type="text" class="ii-unit" value="' + esc(it.unit || 'session') + '" readonly tabindex="-1"></td>' +
      '<td><input type="text" class="ii-desc" value="' + esc(it.description || '') + '" readonly tabindex="-1"></td>' +
      '<td class="col-cost"><input type="text" inputmode="decimal" data-money class="ii-cost" value="' + window.moneyStr(it.unit_cost) + '" readonly tabindex="-1"></td>' +
      '<td class="col-amt amt ii-amt">₱' + peso2((it.qty || 1) * (it.unit_cost || 0)) + '</td>' +
      '</tr>';
  }

  // A service the cashier adds at checkout (2026-10-21): pick from the active
  // services, Qty only for per-unit services, price typed (starts at the list
  // price). Saved to the visit on Pay (api/payments.php extra_services).
  const CATALOG = () => (DATA && DATA.service_catalog) || [];
  function usedServiceIds(except) {
    return [...body().querySelectorAll('tr')].filter((tr) => tr !== except)
      .map((tr) => parseInt(tr.dataset.serviceId, 10)).filter(Boolean);
  }
  function extraOptions(tr, selected) {
    const used = usedServiceIds(tr);
    return '<option value="">Choose a service</option>' + CATALOG()
      .filter((sv) => +sv.service_id === +selected || used.indexOf(+sv.service_id) === -1)
      .map((sv) => '<option value="' + sv.service_id + '"' + (+sv.service_id === +selected ? ' selected' : '') + '>' + esc(sv.service_name) + '</option>').join('');
  }
  function addExtraRow() {
    const tr = document.createElement('tr');
    tr.dataset.kind = 'extra';
    tr.dataset.serviceId = '';
    tr.innerHTML =
      '<td class="col-qty"><input type="number" class="ii-qty" value="1" min="1" max="99" readonly tabindex="-1"></td>' +
      '<td class="col-unit"><input type="text" class="ii-unit" value="" readonly tabindex="-1"></td>' +
      '<td><select class="ii-svc"></select><input type="hidden" class="ii-desc" value=""></td>' +
      '<td class="col-cost"><input type="text" inputmode="decimal" data-money class="ii-cost" value="0.00" min="0" step="0.01"></td>' +
      '<td class="col-amt amt"><span class="ii-amt">₱0.00</span>' +
        '<button type="button" class="ii-del no-print ml-2 inline-flex h-6 w-6 items-center justify-center rounded-full text-danger hover:bg-danger-soft" aria-label="Remove this service">&times;</button></td>';
    body().appendChild(tr);
    tr.querySelector('.ii-svc').innerHTML = extraOptions(tr, '');
    tr.querySelector('.ii-svc').focus();
    recalc();
  }
  function refreshExtraOptions() {
    body().querySelectorAll('tr[data-kind="extra"]').forEach((tr) => {
      const sel = tr.querySelector('.ii-svc');
      sel.innerHTML = extraOptions(tr, sel.value);
    });
    const btn = g('addSvcBtn');
    if (btn) btn.disabled = usedServiceIds(null).length >= CATALOG().length ||
      [...body().querySelectorAll('tr[data-kind="extra"] .ii-svc')].some((sel) => !sel.value);
  }
  function wireExtraRows() {
    const btn = g('addSvcBtn');
    if (!btn) return;
    btn.addEventListener('click', () => { addExtraRow(); refreshExtraOptions(); });
    body().addEventListener('change', (e) => {
      const sel = e.target.closest('.ii-svc');
      if (!sel) return;
      const tr = sel.closest('tr');
      const sv = CATALOG().find((x) => String(x.service_id) === sel.value);
      tr.dataset.serviceId = sv ? sv.service_id : '';
      tr.querySelector('.ii-desc').value = sv ? sv.service_name : '';
      tr.querySelector('.ii-unit').value = sv ? (sv.quantity_unit || 'session') : '';
      const q = tr.querySelector('.ii-qty');
      q.value = 1;
      q.readOnly = !(sv && sv.quantity_unit);
      q.tabIndex = q.readOnly ? -1 : 0;
      tr.querySelector('.ii-cost').value = sv ? window.moneyStr(sv.base_price) : '0.00';
      refreshExtraOptions();
      recalc();
    });
    body().addEventListener('click', (e) => {
      const del = e.target.closest('.ii-del');
      if (!del) return;
      del.closest('tr').remove();
      refreshExtraOptions();
      recalc();
    });
    body().addEventListener('keydown', (e) => {
      if (e.target.matches('.ii-cost, .ii-qty') && ['-', '+', 'e', 'E'].includes(e.key)) e.preventDefault();
    });
  }
  function extraServices() {
    return [...body().querySelectorAll('tr[data-kind="extra"]')].map((tr) => ({
      service_id: parseInt(tr.dataset.serviceId, 10) || 0,
      qty: Math.max(parseInt(tr.querySelector('.ii-qty').value, 10) || 1, 1),
      unit_cost: Math.max(window.moneyVal(tr.querySelector('.ii-cost')) || 0, 0),
      name: tr.querySelector('.ii-desc').value,
    }));
  }

  function render() {
    const a = DATA.appointment;
    const today = todayISO();
    const items = (DATA.line_items && DATA.line_items.length) ? DATA.line_items : [{ kind: 'service', service_id: a.service_id || '', qty: 1, unit: 'session', description: a.requested_service || '', unit_cost: 0 }];
    // Restore the payment method + its detail fields on a re-open (a settled bill,
    // or a partial being topped up) so the printed copy shows how it was actually
    // paid — GCash / Bank / Split, not a hardcoded "Cash".
    const pmSel = a.payment_mode || 'cash';
    // Installment only when the bill has an orthodontic service (migration 069).
    const instOk = !!DATA.installment_allowed;
    const chk = (v) => pmSel === v ? ' checked' : '';

    root.innerHTML =
      '<div class="inv-clinic">' +
      '<h2>' + esc(CLINIC_NAME) + '</h2>' +
      CLINIC_TOP_LINES.concat(CLINIC_ADDRESS_LINES).map((l) => '<p>' + esc(l) + '</p>').join('') +
      '<div class="inv-doctitle">SERVICE INVOICE</div>' +
      '</div>' +

      (paid ? '<div class="no-print mb-3.5 rounded-md bg-ok-soft px-3.5 py-2.5 text-[13px] text-ok dark:bg-ok/20">This invoice is fully paid. It is shown read-only. Use <strong>Print Preview</strong> for another copy.</div>' : '') +

      '<div class="inv-grid2">' +
      '<div class="inv-field"><label>Date</label><input type="text" id="f_date" value="' + esc(today) + '" readonly></div>' +
      '<div class="inv-field"><label>Phone Number</label><input type="tel" inputmode="numeric" id="f_phone" value="' + esc(DATA.patient_phone || '') + '" readonly></div>' +
      '<div class="inv-field" style="grid-column:1 / -1"><label>Received from</label><input type="text" id="f_from" value="' + esc(a.patient_name || '') + '" readonly></div>' +
      '<div class="inv-field"><label>The sum of</label><input type="text" id="f_words" readonly></div>' +
      '<div class="inv-field"><label>pesos (₱)</label><input type="text" id="f_pesos" readonly></div>' +
      '</div>' +

      '<div class="overflow-x-auto"><table class="inv-lineitems"><thead><tr>' +
      '<th class="col-qty">Qty.</th><th class="col-unit">Unit</th><th>Description</th>' +
      '<th class="col-cost">Unit Cost</th><th class="col-amt">Amount</th>' +
      '</tr></thead><tbody id="ii_body">' + items.map(rowHtml).join('') + '</tbody></table></div>' +
      (paid ? '' : '<div class="no-print mb-2 mt-1"><button type="button" id="addSvcBtn" class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-dashed border-accent bg-white px-3 py-1.5 text-[13px] font-semibold text-accent hover:bg-accent/5 disabled:cursor-not-allowed disabled:opacity-50">+ Add Service</button>' +
        '<span class="ml-2 text-[12px] text-steel-500">For a service the dentist did not record. Pick it and enter the price.</span></div>') +

      '<div class="inv-split">' +
      // ---- left: payment + discount verification ----
      '<div>' +
      '<div class="inv-field"><label>Payment Type</label>' +
      '<div class="flex flex-wrap gap-4">' +
      '<label class="check-line"><input type="radio" name="pt" value="full" checked> Full Payment</label>' +
      // Installment is left out entirely unless the bill is orthodontic.
      (instOk ? '<label class="check-line"><input type="radio" name="pt" value="installment"> Installment</label>' : '') +
      '</div></div>' +
      '<div class="inv-field"><label>Form of Payment</label>' +
      '<div class="flex flex-wrap gap-4">' +
      '<label class="check-line"><input type="radio" name="pm" value="cash"' + chk('cash') + '> Cash</label>' +
      '<label class="check-line"><input type="radio" name="pm" value="gcash"' + chk('gcash') + '> GCash</label>' +
      '<label class="check-line"><input type="radio" name="pm" value="bank"' + chk('bank') + '> Bank Transfer</label>' +
      '<label class="check-line"><input type="radio" name="pm" value="split"' + chk('split') + '> Split</label>' +
      '</div></div>' +
      // Split (adviser feedback 2026-10-11): Cash first, then GCash, then Bank.
      // Typing the Cash amount fills the rest into GCash; lowering GCash fills
      // what is left into Bank. A field locks at 0 once the methods above it
      // cover the bill. Cash Handed Over is the cash actually given; the
      // change comes out of it. GCash / bank reference blocks show once their
      // amount is > 0.
      '<div class="inv-grid2" id="pm_split" hidden>' +
      '<div class="inv-field"><label>Cash</label><input type="text" inputmode="decimal" data-money id="sp_cash" value="' + (pmSel === 'split' && Number(a.amount_cash) > 0 ? window.moneyStr(a.amount_cash) : 0) + '"></div>' +
      '<div class="inv-field"><label>Cash Handed Over</label><input type="text" inputmode="decimal" data-money id="sp_tend" placeholder="Same as Cash Amount"></div>' +
      '<div class="inv-field"><label>GCash</label><input type="text" inputmode="decimal" data-money id="sp_gcash" value="' + (pmSel === 'split' && Number(a.amount_gcash) > 0 ? window.moneyStr(a.amount_gcash) : 0) + '"></div>' +
      '<div class="inv-field"><label>Bank Transfer</label><input type="text" inputmode="decimal" data-money id="sp_bank" value="' + (pmSel === 'split' && Number(a.amount_bank) > 0 ? window.moneyStr(a.amount_bank) : 0) + '"></div>' +
      '<div class="inv-field no-print" style="grid-column:1/-1"><div class="sp-sum" id="sp_sum"></div></div>' +
      '<div class="inv-field" style="grid-column:1/-1"><div class="field-err" id="e_split" hidden></div></div>' +
      '</div>' +
      '<div class="inv-field" id="pm_gcash" hidden><label>GCash Reference No. (13 digits)</label><input type="tel" inputmode="numeric" id="f_gref" maxlength="13" pattern="[0-9]{13}" value="' + esc(a.gcash_reference_number || '') + '"><div class="field-err" id="e_gref" hidden></div></div>' +
      '<div class="inv-field" id="pm_bank" hidden><label>Bank Reference No.</label><input id="f_bref" maxlength="20" pattern="[A-Za-z0-9]{10,20}" value="' + esc(a.bank_reference || '') + '"><div class="field-err" id="e_bref" hidden></div></div>' +
      // Screen-only control — the applied promo prints as the "Less: <name>" totals row, not this picker.
      '<div class="inv-field no-print" id="promoField" hidden><label>Promotion / Discount</label>' +
      '<select id="f_promo"' + (paid ? ' disabled' : '') + '><option value="">(No promo)</option></select>' +
      '<div class="mt-1 text-[11px] text-steel-400" id="promoHint"></div></div>' +
      '<div class="inv-grid2">' +
      '<div class="inv-field"><label>Payment Date</label><input type="text" id="f_pdate" value="' + esc(a.payment_date || today) + '" readonly></div>' +
      '<div class="inv-field"><label>Total Amount</label><input type="text" id="f_totamt" readonly></div>' +
      // Senior citizen or PWD discount (2026-10-18): always visible, synced
      // with the "Less: SC/PWD Discount" checkbox in the totals panel.
      '<div class="inv-field" id="scTypeWrap"><label>SC / PWD Discount</label><select id="f_sctype"' + (paid ? ' disabled' : '') + '>' +
        '<option value="">None</option>' +
        (seniorOk() ? '<option value="senior"' + (a.sc_pwd_discount == 1 && a.sc_pwd_type !== 'pwd' ? ' selected' : '') + '>Senior Citizen (60+)</option>' : '') +
        '<option value="pwd"' + (a.sc_pwd_discount == 1 && a.sc_pwd_type === 'pwd' ? ' selected' : '') + '>PWD</option></select></div>' +
      '<div class="inv-field" id="scOscaWrap" hidden><label>OSCA / Senior Citizen ID No.</label><input id="f_osca" maxlength="20" placeholder="e.g. 2024-01234" value="' + esc(a.sc_pwd_type === 'senior' ? (a.sc_pwd_id || '') : '') + '"><div class="field-err" id="e_osca" hidden></div></div>' +
      '<div class="inv-field" id="scTinWrap" hidden><label>Sr. Citizen TIN (optional)</label><input id="f_srtin" maxlength="17" inputmode="numeric" placeholder="123-456-789-000" value="' + esc(a.sc_tin || '') + '"><div class="field-err" id="e_srtin" hidden></div></div>' +
      '<div class="inv-field" id="scPwdWrap" hidden><label>PWD ID No.</label><input id="f_pwd" maxlength="19" inputmode="numeric" placeholder="13-7405-000-0001234" value="' + esc(a.sc_pwd_type === 'pwd' ? (a.sc_pwd_id || '') : '') + '"><div class="field-err" id="e_pwd" hidden></div></div>' +
      '</div>' +
      '</div>' +
      // ---- right: totals ----
      '<div class="inv-totals">' +
      '<div class="or-row"><span>Total Sales</span><span id="t_sales">₱0.00</span></div>' +
      '<div class="or-row"><span><label class="check-line"><input type="checkbox" id="t_sc"' + (a.sc_pwd_discount == 1 ? ' checked' : '') + '> Less: ' + (seniorOk() ? 'SC/PWD' : 'PWD') + ' Discount (20%)</label></span><span id="t_disc">₱0.00</span></div>' +
      '<div class="or-row" id="promoRow" hidden><span id="promoLbl">Less: Promo</span><span id="t_promo">₱0.00</span></div>' +
      '<div class="or-row"><span>Total Due</span><span id="t_due">₱0.00</span></div>' +
      (Number(a.reservation_fee_paid) > 0 ? '<div class="or-row"><span>Less: Reservation Fee Paid' + resvRefNote(a) + '</span><span>−₱' + peso2(a.reservation_fee_paid) + '</span></div>' : '') +
      '<div class="or-row or-grand"><span>Total Amount Due</span><span id="t_bal">₱0.00</span></div>' +
      '<div class="or-row" id="recvRow"><span>Received Amount</span><span><input type="text" inputmode="decimal" data-money id="f_recv" value="0" style="width:130px;text-align:right;padding:4px 6px;border:1px solid #c7d2df;border-radius:4px;font:inherit"></span></div>' +
      '<div class="or-row no-print" id="exactRow"><span></span><label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:12px"><input type="checkbox" id="f_exact"> Exact Amount</label></div>' +
      '<div class="or-row"><span id="t_changeLbl">Change</span><span id="t_change">₱0.00</span></div>' +
      '</div>' +
      '</div>';

    // Action buttons sit in a bar below the invoice card, never on the invoice body.
    g('invActions').innerHTML =
      '<button type="button" class="' + BTN + GHOST + '" id="printBtn">Print Preview</button>' +
      (paid ? '' : '<button type="button" class="' + BTN + OK + '" id="paidBtn">Pay</button>');

    wire();
    syncPayPanels();   // reveal the reference block for the restored payment method, then recalc()
  }

  /* ---------- line-item helpers ---------- */
  const body = () => g('ii_body');
  function collectItems() {
    return [...body().querySelectorAll('tr')].map((tr) => ({
      kind: tr.dataset.kind === 'extra' ? 'service' : tr.dataset.kind,
      service_id: parseInt(tr.dataset.serviceId, 10) || null,
      qty: Math.max(parseInt(tr.querySelector('.ii-qty').value, 10) || 1, 1),
      unit: tr.querySelector('.ii-unit').value.trim(),
      description: tr.querySelector('.ii-desc').value.trim(),
      unit_cost: Math.max(window.moneyVal(tr.querySelector('.ii-cost')) || 0, 0),
    })).filter((it) => it.description !== '');
  }

  /* ---------- promotions / discounts ----------
     A promo is offered when its scope is "all" or it targets a service that is
     on the invoice right now. The deduction mirrors api/_bootstrap.php
     promoDeduction(): percentage / fixed against either the whole Total Sales
     ("all") or just the matching line items ("specific"). */
  const ALL_DISC = () => (DATA && DATA.discounts) || [];
  let promoInit = '';   // set in boot() once DATA is loaded

  function lineServiceIds() {
    return [...body().querySelectorAll('tr')]
      .map((tr) => parseInt(tr.dataset.serviceId, 10)).filter(Boolean);
  }
  function eligibleDiscounts() {
    const ids = new Set(lineServiceIds());
    return ALL_DISC().filter((d) => d.target_scope === 'all' || (d.service_ids || []).some((x) => ids.has(x)));
  }
  function discLabel(d) {
    return d.discount_name + ' · ' + (d.discount_type === 'percentage'
      ? (Number(d.discount_value) + '% off')
      : ('₱' + peso2(d.discount_value) + ' off'));
  }
  function promoDeductionFor(d, totalSales) {
    if (!d) return 0;
    let base = 0;
    if (d.target_scope === 'all') {
      base = totalSales;
    } else {
      const ids = new Set(d.service_ids || []);
      body().querySelectorAll('tr').forEach((tr) => {
        const sid = parseInt(tr.dataset.serviceId, 10);
        if (!sid || !ids.has(sid)) return;
        const q = Math.max(parseInt(tr.querySelector('.ii-qty').value, 10) || 1, 1);
        const c = Math.max(window.moneyVal(tr.querySelector('.ii-cost')) || 0, 0);
        base += Math.round(q * c * 100) / 100;
      });
    }
    if (base <= 0) return 0;
    let ded = d.discount_type === 'percentage' ? base * Number(d.discount_value) / 100 : Math.min(Number(d.discount_value), base);
    ded = Math.round(ded * 100) / 100;
    return Math.min(ded, totalSales);
  }
  function syncPromoOptions() {
    const sel = g('f_promo');
    if (!sel) return;
    const elig = eligibleDiscounts();
    const want = sel.value || promoInit;
    promoInit = '';
    sel.innerHTML = '<option value="">(No promo)</option>' +
      elig.map((d) => '<option value="' + d.id + '">' + esc(discLabel(d)) + '</option>').join('');
    if (want && elig.some((d) => String(d.id) === String(want))) sel.value = String(want);
    g('promoField').hidden = !(elig.length || (paid && DATA.applied_promo));
    if (paid && DATA.applied_promo && !elig.length) {
      sel.innerHTML = '<option value="">' + esc(DATA.applied_promo.label || 'Promo applied') + '</option>';
    }
  }

  /* ---------- split payment (Cash -> GCash -> Bank) ---------- */
  let splitSrc = null;   // which split field the cashier just typed in
  const money = (id) => Math.max(Math.round((window.moneyVal(g(id)) || 0) * 100) / 100, 0);
  const setMoney = (id, v) => { g(id).value = v > 0 ? window.moneyStr(Math.round(v * 100) / 100) : '0'; };
  // Keeps the three amounts adding up to the balance due. In Full Payment the
  // lower-priority fields are filled with what is left and locked at 0 once the
  // fields above cover the bill; an Installment only caps them at the balance.
  function balanceSplit(bal, installment) {
    const src = splitSrc; splitSrc = null;
    let cash = Math.min(money('sp_cash'), bal);
    let gc = money('sp_gcash');
    let bk = money('sp_bank');
    if (src === 'sp_cash') {
      if (!installment) { gc = bal - cash; bk = 0; }
    } else if (src === 'sp_gcash') {
      gc = Math.min(gc, bal - cash);
      if (!installment) bk = bal - cash - gc;
    }
    gc = Math.min(gc, Math.max(bal - cash, 0));
    bk = Math.min(bk, Math.max(bal - cash - gc, 0));
    // A change in the bill (promo, SC/PWD) keeps Cash and GCash and refills Bank.
    if (!installment && src === null && cash + gc + bk < bal && (cash + gc) > 0) bk = bal - cash - gc;
    if (src !== 'sp_cash' || money('sp_cash') !== cash) setMoney('sp_cash', cash);
    if (src !== 'sp_gcash' || money('sp_gcash') !== gc) setMoney('sp_gcash', gc);
    if (src !== 'sp_bank' || money('sp_bank') !== bk) setMoney('sp_bank', bk);
    const coverCash = Math.round(cash * 100) >= Math.round(bal * 100);
    const coverTwo = Math.round((cash + gc) * 100) >= Math.round(bal * 100);
    g('sp_gcash').disabled = !installment && coverCash && bal > 0;
    g('sp_bank').disabled = !installment && coverTwo && bal > 0;
    return { cash, gc, bk };
  }

  /* ---------- totals ---------- */
  function recalc() {
    let totalSales = 0;
    body().querySelectorAll('tr').forEach((tr) => {
      const q = Math.max(parseInt(tr.querySelector('.ii-qty').value, 10) || 1, 1);
      const c = Math.max(window.moneyVal(tr.querySelector('.ii-cost')) || 0, 0);
      const amt = Math.round(q * c * 100) / 100;
      tr.querySelector('.ii-amt').textContent = '₱' + peso2(amt);
      totalSales += amt;
    });

    const a = DATA.appointment;
    const sc = g('t_sc').checked;
    const disc = sc ? Math.round(totalSales * SPR * 100) / 100 : 0;

    syncPromoOptions();
    const promoSel = g('f_promo');
    const promoD = (promoSel && promoSel.value) ? ALL_DISC().find((d) => String(d.id) === promoSel.value) : null;
    let promoDed = promoD ? promoDeductionFor(promoD, totalSales) : 0;
    if (paid && DATA.applied_promo && !promoD) promoDed = Number(DATA.applied_promo.amount) || 0;
    if (g('promoRow')) {
      if (promoDed > 0) {
        g('promoRow').hidden = false;
        g('promoLbl').textContent = 'Less: ' + (promoD ? promoD.discount_name : (DATA.applied_promo.label || 'Promo'));
        g('t_promo').textContent = '−₱' + peso2(promoDed);
      } else {
        g('promoRow').hidden = true;
      }
    }
    if (g('promoHint')) {
      g('promoHint').textContent = promoD
        ? (promoD.target_scope === 'all'
            ? 'Applies to the whole bill.'
            : 'Applies only to matching services on this invoice.') + (promoDed > 0 ? '' : ' No matching service on the invoice yet.')
        : '';
    }

    const totalDue = Math.round((totalSales - disc - promoDed) * 100) / 100;
    const reservation = Number(a.reservation_fee_paid) || 0;
    const priorPaid = (Number(a.amount_cash) || 0) + (Number(a.amount_gcash) || 0) + (Number(a.amount_bank) || 0);
    const bal = Math.max(Math.round((totalDue - reservation - priorPaid) * 100) / 100, 0);
    const pm = document.querySelector('input[name="pm"]:checked');
    const splitMode = pm && pm.value === 'split';
    // Nothing left to collect (e.g. the reservation covers the whole bill):
    // Received Amount is locked at 0 so no phantom payment or change is entered.
    const zeroDue = !paid && Math.round(bal * 100) <= 0;
    ['sp_cash', 'sp_gcash', 'sp_bank'].forEach((id) => {
      if (!g(id)) return;
      if (zeroDue) g(id).value = '0';
      g(id).readOnly = zeroDue;
    });
    if (!paid && g('f_exact')) {
      if (zeroDue) g('f_exact').checked = true;
      g('f_exact').disabled = zeroDue || splitMode;
    }
    if (!paid && g('f_recv')) {
      if (zeroDue) g('f_recv').value = '0';
      g('f_recv').readOnly = zeroDue || splitMode || !!(g('f_exact') && g('f_exact').checked);
    }

    const ptSel = document.querySelector('input[name="pt"]:checked');
    const instMode = !!(ptSel && ptSel.value === 'installment');
    let recv;
    let splitInfo = null;
    if (splitMode) {
      const sp = paid ? { cash: money('sp_cash'), gc: money('sp_gcash'), bk: money('sp_bank') } : balanceSplit(bal, instMode);
      const tendRaw = g('sp_tend').value.trim();
      const tend = tendRaw === '' ? sp.cash : money('sp_tend');
      const cashChange = Math.max(Math.round((tend - sp.cash) * 100) / 100, 0);
      const portions = Math.round((sp.cash + sp.gc + sp.bk) * 100) / 100;
      const left = Math.max(Math.round((bal - portions) * 100) / 100, 0);
      recv = Math.round((Math.max(tend, sp.cash) + sp.gc + sp.bk) * 100) / 100;
      splitInfo = { ...sp, tend, cashChange, portions, left, short: tendRaw !== '' && tend < sp.cash };
      if (g('f_recv')) g('f_recv').value = window.moneyStr(recv);
      // A reference block shows only for a method that is actually used.
      g('pm_gcash').hidden = !(sp.gc > 0);
      g('pm_bank').hidden = !(sp.bk > 0);
      if (g('sp_sum')) {
        g('sp_sum').innerHTML =
          '<div class="flex flex-wrap gap-x-5 gap-y-1 rounded-md bg-steel-50 px-3 py-2 text-[12.5px] dark:bg-white/5">' +
          '<span>Split total: <strong>₱' + peso2(portions) + '</strong></span>' +
          '<span class="' + (left > 0 ? 'text-danger font-semibold' : 'text-ok font-semibold') + '">' + (left > 0 ? 'Still to collect: ₱' + peso2(left) : '✓ Balanced') + '</span>' +
          (sp.cash > 0 ? '<span>Cash due ₱' + peso2(sp.cash) + ' · Handed over ₱' + peso2(tend) + ' · <strong>Change ₱' + peso2(cashChange) + '</strong></span>' : '') +
          '</div>';
      }
    } else {
      // "Exact Amount": Received Amount follows the balance due, so a later
      // discount / promo / line change keeps it in step.
      if (!paid && g('f_exact') && g('f_exact').checked) g('f_recv').value = window.moneyStr(bal);
      recv = Math.max(window.moneyVal(g('f_recv')) || 0, 0);
    }
    const pt = document.querySelector('input[name="pt"]:checked');
    const installment = pt && pt.value === 'installment';
    // The reservation paid online was more than the final bill: the difference
    // is handed back with the change (2026-10-26, settle records it).
    const excessResv = installment ? 0 : Math.max(Math.round((reservation + priorPaid - totalDue) * 100) / 100, 0);
    const change = Math.round(((splitInfo ? splitInfo.cashChange : (installment ? 0 : Math.max(recv - bal, 0))) + excessResv) * 100) / 100;

    if (splitMode && g('e_split') && !paid) {
      const err = g('e_split');
      const msg = !splitInfo || splitInfo.portions <= 0 ? 'Enter how much was paid in each method, starting with Cash.'
        : splitInfo.short ? 'Cash Handed Over cannot be less than the cash amount (₱' + peso2(splitInfo.cash) + ').'
        : (!installment && splitInfo.left > 0) ? 'The split adds up to ₱' + peso2(splitInfo.portions) + '. ₱' + peso2(splitInfo.left) + ' is still to collect.'
        : '';
      err.textContent = msg; err.hidden = !msg;
    }
    // Pay stays disabled while the cash handed over is less than the cash
    // amount. Only this rule's own lock is lifted here, so a duplicate
    // reference number (wireRefCheck) still keeps the button disabled.
    const payBtn = g('paidBtn');
    if (payBtn && !paid) {
      const shortTend = !!(splitMode && splitInfo && splitInfo.short);
      if (shortTend) { payBtn.disabled = true; payBtn.dataset.tendLock = '1'; }
      else if (payBtn.dataset.tendLock) { delete payBtn.dataset.tendLock; payBtn.disabled = false; }
    }

    g('t_sales').textContent = '₱' + peso2(totalSales);
    g('t_disc').textContent = '₱' + peso2(disc);
    g('t_due').textContent = '₱' + peso2(totalDue);
    g('t_bal').textContent = '₱' + peso2(bal);

    if (paid) {
      // A settled invoice prints as a finished receipt. "Total Amount Due" / the
      // "sum of" / pesos / Total Amount fields all show what was billable at the
      // counter (Total Due less the reservation credit), NOT the now-zero
      // remaining balance — a ₱0.00 there reads as if the visit was free.
      // Received Amount + Change show the settlement. Matches shared/receipt.php.
      const billable = Math.max(Math.round((totalDue - reservation) * 100) / 100, 0);
      const recvPaid = Number(a.received_amount) || Math.round((reservation + priorPaid) * 100) / 100;
      g('t_bal').textContent = '₱' + peso2(billable);
      g('f_recv').value = window.moneyStr(recvPaid);
      g('f_recv').readOnly = true;
      if (g('exactRow')) g('exactRow').hidden = true;
      g('t_changeLbl').textContent = Number(a.reservation_refund) > 0 ? 'Change (excess reservation)' : 'Change';
      g('t_change').textContent = '₱' + peso2(Number(a.change_amount) || 0);
      g('f_totamt').value = '₱' + peso2(billable);
      g('f_words').value = pesosToWords(billable);
      g('f_pesos').value = peso2(billable);
      return { totalSales, bal };
    }

    if (installment) {
      const remainingAfter = Math.max(Math.round((bal - recv) * 100) / 100, 0);
      g('t_changeLbl').textContent = 'Balance after downpayment';
      g('t_change').textContent = '₱' + peso2(remainingAfter);
    } else {
      g('t_changeLbl').textContent = excessResv > 0 ? 'Change (excess reservation)' : 'Change';
      g('t_change').textContent = '₱' + peso2(change);
    }
    g('f_totamt').value = '₱' + peso2(bal);
    g('f_words').value = pesosToWords(bal);
    g('f_pesos').value = peso2(bal);

    return { totalSales, bal };
  }

  function setErr(inputId, errId, msg) {
    const inp = g(inputId), err = g(errId);
    if (!inp || !err) return;
    if (msg) { inp.classList.add('is-invalid'); err.textContent = msg; err.hidden = false; }
    else { inp.classList.remove('is-invalid'); err.hidden = true; }
  }

  // Same GCash / Bank field rules as the patient booking form.
  function validatePayMode(mode) {
    if (mode === 'gcash') {
      const bad = !/^\d{13}$/.test(g('f_gref').value.trim());
      setErr('f_gref', 'e_gref', bad ? 'GCash Reference Number must be exactly 13 digits' : '');
      return !bad;
    }
    if (mode === 'bank') {
      const badR = !/^[A-Za-z0-9]{10,20}$/.test(g('f_bref').value.trim());
      setErr('f_bref', 'e_bref', badR ? 'Bank Reference Number must be 10-20 characters' : '');
      return !badR;
    }
    if (mode === 'split') {
      const c = window.moneyVal(g('sp_cash')) || 0;
      const gc = window.moneyVal(g('sp_gcash')) || 0;
      const bk = window.moneyVal(g('sp_bank')) || 0;
      const err = g('e_split');
      if (c + gc + bk <= 0) { err.textContent = 'Enter how much was paid in each method, starting with Cash.'; err.hidden = false; return false; }
      if (!err.hidden && err.textContent) return false;   // short tender / unbalanced split (set by recalc)
      // only validate a method's reference block when that method has an amount
      const okG = gc > 0 ? validatePayMode('gcash') : true;
      const okB = bk > 0 ? validatePayMode('bank') : true;
      return okG && okB;
    }
    return true;
  }

  // Show/hide the split panel and the GCash / bank reference blocks. In split
  // mode a reference block appears once its amount is > 0; Received Amount is
  // driven by the split total.
  function syncPayPanels() {
    const m = document.querySelector('input[name="pm"]:checked').value;
    const split = m === 'split';
    const spG = split && (window.moneyVal(g('sp_gcash')) || 0) > 0;
    const spB = split && (window.moneyVal(g('sp_bank')) || 0) > 0;
    // Use the `hidden` attribute (not style.display) so the print stylesheet's
    // `.inv-grid2 { display:grid !important }` can't force a non-selected
    // method's panel back onto the printed invoice.
    g('pm_split').hidden = !split;
    g('pm_gcash').hidden = !(m === 'gcash' || spG);
    g('pm_bank').hidden = !(m === 'bank' || spB);
    if (g('f_exact')) {
      // Split mode already drives Received Amount from the three method amounts.
      if (split) g('f_exact').checked = false;
      g('f_exact').disabled = split;
    }
    if (g('f_recv')) g('f_recv').readOnly = split || !!(g('f_exact') && g('f_exact').checked);
    if (!split && g('e_split')) g('e_split').hidden = true;
    // reset any stale reference-check state so it doesn't block the other method
    ['f_gref', 'f_bref'].forEach((id) => g(id) && g(id)._refCheckReset && g(id)._refCheckReset());
    if (g('paidBtn')) g('paidBtn').disabled = false;
    recalc();
  }

  function wire() {
    wireExtraRows();
    root.addEventListener('input', (e) => {
      if (e.target.closest('.inv-lineitems') || e.target.id === 'f_recv') recalc();
    });

    // Live "already used?" check on the GCash / bank reference fields.
    const payId = DATA.appointment.payment_id;
    if (g('f_gref')) wireRefCheck(g('f_gref'), {
      errEl: g('e_gref'), submitBtn: g('paidBtn'), excludePayment: payId,
      format: { re: /^\d{13}$/, msg: 'GCash Reference Number must be exactly 13 digits' },
    });
    if (g('f_bref')) wireRefCheck(g('f_bref'), {
      errEl: g('e_bref'), submitBtn: g('paidBtn'), excludePayment: payId,
      format: { re: /^[A-Za-z0-9]{10,20}$/, msg: 'Bank Reference Number must be 10-20 characters' },
    });
    if (g('f_phone')) wirePhoneField(g('f_phone'));
    g('t_sc').addEventListener('change', () => {
      // Ticking with "None" picked defaults to Senior (60+) or PWD; unticking clears it.
      if (g('t_sc').checked && !g('f_sctype').value) g('f_sctype').value = seniorOk() ? 'senior' : 'pwd';
      if (!g('t_sc').checked) g('f_sctype').value = '';
      syncSc(); recalc();
    });
    g('f_sctype').addEventListener('change', () => {
      g('t_sc').checked = !!g('f_sctype').value;
      syncSc(); recalc();
    });
    // Type-as-you-go formatting: TIN 123-456-789-000, PWD 13-7405-000-0001234.
    const groupDigits = (el, sizes) => el.addEventListener('input', () => {
      const d = el.value.replace(/\D/g, '').slice(0, sizes.reduce((x, y) => x + y, 0));
      const out = []; let i = 0;
      for (const n of sizes) { if (i >= d.length) break; out.push(d.slice(i, i + n)); i += n; }
      el.value = out.join('-');
      scFieldErr(el, '');
    });
    groupDigits(g('f_srtin'), [3, 3, 3, 5]);
    groupDigits(g('f_pwd'), [2, 4, 3, 7]);
    g('f_osca').addEventListener('input', () => {
      g('f_osca').value = g('f_osca').value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
      scFieldErr(g('f_osca'), '');
    });
    syncSc();
    if (g('f_exact')) g('f_exact').addEventListener('change', () => {
      g('f_recv').readOnly = g('f_exact').checked;
      if (!g('f_exact').checked) g('f_recv').value = '0';   // unticking clears it
      recalc();
    });
    if (g('f_promo')) g('f_promo').addEventListener('change', recalc);
    document.getElementsByName('pt').forEach((r) => r.addEventListener('change', recalc));
    document.getElementsByName('pm').forEach((r) => r.addEventListener('change', syncPayPanels));
    ['sp_cash', 'sp_gcash', 'sp_bank'].forEach((id) => g(id) && g(id).addEventListener('input', () => { splitSrc = id; syncPayPanels(); }));
    if (g('sp_tend')) g('sp_tend').addEventListener('input', recalc);
    // No negative amounts anywhere on the payment side.
    root.addEventListener('keydown', (e) => {
      if (e.target.matches('#pm_split input, #f_recv') && ['-', '+', 'e', 'E'].includes(e.key)) e.preventDefault();
    });
    g('printBtn').addEventListener('click', () => printInvoice());
    if (g('paidBtn')) g('paidBtn').addEventListener('click', markPaid);
  }

  // OR series (migration 084): the receipt loaded in the printer must match the
  // system's next OR number before a payment is taken.
  const OR_SETUP = '/dgt_dental_clinic/modules/cashier/or_setup';
  // OR number printed at the bottom left of the invoice: the one this bill was
  // paid on, else (an unpaid preview) the next number in the series.
  // The OR No. printed under the printer block: the saved receipt's number,
  // or for a payment not yet made the series' next number.
  function printOrNo(useLast) {
    const a = DATA && DATA.appointment;
    if (useLast && a && a.or_number) return a.or_number;
    return (DATA && DATA.or && DATA.or.series && DATA.or.next) || (a && a.or_number) || '';
  }
  g('orBar') && g('orBar').addEventListener('click', (e) => {
    if (e.target.closest('[data-realign]')) { try { sessionStorage.setItem('dgt_or_realign', '1'); } catch (_) {} }
  });
  function renderOrBar() {
    const bar = g('orBar');
    const o = DATA && DATA.or;
    if (!bar || !o) return;
    const orNo = DATA.appointment && DATA.appointment.or_number;
    if (paid) {
      bar.innerHTML = orNo ? '<div class="rounded-lg border border-steel-100 bg-white px-4 py-2.5 text-[13px] text-steel-600 dark:border-white/10 dark:bg-navy-800 dark:text-steel-300">Paid on <strong class="tabular-nums text-navy-900 dark:text-steel-50">OR No. ' + esc(orNo) + '</strong></div>' : '';
      return;
    }
    if (!o.series) {
      bar.innerHTML = '<div class="rounded-lg border border-warn/40 bg-warn-soft px-4 py-3 text-[13px] text-[#9a5a0a] dark:bg-warn/20 dark:text-[#f3c77e]">' +
        '<strong>No OR series is set up</strong>, so payments cannot be taken. ' +
        '<a data-nav href="' + OR_SETUP + '" class="font-semibold underline">Go to OR / Invoice Setup</a></div>';
      const b = g('paidBtn'); if (b) { b.disabled = true; b.dataset.orLock = '1'; }
      return;
    }
    if (o.next_voided) {
      // The admin voided the receipt waiting in the printer (migration 085).
      bar.innerHTML = '<div class="rounded-lg border border-danger/40 bg-danger-soft px-4 py-3 text-[13px] text-danger dark:bg-danger/20">' +
        '<strong>OR No. ' + esc(o.next) + ' was voided by the admin</strong>, so payments cannot be taken on it. Load the next fresh receipt and re-align. ' +
        '<a href="' + OR_SETUP + '" data-nav data-realign class="font-semibold underline">Re-align Next Number</a></div>';
      const b = g('paidBtn'); if (b) { b.disabled = true; b.dataset.orLock = '1'; }
      return;
    }
    bar.innerHTML = '<div class="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-lg border border-steel-100 bg-white px-4 py-2.5 text-[13px] text-steel-600 dark:border-white/10 dark:bg-navy-800 dark:text-steel-300">' +
      '<span>Transaction <strong class="text-[15px] text-navy-900 dark:text-steel-50">#' + o.next_txn + '</strong> today</span>' +
      '<span>Next OR No. <strong class="text-[15px] tabular-nums text-navy-900 dark:text-steel-50">' + esc(o.next) + '</strong></span>' +
      '<span class="' + (o.remaining <= 3 ? 'font-semibold text-danger' : '') + '">' + o.remaining + ' left in ' + esc(o.series.start) + ' to ' + esc(o.series.end) + '</span>' +
      '<a data-nav data-realign href="' + OR_SETUP + '" class="ml-auto text-[12.5px] font-semibold text-accent hover:underline">Re-align Next Number</a></div>';
  }

  // Online bookings only: the reservation payment's reference no., so the
  // cashier can check the fee was really paid (e.g. "GCash Ref: 1234567890123").
  function resvRefNote(a) {
    if (a.booking_type !== 'online' || !a.reservation_reference) return '';
    const m = a.reservation_method;
    const lbl = m === 'gcash' ? 'GCash' : m === 'bank' ? 'Bank' : (a.reservation_method_label || 'Payment');
    return ' <span class="no-print ml-1 whitespace-nowrap text-[12px] font-normal text-steel-500">(' + esc(lbl) + ' Ref: <strong class="tabular-nums text-navy-800">' + esc(a.reservation_reference) + '</strong>)</span>';
  }

  async function markPaid() {
    const extras = extraServices();
    if (extras.some((x) => !x.service_id)) return toast('Choose a service for every added row, or remove the empty row.', 'err');
    const noPrice = extras.find((x) => x.unit_cost <= 0);
    if (noPrice) return toast('Enter the price for ' + noPrice.name + '.', 'err');
    const items = collectItems();
    if (!items.length) return toast('Add at least one line item with a description.', 'err');
    const { bal } = recalc();
    const recv = Math.max(window.moneyVal(g('f_recv')) || 0, 0);
    const partial = document.querySelector('input[name="pt"]:checked').value === 'installment';
    const zeroDue = Math.round(bal * 100) <= 0;   // e.g. the reservation fee covers the whole bill
    if (recv <= 0 && !zeroDue) return toast('Enter the amount received.', 'err');
    // Exact centavos, no tolerance: ₱1,799.99 does not cover ₱1,800.00.
    if (!partial && Math.round(recv * 100) < Math.round(bal * 100)) return toast('Received amount ₱' + peso2(recv) + ' is less than the Total Amount Due ₱' + peso2(bal) + '. Enter the exact amount or more, or choose Installment.', 'err');

    const mode = document.querySelector('input[name="pm"]:checked').value;
    const scErr = scError();
    if (scErr) return toast(scErr, 'err');
    if (!zeroDue && !validatePayMode(mode)) return toast('Please correct the highlighted payment fields.', 'err');
    const body_ = {
      appointment_id: APPT_ID,
      payment_type: partial ? 'installment' : 'full',
      line_items: items,
      extra_services: extras.map((x) => ({ service_id: x.service_id, qty: x.qty, unit_cost: x.unit_cost })),
      sc_pwd_discount: g('t_sc').checked,
      sc_pwd_id: g('t_sc').checked ? scIdValue() : '',
      sc_pwd_type: g('t_sc').checked ? g('f_sctype').value : '',
      sc_tin: g('t_sc').checked && g('f_sctype').value === 'senior' ? g('f_srtin').value.trim() : '',
      promo_discount_id: (g('f_promo') && g('f_promo').value) ? g('f_promo').value : 0,
      payment_mode: mode,
      received_amount: window.moneyVal(g('f_recv')),
      gcash_reference_number: g('f_gref') ? g('f_gref').value.trim() : '',
      bank_reference: g('f_bref') ? g('f_bref').value.trim() : '',
    };
    if (mode === 'split') {
      const tendRaw = g('sp_tend').value.trim();
      body_.split_cash = tendRaw === '' ? window.moneyVal(g('sp_cash')) : Math.max(window.moneyVal(tendRaw) || 0, window.moneyVal(g('sp_cash')) || 0);
      body_.split_gcash = window.moneyVal(g('sp_gcash'));
      body_.split_bank = window.moneyVal(g('sp_bank'));
    }

    const orInfo = DATA.or || {};
    if (!orInfo.series) return toast('Set up the OR series in OR / Invoice Setup first.', 'err');
    body_.or_number = orInfo.next;

    const btn = g('paidBtn');
    btn.disabled = true; btn.textContent = 'Saving…';
    try {
      const { payment } = await api('payments.php?action=settle', { method: 'POST', body: body_ });
      if (payment.payment_status === 'fully_paid') {
        toast('Payment complete on OR No. ' + payment.or_number + ' (transaction #' + payment.day_seq + ' today). Printing…');
        paid = true;
        // Re-render as the settled read-only receipt FIRST, then print — printing
        // on a blind timer could fire mid-refresh and capture a half-built page.
        DATA = await api('payments.php?action=invoice&id=' + APPT_ID);
        render();
        renderOrBar();
        setTimeout(() => printInvoice(true), 300);
      } else {
        toast('Installment of ₱' + peso2(payment.applied) + ' recorded on OR No. ' + payment.or_number + '. Balance: ₱' + peso2(payment.remaining_balance) + '. Printing…');
        // Print the Charge Sales invoice for this installment, then leave the
        // form ready for the next payment.
        DATA = await api('payments.php?action=invoice&id=' + APPT_ID);
        render();
        renderOrBar();
        setTimeout(() => printInvoice(true), 300);
      }
    } catch (e) {
      toast(e.message, 'err');
      btn.disabled = false; btn.textContent = 'Pay';
      // The next OR number moved on (another payment / re-align): show the current one.
      if (/OR number|OR series/i.test(e.message)) {
        try { DATA.or = (await api('payments.php?action=invoice&id=' + APPT_ID)).or; renderOrBar(); } catch (_) {}
      }
    }
  }

  boot();
})();
JS;
$PAGE_SCRIPT = str_replace('__APPT__', json_encode($appointmentId), $PAGE_SCRIPT);
require __DIR__ . '/../../includes/layout.php';
