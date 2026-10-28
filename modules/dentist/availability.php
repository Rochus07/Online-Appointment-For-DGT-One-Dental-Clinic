<?php
declare(strict_types=1);

/*
 * My Availability (2026-10-19): the In-House dentist manages their own hours.
 * Pick a date on the calendar and set it to a Full Day Off or custom hours
 * (e.g. 10:00 AM to 12:00 PM only). Every other date keeps the usual hours.
 * Saving locks those hours on the online booking calendar at once, and any
 * patient already booked inside them is moved to an on-duty roaming dentist
 * or asked to pick a new time (api/schedules.php processScheduleConflicts()).
 * Roaming dentists have no fixed hours, so they never see this page.
 */
require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['dentist']);
if (($me['doctor_type'] ?? '') !== 'owner') {
    header('Location: ' . roleHome('dentist'));
    exit;
}
$PAGE_TITLE = 'My Availability';
$ACTIVE = 'availability';
$PAGE_FULL = true;

$cardCls  = 'rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800';
$h2Cls    = 'text-[15px] text-navy-800 dark:text-steel-50';
$btnDark  = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md bg-navy-700 px-[15px] py-[9px] text-[13px] font-semibold text-white hover:brightness-110 disabled:opacity-50';
$btnGhost = 'inline-flex cursor-pointer items-center justify-center rounded-md border border-steel-200 bg-white px-2.5 py-1.5 text-[12px] font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:bg-navy-900 dark:text-steel-100 dark:hover:bg-white/5';

ob_start();
?>
<style>
#calGrid button { min-width: 0 !important; min-height: 0 !important; }
</style>
<div class="mb-[18px]"><h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">My Availability</h1></div>

