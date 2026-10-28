<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['admin', 'cashier']);
$PAGE_TITLE = 'Revenue Reports';
$ACTIVE = 'reports';
$PAGE_FULL = true;   // admin screens fill the full viewport width

$clinicName = setting('clinic_name', 'DGT One Dental Center - Bulacan');
$preparedBy = trim((string) ($me['full_name'] ?? '')) ?: ($me['username'] ?? 'Staff');

$inputCls = 'rounded-md border border-steel-200 bg-white px-2.5 py-1.5 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
$tabCls   = 'tab cursor-pointer rounded-full border border-steel-200 bg-white px-4 py-2 text-[13px] font-semibold text-steel-500 dark:border-white/15 dark:bg-navy-800 dark:text-steel-300 '
          . '[&.active]:border-navy-700 [&.active]:bg-navy-700 [&.active]:text-white';
$tableCls = 'w-full table-fixed border-collapse text-[13px] '
    . "[&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 "
    . '[&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:dark:border-white/10 [&_tbody_tr:hover]:bg-steel-50 [&_tbody_tr:hover]:dark:bg-white/5 [&_.amt]:text-right [&_th.amt]:text-right 2xl:text-[14.5px] 2xl:[&_td]:py-3.5 2xl:[&_th]:py-3';

ob_start();
?>
<script src="<?= e(asset('assets/js/vendor/html2pdf.bundle.min.js')) ?>"></script>

