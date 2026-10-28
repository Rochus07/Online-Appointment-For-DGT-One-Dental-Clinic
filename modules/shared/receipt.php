<?php
/**
 * Service Invoice for a settled (or partially settled) bill — a read-only
 * render of the cashier Billing screen, driven by the persisted payment.
 * Reachable by the cashier, an admin, or the patient the bill belongs to.
 * Laid out as a copy of the clinic's own paper Service Invoice pad.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../api/_bootstrap.php';

// _bootstrap.php sends a JSON content-type for API endpoints; this page is HTML.
header('Content-Type: text/html; charset=UTF-8');

$me = checkAuth();
$appointmentId = (int) ($_GET['appointment'] ?? 0);
$a = appointmentDetail($appointmentId);

if (!$a || !$a['payment_id']) {
    http_response_code(404);
    exit('Invoice not found.');
}
if ($me['role'] === 'patient' && (int) $a['patient_id'] !== (int) $me['user_id']) {
    http_response_code(403);
    exit('This invoice is not yours.');
}

// Clinic identity exactly as printed on the clinic's own paper Service
// Invoice pad (2026-10-07); billing.php carries the same lines.
$clinicName  = 'DGT ONE DENTAL CENTER CO.';
$clinicLines = [
    'VAT Reg TIN: 608-196-530-00009',
    '2nd Flr. Unit F & G Del Rey Bldg., Gov. F. Halili Ave.,',
    'Tungkong Mangga, 3023 San Jose Del Monte, Bulacan, Phils.',
];

// Line items: the invoice of record if it exists, else one row per procedure
// the dentist logged.
$li = db()->prepare('SELECT kind, description, qty, unit, unit_cost, amount FROM invoice_items WHERE payment_id = ? ORDER BY id');
$li->execute([(int) $a['payment_id']]);
$items = $li->fetchAll();
if (!$items) {
    foreach (($a['services'] ?? []) as $s) {
        $qty = max((int) ($s['qty'] ?? 1), 1);
        $items[] = [
            'kind' => 'service', 'description' => $s['service_name'],
            'qty' => $qty, 'unit' => 'session',
            'unit_cost' => (float) $s['price'] / $qty, 'amount' => (float) $s['price'],
        ];
    }
}

$totalSales  = (float) $a['total_amount'];
$scPwd       = (int) $a['sc_pwd_discount'] === 1;
$discount    = (float) $a['discount'];
$promoAmount = (float) $a['promo_discount_amount'];
$promoLabel  = (string) ($a['promo_discount_label'] ?: 'Promo Discount');
$reservation = (float) $a['reservation_fee_paid'];
// Figures for the bill's latest receipt: Cash Sales (nothing left, Total
// Amount Due 0.00) or Charge Sales (Down Payment + remaining balance). Same
// rules as the cashier Billing print (invoicePrintFigures() in _bootstrap.php).
$fig         = invoicePrintFigures($a, max($totalSales - $discount - $promoAmount, 0.0));
$cashSale    = $fig['cash_sale'];
$orNo        = !empty($a['or_number']) ? 'OR No. ' . $a['or_number'] : 'Service Invoice';   // never a number built from payment_id
$dateBasis   = strtotime((string) ($a['payment_date'] ?: $a['paid_at'] ?: 'now'));
$mon         = date('M', $dateBasis);
$paperMd     = $mon . ($mon === 'May' ? ' ' : '. ') . date('d', $dateBasis);   // "Oct. 07"
$paperYy     = date('y', $dateBasis);                                       // "26" after the printed "20"
$scId        = $scPwd ? (string) ($a['sc_pwd_id'] ?? '') : '';
$scTin       = $scPwd && ($a['sc_pwd_type'] ?? '') === 'senior' ? (string) ($a['sc_tin'] ?? '') : '';

// "Twelve Hundred Pesos Only" style amount in words (matches billing.php).
function pesosToWords(float $amount): string
{
    $n = (int) floor(abs($amount));
    if ($n === 0) return 'Zero Pesos Only';
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $below1000 = function (int $x) use ($ones, $tens): string {
        $s = '';
        if ($x >= 100) { $s .= $ones[intdiv($x, 100)] . ' Hundred'; $x %= 100; if ($x) $s .= ' '; }
        if ($x >= 20) { $s .= $tens[intdiv($x, 10)]; if ($x % 10) $s .= ' ' . $ones[$x % 10]; }
        elseif ($x > 0) { $s .= $ones[$x]; }
        return $s;
    };
    $parts = [];
    foreach ([[1000000000, 'Billion'], [1000000, 'Million'], [1000, 'Thousand'], [1, '']] as [$v, $name]) {
        if ($n >= $v) { $c = intdiv($n, $v); $n %= $v; $parts[] = trim($below1000($c) . ($name ? ' ' . $name : '')); }
    }
    $w = implode(' ', array_filter($parts));
    return $w . ' Peso' . ($w === 'One' ? '' : 's') . ' Only';
}

function siMoney(float $n): string
{
    return $n > 0 ? number_format($n, 2) : '';
}

// $labelHtml is trusted markup built in this file; the value is escaped.
function siRow(string $labelHtml, string $value, string $cls = ''): string
{
    return '<tr' . ($cls ? ' class="' . $cls . '"' : '') . '><th>' . $labelHtml . '</th><td>' . e($value) . '</td></tr>';
}

$actBtn = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold';

// "LastName,FirstName_MM-DD-YY" (today's date) — same convention as the
// cashier Billing screen's invoiceFilename().
$fnParts = array_values(array_filter(preg_split('/\s+/', trim((string) $a['patient_name'])) ?: []));
$fnFirst = $fnParts[0] ?? 'Patient';
$fnLast  = count($fnParts) > 1 ? end($fnParts) : $fnFirst;
$invoiceFilename   = $fnLast . ',' . $fnFirst . '_' . date('m-d-y');
$invoiceFilenameJs = json_encode($invoiceFilename, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($orNo) ?> &middot; <?= e($clinicName) ?></title>
<link rel="icon" type="image/x-icon" href="<?= e(asset('assets/img/favicon/favicon.ico')) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('assets/img/favicon/favicon-32x32.png')) ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= e(asset('assets/img/favicon/favicon-16x16.png')) ?>">
<?php require __DIR__ . '/../../includes/tailwind.php'; ?>
<style>
    /* On screen the invoice reads as a paper sheet on a desk: a narrow card
       on a muted backdrop with a soft shadow. */
    body { background: #eaeff5; padding: 25px 15px 40px; overflow-x: hidden; }
    #printable-invoice-sheet {
        width: 100%; max-width: 620px; margin: 0 auto; padding: 30px;
        background: #fff; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); border-radius: 2px; box-sizing: border-box;
    }
    .rcpt-actions { max-width: 620px; margin: 16px auto 0; display: flex; gap: 10px; justify-content: flex-end; }
    @media print { body { background: #fff; padding: 0; overflow-x: visible; } }
    /* Phones and tablets: centre the sheet + buttons vertically too, so a
       short invoice doesn't sit at the top over a block of empty backdrop. */
    @media screen and (max-width: 1279.98px) {
        body { min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; justify-content: center; }
        .rcpt-actions { width: 100%; }
    }
    @media screen and (max-width: 640px) {
        body { padding: 10px 8px 28px; }
        #printable-invoice-sheet { padding: 16px 12px; }
        .rcpt-actions { margin-top: 12px; }
        .rcpt-actions button { flex: 1 1 0; }
    }
</style>
</head>
<body class="text-ink">
<?php require __DIR__ . '/../../includes/invoice_style.php'; ?>
<div id="printable-invoice-sheet" class="inv-card">
    <div class="invoice-sheet">
    <div class="si-doc<?= count($items) > 9 ? ' si-many si-dense' : (count($items) > 3 ? ' si-many' : '') ?>">
        <div class="si-namewrap"><div class="si-name"><?= e($clinicName) ?></div></div>
        <div class="si-headrow">
            <div class="si-lines">
                <?php foreach ($clinicLines as $line): ?><div><?= e($line) ?></div><?php endforeach; ?>
            </div>
            <div class="si-title"><b>SERVICE</b><b>INVOICE</b></div>
        </div>

        <div class="si-meta">
            <span class="si-chk"><span class="si-box"><?= $cashSale ? '&#10003;' : '' ?></span>CASH SALES</span>
            <span class="si-chk"><span class="si-box"><?= $cashSale ? '' : '&#10003;' ?></span>CHARGE SALES</span>
            <span class="si-date">Date: <span class="si-fill si-md"><?= e($paperMd) ?></span>, 20<span class="si-fill si-yy"><?= e($paperYy) ?></span></span>
        </div>
        <div class="si-sold"><span>S<small>OLD</small> T<small>O</small>:</span><span class="si-fill"><?= e(invoiceSoldTo($a)) ?></span></div>

        <div class="si-cust"><div class="si-crow"><span class="si-clbl">Registered Name</span><span class="si-cline"></span></div><div class="si-crow"><span class="si-clbl">TIN</span><span class="si-cline"><span class="si-csp"><?= e($scTin) ?></span><span class="si-csc"><span>SC/PWD/NAAC/<br>MOV/Solo Parent</span><b>ID #</b></span><span class="si-cval"><?= e($scId) ?></span></span></div><div class="si-crow"><span class="si-clbl">Bus. Address:</span><span class="si-cline"><span class="si-csp"></span><span class="si-csc"><span>SC/PWD/NAAC/<br>MOV/Solo Parent</span><b>SIGNATURE</b></span><span class="si-cval"></span></span></div></div>

        <div class="si-items-wrap"><table class="si-items"><colgroup><col style="width:54%"><col style="width:8%"><col style="width:14%"><col style="width:24%"></colgroup>
            <thead><tr><th>Nature of Service</th><th>Qty</th><th class="sm">Unit<br>Price</th><th>Amount</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= e($it['description']) ?></td>
                    <td class="c"><?= e((string) ($it['qty'] ?: 1)) ?></td>
                    <td class="num"><?= e(number_format((float) $it['unit_cost'], 2)) ?></td>
                    <td class="num"><?= e(number_format((float) $it['amount'], 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php for ($i = count($items); $i < 3; $i++): ?><tr><td></td><td></td><td></td><td></td></tr><?php endfor; ?>
            </tbody>
        </table></div>

        <div class="si-bottom">
            <div class="si-left">
                <table class="si-vat"><tbody>
                    <?= siRow('VATable Sales', '') ?>
                    <?= siRow('VAT-Exempt Sales', '') ?>
                    <?= siRow('Zero-Rated Sales', '') ?>
                    <?= siRow('VAT Amount', '') ?>
                </tbody></table>
                <div class="si-recv">
                    <div class="si-recv-h">Received the Amount of</div>
                    <div class="si-recv-v"><?= e(formatReceivedAmountText($fig['pieces'], 'pesosToWords')) ?></div>
                </div>
                <div class="si-pfoot"><div class="si-printer"><b>F.G. VITUG <i>Social &amp; Commercial Printers</i></b><br>7 Encarnita St., Richland Subd., Sauyo, Nova., Q.C.<br>Printer's VAT Reg. TIN: 132-436-565-00000<br>50 bklts. (50x2) #000001-002500<br>BIR ATP No. OCN: 25BAU20260000010863<br>Date of ATP: <b>May 25, 2026</b></div>
                <?php if (!empty($a['or_number'])): ?><div class="si-orno">OR No. <b><?= e($a['or_number']) ?></b></div><?php endif; ?></div>
            </div>
            <div class="si-right">
            <table class="si-tot"><tbody>
                <?= siRow('<b>TOTAL TREATMENT:</b> <small class="inl">VAT<br>Inclusive</small>', siMoney($totalSales)) ?>
                <?= siRow('<b>Less:</b> VAT', '') ?>
                <?= siRow('<b>AMT.:</b> Net of VAT', '') ?>
                <?= siRow('<b>Less Discount:</b><small>(SC/PWD/NAAC/MOV/Solo Parent)</small>', $scPwd ? siMoney($discount) : '') ?>
                <?php if ($promoAmount > 0): ?><?= siRow('<b>Less:</b> ' . e($promoLabel), siMoney($promoAmount)) ?><?php endif; ?>
                <?= siRow('<b>Add:</b> VAT 12%', '') ?>
                <?= siRow('<b>Less:</b> Withholding Tax', '') ?>
                <?php if ($reservation > 0.005): ?><?= siRow('<b>Less:</b> Reservation Deposit', siMoney($reservation)) ?><?php endif; ?>
                <?php if ($fig['prior'] > 0.005): ?><?= siRow('<b>Less:</b> Previous Payments', siMoney($fig['prior'])) ?><?php endif; ?>
                <?= siRow('<b>Less:</b> Payments Received Today', siMoney($fig['today'])) ?>
                <?php if ($fig['refund'] > 0.005): ?><?= siRow('<b>Less:</b> Excess Reservation Returned (Change)', siMoney($fig['refund'])) ?><?php endif; ?>
                <?= siRow('<b>TOTAL AMOUNT PAID: &#8369;</b>', number_format($fig['reservation'] + $fig['prior'] + $fig['today'] - $fig['refund'], 2), $cashSale ? 'due' : '') ?>
                <?php if (!$cashSale): // installment: a balance is still owed ?><?= siRow('<b>REMAINING BALANCE (TOTAL DUE): &#8369;</b>', number_format($fig['remaining'], 2), 'due') ?><?php endif; ?>
            </tbody></table>
            <div class="si-sign"><div class="si-sigline"></div>Cashier/Authorized Signature</div>
            </div>
        </div>
    </div>
    </div>
</div>

<div class="rcpt-actions no-print">
    <button class="<?= $actBtn ?> border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50" onclick="window.close()">Close</button>
    <button class="<?= $actBtn ?> bg-accent text-white hover:bg-accent-dark" id="printBtn">Print Preview</button>
</div>
<script>
(function () {
    var FILENAME = <?= $invoiceFilenameJs ?>;

    // Chrome's own "Save as PDF" print destination takes its suggested filename
    // from document.title as it stands when the dialog opens; a real printer
    // driver (e.g. "Microsoft Print to PDF") does not forward it.
    function printInvoice() {
        var prevTitle = document.title;
        var restored = false;
        function restore() {
            if (restored) return;
            restored = true;
            document.title = prevTitle;
            window.removeEventListener('afterprint', restore);
        }
        window.addEventListener('afterprint', restore);
        setTimeout(restore, 10000);
        document.title = FILENAME;
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { window.print(); });
        });
    }

    document.getElementById('printBtn').addEventListener('click', printInvoice);
})();
</script>
</body>
</html>
