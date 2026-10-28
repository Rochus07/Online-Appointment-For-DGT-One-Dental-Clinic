<?php
declare(strict_types=1);

/*
 * Dental assistants: a roster row (dental_assistants) plus, since migration 058,
 * a linked login account (users.role = 'dental_assistant', dental_assistants.user_id).
 *
 * Physical duties: room preparation, instrument sterilization, four-handed
 * dentistry assistance (instrument passing, suction), and dental-material prep.
 *
 * System role (when signed in): look up patient records in the same
 * non-clinical view the receptionist has, check today's patients in (tagging
 * themselves as the assisting staff), and mark themselves on/off duty. No
 * clinical charting, treatment plans, or appointment approve/decline/
 * reschedule/cancel/reassign.
 *
 * Endpoints:
 *   GET  action=list      (admin, receptionist)          every assistant + on_duty_today + login username
 *   GET  action=on_duty    (receptionist, dentist, admin) today's on-duty roster
 *   GET  action=me         (dental_assistant)             own profile + on_duty_today
 *   POST action=add        (admin)   {first_name, middle_name?, last_name, contact_number, email, years_experience?, about?}
 *   POST action=update     (admin)   {assistant_id, first_name, middle_name?, last_name, contact_number, email, years_experience?, about?}
 *   POST action=toggle_active (admin)                     {assistant_id}
 *   POST action=set_shift  (receptionist, admin, dental_assistant) {assistant_id, on}; an assistant may only set their own
 */

require_once __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'list') {
    apiAuth(['admin', 'receptionist']);
    $rows = db()->query(
        "SELECT da.assistant_id, da.user_id, u.username, da.first_name, da.middle_name, da.last_name, da.full_name,
                da.contact_number, da.email, da.years_experience, da.about, COALESCE(u.avatar, da.avatar) AS avatar, da.is_active,
                u.date_of_birth,
                da.created_at,
                EXISTS (SELECT 1 FROM assistant_shifts s
                        WHERE s.assistant_id = da.assistant_id
                          AND s.shift_date = CURDATE()) AS on_duty_today
         FROM dental_assistants da
         LEFT JOIN users u ON u.user_id = da.user_id
         ORDER BY da.is_active DESC, da.full_name"
    )->fetchAll();
    jsonOut(['ok' => true, 'assistants' => $rows]);
}

if ($method === 'GET' && $action === 'on_duty') {
    apiAuth(['receptionist', 'dentist', 'admin']);
    jsonOut(['ok' => true, 'assistants' => dentalAssistantsOnDuty()]);
}

if ($method === 'GET' && $action === 'me') {
    $me = apiAuth(['dental_assistant']);
    $st = db()->prepare(
        "SELECT da.assistant_id, da.full_name,
                EXISTS (SELECT 1 FROM assistant_shifts s
                        WHERE s.assistant_id = da.assistant_id
                          AND s.shift_date = CURDATE()) AS on_duty_today
         FROM dental_assistants da
         WHERE da.user_id = ? AND da.is_active = 1"
    );
    $st->execute([(int) $me['user_id']]);
    $row = $st->fetch();
    if (!$row) {
        jsonOut(['ok' => false, 'error' => 'Your account is not linked to an active assistant profile.'], 404);
    }
    jsonOut(['ok' => true, 'assistant' => $row]);
}

$me = apiAuth(['admin', 'receptionist', 'dental_assistant']);
$in = requestBody();

/**
 * Validate the shared add/update profile fields. Returns the cleaned values.
 * E-mail is required: every assistant gets (or already has) a login, which
 * needs an address for password reset.
 */
