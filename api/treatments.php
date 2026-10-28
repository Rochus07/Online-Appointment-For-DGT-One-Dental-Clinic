<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$me  = apiAuth(['dentist', 'admin']);
$uid = (int) $me['user_id'];
$in  = requestBody();
$action = $_GET['action'] ?? '';

if ($action !== 'log') {
    jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
}

$appointmentId = (int) ($in['appointment_id'] ?? 0);
$items = $in['services_availed'] ?? $in['services'] ?? [];   // [{service_id, price}, ...]
$notes = trim($in['dentist_notes'] ?? $in['notes'] ?? '');   // appended to appointments.notes as "Treatment notes: …"

// Optional assisting-staff tag. Dental assistants are non-account staff (see
// api/assistants.php) — the chosen one must be marked on duty today. Stored on
// appointments.treatment_assistant_id for shift / workload reporting.
$asstId   = (int) ($in['assistant_id'] ?? 0);
$asstName = '';
if ($asstId > 0) {
    foreach (dentalAssistantsOnDuty() as $da) {
        if ((int) $da['assistant_id'] === $asstId) {
            $asstName = $da['full_name'];
        }
    }
    if ($asstName === '') {
        jsonOut(['ok' => false, 'error' => 'That assistant is not marked on duty today.'], 422);
    }
}

$a = appointmentDetail($appointmentId);
if (!$a) {
    jsonOut(['ok' => false, 'error' => 'Appointment not found.'], 404);
}
if ($me['role'] === 'dentist' && (int) $a['dentist_id'] !== $uid) {
    jsonOut(['ok' => false, 'error' => 'This appointment is not assigned to you.'], 403);
}
if (!in_array($a['status'], ['approved', 'rescheduled'], true)) {
    jsonOut(['ok' => false, 'error' => 'Only approved visits can be completed.'], 422);
}
if (empty($a['checked_in_at'])) {
    jsonOut(['ok' => false, 'error' => 'The front desk has not checked this patient in yet.'], 422);
}
// Compare against the DB clock, not PHP's — this box runs PHP and MySQL in
// different timezones, and the dentist queue that surfaces this visit filters on
// CURDATE(), so the guard must use the same clock or a same-day visit that shows
// in the queue still can't be logged.
if ($a['scheduled_date'] > db()->query('SELECT CURDATE()')->fetchColumn()) {
    jsonOut(['ok' => false, 'error' => 'This visit is scheduled for a future date. Procedures can only be logged on or after the appointment day.'], 422);
}
if (!is_array($items) || count($items) === 0) {
    jsonOut(['ok' => false, 'error' => 'Select at least one procedure that was performed.'], 422);
}

// Optional follow-up recommendation (migration 066): a service + a timeframe.
// It becomes a treatment_plans row locked to the dentist who logged this visit.
$fu = is_array($in['follow_up'] ?? null) ? $in['follow_up'] : [];
$fuServiceId = (int) ($fu['service_id'] ?? 0);
$fuTimeframe = (string) ($fu['timeframe'] ?? '');
$fuInterval = FOLLOWUP_TIMEFRAMES[$fuTimeframe] ?? null;
$fuServiceName = '';
if ($fuServiceId > 0 || $fuTimeframe !== '') {
    if ($fuInterval === null) {
        jsonOut(['ok' => false, 'error' => 'Choose a suggested timeframe for the follow-up.'], 422);
    }
    $fs = db()->prepare('SELECT service_name FROM services WHERE service_id = ? AND is_active = 1');
    $fs->execute([$fuServiceId]);
    $fuServiceName = (string) ($fs->fetchColumn() ?: '');
    if ($fuServiceName === '') {
        jsonOut(['ok' => false, 'error' => 'Choose the recommended follow-up service.'], 422);
    }
    if ((int) ($a['dentist_id'] ?? 0) <= 0) {
        jsonOut(['ok' => false, 'error' => 'Assign a dentist to this visit before recommending a follow-up.'], 422);
    }
}

