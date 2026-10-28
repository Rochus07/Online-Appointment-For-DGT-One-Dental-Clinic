<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['dentist']);
$PAGE_TITLE = 'My Schedule';
$ACTIVE = 'dashboard';
$PAGE_FULL = true;   // full-bleed content column (matches the admin screens)

$cardCls   = 'rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800';
$headCls   = 'mb-3.5 flex flex-wrap items-center justify-between gap-3';
$h2Cls     = 'text-[15px] text-navy-800 dark:text-steel-50';
$btnGhostSm = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md border border-steel-200 bg-transparent px-2.5 py-1.5 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
$tableCls  = 'w-full border-collapse text-[13px] '
    . "[&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 "
    . '[&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:dark:border-white/10 [&_tbody_tr:hover]:bg-steel-50 [&_tbody_tr:hover]:dark:bg-white/5 2xl:text-[14.5px] 2xl:[&_td]:py-3.5 2xl:[&_th]:py-3';

ob_start();
// Tooth chart step before Perform Procedure (2026-10-26).
require __DIR__ . '/../../includes/records_chart_style.php';
?>
<div class="mb-[22px] flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">Today&rsquo;s Schedule</h1></div>
</div>

<style>
    /* Scroll target flash (2026-09) — a brief highlight on the Queue card when
       the header's on-call bell (roaming dentists only) navigates/scrolls here
       after an overflow booking is approved. Harmless no-op for an in-house
       Owner, who has no on-call bell to ever trigger it. prefers-reduced-motion
       still gets the scroll (an instant jump, handled in JS) just not this
       fade. */
    #queueSection.oc-flash { animation: oc-flash-pulse 1.5s ease; }
    @keyframes oc-flash-pulse {
        0%   { box-shadow: 0 0 0 0 rgba(214,158,46,0); }
        20%  { box-shadow: 0 0 0 4px rgba(214,158,46,.45); }
        100% { box-shadow: 0 0 0 0 rgba(214,158,46,0); }
    }
    @media (prefers-reduced-motion: reduce) { #queueSection.oc-flash { animation: none; } }
</style>
<div class="<?= $cardCls ?>" id="queueSection">
    <div class="<?= $headCls ?>">
        <h2 class="<?= $h2Cls ?>">Queue <span id="queueCount" class="ml-1 text-[12px] font-normal text-steel-400"></span></h2>
        <button class="<?= $btnGhostSm ?>" id="refreshBtn">Refresh</button>
    </div>
    <div class="overflow-x-auto">
        <table class="rtable rt-mini <?= $tableCls ?>" id="queueTable">
            <thead><tr><th>Time</th><th>Patient</th><th>Age</th><th>Concern</th><th>Booking</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody><tr><td colspan="7" class="py-7 text-center text-steel-400">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
    <nav id="queuePager" class="mt-4 flex flex-wrap items-center justify-between gap-3" aria-label="Queue pages" hidden></nav>
</div>

<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  let services = [];
  let queue = [];

  const BTN_SM   = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold disabled:cursor-not-allowed disabled:opacity-50';
  const GHOST    = ' border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
  const PRIMARY  = ' bg-accent text-white hover:bg-accent-dark';
  const BADGE    = 'inline-block rounded-full px-2.5 py-[3px] text-[11px] font-bold capitalize';
  const emptyRow = (n, msg) => '<tr><td colspan="' + n + '" class="py-7 text-center text-steel-400">' + msg + '</td></tr>';

  async function load() {
    const tb = document.querySelector('#queueTable tbody');
    try {
      const [{ appointments }, s] = await Promise.all([
        api('appointments.php?filter=today'),
        api('services.php?active=1'),
      ]);
      services = s.services;
      // The chair queue = every one of today's approved/rescheduled visits,
      // regardless of check-in status — the doctor sees the whole day's
      // roster. Check-in never controls who's visible here (the status badge
      // stays "Approved" either way), but it DOES strictly gate "Perform
      // Procedure": Approved -> Check-In -> Perform Procedure unlocks ->
      // Completed. api/treatments.php action=log re-enforces this server-side.
      // Sorted by booked time slot; within a shared slot the patient who
      // checked in earliest comes first, not-yet-checked-in ones last.
      queue = appointments
        .filter((a) => a.status === 'approved' || a.status === 'rescheduled')
        .sort((x, y) => String(x.time_slot).localeCompare(String(y.time_slot))
          || (x.checked_in_at ? 0 : 1) - (y.checked_in_at ? 0 : 1)
          || String(x.checked_in_at || '').localeCompare(String(y.checked_in_at || ''))
          || (+x.appointment_id) - (+y.appointment_id));
      const here = queue.filter((a) => a.checked_in_at).length;
      const cc = document.getElementById('queueCount');
      if (cc) cc.textContent = queue.length ? '· ' + here + ' checked in / ' + queue.length + ' today' : '';
      renderQueue();
    } catch (e) { tb.innerHTML = emptyRow(7, esc(e.message)); }
  }
  // 10 patients per page (2026-10-17); a reload keeps the page (clamped).
  const QPG = { page: 1 };
  window.fitOnResize(() => { if (document.getElementById('queueTable')) renderQueue(); });
  function renderQueue() {
    const tb = document.querySelector('#queueTable tbody');
    if (!tb) return;
    const { rows } = window.pageSlice(queue, QPG, window.fitRows(document.getElementById('queueTable')), document.getElementById('queuePager'), renderQueue);
    {
      tb.innerHTML = rows.length ? rows.map((a) => {
        return '<tr><td>' + fmtTime(a.time_slot) + '</td>' +
        '<td class="rt-title"><strong>' + esc(a.patient_name) + '</strong></td>' +
        '<td>' + esc(a.patient_age || 'N/A') + '</td>' +
        '<td class="max-w-[280px] text-steel-400">' + esc(initialConcern(a.notes) || 'N/A') + '</td>' +
        '<td>' + srcBadge(a.booking_type) + '</td>' +
        '<td>' + opStatus(a) + '</td>' +
        '<td><div class="flex flex-wrap gap-2">' +
        '<button class="' + BTN_SM + GHOST + '" data-view="' + a.appointment_id + '">View</button>' +
        '<button class="' + BTN_SM + PRIMARY + '" data-log="' + a.appointment_id + '"' +
          (a.checked_in_at ? '' : ' disabled title="Check in the patient first"') + '>Perform Procedure</button>' +
        '</div></td></tr>';
      }).join('') : emptyRow(7, 'No assigned patients for today.');
    }
  }

  document.querySelector('#queueTable').addEventListener('click', (e) => {
    const b = e.target.closest('[data-log]');
    if (!b) return;
    const appt = queue.find((a) => String(a.appointment_id) === b.dataset.log);
    if (!appt) return toast('That appointment is no longer in your queue.', 'err');
    logProcedureModal({ appt, services, onDone: () => load() });
  });
  document.getElementById('refreshBtn').addEventListener('click', () => load());

  // Handoff from the header's on-call bell (roaming dentists only, see
  // assets/js/app.js) — an approved overflow booking now just shows up as an
  // ordinary row in the Queue table above (no separate "On-Call Assignments"
  // card any more, simplified 2026-09), so the bell's dropdown items scroll
  // straight to that table instead. Clicking a dropdown item that isn't
  // already on this page sets this flag, navigates here via nav.js, and this
  // re-run of the IIFE (nav.js re-executes inline scripts on every swap)
  // picks it up once. Cleared unconditionally so a stray flag from some other
  // flow never fires later by accident. A no-op for an in-house Owner, who
  // has no on-call bell to ever set it.
  if (sessionStorage.getItem('dgt_scroll_oncall')) {
    sessionStorage.removeItem('dgt_scroll_oncall');
    const section = document.getElementById('queueSection');
    if (section) {
      const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      section.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
      section.classList.add('oc-flash');
      setTimeout(() => section.classList.remove('oc-flash'), 1500);
    }
  }

  // A Decline from the header bell drops the patient from the queue.
  window.__onNotifChanged = () => {
    if (!document.getElementById('queueTable')) return;
    load();
  };

  load();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
