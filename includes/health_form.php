<?php
/**
 * Patient Information Record (2026-10-20, migration 081).
 * A required health questionnaire a patient answers before booking, adapted
 * from the clinic's paper "Patient Information Record" (only the parts that
 * apply to a dental visit). Answers are stored as JSON in
 * patient_records.health_form and copied into the dental record.
 */

/** Yes / No questions. A key in HF_DETAIL also needs a short answer when "Yes". */
// The first four are the General Medical Status screening questions
// (2026-10-20, replacing the Excellent / Good / Fair / Poor rating, the
// "in good health" and "hospitalized" questions and the physician field).
const HF_STATUS = ['under_treatment', 'serious_illness', 'medication', 'bad_reaction'];
const HF_YESNO = [
    'under_treatment'  => 'Are you currently under medical treatment or the care of a physician?',
    'serious_illness'  => 'Have you had any serious illness, operation, or hospitalization in the past 2 to 5 years?',
    'medication'       => 'Are you currently taking any prescription medications, over-the-counter drugs, or supplements?',
    'bad_reaction'     => 'Have you ever had a bad reaction to local anesthesia, antibiotics, or other medicines?',
    'tobacco'          => 'Do you use tobacco products?',
    'drugs'            => 'Do you use alcohol, cocaine or other dangerous drugs?',
];
const HF_DETAIL = [
    'under_treatment' => 'Please specify the condition and your physician\'s name.',
    'serious_illness' => 'Please explain.',
    'medication'      => 'Please list the medications.',
    'bad_reaction'    => 'Which medicine, and what happened?',
];
const HF_STATUS_LABEL = [
    'under_treatment' => 'Under medical treatment',
    'serious_illness' => 'Serious illness, operation or hospitalization (past 2 to 5 years)',
    'medication'      => 'Taking medications or supplements',
    'bad_reaction'    => 'Bad reaction to anesthesia or medicine',
];
const HF_WOMEN = [
    'pregnant'      => 'Are you pregnant?',
    'nursing'       => 'Are you nursing (breastfeeding)?',
    'birth_control' => 'Are you taking birth control pills?',
];
const HF_ALLERGIES = [
    'Local Anesthetic (e.g. Lidocaine)', 'Penicillin / Antibiotics', 'Sulfa Drugs', 'Aspirin', 'Latex',
];
const HF_LAST_VISIT = ['Within the last 6 months', '6 to 12 months ago', '1 to 2 years ago', 'More than 2 years ago', 'Never been to a dentist'];
const HF_BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', "I don't know"];
const HF_CONDITIONS = [
    'High Blood Pressure', 'Low Blood Pressure', 'Epilepsy / Convulsions', 'AIDS or HIV Infection',
    'Sexually Transmitted Disease', 'Stomach Troubles / Ulcers', 'Fainting Seizure', 'Rapid Weight Loss',
    'Radiation Therapy', 'Joint Replacement / Implant', 'Heart Surgery', 'Heart Attack', 'Thyroid Problem',
    'Heart Disease', 'Heart Murmur', 'Hepatitis / Liver Disease', 'Rheumatic Fever', 'Hay Fever / Allergies',
    'Respiratory Problems', 'Hepatitis / Jaundice', 'Tuberculosis', 'Swollen Ankles', 'Kidney Disease',
    'Diabetes', 'Chest Pain', 'Stroke', 'Cancer / Tumors', 'Anemia', 'Angina', 'Asthma', 'Emphysema',
    'Bleeding Problems', 'Blood Diseases', 'Head Injuries', 'Arthritis / Rheumatism',
];

/** Everything the form page needs to draw itself. */
function healthFormDefinition(): array
{
    return [
        'yesno' => HF_YESNO, 'detail' => HF_DETAIL, 'women' => HF_WOMEN,
        'allergies' => HF_ALLERGIES, 'last_visit' => HF_LAST_VISIT,
        'status' => HF_STATUS, 'blood_types' => HF_BLOOD_TYPES,
        'conditions' => HF_CONDITIONS,
    ];
}

