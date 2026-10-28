<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

/** Weekday options for the admin picker — Monday–Saturday (the clinic never opens Sunday). */
function scheduleDayOptions(): array
{
    return [
        ['value' => 1, 'label' => 'Monday'],
        ['value' => 2, 'label' => 'Tuesday'],
        ['value' => 3, 'label' => 'Wednesday'],
        ['value' => 4, 'label' => 'Thursday'],
        ['value' => 5, 'label' => 'Friday'],
        ['value' => 6, 'label' => 'Saturday'],
    ];
}

/* ---------------------------------------------------------------- Reads */
if ($method === 'GET') {

    // A dentist's own working grid — used by the dentist-facing views if needed.
    if ($action === 'mine') {
        $me = apiAuth(['dentist', 'admin']);
        $st = db()->prepare(
            'SELECT day_of_week, time_slot FROM dentist_schedules
             WHERE dentist_id = ? AND is_active = 1 ORDER BY day_of_week, time_slot'
        );
        $st->execute([(int) $me['user_id']]);
        jsonOut([
            'ok'       => true,
            'schedule' => $st->fetchAll(),
            'slots'    => generateSlots(),
            'days'     => scheduleDayOptions(),
        ]);
    }

    // Calendar month for one dentist (2026-10-19): each date's hours (its own
    // per-date hours if set, else the usual weekly grid), the usual hours,
    // absence, clinic closures and this dentist's live bookings per slot.
    if ($action === 'days') {
        $viewer = apiAuth(['dentist']);
        $id   = (int) $viewer['user_id'];
        $from = (string) ($_GET['from'] ?? '');
        $to   = (string) ($_GET['to'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)
            || $to < $from || strtotime($to) - strtotime($from) > 62 * 86400) {
            jsonOut(['ok' => false, 'error' => 'Invalid date range.'], 422);
        }
        $slots = generateSlots();
        $week = [];
        $st = db()->prepare('SELECT day_of_week, time_slot FROM dentist_schedules WHERE dentist_id = ? AND is_active = 1');
        $st->execute([$id]);
        foreach ($st->fetchAll() as $r) {
            $week[(int) $r['day_of_week']][$r['time_slot']] = true;
        }
        $over = [];
        $st = db()->prepare('SELECT work_date, slots FROM dentist_day_schedules WHERE dentist_id = ? AND work_date BETWEEN ? AND ?');
        $st->execute([$id, $from, $to]);
        foreach ($st->fetchAll() as $r) {
            $over[$r['work_date']] = array_flip(array_filter(explode(',', (string) $r['slots']), 'strlen'));
        }
        $st = db()->prepare('SELECT absence_date FROM dentist_absences WHERE dentist_id = ? AND absence_date BETWEEN ? AND ?');
        $st->execute([$id, $from, $to]);
        $absent = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
        $st = db()->prepare('SELECT blocked_date, reason FROM blocked_dates WHERE blocked_date BETWEEN ? AND ?');
        $st->execute([$from, $to]);
        $blocked = [];
        foreach ($st->fetchAll() as $r) {
            $blocked[$r['blocked_date']] = $r['reason'] ?: 'Clinic closed';
        }
        $st = db()->prepare(
            "SELECT scheduled_date, time_slot, COUNT(*) c FROM appointments
              WHERE dentist_id = ? AND scheduled_date BETWEEN ? AND ?
                AND status IN ('pending','approved','rescheduled','payment_declined')
              GROUP BY scheduled_date, time_slot"
        );
        $st->execute([$id, $from, $to]);
        $booked = [];
        foreach ($st->fetchAll() as $r) {
            $booked[$r['scheduled_date']][$r['time_slot']] = (int) $r['c'];
        }

        $days = [];
        for ($d = strtotime($from); $d <= strtotime($to); $d = strtotime('+1 day', $d)) {
            $k = date('Y-m-d', $d);
            $dow = (int) date('w', $d);
            $usual = array_values(array_filter($slots, static fn ($s) => isset($week[$dow][$s])));
            $set = $over[$k] ?? null;
            $days[$k] = [
                'usual'   => $usual,
                'slots'   => $set === null ? $usual : array_values(array_filter($slots, static fn ($s) => isset($set[$s]))),
                'edited'  => $set !== null,
                'absent'  => isset($absent[$k]),
                'closed'  => $dow === 0 ? 'Closed on Sundays' : ($blocked[$k] ?? null),
                'booked'  => (object) ($booked[$k] ?? []),
            ];
        }
        jsonOut(['ok' => true, 'days' => $days, 'slots' => $slots,
                 'today' => db()->query('SELECT CURDATE()')->fetchColumn()]);
    }

    jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
}

