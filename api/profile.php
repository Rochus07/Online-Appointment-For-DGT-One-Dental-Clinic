<?php
/**
 * Self-service profile endpoint. Every authenticated user may edit their own
 * account details, password and profile photo here (no role restriction).
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$me     = apiAuth();
$uid    = (int) $me['user_id'];
$action = $_GET['action'] ?? '';

/* ---------- Personal information ---------- */
if ($action === 'update') {
    $in     = requestBody();
    $first  = properCase($in['first_name'] ?? '');
    $middle = '';   // middle names are no longer collected
    $last   = properCase($in['last_name'] ?? '');
    $phone  = digitsOnly($in['contact_number'] ?? '');
    $email  = trim($in['email'] ?? '');

    if ($first !== '' && $last !== '' && (!validPersonName($first) || !validPersonName($last))) {
        jsonOut(['ok' => false, 'error' => PERSON_NAME_MSG], 422);
    }
    if ($first === '' || $last === '') {
        jsonOut(['ok' => false, 'error' => 'Your first name and last name are required.'], 422);
    }
    // Contact number required for these roles; e-mail for everyone (2026-10-17).
    if ($phone === '' && in_array($me['role'], ['patient', 'receptionist', 'cashier', 'dental_assistant'], true)) {
        jsonOut(['ok' => false, 'error' => 'Your contact number is required. ' . PH_MOBILE_MSG], 422);
    }
    if ($phone !== '' && !isPhMobile($phone)) {
        jsonOut(['ok' => false, 'error' => PH_MOBILE_MSG], 422);
    }
    if (phoneChangeTaken($phone, (int) $me['user_id'])) {
        jsonOut(['ok' => false, 'error' => PHONE_TAKEN_MSG], 422);
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonOut(['ok' => false, 'error' => 'Please enter a valid email address. It is needed for password reset.'], 422);
    }

    // A patient sets their date of birth once; after that only staff can
    // change it (2026-10-17).
    // Staff keep it on users.date_of_birth (migration 072).
    $dob = null;
    if (trim((string) ($in['date_of_birth'] ?? '')) !== '') {
        $cur = db()->prepare($me['role'] === 'patient'
            ? 'SELECT date_of_birth FROM patient_records WHERE patient_id = ?'
            : 'SELECT date_of_birth FROM users WHERE user_id = ?');
        $cur->execute([$uid]);
        if (empty($cur->fetchColumn())) {
            $err = null;
            $dob = $me['role'] === 'patient'
                ? validBirthDate(trim((string) $in['date_of_birth']), $err)
                : staffBirthDate(trim((string) $in['date_of_birth']), $err);
            if ($dob === null) {
                jsonOut(['ok' => false, 'error' => $err], 422);
            }
        }
    }

    $name = personName($first, $middle, $last);
    $stmt = db()->prepare(
        'UPDATE users SET full_name = ?, first_name = ?, middle_name = ?, last_name = ?,
                          contact_number = ?, email = ? WHERE user_id = ?'
    );
    $stmt->execute([$name, $first, ($middle !== '' ? $middle : null), $last, $phone ?: null, $email ?: null, $uid]);

    // A dental assistant's roster row mirrors their login profile (migration 058).
    if ($me['role'] === 'dental_assistant') {
        db()->prepare(
            'UPDATE dental_assistants SET full_name = ?, first_name = ?, middle_name = ?, last_name = ?,
                                          contact_number = ?, email = ? WHERE user_id = ?'
        )->execute([$name, $first, ($middle !== '' ? $middle : null), $last, $phone ?: null, $email ?: null, $uid]);
    }

    if ($dob !== null && $me['role'] !== 'patient') {
        db()->prepare('UPDATE users SET date_of_birth = ? WHERE user_id = ? AND date_of_birth IS NULL')->execute([$dob, $uid]);
        logActivity('records', 'Added their date of birth', $me);
    } elseif ($dob !== null) {
        db()->prepare(
            'INSERT INTO patient_records (patient_id, date_of_birth) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE date_of_birth = COALESCE(date_of_birth, VALUES(date_of_birth))'
        )->execute([$uid, $dob]);
        logActivity('records', 'Added their date of birth', $me);
    }

    $_SESSION['full_name'] = $name;
    jsonOut(['ok' => true]);
}