/** True once the patient has submitted the form. */
function healthFormDone(int $uid): bool
{
    try {
        $st = getDB()->prepare('SELECT health_form_at FROM patient_records WHERE patient_id = ?');
        $st->execute([$uid]);
        return (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        return true;   // never lock a patient out on a DB error
    }
}

/** After this many months a submitted record must be reviewed again (2026-10-20; 3 since 2026-10-26, was 6). */
const HF_REVIEW_MONTHS = 3;

/**
 * Where the patient stands (DB clock):
 *   done        - submitted at least once
 *   due         - submitted, but the review date has come (form unlocked again)
 *   locked      - submitted and not yet due (read-only)
 *   at          - last submission (Y-m-d H:i:s) or null
 *   review_from - date the next review opens (Y-m-d) or null
 */
function healthFormState(int $uid): array
{
    try {
        $st = getDB()->prepare('SELECT health_form_at, DATE(DATE_ADD(health_form_at, INTERVAL ' . HF_REVIEW_MONTHS . ' MONTH)) AS review_from,
                (DATE_ADD(health_form_at, INTERVAL ' . HF_REVIEW_MONTHS . ' MONTH) <= NOW()) AS due
            FROM patient_records WHERE patient_id = ?');
        $st->execute([$uid]);
        $r = $st->fetch() ?: [];
    } catch (Throwable $e) {
        return ['done' => true, 'due' => false, 'locked' => false, 'at' => null, 'review_from' => null];
    }
    $done = !empty($r['health_form_at']);
    $due  = $done && (int) $r['due'] === 1;
    return ['done' => $done, 'due' => $due, 'locked' => $done && !$due,
            'at' => $r['health_form_at'] ?? null, 'review_from' => $done ? $r['review_from'] : null];
}

/** Booking needs a submitted record that is not waiting for its review. */
function healthFormReady(int $uid): bool
{
    $s = healthFormState($uid);
    return $s['done'] && !$s['due'];
}

/**
 * Check the submitted answers. Returns [clean answers, error message or ''].
 */
function healthFormValidate(array $in): array
{
    $t = fn ($k, $max = 120) => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($in[$k] ?? ''))), 0, $max);
    $a = [];
    $a['sex'] = in_array($in['sex'] ?? '', ['male', 'female'], true) ? $in['sex'] : '';
    if ($a['sex'] === '') return [[], 'Choose your sex.'];

    $a['prev_dentist'] = $t('prev_dentist');
    if ($a['prev_dentist'] === '') return [[], 'Enter your previous dentist, or type "None".'];
    $a['last_visit'] = in_array($in['last_visit'] ?? '', HF_LAST_VISIT, true) ? $in['last_visit'] : '';
    if ($a['last_visit'] === '') return [[], 'Choose when your last dental visit was.'];

    foreach (HF_YESNO as $k => $q) {
        $v = $in[$k] ?? '';
        if (!in_array($v, ['yes', 'no'], true)) return [[], 'Answer: ' . $q];
        $a[$k] = $v;
        if (isset(HF_DETAIL[$k])) {
            $a[$k . '_detail'] = $v === 'yes' ? $t($k . '_detail', 200) : '';
            if ($v === 'yes' && $a[$k . '_detail'] === '') return [[], HF_DETAIL[$k] . ' (' . $q . ')'];
        }
    }

    $al = array_values(array_intersect(HF_ALLERGIES, (array) ($in['allergies'] ?? [])));
    $a['allergy_other'] = $t('allergy_other', 120);
    $a['allergy_none'] = !empty($in['allergy_none']);
    if ($a['allergy_none'] && ($al || $a['allergy_other'] !== '')) return [[], 'Choose "None" or your allergies, not both.'];
    if (!$a['allergy_none'] && !$al && $a['allergy_other'] === '') return [[], 'Tick the medicines or materials you are allergic to, or tick "None".'];
    $a['allergies'] = $al;

    if ($a['sex'] === 'female') {
        foreach (HF_WOMEN as $k => $q) {
            $v = $in[$k] ?? '';
            if (!in_array($v, ['yes', 'no'], true)) return [[], 'Answer: ' . $q];
            $a[$k] = $v;
        }
    }

    $a['blood_type'] = in_array($in['blood_type'] ?? '', HF_BLOOD_TYPES, true) ? $in['blood_type'] : '';
    if ($a['blood_type'] === '') return [[], 'Choose your blood type, or "I don\'t know".'];

    $co = array_values(array_intersect(HF_CONDITIONS, (array) ($in['conditions'] ?? [])));
    $a['condition_other'] = $t('condition_other', 160);
    $a['condition_none'] = !empty($in['condition_none']);
    if ($a['condition_none'] && ($co || $a['condition_other'] !== '')) return [[], 'Choose "None of these" or your conditions, not both.'];
    if (!$a['condition_none'] && !$co && $a['condition_other'] === '') return [[], 'Tick the conditions you have or had, or tick "None of these".'];
    $a['conditions'] = $co;

    if (empty($in['certify'])) return [[], 'Please confirm that your answers are true and complete.'];
    return [$a, ''];
}