/* ---------------------------------------------------------------- Mutations
   Since 2026-10-19 the In-House dentist manages their own hours from
   My Availability (modules/dentist/availability.php). The admin has no
   schedule page any more and is never notified. Saving can
   turn off hours that already have bookings: processScheduleConflicts()
   then moves each affected patient to an on-duty roaming dentist, or flags
   it for the patient to pick a new time, and alerts the patient (in-app;
   notify() e-mails patients too) and the receptionists. */

/** Clinic-facing "Dr. Name". */
function drName(string $n): string
{
    return 'Dr. ' . preg_replace('/^Dr\.?\s+/i', '', trim($n));
}

/**
 * Sort this dentist's upcoming live bookings after a schedule change.
 * Group A (still inside working hours) is left alone. Group B (now outside)
 * goes to the least-busy on-duty roaming dentist with room that hour, else is
 * flagged needs_reschedule. A booking flagged earlier whose hour is back on
 * is cleared. Returns ['reassigned' => n, 'needs_reschedule' => n, 'restored' => n].
 */
function processScheduleConflicts(int $dentistId, string $dentistName, ?string $onlyDate, array $actor): array
{
    $out = ['reassigned' => 0, 'needs_reschedule' => 0, 'restored' => 0];
    $sql = "SELECT a.appointment_id, a.patient_id, a.patient_name, a.scheduled_date, a.time_slot, a.status,
                   a.treatment_plan_id, a.sched_change_status, a.booking_for, u.first_name AS account_first,
                   COALESCE(sv.service_name, 'General Consultation') AS service_name
              FROM appointments a
              JOIN users u ON u.user_id = a.patient_id
              LEFT JOIN services sv ON sv.service_id = a.service_id
             WHERE a.dentist_id = ? AND a.status IN ('pending','approved','rescheduled')
               AND a.checked_in_at IS NULL AND a.scheduled_date >= CURDATE()";
    $params = [$dentistId];
    if ($onlyDate !== null) {
        $sql .= ' AND a.scheduled_date = ?';
        $params[] = $onlyDate;
    }
    $st = db()->prepare($sql . ' ORDER BY a.scheduled_date, a.time_slot, a.appointment_id');
    $st->execute($params);
    $rows = $st->fetchAll();
    if (!$rows) {
        return $out;
    }
    $now = (string) db()->query("SELECT DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i')")->fetchColumn();
    $roaming = db()->query("SELECT user_id, full_name FROM users WHERE role = 'dentist' AND is_active = 1 AND doctor_type = 'roaming'")->fetchAll();
    $receptionists = db()->query("SELECT user_id FROM users WHERE role = 'receptionist' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
    $countSt = db()->prepare("SELECT COUNT(*) FROM appointments WHERE dentist_id = ? AND scheduled_date = ? AND time_slot = ?
                                AND status IN ('pending','approved','rescheduled')");
    $dr = drName($dentistName);

    foreach ($rows as $a) {
        $id = (int) $a['appointment_id'];
        $date = (string) $a['scheduled_date'];
        $slot = substr((string) $a['time_slot'], 0, 5);
        if ($date . ' ' . slotEndHm($slot) <= $now) {
            continue;                                   // that hour is already over
        }
        $dateLabel = date('M j, Y', strtotime($date));
        $label = $dateLabel . ' at ' . fmtTime($slot);
        $hi = 'Hi ' . (trim((string) $a['account_first']) ?: 'there') . ', ';
        $forWhom = $a['booking_for'] === 'other' && $a['patient_name'] ? ' for ' . $a['patient_name'] : '';

        if (dentistOnDuty($dentistId, $date, $slot)) {
            // Group A. A booking flagged earlier whose hour is back on is fine again.
            if ($a['sched_change_status'] === 'needs_reschedule') {
                db()->prepare('UPDATE appointments SET sched_change_status = NULL, sched_change_at = NULL, sched_change_from = NULL WHERE appointment_id = ?')
                    ->execute([$id]);
                notify((int) $a['patient_id'], 'Appointment Kept',
                    $hi . 'good news: ' . $dr . ' is available again on ' . $label . '. Your appointment' . $forWhom . ' stays as booked.',
                    $id, 'schedule_change');
                $out['restored']++;
            }
            continue;
        }
        if ($a['sched_change_status'] === 'needs_reschedule') {
            continue;                                   // already flagged and alerted
        }

        // Group B: try an on-duty roaming dentist with room that hour (not for a
        // follow-up, which stays with the dentist who recommended it).
        $pick = null;
        if (empty($a['treatment_plan_id'])) {
            $best = PHP_INT_MAX;
            foreach ($roaming as $r) {
                $rid = (int) $r['user_id'];
                if (!dentistOnDuty($rid, $date, $slot)) {
                    continue;
                }
                $countSt->execute([$rid, $date, $slot]);
                $c = (int) $countSt->fetchColumn();
                if ($c < SLOT_CAPACITY && $c < $best) { // places an hour (SLOT_CAPACITY); the in-house dentist is off then, so the roaming one may take both
                    $best = $c;
                    $pick = $r;
                }
            }
        }

        if ($pick) {
            $rid = (int) $pick['user_id'];
            db()->prepare("UPDATE appointments SET dentist_id = ?, sched_change_status = 'reassigned', sched_change_at = NOW(),
                                  sched_change_from = ? WHERE appointment_id = ?")
                ->execute([$rid, $dentistId, $id]);
            $rn = drName((string) $pick['full_name']);
            $msg = $hi . 'your appointment' . $forWhom . ' on ' . $dateLabel . ' at ' . fmtTime($slot)
                 . ' at DGT One Dental Center has been updated. You will now be attended to by ' . $rn . '. View details on your portal.';
            notify((int) $a['patient_id'], 'Appointment Updated', $msg, $id, 'schedule_change');   // also e-mailed
            if ($a['status'] !== 'pending') {           // a pending one alerts them at Approve
                notify($rid, 'New On-Call Assignment',
                    'You have been assigned an on-call patient: ' . $a['patient_name'] . ' on ' . $date . ' at ' . fmtTime($slot)
                    . ' for ' . $a['service_name'] . '.', $id, 'oncall_assigned');
            }
            foreach ($receptionists as $rc) {
                notify((int) $rc, 'Dentist Schedule Change',
                    $dr . ' turned off ' . $label . '. ' . $a['patient_name'] . ' was moved to ' . $rn . '.', $id, 'schedule_conflict');
            }
            $out['reassigned']++;
        } else {
            db()->prepare("UPDATE appointments SET sched_change_status = 'needs_reschedule', sched_change_at = NOW(),
                                  sched_change_from = ? WHERE appointment_id = ?")
                ->execute([$dentistId, $id]);
            $msg = $hi . $dr . ' modified their schedule for ' . $dateLabel . '. Please log into your patient portal to pick a new date/time slot'
                 . $forWhom . ' at your convenience.';
            notify((int) $a['patient_id'], 'Please Pick a New Time', $msg, $id, 'schedule_change');   // also e-mailed
            foreach ($receptionists as $rc) {
                notify((int) $rc, 'Action Needed: Dentist Schedule Change',
                    $dr . ' turned off ' . $label . '. ' . $a['patient_name'] . ' needs a new time (no roaming dentist available).', $id, 'schedule_conflict');
            }
            $out['needs_reschedule']++;
        }
    }
    if ($out['reassigned'] || $out['needs_reschedule']) {
        logActivity('appointments', 'Schedule change by ' . $dr . ': ' . $out['reassigned'] . ' booking(s) moved to a roaming dentist, '
            . $out['needs_reschedule'] . ' need a new time', $actor);
    }
    return $out;
}

$me = apiAuth(['admin', 'dentist']);
$in = requestBody();

/** Validate the dentist_id in the body points at an active dentist; return [id, full_name, doctor_type]. */
$requireDentist = static function (array $in): array {
    $id = (int) ($in['dentist_id'] ?? 0);
    $st = db()->prepare("SELECT full_name, doctor_type FROM users WHERE user_id = ? AND role = 'dentist' AND is_active = 1");
    $st->execute([$id]);
    $row = $st->fetch();
    if ($row === false) {
        jsonOut(['ok' => false, 'error' => 'Choose an active dentist.'], 422);
    }
    return [$id, (string) $row['full_name'], (string) $row['doctor_type']];
};

if ($action === 'set_type') {
    if ($me['role'] !== 'admin') {
        jsonOut(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    [$dentistId, $name] = $requireDentist($in);
    $type = ($in['doctor_type'] ?? '') === 'owner' ? 'owner' : 'roaming';
    db()->prepare("UPDATE users SET doctor_type = ? WHERE user_id = ? AND role = 'dentist'")
        ->execute([$type, $dentistId]);
    if ($type === 'owner') {
        seedDefaultOwnerSchedule($dentistId);
    }
    logActivity('appointments', 'Set ' . $name . '\'s duty mode to '
        . ($type === 'owner' ? 'In-House (Owner)' : 'Roaming (Associate)'), $me);
    jsonOut(['ok' => true, 'doctor_type' => $type]);
}

// Hours are the dentist's own to change (2026-10-19): the admin is read-only.
if (in_array($action, ['save_schedule', 'save_day'], true)) {
    if ($me['role'] !== 'dentist') {
        jsonOut(['ok' => false, 'error' => 'Dentists manage their own hours from My Availability. This view is read-only.'], 403);
    }
    $in['dentist_id'] = (int) $me['user_id'];
}

if ($action === 'save_schedule') {
    [$dentistId, $name, $doctorType] = $requireDentist($in);
    if ($doctorType !== 'owner') {
        jsonOut(['ok' => false, 'error' => 'Fixed weekly schedules only apply to In-House dentists. Roaming dentists are assigned per appointment upon approval.'], 422);
    }

    $validSlots = generateSlots();
    $cells = [];
    foreach ((array) ($in['cells'] ?? []) as $c) {
        $day  = (int) (is_array($c) ? ($c['day'] ?? $c[0] ?? 0) : 0);
        $slot = (string) (is_array($c) ? ($c['slot'] ?? $c[1] ?? '') : '');
        if ($day >= 1 && $day <= 6 && in_array($slot, $validSlots, true)) {
            $cells[$day . ' ' . $slot] = [$day, $slot];
        }
    }
    $cells = array_values($cells);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM dentist_schedules WHERE dentist_id = ?')->execute([$dentistId]);
        if ($cells) {
            $ins = $pdo->prepare(
                'INSERT INTO dentist_schedules (dentist_id, day_of_week, time_slot) VALUES (?, ?, ?)'
            );
            foreach ($cells as [$d, $s]) {
                $ins->execute([$dentistId, $d, $s]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonOut(['ok' => false, 'error' => 'Could not save the schedule. Please try again.'], 500);
    }

    $dayCount = count(array_unique(array_map(static fn ($c) => $c[0], $cells)));
    logActivity('appointments', 'Updated their usual weekly hours'
        . ' (' . count($cells) . ' slot' . (count($cells) === 1 ? '' : 's')
        . ' across ' . $dayCount . ' day' . ($dayCount === 1 ? '' : 's') . ')', $me);
    $conf = processScheduleConflicts($dentistId, $name, null, $me);
    jsonOut(['ok' => true, 'cells' => $cells, 'conflicts' => $conf]);
}

// One exact date: Full Day Off (slots = []) or custom hours. Reset (or the
// usual weekly hours) goes back to the weekly grid. Bookings inside hours
// that are turned off are sorted by processScheduleConflicts().
if ($action === 'save_day') {
    [$dentistId, $name, $doctorType] = $requireDentist($in);
    if ($doctorType !== 'owner') {
        jsonOut(['ok' => false, 'error' => 'Only In-House dentists have set hours.'], 422);
    }
    $date = (string) ($in['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
        jsonOut(['ok' => false, 'error' => 'Invalid date.'], 422);
    }
    if ($date < db()->query('SELECT CURDATE()')->fetchColumn()) {
        jsonOut(['ok' => false, 'error' => 'Past dates can no longer be changed.'], 422);
    }
    if ((int) date('w', strtotime($date)) === 0) {
        jsonOut(['ok' => false, 'error' => 'The clinic is closed on Sundays.'], 422);
    }
    $bl = db()->prepare('SELECT 1 FROM blocked_dates WHERE blocked_date = ?');
    $bl->execute([$date]);
    if ($bl->fetchColumn()) {
        jsonOut(['ok' => false, 'error' => 'The clinic is closed on this date.'], 422);
    }

    $all = generateSlots();
    $wk = db()->prepare('SELECT time_slot FROM dentist_schedules WHERE dentist_id = ? AND day_of_week = ? AND is_active = 1');
    $wk->execute([$dentistId, (int) date('w', strtotime($date))]);
    $wkSet = array_flip($wk->fetchAll(PDO::FETCH_COLUMN));
    $usual = array_values(array_filter($all, static fn ($s) => isset($wkSet[$s])));

    $reset = !empty($in['reset']);
    $want  = $reset ? $usual : array_values(array_filter(
        $all, static fn ($s) => in_array($s, array_map('strval', (array) ($in['slots'] ?? [])), true)
    ));
    $dateLabel = date('M j, Y', strtotime($date));

    if ($reset || $want === $usual) {
        db()->prepare('DELETE FROM dentist_day_schedules WHERE dentist_id = ? AND work_date = ?')->execute([$dentistId, $date]);
        logActivity('appointments', 'Set their hours on ' . $dateLabel . ' back to the usual weekly schedule', $me);
        $edited = false;
    } else {
        db()->prepare(
            'INSERT INTO dentist_day_schedules (dentist_id, work_date, slots, updated_by) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE slots = VALUES(slots), updated_by = VALUES(updated_by)'
        )->execute([$dentistId, $date, implode(',', $want), (int) $me['user_id']]);
        logActivity('appointments', 'Changed their hours on ' . $dateLabel . ' to '
            . ($want ? implode(', ', array_map(static fn ($s) => date('g:i A', strtotime($s)), $want)) : 'a full day off'), $me);
        $edited = true;
    }
    $conf = processScheduleConflicts($dentistId, $name, $date, $me);
    jsonOut(['ok' => true, 'slots' => $want, 'edited' => $edited, 'conflicts' => $conf]);
}

jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
