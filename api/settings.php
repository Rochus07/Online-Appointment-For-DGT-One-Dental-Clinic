<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

/**
 * Absolute path to a MySQL client binary that ships with XAMPP.
 * Tries the XAMPP root above this app, then a couple of common install paths.
 */
function mysqlClientPath(string $exe): ?string
{
    $candidates = [
        dirname(__DIR__, 3) . '\\mysql\\bin\\' . $exe,   // d:\XAMPP\mysql\bin (app lives in \htdocs\dgt_dental_clinic)
        'C:\\xampp\\mysql\\bin\\' . $exe,
        'D:\\xampp\\mysql\\bin\\' . $exe,
    ];
    foreach ($candidates as $p) {
        if (is_file($p)) {
            return $p;
        }
    }
    return null;
}

/** Common connection arguments for the mysql / mysqldump CLIs. */
function mysqlCliArgs(): string
{
    return sprintf(
        '--host=%s --port=%s --user=%s --default-character-set=%s',
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_PORT),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_CHARSET)
    );
}

/** True when PHP may run shell commands (exec() exists and is not disabled). */
function canExec(): bool
{
    return function_exists('exec')
        && !in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);
}

/**
 * Plain-PHP database dump, used when mysqldump can't run (e.g. the live
 * Hostinger server, where there is no XAMPP folder and exec() may be off).
 * Writes every table's CREATE statement and its rows as batched INSERTs to
 * php://output. Each statement is one line ending in ";" (newlines inside
 * values are escaped), which phpRestore() relies on.
 */
function phpDump(): void
{
    $pdo = db();
    $out = fopen('php://output', 'wb');
    fwrite($out, "-- DGT One Dental Center database backup\n-- Database: " . DB_NAME . "\n-- Created: " . date('Y-m-d H:i:s') . "\n\n");
    fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
    foreach ($tables as [$t]) {
        $q = '`' . str_replace('`', '``', $t) . '`';
        $create = $pdo->query("SHOW CREATE TABLE $q")->fetch(PDO::FETCH_NUM)[1];
        fwrite($out, "DROP TABLE IF EXISTS $q;\n" . $create . ";\n\n");
        $offset = 0;
        while (true) {
            $rows = $pdo->query("SELECT * FROM $q LIMIT 500 OFFSET $offset")->fetchAll(PDO::FETCH_NUM);
            if (!$rows) {
                break;
            }
            $vals = [];
            foreach ($rows as $r) {
                $vals[] = '(' . implode(',', array_map(static fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $r)) . ')';
            }
            fwrite($out, "INSERT INTO $q VALUES " . implode(',', $vals) . ";\n");
            $offset += 500;
            if (count($rows) < 500) {
                break;
            }
        }
        fwrite($out, "\n");
    }
    fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($out);
}

/**
 * Plain-PHP restore for a dump made by phpDump() or mysqldump: runs the file
 * statement by statement (a statement ends at a line ending in ";"). Files
 * that change the DELIMITER (stored routines) are refused.
 */
function phpRestore(string $file): void
{
    $pdo = db();
    $fh = fopen($file, 'rb');
    if (!$fh) {
        throw new RuntimeException('Could not read the uploaded file.');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $buf = '';
    try {
        while (($line = fgets($fh)) !== false) {
            $trim = trim($line);
            if ($buf === '' && ($trim === '' || str_starts_with($trim, '--') || str_starts_with($trim, '#'))) {
                continue;
            }
            if ($buf === '' && stripos($trim, 'DELIMITER') === 0) {
                throw new RuntimeException('This backup contains stored routines, which can only be restored with the mysql tool.');
            }
            $buf .= $line;
            if (str_ends_with($trim, ';')) {
                $stmt = trim($buf);
                $buf = '';
                if (preg_match('/^(USE|CREATE DATABASE|DROP DATABASE)\b/i', $stmt)) {
                    continue;   // always restore into this app's own database
                }
                $pdo->exec($stmt);
            }
        }
        if (trim($buf) !== '') {
            $pdo->exec(trim($buf));
        }
    } finally {
        fclose($fh);
        try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $e) { /* ignore */ }
    }
}

