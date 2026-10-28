<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$me     = apiAuth();
$role   = $me['role'];
$uid    = (int) $me['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Delayed / Standby after the grace period, and the end-of-day No-Show batch
// (migration 088), applied before anything reads appointments.
runAppointmentSweeps();

/* ---------------------------------------------------------------- helpers */
/* generateSlots() lives in includes/functions.php (shared with settings API). */

/** The clinic is closed every Sunday — no bookings, walk-ins or reschedules. */
function isClinicClosed(string $date): bool
{
    return date('w', strtotime($date)) === '0' && !sundayOpen();
}

/**
 * Best-effort: age out pending requests the clinic never acted on. A `pending`
 * row whose requested date is now in the past was never approved or declined, so
 * flip it to `cancelled` — otherwise it sits on the patient's active tabs
 * forever. Only `pending` is swept: an `approved`/`rescheduled` row in the past
 * may be a visit that actually happened and is only waiting on the dentist's
 * procedure log, so the front desk resolves those by hand. Swallows every error
 * so a list request never fails on cleanup (same spirit as touchPresence()).
 */
function expireLapsedPendingAppointments(): void
{
    try {
        $reason = 'This request expired because it was not approved before the requested date.';
        // Also sweeps a payment_declined row (2026-09) — the patient never
        // resubmitted a corrected reference/receipt before the requested date
        // passed, so it's just as stale as an ordinary never-approved pending
        // request and should expire the same way rather than sitting in
        // "awaiting resubmission" forever.
        $stale = db()->query(
            "SELECT appointment_id, patient_id, patient_name, scheduled_date
               FROM appointments
              WHERE status IN ('pending', 'payment_declined') AND scheduled_date < CURDATE()"
        )->fetchAll();
        if (!$stale) {
            return;
        }
        $upd = db()->prepare(
            "UPDATE appointments
                SET status = 'cancelled', cancellation_reason = ?, staff_cancellation_reason = ?,
                    decline_reason = NULL, payment_decline_code = NULL
              WHERE appointment_id = ? AND status IN ('pending', 'payment_declined')"
        );
        foreach ($stale as $r) {
            $upd->execute([$reason, $reason, $r['appointment_id']]);
            notify((int) $r['patient_id'], 'Appointment request expired',
                'Your appointment request for ' . $r['patient_name'] . ' on ' . $r['scheduled_date']
              . ' expired because it was not approved before that date. Please book again if you still need a visit.', (int) $r['appointment_id']);
        }
    } catch (Throwable $e) {
        // cleanup must never break the request
    }
}

/**
 * Notifies a patient the day before their confirmed appointment — a one-time
 * reminder per appointment; reminder_sent_at (migration 048) guards against
 * re-sending it on every subsequent action=list call. Best-effort, same
 * convention as expireLapsedPendingAppointments() just above — a notify
 * failure here must never break the request that happened to trigger it.
 */
function sendUpcomingAppointmentReminders(): void
{
    try {
        $due = db()->query(
            "SELECT appointment_id, patient_id, patient_name, scheduled_date, time_slot
               FROM appointments
              WHERE status IN ('approved', 'rescheduled')
                AND scheduled_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                AND reminder_sent_at IS NULL"
        )->fetchAll();
        if (!$due) {
            return;
        }
        $mark = db()->prepare('UPDATE appointments SET reminder_sent_at = NOW() WHERE appointment_id = ?');
        foreach ($due as $r) {
            $start = fmtTime($r['time_slot']);
            $end   = fmtTime(slotEndHm((string) $r['time_slot']));
            $dateLabel = date('M j, Y', strtotime($r['scheduled_date']));
            notify((int) $r['patient_id'], 'Appointment reminder',
                'This is a reminder of your appointment for ' . $r['patient_name']
              . ' at DGT One Dental Center - Bulacan on ' . $dateLabel . '. Estimated time: ' . $start . '–' . $end
              . '. Kindly arrive on time, and let us know in advance from the Appointments page if you\'re unable to attend.', (int) $r['appointment_id']);
            $mark->execute([$r['appointment_id']]);
        }
    } catch (Throwable $e) {
        // best-effort — a reminder failure must never break the request
    }
}

function activeDentists(): array
{
    return db()->query(
        "SELECT user_id, full_name, doctor_type FROM users
         WHERE role = 'dentist' AND is_active = 1 ORDER BY full_name"
    )->fetchAll();
}

/**
 * A date+time is full once it holds this many live bookings (2026-10-26: 2 per
 * hour, was 5). The first place is the in-house dentist's, the second the
 * roaming dentist's (see dentistHourCap()).
 */
function slotCapacity(): int
{
    return SLOT_CAPACITY;   // includes/functions.php
}

/**
 * How many of an hour's places one dentist may hold (2026-10-26): with 2 places
 * an hour, the in-house dentist takes 1 and the roaming dentist takes 1. When
 * the other type has nobody on duty that hour, the one who is may take both.
 */
function dentistHourCap(int $dentistId, string $date, string $slot): int
{
    $onDuty = dentistsOnDutyAt($date, $slot);
    $type = $onDuty[$dentistId]['doctor_type'] ?? null;
    if ($type === null) {
        $t = db()->prepare('SELECT doctor_type FROM users WHERE user_id = ?');
        $t->execute([$dentistId]);
        $type = (string) $t->fetchColumn();
    }
    $other = $type === 'owner' ? 'roaming' : 'owner';
    foreach ($onDuty as $id => $d) {
        if ((int) $id !== $dentistId && ($d['doctor_type'] ?? '') === $other) {
            return max(1, slotCapacity() - 1);
        }
    }
    return slotCapacity();
}

/** Live bookings one dentist holds at a date + slot, $excludeId left out. */
function dentistHourCount(int $dentistId, string $date, string $slot, int $excludeId = 0): int
{
    $st = db()->prepare("SELECT COUNT(*) FROM appointments
                         WHERE scheduled_date = ? AND time_slot = ? AND dentist_id = ? AND appointment_id <> ?
                           AND status IN ('pending','approved','rescheduled') AND (delayed_at IS NULL OR checked_in_at IS NOT NULL)");
    $st->execute([$date, $slot, $dentistId, $excludeId]);
    return (int) $st->fetchColumn();
}

/** $online: count the way the patient portal does (ONLINE_SEAT_SQL). */
function slotIsFull(string $date, string $slot, ?int $excludeId = null, bool $online = false): bool
{
    $sql = "SELECT COUNT(*) FROM appointments
            WHERE scheduled_date = ? AND time_slot = ?
              AND " . ($online ? ONLINE_SEAT_SQL : SEAT_SQL);
    $params = [$date, $slot];
    if ($excludeId !== null) {
        $sql .= ' AND appointment_id <> ?';
        $params[] = $excludeId;
    }
    $st = db()->prepare($sql);
    $st->execute($params);
    return (int) $st->fetchColumn() >= slotCapacity();
}

/**
 * The default queue for new online bookings and walk-ins: the in-house Owner
 * Dentist. Falls back to any active dentist if no owner is tagged.
 */
function ownerDentistId(): ?int
{
    $id = db()->query(
        "SELECT user_id FROM users
         WHERE role = 'dentist' AND is_active = 1 AND doctor_type = 'owner'
         ORDER BY user_id LIMIT 1"
    )->fetchColumn();
    if ($id === false) {
        $id = db()->query(
            "SELECT user_id FROM users WHERE role = 'dentist' AND is_active = 1 ORDER BY user_id LIMIT 1"
        )->fetchColumn();
    }
    return $id === false ? null : (int) $id;
}

/**
 * Every active dentist (owner or roaming) genuinely on duty for this exact
 * date + slot — their own working grid minus any one-off absence for that
 * date. dentist_id => the row from activeDentists() (carries doctor_type, used
 * by assignAppointmentDentist()'s owner-first tie-break). This is what "is this
 * slot even offered" now checks for online booking (2026-09) — a day/slot is
 * open whenever ANY active dentist covers it, not just the Owner, since a
 * booking can now auto-route to a roaming dentist the Owner doesn't share a
 * schedule with.
 */
function dentistsOnDutyAt(string $date, string $slot): array
{
    $out = [];
    foreach (activeDentists() as $d) {
        if (dentistOnDuty((int) $d['user_id'], $date, $slot)) {
            $out[(int) $d['user_id']] = $d;
        }
    }
    return $out;
}

/**
 * Auto-routing (2026-09, revised a second time to a "dynamic per-dentist cap"
 * waterfall — replaces the earlier flat-1-booking-per-dentist version below,
 * per an explicit instructor requirement pasted verbatim asking for "the
 * defined maximum booking limit per hour for that slot" to gate the Owner
 * before Roaming overflow). The earlier version treated "the Owner's slot"
 * as exactly one live booking per hour (a dentist can only really see one
 * patient at once) — correct physically, but it meant the very 2nd booking
 * in an hour could already spill to Roaming, which the clinic's own dentist
 * confirmed was NOT the intent: the Owner should absorb almost an entire
 * hour's shared capacity before Roaming ever picks one up (confirmed via a
 * concrete worked example — 5 bookings in one hour with 1 Owner + 1 Roaming
 * on duty should land 4-on-Owner / 1-on-Roaming, not 4-on-Owner via 3
 * separate free/busy flips or a 1-then-back-and-forth split).
 *
 * $hourlyCap below is now `max(1, slotCapacity() - 1)` — "every chair in the
 * shared hourly pool except the last one" — rather than a flat 1. This is a
 * deliberate reuse of the existing shared-capacity concept (slotCapacity(),
 * a flat 5) rather than a brand-new disconnected magic number: with the
 * default capacity of 5 that's a cap of 4, which reproduces the confirmed
 * example exactly, and it scales automatically if the clinic's shared slot
 * capacity setting is ever changed. Rule, evaluated per new booking:
 *   1. Any ON-DUTY IN-HOUSE (doctor_type = 'owner') dentist still under
 *      $hourlyCap for this exact date+slot gets it. With more than one
 *      in-house dentist on duty, the least-loaded one among those still
 *      under cap wins ("if the Owner runs out, it goes to the other
 *      in-house [dentist]").
 *   2. Only once EVERY on-duty in-house dentist has hit $hourlyCap this
 *      exact hour does an on-duty ROAMING dentist get it (same "still under
 *      cap" rule, least-loaded among those under cap).
 *   3. If literally every on-duty dentist (in-house and roaming) is already
 *      at cap here — only reachable when the on-duty roster's combined cap
 *      is less than slotCapacity() (e.g. just 1 Owner + 1 Roaming, capped at
 *      4 each = 8 > 5, so in practice not reachable with the current
 *      roster, but possible with a smaller one) — this falls through to the
 *      least-loaded dentist overall, in-house still breaking ties, rather
 *      than leaving the booking unassignable.
 * Falls back to the Owner (or any active dentist) if nobody is actually on
 * duty then — defensive only, since a slot with nobody on duty shouldn't
 * have been offered in the first place (see dentistsOnDutyAt() above).
 *
 * Walk-ins are NOT routed through this function at all — action=walk_in
 * always assigns the Owner as a placeholder, with the receptionist picking
 * the real dentist by hand at Approve (unchanged, per the same requirement's
 * explicit walk-in exception).
 *
 * (Historical — the immediately-prior version, 2026-09: $hourlyCap was a
 * flat 1, reasoned from "a dentist can only physically see one patient in
 * one exact hour." It was verified and deployed, then reverted the same day
 * once the user worked through a concrete example and clarified the Owner
 * was meant to absorb far more of the hour before overflowing — see above.)
 */
function assignAppointmentDentist(string $date, string $slot, int $excludeDentistId = 0): ?int
{
    $onDuty = dentistsOnDutyAt($date, $slot);
    // A dentist who declined this very appointment (on-call decline, migration
    // 062) is never handed it straight back by the auto-router.
    unset($onDuty[$excludeDentistId]);
    if (!$onDuty) {
        return ownerDentistId();
    }
    $ids = array_keys($onDuty);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $cnt = db()->prepare(
        "SELECT dentist_id, COUNT(*) c FROM appointments
         WHERE scheduled_date = ? AND time_slot = ? AND dentist_id IN ($ph)
           AND status IN ('pending','approved','rescheduled') AND (delayed_at IS NULL OR checked_in_at IS NOT NULL)
         GROUP BY dentist_id"
    );
    $cnt->execute(array_merge([$date, $slot], $ids));
    $counts = array_fill_keys($ids, 0);
    foreach ($cnt->fetchAll() as $r) {
        $counts[(int) $r['dentist_id']] = (int) $r['c'];
    }

    // "Every chair in the shared hourly pool except the last one" — see the
    // doc comment above for why this replaced a flat 1.
    $hourlyCap = max(1, slotCapacity() - 1);

    usort($ids, static function ($a, $b) use ($onDuty, $counts, $hourlyCap) {
        // 1. Free (still under $hourlyCap) always beats busy — this is the
        //    actual "ran out of their own slot" gate.
        $freeA = $counts[$a] < $hourlyCap ? 0 : 1;
        $freeB = $counts[$b] < $hourlyCap ? 0 : 1;
        if ($freeA !== $freeB) {
            return $freeA <=> $freeB;
        }
        // 2. Among equally free/busy candidates, in-house outranks roaming.
        $tierA = ($onDuty[$a]['doctor_type'] ?? '') === 'owner' ? 0 : 1;
        $tierB = ($onDuty[$b]['doctor_type'] ?? '') === 'owner' ? 0 : 1;
        if ($tierA !== $tierB) {
            return $tierA <=> $tierB;
        }
        // 3. Within the same free/busy state and tier, least-loaded first —
        //    spreads fairly across multiple in-house or multiple roaming
        //    dentists rather than always picking the same one.
        if ($counts[$a] !== $counts[$b]) {
            return $counts[$a] <=> $counts[$b];
        }
        return $a <=> $b;   // deterministic final tie-break
    });

    return $ids[0];
}

/** True when $dentistId is an active, roaming-associate dentist. */
function isDentistRoaming(int $dentistId): bool
{
    $st = db()->prepare("SELECT doctor_type FROM users WHERE user_id = ? AND role = 'dentist'");
    $st->execute([$dentistId]);
    return $st->fetchColumn() === 'roaming';
}

/**
 * On-call alert for a roaming dentist (2026-09) — they don't actively monitor
 * a fixed daily schedule grid the way the in-house Owner does, so once a
 * booking is actually assigned to them (not merely auto-routed at creation —
 * see assignAppointmentDentist() — but confirmed by a receptionist at Approve
 * or a live Reassign), they get a real notifications-table entry naming the
 * patient, date, time and service, surfaced on their own dashboard's "On-Call
 * Assignments" widget (see modules/dentist/dashboard.php). Best-effort — a
 * notify failure must never undo the approval/reassignment that triggered it.
 */
function notifyRoamingDentistAssigned(int $dentistId, array $appt): void
{
    if (!isDentistRoaming($dentistId)) {
        return;
    }
    try {
        notify($dentistId, 'New On-Call Assignment',
            'You have been assigned an on-call patient: ' . $appt['patient_name']
            . ' on ' . $appt['scheduled_date'] . ' at ' . fmtTime($appt['time_slot'])
            . ' for ' . ($appt['requested_service'] ?: 'General Consultation') . '.',
            (int) $appt['appointment_id'], 'oncall_assigned');
    } catch (Throwable $e) {
        // best-effort — never undo the approval/reassignment itself
    }
}

/** Long human date + time, e.g. "Oct 6, 2026 at 2:00 PM". */
function scheduleLabel(string $date, string $slot): string
{
    $ts = strtotime($date);
    return ($ts ? date('M j, Y', $ts) : $date) . ' at ' . fmtTime($slot);
}

/**
 * E-mail the booking account that the clinic moved their appointment
 * (migration 062 workflow). Best-effort: SMTP trouble never undoes the
 * reschedule, and an account with no e-mail on file is simply skipped.
 */
function emailPatientRescheduled(array $a, string $service, string $oldLabel, string $newLabel, string $dentistName, string $reason): void
{
    try {
        require_once __DIR__ . '/../includes/mailer.php';
        $st = db()->prepare('SELECT email, first_name, full_name FROM users WHERE user_id = ?');
        $st->execute([(int) $a['patient_id']]);
        $u = $st->fetch();
        $to = (string) ($u['email'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $name = trim((string) ($u['first_name'] ?? '')) ?: (string) ($u['full_name'] ?? 'there');
        $body = 'Hi ' . $h($name) . ', your appointment for <strong>' . $h($a['patient_name']) . '</strong> has been moved.<br><br>'
              . '<strong>Clinic:</strong> DGT One Dental Center - Bulacan<br>'
              . '<strong>Service:</strong> ' . $h($service) . '<br>'
              . '<strong>Old schedule:</strong> <span style="text-decoration:line-through;">' . $h($oldLabel) . '</span><br>'
              . '<strong>New schedule:</strong> ' . $h($newLabel) . '<br>'
              . '<strong>Assigned dentist:</strong> ' . $h($dentistName !== '' ? 'Dr. ' . $dentistName : 'To be confirmed') . '<br>'
              . '<strong>Reason:</strong> ' . $h($reason);
        $html = mailShell('Appointment Rescheduled', $body, 'View My Appointments',
            appBaseUrl() . '/modules/patient/appointments',
            'If the new schedule does not work for you, reschedule or cancel from your Appointments page, or call us at 09764484252.');
        sendAppMail($to, 'Your DGT One Dental Center appointment has been rescheduled', $html);
    } catch (Throwable $e) {
        // best-effort
    }
}

/**
 * jsonOut a 422 when a dentist is not on their working schedule for a
 * date + slot (migration 031, `dentist_schedules`). Used by every staff path
 * that pins a dentist to a slot (walk-in, approve, reassign, reschedule).
 */
function assertDentistOnDuty(int $dentistId, string $date, string $slot): void
{
    if ($dentistId <= 0) {
        return;
    }
    if (dentistOnDuty($dentistId, $date, $slot)) {
        return;
    }
    $nm = db()->prepare('SELECT full_name FROM users WHERE user_id = ?');
    $nm->execute([$dentistId]);
    $name = (string) ($nm->fetchColumn() ?: 'That dentist');
    jsonOut(['ok' => false, 'error' => $name . ' is not on duty at ' . fmtTime($slot) . ' on ' . $date
        . '. Choose a dentist working that day and time.'], 422);
}

/**
 * jsonOut a 422 when assigning $dentistId to this date + slot would give them,
 * personally, the slot's entire shared capacity (2026-09) — a receptionist
 * backstop for the Approve/Reassign dentist picker, which now disables a
 * dentist client-side under the identical condition (action=dentists'
 * slot_full). The clinic's 5-chair cap per slot stays one shared total across
 * every on-duty dentist; this only stops it from quietly landing entirely on
 * one of them. $excludeId leaves that one appointment out of the dentist's own
 * count, so re-confirming/re-picking their own already-assigned appointment
 * never locks them out of it.
 */
function assertDentistNotOverloaded(int $dentistId, string $date, string $slot, int $excludeId = 0): void
{
    if ($dentistId <= 0) {
        return;
    }
    $sql = "SELECT COUNT(*) FROM appointments
            WHERE scheduled_date = ? AND time_slot = ? AND dentist_id = ?
              AND status IN ('pending','approved','rescheduled') AND (delayed_at IS NULL OR checked_in_at IS NOT NULL)";
    $params = [$date, $slot, $dentistId];
    if ($excludeId > 0) {
        $sql .= ' AND appointment_id <> ?';
        $params[] = $excludeId;
    }
    $st = db()->prepare($sql);
    $st->execute($params);
    $cap = dentistHourCap($dentistId, $date, $slot);
    if ((int) $st->fetchColumn() < $cap) {
        return;
    }
    $nm = db()->prepare('SELECT full_name FROM users WHERE user_id = ?');
    $nm->execute([$dentistId]);
    $name = (string) ($nm->fetchColumn() ?: 'That dentist');
    jsonOut(['ok' => false, 'error' => $name . ' already has ' . $cap . ' patient' . ($cap === 1 ? '' : 's') . ' at ' . fmtTime($slot) . ' on ' . $date
        . ($cap < slotCapacity() ? '. The other place that hour belongs to the ' . (isDentistRoaming($dentistId) ? 'in-house' : 'roaming') . ' dentist.' : '. Choose a different dentist for this slot.')], 422);
}

/**
 * Walk-in roaming gate (2026-10): the same waterfall online bookings follow
 * (assignAppointmentDentist). A roaming dentist can't be picked for a walk-in
 * while any on-duty in-house dentist still has room under the hourly cap
 * (slotCapacity() - 1, i.e. 4). Returns the blocking in-house dentist's name,
 * or null when the roaming dentist may take the patient.
 */
function roamingLockedBy(int $dentistId, string $date, string $slot): ?string
{
    $onDuty = dentistsOnDutyAt($date, $slot);
    if (!isset($onDuty[$dentistId]) || $onDuty[$dentistId]['doctor_type'] !== 'roaming') {
        return null;
    }
    $cap = max(1, slotCapacity() - 1);
    $st = db()->prepare("SELECT COUNT(*) FROM appointments
                         WHERE scheduled_date = ? AND time_slot = ? AND dentist_id = ?
                           AND status IN ('pending','approved','rescheduled') AND (delayed_at IS NULL OR checked_in_at IS NOT NULL)");
    foreach ($onDuty as $id => $d) {
        if ($d['doctor_type'] !== 'owner') {
            continue;
        }
        $st->execute([$date, $slot, $id]);
        if ((int) $st->fetchColumn() < $cap) {
            return (string) $d['full_name'];
        }
    }
    return null;
}

/**
 * Follow-up booking rules (Treatment Plan module, migration 066). A booking
 * made from a treatment plan stays with the plan's dentist, so a slot is open
 * only when ALL of these hold:
 *   - that dentist is on duty then (weekly grid / absences; roaming = always),
 *   - the clinic-wide hourly cap (slotCapacity(), 2) is not reached,
 *   - a ROAMING dentist has no other live booking that hour (one patient at a
 *     time; they are on call, not running a chair all day),
 *   - an IN-HOUSE dentist is not already holding the whole hourly cap alone.
 * $excludeId leaves the appointment being moved out of every count. Returns
 * a patient-facing reason, or null when the slot is fine.
 */
function planSlotProblem(array $plan, string $date, string $slot, int $excludeId = 0): ?string
{
    $did = (int) $plan['assigned_dentist_id'];
    if (!dentistOnDuty($did, $date, $slot)) {
        return 'Dr. ' . $plan['dentist_name'] . ' is not available at that time. Please choose another slot.';
    }
    $ex = $excludeId > 0 ? ' AND appointment_id <> ' . $excludeId : '';
    $st = db()->prepare("SELECT COUNT(*) AS total, SUM(dentist_id = ?) AS mine FROM appointments
                         WHERE scheduled_date = ? AND time_slot = ?
                           AND status IN ('pending','approved','rescheduled') AND (delayed_at IS NULL OR checked_in_at IS NOT NULL)" . $ex);
    $st->execute([$did, $date, $slot]);
    $c = $st->fetch();
    if ((int) $c['total'] >= slotCapacity()) {
        return 'That date and time is already fully booked. Please choose another slot.';
    }
    $mine = (int) ($c['mine'] ?? 0);
    if ($plan['doctor_type'] === 'roaming' ? $mine > 0 : $mine >= dentistHourCap($did, $date, $slot)) {
        return 'Dr. ' . $plan['dentist_name'] . ' already has a patient at that time. Please choose another slot.';
    }
    return null;
}

/**
 * Alert a roaming dentist that a patient booked the follow-up they recommended
 * (bell notification in their On-Call list + e-mail). The front desk still has
 * to approve it; approval sends the usual "New On-Call Assignment" too.
 */
function notifyRoamingFollowUpBooked(array $plan, int $appointmentId, string $patientName, string $date, string $slot): void
{
    if (($plan['doctor_type'] ?? '') !== 'roaming') {
        return;
    }
    $when = scheduleLabel($date, $slot);
    try {
        notify((int) $plan['assigned_dentist_id'], 'New On-Call Assignment',
            $patientName . ' booked the follow-up you recommended: ' . $plan['service_name'] . ' on ' . $when
            . '. It is waiting for front desk approval.', $appointmentId, 'oncall_assigned');
    } catch (Throwable $e) {
        // best-effort
    }
    try {
        $to = (string) ($plan['dentist_email'] ?? '');
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            require_once __DIR__ . '/../includes/mailer.php';
            $h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
            $body = 'Hi Dr. ' . $h($plan['dentist_name']) . ', a patient booked the follow-up visit you recommended.<br><br>'
                  . '<strong>Patient:</strong> ' . $h($patientName) . '<br>'
                  . '<strong>Service:</strong> ' . $h($plan['service_name']) . '<br>'
                  . '<strong>Schedule:</strong> ' . $h($when) . '<br>'
                  . '<strong>Clinic:</strong> DGT One Dental Center - Bulacan';
            $html = mailShell('Follow-up Booked', $body, 'Open My Schedule',
                appBaseUrl() . '/modules/dentist/myschedule',
                'The front desk will confirm this booking. You will get another notice once it is approved.');
            sendAppMail($to, 'Follow-up booked: ' . $patientName . ' on ' . $when, $html);
        }
    } catch (Throwable $e) {
        // best-effort: SMTP trouble never undoes the booking
    }
}

/* ---------------------------------------------------------------- GET */
if ($method === 'GET') {

    if ($action === 'dentists') {
        $dentists = activeDentists();
        // Direct online booking only ever offers the Owner Dentist(s).
        if (($_GET['type'] ?? '') === 'owner') {
            $dentists = array_values(array_filter(
                $dentists,
                static fn ($d) => $d['doctor_type'] === 'owner'
            ));
        }
        // ?date=YYYY-MM-DD (+ optional &slot=HH:MM) annotates which dentists are
        // on their working schedule then, so the staff assignment dropdowns can
        // disable the off-duty ones. When &slot= is also given, each dentist's
        // own live booking count for that exact date+slot is included too
        // (slot_count / slot_full, 2026-09) — the receptionist's Approve/
        // Reassign picker uses this to stop one dentist from quietly ending up
        // with the slot's whole shared capacity to themselves (the clinic's own
        // 5-chair cap is still a single shared total across every dentist —
        // this only caps how much of it any one dentist can personally hold).
        // &exclude_id= (an appointment id) leaves that one row out of the count
        // so re-confirming/re-picking a dentist's own already-assigned
        // appointment never locks them out of their own slot.
        $date = $_GET['date'] ?? '';
        $slot = $_GET['slot'] ?? '';
        $excludeId = (int) ($_GET['exclude_id'] ?? 0);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $counts = [];
            if ($slot !== '') {
                $sql = "SELECT dentist_id, COUNT(*) c FROM appointments
                        WHERE scheduled_date = ? AND time_slot = ?
                          AND status IN ('pending','approved','rescheduled') AND (delayed_at IS NULL OR checked_in_at IS NOT NULL)";
                $params = [$date, $slot];
                if ($excludeId > 0) {
                    $sql .= ' AND appointment_id <> ?';
                    $params[] = $excludeId;
                }
                $sql .= ' GROUP BY dentist_id';
                $st = db()->prepare($sql);
                $st->execute($params);
                foreach ($st->fetchAll() as $r) {
                    $counts[(int) $r['dentist_id']] = (int) $r['c'];
                }
            }
            foreach ($dentists as &$d) {
                $work = dentistBookableSlots((int) $d['user_id'], $date);
                $d['works_today'] = $work !== [];
                $d['day_slots']   = $work;
                $d['on_duty']     = $slot !== '' ? in_array($slot, $work, true) : ($work !== []);
                if ($slot !== '') {
                    $d['slot_count'] = $counts[(int) $d['user_id']] ?? 0;
                    $d['slot_cap']   = dentistHourCap((int) $d['user_id'], $date, $slot);
                    $d['slot_full']  = $d['slot_count'] >= $d['slot_cap'];
                    // Walk-in picker: roaming stays locked until the in-house
                    // dentist has hit the hourly cap (same as online routing).
                    $d['roaming_locked'] = $d['on_duty'] && $d['doctor_type'] === 'roaming'
                        && roamingLockedBy((int) $d['user_id'], $date, $slot) !== null;
                }
            }
            unset($d);
        }
        jsonOut(['ok' => true, 'dentists' => $dentists]);
    }

    if ($action === 'slot_list') {          // bookable time slots for the date pickers
        jsonOut(['ok' => true, 'slots' => generateSlots()]);
    }

    if ($action === 'slots') {
        $date = $_GET['date'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            jsonOut(['ok' => false, 'error' => 'Invalid date'], 422);
        }
        if (isClinicClosed($date)) {
            jsonOut(['ok' => true, 'blocked' => true, 'reason' => 'The clinic is closed on Sundays.', 'slots' => []]);
        }
        $blk = db()->prepare('SELECT reason FROM blocked_dates WHERE blocked_date = ?');
        $blk->execute([$date]);
        if ($b = $blk->fetch()) {
            jsonOut(['ok' => true, 'blocked' => true, 'reason' => $b['reason'] ?: 'The clinic is closed on this date.', 'slots' => []]);
        }
        // ?dentist_id= checks one specific dentist's own grid (staff walk-in /
        // reassign modals, which pass the Owner explicitly). With no dentist_id
        // (a generic booking date field, e.g. window.wireClinicDate — the patient
        // wizard's own calendar widget uses action=availability instead), a day's
        // offered slots are now the UNION of every active dentist's working slots
        // (2026-09) — open whenever ANY on-duty dentist covers it, since a booking
        // can auto-route to whichever one is on duty, not just the Owner.
        $explicitDentist = (int) ($_GET['dentist_id'] ?? 0);
        if ($explicitDentist > 0) {
            $workSlots = dentistBookableSlots($explicitDentist, $date);
        } else {
            $union = [];
            foreach (activeDentists() as $d) {
                $union = array_merge($union, dentistBookableSlots((int) $d['user_id'], $date));
            }
            $union = array_unique($union);
            $workSlots = array_values(array_filter(generateSlots(), static fn ($s) => in_array($s, $union, true)));
        }
        if ($workSlots === []) {
            jsonOut(['ok' => true, 'blocked' => true, 'reason' => 'No dentist is scheduled to work on this day.', 'slots' => []]);
        }
        $taken = db()->prepare(
            "SELECT time_slot, COUNT(*) c FROM appointments
             WHERE scheduled_date = ? AND status IN ('pending','approved','rescheduled') AND (delayed_at IS NULL OR checked_in_at IS NOT NULL)
             GROUP BY time_slot"
        );
        $taken->execute([$date]);
        $map = [];
        foreach ($taken->fetchAll() as $r) {
            $map[$r['time_slot']] = (int) $r['c'];
        }
        $capacity = slotCapacity(); // 2 bookings per slot (1 in-house + 1 roaming)
        $out = [];
        foreach ($workSlots as $s) {                     // only the dentist's on-duty slots
            $out[] = ['time' => $s, 'available' => ($map[$s] ?? 0) < $capacity];
        }
        jsonOut(['ok' => true, 'blocked' => false, 'slots' => $out]);
    }

    if ($action === 'availability') {
        // Per-day booking availability for a date range — powers the patient
        // booking calendar (month grid + per-day slot list). Same capacity model
        // as action=slots: a date+slot is free while its live pending/scheduled/
        // rescheduled count is below slotCapacity() (2 per slot since 2026-10-26).
        $from = $_GET['from'] ?? '';
        $to   = $_GET['to'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to) {
            jsonOut(['ok' => false, 'error' => 'Invalid date range'], 422);
        }
        if ((strtotime($to) - strtotime($from)) / 86400 > 62) {   // <= ~2 months per call
            jsonOut(['ok' => false, 'error' => 'Range too large'], 422);
        }

        $slots    = generateSlots();
        $capacity = slotCapacity();
        // Genuinely past days render as unbookable; online bookings additionally
        // need 2 days' notice (re-added again 2026-09 — see action=create), so
        // the floor here is "today + 2" rather than just "today". This only
        // governs what a patient can newly book from the calendar — it doesn't
        // affect an existing appointment or a reschedule (action=reschedule has
        // its own, separate "tomorrow onward" floor). Computed on the DB clock
        // (see the same note in action=create) so it agrees with that check.
        // ?dentist_id= (2026-09) scopes the whole day-range to a specific
        // dentist instead of the Owner default — used by the Reschedule modals'
        // month-grid picker, which has no 2-day lead-time floor (only "today
        // onward" for staff / "tomorrow onward" for a patient's own reschedule,
        // both enforced client-side + re-checked in action=reschedule), so the
        // floor here drops to plain "today" whenever a dentist is specified.
        $checkDentistId = (int) ($_GET['dentist_id'] ?? 0);
        // ?plan_id= (Treatment Plan module, migration 066): a follow-up booking
        // (or a reschedule of one, with &exclude_id=) is locked to the plan's
        // dentist and follows planSlotProblem()'s rules per slot.
        $plan = null;
        $planExclude = (int) ($_GET['exclude_id'] ?? 0);
        $planId = (int) ($_GET['plan_id'] ?? 0);
        if ($planId > 0) {
            $plan = treatmentPlanDetail($planId);
            if (!$plan || ($role === 'patient' && (int) $plan['patient_id'] !== $uid)) {
                jsonOut(['ok' => false, 'error' => 'Follow-up not found.'], 404);
            }
            $checkDentistId = (int) $plan['assigned_dentist_id'];
        }
        // ?declined_id= (staff, 2026-10-26): rescheduling a visit a roaming
        // dentist declined (no dentist, oncall_declined_by set). It can't go
        // back to that dentist, so an hour only counts as open while another
        // on-duty dentist still has a place there (dentistHourCap()); the rest
        // show as Unavailable. Like a dentist-scoped call, it opens from today.
        $declinedAppt = null;
        $declinedId = $role !== 'patient' ? (int) ($_GET['declined_id'] ?? 0) : 0;
        if ($declinedId > 0) {
            $dq = db()->prepare("SELECT appointment_id, oncall_declined_by FROM appointments
                                 WHERE appointment_id = ? AND dentist_id IS NULL AND oncall_declined_by IS NOT NULL");
            $dq->execute([$declinedId]);
            $declinedAppt = $dq->fetch() ?: null;
        }
        $today    = (string) db()->query('SELECT CURDATE()')->fetchColumn();
        // Online booking needs onlineLeadDays() days' notice (0 when the admin
        // turned the 2-day rule off). A dentist-scoped call (walk-in / reschedule pickers) has no
        // lead time and opens from today. A slot that has already started today
        // is shown as passed (below) so it can't be picked.
        $earliest = ($checkDentistId > 0 && !($plan && $planExclude === 0)) || $declinedAppt
            ? $today
            : date('Y-m-d', strtotime($today . ' +' . onlineLeadDays() . ' days'));
        // Patients can't book or reschedule more than ONLINE_MAX_MONTHS ahead.
        $latest   = $role === 'patient' ? onlineLatestDate($today) : null;
        // Days this patient already has a booking on (one per day, 2026-10-18).
        // Counted per person (2026-10-20): the booking being moved (exclude_id),
        // a follow-up plan, or ?for=other&person= from the booking wizard.
        $mineDays = [];
        if ($role === 'patient') {
            $forOther = ($_GET['for'] ?? '') === 'other';
            $person   = (int) ($_GET['person'] ?? 0);
            if ($planExclude > 0) {
                $own = db()->prepare('SELECT booking_for, treated_patient_id FROM appointments WHERE appointment_id = ? AND patient_id = ?');
                $own->execute([$planExclude, $uid]);
                if ($o = $own->fetch()) {
                    $forOther = $o['booking_for'] === 'other';
                    $person   = (int) $o['treated_patient_id'];
                }
            } elseif ($plan) {
                $person   = (int) ($plan['treated_patient_id'] ?? 0);
                $forOther = $person > 0;
            }
            // A brand-new person (or an old someone-else booking with no
            // linked record) has nothing to block.
            if (!$forOther || $person > 0) {
                $mineDays = array_flip(patientBookedDates($uid, $planExclude, $forOther ? $person : 0));
            }
        }
        $nowHm    = (string) db()->query("SELECT DATE_FORMAT(NOW(), '%H:%i')")->fetchColumn();
        // Front-desk walk-in picker: the hour in progress stays bookable (a
        // walk-in at 10:20 can take the 10:00 slot while it has room). Online
        // bookings never get this.
        $walkinNow = !empty($_GET['walkin']) && $role !== 'patient';
        // New walk-in picker only (&walkin_new=1): after the same-day cut-off
        // (1 hour before the last slot) today is closed for new walk-ins. The
        // check-in late-arrival lookup doesn't send it, so booked patients are
        // still served until closing.
        $walkinCut = $walkinNow && !empty($_GET['walkin_new']) && walkinSameDayClosed();
        // Patient portal only (adviser rule, 2026-10-11): a slot starting
        // within the next hour can't be booked online, e.g. at 2:06 PM the
        // 3:00 PM slot is closed. Staff calendars are exempt.
        $cutoffHm = $role === 'patient' ? onlineCutoffHm() : null;

        $blk = db()->prepare('SELECT blocked_date, reason FROM blocked_dates WHERE blocked_date BETWEEN ? AND ?');
        $blk->execute([$from, $to]);
        $blocked = [];
        foreach ($blk->fetchAll() as $r) {
            $blocked[$r['blocked_date']] = $r['reason'] ?: 'The clinic is closed on this date.';
        }

        // ?dentist_id= (2026-09, Reschedule modals) scopes the whole range to
        // that one dentist's own grid/absences — a reschedule can belong to a
        // roaming dentist with a different working week than the Owner.
        // With no dentist_id (the patient online-booking calendar), a day is
        // now open whenever ANY active dentist (owner or roaming) covers it
        // (2026-09) — pull every active dentist's weekly grid + absences in
        // range once, up front, and union them per weekday below. Bookings
        // auto-route (assignAppointmentDentist()) to whichever on-duty dentist
        // is least loaded; this only decides whether the day/slot is offered
        // at all. An empty union (nobody works that weekday at all) closes the
        // day; "no active dentist exists at all" stays open (booking is
        // already broken then, but don't hard-close the whole calendar over it).
        $ownerId   = $checkDentistId;
        $ownerWeek = [];
        $ownerOut  = [];   // dates within range the dentist is marked absent (sick day / emergency leave)
        $dayOver   = [];   // [dentist_id][date] => slot set, a date's own hours (migration 079)
        $activeIds = [];
        if ($ownerId > 0) {
            $wk = db()->prepare('SELECT day_of_week, time_slot FROM dentist_schedules WHERE dentist_id = ? AND is_active = 1');
            $wk->execute([$ownerId]);
            foreach ($wk->fetchAll() as $r) {
                $ownerWeek[(int) $r['day_of_week']][$r['time_slot']] = true;
            }

            $ab = db()->prepare('SELECT absence_date FROM dentist_absences WHERE dentist_id = ? AND absence_date BETWEEN ? AND ?');
            $ab->execute([$ownerId, $from, $to]);
            $ownerOut = array_flip($ab->fetchAll(PDO::FETCH_COLUMN));
        } else {
            $activeIds = array_map(static fn ($d) => (int) $d['user_id'], activeDentists());
            if ($activeIds) {
                $ph = implode(',', array_fill(0, count($activeIds), '?'));
                $wk = db()->prepare("SELECT dentist_id, day_of_week, time_slot FROM dentist_schedules WHERE dentist_id IN ($ph) AND is_active = 1");
                $wk->execute($activeIds);
                foreach ($wk->fetchAll() as $r) {
                    $ownerWeek[(int) $r['dentist_id']][(int) $r['day_of_week']][$r['time_slot']] = true;
                }

                $ab = db()->prepare("SELECT dentist_id, absence_date FROM dentist_absences WHERE dentist_id IN ($ph) AND absence_date BETWEEN ? AND ?");
                $ab->execute(array_merge($activeIds, [$from, $to]));
                foreach ($ab->fetchAll() as $r) {
                    $ownerOut[(int) $r['dentist_id']][$r['absence_date']] = true;
                }
                // Per-date hours (migration 079) replace that dentist's weekly row for the date.
                try {
                    $ov = db()->prepare("SELECT dentist_id, work_date, slots FROM dentist_day_schedules WHERE dentist_id IN ($ph) AND work_date BETWEEN ? AND ?");
                    $ov->execute(array_merge($activeIds, [$from, $to]));
                    foreach ($ov->fetchAll() as $r) {
                        $dayOver[(int) $r['dentist_id']][$r['work_date']] = array_flip(array_filter(explode(',', (string) $r['slots']), 'strlen'));
                    }
                } catch (Throwable $e) {
                    // table missing on an old install: weekly grid only
                }
            }
        }

        // Seats freed by delayed patients go to walk-ins; the patient portal
        // also keeps cancelled approved online seats counted as taken, so a
        // freed online seat is never offered online again (migration 088).
        $seatSql = $role === 'patient' ? ONLINE_SEAT_SQL : SEAT_SQL;
        $cnt = db()->prepare(
            "SELECT scheduled_date, time_slot, COUNT(*) c FROM appointments
             WHERE scheduled_date BETWEEN ? AND ? AND " . $seatSql . "
             GROUP BY scheduled_date, time_slot"
        );
        $cnt->execute([$from, $to]);
        $taken = [];
        foreach ($cnt->fetchAll() as $r) {
            $taken[$r['scheduled_date']][$r['time_slot']] = (int) $r['c'];
        }
        // Follow-up: recount without the appointment being moved, plus the
        // plan dentist's own bookings per hour.
        $mineTaken = [];
        if ($plan) {
            $pc = db()->prepare(
                "SELECT scheduled_date, time_slot, COUNT(*) c, SUM(dentist_id = ?) mine FROM appointments
                 WHERE scheduled_date BETWEEN ? AND ? AND " . $seatSql . "
                   AND appointment_id <> ?
                 GROUP BY scheduled_date, time_slot"
            );
            $pc->execute([$checkDentistId, $from, $to, $planExclude]);
            $taken = [];
            foreach ($pc->fetchAll() as $r) {
                $taken[$r['scheduled_date']][$r['time_slot']] = (int) $r['c'];
                $mineTaken[$r['scheduled_date']][$r['time_slot']] = (int) $r['mine'];
            }
        }
        $planRoaming = $plan && $plan['doctor_type'] === 'roaming';

        $days = [];
        for ($d = strtotime($from); $d <= strtotime($to); $d = strtotime('+1 day', $d)) {
            $k = date('Y-m-d', $d);
            $dow = scheduleDow($k);
            $isSunday = isClinicClosed($k);
            if ($isSunday || isset($blocked[$k])) {
                $reason = $isSunday
                    ? 'The clinic is closed on Sundays.'
                    : ($blocked[$k] ?? 'The clinic is closed on this date.');
                $days[$k] = ['closed' => true, 'reason' => $reason];
                continue;
            }
            // A single requested dentist's own working slots for this weekday
            // (empty when their grid has nothing active then, or they're marked
            // absent this date → the day closes just below). With no dentist
            // requested, union every active dentist's own slots for this weekday
            // (skipping one that's absent this exact date) — the day stays open
            // as long as at least one of them covers it.
            if ($plan) {
                $daySlots = dentistBookableSlots($checkDentistId, $k);   // roaming = every slot unless absent
            } elseif ($ownerId > 0) {
                // Same rule as everywhere else: absences close the day, and a
                // roaming dentist works every slot (their leftover grid rows
                // are ignored), an in-house one follows their weekly grid.
                $daySlots = dentistBookableSlots($ownerId, $k);
            } elseif (!$activeIds) {
                $daySlots = $slots;
            } else {
                $union = [];
                foreach ($activeIds as $did) {
                    if (isset($ownerOut[$did][$k])) {
                        continue;   // this dentist is marked absent on this exact date
                    }
                    $own = $dayOver[$did][$k] ?? ($ownerWeek[$did][$dow] ?? []);
                    foreach ($slots as $s) {
                        if (isset($own[$s])) {
                            $union[$s] = true;
                        }
                    }
                }
                $daySlots = array_values(array_filter($slots, static fn ($s) => isset($union[$s])));
            }
            if ($walkinCut && $k === $today) {
                $days[$k] = ['closed' => true, 'reason' => walkinCutoffMsg(), 'walkin_cutoff' => true];
                continue;
            }
            if ($declinedAppt && $k >= $today && $daySlots !== []) {
                $by = (int) $declinedAppt['oncall_declined_by'];
                $daySlots = array_values(array_filter($daySlots, static function ($s) use ($k, $by, $declinedId) {
                    foreach (dentistsOnDutyAt($k, $s) as $did => $d) {
                        if ((int) $did !== $by && dentistHourCount((int) $did, $k, $s, $declinedId) < dentistHourCap((int) $did, $k, $s)) {
                            return true;
                        }
                    }
                    return false;
                }));
            }
            if ($daySlots === []) {
                $days[$k] = ['closed' => true, 'reason' => 'The dentist is not available on this day.'];
                continue;
            }
            $slotMap = [];
            $leftMap = [];
            $passedMap = [];
            $soonMap = [];
            $open = 0;
            foreach ($daySlots as $s) {
                $left = max(0, $capacity - ($taken[$k][$s] ?? 0));   // chairs still open this hour
                if ($plan) {
                    $mine = $mineTaken[$k][$s] ?? 0;
                    if ($planRoaming ? $mine > 0 : $mine >= $capacity) {
                        $left = 0;   // the follow-up dentist already has a patient this hour
                    }
                }
                if (BLOCK_PASSED_SLOTS && $k === $today && $s <= $nowHm
                    && !($walkinNow && slotEndHm($s) > $nowHm)) {
                    $left = 0;   // this hour has already started today
                    $passedMap[$s] = true;
                } elseif ($cutoffHm !== null && $k === $today && $s <= $cutoffHm) {
                    $left = 0;   // starts within the next hour
                    $soonMap[$s] = true;
                }
                $slotMap[$s] = $left > 0;
                $leftMap[$s] = $left;
                if ($left > 0) {
                    $open++;
                }
            }
            $days[$k] = [
                'closed' => false,
                'past'   => $k < $earliest || ($latest !== null && $k > $latest),   // past, or beyond the 6-month limit
                'open'   => $open,
                'total'  => count($daySlots),
                'full'   => $open === 0,
                'slots'  => $slotMap,
                'left'   => $leftMap,              // { 'HH:MM': remaining count } for the day panel
                'passed' => $passedMap,            // { 'HH:MM': true } hours already started today
                'soon'   => $soonMap,              // { 'HH:MM': true } starts within the hour (patient portal)
                'day_over' => count($passedMap) + count($soonMap) === count($daySlots),   // every slot today has passed
                'mine'   => isset($mineDays[$k]),  // patient already has an appointment this day
            ];
        }
        jsonOut(['ok' => true, 'capacity' => $capacity, 'slots' => $slots, 'days' => $days,
                 'walkin_cutoff' => fmtTime(walkinCutoffHm()), 'walkin_closed' => $walkinCut]);
    }

    if ($action === 'poll') {                     // lightweight status check for the patient's "submitted" screen
        $id = (int) ($_GET['id'] ?? 0);
        $stmt = db()->prepare(
            'SELECT a.appointment_id, a.status, a.decline_reason, a.scheduled_date, a.time_slot,
                    d.full_name AS dentist_name
             FROM appointments a
             LEFT JOIN users d ON d.user_id = a.dentist_id
             WHERE a.appointment_id = ? AND a.patient_id = ?'
        );
        $stmt->execute([$id, $uid]);
        $row = $stmt->fetch();
        jsonOut($row ? ['ok' => true, 'appointment' => $row] : ['ok' => false, 'error' => 'Not found'], $row ? 200 : 404);
    }

    if ($action === 'get') {
        $id = (int) ($_GET['id'] ?? 0);
        $a = appointmentDetail($id);
        if (!$a) {
            jsonOut(['ok' => false, 'error' => 'Not found'], 404);
        }
        if ($role === 'patient' && (int) $a['patient_id'] !== $uid) {
            jsonOut(['ok' => false, 'error' => 'Forbidden'], 403);
        }
        // A dentist may also open another dentist's visit when it is one that
        // Patient Records' Treatment History already lists to every dentist.
        if ($role === 'dentist' && (int) $a['dentist_id'] !== $uid
            && !in_array($a['status'], ['completed', 'approved', 'rescheduled'], true)) {
            jsonOut(['ok' => false, 'error' => 'Forbidden'], 403);
        }
        // Staff see the internal appointment number for cross-reference with the
        // History Log; patients don't — a global running ID (e.g. #39 on a first
        // visit) only confuses them.
        $a['ref_label'] = $role === 'patient' ? '' : ('#' . (int) $a['appointment_id']);
        if (in_array($role, ['dentist', 'admin'], true)) {
            $one = [$a];
            attachPatientAllergies($one);
            $a = $one[0];
        }
        if ($role === 'patient') {
            unset($a['oncall_declined_by'], $a['oncall_declined_at'], $a['oncall_decline_reason']);
        } elseif (!empty($a['oncall_declined_by'])) {
            // Lets the receptionist's Reschedule pop-up name the dentist who declined.
            $st = db()->prepare('SELECT full_name FROM users WHERE user_id = ?');
            $st->execute([(int) $a['oncall_declined_by']]);
            $a['oncall_declined_by_name'] = $st->fetchColumn() ?: null;
        }
        // The dentist's treatment notes are clinical: the front desk and the
        // assistant keep only the patient's own booking concern.
        if (in_array($role, ['receptionist', 'dental_assistant'], true)) {
            $a['notes'] = trim(preg_split('/treatment notes:/i', (string) ($a['notes'] ?? ''))[0]);
        }
        jsonOut(['ok' => true, 'appointment' => $a]);
    }

    // "Action Needed: Dentist Schedule Change" (migration 080): upcoming live
    // bookings a dentist's schedule change moved or flagged. Receptionist only
    // (the admin is deliberately left out of this workflow).
    if ($action === 'sched_changes') {
        if ($role !== 'receptionist') {
            jsonOut(['ok' => false, 'error' => 'Forbidden'], 403);
        }
        $st = db()->query(
            "SELECT a.appointment_id, a.patient_name, a.patient_phone, a.scheduled_date, a.time_slot, a.status,
                    a.booking_type, a.treatment_plan_id, a.dentist_id, a.sched_change_status, a.sched_change_at,
                    TIMESTAMPDIFF(MINUTE, a.sched_change_at, NOW()) AS minutes_ago,
                    d.full_name AS dentist_name, f.full_name AS from_dentist_name,
                    u.contact_number AS account_phone, u.email AS account_email,
                    COALESCE(sv.service_name, 'General Consultation') AS requested_service
               FROM appointments a
               JOIN users u ON u.user_id = a.patient_id
               LEFT JOIN users d ON d.user_id = a.dentist_id
               LEFT JOIN users f ON f.user_id = a.sched_change_from
               LEFT JOIN services sv ON sv.service_id = a.service_id
              WHERE a.sched_change_status IS NOT NULL
                AND a.status IN ('pending','approved','rescheduled')
                AND a.scheduled_date >= CURDATE()
              ORDER BY (a.sched_change_status = 'needs_reschedule') DESC, a.scheduled_date, a.time_slot"
        );
        jsonOut(['ok' => true, 'items' => $st->fetchAll()]);
    }

    /**
     * People this patient has booked "for someone else" before — built
     * straight off their own past appointments, not a separate saved table.
     * Lets the booking wizard offer "book for them again" without re-creating
     * a duplicate patient record every time (2026-09, replacing the earlier
     * patient_dependents table). Scoped to `patient_id = $uid` only — a
     * patient must never be able to browse anyone else's booking history.
     */
    if ($action === 'for_others') {
        if ($role !== 'patient') {
            jsonOut(['ok' => false, 'error' => 'Forbidden'], 403);
        }
        $stmt = db()->prepare(
            "SELECT u.user_id AS treated_patient_id, u.full_name,
                    TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) AS age
             FROM appointments a
             JOIN users u ON u.user_id = a.treated_patient_id
             LEFT JOIN patient_records r ON r.patient_id = u.user_id
             WHERE a.patient_id = ? AND a.booking_for = 'other' AND a.treated_patient_id IS NOT NULL
             GROUP BY u.user_id, u.full_name, r.date_of_birth
             ORDER BY u.full_name"
        );
        $stmt->execute([$uid]);
        jsonOut(['ok' => true, 'people' => $stmt->fetchAll()]);
    }

    // list
    expireLapsedPendingAppointments();   // best-effort: clear never-approved past requests
    sendUpcomingAppointmentReminders();  // best-effort: day-before appointment reminder
    $filter = $_GET['filter'] ?? 'all';
    $params = [];
    $where  = [];

    if ($role === 'patient') {
        $where[] = 'a.patient_id = ?';
        $params[] = $uid;
    } elseif ($role === 'dentist') {
        $where[] = 'a.dentist_id = ?';
        $params[] = $uid;
    }

    switch ($filter) {
        case 'today':
            $where[] = 'a.scheduled_date = CURDATE()';
            $where[] = "a.status IN ('approved','rescheduled','completed')";
            break;
        case 'pending':      $where[] = "a.status = 'pending'"; break;
        case 'scheduled':
        case 'approved':     $where[] = "a.status IN ('approved','rescheduled')"; break;
        case 'checked_in':
            // The dentist's live chair queue: today's approved patients the front
            // desk has physically checked in and who are not yet completed.
            $where[] = 'a.scheduled_date = CURDATE()';
            $where[] = 'a.checked_in_at IS NOT NULL';
            $where[] = "a.status NOT IN ('completed','cancelled')";
            break;
        case 'upcoming':
            // Future active schedules only: later today (time not yet passed) or
            // any later day. Excludes completed / cancelled / pending-billing.
            $where[] = "a.status IN ('approved','rescheduled')";
            $where[] = "(a.scheduled_date > CURDATE()
                         OR (a.scheduled_date = CURDATE() AND TIME(a.time_slot) > CURRENT_TIME()))";
            break;
        case 'completed':    $where[] = "a.status = 'completed'"; break;
        case 'cancelled':    $where[] = "a.status = 'cancelled'"; break;
        case 'rescheduled':  $where[] = "a.status = 'rescheduled'"; break;
        case 'online_queue':
            // The front-desk "Appointments for Approval" queue — every pending
            // request, online OR walk-in (walk-ins now enter as 'pending' too).
            $where[] = "a.status = 'pending'";
            break;
    }

    $sql = 'SELECT a.appointment_id, a.patient_name, ' . APPT_AGE_SQL . ' AS patient_age, a.patient_phone, a.scheduled_date, a.time_slot,
                   a.treatment_plan_id, a.patient_id, a.treated_patient_id, a.status, a.checked_in_at, a.delayed_at, a.checkin_assistant_id, a.booking_type, a.booking_for, a.relationship, a.notes, a.service_id, a.dentist_id,
                   a.insurance_provider, a.decline_reason,
                   a.cancellation_reason, a.patient_cancellation_reason, a.staff_cancellation_reason,
                   a.reschedule_reason, a.previous_scheduled_date,
                   a.oncall_declined_at, a.oncall_decline_reason, odb.full_name AS oncall_declined_by_name,
                   a.sched_change_status, a.sched_change_at, scf.full_name AS sched_change_from_name,
                   u.full_name AS booked_by, u.email AS account_email, u.contact_number AS account_phone,
                   d.full_name AS dentist_name,
                   sv.service_name AS requested_service,
                   (SELECT GROUP_CONCAT(s2.service_name ORDER BY aps.id SEPARATOR ", ")
                    FROM appointment_services aps
                    JOIN services s2 ON s2.service_id = aps.service_id
                    WHERE aps.appointment_id = a.appointment_id) AS procedures,
                   pay.payment_status, pay.remaining_balance, pay.total_amount,
                   pay.reservation_fee_paid, pay.amount_cash, pay.amount_gcash, pay.amount_bank,
                   pay.reservation_method, pay.reservation_method_label, pay.reservation_reference, pay.reservation_receipt,
                   pay.receipt_flag_suspected, pay.receipt_flag_reason, pay.receipt_warnings, pay.receipt_amount_read,
                   ca.full_name AS checkin_assistant_name,
                   ta.full_name AS treatment_assistant_name,
                   (da.absence_id IS NOT NULL OR a.sched_change_status = \'needs_reschedule\') AS doctor_absent,
                   (SELECT COUNT(*) FROM appointments pv
                     WHERE pv.status = \'completed\' AND pv.appointment_id <> a.appointment_id
                       AND (CASE WHEN a.treated_patient_id IS NULL
                                 THEN pv.patient_id = a.patient_id AND pv.treated_patient_id IS NULL
                                 ELSE pv.treated_patient_id = a.treated_patient_id END)) AS prior_visits
            FROM appointments a
            JOIN users u ON u.user_id = a.patient_id
            LEFT JOIN users d ON d.user_id = a.dentist_id
            LEFT JOIN users odb ON odb.user_id = a.oncall_declined_by
            LEFT JOIN users scf ON scf.user_id = a.sched_change_from
            LEFT JOIN services sv ON sv.service_id = a.service_id
            LEFT JOIN payments pay ON pay.appointment_id = a.appointment_id
            LEFT JOIN dental_assistants ca ON ca.assistant_id = a.checkin_assistant_id
            LEFT JOIN dental_assistants ta ON ta.assistant_id = a.treatment_assistant_id
            LEFT JOIN dentist_absences da ON da.dentist_id = a.dentist_id AND da.absence_date = a.scheduled_date';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    // 'today' is the dentist / assistant queue: booked time slot first, then,
    // within a shared slot, whoever physically checked in earliest (patients
    // not yet checked in go last; appointment_id keeps the order stable).
    if ($filter === 'upcoming') {
        $sql .= ' ORDER BY a.scheduled_date ASC, a.time_slot ASC LIMIT 5';
    } elseif ($filter === 'today') {
        $sql .= ' ORDER BY a.time_slot ASC, (a.checked_in_at IS NULL) ASC, a.checked_in_at ASC, a.appointment_id ASC';
    } else {
        $sql .= ' ORDER BY a.scheduled_date DESC, a.time_slot DESC';
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    // doctor_absent is a plain presence flag (never the staff-only absence
    // reason) — cast to a real bool so the client never has to guess whether
    // the driver returned "0"/"1" as a string or an int.
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['doctor_absent'] = !empty($r['doctor_absent']);
    }
    unset($r);
    if (in_array($role, ['dentist', 'admin'], true)) attachPatientAllergies($rows);
    jsonOut(['ok' => true, 'appointments' => $rows]);
}

/* ---------------------------------------------------------------- POST */

/* Reservation-fee receipt upload — multipart, must run before requestBody(). */
if ($action === 'upload_receipt') {
    apiAuth(['patient']);
    $file = $_FILES['receipt'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        jsonOut(['ok' => false, 'error' => 'No receipt file was uploaded.'], 422);
    }
    if ($file['size'] > 4 * 1024 * 1024) {
        jsonOut(['ok' => false, 'error' => 'Receipt image must be 4 MB or smaller.'], 422);
    }
    $ext = match (mime_content_type($file['tmp_name'])) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => null,
    };
    if ($ext === null) {
        jsonOut(['ok' => false, 'error' => 'Upload a JPG, PNG or WEBP screenshot of your receipt.'], 422);
    }
    $dir = __DIR__ . '/../uploads/receipts';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $filename = 'r' . $uid . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        jsonOut(['ok' => false, 'error' => 'Could not save the receipt. Please try again.'], 500);
    }
    jsonOut(['ok' => true, 'receipt' => $filename]);
}

$in = requestBody();

/**
 * The patient's current age, computed from patient_records.date_of_birth (never
 * a typed number). When no birthdate is on file yet and the form sent one
 * ($dobIn), it is validated and saved to the record first. Null = no birthdate.
 */
function patientAgeFromRecord(int $patientId, string $dobIn, int $by): ?int
{
    $st = db()->prepare('SELECT date_of_birth FROM patient_records WHERE patient_id = ?');
    $st->execute([$patientId]);
    $dob = $st->fetchColumn() ?: null;
    if (!$dob && trim($dobIn) !== '') {
        $dob = validBirthDate($dobIn, $err);
        if ($dob === null) {
            jsonOut(['ok' => false, 'error' => $err], 422);
        }
        db()->prepare(
            'INSERT INTO patient_records (patient_id, date_of_birth, updated_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE date_of_birth = VALUES(date_of_birth), updated_by = VALUES(updated_by)'
        )->execute([$patientId, $dob, $by]);
    }
    return ageFromBirthDate($dob);
}

/** shared validation for a new appointment */
function validateNew(array $in): array
{
    $date = $in['scheduled_date'] ?? '';
    $slot = $in['time_slot'] ?? '';
    $name = properCase($in['patient_name'] ?? '');
    $age  = (int) ($in['patient_age'] ?? 0);
    $for  = ($in['booking_for'] ?? 'self') === 'other' ? 'other' : 'self';
    $rel  = properCase($in['relationship'] ?? '');
    $notes = trim($in['notes'] ?? '');
    // A patient booking online "For Someone Else" now either picks someone
    // they've booked for before (treated_patient_id) or types a brand-new
    // person directly into the form (new_patient, 2026-09) instead of typing a
    // raw name/age here — the caller resolves patient_name/patient_age from
    // that person's real record (existing or freshly created) after this
    // returns, so skip the raw-text check in both cases. Walk-ins never send
    // either (booking_for is always 'self' there).
    $treatedId = (int) ($in['treated_patient_id'] ?? 0);
    $hasNewPatient = $for === 'other' && is_array($in['new_patient'] ?? null);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d')) {
        jsonOut(['ok' => false, 'error' => 'Please choose a valid future date.'], 422);
    }
    if (isClinicClosed($date)) {
        jsonOut(['ok' => false, 'error' => 'The clinic is closed on Sundays. Please choose another day.'], 422);
    }
    if (!in_array($slot, generateSlots(), true)) {
        jsonOut(['ok' => false, 'error' => 'Please choose a valid time slot.'], 422);
    }
    // The age is no longer typed: callers derive it from the patient's Date of
    // Birth on file (patientAgeFromRecord()), so only the name is checked here.
    if (!($for === 'other' && ($treatedId > 0 || $hasNewPatient)) && $name === '') {
        jsonOut(['ok' => false, 'error' => 'The patient name is required.'], 422);
    }
    if ($for === 'other' && $rel === '') {
        jsonOut(['ok' => false, 'error' => 'Enter your relationship to this person.'], 422);
    }
    $blk = db()->prepare('SELECT 1 FROM blocked_dates WHERE blocked_date = ?');
    $blk->execute([$date]);
    if ($blk->fetch()) {
        jsonOut(['ok' => false, 'error' => 'The clinic is closed on the selected date.'], 422);
    }
    return [$date, $slot, $name, $age, $for, $rel, $notes, $treatedId];
}

/**
 * Shared validation for a reservation-fee payment — used both by a brand-new
 * online booking (action=create) and by a patient resubmitting a previously
 * declined one (action=resubmit_payment). Same rules either way: a bank/gcash
 * method, a well-formed reference number that isn't already used elsewhere, a
 * real receipt file already on disk (from action=upload_receipt), and a
 * server-side re-check of that file for image-manipulation signals.
 * $excludePaymentId lets a resubmission keep its own already-on-file
 * reference/receipt without tripping the duplicate check against itself.
 * Returns [$method, $ref, $receipt, $bankRef, $receiptCheck]; jsonOut()s on
 * any invalid field, same convention as validateNew().
 */
/**
 * True when another payment already has a receipt image with this SHA-256.
 * Rows saved before migration 070 have no hash yet: they are hashed from
 * their file on disk the first time this runs, then stored.
 */
function receiptImageInUse(string $hash, ?int $excludePaymentId = null): bool
{
    $pdo = db();
    $todo = $pdo->query("SELECT payment_id, reservation_receipt FROM payments
                         WHERE receipt_hash IS NULL AND reservation_receipt IS NOT NULL AND reservation_receipt <> ''")->fetchAll();
    if ($todo) {
        $set = $pdo->prepare('UPDATE payments SET receipt_hash = ? WHERE payment_id = ?');
        foreach ($todo as $r) {
            $f = __DIR__ . '/../uploads/receipts/' . basename($r['reservation_receipt']);
            $h = is_file($f) ? hash_file('sha256', $f) : false;
            $set->execute([$h ?: '', (int) $r['payment_id']]);   // '' = file missing, don't retry
        }
    }
    $st = $pdo->prepare('SELECT 1 FROM payments WHERE receipt_hash = ? AND payment_id <> ? LIMIT 1');
    $st->execute([$hash, (int) ($excludePaymentId ?? 0)]);
    return (bool) $st->fetchColumn();
}

function validateReservationPayment(array $in, ?int $excludePaymentId = null): array
{
    $method  = $in['reservation_method'] ?? '';
    $ref     = trim($in['reservation_reference'] ?? '');
    $receipt = basename(trim($in['reservation_receipt'] ?? ''));
    // Built-in GCash / Bank Transfer, or an active admin-added method 'm<id>' (migration 078).
    $extra = extraPaymentMethod((string) $method);
    if (!in_array($method, ['bank', 'gcash'], true) && !$extra) {
        jsonOut(['ok' => false, 'error' => 'Choose how you paid the reservation fee.'], 422);
    }
    $label = $extra ? $extra['name'] : null;
    if ($ref === '') {
        jsonOut(['ok' => false, 'error' => 'Enter the payment reference number.'], 422);
    }

    // Per-method reference-number format check — Bank / Branch and Account No.
    // are no longer collected (2026-09, instructor feedback): the reference
    // number alone is the single identifier for a bank transfer, same as GCash.
    $bankRef = null;
    if ($method === 'gcash') {
        if (!preg_match('/^\d{13}$/', $ref)) {
            jsonOut(['ok' => false, 'error' => 'GCash Reference Number must be exactly 13 digits.'], 422);
        }
    } elseif ($extra) {
        if (!preg_match('/^[A-Za-z0-9-]{6,30}$/', $ref)) {
            jsonOut(['ok' => false, 'error' => 'Reference Number must be 6-30 letters or digits.'], 422);
        }
    } else { // bank
        $bankRef = trim((string) ($in['bank_reference'] ?? $ref));
        if (!preg_match('/^[A-Za-z0-9]{10,20}$/', $bankRef)) {
            jsonOut(['ok' => false, 'error' => 'Bank Reference Number must be 10-20 characters.'], 422);
        }
    }
    if (referenceInUse($ref, $excludePaymentId)) {
        jsonOut(['ok' => false, 'error' => 'This reference number has already been used for another transaction.'], 409);
    }
    // The screenshot is required only while the admin's "Require Payment
    // Screenshot Upload" setting is on (migration 063, default on). Off: the
    // reference number alone is enough, and a screenshot is optional.
    $receiptRequired = setting('enable_receipt_validation', '1') !== '0';
    $hasFile = $receipt !== '' && is_file(__DIR__ . '/../uploads/receipts/' . $receipt);
    if (!$hasFile) {
        if ($receiptRequired) {
            jsonOut(['ok' => false, 'error' => 'Attach a screenshot of your receipt.'], 422);
        }
        return [$method, $ref, null, $bankRef, ['suspected' => false, 'reason' => null, 'warnings' => null, 'amount' => null, 'hash' => null], $label];
    }

    // Advisory image-manipulation check (re-run here, server-side, against
    // the actual saved file rather than trusting anything the client sent —
    // never blocks the booking, only flags it for the receptionist to
    // double-check before approving. See inspectReceiptForManipulation()'s
    // own doc comment for why "no metadata" is NOT treated as suspicious.
    $receiptCheck = inspectReceiptForManipulation(__DIR__ . '/../uploads/receipts/' . $receipt);
    // The same screenshot can't pay for two bookings (migration 070): the
    // reference box is locked to what the screenshot shows, and this stops a
    // reused image even if someone gets past that lock.
    $hash = hash_file('sha256', __DIR__ . '/../uploads/receipts/' . $receipt) ?: null;
    if ($hash !== null && receiptImageInUse($hash, $excludePaymentId)) {
        jsonOut(['ok' => false, 'error' => 'This payment screenshot was already used for another booking. Please upload the receipt for this payment.'], 409);
    }
    $receiptCheck['hash'] = $hash;
    // The errors the upload box showed the patient (blurry, not GCash, amount,
    // date, ...), kept for the receptionist's receipt viewer (migration 067).
    $warn = trim(preg_replace('/\s+/u', ' ', (string) ($in['receipt_warnings'] ?? '')));
    $receiptCheck['warnings'] = $warn !== '' ? mb_substr($warn, 0, 2000) : null;
    // The amount the upload check read off the receipt (migration 068); the
    // receptionist confirms it at Approve. A guess only, never trusted as paid.
    $amt = round((float) ($in['receipt_amount'] ?? 0), 2);
    $receiptCheck['amount'] = ($amt > 0 && $amt <= 1000000) ? $amt : null;

    return [$method, $ref, $receipt, $bankRef, $receiptCheck, $label];
}

if ($action === 'create') {                       // patient online request
    apiAuth(['patient']);
    [$date, $slot, $name, $age, $for, $rel, $notes, $treatedId] = validateNew($in);

    // Booking a dentist's follow-up recommendation (Treatment Plan module,
    // migration 066): the patient, the service and the dentist all come from
    // the plan, never from the form.
    $plan = null;
    $planId = (int) ($in['treatment_plan_id'] ?? 0);
    if ($planId > 0) {
        syncTreatmentPlans(null, $planId);
        $plan = treatmentPlanDetail($planId);
        if (!$plan || (int) $plan['patient_id'] !== $uid) {
            jsonOut(['ok' => false, 'error' => 'Follow-up not found.'], 404);
        }
        if ($plan['status'] !== 'Pending') {
            jsonOut(['ok' => false, 'error' => $plan['status'] === 'Completed'
                ? 'This follow-up is already completed.' : 'This follow-up is already booked. See your appointments below.'], 422);
        }
        if (!(int) $plan['service_active'] || !(int) $plan['dentist_active']) {
            jsonOut(['ok' => false, 'error' => 'This follow-up can no longer be booked online. Please call the clinic.'], 422);
        }
        $in['service_id'] = (int) $plan['service_id'];
        if (!empty($plan['treated_patient_id'])) {
            $for = 'other';
            $treatedId = (int) $plan['treated_patient_id'];
            if ($rel === '') {
                $rel = (string) ($plan['consult_relationship'] ?: 'Family');
            }
        } else {
            $for = 'self';
            $treatedId = 0;
            $name = (string) $me['full_name'];
        }
    }

    // "For Someone Else" resolves to a real patient account (2026-09,
    // replacing the earlier patient_dependents table) — either one this
    // account has booked for before (treated_patient_id, scoped to its own
    // booking history) or a brand-new person typed straight into the form,
    // created here exactly like a walk-in's "New patient" flow. Either way
    // patient_name/patient_age come from that real record, never trusted
    // as-sent, so their own visit history accumulates on their own account
    // instead of landing on the booking account's own patient_records.
    if ($for === 'other') {
        if ($treatedId <= 0 && is_array($in['new_patient'] ?? null)) {
            // The reachable-number field on this booking (patient_phone) and
            // the booking account's own e-mail also become this new person's
            // contact info — otherwise Patient Records would show them with no
            // phone/e-mail at all (they have none of their own to collect here).
            $newPatient = $in['new_patient'];
            $newPatient['contact_number'] = $in['patient_phone'] ?? '';
            $newPatient['email'] = $me['email'] ?? '';
            [$treatedId, $name, $age] = createUnlinkedPatientRecord($newPatient, $uid, $uid);   // this account is the child's guardian
        } elseif ($treatedId > 0) {
            $person = db()->prepare(
                "SELECT u.full_name, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) AS age
                 FROM appointments a
                 JOIN users u ON u.user_id = a.treated_patient_id
                 LEFT JOIN patient_records r ON r.patient_id = u.user_id
                 WHERE a.patient_id = ? AND a.treated_patient_id = ? AND a.booking_for = 'other'
                 ORDER BY a.created_at DESC LIMIT 1"
            );
            $person->execute([$uid, $treatedId]);
            $row = $person->fetch();
            if (!$row) {
                jsonOut(['ok' => false, 'error' => 'That person was not found in your booking history.'], 422);
            }
            $name = $row['full_name'];
            $age  = (int) ($row['age'] ?? 0);
            // Children only (2026-10-23); a dentist's follow-up plan is exempt.
            if (!$plan && $row['age'] !== null && $age > OTHER_MAX_AGE) {
                jsonOut(['ok' => false, 'error' => OTHER_ADULT_MSG], 422);
            }
        } else {
            jsonOut(['ok' => false, 'error' => 'Choose who this appointment is for.'], 422);
        }
    } else {
        $treatedId = 0;
        // Self-booking: age from the account's own Date of Birth (saved here
        // if the record has none yet, e.g. an old staff-created account).
        $age = patientAgeFromRecord($uid, (string) ($in['date_of_birth'] ?? ''), $uid);
        if ($age === null) {
            jsonOut(['ok' => false, 'error' => 'Enter your date of birth.'], 422);
        }
    }

    // Online bookings need onlineLeadDays() days' notice (DB clock, same as
    // action=availability). With the 2-day rule off, a slot that has already
    // started today is refused instead.
    $dbNow = db()->query("SELECT CURDATE() AS d, DATE_FORMAT(NOW(), '%H:%i') AS t")->fetch();
    $minOnlineDate = date('Y-m-d', strtotime($dbNow['d'] . ' +' . onlineLeadDays() . ' days'));
    if ($date < $minOnlineDate) {
        jsonOut(['ok' => false, 'error' => 'Appointments must be booked at least 1 day in advance. Please choose ' . date('M j, Y', strtotime($minOnlineDate)) . ' or later, or contact our reception.'], 400);
    }
    $maxOnlineDate = onlineLatestDate($dbNow['d']);
    if ($date > $maxOnlineDate) {
        jsonOut(['ok' => false, 'error' => 'Appointments can only be booked up to ' . ONLINE_MAX_MONTHS . ' months ahead. Please choose ' . date('M j, Y', strtotime($maxOnlineDate)) . ' or earlier.'], 400);
    }
    if (!healthFormReady($uid)) {
        jsonOut(['ok' => false, 'error' => healthFormDone($uid)
            ? 'Please review and update your Patient Information Record before booking.'
            : 'Please complete your Patient Information Record before booking.'], 422);
    }
    // One appointment per day per person: "For Myself" checks the account's
    // own bookings, "For Someone Else" checks that person's.
    if (in_array($date, patientBookedDates($uid, 0, $for === 'other' ? $treatedId : 0), true)) {
        jsonOut(['ok' => false, 'error' => ($for === 'other' ? $name . ' already has' : 'You already have') . ' an appointment on ' . date('M j, Y', strtotime($date)) . '. Only one appointment per day is allowed for each person.'], 422);
    }
    if (BLOCK_PASSED_SLOTS && $date === $dbNow['d'] && $slot <= $dbNow['t']) {
        jsonOut(['ok' => false, 'error' => 'That time has already passed today. Please choose a later time or another day.'], 400);
    }
    if ($date === $dbNow['d'] && $slot <= onlineCutoffHm()) {
        jsonOut(['ok' => false, 'error' => 'Online bookings must start at least 1 hour from now. Please choose a later time or another day, or contact our reception.'], 400);
    }

    // At least one active dentist (owner or roaming) must be on duty for this
    // exact date + slot — the form already hides slots nobody covers; this is
    // a backstop. Which one actually gets it is decided below, once the shared
    // capacity check has passed too.
    if (!$plan && !dentistsOnDutyAt($date, $slot)) {
        jsonOut(['ok' => false, 'error' => 'No dentist is available at the selected time. Please choose another slot.'], 422);
    }
    if ($plan && ($problem = planSlotProblem($plan, $date, $slot)) !== null) {
        jsonOut(['ok' => false, 'error' => $problem], 422);
    }

    // Requested service.
    $serviceId = (int) ($in['service_id'] ?? 0);
    $svc = db()->prepare('SELECT service_name FROM services WHERE service_id = ? AND is_active = 1');
    $svc->execute([$serviceId]);
    $serviceName = $svc->fetchColumn();
    if ($serviceName === false) {
        jsonOut(['ok' => false, 'error' => 'Please choose a service.'], 422);
    }
    if (slotIsFull($date, $slot, null, true)) {
        jsonOut(['ok' => false, 'error' => 'That date and time is already fully booked. Please choose another slot.'], 422);
    }

    $insurance = trim($in['insurance_provider'] ?? '');
    $phone     = digitsOnly($in['patient_phone'] ?? '');
    if (!isPhMobile($phone)) {
        jsonOut(['ok' => false, 'error' => PH_MOBILE_MSG], 422);
    }

    // Reservation-fee payment.
    [$method, $ref, $receipt, $bankRef, $receiptCheck, $methodLabel] = validateReservationPayment($in);

    $fee = (float) setting('reservation_fee', '500.00');

    // Auto-routing (2026-09): pick whichever on-duty dentist is currently least
    // loaded for this exact date+slot (Owner breaks a tie) — see
    // assignAppointmentDentist()'s own comment. The receptionist can still
    // reassign a waiting patient to someone else live via action=assign.
    $assignedDentistId = $plan ? (int) $plan['assigned_dentist_id'] : assignAppointmentDentist($date, $slot);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO appointments
                (patient_id, treated_patient_id, dentist_id, service_id, treatment_plan_id, booking_for, patient_name, patient_age, patient_phone, relationship,
                 insurance_provider, scheduled_date, time_slot, booking_type, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'online', 'pending', ?)"
        );
        $stmt->execute([
            $uid, $treatedId ?: null, $assignedDentistId, $serviceId, $plan ? $planId : null, $for, $name, $age, $phone ?: null, $for === 'other' ? $rel : null,
            $insurance ?: null, $date, $slot, $notes ?: null,
        ]);
        $id = (int) $pdo->lastInsertId();

        $pay = $pdo->prepare(
            "INSERT INTO payments
                (appointment_id, total_amount, reservation_fee_paid, reservation_paid_at,
                 reservation_method, reservation_method_label, reservation_reference, reservation_receipt,
                 receipt_flag_suspected, receipt_flag_reason, receipt_warnings, receipt_amount_read, receipt_hash,
                 bank_reference,
                 remaining_balance, payment_status)
             VALUES (?, 0, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'unpaid')"
        );
        $pay->execute([
            $id, $fee, $method, $methodLabel, $ref, $receipt,
            $receiptCheck['suspected'] ? 1 : 0, $receiptCheck['reason'], $receiptCheck['warnings'], $receiptCheck['amount'], $receiptCheck['hash'],
            $bankRef ?: null,
        ]);
        // A self-booking's phone becomes the account's contact number when it
        // has none yet (sign-up no longer asks for one, 2026-10-11).
        // Skipped when another account already uses that number.
        if ($for === 'self' && $phone !== '' && !phoneInUse($phone, $uid)) {
            $pdo->prepare("UPDATE users SET contact_number = ? WHERE user_id = ? AND (contact_number IS NULL OR contact_number = '')")
                ->execute([$phone, $uid]);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        jsonOut(['ok' => false, 'error' => 'Could not submit your request. Please try again.'], 500);
    }

    if ($plan) {
        syncTreatmentPlans(null, $planId);   // Pending -> Scheduled
        notifyRoamingFollowUpBooked($plan, $id, $name, $date, $slot);
    }

    logActivity('appointments', 'Submitted an online booking request (Appointment #' . $id . ') for '
        . $name . ', ' . $serviceName . ' on ' . $date . ' ' . $slot
        . ($plan ? ' (follow-up recommended by Dr. ' . $plan['dentist_name'] . ')' : ''), $me);
    jsonOut(['ok' => true, 'appointment_id' => $id]);
}

if ($action === 'decline') {                       // receptionist rejects a pending request
    apiAuth(['receptionist', 'admin']);
    $id     = (int) ($in['appointment_id'] ?? 0);
    $reason = trim($in['reason'] ?? '');
    if ($reason === '') {
        jsonOut(['ok' => false, 'error' => 'Please give the patient a reason.'], 422);
    }
    $a = appointmentDetail($id);
    if (!$a) {
        jsonOut(['ok' => false, 'error' => 'Not found'], 404);
    }
    if ($a['status'] !== 'pending') {
        jsonOut(['ok' => false, 'error' => 'Only pending requests can be declined.'], 422);
    }
    // Decline is for online bookings only (a payment screenshot / reference
    // problem). A walk-in is standing at the counter: approve it or reschedule it.
    if ($a['booking_type'] !== 'online') {
        jsonOut(['ok' => false, 'error' => 'Walk-in requests cannot be declined. Approve or reschedule it instead.'], 422);
    }
    db()->prepare("UPDATE appointments SET status = 'cancelled', decline_reason = ?, staff_cancellation_reason = ?, cancellation_reason = ? WHERE appointment_id = ?")
        ->execute([$reason, $reason, $reason, $id]);
    notify((int) $a['patient_id'], 'Appointment request declined',
        'Your request for ' . $a['patient_name'] . ' on ' . $a['scheduled_date'] . ' was declined: ' . $reason, $id);
    logActivity('appointments', 'Declined the request (Appointment #' . $id . ') for ' . $a['patient_name']
        . ' on ' . $a['scheduled_date'] . ' (reason: ' . $reason . ')', $me);
    jsonOut(['ok' => true]);
}

/* Structured reason codes for a payment-only decline (2026-09) — kept as a
   plain server-side map (not a DB table) since the set is small, fixed, and
   the receptionist's own dropdown must offer exactly these options. */
const PAYMENT_DECLINE_REASONS = [
    'blurry_image'       => 'Blurry Image',
    'reference_mismatch' => 'Reference Code Mismatch',
    'invalid_screenshot' => 'Invalid Screenshot',
    'other'              => 'Other',
];

if ($action === 'decline_payment') {                // receptionist rejects just the online reservation receipt
    apiAuth(['receptionist', 'admin']);
    $id     = (int) ($in['appointment_id'] ?? 0);
    $code   = trim($in['reason_code'] ?? '');
    $detail = trim($in['reason'] ?? '');
    if (!isset(PAYMENT_DECLINE_REASONS[$code])) {
        jsonOut(['ok' => false, 'error' => 'Please choose a reason.'], 422);
    }
    if ($code === 'other' && $detail === '') {
        jsonOut(['ok' => false, 'error' => 'Please describe the issue with this receipt.'], 422);
    }
    $reason = PAYMENT_DECLINE_REASONS[$code] . ($detail !== '' ? ': ' . $detail : '');

    $a = appointmentDetail($id);
    if (!$a) {
        jsonOut(['ok' => false, 'error' => 'Not found'], 404);
    }
    if ($a['booking_type'] !== 'online') {
        jsonOut(['ok' => false, 'error' => 'Only an online booking has a reservation receipt to decline.'], 422);
    }
    if ($a['status'] !== 'pending') {
        jsonOut(['ok' => false, 'error' => 'Only a pending request can have its payment declined.'], 422);
    }
    // Unlike action=decline, this never touches cancellation_reason /
    // staff_cancellation_reason — the appointment isn't being cancelled, only
    // paused pending a corrected receipt, so those "why was this cancelled"
    // columns must stay untouched.
    db()->prepare("UPDATE appointments SET status = 'payment_declined', decline_reason = ?, payment_decline_code = ? WHERE appointment_id = ?")
        ->execute([$reason, $code, $id]);
    notify((int) $a['patient_id'], 'Payment declined: please resubmit',
        'Your reservation payment for ' . $a['patient_name'] . ' on ' . $a['scheduled_date']
        . ' could not be verified (' . $reason . '). Please update your reference number and receipt to keep your booking.', $id);
    logActivity('appointments', 'Declined the reservation payment (Appointment #' . $id . ') for ' . $a['patient_name']
        . ' on ' . $a['scheduled_date'] . ' (reason: ' . $reason . ')', $me);
    jsonOut(['ok' => true]);
}

if ($action === 'resubmit_payment') {               // patient fixes a declined reservation receipt
    apiAuth(['patient']);
    $id = (int) ($in['appointment_id'] ?? 0);
    $a  = appointmentDetail($id);
    if (!$a) {
        jsonOut(['ok' => false, 'error' => 'Not found'], 404);
    }
    if ((int) $a['patient_id'] !== $uid) {
        jsonOut(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    if ($a['booking_type'] !== 'online' || $a['status'] !== 'payment_declined') {
        jsonOut(['ok' => false, 'error' => 'This appointment is not awaiting a payment resubmission.'], 422);
    }
    // Same "can't act on a past appointment" guard action=cancel already
    // applies to a patient — a payment_declined row still has a real
    // scheduled_date/time_slot on file even though it was never approved.
    $apptStart = strtotime(($a['scheduled_date'] ?? '') . ' ' . ($a['time_slot'] ?? '00:00'));
    if ($apptStart !== false && $apptStart < time()) {
        jsonOut(['ok' => false, 'error' => 'This appointment date has already passed. Please contact the clinic if you need help.'], 422);
    }

    // Same reservation-payment rules as a brand-new booking, re-validated in
    // full — excluding this appointment's own existing payments row from the
    // duplicate-reference check, so resubmitting is never blocked by the very
    // reference/receipt already on file for it.
    [$method, $ref, $receipt, $bankRef, $receiptCheck, $methodLabel] = validateReservationPayment($in, (int) $a['payment_id']);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "UPDATE payments SET reservation_paid_at = NOW(), reservation_confirmed_at = NULL,
                reservation_method = ?, reservation_method_label = ?, reservation_reference = ?, reservation_receipt = ?,
                bank_reference = ?, receipt_flag_suspected = ?, receipt_flag_reason = ?, receipt_warnings = ?, receipt_amount_read = ?, receipt_hash = ?
             WHERE payment_id = ?"
        )->execute([
            $method, $methodLabel, $ref, $receipt, $bankRef ?: null,
            $receiptCheck['suspected'] ? 1 : 0, $receiptCheck['reason'], $receiptCheck['warnings'], $receiptCheck['amount'], $receiptCheck['hash'],
            (int) $a['payment_id'],
        ]);
        // Back to 'pending' — the exact status a brand-new request starts at,
        // so it reappears in the receptionist's existing approval queue
        // (filter=online_queue is already just WHERE status='pending') with
        // no separate queue/filter needed. Clears the old rejection so it
        // doesn't linger once a fresh receipt is on file.
        $pdo->prepare(
            "UPDATE appointments SET status = 'pending', decline_reason = NULL, payment_decline_code = NULL
             WHERE appointment_id = ?"
        )->execute([$id]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        jsonOut(['ok' => false, 'error' => 'Could not resubmit your payment. Please try again.'], 500);
    }

    logActivity('appointments', 'Resubmitted the reservation payment for Appointment #' . $id
        . ' for ' . $a['patient_name'] . ', back in the approval queue', $me);
    jsonOut(['ok' => true]);
}

if ($action === 'walk_in') {                       // receptionist fast booking
    apiAuth(['receptionist', 'admin']);
    [$date, $slot, $name, $age, $for, $rel, $notes] = validateNew($in);   // a minor is booked as 'other' under their guardian (migration 089)
    // Walk-ins now share the same 5-chair capacity gate as an online booking
    // (2026-09 — previously exempt, so a physically-present patient could
    // still be squeezed into an already-full hour; the user asked for parity
    // with the online flow instead, matching the "— Full" disabled option the
    // Walk-in modal's Time <select> now shows client-side).
    // A walk-in may take today's hour in progress (10:20 into the 10:00 slot),
    // but not an hour that has already ended.
    $dbNow = db()->query("SELECT CURDATE() d, DATE_FORMAT(NOW(), '%H:%i') t")->fetch();
    if (BLOCK_PASSED_SLOTS && $date === $dbNow['d'] && slotEndHm($slot) <= $dbNow['t']) {
        jsonOut(['ok' => false, 'error' => 'That hour is already over. Please choose a later time or another day.'], 422);
    }
    if ($date === $dbNow['d'] && walkinSameDayClosed()) {
        jsonOut(['ok' => false, 'error' => walkinCutoffMsg()], 422);
    }
    if (slotIsFull($date, $slot)) {
        jsonOut(['ok' => false, 'error' => 'That date and time is already fully booked. Please choose another slot.'], 422);
    }
    // The front desk picks the dentist on the walk-in form itself (2026-10), so
    // a walk-in skips the approval queue and is booked straight as 'approved'.
    // Same checks as action=confirm: on duty that hour, and not already holding
    // the hour's whole shared capacity personally.
    $dentistId = (int) ($in['dentist_id'] ?? 0) ?: (int) (ownerDentistId() ?? 0);
    if ($dentistId <= 0) {
        jsonOut(['ok' => false, 'error' => 'Please assign a dentist.'], 422);
    }
    $dchk = db()->prepare("SELECT 1 FROM users WHERE user_id = ? AND role = 'dentist' AND is_active = 1");
    $dchk->execute([$dentistId]);
    if (!$dchk->fetchColumn()) {
        jsonOut(['ok' => false, 'error' => 'Please assign an active dentist.'], 422);
    }
    assertDentistOnDuty($dentistId, $date, $slot);
    assertDentistNotOverloaded($dentistId, $date, $slot);
    $lockedBy = roamingLockedBy($dentistId, $date, $slot);
    if ($lockedBy !== null) {
        jsonOut(['ok' => false, 'error' => 'A roaming dentist can only take this walk-in once ' . $lockedBy
            . ' has ' . max(1, slotCapacity() - 1) . ' patient' . (max(1, slotCapacity() - 1) === 1 ? '' : 's') . ' at ' . fmtTime($slot) . '.'], 422);
    }

    // A walk-in must resolve to a real patient account, so the visit shows up in
    // Patient Records and a returning walk-in keeps one continuous history. The
    // front desk picks an existing record or creates one (api/records.php
    // action=create) before booking — there is no receptionist-record fallback.
    $patientId = (int) ($in['patient_id'] ?? 0);
    $pchk = db()->prepare("SELECT 1 FROM users WHERE user_id = ? AND role = 'patient' AND is_active = 1");
    $pchk->execute([$patientId]);
    if (!$pchk->fetchColumn()) {
        jsonOut(['ok' => false, 'error' => 'Select or create a patient record for this walk-in.'], 422);
    }
    // Minors (migration 089): the account belongs to the parent / guardian and
    // the child is a dependent record linked to it (users.guardian_user_id).
    // booking_for = 'other': patient_id is the guardian, the child is an
    // existing dependent (treated_patient_id) or a new one (new_patient).
    $treatedId = 0;
    $newChild  = null;
    if ($for === 'other') {
        $gAge = patientAgeFromRecord($patientId, '', $uid);
        if ($gAge !== null && $gAge < STAFF_MIN_AGE) {
            jsonOut(['ok' => false, 'error' => 'The parent / guardian must be 18 or older.'], 422);
        }
        $treatedId = (int) ($in['treated_patient_id'] ?? 0);
        if ($treatedId > 0) {
            $c = db()->prepare("SELECT u.full_name, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) age FROM users u
                                LEFT JOIN patient_records r ON r.patient_id = u.user_id
                                WHERE u.user_id = ? AND u.guardian_user_id = ? AND u.is_active = 1");
            $c->execute([$treatedId, $patientId]);
            $child = $c->fetch();
            if (!$child) {
                jsonOut(['ok' => false, 'error' => 'That child is not linked to this parent / guardian.'], 422);
            }
            $name = $child['full_name'];
            $age  = $child['age'] !== null ? (int) $child['age'] : null;
        } elseif (is_array($in['new_patient'] ?? null)) {
            $newChild = $in['new_patient'];
            $cFirst = properCase($newChild['first_name'] ?? '');
            $cLast  = properCase($newChild['last_name'] ?? '');
            $cDob   = validBirthDate((string) ($newChild['date_of_birth'] ?? ''), $dobErr);
            if ($cDob !== null && ($r = existingPatientRecord($cFirst, $cLast, $cDob))) {
                jsonOut(['ok' => false, 'duplicate' => true, 'error' => 'Record already exists. A patient (' . $r['full_name']
                    . ') is already registered with the same name and date of birth. Switch to Existing patient and search for them instead of creating a new record.'], 409);
            }
            $age = null;   // set when the record is created below
        } else {
            jsonOut(['ok' => false, 'error' => 'Enter the child’s details.'], 422);
        }
    } else {
        // An existing child picked directly is booked under their guardian.
        $g = db()->prepare("SELECT u.guardian_user_id, u.full_name, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) age
                              FROM users u LEFT JOIN patient_records r ON r.patient_id = u.user_id
                             WHERE u.user_id = ? AND u.guardian_user_id IS NOT NULL AND u.adult_account_at IS NULL");
        $g->execute([$patientId]);
        if (($gr = $g->fetch()) && $gr['age'] !== null && (int) $gr['age'] < 18) {
            $treatedId = $patientId;
            $patientId = (int) $gr['guardian_user_id'];
            $for  = 'other';
            $rel  = $rel !== '' ? $rel : 'Parent / Guardian';
            $name = $gr['full_name'];
            $age  = (int) $gr['age'];
        }
    }
    // Age comes from the patient's Date of Birth (saved here if none on file).
    if ($for !== 'other') {
        $age = patientAgeFromRecord($patientId, (string) ($in['date_of_birth'] ?? ''), $uid);
        if ($age === null) {
            jsonOut(['ok' => false, 'error' => 'Enter the patient’s date of birth.'], 422);
        }
    }

    $phone = digitsOnly($in['phone'] ?? $in['patient_phone'] ?? '');
    if (!isPhMobile($phone)) {
        jsonOut(['ok' => false, 'error' => 'A valid phone number is required. ' . PH_MOBILE_MSG], 422);
    }

    // The visit's service — so it shows in Patient Records, the dentist's
    // Log Procedure pre-tick, and the Revenue Report (which reads a.service_id).
    $serviceId = (int) ($in['service_id'] ?? 0);
    $svcChk = db()->prepare('SELECT 1 FROM services WHERE service_id = ? AND is_active = 1');
    $svcChk->execute([$serviceId]);
    if (!$svcChk->fetchColumn()) {
        jsonOut(['ok' => false, 'error' => 'Please choose a service for this walk-in.'], 422);
    }

    // Reservation fee / downpayment — now a standard, required part of every
    // walk-in booking (Cash, GCash, or Bank Transfer), the walk-in counterpart
    // of the online booking's own mandatory reservation fee (no opt-out
    // checkbox client-side any more, so the server enforces it too). GCash/
    // Bank just need the transaction's reference number — no QR image or
    // receipt upload here, the patient pays at the counter or hands over a
    // physical slip. Stored on `payments` exactly like the online flow's own
    // reservation row — reservation_fee_paid is a credit recomputePayment()
    // deducts from the total at checkout, and it alone never advances
    // payment_status past 'unpaid' (matching the online rule) until the
    // cashier actually processes money at billing.
    $resMethod = (string) ($in['reservation_method'] ?? '');
    if (!in_array($resMethod, ['cash', 'gcash', 'bank'], true)) {
        jsonOut(['ok' => false, 'error' => 'Choose Cash, GCash, or Bank Transfer for the walk-in reservation fee.'], 422);
    }
    $resAmount = (float) ($in['reservation_amount'] ?? 0);
    if ($resAmount <= 0) {
        jsonOut(['ok' => false, 'error' => 'Enter a valid reservation fee / downpayment amount.'], 422);
    }
    $resRef = null;
    if ($resMethod !== 'cash') {
        // Same per-method reference format the online booking wizard's own
        // action=create enforces (see the GCash/bank checks a little above
        // this action in the file) — GCash is exactly 13 digits, Bank is
        // 10-20 alphanumeric characters.
        $resRef = trim((string) ($in['reservation_reference'] ?? ''));
        if ($resMethod === 'gcash' && !preg_match('/^\d{13}$/', $resRef)) {
            jsonOut(['ok' => false, 'error' => 'GCash Reference Number must be exactly 13 digits.'], 422);
        }
        if ($resMethod === 'bank' && !preg_match('/^[A-Za-z0-9]{10,20}$/', $resRef)) {
            jsonOut(['ok' => false, 'error' => 'Bank Reference Number must be 10-20 characters.'], 422);
        }
        if (referenceInUse($resRef)) {
            jsonOut(['ok' => false, 'error' => 'This reference number has already been used for another transaction.'], 409);
        }
    }

    // A walk-in is booked straight as 'approved' with the dentist picked above
    // (2026-10; it used to wait as 'pending' for an Approve step). Check-in is
    // still a separate manual step from the Approved Appointments table once
    // the patient is ready (action=check_in).
    // New child: created last, once every other check has passed, as a
    // dependent of the guardian (guardian's phone + e-mail as contact).
    if ($newChild !== null) {
        $ge = db()->prepare('SELECT email FROM users WHERE user_id = ?');
        $ge->execute([$patientId]);
        $newChild['contact_number'] = $phone;
        $newChild['email'] = (string) ($ge->fetchColumn() ?: '');
        [$treatedId, $name, $age] = createUnlinkedPatientRecord($newChild, $uid, $patientId);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO appointments
                (patient_id, treated_patient_id, dentist_id, service_id, booking_for, patient_name, patient_age, patient_phone, relationship,
                 scheduled_date, time_slot, booking_type, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'walk_in', 'approved', ?)"
        );
        $stmt->execute([$patientId, $treatedId > 0 ? $treatedId : null, $dentistId, $serviceId, $for, $name, $age, $phone ?: null, $for === 'other' ? $rel : null, $date, $slot, $notes ?: null]);
        $newId = (int) $pdo->lastInsertId();

        $pay = $pdo->prepare(
            "INSERT INTO payments
                (appointment_id, total_amount, reservation_fee_paid, reservation_paid_at, reservation_confirmed_at,
                 reservation_method, reservation_reference,
                 remaining_balance, payment_status)
             VALUES (?, 0, ?, NOW(), NOW(), ?, ?, 0, 'unpaid')"
        );
        $pay->execute([$newId, $resAmount, $resMethod, $resRef]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        jsonOut(['ok' => false, 'error' => 'Could not log this walk-in. Please try again.'], 500);
    }

    $feeNote = ', logged a ' . peso($resAmount) . ' reservation fee via ' . strtoupper($resMethod);
    $appt = appointmentDetail($newId);
    if ($appt) {
        // The walk-in skips the Approve step that used to send "Appointment
        // approved", so tell the patient's account here instead.
        notify($patientId, 'Appointment booked',
            'Your appointment on ' . date('M j, Y', strtotime($date)) . ' at ' . date('g:i A', strtotime($slot))
            . ' with Dr. ' . ($appt['dentist_name'] ?? 'your dentist') . ' has been booked.', $newId);
        notifyRoamingDentistAssigned($dentistId, $appt);
    }
    logActivity('appointments', 'Booked a walk-in for ' . $name . ' on ' . $date . ' ' . $slot
        . ' (Appointment #' . $newId . ', approved with ' . ($appt['dentist_name'] ?? 'the dentist') . ')' . $feeNote, $me);
    jsonOut(['ok' => true, 'appointment_id' => $newId]);
}

if ($action === 'confirm') {                       // receptionist approves an online request
    apiAuth(['receptionist', 'admin']);
    $id = (int) ($in['appointment_id'] ?? 0);
    // Default to the Owner Dentist when the receptionist doesn't pick one.
    $dentistId = (int) ($in['dentist_id'] ?? 0) ?: (int) (ownerDentistId() ?? 0);
    if ($dentistId <= 0) {
        jsonOut(['ok' => false, 'error' => 'Please assign a dentist.'], 422);
    }
    $pending = appointmentDetail($id);
    if (!$pending || $pending['status'] !== 'pending') {
        jsonOut(['ok' => false, 'error' => 'Only a pending request can be approved.'], 422);
    }
    // A follow-up booking stays with the dentist who recommended it.
    if (!empty($pending['treatment_plan_id']) && (int) $pending['dentist_id'] > 0) {
        $dentistId = (int) $pending['dentist_id'];
    }
    assertDentistOnDuty($dentistId, $pending['scheduled_date'], $pending['time_slot']);
    assertDentistNotOverloaded($dentistId, $pending['scheduled_date'], $pending['time_slot'], $id);
    // Online reservation: the receptionist confirms the amount actually sent
    // (migration 068). It replaces the default fee, so anything sent above the
    // fee is deducted at the cashier too.
    $resAmount = null;
    if (array_key_exists('reservation_amount', $in) && !empty($pending['payment_id']) && !empty($pending['reservation_method'])) {
        $resAmount = round((float) $in['reservation_amount'], 2);
        if ($resAmount <= 0 || $resAmount > 1000000) {
            jsonOut(['ok' => false, 'error' => 'Enter the amount received, as shown on the receipt.'], 422);
        }
    }
    // Approve only assigns the dentist and flips the status to 'approved'. It does
    // NOT check the patient in — check-in is always its own step from the
    // Approved Appointments table when the patient actually arrives.
    $stmt = db()->prepare(
        "UPDATE appointments SET status = 'approved', dentist_id = ?
         WHERE appointment_id = ? AND status = 'pending'"
    );
    $stmt->execute([$dentistId, $id]);
    // The front desk accepted the fee: it now counts as revenue on the day it was paid.
    if ($stmt->rowCount() > 0 && !empty($pending['payment_id'])) {
        db()->prepare('UPDATE payments SET reservation_confirmed_at = COALESCE(reservation_confirmed_at, NOW()) WHERE payment_id = ?')
            ->execute([(int) $pending['payment_id']]);
    }
    $prevRes = (float) ($pending['reservation_fee_paid'] ?? 0);
    if ($resAmount !== null && $stmt->rowCount() > 0 && abs($resAmount - $prevRes) >= 0.01) {
        db()->prepare('UPDATE payments SET reservation_fee_paid = ? WHERE payment_id = ?')
            ->execute([$resAmount, (int) $pending['payment_id']]);
        recomputePayment((int) $pending['payment_id']);
        logActivity('billing', 'Confirmed a ' . peso($resAmount) . ' reservation payment (was recorded as '
            . peso($prevRes) . ') for Appointment #' . $id, $me);
    }
    $a = appointmentDetail($id);
    if ($a) {
        notify((int) $a['patient_id'], 'Appointment approved',
            'Your appointment for ' . $a['patient_name'] . ' on ' . $a['scheduled_date'] . ' at ' . $a['time_slot'] . ' has been approved.', $id);
        notifyRoamingDentistAssigned($dentistId, $a);
        logActivity('appointments', 'Approved Appointment #' . $id . ' for ' . $a['patient_name']
            . ' on ' . $a['scheduled_date'] . ' ' . $a['time_slot'], $me);
    }
    jsonOut(['ok' => true]);
}

/* Front-desk check-in / undo. Check-in stamps checked_in_at — it gates the
 * dentist's "Log Procedure" and keeps the patient on the dashboard's "Ongoing
 * Patients" card until the cashier settles the bill. It does not change the
 * appointment status or its badge. */
if ($action === 'check_in' || $action === 'undo_check_in') {
    apiAuth(['receptionist', 'admin', 'dental_assistant']);
    // A signed-in dental assistant always tags themselves; only the front desk
    // may pick someone else from the on-duty roster.
    if ($role === 'dental_assistant') {
        $selfId = assistantIdForUser($uid);
        if ($selfId === null) {
            jsonOut(['ok' => false, 'error' => 'Your account is not linked to an active assistant profile.'], 403);
        }
        $in['assistant_id'] = $selfId;
    }
    $id = (int) ($in['appointment_id'] ?? 0);
    $a  = appointmentDetail($id);
    if (!$a) {
        jsonOut(['ok' => false, 'error' => 'Not found'], 404);
    }
    if (in_array($a['status'], ['completed', 'cancelled'], true)) {
        jsonOut(['ok' => false, 'error' => 'This visit is already ' . $a['status'] . '.'], 422);
    }
    if ($action === 'check_in') {
        if (!in_array($a['status'], ['approved', 'rescheduled'], true)) {
            jsonOut(['ok' => false, 'error' => 'Only an approved appointment can be checked in.'], 422);
        }
        // Arrived (Late), migration 088: a Delayed / Standby patient's seat was
        // released, so they are seated in an open hour today (the one in
        // progress or a later one). The front desk picks it; no seat = reschedule.
        $lateNote = '';
        if (!empty($a['delayed_at']) && empty($a['checked_in_at'])) {
            $todayDb = (string) db()->query('SELECT CURDATE()')->fetchColumn();
            $nowHm   = (string) db()->query("SELECT DATE_FORMAT(NOW(), '%H:%i')")->fetchColumn();
            $seat    = substr((string) ($in['seat_slot'] ?? ''), 0, 5);
            if (substr((string) $a['scheduled_date'], 0, 10) !== $todayDb) {
                jsonOut(['ok' => false, 'error' => 'Only a delayed patient booked for today can be checked in. Please reschedule them.'], 422);
            }
            if (!in_array($seat, generateSlots(), true) || slotEndHm($seat) <= $nowHm) {
                jsonOut(['ok' => false, 'error' => 'This patient is on Standby. Choose an hour that has not ended yet, or reschedule them.'], 422);
            }
            if (slotIsFull($todayDb, $seat, $id)) {
                jsonOut(['ok' => false, 'error' => 'The ' . fmtTime($seat) . ' hour is already full. Choose another hour or reschedule the patient.'], 422);
            }
            $did = (int) ($a['dentist_id'] ?? 0);
            if (empty($a['treatment_plan_id'])
                && ($did <= 0 || !dentistOnDuty($did, $todayDb, $seat)
                    || dentistHourCount($did, $todayDb, $seat, $id) >= dentistHourCap($did, $todayDb, $seat))) {
                $did = (int) (assignAppointmentDentist($todayDb, $seat) ?? 0);
            }
            db()->prepare('UPDATE appointments SET time_slot = ?, dentist_id = ? WHERE appointment_id = ?')
                ->execute([$seat, $did > 0 ? $did : null, $id]);
            if ($did > 0 && $did !== (int) ($a['dentist_id'] ?? 0)) {
                notifyRoamingDentistAssigned($did, appointmentDetail($id) ?: $a);
            }
            $lateNote = ' · arrived late (booked ' . fmtTime((string) $a['time_slot']) . '), seated at ' . fmtTime($seat);
            $a['time_slot'] = $seat;
        }
        $prevAssistantId = (int) ($a['checkin_assistant_id'] ?? 0);
        // Optional assisting-staff tag — must be an assistant marked on duty today.
        $asstId     = (int) ($in['assistant_id'] ?? 0);
        $asstName   = '';
        $asstUserId = null;
        $autoAssigned = false;
        if ($asstId > 0) {
            foreach (dentalAssistantsOnDuty() as $da) {
                if ((int) $da['assistant_id'] === $asstId) {
                    $asstName   = $da['full_name'];
                    $asstUserId = $da['user_id'] !== null ? (int) $da['user_id'] : null;
                }
            }
            if ($asstName === '') {
                jsonOut(['ok' => false, 'error' => 'That assistant is not marked on duty today.'], 422);
            }
        } elseif ($role !== 'dental_assistant' && $prevAssistantId === 0 && empty($in['no_assistant'])) {
            // (An explicit "None" from the front desk sends no_assistant=1 and
            // skips this block: the patient goes straight to the dentist with
            // checkin_assistant_id NULL and no notification.)
            // The front desk left the picker blank AND nobody is tagged on
            // this visit yet — auto-assign the least-loaded on-duty assistant
            // instead of leaving it with no one at all. Guarded on the
            // appointment's *existing* tag (not just the request payload) so
            // a repeat check_in call (e.g. re-confirming an already-tagged
            // visit) never silently reassigns it to someone new. If nobody is
            // on duty, this is a no-op and checkin_assistant_id just stays
            // NULL (direct-to-doctor mode).
            $picked = leastLoadedOnDutyAssistant();
            if ($picked) {
                $asstId     = (int) $picked['assistant_id'];
                $asstName   = $picked['full_name'];
                $asstUserId = $picked['user_id'] !== null ? (int) $picked['user_id'] : null;
                $autoAssigned = true;
            }
        }
        // Stamp the arrival time once; set / update the assisting-staff tag
        // whenever one is supplied (a same-day walk-in is auto-checked-in at
        // approval without a picker, so this is also how it gets tagged later).
        db()->prepare(
            "UPDATE appointments
             SET checked_in_at = COALESCE(checked_in_at, NOW()),
                 checkin_assistant_id = COALESCE(?, checkin_assistant_id)
             WHERE appointment_id = ?"
        )->execute([$asstId ?: null, $id]);
        logActivity('appointments', 'Checked in ' . $a['patient_name'] . ' (Appointment #' . $id . ')' . $lateNote
            . ($asstName !== '' ? ' · assisted by ' . $asstName . ($autoAssigned ? ' (auto-assigned, least loaded)' : '') : ''), $me);
        // Alert the assistant on their own dashboard whenever this call is
        // what actually put them on the visit — a fresh manual pick by the
        // front desk, a reassignment to someone new, or the least-loaded
        // auto-assign above. Deliberately NOT fired on: a redundant repeat
        // call re-confirming the same assistant already tagged (asstId ===
        // prevAssistantId), or a self-check-in (the assistant obviously
        // already knows they just checked their own patient in).
        if ($asstId > 0 && $asstId !== $prevAssistantId && $role !== 'dental_assistant' && $asstUserId !== null) {
            notify(
                $asstUserId,
                'New Patient Assigned',
                $a['patient_name'] . ' was checked in at ' . fmtTime($a['time_slot'])
                    . ' for ' . ($a['requested_service'] ?: 'their visit')
                    . ($autoAssigned ? ' and automatically assigned to you.' : ' and assigned to you.'),
                $id,
                'assistant_assigned'
            );
        }
    } else {
        db()->prepare('UPDATE appointments SET checked_in_at = NULL, checkin_assistant_id = NULL WHERE appointment_id = ?')
            ->execute([$id]);
        logActivity('appointments', 'Undid check-in for ' . $a['patient_name'] . ' (Appointment #' . $id . ')', $me);
    }
    jsonOut(['ok' => true]);
}


if ($action === 'reschedule') {
    apiAuth(['receptionist', 'admin', 'dentist', 'cashier', 'patient']);
    $id     = (int) ($in['appointment_id'] ?? 0);
    $date   = $in['scheduled_date'] ?? '';
    $slot   = $in['time_slot'] ?? '';
    $reason = trim($in['reason'] ?? '');
    if ($reason === '') {
        jsonOut(['ok' => false, 'error' => 'Please give a reason for rescheduling.'], 422);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d') || !in_array($slot, generateSlots(), true)) {
        jsonOut(['ok' => false, 'error' => 'A valid future date and time slot are required.'], 422);
    }
    if (isClinicClosed($date)) {
        jsonOut(['ok' => false, 'error' => 'The clinic is closed on Sundays. Please choose another day.'], 422);
    }
    $a = appointmentDetail($id);
    if (!$a) {
        jsonOut(['ok' => false, 'error' => 'Not found'], 404);
    }
    if ($role === 'patient' && (int) $a['patient_id'] !== $uid) {
        jsonOut(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    if (!in_array($a['status'], ['pending', 'approved', 'rescheduled'], true)) {
        jsonOut(['ok' => false, 'error' => 'This appointment can no longer be rescheduled.'], 422);
    }
    // A missed visit (No Show) can't be rescheduled (2026-10-17). A patient
    // can't move an appointment whose own date has already
    // passed. Staff still can (they may be catching up on a visit the patient
    // phoned in about).
    if ($role === 'patient') {
        $apptStart = strtotime(($a['scheduled_date'] ?? '') . ' ' . ($a['time_slot'] ?? '00:00'));
        if ($apptStart !== false && $apptStart < time()) {
            jsonOut(['ok' => false, 'error' =>
                'This appointment date has already passed. Please book a new appointment or contact the clinic.'], 422);
        }
    }
    // Doctor-absence override: when the appointment's currently-assigned dentist
    // is marked absent on the appointment's own (still-current) date, this
    // reschedule is the clinic's doing, not the patient's — it is exempt from
    // the "tomorrow onward" floor and the 24-hour fee-transfer gate below, and
    // the patient may move it to a different, on-duty dentist instead of only
    // the same (absent) one.
    $origDentistId = (int) ($a['dentist_id'] ?? 0);
    $dentistAbsentNow = false;
    if ($origDentistId > 0) {
        $absSt = db()->prepare('SELECT 1 FROM dentist_absences WHERE dentist_id = ? AND absence_date = ?');
        $absSt->execute([$origDentistId, $a['scheduled_date']]);
        $dentistAbsentNow = (bool) $absSt->fetchColumn();
    }
    // The dentist turned this hour off (migration 080): same exemptions.
    if (($a['sched_change_status'] ?? '') === 'needs_reschedule') {
        $dentistAbsentNow = true;
    }
    // The 2-day lead time only applies to brand-new online bookings — a patient
    // moving an existing appointment just needs a future date (at least tomorrow;
    // staff may reschedule to the same day). A doctor-absence-driven reschedule
    // may land the same day too — that's the whole point of offering another
    // on-duty dentist for today's now-unavailable slot.
    $todayDb = (string) db()->query('SELECT CURDATE()')->fetchColumn();
    // Patients may only move an appointment FORWARD (migration 088): a date
    // after the current one (the same date only when the clinic's dentist
    // became unavailable). Earlier dates go through the receptionist.
    $curDate = substr((string) $a['scheduled_date'], 0, 10);
    $patientMinDate = $dentistAbsentNow
        ? max($todayDb, $curDate)
        : max(date('Y-m-d', strtotime($todayDb . ' +1 day')), date('Y-m-d', strtotime($curDate . ' +1 day')));
    if ($role === 'patient' && $date < $patientMinDate) {
        jsonOut(['ok' => false, 'error' => 'You can only move this appointment to ' . date('M j, Y', strtotime($patientMinDate))
            . ' or later. To move it to an earlier date, please contact the clinic.'], 422);
    }
    $reschOther = ($a['booking_for'] ?? '') === 'other';
    $reschPerson = (int) ($a['treated_patient_id'] ?? 0);
    if ($role === 'patient' && (!$reschOther || $reschPerson > 0)
        && in_array($date, patientBookedDates($uid, $id, $reschOther ? $reschPerson : 0), true)) {
        jsonOut(['ok' => false, 'error' => ($reschOther ? 'This person already has' : 'You already have') . ' another appointment on that day. Only one appointment per day is allowed for each person.'], 422);
    }
    if ($role === 'patient' && $date > onlineLatestDate($todayDb)) {
        jsonOut(['ok' => false, 'error' => 'Appointments can only be moved up to ' . ONLINE_MAX_MONTHS . ' months ahead.'], 422);
    }
    // Reservation-fee policy (T&C §3/§4): the fee is non-refundable, but it
    // TRANSFERS to a rescheduled visit as long as the patient gives at least
    // 24 hours' notice. Inside that window a patient can't move a confirmed
    // appointment — if they don't show, the fee is forfeited. Staff may still
    // reschedule at any time for a patient who has contacted the clinic. A
    // doctor-absence-driven reschedule is exempt too — the clinic caused it,
    // not the patient, so it never costs them the 24-hour window.
    if ($role === 'patient' && in_array($a['status'], ['approved', 'rescheduled'], true) && !$dentistAbsentNow) {
        $apptStart = strtotime(($a['scheduled_date'] ?? '') . ' ' . ($a['time_slot'] ?? '00:00'));
        if ($apptStart !== false && $apptStart - time() < 86400) {
            jsonOut(['ok' => false, 'error' =>
                'Reschedule requests must be made at least 24 hours before your appointment. '
              . 'Please contact the clinic directly to make changes at short notice.'], 422);
        }
    }
    $blk = db()->prepare('SELECT 1 FROM blocked_dates WHERE blocked_date = ?');
    $blk->execute([$date]);
    if ($blk->fetch()) {
        jsonOut(['ok' => false, 'error' => 'The clinic is closed on the selected date.'], 422);
    }
    // A still-pending request (e.g. a walk-in moved from the approval queue)
    // stays pending: it still needs the front desk to approve it and pick the
    // dentist, so the dentist duty check happens at Approve, not here.
    $wasPending = $a['status'] === 'pending';
    // Nobody can reschedule onto a full hour (slotCapacity(), 2 patients), staff included
    // (2026-10-17; staff used to be able to override).
    if (slotIsFull($date, $slot, $id, $role === 'patient')) {
        jsonOut(['ok' => false, 'error' => 'That date and time is already fully booked. Please choose another slot.'], 422);
    }
    // Optional dentist reassignment alongside the reschedule. A patient may only
    // switch dentists this way when their current one is the one marked absent
    // — ordinary reassignment stays the receptionist's action=assign; staff
    // (already trusted to reassign freely) may pass it for any reason.
    $newDentistId = (int) ($in['dentist_id'] ?? 0);
    $targetDentistId = $origDentistId;
    // A reschedule must actually move the visit: the same date + time is
    // refused (unless a doctor-absence reschedule switches to another dentist).
    if (substr((string) $a['scheduled_date'], 0, 10) === $date && substr((string) $a['time_slot'], 0, 5) === $slot
        && !($newDentistId > 0 && $newDentistId !== $origDentistId)) {
        jsonOut(['ok' => false, 'error' => 'This is already the date and time of this appointment. Please pick a different time.'], 422);
    }
    // Follow-up bookings (migration 066) stay with the recommending dentist and
    // follow the follow-up slot rules at the new date + time.
    $isPlanAppt = !empty($a['treatment_plan_id']) && $origDentistId > 0;
    if ($isPlanAppt) {
        if ($newDentistId > 0 && $newDentistId !== $origDentistId) {
            jsonOut(['ok' => false, 'error' => 'This follow-up stays with Dr. ' . $a['dentist_name'] . '. Choose another date or time instead.'], 422);
        }
        $newDentistId = 0;
        $planRow = treatmentPlanDetail((int) $a['treatment_plan_id']);
        if ($planRow && (int) $planRow['assigned_dentist_id'] === $origDentistId) {
            if (($problem = planSlotProblem($planRow, $date, $slot, $id)) !== null) {
                jsonOut(['ok' => false, 'error' => $problem], 422);
            }
        }
    }
    if ($newDentistId > 0 && $newDentistId !== $origDentistId) {
        if ($role === 'patient' && !$dentistAbsentNow) {
            jsonOut(['ok' => false, 'error' => 'You can only choose a different dentist when your current one is unavailable.'], 422);
        }
        $chk = db()->prepare("SELECT 1 FROM users WHERE user_id = ? AND role = 'dentist' AND is_active = 1");
        $chk->execute([$newDentistId]);
        if (!$chk->fetchColumn()) {
            jsonOut(['ok' => false, 'error' => 'Choose an active dentist.'], 422);
        }
        $targetDentistId = $newDentistId;
    }
    // An on-call decline (migration 062) leaves the visit with no dentist.
    // Rescheduling it re-routes to whoever is on duty at the new date + slot,
    // never back to the dentist who declined it.
    if ($targetDentistId <= 0 && !$wasPending) {
        $targetDentistId = (int) (assignAppointmentDentist($date, $slot, (int) ($a['oncall_declined_by'] ?? 0)) ?? 0);
        if ($targetDentistId <= 0) {
            jsonOut(['ok' => false, 'error' => 'No dentist is on duty at that date and time. Please choose another slot.'], 422);
        }
    }
    // The in-house dentist holds at most 4 an hour; moving a 5th patient
    // into that hour hands it to the on-duty roaming dentist (2026-10-17).
    if (!$wasPending && !$isPlanAppt && $targetDentistId > 0 && $targetDentistId === $origDentistId
        && dentistHourCount($targetDentistId, $date, $slot, $id) >= dentistHourCap($targetDentistId, $date, $slot)) {
        $routed = (int) (assignAppointmentDentist($date, $slot, $targetDentistId) ?? 0);
        if ($routed > 0) {
            $targetDentistId = $routed;
        }
    }
    // The (possibly reassigned) dentist must be on their working schedule at the new date + slot.
    if (!$wasPending) {
        assertDentistOnDuty($targetDentistId, $date, $slot);
        if (!$isPlanAppt) {
            assertDentistNotOverloaded($targetDentistId, $date, $slot, $id);
        }
    }
    db()->prepare(
        "UPDATE appointments
            SET previous_scheduled_date = scheduled_date,
                scheduled_date = ?, time_slot = ?, reschedule_reason = ?, status = ?,
                dentist_id = ?, reminder_sent_at = NULL, missed_notified_at = NULL, delayed_at = NULL,
                sched_change_status = NULL, sched_change_at = NULL, sched_change_from = NULL
          WHERE appointment_id = ?"
    )->execute([$date, $slot, $reason, $wasPending ? 'pending' : 'rescheduled', $targetDentistId > 0 ? $targetDentistId : null, $id]);
    $who = $role === 'patient' ? 'The patient' : 'The clinic';
    $dn = db()->prepare('SELECT full_name FROM users WHERE user_id = ?');
    $dn->execute([$targetDentistId]);
    $assignedName = (string) ($dn->fetchColumn() ?: '');
    $service  = (string) ($a['requested_service'] ?: 'General Consultation');
    $oldLabel = scheduleLabel((string) $a['scheduled_date'], (string) $a['time_slot']);
    $newLabel = scheduleLabel($date, $slot);
    notify((int) $a['patient_id'], 'Appointment Rescheduled',
        $who . ' rescheduled the appointment for ' . $a['patient_name'] . ' at DGT One Dental Center - Bulacan. '
        . 'Service: ' . $service . '. Old schedule: ' . $oldLabel . '. New schedule: ' . $newLabel . '. '
        . 'Assigned dentist: ' . ($assignedName !== '' ? 'Dr. ' . $assignedName : 'to be confirmed') . '. Reason: ' . $reason,
        $id, 'appointment_rescheduled');
    // In-app notification only (above); the reschedule e-mail was turned off
    // 2026-10-09 at the user's request. emailPatientRescheduled() is kept unused.
    // A newly routed roaming dentist is alerted the same way as at Approve
    // (a pending request is not assigned yet; that happens at Approve).
    if ($targetDentistId !== $origDentistId && !$wasPending) {
        $moved = appointmentDetail($id);
        if ($moved) {
            notifyRoamingDentistAssigned($targetDentistId, $moved);
        }
    }
    logActivity('appointments', 'Rescheduled Appointment #' . $id . ' for '
        . $a['patient_name'] . ' from ' . $a['scheduled_date'] . ' to ' . $date . ' ' . $slot
        . ($targetDentistId !== $origDentistId ? ' (reassigned dentist)' : '')
        . ' (reason: ' . $reason . ')', $me);
    jsonOut(['ok' => true]);
}

/* Cancelation / Rescheduling module (reference doc, Customer / Patient Module):
   a patient may cancel their own booking, and staff may cancel one on a patient's
   behalf (they phone in). Cancelling forfeits the reservation fee — it is
   non-refundable and simply stays on the cancelled appointment, never credited
   (T&C §3/§4). `decline` (receptionist rejecting a still-pending request) stays
   separate. A visit that will still happen should be rescheduled instead; a
   reschedule made ≥ 24 h ahead carries the fee to the new date. */
if ($action === 'cancel') {
    apiAuth(['receptionist', 'admin', 'dentist', 'cashier', 'patient']);
    $id     = (int) ($in['appointment_id'] ?? 0);
    $reason = trim($in['reason'] ?? '');
    if ($reason === '') {
        jsonOut(['ok' => false, 'error' => 'Please give a reason for the cancellation.'], 422);
    }
    $a = appointmentDetail($id);
    if (!$a) {
        jsonOut(['ok' => false, 'error' => 'Not found'], 404);
    }
    if ($role === 'patient' && (int) $a['patient_id'] !== $uid) {
        jsonOut(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    if (in_array($a['status'], ['completed', 'cancelled'], true)) {
        jsonOut(['ok' => false, 'error' => 'This appointment can no longer be cancelled.'], 422);
    }
    // Once the date has passed a patient can't cancel here — a never-approved
    // request auto-expires, and a missed confirmed visit is the front desk's to
    // resolve. Staff may still cancel a past row for cleanup.
    if ($role === 'patient') {
        $apptStart = strtotime(($a['scheduled_date'] ?? '') . ' ' . ($a['time_slot'] ?? '00:00'));
        if ($apptStart !== false && $apptStart < time()) {
            jsonOut(['ok' => false, 'error' =>
                'This appointment date has already passed. Please contact the clinic if you need help.'], 422);
        }
    }
    // Attribute the reason: patient-submitted vs receptionist/admin-submitted.
    $col = $role === 'patient' ? 'patient_cancellation_reason' : 'staff_cancellation_reason';
    // A cancelled approved online booking's seat goes to the walk-in schedule
    // only: it keeps counting as taken on the patient portal (migration 088).
    $released = ($a['booking_type'] ?? '') === 'online' && in_array($a['status'], ['approved', 'rescheduled'], true) ? 1 : 0;
    db()->prepare("UPDATE appointments SET status = 'cancelled', $col = ?, cancellation_reason = ?, seat_released_online = ? WHERE appointment_id = ?")
        ->execute([$reason, $reason, $released, $id]);
    $who = $role === 'patient' ? 'The patient' : 'The clinic';
    notify((int) $a['patient_id'], 'Appointment cancelled',
        $who . ' cancelled the appointment for ' . $a['patient_name'] . ' on ' . $a['scheduled_date'] . '. Reason: ' . $reason, $id);
    logActivity('appointments', 'Cancelled Appointment #' . $id . ' for ' . $a['patient_name']
        . ' on ' . $a['scheduled_date'] . ' (reason: ' . $reason . ')', $me);
    jsonOut(['ok' => true]);
}

/* No Show (migration 064): the front desk closes out an approved / rescheduled
   visit the patient never checked in for, at the end of the day (after the
   clinic's closing time, or any later day).
   The slot is freed (capacity only counts pending / approved / rescheduled,
   and a walk-in can still take the hour while it is in progress),
   the reservation fee is forfeited like a cancellation, and the patient is told. */
if ($action === 'no_show') {
    apiAuth(['receptionist', 'admin']);
    $id = (int) ($in['appointment_id'] ?? 0);
    $a  = appointmentDetail($id);
    if (!$a) {
        jsonOut(['ok' => false, 'error' => 'Not found'], 404);
    }
    if (!in_array($a['status'], ['approved', 'rescheduled'], true)) {
        jsonOut(['ok' => false, 'error' => 'Only approved or rescheduled appointments can be marked as No Show.'], 422);
    }
    if (!empty($a['checked_in_at'])) {
        jsonOut(['ok' => false, 'error' => 'This patient is checked in. Undo the check-in first if they left.'], 422);
    }
    // Manual No Show (migration 088): allowed once the patient is Delayed /
    // Standby (not checked in 15 minutes after the start) or the day is
    // over. Before that the patient is still within the grace period.
    $nowDb = db()->query("SELECT CURDATE() d, DATE_FORMAT(NOW(), '%H:%i') t")->fetch();
    $close = substr((string) setting('clinic_close', '17:00'), 0, 5);
    $visitDay = substr((string) $a['scheduled_date'], 0, 10);
    $dayOver = $visitDay < $nowDb['d'] || ($visitDay === $nowDb['d'] && $nowDb['t'] >= $close);
    if (empty($a['delayed_at']) && !$dayOver) {
        jsonOut(['ok' => false, 'error' => 'No Show can only be marked once the patient is ' . GRACE_MINUTES . ' minutes late (Delayed / Standby).'], 422);
    }
    db()->prepare("UPDATE appointments SET status = 'no_show', missed_notified_at = COALESCE(missed_notified_at, NOW())
                    WHERE appointment_id = ? AND status IN ('approved', 'rescheduled') AND checked_in_at IS NULL")
        ->execute([$id]);
    if (!empty($a['treatment_plan_id'])) {
        syncTreatmentPlans(null, (int) $a['treatment_plan_id']);   // Scheduled -> Pending, so the patient can rebook it
    }
    $dateLabel = date('M j, Y', strtotime((string) $a['scheduled_date']));
    notify((int) $a['patient_id'], 'Missed appointment',
        'We noticed that you missed your appointment for ' . $a['patient_name'] . ' on ' . $dateLabel
      . '. It has been marked as No Show. Please contact the clinic or book a new appointment online.', $id);
    logActivity('appointments', 'Marked Appointment #' . $id . ' for ' . $a['patient_name']
        . ' on ' . $a['scheduled_date'] . ' as No Show', $me);
    jsonOut(['ok' => true]);
}

/* On-call decline (migration 062): a roaming dentist turns down an extra
   patient they were assigned. The visit keeps its status (approved /
   rescheduled) but loses its dentist until the front desk reassigns or
   reschedules it; every active receptionist is alerted, and the History Log
   records it for the admin. */
if ($action === 'decline_oncall') {
    apiAuth(['dentist']);
    $id     = (int) ($in['appointment_id'] ?? 0);
    $reason = trim((string) ($in['reason'] ?? ''));
    if ($reason === '') {
        jsonOut(['ok' => false, 'error' => 'Please give a reason for declining.'], 422);
    }
    $reason = mb_substr($reason, 0, 255);
    if (!isDentistRoaming($uid)) {
        jsonOut(['ok' => false, 'error' => 'Only on-call dentists can decline an assignment.'], 403);
    }
    $a = appointmentDetail($id);
    if (!$a || (int) $a['dentist_id'] !== $uid) {
        jsonOut(['ok' => false, 'error' => 'This patient is no longer assigned to you.'], 409);
    }
    if (!in_array($a['status'], ['approved', 'rescheduled'], true)) {
        jsonOut(['ok' => false, 'error' => 'This appointment can no longer be declined.'], 422);
    }
    if (!empty($a['checked_in_at'])) {
        jsonOut(['ok' => false, 'error' => 'The patient is already checked in. Please talk to the front desk.'], 422);
    }
    // Conditional on dentist_id so a reassignment that lands mid-request wins.
    $st = db()->prepare(
        'UPDATE appointments
            SET dentist_id = NULL, oncall_declined_by = ?, oncall_declined_at = NOW(), oncall_decline_reason = ?
          WHERE appointment_id = ? AND dentist_id = ?'
    );
    $st->execute([$uid, $reason, $id, $uid]);
    if ($st->rowCount() === 0) {
        jsonOut(['ok' => false, 'error' => 'This patient is no longer assigned to you.'], 409);
    }
    // The dentist's own alert for it is dealt with.
    db()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND appointment_id = ? AND type = 'oncall_assigned'")
        ->execute([$uid, $id]);

    $dentistName = (string) ($me['full_name'] ?? '');
    $service = (string) ($a['requested_service'] ?: 'General Consultation');
    $msg = 'Dr. ' . $dentistName . ' declined the on-call assignment for ' . $a['patient_name'] . ' on '
         . scheduleLabel((string) $a['scheduled_date'], (string) $a['time_slot']) . ' (' . $service . '). Reason: ' . $reason
         . '. Please reassign a dentist or reschedule.';
    try {
        $rc = db()->query("SELECT user_id FROM users WHERE role = 'receptionist' AND is_active = 1");
        foreach ($rc->fetchAll(PDO::FETCH_COLUMN) as $rid) {
            notify((int) $rid, 'On-Call Assignment Declined', $msg, $id, 'oncall_declined');
        }
    } catch (Throwable $e) {
        // best-effort: the History Log line below still records it
    }
    logActivity('appointments', 'Declined the on-call assignment for Appointment #' . $id . ' (' . $a['patient_name']
        . ', ' . $a['scheduled_date'] . ' ' . $a['time_slot'] . '). Reason: ' . $reason, $me);
    jsonOut(['ok' => true]);
}

if ($action === 'assign') {                        // receptionist live workload reassignment
    apiAuth(['receptionist', 'admin']);
    $id        = (int) ($in['appointment_id'] ?? 0);
    $dentistId = (int) ($in['dentist_id'] ?? 0);

    $d = db()->prepare("SELECT full_name FROM users WHERE user_id = ? AND role = 'dentist' AND is_active = 1");
    $d->execute([$dentistId]);
    $dentistName = $d->fetchColumn();
    if ($dentistName === false) {
        jsonOut(['ok' => false, 'error' => 'Choose an active dentist.'], 422);
    }

    $a = appointmentDetail($id);
    if (!$a) {
        jsonOut(['ok' => false, 'error' => 'Not found'], 404);
    }
    if (!in_array($a['status'], ['pending', 'approved', 'rescheduled'], true)) {
        jsonOut(['ok' => false, 'error' => 'This appointment can no longer be reassigned.'], 422);
    }
    // Follow-up bookings keep the recommending dentist (unless that dentist
    // declined the on-call assignment, which leaves it unassigned).
    if (!empty($a['treatment_plan_id']) && (int) $a['dentist_id'] > 0 && (int) $a['dentist_id'] !== $dentistId) {
        jsonOut(['ok' => false, 'error' => 'This is a follow-up booked with Dr. ' . $a['dentist_name']
            . ', who recommended it. It stays with that dentist; reschedule it instead if the time does not work.'], 422);
    }
    assertDentistOnDuty($dentistId, $a['scheduled_date'], $a['time_slot']);
    assertDentistNotOverloaded($dentistId, $a['scheduled_date'], $a['time_slot'], $id);

    // If the patient's previous dentist was marked absent on this appointment's
    // own date, this reassignment is resolving that — the patient was already
    // told (at the time of the absence) to reschedule or cancel, so silently
    // fixing it out from under them would leave that notice stale with no
    // follow-up. An ordinary workload reassignment (the dentist wasn't absent)
    // stays quiet, same as before.
    $prevDentistId = (int) ($a['dentist_id'] ?? 0);
    $resolvedAbsence = false;
    if ($prevDentistId > 0 && $prevDentistId !== $dentistId) {
        $absSt = db()->prepare('SELECT 1 FROM dentist_absences WHERE dentist_id = ? AND absence_date = ?');
        $absSt->execute([$prevDentistId, $a['scheduled_date']]);
        $resolvedAbsence = (bool) $absSt->fetchColumn();
    }
    if (($a['sched_change_status'] ?? '') === 'needs_reschedule' && $prevDentistId !== $dentistId) {
        $resolvedAbsence = true;
    }

    db()->prepare('UPDATE appointments SET dentist_id = ?, sched_change_status = NULL, sched_change_at = NULL, sched_change_from = NULL
                   WHERE appointment_id = ?')
        ->execute([$dentistId, $id]);
    logActivity('appointments', 'Reassigned ' . $a['patient_name'] . ' to ' . $dentistName, $me);

    // Only a genuine change of dentist counts as "calling in" a roaming
    // dentist — reassigning to the same dentist they already had is a no-op
    // and shouldn't re-alert them.
    if ($dentistId !== $prevDentistId) {
        notifyRoamingDentistAssigned($dentistId, $a);
    }

    if ($resolvedAbsence) {
        try {
            notify((int) $a['patient_id'], 'Dentist reassigned',
                'Your appointment on ' . $a['scheduled_date'] . ' at ' . $a['time_slot']
                . ' has been reassigned to Dr. ' . $dentistName . '. No further action is needed.', $id);
        } catch (Throwable $e) {
            // Best-effort — a notify failure never undoes the reassignment.
        }
    }

    jsonOut(['ok' => true, 'dentist_name' => $dentistName]);
}

// Schedule-change queue actions (migration 080), receptionist only.
//   sched_flag     unlock the visit on the patient's portal so they pick a new
//                  time (in-app + e-mail), e.g. after an auto-reassignment the
//                  patient doesn't want, or as a reminder.
//   sched_dismiss  mark a reassigned / handled row as reviewed.
if ($action === 'sched_flag' || $action === 'sched_dismiss') {
    apiAuth(['receptionist']);
    $id = (int) ($in['appointment_id'] ?? 0);
    $a = appointmentDetail($id);
    if (!$a || empty($a['sched_change_status'])) {
        jsonOut(['ok' => false, 'error' => 'This booking is no longer in the schedule change list.'], 404);
    }
    if ($action === 'sched_dismiss') {
        db()->prepare('UPDATE appointments SET sched_change_status = NULL, sched_change_at = NULL, sched_change_from = NULL WHERE appointment_id = ?')
            ->execute([$id]);
        logActivity('appointments', 'Marked the schedule change for Appointment #' . $id . ' (' . $a['patient_name'] . ') as reviewed', $me);
        jsonOut(['ok' => true]);
    }
    if (!in_array($a['status'], ['pending', 'approved', 'rescheduled'], true)) {
        jsonOut(['ok' => false, 'error' => 'This appointment can no longer be rescheduled.'], 422);
    }
    $again = $a['sched_change_status'] === 'needs_reschedule';
    db()->prepare("UPDATE appointments SET sched_change_status = 'needs_reschedule', sched_change_at = NOW() WHERE appointment_id = ?")
        ->execute([$id]);
    $acc = db()->prepare('SELECT first_name FROM users WHERE user_id = ?');
    $acc->execute([(int) $a['patient_id']]);
    $u = $acc->fetch() ?: [];
    $msg = 'Hi ' . (trim((string) ($u['first_name'] ?? '')) ?: 'there') . ', please log into your patient portal to pick a new date/time slot for your appointment'
         . (($a['booking_for'] ?? '') === 'other' && $a['patient_name'] ? ' for ' . $a['patient_name'] : '') . ' on ' . scheduleLabel((string) $a['scheduled_date'], (string) $a['time_slot'])
         . ' at your convenience. The dentist\'s schedule changed for that day.';
    notify((int) $a['patient_id'], 'Please Pick a New Time', $msg, $id, 'schedule_change');   // also e-mailed
    logActivity('appointments', ($again ? 'Reminded ' : 'Asked ') . $a['patient_name'] . ' to pick a new time for Appointment #' . $id, $me);
    jsonOut(['ok' => true]);
}

jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
