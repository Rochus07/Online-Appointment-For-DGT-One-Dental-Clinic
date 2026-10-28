<?php
/**
 * Common bootstrap for every JSON API endpoint.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

/** Shortcut: current DB handle. */
function db(): PDO
{
    return getDB();
}

/** Read one appointment with joined patient / dentist / payment / services. */
/**
 * An appointment's patient age, computed live from the treated person's Date of
 * Birth (the booking account itself for a self-booking). appointments.patient_age
 * is only a fallback for an old "for someone else" booking with no linked record.
 */
/**
 * Tooth chart auto-update (2026-10-17): logging one of these services with
 * teeth picked marks those teeth on the patient's chart (patient_records.
 * tooth_chart). Extraction -> missing, Resto -> resto, Root Canal -> rct. Matched on
 * the service name; the same rule lives in app.js toothEffect().
 */
function toothChartEffect(string $serviceName): ?string
{
    if (preg_match('/extraction/i', $serviceName)) {
        return 'extract'; // pulled at the clinic keeps For Extraction (Missing = already gone when first seen)
    }
    if (preg_match('/root canal|\(rct\)/i', $serviceName)) {
        return 'rct';
    }
    if (preg_match('/^resto\b/i', $serviceName)) {
        return 'resto';
    }
    return null;
}

/** FDI tooth number (permanent 11-48, primary 51-85). */
function isFdiTooth(string $t): bool
{
    return (bool) preg_match('/^(1[1-8]|2[1-8]|3[1-8]|4[1-8]|5[1-5]|6[1-5]|7[1-5]|8[1-5])$/', $t);
}

/**
 * Per-tooth dental charting (2026-10-26, migration 091). Tooth conditions on
 * patient_records.tooth_chart ('' / missing key = healthy); quick tags + an
 * optional note per tooth per visit in tooth_records. Same lists in app.js
 * (TOOTH_STATUS / TOOTH_TAGS).
 */
const TOOTH_STATES = ['caries', 'extract', 'missing', 'resto', 'rct'];
const TOOTH_TAGS = [
    // Symptoms
    'Sensitive to cold', 'Sensitive to sweets', 'Pain when biting', 'Pain at night', 'Gum swelling', 'Bleeding gums',
    // Findings
    'Deep cavity', 'Fractured / chipped', 'Loose / mobile', 'Plaque / tartar', 'Stain / discoloration', 'Gum recession', 'Abscess / pus', 'Old filling worn', 'Temporary filling', 'Root stump', 'Impacted', 'Wisdom tooth erupting', 'Bracket loose', 'Wire poking',
    // Recommended
    'Needs cleaning', 'Needs filling (Resto)', 'Needs root canal', 'Needs extraction', 'Needs X-ray', 'Needs bridge', 'Needs denture', 'Needs veneer', 'Needs whitening', 'Needs gum treatment', 'Needs surgery', 'Braces adjustment', 'Monitor next visit',
];

/** Every tooth record of a patient, newest first, with who saved it. */
function toothRecordsFor(int $pid): array
{
    try {
        $st = db()->prepare(
            "SELECT tr.record_id, tr.tooth, tr.status, tr.tags, tr.note, tr.appointment_id, tr.visit_date,
                    tr.updated_at, u.full_name AS by_name, u.role AS by_role
             FROM tooth_records tr LEFT JOIN users u ON u.user_id = tr.recorded_by
             WHERE tr.patient_id = ? ORDER BY tr.visit_date DESC, tr.updated_at DESC, tr.record_id DESC"
        );
        $st->execute([$pid]);
    } catch (Throwable $e) {
        return [];
    }
    return array_map(static function (array $r): array {
        $r['tooth'] = (int) $r['tooth'];
        $r['tags'] = $r['tags'] !== null && $r['tags'] !== '' ? explode('|', $r['tags']) : [];
        return $r;
    }, $st->fetchAll());
}

/**
 * Saves dentist tooth entries [{tooth, status, tags[], note}] for one visit:
 * updates the status map on patient_records.tooth_chart and keeps one
 * tooth_records row per tooth per visit (re-saving the same visit updates it).
 * Throws InvalidArgumentException on bad input. Returns the number saved.
 */