/** Allergies as one line for the dental record ("None" when there are none). */
function healthFormAllergyText(array $a): string
{
    $list = $a['allergies'] ?? [];
    if (($a['allergy_other'] ?? '') !== '') $list[] = $a['allergy_other'];
    return $list ? implode(', ', $list) : 'None';
}

/**
 * The General Medical Status answers for the record's General Condition box:
 * one "• question: answer" per item, a "Yes" item's details on the line below,
 * and a blank line between items (2026-10-20, easier to read).
 */
function healthFormStatusText(array $a): string
{
    $out = [];
    foreach (HF_STATUS as $k) {
        $yes = ($a[$k] ?? '') === 'yes';
        $item = '• ' . HF_STATUS_LABEL[$k] . ': ' . ($yes ? 'YES' : 'No');
        if ($yes && ($a[$k . '_detail'] ?? '') !== '') $item .= chr(10) . '   Details: ' . $a[$k . '_detail'];
        $out[] = $item;
    }
    return implode(chr(10) . chr(10), $out);
}

/**
 * The answers as readable lines for the dental record's Medical History:
 * grouped under headings, one answer per line, a blank line between groups
 * (2026-10-20: the one-line-per-group version was too compact to read).
 */
function healthFormSummary(array $a, string $date): string
{
    $yn = fn ($k) => ($a[$k] ?? '') === 'yes' ? 'Yes' : 'No';
    $li = fn ($s) => '• ' . $s;
    $groups = [];
    $groups['DENTAL HISTORY'] = [
        $li('Previous dentist: ' . $a['prev_dentist']),
        $li('Last dental visit: ' . $a['last_visit']),
    ];
    // General Medical Status lives in the intake log only (2026-10-20): the
    // separate General Condition box was removed from the dental record.
    $st = [];
    foreach (HF_STATUS as $k) {
        $st[] = $li(HF_STATUS_LABEL[$k] . ': ' . $yn($k));
        if (($a[$k] ?? '') === 'yes' && ($a[$k . '_detail'] ?? '') !== '') $st[] = '   Details: ' . $a[$k . '_detail'];
    }
    $groups['GENERAL MEDICAL STATUS'] = $st;
    $groups['HABITS'] = [$li('Uses tobacco: ' . $yn('tobacco')), $li('Uses alcohol or drugs: ' . $yn('drugs'))];
    $al = $a['allergies'] ?? [];
    if (($a['allergy_other'] ?? '') !== '') $al[] = $a['allergy_other'];
    $groups['ALLERGIES'] = $al ? array_map($li, $al) : [$li('None')];
    if (($a['sex'] ?? '') === 'female') {
        $groups['FOR WOMEN'] = [$li('Pregnant: ' . $yn('pregnant')), $li('Nursing: ' . $yn('nursing')), $li('Taking birth control pills: ' . $yn('birth_control'))];
    }
    $groups['BLOOD'] = [$li('Blood type: ' . $a['blood_type'])];
    $cond = $a['conditions'] ?? [];
    if (($a['condition_other'] ?? '') !== '') $cond[] = $a['condition_other'];
    $groups['MEDICAL CONDITIONS'] = $cond ? array_map($li, $cond) : [$li('None')];

    // No title line (2026-10-20): the entry's date is shown beside it.
    $out = [];
    foreach ($groups as $h => $lines) {
        if ($out) $out[] = '';
        $out[] = $h;
        array_push($out, ...$lines);
    }
    return implode("\n", $out);
}

/** A patient's submitted records, newest first (for the dentist's view and Dental Records). */
function healthFormLogs(int $pid): array
{
    try {
        // by_name / by_role: who saved it (the patient, or a dentist who updated it, 2026-10-26).
        $st = getDB()->prepare('SELECT l.log_id, l.summary, l.submitted_at, l.submitted_by,
                                       u.full_name AS by_name, u.role AS by_role
                                FROM health_form_logs l LEFT JOIN users u ON u.user_id = l.submitted_by
                                WHERE l.patient_id = ? ORDER BY l.submitted_at DESC, l.log_id DESC');
        $st->execute([$pid]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
