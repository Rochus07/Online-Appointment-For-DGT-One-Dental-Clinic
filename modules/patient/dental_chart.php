<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

// Patient's read-only view of their dental record (2026-10-18): the same tooth
// chart and notes the dentist keeps, without any edit controls.
$me = checkAuth(['patient']);
// Patient Information Record first (2026-10-20): only My Profile until it is answered.
if (!healthFormDone((int) $me['user_id'])) {
    header('Location: /dgt_dental_clinic/modules/patient/health_form');
    exit;
}
$PAGE_TITLE = 'Dental Records';
$ACTIVE = 'records';
$PAGE_FULL = true;

$inputCls = 'rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-100';

ob_start();
require __DIR__ . '/../../includes/records_chart_style.php';
?>
<style>.pt-clinical { grid-template-columns: repeat(2, minmax(0, 1fr)); } @media (max-width: 560px) { .pt-clinical { grid-template-columns: 1fr; } }</style>
<div class="mb-[22px] flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">Dental Records</h1></div>
    <a id="hfBtn" data-nav href="/dgt_dental_clinic/modules/patient/health_form" class="ml-auto inline-flex items-center gap-1.5 rounded-md border border-steel-200 px-3 py-2 text-[12.5px] font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5">Update Patient Information Record</a>
    <div id="personWrap" hidden>
        <label for="personSel" class="sr-only">Whose records</label>
        <select id="personSel" class="<?= $inputCls ?> min-w-[220px]"></select>
    </div>
