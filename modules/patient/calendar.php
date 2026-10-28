<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['patient']);
// Patient Information Record first (2026-10-20): no booking until it is answered.
// Also when the record's review (every HF_REVIEW_MONTHS) is due (2026-10-20).
if (!healthFormReady((int) $me['user_id'])) {
    header('Location: /dgt_dental_clinic/modules/patient/health_form');
    exit;
}
$PAGE_TITLE = 'Book an Appointment';
$ACTIVE = 'calendar';
$PAGE_FULL = true;

$cardCls = 'rounded-[10px] border border-steel-100 bg-white p-3 shadow-sm sm:p-[18px] dark:border-white/10 dark:bg-navy-800';

ob_start();
require __DIR__ . '/../../includes/patient_calendar_style.php';
?>
<div class="mb-[22px] flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">Book an Appointment</h1></div>
    <a href="/dgt_dental_clinic/modules/patient/appointments" data-nav
       class="inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md border border-steel-200 bg-transparent px-[15px] py-[9px] text-[13px] font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5">My Appointments</a>
</div>

<!-- Responsive booking card (2026-10): phones = full width, calendar then
     times stacked, compact squares; large phones = one centred card
     (max-w-md); tablets (768+) = full-width card, big calendar with the times
     below; desktop (1280+) = full-width calendar too (2026-10-10); the times open in a pop-up and the picked date stays green.
     Cells are wider-than-tall from 768px up so the month fills the page
     without becoming taller than the screen. -->
