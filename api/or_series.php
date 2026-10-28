<?php
declare(strict_types=1);

/**
 * OR / Invoice Setup (migration 084): the cashier's block of pre-printed Official
 * Receipt numbers. One series is active at a time; every payment on the Billing
 * screen takes its next number (orIssueNext() in _bootstrap.php).
 *
 *   GET  status   current series + next OR, today's receipts, past series
 *   POST create   {start, end}  start a new series (closes the current one)
 *   POST realign  {next}        make the next OR match the receipt in the printer
 *   POST reset    close the current series
 */
require_once __DIR__ . '/_bootstrap.php';

$me     = apiAuth(['cashier']);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$in     = $method === 'POST' ? requestBody() : [];

const OR_MAX_BLOCK = 10000;   // receipts in one series

/** How many OR numbers between $from and $to were already issued. */
function orIssuedInRange(int $from, int $to): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM or_issued WHERE CAST(or_no AS UNSIGNED) BETWEEN ? AND ?');
    $st->execute([$from, $to]);
    return (int) $st->fetchColumn();
}

if ($method === 'GET' && $action === 'status') {
    $today = db()->query(
        "SELECT o.or_no, o.day_seq, o.amount, o.voided_at, DATE_FORMAT(o.issued_at, '%h:%i %p') AS time,
                a.appointment_id, a.patient_name, pay.payment_status
         FROM or_issued o
         LEFT JOIN payments pay ON pay.payment_id = o.payment_id
         LEFT JOIN appointments a ON a.appointment_id = o.appointment_id
         WHERE DATE(o.issued_at) = CURDATE()
         ORDER BY o.day_seq DESC"
    )->fetchAll();
    $history = [];
    foreach (db()->query(
        "SELECT s.*, (SELECT COUNT(*) FROM or_issued o WHERE o.series_id = s.series_id) AS used
         FROM or_series s ORDER BY s.series_id DESC LIMIT 500"
    )->fetchAll() as $s) {
        $d = (int) $s['digits'];
        $history[] = [
            'start'      => orFormat((int) $s['start_no'], $d),
            'end'        => orFormat((int) $s['end_no'], $d),
            'total'      => (int) $s['end_no'] - (int) $s['start_no'] + 1,
            'used'       => (int) $s['used'],
            'status'     => $s['status'],
            'created_at' => $s['created_at'],
            'closed_at'  => $s['closed_at'],
        ];
    }
    jsonOut(['ok' => true, 'or' => orStatus(), 'today' => $today, 'history' => $history]);
}

if ($method !== 'POST') {
    jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
}

if ($action === 'create') {
    $startRaw = trim((string) ($in['start'] ?? ''));
    $endRaw   = trim((string) ($in['end'] ?? ''));
    if (!preg_match('/^\d{1,10}$/', $startRaw) || !preg_match('/^\d{1,10}$/', $endRaw)) {
        jsonOut(['ok' => false, 'error' => 'Enter the first and last receipt numbers using digits only.'], 422);
    }
    $start = (int) $startRaw;
    $end   = (int) $endRaw;
    if ($start < 1) {
        jsonOut(['ok' => false, 'error' => 'The first receipt number must be 1 or higher.'], 422);
    }
    if ($end < $start) {
        jsonOut(['ok' => false, 'error' => 'The last receipt number must be the same as or higher than the first.'], 422);
    }
    if ($end - $start + 1 > OR_MAX_BLOCK) {
        jsonOut(['ok' => false, 'error' => 'One series can hold at most ' . OR_MAX_BLOCK . ' receipts.'], 422);
    }
    $used = orIssuedInRange($start, $end);
    $vst = db()->prepare('SELECT COUNT(*) FROM or_voids v WHERE CAST(v.or_no AS UNSIGNED) BETWEEN ? AND ? AND v.or_id IS NULL');   // blank sheets (issued ones are already counted)
    $vst->execute([$start, $end]);
    $used += (int) $vst->fetchColumn();
    if ($used > 0) {
        jsonOut(['ok' => false, 'error' => $used . ' receipt number(s) in this range were already issued or voided. Enter a range that has not been used.'], 422);
    }
    $digits = max(strlen($startRaw), strlen($endRaw), strlen((string) $end));

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $old = orActiveSeries();
        if ($old) {
            $pdo->prepare("UPDATE or_series SET status = 'closed', closed_at = NOW() WHERE series_id = ?")
                ->execute([(int) $old['series_id']]);
        }
        $pdo->prepare("INSERT INTO or_series (start_no, end_no, next_no, digits, status, created_by) VALUES (?, ?, ?, ?, 'active', ?)")
            ->execute([$start, $end, $start, $digits, (int) $me['user_id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonOut(['ok' => false, 'error' => 'Could not save the OR series.'], 500);
    }
    $msg = 'Set up OR series ' . orFormat($start, $digits) . ' to ' . orFormat($end, $digits);
    if (!empty($old)) {
        $od = (int) $old['digits'];
        $msg .= ' (closed ' . orFormat((int) $old['start_no'], $od) . ' to ' . orFormat((int) $old['end_no'], $od) . ')';
    }
    logActivity('billing', $msg, $me);
    jsonOut(['ok' => true, 'or' => orStatus()]);
}

if ($action === 'realign') {
    $s = orActiveSeries();
    if (!$s) {
        jsonOut(['ok' => false, 'error' => 'There is no active OR series.'], 422);
    }
    $raw = trim((string) ($in['next'] ?? ''));
    if (!preg_match('/^\d{1,10}$/', $raw)) {
        jsonOut(['ok' => false, 'error' => 'Enter the receipt number using digits only.'], 422);
    }
    $next = (int) $raw;
    $d = (int) $s['digits'];
    if ($next < (int) $s['start_no'] || $next > (int) $s['end_no']) {
        jsonOut(['ok' => false, 'error' => 'That number is outside the current series (' . orFormat((int) $s['start_no'], $d) . ' to ' . orFormat((int) $s['end_no'], $d) . '). Set up a new series instead.'], 422);
    }
    if (orIssuedInRange($next, $next) > 0) {
        jsonOut(['ok' => false, 'error' => 'OR No. ' . orFormat($next, $d) . ' was already issued.'], 422);
    }
    if (orIsVoided($next)) {
        jsonOut(['ok' => false, 'error' => 'OR No. ' . orFormat($next, $d) . ' was voided. Enter the next fresh receipt number.'], 422);
    }
    $was = (int) $s['next_no'];
    db()->prepare('UPDATE or_series SET next_no = ? WHERE series_id = ?')->execute([$next, (int) $s['series_id']]);
    logActivity('billing', 'Re-aligned the next OR number from ' . orFormat($was, $d) . ' to ' . orFormat($next, $d), $me);
    jsonOut(['ok' => true, 'or' => orStatus()]);
}

if ($action === 'reset') {
    $s = orActiveSeries();
    if (!$s) {
        jsonOut(['ok' => false, 'error' => 'There is no active OR series.'], 422);
    }
    db()->prepare("UPDATE or_series SET status = 'closed', closed_at = NOW() WHERE series_id = ?")
        ->execute([(int) $s['series_id']]);
    $d = (int) $s['digits'];
    logActivity('billing', 'Reset the OR series ' . orFormat((int) $s['start_no'], $d) . ' to ' . orFormat((int) $s['end_no'], $d)
        . ' (next unused was ' . orFormat((int) $s['next_no'], $d) . ')', $me);
    jsonOut(['ok' => true, 'or' => orStatus()]);
}

jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
