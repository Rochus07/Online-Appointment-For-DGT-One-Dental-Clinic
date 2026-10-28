<?php
declare(strict_types=1);

/**
 * Voided Receipts (migration 085), admin only. The cashier brings the spoiled
 * paper receipt to the admin, who looks up its OR number and voids it.
 *
 *   GET  lookup&or=000311   what that receipt number is (issued / blank / voided)
 *   GET  list               voided receipts history
 *   POST void {or_no, reason_code, reason_text}
 *
 * Voiding an issued receipt takes that payment back off the bill (the bill
 * returns to the cashier's Transaction queue to be paid again on a fresh
 * receipt), so its amount drops out of every revenue figure. A blank sheet
 * that was never issued is just recorded, so the number can never be issued.
 */
require_once __DIR__ . '/_bootstrap.php';

$me     = apiAuth(['admin']);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

const VOID_REASONS = [
    'misprint'   => 'Wrong Printing / Paper Jam',
    'mismatch'   => 'OR Number Mismatch',
    'damaged'    => 'Damaged / Chewed-up Receipt',
    'cancelled'  => 'Customer Transaction Cancelled',
    'other'      => 'Other',
];

/** Digits of a typed OR number ("OR No. 000311" -> 311), or 0. */
function voidOrInt(string $raw): int
{
    $d = preg_replace('/\D/', '', $raw);
    return ($d === '' || strlen($d) > 10) ? 0 : (int) $d;
}

/** Everything the admin needs to see about one receipt number. */
function voidLookup(int $n): array
{
    $v = db()->prepare('SELECT * FROM or_voids WHERE CAST(or_no AS UNSIGNED) = ? LIMIT 1');
    $v->execute([$n]);
    if ($row = $v->fetch()) {
        return ['state' => 'voided', 'or_no' => $row['or_no'], 'void' => $row];
    }
    $st = db()->prepare(
        "SELECT o.*, a.patient_name, pay.payment_status, u.full_name AS cashier_name
         FROM or_issued o
         LEFT JOIN appointments a ON a.appointment_id = o.appointment_id
         LEFT JOIN payments pay ON pay.payment_id = o.payment_id
         LEFT JOIN users u ON u.user_id = o.issued_by
         WHERE CAST(o.or_no AS UNSIGNED) = ? LIMIT 1"
    );
    $st->execute([$n]);
    if ($o = $st->fetch()) {
        $out = ['state' => 'issued', 'or_no' => $o['or_no'], 'issued' => $o, 'can_void' => true, 'block' => ''];
        if ($o['payment_id']) {
            // Only the latest receipt of a bill can be voided: an earlier
            // installment's money is already part of what came after it.
            $lt = db()->prepare('SELECT or_id FROM or_issued WHERE payment_id = ? AND voided_at IS NULL ORDER BY or_id DESC LIMIT 1');
            $lt->execute([(int) $o['payment_id']]);
            if ((int) $lt->fetchColumn() !== (int) $o['or_id']) {
                $out['can_void'] = false;
                $out['block'] = 'A later receipt was already issued for this bill. Void the latest receipt of the bill first.';
            }
        }
        return $out;
    }
    $ss = db()->prepare('SELECT * FROM or_series WHERE ? BETWEEN start_no AND end_no ORDER BY series_id DESC LIMIT 1');
    $ss->execute([$n]);
    if ($s = $ss->fetch()) {
        $d = (int) $s['digits'];
        $isNext = $s['status'] === 'active' && (int) $s['next_no'] === $n;
        return ['state' => 'blank', 'or_no' => orFormat($n, $d), 'series' => [
            'start' => orFormat((int) $s['start_no'], $d), 'end' => orFormat((int) $s['end_no'], $d),
            'status' => $s['status'], 'series_id' => (int) $s['series_id'],
        ], 'is_next' => $isNext, 'can_void' => true, 'block' => ''];
    }
    return ['state' => 'unknown', 'or_no' => (string) $n, 'can_void' => false,
            'block' => 'This number is not in any OR series set up by the cashier.'];
}

if ($method === 'GET' && $action === 'lookup') {
    $n = voidOrInt((string) ($_GET['or'] ?? ''));
    if ($n < 1) {
        jsonOut(['ok' => false, 'error' => 'Enter the OR number printed on the receipt.'], 422);
    }
    jsonOut(['ok' => true, 'receipt' => voidLookup($n), 'reasons' => VOID_REASONS]);
}

if ($method === 'GET' && $action === 'list') {
    $rows = db()->query(
        "SELECT void_id, or_no, payment_id, appointment_id, patient_name, amount, day_seq, issued_at,
                cashier_name, voided_by_name, reason_code, reason, voided_at
         FROM or_voids ORDER BY voided_at DESC, void_id DESC LIMIT 1000"
    )->fetchAll();
    jsonOut(['ok' => true, 'rows' => $rows, 'reasons' => VOID_REASONS]);
}

if ($method !== 'POST' || $action !== 'void') {
    jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
}

$in   = requestBody();
$n    = voidOrInt((string) ($in['or_no'] ?? ''));
$code = (string) ($in['reason_code'] ?? '');
$text = trim((string) ($in['reason_text'] ?? ''));
if ($n < 1) {
    jsonOut(['ok' => false, 'error' => 'Enter the OR number printed on the receipt.'], 422);
}
if (!isset(VOID_REASONS[$code])) {
    jsonOut(['ok' => false, 'error' => 'Choose a reason for voiding.'], 422);
}
if ($code === 'other' && mb_strlen($text) < 3) {
    jsonOut(['ok' => false, 'error' => 'Type the reason for voiding.'], 422);
}
$text   = mb_substr($text, 0, 200);
$reason = $code === 'other' ? $text : VOID_REASONS[$code] . ($text !== '' ? ': ' . $text : '');