function saveToothEntries(int $pid, array $entries, ?int $apptId, string $visitDate, int $uid): int
{
    $clean = [];
    foreach ($entries as $e) {
        if (!is_array($e)) continue;
        $t = (string) ($e['tooth'] ?? '');
        if (!isFdiTooth($t)) {
            throw new InvalidArgumentException('Unknown tooth number.');
        }
        $status = (string) ($e['status'] ?? '');
        if ($status !== '' && !in_array($status, TOOTH_STATES, true)) {
            throw new InvalidArgumentException('Unknown condition for tooth ' . $t . '.');
        }
        $tags = [];
        foreach ((array) ($e['tags'] ?? []) as $tag) {
            if (in_array($tag, TOOTH_TAGS, true) && !in_array($tag, $tags, true)) $tags[] = $tag;
        }
        if (mb_strlen(implode('|', $tags)) > 500) {
            throw new InvalidArgumentException('Too many quick tags on tooth ' . $t . '. Please keep the main ones.');
        }
        $note = trim(str_replace(["\r", "\n"], ' ', (string) ($e['note'] ?? '')));
        if (mb_strlen($note) > 500) {
            throw new InvalidArgumentException('The note for tooth ' . $t . ' is too long (500 characters max).');
        }
        $clean[$t] = [$status, $tags, $note];
    }
    if (!$clean) return 0;

    $pdo = db();
    $cs = $pdo->prepare('SELECT tooth_chart FROM patient_records WHERE patient_id = ?');
    $cs->execute([$pid]);
    $raw = $cs->fetchColumn();
    $chart = $raw ? (json_decode((string) $raw, true) ?: []) : [];

    $find = $pdo->prepare($apptId
        ? 'SELECT record_id FROM tooth_records WHERE patient_id = ? AND tooth = ? AND appointment_id = ? LIMIT 1'
        : 'SELECT record_id FROM tooth_records WHERE patient_id = ? AND tooth = ? AND visit_date = ? AND appointment_id IS NULL LIMIT 1');
    $upd = $pdo->prepare('UPDATE tooth_records SET status = ?, tags = ?, note = ?, recorded_by = ? WHERE record_id = ?');
    $ins = $pdo->prepare('INSERT INTO tooth_records (patient_id, tooth, status, tags, note, appointment_id, visit_date, recorded_by)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($clean as $t => [$status, $tags, $note]) {
        if ($status === '') unset($chart[$t]); else $chart[$t] = $status;
        $vals = [$status ?: null, $tags ? implode('|', $tags) : null, $note !== '' ? $note : null];
        $find->execute([$pid, (int) $t, $apptId ?: $visitDate]);
        $rid = $find->fetchColumn();
        if ($rid) {
            $upd->execute([...$vals, $uid, $rid]);
        } else {
            $ins->execute([$pid, (int) $t, ...$vals, $apptId ?: null, $visitDate, $uid]);
        }
    }
    $json = $chart ? json_encode($chart) : null;
    if ($raw === false) {
        $pdo->prepare('INSERT INTO patient_records (patient_id, tooth_chart, updated_by) VALUES (?, ?, ?)')->execute([$pid, $json, $uid]);
    } else {
        $pdo->prepare('UPDATE patient_records SET tooth_chart = ?, updated_by = ? WHERE patient_id = ?')->execute([$json, $uid, $pid]);
    }
    return count($clean);
}

const APPT_AGE_SQL ="COALESCE((SELECT TIMESTAMPDIFF(YEAR, pra.date_of_birth, CURDATE()) FROM patient_records pra
        WHERE pra.patient_id = COALESCE(a.treated_patient_id, IF(a.booking_for = 'self', a.patient_id, NULL))), a.patient_age)";

/**
 * Adds patient_allergies (the treated person's patient_records.allergies, or
 * null when never answered) to each appointment row. Dentist / admin only:
 * callers decide who sees it (2026-10-17).
 */
function attachPatientAllergies(array &$rows): void
{
    $ids = [];
    foreach ($rows as $r) {
        $pid = (int) (($r['treated_patient_id'] ?? 0) ?: ($r['patient_id'] ?? 0));
        if ($pid) $ids[$pid] = true;
    }
    $map = [];
    if ($ids) {
        $in = implode(',', array_map('intval', array_keys($ids)));
        foreach (db()->query("SELECT patient_id, allergies FROM patient_records WHERE patient_id IN ($in)") as $x) {
            $map[(int) $x['patient_id']] = $x['allergies'];
        }
    }
    foreach ($rows as &$r) {
        $pid = (int) (($r['treated_patient_id'] ?? 0) ?: ($r['patient_id'] ?? 0));
        $r['patient_allergies'] = $map[$pid] ?? null;
    }
    unset($r);
}

function appointmentDetail(int $id): ?array
{
    $sql = 'SELECT a.*, ' . APPT_AGE_SQL . ' AS patient_age,
                   p.full_name  AS patient_account,
                   d.full_name  AS dentist_name,
                   d.prc_license AS dentist_prc_license,
                   sv.service_name AS requested_service,
                   pay.payment_id, pay.total_amount, pay.discount,
                   pay.promo_discount_id, pay.promo_discount_label, pay.promo_discount_amount,
                   pay.sc_pwd_discount, pay.sc_pwd_id, pay.sc_pwd_type, pay.sc_tin, pay.doctor_fee, pay.withholding_tax,
                   pay.payment_mode, pay.reservation_fee_paid, pay.or_number,
                   pay.reservation_method, pay.reservation_method_label, pay.reservation_reference, pay.reservation_receipt, pay.receipt_amount_read,
                   pay.amount_cash, pay.amount_gcash, pay.amount_bank,
                   pay.gcash_reference_number, pay.gcash_number, pay.bank_branch, pay.bank_account, pay.bank_reference,
                   pay.payment_date, pay.received_amount, pay.change_amount, pay.reservation_refund,
                   pay.remaining_balance, pay.payment_status, pay.paid_at,
                   pay.created_at AS payment_created_at,
                   ca.full_name AS checkin_assistant_name,
                   ta.full_name AS treatment_assistant_name
            FROM appointments a
            JOIN users p ON p.user_id = a.patient_id
            LEFT JOIN users d ON d.user_id = a.dentist_id
            LEFT JOIN services sv ON sv.service_id = a.service_id
            LEFT JOIN payments pay ON pay.appointment_id = a.appointment_id
            LEFT JOIN dental_assistants ca ON ca.assistant_id = a.checkin_assistant_id
            LEFT JOIN dental_assistants ta ON ta.assistant_id = a.treatment_assistant_id
            WHERE a.appointment_id = ?';
    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $s = db()->prepare(
        'SELECT aps.id, aps.service_id, aps.qty, aps.price, s.service_name, s.quantity_unit, s.allows_installment, s.duration_minutes
         FROM appointment_services aps
         JOIN services s ON s.service_id = aps.service_id
         WHERE aps.appointment_id = ?'
    );
    $s->execute([$id]);
    $row['services'] = $s->fetchAll();
    return $row;
}

/**
 * Has this payment reference number already been used on another transaction?
 * Checks every column that can hold a GCash / bank reference: the reservation
 * reference, the GCash reference, the bank reference on `payments`, and the
 * counter-transaction reference. `$excludePaymentId` / `$excludeCounterId` let an
 * edit keep its own reference without tripping the check on itself.
 */
