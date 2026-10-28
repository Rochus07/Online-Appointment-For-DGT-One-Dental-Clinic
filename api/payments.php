<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

/**
 * Installment payments are only for orthodontic (braces) treatments: allowed
 * when the bill has a service with services.allows_installment = 1 (migration
 * 069), or a payment plan is already running on it.
 */
function installmentAllowed(array $a): bool
{
    if (($a['payment_status'] ?? '') === 'partially_paid') {
        return true;
    }
    foreach ($a['services'] ?? [] as $s) {
        if (!empty($s['allows_installment'])) {
            return true;
        }
    }
    return false;
}

$me     = apiAuth(['cashier', 'admin']);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

const SC_PWD_RATE     = 0.20;   // senior citizen / PWD statutory discount

/* ---------------------------------------------------------------- Transactions queue */
if ($method === 'GET' && $action === 'queue') {
    $sql = "SELECT a.appointment_id, a.patient_name, a.scheduled_date, a.time_slot, a.status,
                   d.full_name AS specialist,
                   sv.service_name AS requested_service,
                   pay.payment_id, pay.total_amount, pay.remaining_balance, pay.payment_status,
                   pay.reservation_reference
            FROM appointments a
            LEFT JOIN users d ON d.user_id = a.dentist_id
            LEFT JOIN services sv ON sv.service_id = a.service_id
            LEFT JOIN payments pay ON pay.appointment_id = a.appointment_id
            WHERE a.status = 'completed'
              AND (pay.payment_status IS NULL OR pay.payment_status <> 'fully_paid')
            ORDER BY pay.payment_id DESC, a.appointment_id DESC";
    jsonOut(['ok' => true, 'queue' => db()->query($sql)->fetchAll()]);
}

/* -------------------------------------------------- Over-the-counter transactions */
if ($method === 'GET' && $action === 'counter_list') {
    $rows = db()->query(
        "SELECT ct.id, ct.description, ct.customer_name, ct.amount, ct.payment_method,
                ct.reference_no, ct.gcash_number, ct.bank_branch, ct.bank_account, ct.bank_reference,
                ct.amount_cash, ct.amount_gcash, ct.amount_bank, ct.received_amount, ct.change_amount,
                ct.payment_date, ct.created_at, u.full_name AS cashier_name
         FROM counter_transactions ct
         LEFT JOIN users u ON u.user_id = ct.cashier_id
         ORDER BY ct.created_at DESC
         LIMIT 100"
    )->fetchAll();
    jsonOut(['ok' => true, 'transactions' => $rows]);
}

/* ---------------------------------------------------------------- Transaction logs */
if ($method === 'GET' && ($action === 'logs' || $action === 'history')) {
    $sql = "SELECT pay.payment_id, a.appointment_id, a.patient_name, a.scheduled_date,
                   sv.service_name AS requested_service, d.full_name AS specialist,
                   pay.total_amount, pay.discount, pay.doctor_fee, pay.withholding_tax,
                   pay.payment_mode, pay.received_amount, pay.change_amount,
                   pay.remaining_balance, pay.payment_status, pay.paid_at, pay.created_at, pay.or_number
            FROM payments pay
            JOIN appointments a ON a.appointment_id = pay.appointment_id
            LEFT JOIN services sv ON sv.service_id = a.service_id
            LEFT JOIN users d ON d.user_id = a.dentist_id
            WHERE a.status <> 'cancelled'
            ORDER BY (pay.payment_status = 'fully_paid'), pay.paid_at DESC, pay.created_at DESC
            LIMIT 200";
    jsonOut(['ok' => true, 'logs' => db()->query($sql)->fetchAll()]);
}

