<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/health_form.php';

// Patient Information Record (2026-10-20): the required health questionnaire a
// patient answers before booking. Saving fills in their dental record.
// A dentist opens it with ?patient=<id> (2026-10-26) to update the answers,
// e.g. an allergy found during the exam: always editable, saved as a new entry.
$me = checkAuth(['patient', 'dentist']);
$staff = $me['role'] === 'dentist';
$pid = $staff ? (int) ($_GET['patient'] ?? 0) : (int) $me['user_id'];
$patientName = '';
if ($staff) {
    $pq = getDB()->prepare("SELECT full_name FROM users WHERE user_id = ? AND role = 'patient'");
    $pq->execute([$pid]);
    $patientName = (string) ($pq->fetchColumn() ?: '');
    if ($patientName === '') {
        header('Location: /dgt_dental_clinic/modules/dentist/records');
        exit;
    }
}
$PAGE_TITLE = 'Patient Information Record';
$ACTIVE = 'records';
$PAGE_FULL = true;
$hfState = healthFormState($pid);
$firstTime = !$hfState['done'] && !$staff;
// first = never answered; locked = submitted, read-only until the review date;
// due = the review (every HF_REVIEW_MONTHS) has come, so it opens again for a new entry.
$hfMode = $staff ? 'staff' : ($firstTime ? 'first' : ($hfState['due'] ? 'due' : 'locked'));
$hfLast = $hfState['at'] ? date('M j, Y', strtotime($hfState['at'])) : '';
$hfNext = $hfState['review_from'] ? date('M j, Y', strtotime($hfState['review_from'])) : '';