</div>
<div id="chartRoot"><p class="py-7 text-center text-steel-400 dark:text-steel-500">Loading&hellip;</p></div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const g = (id) => document.getElementById(id);
  const CARD = 'mb-4 rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800';

  // The dentist's fields a patient may see, in plain labels (shown even when empty).
  // Bite, Gum Condition, Oral Hygiene and Abnormalities are the dentist's clinical
  // findings and stay off the patient's view (2026-10-20).
  const NOTES = [
    // Dentures and allergies removed 2026-10-21: they are in the Patient Information Record.
    ['medical_history', 'Clinical Notes', 'full'],
  ];
  const ROWS = [
    [[18,17,16,15,14,13,12,11], [21,22,23,24,25,26,27,28]],
    [[48,47,46,45,44,43,42,41], [31,32,33,34,35,36,37,38]],
    [[55,54,53,52,51], [61,62,63,64,65]],
    [[85,84,83,82,81], [71,72,73,74,75]],
  ];
  // Conditions come from app.js window.TOOTH_STATUS (2026-10-26).
  const TOOTH_LABEL = Object.fromEntries(window.TOOTH_STATUS.map((s) => [s.key, s.tag]));
  const STATUS_NAME = Object.fromEntries(window.TOOTH_STATUS.map((s) => [s.key, s.label]));
  const BAND = { adult: [['Molars', 3], ['Premolars', 2], ['Canine', 1], ['Front', 2]], baby: [['Molars', 2], ['Canine', 1], ['Front', 2]] };
  const toothInfo = window.toothInfo, toothSvg = window.toothSvg;

  function ageFrom(dob) {
    if (!dob) return null;
    const d = new Date(dob + 'T00:00:00'), n = new Date();
    let a = n.getFullYear() - d.getFullYear();
    if (n.getMonth() < d.getMonth() || (n.getMonth() === d.getMonth() && n.getDate() < d.getDate())) a--;
    return a >= 0 && a < 130 ? a : null;
  }
  function toothCell(t, chart) {
    const st = chart[t] || '';
    const title = 'Tooth ' + t + ': ' + toothInfo(t).label + ' · ' + STATUS_NAME[st];
    return '<div class="tooth-cell"><span class="tooth-num">' + t + '</span>' +
      '<div class="tooth-box' + (st ? ' t-' + st : '') + '" title="' + esc(title) + '" role="img" aria-label="' + esc(title) + '">' +
      toothSvg(t, st) + '<span class="tooth-tag">' + (TOOTH_LABEL[st] || '') + '</span></div></div>';
  }
  function bandRow(kind) {
    const groups = BAND[kind], n = groups.reduce((a, x) => a + x[1], 0);
    const half = (list) => '<div class="tooth-quad tooth-band" style="grid-template-columns:repeat(' + n + ',minmax(0,1fr))">' +
      list.map(([lbl, span]) => '<span style="grid-column:span ' + span + '">' + lbl + '</span>').join('') + '</div>';
    return '<div class="tooth-quad-row tooth-band-row"><span class="tooth-jaw"></span>' + half(groups) + half(groups.slice().reverse()) + '</div>';
  }
  const jawRow = (label, [left, right], chart) => '<div class="tooth-quad-row"><span class="tooth-jaw">' + label + '</span>' +
    [left, right].map((q) => '<div class="tooth-quad">' + q.map((t) => toothCell(t, chart)).join('') + '</div>').join('') + '</div>';
  const section = (title, kind, up, low, chart) => '<div class="tooth-sec' + (kind === 'baby' ? ' is-primary' : '') + '">' +
    '<div class="tooth-sec-head"><span class="tooth-sec-title">' + title + '</span>' +
    '<span class="tooth-sides"><span>&larr; Right side</span><span>Left side &rarr;</span></span></div>' +
    jawRow('Upper', up, chart) + bandRow(kind) + jawRow('Lower', low, chart) + '</div>';

  // All-caps lines (the Patient Information Record's group headings) show in bold.
  const fmtNote = (v) => String(v).split(/\r?\n/).map((l) => /^[A-Z][A-Z ()0-9,]+$/.test(l.trim()) && l.trim().length > 3
    ? '<b class="font-semibold text-navy-900 dark:text-steel-50">' + esc(l) + '</b>' : esc(l)).join(String.fromCharCode(10));

  function render(d) {
    const r = d.record || {}, chart = r.tooth_chart || {}, age = ageFrom(r.date_of_birth);
    // Baby teeth only for children (12 or younger), no birthdate, or a baby tooth marked.
    const baby = age === null || age <= 12 || Object.keys(chart).some((t) => +t >= 51 && +t <= 85 && chart[t]);
    const p = d.patient || {};
    const box = (label, val, cls) => '<div class="rec-field' + (cls ? ' ' + cls : '') + '"><label>' + label + '</label>' +
      '<div class="rec-value whitespace-pre-line">' + (val ? fmtNote(val) : '<span class="text-steel-400">Not recorded</span>') + '</div></div>';
    const cap = (v) => v ? v[0].toUpperCase() + v.slice(1) : '';

    // Patient Information Record (2026-10-20): the latest submitted entry, read-only.
    const logs = d.health_logs || [], hs = d.health_state;
    const hfCard = logs.length
      ? '<div class="' + CARD + '">' +
          '<div class="mb-3 flex flex-wrap items-center gap-2"><h4 class="rec-section !mb-0 !border-0 !pb-0">Patient Information Record</h4>' +
          '<span class="inline-flex items-center gap-1 rounded-full bg-ok-soft px-2.5 py-0.5 text-[11.5px] font-semibold text-ok dark:bg-ok/20">' + (logs[0].by_role === 'dentist' && logs[0].by_name ? 'Updated by Dr. ' + esc(logs[0].by_name) + ' on ' : 'Submitted ') + fmtDate(String(logs[0].submitted_at).slice(0, 10)) + '</span>' +
          (hs && hs.review_from ? '<span class="text-[12px] text-steel-500 dark:text-steel-400">' + (hs.due ? 'Review due now' : 'Next review from ' + fmtDate(hs.review_from)) + '</span>' : '') +
          '</div>' +
          '<div class="max-h-[420px] overflow-y-auto whitespace-pre-line rounded-lg border border-steel-100 bg-steel-50/50 px-4 py-3 text-[13px] leading-relaxed text-navy-800 dark:border-white/10 dark:bg-white/[.03] dark:text-steel-100">' + fmtNote(logs[0].summary) + '</div>' +
        '</div>'
      : '';
    const btn = g('hfBtn');
    if (btn) { btn.style.display = hs ? '' : 'none'; btn.textContent = hs && (hs.due || !hs.done) ? 'Review & Update Patient Information Record' : 'View Patient Information Record'; }

    g('chartRoot').innerHTML =
      '<div class="' + CARD + '">' +
        '<h4 class="rec-section">Personal Information</h4>' +
        '<div class="rec-info-grid">' +
          box('Name', p.full_name) +
          box('Age', age !== null ? age + ' years old' : '') +
          box('Gender', cap(r.gender)) +
          box('Contact Number', p.contact_number) +
          box('Email', p.email) +
          box('Address', r.address) +
          box('Emergency Contact', r.emergency_contact_name) +
          box('Emergency Contact Number', r.emergency_contact_number) +
        '</div>' +
      '</div>' +
      hfCard +
      '<div class="' + CARD + '">' +
        '<h4 class="rec-section">Dental Chart &amp; Clinical Notes</h4>' +
        '<div class="rec-split">' +
          '<div class="rec-clinical pt-clinical">' +
            NOTES.map(([k, label, cls]) => box(label, r[k], cls)).join('') +
          '</div>' +
          '<div class="rec-chart">' +
            '<div class="tooth-board">' +
              section('Adult teeth', 'adult', ROWS[0], ROWS[1], chart) +
              (baby ? section('Baby teeth (children)', 'baby', ROWS[2], ROWS[3], chart) : '') +
            '</div>' +
            '<div class="tooth-status-legend"><strong>Tooth Status:</strong>' +
              window.TOOTH_STATUS.map((st) => '<span' + (st.key ? ' class="t-' + st.key + '"' : '') + '>' + toothSvg(36, st.key) + ' ' + st.legend + '</span>').join('') +
            '</div>' +
          '</div>' +
        '</div>' +
        (r.updated_by_name ? '<p class="mt-3 text-[11px] text-steel-400">Last updated by ' + esc(r.updated_by_name) + ' on ' + fmtDate(String(r.updated_at).slice(0, 10)) + '</p>' : '') +
      '</div>' +
      '';
  }

  async function load(person) {
    try {
      const d = await api('my_record.php' + (person ? '?person=' + person : ''));
      const sel = g('personSel');
      if (d.people.length > 1) {
        sel.innerHTML = d.people.map((p) => '<option value="' + p.id + '"' + (+p.id === +d.person_id ? ' selected' : '') + '>' +
          esc(p.self ? 'My records' : p.full_name) + '</option>').join('');
        g('personWrap').hidden = false;
      }
      render(d);
    } catch (e) {
      g('chartRoot').innerHTML = '<p class="py-7 text-center text-steel-400">' + esc(e.message) + '</p>';
    }
  }
  g('personSel').addEventListener('change', (e) => load(e.target.value));
  load();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
