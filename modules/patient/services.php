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
$PAGE_TITLE = 'Services';
$ACTIVE = 'services';
$PAGE_FULL = true;   // full-bleed content column (matches the admin screens)

$inputCls = 'rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-100';

ob_start();
?>
<div class="mb-[22px] flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">Services</h1></div>
</div>

<div class="relative mb-5 overflow-hidden rounded-[10px] bg-[linear-gradient(135deg,#16263c,#226690_90%)] px-[26px] py-[22px] text-white shadow-sm after:absolute after:-top-10 after:-right-5 after:h-40 after:w-40 after:rounded-full after:bg-white/[0.06]">
    <h2 class="text-lg tracking-[0.3px]">DGT One Dental Center - Bulacan</h2>
    <p class="mt-[5px] text-[13px] leading-relaxed text-white/[0.88]">B-6L-47 Delrey Bldg., Bgy. Tungkong Mangga, San Jose Del Monte, Bulacan<br>
    09764484252 &nbsp;&bull;&nbsp; customerservice@dgtonedentalph.com<br>
    Open Monday&ndash;Saturday by appointment &nbsp;&bull;&nbsp; Closed Sundays</p>
</div>

<div class="rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800">
    <div class="mb-3.5 flex flex-wrap items-center gap-3">
        <input type="search" id="svcSearch" placeholder="Search services by name&hellip;"
               class="<?= $inputCls ?> min-w-[220px] flex-1">
    </div>
    <div id="svcGrid"><p class="py-7 text-center text-steel-400 dark:text-steel-500">Loading&hellip;</p></div>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);
  const IMG_BASE = '/dgt_dental_clinic/uploads/services/';
  const GRID = 'grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3';
  const CLOCK = '<svg viewBox="0 0 24 24" class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
  const CARD = 'group flex flex-col overflow-hidden rounded-[10px] border border-steel-100 bg-white text-left shadow-sm transition hover:-translate-y-0.5 hover:border-dgt-blue-200 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent dark:border-white/10 dark:bg-navy-800 dark:hover:border-white/25 motion-reduce:transition-none motion-reduce:hover:translate-y-0';
  const BOOK_URL = '/dgt_dental_clinic/modules/patient/appointments';
  const EMPTY = '<p class="py-7 text-center text-steel-400 dark:text-steel-500">';

  let SVC = [];

  function render() {
    const q = g('svcSearch').value.trim().toLowerCase();
    const rows = SVC.filter((s) => s.is_active == 1 && (!q || s.service_name.toLowerCase().includes(q)));
    const grid = g('svcGrid');
    if (!rows.length) { grid.className = ''; grid.innerHTML = EMPTY + 'No services found.</p>'; return; }
    grid.className = GRID;
    grid.innerHTML = rows.map((s) => {
      const img = s.image
        ? '<img class="block aspect-[16/10] w-full bg-steel-100 object-cover transition duration-500 group-hover:scale-105 motion-reduce:transition-none motion-reduce:group-hover:scale-100 dark:bg-white/5" src="' + IMG_BASE + encodeURIComponent(s.image) + (s.image_v ? '?v=' + s.image_v : '') + '" alt="">'
        : '<div class="grid aspect-[16/10] w-full place-items-center bg-steel-100 text-[40px] dark:bg-white/5">🦷</div>';
      // The whole card is a link to Appointments; the click handler below
      // stashes the service so the booking wizard opens with it selected.
      return '<a href="' + BOOK_URL + '" data-nav data-book-svc="' + s.service_id + '" class="' + CARD + '" aria-label="Book ' + esc(s.service_name) + '">' +
        '<div class="overflow-hidden">' + img + '</div>' +
        '<div class="flex flex-1 flex-col gap-[9px] px-[18px] py-4">' +
          '<div class="flex items-start justify-between gap-2.5"><h3 class="text-[15px] text-navy-900 dark:text-steel-50">' + esc(s.service_name) + '</h3></div>' +
          (s.description ? '<div class="flex-1 text-[13px] leading-normal text-steel-500 dark:text-steel-400">' + esc(s.description) + '</div>' : '') +
          // Starting (base) price only; the dentist sets the real charge.
          (+s.base_price > 0
            ? '<div class="rounded-md bg-steel-50 px-2.5 py-2 dark:bg-white/5">' +
                '<div class="text-[13.5px] font-semibold text-ok">Starts at ' + peso(s.base_price) + (s.quantity_unit ? ' per ' + esc(s.quantity_unit) : '') + '</div>' +
                '<div class="mt-0.5 text-[11.5px] leading-snug text-steel-500 dark:text-steel-400">Note: Prices shown are baseline amounts (&quot;starts at&quot;). The final cost may vary based on the actual condition, severity, or additional units required as determined by the dentist during treatment.</div></div>'
            : '') +
          (+s.duration_minutes > 0
            ? '<div class="flex items-center gap-1.5 text-[12.5px] text-steel-500 dark:text-steel-400">' + CLOCK + 'Usual time: <strong class="font-semibold text-navy-800 dark:text-steel-100">' + esc(fmtDuration(s.duration_minutes, s.quantity_unit)) + '</strong></div>'
            : '') +
          '<div class="mt-0.5 flex items-center gap-3">' +
            '<span class="text-xs font-semibold text-accent opacity-80 transition group-hover:opacity-100">Book this service &rarr;</span>' +
          '</div>' +
        '</div></a>';
    }).join('');
  }

  // Runs before nav.js's document-level data-nav handler (bubbling order), so the
  // prefill is in sessionStorage by the time appointments.php's script reads it.
  g('svcGrid').addEventListener('click', (e) => {
    const card = e.target.closest('[data-book-svc]');
    if (!card) return;
    try { sessionStorage.setItem('dgt_book_prefill', JSON.stringify({ service_id: card.dataset.bookSvc, from: 'services' })); } catch (_) { /* ignore */ }
  });

  async function load() {
    try {
      const r = await api('services.php?active=1');
      SVC = r.services || [];
      render();
    } catch (e) { g('svcGrid').innerHTML = EMPTY + esc(e.message) + '</p>'; }
  }

  g('svcSearch').addEventListener('input', render);
  load();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
