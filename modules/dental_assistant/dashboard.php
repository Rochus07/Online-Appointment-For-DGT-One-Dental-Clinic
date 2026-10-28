<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['dental_assistant']);
$PAGE_TITLE = 'Today\'s Patients';
$ACTIVE = 'dashboard';
$PAGE_FULL = true;

$cardCls  = 'rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800';
$headCls  = 'mb-3.5 flex flex-wrap items-center justify-between gap-3';
$h2Cls    = 'flex items-center gap-2 text-[15px] text-navy-800 dark:text-steel-50';
$btnGhost = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md border border-steel-200 bg-transparent px-2.5 py-1.5 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
$tableCls = 'w-full border-collapse text-[13px] '
    . "[&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 "
    . '[&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:dark:border-white/10 [&_tbody_tr:hover]:bg-steel-50 [&_tbody_tr:hover]:dark:bg-white/5';

ob_start();
?>
<div class="mb-[22px] flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">Today's Patients</h1></div>
</div>

<div class="<?= $cardCls ?> mb-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="<?= $h2Cls ?>">My Duty Today</h2>
            <p class="mt-1 text-[13px] text-steel-500 dark:text-steel-300" id="dutyText">Loading&hellip;</p>
        </div>
        <button class="<?= $btnGhost ?>" id="dutyBtn" disabled>&hellip;</button>
    </div>
</div>