$readProfile = static function (array $in, ?int $excludeUserId, bool $requireDob = false, ?string $savedDob = null): array {
    $first  = properCase($in['first_name'] ?? '');
    $middle = '';   // middle names are no longer collected
    $last   = properCase($in['last_name'] ?? '');
    if ($first !== '' && $last !== '' && (!validPersonName($first) || !validPersonName($last))) {
        jsonOut(['ok' => false, 'error' => PERSON_NAME_MSG], 422);
    }
    if ($first === '' || $last === '') {
        jsonOut(['ok' => false, 'error' => 'Enter the assistant’s first and last name.'], 422);
    }
    $phone = digitsOnly($in['contact_number'] ?? '');
    if (!isPhMobile($phone)) {
        jsonOut(['ok' => false, 'error' => PH_MOBILE_MSG], 422);
    }
    if ($excludeUserId ? phoneChangeTaken($phone, $excludeUserId) : phoneInUse($phone)) {
        jsonOut(['ok' => false, 'error' => PHONE_TAKEN_MSG], 422);
    }
    $email = trim($in['email'] ?? '');
    if ($email === '') {
        jsonOut(['ok' => false, 'error' => 'An e-mail address is required. It is needed for password reset.'], 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonOut(['ok' => false, 'error' => 'Enter a valid e-mail address.'], 422);
    }
    $dupe = db()->prepare('SELECT 1 FROM users WHERE email = ?' . ($excludeUserId ? ' AND user_id <> ?' : '') . ' LIMIT 1');
    $dupe->execute($excludeUserId ? [$email, $excludeUserId] : [$email]);
    if ($dupe->fetchColumn()) {
        jsonOut(['ok' => false, 'error' => 'That email address is already registered to another account.'], 422);
    }
    $years = $in['years_experience'] ?? '';
    $years = ($years === '' || $years === null) ? null : max(0, (int) $years);
    $about = trim((string) ($in['about'] ?? ''));
    // Date of Birth (18 to 80 when newly entered): required on add and on edit.
    $dob = null;
    if (trim((string) ($in['date_of_birth'] ?? '')) !== '' || $requireDob) {
        $dob = editBirthDate((string) ($in['date_of_birth'] ?? ''), $savedDob, $dobErr);
        if ($dob === null) {
            jsonOut(['ok' => false, 'error' => (($in['date_of_birth'] ?? '') === '' ? 'The date of birth is required.' : $dobErr)], 422);
        }
    }
    return [
        'dob'    => $dob,
        'first'  => mb_substr($first, 0, 60),
        'middle' => $middle !== '' ? mb_substr($middle, 0, 60) : null,
        'last'   => mb_substr($last, 0, 60),
        'full'   => mb_substr(personName($first, $middle, $last), 0, 120),
        'phone'  => $phone,
        'email'  => $email,
        'years'  => $years,
        'about'  => $about !== '' ? $about : null,
    ];
};

/** Same mailbox-existence gate as api/users.php action=add; run before opening a transaction. */
$assertEmailExists = static function (string $email): void {
    require_once __DIR__ . '/../includes/email_verify.php';
    if (verifyEmailExists(strtolower($email))['exists'] === false) {
        jsonOut(['ok' => false, 'error' => 'This email address does not exist.'], 422);
    }
};

/** Create the linked users row for a roster entry. Returns [user_id, username, temp_password]. */
$createLogin = static function (array $p): array {
    $username = makeUsername(db(), $p['first'], $p['last']);
    $tempPass = tempPassword();
    db()->prepare(
        "INSERT INTO users (full_name, first_name, middle_name, last_name, username, password,
                            must_change_password, is_verified, role, years_experience, about, contact_number, email, date_of_birth)
         VALUES (?, ?, ?, ?, ?, ?, 1, 1, 'dental_assistant', ?, ?, ?, ?, ?)"
    )->execute([
        $p['full'], $p['first'], $p['middle'], $p['last'], $username,
        password_hash($tempPass, PASSWORD_DEFAULT), $p['years'], $p['about'], $p['phone'], $p['email'], $p['dob'],
    ]);
    return [(int) db()->lastInsertId(), $username, $tempPass];
};

if ($action === 'add') {
    apiAuth(['admin']);
    $p = $readProfile($in, null, true);
    $active = array_key_exists('is_active', $in) ? (int) (bool) $in['is_active'] : 1;
    $assertEmailExists($p['email']);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        [$userId, $username, $tempPass] = $createLogin($p);
        $pdo->prepare(
            'INSERT INTO dental_assistants
                (user_id, first_name, middle_name, last_name, full_name, contact_number, email, years_experience, about, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $userId, $p['first'], $p['middle'], $p['last'], $p['full'], $p['phone'], $p['email'],
            $p['years'], $p['about'], $active,
        ]);
        $newId = (int) $pdo->lastInsertId();
        if (!$active) {
            $pdo->prepare('UPDATE users SET is_active = 0 WHERE user_id = ?')->execute([$userId]);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonOut(['ok' => false, 'error' => 'Could not add the assistant.'], 500);
    }
    logActivity('auth', 'Added assistant dentist ' . $p['full'] . ' to the roster (login ' . $username . ')', $me);
    jsonOut(['ok' => true, 'assistant_id' => $newId, 'username' => $username, 'temp_password' => $tempPass]);
}

