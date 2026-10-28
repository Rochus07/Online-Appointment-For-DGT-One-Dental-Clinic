<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['cashier']);
$PAGE_TITLE = 'OR / Invoice Setup';
$ACTIVE = 'or_setup';
$PAGE_FULL = true;

$cardCls  = 'rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800';
$h2Cls    = 'text-[15px] font-semibold text-navy-800 dark:text-steel-50';
$lblCls   = 'text-xs font-semibold text-steel-500 dark:text-steel-300';
$inpCls   = 'w-full rounded-md border border-steel-200 bg-white px-3 py-2 text-[14px] tabular-nums text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
$btnCls   = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold disabled:opacity-50';
$tableCls = 'w-full border-collapse text-[13px] '
    . "[&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 "
    . '[&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:dark:border-white/10 2xl:text-[14.5px] 2xl:[&_td]:py-3.5 2xl:[&_th]:py-3';

ob_start();
?>
<div class="mb-[22px]">
    <h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">OR / Invoice Setup</h1>
</div>

<div class="grid gap-4 lg:grid-cols-2 lg:items-start">
    <div class="<?= $cardCls ?>">
        <h2 class="<?= $h2Cls ?>">Current OR Series</h2>
        <div id="orCurrent" class="mt-3"><p class="py-6 text-center text-steel-400">Loading&hellip;</p></div>
    </div>

    <div class="<?= $cardCls ?>">
        <h2 class="<?= $h2Cls ?>">Set Up a New Series</h2>
        <p class="mt-1 text-[12.5px] text-steel-500 dark:text-steel-300">Enter the first and last numbers printed on the receipt booklet. Saving closes the current series.</p>
        <div class="mt-3 grid grid-cols-2 gap-3">
            <label class="flex flex-col gap-1.5"><span class="<?= $lblCls ?>">First OR No.<span class="text-danger"> *</span></span>
                <input id="or_start" class="<?= $inpCls ?>" type="text" inputmode="numeric" maxlength="10" placeholder="e.g. 000301"></label>
            <label class="flex flex-col gap-1.5"><span class="<?= $lblCls ?>">Last OR No.<span class="text-danger"> *</span></span>
                <input id="or_end" class="<?= $inpCls ?>" type="text" inputmode="numeric" maxlength="10" placeholder="e.g. 000320"></label>
        </div>
        <p id="or_preview" class="mt-2 min-h-[18px] text-[12.5px] text-steel-500 dark:text-steel-300"></p>
        <button id="or_save" class="<?= $btnCls ?> mt-2 bg-accent text-white hover:bg-accent-dark">Save Series</button>
    </div>
</div>

<div class="<?= $cardCls ?> mt-4">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="<?= $h2Cls ?>">Today's Transactions</h2>
        <span id="todayCount" class="text-xs text-steel-400"></span>
    </div>
    <div class="overflow-x-auto">
        <table class="<?= $tableCls ?>">
            <thead><tr><th>Transaction #</th><th>OR No.</th><th>Patient</th><th>Time</th><th class="!text-right">Amount</th><th id="todayStatusTh" hidden>Status</th></tr></thead>
            <tbody id="todayBody"><tr><td colspan="5" class="py-6 text-center text-steel-400">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
    <nav id="todayPager" class="mt-3 flex flex-wrap items-center justify-between gap-3" aria-label="Today's transaction pages" hidden></nav>
</div>

