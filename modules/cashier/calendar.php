<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['cashier']);
$PAGE_TITLE = 'Calendar';
$ACTIVE = 'calendar';
$PAGE_FULL = true;   // full-bleed content column (matches the admin screens)

$cardCls  = 'rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800';
$h2Cls    = 'text-[15px] text-navy-800 dark:text-steel-50';
$headCls  = 'mb-3.5 flex flex-wrap items-center justify-between gap-3';
$tableCls = 'w-full border-collapse text-[13px] '
    . "[&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 "
    . '[&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:dark:border-white/10 [&_tbody_tr:hover]:bg-steel-50 [&_tbody_tr:hover]:dark:bg-white/5';

ob_start();
require __DIR__ . '/../../includes/calendar_style.php';
?>
<div class="mb-[22px] flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">Appointment Calendar</h1></div>
</div>

<div class="cal-layout">
    <div class="<?= $cardCls ?> cal-main">
        <div class="cal-toolbar">
            <button class="cal-nav-btn" id="calPrev" type="button" aria-label="Previous month">&lsaquo;</button>
            <h2 id="calMonth">&nbsp;</h2>
            <button class="cal-nav-btn" id="calNext" type="button" aria-label="Next month">&rsaquo;</button>
            <button class="cal-today-btn inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md border border-steel-200 bg-transparent px-2.5 py-1.5 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5" id="calToday" type="button">Today</button>
        </div>
        <div class="cal-grid cal-dow-row" id="calDow"></div>
        <div class="cal-grid" id="calBody"><p class="col-span-full py-7 text-center text-steel-400">Loading&hellip;</p></div>
    </div>

    <div class="cal-side">
        <div class="<?= $cardCls ?>">
            <div class="<?= $headCls ?>"><h2 class="<?= $h2Cls ?>">Statistics</h2></div>
            <div class="cal-stats">
                <div class="cal-stat"><span id="stTotal">0</span><small>Appointments</small></div>
                <div class="cal-stat"><span id="stMonth">0</span><small>This Month</small></div>
                <div class="cal-stat"><span id="stToday">0</span><small>Today&rsquo;s Schedule</small></div>
            </div>
        </div>
        <div class="<?= $cardCls ?>">
            <div class="<?= $headCls ?>"><h2 class="<?= $h2Cls ?>">Upcoming Appointments</h2></div>
            <div id="calUpcoming"><p class="text-steel-400">Loading&hellip;</p></div>
        </div>
    </div>

    <div class="<?= $cardCls ?>" id="dayCard" hidden>
    <div class="<?= $headCls ?>"><h2 class="<?= $h2Cls ?>" id="dayTitle"></h2></div>
    <div class="overflow-x-auto">
        <table class="<?= $tableCls ?>">
            <thead><tr><th>Time</th><th>Patient</th><th>Concern</th><th>Services</th><th>Booking</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody id="dayList"></tbody>
        </table>
    </div>
    </div>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  const DOW = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  const BTN_GHOST_SM = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md border border-steel-200 bg-transparent px-2.5 py-1.5 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
  const EMPTY_ROW = (msg) => '<tr><td colspan="7" class="py-7 text-center text-steel-400">' + msg + '</td></tr>';

  const pad = (n) => String(n).padStart(2, '0');
  const keyOf = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  let todayKey = keyOf(new Date());   // refreshed by Today (the page may stay open past midnight)

  // Calendar chip modifier = the plain appointment status (no "Ongoing" state).
  const chipStatus = (a) => esc(a.status);

  let appts = [];
  let byDate = {};
  let blocked = {};          // { 'YYYY-MM-DD': reason }
  let view = new Date(); view.setDate(1);
  let selected = null;

  const dow = document.getElementById('calDow');
  dow.innerHTML = DOW.map((d) => '<div class="cal-dow">' + d + '</div>').join('');

  async function load() {
    try {
      const [all, upcoming, blk] = await Promise.all([
        api('appointments.php?filter=all'),
        api('appointments.php?filter=upcoming'),
        api('settings.php?action=blocked_dates'),
      ]);
      appts = all.appointments;
      blocked = {};
      (blk.blocked || []).forEach((b) => { blocked[b.blocked_date] = b.reason || 'Clinic closed'; });
      byDate = {};
      appts.forEach((a) => { (byDate[a.scheduled_date] = byDate[a.scheduled_date] || []).push(a); });
      Object.values(byDate).forEach((list) => list.sort((x, y) => x.time_slot.localeCompare(y.time_slot)));
      render();
      renderStats();
      renderUpcoming(upcoming.appointments || []);
    } catch (e) {
      document.getElementById('calBody').innerHTML = '<p class="col-span-full py-7 text-center text-steel-400">' + esc(e.message) + '</p>';
    }
  }

  function render() {
    document.getElementById('calMonth').textContent = MONTHS[view.getMonth()] + ' ' + view.getFullYear();
    const first = new Date(view.getFullYear(), view.getMonth(), 1);
    const start = new Date(first);
    start.setDate(1 - first.getDay());

    let html = '';
    for (let i = 0; i < 42; i++) {
      const d = new Date(start);
      d.setDate(start.getDate() + i);
      const k = keyOf(d);
      const inMonth = d.getMonth() === view.getMonth();
      const list = byDate[k] || [];
      const closed = (d.getDay() === 0 && !window.SUNDAY_OPEN) || (k in blocked);   // Sundays + blocked dates
      const cls = ['cal-cell'];
      if (!inMonth) cls.push('other');
      if (k === todayKey) cls.push('today');
      if (k === selected) cls.push('selected');
      if (closed) cls.push('closed');

      const chips = list.slice(0, 3).map((a) =>
        '<span class="cal-chip s-' + chipStatus(a) + '">' + fmtTime(a.time_slot) + ' &middot; ' + esc(a.patient_name) + '</span>'
      ).join('');
      const more = list.length > 3 ? '<span class="cal-more">+' + (list.length - 3) + ' more</span>' : '';
      // Phones show this count badge instead of the chips (calendar_style.php).
      const count = list.length ? '<span class="cal-count">' + list.length + '</span>' : '';

      html += '<div class="' + cls.join(' ') + '" data-day="' + k + '">' +
        '<span class="cal-date">' + d.getDate() + '</span>' +
        (closed && !list.length ? '<span class="cal-closed">Closed</span>' : chips + more + count) + '</div>';
    }
    document.getElementById('calBody').innerHTML = html;
  }

  function renderStats() {
    const live = appts.filter((a) => a.status !== 'cancelled');
    const now = new Date();
    const ym = now.getFullYear() + '-' + pad(now.getMonth() + 1);
    document.getElementById('stTotal').textContent = live.length;
    document.getElementById('stMonth').textContent = live.filter((a) => a.scheduled_date.startsWith(ym)).length;
    document.getElementById('stToday').textContent = live.filter((a) => a.scheduled_date === todayKey).length;
  }

  // `up` comes pre-filtered and pre-sorted from appointments.php?filter=upcoming
  // (future active schedules only — past hours today are already excluded).
  function renderUpcoming(up) {
    const wrap = document.getElementById('calUpcoming');
    if (!up.length) {
      wrap.innerHTML = '<p class="text-steel-400">No upcoming appointments scheduled.</p>';
      return;
    }
    wrap.innerHTML = up.map((a) => {
      const d = new Date(a.scheduled_date + 'T00:00:00');
      const when = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
      return '<div class="cal-up-item"><b>' + esc(when) + '</b>' +
        '<span>' + esc(a.patient_name) + '</span>' +
        '<small>' + fmtTime(a.time_slot) + ' &middot; ' + esc(a.requested_service || 'Consultation') + '</small></div>';
    }).join('');
  }

  function showDay(k) {
    selected = k;
    render();
    const list = byDate[k] || [];
    const card = document.getElementById('dayCard');
    const d = new Date(k + 'T00:00:00');
    const sunClosed = d.getDay() === 0 && !window.SUNDAY_OPEN;
    const closedReason = sunClosed ? 'Sundays' : (blocked[k] || '');
    const closed = sunClosed || (k in blocked);
    document.getElementById('dayTitle').textContent =
      d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) +
      (closed ? ': clinic closed' : '');
    document.getElementById('dayList').innerHTML = list.length ? list.map((a) => {
      // Once services are availed (logged), show that list; otherwise the
      // single service the patient booked.
      const svcCell = a.procedures
        ? serviceSummary(a.procedures)
        : esc(a.requested_service || 'N/A');
      return '<tr><td>' + fmtTime(a.time_slot) + '</td><td><strong>' + esc(a.patient_name) + '</strong></td>' +
        '<td class="max-w-[240px] text-steel-400">' + esc(initialConcern(a.notes) || 'N/A') + '</td>' +
        '<td>' + svcCell + '</td>' +
        '<td>' + srcBadge(a.booking_type) + '</td>' +
        '<td>' + opStatus(a) + '</td>' +
        '<td><button class="' + BTN_GHOST_SM + '" data-view="' + a.appointment_id + '">View</button></td></tr>';
    }).join('') : EMPTY_ROW(
        closed ? 'Clinic closed' + (closedReason ? ': ' + esc(closedReason) : '') + '.' : 'No appointments on this day.');
    // Every screen size: the day opens in a pop-up instead of a card further
    // down the page, so there is no scrolling to find it.
    {
      card.hidden = true;
      const items = list.length ? list.map((a) =>
        '<div class="rounded-[10px] border border-steel-100 p-3.5 dark:border-white/10">' +
          '<div class="flex items-start justify-between gap-3">' +
            '<div class="min-w-0"><div class="text-xs font-semibold text-steel-500 dark:text-steel-400">' + fmtTime(a.time_slot) + '</div>' +
            '<div class="mt-0.5 text-[15px] font-semibold text-navy-900 dark:text-steel-50">' + esc(a.patient_name) + '</div>' +
            '<div class="mt-0.5 text-[13px] text-steel-500 dark:text-steel-400">' + (a.procedures ? serviceSummary(a.procedures) : esc(a.requested_service || 'N/A')) + '</div></div>' +
            '<button class="tap-sm inline-flex h-9 flex-none items-center justify-center rounded-lg border border-steel-200 px-4 text-[13px] font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5" data-view="' + a.appointment_id + '">View</button>' +
          '</div>' +
          (initialConcern(a.notes) ? '<div class="mt-2 rounded-md bg-steel-50 px-2.5 py-1.5 text-xs text-steel-500 dark:bg-white/5 dark:text-steel-400"><span class="font-semibold">Concern:</span> ' + esc(initialConcern(a.notes)) + '</div>' : '') +
          '<div class="mt-2 flex flex-wrap gap-1.5">' + srcBadge(a.booking_type) + opStatus(a) + '</div>' +
        '</div>').join('')
        : '<p class="py-6 text-center text-[13px] text-steel-400">' + (closed ? 'Clinic closed' + (closedReason ? ': ' + esc(closedReason) : '') + '.' : 'No appointments on this day.') + '</p>';
      openModal(modalShell(esc(d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })),
        (list.length ? '<p class="mb-3 text-xs text-steel-400">' + list.length + ' appointment' + (list.length === 1 ? '' : 's') + '</p>' : '') +
        '<div class="grid gap-2.5 md:grid-cols-2">' + items + '</div>'), { wide: true });
      return;
    }
    card.hidden = false;
  }

  document.getElementById('calBody').addEventListener('click', (e) => {
    const cell = e.target.closest('[data-day]');
    if (cell) showDay(cell.dataset.day);
  });
  document.getElementById('calPrev').addEventListener('click', () => { view.setMonth(view.getMonth() - 1); render(); });
  document.getElementById('calNext').addEventListener('click', () => { view.setMonth(view.getMonth() + 1); render(); });
  document.getElementById('calToday').addEventListener('click', () => {
    todayKey = keyOf(new Date()); view = new Date(); view.setDate(1); showDay(todayKey);
  });

  load();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