$pdo = db();
$pdo->beginTransaction();
try {
    // Resolve prices against the live service catalogue.
    // `price` stored on appointment_services is the LINE TOTAL (qty x unit price).
    // Only a per-unit service (services.quantity_unit set, e.g. per tooth) may have
    // a quantity above 1; a single-session service is always 1.
    $insAS = $pdo->prepare('INSERT INTO appointment_services (appointment_id, service_id, qty, price) VALUES (?, ?, ?, ?)');
    $priceStmt = $pdo->prepare('SELECT service_name, base_price, quantity_unit FROM services WHERE service_id = ? AND is_active = 1');
    $total = 0.0;
    $chartMarks = [];   // tooth => state, from the teeth picked per service
    $teethNotes = [];   // "Extraction (Bunot): teeth 16, 26"

    $seenSvc = [];
    foreach ($items as $it) {
        $sid = (int) ($it['service_id'] ?? 0);
        if (isset($seenSvc[$sid])) {
            throw new RuntimeException('Each service can only be added once.');
        }
        $seenSvc[$sid] = true;
        $priceStmt->execute([$sid]);
        $svc = $priceStmt->fetch();
        if (!$svc) {
            throw new RuntimeException('One of the selected procedures is no longer available.');
        }
        // The dentist types the real charge (list prices are only estimates).
        $rawPrice = $it['unit_price'] ?? ($it['price'] ?? null);
        if ($rawPrice === null || $rawPrice === '' || !is_numeric($rawPrice)) {
            throw new RuntimeException('Enter the charge for every procedure performed.');
        }
        $unitPrice = (float) $rawPrice;
        if ($unitPrice <= 0) {
            throw new RuntimeException('Enter a price above ₱0 for every service.');
        }
        // Teeth picked for a chart-updating service (Extraction, Resto, RCT).
        $effect = toothChartEffect((string) $svc['service_name']);
        $teeth = [];
        if ($effect !== null) {
            foreach ((array) ($it['teeth'] ?? []) as $t) {
                $t = (string) $t;
                if (isFdiTooth($t)) {
                    $teeth[$t] = true;
                }
            }
            $teeth = array_keys($teeth);
            if (!$teeth) {
                throw new RuntimeException('Pick the tooth or teeth treated for ' . $svc['service_name'] . '.');
            }
            foreach ($teeth as $t) {
                $chartMarks[$t] = $effect;
            }
            $teethNotes[] = $svc['service_name'] . ': ' . (count($teeth) > 1 ? 'teeth ' : 'tooth ') . implode(', ', $teeth);
        }
        $qty = $svc['quantity_unit'] !== null ? (int) ($it['qty'] ?? 1) : 1;
        if ($qty < 1 || $qty > 99) {
            throw new RuntimeException('Quantity must be between 1 and 99.');
        }
        $line = round($qty * $unitPrice, 2);
        $insAS->execute([$appointmentId, $sid, $qty, $line]);
        $total += $line;
    }

    // Mark the visit completed.
    $updA = $pdo->prepare(
        "UPDATE appointments SET status = 'completed', notes = CONCAT_WS('\n', NULLIF(notes,''), ?),
                treatment_assistant_id = COALESCE(?, treatment_assistant_id)
         WHERE appointment_id = ?"
    );
    $noteLine = trim(implode('. ', $teethNotes) . ($teethNotes && $notes !== '' ? '. ' : '') . $notes);
    $updA->execute([$noteLine !== '' ? 'Treatment notes: ' . $noteLine : null, $asstId ?: null, $appointmentId]);

    // Mark the treated teeth on the patient's chart (the person actually seen).
    if ($chartMarks) {
        $chartPid = (int) ($a['treated_patient_id'] ?: $a['patient_id']);
        $cs = $pdo->prepare('SELECT tooth_chart FROM patient_records WHERE patient_id = ?');
        $cs->execute([$chartPid]);
        $raw = $cs->fetchColumn();
        $chart = $raw ? (json_decode((string) $raw, true) ?: []) : [];
        foreach ($chartMarks as $t => $state) {
            $chart[(string) $t] = $state;
        }
        if ($raw === false) {
            $pdo->prepare('INSERT INTO patient_records (patient_id, tooth_chart, updated_by) VALUES (?, ?, ?)')
                ->execute([$chartPid, json_encode($chart), $uid]);
        } else {
            $pdo->prepare('UPDATE patient_records SET tooth_chart = ?, updated_by = ? WHERE patient_id = ?')
                ->execute([json_encode($chart), $uid, $chartPid]);
        }
        // The visit's tooth record shows the new condition too (migration 091);
        // tags / a note the dentist added in the chart step are kept.
        $fr = $pdo->prepare('SELECT record_id FROM tooth_records WHERE patient_id = ? AND tooth = ? AND appointment_id = ? LIMIT 1');
        $ur = $pdo->prepare('UPDATE tooth_records SET status = ?, recorded_by = ? WHERE record_id = ?');
        $ir = $pdo->prepare('INSERT INTO tooth_records (patient_id, tooth, status, appointment_id, visit_date, recorded_by) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($chartMarks as $t => $state) {
            $fr->execute([$chartPid, (int) $t, $appointmentId]);
            $rid = $fr->fetchColumn();
            if ($rid) $ur->execute([$state, $uid, $rid]);
            else $ir->execute([$chartPid, (int) $t, $state, $appointmentId, $a['scheduled_date'], $uid]);
        }
    }

    // Follow-up recommendation: hard-locked to this visit's dentist (the
    // logged-in dentist; an admin logging on their behalf keeps their name).
    if ($fuServiceName !== '') {
        $insTp = $pdo->prepare(
            'INSERT INTO treatment_plans
                (patient_id, treated_patient_id, consultation_id, service_id, assigned_dentist_id,
                 timeframe_recommendation, due_date, status)
             VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(GREATEST(?, CURDATE()), INTERVAL ' . $fuInterval . "), 'Pending')"
        );
        $insTp->execute([
            (int) $a['patient_id'], $a['treated_patient_id'] ?: null, $appointmentId, $fuServiceId,
            (int) $a['dentist_id'], $fuTimeframe, $a['scheduled_date'],
        ]);
        // A due date landing on a Sunday or a blocked operational date moves to
        // the next day the clinic is open.
        $planId = (int) $pdo->lastInsertId();
        $dd = $pdo->prepare('SELECT due_date FROM treatment_plans WHERE plan_id = ?');
        $dd->execute([$planId]);
        $pdo->prepare('UPDATE treatment_plans SET due_date = ? WHERE plan_id = ?')
            ->execute([nextOpenClinicDate((string) $dd->fetchColumn()), $planId]);
    }

    // Create / refresh the billing record routed to the cashier queue.
    if ($a['payment_id']) {
        $pay = $pdo->prepare('UPDATE payments SET total_amount = ? WHERE payment_id = ?');
        $pay->execute([$total, $a['payment_id']]);
        $pid = (int) $a['payment_id'];
    } else {
        $pay = $pdo->prepare(
            "INSERT INTO payments (appointment_id, total_amount, remaining_balance, payment_status)
             VALUES (?, ?, ?, 'unpaid')"
        );
        $pay->execute([$appointmentId, $total, $total]);
        $pid = (int) $pdo->lastInsertId();
    }

    $pdo->commit();
    recomputePayment($pid);
} catch (Throwable $ex) {
    $pdo->rollBack();
    jsonOut(['ok' => false, 'error' => $ex->getMessage()], 422);
}

// A visit booked from a follow-up recommendation closes that plan.
if (!empty($a['treatment_plan_id'])) {
    syncTreatmentPlans(null, (int) $a['treatment_plan_id']);
}
if ($fuServiceName !== '') {
    try {
        notify((int) $a['patient_id'], 'Recommended follow-up',
            'Dr. ' . ($a['dentist_name'] ?? '') . ' recommends a follow-up visit for ' . $a['patient_name'] . ': '
            . $fuServiceName . ' in about ' . strtolower($fuTimeframe) . '. Book it from your Appointments page under "Recommended Next Services".');
    } catch (Throwable $e) {
        // best-effort
    }
    logActivity('records', 'Recommended a follow-up (' . $fuServiceName . ', ' . $fuTimeframe . ') for ' . $a['patient_name'], $me);
}

$procCount = count($items);
logActivity('appointments', 'Completed the visit for ' . $a['patient_name']
    . ' and logged ' . $procCount . ' procedure' . ($procCount === 1 ? '' : 's')
    . ' totalling ' . peso($total)
    . ($asstName !== '' ? ' · assisted by ' . $asstName : ''), $me);
if ($notes !== '') {
    logActivity('records', 'Added dental procedure notes to the record for ' . $a['patient_name'], $me);
}

jsonOut(['ok' => true, 'total' => $total]);
