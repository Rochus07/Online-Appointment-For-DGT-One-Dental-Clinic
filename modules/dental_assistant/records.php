<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['dental_assistant']);
$PAGE_TITLE = 'Patient Records';
$ACTIVE = 'records';
$PAGE_FULL = true;

$inputCls = 'rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
$tableCls = 'w-full border-collapse text-[13px] '
    . "[&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 "
    . '[&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:dark:border-white/10 [&_tbody_tr:hover]:bg-steel-50 [&_tbody_tr:hover]:dark:bg-white/5 2xl:text-[14.5px] 2xl:[&_td]:py-3.5 2xl:[&_th]:py-3';

ob_start();
require __DIR__ . '/../../includes/records_chart_style.php';
?>
<div class="mb-[22px] flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">Patient Records</h1></div>
</div>

<div class="rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800">
    <div class="mb-3.5 flex flex-wrap items-center gap-3">
        <input type="search" id="recSearch" placeholder="Search patients by name, email or contact number&hellip;"
               class="<?= $inputCls ?> min-w-[220px] flex-1">
    </div>
    <div class="overflow-x-auto">
        <table class="rtable rt-plain rt-phone <?= $tableCls ?>" id="recTable">
            <thead><tr><th>#</th><th>Name</th><th>Age</th><th>Contact No.</th><th>Email Address</th><th>Visits</th></tr></thead>
            <tbody><tr><td colspan="6" class="py-7 text-center text-steel-400">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
    <nav id="recPager" class="mt-4 flex flex-wrap items-center justify-between gap-3" aria-label="Table pages" hidden></nav>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);
  let searchTimer = null;

  const BTN_SM  = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold';
  const PRIMARY = ' bg-accent text-white hover:bg-accent-dark';
  const GHOST   = ' border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
  const OK      = ' bg-ok text-white hover:brightness-95';
  const FG      = 'flex flex-col gap-1.5';
  const LBL     = 'text-xs font-semibold text-steel-500 dark:text-steel-300';
  const INP     = 'w-full rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent disabled:bg-steel-50 dark:border-white/15 dark:bg-navy-900 dark:text-steel-50 dark:disabled:bg-white/5';
  const TABLE   = 'w-full border-collapse text-[13px] [&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 [&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:dark:border-white/10';
  const BADGE   = 'inline-block whitespace-nowrap rounded-full px-2.5 py-[3px] text-[11px] font-bold';
  const EMPTY   = 'py-7 text-center text-steel-400';

  function lastFirst(name) {
    const s = String(name || '').trim();
    if (!s) return 'N/A';
    if (s.includes(',')) return s;
    const parts = s.split(/\s+/);
    return parts.length > 1 ? parts[parts.length - 1] + ', ' + parts.slice(0, -1).join(' ') : s;
  }

  function ageFrom(dob) {
    if (!dob) return 'N/A';
    const d = new Date(dob + 'T00:00:00'), n = new Date();
    let a = n.getFullYear() - d.getFullYear();
    if (n.getMonth() < d.getMonth() || (n.getMonth() === d.getMonth() && n.getDate() < d.getDate())) a--;
    return a >= 0 && a < 130 ? a : 'N/A';
  }

  const visitCell = (n) => +n > 0
    ? '<span class="' + BADGE + ' bg-dgt-blue-50 text-dgt-blue-700 dark:bg-dgt-blue-500/20 dark:text-dgt-blue-200">Returning</span>'
    : '<span class="' + BADGE + ' bg-dgt-teal-50 text-dgt-teal-700 dark:bg-dgt-teal-500/20 dark:text-dgt-teal-200">New</span>';

  // Patients per page fill the screen (10 minimum) and re-fit on resize;
  // a new search goes back to page 1 (2026-10-17).
  const PG = { page: 1 };
  let ALL = [];
  async function load() {
    const tb = document.querySelector('#recTable tbody');
    try {
      ALL = (await api('records.php?action=list&q=' + encodeURIComponent(g('recSearch').value.trim()))).patients || [];
      render();
      render();   // again with a real row's height measured
    } catch (e) { tb.innerHTML = '<tr><td colspan="6" class="' + EMPTY + '">' + esc(e.message) + '</td></tr>'; }
  }
  function render() {
    const tb = document.querySelector('#recTable tbody');
    if (!tb) return;
    const { rows: patients, from } = window.pageSlice(ALL, PG, window.fitRows(g('recTable')), g('recPager'), render);
    tb.innerHTML = patients.length ? patients.map((p, i) =>
        '<tr data-open="' + p.id + '" class="cursor-pointer">' +
        '<td class="rt-hide">' + (from + i + 1) + '</td>' +
        '<td class="rt-title"><strong>' + esc(lastFirst(p.full_name)) + '</strong></td>' +
        '<td>' + (p.age || 'N/A') + '</td>' +
        '<td>' + esc(p.contact_number || 'N/A') + '</td>' +
        '<td class="rt-email">' + esc(p.email || 'N/A') + '</td>' +
        '<td>' + visitCell(p.visit_count) + '</td></tr>'
      ).join('') : '<tr><td colspan="6" class="' + EMPTY + '">No patients found.</td></tr>';
  }
  clearTimeout(window.__recFitT);
  window.removeEventListener('resize', window.__recFit || (() => {}));
  window.__recFit = () => { clearTimeout(window.__recFitT); window.__recFitT = setTimeout(() => { if (document.getElementById('recTable')) render(); }, 150); };
  window.addEventListener('resize', window.__recFit);

  /* ---------- Patient Details (non-clinical view, same as the front desk) ---------- */
  async function openRecord(pid) {
    let data;
    try { data = await api('records.php?action=get&patient_id=' + pid); }
    catch (e) { return toast(e.message, 'err'); }
    const p = data.patient;
    const r = data.record || {};

    // Appointment history: 5 visits per page (2026-10-17).
    const HIST = data.history || [], HPG = { page: 1 };
    const histRow = (h) =>
          '<tr data-view="' + h.appointment_id + '" tabindex="0" title="View visit details" class="cursor-pointer hover:bg-dgt-blue-50 dark:hover:bg-white/5"><td>' + fmtDate(h.scheduled_date) + ' ' + fmtTime(h.time_slot) + '</td>' +
          '<td>' + esc(h.requested_service || 'N/A') + '</td>' +
          '<td>' + esc(h.dentist_name || 'N/A') + '</td>' +
          '<td>' + badge(h.status) + '</td></tr>';
    function drawHist() {
      const tb = document.getElementById('histBody');
      if (!tb) return;
      const { rows } = window.pageSlice(HIST, HPG, 5, document.getElementById('histPager'), drawHist);
      tb.innerHTML = rows.length ? rows.map(histRow).join('') : '<tr><td colspan="4" class="' + EMPTY + '">No appointments recorded.</td></tr>';
      // Rows open the visit (data-view, global handler in app.js); Enter / Space too.
      tb.querySelectorAll('tr[data-view]').forEach((tr) => tr.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); tr.click(); }
      }));
    }

    const F = [
      ['f_contact', 'Contact Number', p.contact_number, 'tel'],
      ['f_email', 'Email Address', p.email, 'email'],
      ['f_address', 'Address', r.address, 'text'],
    ];
    const editField = ([id, label, val, type2]) =>
      '<div class="' + FG + (id === 'f_address' ? ' col-span-2' : '') + '"><label class="' + LBL + '">' + label + '</label>' +
      '<input class="' + INP + '" id="' + id + '" type="' + type2 + '"' + (type2 === 'tel' ? ' inputmode="numeric"' : '') +
      ' value="' + esc(val || '') + '" disabled></div>';

    openModal(modalShell('Patient Details: ' + esc(p.full_name),
      '<h4 class="rec-section">Personal Information</h4>' +
      '<div class="rec-info-grid">' +
      '<div class="rec-field"><label>Name</label><div class="rec-value">' + (esc(p.full_name) || '&nbsp;') + '</div></div>' +
      '<div class="rec-field"><label>Age</label><div class="rec-value">' + ageFrom(r.date_of_birth) + '</div></div>' +
      '</div>' +
      '<h4 class="rec-section" style="margin:16px 0 12px">Contact Information</h4>' +
      '<div class="grid grid-cols-2 gap-3.5">' + F.map(editField).join('') + '</div>' +
      '<hr class="my-4 border-0 border-t border-steel-100 dark:border-white/10"><h4 class="rec-section">Appointment History</h4>' +
      '<div class="overflow-x-auto"><table class="rtable rt-plain rt-phone ' + TABLE + '"><thead><tr><th>Date</th><th>Service</th><th>Dentist</th><th>Status</th></tr></thead><tbody id="histBody"></tbody></table></div><nav id="histPager" class="mt-3 flex flex-wrap items-center justify-between gap-3" aria-label="Appointment history pages" hidden></nav>' +
      '<p class="mt-2.5 text-[11px] text-steel-400">Clinical dental charts, medical history and treatment notes are only visible to the dentist.</p>'),
      { wide: true });
    drawHist();


    const mHead = document.querySelector('#modalRoot .modal-head');
    const headActions = document.createElement('div');
    headActions.className = 'modal-head-actions';
    headActions.innerHTML =
      '<button class="' + BTN_SM + PRIMARY + '" id="rec_edit">Edit Contact Info</button>' +
      '<button class="' + BTN_SM + OK + '" id="rec_save" style="display:none">Save Changes</button>' +
      '<button class="' + BTN_SM + GHOST + '" id="rec_cancelEdit" style="display:none">Cancel</button>';
    mHead.insertBefore(headActions, mHead.querySelector('.modal-close'));

    const fieldEls = () => F.map(([id]) => g(id));
    wirePhoneField(g('f_contact'), { submitBtn: g('rec_save') });

    function setEditing(on) {
      fieldEls().forEach((el) => (el.disabled = !on));
      g('rec_edit').style.display = on ? 'none' : '';
      g('rec_save').style.display = on ? '' : 'none';
      g('rec_cancelEdit').style.display = on ? '' : 'none';
    }

    function resetFields() {
      g('f_contact').value = p.contact_number || '';
      g('f_email').value = p.email || '';
      g('f_address').value = r.address || '';
    }

    g('rec_edit').addEventListener('click', () => setEditing(true));
    g('rec_cancelEdit').addEventListener('click', () => { resetFields(); setEditing(false); });

    g('rec_save').addEventListener('click', async () => {
      if (!g('f_contact')._phoneOk(true)) return toast(PH_MOBILE_MSG, 'err');
      try {
        await api('records.php?action=save', { method: 'POST', body: {
          patient_id: pid,
          contact_number: g('f_contact').value.trim(),
          email: g('f_email').value.trim(),
          address: g('f_address').value.trim(),
        }});
        toast('Contact details saved.');
        closeModal();
        openRecord(pid);
      } catch (e) { toast(e.message, 'err'); }
    });
  }

  g('recSearch').addEventListener('input', () => { clearTimeout(searchTimer); PG.page = 1; searchTimer = setTimeout(load, 250); });
  document.querySelector('#recTable').addEventListener('click', (e) => {
    const row = e.target.closest('[data-open]');
    if (row) openRecord(+row.dataset.open);
  });

  load();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
