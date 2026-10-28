<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['admin']);
$PAGE_TITLE = 'Dashboard';
$ACTIVE = 'dashboard';
$PAGE_FULL = true;

/* --- DGT design system (see /DESIGN.md). Elevated "bento" dashboard, unified
   grid: every operational metric card is identical in width, height and padding
   (grid-cols-1 / sm:2 / lg:4, auto-rows-fr). All cards are white — navy is an
   accent only (the metric value, the icon glyph), never a full card fill. The
   lower section is two side-by-side cards sized to their own content: Revenue
   Summary (three equal revenue tiles) and the Service Usage & Demand bar
   graph (Daily/Monthly toggle over all active services — replaced the old
   Revenue-by-Service donut, which only ever showed the top handful of
   services by revenue rather than clinic-wide demand across the catalogue).
   "Total Active Accounts" was removed from the operational row at the user's
   request, leaving Doctors on Duty / Assistants on Duty as the second row. --- */
$card   = 'rounded-2xl border border-dgt-ink-200 bg-white p-5 shadow-sm dark:border-dgt-ink-700 dark:bg-dgt-ink-800';
$cardHd = 'mb-3 flex items-center justify-between gap-3 border-b border-dgt-ink-100 pb-2.5 dark:border-dgt-ink-700';
$h2     = 'text-[17px] font-semibold text-dgt-blue-900 dark:text-dgt-ink-50';

ob_start();
?>
<style>
/* Drag-to-pan on the Daily charts (mouse only, see wireDragPan()). */
@media (hover: hover) and (pointer: fine) {
    #chartRevTrend .overflow-x-auto, #chartSvcUsage .overflow-x-auto { cursor: grab; }
}
#chartRevTrend .overflow-x-auto.is-panning, #chartSvcUsage .overflow-x-auto.is-panning { cursor: grabbing; user-select: none; }
/* The legend's "+ N more" pill stays slim on touch screens (the global 44px tap rule would make it a tall blob). */
#chartSvcUsage [data-legend-toggle] { min-height: 0 !important; min-width: 0 !important; line-height: 1.4; }
/* Restrained hover for the white metric cards — a small lift, a DGT-blue border
   and a soft shadow. On hover the icon chip also animates from the pale blue
   tint to a solid DGT-blue fill with a white glyph. prefers-reduced-motion keeps
   the colour change but drops the movement + easing. */
