<?php
/**
 * Printable revenue report (open in a new tab, then Print / Save as PDF).
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: text/html; charset=UTF-8');
$me = checkAuth(['admin']);

[$from, $to] = reportWindow();

$stmt = getDB()->prepare(
    "SELECT a.patient_name, d.full_name AS specialist,
            CONCAT(CASE rl.kind WHEN 'reservation' THEN 'Reservation fee: ' WHEN 'refund' THEN 'Excess reservation returned: ' ELSE '' END, COALESCE(sv.service_name, 'N/A')) AS service,
            rl.rev_date AS date_of_service, rl.amount, rl.method AS payment_mode
     FROM " . revenueLinesSql() . " rl
     JOIN appointments a ON a.appointment_id = rl.appointment_id
     LEFT JOIN users d ON d.user_id = a.dentist_id
     LEFT JOIN services sv ON sv.service_id = a.service_id
     WHERE rl.amount <> 0 AND rl.rev_date BETWEEN ? AND ?
     ORDER BY rl.rev_at DESC, rl.payment_id DESC"
);
$stmt->execute([$from, $to]);
$rows  = $stmt->fetchAll();
$total = array_sum(array_map(static fn ($r) => (float) $r['amount'], $rows));
$clinic = setting('clinic_name', 'DGT One Dental Center - Bulacan');
$preparedBy = trim((string) ($me['full_name'] ?? '')) ?: ($me['username'] ?? 'Staff');
// Instructor-specified format: "YYYY-MM-DD | HH:MM AM/PM".
$genTimestamp = date('Y-m-d | h:i A');

// The printed range ends at the last transaction's date (2026-10-22), e.g.
// 10-01 to 10-20; with none, it stops at today while the period is running.
$today = (string) getDB()->query('SELECT CURDATE()')->fetchColumn();
$lastTx = $rows ? max(array_map(static fn ($r) => substr((string) $r['date_of_service'], 0, 10), $rows)) : '';
$docTo = $lastTx !== '' ? $lastTx : (($to > $today && $from <= $today) ? $today : $to);
$fromLabel = date('m-d-Y', strtotime($from));
$toLabel   = date('m-d-Y', strtotime($docTo));
$rangeLabel = $from === $docTo ? $fromLabel : $fromLabel . ' – ' . $toLabel;
// Browsers put the document <title> in the print header margin, so keep it clean.
$docTitle = $clinic . ' - Revenue Report (' . $rangeLabel . ')';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($docTitle) ?></title>
<link rel="icon" type="image/x-icon" href="<?= e(asset('assets/img/favicon/favicon.ico')) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('assets/img/favicon/favicon-32x32.png')) ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= e(asset('assets/img/favicon/favicon-16x16.png')) ?>">
<?php require __DIR__ . '/../../includes/tailwind.php'; ?>
<style>
    /* Self-contained print/report styling (this page does not use layout.php). */
    body { background: #e6ecf3; padding: 24px; font-family: 'Segoe UI', Roboto, Arial, sans-serif; }
    .rpt { max-width: 900px; margin: 0 auto; background: #fff; border: 1px solid #c7d2df;
           border-radius: 10px; padding: 30px 34px; }
    .rpt h1 { font-size: 18px; color: #0f1c2e; text-align: center; }
    .rpt .sub { text-align: center; color: #48607a; font-size: 13px; margin: 4px 0 20px; }
    .rpt table { width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 12px; }
    .rpt th { text-align: left; background: #f4f7fa; padding: 8px 9px; font-size: 10px;
              text-transform: uppercase; letter-spacing: .5px; color: #48607a; border-bottom: 1px solid #c7d2df; }
    .rpt td { padding: 8px 9px; border-bottom: 1px solid #e6ecf3; word-break: break-word; overflow-wrap: anywhere; vertical-align: top; }
    .rpt td.amt, .rpt th.amt { text-align: right; white-space: nowrap; }
    .rpt tfoot td { font-weight: 700; font-size: 15px; border-top: 2px solid #16263c; }
    .rpt-footer { margin: 14px 0 0; padding-top: 8px; border-top: 1px solid #e6ecf3; font-size: 11px; color: #48607a; line-height: 1.6; }
    .rpt-actions { max-width: 900px; margin: 16px auto 0; display: flex; gap: 10px; justify-content: flex-end; }
    .rpt-actions button[disabled] { opacity: .6; pointer-events: none; }

    /* While the browser print dialog is open, or html2pdf is rendering, show only the report. */
    @media print {
        /* Zero top/bottom page margin removes the browser's own date/title/URL header line. */
        @page { size: A4; margin: 0 10mm; }
        html, body { background: #fff !important; padding: 0 !important; margin: 0 !important; }
        .rpt-actions, .no-print { display: none !important; }
        .rpt { max-width: none; border: 0; border-radius: 0; padding: 14mm 0; box-shadow: none; }
        .rpt th { background: #f1f5f9 !important; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
    /* html2pdf renders screen styles, not @media print — flatten the card while it snapshots. */
    body.exporting .rpt-actions { display: none; }
    body.exporting .rpt { border: 0; border-radius: 0; box-shadow: none; padding: 8px 4px; max-width: none; }
</style>
</head>
<body class="text-ink">
<div class="rpt">
    <h1><?= e($clinic) ?></h1>
    <p class="sub">Revenue Report &middot; <?= e($rangeLabel) ?></p>
    <table>
        <colgroup>
            <col style="width:5%"><col style="width:32%"><col style="width:30%">
            <col style="width:17%"><col style="width:16%">
        </colgroup>
        <thead><tr><th>#</th><th>Name</th><th>Service</th><th>Date of Service</th><th class="amt">Cost</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $i => $r): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= e($r['patient_name']) ?></td>
                <td><?= e($r['service'] ?: 'N/A') ?></td>
                <td><?= e(date('m-d-Y', strtotime((string) $r['date_of_service']))) ?></td>
                <td class="amt"><?= e(peso($r['amount'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="5" style="text-align:center;color:#6b819a;padding:24px">No revenue in this period.</td></tr>
        <?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="4">Total Revenue</td><td class="amt"><?= e(peso($total)) ?></td></tr></tfoot>
    </table>
    <p class="rpt-footer"><strong>Prepared By:</strong> <?= e($preparedBy) ?><br>
        <strong>Generated Date &amp; Time:</strong> <?= e($genTimestamp) ?></p>
</div>
<?php
$actBtn  = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold';
$actPrim = $actBtn . ' bg-accent text-white hover:bg-accent-dark';
$actGhost = $actBtn . ' border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50';
?>
<div class="rpt-actions no-print">
    <button class="<?= $actGhost ?>" onclick="window.close()">Close</button>
    <button class="<?= $actPrim ?>" id="pdfBtn">Export PDF</button>
    <button class="<?= $actPrim ?>" onclick="window.print()">Print</button>
</div>

<script src="<?= e(asset('assets/js/vendor/html2pdf.bundle.min.js')) ?>"></script>
<script>
(function () {
  var btn = document.getElementById('pdfBtn');
  var fileName = <?= json_encode('Revenue-Report_' . $from . '_to_' . $docTo . '.pdf') ?>;

  // Formal "Page X of Y" footer, stamped onto every page of the finished PDF.
  function stampPageNumbers(pdf) {
    var total = pdf.internal.getNumberOfPages();
    var w = pdf.internal.pageSize.getWidth(), h = pdf.internal.pageSize.getHeight();
    for (var i = 1; i <= total; i++) {
      pdf.setPage(i);
      pdf.setFont('helvetica', 'normal');
      pdf.setFontSize(8);
      pdf.setTextColor(100, 116, 139);
      pdf.text('Page ' + i + ' of ' + total, w - 10, h - 6, { align: 'right' });
    }
  }

  btn.addEventListener('click', function () {
    if (typeof html2pdf === 'undefined') { window.print(); return; }
    var report = document.querySelector('.rpt');
    var original = btn.textContent;
    btn.textContent = 'Preparing…';
    btn.disabled = true;
    document.body.classList.add('exporting');

    html2pdf().set({
      margin:       [10, 10, 12, 10],
      filename:     fileName,
      image:        { type: 'jpeg', quality: 0.98 },
      html2canvas:  { scale: 2, useCORS: true, backgroundColor: '#ffffff' },
      jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
      pagebreak:    { mode: ['css', 'legacy'], avoid: 'tr' },   // never slice a row across pages
    }).from(report).toPdf().get('pdf').then(stampPageNumbers).save().catch(function () {
      window.print();
    }).finally(function () {
      btn.textContent = original;
      btn.disabled = false;
      document.body.classList.remove('exporting');
    });
  });

  // "Generate PDF" on the Revenue Reports page opens this view with ?auto=1
  // so the download starts without a second click.
  if (new URLSearchParams(location.search).get('auto') === '1') {
    window.addEventListener('load', function () { setTimeout(function () { btn.click(); }, 300); });
  }
})();
</script>
</body>
</html>