/* ---------- Health & emergency information (patients, 2026-10-17) ---------- */
if ($action === 'health') {
    if ($me['role'] !== 'patient') {
        jsonOut(['ok' => false, 'error' => 'Only patients have this section.'], 403);
    }
    $in    = requestBody();
    $ecName = properCase($in['emergency_contact_name'] ?? '');
    $ecNum  = digitsOnly($in['emergency_contact_number'] ?? '');
    $allergies = trim(preg_replace('/\s+/u', ' ', (string) ($in['allergies'] ?? '')));

    if ($ecName === '' || $ecNum === '') {
        jsonOut(['ok' => false, 'error' => 'Enter an emergency contact name and number.'], 422);
    }
    if (mb_strlen($ecName) > 120) {
        jsonOut(['ok' => false, 'error' => 'The emergency contact name is too long.'], 422);
    }
    if (!isPhMobile($ecNum)) {
        jsonOut(['ok' => false, 'error' => 'Emergency contact number: ' . PH_MOBILE_MSG], 422);
    }
    if ($allergies === '') {
        jsonOut(['ok' => false, 'error' => 'List your allergies, or type "None" if you have none.'], 422);
    }
    if (mb_strlen($allergies) > 500) {
        jsonOut(['ok' => false, 'error' => 'Please keep allergies under 500 characters.'], 422);
    }

    db()->prepare(
        'INSERT INTO patient_records (patient_id, emergency_contact_name, emergency_contact_number, allergies)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE emergency_contact_name = VALUES(emergency_contact_name),
             emergency_contact_number = VALUES(emergency_contact_number),
             allergies = VALUES(allergies)'
    )->execute([$uid, $ecName, $ecNum, $allergies]);
    logActivity('records', 'Updated their health and emergency information', $me);
    jsonOut(['ok' => true, 'missing' => patientProfileMissing($uid)]);
}

/* ---------- Password ---------- */
if ($action === 'password') {
    $in      = requestBody();
    $current = $in['current_password'] ?? '';
    $new     = $in['new_password'] ?? '';

    if (!isStrongPassword($new)) {
        jsonOut(['ok' => false, 'error' => STRONG_PASSWORD_MSG], 422);
    }

    $stmt = db()->prepare('SELECT password FROM users WHERE user_id = ?');
    $stmt->execute([$uid]);
    $hash = (string) $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        jsonOut(['ok' => false, 'error' => 'Your current password is incorrect.'], 422);
    }

    // Clear the "still on a temporary password" flag once they set their own.
    $wasTemp = !empty($me['must_change_password']);
    $upd = db()->prepare('UPDATE users SET password = ?, must_change_password = 0 WHERE user_id = ?');
    $upd->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
    logActivity('auth', 'Changed own password'
        . ($wasTemp ? ' (replacing the temporary one from a forced reset)' : ''), $me);
    jsonOut(['ok' => true]);
}

/* ---------- Profile photo ---------- */
if ($action === 'avatar') {
    $file = $_FILES['photo'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        jsonOut(['ok' => false, 'error' => 'No image was uploaded.'], 422);
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        jsonOut(['ok' => false, 'error' => 'Image must be 2 MB or smaller.'], 422);
    }

    $ext = match (mime_content_type($file['tmp_name'])) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => null,
    };
    if ($ext === null) {
        jsonOut(['ok' => false, 'error' => 'Use a JPG, PNG, WEBP or GIF image.'], 422);
    }

    $dir = __DIR__ . '/../uploads/avatars';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $filename = 'u' . $uid . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        jsonOut(['ok' => false, 'error' => 'Could not save the image. Please try again.'], 500);
    }

    // Drop the previous file.
    $stmt = db()->prepare('SELECT avatar FROM users WHERE user_id = ?');
    $stmt->execute([$uid]);
    $old = (string) $stmt->fetchColumn();
    if ($old !== '' && is_file($dir . '/' . $old)) {
        @unlink($dir . '/' . $old);
    }

    $upd = db()->prepare('UPDATE users SET avatar = ? WHERE user_id = ?');
    $upd->execute([$filename, $uid]);
    jsonOut(['ok' => true, 'avatar_url' => avatarUrl($filename)]);
}

if ($action === 'avatar_remove') {
    $stmt = db()->prepare('SELECT avatar FROM users WHERE user_id = ?');
    $stmt->execute([$uid]);
    $old = (string) $stmt->fetchColumn();
    $dir = __DIR__ . '/../uploads/avatars';
    if ($old !== '' && is_file($dir . '/' . $old)) {
        @unlink($dir . '/' . $old);
    }
    $upd = db()->prepare('UPDATE users SET avatar = NULL WHERE user_id = ?');
    $upd->execute([$uid]);
    jsonOut(['ok' => true]);
}

jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