ob_start();
?>
<style>
  /* Field the patient was sent to by "Save" when something is missing. */
  .hf-miss { box-shadow: 0 0 0 2px #d98a10; border-radius: 10px; }
  .hf-sec { scroll-margin-top: 84px; }
</style>
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">Patient Information Record<?php if ($staff): ?>: <?= e($patientName) ?><?php endif; ?></h1>
    <?php if ($hfMode !== 'locked'): ?><p class="text-[12.5px] text-steel-500 dark:text-steel-400">Fields marked <span class="text-danger">*</span> are required</p><?php endif; ?>
</div>
<?php if ($hfMode === 'locked'): ?>
<div class="mb-5 flex items-center gap-3 rounded-xl border border-ok/30 bg-ok-soft px-4 py-3.5 text-[13px] text-navy-800 dark:bg-ok/15 dark:text-steel-100">
    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-ok text-white">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
    </span>
    <span><b class="font-semibold text-navy-900 dark:text-steel-50">Submitted on <?= e($hfLast) ?> and locked.</b> If something has changed, tell your dentist at your visit. You will be asked to review your answers again from <?= e($hfNext) ?>.</span>
</div>
<?php elseif ($hfMode === 'staff'): ?>
<div class="mb-5 flex items-center gap-3 rounded-xl border border-dgt-blue-200 bg-dgt-blue-50 px-4 py-3.5 text-[13px] text-navy-800 dark:border-dgt-blue-500/30 dark:bg-dgt-blue-500/10 dark:text-steel-100">
    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-dgt-blue-600 text-white">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
    </span>
    <span><b class="font-semibold text-navy-900 dark:text-steel-50">You are updating <?= e($patientName) ?>'s record.</b> <?= $hfState['done'] ? 'Their latest answers (' . e(date('M j, Y', strtotime($hfState['at']))) . ') are filled in.' : 'They have not answered it yet.' ?> Your changes are saved as a new entry under your name; the earlier entries are kept.</span>
</div>
<?php elseif ($hfMode === 'due'): ?>
<div class="mb-5 flex items-center gap-3 rounded-xl border border-warn/40 bg-warn-soft px-4 py-3.5 text-[13px] text-navy-800 dark:bg-warn/15 dark:text-steel-100">
    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-warn text-white">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/></svg>
    </span>
    <span><b class="font-semibold text-navy-900 dark:text-steel-50">Time to review your record.</b> Your last answers are from <?= e($hfLast) ?>. Check each one, change anything that is different now, then submit. Your previous entry is kept.</span>
</div>
<?php endif; ?>
<?php if ($firstTime): ?>
<div class="mb-5 flex items-center gap-3 rounded-xl border border-dgt-teal-200 bg-dgt-teal-50 px-4 py-3.5 text-[13px] text-navy-800 dark:border-dgt-teal-500/30 dark:bg-dgt-teal-500/10 dark:text-steel-100">
    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-dgt-teal-600 text-white">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/></svg>
    </span>
    <span><b class="font-semibold text-navy-900 dark:text-steel-50">One step before your first booking.</b> Your dentist uses these answers to treat you safely, and they are saved to your Dental Records. It takes about 5 minutes.</span>
</div>
<?php endif; ?>
<div id="hfRoot"><p class="py-7 text-center text-steel-400 dark:text-steel-500">Loading&hellip;</p></div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = str_replace(
    ['__DEF__', '__FIRST__', '__MODE__', '__PATIENT__'],
    [json_encode(healthFormDefinition(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG), $firstTime ? 'true' : 'false', json_encode($hfMode), $staff ? (string) $pid : '0'],
    <<<'JS'
(function () {
  const DEF = __DEF__;
  const FIRST = __FIRST__;
  const MODE = __MODE__;   // 'first' | 'locked' | 'due' | 'staff' (a dentist updating it)
  const PATIENT = __PATIENT__;   // the patient a dentist is updating, else 0
  const STAFF = MODE === 'staff';
  const g = (id) => document.getElementById(id);
  const root = g('hfRoot');
  if (!root) return;

  const CARD = 'hf-sec rounded-xl border border-steel-100 bg-white shadow-sm dark:border-white/10 dark:bg-navy-800';
  const LBL = 'mb-1 block text-[12.5px] font-medium text-steel-600 dark:text-steel-300';
  const INP = 'w-full rounded-lg border border-steel-200 bg-white px-3 py-2.5 text-[13.5px] text-navy-800 outline-none focus:border-dgt-blue-500 focus:ring-2 focus:ring-dgt-blue-500/15 dark:border-white/15 dark:bg-navy-900 dark:text-steel-100';
  const REQ = '<span class="text-danger"> *</span>';

  const ico = (p) => '<svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>';
  const ICONS = {
    personal: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    dental: '<path d="M7 3C4.5 3 3 5 3 7.5c0 3 1.5 4.5 2 7 .5 3 1 6.5 2.5 6.5S9 17 10 15c.5-1 1.5-1 2 0 1 2 1 6 2.5 6s2-3.5 2.5-6.5c.5-2.5 2-4 2-7C19 5 17.5 3 15 3c-2 0-2 1-4 1S9 3 7 3z"/>',
    status: '<path d="M3 12h4l2-5 4 10 2-5h6"/>',
    habits: '<path d="M17 8h1a4 4 0 0 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4z"/><path d="M7 2v2M11 2v2"/>',
    allergies: '<path d="M12 3l9 16H3z"/><path d="M12 10v4M12 17h.01"/>',
    women: '<circle cx="12" cy="9" r="5"/><path d="M12 14v7M9 18h6"/>',
    blood: '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/>',
    conditions: '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4h6v3H9zM9 12h6M9 16h4"/>',
    confirm: '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
  };
  const SECTIONS = [
    ['personal', 'Personal & Emergency Contact'], ['dental', 'Dental History'], ['status', 'General Medical Status'],
    ['habits', 'Habits'], ['allergies', 'Allergies'], ['women', 'For Women Only'], ['blood', 'Blood'],
    ['conditions', 'Medical Conditions'], ['confirm', 'Confirm'],
  ];
  const CHECK_SVG = (w) => '<svg class="' + w + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>';

  // Small inline icons for fields and buttons (24 viewBox, currentColor).
  const FI = {
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
    tooth: ICONS.dental,
    pen: '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13 7l4 4"/>',
    male: '<circle cx="10" cy="14" r="6"/><path d="M14.5 9.5L20 4M15 4h5v5"/>',
    female: '<circle cx="12" cy="9" r="6"/><path d="M12 15v6M9 18h6"/>',
    yes: '<path d="M5 12l5 5 9-10"/>',
    no: '<path d="M6 6l12 12M18 6L6 18"/>',
  };
  const svg = (k, cls) => '<svg class="' + cls + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + FI[k] + '</svg>';
  // Puts an icon inside the left edge of an <input> / <select>.
  const withIco = (k, field) => '<div class="relative"><span class="pointer-events-none absolute left-3 top-[13px] text-steel-400 dark:text-steel-500">' + svg(k, 'h-4 w-4') + '</span>'
    + field.replace('class="' + INP + '"', 'class="' + INP + ' pl-9"') + '</div>';

  // Each section's icon chip has its own colour (2026-10-20, user request).
  const SEC_TINT = {
    personal:   'bg-dgt-blue-50 text-dgt-blue-600 dark:bg-dgt-blue-500/15 dark:text-dgt-blue-300',
    dental:     'bg-dgt-teal-50 text-dgt-teal-600 dark:bg-dgt-teal-500/15 dark:text-dgt-teal-300',
    status:     'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300',
    habits:     'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
    allergies:  'bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-300',
    women:      'bg-pink-50 text-pink-600 dark:bg-pink-500/15 dark:text-pink-300',
    blood:      'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-300',
    conditions: 'bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300',
    confirm:    'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
  };

  function card(key, title, hint, body) {
    return '<section class="' + CARD + '" id="hf_sec_' + key + '"' + (key === 'women' ? ' hidden' : '') + '>'
      + '<header class="flex items-center gap-3 border-b border-steel-100 px-5 py-3.5 dark:border-white/10">'
      + '<span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg ' + SEC_TINT[key] + '">' + ico(ICONS[key]) + '</span>'
      + '<div class="min-w-0 flex-1"><h2 class="text-[15px] font-semibold text-navy-900 dark:text-steel-50">' + title + '</h2>'
      + (hint ? '<p class="text-[12px] text-steel-500 dark:text-steel-400">' + hint + '</p>' : '') + '</div>'
      + '<span data-sec-badge="' + key + '" class="hidden shrink-0 items-center gap-1 rounded-full bg-ok-soft px-2.5 py-1 text-[11.5px] font-semibold text-ok dark:bg-ok/20">'
      + CHECK_SVG('h-3 w-3') + 'Done</span>'
      + '</header><div class="p-5">' + body + '</div></section>';
  }

  // A Yes / No question tile; detailQ adds a short answer box shown on "Yes".
  function yesNo(key, q, detailQ) {
    const opt = (v, t, on) => '<label class="relative inline-flex min-w-[64px] cursor-pointer select-none items-center justify-center rounded-lg border border-steel-200 bg-white px-3.5 py-1.5 text-[13px] font-medium text-navy-700 hover:border-steel-300 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-dgt-blue-500/40 dark:border-white/15 dark:bg-navy-900 dark:text-steel-100 ' + on + '">'
      + '<input type="radio" name="hf_' + key + '" value="' + v + '" class="sr-only">' + svg(v, 'mr-1 h-3.5 w-3.5') + t + '</label>';
    return '<div class="flex flex-col rounded-lg border border-steel-100 bg-steel-50/60 p-3.5 has-[input:checked]:border-steel-200 has-[input:checked]:bg-white dark:border-white/10 dark:bg-white/[.03] dark:has-[input:checked]:border-white/20 dark:has-[input:checked]:bg-transparent" data-yn="' + key + '">'
      + '<div class="flex items-start justify-between gap-3"><span class="pt-1 text-[13.5px] leading-snug text-navy-800 dark:text-steel-100">' + esc(q) + REQ + '</span>'
      + '<span class="flex shrink-0 gap-1.5">'
      + opt('yes', 'Yes', 'has-[:checked]:border-dgt-teal-600 has-[:checked]:bg-dgt-teal-600 has-[:checked]:text-white dark:has-[:checked]:border-dgt-teal-500 dark:has-[:checked]:bg-dgt-teal-500 dark:has-[:checked]:text-white')
      + opt('no', 'No', 'has-[:checked]:border-dgt-blue-900 has-[:checked]:bg-dgt-blue-900 has-[:checked]:text-white dark:has-[:checked]:border-steel-300 dark:has-[:checked]:bg-steel-300 dark:has-[:checked]:text-navy-900')
      + '</span></div>'
      + (detailQ ? '<div class="mt-3" id="hf_' + key + '_wrap" hidden><label class="' + LBL + '" for="hf_' + key + '_detail">' + esc(detailQ) + REQ + '</label>'
        + withIco('pen', '<input id="hf_' + key + '_detail" maxlength="200" class="' + INP + '">') + '</div>' : '')
      + '</div>';
  }
  // Yes / No questions stack one per row (2026-10-20: the user preferred this to two side by side).
  const tiles = (html) => '<div class="grid gap-3">' + html + '</div>';

  // Checklist item; CHIP_B has no align-items so callers pick one.
  const CHIP_B = 'flex cursor-pointer gap-2.5 rounded-lg border border-steel-200 px-3 py-2.5 text-[13px] text-navy-800 hover:border-steel-300 dark:border-white/15 dark:text-steel-100';
  const CHIP = CHIP_B + ' items-start has-[:checked]:border-dgt-blue-500 has-[:checked]:bg-dgt-blue-50 has-[:checked]:text-dgt-blue-800 dark:has-[:checked]:bg-dgt-blue-500/15 dark:has-[:checked]:text-dgt-blue-100';
  const OK_CHIP = CHIP_B + ' items-center has-[:checked]:border-ok has-[:checked]:bg-ok-soft has-[:checked]:text-navy-900 dark:has-[:checked]:bg-ok/15 dark:has-[:checked]:text-steel-50';
  const chips = (name, items, cols) => '<div class="grid gap-2 ' + cols + '">'
    + items.map((t) => '<label class="' + CHIP + '"><input type="checkbox" name="' + name + '" value="' + esc(t) + '" class="mt-0.5 h-4 w-4 shrink-0 accent-dgt-blue-500"> <span>' + esc(t) + '</span></label>').join('') + '</div>';
  // "Other" box + a "None" toggle under a checklist.
  const extra = (otherId, otherLbl, ph, max, noneId, noneLbl) => '<div class="mt-4 grid items-end gap-3 border-t border-steel-100 pt-4 sm:grid-cols-2 dark:border-white/10">'
    + '<div><label class="' + LBL + '" for="' + otherId + '">' + otherLbl + '</label>' + withIco('pen', '<input id="' + otherId + '" maxlength="' + max + '" placeholder="' + ph + '" class="' + INP + '">') + '</div>'
    + '<label class="' + OK_CHIP + '"><input type="checkbox" id="' + noneId + '" class="h-4 w-4 shrink-0 accent-ok"> <span class="font-medium">' + noneLbl + '</span></label></div>';
  // No left icon on selects: the browser indents the open option list by the select's padding.
  const select = (id, label, opts) => '<div><label class="' + LBL + '" for="' + id + '">' + label + REQ + '</label>'
    + '<select id="' + id + '" class="' + INP + '"><option value="">Choose…</option>'
    + opts.map((v) => '<option>' + esc(v) + '</option>').join('') + '</select></div>';
  const sexOpt = (v, t) => '<label class="relative flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-lg border border-steel-200 px-4 py-2.5 text-[13.5px] font-medium text-navy-800 hover:border-steel-300 has-[:checked]:border-dgt-blue-500 has-[:checked]:bg-dgt-blue-50 has-[:checked]:text-dgt-blue-800 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-dgt-blue-500/40 dark:border-white/15 dark:text-steel-100 dark:has-[:checked]:bg-dgt-blue-500/15 dark:has-[:checked]:text-dgt-blue-100">'
    + '<input type="radio" name="hf_sex" value="' + v + '" class="sr-only">' + svg(v, 'h-4 w-4') + t + '</label>';
  const bar = '<div class="mt-2 h-2 overflow-hidden rounded-full bg-steel-100 dark:bg-white/10"><div data-bar class="h-full w-0 rounded-full bg-dgt-teal-600 transition-[width] duration-300"></div></div>';

  function render(prefill) {
    const nav = SECTIONS.map(([k, t]) => '<li' + (k === 'women' ? ' hidden' : '') + ' data-nav-sec="' + k + '"><a href="#hf_sec_' + k + '" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] text-steel-600 hover:bg-steel-50 dark:text-steel-300 dark:hover:bg-white/5">'
      + '<span data-dot class="grid h-5 w-5 shrink-0 place-items-center rounded-full border-2 border-steel-200 dark:border-white/20"></span><span>' + t + '</span></a></li>').join('');

    root.innerHTML = '<div class="grid gap-5 xl:grid-cols-[250px_minmax(0,1fr)]">'
      + '<aside class="hidden xl:block"><div class="sticky top-[84px] rounded-xl border border-steel-100 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-navy-800">'
      + '<p class="text-[11px] font-semibold uppercase tracking-wider text-steel-500 dark:text-steel-400">Your progress</p>'
      + '<p class="mt-1 text-[22px] font-bold text-navy-900 dark:text-steel-50"><span data-pct>0</span>%</p>' + bar
      + '<ul class="mt-4 space-y-0.5">' + nav + '</ul></div></aside>'

      + '<div class="min-w-0 space-y-5">'
      + '<div class="rounded-xl border border-steel-100 bg-white p-4 shadow-sm xl:hidden dark:border-white/10 dark:bg-navy-800">'
      + '<div class="flex items-center justify-between text-[13px]"><span class="font-semibold text-navy-900 dark:text-steel-50">Your progress</span><span class="text-steel-500 dark:text-steel-400"><span data-done>0</span> of <span data-total>0</span> sections</span></div>' + bar + '</div>'

      + card('personal', 'Personal &amp; Emergency Contact', 'Who we should call if something happens during your visit', '<div class="grid gap-4 sm:grid-cols-2' + (prefill.has_dob ? ' lg:grid-cols-3' : '') + '">'
        + '<div' + (prefill.has_dob ? ' class="sm:col-span-2 lg:col-span-1"' : '') + '><span class="' + LBL + '">Sex' + REQ + '</span><div class="flex gap-2" data-clear>' + sexOpt('male', 'Male') + sexOpt('female', 'Female') + '</div></div>'
        + (prefill.has_dob ? '' : '<div><span class="' + LBL + '">Date of Birth' + REQ + '</span><div id="hf_dobWrap"></div></div>')
        + '<div><label class="' + LBL + '" for="hf_ec_name">Emergency Contact Name' + REQ + '</label>' + withIco('user', '<input id="hf_ec_name" maxlength="120" placeholder="e.g. Maria Santos (Mother)" class="' + INP + '">') + '</div>'
        + '<div><label class="' + LBL + '" for="hf_ec_number">Emergency Contact Number' + REQ + '</label>' + withIco('phone', '<input id="hf_ec_number" type="tel" inputmode="numeric" maxlength="11" placeholder="09XXXXXXXXX" class="' + INP + '">') + '</div>'
        + '</div>')

      + card('dental', 'Dental History', 'Your past dental care', '<div class="grid gap-4 sm:grid-cols-2">'
        + '<div><label class="' + LBL + '" for="hf_prev_dentist">Previous Dentist' + REQ + '</label>' + withIco('tooth', '<input id="hf_prev_dentist" maxlength="120" placeholder="Type &quot;None&quot; if this is your first dentist" class="' + INP + '">') + '</div>'
        + select('hf_last_visit', 'Last Dental Visit', DEF.last_visit) + '</div>')

      + card('status', 'General Medical Status', 'Any treatment or medicine that could affect your dental care',
        tiles(DEF.status.map((k) => yesNo(k, DEF.yesno[k], DEF.detail[k])).join('')))
      + card('habits', 'Habits', '',
        tiles(Object.keys(DEF.yesno).filter((k) => !DEF.status.includes(k)).map((k) => yesNo(k, DEF.yesno[k], DEF.detail[k])).join('')))

      + card('allergies', 'Allergies' + REQ, 'Are you allergic to any of the following? Tick all that apply.',
        chips('hf_allergy', DEF.allergies, 'sm:grid-cols-2 lg:grid-cols-3')
        + extra('hf_allergy_other', 'Other allergies', 'e.g. Ibuprofen, seafood', 120, 'hf_allergy_none', 'None, I have no allergies'))

      + card('women', 'For Women Only', '', tiles(Object.keys(DEF.women).map((k) => yesNo(k, DEF.women[k])).join('')))

      + card('blood', 'Blood', 'Choose "I don\'t know" if you are not sure', '<div class="grid gap-4 sm:grid-cols-2">'
        + select('hf_blood_type', 'Blood Type', DEF.blood_types) + '</div>')

      + card('conditions', 'Medical Conditions' + REQ, 'Do you have or have you had any of the following? <span data-cond-count class="font-semibold text-dgt-blue-600 dark:text-dgt-blue-300"></span>',
        chips('hf_condition', DEF.conditions, 'sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4')
        + extra('hf_condition_other', 'Others', 'Other conditions', 160, 'hf_condition_none', 'None of these'))

      + card('confirm', 'Confirm', '', '<label class="' + OK_CHIP + '"><input type="checkbox" id="hf_certify" class="h-4 w-4 shrink-0 accent-ok"> '
        + '<span>' + (STAFF ? 'I confirm these answers were checked with the patient during the visit.' : 'I confirm that the information above is true and complete to the best of my knowledge.') + REQ + '</span></label>')

      + '<div id="hf_bar" class="sticky bottom-3 z-10 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-steel-100 bg-white/95 px-4 py-3 shadow-[0_-4px_18px_rgba(16,47,81,.10)] backdrop-blur dark:border-white/10 dark:bg-navy-800/95">'
      + '<span class="text-[13px] text-steel-600 dark:text-steel-300"><b class="font-semibold text-navy-900 dark:text-steel-50"><span data-done>0</span> of <span data-total>0</span></b> sections complete</span>'
      + '<button id="hf_save" class="inline-flex items-center justify-center gap-2 rounded-lg bg-dgt-teal-600 px-5 py-2.5 text-[13.5px] font-semibold text-white hover:bg-dgt-teal-700">'
      + (FIRST ? 'Save and Continue to Booking' : 'Submit Updated Record')
      + '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button></div>'
      + '</div></div>';

    if (!prefill.has_dob) { g('hf_dobWrap').innerHTML = dobSelectsHtml('hf_dob', '', INP, {}); wireDobSelects('hf_dob'); }
    wirePhoneField(g('hf_ec_number'), {});
    g('hf_ec_name').value = prefill.ec_name || '';
    g('hf_ec_number').value = prefill.ec_number || '';
    if (prefill.sex) document.querySelector('input[name="hf_sex"][value="' + prefill.sex + '"]').checked = true;
    root.querySelectorAll('a[href^="#hf_sec_"]').forEach((a) => a.addEventListener('click', (e) => {
      e.preventDefault();
      g(a.getAttribute('href').slice(1)).scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));
  }

  function fill(a) {
    if (!a) return;
    const setRadio = (k, v) => { const el = document.querySelector('input[name="hf_' + k + '"][value="' + v + '"]'); if (el) el.checked = true; };
    setRadio('sex', a.sex);
    ['prev_dentist', 'last_visit', 'allergy_other', 'blood_type', 'condition_other'].forEach((k) => { if (g('hf_' + k) && a[k] != null) g('hf_' + k).value = a[k]; });
    Object.keys(DEF.yesno).concat(Object.keys(DEF.women)).forEach((k) => {
      if (a[k]) setRadio(k, a[k]);
      if (g('hf_' + k + '_detail')) g('hf_' + k + '_detail').value = a[k + '_detail'] || '';
    });
    document.querySelectorAll('input[name="hf_allergy"]').forEach((c) => { c.checked = (a.allergies || []).includes(c.value); });
    document.querySelectorAll('input[name="hf_condition"]').forEach((c) => { c.checked = (a.conditions || []).includes(c.value); });
    g('hf_allergy_none').checked = !!a.allergy_none;
    g('hf_condition_none').checked = !!a.condition_none;
  }

  // Which sections are fully answered (drives the progress bar and Done badges);
  // null = not shown (the women-only card for a male patient).
  function sectionsDone(b) {
    const yn = (k) => !!b[k] && !(DEF.detail[k] && b[k] === 'yes' && !b[k + '_detail']);
    return {
      personal: !!(b.sex && b.ec_name && /^09\d{9}$/.test(b.ec_number) && (!g('hf_dob') || b.date_of_birth)),
      dental: !!(b.prev_dentist && b.last_visit),
      status: DEF.status.every(yn),
      habits: Object.keys(DEF.yesno).filter((k) => !DEF.status.includes(k)).every(yn),
      allergies: b.allergy_none || b.allergies.length > 0 || !!b.allergy_other,
      women: b.sex === 'female' ? Object.keys(DEF.women).every((k) => b[k]) : null,
      blood: !!b.blood_type,
      conditions: b.condition_none || b.conditions.length > 0 || !!b.condition_other,
      confirm: !!b.certify,
    };
  }
  function progress() {
    const d = sectionsDone(collect());
    const keys = Object.keys(d).filter((k) => d[k] !== null);
    const done = keys.filter((k) => d[k]).length;
    const pct = Math.round(done * 100 / keys.length);
    root.querySelectorAll('[data-done]').forEach((el) => { el.textContent = done; });
    root.querySelectorAll('[data-total]').forEach((el) => { el.textContent = keys.length; });
    root.querySelectorAll('[data-pct]').forEach((el) => { el.textContent = pct; });
    root.querySelectorAll('[data-bar]').forEach((el) => { el.style.width = pct + '%'; });
    Object.keys(d).forEach((k) => {
      const badge = root.querySelector('[data-sec-badge="' + k + '"]');
      if (badge) { badge.classList.toggle('hidden', !d[k]); badge.classList.toggle('inline-flex', !!d[k]); }
      const li = root.querySelector('[data-nav-sec="' + k + '"]');
      if (!li) return;
      li.hidden = d[k] === null;
      const dot = li.querySelector('[data-dot]');
      dot.className = 'grid h-5 w-5 shrink-0 place-items-center rounded-full border-2 ' + (d[k] ? 'border-ok bg-ok text-white' : 'border-steel-200 dark:border-white/20');
      dot.innerHTML = d[k] ? CHECK_SVG('h-3 w-3') : '';
    });
    const n = root.querySelectorAll('input[name="hf_condition"]:checked').length;
    root.querySelector('[data-cond-count]').textContent = n ? '(' + n + ' selected)' : '';
  }

  // Show/hide the "If yes" boxes and the women-only card; "None" clears the list.
  function sync() {
    Object.keys(DEF.detail).forEach((k) => {
      const yes = !!document.querySelector('input[name="hf_' + k + '"][value="yes"]:checked');
      g('hf_' + k + '_wrap').hidden = !yes;
    });
    const fem = !!document.querySelector('input[name="hf_sex"][value="female"]:checked');
    g('hf_sec_women').hidden = !fem;
    progress();
  }
  function wireNone(listName, noneId, otherId) {
    const boxes = () => document.querySelectorAll('input[name="' + listName + '"]');
    g(noneId).addEventListener('change', () => {
      if (g(noneId).checked) { boxes().forEach((c) => { c.checked = false; }); g(otherId).value = ''; progress(); }
    });
    const off = () => { if (g(noneId).checked) g(noneId).checked = false; };
    boxes().forEach((c) => c.addEventListener('change', () => { if (c.checked) off(); }));
    g(otherId).addEventListener('input', () => { if (g(otherId).value.trim()) off(); });
  }

  function collect() {
    const radio = (k) => (document.querySelector('input[name="hf_' + k + '"]:checked') || {}).value || '';
    const val = (k) => (g('hf_' + k) ? g('hf_' + k).value.trim() : '');
    const b = {
      sex: radio('sex'), ec_name: val('ec_name'), ec_number: val('ec_number'),
      prev_dentist: val('prev_dentist'), last_visit: val('last_visit'),
      allergies: [...document.querySelectorAll('input[name="hf_allergy"]:checked')].map((c) => c.value),
      allergy_other: val('allergy_other'), allergy_none: g('hf_allergy_none').checked,
      blood_type: val('blood_type'),
      conditions: [...document.querySelectorAll('input[name="hf_condition"]:checked')].map((c) => c.value),
      condition_other: val('condition_other'), condition_none: g('hf_condition_none').checked,
      certify: g('hf_certify').checked,
    };
    if (g('hf_dob')) b.date_of_birth = g('hf_dob').value;
    Object.keys(DEF.yesno).concat(Object.keys(DEF.women)).forEach((k) => {
      b[k] = radio(k);
      if (g('hf_' + k + '_detail')) b[k + '_detail'] = val(k + '_detail');
    });
    return b;
  }

  // First unanswered field, so the patient can be taken straight to it.
  function firstMissing(b) {
    const q = (sel) => document.querySelector(sel);
    if (!b.sex) return q('input[name="hf_sex"]');
    if (g('hf_dob') && !b.date_of_birth) return q('#hf_dobWrap select');
    for (const k of ['ec_name', 'ec_number', 'prev_dentist', 'last_visit']) if (!b[k]) return g('hf_' + k);
    for (const k of Object.keys(DEF.yesno)) {
      if (!b[k]) return q('[data-yn="' + k + '"]');
      if (DEF.detail[k] && b[k] === 'yes' && !b[k + '_detail']) return g('hf_' + k + '_detail');
    }
    if (!b.allergy_none && !b.allergies.length && !b.allergy_other) return q('input[name="hf_allergy"]');
    if (b.sex === 'female') for (const k of Object.keys(DEF.women)) if (!b[k]) return q('[data-yn="' + k + '"]');
    if (!b.blood_type) return g('hf_blood_type');
    if (!b.condition_none && !b.conditions.length && !b.condition_other) return q('input[name="hf_condition"]');
    if (!b.certify) return g('hf_certify');
    return null;
  }

  (async () => {
    let r;
    try { r = await api('health_form.php?action=get' + (STAFF ? '&patient=' + PATIENT : '')); }
    catch (e) { root.innerHTML = '<p class="py-7 text-center text-danger">' + esc(e.message) + '</p>'; return; }
    render(r.prefill || {});
    fill(r.answers);
    // A review must be confirmed again; a locked record is shown read-only.
    g('hf_certify').checked = MODE === 'locked';
    sync();
    if (MODE === 'locked') {
      root.querySelectorAll('input, select, textarea').forEach((el) => { el.disabled = true; });
      root.querySelectorAll('label').forEach((el) => el.classList.add('!cursor-default'));
      g('hf_bar').style.display = 'none';
      return;
    }
    root.addEventListener('change', sync);
    root.addEventListener('input', progress);
    // Clicking the chosen Yes / No (or Sex) again clears it (a plain radio can't be undone).
    let wasOn = null;
    root.addEventListener('pointerdown', (e) => {
      const lab = e.target.closest('[data-yn] label, [data-clear] label');
      const r = lab && lab.querySelector('input[type="radio"]');
      wasOn = r && r.checked ? r : null;
    });
    root.addEventListener('click', (e) => {
      const r = e.target.closest('[data-yn] input[type="radio"], [data-clear] input[type="radio"]');
      if (r && r === wasOn) {
        r.checked = false;
        wasOn = null;
        r.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    wireNone('hf_allergy', 'hf_allergy_none', 'hf_allergy_other');
    wireNone('hf_condition', 'hf_condition_none', 'hf_condition_other');

    g('hf_save').addEventListener('click', async () => {
      const b = collect();
      const miss = firstMissing(b);
      if (miss) {
        const box = miss.closest('[data-yn], .grid > div, label') || miss;
        box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        box.classList.add('hf-miss');
        setTimeout(() => box.classList.remove('hf-miss'), 2200);
        if (miss.focus) miss.focus({ preventScroll: true });
        return toast('Please answer every question marked with *.', 'err');
      }
      if (!g('hf_ec_number')._phoneOk(false)) return toast('Emergency contact number: ' + PH_MOBILE_MSG, 'err');
      try {
        if (STAFF) b.patient_id = PATIENT;
        await api('health_form.php?action=save', { method: 'POST', body: b });
        toast('Patient Information Record saved.');
        const next = STAFF ? '/dgt_dental_clinic/modules/dentist/records' : '/dgt_dental_clinic/modules/patient/appointments';
        const a = document.createElement('a'); a.href = next; a.setAttribute('data-nav', ''); document.body.appendChild(a); a.click(); a.remove();
      } catch (e) { toast(e.message, 'err'); }
    });
  })();
})();
JS
);
require __DIR__ . '/../../includes/layout.php';