/* ---------------------------------------------------------------- Invoice detail */
if ($method === 'GET' && ($action === 'invoice' || $action === 'bill')) {
    $id = (int) ($_GET['id'] ?? 0);
    $a = appointmentDetail($id);
    if (!$a) {
        jsonOut(['ok' => false, 'error' => 'Not found'], 404);
    }

    // Patient account phone for the "Mobile Number" invoice field.
    $ph = db()->prepare('SELECT contact_number FROM users WHERE user_id = ?');
    $ph->execute([(int) $a['patient_id']]);
    $patientPhone = $ph->fetchColumn() ?: '';

    // Patient's on-file address for the "Invoice To" block.
    $addr = db()->prepare('SELECT address FROM patient_records WHERE patient_id = ?');
    $addr->execute([(int) $a['patient_id']]);
    $patientAddress = $addr->fetchColumn() ?: '';

    // Invoice line items: a saved set if the bill was worked on before, else one
    // row per procedure the dentist logged.
    $lineItems = [];
    if ($a['payment_id']) {
        $li = db()->prepare('SELECT kind, service_id, description, qty, unit, unit_cost, amount FROM invoice_items WHERE payment_id = ? ORDER BY id');
        $li->execute([(int) $a['payment_id']]);
        $lineItems = $li->fetchAll();
    }
    if (!$lineItems) {
        foreach ($a['services'] as $s) {
            $qty = max((int) ($s['qty'] ?? 1), 1);
            $lineItems[] = [
                'kind' => 'service', 'service_id' => (int) $s['service_id'],
                'description' => $s['service_name'],
                'qty' => $qty, 'unit' => $s['quantity_unit'] ?: 'session',
                'unit_cost' => round((float) $s['price'] / $qty, 2), 'amount' => (float) $s['price'],
            ];
        }
    }

    $appliedPromo = null;
    if ($a['promo_discount_id'] || (float) $a['promo_discount_amount'] > 0) {
        $appliedPromo = [
            'id'     => $a['promo_discount_id'] ? (int) $a['promo_discount_id'] : null,
            'label'  => $a['promo_discount_label'],
            'amount' => (float) $a['promo_discount_amount'],
        ];
    }

    jsonOut([
        'ok'              => true,
        'appointment'     => $a,
        'patient_phone'   => $patientPhone,
        'patient_address' => $patientAddress,
        'sold_to'         => invoiceSoldTo($a),
        'line_items'      => $lineItems,
        'installment_allowed' => installmentAllowed($a),
        'last_receipt'    => invoiceLastReceipt($a),
        'or'              => orStatus(),
        'discounts'       => activeDiscounts(),
        // Services the cashier may add at checkout (2026-10-21).
        'service_catalog' => db()->query('SELECT service_id, service_name, base_price, quantity_unit FROM services WHERE is_active = 1 ORDER BY service_name')->fetchAll(),
        'applied_promo'   => $appliedPromo,
        'reservation_fee' => (float) setting('reservation_fee', '500.00'),
        'sc_pwd_rate'     => SC_PWD_RATE,
        'clinic_name'     => setting('clinic_name', 'DGT One Dental Center - Bulacan'),
    ]);
}

/* ---------------------------------------------------------------- Settle a bill */
$in = requestBody();