<div class="pcal-shell mx-auto w-full rounded-[10px] border border-steel-100 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-navy-800 sm:max-w-md sm:rounded-2xl sm:p-6 md:max-w-none xl:p-8">
    <div class="pcal-main">
        <div class="pcal-toolbar">
            <button class="pcal-nav-btn" id="pcalPrev" type="button" aria-label="Previous month">&lsaquo;</button>
            <h2 id="pcalMonth">&nbsp;</h2>
            <button class="pcal-nav-btn" id="pcalNext" type="button" aria-label="Next month">&rsaquo;</button>
            <button class="pcal-today-btn inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md border border-steel-200 bg-transparent px-2.5 py-1.5 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5" id="pcalToday" type="button">Today</button>
        </div>
        <div class="pcal-legend">
            <span><i class="lg-open"></i> Available</span>
            <span><i class="lg-full"></i> Fully Booked</span>
            <span><i class="lg-closed"></i> Closed</span>
        </div>
        <div class="pcal-grid pcal-dow-row" id="pcalDow"></div>
        <div class="pcal-grid" id="pcalBody"><p class="col-span-full py-7 text-center text-steel-400 dark:text-steel-500">Loading&hellip;</p></div>
    </div>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  const DOW = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  const g = (id) => document.getElementById(id);
  const pad = (n) => String(n).padStart(2, '0');
  const keyOf = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  // Each clinic slot is a 1-hour chair booking — show it as a start-end
  // range ("10:00 AM – 11:00 AM") rather than just the start time. The
  // clinic's own last slot (4:00 PM, migration 053 dropped 5:00 PM) runs
  // exactly to closing time, so a flat "+1 hour" is always accurate here —
  // no special-casing needed for the last slot of the day.
  const slotRange = (s) => {
    const m = String(s).match(/^(\d{1,2}):(\d{2})/);
    if (!m) return fmtTime(s);
    const end = String((+m[1] + 1) % 24).padStart(2, '0') + ':00';   // ends on the next full hour
    return fmtTime(s) + ' – ' + fmtTime(end);
  };

  let todayKey = keyOf(new Date());   // refreshed by Today (the page may stay open past midnight)
  // Online bookings need onlineLeadDays() days' notice (2, or 0 when the admin
  // turned the rule off). Defensive client-side mirror of
  // action=availability's `info.past`.
  const ONLINE_LEAD_DAYS = __LEAD_DAYS__;
  let earliestKey = todayISO(ONLINE_LEAD_DAYS);

  let view = new Date(); view.setDate(1);
  let days = {};              // { 'YYYY-MM-DD': {closed|past|open|full|slots|left...} }
  let slotOrder = [];
  let selected = null;
  let loadSeq = 0;

  g('pcalDow').innerHTML = DOW.map((d) => '<div class="pcal-dow">' + d + '</div>').join('');

  function gridStart() {
    const first = new Date(view.getFullYear(), view.getMonth(), 1);
    const s = new Date(first);
    s.setDate(1 - first.getDay());
    return s;
  }

  async function load() {
    const seq = ++loadSeq;
    g('pcalMonth').textContent = MONTHS[view.getMonth()] + ' ' + view.getFullYear();
    const start = gridStart();
    const end = new Date(start); end.setDate(start.getDate() + 41);
    try {
      const r = await api('appointments.php?action=availability&from=' + keyOf(start) + '&to=' + keyOf(end));
      if (seq !== loadSeq) return;
      days = r.days || {};
      slotOrder = r.slots || [];
      render();
    } catch (e) {
      if (seq === loadSeq) g('pcalBody').innerHTML = '<p class="col-span-full py-7 text-center text-steel-400 dark:text-steel-500">' + esc(e.message) + '</p>';
    }
  }

  function cellInfo(k, inMonth) {
    const info = days[k] || {};
    if (!inMonth) return { cls: 'out', tag: '', click: false };
    if (info.closed) return { cls: 'closed', tag: 'Closed', click: true };
    if (info.past || k < earliestKey) return { cls: 'out', tag: '', click: false };
    if (info.day_over) return { cls: 'closed', tag: 'Closed', click: true };
        if (info.mine) return { cls: 'full', tag: 'Booked', click: true };
    if (info.full) return { cls: 'full', tag: 'Fully Booked', click: true };
    // Patient places left that day (2026-10-26): every hour's free places added
    // up, in-house + roaming (2 an hour), not just the hours with room.
    const n = Object.values(info.left || {}).reduce((t, v) => t + (+v || 0), 0) || (+info.open || 0);
    return { cls: 'open bookable', tag: n ? n + (n === 1 ? ' slot left' : ' slots left') : '', click: true };
  }

  function render() {
    const start = gridStart();
    let html = '';
    for (let i = 0; i < 42; i++) {
      const d = new Date(start); d.setDate(start.getDate() + i);
      const k = keyOf(d);
      const inMonth = d.getMonth() === view.getMonth();
      const ci = cellInfo(k, inMonth);
      const cls = ['pcal-cell', ci.cls];
      if (k === todayKey) cls.push('today');
      const isPicked = inMonth && picked && picked.date === k;
      if (isPicked) cls.push('picked');
      else if (k === selected) cls.push('selected');
      const tag = isPicked
        ? '<span class="pcal-pick"><span class="pk-short">&#10003;' + esc(fmtTime(picked.slot).replace(':00', '').replace(' ', '')) + '</span><span class="pk-long">&#10003; ' + esc(fmtTime(picked.slot)) + '</span></span>'
        : (k === todayKey && inMonth) ? '<span class="pcal-tag pcal-tag-today">Today</span>'
        : (ci.tag ? '<span class="pcal-tag">' + ci.tag + '</span>' : '');
      html += '<' + (ci.click ? 'button type="button"' : 'div') + ' class="' + cls.join(' ') + '"' +
        (ci.click ? ' data-day="' + k + '"' : '') + '>' +
        '<span class="pcal-date">' + d.getDate() + '</span>' + tag +
        '</' + (ci.click ? 'button' : 'div') + '>';
    }
    g('pcalBody').innerHTML = html;
  }

  // Clicking a date opens its times in a pop-up (window.openSlotPopup,
  // 2026-10-10, adviser feedback: the list used to render further down the
  // page, out of view on phones and tablets). The picked date stays green
  // with its time, and the right/lower panel offers Continue to Booking.
  // {date, slot, popupDay} is kept in sessionStorage so a reload restores it.
  const DRAFT_KEY = 'dgt_cal_draft';
  let picked = null;      // { date, slot }
  let popupDay = null;
  function saveDraft() {
    try {
      if (!picked && !popupDay) sessionStorage.removeItem(DRAFT_KEY);
      else sessionStorage.setItem(DRAFT_KEY, JSON.stringify({ v: 1, date: picked ? picked.date : null, slot: picked ? picked.slot : null, popupDay }));
    } catch (_) { /* private mode: nothing to keep */ }
  }
  function readDraft() {
    try {
      const d = JSON.parse(sessionStorage.getItem(DRAFT_KEY) || 'null');
      if (d && d.v === 1) return d;
    } catch (_) { /* corrupted */ }
    try { sessionStorage.removeItem(DRAFT_KEY); } catch (_) { /* blocked */ }
    return null;
  }
  const longDay = (k) => new Date(k + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
  const isFree = (info, s) => info.left ? (info.left[s] | 0) > 0 : !!(info.slots && info.slots[s]);

  function setPicked(k, slot) {
    picked = slot ? { date: k, slot } : null;
    render(); saveDraft();
  }

  function showDay(k) {
    const info = days[k] || {};
    popupDay = k; selected = k; render(); saveDraft();
    let note = '', slots = [];
    if (info.closed) note = info.reason || 'The clinic is closed on this date.';
    else {
      const order = slotOrder.length ? slotOrder : Object.keys(info.slots || {});
      slots = order.map((s) => isFree(info, s)
        ? { slot: s, label: slotRange(s), free: true, sub: info.left ? slotsLeftText(info.left[s]) : '' }
        : { slot: s, label: slotRange(s), free: false, sub: info.passed && info.passed[s] ? 'Passed' : info.soon && info.soon[s] ? 'Too soon' : (info.slots && !(s in info.slots)) ? 'Unavailable' : 'Occupied' });
      if (!slots.some((x) => x.free)) note = info.day_over ? 'Clinic hours are over for today. Please choose another day.' : ((info.passed && Object.keys(info.passed).length) || (info.soon && Object.keys(info.soon).length) ? 'No time slots are left today. Please choose another day.' : 'This day is fully booked. Please choose another day.');
    }
    openSlotPopup({
      title: longDay(k), note, slots,
      selected: picked && picked.date === k ? picked.slot : '',
      confirmLabel: 'Continue to Booking',
      onPick: (slot) => setPicked(k, slot),
      onConfirm: () => goToBooking(),
      onClose: () => { popupDay = null; selected = null; render(); saveDraft(); },
    });
  }

  // Continue to Booking (the pop-up button): hand the date + time to the
  // booking wizard. The green date alone shows the pick after closing;
  // clicking it reopens the pop-up to change it.
  function goToBooking() {
    if (!picked) return;
    try {
      sessionStorage.setItem('dgt_book_prefill', JSON.stringify({ date: picked.date, slot: picked.slot }));
      sessionStorage.removeItem(DRAFT_KEY);
    } catch (_) { /* private mode: the wizard still opens, just without the prefill */ }
    const a = document.createElement('a');
    a.href = '/dgt_dental_clinic/modules/patient/appointments';
    a.setAttribute('data-nav', '');
    a.hidden = true;
    document.body.appendChild(a);
    a.click();
    a.remove();
  }

  g('pcalBody').addEventListener('click', (e) => {
    const cell = e.target.closest('[data-day]');
    if (cell && (days[cell.dataset.day] || {}).mine) return window.dayTakenPopup();
    if (cell) showDay(cell.dataset.day);
  });
  g('pcalPrev').addEventListener('click', () => { view.setMonth(view.getMonth() - 1); load(); });
  g('pcalNext').addEventListener('click', () => { view.setMonth(view.getMonth() + 1); load(); });
  g('pcalToday').addEventListener('click', async () => {
    todayKey = keyOf(new Date()); earliestKey = todayISO(ONLINE_LEAD_DAYS);
    view = new Date(); view.setDate(1);
    await load();
    if (g('pcalBody').querySelector('[data-day="' + todayKey + '"]')) showDay(todayKey);
  });

  // Restore a pick (and an open pop-up) from before a reload. A stale pick
  // (now past, full or closed) is dropped.
  (async function restore() {
    const d = readDraft();
    const okDate = (x) => /^\d{4}-\d{2}-\d{2}$/.test(x || '');
    const focus = d && (okDate(d.popupDay) ? d.popupDay : okDate(d.date) ? d.date : null);
    if (focus) { view = new Date(focus + 'T00:00:00'); view.setDate(1); }
    await load();
    if (!d) return;
    if (okDate(d.date) && d.slot) {
      const info = days[d.date];
      if (!info || (!info.closed && !info.past && d.date >= earliestKey && isFree(info, d.slot))) picked = { date: d.date, slot: String(d.slot) };
    }
    render(); saveDraft();
    if (okDate(d.popupDay) && days[d.popupDay] && !days[d.popupDay].past && (days[d.popupDay].closed || d.popupDay >= earliestKey)) showDay(d.popupDay);
  })();
})();
JS;
$PAGE_SCRIPT = str_replace('__LEAD_DAYS__', (string) onlineLeadDays(), $PAGE_SCRIPT);
require __DIR__ . '/../../includes/layout.php';