/**
 * Admin-added online payment methods (migration 078). Keys are 'm<id>' and
 * sit beside the built-in 'gcash' / 'bank'. $activeOnly: only methods patients
 * may pick. Never throws (an install without the table just has none).
 */
function extraPaymentMethods(bool $activeOnly = true): array
{
    try {
        $rows = db()->query('SELECT * FROM payment_methods' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY name')->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    return array_map(static function (array $r): array {
        $qr = (string) ($r['qr_image'] ?? '');
        return [
            'method_id'      => (int) $r['method_id'],
            'key'            => 'm' . (int) $r['method_id'],
            'name'           => $r['name'],
            'account_name'   => $r['account_name'] ?? '',
            'account_number' => $r['account_number'] ?? '',
            'qr'             => $qr !== '' && is_file(__DIR__ . '/../uploads/payment/' . $qr)
                ? '/dgt_dental_clinic/uploads/payment/' . rawurlencode($qr) : null,
            'is_active'      => (int) $r['is_active'],
        ];
    }, $rows);
}

/** One active admin-added method by its 'm<id>' key, or null. */
function extraPaymentMethod(string $key): ?array
{
    if (!preg_match('/^m(\d+)$/', $key)) {
        return null;
    }
    foreach (extraPaymentMethods() as $m) {
        if ($m['key'] === $key) {
            return $m;
        }
    }
    return null;
}

function referenceInUse(string $ref, ?int $excludePaymentId = null, ?int $excludeCounterId = null): bool
{
    $ref = trim($ref);
    if ($ref === '') {
        return false;
    }

    // Positional placeholders — this project runs PDO with real prepares
    // (ATTR_EMULATE_PREPARES = false), where a named param can't be reused.
    $paySql = 'SELECT 1 FROM payments
               WHERE (reservation_reference = ? OR gcash_reference_number = ? OR bank_reference = ?)';
    $payParams = [$ref, $ref, $ref];
    if ($excludePaymentId !== null) {
        $paySql .= ' AND payment_id <> ?';
        $payParams[] = $excludePaymentId;
    }
    $paySql .= ' LIMIT 1';
    $st = db()->prepare($paySql);
    $st->execute($payParams);
    if ($st->fetchColumn() !== false) {
        return true;
    }

    $ctSql = 'SELECT 1 FROM counter_transactions WHERE (reference_no = ? OR bank_reference = ?)';
    $ctParams = [$ref, $ref];
    if ($excludeCounterId !== null) {
        $ctSql .= ' AND id <> ?';
        $ctParams[] = $excludeCounterId;
    }
    $ctSql .= ' LIMIT 1';
    $st = db()->prepare($ctSql);
    $st->execute($ctParams);
    return $st->fetchColumn() !== false;
}

/* ---------------------------------------------------------------- Discounts */

/** Active admin promotions, each with its service-id list already parsed. */
function activeDiscounts(): array
{
    $rows = db()->query(
        'SELECT id, discount_name, discount_type, discount_value, target_scope, applied_service_ids
         FROM discounts WHERE is_active = 1 ORDER BY discount_name'
    )->fetchAll();
    foreach ($rows as &$d) {
        $d['service_ids'] = discountServiceIds($d['applied_service_ids']);
        unset($d['applied_service_ids']);
        $d['discount_value'] = (float) $d['discount_value'];
    }
    return $rows;
}

/** Parse the JSON `applied_service_ids` column into a list of positive ints. */
function discountServiceIds(?string $json): array
{
    $decoded = json_decode((string) $json, true);
    if (!is_array($decoded)) {
        return [];
    }
    return array_values(array_filter(array_map('intval', $decoded), static fn ($n) => $n > 0));
}

