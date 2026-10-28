<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

// The activity / audit trail is visible to the Admin / Clinic Owner only.
$me     = apiAuth(['admin']);
$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || $action !== 'list') {
    jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
}

$role     = trim((string) ($_GET['role'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$from     = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : null;
$to       = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : null;
if ($from && $to && $from > $to) { [$from, $to] = [$to, $from]; }   // reversed range
$q        = trim((string) ($_GET['q'] ?? ''));
$limit    = min(max((int) ($_GET['limit'] ?? 300), 1), 1000);

$where  = ['1=1'];
$params = [];

if ($role !== '') {
    $where[] = 'role = ?';
    $params[] = $role;
}
if (in_array($category, ACTIVITY_CATEGORIES, true)) {
    $where[] = 'category = ?';
    $params[] = $category;
}
if ($from) {
    $where[] = 'created_at >= ?';
    $params[] = $from . ' 00:00:00';
}
if ($to) {
    $where[] = 'created_at <= ?';
    $params[] = $to . ' 23:59:59';
}
if ($q !== '') {
    $where[] = '(username LIKE ? OR action LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

$sql = 'SELECT log_id, created_at, user_id, username, role, category, action, ip
        FROM activity_log
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY created_at DESC, log_id DESC
        LIMIT ' . $limit;
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Target entity for the detail pop-up: a log line that names "Appointment #N"
// or "Invoice #N" is linked back to that appointment and its patient (two
// batched lookups, no per-row queries).
$apptIds = [];
$invIds  = [];
foreach ($rows as $r) {
    if (preg_match('/Appointment #(\d+)/', $r['action'], $m)) $apptIds[(int) $m[1]] = true;
    if (preg_match('/Invoice #(\d+)/', $r['action'], $m)) $invIds[(int) $m[1]] = true;
}
$apptInfo = [];
$invAppt  = [];
$invOr    = [];
if ($invIds) {
    $ids = array_keys($invIds);
    $st = db()->prepare('SELECT payment_id, appointment_id, or_number FROM payments WHERE payment_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $st->execute($ids);
    foreach ($st->fetchAll() as $p) {
        $invAppt[(int) $p['payment_id']] = (int) $p['appointment_id'];
        $invOr[(int) $p['payment_id']]   = (string) ($p['or_number'] ?? '');
        $apptIds[(int) $p['appointment_id']] = true;
    }
}
if ($apptIds) {
    $ids = array_keys($apptIds);
    $st = db()->prepare('SELECT appointment_id, patient_name, status, scheduled_date, time_slot FROM appointments WHERE appointment_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $st->execute($ids);
    foreach ($st->fetchAll() as $a) $apptInfo[(int) $a['appointment_id']] = $a;
}

foreach ($rows as &$r) {
    $r['role_label'] = $r['role'] !== '' ? roleLabel($r['role']) : 'N/A';
    $apptId = preg_match('/Appointment #(\d+)/', $r['action'], $m) ? (int) $m[1] : 0;
    $invId  = preg_match('/Invoice #(\d+)/', $r['action'], $m) ? (int) $m[1] : 0;
    if (!$apptId && $invId && isset($invAppt[$invId])) $apptId = $invAppt[$invId];
    $a = $apptInfo[$apptId] ?? null;
    // Older lines named the internal payment number ("Invoice #555"); show the
    // cashier's OR number instead (2026-10-22). The stored row is not changed.
    $orNo = preg_match('/OR No\. (\d+)/', $r['action'], $mo) ? $mo[1] : ($invOr[$invId] ?? '');
    if ($invId) {
        $r['action'] = preg_replace(
            ['/, Invoice #\d+\)/', '/taken off Invoice #\d+/', '/ on Invoice #\d+/', '/Invoice #\d+/'],
            [')', 'taken off the bill', $orNo !== '' ? ' on OR No. ' . $orNo : '', $orNo !== '' ? 'OR No. ' . $orNo : 'the bill'],
            $r['action']
        );
    }
    $r['target'] = [
        'appointment_id'  => $a ? $apptId : null,   // null when the appointment was since deleted
        'appointment_ref' => $apptId ?: null,
        'or_no'           => $orNo !== '' ? $orNo : null,
        'patient_name'    => $a['patient_name'] ?? null,
        'appt_status'     => $a['status'] ?? null,
        'scheduled_date'  => $a['scheduled_date'] ?? null,
        'time_slot'       => $a['time_slot'] ?? null,
    ];
}
unset($r);

// Role filter dropdown: the standard roles always present (so staff can always
// one-click "Patient" to see patient-initiated actions), plus any other role
// value that has turned up in the log.
$present = db()->query(
    "SELECT DISTINCT role FROM activity_log WHERE role <> ''"
)->fetchAll(PDO::FETCH_COLUMN);
$roleValues = array_values(array_unique(array_merge(
    ['admin', 'dentist', 'receptionist', 'dental_assistant', 'cashier', 'patient'],
    $present
)));

jsonOut([
    'ok'         => true,
    'rows'       => $rows,
    'count'      => count($rows),
    'limit'      => $limit,
    'roles'      => array_map(static fn ($x) => ['value' => $x, 'label' => roleLabel($x)], $roleValues),
    'categories' => ACTIVITY_CATEGORIES,
]);