<div class="rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800">
    <div class="mb-2 flex flex-wrap items-center gap-3 sm:gap-4">
        <button class="inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md bg-ok px-[15px] py-[9px] text-[13px] font-semibold text-white hover:brightness-95" id="pdfBtn"><svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 11v6"/><path d="m9 14 3 3 3-3"/></svg> Generate PDF</button>
        <button class="inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md border border-steel-200 bg-white px-[15px] py-[9px] text-[13px] font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:bg-navy-900 dark:text-steel-100 dark:hover:bg-white/5" id="previewBtn"><svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg> Preview Report</button>
        <label class="flex items-center gap-2 text-[13px] dark:text-steel-100">Filter by:
            <span class="inline-flex gap-1.5" id="periodTabs">
                <button class="<?= $tabCls ?>" data-p="day">Daily</button>
                <button class="<?= $tabCls ?>" data-p="week">Weekly</button>
                <button class="<?= $tabCls ?> active" data-p="month">Monthly</button>
            </span>
        </label>
        <input type="date" id="anchorDate" class="<?= $inputCls ?> w-full sm:w-auto">
        <!-- Phones: "Date Range" label on its own line, then From / To side by
             side as two equal boxes; from sm: up it's the original inline row. -->
        <div class="w-full text-[13px] sm:flex sm:w-auto sm:items-center sm:gap-2 dark:text-steel-100">
            <span class="mb-1.5 block font-medium text-steel-500 sm:mb-0 sm:inline sm:font-normal sm:text-inherit dark:text-steel-300 sm:dark:text-steel-100">Date Range:</span>
            <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 sm:flex">
                <input type="date" id="fromDate" aria-label="From date" class="<?= $inputCls ?> w-full min-w-0 sm:w-auto">
                <span class="text-steel-400 dark:text-steel-500">to</span>
                <input type="date" id="toDate" aria-label="To date" class="<?= $inputCls ?> w-full min-w-0 sm:w-auto">
            </div>
        </div>
    </div>

    <div class="mt-3.5 flex flex-wrap items-center justify-between gap-3 border-t border-steel-100 pt-3.5 dark:border-white/10">
        <h2 class="text-[15px] text-navy-800 dark:text-steel-50">Patient Transactions</h2>
        <button type="button" id="totalBtn" class="tap-sm inline-flex cursor-pointer items-center gap-1.5 rounded-md bg-transparent px-1.5 py-1 text-steel-400 hover:bg-steel-50 dark:text-steel-500 dark:hover:bg-white/5" title="See the breakdown">Total Revenue: <strong class="text-base font-bold text-navy-900 dark:text-steel-50" id="totalRevenue">N/A</strong><span aria-hidden="true" class="text-steel-300">&rsaquo;</span></button>
    </div>
    <p class="text-[11px] text-steel-400 dark:text-steel-500" id="periodLabel"></p>
    <p class="text-[11px] text-steel-400 dark:text-steel-500">Prepared By: <?= e($preparedBy) ?></p>

    <div class="mt-2 overflow-x-auto">
        <table class="rtable rt-plain <?= $tableCls ?>" id="revTable" style="table-layout:fixed !important;width:100%">
            <colgroup>
                <col style="width:6%"><col style="width:34%"><col style="width:30%">
                <col style="width:16%"><col style="width:14%">
            </colgroup>
            <thead><tr>
                <th>#</th><th>Name</th><th>Service</th>
                <th class="!text-left whitespace-nowrap">Date of Service</th><th class="amt">Cost</th>
            </tr></thead>
            <tbody><tr><td colspan="5" class="py-7 text-center text-steel-400 dark:text-steel-500">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
    <nav id="revPager" class="mt-3 flex flex-wrap items-center justify-between gap-3" aria-label="Transaction pages" hidden></nav>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);
  const CLINIC = __CLINIC__;
  const PREPARED_BY = __PREPARED_BY__;

  // Instructor-specified format: "YYYY-MM-DD | HH:MM AM/PM" — distinct from
  // the app's usual toLocaleString formatting, so this is its own helper.
  function reportTimestamp() {
    const now = new Date();
    const p2 = (n) => String(n).padStart(2, '0');
    let h = now.getHours();
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return now.getFullYear() + '-' + p2(now.getMonth() + 1) + '-' + p2(now.getDate()) +
      ' | ' + p2(h) + ':' + p2(now.getMinutes()) + ' ' + ampm;
  }
  const EMPTY = '<tr><td colspan="5" class="py-7 text-center text-steel-400 dark:text-steel-500">';
  let period = 'month';
  let last = null;                       // most recent {from, to, rows, total}

  const today = todayISO();
  g('anchorDate').value = today;

  function query() {
    const from = g('fromDate').value, to = g('toDate').value;
    if (from && to) return 'period=range&from=' + from + '&to=' + to;
    return 'period=' + period + '&date=' + (g('anchorDate').value || today);
  }

  // Revenue Reports show every date as mm-dd-yyyy.
  const mdy = (s) => {
    const m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? m[2] + '-' + m[3] + '-' + m[1] : (s || 'N/A');
  };
  const rangeLabel = (from, to) => from === to ? mdy(from) : mdy(from) + ' – ' + mdy(to);

  async function load() {
    const tb = document.querySelector('#revTable tbody');
    try {
      const d = await api('reports.php?action=revenue&' + query());
      last = d;
      g('totalRevenue').textContent = peso(d.total);
      g('periodLabel').textContent = 'Showing ' + rangeLabel(d.from, d.to) +
        ' · ' + d.rows.length + ' transaction' + (d.rows.length === 1 ? '' : 's');
      PG.page = 1;
      drawRows();
    } catch (e) { tb.innerHTML = EMPTY + esc(e.message) + '</td></tr>'; }
  }

  // Patient Transactions: 10 per page (window.pageSlice, app.js). The PDF still lists every row.
  const PG = { page: 1 };
  window.fitOnResize(() => { if (document.getElementById('revTable')) drawRows(); });
  function drawRows() {
    const tb = document.querySelector('#revTable tbody');
    if (!tb || !last) return;
    const { rows, from } = window.pageSlice(last.rows, PG, window.fitRows(g('revTable')), g('revPager'), drawRows);
    tb.innerHTML = rows.length ? rows.map((r, i) =>
        '<tr class="cursor-pointer" data-rev="' + (from + i) + '" tabindex="0" title="View transaction"><td class="rt-hide">' + (from + i + 1) + '</td><td class="rt-title break-words"><strong>' + esc(r.patient_name) + '</strong></td>' +
        '<td class="break-words">' + esc(r.service || 'N/A') + '</td>' +
        '<td class="text-left whitespace-nowrap">' + mdy(r.date_of_service) + '</td>' +
        '<td class="amt">' + peso(r.amount) + '</td></tr>'
      ).join('') : EMPTY + 'No revenue in this period.</td></tr>';
  }

  /* ---------- Payment-method label (for the itemized table's Method column) ---------- */
  // Short labels — the Method column is a narrow 8% (~57px) fixed width.
  const METHOD_LABEL = { cash: 'Cash', gcash: 'GCash', bank: 'Bank', split: 'Split' };
  const methodLabel = (m) => METHOD_LABEL[m] || 'N/A';

  /* ---------- The report document node ----------
     Built once, used by BOTH "Generate PDF" (html2pdf renders this node) and
     "Preview Report" (the modal shows this same node — same layout, no download).
     A4 portrait content area at 96dpi with ~10mm side margins ≈ 718px.

     `table-layout:fixed` with an explicit per-column % (# 5 / Name 34 /
     Service 33 / Date 16 / Amount 12 = 100). Specialist, Status and Method
     were removed 2026-10-18 at the user's request. Name / Service wrap; # and
     Date stay on one line, so the table never overflows the 718px page. */
  // The PDF / Preview range ends at the last transaction's date (2026-10-22),
  // e.g. 10-01 to 10-20 when the last payment was Oct 20. No transactions:
  // it stops at today while the period is still running.
  const docTo = (d) => {
    const last = (d.rows || []).reduce((m, r) => { const x = String(r.date_of_service || '').slice(0, 10); return x > m ? x : m; }, '');
    if (last) return last;
    const t = todayISO(); return d.to > t && d.from <= t ? t : d.to;
  };

  function buildDoc(d) {
    const CELL = 'padding:7px 10px;border-bottom:1px solid #e2e8f0;vertical-align:top;word-break:break-word;overflow-wrap:anywhere';
    const NOWRAP = CELL + ';white-space:nowrap';
    const AMT  = CELL + ';text-align:right;white-space:nowrap;padding-left:2px;padding-right:14px';
    const rowsHtml = d.rows.length
      ? d.rows.map((r, i) =>
          '<tr>' +
          '<td style="' + NOWRAP + '">' + (i + 1) + '</td>' +
          '<td style="' + CELL + '">' + esc(r.patient_name) + '</td>' +
          '<td style="' + CELL + '">' + esc(r.service || 'N/A') + '</td>' +
          '<td style="' + NOWRAP + '">' + mdy(r.date_of_service) + '</td>' +
          '<td style="' + AMT + '">' + peso(r.amount) + '</td>' +
          '</tr>').join('')
      : '<tr><td colspan="5" style="text-align:center;padding:20px;color:#888">No revenue in this period.</td></tr>';

    const heads = ['#', 'Name', 'Service', 'Date of Service', 'Amount'];
    const th = (h, i) => '<th style="text-align:' + (i === 4 ? 'right' : 'left') +
      ';background:#f1f5f9;padding:7px 10px' + (i === 4 ? ';padding-left:2px;padding-right:14px' : '') + ';border-bottom:1px solid #cbd5e1;white-space:nowrap;' +
      'text-transform:uppercase;font-size:8px;letter-spacing:.1px;color:#475569">' + h + '</th>';

    const el = document.createElement('div');
    el.style.cssText = 'width:718px;box-sizing:border-box;background:#fff;' +
      'font-family:Arial,Helvetica,sans-serif;color:#1f2937';
    el.innerHTML =
      '<h1 style="font-size:17px;text-align:center;margin:0 0 4px;color:#0f172a">' + esc(CLINIC) + '</h1>' +
      '<p style="text-align:center;margin:0 0 16px;font-size:11px;color:#64748b">' +
        'Revenue Report &middot; ' + rangeLabel(d.from, docTo(d)) +
        ' &middot; ' + d.rows.length + ' transaction' + (d.rows.length === 1 ? '' : 's') + '</p>' +
      '<table style="width:100%;box-sizing:border-box;overflow:hidden;table-layout:fixed;border-collapse:collapse;font-size:9.5px">' +
      '<colgroup>' +
        '<col style="width:5%"><col style="width:34%"><col style="width:33%">' +
        '<col style="width:16%"><col style="width:12%">' +
      '</colgroup>' +
      '<thead><tr>' + heads.map(th).join('') + '</tr></thead>' +
      '<tbody>' + rowsHtml + '</tbody>' +
      '<tfoot><tr>' +
        '<td colspan="4" style="padding:9px 10px;font-weight:700;border-top:2px solid #0f172a">Total Revenue</td>' +
        '<td style="padding:9px 14px 9px 2px;font-weight:700;text-align:right;white-space:nowrap;border-top:2px solid #0f172a">' + peso(d.total) + '</td>' +
      '</tr></tfoot></table>' +
      '<p style="margin:14px 0 0;padding-top:8px;border-top:1px solid #e2e8f0;font-size:9.5px;color:#64748b">' +
        '<strong>Prepared By:</strong> ' + esc(PREPARED_BY) + '<br>' +
        '<strong>Generated Date &amp; Time:</strong> ' + reportTimestamp() + '</p>';
    return el;
  }

  // One clean PDF path for both the toolbar "Generate PDF" and the preview
  // modal's "Save as PDF" — html2pdf renders buildDoc() straight to a download,
  // so there is no browser print dialog and no browser-injected date / title /
  // URL header or footer on the page.
  // Formal "Page X of Y" footer, stamped onto every page of the finished PDF.
  function stampPageNumbers(pdf) {
    const total = pdf.internal.getNumberOfPages();
    const w = pdf.internal.pageSize.getWidth(), h = pdf.internal.pageSize.getHeight();
    for (let i = 1; i <= total; i++) {
      pdf.setPage(i);
      pdf.setFont('helvetica', 'normal');
      pdf.setFontSize(8);
      pdf.setTextColor(100, 116, 139);
      pdf.text('Page ' + i + ' of ' + total, w - 10, h - 6, { align: 'right' });
    }
  }

  async function generatePdf(btn) {
    if (typeof html2pdf === 'undefined') return toast('PDF tool is still loading. Try again in a moment.', 'err');
    const d = last;
    if (!d) return toast('Report is still loading.', 'err');

    const label = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Preparing…'; }

    // Render the node in a brief full-screen overlay pinned to the viewport
    // origin. `windowWidth` must NOT be set (it re-lays-out the html2canvas clone
    // and crops the right-hand columns); `scrollX/Y: 0` are correct here because
    // the overlay is position:fixed so the node sits at viewport coords already.
    window.scrollTo(0, 0);
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;background:#fff;' +
      'overflow:auto;padding:24px;display:flex;justify-content:center;align-items:flex-start';
    const doc = buildDoc(d);
    overlay.appendChild(doc);
    document.body.appendChild(overlay);
    await new Promise(requestAnimationFrame);
    await new Promise(requestAnimationFrame);

    const name = 'Revenue_Report_' + (d.from === docTo(d) ? d.from : d.from + '_to_' + docTo(d)) + '.pdf';
    try {
      await html2pdf().set({
        margin: [12, 10, 14, 10],
        filename: name,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, backgroundColor: '#ffffff', scrollX: 0, scrollY: 0 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: ['css', 'legacy'], avoid: 'tr' },   // never slice a row across pages
      }).from(doc).toPdf().get('pdf').then(stampPageNumbers).save();
      toast('PDF downloaded.');
    } catch (e) {
      toast('Could not generate the PDF.', 'err');
    } finally {
      overlay.remove();
      if (btn) { btn.disabled = false; btn.innerHTML = label; }
    }
  }

  /* ---------- Preview Report modal — shows the same buildDoc() node, no download ---------- */
  function openPreview() {
    if (!last) return toast('Report is still loading.', 'err');
    openModal(modalShell('Revenue Report Preview',
      // The document is an A4 page (fixed 718px). On a narrow screen it is
      // shown as a scaled-down page, exactly as it will print, instead of
      // letting the columns squeeze until words break letter by letter.
      '<div id="rptDocWrap" style="width:100%;overflow:hidden">' +
        '<div id="rptDoc" style="width:718px;margin:0 auto;transform-origin:top left">' +
        buildDoc(last).outerHTML + '</div></div>',
      '<button class="inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md bg-ok px-[15px] py-[9px] text-[13px] font-semibold text-white hover:brightness-95" id="rptPrint"><svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 11v6"/><path d="m9 14 3 3 3-3"/></svg> Save as PDF</button>'),
      { wide: true });

    // Tap the page to switch between "fit to screen" and actual size (which
    // then scrolls sideways), so the small print is readable on a phone.
    let zoomed = false;
    const fit = () => {
      const wrap = g('rptDocWrap'), doc = g('rptDoc'), hint = g('rptZoomHint');
      if (!wrap || !doc) return window.removeEventListener('resize', fit);
      const s = Math.min(1, wrap.clientWidth / 718);
      const shrink = s < 1 && !zoomed;
      doc.style.transform = shrink ? 'scale(' + s + ')' : '';
      doc.style.marginLeft = s < 1 ? '0' : 'auto';
      wrap.style.height = shrink ? Math.ceil(doc.offsetHeight * s) + 'px' : '';
      wrap.style.overflowX = s < 1 && zoomed ? 'auto' : 'hidden';
      wrap.style.cursor = s < 1 ? (zoomed ? 'zoom-out' : 'zoom-in') : '';
      if (hint) {
        hint.hidden = s >= 1;
        hint.textContent = zoomed ? 'Tap the page to fit it to the screen.' : 'Tap the page to zoom in.';
      }
    };
    g('rptDocWrap').insertAdjacentHTML('beforebegin', '<p id="rptZoomHint" class="mb-2 text-center text-[12px] text-steel-400" hidden></p>');
    g('rptDocWrap').addEventListener('click', () => {
      if (g('rptDocWrap').clientWidth >= 718) return;
      zoomed = !zoomed;
      fit();
    });
    fit();
    requestAnimationFrame(fit);
    window.addEventListener('resize', fit);

    g('rptPrint').addEventListener('click', () => generatePdf(g('rptPrint')));
  }

  g('previewBtn').addEventListener('click', openPreview);

  g('periodTabs').addEventListener('click', (e) => {
    const t = e.target.closest('.tab');
    if (!t) return;
    period = t.dataset.p;
    document.querySelectorAll('#periodTabs .tab').forEach((x) => x.classList.toggle('active', x === t));
    g('fromDate').value = ''; g('toDate').value = '';
    load();
  });
  g('anchorDate').addEventListener('change', () => { g('fromDate').value = ''; g('toDate').value = ''; load(); });
  window.wireDateRange(g('fromDate'), g('toDate'));
  g('fromDate').addEventListener('change', load);
  g('toDate').addEventListener('change', load);
  g('pdfBtn').addEventListener('click', () => generatePdf(g('pdfBtn')));

  /* ---------- Interactive drill-downs ----------
     A row opens that transaction's details (with links to the full
     appointment and its receipt); the Total Revenue figure opens a breakdown
     of the period by payment method and by dentist. */
  const BTNX = 'inline-flex cursor-pointer items-center justify-center rounded-md px-3.5 py-2 text-[13px] font-semibold';
  const GHOSTX = ' border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
  const KVR = (k, v) => '<div class="grid grid-cols-1 gap-0.5 border-b border-steel-100 py-2.5 last:border-0 sm:grid-cols-[150px_minmax(0,1fr)] sm:gap-3 dark:border-white/10">' +
    '<dt class="text-[11px] font-semibold uppercase tracking-wide text-steel-400 sm:pt-0.5 sm:text-[12px] sm:font-normal sm:normal-case sm:tracking-normal">' + k + '</dt>' +
    '<dd class="min-w-0 break-words text-[14px] text-navy-800 sm:text-[13px] dark:text-steel-50">' + v + '</dd></div>';

  function openTxn(r) {
    const when = mdy(r.date_of_service) + (r.time_of_service ? ' · ' + esc(r.time_of_service) : '');
    openModal(modalShell('Transaction',
      '<dl>' +
        KVR('Patient', '<span class="font-semibold">' + esc(r.patient_name) + '</span>') +
        KVR('Paid on', when) +
        KVR('Service', esc(r.service || 'N/A')) +
        KVR('Amount', '<span class="text-[16px] font-bold">' + peso(r.amount) + '</span>') +
      '</dl>',
      '<a class="' + BTNX + GHOSTX + '" href="/dgt_dental_clinic/modules/shared/receipt?appointment=' + r.appointment_id + '" target="_blank" rel="noopener">Receipt</a>' +
      '<button class="' + BTNX + ' bg-accent text-white hover:bg-accent-dark" id="txnAppt">View Transaction</button>' +
      '<button class="' + BTNX + GHOSTX + '" data-close>Close</button>'));
    const b = g('txnAppt');
    if (b) b.addEventListener('click', () => appointmentModal(+r.appointment_id, { title: 'Transaction' }));
  }

  function groupSum(rows, keyFn) {
    const m = new Map();
    rows.forEach((r) => {
      const k = keyFn(r);
      const o = m.get(k) || { n: 0, amt: 0 };
      o.n++; o.amt += +r.amount || 0;
      m.set(k, o);
    });
    return [...m.entries()].sort((a, b) => b[1].amt - a[1].amt);
  }
  function breakdownTable(title, entries, total) {
    return '<h4 class="mb-1.5 mt-1 text-[12px] font-semibold uppercase tracking-wide text-steel-400">' + title + '</h4>' +
      '<div class="mb-4 flex flex-col gap-2">' + entries.map(([k, o]) => {
        const pct = total > 0 ? Math.round(o.amt / total * 100) : 0;
        return '<div class="rounded-md border border-steel-100 px-3 py-2 dark:border-white/10">' +
          '<div class="flex items-baseline justify-between gap-3"><span class="min-w-0 break-words font-semibold text-navy-800 dark:text-steel-50">' + esc(k) + '</span>' +
          '<span class="whitespace-nowrap font-bold text-navy-900 dark:text-steel-50">' + peso(o.amt) + '</span></div>' +
          '<div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-steel-100 dark:bg-white/10"><div class="h-full rounded-full bg-accent" style="width:' + pct + '%"></div></div>' +
          '<div class="mt-1 text-[11px] text-steel-400">' + o.n + ' transaction' + (o.n === 1 ? '' : 's') + ' · ' + pct + '%</div></div>';
      }).join('') + '</div>';
  }
  function openBreakdown() {
    if (!last) return;
    const rows = last.rows || [];
    const total = +last.total || 0;
    const body = rows.length
      ? '<div class="mb-3 rounded-md bg-steel-50 px-3 py-2.5 dark:bg-white/5"><div class="text-[12px] text-steel-400">' + esc(rangeLabel(last.from, last.to)) + ' · ' + rows.length + ' transaction' + (rows.length === 1 ? '' : 's') + '</div>' +
          '<div class="text-[22px] font-bold text-navy-900 dark:text-steel-50">' + peso(total) + '</div></div>' +
        breakdownTable('By payment method', groupSum(rows, (r) => { const m = METHOD_LABEL[r.payment_mode] || r.method_label || 'N/A'; return m === 'Bank' ? 'Bank Transfer' : m; }), total) +
        breakdownTable('By dentist', groupSum(rows, (r) => r.specialist || 'Unassigned'), total)
      : '<p class="py-6 text-center text-steel-400">No revenue in this period.</p>';
    openModal(modalShell('Revenue Breakdown', body, '<button class="' + BTNX + GHOSTX + '" data-close>Close</button>'));
  }

  const revTb = document.querySelector('#revTable tbody');
  const pickRev = (e) => { const tr = e.target.closest('tr[data-rev]'); return tr && last ? last.rows[+tr.dataset.rev] : null; };
  revTb.addEventListener('click', (e) => { const r = pickRev(e); if (r) openTxn(r); });
  revTb.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const r = pickRev(e);
    if (!r) return;
    e.preventDefault();
    openTxn(r);
  });
  g('totalBtn').addEventListener('click', openBreakdown);

  load();
})();
JS;
$PAGE_SCRIPT = str_replace(
    ['__CLINIC__', '__PREPARED_BY__'],
    [
        json_encode($clinicName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        json_encode($preparedBy, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    ],
    $PAGE_SCRIPT
);
require __DIR__ . '/../../includes/layout.php';