.dash-card { transition: transform .3s ease-out, box-shadow .3s ease-out, border-color .3s ease-out; }
.dash-card:hover,
.dash-card:focus-visible {
  transform: translateY(-4px);
  border-color: var(--chip, #006cc0);
  box-shadow: 0 20px 40px -10px rgba(16, 47, 81, .18), 0 8px 16px -8px rgba(16, 47, 81, .1);
}
html.dark .dash-card:hover,
html.dark .dash-card:focus-visible {
  box-shadow: 0 20px 40px -10px rgba(0, 0, 0, .55), 0 8px 16px -8px rgba(0, 0, 0, .4);
}
.dash-card:focus-visible { outline: none; }
/* the icon chip animates from the calm pale-blue tint to the card's own
   meaning colour (--chip, set per card): blue = the day's queue, teal = in
   progress, amber = waiting, violet = rescheduled, green/pink = staff on duty,
   navy = accounts / system. */
.dash-card .dash-chip { transition: background-color .25s ease, color .25s ease; }
.dash-card:hover .dash-chip,
.dash-card:focus-visible .dash-chip { background-color: var(--chip, #006cc0); color: #fff; }
@media (prefers-reduced-motion: reduce) {
  .dash-card, .dash-card:hover, .dash-card:focus-visible { transform: none; transition: none; }
  .dash-card .dash-chip { transition: none; }
}
/* The operational drill-down tables (Today's Schedule, Ongoing Patients,
   Doctors on Duty, …) run 6-8 columns wide with real names/dentists/services
   that shouldn't wrap — the standard `{wide:true}` modal (880px) forced a
   horizontal scrollbar even with every cell already `whitespace-nowrap`.
   Widen just these drill-down modals (same `:has()` technique used for the
   reschedule calendar / records modals) so the table fits without scrolling
   on a normal desktop instead. A flat 1040px looked fine on a wide desktop
   but visually dominated a laptop-width window (still ~75% of the viewport
   at 1366px, with almost nothing of the dashboard showing around it) — below
   ~1500px wide it steps down to 900px (close to the app's ordinary 880px
   "wide" modal size) instead; the table's own `.ops-drill-wide` wrapper still
   scrolls horizontally as a fallback on whatever's left over. */
#modalRoot > [role="dialog"]:has(.ops-drill-wide) { width: min(1040px, calc(100% - 32px)) !important; }
@media (max-width: 1500px) {
  #modalRoot > [role="dialog"]:has(.ops-drill-wide) { width: min(900px, calc(100% - 32px)) !important; }
}
</style>
<div>
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-[22px] font-semibold text-dgt-blue-900 dark:text-dgt-ink-50">Dashboard Overview</h1>
</div>

<!-- ── operational metric cards — one unified 12-column grid ──
     row 1 (4 cards): col-span-12 sm:col-span-6 lg:col-span-3   → 1 / 2 / 4 per row
     row 2 (2 cards): col-span-12 sm:col-span-6                → 1 / 2 per row
     every card sits on the same 12-col track, so gaps and outer edges always align -->
<div class="grid grid-cols-12 gap-4 auto-rows-fr" id="opsCards">
    <p class="col-span-12 text-dgt-ink-400 dark:text-dgt-ink-500">Loading&hellip;</p>
</div>

<!-- ── lower section — Revenue Summary full-width, Service Usage & Demand full-width below it (stacked, not side-by-side, so the chart gets the whole row's width) ── -->
<div class="mt-4 grid grid-cols-12 gap-4">
    <section class="<?= $card ?> col-span-12 flex flex-col">
        <div class="<?= $cardHd ?> flex-wrap gap-y-2">
            <h2 class="<?= $h2 ?>">Revenue Summary</h2>
            <div class="inline-flex rounded-lg border border-dgt-ink-200 bg-dgt-ink-50 p-0.5 dark:border-dgt-ink-700 dark:bg-dgt-ink-900" role="tablist" aria-label="Revenue trend view">
                <button type="button" id="revTrendViewDay" role="tab" aria-selected="true" class="tab rounded-md px-3 py-1.5 text-[12.5px] font-semibold text-dgt-blue-900 bg-white shadow-sm dark:bg-dgt-ink-700 dark:text-dgt-ink-50">Daily</button>
                <button type="button" id="revTrendViewMonth" role="tab" aria-selected="false" class="tab rounded-md px-3 py-1.5 text-[12.5px] font-semibold text-dgt-ink-500 dark:text-dgt-ink-400">Monthly</button>
            </div>
        </div>
        <div id="chartRevTrend" class="flex flex-1 min-h-[300px] items-center justify-center"><p class="text-dgt-ink-400 dark:text-dgt-ink-500">Loading&hellip;</p></div>
    </section>

    <section id="svcCard" class="<?= $card ?> col-span-12 flex flex-col">
        <div class="<?= $cardHd ?> flex-wrap gap-y-2">
            <h2 class="<?= $h2 ?>">Service Usage &amp; Demand</h2>
            <div class="inline-flex rounded-lg border border-dgt-ink-200 bg-dgt-ink-50 p-0.5 dark:border-dgt-ink-700 dark:bg-dgt-ink-900" role="tablist" aria-label="Service usage view">
                <button type="button" id="svcViewDay" role="tab" aria-selected="true" class="tab rounded-md px-3 py-1.5 text-[12.5px] font-semibold text-dgt-blue-900 bg-white shadow-sm dark:bg-dgt-ink-700 dark:text-dgt-ink-50">Daily</button>
                <button type="button" id="svcViewMonth" role="tab" aria-selected="false" class="tab rounded-md px-3 py-1.5 text-[12.5px] font-semibold text-dgt-ink-500 dark:text-dgt-ink-400">Monthly</button>
            </div>
        </div>
        <div id="chartSvcUsage" class="flex flex-1 min-h-[300px] items-center justify-center"><p class="text-dgt-ink-400 dark:text-dgt-ink-500">Loading&hellip;</p></div>
    </section>
</div>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);

  const DASH_ICON = {
    peso:      '<path d="M7 4h6a4 4 0 0 1 0 8H7zM7 9h10M7 13h10M7 4v16"/>',
    pesoPh:    '<path d="M20 11H4M20 7H4M7 21V4a1 1 0 0 1 1-1h4a1 1 0 0 1 0 12H7"/>',
    calendar:  '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    calendar1: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M11 14h1v4"/>',
    calDays:   '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"/>',
    clock:     '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    activity:  '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
    hourglass: '<path d="M5 3h14M5 21h14M6 3l6 8 6-8M6 21l6-8 6 8"/>',
    clockFade: '<path d="M12 2a10 10 0 0 1 7.38 16.75M12 6v6l4 2M2.5 8.875a10 10 0 0 0-.5 3M2.83 16a10 10 0 0 0 2.43 3.4M4.636 5.235a10 10 0 0 1 .891-.857M8.644 21.42a10 10 0 0 0 7.631-.38"/>',
    swap:      '<path d="M16 3h5v5M21 3l-8 8M8 21H3v-5M3 21l8-8"/>',
    clipClock: '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v.832M8 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3.5M16 14v2.2l1.6 1"/><circle cx="16" cy="16" r="6"/>',
    tooth:     '<path d="M12 5.5c-1.8-2.4-5.6-2.2-6.7.7-1 2.7.4 4.8.9 7.6.4 2.3.2 5.7 1.9 5.7 1.5 0 1.4-2.8 1.9-4.7.3-1.1.8-1.1 1.1 0 .5 1.9.4 4.7 1.9 4.7 1.7 0 1.5-3.4 1.9-5.7.5-2.8 1.9-4.9.9-7.6-1.1-2.9-4.9-3.1-6.7-.7Z"/>',
    users:     '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
  };
  const HUE = {
    blue: '#006cc0', teal: '#0086a0', amber: '#c98500',
    violet: '#4a3aa7', green: '#1baf7a', pink: '#e87ba4',
  };

  /* ---- one metric card — always white, identical box; navy is an accent only
         (the metric value and the icon glyph). No navy fill, no watermark. ---- */
  function tile(o) {
    const attr = o.drill ? ' role="button" tabindex="0" data-drill="' + o.drill + '"' : '';
    const hov  = o.drill ? ' dash-card cursor-pointer' : '';
    const span = o.span ? ' ' + o.span : '';
    const vCls = o.valueCls || 'text-[26px]';
    const mh   = o.minH || 'min-h-[124px]';
    const chip = o.accent ? ' style="--chip:' + o.accent + '"' : '';   // per-card hover colour
    const dot  = o.live
      ? '<span class="ml-1.5 inline-block h-[7px] w-[7px] animate-pulse rounded-full bg-dgt-teal-500 align-middle"></span>'
      : '';
    return '<div' + attr + chip + ' class="ops-tile sz-pad group relative flex h-full ' + mh + ' flex-col gap-3 rounded-2xl border border-dgt-ink-200 bg-white p-4 shadow-sm dark:border-dgt-ink-700 dark:bg-dgt-ink-800' + hov + span + '">'
      + '<div class="flex items-start justify-between gap-2">'
        + '<span class="sz-ico dash-chip grid shrink-0 place-items-center rounded-xl bg-dgt-blue-50 text-dgt-blue-700 [&_svg]:h-[18px] [&_svg]:w-[18px] h-9 w-9 dark:bg-dgt-blue-900/40 dark:text-dgt-blue-300">'
          + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' + DASH_ICON[o.icon] + '</svg></span>'
        + (o.pill || '')
      + '</div>'
      + '<div class="mt-auto min-w-0">'
        + '<div class="sz-num ' + vCls + ' font-bold leading-tight tracking-tight text-dgt-blue-900 [overflow-wrap:anywhere] dark:text-dgt-ink-50">' + o.value + '</div>'
        + '<div class="sz-lbl mt-1.5 text-[13px] font-medium text-dgt-ink-500 dark:text-dgt-ink-400">' + esc(o.label) + dot + '</div>'
        + (o.sub ? '<div class="sz-sub mt-1 text-[12px] text-dgt-ink-400 dark:text-dgt-ink-500">' + o.sub + '</div>' : '')
      + '</div>'
      + (o.drill ? '<span aria-hidden="true" class="pointer-events-none absolute bottom-3 right-4 text-[17px] text-dgt-ink-300 transition-colors group-hover:text-dgt-blue-500 dark:text-dgt-ink-600 dark:group-hover:text-dgt-blue-300">&rsaquo;</span>' : '')
      + '</div>';
  }

  const ratio = (a, b) => a + '<span class="text-dgt-ink-400 dark:text-dgt-ink-500"> / ' + b + '</span>';

  /* Stagger the direct children of a just-populated container in on a fade +
     small rise — the same "just entered the page" entrance language as
     charts.js's playDonutIntro(), applied to the operational cards and the
     revenue tiles. Callers decide *when* to call this — it never runs on its
     own — so a silent 30s poll refresh (the operational cards) can skip it
     and only the actual page-entry render replays it; see
     loadOps()/render() below. */
  function staggerIn(container, opts) {
    if (!container || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    opts = opts || {};
    const dur = opts.duration || 420, stagger = opts.stagger || 70, dist = opts.distance || 18;
    Array.prototype.forEach.call(container.children, (el, i) => {
      el.animate(
        [{ opacity: 0, transform: 'translateY(' + dist + 'px)' }, { opacity: 1, transform: 'translateY(0)' }],
        { duration: dur, delay: i * stagger, easing: 'cubic-bezier(.16,1,.3,1)', fill: 'backwards' }
      );
    });
  }

  const GHOST_BTN = 'inline-flex items-center gap-2 rounded-md border border-dgt-ink-200 bg-white px-4 py-2 text-[13px] font-semibold text-dgt-blue-800 hover:bg-dgt-ink-50 dark:border-dgt-ink-600 dark:bg-dgt-ink-800 dark:text-dgt-ink-100 dark:hover:bg-dgt-ink-700';
  const TABLE = 'w-full border-collapse text-[14px] '
    + '[&_th]:border-b [&_th]:border-dgt-ink-100 [&_th]:bg-dgt-ink-50 [&_th]:px-3 [&_th]:py-2 [&_th]:text-left [&_th]:text-[12px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-dgt-ink-500 [&_th]:dark:border-dgt-ink-700 [&_th]:dark:bg-dgt-ink-900 [&_th]:dark:text-dgt-ink-400 '
    + '[&_td]:border-b [&_td]:border-dgt-ink-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:dark:border-dgt-ink-700';

  /* ======================================================================
   * Section 1 — operational cards (polls dashboard.php?action=operational)
   * =================================================================== */
  function render(o, animate) {
    const pend = 'Online <b class="text-dgt-ink-600 dark:text-dgt-ink-200">' + o.pending_online + '</b> · Walk-in <b class="text-dgt-ink-600 dark:text-dgt-ink-200">' + o.pending_walkin + '</b>';
    // one 12-col grid — row 1 = 4 cards, row 2 = 2 cards, spans resolve per breakpoint.
    const R1 = 'col-span-12 sm:col-span-6 lg:col-span-3';   // 1 / 2 / 4 per row
    const R2 = 'col-span-12 sm:col-span-6';                 // 1 / 2 per row
    // hover colour keyed to what each card means:
    //   blue = the day's queue · teal = in progress · amber = waiting on the front desk
    //   violet = a changed booking · green/pink = staff on duty
    g('opsCards').innerHTML = [
      tile({ icon: 'calendar1', label: "Today's Schedule", value: o.today_total, drill: 'today_schedule', span: R1, accent: HUE.blue }),
      tile({ icon: 'activity', label: 'Ongoing Patients', value: o.ongoing, drill: 'ongoing', span: R1, accent: HUE.teal }),
      tile({ icon: 'clockFade', label: 'Pending Schedule', value: o.pending_total, sub: pend, drill: 'pending', span: R1, accent: HUE.amber }),
      tile({ icon: 'clipClock', label: 'Rescheduled', value: o.rescheduled, drill: 'rescheduled', span: R1, accent: HUE.violet }),
      tile({ icon: 'tooth', label: 'Doctors on Duty', value: ratio(o.doctors_on_duty, o.doctors_active), drill: 'doctors_on_duty', span: R2, accent: HUE.green }),
      tile({ icon: 'users', label: 'Assistant Dentists on Duty', value: ratio(o.assistants_on_duty, o.assistants_active), drill: 'assistants_on_duty', span: R2, accent: HUE.pink }),
    ].join('');
    // Only the real page-entry render animates — a silent 30s poll refresh
    // (or the post-action refreshes after marking a doctor absent / an
    // assistant on duty) would otherwise replay the fade-in every time.
    if (animate) staggerIn(g('opsCards'));
  }

  function stopOps() {
    if (window.__opsTimer) { clearInterval(window.__opsTimer); window.__opsTimer = null; }
  }
  function scheduleOps() {
    stopOps();
    // Always on (2026-10-18): the cards refresh silently every 10 s, no toggle.
    window.__opsTimer = setInterval(() => loadOps(true), 10000);
  }
  async function loadOps(silent) {
    const box = g('opsCards');
    if (!box) { stopOps(); return; }
    if (!silent) box.style.opacity = '0.5';
    try {
      const { operational: o } = await api('dashboard.php?action=operational');
      if (!g('opsCards')) { stopOps(); return; }
      render(o, !silent);
    } catch (e) {
      if (!silent) box.innerHTML = '<p class="col-span-full py-6 text-center text-danger">' + esc(e.message) + '</p>';
    } finally {
      if (g('opsCards')) g('opsCards').style.opacity = '';
    }
  }

  /* ---- drill-downs (every card opens a detail modal) ---- */
  const APPT_DRILL = {
    today_schedule: "Today's Schedule", ongoing: 'Ongoing Patients',
    pending: 'Pending Schedule', rescheduled: 'Rescheduled Appointments',
  };
  const STAFF_DRILL = {
    doctors_on_duty: 'Doctors on Duty', assistants_on_duty: 'Assistant Dentists on Duty',
  };
  const DRILL_EMPTY = {
    today_schedule: 'No appointments scheduled for today.',
    ongoing: 'No checked-in patients with an open bill.',
    pending: 'No appointment requests are waiting for approval.',
    rescheduled: 'No rescheduled appointments.',
    doctors_on_duty: 'No active dentists on staff.',
    assistants_on_duty: 'No assistant dentists currently on duty.',
  };

  function wireDrill(el) {
    if (!el) return;
    el.addEventListener('click', (e) => {
      const c = e.target.closest('[data-drill]');
      if (c) openDrill(c.dataset.drill);
    });
    el.addEventListener('keydown', (e) => {
      const c = e.target.closest('[data-drill]');
      if (c && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); c.click(); }
    });
  }

  function relTime(s) {
    if (s == null || s === '') return 'N/A';
    s = +s;
    if (s < 45) return 'just now';
    if (s < 3600) return Math.max(1, Math.round(s / 60)) + ' min ago';
    if (s < 86400) return Math.round(s / 3600) + ' hr ago';
    return Math.round(s / 86400) + ' d ago';
  }

  async function openDrill(key) {
    const isStaff = key in STAFF_DRILL;
    const startedAt = Date.now();
    openModal(modalShell(STAFF_DRILL[key] || APPT_DRILL[key] || 'Details',
      '<div id="opsDrillBody" class="dgt-modal-fade text-[13px] text-dgt-ink-500 dark:text-dgt-ink-400">' + window.modalLoadingHtml() + '</div>'), { wide: true });
    let html;
    try {
      const { rows } = await api('dashboard.php?action=' + (isStaff ? 'staff_list' : 'list') + '&key=' + key);
      html = rows.length
        ? (isStaff ? staffTable(key, rows) : apptTable(rows, key))
        : '<p class="py-6 text-center text-dgt-ink-400 dark:text-dgt-ink-500">' + esc(DRILL_EMPTY[key] || 'Nothing to show.') + '</p>';
    } catch (e) {
      html = '<p class="py-6 text-center text-danger">' + esc(e.message) + '</p>';
    }
    const body = g('opsDrillBody');
    await window.swapModalBody(body, html, startedAt);
    if (!document.contains(body)) return;
  }

  function apptTable(rows, key) {
    const showSrc = rows.some((r) => r.booking_type);
    const statusCell = (r) => window.badge(r.status);
    return '<div class="ops-drill-wide overflow-x-auto"><table class="' + TABLE + '">'
      + '<thead><tr><th>Date</th><th>Time</th><th>Patient</th>' + (showSrc ? '<th>Source</th>' : '')
      + '<th>Dentist</th><th>Service</th><th>Status</th></tr></thead><tbody>'
      + rows.map((r) =>
          '<tr data-view="' + r.appointment_id + '" class="cursor-pointer [&:hover]:bg-dgt-ink-50 dark:[&:hover]:bg-dgt-ink-700/60">'
          + '<td class="whitespace-nowrap">' + esc(window.fmtDate(String(r.scheduled_date).slice(0, 10))) + '</td>'
          + '<td class="whitespace-nowrap tabular-nums">' + esc(window.fmtTime(r.time_slot)) + '</td>'
          + '<td><strong class="text-dgt-blue-900 dark:text-dgt-ink-50">' + esc(r.patient_name) + '</strong></td>'
          + (showSrc ? '<td class="whitespace-nowrap">' + window.badgeHTML(r.booking_type === 'walk_in' ? 'Walk-In' : 'Online', r.booking_type === 'walk_in' ? 'src-walkin' : 'src-online') + '</td>' : '')
          + '<td>' + esc(r.dentist_name || 'N/A') + '</td>'
          + '<td>' + esc(r.requested_service || 'N/A') + '</td>'
          + '<td>' + statusCell(r) + '</td>'
          + '</tr>').join('')
      + '</tbody></table><p class="mt-2.5 text-[12px] text-dgt-ink-400 dark:text-dgt-ink-500">Select a row to open the full patient &amp; appointment details.</p></div>';
  }

  function staffTable(key, rows) {
    let head, cells;
    // The assistants drill is already scoped server-side to on-duty rows only
    // (see api/dashboard.php's action=staff_list, key=assistants_on_duty), so
    // rows.length IS the "Total On Duty" count — no separate query needed.
    const summary = key === 'assistants_on_duty'
      ? '<p class="mb-2.5 text-[13px] font-semibold text-dgt-blue-900 dark:text-dgt-ink-100">Total On Duty: ' + rows.length + '</p>'
      : '';
    if (key === 'doctors_on_duty') {
      head = '<th>Doctor</th><th>Type</th><th>On Duty Today</th><th>Booked Today</th><th>Completed</th><th>Waiting</th><th>Last Active</th>';
      cells = (r) => '<td class="whitespace-nowrap"><strong class="text-dgt-blue-900 dark:text-dgt-ink-50">' + esc(r.full_name) + '</strong></td>'
        + '<td class="whitespace-nowrap">' + window.badgeHTML(r.doctor_type === 'owner' ? 'In-House' : 'Roaming', r.doctor_type === 'owner' ? 'src-walkin' : 'src-online') + '</td>'
        + '<td class="whitespace-nowrap">' + (+r.absent_today
              ? window.badgeHTML('Absent today', 'absent')
              : window.badgeHTML(+r.on_duty ? 'On duty' : 'Off', +r.on_duty ? 'on' : 'off')) + '</td>'
        + '<td class="tabular-nums">' + (+r.today_count) + '</td>'
        + '<td class="tabular-nums">' + (+r.done_count) + '</td>'
        + '<td class="tabular-nums">' + (+r.pending_count) + '</td>'
        + '<td class="whitespace-nowrap text-dgt-ink-400 dark:text-dgt-ink-500">' + esc(relTime(r.seen_ago)) + '</td>';
    } else {
      // Read-only — an assistant manages their own duty status from their own
      // dashboard, so this drill no longer offers a Mark on/off duty control.
      head = '<th>Assistant</th><th>Status</th><th>Active Patients</th><th>Availability</th>';
      cells = (r) => '<td class="whitespace-nowrap"><strong class="text-dgt-blue-900 dark:text-dgt-ink-50">' + esc(r.full_name) + '</strong></td>'
        + '<td class="whitespace-nowrap">' + window.badgeHTML('On Duty', 'on') + '</td>'
        + '<td class="tabular-nums">' + (+r.active_patients) + '</td>'
        + '<td class="whitespace-nowrap">' + (+r.active_patients > 0
              ? window.badgeHTML('Busy', 'busy')
              : window.badgeHTML('Available', 'available')) + '</td>';
    }
    return summary + '<div class="ops-drill-wide overflow-x-auto"><table class="' + TABLE + '"><thead><tr>' + head + '</tr></thead><tbody>'
      + rows.map((r) => '<tr>' + cells(r) + '</tr>').join('')
      + '</tbody></table></div>';
  }


  /* ======================================================================
   * Section 2 — Revenue Trend line chart (dashboard.php?action=revenue_summary)
   * The Revenue Summary card's only content now — the three fixed
   * today/week/month tiles it used to sit under (and their own
   * action=revenue_list drill-down) were removed 2026-09 at the user's
   * request, and the chart's Daily/Monthly windows were made to match
   * Service Usage & Demand's own exactly: Daily is always the current
   * calendar month (day 1 through its real last day, no dropdown), Monthly
   * is the rolling 12 calendar months. The chart itself is sized the same
   * as Service Usage's (min-h-[300px], flex-1) so the two full-width cards
   * read as a matched pair.
   * =================================================================== */
  const BLUE  = '#006cc0';
  const EMPTY = '<p class="py-7 text-center text-dgt-ink-400 dark:text-dgt-ink-500">';
  let revTrendView = 'day';
  // Caches the last successful fetch so a resize-triggered redraw (see
  // wireChartResize() below) can re-render at the container's new width
  // without hitting the API again — a redraw is a re-layout of data already
  // in hand, not a reason to refetch it.
  let lastRevTrend = null;

  // Builds and inserts the chart from lastRevTrend at the container's
  // *current* real width (window.lineChart's own opts.containerWidth) —
  // called both on a genuine data load (animate = true, plays the draw-in
  // entrance) and on a resize/parent-resize redraw (animate = false, since
  // replaying the entrance every time the window or sidebar is resized would
  // be distracting, not delightful). wireLineChartHover() is re-wired every
  // time regardless, since each render replaces the <svg> and any listeners
  // attached to the previous one go with it.
  // Daily views cover the whole current month, so "fully right" lands on the
  // month's last day, not today: early in the month today's data sat hidden
  // off the left edge (and Revenue Summary, being the wider chart, showed even
  // fewer recent days than Service Usage). Instead, put TODAY's column near the
  // right edge with a little of the month ahead still visible; a past month
  // (no "today" column) falls back to scrolling fully right.
  function scrollDailyToToday(box, sel, attr) {
    const scroller = box.querySelector('.overflow-x-auto');
    if (!scroller) return;
    const today = todayISO();
    const el = [...box.querySelectorAll(sel)].find((n) => n.dataset[attr] === today);
    if (!el) { scroller.scrollLeft = scroller.scrollWidth; return; }
    const r = el.getBoundingClientRect(), sr = scroller.getBoundingClientRect();
    const x = r.left + r.width / 2 - sr.left + scroller.scrollLeft;   // today's x inside the scroll content
    scroller.scrollLeft = Math.max(0, x - scroller.clientWidth * 0.8);
  }

  function renderRevTrend(animate) {
    const box = g('chartRevTrend');
    if (!box || !lastRevTrend) return;
    const { points, total, isMonth } = lastRevTrend;
    // Daily (up to 31 dates for a full calendar month) renders at its
    // natural per-point width inside a horizontally-scrollable wrapper —
    // squeezing every date into the card's fixed width made the axis
    // labels overlap and become unreadable. Monthly (always 12 points)
    // fluidly expands to fill the card's real measured width instead — the
    // same natural-minimum-vs-fluid split Service Usage & Demand's own
    // groupedBarChart() uses between its Daily and Monthly views.
    // Monthly gets opts.fill: a hard guarantee of zero horizontal scrollbar
    // (W is capped to the card's own measured width, never left free to
    // exceed it) — the natural 12-point minimum can, on a narrower window or
    // with the sidebar expanded, land a few pixels wider than the real card,
    // which was popping a stray scrollbar. Daily is unaffected — a full
    // calendar month of dates is expected to scroll, and locking its scroll
    // position across a modal open/close is handled separately by
    // wireChartResize()'s body-scroll-lock guard below.
    const lineOpts = Object.assign(
      isMonth ? { color: BLUE, height: 420, money: true, fill: true } : { color: BLUE, height: 300, money: true },
      { containerWidth: box.clientWidth }
    );
    const hasChart = total > 0;
    box.innerHTML = hasChart
      ? window.lineChart(points, lineOpts)
      : EMPTY + 'No revenue recorded in this period yet.</p>';
    // Same fix as Service Usage & Demand's own Daily view (see its own
    // comment above) — Daily always covers the whole current calendar
    // month and needs real horizontal scroll, which defaulted to
    // scrollLeft:0 on every render, putting today's revenue (the
    // rightmost point) off-screen until manually scrolled into view.
    // Scroll the chart's overflow-x-auto wrapper fully right so the latest
    // date is visible by default; Monthly renders with opts.fill (no
    // scrollbar) so this is a no-op there. Deferred to the next animation
    // frame (not applied synchronously right here) — see the matching
    // comment in renderSvcUsage() for why: the two charts' independent
    // fetch/render cycles race, and setting scrollLeft synchronously right
    // after innerHTML was found (via headless-Chrome testing) to
    // intermittently get silently discarded for whichever of the two chart
    // renders happened to land first, depending purely on which of the two
    // API calls resolved first — a real, reproducible race, not a one-off
    // fluke. requestAnimationFrame runs after the browser has committed
    // layout for the current frame, so both charts' scroll assignments are
    // reliably applied regardless of load order.
    if (!isMonth) requestAnimationFrame(() => scrollDailyToToday(box, '.line-node[data-key]', 'key'));
    if (animate) window.playLineChartIntro(box);
    // Clicking a point drills into the transactions behind it, same as every
    // other clickable panel on this dashboard (a bar, a tile, a donut arc) —
    // only wired when there's an actual chart to click.
    window.wireLineChartHover(box, hasChart ? openRevTrendDrill : undefined);
  }

  async function loadRevTrend() {
    const box = g('chartRevTrend');
    if (!box) return;
    box.style.opacity = '0.45';
    const isMonth = revTrendView === 'month';
    try {
      const d = await api('dashboard.php?action=revenue_summary&view=' + (isMonth ? 'month' : 'day'));
      lastRevTrend = { points: d.points, total: d.total, isMonth };
      renderRevTrend(true);
    } catch (e) {
      lastRevTrend = null;
      box.innerHTML = EMPTY + esc(e.message) + '</p>';
    } finally {
      box.style.opacity = '';
    }
  }

  function wireRevTrendControls() {
    const dayBtn = g('revTrendViewDay'), monthBtn = g('revTrendViewMonth');
    if (!dayBtn || !monthBtn) return;

    function setView(view) {
      revTrendView = view;
      const isMonth = view === 'month';
      dayBtn.setAttribute('aria-selected', isMonth ? 'false' : 'true');
      monthBtn.setAttribute('aria-selected', isMonth ? 'true' : 'false');
      dayBtn.classList.toggle('bg-white', !isMonth);
      dayBtn.classList.toggle('shadow-sm', !isMonth);
      dayBtn.classList.toggle('dark:bg-dgt-ink-700', !isMonth);
      dayBtn.classList.toggle('text-dgt-blue-900', !isMonth);
      dayBtn.classList.toggle('dark:text-dgt-ink-50', !isMonth);
      dayBtn.classList.toggle('text-dgt-ink-500', isMonth);
      dayBtn.classList.toggle('dark:text-dgt-ink-400', isMonth);
      monthBtn.classList.toggle('bg-white', isMonth);
      monthBtn.classList.toggle('shadow-sm', isMonth);
      monthBtn.classList.toggle('dark:bg-dgt-ink-700', isMonth);
      monthBtn.classList.toggle('text-dgt-blue-900', isMonth);
      monthBtn.classList.toggle('dark:text-dgt-ink-50', isMonth);
      monthBtn.classList.toggle('text-dgt-ink-500', !isMonth);
      monthBtn.classList.toggle('dark:text-dgt-ink-400', !isMonth);
      loadRevTrend();
    }
    dayBtn.addEventListener('click', () => setView('day'));
    monthBtn.addEventListener('click', () => setView('month'));
  }

  /* ---- point-level drill-down — the transactions behind a single day/month's
     revenue figure, the Revenue Trend counterpart of Service Usage's
     openSvcUsageDrill(). Wired via window.wireLineChartHover()'s onPick
     param in renderRevTrend() above (a click, or Enter/Space on a tabbed-to
     point, resolves to the point's own `key` and lands here).
     **The Date/Time columns show `revenue_date`/`revenue_time` — the
     revenue-recognition date/time the API filtered on — never the
     appointment's own `scheduled_date`/`time_slot`.** A visit can be
     scheduled one day and its bill settled another (an installment paid off
     later, a same-day visit billed after midnight); showing scheduled_date
     here was a real bug — a transaction correctly counted under the Sep 21
     bucket displayed "Sep 20" (its visit date) instead, reading as if the
     modal had pulled the wrong day's transactions when the underlying query
     was already exactly right. `revenue_time` is `null` (rendered "N/A") for a
     row on record only via the date-only `payment_date` fallback column. */
  function revenueTable(rows) {
    return '<div class="overflow-x-auto"><table class="' + TABLE + '">'
      + '<thead><tr><th>Date</th><th>Time</th><th>Patient</th><th>Dentist</th><th>Service</th><th class="text-right">Amount</th></tr></thead><tbody>'
      + rows.map((r) =>
          '<tr data-view="' + r.appointment_id + '" class="cursor-pointer [&:hover]:bg-dgt-ink-50 dark:[&:hover]:bg-dgt-ink-700/60">'
          + '<td class="whitespace-nowrap">' + esc(window.fmtDate(String(r.revenue_date).slice(0, 10))) + '</td>'
          + '<td class="whitespace-nowrap tabular-nums">' + esc(r.revenue_time || 'N/A') + '</td>'
          // Name / dentist / service wrap so the Amount column always fits the pop-up.
          + '<td><strong class="text-dgt-blue-900 dark:text-dgt-ink-50">' + esc(r.patient_name) + '</strong></td>'
          + '<td>' + esc(r.dentist_name || 'N/A') + '</td>'
          + '<td>' + (r.kind === 'reservation' ? 'Reservation fee: ' : '') + esc(r.requested_service || 'N/A') + '</td>'
          + '<td class="whitespace-nowrap text-right tabular-nums font-semibold text-dgt-blue-900 dark:text-dgt-ink-50">' + esc(window.peso(r.amount)) + '</td>'
          + '</tr>').join('')
      + '</tbody></table><p class="mt-2.5 text-[12px] text-dgt-ink-400 dark:text-dgt-ink-500">Select a row to open the full patient &amp; appointment details.</p></div>';
  }

  async function openRevTrendDrill(key) {
    const pt = lastRevTrend && lastRevTrend.points.find((p) => String(p.key) === String(key));
    const title = 'Revenue: ' + (pt ? (pt.tip_label || pt.label) : key);
    const startedAt = Date.now();
    openModal(modalShell(title,
      '<div id="revTrendDrillBody" class="dgt-modal-fade text-[13px] text-dgt-ink-500 dark:text-dgt-ink-400">' + window.modalLoadingHtml() + '</div>'), { wide: true });
    let html;
    try {
      const d = await api('dashboard.php?action=revenue_detail&bucket=' + encodeURIComponent(key)
        + '&view=' + (revTrendView === 'month' ? 'month' : 'day'));
      html = d.rows.length
        ? revenueTable(d.rows)
        : '<p class="py-6 text-center text-dgt-ink-400 dark:text-dgt-ink-500">No revenue recorded for this period.</p>';
    } catch (e) {
      html = '<p class="py-6 text-center text-danger">' + esc(e.message) + '</p>';
    }
    await window.swapModalBody(g('revTrendDrillBody'), html, startedAt);
  }

  /* ======================================================================
   * Section 3 — Service Usage & Demand (dashboard.php?action=service_usage)
   * Replaces the old Revenue-by-Service donut: a bar per active service,
   * toggled between a single day's patient volume and a whole month's
   * total, so the clinic owner can spot peak days and high-demand services
   * across the full catalogue rather than just the top few by revenue.
   * =================================================================== */
  let svcUsageView  = 'day';
  // Remembers the last-rendered categories/series labels so a bar click can
  // build a modal title synchronously (no round-trip needed just to know
  // which service/date was clicked) — refreshed on every loadSvcUsage() call.
  let svcUsageCategories = [];
  let svcUsageSeriesMeta = [];

  // The shared app-wide CHART_PALETTE (charts.js) is validated for colour-blind
  // separation but only carries 8 hues, one short of the clinic's fixed ~10-
  // service catalogue — sharing a colour between two services in the same
  // clustered chart would make them indistinguishable. Extend it locally (this
  // chart only, not the shared palette every other chart on the site draws
  // from) with two further hues picked to sit in the gaps of the existing hue
  // wheel: a cyan between the palette's blue and green, and a lighter
  // slate-grey for the 10th (Restorative Dentistry, last alphabetically).
  // The slate started out much darker (#475569) — the user flagged it as
  // hard to see in dark mode, where it nearly disappeared into the dark
  // navy card background; lightened to this slate-400 step, which stays
  // legible on both the white light-mode card and the dark navy dark-mode one.
  const SVC_COLORS = window.CHART_PALETTE.concat(['#0aa3b5', '#94a3b8']);
  // Same redraw-from-cache pattern as lastRevTrend/renderRevTrend above — a
  // resize/parent-resize redraw re-lays out data already in hand rather than
  // refetching it.
  let lastSvcUsage = null;
  let svcLegendAll = false;   // legend shows the top 5 until "+ N more" is clicked

  function renderSvcUsage(animate) {
    const box = g('chartSvcUsage');
    if (!box || !lastSvcUsage) return;
    const { categories, series, totalPatients, isMonth, rangeLabel } = lastSvcUsage;
    // Monthly fluidly expands to fill the card's real measured width (plus a
    // taller 420px canvas) — this churned back and forth the same day
    // (tried, reverted as "too stretch", reverted again back to plain, then
    // explicitly asked for again: "make it back where the months are
    // stretch") and this is where it settled: filling the card. Daily (up to
    // 31 categories for a full calendar month) keeps its natural minimum
    // width and only expands past it when the container genuinely has more
    // room than that — it still needs real horizontal scroll to stay legible
    // at that category count on an ordinary desktop.
    const barOpts = Object.assign(
      { unit: 'Patient', barWidth: 6, gapBar: 1, gapGroup: 12, height: isMonth ? 420 : 300 },
      // Both views: cap each date/month's cluster to its own top 5 busiest
      // services (of the ~10-service catalogue) instead of always drawing
      // all of them, most at zero height — a click on the date/month label
      // still opens the full per-service breakdown (including the ones that
      // didn't make that period's top 5), so no information is actually
      // lost, just decluttered on the chart itself.
      { topPerCategory: 5, categoryClick: true },
      // Legend: only the 5 busiest services of this window, with a toggle
      // to list all of them (the catalogue is ~21 services).
      { legendTop: 5, legendExpanded: svcLegendAll },
      { containerWidth: box.clientWidth }
    );
    box.innerHTML = totalPatients > 0
      ? window.groupedBarChart(categories, series, barOpts)
      : EMPTY + 'No completed visits recorded in ' + (isMonth ? 'this 12-month window' : (rangeLabel || 'this month')) + ' yet.</p>';
    // Daily always covers the whole current calendar month, oldest day
    // first, and needs real horizontal scroll to stay legible — left at its
    // default scrollLeft:0 that put today's data (the rightmost category)
    // off-screen on every load, requiring a manual scroll just to see
    // current numbers. Scrolling the chart's own overflow-x-auto wrapper
    // fully right on every render shows the latest date by default instead;
    // Monthly renders with opts.fill (no scrollbar at all) so this is a
    // no-op there. Deferred to the next animation frame, not applied here
    // synchronously — this chart's load races Revenue Summary's independent
    // load (both fire at page-script bottom with no ordering guarantee), and
    // headless-Chrome testing confirmed a real, reproducible bug: whichever
    // of the two charts' fetches resolved and rendered first would
    // intermittently have its synchronous scrollLeft assignment silently
    // discarded (landing back at 0) depending purely on load order.
    // requestAnimationFrame runs after the browser has committed layout for
    // the frame, which reliably avoids the race for both charts.
    if (!isMonth) requestAnimationFrame(() => scrollDailyToToday(box, '[data-bucket]', 'bucket'));
    // The bars themselves draw in (grow up from the baseline, grouped by
    // date/month, legend fading in after) via charts.js's
    // window.playGroupedBarChartIntro() — the bar-chart equivalent of
    // playDonutIntro(). Skipped on a resize-triggered redraw (animate =
    // false) so resizing the window doesn't replay the entrance. A no-op on
    // the empty-state branch either way (no .grouped-bar elements to select).
    if (animate) window.playGroupedBarChartIntro(box);
  }

  async function loadSvcUsage() {
    const box = g('chartSvcUsage');
    if (!box) return;
    box.style.opacity = '0.45';
    const isMonth = svcUsageView === 'month';
    try {
      // Daily has no picker — it always means "the current calendar month
      // so far" (server-resolved from the DB clock); a picker was tried and
      // removed the same day at the user's request.
      const d = await api('dashboard.php?action=service_usage&view=' + (isMonth ? 'month' : 'day'));
      const series = d.series.map((s, i) => ({ label: s.label, data: s.data, color: SVC_COLORS[i % SVC_COLORS.length], service_id: s.service_id }));
      svcUsageCategories = d.categories;
      svcUsageSeriesMeta = series.map((s) => ({ service_id: s.service_id, label: s.label }));
      lastSvcUsage = { categories: d.categories, series, totalPatients: d.total_patients, isMonth, rangeLabel: d.range_label };
      renderSvcUsage(true);
    } catch (e) {
      lastSvcUsage = null;
      box.innerHTML = EMPTY + esc(e.message) + '</p>';
    } finally {
      box.style.opacity = '';
    }
  }

  function wireSvcUsageControls() {
    const dayBtn = g('svcViewDay'), monthBtn = g('svcViewMonth');
    if (!dayBtn || !monthBtn) return;

    function setView(view) {
      svcUsageView = view;
      const isMonth = view === 'month';
      dayBtn.setAttribute('aria-selected', isMonth ? 'false' : 'true');
      monthBtn.setAttribute('aria-selected', isMonth ? 'true' : 'false');
      dayBtn.classList.toggle('bg-white', !isMonth);
      dayBtn.classList.toggle('shadow-sm', !isMonth);
      dayBtn.classList.toggle('dark:bg-dgt-ink-700', !isMonth);
      dayBtn.classList.toggle('text-dgt-blue-900', !isMonth);
      dayBtn.classList.toggle('dark:text-dgt-ink-50', !isMonth);
      dayBtn.classList.toggle('text-dgt-ink-500', isMonth);
      dayBtn.classList.toggle('dark:text-dgt-ink-400', isMonth);
      monthBtn.classList.toggle('bg-white', isMonth);
      monthBtn.classList.toggle('shadow-sm', isMonth);
      monthBtn.classList.toggle('dark:bg-dgt-ink-700', isMonth);
      monthBtn.classList.toggle('text-dgt-blue-900', isMonth);
      monthBtn.classList.toggle('dark:text-dgt-ink-50', isMonth);
      monthBtn.classList.toggle('text-dgt-ink-500', !isMonth);
      monthBtn.classList.toggle('dark:text-dgt-ink-400', !isMonth);
      loadSvcUsage();
    }
    dayBtn.addEventListener('click', () => setView('day'));
    monthBtn.addEventListener('click', () => setView('month'));
  }

  /* ---- bar-level drill-down — makes the chart clickable like every other
     panel on this dashboard, since the card itself can't be (it hosts the
     Daily/Monthly toggle). Each bar already carries data-service-id/data-
     bucket (added in charts.js's groupedBarChart()); clicking or Enter/Space-
     activating one opens the completed visits behind that single count. ---- */
  function svcUsageTable(rows) {
    return '<div class="overflow-x-auto"><table class="' + TABLE + '">'
      + '<thead><tr><th>Date</th><th>Time</th><th>Patient</th><th>Dentist</th></tr></thead><tbody>'
      + rows.map((r) =>
          '<tr data-view="' + r.appointment_id + '" class="cursor-pointer [&:hover]:bg-dgt-ink-50 dark:[&:hover]:bg-dgt-ink-700/60">'
          + '<td class="whitespace-nowrap">' + esc(window.fmtDate(String(r.scheduled_date).slice(0, 10))) + '</td>'
          + '<td class="whitespace-nowrap tabular-nums">' + esc(window.fmtTime(r.time_slot)) + '</td>'
          + '<td><strong class="text-dgt-blue-900 dark:text-dgt-ink-50">' + esc(r.patient_name) + '</strong></td>'
          + '<td>' + esc(r.dentist_name || 'N/A') + '</td>'
          + '</tr>').join('')
      + '</tbody></table><p class="mt-2.5 text-[12px] text-dgt-ink-400 dark:text-dgt-ink-500">Select a row to open the full patient &amp; appointment details.</p></div>';
  }

  // Restyles the just-pushed pane's head (app.js's addBackButton() puts the
  // shared "← Back" button and the pane's own title on one row, back-left /
  // title-centered — the standard layout every other stacked drill-down in
  // this app uses) so the title instead sits on its own row directly under
  // the Back button, at the user's request for this one drill-down. Only
  // this pane's head is touched via direct element refs (grid-row/grid-
  // column inline styles) — the shared addBackButton()/`.modal-back` styling
  // itself, and every other stacked modal that uses it, is left unchanged.
  function moveDrillTitleBelowBack() {
    const track = document.querySelector('#modalRoot .modal-track');
    const panes = track && track.querySelectorAll(':scope > .modal-pane');
    const pane = panes && panes[panes.length - 1];
    const head = pane && pane.querySelector('.modal-head');
    const title = head && head.querySelector('h3');
    const back = head && head.querySelector('.modal-back');
    if (!head || !title || !back) return;
    const close = head.querySelector('[data-close]');
    head.style.gridTemplateRows = 'auto auto';
    back.style.gridColumn = '1';
    back.style.gridRow = '1';
    if (close) { close.style.gridColumn = '3'; close.style.gridRow = '1'; }
    title.style.gridColumn = '1 / -1';
    title.style.gridRow = '2';
    title.style.marginTop = '.125rem';
    title.classList.remove('text-center');
    title.classList.add('text-left');
  }

  // `stack` is only true when reached from the "Service Usage: <date>" full-
  // breakdown list (never a direct chart-bar click) — openModal's own shared
  // pane-stack system (the same one every other dashboard drill-down-on-a-
  // drill-down uses) then slides this pane in over it with a real "← Back"
  // button (window.modalBack()) that slides straight back to that exact
  // breakdown pane, still scrolled/rendered as it was — no re-fetch, no
  // custom back-button wiring needed here.
  async function openSvcUsageDrill(serviceId, bucket, stack) {
    const svc = svcUsageSeriesMeta.find((s) => String(s.service_id) === String(serviceId));
    const cat = svcUsageCategories.find((c) => c.key === bucket);
    const title = esc((svc ? svc.label : 'Service') + ': ' + (cat ? cat.label : bucket));
    const startedAt = Date.now();
    openModal(modalShell(title,
      '<div id="svcUsageDrillBody" class="dgt-modal-fade text-[13px] text-dgt-ink-500 dark:text-dgt-ink-400">' + window.modalLoadingHtml() + '</div>'), { wide: true, stack: !!stack });
    if (stack) moveDrillTitleBelowBack();
    let html;
    try {
      const d = await api('dashboard.php?action=service_usage_detail&service_id=' + encodeURIComponent(serviceId)
        + '&bucket=' + encodeURIComponent(bucket) + '&view=' + (svcUsageView === 'month' ? 'month' : 'day'));
      html = d.rows.length
        ? svcUsageTable(d.rows)
        : '<p class="py-6 text-center text-dgt-ink-400 dark:text-dgt-ink-500">No completed visits for this service in this period.</p>';
    } catch (e) {
      html = '<p class="py-6 text-center text-danger">' + esc(e.message) + '</p>';
    }
    await window.swapModalBody(g('svcUsageDrillBody'), html, startedAt);
  }

  /* ---- date-level breakdown — Daily's bars only ever show a date's top 5
     services (see barOpts.topPerCategory above); clicking the date's own
     axis label (charts.js's `.grp-cat-label`, opts.categoryClick) instead
     opens every service's count for that one date, including the ones that
     didn't make the top 5 — a genuine 0, not a bar that just isn't shown.
     Built entirely from the already-fetched lastSvcUsage.series, no extra
     API call, since that response always carries every service's full data
     array regardless of what the chart itself chose to draw. ---- */
  function svcUsageBreakdownTable(rows) {
    return '<div class="overflow-x-auto"><table class="' + TABLE + '">'
      + '<thead><tr><th>Service</th><th class="text-right">Patients</th></tr></thead><tbody>'
      + rows.map((r) =>
          '<tr' + (r.count > 0 ? ' data-service-id="' + r.service_id + '" class="cursor-pointer [&:hover]:bg-dgt-ink-50 dark:[&:hover]:bg-dgt-ink-700/60"' : ' class="opacity-50"') + '>'
          + '<td><span class="mr-2 inline-block h-2.5 w-2.5 rounded-full align-middle" style="background:' + esc(r.color) + '"></span>' + esc(r.label) + '</td>'
          + '<td class="text-right tabular-nums">' + r.count + '</td>'
          + '</tr>').join('')
      + '</tbody></table></div>';
  }

  function openSvcUsageDayBreakdown(bucket) {
    if (!lastSvcUsage) return;
    const ci = svcUsageCategories.findIndex((c) => c.key === bucket);
    const cat = svcUsageCategories[ci];
    const rows = lastSvcUsage.series
      .map((s) => ({ service_id: s.service_id, label: s.label, color: s.color, count: ci === -1 ? 0 : (+(s.data && s.data[ci]) || 0) }))
      .sort((a, b) => b.count - a.count);
    const title = 'Service Usage: ' + (cat ? cat.label : bucket);
    openModal(modalShell(title,
      svcUsageBreakdownTable(rows) +
      '<p class="mt-2.5 text-[12px] text-dgt-ink-400 dark:text-dgt-ink-500">The chart only draws the top 5 services for this period. Every service is listed here, including any with zero visits. Select one with visits to see the patients behind it.</p>'));
    const dlg = document.querySelector('#modalRoot [role="dialog"]');
    if (dlg) {
      dlg.addEventListener('click', (e) => {
        const row = e.target.closest('tr[data-service-id]');
        if (row) openSvcUsageDrill(row.dataset.serviceId, bucket, true);
      });
    }
  }

  function wireSvcUsageBarClicks() {
    const box = g('chartSvcUsage');
    if (!box) return;
    box.addEventListener('click', (e) => {
      if (e.target.closest('[data-legend-toggle]')) {
        svcLegendAll = !svcLegendAll;
        renderSvcUsage(false);
        const t = box.querySelector('[data-legend-toggle]');
        if (t) t.focus();
        return;
      }
      const bar = e.target.closest('.grouped-bar');
      if (bar && bar.dataset.serviceId && bar.dataset.bucket) return openSvcUsageDrill(bar.dataset.serviceId, bar.dataset.bucket);
      const catLbl = e.target.closest('.grp-cat-label');
      if (catLbl && catLbl.dataset.bucket) openSvcUsageDayBreakdown(catLbl.dataset.bucket);
    });
    box.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      const bar = e.target.closest('.grouped-bar');
      if (bar && bar.dataset.serviceId && bar.dataset.bucket) { e.preventDefault(); return openSvcUsageDrill(bar.dataset.serviceId, bar.dataset.bucket); }
      const catLbl = e.target.closest('.grp-cat-label');
      if (catLbl && catLbl.dataset.bucket) { e.preventDefault(); openSvcUsageDayBreakdown(catLbl.dataset.bucket); }
    });
  }

  /* ---- responsive redraw: both charts re-lay out (not refetch) at the
     card's real current width whenever it changes — a browser/window resize,
     or the sidebar collapse toggle shrinking/growing #appShellContent with no
     window-resize event of its own. A ResizeObserver on each chart's own
     container catches both cases in one mechanism (the container's box
     changes either way). Debounced so a drag-resize doesn't re-render on
     every intermediate frame; stored on `window` (same convention as
     __opsTimer above) so nav.js recreating this whole script on every
     navigation disconnects the old observer instead of leaking a second one
     watching now-detached elements.
     Only a genuine *width* change triggers a redraw — a ResizeObserver fires
     on any box-size change, including the box's own *height* growing once
     the chart itself is inserted (it starts at min-h-[300px] showing "Loading…",
     then grows taller to fit the real SVG/legend). Without this guard, that
     self-inflicted height change fired the observer again ~150ms after every
     genuine load, and the debounced callback silently replaced the just-drawn
     (animated) chart with a byte-for-byte identical, non-animated one — the
     entrance animation never got a chance to be seen. `widths` is seeded with
     each box's own width *before* observe() is called, so the very first
     (always-fires-once) observation callback also compares equal and is
     correctly treated as a no-op rather than a false "resize".
     A second, similar self-inflicted noise source: `openModal()`/`closeModal()`
     (app.js) toggle `document.body.style.overflow` to lock/restore page
     scroll while a modal is open — which hides/reveals the browser's own
     vertical scrollbar and shifts every full-width element's real pixel
     width by that scrollbar's ~15-17px, these chart containers included. That
     is page-chrome noise, not a resize the charts should react to — reacting
     to it rebuilt the chart mid-click (a user clicking a point to open the
     drill-down modal saw Daily's chart snap its horizontal scroll back to day
     1, and Monthly could briefly render at a mismatched width and show a
     stray horizontal scrollbar) — reported directly ("when i click on daily
     and goes back on sep 1... for monthly a horizontal bar will pop up").
     Skipped entirely while `document.body.style.overflow === 'hidden'` (a
     modal is open) — the container's real width is exactly what it was
     before the modal opened once it closes again, so no catch-up redraw is
     needed either; the `widths` map is left untouched during that window so
     the close-triggered width-restore compares equal to what was already
     on record and is correctly treated as a no-op too. ---- */
  function stopChartResize() {
    if (window.__dashChartResizeObserver) { window.__dashChartResizeObserver.disconnect(); window.__dashChartResizeObserver = null; }
  }
  function wireChartResize() {
    stopChartResize();
    if (!window.ResizeObserver) return;
    let pending = null;
    const widths = new WeakMap();
    const ro = new ResizeObserver((entries) => {
      if (document.body.style.overflow === 'hidden') return;   // a modal is open — see comment above
      let widthChanged = false;
      entries.forEach((entry) => {
        const w = Math.round(entry.contentRect.width);
        if (widths.get(entry.target) !== w) { widths.set(entry.target, w); widthChanged = true; }
      });
      if (!widthChanged) return;
      clearTimeout(pending);
      pending = setTimeout(() => { renderRevTrend(false); renderSvcUsage(false); }, 120);
    });
    const revBox = g('chartRevTrend'), svcBox = g('chartSvcUsage');
    if (revBox) { widths.set(revBox, revBox.clientWidth); ro.observe(revBox); }
    if (svcBox) { widths.set(svcBox, svcBox.clientWidth); ro.observe(svcBox); }
    window.__dashChartResizeObserver = ro;
  }

  /* ---- wire up (safe to re-run on every nav.js re-entry) ---- */
  stopOps();
  wireDrill(g('opsCards'));
  wireRevTrendControls();
  wireSvcUsageControls();
  // Desktop drag-to-pan (2026-10): on an iPad the Daily charts scroll with a
  // finger swipe; with a mouse you can now click-and-drag the chart left/right
  // the same way instead of reaching for the scrollbar. Mouse only (touch keeps
  // its native swipe). Wired once on the persistent chart boxes and resolved
  // per event, so each re-render's fresh scroll wrapper is picked up. A drag
  // that actually moved swallows the click that follows, so letting go never
  // opens a drill-down by accident.
  function wireDragPan(box) {
    if (!box) return;
    let drag = null, moved = false;
    box.addEventListener('pointerdown', (e) => {
      if (e.pointerType !== 'mouse' || e.button !== 0) return;
      const sc = e.target.closest('.overflow-x-auto');
      if (!sc || sc.scrollWidth <= sc.clientWidth + 1) return;
      drag = { sc, id: e.pointerId, x: e.clientX, left: sc.scrollLeft };
      moved = false;
    });
    box.addEventListener('pointermove', (e) => {
      if (!drag || e.pointerId !== drag.id) return;
      const dx = e.clientX - drag.x;
      if (!moved && Math.abs(dx) < 5) return;
      if (!moved) {
        moved = true;
        try { drag.sc.setPointerCapture(drag.id); } catch (_) {}
        drag.sc.classList.add('is-panning');
      }
      drag.sc.scrollLeft = drag.left - dx / (window.uiZoom || 1);   // page zoom (tailwind.php)
    });
    const end = (e) => {
      if (!drag || e.pointerId !== drag.id) return;
      drag.sc.classList.remove('is-panning');
      drag = null;
      if (moved) setTimeout(() => { moved = false; }, 0);
    };
    box.addEventListener('pointerup', end);
    box.addEventListener('pointercancel', end);
    box.addEventListener('lostpointercapture', end);
    box.addEventListener('click', (e) => { if (moved) { e.stopPropagation(); e.preventDefault(); } }, true);
  }
  wireDragPan(g('chartRevTrend'));
  wireDragPan(g('chartSvcUsage'));

  wireSvcUsageBarClicks();
  wireChartResize();
  loadOps(false).then(scheduleOps);
  loadRevTrend();
  loadSvcUsage();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