<style>
    /* Scroll target flash (2026-09) — a brief highlight on the Today's
       Schedule card when the header's "New Patient Assigned" bell navigates/
       scrolls here after a checked-in patient is auto-assigned to this
       assistant. Same pattern as the dentist dashboard's own #queueSection
       oc-flash. */
    #schedSection.asst-flash { animation: asst-flash-pulse 1.5s ease; }
    @keyframes asst-flash-pulse {
        0%   { box-shadow: 0 0 0 0 rgba(214,158,46,0); }
        20%  { box-shadow: 0 0 0 4px rgba(214,158,46,.45); }
        100% { box-shadow: 0 0 0 0 rgba(214,158,46,0); }
    }
    @media (prefers-reduced-motion: reduce) { #schedSection.asst-flash { animation: none; } }
</style>
<div class="<?= $cardCls ?>" id="schedSection">
    <div class="<?= $headCls ?>"><h2 class="<?= $h2Cls ?>">Today's Schedule
        <span class="inline-block rounded-full bg-info-soft px-2.5 py-[3px] text-[11px] font-bold text-info dark:bg-info/20" id="schedCount">0</span></h2>
        <button class="<?= $btnGhost ?>" id="refreshBtn">Refresh</button></div>
    <div class="overflow-x-auto">
        <table class="<?= $tableCls ?>" id="schedTable">
            <thead><tr><th>Time</th><th>Patient</th><th>Visit</th><th>Service</th><th>Dentist</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody><tr><td colspan="7" class="py-7 text-center text-steel-400">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);
  const BTN_SM  = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold disabled:cursor-not-allowed disabled:opacity-50';
  const PRIMARY = ' bg-accent text-white hover:bg-accent-dark';
  const GHOST   = ' border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
  const HINT    = 'text-[11px] text-steel-400';
  const BADGE   = 'inline-block whitespace-nowrap rounded-full px-2.5 py-[3px] text-[11px] font-bold';
  const EMPTY   = 'py-7 text-center text-steel-400';

  let onDuty = false;

  // New vs. returning: any earlier completed visit for the person being treated.
  const visitBadge = (a) => +a.prior_visits > 0
    ? '<span class="' + BADGE + ' bg-dgt-blue-50 text-dgt-blue-700 dark:bg-dgt-blue-500/20 dark:text-dgt-blue-200">Returning</span>' +
      '<div class="' + HINT + '">' + (+a.prior_visits) + ' past visit' + (+a.prior_visits === 1 ? '' : 's') + '</div>'
    : '<span class="' + BADGE + ' bg-dgt-teal-50 text-dgt-teal-700 dark:bg-dgt-teal-500/20 dark:text-dgt-teal-200">New patient</span>';

  async function loadDuty() {
    const btn = g('dutyBtn');
    try {
      const { assistant } = await api('assistants.php?action=me');
      onDuty = !!+assistant.on_duty_today;
      g('dutyText').textContent = onDuty
        ? 'You are marked on duty today. You can check patients in.'
        : 'You are not marked on duty yet. Mark yourself on duty to check patients in.';
      btn.textContent = onDuty ? 'Mark me off duty' : 'Mark me on duty';
      btn.className = BTN_SM + (onDuty ? GHOST : PRIMARY);
      btn.disabled = false;
    } catch (e) {
      g('dutyText').textContent = e.message;
      btn.disabled = true;
    }
  }

  async function loadSched() {
    const tb = document.querySelector('#schedTable tbody');
    try {
      const { appointments } = await api('appointments.php?filter=today');
      const rows = appointments.slice().sort((x, y) => String(x.time_slot).localeCompare(String(y.time_slot)));
      g('schedCount').textContent = rows.length;

      tb.innerHTML = rows.length ? rows.map((a) => {
        const action = a.status !== 'completed'
          ? (a.checked_in_at
              ? '<button class="' + BTN_SM + GHOST + '" data-undo="' + a.appointment_id + '">Undo check-in</button>'
              : '<button class="' + BTN_SM + PRIMARY + '" data-checkin="' + a.appointment_id + '"' +
                  (onDuty ? '' : ' disabled title="Mark yourself on duty first"') + '>Check In</button>')
          : '';
        const arrived = a.checked_in_at
          ? '<div class="' + HINT + '">Checked in' + (a.checkin_assistant_name ? ' · ' + esc(a.checkin_assistant_name) : '') + '</div>' : '';
        return '<tr><td class="whitespace-nowrap">' + fmtTime(a.time_slot) + '</td>' +
          '<td><strong>' + esc(a.patient_name) + '</strong>' + arrived + '</td>' +
          '<td>' + visitBadge(a) + '</td>' +
          '<td>' + esc(a.requested_service || 'N/A') + '</td>' +
          '<td>' + esc(a.dentist_name || 'Unassigned') + '</td>' +
          '<td>' + badge(a.status) + '</td>' +
          '<td><div class="flex flex-wrap gap-1.5">' + action + '</div></td></tr>';
      }).join('') : '<tr><td colspan="7" class="' + EMPTY + '">No patients scheduled for today.</td></tr>';
    } catch (e) {
      tb.innerHTML = '<tr><td colspan="7" class="' + EMPTY + '">' + esc(e.message) + '</td></tr>';
    }
  }

  g('dutyBtn').addEventListener('click', async () => {
    g('dutyBtn').disabled = true;
    try {
      await api('assistants.php?action=set_shift', { method: 'POST', body: { on: !onDuty } });
      toast(onDuty ? 'Marked off duty.' : 'Marked on duty.');
    } catch (e) { toast(e.message, 'err'); }
    await loadDuty();
    loadSched();
  });

  g('refreshBtn').addEventListener('click', () => { loadDuty(); loadSched(); });

  document.querySelector('#schedTable').addEventListener('click', async (e) => {
    const ci = e.target.closest('[data-checkin]');
    const un = e.target.closest('[data-undo]');
    if (!ci && !un) return;
    const id = +(ci ? ci.dataset.checkin : un.dataset.undo);
    try {
      await api('appointments.php?action=' + (ci ? 'check_in' : 'undo_check_in'),
        { method: 'POST', body: { appointment_id: id } });
      toast(ci ? 'Patient checked in.' : 'Check-in undone.');
      loadSched();
    } catch (err) { toast(err.message, 'err'); }
  });

  // Handoff from the header's "New Patient Assigned" bell (see assets/js/app.js)
  // — clicking a dropdown item that isn't already on this page sets this flag,
  // navigates here via nav.js, and this re-run of the IIFE (nav.js re-executes
  // inline scripts on every swap) picks it up once and scrolls/flashes the
  // Today's Schedule card. Cleared unconditionally so a stray flag never
  // fires later by accident.
  if (sessionStorage.getItem('dgt_scroll_asst')) {
    sessionStorage.removeItem('dgt_scroll_asst');
    const section = g('schedSection');
    if (section) {
      const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      section.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
      section.classList.add('asst-flash');
      setTimeout(() => section.classList.remove('asst-flash'), 1500);
    }
  }

  loadDuty().then(loadSched);
})();
JS;
require __DIR__ . '/../../includes/layout.php';
