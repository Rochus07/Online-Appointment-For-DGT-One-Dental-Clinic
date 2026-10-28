<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$me     = apiAuth(['dentist', 'admin', 'receptionist', 'dental_assistant']);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Dentists and admins see and edit the full clinical record; the receptionist and
// dental assistant are limited to contact details and a bare appointment history.
$canClinical = in_array($me['role'], ['dentist', 'admin'], true);

// Tooth states, quick tags and per-tooth records: api/_bootstrap.php (migration 091).

// Clinical free-text fields — dentist / admin only.
$TEXT_FIELDS = [
    // dental_age (Child / Adult Age) removed 2026-10-20: the real age comes from the birthdate.
    'occlusion', 'periodontal_condition', 'oral_hygiene',
    'denture_upper', 'denture_lower', 'abnormalities',
    // general_condition removed 2026-10-20: those answers are in the Patient Information Record log.
    'medical_history',
    'allergies',   // patient-entered on My Profile (migration 071)
];

// Front-desk contact fields on the record — any allowed role may edit these.
$CONTACT_FIELDS = ['address', 'emergency_contact_name', 'emergency_contact_number'];

/* ---------------------------------------------------------------- list patients */
if ($method === 'GET' && $action === 'list') {
    $q = trim($_GET['q'] ?? '');

    $where = "u.role = 'patient' AND u.is_active = 1";
    $params = [];
    if ($q !== '') {
        if (str_contains($q, '@')) {
            $where .= ' AND u.email LIKE ?';
            $params[] = '%' . $q . '%';
        } elseif (preg_match('/^[\d\s()+\-]+$/', $q)) {
            $where .= ' AND u.contact_number LIKE ?';
            $params[] = '%' . preg_replace('/\D/', '', $q) . '%';
        } else {
            // Each typed word must match the START of a name word
            // ("ga" finds Gayo, not Vargas).
            foreach (preg_split('/\s+/', $q) as $tok) {
                $tok = addcslashes($tok, '%_\\');
                $where .= ' AND (u.full_name LIKE ? OR u.full_name LIKE ?)';
                array_push($params, $tok . '%', '% ' . $tok . '%');
            }
        }
    }

    // A visit counts toward this person's own last-visit/visit-count either
    // way: booked under their own login (patient_id, self-booking) or as the
    // person actually treated under someone else's login (treated_patient_id —
    // action=create's "New Person"/reuse flow, which gives them a real record
    // just like a walk-in, but no login of their own to book with directly).
    $sql = "SELECT u.user_id AS id, u.full_name, u.contact_number, u.email,
                   r.date_of_birth, r.gender,
                   (r.record_id IS NOT NULL) AS has_record,
                   -- Age is computed from the date of birth; the booking's age is
                   -- only a fallback for an old record with no birthdate on file.
                   COALESCE(
                       TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()),
                       (SELECT ap.patient_age FROM appointments ap
                         WHERE ((ap.patient_id = u.user_id AND ap.treated_patient_id IS NULL) OR ap.treated_patient_id = u.user_id)
                               AND ap.patient_age > 0
                         ORDER BY ap.created_at DESC LIMIT 1)
                   ) AS age,
                   MAX(CASE WHEN a.status = 'completed' THEN a.scheduled_date END) AS last_visit,
                   COUNT(CASE WHEN a.status = 'completed' THEN 1 END) AS visit_count
            FROM users u
            LEFT JOIN patient_records r ON r.patient_id = u.user_id
            LEFT JOIN appointments a ON (a.patient_id = u.user_id AND a.treated_patient_id IS NULL) OR a.treated_patient_id = u.user_id
            WHERE $where
            GROUP BY u.user_id
            ORDER BY u.full_name";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    jsonOut(['ok' => true, 'patients' => $stmt->fetchAll()]);
}

