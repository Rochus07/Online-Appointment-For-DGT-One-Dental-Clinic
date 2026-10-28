<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['patient']);
// Patient Information Record first (2026-10-20): only My Profile until it is answered.
if (!healthFormDone((int) $me['user_id'])) {
    header('Location: /dgt_dental_clinic/modules/patient/health_form');
    exit;
}
$PAGE_TITLE = 'Treatment & Transaction History';
$ACTIVE = 'billing';
$PAGE_FULL = true;   // full-bleed content column (matches the admin screens)

// Compact on phones (2-up grid, Outstanding spans the row), full size from sm:.
$metricBase = 'relative flex min-w-0 flex-col gap-1 rounded-[10px] border border-steel-100 border-l-4 px-3 py-2.5 shadow-sm sm:gap-1.5 sm:p-4 dark:border-white/10';
$icoBase    = 'absolute right-3.5 top-3.5 hidden h-[34px] w-[34px] place-items-center rounded-[9px] sm:grid [&_svg]:h-[18px] [&_svg]:w-[18px]';
$mLabel     = 'text-[10px] uppercase tracking-[0.6px] text-steel-500 sm:text-[11px] dark:text-steel-400';
$mValue     = 'truncate text-lg font-bold tabular-nums sm:text-2xl';
$tableCls   = 'w-full border-collapse text-[13px] '
    . "[&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 "
    . '[&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:dark:border-white/10';

ob_start();
?>
<div class="mb-[22px] flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">Treatment &amp; Transaction History</h1></div>
</div>

<div class="mb-[18px] grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-4">
    <div class="<?= $metricBase ?> border-l-accent bg-accent-soft dark:bg-accent/20">
        <span class="<?= $icoBase ?> bg-accent/[0.16] text-accent-dark dark:bg-accent/25"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 15h.01M12 15h.01M16 15h.01"/></svg></span>
        <span class="<?= $mLabel ?>">Total Visits</span>
        <span class="<?= $mValue ?> text-accent-dark dark:text-accent" id="st_visits">N/A</span>
    </div>
    <div class="<?= $metricBase ?> border-l-ok bg-ok-soft dark:bg-ok/20">
        <span class="<?= $icoBase ?> bg-ok/[0.16] text-ok dark:bg-ok/25"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
        <span class="<?= $mLabel ?>">Total Billed</span>
        <span class="<?= $mValue ?> text-ok" id="st_billed">N/A</span>
    </div>
    <div class="<?= $metricBase ?> col-span-2 border-l-warn bg-warn-soft sm:col-span-1 dark:bg-warn/20">
        <span class="<?= $icoBase ?> bg-warn/[0.16] text-warn dark:bg-warn/25"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16h.01"/></svg></span>
        <span class="<?= $mLabel ?>">Outstanding</span>
        <span class="<?= $mValue ?> text-warn" id="st_out">N/A</span>
    </div>
</div>

<div id="balBanner"></div>

<!-- Date picker: the list starts on the current month; pick a year / month /
     day (or "All") to see older visits. -->
<div class="mb-3.5 flex flex-wrap items-center gap-2 rounded-[10px] border border-steel-100 bg-white px-3.5 py-3 shadow-sm dark:border-white/10 dark:bg-navy-800">
    <span class="mr-1 text-xs font-semibold uppercase tracking-wide text-steel-500 dark:text-steel-400">Show visits from</span>
    <select id="hf_year" aria-label="Year" class="rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50"></select>
    <select id="hf_month" aria-label="Month" class="rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50"></select>
    <select id="hf_day" aria-label="Day" class="rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50"></select>
    <button type="button" id="hf_now" class="inline-flex cursor-pointer items-center justify-center rounded-md border border-steel-200 bg-transparent px-2.5 py-2 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5">This Month</button>
    <span id="hf_count" class="text-xs text-steel-400 dark:text-steel-500"></span>
</div>