/** One discount row by id (any status), or null. */
function discountById(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM discounts WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/**
 * Peso deduction a promo produces against a bill.
 *  - scope "all": the whole Total Sales is the base.
 *  - scope "specific": only the line items whose service_id is in the promo's
 *    list count toward the base.
 * A percentage promo takes that share of the base; a fixed promo takes the
 * lower of its amount and the base. The result never exceeds Total Sales.
 * `$lineItems` rows are ['amount' => float, 'service_id' => int|null].
 */
function promoDeduction(?array $d, float $totalSales, array $lineItems): float
{
    if (!$d || (int) ($d['is_active'] ?? 1) !== 1 || $totalSales <= 0) {
        return 0.0;
    }
    if (($d['target_scope'] ?? 'all') === 'all') {
        $base = $totalSales;
    } else {
        $ids  = discountServiceIds($d['applied_service_ids'] ?? null);
        $base = 0.0;
        foreach ($lineItems as $li) {
            $sid = (int) ($li['service_id'] ?? 0);
            if ($sid > 0 && in_array($sid, $ids, true)) {
                $base += (float) ($li['amount'] ?? 0);
            }
        }
    }
    if ($base <= 0) {
        return 0.0;
    }
    $val = (float) $d['discount_value'];
    $ded = ($d['discount_type'] ?? 'percentage') === 'percentage'
        ? $base * $val / 100
        : min($val, $base);
    return min(round(max($ded, 0), 2), round($totalSales, 2));
}

/**
 * The name shown on the receipt / View modal beside the deduction, e.g.
 * "Less: Back to School Promo   −₱350.00". The rate/amount already renders in
 * the value column, so the label is just the promo's name (with the rate
 * appended only when the name doesn't already say it).
 */
function promoLabel(array $d): string
{
    $name = trim((string) $d['discount_name']);
    if (!preg_match('/%|\boff\b/i', $name)) {
        $tag = ($d['discount_type'] ?? 'percentage') === 'percentage'
            ? rtrim(rtrim(number_format((float) $d['discount_value'], 2, '.', ''), '0'), '.') . '% off'
            : peso((float) $d['discount_value']) . ' off';
        $name .= ' (' . $tag . ')';
    }
    return mb_substr($name, 0, 160);
}

/**
 * Create a bare patient account + patient_records row for someone being
 * treated who isn't the one managing the booking — used when a patient books
 * online "For Someone Else" and types in a brand-new person (no usable login
 * of their own: this is "just a record", the same shape a walk-in's "New
 * patient" flow creates, minus the temp-password handover since there's no
 * front desk here to hand it to). Contact number and e-mail are copied from
 * the booking itself / the booking account (the user's explicit request — the
 * new record should be reachable the same way the account holder already is,
 * since there's no separate contact info of this person's own to collect
 * here). A shared e-mail across two accounts is a deliberate trade-off: this
 * new account is never actually logged into, so the only practical cost is
 * that a password-reset request for that address could land a token on
 * whichever row `LIMIT 1` in api/request_password_reset.php happens to pick.
 * Returns [user_id, full_name, age].
 */
/** $guardianId: the parent / guardian account managing this child (migration 089). */
function createUnlinkedPatientRecord(array $in, int $createdBy, int $guardianId = 0): array
{
    $first   = properCase($in['first_name'] ?? '');
    $last    = properCase($in['last_name'] ?? '');
    $contact = digitsOnly($in['contact_number'] ?? '');
    $email   = trim((string) ($in['email'] ?? ''));

    if ($first !== '' && $last !== '' && (!validPersonName($first) || !validPersonName($last))) {
        jsonOut(['ok' => false, 'error' => PERSON_NAME_MSG], 422);
    }
    if ($first === '' || $last === '') {
        jsonOut(['ok' => false, 'error' => 'Enter the patient\'s first and last name.'], 422);
    }
    // Date of Birth, not a typed age (the age is computed from it).
    if (trim((string) ($in['date_of_birth'] ?? '')) === '') {
        jsonOut(['ok' => false, 'error' => 'Enter the patient’s date of birth.'], 422);
    }
    $dob = validBirthDate((string) $in['date_of_birth'], $dobErr);
    if ($dob === null) {
        jsonOut(['ok' => false, 'error' => $dobErr], 422);
    }
    $age = (int) ageFromBirthDate($dob);
    // Children only, 1 to 17 (2026-10-23): adults register their own account.
    if ($age > OTHER_MAX_AGE) {
        jsonOut(['ok' => false, 'error' => OTHER_ADULT_MSG], 422);
    }
    if ($age < WALKIN_MIN_AGE) {
        jsonOut(['ok' => false, 'error' => 'The patient must be at least ' . WALKIN_MIN_AGE . ' year old.'], 422);
    }

    $name = personName($first, '', $last);
    $pdo  = db();
    $username = makeUsername($pdo, $first, $last);
    $tempPass = tempPassword();   // never shown anywhere — this account is never actually logged into

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "INSERT INTO users (full_name, first_name, last_name, username, password,
                                must_change_password, is_verified, role, guardian_user_id, contact_number, email)
             VALUES (?, ?, ?, ?, ?, 1, 0, 'patient', ?, ?, ?)"
        )->execute([
            $name, $first, $last, $username, password_hash($tempPass, PASSWORD_DEFAULT),
            $guardianId > 0 ? $guardianId : null,
            $contact !== '' ? $contact : null, $email !== '' ? $email : null,
        ]);
        $pid = (int) $pdo->lastInsertId();   // capture before logActivity() inserts its own row

        $pdo->prepare('INSERT INTO patient_records (patient_id, date_of_birth, updated_by) VALUES (?, ?, ?)')
            ->execute([$pid, $dob, $createdBy]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        jsonOut(['ok' => false, 'error' => 'Could not create the patient record.'], 500);
    }

    logActivity('records', 'Created a patient record for ' . $name);
    return [$pid, $name, $age];
}

/* ---------------------------------------------------------------- OR series (migration 084) */

/** The cashier's active Official Receipt series, or null. */
function orActiveSeries(): ?array
{
    $row = db()->query("SELECT * FROM or_series WHERE status = 'active' ORDER BY series_id DESC LIMIT 1")->fetch();
    return $row ?: null;
}

function orFormat(int $n, int $digits): string
{
    return str_pad((string) $n, max(1, $digits), '0', STR_PAD_LEFT);
}

/** True when an admin has voided this receipt number (migration 085). */
function orIsVoided(int $n): bool
{
    $st = db()->prepare('SELECT 1 FROM or_voids WHERE CAST(or_no AS UNSIGNED) = ? LIMIT 1');
    $st->execute([$n]);
    return (bool) $st->fetchColumn();
}

/** What the Billing / OR Setup screens show: the next OR number and today's transaction count. */
function orStatus(): array
{
    $s = orActiveSeries();
    $today = (int) db()->query('SELECT COUNT(*) FROM or_issued WHERE DATE(issued_at) = CURDATE()')->fetchColumn();
    $out = ['series' => null, 'next' => null, 'remaining' => 0, 'today_count' => $today, 'next_txn' => $today + 1];
    if ($s) {
        $d = (int) $s['digits'];
        $out['series'] = [
            'series_id' => (int) $s['series_id'],
            'start'     => orFormat((int) $s['start_no'], $d),
            'end'       => orFormat((int) $s['end_no'], $d),
            'total'     => (int) $s['end_no'] - (int) $s['start_no'] + 1,
            'used'      => (int) db()->query('SELECT COUNT(*) FROM or_issued WHERE series_id = ' . (int) $s['series_id'])->fetchColumn(),
        ];
        $out['next'] = orFormat((int) $s['next_no'], $d);
        // The receipt waiting in the printer was voided by the admin: the
        // cashier must re-align to the next fresh one before taking a payment.
        $out['next_voided'] = orIsVoided((int) $s['next_no']);
        $out['remaining'] = max(0, (int) $s['end_no'] - (int) $s['next_no'] + 1);
    }
    return $out;
}