/* ---------------------------------------------------------------------------
   Database backup — streams a mysqldump as a file download (admin only).
   --------------------------------------------------------------------------- */
if ($method === 'GET' && $action === 'backup') {
    $me = apiAuth(['admin']);

    $name = 'dental_clinic_backup_' . date('Y-m-d_His') . '.sql';
    $dump = canExec() ? mysqlClientPath('mysqldump.exe') : null;
    if ($dump === null) {
        // No mysqldump here (live server): build the dump in PHP instead.
        @set_time_limit(300);
        header_remove('Content-Type');
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: no-store');
        phpDump();
        logActivity('auth', 'Downloaded a database backup', $me ?? currentUser());
        exit;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'dcbak');
    $err = $tmp . '.err';

    putenv('MYSQL_PWD=' . DB_PASS);
    $cmd = sprintf(
        '%s %s --single-transaction --add-drop-table --routines --events %s > %s 2> %s',
        escapeshellarg($dump),
        mysqlCliArgs(),
        escapeshellarg(DB_NAME),
        escapeshellarg($tmp),
        escapeshellarg($err)
    );
    exec($cmd, $_o, $code);
    putenv('MYSQL_PWD');

    if ($code !== 0 || !is_file($tmp) || filesize($tmp) === 0) {
        $detail = is_file($err) ? trim((string) file_get_contents($err)) : '';
        @unlink($tmp);
        @unlink($err);
        jsonOut(['ok' => false, 'error' => 'Backup failed. ' . ($detail !== '' ? $detail : 'mysqldump exited with code ' . $code)], 500);
    }

    logActivity('auth', 'Downloaded a database backup', $me);
    header_remove('Content-Type');
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: no-store');
    readfile($tmp);
    @unlink($tmp);
    @unlink($err);
    exit;
}

/** Online-payment instructions shown to the patient at the reservation-fee step. */
function paymentInfo(): array
{
    $qr = static function (string $key): ?string {
        $f = setting($key, '');
        return $f !== '' && is_file(__DIR__ . '/../uploads/payment/' . $f)
            ? '/dgt_dental_clinic/uploads/payment/' . rawurlencode($f)
            : null;
    };
    return [
        'bank_details'    => setting('pay_bank_details', ''),
        'gcash_name'      => setting('pay_gcash_name', ''),
        'gcash_number'    => setting('pay_gcash_number', ''),
        'gcash_qr'        => $qr('pay_gcash_qr'),
        'bank_qr'         => $qr('pay_bank_qr'),
        // Admin toggle (migration 063): must an online payment carry a
        // receipt screenshot, or is the reference number enough?
        'enable_receipt_validation' => setting('enable_receipt_validation', '1') !== '0',
        // Extra methods the admin added (migration 078), active ones only.
        'methods'         => extraPaymentMethods(),
    ];
}

/* Legal texts for the admin editor (Terms & Conditions, Privacy Policy). */
if ($method === 'GET' && $action === 'legal') {
    apiAuth(['admin']);
    require_once __DIR__ . '/../includes/legal.php';
    $docs = [];
    foreach (LEGAL_DOCS as $k => $title) {
        $d = legalDoc($k);
        $docs[$k] = ['title' => $title, 'body' => $d['body'], 'custom' => $d['custom'], 'updated_at' => $d['updated_at']];
    }
    jsonOut(['ok' => true, 'docs' => $docs]);
}

/* Reading blocked dates + fee + payment info is allowed for booking flows (any auth user). */
if ($method === 'GET') {
    apiAuth();
    if ($action === 'blocked_dates') {
        $rows = db()->query('SELECT blocked_date, reason FROM blocked_dates ORDER BY blocked_date')->fetchAll();
        jsonOut(['ok' => true, 'blocked' => $rows]);
    }
    if ($action === 'payment_info') {
        jsonOut(['ok' => true, 'payment' => paymentInfo()]);
    }
    // Every admin-added method, including the ones turned off (Settings card).
    if ($action === 'payment_methods') {
        apiAuth(['admin']);
        jsonOut(['ok' => true, 'methods' => extraPaymentMethods(false)]);
    }
    jsonOut([
        'ok' => true,
        'settings' => array_merge(
            ['reservation_fee' => setting('reservation_fee', '500.00')],
            paymentInfo()
        ),
    ]);
}

