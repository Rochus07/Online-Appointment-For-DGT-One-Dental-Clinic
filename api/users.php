<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$me     = apiAuth(['admin']);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

const USER_COLS = 'user_id, full_name, first_name, middle_name, last_name, username, must_change_password,
                   role, doctor_type, specialties, degree, prc_license, years_experience,
                   about, contact_number, email, avatar, is_active, created_at,
                   COALESCE((SELECT pr.date_of_birth FROM patient_records pr WHERE pr.patient_id = users.user_id), users.date_of_birth) AS date_of_birth,
                   TIMESTAMPDIFF(YEAR, COALESCE((SELECT pr.date_of_birth FROM patient_records pr WHERE pr.patient_id = users.user_id), users.date_of_birth), CURDATE()) AS age';

$VALID_ROLES = ['admin', 'dentist', 'cashier', 'receptionist', 'dental_assistant', 'patient'];

if ($method === 'GET' && $action === 'list') {
    $role = $_GET['role'] ?? 'patient';
    if (!in_array($role, $VALID_ROLES, true)) {
        jsonOut(['ok' => false, 'error' => 'Invalid role'], 400);
    }
    $stmt = db()->prepare(
        'SELECT ' . USER_COLS . ' FROM users WHERE role = ? ORDER BY is_active DESC, full_name'
    );
    $stmt->execute([$role]);
    jsonOut(['ok' => true, 'users' => $stmt->fetchAll(), 'specialties' => dentistSpecialties(),
             'me_id' => (int) $me['user_id'], 'owner_admin_id' => ownerAdminId()]);
}

if ($method === 'GET' && $action === 'get') {
    $stmt = db()->prepare('SELECT ' . USER_COLS . ' FROM users WHERE user_id = ?');
    $stmt->execute([(int) ($_GET['id'] ?? 0)]);
    $u = $stmt->fetch();
    jsonOut($u ? ['ok' => true, 'user' => $u] : ['ok' => false, 'error' => 'Not found'], $u ? 200 : 404);
}

$in = requestBody();