<div class="<?= $cardCls ?> mt-4">
    <h2 class="<?= $h2Cls ?> mb-3">Series History</h2>
    <div class="overflow-x-auto">
        <table class="<?= $tableCls ?>">
            <thead><tr><th>Range</th><th>Used</th><th>Status</th><th>Set Up</th><th>Closed</th></tr></thead>
            <tbody id="histBody"><tr><td colspan="5" class="py-6 text-center text-steel-400">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
    <nav id="histPager" class="mt-3 flex flex-wrap items-center justify-between gap-3" aria-label="Series history pages" hidden></nav>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);
  const BTN = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold disabled:opacity-50';
  const GHOST = ' border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
  const DANGER = ' bg-danger text-white hover:brightness-95';
  const PRIMARY = ' bg-accent text-white hover:bg-accent-dark';
  const INP = 'w-full rounded-md border border-steel-200 bg-white px-3 py-2 text-[14px] tabular-nums text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
  const BADGE = 'inline-block whitespace-nowrap rounded-full px-2.5 py-[3px] text-[11px] font-bold';
  const STATUS = {
    active:  ['Active', ' bg-ok-soft text-ok dark:bg-ok/20'],
    used_up: ['Used up', ' bg-steel-100 text-steel-600 dark:bg-white/10 dark:text-steel-300'],
    closed:  ['Reset', ' bg-warn-soft text-[#9a5a0a] dark:bg-warn/20 dark:text-[#f3c77e]'],
  };
  const digitsOnly = (el) => el.addEventListener('input', () => { el.value = el.value.replace(/\D+/g, ''); preview(); });
  let OR = null;

  function fmtWhen(s) {
    if (!s) return '—';
    const d = new Date(String(s).replace(' ', 'T'));
    return isNaN(d) ? esc(s) : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function renderCurrent() {
    const box = g('orCurrent');
    const o = OR;
    if (!o || !o.series) {
      box.innerHTML = '<div class="rounded-lg border border-warn/40 bg-warn-soft px-4 py-3 text-[13px] text-[#9a5a0a] dark:bg-warn/20 dark:text-[#f3c77e]">' +
        '<strong>No active OR series.</strong> Set up the receipt numbers on the right before taking payments.</div>' +
        '<p class="mt-3 text-[13px] text-steel-500 dark:text-steel-300">Transactions today: <strong class="text-navy-800 dark:text-steel-50">' + o.today_count + '</strong></p>';
      return;
    }
    const s = o.series;
    const pct = s.total ? Math.round((s.used / s.total) * 100) : 0;
    box.innerHTML =
      '<div class="flex flex-wrap items-end justify-between gap-3">' +
        '<div><div class="text-xs font-semibold uppercase tracking-wide text-steel-500 dark:text-steel-300">Next OR No. (load this in the printer)</div>' +
        '<div class="mt-1 text-[34px] font-bold tabular-nums leading-none text-navy-900 dark:text-steel-50">' + esc(o.next) + '</div></div>' +
        '<div class="text-right text-[13px] text-steel-500 dark:text-steel-300">Next transaction today<div class="text-[22px] font-bold text-navy-900 dark:text-steel-50">#' + o.next_txn + '</div></div>' +
      '</div>' +
      '<div class="mt-4 grid grid-cols-3 gap-3 text-[13px]">' +
        '<div><div class="text-steel-500 dark:text-steel-300">Series</div><div class="font-semibold tabular-nums text-navy-800 dark:text-steel-50">' + esc(s.start) + ' to ' + esc(s.end) + '</div></div>' +
        '<div><div class="text-steel-500 dark:text-steel-300">Used</div><div class="font-semibold text-navy-800 dark:text-steel-50">' + s.used + ' of ' + s.total + '</div></div>' +
        '<div><div class="text-steel-500 dark:text-steel-300">Left</div><div class="font-semibold ' + (o.remaining <= 3 ? 'text-danger' : 'text-navy-800 dark:text-steel-50') + '">' + o.remaining + '</div></div>' +
      '</div>' +
      '<div class="mt-3 h-2 overflow-hidden rounded-full bg-steel-100 dark:bg-white/10"><div class="h-full rounded-full bg-accent" style="width:' + pct + '%"></div></div>' +
      (o.remaining <= 3 ? '<p class="mt-2 text-[12.5px] font-semibold text-danger">Only ' + o.remaining + ' receipt(s) left. Prepare the next booklet.</p>' : '') +
      (o.next_voided ? '<p class="mt-2 rounded-lg border border-danger/40 bg-danger-soft px-3 py-2 text-[12.5px] font-semibold text-danger dark:bg-danger/20">OR No. ' + esc(o.next) + ' was voided by the admin. Load the next fresh receipt and use Re-align Next Number.</p>' : '') +
      '<div class="mt-4 flex flex-wrap gap-2">' +
        '<button class="' + BTN + GHOST + '" id="or_realign">Re-align Next Number</button>' +
        '<button class="' + BTN + DANGER + '" id="or_reset">Reset Series</button>' +
      '</div>';
    g('or_realign').addEventListener('click', realignModal);
    g('or_reset').addEventListener('click', resetModal);
  }

  function renderToday(rows) {
    rows = rows || TODAY;
    TODAY = rows;
    const all = rows;
    // The Status column only shows when a receipt today was voided.
    const hasVoid = all.some((r) => r.voided_at);
    g('todayStatusTh').hidden = !hasVoid;
    const cols = hasVoid ? 6 : 5;
    rows = window.pageSlice(all, PG_TODAY, 10, g('todayPager'), () => renderToday()).rows;
    g('todayCount').textContent = all.length + ' today';
    g('todayBody').innerHTML = rows.length ? rows.map((r) =>
      '<tr' + (r.appointment_id ? ' class="cursor-pointer" data-view="' + r.appointment_id + '"' : '') + '>' +
      '<td class="font-semibold">#' + r.day_seq + '</td>' +
      '<td class="tabular-nums">' + esc(r.or_no) + '</td>' +
      '<td>' + esc(r.patient_name || '—') + '</td>' +
      '<td class="whitespace-nowrap">' + esc(r.time || '') + '</td>' +
      '<td class="text-right tabular-nums' + (r.voided_at ? ' text-steel-400 line-through' : '') + '">' + peso(r.amount) + '</td>' +
      (hasVoid ? '<td>' + (r.voided_at
        ? '<span class="' + BADGE + ' bg-danger-soft text-danger dark:bg-danger/20">VOIDED</span>'
        : '<span class="' + BADGE + ' bg-ok-soft text-ok dark:bg-ok/20">Valid</span>') + '</td>' : '') +
      '</tr>').join('')
      : '<tr><td colspan="' + cols + '" class="py-6 text-center text-steel-400">No receipts issued today yet.</td></tr>';
  }

  function renderHistory(rows) {
    rows = rows || HIST;
    HIST = rows;
    rows = window.pageSlice(rows, PG_HIST, 10, g('histPager'), () => renderHistory()).rows;
    g('histBody').innerHTML = rows.length ? rows.map((r) => {
      const st = STATUS[r.status] || [r.status, ''];
      return '<tr><td class="tabular-nums">' + esc(r.start) + ' to ' + esc(r.end) + '</td>' +
        '<td>' + r.used + ' of ' + r.total + '</td>' +
        '<td><span class="' + BADGE + st[1] + '">' + st[0] + '</span></td>' +
        '<td class="whitespace-nowrap">' + fmtWhen(r.created_at) + '</td>' +
        '<td class="whitespace-nowrap">' + fmtWhen(r.closed_at) + '</td></tr>';
    }).join('') : '<tr><td colspan="5" class="py-6 text-center text-steel-400">No series set up yet.</td></tr>';
  }

  // Both lists show 10 per page (window.pageSlice, app.js); a reload keeps the page.
  let TODAY = [], HIST = [];
  const PG_TODAY = { page: 1 }, PG_HIST = { page: 1 };

  async function load() {
    try {
      const d = await api('or_series.php?action=status');
      OR = d.or;
      renderCurrent(); renderToday(d.today || []); renderHistory(d.history || []);
      // Came from the Billing screen's "Re-align Next Number" link.
      let ra = null; try { ra = sessionStorage.getItem('dgt_or_realign'); sessionStorage.removeItem('dgt_or_realign'); } catch (_) {}
      if (ra && OR && OR.series) realignModal();
    } catch (e) { g('orCurrent').innerHTML = '<p class="text-danger">' + esc(e.message) + '</p>'; }
  }

  function preview() {
    const a = g('or_start').value, b = g('or_end').value, p = g('or_preview');
    if (!a || !b) { p.textContent = ''; return; }
    const n = parseInt(b, 10) - parseInt(a, 10) + 1;
    p.className = 'mt-2 min-h-[18px] text-[12.5px] ' + (n > 0 ? 'text-steel-500 dark:text-steel-300' : 'text-danger');
    p.textContent = n > 0 ? n + ' receipt(s): ' + a + ' to ' + b : 'The last number must be the same as or higher than the first.';
  }
  digitsOnly(g('or_start')); digitsOnly(g('or_end'));

  g('or_save').addEventListener('click', () => {
    const start = g('or_start').value.trim(), end = g('or_end').value.trim();
    if (!start || !end) return toast('Enter the first and last OR numbers.', 'err');
    if (parseInt(end, 10) < parseInt(start, 10)) return toast('The last number must be the same as or higher than the first.', 'err');
    const cur = OR && OR.series;
    const save = async () => {
      try {
        const d = await api('or_series.php?action=create', { method: 'POST', body: { start, end } });
        OR = d.or; closeModal('button');
        g('or_start').value = ''; g('or_end').value = ''; preview();
        toast('OR series ' + start + ' to ' + end + ' is now active.');
        load();
      } catch (e) { toast(e.message, 'err'); }
    };
    if (!cur) return save();
    openModal(modalShell('Replace the Current Series?',
      '<p class="text-[13.5px] leading-relaxed">The current series <strong>' + esc(cur.start) + ' to ' + esc(cur.end) + '</strong> will be closed' +
      (OR.remaining ? ' with <strong>' + OR.remaining + '</strong> unused receipt(s)' : '') +
      '. New payments will start at <strong>' + esc(start) + '</strong>.</p>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + PRIMARY + '" id="or_go">Yes, Use New Series</button>'));
    g('or_go').addEventListener('click', save);
  });

  function realignModal() {
    const s = OR.series;
    openModal(modalShell('Re-align Next OR Number',
      '<p class="text-[13.5px] leading-relaxed">Use this when the receipt in the printer does not match, for example after a spoiled or skipped receipt. Enter the number printed on the receipt that is loaded now (between ' + esc(s.start) + ' and ' + esc(s.end) + ').</p>' +
      '<label class="mt-3 flex flex-col gap-1.5"><span class="text-xs font-semibold text-steel-500 dark:text-steel-300">OR No. loaded in the printer</span>' +
      '<input id="ra_next" class="' + INP + '" type="text" inputmode="numeric" maxlength="10" value="' + esc(OR.next) + '"></label>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + PRIMARY + '" id="ra_go">Save</button>'));
    const inp = g('ra_next');
    inp.addEventListener('input', () => { inp.value = inp.value.replace(/\D+/g, ''); });
    g('ra_go').addEventListener('click', async () => {
      if (!inp.value) return toast('Enter the OR number.', 'err');
      try {
        const d = await api('or_series.php?action=realign', { method: 'POST', body: { next: inp.value } });
        OR = d.or; closeModal('button'); toast('Next OR number is now ' + d.or.next + '.'); load();
      } catch (e) { toast(e.message, 'err'); }
    });
  }

  function resetModal() {
    const s = OR.series;
    openModal(modalShell('Reset OR Series?',
      '<p class="text-[13.5px] leading-relaxed">This closes the series <strong>' + esc(s.start) + ' to ' + esc(s.end) + '</strong>' +
      (OR.remaining ? ' (' + OR.remaining + ' receipt(s) not used)' : '') +
      '. Payments are blocked until you set up the next series.</p>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + DANGER + '" id="rs_go">Yes, Reset</button>'));
    g('rs_go').addEventListener('click', async () => {
      try {
        const d = await api('or_series.php?action=reset', { method: 'POST', body: {} });
        OR = d.or; closeModal('button'); toast('OR series reset. Set up the next series.'); load();
        g('or_start').focus();
      } catch (e) { toast(e.message, 'err'); }
    });
  }

  load();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