/* ---------------------------------------------------------------- one patient + record */
if ($method === 'GET' && $action === 'get') {
    $pid = (int) ($_GET['patient_id'] ?? $_GET['id'] ?? 0);

    $u = db()->prepare("SELECT user_id, full_name, username, contact_number, email FROM users WHERE user_id = ? AND role = 'patient'");
    $u->execute([$pid]);
    $patient = $u->fetch();
    if (!$patient) {
        jsonOut(['ok' => false, 'error' => 'Patient not found.'], 404);
    }

    $r = db()->prepare('SELECT * FROM patient_records WHERE patient_id = ?');
    $r->execute([$pid]);
    $record = $r->fetch() ?: null;
    if ($record) {
        $record['tooth_chart'] = $record['tooth_chart'] ? json_decode($record['tooth_chart'], true) : [];
        $upd = db()->prepare('SELECT full_name FROM users WHERE user_id = ?');
        $upd->execute([$record['updated_by']]);
        $record['updated_by_name'] = $upd->fetchColumn() ?: null;

        // Strip clinical data for the receptionist — front desk keeps only the
        // contact block plus date of birth / gender.
        if (!$canClinical) {
            foreach ($TEXT_FIELDS as $f) {
                unset($record[$f]);
            }
            $record['tooth_chart'] = [];
        }
    }
    // A visit shows on this person's own history either way: booked under
    // their own login (self-booking) or as the person actually treated under
    // someone else's login — never both (a "for someone else" visit is
    // excluded from the booking account's own history via the second half).
    $h = db()->prepare(
        "SELECT a.appointment_id, a.scheduled_date, a.time_slot, a.status, a.notes,
                d.full_name AS dentist_name,
                sv.service_name AS requested_service,
                GROUP_CONCAT(s.service_name SEPARATOR ', ') AS procedures,
                pay.total_amount, pay.payment_status
         FROM appointments a
         LEFT JOIN users d ON d.user_id = a.dentist_id
         LEFT JOIN services sv ON sv.service_id = a.service_id
         LEFT JOIN appointment_services aps ON aps.appointment_id = a.appointment_id
         LEFT JOIN services s ON s.service_id = aps.service_id
         LEFT JOIN payments pay ON pay.appointment_id = a.appointment_id
         WHERE ((a.patient_id = ? AND a.treated_patient_id IS NULL) OR a.treated_patient_id = ?)
               AND a.status IN ('completed','approved','rescheduled')
         GROUP BY a.appointment_id
         ORDER BY a.scheduled_date DESC, a.time_slot DESC"
    );
    $h->execute([$pid, $pid]);
    $history = $h->fetchAll();

    // The receptionist sees appointment history but not treatment detail:
    // no availed-procedure list, no per-visit notes, no billing figures.
    if (!$canClinical) {
        $history = array_map(static fn (array $row) => [
            'appointment_id'    => $row['appointment_id'],
            'scheduled_date'    => $row['scheduled_date'],
            'time_slot'         => $row['time_slot'],
            'status'            => $row['status'],
            'dentist_name'      => $row['dentist_name'],
            'requested_service' => $row['requested_service'],
        ], $history);
    }

    // Patient Information Record entries (newest first), clinical roles only (2026-10-20).
    $healthLogs = $canClinical ? healthFormLogs($pid) : [];

    jsonOut(['ok' => true, 'patient' => $patient, 'record' => $record, 'history' => $history, 'health_logs' => $healthLogs,
        'tooth_records' => $canClinical ? toothRecordsFor($pid) : [], 'tooth_tags' => TOOTH_TAGS]);
}

/* ---------------------------------------------------------------- create a walk-in patient */
$in = requestBody();