if ($action === 'add') {
    $first  = properCase($in['first_name'] ?? '');
    $middle = '';   // middle names are no longer collected
    $last   = properCase($in['last_name'] ?? '');
    $role   = $in['role'] ?? '';
    $email  = trim($in['email'] ?? '');
    $phone  = digitsOnly($in['contact_number'] ?? '');
    $about  = trim($in['about'] ?? '');
    $years  = ($in['years_experience'] ?? '') === '' ? null : max((int) $in['years_experience'], 0);

    if (!in_array($role, $VALID_ROLES, true)) {
        jsonOut(['ok' => false, 'error' => 'Choose a valid role.'], 422);
    }
    if ($first !== '' && $last !== '' && (!validPersonName($first) || !validPersonName($last))) {
        jsonOut(['ok' => false, 'error' => PERSON_NAME_MSG], 422);
    }
    if ($first === '' || $last === '') {
        jsonOut(['ok' => false, 'error' => 'First name and last name are required.'], 422);
    }
    // E-mail is required for every role — the account can sign in, so it needs an
    // address for password reset (and, for a self-verifying patient, verification).
    if ($email === '') {
        jsonOut(['ok' => false, 'error' => 'An e-mail address is required. It is needed for password reset.'], 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonOut(['ok' => false, 'error' => 'Enter a valid email address.'], 422);
    }
    $dupe = db()->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
    $dupe->execute([$email]);
    if ($dupe->fetchColumn()) {
        jsonOut(['ok' => false, 'error' => $role === 'patient'
            ? 'Record already exists for this e-mail address. Search for the patient under Existing patient / Patient Records instead of creating a new one.'
            : 'That email address is already registered to another account.'], 422);
    }
    // (Mailbox-existence check removed: the account is created on the spot
    // with a temporary password, format + uniqueness checks only.)
    // Every role whose Add form has a phone field must supply a valid PH mobile
    // (patient / receptionist / cashier / dental assistant); admin & dentist have
    // no phone field, so a stray value there is just format-checked.
    $rolesWithPhone = ['patient', 'receptionist', 'cashier', 'dental_assistant'];
    if (in_array($role, $rolesWithPhone, true)) {
        if (!isPhMobile($phone)) {
            jsonOut(['ok' => false, 'error' => 'A valid phone number is required. ' . PH_MOBILE_MSG], 422);
        }
    } elseif ($phone !== '' && !isPhMobile($phone)) {
        jsonOut(['ok' => false, 'error' => PH_MOBILE_MSG], 422);
    }
    if (phoneInUse($phone)) {
        jsonOut(['ok' => false, 'error' => PHONE_TAKEN_MSG], 422);
    }

    // Date of Birth is required for every role; age is always computed from
    // it. Patients: patient_records; staff (30+): users.date_of_birth.
    $dob = staffBirthDate((string) ($in['date_of_birth'] ?? ''), $dobErr);   // 18 to 80, every role
    if ($dob === null) {
        jsonOut(['ok' => false, 'error' => (($in['date_of_birth'] ?? '') === '' ? 'The date of birth is required.' : $dobErr)], 422);
    }
    $staffDob = $role === 'patient' ? null : $dob;

    $name     = personName($first, $middle, $last);
    $username = makeUsername(db(), $first, $last);
    $tempPass = tempPassword();   // system-generated; the holder must replace it at first login

    // Role-specific fields.
    $specCsv  = null;
    $degree   = null;
    $prc      = null;
    $docType  = 'roaming';   // NOT NULL column default; only meaningful for dentists
    if ($role === 'dentist') {
        $docType = in_array($in['doctor_type'] ?? '', ['owner', 'roaming'], true)
            ? $in['doctor_type'] : 'roaming';
        $known = array_keys(dentistSpecialties());
        $specs = [];
        foreach ((array) ($in['specialties'] ?? []) as $s) {
            $s = trim((string) $s);
            $key = in_array($s, $known, true) ? $s : preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $s)));
            if ($key !== '') {
                $specs[$key] = true;
            }
        }
        if (!$specs) {
            jsonOut(['ok' => false, 'error' => 'Select at least one specialty.'], 422);
        }
        $specCsv = implode(',', array_keys($specs));
        $degree  = trim($in['degree'] ?? '') ?: null;
        if ($degree === null) {
            jsonOut(['ok' => false, 'error' => 'Degree is required for a dentist.'], 422);
        }
        $prc = trim($in['prc_license'] ?? '');
        if (!preg_match('/^\d{7}$/', $prc)) {
            jsonOut(['ok' => false, 'error' => 'PRC License No. must be a valid 7-digit number.'], 422);
        }
    }

    $stmt = db()->prepare(
        'INSERT INTO users (full_name, first_name, middle_name, last_name, username, password,
                            must_change_password, is_verified, role, doctor_type, specialties, degree, prc_license,
                            years_experience, about, contact_number, email, date_of_birth)
         VALUES (?, ?, ?, ?, ?, ?, 1, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $name, $first, ($middle !== '' ? $middle : null), $last, $username,
        password_hash($tempPass, PASSWORD_DEFAULT), $role, $docType,
        $specCsv, $degree, $prc, $years, $about ?: null, $phone ?: null, $email ?: null, $staffDob,
    ]);
    $newUserId = (int) db()->lastInsertId();
    if ($role === 'patient') {
        db()->prepare('INSERT INTO patient_records (patient_id, date_of_birth, updated_by) VALUES (?, ?, ?)')
            ->execute([$newUserId, $dob, (int) $me['user_id']]);
    }
    // A brand-new In-House dentist starts with a full Mon–Sat working grid by
    // default; a Roaming one starts empty until the admin sets one.
    if ($role === 'dentist' && $docType === 'owner') {
        seedDefaultOwnerSchedule($newUserId);
    }
    logActivity('auth', 'Created a ' . roleLabel($role) . ' account (' . $username . ')', $me);
    jsonOut([
        'ok' => true,
        'user_id' => $newUserId,
        'username' => $username,
        'temp_password' => $tempPass,
    ]);
}

