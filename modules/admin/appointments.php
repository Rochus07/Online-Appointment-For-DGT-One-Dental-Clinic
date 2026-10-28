<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['admin']);
$PAGE_TITLE = 'Appointments';
$ACTIVE = 'appointments';
$PAGE_FULL = true;   // admin screens fill the full viewport width

$btnBase   = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold disabled:opacity-50';
$fLbl      = 'text-[11px] font-semibold uppercase tracking-wide text-steel-500 dark:text-steel-400';
$inputCls  = 'h-[34px] rounded-md border border-steel-200 bg-white px-2.5 py-1.5 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
$tableCls  = 'w-full border-collapse text-[13px] '
    . "[&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 "
    . '[&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:dark:border-white/10 [&_tbody_tr:hover]:bg-steel-50 [&_tbody_tr:hover]:dark:bg-white/5 '
    . "[&_th.sortable]:cursor-pointer [&_th.sortable]:select-none hover:[&_th.sortable]:text-navy-700 [&_th.sortable_.arrow]:ml-[3px] [&_th.sortable_.arrow]:text-[9px] [&_th.sortable_.arrow]:opacity-30 [&_th.sorted_.arrow]:opacity-100 hover:[&_th.sortable]:dark:text-steel-50";

// status-count card: soft tint + coloured left edge + icon chip (matches the
// patient Treatment & Billing metric tiles).
$mCard  = 'sz-pad relative flex flex-col gap-1.5 rounded-[10px] border border-steel-100 border-l-4 p-4 shadow-sm dark:border-white/10';
$mIco   = 'sz-ico absolute right-3.5 top-3.5 grid h-[34px] w-[34px] place-items-center rounded-[9px] [&_svg]:h-[18px] [&_svg]:w-[18px]';
$mLabel = 'sz-cap text-[11px] uppercase tracking-[0.6px] text-steel-500 dark:text-steel-400';
$mValue = 'sz-num text-[26px] font-bold tabular-nums leading-none';

ob_start();
?>
<div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6" id="countGrid">
    <div class="<?= $mCard ?> border-l-warn bg-warn-soft dark:bg-warn/20">
        <span class="<?= $mIco ?> bg-warn/[0.16] text-warn dark:bg-warn/25"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 0 1 7.38 16.75M12 6v6l4 2M2.5 8.875a10 10 0 0 0-.5 3M2.83 16a10 10 0 0 0 2.43 3.4M4.636 5.235a10 10 0 0 1 .891-.857M8.644 21.42a10 10 0 0 0 7.631-.38"/></svg></span>
        <span class="<?= $mLabel ?>">Pending</span>
        <span class="<?= $mValue ?> text-warn" data-c="pending">N/A</span>
    </div>
    <div class="<?= $mCard ?> border-l-info bg-info-soft dark:bg-info/20">
        <span class="<?= $mIco ?> bg-info/[0.16] text-info dark:bg-info/25"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/></svg></span>
        <span class="<?= $mLabel ?>">Approved</span>
        <span class="<?= $mValue ?> text-info" data-c="approved">N/A</span>
    </div>
    <div class="<?= $mCard ?> border-l-ok bg-ok-soft dark:bg-ok/20">
        <span class="<?= $mIco ?> bg-ok/[0.16] text-ok dark:bg-ok/25"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/></svg></span>
        <span class="<?= $mLabel ?>">Completed</span>
        <span class="<?= $mValue ?> text-ok" data-c="completed">N/A</span>
    </div>
    <div class="<?= $mCard ?> border-l-danger bg-danger-soft dark:bg-danger/20">
        <span class="<?= $mIco ?> bg-danger/[0.16] text-danger dark:bg-danger/25"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/></svg></span>
        <span class="<?= $mLabel ?>">Cancelled</span>
        <span class="<?= $mValue ?> text-danger" data-c="cancelled">N/A</span>
    </div>
    <div class="<?= $mCard ?> border-l-steel-300 bg-steel-50 dark:bg-white/5">
        <span class="<?= $mIco ?> bg-steel-500/[0.16] text-steel-500 dark:bg-steel-400/25 dark:text-steel-300"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v.832M8 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3.5M16 14v2.2l1.6 1"/><circle cx="16" cy="16" r="6"/></svg></span>
        <span class="<?= $mLabel ?>">Rescheduled</span>
        <span class="<?= $mValue ?> text-[#a21caf] dark:text-[#f0abfc]" data-c="rescheduled">N/A</span>
    </div>
    <div class="<?= $mCard ?> border-l-[#5b4a8b] bg-[#ece8f6] dark:bg-[#5b4a8b]/20">
        <span class="<?= $mIco ?> bg-[#5b4a8b]/[0.16] text-[#5b4a8b] dark:bg-[#5b4a8b]/30 dark:text-[#cfc4ef]"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="4"/><path d="M3 21v-1a6 6 0 0 1 9.5-4.9M16 16l5 5M21 16l-5 5"/></svg></span>
        <span class="<?= $mLabel ?>">No Show</span>
        <span class="<?= $mValue ?> text-[#5b4a8b] dark:text-[#cfc4ef]" data-c="no_show">N/A</span>
    </div>