/* Payment QR upload — multipart, admin only, must run before requestBody(). */
if ($action === 'upload_payment_qr') {
    apiAuth(['admin']);
    $which = $_GET['method'] ?? '';
    // 'm<id>' = an admin-added method (migration 078); its QR lives on its row.
    $extraId = preg_match('/^m(\d+)$/', $which, $mm) ? (int) $mm[1] : 0;
    $extraRow = null;
    if ($extraId) {
        $chk = db()->prepare('SELECT qr_image FROM payment_methods WHERE method_id = ?');
        $chk->execute([$extraId]);
        $extraRow = $chk->fetch();
        if (!$extraRow) {
            jsonOut(['ok' => false, 'error' => 'Unknown payment method.'], 422);
        }
    } elseif (!in_array($which, ['gcash', 'bank'], true)) {
        jsonOut(['ok' => false, 'error' => 'Unknown payment method.'], 422);
    }
    $file = $_FILES['qr'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        jsonOut(['ok' => false, 'error' => 'No image was uploaded.'], 422);
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        jsonOut(['ok' => false, 'error' => 'Image must be 3 MB or smaller.'], 422);
    }
    $ext = match (mime_content_type($file['tmp_name'])) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => null,
    };
    if ($ext === null) {
        jsonOut(['ok' => false, 'error' => 'Use a JPG, PNG or WEBP image.'], 422);
    }
    $dir = __DIR__ . '/../uploads/payment';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = 'qr_' . $which . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        jsonOut(['ok' => false, 'error' => 'Could not save the image.'], 500);
    }
    $old = $extraId ? (string) ($extraRow['qr_image'] ?? '') : setting('pay_' . $which . '_qr', '');
    if ($old !== '' && is_file($dir . '/' . $old)) {
        @unlink($dir . '/' . $old);
    }
    if ($extraId) {
        db()->prepare('UPDATE payment_methods SET qr_image = ? WHERE method_id = ?')->execute([$name, $extraId]);
    } else {
        setSetting('pay_' . $which . '_qr', $name);
    }
    jsonOut(['ok' => true, 'url' => '/dgt_dental_clinic/uploads/payment/' . rawurlencode($name)]);
}

/* ---------------------------------------------------------------------------
   Database restore — imports an uploaded .sql snapshot (admin only).
   --------------------------------------------------------------------------- */
if ($action === 'restore') {
    apiAuth(['admin']);

    $client = canExec() ? mysqlClientPath('mysql.exe') : null;

    $file = $_FILES['sqlfile'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        jsonOut(['ok' => false, 'error' => 'No backup file was uploaded.'], 422);
    }
    if (!preg_match('/\.sql$/i', (string) $file['name'])) {
        jsonOut(['ok' => false, 'error' => 'The backup file must have a .sql extension.'], 422);
    }
    if (($file['size'] ?? 0) > 64 * 1024 * 1024) {
        jsonOut(['ok' => false, 'error' => 'Backup file is larger than the 64 MB limit.'], 422);
    }

    $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 4096);
    if (stripos($head, 'CREATE TABLE') === false && stripos($head, 'INSERT INTO') === false) {
        jsonOut(['ok' => false, 'error' => 'That file does not look like a SQL database dump.'], 422);
    }

    $sql = tempnam(sys_get_temp_dir(), 'dcres');
    $err = $sql . '.err';
    if (!move_uploaded_file($file['tmp_name'], $sql)) {
        @unlink($sql);
        jsonOut(['ok' => false, 'error' => 'Could not stage the uploaded file.'], 500);
    }
    if ($client === null) {
        // No mysql client here (live server): run the file in PHP instead.
        @set_time_limit(300);
        try {
            phpRestore($sql);
        } catch (Throwable $e) {
            @unlink($sql);
            jsonOut(['ok' => false, 'error' => 'Restore failed. ' . $e->getMessage()], 500);
        }
        @unlink($sql);
        jsonOut(['ok' => true]);
    }

    putenv('MYSQL_PWD=' . DB_PASS);
    $cmd = sprintf(
        '%s %s %s < %s 2> %s',
        escapeshellarg($client),
        mysqlCliArgs(),
        escapeshellarg(DB_NAME),
        escapeshellarg($sql),
        escapeshellarg($err)
    );
    exec($cmd, $_o, $code);
    putenv('MYSQL_PWD');

    $detail = is_file($err) ? trim((string) file_get_contents($err)) : '';
    @unlink($sql);
    @unlink($err);

    if ($code !== 0) {
        jsonOut(['ok' => false, 'error' => 'Restore failed. ' . ($detail !== '' ? $detail : 'mysql exited with code ' . $code)], 500);
    }
    jsonOut(['ok' => true]);
}

