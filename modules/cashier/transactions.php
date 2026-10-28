<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['cashier']);
$PAGE_TITLE = 'Transaction';
$ACTIVE = 'transactions';
$PAGE_FULL = true;   // full-bleed content column (matches the admin screens)

$cardCls    = 'rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800';
$headCls    = 'mb-3.5 flex flex-wrap items-center justify-between gap-3';
$h2Cls      = 'text-[15px] text-navy-800 dark:text-steel-50';
$btnGhostSm = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md border border-steel-200 bg-transparent px-2.5 py-1.5 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
$tableCls   = 'w-full table-fixed border-collapse text-[13px] '
    . "[&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 "
    . '[&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:break-words [&_td]:dark:border-white/10 [&_tbody_tr:hover]:bg-steel-50 [&_tbody_tr:hover]:dark:bg-white/5 2xl:text-[14.5px] 2xl:[&_td]:py-3.5 2xl:[&_th]:py-3';

ob_start();
?>
<div class="mb-[22px] flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">Transaction</h1>
    </div>
</div>

<div class="<?= $cardCls ?>">
    <div class="<?= $headCls ?>">
        <h2 class="<?= $h2Cls ?>">Patient Transactions</h2>
        <div class="flex flex-wrap gap-2">
            <button class="<?= $btnGhostSm ?>" id="refreshBtn">Refresh</button>
        </div>
    </div>
    <div class="mb-3.5 flex flex-wrap items-center gap-2">
        <div class="relative w-full sm:max-w-[420px]">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-steel-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" id="txSearch" autocomplete="off" aria-label="Search patient name"
                   placeholder="Search patient name"
                   class="w-full rounded-md border border-steel-200 bg-white py-2 pl-9 pr-3 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50">
        </div>
        <span id="txCount" class="text-xs text-steel-400"></span>
    </div>
    <div class="overflow-x-auto">
        <table class="rtable <?= $tableCls ?>" id="txTable" style="table-layout:fixed !important;width:100%">
            <colgroup>
                <col style="width:5%"><col style="width:23%"><col style="width:14%"><col style="width:16%">
                <col style="width:16%"><col style="width:9%"><col style="width:17%">
            </colgroup>
            <thead><tr>
                <th>#</th><th>Name</th><th>Specialist</th><th>Service</th>
                <th class="!text-left whitespace-nowrap">Date of Service</th><th>Status</th><th>Action</th>
            </tr></thead>
            <tbody><tr><td colspan="7" class="py-7 text-center text-steel-400">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
    <nav id="txPager" class="mt-3 flex flex-wrap items-center justify-between gap-3" aria-label="Transaction pages" hidden></nav>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);

  const BTN_SM   = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold disabled:opacity-50';
  const PRIMARY  = ' bg-accent text-white hover:bg-accent-dark';
  const BADGE    = 'inline-block rounded-full px-2.5 py-[3px] text-[11px] font-bold capitalize';
  const B_PEND   = BADGE + ' bg-warn-soft text-warn dark:bg-warn/20';
  const EMPTY    = 'py-7 text-center text-steel-400';

  const PG = { page: 1 };   // 10 per page (window.pageSlice, app.js)
  window.fitOnResize(() => { if (document.getElementById('txTable')) render(); });
  let QUEUE = null;   // last fetched queue; the search box filters this locally
  const norm = (v) => String(v || '').toLowerCase();

  // Search by patient name only.
  function matches(q, term) {
    return !term || norm(q.patient_name).includes(term);
  }

  function render() {
    const tb = document.querySelector('#txTable tbody');
    if (!tb || !QUEUE) return;
    const term = norm(g('txSearch').value).trim();
    const list = QUEUE.filter((q) => matches(q, term));
    g('txCount').textContent = term ? list.length + ' of ' + QUEUE.length + ' shown' : '';
    {
      const { rows: queue, from } = window.pageSlice(list, PG, window.fitRows(g('txTable')), g('txPager'), render);
      const rows = queue.map((q) => {
        const billable = q.status === 'completed';
        const st = q.payment_status === 'partially_paid'
          ? '<span class="' + B_PEND + '">Partial Payment</span>'
          : (billable ? '<span class="' + B_PEND + '">Pending</span>' : badge(q.status));
        const action = billable
          ? '<a class="' + BTN_SM + PRIMARY + ' whitespace-nowrap" data-nav href="/dgt_dental_clinic/modules/cashier/billing?appointment=' + q.appointment_id + '">Proceed to Payment</a>'
          : '<span class="text-steel-400">N/A</span>';
        return '<td class="rt-title"><strong>' + esc(q.patient_name) + '</strong></td>' +
          '<td>' + esc(q.specialist || 'N/A') + '</td>' +
          '<td>' + esc(q.requested_service || 'N/A') + '</td>' +
          '<td class="text-left whitespace-nowrap">' + fmtDate(q.scheduled_date) + '</td>' +
          '<td>' + st + '</td>' +
          '<td><div class="flex flex-wrap gap-2">' + action + '</div></td>';
      });

      tb.innerHTML = rows.length
        ? rows.map((r, i) => '<tr><td class="rt-hide">' + (from + i + 1) + '</td>' + r + '</tr>').join('')
        : '<tr><td colspan="7" class="' + EMPTY + '">' + (term
            ? 'No pending payment records found for "' + esc(g('txSearch').value.trim()) + '".'
            : 'No transactions right now.') + '</td></tr>';
    }
  }

  async function load() {
    const tb = document.querySelector('#txTable tbody');
    try {
      QUEUE = (await api('payments.php?action=queue')).queue || [];
      render();
    } catch (e) { tb.innerHTML = '<tr><td colspan="7" class="' + EMPTY + '">' + esc(e.message) + '</td></tr>'; }
  }

  g('txSearch').addEventListener('input', () => { PG.page = 1; render(); });

  // "Proceed to Payment" is a <a data-nav> link → nav.js opens modules/cashier/billing.php.
  g('refreshBtn').addEventListener('click', load);

  load();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
