<?php
declare(strict_types=1);

/**
 * Patient "My Dental Records" (2026-10-18): a read-only copy of the dentist's
 * Patient Record for the signed-in patient, or for a person they booked
 * "for someone else" (only people from their own bookings).
 */
require_once __DIR__ . '/_bootstrap.php';

$me  = apiAuth(['patient']);
if (!healthFormDone((int) $me['user_id'])) {
    jsonOut(['ok' => false, 'error' => 'Please complete your Patient Information Record first.'], 403);
}
$uid = (int) $me['user_id'];

// Self first, then everyone this account has booked for.
$p = db()->prepare(
    "SELECT DISTINCT u.user_id AS id, u.full_name
     FROM appointments a JOIN users u ON u.user_id = a.treated_patient_id
     WHERE a.patient_id = ? AND a.booking_for = 'other' AND a.treated_patient_id IS NOT NULL AND a.treated_patient_id <> ?
     ORDER BY u.full_name"
);
$p->execute([$uid, $uid]);
$people = array_merge([['id' => $uid, 'full_name' => $me['full_name'], 'self' => true]], $p->fetchAll());

$pid = (int) ($_GET['person'] ?? $uid);
if (!in_array($pid, array_map(static fn ($x) => (int) $x['id'], $people), true)) {
    jsonOut(['ok' => false, 'error' => 'Record not found.'], 404);
}

$r = db()->prepare(
    // Bite, gum condition, oral hygiene and abnormalities are not sent to the patient (2026-10-20).
    'SELECT r.date_of_birth, r.gender,
            r.denture_upper, r.denture_lower, r.medical_history,
            r.allergies, r.address, r.emergency_contact_name, r.emergency_contact_number,
            r.tooth_chart, r.updated_at, ub.full_name AS updated_by_name
     FROM patient_records r LEFT JOIN users ub ON ub.user_id = r.updated_by
     WHERE r.patient_id = ?'
);
$r->execute([$pid]);
$record = $r->fetch() ?: [];
$record['tooth_chart'] = !empty($record['tooth_chart']) ? (json_decode($record['tooth_chart'], true) ?: []) : [];

$name = '';
foreach ($people as $x) {
    if ((int) $x['id'] === $pid) {
        $name = $x['full_name'];
    }
}

$u = db()->prepare('SELECT full_name, contact_number, email FROM users WHERE user_id = ?');
$u->execute([$pid]);
$patient = $u->fetch() ?: [];

jsonOut(['ok' => true, 'people' => $people, 'person_id' => $pid, 'name' => $name,
         'patient' => $patient, 'record' => $record,
         // Patient Information Record entries + lock state (2026-10-20). Only the
         // account holder answers the form.
         'health_logs' => healthFormLogs($pid),
         'health_state' => $pid === $uid ? healthFormState($uid) : null]);
