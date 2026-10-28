<?php
declare(strict_types=1);

/**
 * Treatment Plan & Follow-up Service (migration 066).
 * GET action=mine  (patient) the account's still-unbooked follow-up
 *                  recommendations, for the "Recommended Next Services" card.
 * Plans are created by api/treatments.php when a dentist logs a visit, and
 * booked through api/appointments.php action=create (treatment_plan_id).
 */
require_once __DIR__ . '/_bootstrap.php';

$me  = apiAuth(['patient']);
$uid = (int) $me['user_id'];
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'mine') {
    syncTreatmentPlans($uid);
    $st = db()->prepare(
        "SELECT tp.plan_id, tp.service_id, tp.treated_patient_id, tp.timeframe_recommendation, tp.due_date, tp.status,
                s.service_name, d.full_name AS dentist_name, d.doctor_type,
                c.scheduled_date AS consult_date, c.relationship AS consult_relationship,
                COALESCE(t.full_name, c.patient_name) AS for_name
         FROM treatment_plans tp
         JOIN services s ON s.service_id = tp.service_id AND s.is_active = 1
         JOIN users d ON d.user_id = tp.assigned_dentist_id AND d.is_active = 1
         JOIN appointments c ON c.appointment_id = tp.consultation_id
         LEFT JOIN users t ON t.user_id = tp.treated_patient_id
         WHERE tp.patient_id = ? AND tp.status = 'Pending'
         ORDER BY tp.due_date, tp.plan_id"
    );
    $st->execute([$uid]);
    jsonOut(['ok' => true, 'plans' => $st->fetchAll()]);
}

jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