/* Time-slot management — clinic-wide, admin only (from the Settings page). */
if ($action === 'add_slot' || $action === 'remove_slot') {
    apiAuth(['admin']);
    $in   = requestBody();
    $slot = trim((string) ($in['slot'] ?? ''));
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $slot)) {
        jsonOut(['ok' => false, 'error' => 'Enter a time as HH:MM (e.g. 17:00).'], 422);
    }
    $slots = generateSlots();
    if ($action === 'add_slot') {
        $slots[] = $slot;
    } else {
        $slots = array_diff($slots, [$slot]);
    }
    $slots = array_values(array_unique($slots));
    sort($slots);
    if (!$slots) {
        jsonOut(['ok' => false, 'error' => 'Keep at least one time slot.'], 422);
    }
    setSetting('time_slots', implode(',', $slots));
    jsonOut(['ok' => true, 'slots' => $slots]);
}

/* Blocking / unblocking an operational date is clinic-wide, admin only
   (from the Settings page). Not per-dentist. */
if ($action === 'block_date' || $action === 'unblock_date') {
    apiAuth(['admin']);
    $in   = requestBody();
    $date = $in['blocked_date'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        jsonOut(['ok' => false, 'error' => 'A valid date is required.'], 422);
    }
    if ($action === 'block_date') {
        db()->prepare(
            'INSERT INTO blocked_dates (blocked_date, reason) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE reason = VALUES(reason)'
        )->execute([$date, (trim($in['reason'] ?? '') ?: null)]);
        // Follow-up plans recommended for this date move to the next open day.
        shiftTreatmentPlanDueDates($date);
    } else {
        db()->prepare('DELETE FROM blocked_dates WHERE blocked_date = ?')->execute([$date]);
    }
    jsonOut(['ok' => true]);
}

/* Mutations: admin only. */
$me = apiAuth(['admin']);
$in = requestBody();

if ($action === 'save_fees') {
    $fee = (float) ($in['reservation_fee'] ?? 0);
    if ($fee < 0) {
        jsonOut(['ok' => false, 'error' => 'Reservation fee cannot be negative.'], 422);
    }
    setSetting('reservation_fee', number_format($fee, 2, '.', ''));
    jsonOut(['ok' => true]);
}

if ($action === 'save_receipt_validation') {
    $on = !empty($in['enabled']) && $in['enabled'] !== 'false';
    setSetting('enable_receipt_validation', $on ? '1' : '0');
    logActivity('appointments', ($on ? 'Turned on' : 'Turned off') . ' the required payment screenshot for online bookings', $me);
    jsonOut(['ok' => true, 'enabled' => $on]);
}

if ($action === 'save_sunday_open') {   // TEMPORARY switch
    $on = !empty($in['enabled']) && $in['enabled'] !== 'false';
    setSetting('sunday_open', $on ? '1' : '0');
    logActivity('appointments', ($on ? 'Opened' : 'Closed') . ' the clinic on Sundays', $me);
    jsonOut(['ok' => true, 'enabled' => $on]);
}