/**
 * Give the next OR number of the active series to a payment. $expected is the
 * number the cashier confirmed is loaded in the printer; if another payment
 * took it first, nothing is issued and the caller gets a 409.
 */
function orIssueNext(string $expected, int $paymentId, int $appointmentId, float $amount, ?int $userId, array $split = []): array
{
    $s = orActiveSeries();
    if (!$s) {
        jsonOut(['ok' => false, 'error' => 'No OR series is set up. Set the receipt numbers in OR / Invoice Setup first.', 'or_missing' => true], 422);
    }
    $d = (int) $s['digits'];
    $no = (int) $s['next_no'];
    if ($expected === '' || ltrim($expected, '0') !== ltrim(orFormat($no, $d), '0')) {
        jsonOut(['ok' => false, 'error' => 'The next OR number is now ' . orFormat($no, $d) . '. Check the receipt loaded in the printer and try again.', 'or_changed' => true], 409);
    }
    if (orIsVoided($no)) {
        jsonOut(['ok' => false, 'error' => 'OR No. ' . orFormat($no, $d) . ' was voided by the admin. Re-align to the next fresh receipt in OR / Invoice Setup.', 'or_changed' => true], 409);
    }
    $upd = db()->prepare(
        "UPDATE or_series SET status = IF(next_no >= end_no, 'used_up', 'active'), next_no = next_no + 1
         WHERE series_id = ? AND next_no = ? AND status = 'active'"
    );
    $upd->execute([(int) $s['series_id'], $no]);
    if ($upd->rowCount() === 0) {
        jsonOut(['ok' => false, 'error' => 'That OR number was just used. Reload the page and check the printer.', 'or_changed' => true], 409);
    }
    $orNo = orFormat($no, $d);
    $seq = (int) db()->query('SELECT COUNT(*) FROM or_issued WHERE DATE(issued_at) = CURDATE()')->fetchColumn() + 1;
    db()->prepare('INSERT INTO or_issued (or_no, series_id, payment_id, appointment_id, amount, pay_cash, pay_gcash, pay_bank, day_seq, issued_by, issued_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$orNo, (int) $s['series_id'], $paymentId, $appointmentId, $amount,
                   round((float) ($split['cash'] ?? 0), 2), round((float) ($split['gcash'] ?? 0), 2), round((float) ($split['bank'] ?? 0), 2),
                   $seq, $userId]);
    db()->prepare('UPDATE payments SET or_number = ? WHERE payment_id = ?')->execute([$orNo, $paymentId]);
    return ['or_number' => $orNo, 'day_seq' => $seq];
}

/** Recalculate + persist a payment row's derived fields. */
function recomputePayment(int $paymentId): void
{
    $stmt = db()->prepare('SELECT * FROM payments WHERE payment_id = ?');
    $stmt->execute([$paymentId]);
    $p = $stmt->fetch();
    if (!$p) {
        return;
    }

    $total    = (float) $p['total_amount'];
    // Money actually collected at the counter toward the procedure bill.
    $procPaid = (float) $p['amount_cash'] + (float) $p['amount_gcash'] + (float) $p['amount_bank'];
    $covered  = $procPaid + (float) $p['reservation_fee_paid'] + (float) $p['discount'] + (float) $p['promo_discount_amount'];
    $remaining = max($total - $covered, 0);

    // A reservation-fee credit alone (no cashier money) never advances the
    // status past "unpaid" — even when it happens to equal the whole bill
    // (e.g. a ₱500 reservation fee against a ₱500 service). The cashier must
    // actually process something before a visit reads as partially/fully paid.
    // The one exception: the cashier pressed Pay on a bill with nothing left
    // to collect (settle stamps payment_date), which closes it as fully paid.
    $status = 'unpaid';
    if (($procPaid > 0 || $p['payment_date'] !== null) && $covered >= $total && $total > 0) {
        $status = 'fully_paid';
    } elseif ($procPaid > 0) {
        $status = 'partially_paid';
    }

    $paidAt = $p['paid_at'];
    if ($status === 'fully_paid') {
        if ($paidAt === null) {
            $paidAt = date('Y-m-d H:i:s');
        }
    } else {
        // Only a fully-paid bill carries a settlement timestamp.
        $paidAt = null;
    }

    $upd = db()->prepare(
        'UPDATE payments SET remaining_balance = ?, payment_status = ?, paid_at = ? WHERE payment_id = ?'
    );
    $upd->execute([$remaining, $status, $paidAt, $paymentId]);
}

/* ---------------------------------------------------------------
   Treatment Plan & Follow-up Service (migration 066)
   --------------------------------------------------------------- */

/**
 * Follow-up timeframes the dentist picks from => MySQL INTERVAL for the due date.
 * Common dental follow-up intervals, days / weeks / months only (user's call,
 * 2026-10-08): denture adjustment check (3 days), extraction / surgery review
 * (1 week), suture removal or next root canal session (2 weeks), braces
 * adjustment (1 month / 6 weeks), gum maintenance and implant review
 * (3 months), routine check-up and cleaning (6 months).
 * Keep the labels in sync with FU_TIMEFRAMES in assets/js/app.js.
 */
const FOLLOWUP_TIMEFRAMES = [
    '3 Days'   => '3 DAY',
    '1 Week'   => '1 WEEK',
    '2 Weeks'  => '2 WEEK',
    '1 Month'  => '1 MONTH',
    '6 Weeks'  => '6 WEEK',
    '3 Months' => '3 MONTH',
    '6 Months' => '6 MONTH',
];

