<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['receptionist']);
$PAGE_TITLE = 'Appointments';
$ACTIVE = 'dashboard';
$PAGE_FULL = true;   // full-bleed content column (matches the admin screens)

$cardCls    = 'rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800';
$headCls    = 'mb-3.5 flex flex-wrap items-center justify-between gap-3';
$h2Cls      = 'flex items-center gap-2 text-[15px] text-navy-800 dark:text-steel-50';
$btnPrimary = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md bg-accent px-[15px] py-[9px] text-[13px] font-semibold text-white hover:bg-accent-dark';
$btnGhostSm = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md border border-steel-200 bg-transparent px-2.5 py-1.5 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
$tableCls   = 'w-full border-collapse text-[13px] '
    . "[&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 "
    . '[&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:align-middle [&_td]:dark:border-white/10 [&_tbody_tr:hover]:bg-steel-50 [&_tbody_tr:hover]:dark:bg-white/5';
$compactCls = 'w-full border-collapse text-xs '
    . "[&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-[7px] [&_th]:py-2 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:whitespace-nowrap [&_th]:dark:border-white/10 [&_th]:dark:bg-white/5 [&_th]:dark:text-steel-300 "
    . '[&_td]:border-b [&_td]:border-steel-100 [&_td]:px-[7px] [&_td]:py-2 [&_td]:align-top [&_td]:dark:border-white/10 [&_tbody_tr:hover]:bg-steel-50 [&_tbody_tr:hover]:dark:bg-white/5';

ob_start();
require __DIR__ . '/../../includes/patient_calendar_style.php';
require __DIR__ . '/../../includes/patient_booking_style.php'; // reuses the .pm-card payment-method picker for the walk-in reservation fee
?>
<div class="mb-[22px] flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-[22px] font-semibold text-navy-900 dark:text-steel-50">Appointments</h1></div>
    <button class="<?= $btnPrimary ?>" id="walkInBtn">+ Walk-in Booking</button>
</div>

<!-- Action Needed: Dentist Schedule Change (migration 080). Shown only while
     a dentist's schedule change has moved or flagged upcoming bookings. -->
<div class="<?= $cardCls ?> mb-4 !border-warn/60 border-l-4" id="chgCard" hidden>
    <div class="<?= $headCls ?>"><h2 class="<?= $h2Cls ?>">
        <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] text-warn" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>
        Action Needed: Dentist Schedule Change
        <span class="inline-block rounded-full bg-warn-soft px-2.5 py-[3px] text-[11px] font-bold text-warn dark:bg-warn/20" id="chgCount">0</span></h2></div>
    <div class="overflow-x-auto">
        <table class="rtable <?= $tableCls ?>" id="chgTable">
            <thead><tr><th>Patient</th><th>Phone</th><th>Date &amp; Time</th><th>Service</th><th>Dentist</th><th>Status</th><th>Flagged</th><th>Actions</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="<?= $cardCls ?>">
    <div class="<?= $headCls ?>"><h2 class="<?= $h2Cls ?>">Appointments for Approval
        <span class="inline-block rounded-full bg-warn-soft px-2.5 py-[3px] text-[11px] font-bold text-warn dark:bg-warn/20" id="reqCount">0</span></h2>
        <button class="<?= $btnGhostSm ?>" id="refreshBtn">Refresh</button></div>
    <div class="mb-3.5 flex flex-wrap items-center gap-2">
        <div class="relative w-full sm:w-[300px]">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-steel-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" id="af_q" autocomplete="off" aria-label="Search requests"
                   placeholder="Search patient, doctor, email or ref no." class="w-full py-2 pl-9 pr-3 rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50">
        </div>
        <select id="af_month" aria-label="Filter by month" class="rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50"><option value="">All months</option></select>
        <input type="date" id="af_date" aria-label="Filter by date" class="rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50">
        <button type="button" id="af_reset" class="inline-flex cursor-pointer items-center justify-center rounded-md border border-steel-200 bg-transparent px-2.5 py-2 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5">Reset Filters</button>
        <span id="af_count" class="text-xs text-steel-400"></span>
    </div>
    <div class="overflow-x-auto">
        <table class="rtable <?= $compactCls ?>" id="reqTable">
            <thead><tr>
                <th>#</th><th>Ref. No.</th><th>Email</th><th>Patient Name</th>
                <th>Relation</th><th>Age</th><th>Phone</th><th>Service</th><th>Doctor</th>
                <th>Date &amp; Time</th><th>Payment</th><th>Actions</th>
            </tr></thead>
            <tbody><tr><td colspan="12" class="py-7 text-center text-steel-400">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
    <nav id="af_pager" class="mt-4 flex flex-wrap items-center justify-between gap-3" aria-label="Table pages" hidden></nav>
</div>