if ($action === 'save_lead_time') {
    $on = !empty($in['enabled']) && $in['enabled'] !== 'false';
    setSetting('enforce_two_day_advance', $on ? '1' : '0');
    logActivity('appointments', ($on ? 'Turned on' : 'Turned off') . ' the 1-day advance booking rule for online bookings', $me);
    jsonOut(['ok' => true, 'enabled' => $on]);
}

if ($action === 'save_payment') {
    // Only the keys that were sent are saved, so the GCash form never wipes
    // the bank details and vice versa.
    if (array_key_exists('pay_gcash_number', $in)) {
        $gnum = digitsOnly((string) $in['pay_gcash_number']);
        if ($gnum !== '' && !isPhMobile($gnum)) {
            jsonOut(['ok' => false, 'error' => PH_MOBILE_MSG], 422);
        }
        setSetting('pay_gcash_number', $gnum);
    }
    if (array_key_exists('pay_gcash_name', $in)) {
        $gname = trim((string) $in['pay_gcash_name']);
        if (mb_strlen($gname) > 100) {
            jsonOut(['ok' => false, 'error' => 'Account name is too long.'], 422);
        }
        setSetting('pay_gcash_name', $gname);
    }
    if (array_key_exists('pay_bank_details', $in)) {
        setSetting('pay_bank_details', mb_substr(trim((string) $in['pay_bank_details']), 0, 255));
    }
    logActivity('billing', 'Updated the online payment details', $me);
    jsonOut(['ok' => true]);
}

if ($action === 'save_legal' || $action === 'reset_legal') {
    require_once __DIR__ . '/../includes/legal.php';
    $doc = (string) ($in['doc'] ?? '');
    if (!isset(LEGAL_DOCS[$doc])) {
        jsonOut(['ok' => false, 'error' => 'Unknown document.'], 422);
    }
    if ($action === 'reset_legal') {
        db()->prepare('DELETE FROM legal_documents WHERE doc_key = ?')->execute([$doc]);
        logActivity('records', 'Reset the ' . LEGAL_DOCS[$doc] . ' to the default text', $me);
    } else {
        $body = preg_replace('/\R/u', "\n", (string) ($in['body'] ?? ''));
        if (trim($body) === '') {
            jsonOut(['ok' => false, 'error' => 'The text cannot be empty.'], 422);
        }
        if (strlen($body) > 200000) {
            jsonOut(['ok' => false, 'error' => 'The text is too long.'], 422);
        }
        db()->prepare(
            'INSERT INTO legal_documents (doc_key, body, updated_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE body = VALUES(body), updated_by = VALUES(updated_by), updated_at = NOW()'
        )->execute([$doc, $body, (int) $me['user_id']]);
        logActivity('records', 'Updated the ' . LEGAL_DOCS[$doc], $me);
    }
    $d = legalDoc($doc);
    jsonOut(['ok' => true, 'doc' => ['body' => $d['body'], 'custom' => $d['custom'], 'updated_at' => $d['updated_at']]]);
}

