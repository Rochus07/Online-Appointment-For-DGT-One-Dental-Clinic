<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['admin']);
$PAGE_TITLE = 'Voided Receipts';
$ACTIVE = 'voids';
$PAGE_FULL = true;

$cardCls  = 'rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800';
$h2Cls    = 'text-[15px] font-semibold text-navy-800 dark:text-steel-50';
$inpCls   = 'w-full rounded-md border border-steel-200 bg-white px-3 py-2 text-[14px] tabular-nums text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
$btnCls   = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold disabled:opacity-50';
$tableCls = 'w-full border-collapse text-[13px] '
    . "[&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 "
    . '[&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:dark:border-white/10 2xl:text-[14.5px] 2xl:[&_td]:py-3.5 2xl:[&_th]:py-3';

ob_start();
?>
<div class="mb-[22px]">
    <h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">Voided Receipts</h1>
</div>

<div class="<?= $cardCls ?>">
    <h2 class="<?= $h2Cls ?>">Void a Receipt</h2>
    <p class="mt-1 text-[12.5px] text-steel-500 dark:text-steel-300">Check the spoiled paper receipt the cashier brought, then enter the OR number printed on it.</p>
    <form id="vdFind" class="mt-3 flex flex-wrap items-end gap-2">
        <label class="flex w-full flex-col gap-1.5 sm:w-[260px]"><span class="text-xs font-semibold text-steel-500 dark:text-steel-300">OR No.</span>
            <input id="vd_or" class="<?= $inpCls ?>" type="text" inputmode="numeric" maxlength="10" placeholder="e.g. 000311" autocomplete="off"></label>
        <button type="submit" class="<?= $btnCls ?> bg-accent text-white hover:bg-accent-dark">Find Receipt</button>
    </form>
    <div id="vdResult" class="mt-4" hidden></div>
</div>

<div class="<?= $cardCls ?> mt-4">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="<?= $h2Cls ?>">Voided Receipts History</h2>
        <span id="vdCount" class="text-xs text-steel-400"></span>
    </div>
    <div class="overflow-x-auto">
        <table id="vdTable" class="<?= $tableCls ?>">
            <thead><tr><th>OR No.</th><th>Date &amp; Time</th><th>Patient</th><th class="!text-right">Amount</th><th>Handled Cashier</th><th>Voided By (Admin)</th><th>Reason</th><th>Status</th></tr></thead>
            <tbody><tr><td colspan="8" class="py-6 text-center text-steel-400">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
    <nav id="vdPager" class="mt-3 flex flex-wrap items-center justify-between gap-3" aria-label="Voided receipt pages" hidden></nav>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();

