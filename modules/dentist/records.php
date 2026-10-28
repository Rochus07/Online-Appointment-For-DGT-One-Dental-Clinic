<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['dentist']);
$PAGE_TITLE = 'Patient Records';
$ACTIVE = 'records';
$PAGE_FULL = true;   // full-bleed content column (matches the admin screens)

$inputCls   = 'rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
$btnPrimSm  = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md bg-accent px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-accent-dark';
$tableCls   = 'w-full border-collapse text-[13px] '
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
        <button class="<?= $btnPrimSm ?>" id="createRec">+ Create Record</button>
    </div>
    <div class="overflow-x-auto">
        <table class="rtable rt-plain rt-phone <?= $tableCls ?>" id="recTable">
            <thead><tr><th>#</th><th>Name</th><th>Age</th><th>Contact No.</th><th>Email Address</th></tr></thead>
            <tbody><tr><td colspan="5" class="py-7 text-center text-steel-400">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
    <nav id="recPager" class="mt-4 flex flex-wrap items-center justify-between gap-3" aria-label="Table pages" hidden></nav>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);

  const BTN     = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold';
  const BTN_SM  = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold';
  const PRIMARY = ' bg-accent text-white hover:bg-accent-dark';
  const GHOST   = ' border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
  const OK      = ' bg-ok text-white hover:brightness-95';
  const FG      = 'flex flex-col gap-1.5';
  const FGF     = 'flex flex-col gap-1.5 full';
  const LBL     = 'text-xs font-semibold text-steel-500 dark:text-steel-300';
  const REQ     = '<span class="text-danger"> *</span>';
  const INP     = 'w-full rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
  const BADGE_OFF = 'inline-block rounded-full bg-steel-100 px-2.5 py-[3px] text-[11px] font-bold capitalize text-steel-500 dark:bg-white/10 dark:text-steel-300';
  const TABLE   = 'w-full border-collapse text-[13px] [&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 [&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:dark:border-white/10';
  const EMPTY   = 'py-7 text-center text-steel-400';

  const TEXT_FIELDS = [
    ['occlusion', 'Occlusion'],
    ['periodontal_condition', 'Periodontal Condition'],
    ['oral_hygiene', 'Oral Hygiene'],
    // Dentures and allergies removed 2026-10-21: they are in the Patient Information Record.
    ['abnormalities', 'Abnormalities'],
  ];

  let searchTimer = null;

  function lastFirst(name) {
    const s = String(name || '').trim();
    if (!s) return 'N/A';
    if (s.includes(',')) return s;
    const parts = s.split(/\s+/);
    return parts.length > 1 ? parts[parts.length - 1] + ', ' + parts.slice(0, -1).join(' ') : s;
  }

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
    } catch (e) { tb.innerHTML = '<tr><td colspan="5" class="' + EMPTY + '">' + esc(e.message) + '</td></tr>'; }
  }
  function render() {
    const tb = document.querySelector('#recTable tbody');
    if (!tb) return;
    const { rows: patients, from } = window.pageSlice(ALL, PG, window.fitRows(g('recTable')), g('recPager'), render);
    tb.innerHTML = patients.length ? patients.map((p, i) =>
        '<tr data-open="' + p.id + '" class="cursor-pointer">' +
        '<td class="rt-hide">' + (from + i + 1) + '</td>' +
        '<td class="rt-title"><strong>' + esc(lastFirst(p.full_name)) + '</strong>' +
          (p.has_record == 1 ? '' : ' <span class="' + BADGE_OFF + '">No record</span>') + '</td>' +
        '<td>' + (p.age || 'N/A') + '</td>' +
        '<td>' + esc(p.contact_number || 'N/A') + '</td>' +
        '<td>' + (p.email ? '<a class="text-accent-dark" href="mailto:' + esc(p.email) + '">' + esc(p.email) + '</a>' : 'N/A') + '</td></tr>'
      ).join('') : '<tr><td colspan="5" class="' + EMPTY + '">No patients found.</td></tr>';
  }
  clearTimeout(window.__recFitT);
  window.removeEventListener('resize', window.__recFit || (() => {}));
  window.__recFit = () => { clearTimeout(window.__recFitT); window.__recFitT = setTimeout(() => { if (document.getElementById('recTable')) render(); }, 150); };
  window.addEventListener('resize', window.__recFit);

  function ageFrom(dob) {
    if (!dob) return 'N/A';
    const d = new Date(dob + 'T00:00:00'), n = new Date();
    let a = n.getFullYear() - d.getFullYear();
    if (n.getMonth() < d.getMonth() || (n.getMonth() === d.getMonth() && n.getDate() < d.getDate())) a--;
    return a >= 0 && a < 130 ? a : 'N/A';
  }

  // Tooth chart (2026-10-18; per-tooth pop-up 2026-10-26, migration 091): the
  // board, legend, tooth pop-up and chart state live in app.js (shared with the
  // chart step before Perform Procedure). Click a tooth to set its condition,
  // quick tags and a note; while not editing, the click shows its records.
  const toothSvg = window.toothSvg;
  let showBaby = true;
  function toothBoard(S, editing) {
    return window.toothBoardHtml(S.chart, { clickable: true, baby: showBaby, info: S.info, pending: S.pending,
      hint: editing ? 'Click a tooth to set its condition, add quick tags or a note.' : '' });
  }

  /* ---------- One-time sign-in details for a new patient ---------- */
  function showCreds(name, res) {
    const CHIP = 'rounded-lg border border-steel-200 bg-steel-50 px-4 py-3 dark:border-white/15 dark:bg-white/5';
    openModal(modalShell('Online login for ' + esc(name),
      '<p class="text-[13px] text-steel-500">Give these sign-in details to the patient. The temporary password is shown only once. They use it to book online and must set their own password on first login.</p>' +
      '<div class="mt-3 grid gap-2 sm:grid-cols-2">' +
        '<div class="' + CHIP + '"><p class="text-[11px] font-semibold uppercase tracking-wide text-steel-500">Username</p><p class="font-mono text-lg font-bold text-navy-900 dark:text-steel-50">' + esc(res.username) + '</p></div>' +
        '<div class="' + CHIP + '"><p class="text-[11px] font-semibold uppercase tracking-wide text-steel-500">Temporary Password</p><p class="font-mono text-lg font-bold text-navy-900 dark:text-steel-50">' + esc(res.temp_password) + '</p></div>' +
      '</div>',
      '<button class="' + BTN + GHOST + '" data-close>Done</button><button class="' + BTN + PRIMARY + '" id="cr_open">Open record</button>'));
    g('cr_open').addEventListener('click', () => { closeModal(); openRecord(res.patient_id); });
  }

  /* ---------- Create a new (walk-in) patient ---------- */
  function createModal() {
    openModal(modalShell('Create Patient Record',
      '<div class="grid grid-cols-2 gap-3.5">' +
      '<div class="' + FG + '"><label class="' + LBL + '">First Name' + REQ + '</label><input class="' + INP + '" id="c_first"></div>' +
      '<div class="' + FG + '"><label class="' + LBL + '">Last Name' + REQ + '</label><input class="' + INP + '" id="c_last"></div>' +
      '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Date of Birth' + REQ + '</label>' + dobSelectsHtml('c_dob', '', INP) +
        '<span class="text-[11px] text-steel-400 dark:text-steel-500" id="c_dobAge"></span></div>' +
      '<div class="' + FG + '"><label class="' + LBL + '">Gender</label><select class="' + INP + '" id="c_gender">' +
        '<option value="">Select</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option>' +
      '</select></div>' +
      '<div class="' + FG + '"><label class="' + LBL + '">Contact No.' + REQ + '</label><input class="' + INP + '" id="c_contact" type="tel" inputmode="numeric"></div>' +
      '<div class="' + FG + ' col-span-2"><label class="' + LBL + '">Email Address' + REQ + '</label><input class="' + INP + '" type="email" id="c_email"></div>' +
      '</div>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + PRIMARY + '" id="c_go">Create Record</button>'));
    wirePhoneField(g('c_contact'), { submitBtn: g('c_go') });
    wireDobSelects('c_dob');
    g('c_dob').addEventListener('input', () => {
      const a = ageFrom(g('c_dob').value);
      g('c_dobAge').textContent = a !== 'N/A' && a !== '' ? 'Age: ' + a : '';
    });
    g('c_go').addEventListener('click', async () => {
      const first = g('c_first').value.trim(), last = g('c_last').value.trim();
      const email = g('c_email').value.trim();
      if (!first || !last) return toast('Enter the first and last name.', 'err');
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return toast('Enter the patient’s e-mail address. It is needed for password reset.', 'err');
      if (!g('c_contact')._phoneOk(false)) return toast('Enter a valid contact number. It is required. ' + PH_MOBILE_MSG, 'err');
      const dob = g('c_dob').value;
      if (!/^\d{4}-\d{2}-\d{2}$/.test(dob) || dob > todayISO()) return toast('Enter the patient’s date of birth.', 'err');
      try {
        const res = await api('records.php?action=create', { method: 'POST', body: {
          first_name: first, last_name: last,
          date_of_birth: dob, gender: g('c_gender').value,
          contact_number: g('c_contact').value.trim(), email,
        }});
        closeModal(); toast('Patient record created.'); load();
        showCreds(first + ' ' + last, res);
      } catch (e) { toast(e.message, 'err'); }
    });
  }

  /* ---------- Patient Details ---------- */
  async function openRecord(pid) {
    let data;
    try { data = await api('records.php?action=get&patient_id=' + pid); }
    catch (e) { return toast(e.message, 'err'); }
    const p = data.patient;
    const r = data.record || {};
    let TS = window.toothChartState(r.tooth_chart || {}, data.tooth_records || []);
    showBaby = window.toothWantsBaby(r.date_of_birth, TS.chart, TS.records);
    let editing = false;

    const field = (id, label, val, type) =>
      '<div class="' + FG + (id === 'f_abnormalities' ? ' rec-span2' : '') + '"><label class="' + LBL + '">' + label + '</label>' +
      (type === 'textarea'
        ? '<textarea class="' + INP + ' min-h-[72px] resize-y" id="' + id + '" disabled>' + esc(val || '') + '</textarea>'
        : '<input class="' + INP + '" id="' + id + '" ' + (type ? 'type="' + type + '" ' : '') + 'value="' + esc(val || '') + '" disabled>') +
      '</div>';

    // Appointment history: 5 visits per page (2026-10-17).
    const HIST = data.history || [], HPG = { page: 1 };
    const histRow = (h) =>
          '<tr data-view="' + h.appointment_id + '" tabindex="0" title="View visit details" class="cursor-pointer hover:bg-dgt-blue-50 dark:hover:bg-white/5"><td>' + fmtDate(h.scheduled_date) + '</td><td>' + esc(h.procedures || 'N/A') + '</td>' +
          '<td>' + esc(h.dentist_name || 'N/A') + '</td><td>' + badge(h.status) + '</td>' +
          '<td>' + (h.total_amount ? peso(h.total_amount) : 'N/A') + '</td></tr>';
    function drawHist() {
      const tb = document.getElementById('histBody');
      if (!tb) return;
      const { rows } = window.pageSlice(HIST, HPG, 5, document.getElementById('histPager'), drawHist);
      tb.innerHTML = rows.length ? rows.map(histRow).join('') : '<tr><td colspan="5" class="' + EMPTY + '">No visits recorded.</td></tr>';
      // Rows open the visit (data-view, global handler in app.js); Enter / Space too.
      tb.querySelectorAll('tr[data-view]').forEach((tr) => tr.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); tr.click(); }
      }));
    }

    // Patient Information Record (2026-10-20): the patient's submitted answers,
    // read-only, one entry per submission (newest first). Dentist changes go
    // in the fields below, never into these entries.
    const LOGS = data.health_logs || [];
    const hfText = (v) => String(v || '').split(/\r?\n/).map((l) => /^[A-Z][A-Z ()0-9,]+$/.test(l.trim()) && l.trim().length > 3
      ? '<b class="font-semibold text-navy-900 dark:text-steel-50">' + esc(l) + '</b>' : esc(l)).join(String.fromCharCode(10));
    const hfWhen = (dt) => fmtDate(String(dt).slice(0, 10));
    // Who saved an entry: the patient, or a dentist who updated it (2026-10-26).
    const hfBy = (l) => l.by_role === 'dentist' && l.by_name ? 'updated by Dr. ' + l.by_name : 'by the patient';
    // Edit Record turns the entry into a text box the dentist types in (2026-10-26);
    // a never-answered record still opens the question form (Fill In Record).
    const HF_BTN = 'inline-flex items-center gap-1.5 rounded-md border border-dgt-blue-500 px-2.5 py-1 text-[12px] font-semibold text-dgt-blue-600 hover:bg-dgt-blue-50 dark:text-dgt-blue-300 dark:hover:bg-white/5';
    const HF_PEN = '<svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>';
    const hfEdit = LOGS.length
      ? '<button type="button" id="hfEditBtn" class="' + HF_BTN + '">' + HF_PEN + 'Edit Record</button>'
      : '<a href="/dgt_dental_clinic/modules/patient/health_form?patient=' + p.user_id + '" data-nav data-close class="' + HF_BTN + '">' + HF_PEN + 'Fill In Record</a>';
    const hfCard = () => '<div class="mt-4 rounded-xl border border-steel-100 bg-steel-50/50 p-4 dark:border-white/10 dark:bg-white/[.03]">'
      + '<div class="mb-3 flex flex-wrap items-center gap-2">'
      + '<h4 class="text-[14px] font-semibold text-navy-900 dark:text-steel-50">Patient Information Record</h4>'
      + (LOGS.length ? '<span class="inline-flex items-center gap-1 rounded-full bg-ok-soft px-2.5 py-0.5 text-[11.5px] font-semibold text-ok dark:bg-ok/20">'
        + '<svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>Submitted · Locked</span>' : '')
      + (LOGS.length > 1 ? '<label class="ml-auto flex items-center gap-2 text-[12px] text-steel-500 dark:text-steel-400">Entry'
        + '<select id="hfLogSel" class="' + INP + ' !w-auto !py-1">' + LOGS.map((l, i) => '<option value="' + i + '">' + hfWhen(l.submitted_at) + ' · ' + esc(hfBy(l)) + (i === 0 ? ' (latest)' : '') + '</option>').join('') + '</select></label>'
        : (LOGS.length ? '<span class="ml-auto text-[12px] text-steel-500 dark:text-steel-400">Submitted ' + hfWhen(LOGS[0].submitted_at) + ' ' + esc(hfBy(LOGS[0])) + '</span>' : ''))
      + hfEdit
      + '</div>'
      + (LOGS.length
        ? '<div id="hfLogBody" class="max-h-[420px] overflow-y-auto whitespace-pre-line rounded-lg border border-steel-100 bg-white px-4 py-3 text-[13px] leading-relaxed text-navy-800 dark:border-white/10 dark:bg-navy-900 dark:text-steel-100">' + hfText(LOGS[0].summary) + '</div>'
          + '<div id="hfEditWrap" hidden><textarea id="hfEditText" class="' + INP + ' min-h-[320px] font-normal leading-relaxed" spellcheck="true"></textarea>'
          + '<div class="mt-2 flex justify-end gap-2"><button type="button" id="hfEditCancel" class="' + BTN + GHOST + '">Cancel</button>'
          + '<button type="button" id="hfEditSave" class="' + BTN + PRIMARY + '">Save Record</button></div></div>'
          + '<p id="hfHint" class="mt-2 text-[11.5px] text-steel-500 dark:text-steel-400">Found something new, like an allergy? Click Edit Record and type it in: your changes are saved as a new entry and the earlier ones are kept.</p>'
        : '<p class="text-[13px] text-steel-500 dark:text-steel-400">The patient has not answered it yet.</p>')
      + '</div>';

    const infoBox = (label, val) =>
      '<div class="rec-field"><label>' + label + '</label><div class="rec-value">' + (esc(val) || '&nbsp;') + '</div></div>';

    openModal(modalShell('Patient Details: ' + esc(p.full_name),
      '<h4 class="rec-section">Personal Information</h4>' +
      '<div class="rec-info-grid">' +
      infoBox('Name', p.full_name) +
      infoBox('Age', ageFrom(r.date_of_birth)) +
      infoBox('Contact Number', p.contact_number || 'N/A') +
      infoBox('Email', p.email || 'N/A') +
      infoBox('Emergency Contact', r.emergency_contact_name || 'Not provided') +
      infoBox('Emergency Contact Number', r.emergency_contact_number || 'Not provided') +
      '</div>' +
      hfCard() +
      '<h4 class="rec-section" style="margin:16px 0 12px">Dental Chart &amp; Clinical Notes</h4>' +
      '<div class="rec-split">' +
      '<div class="rec-clinical">' +
      TEXT_FIELDS.filter(([id]) => id !== 'abnormalities').map(([id, label]) => field('f_' + id, label, r[id])).join('') +
      // Age is shown in Personal Information above (2026-10-17).
      '<div class="' + FG + '"><label class="' + LBL + '">Gender</label><select class="' + INP + '" id="f_gender" disabled>' +
        ['', 'male', 'female', 'other'].map((v) => '<option value="' + v + '"' + (r.gender === v ? ' selected' : '') + '>' + (v ? v[0].toUpperCase() + v.slice(1) : 'N/A') + '</option>').join('') +
      '</select></div>' +
      // Abnormalities fills the 2 columns beside Gender (2026-10-21; full row on smaller screens).
      field('f_abnormalities', 'Abnormalities', r.abnormalities) +
      '<div class="' + FGF + '"><label class="' + LBL + '">Clinical Notes</label><textarea class="' + INP + ' min-h-[200px] resize-y" id="f_medical_history" disabled placeholder="Your notes from the exam, e.g. an answer the patient clarified">' + esc(r.medical_history || '') + '</textarea></div>' +
      '</div>' +
      '<div class="rec-chart">' +
      '<div id="toothChart">' + toothBoard(TS, false) + '</div>' +
      window.toothLegendHtml() +
      '<details class="rounded-lg border border-steel-100 px-3 py-2 dark:border-white/10"' + (TS.records.length ? '' : ' hidden') + '>' +
        '<summary class="cursor-pointer text-[13px] font-semibold text-navy-900 dark:text-steel-50">Tooth Records by Visit (' + TS.records.length + ')</summary>' +
        '<div class="mt-2 max-h-[360px] overflow-y-auto">' + window.toothHistoryHtml(TS.records, true) + '</div></details>' +
      '</div>' +
      '</div>' +
      '<hr class="my-4 border-0 border-t border-steel-100 dark:border-white/10"><h4 class="rec-section">Treatment History</h4>' +
      '<div class="overflow-x-auto"><table class="rtable rt-plain rt-phone ' + TABLE + '"><thead><tr><th>Date</th><th>Procedures</th><th>Dentist</th><th>Status</th><th>Total</th></tr></thead><tbody id="histBody"></tbody></table></div><nav id="histPager" class="mt-3 flex flex-wrap items-center justify-between gap-3" aria-label="Appointment history pages" hidden></nav>' +
      (r.updated_by_name ? '<p class="mt-2.5 text-[11px] text-steel-400">Last updated by ' + esc(r.updated_by_name) + ' on ' + new Date(String(r.updated_at).replace(' ', 'T')).toLocaleString() + '</p>' : '')),
      { wide: true });
    drawHist();

    // "Edit Dental Info" / "Save Changes" + "Cancel" sit on the modal header line.
    const mHead = document.querySelector('#modalRoot .modal-head');
    const headActions = document.createElement('div');
    headActions.className = 'modal-head-actions';
    headActions.innerHTML =
      '<button class="' + BTN_SM + PRIMARY + '" id="rec_edit">Edit Dental Info</button>' +
      '<button class="' + BTN_SM + OK + '" id="rec_save" style="display:none">Save Changes</button>' +
      '<button class="' + BTN_SM + GHOST + '" id="rec_cancelEdit" style="display:none">Cancel</button>';
    mHead.insertBefore(headActions, mHead.querySelector('.modal-close'));

    // Treatment History rows open the visit (data-view, global handler in app.js);
    // Enter / Space do the same for keyboard users.
    document.querySelectorAll('#modalRoot tr[data-view]').forEach((tr) => tr.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); tr.click(); }
    }));

    const inputs = () => document.querySelectorAll('#modalRoot .rec-split input, #modalRoot .rec-split select, #modalRoot .rec-split textarea');
    const chartWrap = g('toothChart');

    function setEditing(on) {
      editing = on;
      inputs().forEach((el) => (el.disabled = !on));
      g('rec_edit').style.display = on ? 'none' : '';
      g('rec_save').style.display = on ? '' : 'none';
      g('rec_cancelEdit').style.display = on ? '' : 'none';
      chartWrap.innerHTML = toothBoard(TS, on);
    }

    function resetFields() {
      g('f_gender').value = r.gender || '';
      TEXT_FIELDS.forEach(([id]) => (g('f_' + id).value = r[id] || ''));
      g('f_medical_history').value = r.medical_history || '';
      fitHistory();
    }
    // Medical History grows to show the whole Patient Information Record summary (200px to 640px).
    function fitHistory() {
      const t = g('f_medical_history');
      if (!t) return;
      t.style.height = 'auto';
      t.style.height = Math.min(640, Math.max(200, t.scrollHeight + 4)) + 'px';
    }
    requestAnimationFrame(fitHistory);
    const hfSel = g('hfLogSel');
    if (hfSel) hfSel.addEventListener('change', () => { g('hfLogBody').innerHTML = hfText(LOGS[+hfSel.value].summary); });
    const hfBtn = g('hfEditBtn');
    if (hfBtn) {
      const hfMode = (on) => {
        g('hfLogBody').hidden = on;
        g('hfEditWrap').hidden = !on;
        g('hfHint').hidden = on;
        hfBtn.style.display = on ? 'none' : '';
        if (hfSel) hfSel.disabled = on;
      };
      hfBtn.addEventListener('click', () => {
        const t = g('hfEditText');
        t.value = String(LOGS[0].summary || '');
        hfMode(true);
        t.style.height = 'auto';
        t.style.height = Math.min(640, Math.max(320, t.scrollHeight + 4)) + 'px';
        t.focus();
      });
      g('hfEditCancel').addEventListener('click', () => hfMode(false));
      g('hfEditSave').addEventListener('click', async () => {
        const text = g('hfEditText').value.trim();
        if (!text) return toast('The record cannot be empty.', 'err');
        try {
          await api('health_form.php?action=save_text', { method: 'POST', body: { patient_id: p.user_id, summary: text } });
          toast('Patient Information Record updated.', 'ok');
          openRecord(p.user_id);
        } catch (e) { toast(e.message, 'err'); }
      });
    }

    g('rec_edit').addEventListener('click', () => setEditing(true));
    g('rec_cancelEdit').addEventListener('click', () => {
      TS = window.toothChartState(r.tooth_chart || {}, data.tooth_records || []);
      resetFields();
      setEditing(false);
    });

    chartWrap.addEventListener('click', (e) => {
      const b = e.target.closest('.tooth-box');
      if (b) TS.open(b.dataset.tooth, !editing, () => { chartWrap.innerHTML = toothBoard(TS, true); });
    });

    g('rec_save').addEventListener('click', async () => {
      const body = {
        patient_id: pid,
        gender: g('f_gender').value,
        medical_history: g('f_medical_history').value.trim(),
        tooth_entries: TS.entries(),
      };
      TEXT_FIELDS.forEach(([id]) => (body[id] = g('f_' + id).value.trim()));
      try {
        await api('records.php?action=save', { method: 'POST', body });
        toast('Record saved.');
        closeModal();
        openRecord(pid);
      } catch (e) { toast(e.message, 'err'); }
    });
  }

  g('recSearch').addEventListener('input', () => { clearTimeout(searchTimer); PG.page = 1; searchTimer = setTimeout(load, 250); });
  g('createRec').addEventListener('click', createModal);
  document.querySelector('#recTable').addEventListener('click', (e) => {
    const row = e.target.closest('[data-open]');
    if (row) openRecord(+row.dataset.open);
  });

  load();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
