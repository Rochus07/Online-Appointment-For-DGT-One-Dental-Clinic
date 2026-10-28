<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$me     = apiAuth(['admin', 'cashier']);
$action = $_GET['action'] ?? '';

// The cashier may read Revenue Reports only; History Logs / CSV / deletes stay admin.
if ($me['role'] !== 'admin' && $action !== 'revenue') {
    jsonOut(['ok' => false, 'error' => 'Forbidden'], 403);
}

/* ---------------------------------------------------------------- Revenue report */
if ($action === 'revenue') {
    [$from, $to] = reportWindow();
    // The reservation fee is its own row on the day it was paid; the rest of
    // the bill is a row on the settlement day (revenueLinesSql(), 2026-10-21).
    $sql = "SELECT rl.payment_id, a.appointment_id, a.patient_name, rl.kind,
                   d.full_name AS specialist,
                   CONCAT(CASE rl.kind WHEN 'reservation' THEN 'Reservation fee: ' WHEN 'refund' THEN 'Excess reservation returned: ' ELSE '' END,
                          COALESCE(COALESCE(sv.service_name,
                            (SELECT GROUP_CONCAT(s2.service_name ORDER BY s2.service_name SEPARATOR ', ')
                             FROM appointment_services aps JOIN services s2 ON s2.service_id = aps.service_id
                             WHERE aps.appointment_id = a.appointment_id)), 'N/A')) AS service,
                   rl.rev_date AS date_of_service,
                   DATE_FORMAT(rl.rev_at, '%h:%i %p') AS time_of_service,
                   rl.amount, rl.method AS payment_mode, rl.method_label
            FROM " . revenueLinesSql() . " rl
            JOIN appointments a ON a.appointment_id = rl.appointment_id
            LEFT JOIN users d ON d.user_id = a.dentist_id
            LEFT JOIN services sv ON sv.service_id = a.service_id
            WHERE rl.amount <> 0 AND rl.rev_date BETWEEN ? AND ?
            ORDER BY rl.rev_at DESC, rl.payment_id DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll();
    $total = array_sum(array_map(static fn ($r) => (float) $r['amount'], $rows));

    jsonOut(['ok' => true, 'from' => $from, 'to' => $to, 'rows' => $rows, 'total' => $total]);
}

/* ---------------------------------------------------------------- History logs */

/** Rows for the admin History Logs table, filtered by status tab + date range. */
function historyLogRows(): array
{
    $valid  = ['pending', 'approved', 'completed', 'cancelled', 'rescheduled', 'payment_declined', 'no_show'];
    $status = $_GET['status'] ?? 'all';
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : null;
    $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : null;
    if ($from && $to && $from > $to) { [$from, $to] = [$to, $from]; }   // reversed range

    $where  = ['1=1'];
    $params = [];
    if (in_array($status, $valid, true)) {
        $where[] = 'a.status = ?';
        $params[] = $status;
    }
    if ($from) {
        $where[] = 'a.scheduled_date >= ?';
        $params[] = $from;
    }
    if ($to) {
        $where[] = 'a.scheduled_date <= ?';
        $params[] = $to;
    }

    $stmt = db()->prepare(
        "SELECT a.appointment_id, a.patient_name, a.status, a.scheduled_date, a.time_slot,
                a.created_at, " . APPT_AGE_SQL . " AS age, a.booking_type,
                u.contact_number AS phone,
                d.full_name AS specialist,
                COALESCE(sv.service_name,
                         (SELECT GROUP_CONCAT(s2.service_name ORDER BY s2.service_name SEPARATOR ', ')
                          FROM appointment_services aps JOIN services s2 ON s2.service_id = aps.service_id
                          WHERE aps.appointment_id = a.appointment_id)) AS service
         FROM appointments a
         LEFT JOIN users u ON u.user_id = a.patient_id
         LEFT JOIN users d ON d.user_id = a.dentist_id
         LEFT JOIN services sv ON sv.service_id = a.service_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY a.created_at DESC
         LIMIT 1000"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

if ($action === 'logs') {
    $counts = db()->query(
        "SELECT
            SUM(status = 'pending')     AS pending,
            SUM(status = 'approved')    AS approved,
            SUM(status = 'completed')   AS completed,
            SUM(status = 'cancelled')   AS cancelled,
            SUM(status = 'rescheduled') AS rescheduled,
            SUM(status = 'payment_declined') AS payment_declined,
            SUM(status = 'no_show')     AS no_show
         FROM appointments"
    )->fetch();

    jsonOut(['ok' => true, 'rows' => historyLogRows(), 'counts' => $counts]);
}

if ($action === 'logs_csv') {
    $rows = historyLogRows();
    header_remove('Content-Type');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="appointments_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#', 'Name', 'Age', 'Phone', 'Specialist', 'Service', 'Type', 'Date', 'Time', 'Status']);
    foreach ($rows as $i => $r) {
        fputcsv($out, [
            $i + 1, $r['patient_name'], $r['age'], $r['phone'],
            $r['specialist'], $r['service'],
            ($r['booking_type'] === 'walk_in' ? 'Walk-in' : 'Online'),
            $r['scheduled_date'], fmtTime($r['time_slot']),
            ucfirst((string) $r['status']),
        ]);
    }
    fclose($out);
    exit;
}

if ($action === 'delete_logs') {
    $in  = requestBody();
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($in['ids'] ?? [])))));
    if (!$ids) {
        jsonOut(['ok' => false, 'error' => 'No rows were selected.'], 422);
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("DELETE FROM appointments WHERE appointment_id IN ($ph)");
    $stmt->execute($ids);
    jsonOut(['ok' => true, 'deleted' => $stmt->rowCount()]);
}

jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