// Edit an existing account's profile fields — opened by clicking a row on the
// admin Users table. Username and password are never touched here (username
// stays fixed per the app's convention; password changes go through Reset
// Password instead).
if ($action === 'update') {
    $id = (int) ($in['user_id'] ?? 0);
    $stmt = db()->prepare('SELECT role FROM users WHERE user_id = ?');
    $stmt->execute([$id]);
    $role = $stmt->fetchColumn();
    if (!$role) {
        jsonOut(['ok' => false, 'error' => 'Account not found.'], 404);
    }

    // Role change (2026-10-18): the same account (username, password, history)
    // moves to another role, e.g. a patient hired as a cashier. Everything
    // below then validates and saves the fields of the NEW role.
    $oldRole = $role;
    $newRole = (string) ($in['role'] ?? $oldRole);
    if (!in_array($newRole, $VALID_ROLES, true)) {
        jsonOut(['ok' => false, 'error' => 'Choose a valid role.'], 422);
    }
    $changing = $newRole !== $oldRole;
    if ($oldRole === 'dental_assistant' && !$changing) {
        jsonOut(['ok' => false, 'error' => 'Edit assistant profiles from the Assistant Dentists list.'], 422);
    }
    if ($changing) {
        if ($id === (int) $me['user_id']) {
            jsonOut(['ok' => false, 'error' => 'You cannot change your own role.'], 422);
        }
        if ($id === ownerAdminId()) {
            jsonOut(['ok' => false, 'error' => 'The main admin account\'s role cannot be changed.'], 403);
        }
        $live = "status IN ('pending','approved','rescheduled','payment_declined') AND scheduled_date >= CURDATE()";
        if ($oldRole === 'patient') {
            $c = db()->prepare("SELECT COUNT(*) FROM appointments WHERE (patient_id = ? OR treated_patient_id = ?) AND $live");
            $c->execute([$id, $id]);
            if (($n = (int) $c->fetchColumn()) > 0) {
                jsonOut(['ok' => false, 'error' => 'This patient still has ' . $n . ' upcoming appointment' . ($n === 1 ? '' : 's')
                    . '. Finish or cancel ' . ($n === 1 ? 'it' : 'them') . ' before changing the role.'], 422);
            }
        }
        if ($oldRole === 'dentist') {
            $c = db()->prepare("SELECT COUNT(*) FROM appointments WHERE dentist_id = ? AND $live");
            $c->execute([$id]);
            if (($n = (int) $c->fetchColumn()) > 0) {
                jsonOut(['ok' => false, 'error' => 'This doctor still has ' . $n . ' upcoming appointment' . ($n === 1 ? '' : 's')
                    . ' assigned. Reassign ' . ($n === 1 ? 'it' : 'them') . ' to another doctor first.'], 422);
            }
        }
    }
    $role = $newRole;

    $first  = properCase($in['first_name'] ?? '');
    $middle = '';   // middle names are no longer collected
    $last   = properCase($in['last_name'] ?? '');
    $email  = trim($in['email'] ?? '');
    $phone  = digitsOnly($in['contact_number'] ?? '');
    $about  = trim($in['about'] ?? '');
    $years  = ($in['years_experience'] ?? '') === '' ? null : max((int) $in['years_experience'], 0);

    if ($first !== '' && $last !== '' && (!validPersonName($first) || !validPersonName($last))) {
        jsonOut(['ok' => false, 'error' => PERSON_NAME_MSG], 422);
    }
    if ($first === '' || $last === '') {
        jsonOut(['ok' => false, 'error' => 'First name and last name are required.'], 422);
    }
    if ($email === '') {
        jsonOut(['ok' => false, 'error' => 'An e-mail address is required. It is needed for password reset.'], 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonOut(['ok' => false, 'error' => 'Enter a valid email address.'], 422);
    }
    $dupe = db()->prepare('SELECT 1 FROM users WHERE email = ? AND user_id <> ? LIMIT 1');
    $dupe->execute([$email, $id]);
    if ($dupe->fetchColumn()) {
        jsonOut(['ok' => false, 'error' => 'That email address is already registered to another account.'], 422);
    }
    $rolesWithPhone = ['patient', 'receptionist', 'cashier', 'dental_assistant'];
    if (in_array($role, $rolesWithPhone, true)) {
        if (!isPhMobile($phone)) {
            jsonOut(['ok' => false, 'error' => 'A valid phone number is required. ' . PH_MOBILE_MSG], 422);
        }
    } elseif ($phone !== '' && !isPhMobile($phone)) {
        jsonOut(['ok' => false, 'error' => PH_MOBILE_MSG], 422);
    }
    if (phoneChangeTaken($phone, $id)) {
        jsonOut(['ok' => false, 'error' => PHONE_TAKEN_MSG], 422);
    }

    $name = personName($first, $middle, $last);

    // Staff Date of Birth (18 to 80 when newly entered): required on edit too.
    // A former patient's birthdate is on patient_records, so fall back to it.
    if ($role !== 'patient') {
        $cur = db()->prepare('SELECT COALESCE(u.date_of_birth, pr.date_of_birth) FROM users u
                              LEFT JOIN patient_records pr ON pr.patient_id = u.user_id WHERE u.user_id = ?');
        $cur->execute([$id]);
        $sdob = editBirthDate((string) ($in['date_of_birth'] ?? ''), $cur->fetchColumn() ?: null, $dobErr);
        if ($sdob === null) {
            jsonOut(['ok' => false, 'error' => (($in['date_of_birth'] ?? '') === '' ? 'The date of birth is required.' : $dobErr)], 422);
        }
        if ($changing && (ageFromBirthDate($sdob) ?? 0) < STAFF_MIN_AGE) {
            jsonOut(['ok' => false, 'error' => 'Staff must be at least ' . STAFF_MIN_AGE . ' years old.'], 422);
        }
        db()->prepare('UPDATE users SET date_of_birth = ? WHERE user_id = ?')->execute([$sdob, $id]);
    }

    if ($role === 'dentist') {
        $docType = in_array($in['doctor_type'] ?? '', ['owner', 'roaming'], true)
            ? $in['doctor_type'] : 'roaming';
        $known = array_keys(dentistSpecialties());
        $specs = [];
        foreach ((array) ($in['specialties'] ?? []) as $s) {
            $s = trim((string) $s);
            $key = in_array($s, $known, true) ? $s : preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $s)));
            if ($key !== '') {
                $specs[$key] = true;
            }
        }
        if (!$specs) {
            jsonOut(['ok' => false, 'error' => 'Select at least one specialty.'], 422);
        }
        $specCsv = implode(',', array_keys($specs));
        $degree  = trim($in['degree'] ?? '') ?: null;
        if ($degree === null) {
            jsonOut(['ok' => false, 'error' => 'Degree is required for a dentist.'], 422);
        }
        $prc = trim($in['prc_license'] ?? '');
        if (!preg_match('/^\d{7}$/', $prc)) {
            jsonOut(['ok' => false, 'error' => 'PRC License No. must be a valid 7-digit number.'], 422);
        }

        $stmt = db()->prepare(
            'UPDATE users SET full_name = ?, first_name = ?, middle_name = ?, last_name = ?, email = ?,
                doctor_type = ?, specialties = ?, degree = ?, prc_license = ?, years_experience = ?, about = ?
             WHERE user_id = ?'
        );
        $stmt->execute([
            $name, $first, ($middle !== '' ? $middle : null), $last, $email,
            $docType, $specCsv, $degree, $prc, $years, $about ?: null, $id,
        ]);
        // Switching a dentist to In-House gives them the same full-grid default
        // as a brand-new one — but only if they don't already have a schedule
        // (e.g. a former Roaming dentist who was already given hours keeps them).
        if ($docType === 'owner') {
            seedDefaultOwnerSchedule($id);
        }
    } elseif ($role === 'receptionist' || $role === 'cashier' || $role === 'dental_assistant') {
        $stmt = db()->prepare(
            'UPDATE users SET full_name = ?, first_name = ?, middle_name = ?, last_name = ?, email = ?,
                contact_number = ?, years_experience = ?, about = ?
             WHERE user_id = ?'
        );
        $stmt->execute([$name, $first, ($middle !== '' ? $middle : null), $last, $email, $phone ?: null, $years, $about ?: null, $id]);
        if ($role === 'dental_assistant') {
            // An assistant also needs a roster row (duty check-in, assist tags).
            // Reuse this person's old roster row (moved away and back) if there is one.
            $ex = db()->prepare('SELECT assistant_id FROM dental_assistants
                                  WHERE user_id = ? OR (user_id IS NULL AND email = ?)
                                  ORDER BY user_id IS NULL, assistant_id DESC LIMIT 1');
            $ex->execute([$id, $email]);
            if ($aid = $ex->fetchColumn()) {
                db()->prepare('UPDATE dental_assistants SET user_id = ?, first_name = ?, last_name = ?, full_name = ?, contact_number = ?,
                                   email = ?, years_experience = ?, about = ?, is_active = 1 WHERE assistant_id = ?')
                    ->execute([$id, $first, $last, $name, $phone ?: null, $email, $years, $about ?: null, (int) $aid]);
            } else {
                db()->prepare('INSERT INTO dental_assistants (user_id, first_name, last_name, full_name, contact_number,
                                   email, years_experience, about, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)')
                    ->execute([$id, $first, $last, $name, $phone ?: null, $email, $years, $about ?: null]);
            }
        }
    } elseif ($role === 'patient') {
        $cur = db()->prepare('SELECT COALESCE(pr.date_of_birth, u.date_of_birth) FROM users u
                              LEFT JOIN patient_records pr ON pr.patient_id = u.user_id WHERE u.user_id = ?');
        $cur->execute([$id]);
        $dob = editBirthDate((string) ($in['date_of_birth'] ?? ''), $cur->fetchColumn() ?: null, $dobErr);
        if ($dob === null) {
            jsonOut(['ok' => false, 'error' => (($in['date_of_birth'] ?? '') === '' ? 'The date of birth is required.' : $dobErr)], 422);
        }
        db()->prepare(
            'INSERT INTO patient_records (patient_id, date_of_birth, updated_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE date_of_birth = VALUES(date_of_birth), updated_by = VALUES(updated_by)'
        )->execute([$id, $dob, (int) $me['user_id']]);
        $stmt = db()->prepare(
            'UPDATE users SET full_name = ?, first_name = ?, middle_name = ?, last_name = ?, email = ?, contact_number = ?
             WHERE user_id = ?'
        );
        $stmt->execute([$name, $first, ($middle !== '' ? $middle : null), $last, $email, $phone ?: null, $id]);
    } else {
        $stmt = db()->prepare(
            'UPDATE users SET full_name = ?, first_name = ?, middle_name = ?, last_name = ?, email = ? WHERE user_id = ?'
        );
        $stmt->execute([$name, $first, ($middle !== '' ? $middle : null), $last, $email, $id]);
    }

    // Editing your own account (an admin can appear in their own Admins tab)
    // should refresh the session copy of the name too — same as profile.php.
    if ($id === (int) $me['user_id']) {
        $_SESSION['full_name'] = $name;
    }

    if ($changing) {
        db()->prepare('UPDATE users SET role = ? WHERE user_id = ?')->execute([$newRole, $id]);
        if ($oldRole === 'dental_assistant') {
            // The roster row leaves the Assistant Dentists list; past assist tags keep it.
            db()->prepare('UPDATE dental_assistants SET is_active = 0, user_id = NULL WHERE user_id = ?')->execute([$id]);
        }
        logActivity('auth', 'Changed the role of ' . $name . ' from ' . roleLabel($oldRole) . ' to ' . roleLabel($newRole), $me);
        jsonOut(['ok' => true, 'role' => $newRole]);
    }

    logActivity('auth', 'Updated the profile for ' . $name, $me);
    jsonOut(['ok' => true]);
}

if ($action === 'toggle_active') {
    $id = (int) ($in['user_id'] ?? 0);
    if ($id === (int) $me['user_id']) {
        jsonOut(['ok' => false, 'error' => 'You cannot archive your own account.'], 422);
    }
    if ($id === ownerAdminId()) {
        jsonOut(['ok' => false, 'error' => 'The main admin account cannot be archived.'], 403);
    }
    $stmt = db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE user_id = ?');
    $stmt->execute([$id]);
    jsonOut(['ok' => true]);
}

if ($action === 'reset_password') {
    $id = (int) ($in['user_id'] ?? 0);
    $new = tempPassword();
    // Force the holder to set their own password on next login.
    $stmt = db()->prepare('UPDATE users SET password = ?, must_change_password = 1 WHERE user_id = ?');
    $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $id]);
    $target = db()->prepare('SELECT username FROM users WHERE user_id = ?');
    $target->execute([$id]);
    logActivity('auth', 'Issued a forced password reset for '
        . ($target->fetchColumn() ?: ('user #' . $id)), $me);
    jsonOut(['ok' => true, 'temp_password' => $new]);
}

jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