<!-- Phones + tablets: one card per visit (no sideways scroll). Desktop (xl, 1280+): the table. -->
<div id="historyCards" class="grid gap-3 md:grid-cols-2 xl:hidden">
    <div class="rounded-[10px] border border-steel-100 bg-white p-4 text-center text-[13px] text-steel-400 shadow-sm dark:border-white/10 dark:bg-navy-800 dark:text-steel-500 md:col-span-2">Loading&hellip;</div>
</div>

<div class="hidden rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm xl:block dark:border-white/10 dark:bg-navy-800">
    <div class="overflow-x-auto">
        <table class="<?= $tableCls ?>" id="historyTable">
            <thead><tr><th>Date</th><th>Patient</th><th>Service</th><th>Total</th><th>Balance</th><th>Status</th><th></th></tr></thead>
            <tbody><tr><td colspan="7" class="py-7 text-center text-steel-400 dark:text-steel-500">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
</div>
<!-- 10 visits per page, under the table / cards (2026-10-17). -->
<nav id="histPager" class="mt-5 flex flex-wrap items-center justify-between gap-3" aria-label="Visit pages" hidden></nav>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);

  const BADGE   = 'inline-block rounded-full px-2.5 py-[3px] text-[11px] font-bold capitalize';
  const BTN_SM  = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold';
  const PRIMARY = ' bg-accent text-white hover:bg-accent-dark';
  const OK      = ' bg-ok text-white hover:brightness-95';
  const EMPTY   = 'py-7 text-center text-steel-400 dark:text-steel-500';

  function statusBadge(ps) {
    if (ps === 'fully_paid')     return '<span class="' + BADGE + ' bg-ok-soft text-ok dark:bg-ok/20">Fully Paid</span>';
    if (ps === 'partially_paid') return '<span class="' + BADGE + ' bg-warn-soft text-warn dark:bg-warn/20">Partial</span>';
    return '<span class="' + BADGE + ' bg-danger-soft text-danger dark:bg-danger/20">Unpaid</span>';
  }

  // appointments.notes = the patient's booking concern, then (once the dentist
  // logs a procedure) their notes appended as a "Treatment notes: ..." line.
  // Pull just the dentist's part out for the Service cell; the full text stays
  // in the shared View modal.
  function dentistNotes(raw) {
    const s = String(raw || '');
    const i = s.search(/treatment notes:/i);
    return i >= 0 ? s.slice(i).replace(/^treatment notes:\s*/i, '').trim() : '';
  }

  const CARD = 'rounded-[10px] border border-steel-100 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-navy-800';
  const cardMsg = (msg) => '<div class="' + CARD + ' text-center text-[13px] text-steel-400 dark:text-steel-500 md:col-span-2">' + msg + '</div>';

  /* ---------- Year / Month / Day picker ---------- */
  const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  const pad = (n) => String(n).padStart(2, '0');
  const NOW = todayISO();   // YYYY-MM-DD, local clock
  let ALL = [];
  function fillYears() {
    const sel = g('hf_year'), keep = sel.value || NOW.slice(0, 4);
    const years = [...new Set([NOW.slice(0, 4)].concat(ALL.map((a) => String(a.scheduled_date).slice(0, 4))))].sort().reverse();
    sel.innerHTML = '<option value="">All years</option>' + years.map((y) => '<option value="' + y + '">' + y + '</option>').join('');
    sel.value = years.includes(keep) || keep === '' ? keep : NOW.slice(0, 4);
  }
  function fillMonths(keep) {
    const sel = g('hf_month');
    sel.innerHTML = '<option value="">All months</option>' + MONTHS.map((m, i) => '<option value="' + pad(i + 1) + '">' + m + '</option>').join('');
    sel.disabled = !g('hf_year').value;
    sel.value = sel.disabled ? '' : (keep || '');
  }
  function fillDays(keep) {
    const sel = g('hf_day'), y = +g('hf_year').value, m = +g('hf_month').value;
    const n = y && m ? new Date(y, m, 0).getDate() : 0;
    let html = '<option value="">All days</option>';
    for (let d = 1; d <= n; d++) html += '<option value="' + pad(d) + '">' + d + '</option>';
    sel.innerHTML = html;
    sel.disabled = !n;
    sel.value = n && keep && +keep <= n ? keep : '';
  }
  function filterLabel() {
    const y = g('hf_year').value, m = g('hf_month').value, d = g('hf_day').value;
    if (!y) return 'all dates';
    if (!m) return y;
    return (d ? MONTHS[+m - 1] + ' ' + (+d) + ', ' : MONTHS[+m - 1] + ' ') + y;
  }
  function inFilter(a) {
    const k = String(a.scheduled_date).slice(0, 10);
    const y = g('hf_year').value, m = g('hf_month').value, d = g('hf_day').value;
    return (!y || k.slice(0, 4) === y) && (!m || k.slice(5, 7) === m) && (!d || k.slice(8, 10) === d);
  }
  function setThisMonth() {
    fillYears(); g('hf_year').value = NOW.slice(0, 4);
    fillMonths(NOW.slice(5, 7)); fillDays('');
  }
  let PAGE = 1;
  const PER_PAGE = 10;
  g('hf_year').addEventListener('change', () => { PAGE = 1; fillMonths(g('hf_month').value); fillDays(g('hf_day').value); renderList(); });
  g('hf_month').addEventListener('change', () => { PAGE = 1; fillDays(g('hf_day').value); renderList(); });
  g('hf_day').addEventListener('change', () => { PAGE = 1; renderList(); });
  g('hf_now').addEventListener('click', () => { PAGE = 1; setThisMonth(); renderList(); });
  g('histPager').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-page]');
    if (!btn || btn.disabled) return;
    PAGE = +btn.dataset.page;
    renderList();
    const top = g('hf_year').closest('div');
    if (top && top.getBoundingClientRect().top < 0) {
      top.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    }
  });

  // Previous / page numbers / Next; hidden when one page is enough.
  function renderPager(total, pages, from, count) {
    const nav = g('histPager');
    if (pages <= 1) { nav.hidden = true; nav.innerHTML = ''; return; }
    const base = 'inline-flex h-9 min-w-9 cursor-pointer items-center justify-center rounded-md border px-3 text-[13px] font-semibold ';
    const idle = 'border-steel-200 bg-white text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:bg-navy-800 dark:text-steel-100 dark:hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-40';
    const on = 'border-dgt-blue-900 bg-dgt-blue-900 text-white dark:border-dgt-blue-500 dark:bg-dgt-blue-500';
    const nums = [];
    for (let i = 1; i <= pages; i++) {
      if (i === 1 || i === pages || Math.abs(i - PAGE) <= 1) nums.push(i);
      else if (nums[nums.length - 1] !== '…') nums.push('…');
    }
    nav.innerHTML =
      '<p class="text-[13px] text-steel-500 dark:text-steel-400">Showing ' + (from + 1) + '–' + (from + count) + ' of ' + total + '</p>' +
      '<div class="flex flex-wrap items-center gap-1.5">' +
        '<button type="button" class="' + base + idle + '" data-page="' + (PAGE - 1) + '"' + (PAGE === 1 ? ' disabled' : '') + '>Previous</button>' +
        nums.map((n) => n === '…'
          ? '<span class="px-1 text-steel-400" aria-hidden="true">…</span>'
          : '<button type="button" class="' + base + (n === PAGE ? on : idle) + '" data-page="' + n + '"' +
              (n === PAGE ? ' aria-current="page"' : '') + ' aria-label="Page ' + n + '">' + n + '</button>').join('') +
        '<button type="button" class="' + base + idle + '" data-page="' + (PAGE + 1) + '"' + (PAGE === pages ? ' disabled' : '') + '>Next</button>' +
      '</div>';
    nav.hidden = false;
  }

  async function loadBilling() {
    const ht = document.querySelector('#historyTable tbody');
    const hc = g('historyCards');
    const banner = g('balBanner');
    try {
      const { appointments } = await api('appointments.php?filter=completed');
      ALL = appointments;
      if (!g('hf_year').options.length) setThisMonth(); else fillYears();

      const today = todayISO();
      let billed = 0, outstanding = 0;
      appointments.forEach((a) => {
        if (String(a.scheduled_date).slice(0, 10) === today) {
          billed += parseFloat(a.total_amount) || 0;
        }
        outstanding += parseFloat(a.remaining_balance) || 0;
      });
      g('st_visits').textContent = appointments.length;
      g('st_billed').textContent = peso(billed);
      g('st_out').textContent = peso(outstanding);

      banner.innerHTML = outstanding > 0.005
        ? '<div class="mb-[18px] rounded-md bg-warn-soft px-3.5 py-2.5 text-[13px] text-warn dark:bg-warn/20">' +
          '<strong>Outstanding Balance: ' + peso(outstanding) + '</strong><br>' +
          'You have an unpaid balance. Please contact the clinic to arrange payment.</div>'
        : '';
      renderList();
    } catch (e) {
      ht.innerHTML = '<tr><td colspan="7" class="' + EMPTY + '">' + esc(e.message) + '</td></tr>';
      hc.innerHTML = cardMsg(esc(e.message));
    }
  }

  // The visit list follows the date picker; the tiles and the Outstanding
  // banner above always cover every visit.
  function renderList() {
    const ht = document.querySelector('#historyTable tbody');
    const hc = g('historyCards');
    const appointments = ALL.filter(inFilter);
    g('hf_count').textContent = appointments.length + ' visit' + (appointments.length === 1 ? '' : 's') + ' · ' + filterLabel();
    {
      if (!appointments.length) {
        const msg = ALL.length
          ? 'No treatments for ' + esc(filterLabel()) + '. Pick another month or year above to see older visits.'
          : 'No completed treatments yet.';
        ht.innerHTML = '<tr><td colspan="7" class="' + EMPTY + '">' + msg + '</td></tr>';
        hc.innerHTML = cardMsg(msg);
        renderPager(0, 1, 0, 0);
        return;
      }

      const pages = Math.max(1, Math.ceil(appointments.length / PER_PAGE));
      PAGE = Math.min(Math.max(1, PAGE), pages);
      const from = (PAGE - 1) * PER_PAGE;
      const pageRows = appointments.slice(from, from + PER_PAGE);
      renderPager(appointments.length, pages, from, pageRows.length);

      const rows = pageRows.map((a) => {
        const total = parseFloat(a.total_amount) || 0;
        const bal = parseFloat(a.remaining_balance) || 0;
        const paid = (parseFloat(a.amount_cash) || 0) + (parseFloat(a.amount_gcash) || 0) +
                     (parseFloat(a.amount_bank) || 0) + (parseFloat(a.reservation_fee_paid) || 0);
        const pct = total > 0 ? Math.max(0, Math.min(100, Math.round(paid / total * 100))) : 0;
        const balCell = bal > 0.005
          ? '<strong class="text-warn">' + peso(bal) + '</strong>' +
            '<div class="mt-[5px] h-[5px] w-[120px] max-w-full overflow-hidden rounded-full bg-steel-100 dark:bg-white/10"><span class="block h-full rounded-full bg-warn" style="width:' + pct + '%"></span></div>' +
            '<span class="mt-[3px] block text-[11px] text-warn">' + pct + '% paid</span>'
          : '<span class="text-steel-400 dark:text-steel-500">' + peso(0) + '</span>';
        const receipt = a.payment_status
          ? '<a class="' + BTN_SM + OK + '" href="/dgt_dental_clinic/modules/shared/receipt?appointment=' + a.appointment_id + '" target="_blank" rel="noopener">Receipt</a>'
          : '';
        const tnote = dentistNotes(a.notes);
        const tnoteCell = tnote
          ? '<div class="mt-1 max-w-[320px] text-[11px] leading-snug text-steel-400 dark:text-steel-500">' +
            '<span class="font-semibold uppercase tracking-wide text-steel-500 dark:text-steel-400">Visit notes:</span> ' + esc(tnote) + '</div>'
          : '';
        const actions = '<button class="' + BTN_SM + PRIMARY + '" data-view="' + a.appointment_id + '">View</button>' + receipt;
        // Card buttons: a fixed 36px height (opted out of the 44px touch rule
        // via .tap-sm) so they read as normal pills, not tall blocks.
        const CARD_BTN = 'tap-sm inline-flex h-9 items-center justify-center rounded-lg px-4 text-[13px] font-semibold';
        const cardActions = '<button class="' + CARD_BTN + PRIMARY + '" data-view="' + a.appointment_id + '">View</button>' +
          (a.payment_status ? '<a class="' + CARD_BTN + OK + '" href="/dgt_dental_clinic/modules/shared/receipt?appointment=' + a.appointment_id + '" target="_blank" rel="noopener">Receipt</a>' : '');
        const cardBal = bal > 0.005
          ? '<div class="mt-3"><div class="flex items-center justify-between text-xs"><span class="text-steel-500 dark:text-steel-400">Balance <strong class="text-warn">' + peso(bal) + '</strong></span><span class="text-warn">' + pct + '% paid</span></div>' +
            '<div class="mt-1.5 h-[5px] w-full overflow-hidden rounded-full bg-steel-100 dark:bg-white/10"><span class="block h-full rounded-full bg-warn" style="width:' + pct + '%"></span></div></div>'
          : '';
        const card = '<article class="' + CARD + ' flex flex-col">' +
          '<div class="flex items-start justify-between gap-3 text-xs text-steel-500 dark:text-steel-400">' +
            '<span>' + esc(fmtDate(a.scheduled_date)) + (a.time_slot ? ' · ' + esc(fmtTime(a.time_slot)) : '') + '</span>' +
            '<span class="text-base font-bold tabular-nums text-navy-900 dark:text-steel-50">' + peso(total) + '</span></div>' +
          '<h3 class="mt-1.5 text-[15px] font-semibold leading-snug text-navy-900 dark:text-steel-50">' + serviceSummary(a.procedures || a.requested_service) + '</h3>' +
          '<p class="mt-0.5 text-xs text-steel-500 dark:text-steel-400">' + esc(a.patient_name) + ' · ' + esc(a.dentist_name || 'N/A') + '</p>' +
          cardBal +
          (tnote ? '<div class="mt-3 rounded-md bg-steel-50 px-3 py-2 text-xs leading-snug text-steel-500 dark:bg-white/5 dark:text-steel-400">' +
            '<span class="font-semibold uppercase tracking-wide text-[10.5px]">Visit notes</span><div class="mt-0.5">' + esc(tnote) + '</div></div>' : '') +
          '<div class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-3">' + statusBadge(a.payment_status) +
            '<div class="flex flex-wrap items-center gap-2">' + cardActions + '</div></div>' +
          '</article>';
        const row = '<tr><td>' + dateTimeStack(esc(a.scheduled_date), a.time_slot) + '</td>' +
          '<td><strong>' + esc(a.patient_name) + '</strong>' +
            '<div class="text-xs text-steel-400 dark:text-steel-500">' + esc(a.dentist_name || 'N/A') + '</div></td>' +
          '<td>' + serviceSummary(a.procedures || a.requested_service) + tnoteCell + '</td>' +
          '<td>' + peso(total) + '</td>' +
          '<td>' + balCell + '</td>' +
          '<td>' + statusBadge(a.payment_status) + '</td>' +
          '<td><div class="flex flex-wrap gap-2">' + actions + '</div></td></tr>';
        return { row, card };
      });
      ht.innerHTML = rows.map((r) => r.row).join('');
      hc.innerHTML = rows.map((r) => r.card).join('');
    }
  }

  loadBilling();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
