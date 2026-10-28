<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

// Admin / Clinic Owner only — the clinic owner signs in on the admin account.
$me = checkAuth(['admin']);
$PAGE_TITLE = 'Audit Logs';
$ACTIVE = 'logs';
$PAGE_FULL = true;

$btnBase  = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold disabled:opacity-50';
$inputCls = 'rounded-md border border-steel-200 bg-white px-2.5 py-1.5 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
$tableCls = 'w-full border-collapse text-[13px] '
    . "[&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 "
    . '[&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-top [&_td]:dark:border-white/10 [&_tbody_tr:hover]:bg-steel-50 [&_tbody_tr:hover]:dark:bg-white/5';

ob_start();
?>
<div class="rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800">
    <div class="mb-3 flex flex-wrap items-center gap-3 pb-0.5 sm:flex-nowrap sm:overflow-x-auto">
        <label class="flex w-full flex-none items-center gap-1.5 whitespace-nowrap sm:w-auto text-[13px] dark:text-steel-100">Role:
            <select id="roleSel" class="<?= $inputCls ?> min-w-0 flex-1 sm:w-[104px] sm:flex-none"><option value="">All roles</option></select>
        </label>
        <label class="flex w-full flex-none items-center gap-1.5 whitespace-nowrap sm:w-auto text-[13px] dark:text-steel-100">Category:
            <select id="catSel" class="<?= $inputCls ?> min-w-0 flex-1 sm:w-[126px] sm:flex-none">
                <option value="">All categories</option>
                <option value="auth">Authentication</option>
                <option value="appointments">Appointments</option>
                <option value="records">Records</option>
                <option value="billing">Billing</option>
            </select>
        </label>
        <label class="flex w-full flex-none items-center gap-1.5 whitespace-nowrap sm:w-auto text-[13px] dark:text-steel-100">Date Range:
            <input type="date" id="fromDate" class="<?= $inputCls ?> min-w-0 flex-1 sm:w-[132px] sm:flex-none">
            <span class="text-steel-400 dark:text-steel-500">to</span>
            <input type="date" id="toDate" class="<?= $inputCls ?> min-w-0 flex-1 sm:w-[132px] sm:flex-none">
        </label>
        <label class="flex w-full flex-none items-center gap-1.5 whitespace-nowrap sm:w-auto text-[13px] dark:text-steel-100">Search:
            <input type="search" id="searchBox" placeholder="user or action…" class="<?= $inputCls ?> min-w-0 flex-1 sm:w-[150px] sm:flex-none">
        </label>
        <button class="<?= $btnBase ?> flex-none border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5" id="clearFilters">Clear</button>
    </div>

    <div class="mb-2 text-steel-400 dark:text-steel-500" id="rowCount"></div>

    <div class="overflow-x-auto">
        <table class="rtable <?= $tableCls ?>" id="logTable">
            <thead><tr>
                <th class="w-[170px]">Timestamp</th>
                <th class="w-[150px]">User</th>
                <th class="w-[120px]">Role</th>
                <th class="w-[120px]">Category</th>
                <th>Action Description</th>
                <th><span class="sr-only">Details</span></th>
            </tr></thead>
            <tbody><tr><td colspan="6" class="py-7 text-center text-steel-400 dark:text-steel-500">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
    <nav id="logPager" class="mt-3 flex flex-wrap items-center justify-between gap-3" aria-label="Audit log pages" hidden></nav>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);
  const COLS = 6;
  let lastRows = [];
  const EMPTY = '<tr><td colspan="' + COLS + '" class="py-7 text-center text-steel-400 dark:text-steel-500">';

  const BADGE = 'inline-block rounded-full px-2.5 py-[3px] text-[11px] font-bold capitalize';
  const CAT_CLS = {
    auth: ' bg-info-soft text-info dark:bg-info/20',
    appointments: ' bg-[#e5f4f3] text-[#0b7c74] dark:bg-[#0b7c74]/25 dark:text-[#8fe0d8]',
    records: ' bg-warn-soft text-warn dark:bg-warn/20',
    billing: ' bg-ok-soft text-ok dark:bg-ok/20',
  };
  const CAT_LABEL = { auth: 'Authentication', appointments: 'Appointments', records: 'Records', billing: 'Billing' };
  const catBadge = (c) => '<span class="' + BADGE + (CAT_CLS[c] || ' bg-steel-100 text-steel-500 dark:bg-white/10 dark:text-steel-300') + '">'
    + esc(CAT_LABEL[c] || c || 'N/A') + '</span>';

  function fmtTs(s) {
    const d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d)) return esc(s);
    const M = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    let h = d.getHours(), ap = h < 12 ? 'AM' : 'PM';
    h = h % 12 || 12;
    const mm = String(d.getMinutes()).padStart(2, '0');
    return M[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear() + ' ' + h + ':' + mm + ' ' + ap;
  }

  const PG = { page: 1 };   // pager (window.pageSlice + fitRows)
  let deb = null;

  /* ---------- Log detail pop-up ----------
     The log stores one sentence per event, so the pop-up reads what it can
     out of it: the outcome, a "from X to Y" change, a reason, and (looked up
     by the API) the appointment / invoice / patient the event was about. */
  const LOG_BTN = 'inline-flex cursor-pointer items-center justify-center rounded-md px-3.5 py-2 text-[13px] font-semibold';
  const OUTCOME = {
    failed: ['Failed', ' bg-danger-soft text-danger dark:bg-danger/20'],
    blocked: ['Blocked', ' bg-danger-soft text-danger dark:bg-danger/20'],
    declined: ['Declined', ' bg-warn-soft text-warn dark:bg-warn/20'],
    cancelled: ['Cancelled', ' bg-warn-soft text-warn dark:bg-warn/20'],
    success: ['Success', ' bg-ok-soft text-ok dark:bg-ok/20'],
  };
  function outcomeOf(t) {
    const s = String(t).toLowerCase();
    if (/failed|denied|wrong password|invalid/.test(s)) return 'failed';
    if (/blocked/.test(s)) return 'blocked';
    if (/^declined/.test(s)) return 'declined';
    if (/^cancel|no show|no-show|expired/.test(s)) return 'cancelled';
    return 'success';
  }
  function changeOf(t) {
    const s = String(t);
    const out = {};
    const ft = s.match(/\bfrom (.+?) to (.+?)(?=\s*\(reason:|\.\s|$)/i);
    if (ft) { out.prev = ft[1].trim(); out.next = ft[2].trim(); }
    const rs = s.match(/reason:\s*([^)]+?)(?:\)|$)/i);
    if (rs) out.reason = rs[1].trim();
    return out;
  }
  const KVROW = (k, v) => '<div class="grid grid-cols-1 gap-0.5 border-b border-steel-100 py-2.5 last:border-0 sm:grid-cols-[150px_minmax(0,1fr)] sm:gap-3 dark:border-white/10">' +
    '<dt class="text-[11px] font-semibold uppercase tracking-wide text-steel-400 sm:pt-0.5 sm:text-[12px] sm:font-normal sm:normal-case sm:tracking-normal">' + k + '</dt>' +
    '<dd class="min-w-0 break-words text-[14px] text-navy-800 sm:text-[13px] dark:text-steel-50">' + v + '</dd></div>';

  function openLog(r) {
    const t = r.target || {};
    const oc = OUTCOME[outcomeOf(r.action)];
    const ch = changeOf(r.action);
    let target = '';
    if (t.patient_name) target += '<div class="font-semibold">' + esc(t.patient_name) + '</div>';
    const refs = [];
    if (t.appointment_ref) refs.push('Appointment #' + esc(t.appointment_ref));
    if (t.or_no) refs.push('OR No. ' + esc(t.or_no));
    if (refs.length) target += '<div class="text-[12px] text-steel-500 dark:text-steel-400">' + refs.join(' · ') + '</div>';
    if (t.scheduled_date) target += '<div class="mt-1 flex flex-wrap items-center gap-1.5 text-[12px] text-steel-500 dark:text-steel-400">Visit: ' + esc(fmtDate(String(t.scheduled_date).slice(0, 10))) + (t.time_slot ? ' · ' + esc(fmtTime(t.time_slot)) : '') + (t.appt_status ? ' ' + badge(t.appt_status) : '') + '</div>';
    if (t.appointment_ref && !t.appointment_id) target += '<div class="text-[12px] text-steel-400">This appointment has since been removed.</div>';
    if (!target) target = r.category === 'auth'
      ? 'The account itself (' + esc(r.username || 'N/A') + ')'
      : '<span class="text-steel-400">Not linked to a specific record</span>';

    let change;
    if (ch.prev || ch.reason) {
      change = '<div class="flex flex-col gap-2">' +
        (ch.prev ? '<div class="grid grid-cols-2 gap-2">' +
          '<div class="rounded-md bg-danger-soft px-2.5 py-2 dark:bg-danger/15"><div class="text-[11px] font-semibold uppercase tracking-wide text-danger">Previous</div><div class="mt-0.5 break-words">' + esc(ch.prev) + '</div></div>' +
          '<div class="rounded-md bg-ok-soft px-2.5 py-2 dark:bg-ok/15"><div class="text-[11px] font-semibold uppercase tracking-wide text-ok">New</div><div class="mt-0.5 break-words">' + esc(ch.next) + '</div></div>' +
        '</div>' : '') +
        (ch.reason ? '<div class="rounded-md bg-steel-50 px-2.5 py-2 dark:bg-white/5"><span class="font-semibold">Reason:</span> ' + esc(ch.reason) + '</div>' : '') +
        '</div>';
    } else {
      change = '<span class="text-steel-400">No before / after values were recorded for this event.</span>';
    }

    openModal(modalShell('Log Entry #' + esc(r.log_id),
      '<dl>' +
        KVROW('Timestamp', esc(fmtTs(r.created_at)) + '<div class="text-[12px] text-steel-400">' + esc(r.created_at) + '</div>') +
        KVROW('Performed by', '<span class="font-mono text-[12px]">' + esc(r.username || 'N/A') + '</span> · ' + esc(r.role_label || 'N/A') +
          (r.user_id ? '' : '<div class="text-[12px] text-steel-400">This account no longer exists.</div>')) +
        KVROW('Target', target) +
        KVROW('Category &amp; Status', '<div class="flex flex-wrap gap-1.5">' + catBadge(r.category) + '<span class="' + BADGE + oc[1] + '">' + oc[0] + '</span></div>') +
        KVROW('Action', esc(r.action)) +
        KVROW('Change details', change) +
        KVROW('IP address', esc(r.ip || 'N/A')) +
      '</dl>',
      (t.appointment_id ? '<button class="' + LOG_BTN + ' bg-accent text-white hover:bg-accent-dark" data-logview="' + t.appointment_id + '">View Appointment</button>' : '') +
      '<button class="' + LOG_BTN + ' border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5" data-close>Close</button>'));
    const v = document.querySelector('#modalRoot [data-logview]');
    if (v) v.addEventListener('click', () => appointmentModal(+v.dataset.logview));
  }

  const logTb = document.querySelector('#logTable tbody');
  const pick = (e) => {
    const tr = e.target.closest('tr[data-log]');
    return tr ? lastRows[+tr.dataset.log] : null;
  };
  logTb.addEventListener('click', (e) => { const r = pick(e); if (r) openLog(r); });
  logTb.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const r = pick(e);
    if (!r) return;
    e.preventDefault();
    openLog(r);
  });

  function query() {
    const p = new URLSearchParams();
    if (g('roleSel').value) p.set('role', g('roleSel').value);
    if (g('catSel').value) p.set('category', g('catSel').value);
    if (g('fromDate').value) p.set('from', g('fromDate').value);
    if (g('toDate').value) p.set('to', g('toDate').value);
    if (g('searchBox').value.trim()) p.set('q', g('searchBox').value.trim());
    return p.toString();
  }

  async function load(isAuto) {
    const tb = document.querySelector('#logTable tbody');
    try {
      const d = await api('logs.php?action=list&' + query());

      // (Re)populate the Role dropdown from the roles present in the log.
      const sel = g('roleSel');
      if (sel.options.length <= 1 && d.roles.length) {
        d.roles.forEach((r) => {
          const o = document.createElement('option');
          o.value = r.value; o.textContent = r.label;
          sel.appendChild(o);
        });
      }

      g('rowCount').textContent = d.count + ' event' + (d.count === 1 ? '' : 's')
        + (d.count >= d.limit ? ' (showing the most recent ' + d.limit + ')' : '');

      lastRows = d.rows;
      if (!isAuto) PG.page = 1;   // a new filter starts on page 1; the 10 s refresh keeps the page
      renderRows();
    } catch (e) {
      tb.innerHTML = EMPTY + esc(e.message) + '</td></tr>';
    }
  }

  function renderRows() {
    const tb = document.querySelector('#logTable tbody');
    if (!tb) return;
    const { rows, from } = window.pageSlice(lastRows, PG, window.fitRows(g('logTable')), g('logPager'), renderRows);
    tb.innerHTML = rows.length ? rows.map((r, k) => { const i = from + k; return (
        '<tr class="cursor-pointer" data-log="' + i + '" tabindex="0" title="View details">' +
        '<td class="whitespace-nowrap text-steel-500 dark:text-steel-400">' + fmtTs(r.created_at) + '</td>' +
        '<td class="font-mono text-[12px] text-navy-800 dark:text-steel-100">' + esc(r.username || 'N/A') + '</td>' +
        '<td class="dark:text-steel-100">' + esc(r.role_label || 'N/A') + '</td>' +
        '<td>' + catBadge(r.category) + '</td>' +
        '<td class="text-navy-800 dark:text-steel-100">' + esc(r.action) + '</td>' +
        '<td class="w-[1%] whitespace-nowrap text-right"><div class="flex justify-end"><span class="inline-flex items-center justify-center rounded-md px-2 py-1 text-[12px] font-semibold text-accent-dark ring-1 ring-inset ring-steel-200 dark:text-accent dark:ring-white/15">View details</span></div></td>' +
        '</tr>'); }
      ).join('') : EMPTY + 'No matching activity.</td></tr>';
  }

  window.wireDateRange(g('fromDate'), g('toDate'));
  ['roleSel', 'catSel', 'fromDate', 'toDate'].forEach((id) =>
    g(id).addEventListener('change', () => load()));
  g('searchBox').addEventListener('input', () => {
    clearTimeout(deb);
    deb = setTimeout(() => load(), 300);
  });
  g('clearFilters').addEventListener('click', () => {
    g('roleSel').value = ''; g('catSel').value = '';
    g('fromDate').value = ''; g('toDate').value = ''; g('searchBox').value = '';
    load();
  });
  window.fitOnResize(() => { if (document.getElementById('logTable')) renderRows(); });

  // Always on (2026-10-18, same as the dashboard): reload silently every 10 s, no toggle.
  clearInterval(window.__logTimer);
  window.__logTimer = setInterval(() => {
    if (!document.getElementById('logTable')) { clearInterval(window.__logTimer); return; }
    load(true);
  }, 10000);

  load();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