/**
 * The first date on or after $date the clinic is open: skips Sundays and any
 * admin-blocked operational date (blocked_dates). Used for follow-up due dates.
 */
function nextOpenClinicDate(string $date): string
{
    $d = new DateTime($date);
    try {
        $st = db()->prepare('SELECT blocked_date FROM blocked_dates WHERE blocked_date BETWEEN ? AND DATE_ADD(?, INTERVAL 120 DAY)');
        $st->execute([$d->format('Y-m-d'), $d->format('Y-m-d')]);
        $blocked = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        $blocked = [];
    }
    for ($i = 0; $i < 120; $i++) {
        if (($d->format('w') !== '0' || sundayOpen()) && !isset($blocked[$d->format('Y-m-d')])) {
            break;
        }
        $d->modify('+1 day');
    }
    return $d->format('Y-m-d');
}

/**
 * Move every not-yet-completed follow-up plan whose due date falls on a Sunday
 * or a blocked operational date to the next open day. Optionally scoped to one
 * date (an admin just blocked it). Best-effort, never fails the request.
 */
function shiftTreatmentPlanDueDates(?string $onDate = null): void
{
    try {
        $sql = "SELECT tp.plan_id, tp.due_date FROM treatment_plans tp
                WHERE tp.status <> 'Completed'
                  AND (DAYOFWEEK(tp.due_date) = 1
                       OR EXISTS (SELECT 1 FROM blocked_dates b WHERE b.blocked_date = tp.due_date))";
        $args = [];
        if ($onDate !== null) {
            $sql .= ' AND tp.due_date = ?';
            $args[] = $onDate;
        }
        $st = db()->prepare($sql);
        $st->execute($args);
        $upd = db()->prepare('UPDATE treatment_plans SET due_date = ? WHERE plan_id = ?');
        foreach ($st->fetchAll() as $p) {
            $upd->execute([nextOpenClinicDate((string) $p['due_date']), (int) $p['plan_id']]);
        }
    } catch (Throwable $e) {
        // best-effort
    }
}

/**
 * Re-derive treatment_plans.status from the bookings made for each plan, so a
 * cancelled / declined / no-show booking puts the plan back to Pending (the
 * patient can book it again) and a completed one closes it:
 *   any completed booking  => Completed
 *   any live booking       => Scheduled (pending / payment_declined / approved / rescheduled)
 *   otherwise              => Pending
 * Scope it with $patientId or $planId; best-effort, never fails the request.
 */
function syncTreatmentPlans(?int $patientId = null, ?int $planId = null): void
{
    try {
        $sql = "UPDATE treatment_plans tp SET tp.status = CASE
                    WHEN EXISTS (SELECT 1 FROM appointments a WHERE a.treatment_plan_id = tp.plan_id AND a.status = 'completed') THEN 'Completed'
                    WHEN EXISTS (SELECT 1 FROM appointments a WHERE a.treatment_plan_id = tp.plan_id
                                 AND a.status IN ('pending','payment_declined','approved','rescheduled')) THEN 'Scheduled'
                    ELSE 'Pending' END";
        $params = [];
        if ($planId !== null) {
            $sql .= ' WHERE tp.plan_id = ?';
            $params[] = $planId;
        } elseif ($patientId !== null) {
            $sql .= ' WHERE tp.patient_id = ?';
            $params[] = $patientId;
        }
        db()->prepare($sql)->execute($params);
    } catch (Throwable $e) {
        // best-effort
    }
}

/** One plan with its service / dentist names, or null. */
function treatmentPlanDetail(int $planId): ?array
{
    $st = db()->prepare(
        "SELECT tp.*, s.service_name, s.is_active AS service_active,
                d.full_name AS dentist_name, d.doctor_type, d.is_active AS dentist_active, d.email AS dentist_email,
                c.relationship AS consult_relationship, c.booking_for AS consult_booking_for,
                COALESCE(t.full_name, c.patient_name) AS for_name
         FROM treatment_plans tp
         JOIN services s ON s.service_id = tp.service_id
         JOIN users d ON d.user_id = tp.assigned_dentist_id
         JOIN appointments c ON c.appointment_id = tp.consultation_id
         LEFT JOIN users t ON t.user_id = tp.treated_patient_id
         WHERE tp.plan_id = ?"
    );
    $st->execute([$planId]);
    $row = $st->fetch();
    return $row ?: null;
}

/* ---- Grace period + end-of-day No-Show (migration 088) ---- */

/** Actor recorded in the Audit Log for automatic status changes. */
function systemActor(): array
{
    return ['user_id' => null, 'username' => 'system', 'role' => 'system'];
}

/**
 * An approved visit not checked in GRACE_MINUTES after its start becomes
 * Delayed / Standby: delayed_at is stamped, SEAT_SQL stops counting it, so the
 * walk-in calendar shows the seat as free. The booking itself stays active and
 * the patient is checked in as "Arrived (Late)" if they come.
 */
function markDelayedAppointments(): void
{
    try {
        $rows = db()->prepare(
            "SELECT appointment_id, patient_id, patient_name, time_slot FROM appointments
              WHERE status IN ('approved','rescheduled') AND scheduled_date = CURDATE()
                AND checked_in_at IS NULL AND delayed_at IS NULL
                AND NOW() >= TIMESTAMP(scheduled_date, time_slot) + INTERVAL ? MINUTE"
        );
        $rows->execute([GRACE_MINUTES]);
        $mark = db()->prepare('UPDATE appointments SET delayed_at = NOW() WHERE appointment_id = ? AND delayed_at IS NULL AND checked_in_at IS NULL');
        foreach ($rows->fetchAll() as $r) {
            $mark->execute([$r['appointment_id']]);
            if ($mark->rowCount() === 0) {
                continue;
            }
            notify((int) $r['patient_id'], 'Running late',
                'You have not checked in for your ' . fmtTime((string) $r['time_slot']) . ' appointment for ' . $r['patient_name']
              . '. Your slot has been released, but your booking is kept. If you are on your way, please go to the front desk when you arrive and we will seat you in the next available slot.',
                (int) $r['appointment_id']);
            logActivity('appointments', 'Moved Appointment #' . $r['appointment_id'] . ' for ' . $r['patient_name']
                . ' to Delayed / Standby (not checked in ' . GRACE_MINUTES . ' minutes after ' . fmtTime((string) $r['time_slot'])
                . '); slot released to walk-ins', systemActor());
        }
    } catch (Throwable $e) {
        // best-effort: never break the request that triggered it
    }
}