if ($action === 'counter_add') {                  // "+ Add Transaction" — OTC sale / manual adjustment
    $desc   = mb_substr(trim((string) ($in['description'] ?? '')), 0, 255);
    $name   = trim((string) ($in['customer_name'] ?? ''));
    $amount = round((float) ($in['amount'] ?? 0), 2);   // the bill total
    $method = $in['payment_method'] ?? 'cash';
    $gcashRef = trim((string) ($in['reference_no'] ?? ''));
    $gcashNum = digitsOnly($in['gcash_number'] ?? '');
    $bankBranch = null;
    $bankAcct   = null;
    $bankRef    = null;

    if ($desc === '') {
        jsonOut(['ok' => false, 'error' => 'Enter a description for the transaction.'], 422);
    }
    if ($amount <= 0) {
        jsonOut(['ok' => false, 'error' => 'Enter an amount greater than zero.'], 422);
    }
    if (!in_array($method, ['cash', 'gcash', 'bank', 'split'], true)) {
        jsonOut(['ok' => false, 'error' => 'Choose a form of payment.'], 422);
    }
    if ($gcashNum !== '' && !isPhMobile($gcashNum)) {
        jsonOut(['ok' => false, 'error' => PH_MOBILE_MSG], 422);
    }

    $pdate = $in['payment_date'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $pdate)) {
        $pdate = date('Y-m-d');
    }

    // Split amounts (only meaningful when $method === 'split').
    $splitCash  = max(round((float) ($in['split_cash'] ?? 0), 2), 0);
    $splitGcash = max(round((float) ($in['split_gcash'] ?? 0), 2), 0);
    $splitBank  = max(round((float) ($in['split_bank'] ?? 0), 2), 0);

    if ($method === 'split') {
        $received = round($splitCash + $splitGcash + $splitBank, 2);
        if ($received <= 0) {
            jsonOut(['ok' => false, 'error' => 'Enter at least one split amount.'], 422);
        }
        if ($received + 0.01 < $amount) {
            jsonOut(['ok' => false, 'error' => 'The split total (' . peso($received) . ') is less than the ' . peso($amount) . ' total.'], 422);
        }
        $change  = round($received - $amount, 2);
        $netCash = round($splitCash - $change, 2);   // change comes out of the cash portion
        if ($netCash < -0.005) {
            $shortfall = round($change - $splitCash, 2);
            jsonOut(['ok' => false, 'error' =>
                'You entered ' . peso($received) . ' total for a ' . peso($amount) . ' amount, so ' . peso($change) . ' change is owed, '
                . 'but only ' . peso($splitCash) . ' of that was cash, which isn\'t enough to hand back as change. '
                . 'Add at least ' . peso($shortfall) . ' more to the Cash amount, or lower one of the split amounts so no change is due.'
            ], 422);
        }
        $amtCash  = max($netCash, 0);
        $amtGcash = $splitGcash;
        $amtBank  = $splitBank;
        $useGcash = $splitGcash > 0.005;
        $useBank  = $splitBank > 0.005;
    } else {
        $received = round((float) ($in['received_amount'] ?? 0), 2);
        if ($received <= 0) {
            $received = $amount;   // default to the exact amount
        }
        if ($received + 0.01 < $amount) {
            jsonOut(['ok' => false, 'error' => 'Received amount is less than the ' . peso($amount) . ' total.'], 422);
        }
        $change   = $method === 'cash' ? round($received - $amount, 2) : 0.0;
        $amtCash  = $method === 'cash'  ? $amount : 0.0;
        $amtGcash = $method === 'gcash' ? $amount : 0.0;
        $amtBank  = $method === 'bank'  ? $amount : 0.0;
        $useGcash = $method === 'gcash';
        $useBank  = $method === 'bank';
    }

    if ($useGcash) {
        if (!preg_match('/^\d{13}$/', $gcashRef)) {
            jsonOut(['ok' => false, 'error' => 'GCash Reference Number must be exactly 13 digits.'], 422);
        }
        if (referenceInUse($gcashRef)) {
            jsonOut(['ok' => false, 'error' => 'This reference number has already been used for another transaction.'], 409);
        }
    } else {
        $gcashRef = '';
    }
    if ($useBank) {
        $bankBranch = mb_substr(trim((string) ($in['bank_branch'] ?? '')), 0, 120);
        $bankAcct   = trim((string) ($in['bank_account'] ?? ''));
        $bankRef    = trim((string) ($in['bank_reference'] ?? ''));
        if (mb_strlen($bankBranch) < 3) {
            jsonOut(['ok' => false, 'error' => 'Please enter a valid Bank / Branch.'], 422);
        }
        if (!preg_match('/^\d{8,16}$/', $bankAcct)) {
            jsonOut(['ok' => false, 'error' => 'Account number must be 8-16 digits.'], 422);
        }
        if (!preg_match('/^[A-Za-z0-9]{10,20}$/', (string) $bankRef)) {
            jsonOut(['ok' => false, 'error' => 'Bank Reference Number must be 10-20 characters.'], 422);
        }
        if (referenceInUse($bankRef)) {
            jsonOut(['ok' => false, 'error' => 'This reference number has already been used for another transaction.'], 409);
        }
    }

    db()->prepare(
        'INSERT INTO counter_transactions
            (description, customer_name, amount, payment_method, reference_no, gcash_number,
             bank_branch, bank_account, bank_reference,
             amount_cash, amount_gcash, amount_bank, received_amount, change_amount, payment_date, cashier_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $desc, $name ?: null, $amount, $method,
        $gcashRef ?: null, ($useGcash && $gcashNum !== '') ? $gcashNum : null,
        $bankBranch ?: null, $bankAcct ?: null, $bankRef ?: null,
        $amtCash, $amtGcash, $amtBank, $received, $change, $pdate,
        (int) $me['user_id'],
    ]);

    logActivity('billing', 'Recorded a ' . peso($amount) . ' '
        . match ($method) { 'cash' => 'Cash', 'gcash' => 'GCash', 'bank' => 'Bank Transfer', 'split' => 'Split', default => ucfirst($method) }
        . ' counter transaction: ' . $desc . ($name !== '' ? ' (' . $name . ')' : ''), $me);
    jsonOut(['ok' => true, 'id' => (int) db()->lastInsertId()]);
}

/**
 * E-mail the patient their receipt after a payment (2026-10-19): the visit,
 * every billed line, discounts, the reservation credit, what was paid and the
 * balance, plus a link to the printable invoice. Queued, so it is sent after
 * the cashier's response. Best-effort.
 */