<div class="<?= $cardCls ?> mt-4">
    <div class="<?= $headCls ?>"><h2 class="<?= $h2Cls ?>">Approved Appointments</h2></div>
    <div class="mb-3.5 flex flex-wrap items-center gap-2">
        <div class="relative w-full sm:w-[300px]">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-steel-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" id="sf_q" autocomplete="off" aria-label="Search appointments"
                   placeholder="Search patient or doctor" class="w-full py-2 pl-9 pr-3 rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50">
        </div>
        <select id="sf_month" aria-label="Filter by month" class="rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50"><option value="">All months</option></select>
        <input type="date" id="sf_date" aria-label="Filter by date" class="rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50">
        <button type="button" id="sf_reset" class="inline-flex cursor-pointer items-center justify-center rounded-md border border-steel-200 bg-transparent px-2.5 py-2 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5">Reset Filters</button>
        <span id="sf_count" class="text-xs text-steel-400"></span>
    </div>
    <div class="overflow-x-auto">
        <table class="rtable <?= $tableCls ?>" id="schedTable">
            <thead><tr><th>Date</th><th>Time</th><th>Patient</th><th>Service</th><th>Doctor</th><th>Booking</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody><tr><td colspan="8" class="py-7 text-center text-steel-400">Loading&hellip;</td></tr></tbody>
        </table>
    </div>
    <nav id="sf_pager" class="mt-4 flex flex-wrap items-center justify-between gap-3" aria-label="Table pages" hidden></nav>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  let dentists = [];
  let SERVICES = [];
  let ASSISTANTS = [];   // full roster {assistant_id, full_name, is_active, on_duty_today}

  const BTN      = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold disabled:opacity-50';
  const BTN_SM   = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold disabled:opacity-50';
  const PRIMARY  = ' bg-accent text-white hover:bg-accent-dark';
  const GHOST    = ' border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
  const DANGER   = ' bg-danger text-white hover:brightness-95';
  // Rescheduled rows get an amber View button so staff spot them at a glance.
  const isResched = (a) => a.status === 'rescheduled' || !!a.previous_scheduled_date;
  const RESCHED_BTN = ' border border-warn bg-warn text-white hover:brightness-95';
  const NEUTRAL  = ' bg-steel-100 text-navy-700 hover:brightness-95 dark:bg-white/10 dark:text-steel-100';
  const FG       = 'flex flex-col gap-1.5';
  const FGF      = 'flex flex-col gap-1.5 col-span-2';
  const LBL      = 'text-xs font-semibold text-steel-500 dark:text-steel-300';
  const REQ      = '<span class="text-danger"> *</span>';   // required-field marker
  const INP      = 'w-full rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
  const HINT     = 'text-[11px] text-steel-400';
  const BADGE    = 'inline-block rounded-full px-2.5 py-[3px] text-[11px] font-bold capitalize';
  const CLIP     = 'inline-block max-w-[150px] [overflow-wrap:anywhere] align-middle leading-tight';   // wraps to 2+ lines instead of ellipsis

  // Plain overflow-wrap:anywhere has no preferred break point in an email
  // address (no spaces/hyphens to break at), so the browser was splitting it
  // wherever it happened to run out of width — landing mid-domain (e.g.
  // "jedidiahvargas14@g" / "mail.com", slicing "gmail.com" itself in two).
  // Inserting a <wbr> right after "@" gives the browser a real, preferred
  // break opportunity there, so a wrap lands between the local part and the
  // domain (keeping "gmail.com" intact) whenever the local part alone is
  // short enough to fit — overflow-wrap:anywhere is still the fallback for
  // a local part too long to fit even on its own line.
  function emailCell(v) {
    if (!v) return 'N/A';
    const s = esc(v);
    const at = s.indexOf('@');
    return at === -1 ? s : s.slice(0, at + 1) + '<wbr>' + s.slice(at + 1);
  }
  const NW       = 'whitespace-nowrap';   // cells that must stay on one line (dates, phone, badges, actions)
  const GRID2    = 'grid grid-cols-2 gap-3.5';

  async function boot() {
    try { dentists = (await api('appointments.php?action=dentists')).dentists; } catch { dentists = []; }
    try { SERVICES = (await api('services.php?active=1')).services || []; } catch { SERVICES = []; }
    loadDuty();
    load(); loadSched();
  }

  /* ---- Dental assistants: today's roster (fallback for the check-in picker;
     assistants mark themselves on duty from their own dashboard) ---- */
  const onDutyAssistants = () => ASSISTANTS.filter((a) => +a.is_active && +a.on_duty_today);

  async function loadDuty() {
    try { ASSISTANTS = (await api('assistants.php?action=list')).assistants || []; }
    catch { ASSISTANTS = []; }
  }


  const METHODS = { bank: 'Bank Transfer', gcash: 'GCash', cash: 'Cash' };
  const RES_METHOD_SUB = { cash: 'Pay at the counter', gcash: 'Pay using your GCash wallet', bank: 'Pay via online banking' };
  const RECEIPT_BASE = '/dgt_dental_clinic/uploads/receipts/';

  // CASH / GCASH / BANK TRANSFER when a reservation payment is on file,
  // otherwise NOT PAID — an online booking always has one (reservation fee
  // required to submit); a walk-in only has one when the front desk chose to
  // collect a reservation fee/downpayment at booking (optional, see
  // walkInModal()'s "Collect a reservation fee now" toggle).
  function payMethodBadge(a) {
    if (!a.reservation_method) return '<span class="' + BADGE + ' bg-danger-soft text-danger dark:bg-danger/20">NOT PAID</span>';
    const label = (METHODS[a.reservation_method] || a.reservation_method_label || a.reservation_method).toUpperCase();   // label = admin-added method (migration 078)
    return '<span class="' + BADGE + ' bg-ok-soft text-ok dark:bg-ok/20">' + esc(label) + '</span>';
  }

  const relationshipLabel = (a) =>
    a.booking_for === 'other' ? (a.relationship ? a.relationship : 'Other') : 'Self';

  // Appointments for Approval filters (same controls as Approved Appointments).
  let REQS = null;
  // 10 rows per page on both tables (2026-10-17).
  const PER_PAGE = 10;
  const AF_PG = { page: 1 }, SF_PG = { page: 1 };
  async function load() {
    const tb = document.querySelector('#reqTable tbody');
    try {
      REQS = (await api('appointments.php?filter=online_queue')).appointments || [];
      document.getElementById('reqCount').textContent = REQS.length;
      fillMonthOptions('af_month', REQS);
      renderReq();
    } catch (e) { tb.innerHTML = '<tr><td colspan="12" class="py-7 text-center text-steel-400">' + esc(e.message) + '</td></tr>'; }
  }
  function renderReq() {
    const tb = document.querySelector('#reqTable tbody');
    if (!tb || !REQS) return;
    const filtering = filtersOn('af');
    const appointments = applyFilters('af', REQS, (a) => [a.patient_name, a.dentist_name, a.requested_service, a.account_email, a.patient_phone, a.account_phone, a.reservation_reference]);
    document.getElementById('af_count').textContent = filtering ? appointments.length + ' of ' + REQS.length + ' shown' : '';
    const pg = window.pageSlice(appointments, AF_PG, PER_PAGE, document.getElementById('af_pager'), renderReq);
    {
      tb.innerHTML = appointments.length ? pg.rows.map((a, i) =>
        '<tr>' +
        '<td class="rt-hide ' + NW + '">' + (pg.from + i + 1) + '</td>' +
        '<td' + (a.reservation_reference ? '' : ' class="rt-hide"') + '>' + esc(a.reservation_reference || 'N/A') + '</td>' +
        '<td class="rt-email"><span class="' + CLIP + '" title="' + esc(a.account_email || '') + '">' + emailCell(a.account_email) + '</span></td>' +
        '<td class="rt-title min-w-[104px]"><strong>' + esc(a.patient_name) + '</strong></td>' +
        '<td' + (a.booking_for === 'other' ? '' : ' class="rt-hide"') + '>' + esc(relationshipLabel(a)) + '</td>' +
        '<td class="' + NW + '">' + (a.patient_age ? esc(a.patient_age) : 'N/A') + '</td>' +
        '<td class="' + NW + '">' + esc(a.patient_phone || a.account_phone || 'N/A') + '</td>' +
        '<td><span class="' + CLIP + '" title="' + esc(a.requested_service || '') + '">' + esc(a.requested_service || 'N/A') + '</span></td>' +
        '<td>' + esc(a.dentist_name || 'Unassigned') + '</td>' +
        '<td class="' + NW + '">' + esc(String(a.scheduled_date).slice(0, 10)) + '<div class="mt-0.5 font-semibold text-dgt-blue-700 dark:text-dgt-blue-300">' + esc(fmtTime(a.time_slot)) + '</div></td>' +
        '<td class="' + NW + '">' + payMethodBadge(a) + '</td>' +
        // A row with a receipt shows only View; Approve / Decline are inside the
        // receipt pop-up so staff check the screenshot first (2026-10-17).
        '<td class="' + NW + '">' + (a.reservation_receipt
          ? '<button class="' + BTN_SM + (isResched(a) ? RESCHED_BTN : GHOST) + '" data-receipt="' + esc(a.reservation_receipt) + '" data-appt="' + a.appointment_id + '">View</button>'
          // No screenshot (screenshot upload turned off): verify the Ref. No.
          : (a.booking_type === 'online' && a.reservation_reference
              ? '<div class="mb-1 text-[11px] font-semibold text-warn">No screenshot, verify Ref. No.</div>' : '') +
            '<div class="flex flex-nowrap gap-1.5">' +
            '<button class="' + BTN_SM + PRIMARY + '" data-confirm="' + a.appointment_id + '">Approve</button>' +
            '<button class="' + BTN_SM + DANGER + '" data-decline="' + a.appointment_id + '">Decline</button>' +
            '</div>') +
        '</td></tr>'
      ).join('') : '<tr><td colspan="12" class="py-7 text-center text-steel-400">' +
        (filtering ? 'No requests match your filters.' : 'No requests waiting for approval.') + '</td></tr>';
    }
  }

  function receiptModal(file, apptId) {
    // A GCash receipt screenshot is tall/portrait — scaling it to the modal's
    // full width (the old `w-full`) made it far taller than the viewport and
    // forced a scroll just to see the whole thing. Capping the height instead
    // (object-contain preserves aspect ratio) fits the whole receipt on
    // screen at once; no need for the wide modal either, since a portrait
    // image doesn't benefit from extra width.
    openModal(modalShell('Payment Receipt',
      '<img src="' + RECEIPT_BASE + encodeURIComponent(file) + '" alt="Receipt" class="mx-auto block max-h-[calc(62vh/var(--uiz,1))] w-auto max-w-full rounded-lg object-contain">',
      apptId
        ? '<button class="' + BTN + GHOST + '" data-close>Close</button>' +
          '<button class="' + BTN + DANGER + '" id="rc_decline">Decline</button>' +
          '<button class="' + BTN + PRIMARY + '" id="rc_approve">Approve</button>'
        : ''));
    if (!apptId) return;
    document.getElementById('rc_approve').addEventListener('click', () => confirmModal(apptId));
    document.getElementById('rc_decline').addEventListener('click', () => declinePaymentModal(apptId));
  }

  /* ---------- Decline just the online reservation receipt (2026-09) ----------
     The only Decline flow (online bookings only). It does NOT cancel the appointment:
     it flips status to Payment Declined, keeping the same date/time/service,
     so the patient can correct the reference number/receipt and resubmit
     instead of booking again from scratch. Same two-step confirm pattern as
     every other status-changing action in this app. */
  const PAYMENT_DECLINE_REASONS = [
    ['blurry_image', 'Blurry Image'],
    ['reference_mismatch', 'Reference Code Mismatch'],
    ['invalid_screenshot', 'Invalid Screenshot'],
    ['other', 'Other'],
  ];

  function declinePaymentModal(id, prevCode, prevDetail) {
    openModal(modalShell('Decline Payment',
      '<div class="' + FGF + '"><label class="' + LBL + '">Reason (the patient will see this)</label>' +
      '<select class="' + INP + '" id="dp_code">' +
      PAYMENT_DECLINE_REASONS.map(([code, label]) =>
        '<option value="' + code + '"' + (code === prevCode ? ' selected' : '') + '>' + esc(label) + '</option>').join('') +
      '</select></div>' +
      '<div class="' + FGF + '"><label class="' + LBL + '">Additional detail (optional, shown to the patient)</label>' +
      '<textarea class="' + INP + ' min-h-[64px] resize-y" id="dp_detail" placeholder="e.g. the last 4 digits don\'t match what we have on file.">' + esc(prevDetail || '') + '</textarea></div>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + DANGER + '" id="dp_go">Decline Payment</button>'));
    document.getElementById('dp_go').addEventListener('click', () => {
      const code = document.getElementById('dp_code').value;
      const detail = document.getElementById('dp_detail').value.trim();
      if (code === 'other' && !detail) return toast('Please describe the issue with this receipt.', 'err');
      declinePaymentModalStep2(id, code, detail);
    });
  }

  function declinePaymentModalStep2(id, code, detail) {
    const label = (PAYMENT_DECLINE_REASONS.find((r) => r[0] === code) || [null, code])[1];
    openModal(modalShell('Are You Sure?',
      '<div class="' + window.alertCls('error') + ' !mt-0">' +
      '<strong>This does not cancel the appointment.</strong> Confirming will:' +
      '<ul class="mt-1.5 list-disc space-y-1 pl-4 text-[12.5px]">' +
      '<li>Mark this request’s payment as declined and pause it from the approval queue.</li>' +
      '<li>Notify the patient of the reason so they can correct their reference number/receipt.</li>' +
      '<li>Once the patient resubmits, it reappears here automatically for approval. The same date, time and service are kept.</li>' +
      '</ul></div>' +
      '<p class="' + HINT + ' mt-2.5">Reason given: &ldquo;' + esc(label) + (detail ? ': ' + detail : '') + '&rdquo;</p>',
      '<button class="' + BTN + GHOST + '" id="dp_back">No, Go Back</button>' +
      '<button class="' + BTN + DANGER + '" id="dp_final">Yes, Decline Payment</button>'));
    document.getElementById('dp_back').addEventListener('click', () => declinePaymentModal(id, code, detail));
    document.getElementById('dp_final').addEventListener('click', async () => {
      try {
        await api('appointments.php?action=decline_payment', { method: 'POST', body: { appointment_id: id, reason_code: code, reason: detail } });
        closeModal(); toast('Payment declined. The patient can now resubmit.'); load(); loadSched();
      } catch (e) { toast(e.message, 'err'); }
    });
  }

  /* ---------- Cancel an appointment on the patient's behalf (reason required) ----------
     Cancelling forfeits the patient's reservation fee (non-refundable). A visit
     that will still happen should be rescheduled instead. Separate from Decline,
     which only rejects a still-pending request. */
  function cancelModal(id, prevReason) {
    openModal(modalShell('Cancel Appointment',
      '<div class="' + FGF + '"><label class="' + LBL + '">Reason for cancellation (the patient will see this)</label>' +
      '<textarea class="' + INP + ' min-h-[72px] resize-y" id="c_reason" placeholder="e.g. Dentist unavailable that day, patient asked us to cancel.">' + esc(prevReason || '') + '</textarea></div>',
      '<button class="' + BTN + GHOST + '" data-close>Keep Appointment</button><button class="' + BTN + DANGER + '" id="c_go">Cancel Appointment</button>'));
    document.getElementById('c_go').addEventListener('click', () => {
      const reason = document.getElementById('c_reason').value.trim();
      if (!reason) return toast('Please give a reason for the cancellation.', 'err');
      cancelModalStep2(id, reason);
    });
  }

  // Second, final confirmation — same "spell out what happens before it's
  // irreversible" pattern as declineModalStep2 above.
  function cancelModalStep2(id, reason) {
    openModal(modalShell('Are You Absolutely Sure?',
      '<div class="' + window.alertCls('error') + ' !mt-0">' +
      '<strong>This cannot be undone.</strong> Confirming will:' +
      '<ul class="mt-1.5 list-disc space-y-1 pl-4 text-[12.5px]">' +
      '<li>Cancel this appointment immediately and notify the patient.</li>' +
      '<li>Forfeit the patient\'s reservation fee: it is <strong>not</strong> refunded or carried over.</li>' +
      '<li>Free up this time slot for other bookings.</li>' +
      '</ul></div>' +
      '<p class="' + HINT + ' mt-2.5">Reason given: &ldquo;' + esc(reason) + '&rdquo;</p>',
      '<button class="' + BTN + GHOST + '" id="c_back">No, Go Back</button>' +
      '<button class="' + BTN + DANGER + '" id="c_final">Yes, Cancel It</button>'));
    document.getElementById('c_back').addEventListener('click', () => cancelModal(id, reason));
    document.getElementById('c_final').addEventListener('click', async () => {
      try {
        await api('appointments.php?action=cancel', { method: 'POST', body: { appointment_id: id, reason } });
        closeModal(); toast('Appointment cancelled.'); load(); loadSched();
      } catch (e) { toast(e.message, 'err'); }
    });
  }

  // No Show (manual): offered once the patient is Delayed / Standby (not
  // checked in 15 minutes after the start) or the day is over. The
  // end-of-day batch marks anyone left after closing (migration 088).
  function noShowModal(id, name) {
    openModal(modalShell('Mark as No Show',
      '<p class="text-[13px]">' + esc(name) + ' did not check in for this appointment.</p>' +
      '<div class="' + window.alertCls('error') + ' mt-2.5 !mb-0">Confirming will:' +
      '<ul class="mt-1.5 list-disc space-y-1 pl-4 text-[12.5px]">' +
      '<li>Mark the appointment as <strong>No Show</strong>.</li>' +
      '<li>Forfeit the reservation fee, the same as a cancellation.</li>' +
      '<li>Notify the patient that they missed the appointment.</li>' +
      '</ul></div>',
      '<button class="' + BTN + GHOST + '" data-close>Go Back</button><button class="' + BTN + DANGER + '" id="ns_go">Yes, Mark No Show</button>'));
    document.getElementById('ns_go').addEventListener('click', async () => {
      try {
        await api('appointments.php?action=no_show', { method: 'POST', body: { appointment_id: id } });
        closeModal(); toast('Marked as No Show.'); loadSched();
      } catch (e) { toast(e.message, 'err'); }
    });
  }

  const isToday = (d) => String(d).slice(0, 10) === todayISO();
  const nowHm = () => { const d = new Date(); return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); };
  const addHour = (s) => String((+String(s).slice(0, 2) + 1) % 24).padStart(2, '0') + ':00';   // slot end = next full hour

  // Approved Appointments filters: text (patient / doctor / service), month,
  // single date and Reset. They filter the last fetch locally, so they stay
  // applied when the table reloads after an action or a notification.
  let SCHED = null;
  const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  function fillMonthOptions(selId, list) {
    const sel = document.getElementById(selId);
    if (!sel || !list) return;
    const keep = sel.value;
    const keys = [...new Set(list.map((a) => String(a.scheduled_date).slice(0, 7)))].sort().reverse();
    sel.innerHTML = '<option value="">All months</option>' + keys.map((k) =>
      '<option value="' + k + '">' + MONTHS[+k.slice(5, 7) - 1] + ' ' + k.slice(0, 4) + '</option>').join('');
    if (keys.includes(keep)) sel.value = keep;
  }
  const filtersOn = (px) => !!(document.getElementById(px + '_q').value.trim() || document.getElementById(px + '_month').value || document.getElementById(px + '_date').value);
  function applyFilters(px, list, fields) {
    const q = (document.getElementById(px + '_q').value || '').trim().toLowerCase();
    const m = document.getElementById(px + '_month').value;
    const d = document.getElementById(px + '_date').value;
    return list.filter((a) => {
      const day = String(a.scheduled_date).slice(0, 10);
      if (d && day !== d) return false;
      if (m && day.slice(0, 7) !== m) return false;
      if (q && !fields(a).some((v) => String(v || '').toLowerCase().includes(q))) return false;
      return true;
    });
  }
  // Today's visits first (by time), then upcoming days (soonest first), then
  // past days still open (most recent first) (2026-10-17).
  function sortSched(list) {
    const today = todayISO();
    const key = (a) => String(a.scheduled_date).slice(0, 10);
    const grp = (a) => (key(a) === today ? 0 : key(a) > today ? 1 : 2);
    return list.slice().sort((x, y) => {
      const g = grp(x) - grp(y);
      if (g) return g;
      const dx = key(x), dy = key(y), tx = String(x.time_slot || ''), ty = String(y.time_slot || '');
      if (dx !== dy) return grp(x) === 2 ? (dx < dy ? 1 : -1) : (dx < dy ? -1 : 1);
      return tx < ty ? -1 : tx > ty ? 1 : 0;
    });
  }
  /* ---- Action Needed: Dentist Schedule Change (migration 080) ----
     Bookings a dentist's schedule change moved to a roaming dentist
     ('reassigned', for review) or left without a time ('needs_reschedule').
     A row still waiting after 3 hours is marked for a phone call. */
  function agoText(m) {
    m = +m || 0;
    if (m < 1) return 'just now';
    if (m < 60) return m + ' min ago';
    const h = Math.floor(m / 60);
    return h < 24 ? h + ' hr' + (h === 1 ? '' : 's') + ' ago' : Math.floor(h / 24) + ' day' + (h >= 48 ? 's' : '') + ' ago';
  }
  async function loadChg() {
    const card = document.getElementById('chgCard');
    if (!card) return;
    let items = [];
    try { items = (await api('appointments.php?action=sched_changes')).items || []; } catch (_) { items = []; }
    card.hidden = !items.length;
    document.getElementById('chgCount').textContent = items.filter((r) => r.sched_change_status === 'needs_reschedule').length || items.length;
    document.querySelector('#chgTable tbody').innerHTML = items.map((r) => {
      const needs = r.sched_change_status === 'needs_reschedule';
      const call = needs && +r.minutes_ago >= 180;
      const phone = r.patient_phone || r.account_phone || '';
      const from = r.from_dentist_name ? 'Dr. ' + r.from_dentist_name : '';
      return '<tr>' +
        '<td><strong class="text-navy-800 dark:text-steel-50">' + esc(r.patient_name) + '</strong> ' + srcBadge(r.booking_type) +
          (r.status === 'pending' ? ' ' + badge('pending') : '') + '</td>' +
        '<td class="' + NW + '">' + (phone ? '<a class="text-accent-dark hover:underline" href="tel:' + esc(phone) + '">' + esc(phone) + '</a>' : 'N/A') + '</td>' +
        '<td class="' + NW + '">' + esc(fmtDate(r.scheduled_date)) + '<div class="text-[11px] text-steel-500 dark:text-steel-400">' + esc(fmtTime(r.time_slot)) + '</div></td>' +
        '<td>' + esc(r.requested_service || '') + '</td>' +
        '<td>' + (needs
            ? esc(from) + '<div class="text-[11px] text-steel-500 dark:text-steel-400">not working this hour</div>'
            : '<span class="text-steel-500 line-through dark:text-steel-400">' + esc(from) + '</span><div class="font-semibold text-navy-800 dark:text-steel-50">Dr. ' + esc(r.dentist_name || '') + '</div>') + '</td>' +
        '<td class="' + NW + '">' + (needs
            ? badgeHTML('Needs new time', 'pending')
            : badgeHTML('Moved to roaming', 'approved')) +
          (call ? '<div class="mt-1 text-[11px] font-semibold text-danger">Call the patient</div>' : '') + '</td>' +
        '<td class="' + NW + ' text-steel-500 dark:text-steel-400">' + esc(agoText(r.minutes_ago)) + '</td>' +
        '<td><div class="flex flex-wrap gap-1.5">' +
          (needs && !r.treatment_plan_id ? '<button class="' + BTN_SM + PRIMARY + '" data-assign="' + r.appointment_id + '">Reassign</button>' : '') +
          '<button class="' + BTN_SM + GHOST + '" data-resched="' + r.appointment_id + '">Reschedule</button>' +
          '<button class="' + BTN_SM + GHOST + '" data-chg-flag="' + r.appointment_id + '" data-name="' + esc(r.patient_name) + '" data-again="' + (needs ? 1 : 0) + '">' +
            (needs ? 'Remind Patient' : 'Flag for Reschedule') + '</button>' +
          (!needs ? '<button class="' + BTN_SM + GHOST + '" data-chg-ok="' + r.appointment_id + '">Mark Reviewed</button>' : '') +
          '<button class="' + BTN_SM + GHOST + '" data-view="' + r.appointment_id + '">View</button>' +
        '</div></td></tr>';
    }).join('');
  }
  document.getElementById('chgTable').addEventListener('click', async (e) => {
    const a = e.target.closest('[data-assign]'), r = e.target.closest('[data-resched]');
    const f = e.target.closest('[data-chg-flag]'), ok = e.target.closest('[data-chg-ok]');
    if (a) return assignModal(+a.dataset.assign, '');
    if (r) return reschedModal(+r.dataset.resched);
    if (ok) {
      try { await api('appointments.php?action=sched_dismiss', { method: 'POST', body: { appointment_id: +ok.dataset.chgOk } }); toast('Marked as reviewed.'); loadChg(); }
      catch (err) { toast(err.message, 'err'); }
      return;
    }
    if (f) {
      const again = f.dataset.again === '1';
      openModal(modalShell(again ? 'Remind Patient' : 'Flag for Reschedule',
        '<p class="text-[13px] text-navy-800 dark:text-steel-100">' + (again
          ? 'Send <strong>' + esc(f.dataset.name) + '</strong> another notice (in-app and e-mail) to pick a new date and time?'
          : 'Unlock this appointment on <strong>' + esc(f.dataset.name) + '</strong>\'s portal so they can pick a new date and time? They get an in-app notice and an e-mail.') + '</p>',
        '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + PRIMARY + '" id="chgFlagGo">' + (again ? 'Send Reminder' : 'Flag for Reschedule') + '</button>'));
      document.getElementById('chgFlagGo').addEventListener('click', async () => {
        try {
          await api('appointments.php?action=sched_flag', { method: 'POST', body: { appointment_id: +f.dataset.chgFlag } });
          closeModal(); toast(again ? 'Reminder sent.' : 'The patient can now pick a new time.'); loadChg();
        } catch (err) { toast(err.message, 'err'); }
      });
    }
  });

  async function loadSched() {
    loadChg();
    const tb = document.querySelector('#schedTable tbody');
    try {
      SCHED = sortSched((await api('appointments.php?filter=approved')).appointments || []);
      fillMonthOptions('sf_month', SCHED);
      renderSched();
    } catch (e) { tb.innerHTML = '<tr><td colspan="8" class="py-7 text-center text-steel-400">' + esc(e.message) + '</td></tr>'; }
  }
  function renderSched() {
    const tb = document.querySelector('#schedTable tbody');
    if (!tb || !SCHED) return;
    const filtering = filtersOn('sf');
    const appointments = applyFilters('sf', SCHED, (a) => [a.patient_name, a.dentist_name, a.requested_service]);
    document.getElementById('sf_count').textContent = filtering ? appointments.length + ' of ' + SCHED.length + ' shown' : '';
    const pg = window.pageSlice(appointments, SF_PG, PER_PAGE, document.getElementById('sf_pager'), renderSched);
    {
      tb.innerHTML = appointments.length ? pg.rows.map((a) => {
        // Check-in is only offered for today's approved visits. It marks the
        // patient as arrived (moves them from "Today's Schedule" to "Ongoing
        // Patients" on the admin dashboard) and strictly gates the dentist's
        // "Perform Procedure" button; it never changes the status badge.
        const checkBtn = a.checked_in_at
          ? '<button class="' + BTN_SM + GHOST + '" data-undocheckin="' + a.appointment_id + '">Undo check-in</button>'
          : (isToday(a.scheduled_date)
              ? '<button class="' + BTN_SM + PRIMARY + '" data-checkin="' + a.appointment_id + '" data-slot="' + esc(String(a.time_slot || '').slice(0, 5)) + '" data-name="' + esc(a.patient_name) + '"' + (a.delayed_at ? ' data-delayed="1"' : '') + '>Check In</button>'
              : '');
        const asstLine = a.checkin_assistant_name
          ? '<div class="' + HINT + '">Asst: ' + esc(a.checkin_assistant_name) + '</div>' : '';
        return '<tr><td class="whitespace-nowrap">' + esc(String(a.scheduled_date).slice(0, 10)) + '</td><td class="whitespace-nowrap">' + fmtTime(a.time_slot) + '</td><td class="rt-title">' + esc(a.patient_name) + asstLine +
          '</td><td>' + esc(a.requested_service || 'N/A') + '</td>' +
          '<td>' + esc(a.dentist_name || 'Unassigned') +
          (!a.dentist_id && a.oncall_declined_at
            ? '<div class="mt-0.5 text-[11px] font-semibold text-danger">Declined by Dr. ' + esc(a.oncall_declined_by_name || '') +
              (a.oncall_decline_reason ? ': ' + esc(a.oncall_decline_reason) : '') + '</div>'
            // Already moved to another dentist / time after a roaming decline.
            : a.dentist_id && a.oncall_declined_at
              ? '<div class="mt-0.5 text-[11px] font-semibold text-warn">Roaming Dr. ' + esc(a.oncall_declined_by_name || '') + ' declined</div>' : '') + '</td>' +
          '<td>' + srcBadge(a.booking_type) + '</td>' +
          '<td>' + apptStatusBadge(a) + '</td>' +
          '<td><div class="flex flex-nowrap gap-1.5 [&_button]:whitespace-nowrap"><button class="' + BTN_SM + (isResched(a) ? RESCHED_BTN : GHOST) + '" data-view="' + a.appointment_id + '">View</button>' +
          checkBtn +
          (!a.checked_in_at && (a.delayed_at || String(a.scheduled_date).slice(0, 10) < todayISO())
            ? '<button class="' + BTN_SM + DANGER + '" data-noshow="' + a.appointment_id + '" data-name="' + esc(a.patient_name) + '">No Show</button>' : '') +
          '<button class="' + BTN_SM + NEUTRAL + '" data-assign="' + a.appointment_id + '" data-current="' + (a.dentist_id || '') + '">Reassign</button>' +
          '<button class="' + BTN_SM + NEUTRAL + '" data-resched="' + a.appointment_id + '">Reschedule</button>' +
          '<button class="' + BTN_SM + DANGER + '" data-cancel="' + a.appointment_id + '">Cancel</button></div></td></tr>';
      }).join('') : '<tr><td colspan="8" class="py-7 text-center text-steel-400">' +
        (filtering ? 'No appointments match your filters.' : 'Nothing approved.') + '</td></tr>';
    }
  }
  function wireFilters(px, rerender) {
    const el = (k) => document.getElementById(px + '_' + k);
    let t = 0;
    el('q').addEventListener('input', () => { clearTimeout(t); t = setTimeout(rerender, 150); });
    // Month and date narrow the same thing, so picking one clears the other.
    el('month').addEventListener('change', () => { el('date').value = ''; rerender(); });
    el('date').addEventListener('change', () => { el('month').value = ''; rerender(); });
    el('reset').addEventListener('click', () => { ['q', 'month', 'date'].forEach((k) => { el(k).value = ''; }); rerender(); });
  }
  // A filter change goes back to page 1.
  wireFilters('sf', () => { SF_PG.page = 1; renderSched(); });
  wireFilters('af', () => { AF_PG.page = 1; renderReq(); });

  async function setCheckIn(id, on, assistantId, noAssistant, seat) {
    try {
      await api('appointments.php?action=' + (on ? 'check_in' : 'undo_check_in'),
        { method: 'POST', body: { appointment_id: id, assistant_id: assistantId || 0, no_assistant: noAssistant ? 1 : 0, seat_slot: seat || '' } });
      toast(on ? (seat ? 'Patient checked in as Arrived (Late), seated at ' + fmtTime(seat) + '.' : 'Patient checked in.') : 'Check-in undone.');
      loadSched();
    } catch (e) { toast(e.message, 'err'); }
  }

  // Check-in modal: confirm the patient arrived and optionally tag the assisting
  // staff member (from today's on-duty roster) for shift / workload reporting.
  async function checkInModal(id, name, slot, delayed) {
    // Re-fetch on-duty assistants fresh, rather than trusting the page's
    // ASSISTANTS snapshot from load time — duty status can change elsewhere
    // (another tab, the admin dashboard's toggle) without this page refreshing,
    // and offering a since-off-duty assistant here just gets rejected by the
    // server at submit time. Same pattern as the dentist's logProcedureModal.
    let duty;
    try { duty = (await api('assistants.php?action=on_duty')).assistants || []; }
    catch { duty = onDutyAssistants(); }
    const picker = duty.length
      ? '<select class="' + INP + '" id="ci_asst"><option value="0">(None)</option>' +
          duty.map((a) => '<option value="' + a.assistant_id + '">' + esc(a.full_name) + '</option>').join('') +
        '</select>'
      : '<p class="' + HINT + '">No assistant dentists are marked on duty today. You can still check the patient in.</p>';
    // Arrived (Late), migration 088: a Delayed / Standby patient's seat was
    // released to walk-ins, so they are seated in an hour today that still
    // has room (the one in progress or a later one). No room = reschedule.
    let late = '', full = false, seats = [];
    if (delayed) {
      try {
        const t = todayISO();
        const r = await api('appointments.php?action=availability&from=' + t + '&to=' + t + '&walkin=1');
        const day = (r.days || {})[t] || {};
        const now = nowHm();
        seats = (r.slots || []).filter((s) => addHour(s) > now && ((day.left || {})[s] ?? 0) > 0);
      } catch { seats = []; }
      full = !seats.length;
      late = full
        ? '<div class="' + window.alertCls('error') + ' !mb-0 mt-2.5">This patient was booked for ' + fmtTime(slot) + ' and is on Standby. No hour left today has room, so please reschedule them.</div>'
        : '<div class="' + window.alertCls('warn') + ' !mb-0 mt-2.5">This patient was booked for ' + fmtTime(slot) + ' and arrived late, so their slot was released. They will be checked in as <strong>Arrived (Late)</strong> and seated in the hour below.</div>' +
          '<div class="' + FG + ' mt-2.5"><label class="' + LBL + '">Seat in</label><select class="' + INP + '" id="ci_seat">' +
          seats.map((s) => '<option value="' + s + '">' + fmtTime(s) + ' – ' + fmtTime(addHour(s)) + '</option>').join('') + '</select></div>';
    }
    openModal(modalShell('Check In: ' + esc(name || 'patient'),
      (full ? '' : '<div class="' + FG + '"><label class="' + LBL + '">Assisting Staff</label>' + picker + '</div>') + late,
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button>' +
      (full ? '<button class="' + BTN + PRIMARY + '" id="ci_resched">Reschedule</button>'
            : '<button class="' + BTN + PRIMARY + '" id="ci_go">Check In</button>')));
    if (full) {
      document.getElementById('ci_resched').addEventListener('click', () => { closeModal(); setTimeout(() => reschedModal(id), 320); });
      return;
    }
    document.getElementById('ci_go').addEventListener('click', async () => {
      const asst = document.getElementById('ci_asst') ? +document.getElementById('ci_asst').value : 0;
      const none = !!document.getElementById('ci_asst') && asst === 0;
      const seat = document.getElementById('ci_seat') ? document.getElementById('ci_seat').value : '';
      closeModal();
      await setCheckIn(id, true, asst, none, seat);
    });
  }

  // Every option is labelled with the doctor type. With no explicit selection the
  // Owner Dentist is pre-selected (online bookings + walk-ins default to them).
  // `off` (optional) maps user_id -> label for a dentist NOT selectable for the
  // relevant date + time — either off their working schedule, or (2026-09)
  // already holding their share of the hour (1 of the 2 places, or both when the other type is off);
  // either way the option is disabled and shows why.
  // A value of `true` (walk-in only: a roaming dentist while the in-house
  // dentist still has room that hour) hides the option entirely; it shows
  // again once the in-house dentist is full or off duty.
  const dentistOptions = (sel, off) => dentists.filter((d) => !(off && off[d.user_id] === true)).map((d) => {
    const gone = off && off[d.user_id];
    const chosen = !gone && (sel != null ? +sel === d.user_id : d.doctor_type === 'owner');
    return '<option value="' + d.user_id + '"' + (chosen ? ' selected' : '') + (gone ? ' disabled' : '') + '>' +
      esc(d.full_name) + ' · ' + (d.doctor_type === 'owner' ? 'In-House' : 'Roaming') +
      (gone && gone !== true ? ' · ' + esc(gone) : '') + '</option>';
  }).join('');

  // Which dentists are off their working schedule for a date (+ optional slot),
  // or — when a slot is given — already personally at the shared per-slot
  // capacity (slot_full, from action=dentists). excludeId leaves the
  // appointment being edited out of that count, so a dentist already assigned
  // to it isn't locked out of their own appointment.
  async function dutyMapFor(date, slot, excludeId, walkIn) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return {};
    try {
      const q = 'appointments.php?action=dentists&date=' + date
        + (slot ? '&slot=' + encodeURIComponent(slot) : '')
        + (excludeId ? '&exclude_id=' + excludeId : '');
      const r = await api(q);
      const m = {};
      (r.dentists || []).forEach((d) => {
        if (!d.on_duty) { m[d.user_id] = slot ? 'Off duty' : 'Not working'; }
        else if (d.slot_full) { m[d.user_id] = 'Full (' + d.slot_count + ' patients already booked this hour)'; }
        else if (walkIn && d.roaming_locked) { m[d.user_id] = true; }
      });
      return m;
    } catch (_) { return {}; }
  }

  async function confirmModal(id) {
    let appt = null;
    try { appt = (await api('appointments.php?action=get&id=' + id)).appointment; } catch (_) { /* no filter */ }
    // Same waterfall as the walk-in picker: a roaming dentist is hidden while the
    // in-house dentist still has room that hour, unless the booking was already
    // routed to them (then they stay listed so the pop-up isn't left empty).
    const off = appt ? await dutyMapFor(appt.scheduled_date, appt.time_slot, id, true) : {};
    if (appt && appt.dentist_id && off[appt.dentist_id] === true) delete off[appt.dentist_id];
    const locked = !!(appt && appt.treatment_plan_id && appt.dentist_id);
    openModal(modalShell('Approve Appointment',
      '<div class="' + FG + '"><label class="' + LBL + '">Assign Dentist</label><select class="' + INP + '" id="c_dentist"' + (locked ? ' disabled' : '') + '>' +
      (locked
        ? '<option value="' + appt.dentist_id + '" selected>' + esc(appt.dentist_name || '') + '</option>'
        : dentistOptions(appt && appt.dentist_id ? String(appt.dentist_id) : null, off)) + '</select>' +
      '<span class="' + HINT + '">' + (locked
        ? 'Follow-up recommended by this dentist. It stays with them.'
        : 'Only dentists on duty, with room left this hour, are selectable. Defaults to the in-house doctor.') + '</span></div>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + PRIMARY + '" id="c_go">Approve</button>'));
    document.getElementById('c_go').addEventListener('click', async () => {
      const body = { appointment_id: id, dentist_id: +document.getElementById('c_dentist').value };
      try {
        await api('appointments.php?action=confirm', { method: 'POST', body });
        closeModal(); toast('Appointment approved.'); load(); loadSched();
      } catch (e) { toast(e.message, 'err'); }
    });
  }

  async function assignModal(id, current) {
    let appt = null;
    try { appt = (await api('appointments.php?action=get&id=' + id)).appointment; } catch (_) { /* no filter */ }
    const off = appt ? await dutyMapFor(appt.scheduled_date, appt.time_slot, id) : {};
    // The dentist who declined this on-call assignment isn't offered it back.
    if (appt && appt.oncall_declined_by && !appt.dentist_id) off[appt.oncall_declined_by] = 'Declined this assignment';
    if (appt && appt.treatment_plan_id && appt.dentist_id) {
      openModal(modalShell('Assign / Reassign Dentist',
        '<p>This is a follow-up that <strong>Dr. ' + esc(appt.dentist_name || '') + '</strong> recommended, so it stays with them. ' +
        'If the time does not work, reschedule it instead.</p>',
        '<button class="' + BTN + GHOST + '" data-close>Close</button>'));
      return;
    }
    openModal(modalShell('Assign / Reassign Dentist',
      '<div class="' + FGF + '"><label class="' + LBL + '">Dentist</label><select class="' + INP + '" id="a_dentist">' + dentistOptions(current || null, off) + '</select>' +
      '<span class="' + HINT + '">Only dentists on duty, with room left this hour, are selectable.</span></div>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + PRIMARY + '" id="a_go">Save</button>'));
    document.getElementById('a_go').addEventListener('click', async () => {
      try {
        await api('appointments.php?action=assign', { method: 'POST', body: {
          appointment_id: id, dentist_id: +document.getElementById('a_dentist').value,
        }});
        closeModal(); toast('Dentist reassigned.'); load(); loadSched(); window.notifChanged && window.notifChanged();
      } catch (e) { toast(e.message, 'err'); }
    });
  }

  // A full month-grid calendar picker inside the modal — the same green/amber/
  // red/hatched "Slots open / Full Schedule / Clinic closed"
  // language as the patient Calendar View page (includes/patient_calendar_style.php's
  // .pcal-* widget, required into this page above), scoped to the
  // appointment's own dentist via action=availability&dentist_id= (a
  // reschedule can belong to a roaming dentist with a different working week
  // than the online-booking Owner). Staff have no lead-time floor, so
  // minDateKey is just today. The compact `.rs-pcal` modifier in that same
  // stylesheet shrinks the grid to fit a dialog instead of a full page.
  function reschedCalHtml() {
    return '<div class="rs-pcal rounded-md border border-steel-200 p-3 dark:border-white/15">' +
      '<div class="pcal-toolbar"><button type="button" class="pcal-nav-btn" id="r_cal_prev">&lsaquo;</button>' +
      '<h2 id="r_cal_month">&nbsp;</h2><button type="button" class="pcal-nav-btn" id="r_cal_next">&rsaquo;</button><button type="button" class="pcal-today-btn inline-flex cursor-pointer items-center justify-center rounded-md border border-steel-200 bg-transparent px-2.5 py-1.5 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5" id="r_cal_today">Today</button></div>' +
      '<div class="pcal-legend"><span><i class="lg-open"></i> Available</span>' +
      '<span><i class="lg-full"></i> Fully Booked</span><span><i class="lg-closed"></i> Closed</span></div>' +
      '<div class="pcal-grid pcal-dow-row" id="r_cal_dow"></div>' +
      '<div class="pcal-grid" id="r_cal_body"><p class="col-span-full py-4 text-center text-[12px] text-steel-400">Loading&hellip;</p></div>' +
      '<div id="r_day_panel" class="mt-3" aria-live="polite" hidden></div></div>';
  }

  // extraQ: extra availability query ('&plan_id=..&exclude_id=..' for a follow-up).
  // current = {date, slot} of the visit being rescheduled: that time is shown
  // as "Current time" and cannot be picked again.
  function wireReschedCal(dentistId, minDateKey, onPick, extraQ, current) {
    const DOW = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const pad = (n) => String(n).padStart(2, '0');
    const keyOf = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    // Each clinic slot is a 1-hour chair booking — show it as a start-end
    // range ("10:00 AM – 11:00 AM") rather than just the start time. The
    // clinic's own last slot (4:00 PM, migration 053 dropped 5:00 PM) runs
    // exactly to closing time, so a flat "+1 hour" is always accurate here —
    // no special-casing needed for the last slot of the day.
    const slotRange = (s) => {
      const m = String(s).match(/^(\d{1,2}):(\d{2})/);
      if (!m) return fmtTime(s);
      const end = String((+m[1] + 1) % 24).padStart(2, '0') + ':00';   // ends on the next full hour
      return fmtTime(s) + ' – ' + fmtTime(end);
    };
    const todayKey = keyOf(new Date());
    let view = new Date(); view.setDate(1);
    let days = {}, slotOrder = [], popupDay = null, loadSeq = 0;
    let pickedDay = null, pickedSlot = '';
    const longDay = (k) => new Date(k + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    const isFree = (info, s) => info.left ? (info.left[s] | 0) > 0 : !!(info.slots && info.slots[s]);

    document.getElementById('r_cal_dow').innerHTML = DOW.map((d) => '<div class="pcal-dow">' + d + '</div>').join('');

    function gridStart() {
      const first = new Date(view.getFullYear(), view.getMonth(), 1);
      const s = new Date(first); s.setDate(1 - first.getDay());
      return s;
    }

    async function load() {
      const seq = ++loadSeq;
      document.getElementById('r_cal_month').textContent = view.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
      const start = gridStart();
      const end = new Date(start); end.setDate(start.getDate() + 41);
      try {
        const r = await api('appointments.php?action=availability&from=' + keyOf(start) + '&to=' + keyOf(end) + (dentistId ? '&dentist_id=' + dentistId : '') + (extraQ || ''));
        if (seq !== loadSeq) return;
        days = r.days || {}; slotOrder = r.slots || [];
        render();
      } catch (e) {
        if (seq === loadSeq) document.getElementById('r_cal_body').innerHTML = '<p class="col-span-full py-4 text-center text-[12px] text-danger">' + esc(e.message) + '</p>';
      }
    }

    function cellInfo(k, inMonth) {
      const info = days[k] || {};
      if (!inMonth) return { cls: 'out', tag: '', click: false };
      if (info.closed) return { cls: 'closed', tag: 'Closed', click: true };
      if (info.past || k < minDateKey) return { cls: 'out', tag: '', click: false };
      if (info.day_over) return { cls: 'closed', tag: 'Closed', click: true };
      if (info.full) return { cls: 'full', tag: 'Fully Booked', click: true };
    // Patient places left that day (2026-10-26): every hour's free places added
    // up, in-house + roaming (2 an hour), not just the hours with room.
    const n = Object.values(info.left || {}).reduce((t, v) => t + (+v || 0), 0) || (+info.open || 0);
    return { cls: 'open bookable', tag: n ? n + (n === 1 ? ' slot left' : ' slots left') : '', click: true };
    }

    function render() {
      const start = gridStart();
      let html = '';
      for (let i = 0; i < 42; i++) {
        const d = new Date(start); d.setDate(start.getDate() + i);
        const k = keyOf(d);
        const inMonth = d.getMonth() === view.getMonth();
        const ci = cellInfo(k, inMonth);
        const picked = inMonth && k === pickedDay && pickedSlot;
        const cls = ['pcal-cell', ci.cls];
        if (k === todayKey) cls.push('today');
        if (picked) cls.push('picked');
        else if (k === popupDay) cls.push('selected');
        const tag = picked
          ? '<span class="pcal-pick"><span class="pk-short">&#10003;' + esc(fmtTime(pickedSlot).replace(':00', '').replace(' ', '')) + '</span><span class="pk-long">&#10003; ' + esc(fmtTime(pickedSlot)) + '</span></span>'
          : (k === todayKey && inMonth) ? '<span class="pcal-tag pcal-tag-today">Today</span>'
          : (ci.tag ? '<span class="pcal-tag">' + ci.tag + '</span>' : '');
        html += '<' + (ci.click ? 'button type="button"' : 'div') + ' class="' + cls.join(' ') + '"' +
          (ci.click ? ' data-day="' + k + '"' : '') + '>' +
          '<span class="pcal-date">' + d.getDate() + '</span>' + tag +
          '</' + (ci.click ? 'button' : 'div') + '>';
      }
      document.getElementById('r_cal_body').innerHTML = html;
    }

    // Same as the patient booking calendar (2026-10-11): a date opens its
    // times in a pop-up (window.openSlotPopup), the picked date stays green
    // with the time, and a green "Selected" line with Change / Clear sits below.
    function renderSummary() {
      const box = document.getElementById('r_day_panel');
      if (!pickedDay || !pickedSlot) { box.hidden = true; box.innerHTML = ''; return; }
      box.innerHTML = '<div class="pcal-picked-box"><span class="flex-1">&#10003; Selected: <strong>' + esc(longDay(pickedDay)) +
        '</strong> at <strong>' + esc(slotRange(pickedSlot)) + '</strong></span>' +
        '<span class="flex gap-2"><button type="button" class="inline-flex cursor-pointer items-center justify-center rounded-md border border-steel-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:bg-transparent dark:text-steel-100 dark:hover:bg-white/5" data-r-change>Change time</button>' +
        '<button type="button" class="inline-flex cursor-pointer items-center justify-center rounded-md border border-transparent bg-transparent px-2.5 py-1.5 text-xs font-semibold text-danger hover:bg-danger-soft dark:hover:bg-danger/20" data-r-clear>Clear</button></span></div>';
      box.hidden = false;
    }
    function setPicked(k, slot) {
      pickedDay = slot ? k : null; pickedSlot = slot || '';
      onPick(pickedDay || '', pickedSlot);
      render(); renderSummary();
    }
    function openDay(k) {
      const info = days[k] || {};
      popupDay = k; render();
      let note = '', slots = [];
      if (info.closed) note = info.reason || 'The clinic is closed on this date.';
      else {
        const order = slotOrder.length ? slotOrder : Object.keys(info.slots || {});
        const isCur = (s) => !!(current && current.date === k && current.slot === s);
        slots = order.map((s) => isCur(s)
          ? { slot: s, label: slotRange(s), free: false, sub: 'Current time' }
          : isFree(info, s)
          ? { slot: s, label: slotRange(s), free: true, sub: info.left ? slotsLeftText(info.left[s]) : '' }
          : { slot: s, label: slotRange(s), free: false, sub: info.passed && info.passed[s] ? 'Passed' : (info.slots && !(s in info.slots)) ? 'Unavailable' : 'Occupied' });
        if (!slots.some((x) => x.free)) note = info.day_over ? 'Clinic hours are over for today. Please choose another day.' : (info.passed && Object.keys(info.passed).length ? 'No time slots are left today. Please choose another day.' : 'This day is fully booked. Please choose another day.');
      }
      openSlotPopup({
        title: longDay(k), note, slots,
        selected: k === pickedDay ? pickedSlot : '',
        onPick: (slot) => setPicked(k, slot),
        // Reschedule only: once Confirm closes the pop-up, bring the Reason box into view.
        onConfirm: (slot) => { if (slot && document.getElementById('r_reason')) window.scrollToReason('r_reason'); },
        onClose: () => { popupDay = null; render(); },
      });
    }

    document.getElementById('r_cal_body').addEventListener('click', (e) => {
      const cell = e.target.closest('[data-day]');
      if (cell) openDay(cell.dataset.day);
    });
    document.getElementById('r_day_panel').addEventListener('click', async (e) => {
      if (e.target.closest('[data-r-change]') && pickedDay) {
        const k = pickedDay;
        if (+k.slice(5, 7) !== view.getMonth() + 1 || +k.slice(0, 4) !== view.getFullYear()) {
          view = new Date(k + 'T00:00:00'); view.setDate(1);
          await load();
        }
        openDay(k);
      }
      if (e.target.closest('[data-r-clear]')) setPicked(null, '');
    });
    document.getElementById('r_cal_prev').addEventListener('click', () => { view.setMonth(view.getMonth() - 1); load(); });
    document.getElementById('r_cal_next').addEventListener('click', () => { view.setMonth(view.getMonth() + 1); load(); });
    // Today jumps back to this month and opens today's times when bookable.
    document.getElementById('r_cal_today').addEventListener('click', async () => {
      view = new Date(); view.setDate(1);
      await load();
      if (document.querySelector('#r_cal_body [data-day="' + todayKey + '"]')) openDay(todayKey);
    });

    load();
    // Lets the walk-in modal keep a separate date + time per patient mode.
    return {
      get: () => (pickedDay && pickedSlot ? { date: pickedDay, slot: pickedSlot } : null),
      set: async (p) => {
        if (p && (+p.date.slice(5, 7) !== view.getMonth() + 1 || +p.date.slice(0, 4) !== view.getFullYear())) {
          view = new Date(p.date + 'T00:00:00'); view.setDate(1);
          await load();
        }
        setPicked(p ? p.date : null, p ? p.slot : '');
      },
    };
  }

  async function reschedModal(id) {
    let appt = null;
    try { appt = (await api('appointments.php?action=get&id=' + id)).appointment; } catch (_) { /* dentist_id filter just won't apply */ }
    // A roaming dentist declined this on-call visit: start the reason with what
    // they wrote, so the front desk doesn't have to type it again (still editable).
    const declined = appt && !appt.dentist_id && appt.oncall_declined_at && appt.oncall_decline_reason;
    const preReason = declined
      ? (appt.oncall_declined_by_name ? 'Dr. ' + appt.oncall_declined_by_name : 'The on-call dentist') +
        ' declined the on-call assignment: ' + appt.oncall_decline_reason
      : '';
    openModal(modalShell('Reschedule Appointment',
      '<div class="flex flex-col gap-3.5">' +
      '<div class="' + FGF + '"><label class="' + LBL + '">Pick a New Date &amp; Time</label>' + reschedCalHtml() + '</div>' +
      '<input type="hidden" id="r_date"><input type="hidden" id="r_slot">' +
      '<div class="' + FGF + '"><label class="' + LBL + '">Reason for Rescheduling</label>' +
      '<textarea class="' + INP + ' min-h-[72px] resize-y" id="r_reason" placeholder="e.g. Patient called to move the visit.">' + esc(preReason) + '</textarea>' +
      (declined ? '<div class="text-[11px] text-steel-400 dark:text-steel-500">Filled in from the dentist\'s decline reason. You can edit it.</div>' : '') + '</div>' +
      '<div class="' + FGF + '" id="r_msg"></div></div>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + PRIMARY + '" id="r_go">Save</button>'), { wide: true });

    wireReschedCal(appt && appt.dentist_id, todayISO(), (date, slot) => {
      document.getElementById('r_date').value = date;
      document.getElementById('r_slot').value = slot;
    }, appt && appt.treatment_plan_id && appt.dentist_id ? '&plan_id=' + appt.treatment_plan_id + '&exclude_id=' + id
      // Declined by the roaming dentist: only hours another dentist can take are open.
      : (appt && !appt.dentist_id && appt.oncall_declined_at ? '&declined_id=' + id : ''),
    appt ? { date: String(appt.scheduled_date).slice(0, 10), slot: String(appt.time_slot || '').slice(0, 5) } : null);

    document.getElementById('r_go').addEventListener('click', async () => {
      const reason = document.getElementById('r_reason').value.trim();
      if (!document.getElementById('r_date').value) return toast('Pick a new date.', 'err');
      if (!document.getElementById('r_slot').value) return toast('Pick an available time slot.', 'err');
      if (!reason) return toast('Please enter a reason for rescheduling.', 'err');
      try {
        await api('appointments.php?action=reschedule', { method: 'POST', body: {
          appointment_id: id, scheduled_date: document.getElementById('r_date').value,
          time_slot: document.getElementById('r_slot').value, reason,
        }});
        closeModal(); toast('Appointment rescheduled. The patient has been notified.'); load(); loadSched(); window.notifChanged && window.notifChanged();
      } catch (e) { toast(e.message, 'err'); }
    });
  }

  // One-time sign-in details for a newly created patient — the temp password is
  // never shown again, so the front desk must hand it over now.
  // child: set when the account is a parent / guardian's (minor walk-in).
  function showCreds(name, creds, child) {
    const CHIP = 'rounded-lg border border-steel-200 bg-steel-50 px-4 py-3 dark:border-white/15 dark:bg-white/5';
    openModal(modalShell('Online login for ' + esc(name),
      '<p class="text-[13px] text-steel-500">' + (child
        ? 'Give these sign-in details to ' + esc(name) + ', the parent / guardian. They use them to manage ' + esc(child) + '’s bookings online. The temporary password is shown only once, and they must set their own password on first login.'
        : 'Give these sign-in details to the patient. The temporary password is shown only once. They use it to book online and must set their own password on first login.') + '</p>' +
      '<div class="mt-3 grid gap-2 sm:grid-cols-2">' +
        '<div class="' + CHIP + '"><p class="text-[11px] font-semibold uppercase tracking-wide text-steel-500">Username</p>' +
          '<p class="font-mono text-lg font-bold text-navy-900 dark:text-steel-50">' + esc(creds.username) + '</p></div>' +
        '<div class="' + CHIP + '"><p class="text-[11px] font-semibold uppercase tracking-wide text-steel-500">Temporary Password</p>' +
          '<p class="font-mono text-lg font-bold text-navy-900 dark:text-steel-50">' + esc(creds.temp_password) + '</p></div>' +
      '</div>',
      '<button class="' + BTN + PRIMARY + '" data-close>Done</button>'));
  }

  function walkInModal() {
    // A walk-in must resolve to a real patient account: pick an existing record
    // (search) or create one inline, so a returning walk-in keeps one history.
    let picked = null;   // { user_id, full_name, contact_number, age }
    let mode = 'exist';
    // Parent / guardian of a minor walk-in (migration 089).
    let gPicked = null;   // existing guardian { user_id, full_name, contact_number }
    let gMode = 'exist';
    let gCreds = null;    // a guardian account created here (kept for a retry)

    openModal(modalShell('Walk-in Booking',
      '<div class="' + GRID2 + '">' +
      '<div class="col-span-2 flex flex-col gap-1.5">' +
        '<label class="' + LBL + '">Patient' + REQ + '</label>' +
        '<div class="flex gap-1.5">' +
          '<button type="button" class="' + BTN_SM + PRIMARY + '" id="w_mExist">Existing patient</button>' +
          '<button type="button" class="' + BTN_SM + GHOST + '" id="w_mNew">New patient</button>' +
        '</div>' +
      '</div>' +
      '<div class="col-span-2 flex-col gap-1.5" id="w_exist" style="display:flex">' +
        '<input class="' + INP + '" id="w_search" placeholder="Search patient by name or phone…" autocomplete="off">' +
        '<div id="w_results" class="max-h-[180px] divide-y divide-steel-100 overflow-y-auto rounded-md border border-steel-100 dark:divide-white/10 dark:border-white/10" style="display:none"></div>' +
        '<div id="w_sel" class="items-center justify-between rounded-md border border-ok/40 bg-ok-soft px-3 py-2 text-[13px] text-navy-800 dark:bg-ok/20 dark:text-steel-50" style="display:none"></div>' +
      '</div>' +
      '<div class="flex-col gap-1.5" id="w_nf1" style="display:none"><label class="' + LBL + '">First Name' + REQ + '</label><input class="' + INP + '" id="w_first"></div>' +
      '<div class="flex-col gap-1.5" id="w_nf3" style="display:none"><label class="' + LBL + '">Last Name' + REQ + '</label><input class="' + INP + '" id="w_last"></div>' +
      '<div class="' + FG + '" id="w_dobWrap" style="display:none"><label class="' + LBL + '">Date of Birth' + REQ + '</label>' + dobSelectsHtml('w_dob', '', INP, { swap: true }) +
        '<span class="' + HINT + '" id="w_dobAge"></span></div>' +
      '<div class="' + FG + ' col-span-2" id="w_phoneWrap"><label class="' + LBL + '"><span id="w_phoneLbl">Phone Number</span>' + REQ + '</label><input class="' + INP + '" id="w_phone" type="tel" inputmode="numeric"></div>' +
      '<div class="col-span-2 flex-col gap-1.5" id="w_nf4" style="display:none"><label class="' + LBL + '">Email Address' + REQ + '</label><input class="' + INP + '" id="w_email" type="email"></div>' +
      // Under 18 on New patient (migration 089): the account is the parent /
      // guardian's and the child is saved as a dependent under it.
      '<div class="col-span-2 flex-col gap-2.5 rounded-lg border border-dgt-blue-200 bg-dgt-blue-50/60 p-3.5 dark:border-white/15 dark:bg-white/5" id="w_guard" style="display:none">' +
        '<div><p class="text-[13px] font-bold text-navy-900 dark:text-steel-50">Parent / Guardian' + REQ + '</p>' +
        '<p class="' + HINT + '">This patient is under 18, so the account belongs to their parent or guardian, who manages bookings for them. The child’s record is saved under that account. When the child turns 18, they get their own login.</p></div>' +
        '<div class="flex gap-1.5"><button type="button" class="' + BTN_SM + PRIMARY + '" id="w_gmExist">Existing account</button>' +
          '<button type="button" class="' + BTN_SM + GHOST + '" id="w_gmNew">New account</button></div>' +
        '<div class="flex flex-col gap-1.5" id="w_gExist">' +
          '<input class="' + INP + '" id="w_gSearch" placeholder="Search the parent / guardian by name or phone…" autocomplete="off">' +
          '<div id="w_gResults" class="max-h-[180px] divide-y divide-steel-100 overflow-y-auto rounded-md border border-steel-100 bg-white dark:divide-white/10 dark:border-white/10 dark:bg-transparent" style="display:none"></div>' +
          '<div id="w_gSel" class="items-center justify-between rounded-md border border-ok/40 bg-ok-soft px-3 py-2 text-[13px] text-navy-800 dark:bg-ok/20 dark:text-steel-50" style="display:none"></div>' +
        '</div>' +
        '<div class="' + GRID2 + '" id="w_gNew" style="display:none">' +
          '<div class="' + FG + '"><label class="' + LBL + '">Guardian First Name' + REQ + '</label><input class="' + INP + '" id="w_g_first"></div>' +
          '<div class="' + FG + '"><label class="' + LBL + '">Guardian Last Name' + REQ + '</label><input class="' + INP + '" id="w_g_last"></div>' +
          '<div class="' + FG + '"><label class="' + LBL + '">Guardian Email Address' + REQ + '</label><input class="' + INP + '" id="w_gemail" type="email"></div>' +
          '<div class="' + FG + '"><label class="' + LBL + '">Guardian Date of Birth' + REQ + '</label>' + dobSelectsHtml('w_gdob', '', INP, { minAge: 18, maxAge: 80 }) + '</div>' +
        '</div>' +
        '<div class="' + FG + '"><label class="' + LBL + '">Relationship to Patient' + REQ + '</label><input class="' + INP + '" id="w_rel" placeholder="e.g. Mother, Father, Guardian"></div>' +
      '</div>' +
      '<div class="' + FGF + '"><label class="' + LBL + '">Reservation Fee / Downpayment Amount' + REQ + '</label><input class="' + INP + ' bg-steel-50 cursor-not-allowed dark:bg-white/5" id="w_resAmount" type="text" inputmode="decimal" data-money value="__RES_FEE__" readonly></div>' +
      '<div class="' + FGF + '"><label class="' + LBL + '">Select Payment Method' + REQ + '</label>' +
        '<div class="pm-cards">' +
        ['cash', 'gcash', 'bank'].map((v) =>
          '<label class="pm-card">' +
          '<input type="radio" name="w_resMethod" id="w_res' + v[0].toUpperCase() + v.slice(1) + '" value="' + v + '">' +
          '<span class="pm-card-txt"><span class="pm-card-t">' + esc(METHODS[v]) + '</span>' +
          '<span class="pm-card-s">' + esc(RES_METHOD_SUB[v]) + '</span></span></label>').join('') +
        '</div>' +
      '</div>' +
      '<div class="' + FGF + '" id="w_resRefWrap" style="display:none"></div>' +
      '<div class="' + FGF + '"><label class="' + LBL + '">Service' + REQ + '</label>' +
        svcPickerHtml('w_service', SERVICES, '', INP, '(Select a service)') + '</div>' +
      '<div class="' + FGF + '"><label class="' + LBL + '">Date &amp; Time' + REQ + '</label><div id="w_cutoffNote" hidden></div>' + reschedCalHtml() + '</div>' +
      '<input type="hidden" id="w_date"><input type="hidden" id="w_slot">' +
      '<div class="' + FGF + '"><label class="' + LBL + '">Assign Dentist' + REQ + '</label><select class="' + INP + '" id="w_dentist" disabled><option value="">Pick a date and time first</option></select>' +
        '<span class="' + HINT + '">Only dentists on duty, with room left this hour, are selectable. Defaults to the in-house doctor.</span></div>' +
      '<div class="' + FGF + '"><label class="' + LBL + '">Concern <span class="font-normal text-steel-400 dark:text-steel-500">(optional)</span></label><textarea class="' + INP + ' min-h-[72px] resize-y" id="w_notes" placeholder="Please describe the concern here…"></textarea></div>' +
      '</div>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + PRIMARY + '" id="w_go">Book &amp; Schedule</button>'), { wide: true });

    wirePhoneField(document.getElementById('w_phone'), { submitBtn: document.getElementById('w_go') });
    wireSvcPicker('w_service');
    // No mailbox-existence check / e-mailed code here: a walk-in is created
    // on the spot with a temporary password (format check only).

    // Reservation fee / downpayment — now a standard part of every walk-in
    // booking (no opt-out checkbox), sitting right under Age/Phone Number.
    // Payment Method (Cash / GCash / Bank Transfer) is always visible; GCash
    // or Bank Transfer reveal a Reference Number field, rebuilt (fresh
    // element, so wireRefCheck's listeners never stack across method
    // switches — same technique the patient booking wizard's own
    // wirePanel(m) uses) with the exact same per-method format + duplicate-
    // transaction check the online booking wizard's payment step enforces:
    // GCash = exactly 13 digits, Bank = 10-20 alphanumeric.
    const resRefWrap = document.getElementById('w_resRefWrap');
    const REF_LABELS = { gcash: 'GCash Reference No.', bank: 'Bank Reference No.' };
    const REF_FORMATS = {
      gcash: { re: /^\d{13}$/, msg: 'GCash Reference Number must be exactly 13 digits' },
      bank: { re: /^[A-Za-z0-9]{10,20}$/, msg: 'Bank Reference Number must be 10-20 characters' },
    };
    // Same input attrs as the patient booking wizard's own #p_ref field per
    // method — numeric keypad + 13-digit cap for GCash, a 20-char cap for
    // Bank — so the placeholder hints exactly what to type either way.
    const REF_ATTRS = {
      gcash: ' type="tel" inputmode="numeric" maxlength="13" pattern="[0-9]{13}" placeholder="13-digit reference number"',
      bank: ' maxlength="20" pattern="[A-Za-z0-9]{10,20}" placeholder="10–20 letters / digits"',
    };
    const syncResMethod = () => {
      const checked = document.querySelector('input[name="w_resMethod"]:checked');
      document.querySelectorAll('.pm-card').forEach((c) => c.classList.toggle('on', c.querySelector('input') === checked));
      const method = checked ? checked.value : '';
      if (method !== 'gcash' && method !== 'bank') {
        resRefWrap.style.display = 'none';
        resRefWrap.innerHTML = '';
        return;
      }
      resRefWrap.style.display = 'flex';
      resRefWrap.innerHTML = '<label class="' + LBL + '">' + esc(REF_LABELS[method]) + REQ + '</label>' +
        '<input class="' + INP + '" id="w_resRef" autocomplete="off"' + REF_ATTRS[method] + '><div class="field-err" id="w_resRefErr" hidden></div>';
      wireRefCheck(document.getElementById('w_resRef'), {
        errEl: document.getElementById('w_resRefErr'), submitBtn: document.getElementById('w_go'),
        format: REF_FORMATS[method],
      });
    };
    document.querySelectorAll('input[name="w_resMethod"]').forEach((r) => r.addEventListener('change', syncResMethod));

    // Walk-ins book under the Owner Dentist placeholder (the real dentist is
    // chosen at Approve) and, unlike online bookings, have no 2-day lead time —
    // same-day is normal. Passing the owner's own id drops action=availability's
    // floor to plain "today" and pulls their real working grid, mirroring what
    // action=slots already did for the old plain date+select fields.
    const owner = dentists.find((d) => d.doctor_type === 'owner');
    // Picking a date + time fills the Assign Dentist picker for that exact
    // hour: off-duty dentists and ones already full that hour are disabled
    // (same rule as the Approve modal), the in-house doctor is the default.
    let dentSeq = 0;
    const wCal = wireReschedCal(owner ? owner.user_id : null, todayISO(), async (date, slot) => {
      document.getElementById('w_date').value = date;
      document.getElementById('w_slot').value = slot;
      const sel = document.getElementById('w_dentist');
      const keep = sel.value || null;
      const mine = ++dentSeq;
      if (!date || !slot) {   // cleared, or switched to a patient mode with no pick yet
        sel.innerHTML = '<option value="">Pick a date and time first</option>';
        sel.disabled = true;
        return;
      }
      sel.disabled = true;
      sel.innerHTML = '<option value="">Checking who is on duty…</option>';
      const off = await dutyMapFor(date, slot, 0, true);
      if (mine !== dentSeq || !document.getElementById('w_dentist')) return;
      sel.innerHTML = dentistOptions(keep && !off[keep] ? keep : null, off);
      if (!sel.value || sel.selectedOptions[0]?.disabled) {
        const first = [...sel.options].find((o) => !o.disabled);
        if (first) first.selected = true;
      }
      const any = [...sel.options].some((o) => !o.disabled);
      if (!any) sel.innerHTML = '<option value="">No dentist available at this time</option>';
      sel.disabled = !any;
    }, '&walkin=1&walkin_new=1');   // walk-ins may still take the hour that is in progress

    // Same-day cut-off (2026-10-22): after it, today is closed for new walk-ins.
    (async () => {
      try {
        const t = todayISO();
        const r = await api('appointments.php?action=availability&from=' + t + '&to=' + t + '&walkin=1&walkin_new=1');
        const box = document.getElementById('w_cutoffNote');
        if (!box || !r.walkin_closed) return;
        box.className = window.alertCls('warn') + ' !mb-2';
        box.textContent = 'Same-day walk-in booking is closed for today (Cut-off: ' + r.walkin_cutoff + '). Please schedule the appointment for a future date.';
        box.hidden = false;
      } catch (e) { /* the calendar still shows today as closed */ }
    })();

    // Guided steps (2026-10-10, adviser feedback): one highlighted step at a
    // time; later steps stay dimmed until the earlier ones are done.
    {
      const el = (id) => document.getElementById(id);
      const filled = (id) => () => !!(el(id) && el(id).value.trim());
      const resMethod = () => (document.querySelector('input[name="w_resMethod"]:checked') || {}).value || '';
      guideForm(el('w_go').closest('.modal-pane') || el('modalRoot'), null, [
        { label: 'Choose or add the patient', fields: [
          { wrap: () => el('w_exist'), ctl: () => el('w_search'), ok: () => !!picked, auto: true },
          { wrap: () => el('w_nf1'), ctl: () => el('w_first'), ok: filled('w_first') },
          { wrap: () => el('w_nf3'), ctl: () => el('w_last'), ok: filled('w_last') },
          { wrap: () => el('w_dobWrap'), ctl: () => [...el('w_dobWrap').querySelectorAll('select')], ok: () => ageOf(el('w_dob').value) !== null, auto: true },
          { wrap: () => el('w_phoneWrap'), ctl: () => el('w_phone'), ok: () => /^09\d{9}$/.test(el('w_phone').value), auto: true },
          { wrap: () => el('w_nf4'), ctl: () => el('w_email'), ok: () => /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(el('w_email').value.trim()) },
          { wrap: () => el('w_guard'), ctl: () => el(gMode === 'exist' ? 'w_gSearch' : 'w_g_first'), ok: () => guardianDone() },
        ] },
        { label: () => (resMethod() && resMethod() !== 'cash' ? 'Enter the reference number' : 'Choose the payment method'), fields: [
          { wrap: () => el('w_resAmount').closest('.flex-col').nextElementSibling, ctl: () => document.querySelector('#modalRoot .pm-cards'),
            focus: () => document.querySelector('input[name="w_resMethod"]:checked') || document.querySelector('input[name="w_resMethod"]'), ok: () => !!resMethod(), auto: true },
          { wrap: () => el('w_resRefWrap'), ctl: () => el('w_resRef'),
            ok: () => { const r = el('w_resRef'); return !!r && (resMethod() === 'bank' ? /^[A-Za-z0-9]{10,20}$/ : /^\d{13}$/).test(r.value.trim()) && !r.classList.contains('is-invalid'); },
            auto: () => resMethod() === 'gcash' },
        ] },
        { label: 'Pick the service', fields: [
          { wrap: () => el('w_service').parentElement, ctl: () => el('w_serviceBtn'), ok: filled('w_service'), auto: true },
        ] },
        { label: 'Choose a date and time', fields: [
          { wrap: () => el('r_cal_body').closest('.rs-pcal').parentElement, ctl: () => el('r_cal_body').closest('.rs-pcal'),
            focus: () => el('r_cal_body').querySelector('.pcal-cell.bookable'),
            ok: () => !!(el('w_date').value && el('w_slot').value), auto: true },
        ] },
        { label: 'Assign the dentist', fields: [
          { wrap: () => el('w_dentist').parentElement, ctl: () => el('w_dentist'), ok: () => !!el('w_dentist').value && !el('w_dentist').disabled, auto: true },
        ] },
        // Concern is optional (same as online): focus moves here after the
        // dentist, but Book & Schedule stays usable with it left blank.
        { label: 'Add the concern (optional), then press "Book & Schedule"', fields: [
          { wrap: () => el('w_notes').parentElement, ctl: () => el('w_notes'), ok: filled('w_notes') },
          { wrap: () => el('w_go'), ok: false, noLock: true },
        ] },
      ], { noScroll: true });   // ring only, no auto-scroll (2026-10-17)
    }

    // Date of Birth (the age is computed from it, never typed). A picked
    // patient's birthdate on file is shown locked; with none on file yet the
    // front desk enters it and it is saved to their record.
    const dobEl = document.getElementById('w_dob');
    function ageOf(dob) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(dob || '')) return null;
      const b = new Date(dob + 'T00:00:00'), n = new Date();
      let a = n.getFullYear() - b.getFullYear();
      if (n.getMonth() < b.getMonth() || (n.getMonth() === b.getMonth() && n.getDate() < b.getDate())) a--;
      return a >= 0 ? a : null;
    }
    function showDobAge() {
      const a = ageOf(dobEl.value);
      document.getElementById('w_dobAge').textContent = a === null ? '' : dobShowsAge('w_dob') ? '' : 'Age: ' + a;
    }
    function setDob(value, locked) {
      // A new walk-in patient must be 1 to 80 (any age for an existing one).
      setDobRange('w_dob', mode === 'new' ? 1 : 0, mode === 'new' ? 80 : 120);
      lockDobSelects('w_dob', false);
      setDobSelects('w_dob', value || '');
      lockDobSelects('w_dob', !!locked);
      showDobAge();
      // Existing patient: no birthdate field until one is picked (then their
      // Age, or the dropdowns if none is on file yet). New patient: always.
      const show = mode === 'new' || !!picked;
      document.getElementById('w_dobWrap').style.display = show ? '' : 'none';
      document.getElementById('w_phoneWrap').classList.toggle('col-span-2', !show);
      lockPhone(!!(picked && picked.contact_number));
    }
    // A picked patient with a number on file: the phone comes from their
    // account and is greyed out (2026-10-17); otherwise it is typed.
    function lockPhone(on) {
      const ph = document.getElementById('w_phone');
      if (ph.readOnly && !on) { ph.value = ''; ph.dispatchEvent(new Event('input')); }
      ph.readOnly = on;
      ph.tabIndex = on ? -1 : 0;
      ['!bg-dgt-ink-50', 'dark:!bg-white/5', 'cursor-not-allowed'].forEach((c) => ph.classList.toggle(c, on));
    }
    wireDobSelects('w_dob');
    dobEl.addEventListener('input', showDobAge);

    // Minor walk-in (migration 089): under 18 on New patient shows the Parent /
    // Guardian section and hides the child's own e-mail; the phone is the
    // guardian's. The booking goes on the guardian's account.
    const isMinor = () => { const a = ageOf(dobEl.value); return mode === 'new' && a !== null && a < 18; };
    function guardianDone() {
      if (!v('w_rel')) return false;
      if (gMode === 'exist') return !!gPicked;
      return !!(v('w_g_first') && v('w_g_last') && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v('w_gemail')) && ageOf(v('w_gdob')) !== null);
    }
    function syncGuardian() {
      const minor = isMinor();
      document.getElementById('w_guard').style.display = minor ? 'flex' : 'none';
      document.getElementById('w_nf4').style.display = mode === 'new' && !minor ? 'flex' : 'none';
      document.getElementById('w_phoneLbl').textContent = minor ? 'Parent / Guardian Phone Number' : 'Phone Number';
      if (!minor && gPicked) { gPicked = null; renderGSel(); }
    }
    function setGMode(m) {
      gMode = m;
      document.getElementById('w_gExist').style.display = m === 'exist' ? 'flex' : 'none';
      document.getElementById('w_gNew').style.display = m === 'new' ? 'grid' : 'none';
      document.getElementById('w_gmExist').className = BTN_SM + (m === 'exist' ? PRIMARY : GHOST);
      document.getElementById('w_gmNew').className = BTN_SM + (m === 'exist' ? GHOST : PRIMARY);
      if (m === 'new' && gPicked) { gPicked = null; renderGSel(); }
    }
    function renderGSel() {
      const box = document.getElementById('w_gSel');
      if (gPicked) {
        box.innerHTML = '<span>Guardian: <strong>' + esc(gPicked.full_name) + '</strong>' +
          (gPicked.contact_number ? ' · ' + esc(gPicked.contact_number) : '') + '</span>' +
          '<button type="button" class="text-xs font-semibold text-accent underline" id="w_gclr">Change</button>';
        box.style.display = 'flex';
        document.getElementById('w_gResults').style.display = 'none';
        if (gPicked.contact_number) {
          const ph = document.getElementById('w_phone');
          ph.readOnly = false; ph.value = gPicked.contact_number; ph.dispatchEvent(new Event('input'));
        }
        lockPhone(!!gPicked.contact_number);
        document.getElementById('w_gclr').addEventListener('click', () => {
          gPicked = null; renderGSel(); document.getElementById('w_gSearch').focus();
        });
      } else {
        box.style.display = 'none';
        lockPhone(false);
      }
    }
    document.getElementById('w_gmExist').addEventListener('click', () => setGMode('exist'));
    document.getElementById('w_gmNew').addEventListener('click', () => setGMode('new'));
    wireDobSelects('w_gdob');
    dobEl.addEventListener('input', syncGuardian);
    const gResults = document.getElementById('w_gResults');
    let gSearchT;
    document.getElementById('w_gSearch').addEventListener('input', (e) => {
      clearTimeout(gSearchT);
      const q = e.target.value.trim();
      if (q.length < 2) { gResults.style.display = 'none'; gResults.innerHTML = ''; return; }
      gSearchT = setTimeout(async () => {
        try {
          const { patients } = await api('records.php?action=list&q=' + encodeURIComponent(q));
          // Only adults (18+, or no birthdate on file) can be a guardian.
          const adults = patients.filter((p) => { const a = ageOf(p.date_of_birth ? String(p.date_of_birth).slice(0, 10) : ''); return a === null || a >= 18; });
          gResults.innerHTML = adults.length
            ? adults.slice(0, 20).map((p) =>
                '<button type="button" class="block w-full px-3 py-2 text-left text-[13px] hover:bg-steel-50 dark:hover:bg-white/5" data-gpick=\'' +
                esc(JSON.stringify({ user_id: p.id, full_name: p.full_name, contact_number: p.contact_number || '' })) + '\'>' +
                '<strong>' + esc(p.full_name) + '</strong><span class="text-steel-400"> · ' + esc(p.contact_number || 'no phone') + '</span></button>')
              .join('')
            : '<div class="px-3 py-2 text-[13px] text-steel-400">No adult account found. Use “New account”.</div>';
          gResults.style.display = 'block';
        } catch (_) { /* ignore transient search errors */ }
      }, 300);
    });
    gResults.addEventListener('click', (e) => {
      const b = e.target.closest('[data-gpick]');
      if (!b) return;
      gPicked = JSON.parse(b.dataset.gpick);
      document.getElementById('w_gSearch').value = '';
      renderGSel();
    });

    const newWraps = ['w_nf1', 'w_nf3', 'w_nf4'].map((id) => document.getElementById(id));
    // Existing and New patient each keep their own date + time (2026-10-11):
    // switching modes saves the current pick and shows that mode's own.
    // The payment method, reference no., service and concern are kept per mode
    // too, so switching to New patient starts those steps fresh (2026-10-11).
    const modePick = { exist: null, new: null };
    const modeForm = { exist: null, new: null };
    const readForm = () => {
      const r = document.querySelector('input[name="w_resMethod"]:checked');
      return { method: r ? r.value : '', ref: document.getElementById('w_resRef') ? document.getElementById('w_resRef').value : '',
        service: document.getElementById('w_service').value, notes: document.getElementById('w_notes').value };
    };
    const writeForm = (f) => {
      f = f || { method: '', ref: '', service: '', notes: '' };
      document.querySelectorAll('input[name="w_resMethod"]').forEach((r) => { r.checked = r.value === f.method; });
      syncResMethod();
      const ref = document.getElementById('w_resRef');
      if (ref && f.ref) { ref.value = f.ref; ref.dispatchEvent(new Event('input', { bubbles: true })); }
      const svc = document.getElementById('w_service');
      svc.value = f.service; svc.dispatchEvent(new Event('change', { bubbles: true }));
      document.getElementById('w_notes').value = f.notes;
    };
    const setMode = (m) => {
      if (m !== mode) {
        modePick[mode] = wCal.get();
        wCal.set(modePick[m]);
        modeForm[mode] = readForm();
        writeForm(modeForm[m]);
      }
      mode = m;
      const on = m === 'exist';
      document.getElementById('w_exist').style.display = on ? 'flex' : 'none';
      newWraps.forEach((w) => { w.style.display = on ? 'none' : 'flex'; });
      document.getElementById('w_mExist').className = BTN_SM + (on ? PRIMARY : GHOST);
      document.getElementById('w_mNew').className = BTN_SM + (on ? GHOST : PRIMARY);
      if (!on) { picked = null; renderSel(); }
      setDob('', false);
      syncGuardian();
    };
    document.getElementById('w_mExist').addEventListener('click', () => setMode('exist'));
    document.getElementById('w_mNew').addEventListener('click', () => setMode('new'));

    function renderSel() {
      const box = document.getElementById('w_sel');
      if (picked) {
        box.innerHTML = '<span>Selected: <strong>' + esc(picked.full_name) + '</strong>' +
          (picked.contact_number ? ' · ' + esc(picked.contact_number) : '') + '</span>' +
          '<button type="button" class="text-xs font-semibold text-accent underline" id="w_clr">Change</button>';
        box.style.display = 'flex';
        document.getElementById('w_results').style.display = 'none';
        document.getElementById('w_clr').addEventListener('click', () => {
          picked = null; renderSel(); setDob('', false); document.getElementById('w_search').focus();
        });
      } else {
        box.style.display = 'none';
      }
    }

    const results = document.getElementById('w_results');
    let searchT;
    document.getElementById('w_search').addEventListener('input', (e) => {
      clearTimeout(searchT);
      const q = e.target.value.trim();
      if (q.length < 2) { results.style.display = 'none'; results.innerHTML = ''; return; }
      searchT = setTimeout(async () => {
        try {
          const { patients } = await api('records.php?action=list&q=' + encodeURIComponent(q));
          results.innerHTML = patients.length
            ? patients.slice(0, 20).map((p) =>
                '<button type="button" class="block w-full px-3 py-2 text-left text-[13px] hover:bg-steel-50 dark:hover:bg-white/5" data-pick=\'' +
                esc(JSON.stringify({ user_id: p.id, full_name: p.full_name, contact_number: p.contact_number || '', date_of_birth: p.date_of_birth ? String(p.date_of_birth).slice(0, 10) : '' })) + '\'>' +
                '<strong>' + esc(p.full_name) + '</strong><span class="text-steel-400"> · ' +
                esc(p.contact_number || 'no phone') +
                (p.visit_count ? ' · ' + p.visit_count + ' visit' + (p.visit_count > 1 ? 's' : '') : ' · new') +
                '</span></button>')
              .join('')
            : '<div class="px-3 py-2 text-[13px] text-steel-400">No match. Use “New patient”.</div>';
          results.style.display = 'block';
        } catch (_) { /* ignore transient search errors */ }
      }, 300);
    });
    results.addEventListener('click', (e) => {
      const b = e.target.closest('[data-pick]');
      if (!b) return;
      picked = JSON.parse(b.dataset.pick);
      document.getElementById('w_search').value = '';
      if (picked.contact_number) {
        const ph = document.getElementById('w_phone');
        ph.readOnly = false;
        ph.value = picked.contact_number; ph.dispatchEvent(new Event('input'));
      }
      setDob(picked.date_of_birth, !!picked.date_of_birth);
      renderSel();
    });

    document.getElementById('w_go').addEventListener('click', async () => {
      const dob = v('w_dob');
      const phone = v('w_phone');
      const serviceId = +document.getElementById('w_service').value;
      if (!document.getElementById('w_phone')._phoneOk(false)) return toast('Enter a valid phone number. It is required. ' + PH_MOBILE_MSG, 'err');
      if (ageOf(dob) === null || dob > todayISO()) return toast(mode === 'new' ? 'Enter the patient’s date of birth (ages 1 to 80 only).' : 'Enter the patient’s date of birth.', 'err');
      if (mode === 'new' && (ageOf(dob) < 1 || ageOf(dob) > 80)) return toast('A new walk-in patient must be 1 to 80 years old.', 'err');
      if (!serviceId) return toast('Choose a service for this walk-in.', 'err');
      if (!v('w_date')) return toast('Pick a date.', 'err');
      if (!v('w_slot')) return toast('Pick an available time slot.', 'err');
      const dentistId = +document.getElementById('w_dentist').value;
      if (!dentistId) return toast('Assign a dentist for this walk-in.', 'err');
      const amt = window.moneyVal(v('w_resAmount'));
      const methodEl = document.querySelector('input[name="w_resMethod"]:checked');
      if (!methodEl) return toast('Choose a payment method.', 'err');
      const method = methodEl.value;
      if (!amt || amt <= 0) return toast('Enter a valid reservation fee / downpayment amount.', 'err');
      let resPayload;
      if (method === 'cash') {
        resPayload = { reservation_method: 'cash', reservation_amount: amt };
      } else {
        const ref = v('w_resRef');
        if (!REF_FORMATS[method].re.test(ref)) return toast(REF_FORMATS[method].msg + '.', 'err');
        resPayload = { reservation_method: method, reservation_amount: amt, reservation_reference: ref };
      }
      try {
        let patientId;
        let name;
        let creds = null;
        let booking = { booking_for: 'self' };
        let guardianName = '';
        if (mode === 'new' && isMinor()) {
          // Minor: book under the parent / guardian's account (migration 089).
          const first = v('w_first');
          const last = v('w_last');
          if (!first || !last) return toast('Enter the patient’s first and last name.', 'err');
          const rel = v('w_rel');
          if (gMode === 'new') {
            const gf = v('w_g_first'), gl = v('w_g_last'), ge = v('w_gemail'), gd = v('w_gdob');
            if (!gf || !gl) return toast('Enter the parent / guardian’s first and last name.', 'err');
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(ge)) return toast('Enter the parent / guardian’s e-mail address. It is needed for password reset.', 'err');
            if (ageOf(gd) === null) return toast('Enter the parent / guardian’s date of birth (18 or older).', 'err');
            if (!rel) return toast('Enter the parent / guardian’s relationship to the patient.', 'err');
            const gr = await api('records.php?action=create', { method: 'POST', body: {
              first_name: gf, last_name: gl, date_of_birth: gd, contact_number: phone, email: ge, walk_in: true, guardian: true,
            }});
            gCreds = { name: gf + ' ' + gl, username: gr.username, temp_password: gr.temp_password };
            // Keep the new account picked, so a retry doesn't create it twice.
            setGMode('exist');
            gPicked = { user_id: gr.patient_id, full_name: gf + ' ' + gl, contact_number: phone };
            renderGSel();
          }
          if (!gPicked) return toast('Search and pick the parent / guardian’s account, or choose New account.', 'err');
          if (!rel) return toast('Enter the parent / guardian’s relationship to the patient.', 'err');
          patientId = gPicked.user_id;
          guardianName = gPicked.full_name;
          name = first + ' ' + last;
          booking = { booking_for: 'other', relationship: rel, new_patient: { first_name: first, last_name: last, date_of_birth: dob } };
        } else if (mode === 'new') {
          const first = v('w_first');
          const last = v('w_last');
          const email = v('w_email');
          if (!first || !last) return toast('Enter the patient’s first and last name.', 'err');
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return toast('Enter the patient’s e-mail address. It is needed for password reset.', 'err');
          const res = await api('records.php?action=create', { method: 'POST', body: {
            first_name: first, last_name: last, date_of_birth: dob,
            contact_number: phone, email, walk_in: true,
          }});
          patientId = res.patient_id;
          name = first + ' ' + last;
          creds = { username: res.username, temp_password: res.temp_password };
        } else {
          if (!picked) return toast('Search and pick a patient, or switch to New patient.', 'err');
          patientId = picked.user_id;
          name = picked.full_name;
        }
        await api('appointments.php?action=walk_in', { method: 'POST', body: Object.assign({
          patient_id: patientId, patient_name: name, date_of_birth: dob, phone,
          service_id: serviceId, dentist_id: dentistId,
          scheduled_date: v('w_date'), time_slot: v('w_slot'),
          notes: v('w_notes'),
        }, booking, resPayload) });
        closeModal(); toast(guardianName ? 'Walk-in booked under ' + guardianName + '’s account.' : 'Walk-in booked and approved.'); load(); loadSched();
        if (creds) showCreds(name, creds);
        else if (guardianName && gCreds) showCreds(gCreds.name, gCreds, name);
      } catch (e) {
        // Duplicate walk-in New patient (2026-10-22): explain it in a pop-up.
        if (/^Record already exists/.test(e.message) && window.noticePopup) window.noticePopup('Record already exists', e.message.replace(/^Record already exists\.\s*/, ''));
        else toast(e.message, 'err');
      }
    });
  }
  const v = (id) => document.getElementById(id).value.trim();

  document.getElementById('walkInBtn').addEventListener('click', walkInModal);
  document.getElementById('refreshBtn').addEventListener('click', () => { load(); loadSched(); });

  document.querySelector('#reqTable').addEventListener('click', (e) => {
    const c = e.target.closest('[data-confirm]');
    const x = e.target.closest('[data-decline]');
    const r = e.target.closest('[data-receipt]');
    if (c) confirmModal(+c.dataset.confirm);
    if (x) declinePaymentModal(+x.dataset.decline);
    if (r) receiptModal(r.dataset.receipt, r.dataset.appt ? +r.dataset.appt : 0);
  });
  document.querySelector('#schedTable').addEventListener('click', (e) => {
    const a = e.target.closest('[data-assign]');
    const r = e.target.closest('[data-resched]');
    const x = e.target.closest('[data-cancel]');
    const ci = e.target.closest('[data-checkin]');
    const ns = e.target.closest('[data-noshow]');
    if (ns) noShowModal(+ns.dataset.noshow, ns.dataset.name);
    const cu = e.target.closest('[data-undocheckin]');
    if (a) assignModal(+a.dataset.assign, a.dataset.current);
    if (r) reschedModal(+r.dataset.resched);
    if (x) cancelModal(+x.dataset.cancel);
    if (ci) void checkInModal(+ci.dataset.checkin, ci.dataset.name, ci.dataset.slot, !!ci.dataset.delayed);
    if (cu) setCheckIn(+cu.dataset.undocheckin, false);
  });

  // The bell's Reschedule / Reassign buttons open these modals directly while
  // this page is showing, or via the sessionStorage hand-off from elsewhere.
  window.__rcpOpen = async (act, id, nid) => {
    if (act !== 'reschedule' && act !== 'reassign') return;
    // From a notification: slide this in on top of its pop-up (reopening the
    // pop-up first when we just arrived from another page), so Cancel / Back
    // slide back to it.
    if (nid && window.stackNextModal) {
      if (!document.querySelector('#modalRoot:not([hidden]) .modal-pane')) window.notifPopup(nid);
      if (document.querySelector('#modalRoot:not([hidden]) .modal-pane')) window.stackNextModal(true);
    }
    try {
      if (act === 'reschedule') await reschedModal(id);
      else await assignModal(id, '');
    } finally { window.stackNextModal && window.stackNextModal(false); }
    // Opened from a notification: Cancel goes back to that pop-up.
    if (nid && window.notifReturnOnCancel) window.notifReturnOnCancel(nid);
  };
  window.__onNotifChanged = () => {
    if (!document.getElementById('schedTable')) return;
    loadSched();
  };
  try {
    const pending = JSON.parse(sessionStorage.getItem('dgt_rcp_action') || 'null');
    sessionStorage.removeItem('dgt_rcp_action');
    if (pending && pending.id) setTimeout(() => window.__rcpOpen(pending.act, +pending.id, +pending.nid || 0), 0);
  } catch (_) { /* nothing queued */ }

  boot();
})();
JS;
$PAGE_SCRIPT = str_replace('__RES_FEE__', number_format((float) setting('reservation_fee', '500.00'), 2), $PAGE_SCRIPT);
require __DIR__ . '/../../includes/layout.php';