/**
 * End-of-day batch: once the clinic has closed (settings clinic_close), every
 * approved visit of that day (or earlier) that was never checked in becomes
 * No-Show. Arrived (Late) patients are checked in, so they are left for the
 * front desk. Runs on the first appointment request after closing.
 */
function processEndOfDayNoShows(): void
{
    try {
        $close = substr((string) setting('clinic_close', '17:00'), 0, 5);
        $st = db()->prepare(
            "SELECT appointment_id, patient_id, patient_name, scheduled_date, treatment_plan_id, missed_notified_at
               FROM appointments
              WHERE status IN ('approved','rescheduled') AND checked_in_at IS NULL
                AND (scheduled_date < CURDATE() OR (scheduled_date = CURDATE() AND DATE_FORMAT(NOW(), '%H:%i') >= ?))"
        );
        $st->execute([$close]);
        $upd = db()->prepare(
            "UPDATE appointments SET status = 'no_show', missed_notified_at = COALESCE(missed_notified_at, NOW())
              WHERE appointment_id = ? AND status IN ('approved','rescheduled') AND checked_in_at IS NULL"
        );
        foreach ($st->fetchAll() as $r) {
            $upd->execute([$r['appointment_id']]);
            if ($upd->rowCount() === 0) {
                continue;
            }
            if (!empty($r['treatment_plan_id'])) {
                syncTreatmentPlans(null, (int) $r['treatment_plan_id']);
            }
            if ($r['missed_notified_at'] === null) {
                notify((int) $r['patient_id'], 'Missed appointment',
                    'We noticed that you missed your appointment for ' . $r['patient_name'] . ' on '
                  . date('M j, Y', strtotime((string) $r['scheduled_date']))
                  . '. It has been marked as No Show. Please contact the clinic or book a new appointment online.',
                    (int) $r['appointment_id']);
            }
            logActivity('appointments', 'Marked Appointment #' . $r['appointment_id'] . ' for ' . $r['patient_name']
                . ' on ' . $r['scheduled_date'] . ' as No Show (end-of-day batch)', systemActor());
        }
    } catch (Throwable $e) {
        // best-effort
    }
}

/** Both status sweeps, at most once per request. */
function runAppointmentSweeps(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    markDelayedAppointments();
    processEndOfDayNoShows();
    processDependentsTurning18();
}

/**
 * A child managed by a parent / guardian account (users.guardian_user_id,
 * migration 089) who has turned 18 gets their own login: a fresh temporary
 * password, must_change_password = 1, is_verified = 1. The guardian is
 * notified with the username and temporary password to hand over; the child's
 * records and visit history stay on their own account. One-shot via
 * adult_account_at.
 */
function processDependentsTurning18(): void
{
    try {
        $rows = db()->query(
            "SELECT u.user_id, u.full_name, u.username, u.guardian_user_id, u.email, g.email AS guardian_email
               FROM users u JOIN patient_records r ON r.patient_id = u.user_id
               LEFT JOIN users g ON g.user_id = u.guardian_user_id
              WHERE u.role = 'patient' AND u.is_active = 1 AND u.guardian_user_id IS NOT NULL
                AND u.adult_account_at IS NULL
                AND r.date_of_birth <= DATE_SUB(CURDATE(), INTERVAL 18 YEAR)"
        )->fetchAll();
        $upd = db()->prepare(
            'UPDATE users SET password = ?, must_change_password = 1, is_verified = 1, adult_account_at = NOW()
              WHERE user_id = ? AND adult_account_at IS NULL'
        );
        foreach ($rows as $r) {
            $temp = tempPassword();
            $upd->execute([password_hash($temp, PASSWORD_DEFAULT), $r['user_id']]);
            if ($upd->rowCount() === 0) {
                continue;
            }
            // The child's record carried the guardian's e-mail; they add their own.
            if ((string) $r['email'] !== '' && strcasecmp((string) $r['email'], (string) $r['guardian_email']) === 0) {
                db()->prepare('UPDATE users SET email = NULL WHERE user_id = ?')->execute([$r['user_id']]);
            }
            notify((int) $r['guardian_user_id'], $r['full_name'] . ' can now have their own account',
                $r['full_name'] . ' is now 18 and can sign in to their own DGT One Dental Center account. '
              . 'Username: ' . $r['username'] . ' · Temporary password: ' . $temp . '. '
              . 'Please give these to them. On first sign-in they will set a new password and add their own e-mail and phone number. '
              . 'Their dental records and visit history are already on their account.',
                null, 'dependent_adult');
            notify((int) $r['user_id'], 'Welcome to your own account',
                'Your account is now yours to manage. Please add your own e-mail address and phone number on My Profile so you can reset your password and receive reminders.');
            logActivity('auth', 'Gave ' . $r['full_name'] . ' (' . $r['username'] . ') their own login at age 18; temporary password sent to their parent / guardian', systemActor());
        }
    } catch (Throwable $e) {
        // best-effort
    }
}