function emailPaymentReceipt(int $appointmentId, string $title, string $message): void
{
    try {
        $a = appointmentDetail($appointmentId);
        if (!$a || empty($a['payment_id'])) {
            return;
        }
        $st = db()->prepare("SELECT email FROM users WHERE user_id = ? AND role = 'patient' AND is_active = 1");
        $st->execute([(int) $a['patient_id']]);
        $to = (string) $st->fetchColumn();
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        require_once __DIR__ . '/../includes/mailer.php';
        $h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $li = db()->prepare('SELECT description, qty, amount FROM invoice_items WHERE payment_id = ? ORDER BY id');
        $li->execute([(int) $a['payment_id']]);
        $items = $li->fetchAll();
        if (!$items) {
            foreach (($a['services'] ?? []) as $s) {
                $items[] = ['description' => $s['service_name'], 'qty' => max((int) ($s['qty'] ?? 1), 1), 'amount' => (float) $s['price']];
            }
        }
        $td  = 'padding:7px 0;border-bottom:1px solid #eef1f6;font-size:13px;color:#24364f;';
        $tdr = $td . 'text-align:right;white-space:nowrap;';
        $row = static fn ($l, $v, $bold = false) => '<tr><td style="' . $td . ($bold ? 'font-weight:700;' : '') . '">' . $l
            . '</td><td style="' . $tdr . ($bold ? 'font-weight:700;' : '') . '">' . $v . '</td></tr>';
        $money = static fn ($n) => '&#8369;' . number_format((float) $n, 2);

        $rows = '';
        foreach ($items as $it) {
            $q = (int) ($it['qty'] ?? 1);
            $rows .= $row($h($it['description']) . ($q > 1 ? ' &times; ' . $q : ''), $money($it['amount']));
        }
        $total = (float) $a['total_amount'];
        $rows .= $row('Total Sales', $money($total), true);
        if ((float) $a['discount'] > 0) {
            $rows .= $row('Less: ' . (($a['sc_pwd_type'] ?? '') === 'pwd' ? 'PWD' : 'SC/PWD') . ' Discount (20%)', '&minus;' . $money($a['discount']));
        }
        if ((float) $a['promo_discount_amount'] > 0) {
            $rows .= $row('Less: ' . $h($a['promo_discount_label'] ?: 'Promo Discount'), '&minus;' . $money($a['promo_discount_amount']));
        }
        if ((float) $a['reservation_fee_paid'] > 0) {
            $rows .= $row('Less: Reservation Deposit', '&minus;' . $money($a['reservation_fee_paid']));
        }
        $collected = (float) $a['amount_cash'] + (float) $a['amount_gcash'] + (float) $a['amount_bank'];
        $paid = $collected > 0 ? $collected : (float) $a['received_amount'];
        $method = ['cash' => 'Cash', 'gcash' => 'GCash', 'bank' => 'Bank Transfer', 'split' => 'Split'][$a['payment_mode'] ?? ''] ?? '';
        $rows .= $row('Amount Paid' . ($method !== '' ? ' (' . $method . ')' : ''), $money($paid), true);
        if ((float) $a['change_amount'] > 0) {
            $rows .= $row((float) ($a['reservation_refund'] ?? 0) > 0 ? 'Change (excess reservation)' : 'Change', $money($a['change_amount']));
        }
        $rows .= $row('Remaining Balance', $money($a['remaining_balance']), true);

        // The cashier's printed OR number only (never a number built from payment_id).
        $orNo = !empty($a['or_number']) ? 'OR No. ' . $a['or_number'] : '';
        $when = date('M j, Y', strtotime((string) ($a['payment_date'] ?: $a['paid_at'] ?: 'now')));
        $extra = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 26px;text-align:left;">'
            . ($orNo !== '' ? $row('<span style="color:#8896a8;">OR No.</span>', $h($a['or_number'])) : '')
            . $row('<span style="color:#8896a8;">Date</span>', $h($when))
            . $row('<span style="color:#8896a8;">Patient</span>', $h($a['patient_name']))
            . $row('<span style="color:#8896a8;">Dentist</span>', $h($a['dentist_name'] ? 'Dr. ' . $a['dentist_name'] : ''))
            . '<tr><td colspan="2" style="padding:14px 0 4px;font-size:11px;font-weight:700;letter-spacing:.06em;color:#8896a8;">SERVICES</td></tr>'
            . $rows . '</table>';
        $html = mailShell($h($title), $h($message), 'View / Print Receipt',
            appBaseUrl() . '/modules/shared/receipt?appointment=' . $appointmentId,
            'Keep this e-mail for your records. Sign in to view or print the official receipt.', $extra);
        queueEmail($to, 'Your receipt from DGT One Dental Center' . ($orNo !== '' ? ' (' . $orNo . ')' : ''), $html);
    } catch (Throwable $e) {
        // best-effort: billing never fails because of e-mail
    }
}