if ($action === 'update') {
    apiAuth(['admin']);
    $id  = (int) ($in['assistant_id'] ?? 0);
    $cur = db()->prepare('SELECT user_id, is_active FROM dental_assistants WHERE assistant_id = ?');
    $cur->execute([$id]);
    $row = $cur->fetch();
    if (!$row) {
        jsonOut(['ok' => false, 'error' => 'Assistant not found.'], 404);
    }
    $linkedId = $row['user_id'] !== null ? (int) $row['user_id'] : null;
    $savedDob = null;
    if ($linkedId !== null) {
        $sd = db()->prepare('SELECT date_of_birth FROM users WHERE user_id = ?');
        $sd->execute([$linkedId]);
        $savedDob = $sd->fetchColumn() ?: null;
    }
    $p = $readProfile($in, $linkedId, true, $savedDob);
    if ($linkedId === null) {
        $assertEmailExists($p['email']);
    }

    $pdo = db();
    $pdo->beginTransaction();
    $creds = null;
    try {
        $pdo->prepare(
            'UPDATE dental_assistants
                SET first_name = ?, middle_name = ?, last_name = ?, full_name = ?,
                    contact_number = ?, email = ?, years_experience = ?, about = ?
              WHERE assistant_id = ?'
        )->execute([
            $p['first'], $p['middle'], $p['last'], $p['full'], $p['phone'], $p['email'],
            $p['years'], $p['about'], $id,
        ]);
        if ($linkedId !== null) {
            $pdo->prepare(
                'UPDATE users SET full_name = ?, first_name = ?, middle_name = ?, last_name = ?,
                                  email = ?, contact_number = ?, years_experience = ?, about = ?
                 WHERE user_id = ?'
            )->execute([
                $p['full'], $p['first'], $p['middle'], $p['last'], $p['email'], $p['phone'],
                $p['years'], $p['about'], $linkedId,
            ]);
            if ($p['dob'] !== null) {
                $pdo->prepare('UPDATE users SET date_of_birth = ? WHERE user_id = ?')->execute([$p['dob'], $linkedId]);
            }
        } else {
            // A roster row from before migration 058 has no login yet; saving
            // its profile issues one, the same way a brand-new assistant gets one.
            $creds = $createLogin($p);
            $pdo->prepare('UPDATE dental_assistants SET user_id = ? WHERE assistant_id = ?')->execute([$creds[0], $id]);
            if (!(int) $row['is_active']) {
                $pdo->prepare('UPDATE users SET is_active = 0 WHERE user_id = ?')->execute([$creds[0]]);
            }
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonOut(['ok' => false, 'error' => 'Could not update the assistant.'], 500);
    }
    if ($creds !== null) {
        logActivity('auth', 'Updated assistant dentist profile for ' . $p['full'] . ' and created a login (' . $creds[1] . ')', $me);
        jsonOut(['ok' => true, 'username' => $creds[1], 'temp_password' => $creds[2]]);
    }
    logActivity('auth', 'Updated assistant dentist profile for ' . $p['full'], $me);
    jsonOut(['ok' => true]);
}

if ($action === 'toggle_active') {
    apiAuth(['admin']);
    $id = (int) ($in['assistant_id'] ?? 0);
    db()->prepare('UPDATE dental_assistants SET is_active = 1 - is_active WHERE assistant_id = ?')->execute([$id]);
    // Archiving or restoring the roster row does the same to the linked login.
    db()->prepare(
        'UPDATE users u JOIN dental_assistants da ON da.user_id = u.user_id
            SET u.is_active = da.is_active
          WHERE da.assistant_id = ?'
    )->execute([$id]);
    jsonOut(['ok' => true]);
}

if ($action === 'set_shift') {
    $id = (int) ($in['assistant_id'] ?? 0);
    $on = (bool) ($in['on'] ?? false);
    if ($me['role'] === 'dental_assistant') {
        $selfId = assistantIdForUser((int) $me['user_id']);
        if ($selfId === null) {
            jsonOut(['ok' => false, 'error' => 'Your account is not linked to an active assistant profile.'], 403);
        }
        if ($id !== 0 && $id !== $selfId) {
            jsonOut(['ok' => false, 'error' => 'You can only change your own duty status.'], 403);
        }
        $id = $selfId;
    }

    $chk = db()->prepare('SELECT full_name FROM dental_assistants WHERE assistant_id = ? AND is_active = 1');
    $chk->execute([$id]);
    $name = $chk->fetchColumn();
    if ($name === false) {
        jsonOut(['ok' => false, 'error' => 'That assistant is not on the active roster.'], 422);
    }

    $today = db()->query('SELECT CURDATE()')->fetchColumn();
    if ($on) {
        db()->prepare(
            'INSERT IGNORE INTO assistant_shifts (assistant_id, shift_date, marked_by) VALUES (?, ?, ?)'
        )->execute([$id, $today, (int) $me['user_id']]);
    } else {
        db()->prepare('DELETE FROM assistant_shifts WHERE assistant_id = ? AND shift_date = ?')
            ->execute([$id, $today]);
    }
    logActivity('appointments', ($me['role'] === 'dental_assistant'
        ? ($on ? 'Marked themselves on duty' : 'Marked themselves off duty')
        : 'Marked ' . $name . ($on ? ' on duty' : ' off duty')) . ' for ' . $today, $me);
    jsonOut(['ok' => true]);
}

jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