/* Admin-added payment methods (migration 078). */
if ($action === 'save_payment_method') {
    $id   = (int) ($in['method_id'] ?? 0);
    $name = trim((string) ($in['name'] ?? ''));
    $acn  = trim((string) ($in['account_name'] ?? ''));
    $acno = trim((string) ($in['account_number'] ?? ''));
    if ($name === '' || mb_strlen($name) > 60) {
        jsonOut(['ok' => false, 'error' => 'Enter a payment method name (up to 60 characters).'], 422);
    }
    if (in_array(mb_strtolower($name), ['gcash', 'bank transfer', 'bank', 'cash'], true)) {
        jsonOut(['ok' => false, 'error' => 'GCash and Bank Transfer are already set up above.'], 422);
    }
    if (mb_strlen($acn) > 100 || mb_strlen($acno) > 60) {
        jsonOut(['ok' => false, 'error' => 'Account name or number is too long.'], 422);
    }
    $dup = db()->prepare('SELECT 1 FROM payment_methods WHERE LOWER(name) = LOWER(?) AND method_id <> ?');
    $dup->execute([$name, $id]);
    if ($dup->fetchColumn()) {
        jsonOut(['ok' => false, 'error' => 'A payment method with that name already exists.'], 422);
    }
    if ($id) {
        db()->prepare('UPDATE payment_methods SET name = ?, account_name = ?, account_number = ? WHERE method_id = ?')
            ->execute([$name, $acn !== '' ? $acn : null, $acno !== '' ? $acno : null, $id]);
    } else {
        db()->prepare('INSERT INTO payment_methods (name, account_name, account_number) VALUES (?, ?, ?)')
            ->execute([$name, $acn !== '' ? $acn : null, $acno !== '' ? $acno : null]);
        $id = (int) db()->lastInsertId();
    }
    logActivity('billing', 'Saved the payment method "' . $name . '"', $me);
    jsonOut(['ok' => true, 'method_id' => $id]);
}
if ($action === 'toggle_payment_method' || $action === 'delete_payment_method') {
    $id = (int) ($in['method_id'] ?? 0);
    $st = db()->prepare('SELECT * FROM payment_methods WHERE method_id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        jsonOut(['ok' => false, 'error' => 'Payment method not found.'], 404);
    }
    if ($action === 'toggle_payment_method') {
        $on = $row['is_active'] ? 0 : 1;
        db()->prepare('UPDATE payment_methods SET is_active = ? WHERE method_id = ?')->execute([$on, $id]);
        logActivity('billing', ($on ? 'Turned on' : 'Turned off') . ' the payment method "' . $row['name'] . '"', $me);
        jsonOut(['ok' => true, 'is_active' => $on]);
    }
    // A method already used on a booking is kept (turn it off instead) so its history still reads right.
    $used = db()->prepare('SELECT 1 FROM payments WHERE reservation_method = ? LIMIT 1');
    $used->execute(['m' . $id]);
    if ($used->fetchColumn()) {
        jsonOut(['ok' => false, 'error' => 'This method was already used for a booking. Turn it off instead.'], 422);
    }
    if (!empty($row['qr_image']) && is_file(__DIR__ . '/../uploads/payment/' . $row['qr_image'])) {
        @unlink(__DIR__ . '/../uploads/payment/' . $row['qr_image']);
    }
    db()->prepare('DELETE FROM payment_methods WHERE method_id = ?')->execute([$id]);
    logActivity('billing', 'Deleted the payment method "' . $row['name'] . '"', $me);
    jsonOut(['ok' => true]);
}

if ($action === 'clear_payment_qr') {
    $which = (string) ($in['method'] ?? '');
    if (preg_match('/^m(\d+)$/', $which, $mm)) {
        $st = db()->prepare('SELECT qr_image FROM payment_methods WHERE method_id = ?');
        $st->execute([(int) $mm[1]]);
        $old = (string) ($st->fetchColumn() ?: '');
        if ($old !== '' && is_file(__DIR__ . '/../uploads/payment/' . $old)) {
            @unlink(__DIR__ . '/../uploads/payment/' . $old);
        }
        db()->prepare('UPDATE payment_methods SET qr_image = NULL WHERE method_id = ?')->execute([(int) $mm[1]]);
        jsonOut(['ok' => true]);
    }
    if (!in_array($which, ['gcash', 'bank'], true)) {
        jsonOut(['ok' => false, 'error' => 'Unknown payment method.'], 422);
    }
    $old = setting('pay_' . $which . '_qr', '');
    if ($old !== '' && is_file(__DIR__ . '/../uploads/payment/' . $old)) {
        @unlink(__DIR__ . '/../uploads/payment/' . $old);
    }
    setSetting('pay_' . $which . '_qr', '');
    jsonOut(['ok' => true]);
}

jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
