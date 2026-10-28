<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['cashier']);
$PAGE_TITLE = 'Transaction Logs';
$ACTIVE = 'logs';
$PAGE_FULL = true;   // full-bleed content column (matches the admin screens)

$tabCls   = 'tab cursor-pointer rounded-full border border-steel-200 bg-white px-4 py-2 text-[13px] font-semibold text-steel-500 dark:border-white/15 dark:bg-navy-800 dark:text-steel-300 '
          . '[&.active]:border-navy-700 [&.active]:bg-navy-700 [&.active]:text-white';
$tableCls = 'w-full border-collapse text-[13px] '
    . "[&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 "
    . '[&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:dark:border-white/10 [&_tbody_tr:hover]:bg-steel-50 [&_tbody_tr:hover]:dark:bg-white/5';

ob_start();
?>
<div class="mb-[22px] flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">Transaction Logs</h1></div>
</div>

<div class="mb-[18px] flex flex-wrap gap-1.5" id="logTabs">
    <button class="<?= $tabCls ?> active" data-f="all">All</button>
    <button class="<?= $tabCls ?>" data-f="fully_paid">Paid</button>
    <button class="<?= $tabCls ?>" data-f="pending">Pending</button>
</div>

<div class="rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800">
    <div class="overflow-x-auto">
        <table class="<?= $tableCls ?>" id="logTable">
            <thead><tr>
                <th>OR / Ref</th><th>Patient</th><th>Specialist</th><th>Service</th>
                <th>Date</th><th>Total</th><th>Mode</th><th>Status</th><th></th>
            </tr></thead>
            <tbody><tr><td colspan="9" class="py-7 text-center text-steel-400">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const MODE = { cash: 'Cash', gcash: 'GCash', bank: 'Bank Transfer', split: 'Split' };
  const BADGE = 'inline-block rounded-full px-2.5 py-[3px] text-[11px] font-bold capitalize';
  const B_PAID = BADGE + ' bg-ok-soft text-ok dark:bg-ok/20';
  const B_PEND = BADGE + ' bg-warn-soft text-warn dark:bg-warn/20';
  const BTN_GHOST_SM = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md border border-steel-200 bg-transparent px-2.5 py-1.5 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
  let rows = [];
  let filter = 'all';

  async function load() {
    const tb = document.querySelector('#logTable tbody');
    try {
      rows = (await api('payments.php?action=logs')).logs;
      render();
    } catch (e) { tb.innerHTML = '<tr><td colspan="9" class="py-7 text-center text-steel-400">' + esc(e.message) + '</td></tr>'; }
  }

  function render() {
    const tb = document.querySelector('#logTable tbody');
    const list = rows.filter((r) =>
      filter === 'all' ? true :
      filter === 'fully_paid' ? r.payment_status === 'fully_paid' :
      r.payment_status !== 'fully_paid');
    tb.innerHTML = list.length ? list.map((r) => {
      const orNo = r.or_number ? r.or_number : '—';   // the cashier's printed OR number
      const paid = r.payment_status === 'fully_paid';
      return '<tr><td>' + esc(orNo) + '</td>' +
        '<td><strong>' + esc(r.patient_name) + '</strong></td>' +
        '<td>' + esc(r.specialist || 'N/A') + '</td>' +
        '<td>' + esc(r.requested_service || 'N/A') + '</td>' +
        '<td>' + (r.paid_at ? new Date(r.paid_at.replace(' ', 'T')).toLocaleDateString() : fmtDate(r.scheduled_date)) + '</td>' +
        '<td>' + peso(r.total_amount) + '</td>' +
        '<td>' + (r.payment_mode ? (MODE[r.payment_mode] || r.payment_mode) : 'N/A') + '</td>' +
        '<td>' + (paid ? '<span class="' + B_PAID + '">Paid</span>' : '<span class="' + B_PEND + '">Pending</span>') + '</td>' +
        '<td><div class="flex flex-wrap gap-2">' +
        '<button class="' + BTN_GHOST_SM + '" data-view="' + r.appointment_id + '">Details</button>' +
        (paid ? '<button class="' + BTN_GHOST_SM + '" data-receipt="' + r.appointment_id + '">Receipt</button>' : '') +
        '</div></td></tr>';
    }).join('') : '<tr><td colspan="9" class="py-7 text-center text-steel-400">No records.</td></tr>';
  }

  document.getElementById('logTabs').addEventListener('click', (e) => {
    const t = e.target.closest('.tab');
    if (!t) return;
    filter = t.dataset.f;
    document.querySelectorAll('#logTabs .tab').forEach((x) => x.classList.toggle('active', x === t));
    render();
  });

  document.querySelector('#logTable').addEventListener('click', (e) => {
    const r = e.target.closest('[data-receipt]');
    if (r) window.open('/dgt_dental_clinic/modules/shared/receipt?appointment=' + r.dataset.receipt, '_blank');
  });

  load();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