/**
 * The bill's latest (non-voided) receipt: amount applied and its cash / GCash
 * / bank split. Receipts issued before migration 085 have no split, so the
 * whole amount goes to the bill's payment mode.
 */
function invoiceLastReceipt(array $a): ?array
{
    if (empty($a['payment_id']) || empty($a['or_number'])) return null;
    try {
        $st = db()->prepare('SELECT or_no, amount, pay_cash, pay_gcash, pay_bank FROM or_issued
                              WHERE payment_id = ? AND or_no = ? AND voided_at IS NULL ORDER BY or_id DESC LIMIT 1');
        $st->execute([(int) $a['payment_id'], (string) $a['or_number']]);
        $r = $st->fetch();
    } catch (Throwable $e) {
        return null;
    }
    if (!$r) return null;
    $amt = round((float) $r['amount'], 2);
    if ($r['pay_cash'] === null && $r['pay_gcash'] === null && $r['pay_bank'] === null) {
        $m = in_array($a['payment_mode'] ?? '', ['gcash', 'bank'], true) ? $a['payment_mode'] : 'cash';
        return ['or_no' => $r['or_no'], 'amount' => $amt, 'cash' => $m === 'cash' ? $amt : 0.0,
                'gcash' => $m === 'gcash' ? $amt : 0.0, 'bank' => $m === 'bank' ? $amt : 0.0];
    }
    return ['or_no' => $r['or_no'], 'amount' => $amt, 'cash' => round((float) $r['pay_cash'], 2),
            'gcash' => round((float) $r['pay_gcash'], 2), 'bank' => round((float) $r['pay_bank'], 2)];
}

/**
 * Figures for the printed Service Invoice, from the persisted bill and its
 * latest receipt ($totalDue = Total Sales less SC/PWD and promo). Cash Sales
 * when nothing is left after this receipt, else Charge Sales. Mirrored by
 * buildPrintDoc() in modules/cashier/billing.php.
 */
function invoicePrintFigures(array $a, float $totalDue): array
{
    $resv  = round((float) $a['reservation_fee_paid'], 2);
    $cumul = round((float) $a['amount_cash'] + (float) $a['amount_gcash'] + (float) $a['amount_bank'], 2);
    $lr = invoiceLastReceipt($a);
    if ($lr) {
        $split = ['cash' => $lr['cash'], 'gcash' => $lr['gcash'], 'bank' => $lr['bank']];
        $prior = max(round($cumul - array_sum($split), 2), 0.0);
    } else {
        $split = ['cash' => (float) $a['amount_cash'], 'gcash' => (float) $a['amount_gcash'], 'bank' => (float) $a['amount_bank']];
        $prior = 0.0;
    }
    $today = round(array_sum($split), 2);
    $remaining = max(round($totalDue - $resv - $prior - $today, 2), 0.0);
    $pieces = [];
    if ($prior <= 0.005 && $resv > 0) {
        $pieces[] = ['amount' => $resv, 'method' => reservationMethodLabel($a), 'ref' => (string) ($a['reservation_reference'] ?? '')];
    }
    if ($split['cash'] > 0)  $pieces[] = ['amount' => $split['cash'],  'method' => 'Cash', 'ref' => ''];
    if ($split['gcash'] > 0) $pieces[] = ['amount' => $split['gcash'], 'method' => 'GCash', 'ref' => (string) ($a['gcash_reference_number'] ?? '')];
    if ($split['bank'] > 0)  $pieces[] = ['amount' => $split['bank'],  'method' => 'Bank Transfer', 'ref' => (string) ($a['bank_reference'] ?? '')];
    // Excess reservation handed back as change at settlement (migration 090).
    $refund = round((float) ($a['reservation_refund'] ?? 0), 2);
    return ['cash_sale' => $remaining <= 0.005, 'reservation' => $resv, 'prior' => $prior,
            'today' => $today, 'remaining' => $remaining, 'refund' => $refund, 'pieces' => $pieces];
}

function reservationMethodLabel(array $a): string
{
    if (!empty($a['reservation_method_label'])) return (string) $a['reservation_method_label'];
    return ['gcash' => 'GCash', 'bank' => 'Bank Transfer', 'cash' => 'Cash', 'paymaya' => 'Maya'][$a['reservation_method'] ?? ''] ?? 'Cash';
}

/**
 * The "Received the Amount of" line, e.g.
 *   Five Hundred Pesos Only (₱500.00) via GCash (Ref: 123456789)
 *   Five Hundred Pesos Only (₱500.00: ₱200 Cash, ₱300 via GCash Ref: 123456789)
 * $pieces = [['amount', 'method', 'ref'], ...]; $words turns a number into words.
 * Plain text (escape before output). Mirrored in billing.php.
 */
function formatReceivedAmountText(array $pieces, callable $words): string
{
    $pieces = array_values(array_filter($pieces, fn ($p) => (float) $p['amount'] > 0.005));
    if (!$pieces) return '';
    $total = round(array_sum(array_map(fn ($p) => (float) $p['amount'], $pieces)), 2);
    $head = $words($total) . ' (₱' . number_format($total, 2);
    if (count($pieces) === 1) {
        $p = $pieces[0];
        return $head . ') via ' . $p['method'] . ($p['ref'] !== '' ? ' (Ref: ' . $p['ref'] . ')' : '');
    }
    $short = fn (float $n) => number_format($n, fmod(round($n, 2), 1.0) == 0.0 ? 0 : 2);
    $parts = array_map(function ($p) use ($short) {
        $amt = '₱' . $short((float) $p['amount']);
        return $p['method'] === 'Cash' ? $amt . ' Cash' : $amt . ' via ' . $p['method'] . ($p['ref'] !== '' ? ' Ref: ' . $p['ref'] : '');
    }, $pieces);
    return $head . ': ' . implode(', ', $parts) . ')';
}