if ($action === 'settle' || $action === 'record') {
    $appointmentId = (int) ($in['appointment_id'] ?? 0);
    $a = appointmentDetail($appointmentId);
    if (!$a || !$a['payment_id']) {
        jsonOut(['ok' => false, 'error' => 'No billing record for this appointment.'], 404);
    }
    if ($a['status'] !== 'completed') {
        jsonOut(['ok' => false, 'error' => 'Only a completed visit can be billed.'], 422);
    }
    if ($a['payment_status'] === 'fully_paid') {
        jsonOut(['ok' => false, 'error' => 'This bill is already fully paid.'], 422);
    }
    // Every payment uses the next pre-printed receipt of the cashier's OR series (migration 084).
    $orExpected = trim((string) ($in['or_number'] ?? ''));
    if (!orActiveSeries()) {
        jsonOut(['ok' => false, 'error' => 'No OR series is set up. Set the receipt numbers in OR / Invoice Setup first.', 'or_missing' => true], 422);
    }

    // Only the dentist sets what was performed, how many and at what price
    // (2026-10): the cashier's line items are read-only, so the invoice of record
    // is rebuilt here from the dentist's procedure log, ignoring any line_items
    // the client sends (appointment_services.price is the line total).
    $lineItems = [];
    foreach ($a['services'] as $ds) {
        $qty  = max((int) ($ds['qty'] ?? 1), 1);
        $line = round((float) $ds['price'], 2);
        $lineItems[] = [
            'kind'        => 'service',
            'service_id'  => (int) $ds['service_id'],
            'description' => $ds['service_name'],
            'qty'         => $qty,
            'unit'        => $ds['quantity_unit'] ?: 'session',
            'unit_cost'   => round($line / $qty, 2),
            'amount'      => $line,
        ];
    }

    // Services the cashier added at checkout (the dentist didn't log them):
    // [{service_id, qty, unit_cost}]. Each must be an active service not
    // already on the visit; single-session services are always qty 1.
    $extraLines = [];
    $onVisit = array_map(static fn ($ds) => (int) $ds['service_id'], $a['services']);
    foreach ((array) ($in['extra_services'] ?? []) as $ex) {
        $sid = (int) ($ex['service_id'] ?? 0);
        $svSt = db()->prepare('SELECT service_id, service_name, quantity_unit FROM services WHERE service_id = ? AND is_active = 1');
        $svSt->execute([$sid]);
        $sv = $svSt->fetch();
        if (!$sv) {
            jsonOut(['ok' => false, 'error' => 'Choose a service for every added row.'], 422);
        }
        if (in_array($sid, $onVisit, true)) {
            jsonOut(['ok' => false, 'error' => $sv['service_name'] . ' is already on this bill.'], 422);
        }
        $qty  = $sv['quantity_unit'] ? max(min((int) ($ex['qty'] ?? 1), 99), 1) : 1;
        $cost = round((float) ($ex['unit_cost'] ?? 0), 2);
        if ($cost <= 0 || $cost > 1000000) {
            jsonOut(['ok' => false, 'error' => 'Enter the price for ' . $sv['service_name'] . '.'], 422);
        }
        $onVisit[] = $sid;
        $extraLines[] = [
            'kind' => 'service', 'service_id' => $sid, 'description' => $sv['service_name'],
            'qty' => $qty, 'unit' => $sv['quantity_unit'] ?: 'session',
            'unit_cost' => $cost, 'amount' => round($qty * $cost, 2),
        ];
    }
    $lineItems = array_merge($lineItems, $extraLines);

    $totalSales = $lineItems
        ? round(array_sum(array_column($lineItems, 'amount')), 2)
        : (float) $a['total_amount'];
    if ($totalSales <= 0) {
        jsonOut(['ok' => false, 'error' => 'This visit has no billable procedures.'], 422);
    }

    // Senior citizen (60+ only) or PWD discount, with a valid ID (2026-10-18).
    $scPwd   = !empty($in['sc_pwd_discount']);
    $scPwdId = strtoupper(trim((string) ($in['sc_pwd_id'] ?? '')));
    $scType  = $scPwd ? (string) ($in['sc_pwd_type'] ?? '') : '';
    $scTin   = $scPwd && $scType === 'senior' ? trim((string) ($in['sc_tin'] ?? '')) : '';
    if ($scPwd) {
        if (!in_array($scType, ['senior', 'pwd'], true)) {
            jsonOut(['ok' => false, 'error' => 'Choose Senior Citizen or PWD for the discount.'], 422);
        }
        if ($scType === 'senior') {
            if ((int) ($a['patient_age'] ?? 0) < SC_MIN_AGE) {
                jsonOut(['ok' => false, 'error' => 'The senior citizen discount is only for patients ' . SC_MIN_AGE . ' years old and above.'], 422);
            }
            if (!validOscaId($scPwdId)) {
                jsonOut(['ok' => false, 'error' => 'Enter the OSCA / Senior Citizen ID No. (3 to 20 letters, digits or hyphens).'], 422);
            }
            if ($scTin !== '' && !validTin($scTin)) {
                jsonOut(['ok' => false, 'error' => 'The TIN must look like 123-456-789, 123-456-789-000 or 123-456-789-00000.'], 422);
            }
        } elseif (!validPwdId($scPwdId)) {
            jsonOut(['ok' => false, 'error' => 'The PWD ID No. must look like 13-7405-000-0001234.'], 422);
        }
    } else {
        $scPwdId = '';
    }

    $discount     = $scPwd ? round($totalSales * SC_PWD_RATE, 2) : 0.0;

    // Admin promotion applied to this bill (optional). The deduction is always
    // recomputed here from the promo rule + the submitted line items — the
    // client figure is never trusted.
    $promoId     = (int) ($in['promo_discount_id'] ?? 0);
    $promo       = $promoId > 0 ? discountById($promoId) : null;
    $promoAmount = 0.0;
    $promoLabel  = null;
    if ($promo && (int) $promo['is_active'] === 1) {
        $promoAmount = promoDeduction($promo, $totalSales, $lineItems);
        $promoLabel  = promoLabel($promo);
        // A percentage/scope pairing that hits nothing on this bill still
        // records the promo, just with a zero deduction.
    } elseif ($promoId > 0) {
        jsonOut(['ok' => false, 'error' => 'That promotion is no longer available.'], 422);
    }

    $patientTotal = round($totalSales - $discount - $promoAmount, 2);
    if ($patientTotal < 0) {
        $patientTotal = 0.0;
    }
    $withholding  = max((float) ($in['withholding_tax'] ?? 0), 0);

    $reservation   = (float) $a['reservation_fee_paid'];
    $priorCash     = (float) $a['amount_cash'];
    $priorGcash    = (float) $a['amount_gcash'];
    $priorBank     = (float) $a['amount_bank'];
    $priorProcPaid = $priorCash + $priorGcash + $priorBank;
    $balanceDue    = max(round($patientTotal - $reservation - $priorProcPaid, 2), 0);
    // Nothing left to collect (e.g. the reservation fee covers the whole
    // bill): Pay still settles it, with ₱0 received, so it closes as paid.
    $zeroDue = $balanceDue <= 0.005;
    if ($zeroDue && $a['payment_status'] === 'fully_paid') {
        jsonOut(['ok' => false, 'error' => 'This bill is already fully paid.'], 422);
    }

    $mode = $in['payment_mode'] ?? '';
    if (!in_array($mode, ['cash', 'gcash', 'bank', 'split'], true)) {
        jsonOut(['ok' => false, 'error' => 'Choose a form of payment.'], 422);
    }

    // Split payment: the bill is settled with more than one method at once. The
    // three amounts are what the patient handed over per method; their sum is
    // the received amount, and any change comes back out of the cash portion.
    $splitCash  = max(round((float) ($in['split_cash'] ?? 0), 2), 0);
    $splitGcash = max(round((float) ($in['split_gcash'] ?? 0), 2), 0);
    $splitBank  = max(round((float) ($in['split_bank'] ?? 0), 2), 0);
    $splitSum   = round($splitCash + $splitGcash + $splitBank, 2);

    // Full payment settles the whole balance; an installment accepts less and
    // leaves the bill "partially_paid".
    $type     = ($in['payment_type'] ?? 'full') === 'installment' ? 'installment' : 'full';
    $received = $mode === 'split' ? $splitSum : max(round((float) ($in['received_amount'] ?? 0), 2), 0);
    if ($received <= 0 && !$zeroDue) {
        jsonOut(['ok' => false, 'error' => $mode === 'split' ? 'Enter at least one split amount.' : 'Enter the amount received.'], 422);
    }
    // Compared in whole centavos, no tolerance: ₱1,799.99 is not ₱1,800.00.
    $recvC = (int) round($received * 100);
    $dueC  = (int) round($balanceDue * 100);
    if ($type === 'installment' && $recvC >= $dueC) {
        $type = 'full';   // an "installment" that covers everything is just a full payment
    }
    if ($type === 'installment' && !installmentAllowed($a)) {
        jsonOut(['ok' => false, 'error' => 'Installment is only for orthodontic treatments such as braces. This bill must be paid in full.'], 422);
    }
    if ($type === 'full') {
        if ($recvC < $dueC) {
            jsonOut(['ok' => false, 'error' => 'Received amount is less than the balance due (' . peso($balanceDue) . '). Switch to Installment to accept a partial payment.'], 422);
        }
        $applied = $balanceDue;
        $change  = round($received - $balanceDue, 2);
        // The reservation paid online was more than the final bill: the
        // difference is handed back too (shown as change, 2026-10-26).
        $resvRefund = max(round($reservation + $priorProcPaid - $patientTotal, 2), 0.0);
    } else {
        $applied = $received;   // partial — takes exactly what was handed over
        $change  = 0.0;
        $resvRefund = 0.0;
    }

    $gcashRef = trim((string) ($in['gcash_reference_number'] ?? ''));
    $bankRef = trim((string) ($in['bank_reference'] ?? ''));

    // Work out how much of $applied lands in each method column, and which
    // reference blocks need validating.
    if ($mode === 'split') {
        $payGcash = $splitGcash;
        $payBank  = $splitBank;
        $payCash  = round($splitCash - $change, 2);   // change is returned from cash
        if ($payCash < -0.005) {
            $shortfall = round($change - $splitCash, 2);
            jsonOut(['ok' => false, 'error' =>
                'You entered ' . peso($received) . ' total for a ' . peso($balanceDue) . ' balance due, so ' . peso($change) . ' change is owed, '
                . 'but only ' . peso($splitCash) . ' of that was cash, which isn\'t enough to hand back as change. '
                . 'Add at least ' . peso($shortfall) . ' more to the Cash amount, or lower one of the split amounts so no change is due.'
            ], 422);
        }
        $payCash  = max($payCash, 0);
        $useGcash = $splitGcash > 0.005;
        $useBank  = $splitBank > 0.005;
    } else {
        $payCash  = $mode === 'cash'  ? $applied : 0.0;
        $payGcash = $mode === 'gcash' ? $applied : 0.0;
        $payBank  = $mode === 'bank'  ? $applied : 0.0;
        $useGcash = $mode === 'gcash' && $applied > 0;
        $useBank  = $mode === 'bank' && $applied > 0;
    }

    if ($useGcash) {
        if (!preg_match('/^\d{13}$/', $gcashRef)) {
            jsonOut(['ok' => false, 'error' => 'GCash reference number must be exactly 13 digits.'], 422);
        }
        if (referenceInUse($gcashRef, (int) $a['payment_id'])) {
            jsonOut(['ok' => false, 'error' => 'This reference number has already been used for another transaction.'], 409);
        }
    }
    if ($useBank) {
        if (!preg_match('/^[A-Za-z0-9]{10,20}$/', $bankRef)) {
            jsonOut(['ok' => false, 'error' => 'Bank Reference Number must be 10-20 characters.'], 422);
        }
        if (referenceInUse($bankRef, (int) $a['payment_id'])) {
            jsonOut(['ok' => false, 'error' => 'This reference number has already been used for another transaction.'], 409);
        }
    }

    // Accumulate onto whatever was collected on an earlier visit to the counter.
    $amountCash  = round($priorCash  + $payCash,  2);
    $amountGcash = round($priorGcash + $payGcash, 2);
    $amountBank  = round($priorBank  + $payBank,  2);

    // Payment Date is no longer client-editable — it is always the current
    // date on the DB clock (never PHP's date(), and never client input), so it
    // can't be backdated/forward-dated by anything sent from the browser.
    $paymentDate = (string) db()->query('SELECT CURDATE()')->fetchColumn();

    $orIssued = orIssueNext($orExpected, (int) $a['payment_id'], $appointmentId, (float) $applied, (int) $me['user_id'],
        ['cash' => $payCash, 'gcash' => $payGcash, 'bank' => $payBank]);

    // Save the cashier-added services onto the visit's procedure list.
    if ($extraLines) {
        $asIns = db()->prepare('INSERT INTO appointment_services (appointment_id, service_id, qty, price, added_by) VALUES (?, ?, ?, ?, ?)');
        foreach ($extraLines as $el) {
            $asIns->execute([$appointmentId, $el['service_id'], $el['qty'], $el['amount'], (int) $me['user_id']]);
        }
    }

    $stmt = db()->prepare(
        'UPDATE payments SET
            total_amount = ?,
            discount = ?, sc_pwd_discount = ?, sc_pwd_id = ?, sc_pwd_type = ?, sc_tin = ?,
            promo_discount_id = ?, promo_discount_label = ?, promo_discount_amount = ?,
            doctor_fee = ?, withholding_tax = ?, payment_mode = ?,
            amount_cash = ?, amount_gcash = ?, amount_bank = ?,
            gcash_reference_number = ?, gcash_number = ?, bank_branch = ?, bank_account = ?, bank_reference = ?,
            payment_date = ?, received_amount = ?, change_amount = ?, reservation_refund = ?
         WHERE payment_id = ?'
    );
    $stmt->execute([
        $totalSales,
        $discount, $scPwd ? 1 : 0, $scPwd ? $scPwdId : null, $scPwd ? $scType : null, $scTin !== '' ? $scTin : null,
        ($promo ? (int) $promo['id'] : null), $promoLabel, $promoAmount,
        0, $withholding, $mode,
        $amountCash, $amountGcash, $amountBank,
        $useGcash ? $gcashRef : ($a['gcash_reference_number'] ?: null),
        $a['gcash_number'] ?: null,
        $a['bank_branch'] ?: null,
        $a['bank_account'] ?: null,
        $useBank ? $bankRef : ($a['bank_reference'] ?: null),
        $paymentDate, $received, round($change + $resvRefund, 2), $resvRefund,
        $a['payment_id'],
    ]);

    // Persist the invoice line items sent by the Billing screen.
    if ($lineItems) {
        db()->prepare('DELETE FROM invoice_items WHERE payment_id = ?')->execute([$a['payment_id']]);
        $ins = db()->prepare(
            'INSERT INTO invoice_items (payment_id, kind, service_id, description, qty, unit, unit_cost, amount)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($lineItems as $li) {
            $ins->execute([$a['payment_id'], $li['kind'], $li['service_id'], $li['description'], $li['qty'], $li['unit'], $li['unit_cost'], $li['amount']]);
        }
    }

    recomputePayment((int) $a['payment_id']);
    $fresh = appointmentDetail($appointmentId);

    // The in-app notice; the e-mail copy is the receipt itself (emailPaymentReceipt()).
    $rcTitle = null;
    if ($fresh['payment_status'] === 'fully_paid') {
        $rcTitle = 'Payment received';
        $rcMsg = 'Payment for ' . $a['patient_name'] . ' (' . peso($patientTotal) . ') is complete. Your official receipt is ready.';
    } elseif ($fresh['payment_status'] === 'partially_paid') {
        $rcTitle = 'Installment recorded';
        $rcMsg = 'An installment of ' . peso($applied) . ' was recorded for ' . $a['patient_name'] . '. Remaining balance: ' . peso((float) $fresh['remaining_balance']) . '.';
    }
    if ($rcTitle !== null) {
        notify((int) $a['patient_id'], $rcTitle, $rcMsg, $appointmentId, null, false);
        emailPaymentReceipt($appointmentId, $rcTitle, $rcMsg);
    }

    $methodLabel = match ($mode) {
        'cash'  => 'Cash',
        'gcash' => 'GCash',
        'bank'  => 'Bank Transfer',
        'split' => 'Split (' . implode(' + ', array_filter([
            $payCash  > 0.005 ? 'Cash ' . peso($payCash)   : null,
            $payGcash > 0.005 ? 'GCash ' . peso($payGcash) : null,
            $payBank  > 0.005 ? 'Bank ' . peso($payBank)   : null,
        ])) . ')',
        default => ucfirst($mode),
    };
    $logMsg = 'Processed a ' . peso($applied) . ' ' . $methodLabel
        . ' payment for ' . $a['patient_name'] . ' on OR No. ' . $orIssued['or_number']
        . ' (transaction #' . $orIssued['day_seq'] . ' today, Appointment #' . (int) $a['appointment_id'] . ')'
        . ': ' . str_replace('_', ' ', (string) $fresh['payment_status']);
    if ($discount > 0 && $scPwd) {
        $logMsg .= '; SC/PWD discount ' . peso($discount);
    }
    if ($promoAmount > 0) {
        $logMsg .= '; discount "' . $promoLabel . '" ' . peso($promoAmount);
    }
    if ($extraLines) {
        $logMsg .= '; cashier added ' . implode(', ', array_map(static fn ($el) => $el['description'] . ($el['qty'] > 1 ? ' x' . $el['qty'] : '') . ' ' . peso($el['amount']), $extraLines));
    }
    if ($resvRefund > 0) {
        $logMsg .= '; returned ' . peso($resvRefund) . ' excess reservation as change';
    }
    if ($fresh['payment_status'] === 'fully_paid') {
        $logMsg .= '; official receipt issued';
    }
    logActivity('billing', $logMsg, $me);

    jsonOut(['ok' => true, 'payment' => [
        'payment_id'        => (int) $a['payment_id'],
        'or_number'         => $orIssued['or_number'],
        'day_seq'           => $orIssued['day_seq'],
        'payment_status'    => $fresh['payment_status'],
        'remaining_balance' => (float) $fresh['remaining_balance'],
        'applied'           => $applied,
        'change'            => $change,
        'promo_label'       => $promoLabel,
        'promo_amount'      => $promoAmount,
    ]]);
}

jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