</div>

<div id="apptListCard" class="mt-4 rounded-[10px] border border-steel-100 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-navy-800 sm:p-[18px]">
    <!-- Toolbar: labels above fields in one grid (phone: status full width,
         dates side by side; iPad and up: one row), then one actions row,
         then a hairline bar with the count (+ Select all / Sort for cards). -->
    <div class="grid grid-cols-2 items-end gap-3 md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1fr)_auto] xl:flex xl:flex-wrap">
        <label class="col-span-2 flex min-w-0 flex-col gap-1 md:col-span-1 xl:w-52">
            <span class="<?= $fLbl ?>">Status</span>
            <select id="statusSel" class="<?= $inputCls ?> w-full">
                <option value="all">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
                <option value="rescheduled">Rescheduled</option>
                <option value="no_show">No Show</option>
            </select>
        </label>
        <label class="flex min-w-0 flex-col gap-1 xl:w-44">
            <span class="<?= $fLbl ?>">From</span>
            <input type="date" id="fromDate" class="<?= $inputCls ?> w-full">
        </label>
        <label class="flex min-w-0 flex-col gap-1 xl:w-44">
            <span class="<?= $fLbl ?>">To</span>
            <input type="date" id="toDate" class="<?= $inputCls ?> w-full">
        </label>
        <button class="<?= $btnBase ?> col-span-2 h-[34px] border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5 md:col-span-1 md:px-4" id="clearFilters">Clear filters</button>
    </div>


    <div class="mb-3 mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-steel-100 pt-3 dark:border-white/10">
        <div class="flex items-center gap-4">
            <span class="text-[13px] text-steel-400 dark:text-steel-500" id="rowCount"></span>
        </div>
        <label class="flex items-center gap-2 text-[13px] text-steel-500 dark:text-steel-400 xl:hidden">Sort
            <select id="cardSort" class="<?= $inputCls ?>">
                <option value="">Oldest first</option>
                <option value="scheduled_date:-1">Newest first</option>
                <option value="patient_name:1">Name A to Z</option>
                <option value="status:1">Status</option>
                <option value="specialist:1">Specialist</option>
            </select>
        </label>
    </div>

    <div id="logCards" class="grid gap-3 md:grid-cols-2 xl:hidden"></div>

    <div class="hidden overflow-x-auto xl:block">
        <table class="<?= $tableCls ?>" id="logTable">
            <thead><tr>
                <th>#</th>
                <th class="sortable" data-sort="patient_name">Name <span class="arrow">▲▼</span></th>
                <th class="sortable" data-sort="age">Age <span class="arrow">▲▼</span></th>
                <th class="sortable" data-sort="phone">Phone <span class="arrow">▲▼</span></th>
                <th class="sortable" data-sort="specialist">Specialist <span class="arrow">▲▼</span></th>
                <th>Service</th>
                <th class="sortable" data-sort="booking_type">Type <span class="arrow">▲▼</span></th>
                <th class="sortable" data-sort="scheduled_date">Date <span class="arrow">▲▼</span></th>
                <th class="sortable" data-sort="status">Status <span class="arrow">▲▼</span></th>
                <th>Actions</th>
            </tr></thead>
            <tbody><tr><td colspan="10" class="py-7 text-center text-steel-400 dark:text-steel-500">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
    <nav id="logPager" class="mt-3 flex flex-wrap items-center justify-between gap-3" aria-label="Appointment pages" hidden></nav>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);
  const COLS = 10;

  const BADGE = 'inline-block whitespace-nowrap rounded-full px-2.5 py-[3px] text-[11px] font-bold capitalize';
  const BADGE_CLS = {
    pending: ' bg-warn-soft text-warn dark:bg-warn/20', approved: ' bg-info-soft text-info dark:bg-info/20',
    completed: ' bg-ok-soft text-ok dark:bg-ok/20', cancelled: ' bg-danger-soft text-danger dark:bg-danger/20',
    rescheduled: ' bg-[#fae8ff] text-[#a21caf] dark:bg-[#a21caf]/25 dark:text-[#f0abfc]',
    payment_declined: ' bg-[#fdecd9] text-[#b3540c] dark:bg-[#b3540c]/25 dark:text-[#f5c99a]',
    no_show: ' bg-[#ece8f6] text-[#5b4a8b] dark:bg-[#5b4a8b]/30 dark:text-[#cfc4ef]',
    delayed: ' bg-[#ffedd5] text-[#c2410c] dark:bg-[#c2410c]/25 dark:text-[#fdba74]',
    arrived_late: ' bg-[#ccfbf1] text-[#0f766e] dark:bg-[#0f766e]/30 dark:text-[#5eead4]',
  };
  const TYPE_CLS = { online: ' bg-[#eef0fb] text-[#3d4bc7] dark:bg-[#3f45b0]/25 dark:text-[#c2c5f5]', walk_in: ' bg-[#e5f4f3] text-[#0b7c74] dark:bg-[#0b7c74]/25 dark:text-[#8fe0d8]' };
  const typeBadge = (t) => {
    const k = t === 'walk_in' ? 'walk_in' : 'online';
    return '<span class="' + BADGE + TYPE_CLS[k] + '">' + (k === 'walk_in' ? 'Walk-in' : 'Online') + '</span>';
  };
  const STATUS_LABEL = {
    pending: 'Pending', approved: 'Approved', rescheduled: 'Rescheduled',
    completed: 'Completed', cancelled: 'Cancelled', payment_declined: 'Payment Declined', no_show: 'No Show',
    delayed: 'Delayed / Standby', arrived_late: 'Arrived (Late)',
  };
  // Grace period (migration 088): shown in place of Approved / Rescheduled.
  const stKey = (r) => (r.delayed_at && (r.status === 'approved' || r.status === 'rescheduled'))
    ? (r.checked_in_at ? 'arrived_late' : 'delayed') : r.status;
  const EMPTY = '<tr><td colspan="' + COLS + '" class="py-7 text-center text-steel-400 dark:text-steel-500">';

  let rows = [];
  const PG = { page: 1 };   // pager (window.pageSlice); rows per page fit the screen on desktop
  let sortKey = null;
  let sortDir = 1;

  function query() {
    const p = new URLSearchParams({ status: g('statusSel').value });
    if (g('fromDate').value) p.set('from', g('fromDate').value);
    if (g('toDate').value) p.set('to', g('toDate').value);
    return p.toString();
  }

  async function load(isAuto) {
    const tb = document.querySelector('#logTable tbody');
    try {
      const d = await api('reports.php?action=logs&' + query());
      ['pending', 'approved', 'completed', 'cancelled', 'rescheduled', 'no_show'].forEach((k) => {
        g('countGrid').querySelector('[data-c="' + k + '"]').textContent = d.counts[k] || 0;
      });
      rows = d.rows;
      if (!isAuto) PG.page = 1;   // a new filter starts on page 1; the 10 s refresh keeps the page
      render();
    } catch (e) {
      tb.innerHTML = EMPTY + esc(e.message) + '</td></tr>';
    }
  }

  function sorted() {
    if (!sortKey) return rows;
    const copy = rows.slice();
    copy.sort((a, b) => {
      let x = a[sortKey], y = b[sortKey];
      if (sortKey === 'age') { x = +x || 0; y = +y || 0; return (x - y) * sortDir; }
      x = (x == null ? '' : String(x)).toLowerCase();
      y = (y == null ? '' : String(y)).toLowerCase();
      return x < y ? -sortDir : x > y ? sortDir : 0;
    });
    return copy;
  }

  function render() {
    document.querySelectorAll('#logTable th.sortable').forEach((th) => {
      th.classList.toggle('sorted', th.dataset.sort === sortKey);
      const ar = th.querySelector('.arrow');
      if (ar) ar.textContent = th.dataset.sort === sortKey ? (sortDir === 1 ? '▲' : '▼') : '▲▼';
    });

    const tb = document.querySelector('#logTable tbody');
    const all = sorted();
    g('rowCount').textContent = all.length + ' record' + (all.length === 1 ? '' : 's');
    const tbl = g('logTable');
    const per = tbl.offsetParent ? window.fitRows(tbl) : 10;   // cards (phones / iPads): 10 per page
    const { rows: list, from } = window.pageSlice(all, PG, per, g('logPager'), render);
    tb.innerHTML = list.length ? list.map((r, i) =>
      '<tr>' +
      '<td>' + (from + i + 1) + '</td>' +
      '<td><strong>' + esc(r.patient_name) + '</strong></td>' +
      '<td>' + (r.age != null ? esc(r.age) : 'N/A') + '</td>' +
      '<td class="whitespace-nowrap">' + esc(r.phone || 'N/A') + '</td>' +
      '<td>' + esc(r.specialist || 'N/A') + '</td>' +
      '<td>' + esc(r.service || 'N/A') + '</td>' +
      '<td>' + typeBadge(r.booking_type) + '</td>' +
      '<td class="whitespace-nowrap">' + esc(String(r.scheduled_date).slice(0, 10)) + ' · ' + fmtTime(r.time_slot) + '</td>' +
      '<td><span class="' + BADGE + (BADGE_CLS[stKey(r)] || '') + '">' + (STATUS_LABEL[stKey(r)] || r.status) + '</span></td>' +
      '<td><button class="inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md border border-steel-200 bg-transparent px-2.5 py-1.5 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5" data-view="' + r.appointment_id + '">View</button></td>' +
      '</tr>'
    ).join('') : EMPTY + 'No matching appointments.</td></tr>';

    const MUTED = 'text-steel-400 dark:text-steel-500';
    const kv = (k, v) => '<div class="min-w-0"><dt class="text-[11px] font-semibold uppercase tracking-wide ' + MUTED + '">' + k + '</dt>' +
      '<dd class="truncate text-[13px] text-navy-700 dark:text-steel-100">' + v + '</dd></div>';
    g('logCards').innerHTML = list.length ? list.map((r) =>
      '<article class="rounded-xl border border-steel-100 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-navy-900/40">' +
        '<div class="flex items-start gap-3">' +
          '<div class="min-w-0 flex-1">' +
            '<div class="flex flex-wrap items-center justify-between gap-2">' +
              '<strong class="text-[15px] text-navy-900 dark:text-steel-50">' + esc(r.patient_name) + '</strong>' +
              '<span class="' + BADGE + (BADGE_CLS[stKey(r)] || '') + '">' + (STATUS_LABEL[stKey(r)] || r.status) + '</span>' +
            '</div>' +
            '<div class="mt-0.5 text-[13px] font-semibold text-accent">' + esc(String(r.scheduled_date).slice(0, 10)) + ' · ' + fmtTime(r.time_slot) + '</div>' +
          '</div>' +
        '</div>' +
        '<dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2.5">' +
          kv('Service', esc(r.service || 'N/A')).replace('class="min-w-0"', 'class="col-span-2 min-w-0"') +
          kv('Specialist', esc(r.specialist || 'N/A')) +
          kv('Type', typeBadge(r.booking_type)) +
          kv('Age', r.age != null ? esc(r.age) : 'N/A') +
          kv('Phone', esc(r.phone || 'N/A')) +
        '</dl>' +
        '<button class="mt-3 inline-flex w-full cursor-pointer items-center justify-center rounded-md border border-steel-200 bg-transparent px-2.5 py-2 text-[13px] font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5" data-view="' + r.appointment_id + '">View</button>' +
      '</article>'
    ).join('') : '<p class="py-7 text-center ' + MUTED + ' md:col-span-2">No matching appointments.</p>';
  }

  g('cardSort').addEventListener('change', (e) => {
    const [k, d] = e.target.value.split(':');
    sortKey = k || null; sortDir = +d || 1;
    PG.page = 1;
    render();
  });

  g('statusSel').addEventListener('change', () => load());
  window.wireDateRange(g('fromDate'), g('toDate'));
  g('fromDate').addEventListener('change', () => load());
  g('toDate').addEventListener('change', () => load());
  g('clearFilters').addEventListener('click', () => {
    g('statusSel').value = 'all'; g('fromDate').value = ''; g('toDate').value = '';
    load();
  });

  document.querySelector('#logTable thead').addEventListener('click', (e) => {
    const th = e.target.closest('th.sortable');
    if (!th) return;
    if (sortKey === th.dataset.sort) sortDir = -sortDir;
    else { sortKey = th.dataset.sort; sortDir = 1; }
    PG.page = 1;
    render();
  });


  window.fitOnResize(() => { if (document.getElementById('logTable')) render(); });

  // Always on (2026-10-18): the list reloads silently every 10 s, no toggle.
  clearInterval(window.__apptTimer);
  window.__apptTimer = setInterval(() => {
    if (!document.getElementById('logTable')) { clearInterval(window.__apptTimer); return; }
    load(true);
  }, 10000);

  load();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