$pdo = db();
$pdo->beginTransaction();
try {
    $r = voidLookup($n);
    if ($r['state'] === 'voided') {
        $pdo->rollBack();
        jsonOut(['ok' => false, 'error' => 'OR No. ' . $r['or_no'] . ' is already voided.'], 409);
    }
    if (!$r['can_void']) {
        $pdo->rollBack();
        jsonOut(['ok' => false, 'error' => $r['block']], 422);
    }
    $adminName = (string) ($me['full_name'] ?: $me['username']);

    if ($r['state'] === 'blank') {
        $pdo->prepare('INSERT INTO or_voids (or_no, series_id, reason_code, reason, voided_by, voided_by_name, voided_at)
                       VALUES (?, ?, ?, ?, ?, ?, NOW())')
            ->execute([$r['or_no'], $r['series']['series_id'], $code, $reason, (int) $me['user_id'], $adminName]);
        $pdo->commit();
        logActivity('billing', 'Voided blank receipt OR No. ' . $r['or_no'] . ' (never issued): ' . $reason, $me);
        jsonOut(['ok' => true, 'message' => 'OR No. ' . $r['or_no'] . ' is voided.'
            . ($r['is_next'] ? ' The cashier must now re-align to the next fresh receipt.' : ''), 'receipt' => voidLookup($n)]);
    }

    // An issued receipt: take its payment back off the bill.
    $o  = $r['issued'];
    $pid = (int) ($o['payment_id'] ?? 0);
    $pdo->prepare('UPDATE or_issued SET voided_at = NOW() WHERE or_id = ?')->execute([(int) $o['or_id']]);
    $pdo->prepare('INSERT INTO or_voids (or_no, series_id, or_id, payment_id, appointment_id, patient_name, amount, day_seq, issued_at,
                                         cashier_id, cashier_name, reason_code, reason, voided_by, voided_by_name, voided_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$o['or_no'], (int) $o['series_id'], (int) $o['or_id'], $pid ?: null, $o['appointment_id'] ?: null,
                   $o['patient_name'], (float) $o['amount'], (int) $o['day_seq'], $o['issued_at'],
                   $o['issued_by'] ?: null, $o['cashier_name'], $code, $reason, (int) $me['user_id'], $adminName]);

    if ($pid) {
        $ps = $pdo->prepare('SELECT * FROM payments WHERE payment_id = ? FOR UPDATE');
        $ps->execute([$pid]);
        $p = $ps->fetch();
        $left = $pdo->prepare('SELECT or_no FROM or_issued WHERE payment_id = ? AND voided_at IS NULL ORDER BY or_id DESC');
        $left->execute([$pid]);
        $remaining = $left->fetchAll(PDO::FETCH_COLUMN);
        if (!$remaining) {
            // That was the bill's only receipt: nothing was collected any more.
            $cash = $gcash = $bank = 0.0;
        } elseif ($o['pay_cash'] !== null) {
            $cash  = max(round((float) $p['amount_cash']  - (float) $o['pay_cash'], 2), 0);
            $gcash = max(round((float) $p['amount_gcash'] - (float) $o['pay_gcash'], 2), 0);
            $bank  = max(round((float) $p['amount_bank']  - (float) $o['pay_bank'], 2), 0);
        } else {
            // Receipt issued before migration 085 (no split saved): undo it
            // from the column its payment mode used.
            $amt = (float) $o['amount'];
            $cash = (float) $p['amount_cash']; $gcash = (float) $p['amount_gcash']; $bank = (float) $p['amount_bank'];
            if ($p['payment_mode'] === 'gcash')    { $gcash = max(round($gcash - $amt, 2), 0); }
            elseif ($p['payment_mode'] === 'bank') { $bank  = max(round($bank - $amt, 2), 0); }
            else                                   { $cash  = max(round($cash - $amt, 2), 0); }
        }
        $pdo->prepare('UPDATE payments SET amount_cash = ?, amount_gcash = ?, amount_bank = ?, or_number = ?,
                              payment_date = IF(?, payment_date, NULL),
                              received_amount = IF(?, received_amount, 0), change_amount = IF(?, change_amount, 0),
                              reservation_refund = 0,
                              paid_at = NULL
                       WHERE payment_id = ?')
            ->execute([$cash, $gcash, $bank, $remaining[0] ?? null,
                       $remaining ? 1 : 0, $remaining ? 1 : 0, $remaining ? 1 : 0, $pid]);
        recomputePayment($pid);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonOut(['ok' => false, 'error' => 'Could not void the receipt.'], 500);
}

logActivity('billing', 'Voided OR No. ' . $o['or_no'] . ' (' . peso((float) $o['amount']) . ', ' . ($o['patient_name'] ?: 'no patient')
    . ', transaction #' . (int) $o['day_seq'] . ' on ' . date('M j, Y', strtotime((string) $o['issued_at'])) . '): ' . $reason
    . ($pid ? '; the payment was taken off the bill (Appointment #' . (int) $o['appointment_id'] . ') and the bill is back in the cashier queue' : ''), $me);
jsonOut(['ok' => true, 'message' => 'OR No. ' . $o['or_no'] . ' is voided.'
    . ($pid ? ' The bill is back in the cashier\'s Transaction queue to be paid again on a fresh receipt.' : ''),
    'receipt' => voidLookup($n)]);