$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);
  const BTN = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold disabled:opacity-50';
  const GHOST = ' border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
  const DANGER = ' bg-danger text-white hover:brightness-95';
  const INP = 'w-full rounded-md border border-steel-200 bg-white px-3 py-2 text-[14px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
  const LBL = 'text-xs font-semibold text-steel-500 dark:text-steel-300';
  const VOID_BADGE = '<span class="inline-block whitespace-nowrap rounded-full bg-danger-soft px-2.5 py-[3px] text-[11px] font-bold text-danger dark:bg-danger/20">VOIDED</span>';
  let REASONS = {}, ROWS = [];
  const PG = { page: 1 };

  function when(s) {
    if (!s) return '—';
    const d = new Date(String(s).replace(' ', 'T'));
    return isNaN(d) ? esc(s) : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) +
      ' ' + d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
  }
  const kv = (k, v) => '<div><div class="text-steel-500 dark:text-steel-300">' + k + '</div><div class="font-semibold text-navy-800 dark:text-steel-50">' + v + '</div></div>';

  function renderResult(r) {
    const box = g('vdResult');
    box.hidden = false;
    let body = '';
    if (r.state === 'voided') {
      const v = r.void;
      body = '<div class="flex flex-wrap items-center gap-2"><span class="text-[18px] font-bold tabular-nums">OR No. ' + esc(r.or_no) + '</span>' + VOID_BADGE + '</div>' +
        '<p class="mt-2 text-[13px] text-steel-600 dark:text-steel-300">Voided by ' + esc(v.voided_by_name || '—') + ' on ' + when(v.voided_at) + ': ' + esc(v.reason) + '</p>';
    } else if (r.state === 'issued') {
      const o = r.issued;
      body = '<div class="text-[18px] font-bold tabular-nums">OR No. ' + esc(r.or_no) + '</div>' +
        '<div class="mt-3 grid grid-cols-2 gap-3 text-[13px] sm:grid-cols-3 lg:grid-cols-6">' +
          kv('Patient', esc(o.patient_name || '—')) + kv('Amount', peso(o.amount)) +
          kv('Transaction #', '#' + esc(o.day_seq)) + kv('Date &amp; Time', when(o.issued_at)) +
          kv('Handled Cashier', esc(o.cashier_name || '—')) +
          kv('Bill Status', esc(String(o.payment_status || '—').replace('_', ' '))) +
        '</div>' +
        (r.can_void
          ? '<p class="mt-3 rounded-lg border border-warn/40 bg-warn-soft px-3 py-2 text-[12.5px] text-[#9a5a0a] dark:bg-warn/20 dark:text-[#f3c77e]">Voiding takes this ' + peso(o.amount) + ' payment off the bill. It leaves the sales totals and the bill goes back to the cashier\'s Transaction queue to be paid again on a fresh receipt.</p>'
          : '<p class="mt-3 text-[13px] font-semibold text-danger">' + esc(r.block) + '</p>');
    } else if (r.state === 'blank') {
      body = '<div class="text-[18px] font-bold tabular-nums">OR No. ' + esc(r.or_no) + '</div>' +
        '<p class="mt-2 text-[13px] text-steel-600 dark:text-steel-300">This receipt was never issued to a payment (series ' + esc(r.series.start) + ' to ' + esc(r.series.end) + '). ' +
        (r.is_next ? 'It is the cashier\'s next receipt, so after voiding the cashier must re-align to the next fresh receipt.' : 'Voiding it means this number can never be used.') + '</p>';
    } else {
      body = '<p class="text-[13px] font-semibold text-danger">OR No. ' + esc(r.or_no) + ': ' + esc(r.block) + '</p>';
    }
    box.innerHTML = '<div class="rounded-lg border border-steel-100 p-4 dark:border-white/10">' + body +
      (r.can_void && r.state !== 'voided' ? '<div class="mt-4"><button class="' + BTN + DANGER + '" id="vd_void">Void Receipt</button></div>' : '') + '</div>';
    if (g('vd_void')) g('vd_void').addEventListener('click', () => voidModal(r));
  }

  function voidModal(r) {
    const opts = Object.keys(REASONS).map((k) => '<option value="' + k + '">' + esc(REASONS[k]) + '</option>').join('');
    openModal(modalShell('Void OR No. ' + esc(r.or_no) + '?',
      '<label class="flex flex-col gap-1.5"><span class="' + LBL + '">Reason<span class="text-danger"> *</span></span>' +
        '<select id="vd_code" class="' + INP + '"><option value="">Choose a reason</option>' + opts + '</select></label>' +
      '<label class="mt-3 flex flex-col gap-1.5"><span class="' + LBL + '" id="vd_txtLbl">Details (optional)</span>' +
        '<textarea id="vd_txt" rows="2" maxlength="200" class="' + INP + '" placeholder="e.g. receipt torn in the printer"></textarea></label>' +
      '<label class="mt-3 flex items-start gap-2 text-[13px]"><input type="checkbox" id="vd_seen" class="mt-0.5">' +
        '<span>I have checked the physical receipt OR No. ' + esc(r.or_no) + ' that the cashier brought.</span></label>' +
      '<p class="mt-3 text-[12.5px] font-semibold text-danger">This cannot be undone.</p>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + DANGER + '" id="vd_go" disabled>Void Receipt</button>'));
    const code = g('vd_code'), txt = g('vd_txt'), seen = g('vd_seen'), go = g('vd_go');
    const sync = () => {
      const other = code.value === 'other';
      g('vd_txtLbl').innerHTML = other ? 'Reason for voiding<span class="text-danger"> *</span>' : 'Details (optional)';
      go.disabled = !code.value || !seen.checked || (other && txt.value.trim().length < 3);
    };
    [code, txt, seen].forEach((el) => el.addEventListener(el === txt ? 'input' : 'change', sync));
    go.addEventListener('click', async () => {
      try {
        const d = await api('or_voids.php?action=void', { method: 'POST', body: { or_no: r.or_no, reason_code: code.value, reason_text: txt.value } });
        closeModal('button');
        toast(d.message);
        renderResult(d.receipt);
        loadList();
      } catch (e) { toast(e.message, 'err'); }
    });
  }

  g('vd_or').addEventListener('input', (e) => { e.target.value = e.target.value.replace(/\D+/g, ''); });
  g('vdFind').addEventListener('submit', async (e) => {
    e.preventDefault();
    const v = g('vd_or').value.trim();
    if (!v) return toast('Enter the OR number printed on the receipt.', 'err');
    try {
      const d = await api('or_voids.php?action=lookup&or=' + encodeURIComponent(v));
      REASONS = d.reasons || REASONS;
      renderResult(d.receipt);
    } catch (err) { toast(err.message, 'err'); }
  });

  function drawList() {
    const tb = document.querySelector('#vdTable tbody');
    if (!tb) return;
    g('vdCount').textContent = ROWS.length + ' voided';
    const { rows } = window.pageSlice(ROWS, PG, window.fitRows(g('vdTable')), g('vdPager'), drawList);
    tb.innerHTML = rows.length ? rows.map((r) =>
      '<tr><td class="font-semibold tabular-nums">' + esc(r.or_no) + '</td>' +
      '<td class="whitespace-nowrap">' + (r.issued_at ? when(r.issued_at) : '<span class="text-steel-400">Not issued</span>') +
        '<div class="text-[11.5px] text-steel-400">Voided ' + when(r.voided_at) + '</div></td>' +
      '<td>' + esc(r.patient_name || '—') + '</td>' +
      '<td class="text-right tabular-nums">' + (r.payment_id ? peso(r.amount) : '—') + '</td>' +
      '<td>' + esc(r.cashier_name || '—') + '</td>' +
      '<td>' + esc(r.voided_by_name || '—') + '</td>' +
      '<td class="min-w-[180px]">' + esc(r.reason) + '</td>' +
      '<td>' + VOID_BADGE + '</td></tr>').join('')
      : '<tr><td colspan="8" class="py-6 text-center text-steel-400">No receipts have been voided.</td></tr>';
  }
  window.fitOnResize(() => { if (document.getElementById('vdTable')) drawList(); });

  async function loadList() {
    try {
      const d = await api('or_voids.php?action=list');
      REASONS = d.reasons || {};
      ROWS = d.rows || [];
      drawList();
    } catch (e) {
      document.querySelector('#vdTable tbody').innerHTML = '<tr><td colspan="8" class="py-6 text-center text-danger">' + esc(e.message) + '</td></tr>';
    }
  }
  loadList();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
