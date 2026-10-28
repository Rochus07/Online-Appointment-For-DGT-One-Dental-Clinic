<?php
/**
 * Patient Information Record (2026-10-20, migration 081). The patient, or
 * (2026-10-26) a dentist updating it with ?patient=<id> / body patient_id, e.g.
 * an allergy found during the exam. A dentist save is a new entry under the
 * dentist's name, never locked, and does not move the patient's review date.
 *   GET  action=get   - the saved answers (or prefill from the dental record)
 *   POST action=save  - validate, store, copy into the dental record
 * Every submission is kept in health_form_logs (2026-10-20). Once submitted the
 * form is locked until the review date (HF_REVIEW_MONTHS), then the patient
 * reviews it and submits a new entry.
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/health_form.php';

$me = apiAuth(['patient', 'dentist']);
$staff = $me['role'] !== 'patient';
$action = $_GET['action'] ?? 'get';
$in = $_SERVER['REQUEST_METHOD'] === 'POST' ? requestBody() : [];
$uid = $staff ? (int) ($in['patient_id'] ?? $_GET['patient'] ?? 0) : (int) $me['user_id'];
if ($staff) {
    $pc = db()->prepare("SELECT full_name FROM users WHERE user_id = ? AND role = 'patient'");
    $pc->execute([$uid]);
    $patientName = $pc->fetchColumn();
    if ($patientName === false) {
        jsonOut(['ok' => false, 'error' => 'Patient not found.'], 404);
    }
}

$rec = db()->prepare('SELECT date_of_birth, gender, emergency_contact_name, emergency_contact_number,
        allergies, medical_history, health_form, health_form_at FROM patient_records WHERE patient_id = ?');
$rec->execute([$uid]);
$r = $rec->fetch() ?: [];
$saved = json_decode((string) ($r['health_form'] ?? ''), true) ?: [];
$state = healthFormState($uid);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get') {
    jsonOut([
        'ok' => true,
        'answers' => $saved['answers'] ?? null,
        'answered_at' => $r['health_form_at'] ?? null,
        'state' => $state,
        'prefill' => [
            'sex' => in_array($r['gender'] ?? '', ['male', 'female'], true) ? $r['gender'] : '',
            'ec_name' => (string) ($r['emergency_contact_name'] ?? ''),
            'ec_number' => (string) ($r['emergency_contact_number'] ?? ''),
            'has_dob' => !empty($r['date_of_birth']),
        ],
    ]);
}

// Dentist types straight into the record (2026-10-26): the edited text is saved
// as a new entry under the dentist's name; earlier entries and the answers stay.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_text') {
    if (!$staff) {
        jsonOut(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    $text = trim(str_replace("\r\n", "\n", (string) ($in['summary'] ?? '')));
    if ($text === '') {
        jsonOut(['ok' => false, 'error' => 'The record cannot be empty.'], 422);
    }
    if (mb_strlen($text) > 20000) {
        jsonOut(['ok' => false, 'error' => 'The record is too long.'], 422);
    }
    $last = db()->prepare('SELECT answers, summary FROM health_form_logs WHERE patient_id = ? ORDER BY submitted_at DESC, log_id DESC LIMIT 1');
    $last->execute([$uid]);
    $prev = $last->fetch();
    if (!$prev) {
        jsonOut(['ok' => false, 'error' => 'The patient has not answered the record yet.'], 422);
    }
    if (trim((string) $prev['summary']) === $text) {
        jsonOut(['ok' => false, 'error' => 'No changes to save.'], 422);
    }
    db()->prepare('INSERT INTO health_form_logs (patient_id, answers, summary, submitted_at, submitted_by) VALUES (?, ?, ?, NOW(), ?)')
        ->execute([$uid, $prev['answers'], $text, (int) $me['user_id']]);
    $saved['summary'] = $text;
    db()->prepare('UPDATE patient_records SET health_form = ? WHERE patient_id = ?')
        ->execute([json_encode($saved, JSON_UNESCAPED_UNICODE), $uid]);
    logActivity('records', 'Updated the Patient Information Record of ' . $patientName . ' (patient #' . $uid . ')', $me);
    jsonOut(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    if ($state['locked'] && !$staff) {
        jsonOut(['ok' => false, 'error' => 'Your Patient Information Record is locked until '
            . date('M j, Y', strtotime($state['review_from'])) . '. Your dentist can update it at your visit.'], 409);
    }
    $ecName = properCase($in['ec_name'] ?? '');
    $ecNum  = digitsOnly($in['ec_number'] ?? '');
    if ($ecName === '' || $ecNum === '') {
        jsonOut(['ok' => false, 'error' => 'Enter an emergency contact name and number.'], 422);
    }
    if (mb_strlen($ecName) > 120) {
        jsonOut(['ok' => false, 'error' => 'The emergency contact name is too long.'], 422);
    }
    if (!isPhMobile($ecNum)) {
        jsonOut(['ok' => false, 'error' => 'Emergency contact number: ' . PH_MOBILE_MSG], 422);
    }

    // Date of birth: asked only while none is on file, then locked.
    $dob = null;
    if (empty($r['date_of_birth'])) {
        $dob = validBirthDate((string) ($in['date_of_birth'] ?? ''));
        if ($dob === null) {
            jsonOut(['ok' => false, 'error' => $staff ? "Enter the patient's date of birth." : 'Enter your date of birth.'], 422);
        }
    }

    [$a, $err] = healthFormValidate($in);
    if ($err !== '') {
        jsonOut(['ok' => false, 'error' => $err], 422);
    }

    $today   = (string) db()->query('SELECT CURDATE()')->fetchColumn();
    $summary = healthFormSummary($a, date('M j, Y', strtotime($today)));

    // Medical History is the dentist's notes now: the summary lives in
    // health_form_logs. A summary still copied there by the first version is taken out.
    $history = trim((string) ($r['medical_history'] ?? ''));
    $old = (string) ($saved['summary'] ?? '');
    if ($old !== '' && str_contains($history, $old)) {
        $history = trim(str_replace($old, '', $history));
    }
    $history = $history === '' ? null : $history;

    $json = json_encode(['answers' => $a, 'summary' => $summary], JSON_UNESCAPED_UNICODE);
    db()->prepare(
        'INSERT INTO patient_records (patient_id, date_of_birth, gender, emergency_contact_name, emergency_contact_number,
             allergies, medical_history, health_form, health_form_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE date_of_birth = COALESCE(date_of_birth, VALUES(date_of_birth)),
             gender = VALUES(gender), emergency_contact_name = VALUES(emergency_contact_name),
             emergency_contact_number = VALUES(emergency_contact_number), allergies = VALUES(allergies),
             medical_history = VALUES(medical_history), health_form = VALUES(health_form), health_form_at = ' . ($staff ? 'COALESCE(health_form_at, NOW())' : 'NOW()')
    )->execute([$uid, $dob, $a['sex'], $ecName, $ecNum, mb_substr(healthFormAllergyText($a), 0, 500),
        $history, $json]);

    db()->prepare('INSERT INTO health_form_logs (patient_id, answers, summary, submitted_at, submitted_by) VALUES (?, ?, ?, NOW(), ?)')
        ->execute([$uid, json_encode($a, JSON_UNESCAPED_UNICODE), $summary, (int) $me['user_id']]);

    logActivity('records', $staff
        ? 'Updated the Patient Information Record of ' . $patientName . ' (patient #' . $uid . ')'
        : (!empty($r['health_form_at']) ? 'Submitted an updated Patient Information Record' : 'Completed their Patient Information Record'), $me);
    jsonOut(['ok' => true]);
}

jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