if ($action === 'create') {
    // A dental assistant tracks existing records; creating a new patient stays
    // with the front desk / dentist / admin.
    if ($me['role'] === 'dental_assistant') {
        jsonOut(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    $first  = properCase($in['first_name'] ?? '');
    $middle = '';   // middle names are no longer collected
    $last   = properCase($in['last_name'] ?? '');
    // Legacy callers may still send a single full_name — split on the first space.
    if ($first === '' && $last === '' && trim((string) ($in['full_name'] ?? '')) !== '') {
        $parts = preg_split('/\s+/', properCase($in['full_name']), 2);
        $first = $parts[0] ?? '';
        $last  = $parts[1] ?? '';
    }
    $contact = digitsOnly($in['contact_number'] ?? '');
    $email   = trim((string) ($in['email'] ?? ''));
    $gender  = in_array($in['gender'] ?? '', ['male', 'female', 'other'], true) ? $in['gender'] : null;

    if ($first !== '' && $last !== '' && (!validPersonName($first) || !validPersonName($last))) {
        jsonOut(['ok' => false, 'error' => PERSON_NAME_MSG], 422);
    }
    if ($first === '' || $last === '') {
        jsonOut(['ok' => false, 'error' => 'The patient first name and last name are required.'], 422);
    }
    $name = personName($first, $middle, $last);
    if (!isPhMobile($contact)) {
        jsonOut(['ok' => false, 'error' => 'A valid contact number is required. ' . PH_MOBILE_MSG], 422);
    }
    // A walk-in New patient gets the composite duplicate check below instead.
    if (empty($in['walk_in']) && phoneInUse($contact)) {
        jsonOut(['ok' => false, 'error' => PHONE_TAKEN_MSG], 422);
    }
    // E-mail is required: the account can sign in, so it needs an address for
    // password reset and (for online self-service) e-mail verification.
    if ($email === '') {
        jsonOut(['ok' => false, 'error' => 'An e-mail address is required. The patient needs it for password reset.'], 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonOut(['ok' => false, 'error' => 'Enter a valid e-mail address.'], 422);
    }
    // Date of Birth, not a typed age: the age is always computed from it.
    if (trim((string) ($in['date_of_birth'] ?? '')) === '') {
        jsonOut(['ok' => false, 'error' => 'The date of birth is required.'], 422);
    }
    // New walk-in patients must be 1 to 80 years old (walkInBirthDate()); a
    // parent / guardian account for a minor walk-in must be 18 to 80.
    $dob = !empty($in['guardian'])
        ? staffBirthDate((string) $in['date_of_birth'], $dobErr)
        : (!empty($in['walk_in'])
            ? walkInBirthDate((string) $in['date_of_birth'], $dobErr)
            : validBirthDate((string) $in['date_of_birth'], $dobErr));
    if ($dob === null) {
        jsonOut(['ok' => false, 'error' => $dobErr], 422);
    }

    // Walk-in New patient duplicate check (2026-10-22), same rule as sign-up:
    // (first + last name AND birthdate) OR e-mail OR mobile number. A same-name
    // patient with a different birthdate and contact info is someone else.
    if (!empty($in['walk_in'])) {
        $dup = null; $why = '';
        if ($r = existingPatientRecord($first, $last, $dob)) {
            $dup = $r; $why = 'the same name and date of birth';
        } else {
            $q = db()->prepare('SELECT user_id, full_name, role FROM users WHERE email = ? LIMIT 1');
            $q->execute([$email]);
            if ($r = $q->fetch()) {
                $dup = $r; $why = 'this e-mail address';
            } elseif (phoneInUse($contact)) {
                $q = db()->prepare('SELECT user_id, full_name, role FROM users WHERE contact_number = ? LIMIT 1');
                $q->execute([$contact]);
                $dup = $q->fetch() ?: ['full_name' => '', 'role' => '']; $why = 'this mobile number';
            }
        }
        if ($dup) {
            $who = ($dup['full_name'] ?? '') !== '' ? ' (' . $dup['full_name'] . ')' : '';
            $msg = (isset($dup['role']) && $dup['role'] !== '' && $dup['role'] !== 'patient')
                ? 'Record already exists. ' . ucfirst($why) . ' already belongs to a staff account. Please use different details.'
                : 'Record already exists. A patient' . $who . ' is already registered with ' . $why
                  . '. Switch to Existing patient and search for them instead of creating a new record.';
            jsonOut(['ok' => false, 'error' => $msg, 'duplicate' => true], 409);
        }
    }

    $pdo  = db();

    // A staff-created patient can also sign in and book online: they get a
    // one-time temporary password (handed over at the desk) and are pre-verified
    // because a staff member met them in person. must_change_password = 1 forces
    // them to set their own on first login, exactly like an admin-created account.
    if ($email !== '') {
        $dupe = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $dupe->execute([$email]);
        if ($dupe->fetchColumn()) {
            jsonOut(['ok' => false, 'error' => 'Record already exists for this e-mail address. Search for the patient under Existing patient / Patient Records instead of creating a new one.'], 422);
        }
    }
    // Real-time mailbox-existence enforcement (any domain) — same cache-first
    // check and gate as api/users.php action=add. This is the server-side
    // backstop for the receptionist walk-in "New patient" flow's inline check
    // on #w_email; it also covers the dentist/receptionist "+ Create Record"
    // modals, which share this same endpoint — accepted as a side effect
    // rather than a second code path.
    // Skipped for the walk-in "New patient" flow (walk_in = true): the front
    // desk creates that account on the spot with a temporary password, with no
    // e-mail verification step at all.
    if (empty($in['walk_in'])) {
        require_once __DIR__ . '/../includes/email_verify.php';
        $emailCheck = verifyEmailExists(strtolower($email));
        if ($emailCheck['exists'] === false) {
            jsonOut(['ok' => false, 'error' => 'This email address does not exist.'], 422);
        }
    }

    $username = makeUsername($pdo, $first, $last);
    $tempPass = tempPassword();

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "INSERT INTO users (full_name, first_name, middle_name, last_name, username, password,
                                must_change_password, is_verified, role, contact_number, email)
             VALUES (?, ?, ?, ?, ?, ?, 1, 1, 'patient', ?, ?)"
        )->execute([
            $name,
            $first,
            $middle !== '' ? $middle : null,
            $last,
            $username,
            password_hash($tempPass, PASSWORD_DEFAULT),
            $contact !== '' ? $contact : null,
            $email !== '' ? $email : null,
        ]);
        $pid = (int) $pdo->lastInsertId();

        $pdo->prepare(
            'INSERT INTO patient_records (patient_id, date_of_birth, gender, updated_by) VALUES (?, ?, ?, ?)'
        )->execute([$pid, $dob, $gender, (int) $me['user_id']]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        jsonOut(['ok' => false, 'error' => 'Could not create the patient record.'], 500);
    }

    logActivity('records', 'Created a patient record for ' . $name . ' (login ' . $username . ')', $me);
    jsonOut(['ok' => true, 'patient_id' => $pid, 'username' => $username, 'temp_password' => $tempPass]);
}

if ($action === 'save') {
    $pid = (int) ($in['patient_id'] ?? 0);
    $chk = db()->prepare("SELECT full_name FROM users WHERE user_id = ? AND role = 'patient'");
    $chk->execute([$pid]);
    $patientName = $chk->fetchColumn();
    if ($patientName === false) {
        jsonOut(['ok' => false, 'error' => 'Patient not found.'], 404);
    }

    // Phone / email live on the users row — front-desk editable for every role.
    if (array_key_exists('contact_number', $in) || array_key_exists('email', $in)) {
        $cn = digitsOnly($in['contact_number'] ?? '');
        $em = trim((string) ($in['email'] ?? ''));
        if ($cn !== '' && !isPhMobile($cn)) {
            jsonOut(['ok' => false, 'error' => PH_MOBILE_MSG], 422);
        }
        if (phoneChangeTaken($cn, $pid)) {
            jsonOut(['ok' => false, 'error' => PHONE_TAKEN_MSG], 422);
        }
        if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) {
            jsonOut(['ok' => false, 'error' => 'Enter a valid email address, or leave it blank.'], 422);
        }
        db()->prepare("UPDATE users SET contact_number = ?, email = ? WHERE user_id = ? AND role = 'patient'")
            ->execute([$cn ?: null, $em ?: null, $pid]);
    }

    $vals = ['patient_id' => $pid];

    // Front-desk contact fields on the record — any allowed role.
    foreach ($CONTACT_FIELDS as $f) {
        if (array_key_exists($f, $in)) {
            $v = trim((string) $in[$f]);
            if ($f !== 'emergency_contact_number') $v = properCase($v);
            $vals[$f] = $v === '' ? null : $v;
        }
    }

    // Clinical data — dentist / admin only. The receptionist never sends these,
    // and the server ignores them if it did.
    // Date of Birth (age is computed from it) — front desk may correct it too.
    if (array_key_exists('date_of_birth', $in)) {
        if (trim((string) $in['date_of_birth']) === '') {
            $vals['date_of_birth'] = null;
        } else {
            $vals['date_of_birth'] = validBirthDate((string) $in['date_of_birth'], $dobErr);
            if ($vals['date_of_birth'] === null) {
                jsonOut(['ok' => false, 'error' => $dobErr], 422);
            }
        }
    }

    if ($canClinical) {
        $vals['gender'] = in_array($in['gender'] ?? '', ['male', 'female', 'other'], true) ? $in['gender'] : null;

        foreach ($TEXT_FIELDS as $f) {
            // Dentures / allergies come from the Patient Information Record and are
            // no longer on the dentist form (2026-10-21): a field not sent is kept.
            if (!array_key_exists($f, $in)) continue;
            $v = trim((string) ($in[$f] ?? ''));
            $vals[$f] = $v === '' ? null : $v;
        }

        // Tooth chart: keep only known teeth with a recognised non-normal state.
        // Sent only by older clients; the chart pop-up sends tooth_entries (below).
        if (array_key_exists('tooth_chart', $in)) {
        $chart = [];
        foreach ((array) ($in['tooth_chart'] ?? []) as $tooth => $state) {
            $tooth = (string) $tooth;
            if (preg_match('/^(1[1-8]|2[1-8]|3[1-8]|4[1-8]|5[1-5]|6[1-5]|7[1-5]|8[1-5])$/', $tooth)
                && in_array($state, TOOTH_STATES, true)) {
                $chart[$tooth] = $state;
            }
        }
        $vals['tooth_chart'] = $chart ? json_encode($chart) : null;
        }
    }

    $vals['updated_by'] = (int) $me['user_id'];

    // Nothing record-level to persist (e.g. the receptionist only changed the
    // phone number) — the users update above already ran.
    if (count($vals) <= 2) {
        logActivity('records', 'Updated contact details for ' . $patientName, $me);
        jsonOut(['ok' => true]);
    }

    $cols = array_keys($vals);
    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
    $updates = implode(', ', array_map(static fn ($c) => "$c = VALUES($c)", array_diff($cols, ['patient_id'])));

    $sql = 'INSERT INTO patient_records (' . implode(', ', $cols) . ") VALUES ($placeholders)
            ON DUPLICATE KEY UPDATE $updates";
    $stmt = db()->prepare($sql);
    $stmt->execute(array_values($vals));

    // Per-tooth condition / tags / notes changed in the chart pop-up (migration 091).
    if ($canClinical && !empty($in['tooth_entries']) && is_array($in['tooth_entries'])) {
        try {
            $n = saveToothEntries($pid, $in['tooth_entries'], null, (string) db()->query('SELECT CURDATE()')->fetchColumn(), (int) $me['user_id']);
        } catch (InvalidArgumentException $e) {
            jsonOut(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        if ($n) logActivity('records', 'Updated ' . $n . ' tooth record' . ($n === 1 ? '' : 's') . ' for ' . $patientName, $me);
    }

    logActivity('records', ($canClinical
        ? 'Edited the clinical chart / medical history for '
        : 'Updated the record for ') . $patientName, $me);
    jsonOut(['ok' => true]);
}

/* ------------------------------------------- tooth chart for one visit (2026-10-26)
 * GET  action=chart&appointment_id=  - the treated patient's chart + tooth records
 * POST action=save_teeth {appointment_id, tooth_entries} - saved under that visit's date
 * Used by the chart step before Perform Procedure. Dentist (own visit) / admin. */
if ($action === 'chart' || $action === 'save_teeth') {
    if (!$canClinical) {
        jsonOut(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    $apptId = (int) ($action === 'chart' ? ($_GET['appointment_id'] ?? 0) : ($in['appointment_id'] ?? 0));
    $a = appointmentDetail($apptId);
    if (!$a) {
        jsonOut(['ok' => false, 'error' => 'Appointment not found.'], 404);
    }
    if ($me['role'] === 'dentist' && (int) $a['dentist_id'] !== (int) $me['user_id']) {
        jsonOut(['ok' => false, 'error' => 'This visit is not assigned to you.'], 403);
    }
    if ($action === 'save_teeth' && $method !== 'POST') {
        jsonOut(['ok' => false, 'error' => 'POST required.'], 405);
    }
    $pid = (int) ($a['treated_patient_id'] ?: $a['patient_id']);
    if ($action === 'chart') {
        $r = db()->prepare('SELECT tooth_chart, date_of_birth FROM patient_records WHERE patient_id = ?');
        $r->execute([$pid]);
        $rec = $r->fetch() ?: [];
        jsonOut(['ok' => true, 'patient_id' => $pid, 'patient_name' => $a['patient_name'],
            'date_of_birth' => $rec['date_of_birth'] ?? null, 'visit_date' => $a['scheduled_date'],
            'tooth_chart' => !empty($rec['tooth_chart']) ? (json_decode($rec['tooth_chart'], true) ?: []) : [],
            'tooth_records' => toothRecordsFor($pid), 'tooth_tags' => TOOTH_TAGS]);
    }
    try {
        $n = saveToothEntries($pid, (array) ($in['tooth_entries'] ?? []), $apptId, (string) $a['scheduled_date'], (int) $me['user_id']);
    } catch (InvalidArgumentException $e) {
        jsonOut(['ok' => false, 'error' => $e->getMessage()], 422);
    }
    if ($n) {
        logActivity('records', 'Updated ' . $n . ' tooth record' . ($n === 1 ? '' : 's') . ' for ' . $a['patient_name']
            . ' (Appointment #' . $apptId . ')', $me);
    }
    jsonOut(['ok' => true, 'saved' => $n]);
}

jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