<div class="flex flex-col gap-4">
    <div class="<?= $cardCls ?> flex flex-col p-3 sm:p-[18px] lg:min-h-[calc((100vh-170px)/var(--uiz,1))]">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="<?= $h2Cls ?> !text-[17px]">Calendar</h2>
            <div class="flex items-center gap-1.5 max-sm:w-full">
                <button class="<?= $btnGhost ?> !px-2.5" id="calPrev" aria-label="Previous month">&lsaquo;</button>
                <span id="calMonth" class="min-w-[128px] max-sm:min-w-0 max-sm:flex-1 text-center text-[13px] font-semibold text-navy-800 dark:text-steel-50"></span>
                <button class="<?= $btnGhost ?> !px-2.5" id="calNext" aria-label="Next month">&rsaquo;</button>
                <button class="<?= $btnGhost ?>" id="calThis">This Month</button>
            </div>
        </div>
        <div class="mb-3 flex flex-wrap gap-x-5 gap-y-1 max-sm:gap-x-3.5 max-sm:text-[11.5px] text-[12.5px] font-semibold text-steel-500 dark:text-steel-400">
            <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-ok"></span>Usual hours</span>
            <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-warn"></span>Custom hours</span>
            <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-[#7c3aed]"></span>Day off</span>
            <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-danger"></span>Absent</span>
        </div>
        <div id="calGrid" class="grid flex-1 grid-cols-6 gap-1 sm:gap-2"></div>
    </div>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);
  const DNAME = { 1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat' };
  let SLOTS = [];
  const pad2 = (n) => String(n).padStart(2, '0');
  const dkey = (d) => d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
  const endOf = (s) => pad2(+s.slice(0, 2) + 1) + ':00';
  const rangeOf = (s) => fmtTime(s) + ' – ' + fmtTime(endOf(s));
  const BTN_Q = 'inline-flex cursor-pointer items-center justify-center rounded-md border border-steel-200 bg-white px-3 py-2 text-[12px] font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:bg-navy-900 dark:text-steel-100 dark:hover:bg-white/5';
  const BTN_P = 'inline-flex cursor-pointer items-center justify-center rounded-md bg-navy-700 px-[15px] py-[9px] text-[13px] font-semibold text-white hover:brightness-110';

  // What happened to booked patients, after a save.
  function conflictToast(c, what) {
    c = c || {};
    const parts = [];
    if (c.reassigned) parts.push(c.reassigned + ' moved to a roaming dentist');
    if (c.needs_reschedule) parts.push(c.needs_reschedule + ' asked to pick a new time');
    if (c.restored) parts.push(c.restored + ' kept their original time');
    toast(what + (parts.length ? ' Patients: ' + parts.join(', ') + '. The front desk was notified.' : ''), 'ok');
  }

  // Confirm before turning off hours that already have patients.
  function confirmAffected(n, onYes) {
    if (!n) return onYes();
    openModal(modalShell('Patients Are Booked in These Hours',
      '<p class="text-[13px] text-navy-800 dark:text-steel-100">' + n + ' patient' + (n === 1 ? ' is' : 's are') +
      ' booked in the hours you are turning off.</p>' +
      '<ul class="mt-2 list-disc pl-5 text-[13px] text-steel-600 dark:text-steel-300">' +
      '<li>Each one moves to an on-duty roaming dentist when one is free at that time.</li>' +
      '<li>Otherwise the patient is asked to pick a new date and time on their portal.</li>' +
      '<li>Patients get an in-app notice and an e-mail. The front desk is alerted.</li></ul>',
      '<button class="' + BTN_Q + '" data-close>Go Back</button><button class="' + BTN_P + '" id="cfYes">Save Changes</button>'));
    g('cfYes').addEventListener('click', onYes);
  }

  /* ---------- Calendar: one date at a time ---------- */
  let calMonth = new Date(); calMonth.setDate(1);
  let calDays = {}, calToday = todayISO(), calSeq = 0;
  const spanText = (slots) => slots.length ? fmtTime(slots[0]) + ' – ' + fmtTime(endOf(slots[slots.length - 1])) : '';

  async function loadCal() {
    g('calMonth').textContent = calMonth.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    const first = new Date(calMonth), last = new Date(calMonth.getFullYear(), calMonth.getMonth() + 1, 0);
    const start = new Date(first); start.setDate(first.getDate() - ((first.getDay() + 6) % 7));
    const end = new Date(last); end.setDate(last.getDate() + (6 - ((last.getDay() + 6) % 7)));
    const seq = ++calSeq;
    try {
      const r = await api('schedules.php?action=days&from=' + dkey(start) + '&to=' + dkey(end));
      if (seq !== calSeq) return;
      calDays = r.days || {}; calToday = r.today || todayISO();
      SLOTS = r.slots || [];
      renderCal(start, end);
    } catch (e) { g('calGrid').innerHTML = '<p class="col-span-6 text-[12px] text-danger">' + esc(e.message) + '</p>'; }
  }

  const CAL_CELL = 'flex min-h-[58px] flex-col items-center justify-center gap-0.5 rounded-lg border p-1 text-center text-[10.5px] sm:min-h-[96px] sm:items-stretch sm:justify-start sm:gap-1 sm:rounded-xl sm:p-3 sm:text-left sm:text-[14px]';
  function renderCal(start, end) {
    let h = Object.values(DNAME).map((x) =>
      '<div class="pb-1 text-center text-[11px] sm:text-[12.5px] font-bold uppercase tracking-wide text-steel-500 dark:text-steel-400">' + x + '</div>').join('');
    for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
      if (d.getDay() === 0) continue;
      const k = dkey(d), info = calDays[k] || {};
      const inMonth = d.getMonth() === calMonth.getMonth(), past = k < calToday;
      const slots = info.slots || [];
      const bookedN = Object.values(info.booked || {}).reduce((a, b) => a + (+b || 0), 0);
      let tone, label, sub = '', short = '';
      if (info.closed) { tone = ' border-steel-100 bg-steel-50 text-steel-400 dark:border-white/5 dark:bg-white/5'; label = 'Closed'; short = 'Closed'; }
      else if (info.absent) { tone = ' border-danger/40 bg-danger-soft text-danger dark:bg-danger/20'; label = 'Absent'; short = 'Absent'; }
      else if (!slots.length) { tone = ' border-[#7c3aed]/50 bg-[#7c3aed]/[0.12] text-[#6d28d9] dark:bg-[#7c3aed]/25 dark:text-[#c4b5fd]'; label = 'Day off'; short = 'Off'; }
      else {
        tone = info.edited
          ? ' border-warn/60 bg-warn-soft text-navy-800 dark:bg-warn/20 dark:text-steel-50'
          : ' border-ok/50 bg-ok-soft text-navy-800 dark:bg-ok/25 dark:text-steel-50';
        label = spanText(slots);
        sub = slots.length + ' slot' + (slots.length === 1 ? '' : 's');
        short = slots.length + (slots.length === 1 ? ' slot' : ' slots');
      }
      const click = !past && !info.closed;
      const today = k === calToday ? ' ring-2 ring-accent ring-offset-1 dark:ring-offset-navy-800' : '';
      h += '<' + (click ? 'button type="button" data-day="' + k + '"' : 'div') + ' class="' + CAL_CELL + tone + today +
        (inMonth ? '' : ' opacity-40') + (past ? ' opacity-50' : '') + (click ? ' cursor-pointer hover:brightness-95' : '') + '"' +
        (click ? ' title="Set your availability on this date"' : '') + '>' +
        '<span class="flex items-center justify-center gap-1 sm:justify-between"><span class="text-[15px] font-bold sm:text-[20px]">' + d.getDate() + '</span>' +
        (info.edited && !info.closed && slots.length ? '<span class="h-1.5 w-1.5 rounded-full bg-warn sm:hidden" aria-hidden="true"></span><span class="hidden rounded bg-warn px-1.5 py-0.5 text-[10.5px] font-bold uppercase text-white sm:inline">Custom</span>' : '') + '</span>' +
        '<span class="font-semibold leading-tight sm:hidden">' + esc(short) + '</span>' +
        '<span class="hidden font-semibold leading-tight sm:inline">' + esc(label) + '</span>' +
        (sub ? '<span class="hidden text-[12.5px] leading-tight opacity-80 sm:block">' + esc(sub) + (bookedN ? ' · ' + bookedN + ' booked' : '') + '</span>' : '') +
        '</' + (click ? 'button' : 'div') + '>';
    }
    g('calGrid').innerHTML = h;
    // Header row sized to its text; the week rows share the card's height.
    const weeks = Math.round((end - start) / 864e5 + 1) / 7;
    g('calGrid').style.gridTemplateRows = 'auto repeat(' + weeks + ', minmax(0, 1fr))';
  }

  const TGL = 'flex items-center justify-between gap-2 rounded-lg border px-3 py-2.5 text-left text-[13px] font-semibold';
  const TGL_ON = ' border-ok bg-ok text-white';
  const TGL_OFF = ' border-steel-200 bg-white text-steel-500 dark:border-white/15 dark:bg-navy-900 dark:text-steel-400';
  const MODE = 'flex flex-1 cursor-pointer items-start gap-2.5 rounded-lg border px-3 py-2.5 text-[13px]';
  const MODE_ON = ' border-accent bg-accent/[0.08] dark:bg-accent/20';
  const MODE_OFF = ' border-steel-200 dark:border-white/15';

  function dayModal(k) {
    const info = calDays[k];
    if (!info) return;
    const on = new Set(info.slots.length ? info.slots : info.usual);
    let mode = info.slots.length ? 'custom' : 'off';
    const booked = info.booked || {};
    const title = new Date(k + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' });
    const modeCard = (m, t, sub) => '<label class="' + MODE + (mode === m ? MODE_ON : MODE_OFF) + '" data-mode-card="' + m + '">' +
      '<input type="radio" name="avMode" value="' + m + '" class="mt-0.5 accent-[#006cc0]"' + (mode === m ? ' checked' : '') + '>' +
      '<span><span class="block font-semibold text-navy-800 dark:text-steel-50">' + t + '</span>' +
      '<span class="block text-[11.5px] text-steel-500 dark:text-steel-400">' + sub + '</span></span></label>';
    const toggles = () => SLOTS.map((s) => {
      const isOn = on.has(s), b = booked[s] || 0;
      return '<button type="button" class="' + TGL + (isOn ? TGL_ON : TGL_OFF) + '" data-slot="' + esc(s) + '" aria-pressed="' + isOn + '">' +
        '<span>' + esc(rangeOf(s)) + '</span><span class="text-[11px] font-bold">' +
        (b ? b + ' booked' : (isOn ? 'Working' : 'Off')) + '</span></button>';
    }).join('');
    const body = () =>
      '<div class="mb-3 flex flex-col gap-2 sm:flex-row">' +
        modeCard('custom', 'Custom / Half-Day', 'Work only the hours you pick') +
        modeCard('off', 'Full Day Off', 'Sick leave, personal time, seminar') +
      '</div>' +
      (mode === 'custom'
        ? '<p class="mb-2 text-[12px] text-steel-500 dark:text-steel-400">Usual hours on this weekday: <strong class="text-navy-800 dark:text-steel-100">' +
            esc(info.usual.length ? spanText(info.usual) : 'Off') + '</strong>. Tap a time to turn it on or off.</p>' +
          '<div id="dayTgl" class="grid gap-2 sm:grid-cols-2">' + toggles() + '</div>'
        : '<p class="rounded-lg border border-steel-200 bg-steel-50 px-3 py-2.5 text-[13px] text-navy-800 dark:border-white/10 dark:bg-white/5 dark:text-steel-100">The whole date is locked. No new online bookings can be made with you that day.</p>');
    openModal(modalShell(esc(title),
      (info.absent ? '<p class="mb-2 text-[12px] font-semibold text-danger">You are marked absent on this date.</p>' : '') +
      '<div id="avBody">' + body() + '</div>',
      (info.edited ? '<button class="' + BTN_Q + ' mr-auto" id="dayReset">Reset to usual hours</button>' : '') +
      '<button class="' + BTN_Q + '" data-close>Cancel</button>' +
      '<button class="' + BTN_P + '" id="daySave">Save Changes</button>'));
    const box = g('avBody');
    const redraw = () => { box.innerHTML = body(); };
    box.addEventListener('change', (e) => {
      if (e.target.name === 'avMode') { mode = e.target.value; if (mode === 'custom' && !on.size) info.usual.forEach((s) => on.add(s)); redraw(); }
    });
    box.addEventListener('click', (e) => {
      const b = e.target.closest('[data-slot]');
      if (!b) return;
      on.has(b.dataset.slot) ? on.delete(b.dataset.slot) : on.add(b.dataset.slot);
      redraw();
    });
    const send = (bodyObj, wantSlots) => {
      const hit = SLOTS.filter((s) => !wantSlots.includes(s)).reduce((n, s) => n + (+booked[s] || 0), 0);
      const go = async () => {
        try {
          const res = await api('schedules.php?action=save_day', { method: 'POST', body: Object.assign({ date: k }, bodyObj) });
          closeModal();
          conflictToast(res.conflicts, 'Availability for ' + title + ' saved.');
          loadCal();
        } catch (e) { toast(e.message, 'err'); }
      };
      confirmAffected(hit, go);
    };
    g('daySave').addEventListener('click', () => {
      const want = mode === 'off' ? [] : SLOTS.filter((s) => on.has(s));
      if (mode === 'custom' && !want.length) return toast('Pick at least one time, or choose Full Day Off.', 'err');
      send({ slots: want }, want);
    });
    if (g('dayReset')) g('dayReset').addEventListener('click', () => send({ reset: true }, info.usual));
  }

  g('calGrid').addEventListener('click', (e) => { const c = e.target.closest('[data-day]'); if (c) dayModal(c.dataset.day); });
  g('calPrev').addEventListener('click', () => { calMonth.setMonth(calMonth.getMonth() - 1); loadCal(); });
  g('calNext').addEventListener('click', () => { calMonth.setMonth(calMonth.getMonth() + 1); loadCal(); });
  g('calThis').addEventListener('click', () => { calMonth = new Date(); calMonth.setDate(1); loadCal(); });

  loadCal();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
