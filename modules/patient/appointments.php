<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['patient']);
// Patient Information Record first (2026-10-20): no booking until it is answered.
// Also when the record's review (every HF_REVIEW_MONTHS) is due (2026-10-20).
if (!healthFormReady((int) $me['user_id'])) {
    header('Location: /dgt_dental_clinic/modules/patient/health_form');
    exit;
}
$PAGE_TITLE = 'Appointments';
$ACTIVE = 'appointments';
$PAGE_FULL = true;   // full-bleed content column (matches the admin screens)

// The booking wizard's "For Myself" prefill (SELF_NAME/SELF_PHONE below) needs
// the account's own age too — computed here from patient_records.date_of_birth
// on the DB clock (never PHP's date(), same convention as everywhere else in
// this app). Blank when the record has no DOB on file (e.g. an older account
// or one a staff member created via a form that only ever collects a manual
// Age, not a birthdate) — the field then just behaves as it did before.
$selfAgeStmt = getDB()->prepare('SELECT TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) FROM patient_records WHERE patient_id = ?');
$selfAgeStmt->execute([$me['user_id']]);
$selfAge = $selfAgeStmt->fetchColumn();
$selfAge = ($selfAge !== null && $selfAge !== false) ? (string) $selfAge : '';

$fltLbl = 'text-[12px] font-semibold text-dgt-ink-500 dark:text-dgt-ink-300';
$fltInp = 'h-[38px] rounded-md border border-dgt-ink-200 bg-white px-3 text-[13.5px] text-dgt-blue-900 outline-none focus:border-dgt-blue-500 focus:ring-2 focus:ring-dgt-blue-500/20 dark:border-dgt-ink-600 dark:bg-dgt-ink-800 dark:text-dgt-ink-50 dark:[color-scheme:dark]';

ob_start();
require __DIR__ . '/../../includes/patient_booking_style.php';
require __DIR__ . '/../../includes/patient_calendar_style.php';
?>
<!-- Vendored (never CDN, per project convention) — scans the uploaded GCash
     receipt in step 2 of the booking wizard to auto-fill the reference
     number. Loaded on this page only; the worker/core/language-data files it
     needs are fetched lazily on first use, not on page load. -->
<script src="<?= e(asset('assets/js/vendor/tesseract/tesseract.min.js')) ?>"></script>
<div class="mb-[22px] flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-[22px] font-semibold text-dgt-blue-900 dark:text-dgt-ink-50">My Appointments</h1>
        <p class="appt-count" id="apptCount"></p>
    </div>
    <button class="inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md bg-dgt-teal-600 px-[15px] py-[9px] text-[13px] font-semibold text-white hover:bg-dgt-teal-700" id="bookBtn">+ Request Appointment</button>
</div>

<div id="planWrap"></div>

<!-- Status dropdown + date filter, 6 cards per page (2026-10-17; replaced the status tab buttons). -->
<div class="mb-[18px] flex flex-wrap items-end gap-3" id="apptFilters">
    <div class="flex flex-col gap-1">
        <label for="apptStatus" class="<?= $fltLbl ?>">Status</label>
        <select id="apptStatus" class="<?= $fltInp ?> min-w-[190px]">
            <option value="all">All</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rescheduled">Rescheduled</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>
    <div class="flex flex-col gap-1">
        <label for="apptDate" class="<?= $fltLbl ?>">Date</label>
        <input type="date" id="apptDate" class="<?= $fltInp ?> min-w-[170px]">
    </div>
    <button type="button" id="apptReset" class="hidden h-[38px] cursor-pointer rounded-md border border-dgt-ink-200 bg-white px-3.5 text-[13px] font-semibold text-dgt-ink-600 hover:bg-dgt-ink-50 dark:border-dgt-ink-600 dark:bg-dgt-ink-800 dark:text-dgt-ink-200 dark:hover:bg-white/5">Clear filters</button>
</div>

<div id="apptGrid"><p class="py-7 text-center text-steel-400 dark:text-dgt-ink-400">Loading&hellip;</p></div>
<nav id="apptPager" class="mt-5 flex flex-wrap items-center justify-between gap-3" aria-label="Appointment pages" hidden></nav>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  const SELF_NAME = __ME__;
  const SELF_PHONE = __PHONE__;
  const SELF_AGE = __AGE__;
  const RES_FEE = __FEE__;
  const METHODS = { bank: 'Bank Transfer', gcash: 'GCash' };
  // An admin-added method (migration 078): 'm<id>', details in payInfo.methods.
  const isExtra = (m) => /^m\d+$/.test(String(m || ''));
  const extraOf = (m) => ((payInfo && payInfo.methods) || []).find((x) => x.key === m) || null;
  // Reference-number rule per method (bank / extra / gcash).
  const refRule = (m) => m === 'gcash'
    ? { re: /^\d{13}$/, msg: 'GCash Reference Number must be exactly 13 digits' }
    : isExtra(m)
      ? { re: /^[A-Za-z0-9-]{6,30}$/, msg: 'Reference Number must be 6-30 letters or digits' }
      : { re: /^[A-Za-z0-9]{10,20}$/, msg: 'Bank Reference Number must be 10-20 characters' };
  const g = (id) => document.getElementById(id);
  let services = [];
  let others = [];   // people this account has booked "for someone else" before (2026-09)
  let dentists = []; // active dentist roster, fetched lazily for the doctor-absence reassign picker
  let payInfo = null;
  let receiptAmountRead = null;   // peso amount OCR read off the uploaded receipt (migration 068)
  // Admin "Require Payment Screenshot Upload" setting (migration 063, default on).
  async function loadPayInfo() {
    if (!payInfo) {
      try { payInfo = (await api('settings.php?action=payment_info')).payment || {}; }
      catch (_) { payInfo = {}; }
      // Admin-added methods (migration 078) join GCash / Bank Transfer as 'm<id>'.
      (payInfo.methods || []).forEach((m) => { METHODS[m.key] = m.name; METHOD_SUB[m.key] = 'Pay with ' + m.name; });
    }
    return payInfo;
  }
  const receiptRequired = () => !payInfo || payInfo.enable_receipt_validation !== false;
  // Upload the chosen screenshot, if any; '' when none was picked (allowed
  // only while the admin setting is off).
  async function uploadReceiptIfAny(file) {
    if (!file) return '';
    const fd = new FormData();
    fd.append('receipt', file);
    const res = await fetch('/dgt_dental_clinic/api/appointments.php?action=upload_receipt', {
      method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: fd,
    });
    const up = await res.json();
    if (!res.ok || up.ok === false) throw new Error(up.error || 'Receipt upload failed.');
    return up.receipt;
  }
  let pollTimer = null;
  let ALL = [];
  let TAB = 'all';
  let PAGE = 1;
  // Cards per page: 6, or two full rows when a wide screen fits 4+ columns,
  // so the last row never has empty spots (2026-10-17). Mirrors .appt-grid's
  // repeat(auto-fill, minmax(330px, 1fr)) with a 16px gap.
  function gridCols() {
    const w = g('apptGrid') ? g('apptGrid').clientWidth : 0;
    return Math.max(1, Math.floor((w + 16) / (330 + 16)));
  }
  function perPage() { const c = gridCols(); return c <= 3 ? 6 : c * 2; }
  let PER_PAGE = 6;
  let COLS = 1;
  let GRID_W = 0;   // width the current column count was worked out for

  // ---------- Receipt OCR (Tesseract.js, vendored — see the <script> tag
  // above) — scans the uploaded GCash receipt for step 2's #p_ref field.
  // Kept at this outer scope (not inside paymentModal()) so the worker is
  // created once and reused across every time the payment step is opened in
  // one page visit, instead of re-downloading the ~3MB core + ~11MB English
  // language data on every open.
  const TESS_BASE = '/dgt_dental_clinic/assets/js/vendor/tesseract';
  // Both live on window so the one engine survives nav.js re-running this
  // page script (one worker per tab, not one per visit).
  // Speed-ups (2026-10-11): lang-data is the small tessdata_fast English
  // model (~2 MB instead of ~11 MB, about 30% quicker, same reads on real
  // GCash receipts); cachePath keys the copy the browser keeps in IndexedDB,
  // so a returning patient never downloads it again. A failed load is
  // forgotten so the next upload tries again.
  function getTessWorker() {
    if (!window.Tesseract) return Promise.resolve(null);
    if (!window.__dgtTessWorker) {
      window.__dgtTessWorker = window.Tesseract.createWorker('eng', 1, {
        workerPath: TESS_BASE + '/worker.min.js',
        corePath: TESS_BASE + '/tesseract-core-simd-lstm.wasm.js',
        langPath: TESS_BASE + '/lang-data',
        cachePath: 'dgt-ocr-fast-v1',
        logger: (m) => { if (window.__dgtTessLog) window.__dgtTessLog(m); },
      }).catch(() => { window.__dgtTessWorker = null; return null; });
    }
    return window.__dgtTessWorker;
  }
  // Warm the engine up in the background once the page has settled, so it is
  // usually ready before the patient even reaches the payment step.
  // Receipt screenshot checks (portrait, readable, GCash, today's date, amount,
  // not edited / AI-made). Was turned off 2026-10-14, back ON the same day.
  // Set false to only preview the screenshot without checking it.
  const RECEIPT_CHECKS = true;
  (function warmOcr() {
    if (!RECEIPT_CHECKS) return;
    const c = navigator.connection;
    if (c && (c.saveData || /(^|-)2g$/.test(c.effectiveType || ''))) return;   // don't eat a slow / metered plan's data
    const go = () => getTessWorker();
    if ('requestIdleCallback' in window) requestIdleCallback(go, { timeout: 4000 }); else setTimeout(go, 1500);
  })();
  // On a slow connection / server the engine may still be downloading when a
  // receipt is picked: wait at most this long, then let the patient type the
  // reference number instead of holding Submit back.
  const OCR_LOAD_WAIT_MS = 15000;
  // Looks for a transaction date in OCR'd receipt text and returns it as a
  // Date (midnight, local time) or null if none was found. Handles GCash's
  // own "Sep 20, 2026" month-name style plus MM/DD/YYYY and YYYY-MM-DD as
  // fallbacks, since a misread character can shift which pattern matches.
  const MONTH_IDX = { jan: 0, feb: 1, mar: 2, apr: 3, may: 4, jun: 5, jul: 6, aug: 7, sep: 8, oct: 9, nov: 10, dec: 11 };
  function extractReceiptDate(text) {
    let m = text.match(/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?\s+(\d{1,2}),?\s+(\d{4})\b/i);
    if (m) {
      const d = new Date(parseInt(m[3], 10), MONTH_IDX[m[1].toLowerCase().slice(0, 3)], parseInt(m[2], 10));
      if (!isNaN(d)) return d;
    }
    m = text.match(/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/);
    if (m) {
      const d = new Date(parseInt(m[3], 10), parseInt(m[1], 10) - 1, parseInt(m[2], 10));
      if (!isNaN(d)) return d;
    }
    m = text.match(/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/);
    if (m) {
      const d = new Date(parseInt(m[1], 10), parseInt(m[2], 10) - 1, parseInt(m[3], 10));
      if (!isNaN(d)) return d;
    }
    return null;
  }

  // Soft warnings on the receipt upload stack into one paragraph instead of
  // the latest one overwriting an earlier one, since more than one can apply
  // to the same upload (e.g. an AI-generated image that also reads blurry).
  // What the upload box is currently warning about, sent with the booking so
  // the receptionist sees it on the receipt viewer (migration 067).
  function receiptWarningsText() {
    const w = g('rcptWarn');
    return w && !w.hidden ? (w._items || []).map((x) => x.msg).join('. ') : '';
  }
  // Submit stays blocked while a receipt is being checked or when the check
  // shows any error (2026-10-09): the date must be today, the amount at least
  // the fee, and the image a readable, unedited GCash / bank screenshot.
  let rcptChecking = false;
  // "Checking your payment screenshot" card with a spinner, a percentage and a
  // progress bar (2026-10-11), and the green "looks good" line.
  const RCPT_STATUS_HTML =
    '<div id="rcptScan" class="mt-3 w-full rounded-2xl border border-dgt-blue-100 bg-dgt-blue-50/60 px-4 py-3.5 text-left dark:border-dgt-blue-400/25 dark:bg-dgt-blue-500/10" hidden>' +
      '<div class="flex items-center gap-3.5">' +
        '<svg class="h-9 w-9 shrink-0 animate-spin motion-reduce:animate-none" viewBox="0 0 36 36" fill="none" aria-hidden="true">' +
          '<circle cx="18" cy="18" r="14" stroke-width="4" class="stroke-dgt-ink-200 dark:stroke-white/15"/>' +
          '<path d="M18 4a14 14 0 0 1 12.1 7" stroke-width="4" stroke-linecap="round" class="stroke-dgt-blue-700 dark:stroke-dgt-blue-300"/></svg>' +
        '<div class="min-w-0"><div class="text-[14px] font-bold text-dgt-blue-900 dark:text-white" role="status">Checking your payment screenshot&hellip;</div>' +
        '<div class="rcpt-scan-sub mt-0.5 text-[12.5px] tabular-nums text-dgt-ink-500 dark:text-dgt-ink-300">Getting ready&hellip; 0%</div></div></div>' +
      '<div class="mt-3 h-1.5 overflow-hidden rounded-full bg-dgt-blue-100 dark:bg-white/10" role="progressbar" aria-label="Screenshot check progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">' +
        '<div class="rcpt-scan-bar h-full rounded-full bg-dgt-blue-600 transition-[width] duration-300 ease-out motion-reduce:transition-none dark:bg-dgt-blue-300" style="width:0%"></div></div>' +
    '</div>' +
    '<div id="rcptOk" class="mt-2.5 w-full rounded-lg border border-ok/30 bg-ok-soft px-3 py-2.5 text-left text-[12.5px] font-medium text-ok dark:bg-ok/20" hidden><div class="flex items-center gap-2">' +
      '<svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>' +
      '<span>Receipt checked. You can submit your request.</span></div></div>';
  const RCPT_WARN_HTML = '<div id="rcptWarn" class="mt-3 w-full rounded-2xl border border-danger/40 bg-danger-soft px-4 py-3 text-left dark:border-danger/40 dark:bg-danger/15" role="alert" hidden></div>';
  function setScanProgress(scanEl, pct, label) {
    if (!scanEl) return;
    pct = Math.max(scanEl._pct || 0, Math.min(100, pct));   // never runs backwards
    scanEl._pct = pct;
    const n = Math.round(pct);
    const sub = scanEl.querySelector('.rcpt-scan-sub');
    if (sub) sub.textContent = label + ' ' + n + '%';
    const bar = scanEl.querySelector('.rcpt-scan-bar');
    if (bar) { bar.style.width = n + '%'; bar.parentElement.setAttribute('aria-valuenow', n); }
  }
  function resetScanProgress(scanEl) {
    if (!scanEl) return;
    scanEl._pct = 0;
    setScanProgress(scanEl, 0, 'Getting ready…');
  }
  function receiptBlocked() {
    if (!RECEIPT_CHECKS) return false;
    const w = g('rcptWarn');
    return !!g('p_file') && (rcptChecking || !!(w && !w.hidden && hasBlockingWarning(w)));
  }
  // A 'soft' row is only a heads-up (e.g. the recipient number couldn't be
  // read): it shows in the box but never blocks Submit.
  function hasBlockingWarning(el) { return (el._items || []).some((x) => x.kind !== 'soft'); }
  // Wraps the submit button's `disabled` so other code (e.g. wireRefCheck)
  // can't re-enable it while the receipt check is blocking it.
  function gateSubmit(btn) {
    if (!btn || btn._rcptSync) return;
    const d = Object.getOwnPropertyDescriptor(HTMLButtonElement.prototype, 'disabled');
    let other = d.get.call(btn);
    Object.defineProperty(btn, 'disabled', { configurable: true,
      get: () => d.get.call(btn),
      set: (v) => { other = !!v; d.set.call(btn, other || receiptBlocked()); } });
    btn._rcptSync = () => d.set.call(btn, other || receiptBlocked());
    btn._rcptSync();
  }
  function syncReceiptGate() {
    ['p_go', 'rp_go'].forEach((id) => { const b = g(id); if (b && b._rcptSync) b._rcptSync(); });
  }
  // Each problem is one row in the red box: an amber "?" when something could
  // not be read, a red "x" when the screenshot is wrong (2026-10-11).
  const WARN_ICON = {
    soft: '<span class="mt-px flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-warn text-[11px] font-bold text-white" aria-hidden="true">!</span>',
    unknown: '<span class="mt-px flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-dgt-blue-600 text-[11px] font-bold text-white" aria-hidden="true">?</span>',
    error: '<span class="mt-px flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-danger text-white" aria-hidden="true">' +
      '<svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span>',
  };
  function renderReceiptWarnings(el) {
    // Only heads-up rows: an amber box instead of the red one.
    const soft = !hasBlockingWarning(el);
    ['border-danger/40', 'bg-danger-soft', 'dark:border-danger/40', 'dark:bg-danger/15'].forEach((c) => el.classList.toggle(c, !soft));
    ['border-warn/40', 'bg-warn-soft', 'dark:border-warn/40', 'dark:bg-warn/15'].forEach((c) => el.classList.toggle(c, soft));
    el.innerHTML = '<ul class="flex flex-col gap-2">' + (el._items || []).map((x) =>
      '<li class="flex items-start gap-2.5 text-[13px] leading-snug ' +
        (x.kind === 'error' ? 'font-semibold text-danger' : 'font-medium text-dgt-ink-700 dark:text-dgt-ink-100') + '">' +
        WARN_ICON[x.kind] + '<span>' + esc(x.msg) + '</span></li>').join('') + '</ul>';
  }
  function clearReceiptWarnings(el) {
    el._items = []; el.innerHTML = ''; el.hidden = true;
  }
  function addReceiptWarning(warnEl, msg, kind) {
    warnEl._items = (warnEl._items || []).concat({ msg, kind: kind || 'error' });
    renderReceiptWarnings(warnEl);
    warnEl.hidden = false;
    syncReceiptGate();
  }
  // Note under the reference box: reading / filled in / not filled in.
  const REF_NOTE_CLS = {
    info: 'bg-dgt-blue-50 text-dgt-blue-800 dark:bg-dgt-blue-500/15 dark:text-dgt-blue-200',
    ok: 'bg-ok-soft text-ok dark:bg-ok/20',
    warn: 'bg-warn-soft text-[#9a5a0a] dark:bg-warn/20 dark:text-[#f3c77e]',
  };
  function setRefNote(kind, msg) {
    const n = g('refNote');
    if (!n) return;
    // The double-check note only shows when the screenshot's reference couldn't be read.
    const cn = g('refCheckNote');
    if (cn && receiptRequired()) cn.hidden = msg !== REF_UNREAD_MSG;
    if (!kind) { n.hidden = true; n.textContent = ''; return; }
    n.className = 'mt-1 rounded-xl px-3.5 py-2.5 text-[12.5px] leading-snug ' + REF_NOTE_CLS[kind];
    n.textContent = msg;
    n.hidden = false;
  }
  // While the screenshot upload is on, the GCash reference can only come from
  // the screenshot itself (2026-10-17): the box is greyed out and read-only,
  // so a reused receipt can't pass by changing one digit. Bank receipts differ
  // too much between banks to read reliably, so a bank reference stays typed.
  function refLocked(m) { return RECEIPT_CHECKS && receiptRequired() && m === 'gcash'; }
  // Reference box, placed under the upload so the order reads top to bottom.
  function refFieldHtml(m, value) {
    const up = receiptRequired();
    const locked = refLocked(m);
    const attrs = m === 'bank'
      ? 'maxlength="20" pattern="[A-Za-z0-9]{10,20}"'
      : isExtra(m) ? 'maxlength="30"'
      : 'type="tel" inputmode="numeric" maxlength="13" pattern="[0-9]{13}"';
    const ph = locked ? 'Filled in from your screenshot'
      : up ? 'Filled in from your screenshot, or type it'
      : (m === 'bank' ? '10–20 letters / digits' : isExtra(m) ? '6–30 letters / digits' : '13-digit reference number');
    const cls = locked ? INP + REF_LOCK_CLS : INP;
    return '<div class="' + FGF + ' mt-3.5"><label class="' + LBL + '">' + (m === 'bank' ? 'Bank' : isExtra(m) ? esc(METHODS[m] || 'Payment') : 'GCash') + ' Reference No.' + REQ + '</label>' +
      '<input class="' + cls + '" id="p_ref" ' + attrs + (locked ? ' readonly aria-readonly="true"' : '') +
        ' placeholder="' + ph + '" value="' + esc(value || '') + '">' + errBox('err_ref') +
      '<div id="refNote" hidden></div>' +
      (locked ? '<p id="refHint" hidden class="' + HINT + ' mt-0.5 leading-snug"></p>'
        : up ? '<p class="' + HINT + ' mt-0.5 leading-snug">After you upload your receipt above, we read the reference number for you. Check that it matches your receipt.</p>' : '') +
      '<div id="refCheckNote"' + (up ? ' hidden' : '') + ' class="mt-1 rounded-xl border border-warn/40 bg-warn-soft px-3.5 py-2.5 text-[12.5px] leading-snug text-[#9a5a0a] dark:bg-warn/20 dark:text-[#f3c77e]">' +
        '<strong>Note:</strong> Before submitting, please double check the details and the reference number so your request is not declined.</div>' +
      '</div>';
  }
  const REF_LOCK_CLS = ' cursor-not-allowed !bg-dgt-ink-100 !text-dgt-ink-600 dark:!bg-white/10 dark:!text-dgt-ink-300';
  // Manual mode: when the screenshot is fine but its GCash reference can't be
  // read, the locked box opens for typing. A new upload locks it again.
  function setRefManual(on) {
    const el = g('p_ref');
    if (!el) return;
    el.readOnly = !on;
    if (on) el.removeAttribute('aria-readonly'); else el.setAttribute('aria-readonly', 'true');
    REF_LOCK_CLS.trim().split(/\s+/).forEach(c => el.classList.toggle(c, !on));
    el.placeholder = on ? '13-digit reference number' : 'Filled in from your screenshot';
    const h = g('refHint');
    if (h) { h.textContent = on ? 'Type the 13-digit reference number exactly as it appears on your GCash receipt.' : ''; h.hidden = !on; }
    if (on) el.focus({ preventScroll: true });
  }
  const REF_UNREAD_MSG = 'The reference number could not be read. Please use the manual input below.';

  // Client-side mirror of includes/functions.php's inspectReceiptForManipulation()
  // — same signatures, same priority order — so a patient sees the warning
  // immediately in the upload box instead of only finding out at billing.
  // The authoritative check still re-runs server-side in api/appointments.php
  // action=create (never trusts the client), so this client-side copy being
  // skippable by disabling JS changes nothing about what staff ultimately see.
  const AI_TOOL_SIGNATURES = [
    'ChatGPT', 'gpt-image', 'DALL-E', 'DALL·E', 'OpenAI',
    'Midjourney', 'Stable Diffusion', 'Stability AI',
    'Adobe Firefly', 'Google Imagen', 'Microsoft Designer',
    'Bing Image Creator', 'Copilot Designer', 'Leonardo.Ai', 'NightCafe',
  ];
  // Deliberately excludes "Photoshop"/"Adobe Photoshop" and "Adobe XMP Core"
  // — those specific strings are also the generic APP13/XMP-toolkit
  // boilerplate many unrelated JPEG-writing libraries copy verbatim with no
  // actual editing involved. Confirmed as a real false positive on a
  // genuine, unedited GCash screenshot — see includes/functions.php's
  // inspectReceiptForManipulation() for the matching server-side exclusion.
  const EDIT_TOOL_SIGNATURES = [
    'Canva', 'GIMP', 'Paint.NET',
    'Affinity Photo', 'Adobe Illustrator', 'CorelDRAW', 'Pixlr',
  ];
  // Returns true when it found something worth flagging (AI-generated or
  // edited), so the caller can skip the separate OCR blur/amount/date checks
  // below — those are minor quality nitpicks that are pointless (and
  // confusing, stacked underneath) once we already know the image itself
  // isn't a genuine, unedited receipt.
  async function scanReceiptForManipulation(file, warnEl) {
    try {
      const buf = await file.arrayBuffer();
      // latin1/ISO-8859-1 maps each byte 0-255 straight to one char code, so
      // this is a byte-for-byte-safe string to run substring matches
      // against — same approach as the PHP version's raw stripos() scan.
      const bytes = new TextDecoder('iso-8859-1').decode(buf);
      const hasCi = (needle) => bytes.toLowerCase().indexOf(needle.toLowerCase()) !== -1;

      const matchedTool = AI_TOOL_SIGNATURES.find((t) => hasCi(t));

      if (hasCi('trainedAlgorithmicMedia') || hasCi('compositeWithTrainedAlgorithmicMedia')) {
        addReceiptWarning(warnEl, 'This looks like an AI-generated image, not a real payment screenshot');
        return true;
      }
      if (matchedTool && (hasCi('c2pa') || hasCi('claim_generator'))) {
        addReceiptWarning(warnEl, 'This looks like an AI-generated image, not a real payment screenshot');
        return true;
      }
      const editedSig = EDIT_TOOL_SIGNATURES.find((s) => hasCi(s));
      if (editedSig) {
        addReceiptWarning(warnEl, 'This image looks edited (' + editedSig + '). Upload the screenshot straight from your app');
        return true;
      }
    } catch (e) { /* best-effort — never blocks the upload on a read failure */ }
    return false;
  }

  // Scans `file` and fills #p_ref with a 13-digit GCash reference number if
  // one is found with reasonable confidence. Never blocks or clears
  // anything on failure — #p_ref stays a normal editable field either way,
  // so an unreadable scan just falls back to typing it in by hand.
  // GCash's own receipt layout always names itself somewhere on screen — any
  // one of these appearing in the OCR'd text is enough to accept it as a
  // genuine GCash screenshot. Bank receipts have no equivalent check (too
  // many different banks/apps to build one reliable keyword list without
  // false-positiving on legitimate ones), so this only ever runs when GCash
  // is the selected payment method.
  //
  // The "gcash" / "express send" wordmark itself is rendered in GCash's
  // stylized brand font on a saturated blue background — real-world testing
  // (a genuine GCash Express Send screenshot, confirmed false-positive'd by
  // this check) found Tesseract reliably fails to read that low-contrast
  // colored text at all, while the plain black-on-white amount/label text
  // lower on the receipt (e.g. "Total Amount Sent") OCRs cleanly. These
  // extra markers key off that reliably-readable template wording instead
  // of the brand name itself.
  const GCASH_MARKERS = [
    'gcash', 'g cash', 'express send',
    'total amount sent', 'amount sent', 'send money',
  ];
  const OCR_TIMEOUT_MS = 20000;
  const OCR_MAX_SIDE = 1600;
  async function shrinkForOcr(file) {
    try {
      const bmp = await createImageBitmap(file);
      const scale = Math.min(1, OCR_MAX_SIDE / Math.max(bmp.width, bmp.height));
      if (scale === 1) { bmp.close && bmp.close(); return file; }
      const c = document.createElement('canvas');
      c.width = Math.round(bmp.width * scale); c.height = Math.round(bmp.height * scale);
      c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
      bmp.close && bmp.close();
      return await new Promise((r) => c.toBlob((b) => r(b || file), 'image/jpeg', 0.92));
    } catch (_) { return file; }
  }
  // Reads the screenshot. The 13-digit reference it finds is kept on
  // warnEl._ref; the caller fills it in only when nothing else is wrong.
  async function scanReceiptForReference(file, warnEl, scanEl, method) {
    function addWarning(msg, kind) { addReceiptWarning(warnEl, msg, kind); }
    // Show progress straight away: the first scan downloads ~15 MB of OCR data.
    scanEl.hidden = false;
    setScanProgress(scanEl, 3, 'Getting ready…');
    window.__dgtTessLog = (m) => {
      if (!m || typeof m.progress !== 'number') return;
      if (m.status === 'recognizing text') setScanProgress(scanEl, 15 + m.progress * 80, 'Reading your screenshot…');
      else setScanProgress(scanEl, 3 + m.progress * 12, 'Getting ready…');
    };
    let loadTimer;
    const worker = await Promise.race([
      getTessWorker(),
      new Promise((r) => { loadTimer = setTimeout(() => r(null), OCR_LOAD_WAIT_MS); }),
    ]);
    clearTimeout(loadTimer);
    if (!worker) { window.__dgtTessLog = null; warnEl._noOcr = true; return; } // OCR unavailable / too slow: manual entry
    setScanProgress(scanEl, 15, 'Reading your screenshot…');
    try {
      // A full-size phone photo (e.g. 4000x6000) can keep Tesseract busy for
      // minutes, so the scan reads a copy capped at 1600px on the long side and
      // gives up after OCR_TIMEOUT_MS instead of leaving "Scanning…" forever.
      const small = await shrinkForOcr(file);
      let timer;
      const timedOut = new Promise((resolve) => { timer = setTimeout(() => resolve(null), OCR_TIMEOUT_MS); });
      // Only the plain text is used: skipping the hOCR / TSV / block output saves time.
      const result = await Promise.race([worker.recognize(small, {}, { text: true, blocks: false, hocr: false, tsv: false }), timedOut]);
      clearTimeout(timer);
      if (!result) {
        // Drop the stuck worker so the next upload starts a fresh one.
        try { worker.terminate(); } catch (_) { /* ignore */ }
        window.__dgtTessWorker = null;
        addWarning("Couldn't read the screenshot in time", 'unknown');
        return;
      }
      setScanProgress(scanEl, 97, 'Checking the details…');
      const { data } = result;
      const text = (data && data.text) || '';
      const confidence = (data && data.confidence) || 0;

      // Content check runs on whatever text WAS recognised, even at low
      // confidence (or none at all) — a keyword match is robust to a few
      // OCR errors elsewhere, and "this isn't even a GCash screenshot" is a
      // more specific, more useful message than a generic blur warning for
      // a document that clearly isn't a receipt at all — a handwritten
      // paper receipt, an unrelated photo/picture, anything with no GCash
      // markers anywhere. Zero recognised text is, if anything, the
      // *strongest* version of "doesn't contain a GCash marker" (there is
      // nothing there to contain one), so it's still routed through this
      // check for GCash uploads rather than assumed to be a photo-quality
      // problem. Bank Transfer has no content check to fall back on, so a
      // completely blank read there still means "blurry/unreadable".
      if (method === 'gcash') {
        const lower = text.toLowerCase();
        const looksLikeGcash = GCASH_MARKERS.some((m) => lower.indexOf(m) !== -1);
        if (!looksLikeGcash) {
          addWarning("This doesn't look like a GCash receipt");
          return;
        }
      } else if (!text.trim()) {
        addWarning('The screenshot is blurry or unreadable', 'unknown');
        return;
      }

      // Only now gate on confidence — reference/amount/date extraction below
      // genuinely needs accurate digit reads, unlike the keyword check above.
      if (confidence < 40) {
        addWarning('The screenshot is blurry or unreadable', 'unknown');
        return;
      }

      // GCash reference numbers are 13 digits; receipts sometimes render
      // them with a space or two between digit groups, so collapse runs of
      // digits/spaces before matching a 13-digit span.
      // Recipient check (2026-10-21): GCash shows the receiver as a masked
      // number like "+63 9•• ••• 4252" (the reader often turns "9••" into
      // "Yee"), so the last 4 digits on that "+63" line
      // must match the clinic's GCash number from Settings. A wrong number
      // blocks Submit; a number that can't be read is only a heads-up.
      if (method === 'gcash') {
        const want = String((payInfo && payInfo.gcash_number) || '').replace(/\D/g, '').slice(-4);
        if (want.length === 4) {
          const seen = text.split(/\r?\n/).filter((l) => /\+\s?6\s?3|\b63\s?9/.test(l))
            .map((l) => { const m = l.match(/(\d{4,})\D*$/); return m ? m[1].slice(-4) : ''; })
            .filter(Boolean);
          if (!seen.length) {
            addWarning("Couldn't read the recipient's number. The clinic will check it before approving.", 'soft');
          } else if (seen.indexOf(want) === -1) {
            addWarning("This payment wasn't sent to the clinic's GCash number (ending in " + want + ')');
          }
        }
      }

      const digitRuns = text.replace(/[^0-9\n ]+/g, ' ');
      const refMatch = digitRuns.match(/\d(?:[\d ]{11,25})\d/);
      const ref = refMatch ? refMatch[0].replace(/\s+/g, '').slice(0, 13) : '';
      if (/^\d{13}$/.test(ref)) warnEl._ref = ref;

      // Soft amount check (never blocks submission — staff verify the real
      // amount at billing regardless): flag a receipt that appears to show
      // less than the required reservation fee.
      // The largest peso figure is the total sent (a receipt also lists e.g. a
      // zero fee); the receptionist confirms it at Approve (migration 068).
      // OCR often reads the peso sign as "P", so accept that, plus any figure
      // on a line that says "Amount" (e.g. "Amount 500.00").
      const amts = [...text.matchAll(/(?:PHP|Php|php|₱|P)\s?([\d,]+\.\d{2})\b/g),
                    ...text.matchAll(/amount[^\d\n]{0,25}([\d,]+\.\d{2})\b/gi)]
        .map((m) => parseFloat(m[1].replace(/,/g, ''))).filter((v) => !isNaN(v) && v > 0);
      if (amts.length) {
        const paid = Math.max(...amts);
        warnEl._amount = paid;
        if (!isNaN(paid) && paid < RES_FEE) {
          addWarning('Amount ' + peso(paid) + ' is less than the ' + peso(RES_FEE) + ' fee');
        }
      } else {
        addWarning("Couldn't read the amount", 'unknown');
      }

      // Soft recency check: catches a patient re-uploading a screenshot of
      // an old, already-used payment instead of a fresh one. Never blocks —
      // an OCR misread of the date (wrong digit/month) is common, and staff
      // still verify the actual payment at billing regardless.
      // Tightened 2026-09 at the user's request — the receipt must be dated
      // exactly today; any other date (even yesterday) is flagged, no
      // multi-day grace window any more.
      const receiptDate = extractReceiptDate(text);
      if (receiptDate) {
        const today = new Date(); today.setHours(0, 0, 0, 0);
        receiptDate.setHours(0, 0, 0, 0);
        const ageDays = Math.round((today - receiptDate) / 86400000);
        const shown = receiptDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        if (ageDays > 0) {
          addWarning('Payment date ' + shown + ' is not today');
        } else if (ageDays < 0) {
          addWarning('Payment date ' + shown + ' is in the future');
        }
      } else {
        addWarning("Couldn't read the payment date", 'unknown');
      }
    } catch (e) {
      addWarning('The screenshot is blurry or unreadable', 'unknown');
    } finally {
      window.__dgtTessLog = null;
    }
  }

  const BTN     = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold disabled:opacity-50';
  const BTN_SM  = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold disabled:opacity-50';
  const PRIMARY = ' bg-dgt-teal-600 text-white hover:bg-dgt-teal-700';
  const GHOST   = ' border border-dgt-ink-200 bg-transparent text-dgt-blue-800 hover:bg-dgt-ink-50 dark:border-dgt-ink-600 dark:text-dgt-ink-100 dark:hover:bg-white/5';
  const DANGER  = ' bg-danger text-white hover:brightness-95';
  const FG      = 'flex flex-col gap-1.5';
  const FGF     = 'flex flex-col gap-1.5 col-span-2';
  const LBL     = 'text-xs font-semibold text-dgt-ink-500 dark:text-dgt-ink-300';
  const INP     = 'w-full rounded-md border border-dgt-ink-200 bg-white px-2.5 py-2 text-[13px] text-dgt-ink-700 outline-none focus:border-dgt-blue-500 dark:border-dgt-ink-600 dark:bg-dgt-ink-900 dark:text-dgt-ink-100';
  const GRID2   = 'grid grid-cols-2 gap-3.5';
  const CHECKLN = 'flex items-center gap-2 text-[13px]';
  const HINT    = 'text-[11px] text-dgt-ink-400';
  const REQ     = ' <span class="text-danger">*</span>';   // required-field marker

  const ICON_CAL = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';
  const ICON_CLK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';

  const longDate = (d) => {
    if (!d) return 'N/A';
    const x = new Date(d + 'T00:00:00');
    return isNaN(x) ? d : x.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  };
  const refNo = (id) => 'APT-' + String(id).padStart(3, '0');

  // Start of an appointment's slot, in ms (NaN when the row has no usable date).
  function apptStartMs(a) {
    return new Date(String(a.scheduled_date).slice(0, 10) + 'T' + String(a.time_slot || '00:00:00')).getTime();
  }

  // Patient-facing status: friendly label + which tab it belongs to.
  function statusOf(a) {
    if (a.status === 'pending') return { key: 'pending', cls: 'pending', txt: 'Pending Approval' };
    // A payment-only rejection (2026-09) — the receptionist found a problem
    // with just the reservation receipt (blurry, mismatched reference, ...).
    // Distinct from Cancelled: the appointment itself is still alive, waiting
    // on the patient to fix and resubmit the payment, not a dead request.
    if (a.status === 'payment_declined') return { key: 'payment_declined', cls: 'payment_declined', txt: 'Payment Declined' };
    // Grace period (migration 088): not checked in 15 minutes after the start
    // = Delayed / Standby; checked in after that = Arrived (Late). Both still
    // filter under Approved.
    if (a.delayed_at && ['approved', 'rescheduled'].includes(a.status)) {
      return a.checked_in_at
        ? { key: 'approved', cls: 'arrived_late', txt: 'Arrived (Late)' }
        : { key: 'approved', cls: 'delayed', txt: 'Delayed / Standby' };
    }
    if (a.status === 'approved' || a.status === 'scheduled') {
      return { key: 'approved', cls: 'approved', txt: 'Approved' };
    }
    if (a.status === 'rescheduled') return { key: 'rescheduled', cls: 'rescheduled', txt: 'Rescheduled' };
    if (a.status === 'no_show') return { key: 'no_show', cls: 'no_show', txt: 'No Show' };
    return { key: 'cancelled', cls: 'cancelled', txt: 'Cancelled' };
  }

  function card(a) {
    const st = statusOf(a);
    const active = ['pending', 'approved', 'rescheduled'].includes(st.key);
    const declined = st.key === 'payment_declined';
    // The reservation fee only transfers to a rescheduled visit with 24h+ notice
    // (T&C §4). Inside that window a confirmed appointment can't be moved here —
    // the patient calls the clinic, or forfeits the fee by not showing.
    const confirmed = ['approved', 'rescheduled'].includes(st.key);
    const startMs = apptStartMs(a);
    // Once the slot's start time has passed the patient can't act on it here — a
    // never-approved request auto-expires server-side, a missed confirmed visit
    // is the front desk's to resolve.
    const past = Number.isFinite(startMs) && startMs < Date.now();
    // A doctor-absence-affected appointment (a.doctor_absent, from the API's
    // dentist_absences join — never the staff-only absence reason) is exempt
    // from the 24-hour reschedule gate below: the clinic caused this, not the
    // patient, so it should never cost them the fee-transfer window.
    const tooLate = confirmed && !past && !a.doctor_absent && Number.isFinite(startMs) && (startMs - Date.now() < 86400000);
    const canResched = active && !tooLate && !past;
    // A payment-declined row isn't in `active` (it shouldn't offer Reschedule
    // — nothing about the date/time is the issue) but Cancel should still work
    // in case the patient would rather not resubmit at all.
    const canCancel = (active || declined) && !past;
    const canResubmitPayment = declined && !past;
    const forOther = a.booking_for === 'other';
    const cancelReason = a.patient_cancellation_reason || a.staff_cancellation_reason || a.decline_reason || a.cancellation_reason || '';

    return '<div class="appt-c">' +
      '<div class="appt-c-head">' +
        badgeHTML(st.txt, st.cls) +
      '</div>' +
      '<h3>' + esc(a.requested_service || 'Appointment') + '</h3>' +
      '<div class="appt-c-dr">' + esc(a.dentist_name || 'Dentist to be assigned') + '</div>' +
      (a.treatment_plan_id ? '<div class="appt-c-for">Follow-up recommended by your dentist</div>' : '') +
      '<div class="appt-c-line">' + ICON_CAL + '<span>' + esc(longDate(a.scheduled_date)) + '</span></div>' +
      '<div class="appt-c-line">' + ICON_CLK + '<span>' + esc(fmtTime(a.time_slot)) + '</span></div>' +
      (forOther
        ? '<div class="appt-c-for">For: <strong>' + esc(a.patient_name) + '</strong>' +
          (a.relationship ? ' (' + esc(a.relationship) + (a.patient_age ? ', age ' + esc(a.patient_age) : '') + ')' : '') + '</div>'
        : '') +
      ((st.key === 'cancelled' || declined) && cancelReason
        ? '<div class="appt-c-reason">' + esc(cancelReason) + '</div>' : '') +
      (a.doctor_absent && active
        ? '<div class="appt-c-notice">Your doctor is unavailable on ' + esc(longDate(a.scheduled_date)) + '. Please reschedule or cancel your appointment.</div>'
        : '') +
      (st.cls === 'delayed'
        ? '<div class="appt-c-notice">You did not check in within 15 minutes of your appointment, so your slot was released. Your booking is kept: please go to the front desk when you arrive and we will seat you in the next available slot.</div>'
        : '') +
      (past && (active || declined) && st.cls !== 'delayed' && st.cls !== 'arrived_late'
        ? '<div class="' + HINT + '" style="margin-top:6px">This appointment date has passed. Please contact the clinic if you still need to be seen. A new booking may be required.</div>'
        : tooLate
          ? '<div class="' + HINT + '" style="margin-top:6px">Less than 24 hours away: this can no longer be rescheduled online. You can still cancel, but the reservation fee is forfeited (it only transfers to a reschedule made 24 hours ahead).</div>'
          : '') +
      '<div class="appt-c-actions">' +
        '<button class="' + BTN_SM + GHOST + '" data-view="' + a.appointment_id + '">View Details</button>' +
        (canResubmitPayment ? '<button class="' + BTN_SM + PRIMARY + '" data-resubmit-payment="' + a.appointment_id + '">Resubmit</button>' : '') +
        (canResched ? '<button class="' + BTN_SM + GHOST + '" data-resched="' + a.appointment_id + '">Reschedule</button>' : '') +
        (canCancel ? '<button class="' + BTN_SM + DANGER + '" data-cancel="' + a.appointment_id + '">Cancel</button>' : '') +
      '</div>' +
    '</div>';
  }

  function render() {
    const buckets = { all: [], pending: [], approved: [], rescheduled: [], cancelled: [] };
    // A payment-declined request has no tab of its own: it sits under Pending
    // (its card still shows the Payment Declined badge and the resubmit button).
    ALL.forEach((a) => { let k = statusOf(a).key; if (k === 'payment_declined') k = 'pending'; buckets.all.push(a); if (buckets[k]) buckets[k].push(a); });

    const LBL = { all: 'All', pending: 'Pending', approved: 'Approved', rescheduled: 'Rescheduled', cancelled: 'Cancelled' };
    [...g('apptStatus').options].forEach((o) => { o.textContent = LBL[o.value] + ' (' + (buckets[o.value] || []).length + ')'; });
    const activeN = buckets.pending.length + buckets.approved.length + buckets.rescheduled.length;
    g('apptCount').textContent = ALL.length + ' total · ' + activeN + ' active';

    const day = g('apptDate').value;
    const rows = (buckets[TAB] || buckets.all)
      .filter((a) => !day || String(a.scheduled_date).slice(0, 10) === day)
      .slice()
      .sort((x, y) => String(y.scheduled_date).localeCompare(String(x.scheduled_date)));
    g('apptReset').classList.toggle('hidden', TAB === 'all' && !day);

    COLS = gridCols();
    GRID_W = g('apptGrid').clientWidth;
    PER_PAGE = COLS <= 3 ? 6 : COLS * 2;
    const pages = Math.max(1, Math.ceil(rows.length / PER_PAGE));
    PAGE = Math.min(Math.max(1, PAGE), pages);
    const from = (PAGE - 1) * PER_PAGE;
    const shown = rows.slice(from, from + PER_PAGE);
    g('apptGrid').innerHTML = shown.length
      // Exactly COLS columns, so the grid always matches the page size and
      // cards fill rows left to right with no stray empty tracks.
      ? '<div class="appt-grid" style="grid-template-columns:repeat(' + COLS + ',minmax(0,1fr))">' + shown.map(card).join('') + '</div>'
      : '<p class="py-7 text-center text-steel-400 dark:text-dgt-ink-400">' +
          (day || TAB !== 'all' ? 'No appointments match these filters.' : 'Nothing here yet.') + '</p>';
    renderPager(rows.length, pages, from, shown.length);
  }

  // Previous / page numbers / Next under the cards; hidden when one page is enough.
  function renderPager(total, pages, from, count) {
    const nav = g('apptPager');
    if (pages <= 1) { nav.hidden = true; nav.innerHTML = ''; return; }
    const base = 'inline-flex h-9 min-w-9 cursor-pointer items-center justify-center rounded-md border px-3 text-[13px] font-semibold ';
    const idle = 'border-dgt-ink-200 bg-white text-dgt-ink-600 hover:bg-dgt-ink-50 dark:border-dgt-ink-600 dark:bg-dgt-ink-800 dark:text-dgt-ink-200 dark:hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-40';
    const on = 'border-dgt-blue-900 bg-dgt-blue-900 text-white dark:border-dgt-blue-500 dark:bg-dgt-blue-500';
    // Page numbers: first, last, and the ones next to the current page.
    const nums = [];
    for (let i = 1; i <= pages; i++) {
      if (i === 1 || i === pages || Math.abs(i - PAGE) <= 1) nums.push(i);
      else if (nums[nums.length - 1] !== '…') nums.push('…');
    }
    nav.innerHTML =
      '<p class="text-[13px] text-dgt-ink-500 dark:text-dgt-ink-300">Showing ' + (from + 1) + '–' + (from + count) + ' of ' + total + '</p>' +
      '<div class="flex flex-wrap items-center gap-1.5">' +
        '<button type="button" class="' + base + idle + '" data-page="' + (PAGE - 1) + '"' + (PAGE === 1 ? ' disabled' : '') + '>Previous</button>' +
        nums.map((n) => n === '…'
          ? '<span class="px-1 text-dgt-ink-400" aria-hidden="true">…</span>'
          : '<button type="button" class="' + base + (n === PAGE ? on : idle) + '" data-page="' + n + '"' +
              (n === PAGE ? ' aria-current="page"' : '') + ' aria-label="Page ' + n + '">' + n + '</button>').join('') +
        '<button type="button" class="' + base + idle + '" data-page="' + (PAGE + 1) + '"' + (PAGE === pages ? ' disabled' : '') + '>Next</button>' +
      '</div>';
    nav.hidden = false;
  }

  async function loadAppts() {
    try {
      const { appointments } = await api('appointments.php?filter=all');
      const nowMs = Date.now();
      // Hide completed visits (they live on Treatment & Billing) and any cancelled
      // / declined booking whose date has already passed — the row + reason stay
      // in the database for staff and the History Log, the patient just doesn't
      // keep seeing stale cancellations.
      ALL = appointments.filter((a) => {
        // Completed visits live on Treatment & Billing; a No Show can't be
        // rescheduled, so it is hidden like a past cancellation (2026-10-17).
        if (a.status === 'completed' || a.status === 'no_show') return false;
        if (a.status === 'cancelled' || a.status === 'payment_declined') {
          const t = apptStartMs(a);
          if (Number.isFinite(t) && t < nowMs) return false;
        }
        return true;
      });
      render();
      loadPlans();
    } catch (e) { g('apptGrid').innerHTML = '<p class="py-7 text-center text-steel-400 dark:text-dgt-ink-400">' + esc(e.message) + '</p>'; }
  }

  /* ---------- Recommended Next Services (Treatment Plan module, migration 066) ----------
     Follow-ups a dentist recommended when logging a visit. "Schedule Now"
     opens the booking wizard with the patient, service and dentist locked. */
  let PLANS = [];
  const ICON_PLAN = '<svg viewBox="0 0 24 24" class="h-[17px] w-[17px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 15l2 2 4-4"/></svg>';
  async function loadPlans() {
    try { PLANS = (await api('treatment_plans.php?action=mine')).plans || []; } catch (_) { PLANS = []; }
    const box = g('planWrap');
    if (!box) return;
    if (!PLANS.length) { box.innerHTML = ''; return; }
    box.innerHTML = '<section class="mb-[18px] rounded-xl border border-dgt-teal-600/30 bg-white p-4 shadow-sm dark:border-dgt-teal-500/30 dark:bg-dgt-ink-800 sm:p-5" aria-labelledby="planHead">' +
      '<div class="mb-3 flex items-center gap-2.5">' +
      '<span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-dgt-teal-50 text-dgt-teal-600 dark:bg-dgt-teal-500/20 dark:text-dgt-teal-200">' + ICON_PLAN + '</span>' +
      '<h2 id="planHead" class="text-[15px] font-semibold text-dgt-blue-900 dark:text-dgt-ink-50">Recommended Next Services</h2>' +
      '<span class="rounded-full bg-dgt-teal-600 px-2 py-0.5 text-[11px] font-semibold text-white">' + PLANS.length + '</span></div>' +
      '<ul class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">' + PLANS.map(planItem).join('') + '</ul></section>';
  }
  function planItem(p) {
    // The clinic is closed on Sundays: show the Monday after instead.
    let due = String(p.due_date).slice(0, 10);
    const dd = new Date(due + 'T00:00:00');
    if (dd.getDay() === 0 && !window.SUNDAY_OPEN) { dd.setDate(dd.getDate() + 1); due = dd.getFullYear() + '-' + String(dd.getMonth() + 1).padStart(2, '0') + '-' + String(dd.getDate()).padStart(2, '0'); }
    const overdue = due < todayISO();
    return '<li class="flex flex-col gap-1 rounded-lg border border-dgt-ink-200 p-3.5 dark:border-white/10">' +
      '<div class="text-[14px] font-semibold text-dgt-blue-900 dark:text-dgt-ink-50">' + esc(p.service_name) + '</div>' +
      (p.treated_patient_id ? '<div class="text-[12.5px] text-dgt-ink-500 dark:text-dgt-ink-300">For: <strong>' + esc(p.for_name) + '</strong></div>' : '') +
      '<div class="text-[12.5px] text-dgt-ink-600 dark:text-dgt-ink-300">Dr. ' + esc(p.dentist_name) + ' &middot; ' + (p.doctor_type === 'roaming' ? 'Specialist' : 'In-House') + '</div>' +
      '<div class="text-[12.5px] ' + (overdue ? 'font-semibold text-warn' : 'text-dgt-ink-500 dark:text-dgt-ink-400') + '">Recommended in ' +
        esc(String(p.timeframe_recommendation).toLowerCase()) + ' &middot; ' + (overdue ? 'due since ' : 'around ') + esc(longDate(due)) + '</div>' +
      '<div class="mt-2"><button class="' + BTN_SM + PRIMARY + '" data-plan="' + p.plan_id + '">Schedule Now</button></div></li>';
  }
  g('planWrap').addEventListener('click', (e) => {
    const b = e.target.closest('[data-plan]');
    if (!b) return;
    const plan = PLANS.find((x) => String(x.plan_id) === b.dataset.plan);
    if (plan) bookModal({ _plan: plan });
  });

  /* ---------- 3-step wizard indicator ---------- */
  function wizSteps(active) {
    return '<div class="wiz-steps">' + ['Appointment', 'Payment', 'Finished'].map((l, i) => {
      const n = i + 1;
      const cls = n < active ? 'done' : (n === active ? 'on' : '');
      return '<div class="wiz-step ' + cls + '"><span class="wiz-num">' + (n < active ? '✓' : n) + '</span>' +
        '<span class="wiz-lbl">' + l + '</span></div>';
    }).join('') + '</div>';
  }

  const NAME_PARTS = String(SELF_NAME).trim().split(/\s+/);
  const SELF_FIRST = NAME_PARTS.shift() || '';
  const SELF_LAST = NAME_PARTS.join(' ');

  /* ---------- Booking: step 1 (appointment details) ---------- */
  const OTHER_MAX_AGE = 17;   // OTHER_MAX_AGE in includes/functions.php
  function otherOptionsHtml(selectedId) {
    // Children only (2026-10-23): a person now 18+ books with their own account.
    const opts = others.filter((x) => x.age == null || x.age === '' || +x.age <= OTHER_MAX_AGE).map((x) =>
      '<option value="' + x.treated_patient_id + '"' + (String(x.treated_patient_id) === String(selectedId) ? ' selected' : '') + '>' +
      esc(x.full_name) + (x.age != null && x.age !== '' ? ' (age ' + x.age + ')' : '') + '</option>').join('');
    // "" always means "a new person, typed in below" — the default pick, so a
    // first-time booking needs no extra step to reveal the name fields. Picking
    // this creates a real patient record for that person, exactly like a
    // walk-in's "New patient" flow (2026-09, replacing the earlier saved
    // "family member" list — repeat visits are offered here instead, built
    // straight off this account's own past "for someone else" bookings).
    return '<option value="">+ Add a Child</option>' + opts;
  }

  /* ---------- Booking draft (2026-10-10, adviser feedback) ----------
     Step 1 of the wizard is saved to sessionStorage on every change, plus
     which date's time pop-up is open. A reload or dropped connection brings the
     wizard back as it was (pop-up open again if it was open, else the picked
     date green with its time). An accidental click outside the wizard keeps
     the draft, so "+ Request Appointment" reopens it as it was; Back, X,
     Escape, submitting or "Start over" drop it ("Start over" was removed 2026-10-11). Follow-up (treatment plan) bookings are not
     saved: they are one click from the Recommended Next Services card. */
  const DRAFT_KEY = 'dgt_book_draft';
  let draftActive = false;
  function readDraft() {
    try {
      const raw = sessionStorage.getItem(DRAFT_KEY);
      if (!raw) return null;
      const d = JSON.parse(raw);
      if (d && typeof d === 'object' && d.v === 1) return d;
    } catch (_) { /* private mode or corrupted: fall through */ }
    clearDraft();
    return null;
  }
  function clearDraft() { try { sessionStorage.removeItem(DRAFT_KEY); } catch (_) { /* blocked */ } }
  function writeDraft(d) { try { sessionStorage.setItem(DRAFT_KEY, JSON.stringify(d)); } catch (_) { /* blocked */ } }
  if (window.__bookDraftOnClose) document.removeEventListener('dgt:modalclose', window.__bookDraftOnClose);
  // Closing the wizard keeps what was entered, flagged closed, so a reload
  // does not pop it back up but "+ Request Appointment" brings it back.
  // Back / X / Cancel / Escape mean "I'm done": forget it. Only an accidental
  // click outside the dialog (or code moving between steps) keeps it.
  window.__bookDraftOnClose = (e) => {
    if (!draftActive) return;
    draftActive = false;
    const why = (e && e.detail && e.detail.reason) || '';
    if (why === 'button' || why === 'escape') { clearDraft(); return; }
    const d = readDraft();
    if (d) { d.open = false; d.popupDay = null; writeDraft(d); }
  };
  // Worth bringing back only if something was actually entered.
  const draftHasInput = (d) => !!(d.service_id || d.scheduled_date || d.notes || d.insurance_provider || d.booking_for === 'other');
  function pfFromDraft(d) {
    const okDate = (x) => /^\d{4}-\d{2}-\d{2}$/.test(x || '');
    return {
      _restored: true,
      _popupDay: okDate(d.popupDay) ? d.popupDay : null,
      booking_for: d.booking_for === 'other' ? 'other' : 'self',
      treated_patient_id: d.treated_patient_id || undefined,
      patient_first: d.patient_first, patient_last: d.patient_last,
      patient_phone: d.patient_phone, relationship: d.relationship,
      date_of_birth: okDate(d.date_of_birth) ? d.date_of_birth : '',
      insurance_provider: d.insurance_provider, notes: d.notes,
      service_id: /^\d+$/.test(String(d.service_id || '')) ? String(d.service_id) : undefined,
      scheduled_date: okDate(d.scheduled_date) ? d.scheduled_date : undefined,
      time_slot: d.time_slot || undefined,
    };
  }
  document.addEventListener('dgt:modalclose', window.__bookDraftOnClose);

  // Set when the wizard was opened from a card on the Services page: its Back
  // button then returns there (2026-10-17). Cleared by "+ Request Appointment".
  let backToServices = false;
  function goToServices() {
    const a = document.createElement('a');
    a.href = '/dgt_dental_clinic/modules/patient/services';
    a.setAttribute('data-nav', '');
    a.hidden = true;
    document.body.appendChild(a);
    a.click();   // nav.js does the in-app page swap
    a.remove();
  }

  async function bookModal(pf) {
    pf = pf || {};
    if (!services.length) {
      try { services = (await api('services.php?active=1')).services; }
      catch (e) { return toast(e.message, 'err'); }
    }
    // Refetched fresh every open — small, and it may have just changed (a new
    // booking for someone new since the last open).
    try { others = (await api('appointments.php?action=for_others')).people; }
    catch (e) { others = []; }

    // Booking a dentist's follow-up: patient, service and dentist are locked.
    const plan = pf._plan || null;
    const planOther = !!(plan && plan.treated_patient_id);
    const ONLINE_LEAD_DAYS = __LEAD_DAYS__;   // onlineLeadDays(): 2, or 0 when the admin turned the rule off
    const minDate = todayISO(ONLINE_LEAD_DAYS);
    const isOther = plan ? planOther : pf.booking_for === 'other';
    const vFirst = pf.patient_first != null ? pf.patient_first : (isOther ? '' : SELF_FIRST);
    const vLast  = pf.patient_last  != null ? pf.patient_last  : (isOther ? '' : SELF_LAST);
    const vPhone = pf.patient_phone != null ? pf.patient_phone : (isOther ? '' : SELF_PHONE);
    const vAge   = pf.patient_age   != null ? pf.patient_age   : (isOther ? '' : SELF_AGE);

    openModal(modalShell('<span class="text-danger">Appointment Details</span>',
      wizSteps(1) +
      (plan
        ? '<div class="rounded-lg border border-dgt-teal-600/30 bg-dgt-teal-50/60 p-3.5 dark:border-dgt-teal-500/30 dark:bg-dgt-teal-500/10">' +
          '<div class="mb-1.5 text-[12px] font-semibold text-dgt-teal-700 dark:text-dgt-teal-200">Recommended follow-up</div>' +
          '<dl class="grid grid-cols-[76px_1fr] gap-x-3 gap-y-1 text-[13px]">' +
          '<dt class="text-dgt-ink-500 dark:text-dgt-ink-400">Patient</dt><dd class="font-semibold text-dgt-blue-900 dark:text-dgt-ink-50">' + esc(planOther ? plan.for_name : SELF_NAME) + '</dd>' +
          '<dt class="text-dgt-ink-500 dark:text-dgt-ink-400">Service</dt><dd class="font-semibold text-dgt-blue-900 dark:text-dgt-ink-50">' + esc(plan.service_name) + '</dd>' +
          '<dt class="text-dgt-ink-500 dark:text-dgt-ink-400">Dentist</dt><dd class="font-semibold text-dgt-blue-900 dark:text-dgt-ink-50">Dr. ' + esc(plan.dentist_name) + ' (' + (plan.doctor_type === 'roaming' ? 'Specialist' : 'In-House') + ')</dd>' +
          '</dl><p class="' + HINT + ' mt-2 mb-0">Set by your dentist. Only times when Dr. ' + esc(plan.dentist_name) + ' is free can be picked.</p></div>'
        : '<div class="' + FGF + '"><label class="' + LBL + '">Who is this appointment for?</label>' +
          '<div class="flex flex-wrap gap-4"><label class="' + CHECKLN + '"><input type="radio" name="bfor" value="self"' + (isOther ? '' : ' checked') + ' class="accent-dgt-blue-500"> For Myself</label>' +
          '<label class="' + CHECKLN + '"><input type="radio" name="bfor" value="other"' + (isOther ? ' checked' : '') + ' class="accent-dgt-blue-500"> For My Child</label></div>' +
          '<div id="otherNote" class="mt-2 flex items-start gap-2 rounded-md border border-warn/40 bg-warn-soft px-3 py-2 text-[12.5px] leading-snug text-[#8a5a00] dark:bg-warn/20 dark:text-[#fcd57a]" style="display:' + (isOther ? '' : 'none') + '">' +
            '<svg viewBox="0 0 24 24" class="mt-px h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>' +
            '<span><strong>Note:</strong> For your child under 18 only. Anyone 18 or older can create their own account and book for themselves.</span></div></div>') +
      '<div class="' + GRID2 + ' mt-2.5">' +
      (plan ? '' :
      '<div class="' + FGF + '" id="otherWrap" style="display:' + (isOther ? '' : 'none') + '">' +
      '<label class="' + LBL + '">Child' + REQ + '</label>' +
      '<select class="' + INP + '" id="b_other">' + otherOptionsHtml(pf.treated_patient_id) + '</select>' +
      '</div>' +
      '<div class="' + FG + '" id="selfFirstWrap" style="display:' + (isOther ? 'none' : '') + '"><label class="' + LBL + '">First Name' + REQ + '</label><input class="' + INP + '" id="b_first" value="' + esc(vFirst) + '"></div>' +
      '<div class="' + FG + '" id="selfLastWrap" style="display:' + (isOther ? 'none' : '') + '"><label class="' + LBL + '">Last Name' + REQ + '</label><input class="' + INP + '" id="b_last" value="' + esc(vLast) + '"></div>') +
      '<div class="' + FGF + '" id="relWrap" style="display:' + (planOther ? '' : 'none') + '"><label class="' + LBL + '">Relationship to Patient' + REQ + '</label><input class="' + INP + '" id="b_rel" placeholder="e.g. Son, Daughter" value="' + esc(planOther ? (pf.relationship || plan.consult_relationship || '') : (pf.relationship || '')) + '"></div>' +
      // Age is never typed: it is computed from a Date of Birth. With one on
      // file (the account itself, or someone booked for before) it shows
      // locked; otherwise the patient enters the Date of Birth.
      (planOther ? '' :
        '<div class="' + FG + '" id="b_ageWrap"><label class="' + LBL + '">Age</label>' +
          '<input class="' + INP + ' bg-dgt-ink-50 dark:bg-white/5" id="b_age" readonly value="' + esc(vAge) + '"></div>' +
        '<div class="' + FG + '" id="b_dobWrap" style="display:none"><label class="' + LBL + '">Date of Birth' + REQ + '</label>' +
          dobSelectsHtml('b_dob', pf.date_of_birth || '', INP) +
          '<span class="' + HINT + '" id="b_dobAge"></span></div>') +
      '<div class="' + FG + '"><label class="' + LBL + '">Phone Number' + REQ + '</label><input class="' + INP + '" id="b_phone" type="tel" inputmode="numeric" value="' + esc(vPhone) + '"></div>' +
      (plan ? '<input type="hidden" id="b_service" value="' + esc(plan.service_id) + '">' :
      '<div class="' + FGF + '"><label class="' + LBL + '">Pick your Service' + REQ + '</label>' +
      '<div class="flex items-start gap-2 rounded-md border border-warn/40 bg-warn-soft px-3 py-2 text-[12.5px] leading-snug text-[#8a5a00] dark:bg-warn/20 dark:text-[#fcd57a]">' +
        '<svg viewBox="0 0 24 24" class="mt-px h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>' +
        '<span><strong>Note:</strong> Prices shown are baseline amounts (&quot;starts at&quot;). The final cost may vary based on the actual condition, severity, or additional units required as determined by the dentist during treatment.</span></div>' +
      // Base (starting) price beside each name in green; the dentist sets the real charge.
      svcPickerHtml('b_service', services, pf.service_id, INP) +
      '</div>') +
      '<div class="' + FGF + '"><label class="' + LBL + '">Preferred Date &amp; Time' + REQ + '</label>' +
      reschedCalHtml() + '</div>' +
      '<input type="hidden" id="b_date" value="' + esc(pf.scheduled_date || '') + '"><input type="hidden" id="b_slot" value="' + esc(pf.time_slot || '') + '">' +
      '<div class="' + FGF + '"><label class="' + LBL + '">Concern <span class="font-normal text-steel-400 dark:text-steel-500">(optional)</span></label><textarea class="' + INP + ' min-h-[72px] resize-y" id="b_notes" placeholder="Please describe your concern here…">' + esc(pf.notes || '') + '</textarea></div>' +
      '</div>',
      '<button class="' + BTN + GHOST + '" data-close id="b_back">' + (backToServices ? 'Back to Services' : 'Back') + '</button><button class="' + BTN + PRIMARY + '" id="b_next">Continue to Payment</button>'), { wide: true });
    if (backToServices) g('b_back').addEventListener('click', () => { backToServices = false; goToServices(); });

    // A blank b_dep value ("+ New Person") means the name/age fields below are
    // a brand-new person, typed directly into this same form — no separate
    // "add" step. api/appointments.php action=create creates a real patient
    // record for them right then, exactly like a walk-in's "New patient" flow
    // (2026-09). Relationship is always retyped for this specific booking,
    // whether the person is new or someone booked for before.
    function ageOfDob(dob) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(dob || '') || dob > todayISO()) return null;
      const b = new Date(dob + 'T00:00:00'), n = new Date();
      let a = n.getFullYear() - b.getFullYear();
      if (n.getMonth() < b.getMonth() || (n.getMonth() === b.getMonth() && n.getDate() < b.getDate())) a--;
      return a >= 0 && a <= 130 ? a : null;
    }
    // 'age' = show the computed age (birthdate on file), 'dob' = ask for it.
    function setAgeMode(mode, ageVal) {
      if (!g('b_ageWrap')) return;
      g('b_ageWrap').style.display = mode === 'age' ? '' : 'none';
      g('b_dobWrap').style.display = mode === 'dob' ? '' : 'none';
      if (mode === 'age') g('b_age').value = ageVal != null ? ageVal : '';
    }
    if (g('b_dob')) {
      wireDobSelects('b_dob');
      const showDobAge = () => {
        const a = ageOfDob(g('b_dob').value);
        g('b_dobAge').textContent = a === null ? '' : 'Age: ' + a;
      };
      g('b_dob').addEventListener('input', showDobAge);
      showDobAge();
    }
    const SELF_HAS_DOB = String(SELF_AGE) !== '';
    if (!planOther) setAgeMode(SELF_HAS_DOB ? 'age' : 'dob', SELF_AGE);

    // "For Myself": name (and phone, when the account has one) come from the
    // account and are greyed out; only "For Someone Else" is typed (2026-10-17).
    const LOCK_CLS = ['!bg-dgt-ink-50', 'dark:!bg-white/5', 'cursor-not-allowed'];
    function lockSelf(on) {
      [['b_first', true], ['b_last', true], ['b_phone', SELF_PHONE !== '']].forEach(([id, has]) => {
        const el = g(id);
        if (!el) return;
        const lock = on && has;
        el.readOnly = lock;
        el.tabIndex = lock ? -1 : 0;
        LOCK_CLS.forEach((c) => el.classList.toggle(c, lock));
      });
    }
    function syncFields() {
      const other = document.querySelector('input[name="bfor"]:checked').value === 'other';
      lockSelf(!other);
      g('otherWrap').style.display = other ? '' : 'none';
      g('relWrap').style.display = other ? '' : 'none';
      if (g('otherNote')) g('otherNote').style.display = other ? '' : 'none';
      if (!other) {
        g('selfFirstWrap').style.display = '';
        g('selfLastWrap').style.display = '';
        g('b_first').value = SELF_FIRST; g('b_last').value = SELF_LAST; g('b_phone').value = SELF_PHONE;
        setAgeMode(SELF_HAS_DOB ? 'age' : 'dob', SELF_AGE);
        if (window.setDobRange) setDobRange('b_dob', 0, 120);
        return;
      }
      const isNew = g('b_other').value === '';
      g('selfFirstWrap').style.display = isNew ? '' : 'none';
      g('selfLastWrap').style.display = isNew ? '' : 'none';
      if (g('b_phone').value === SELF_PHONE) g('b_phone').value = '';
      if (isNew) {
        if (g('b_first').value === SELF_FIRST) g('b_first').value = '';
        if (g('b_last').value === SELF_LAST) g('b_last').value = '';
        setAgeMode('dob');
        // A new person booked for by someone else is a child, 1 to 17.
        if (window.setDobRange) setDobRange('b_dob', 1, OTHER_MAX_AGE);
      } else {
        const person = others.find((x) => String(x.treated_patient_id) === String(g('b_other').value));
        setAgeMode('age', person && person.age != null ? person.age : '');
      }
    }

    if (!plan) {
      document.getElementsByName('bfor').forEach((r) => r.addEventListener('change', syncFields));
      g('b_other').addEventListener('change', syncFields);
      if (isOther) syncFields();
    }
    if (!isOther && !planOther) {
      // Always the account's own details for a self booking (a stale draft can't change them).
      if (g('b_first')) { g('b_first').value = SELF_FIRST; g('b_last').value = SELF_LAST; }
      if (g('b_phone') && SELF_PHONE !== '') g('b_phone').value = SELF_PHONE;
      lockSelf(true);
    }

    wirePhoneField(g('b_phone'), { submitBtn: g('b_next') });
    wireSvcPicker('b_service');
    // Online bookings auto-route across on-duty dentists (no dentist_id here);
    // minDate mirrors action=create's own floor (onlineLeadDays() notice). A
    // prefill coming from Calendar View's "book this slot" flow (sessionStorage
    // dgt_book_prefill) opens the grid straight on that month/day/slot.
    let popupDay = null;
    const saveDraft = () => {
      if (plan || !draftActive || !g('b_next')) return;
      const val = (id) => (g(id) ? g(id).value : '');
      const bf = document.querySelector('input[name="bfor"]:checked');
      writeDraft({
        v: 1, open: true, popupDay,
        booking_for: bf ? bf.value : 'self',
        treated_patient_id: val('b_other'),
        patient_first: val('b_first'), patient_last: val('b_last'),
        patient_phone: val('b_phone'), relationship: val('b_rel'),
        date_of_birth: val('b_dob'), insurance_provider: val('b_ins'),
        service_id: val('b_service'), notes: val('b_notes'),
        scheduled_date: val('b_date'), time_slot: val('b_slot'),
      });
    };
    if (!plan) {
      draftActive = true;
      const pane = g('b_next').closest('.modal-pane') || g('modalRoot');
      pane.addEventListener('input', saveDraft);
      pane.addEventListener('change', saveDraft);
    }
    // One appointment per day per person (2026-10-20): the calendar marks a
    // day "Booked" only for the person this booking is for.
    const personQ = () => {
      const bf = document.querySelector('input[name="bfor"]:checked');
      if (!bf || bf.value !== 'other') return '';
      return '&for=other&person=' + (parseInt(g('b_other').value, 10) || 0);
    };
    const bookCal = wireReschedCal(null, minDate, (date, slot) => { g('b_date').value = date; g('b_slot').value = slot; saveDraft(); },
      pf.scheduled_date ? { date: pf.scheduled_date, slot: pf.time_slot || '', popupDay: pf._popupDay || null }
        : pf._popupDay ? { popupDay: pf._popupDay }
        : plan ? { date: String(plan.due_date).slice(0, 10) > minDate ? String(plan.due_date).slice(0, 10) : minDate, slot: '', findOpen: true }
        : null,
      plan ? '&plan_id=' + plan.plan_id : personQ(),
      { onPopup: (k) => { popupDay = k; saveDraft(); } });
    if (!plan) {
      const refreshPerson = () => bookCal.setExtra(personQ());
      document.getElementsByName('bfor').forEach((r) => r.addEventListener('change', refreshPerson));
      g('b_other').addEventListener('change', refreshPerson);
    }
    saveDraft();

    // Guided steps (2026-10-10, adviser feedback): one highlighted step at a
    // time; later steps stay dimmed until the earlier ones are done.
    const par = (id) => () => (g(id) ? g(id).parentElement : null);
    const filled = (id) => () => !!(g(id) && g(id).value.trim());
    const calWrap = () => { const c = g('rs_cal_body'); return c ? c.closest('.rs-pcal').parentElement : null; };
    guideForm(g('b_next').closest('.modal-pane') || g('modalRoot'), null, [
      { label: 'Fill in the patient details', fields: [
        { wrap: par('b_other'), ok: true },
        { wrap: () => g('selfFirstWrap'), ctl: () => g('b_first'), ok: filled('b_first') },
        { wrap: () => g('selfLastWrap'), ctl: () => g('b_last'), ok: filled('b_last') },
        { wrap: () => g('relWrap'), ctl: () => g('b_rel'), ok: filled('b_rel') },
        { wrap: () => g('b_dobWrap'), ctl: () => g('b_dobWrap') && [...g('b_dobWrap').querySelectorAll('select')],
          ok: () => !!(g('b_dob') && ageOfDob(g('b_dob').value) !== null), auto: true },
        { wrap: par('b_phone'), ctl: () => g('b_phone'), ok: () => /^09\d{9}$/.test(g('b_phone').value), auto: true },
      ] },
      { label: 'Pick your service', fields: [
        { wrap: () => (plan ? null : g('b_service').parentElement), ctl: () => g('b_serviceBtn'), ok: filled('b_service'), auto: true },
      ] },
      { label: 'Choose a date and time', fields: [
        { wrap: calWrap, ctl: () => g('rs_cal_body').closest('.rs-pcal'),
          focus: () => g('rs_cal_body').querySelector('.pcal-cell.picked, .pcal-cell.bookable'),
          ok: () => !!(g('b_date').value && g('b_slot').value), auto: true },
      ] },
      // Concern is optional: focus moves here after the date + time pick, but
      // Continue stays usable (noLock) even when it is left blank.
      { label: 'Add your concern (optional), then press "Continue to Payment"', fields: [
        { wrap: par('b_notes'), ctl: () => g('b_notes'), ok: filled('b_notes') },
        { wrap: () => g('b_next'), ok: false, noLock: true },
      ] },
    ], { noScroll: true });

    g('b_next').addEventListener('click', () => {
      const bfor = plan ? '' : document.querySelector('input[name="bfor"]:checked').value;
      let d;
      if (plan) {
        d = {
          _plan: plan,
          treatment_plan_id: plan.plan_id,
          booking_for: planOther ? 'other' : 'self',
          treated_patient_id: planOther ? plan.treated_patient_id : undefined,
          patient_name: planOther ? plan.for_name : SELF_NAME,
          patient_age: planOther ? '' : (SELF_HAS_DOB ? SELF_AGE : ageOfDob(g('b_dob').value)),
          date_of_birth: !planOther && !SELF_HAS_DOB ? g('b_dob').value : '',
          patient_phone: g('b_phone').value.trim(),
          relationship: planOther ? g('b_rel').value.trim() : '',
          service_id: String(plan.service_id),
          insurance_provider: '',   // the insurance field was removed (2026-10-11)
          scheduled_date: g('b_date').value,
          time_slot: g('b_slot').value,
          notes: g('b_notes').value.trim(),
        };
        if (planOther && !d.relationship) return toast('Enter your relationship to this person.', 'err');
        if (!planOther && !SELF_HAS_DOB && ageOfDob(d.date_of_birth) === null) return toast('Enter your date of birth.', 'err');
      } else if (bfor === 'other') {
        const otherId = g('b_other').value;
        const rel = g('b_rel').value.trim();
        if (!rel) return toast('Enter your relationship to this person.', 'err');
        if (otherId === '') {
          // New person — typed straight into this form; the server creates a
          // real patient record for them as part of creating the booking,
          // exactly like a walk-in's "New patient" flow.
          const first = g('b_first').value.trim();
          const last = g('b_last').value.trim();
          if (!first || !last) return toast('Enter the patient’s first and last name.', 'err');
          const dob = g('b_dob').value;
          if (ageOfDob(dob) === null) return toast('Enter the patient’s date of birth.', 'err');
          d = {
            booking_for: 'other',
            new_patient: { first_name: first, last_name: last, date_of_birth: dob },
            date_of_birth: dob,
            patient_name: (first + ' ' + last).trim(),
            patient_age: ageOfDob(dob),
            patient_phone: g('b_phone').value.trim(),
            relationship: rel,
            service_id: g('b_service').value,
            insurance_provider: '',   // the insurance field was removed (2026-10-11)
            scheduled_date: g('b_date').value,
            time_slot: g('b_slot').value,
            notes: g('b_notes').value.trim(),
          };
        } else {
          const person = others.find((x) => String(x.treated_patient_id) === String(otherId));
          if (!person) return toast('Choose who this is for, or add a new person.', 'err');
          d = {
            booking_for: 'other',
            treated_patient_id: person.treated_patient_id,
            patient_name: person.full_name,
            patient_age: person.age != null ? person.age : '',
            patient_phone: g('b_phone').value.trim(),
            relationship: rel,
            service_id: g('b_service').value,
            insurance_provider: '',   // the insurance field was removed (2026-10-11)
            scheduled_date: g('b_date').value,
            time_slot: g('b_slot').value,
            notes: g('b_notes').value.trim(),
          };
        }
      } else {
        const first = g('b_first').value.trim();
        const last = g('b_last').value.trim();
        if (!first || !last) return toast('Enter the patient first and last name.', 'err');
        if (!SELF_HAS_DOB && ageOfDob(g('b_dob').value) === null) return toast('Enter your date of birth.', 'err');
        d = {
          booking_for: 'self',
          patient_first: first,
          patient_last: last,
          patient_name: (first + ' ' + last).trim(),
          patient_age: SELF_HAS_DOB ? SELF_AGE : ageOfDob(g('b_dob').value),
          date_of_birth: SELF_HAS_DOB ? '' : g('b_dob').value,
          patient_phone: g('b_phone').value.trim(),
          relationship: '',
          service_id: g('b_service').value,
          insurance_provider: '',   // the insurance field was removed (2026-10-11)
          scheduled_date: g('b_date').value,
          time_slot: g('b_slot').value,
          notes: g('b_notes').value.trim(),
        };
      }
      if (!PH_MOBILE_RE.test(d.patient_phone)) return toast(PH_MOBILE_MSG, 'err');
      if (!d.service_id) return toast('Choose a service.', 'err');
      if (!d.scheduled_date) return toast('Choose a preferred date.', 'err');
      if (!d.time_slot) return toast('Pick an available time slot.', 'err');
      if (new Date(d.scheduled_date + 'T00:00:00').getDay() === 0 && !window.SUNDAY_OPEN) return toast('The clinic is closed on Sundays. Please choose another day.', 'err');
      paymentModal(d);
    });
  }

  /* ---------- Booking: step 2 (reservation fee) ---------- */
  const METHOD_SUB = { bank: 'Pay via online banking', gcash: 'Pay using your GCash wallet' };

  const errBox = (id) => '<div class="field-err" id="' + id + '" hidden></div>';

  // Shared #p_file change handler — used by both the booking wizard's payment
  // step and resubmitPaymentModal() below (2026-09), since a resubmission
  // needs the exact same orientation/manipulation/reference-OCR checks a
  // brand-new upload gets. getMethod() reads whichever payment-method radio
  // group the caller's modal is currently using.
  // Guided-form fields shared by the payment step and Resubmit Payment.
  function refField(getMethod) {
    return {
      // Before a method is picked there is no field yet: hold the place with the panel.
      wrap: () => (g('p_ref') ? g('p_ref').parentElement : g('pm_panel')), ctl: () => g('p_ref') || g('pm_panel'),
      ok: () => {
        const r = g('p_ref'); if (!r) return false;
        return refRule(getMethod()).re.test(r.value.trim()) && !r.classList.contains('is-invalid');
      },
      auto: () => getMethod() === 'gcash',   // 13 digits = done; a bank ref has no fixed length
      noLock: true,   // never greyed out: it can be typed before the upload too (user request)
    };
  }
  function uploadField() {
    return {
      wrap: () => (g('p_file') ? g('p_file').closest('.rcpt-drop').parentElement : null),
      ctl: () => g('p_file') && g('p_file').closest('label'),   // the Upload Image button pulses
      ok: () => !!(g('p_file') && g('p_file').files.length && !receiptBlocked()),
      auto: true, autoWindow: 30000,   // the receipt check can take a while
      // Scroll far enough to show the reference box below the upload as well.
      reveal: () => g('p_ref') && g('p_ref').parentElement,
    };
  }

  function wireReceiptUpload(getMethod) {
    if (!g('p_file')) return;   // upload hidden: the admin turned the screenshot requirement off
    const upLbl = g('p_file').closest('label');
    if (upLbl && !upLbl.hasAttribute('tabindex')) {
      upLbl.tabIndex = 0;
      upLbl.setAttribute('role', 'button');
      upLbl.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); g('p_file').click(); } });
    }
    getTessWorker();   // start downloading the OCR engine now, before a receipt is picked
    g('p_file').addEventListener('change', (ev) => {
      const f = ev.target.files[0];
      const warnEl = g('rcptWarn'), scanEl = g('rcptScan');
      clearReceiptWarnings(warnEl);
      const okEl = g('rcptOk');
      if (okEl) okEl.hidden = true;
      scanEl.hidden = !f;
      resetScanProgress(scanEl);
      setRefNote(null);
      // A locked reference belongs to the old screenshot: clear it until the new one is read.
      const lockedRef = refLocked(getMethod()) && g('p_ref');
      if (lockedRef) setRefManual(false);
      if (lockedRef && lockedRef.value) {
        lockedRef.value = '';
        lockedRef.dispatchEvent(new Event('keyup', { bubbles: true }));
      }
      receiptAmountRead = null;
      rcptChecking = !!f;
      syncReceiptGate();
      if (!f) { g('rcptPrev').innerHTML = '<span>No receipt uploaded</span>'; return; }
      const sink = document.createElement('div');
      sink.hidden = true;
      const done = () => {
        if (!g('p_file') || g('p_file').files[0] !== f || g('rcptWarn') !== warnEl) return;   // a newer upload took over
        receiptAmountRead = sink._amount || null;
        if (!sink.hidden) { warnEl._items = sink._items; renderReceiptWarnings(warnEl); warnEl.hidden = false; }
        setScanProgress(scanEl, 100, 'Done');
        scanEl.hidden = true;
        const blocked = !warnEl.hidden && hasBlockingWarning(warnEl);
        if (okEl) okEl.hidden = blocked;
        // Fill in the reference only when the screenshot passed every check.
        const refIn = g('p_ref');
        if (refIn && refLocked(getMethod())) {
          // Locked box: only a screenshot that passed every check fills it.
          if (sink._noOcr) {
            setRefManual(true);
            setRefNote('warn', REF_UNREAD_MSG);
          } else if (blocked) {
            setRefNote('warn', "We can't fill in the reference number until the screenshot problems above are fixed.");
          } else if (sink._ref) {
            refIn.value = sink._ref;
            refIn.dispatchEvent(new Event('keyup', { bubbles: true }));
            setRefNote('ok', 'Filled in from your screenshot.');
          } else {
            setRefManual(true);
            setRefNote('warn', REF_UNREAD_MSG);
          }
        } else if (refIn && !sink._noOcr) {
          if (blocked) {
            setRefNote(refIn.value.trim() ? null : 'warn', "We didn't fill in the reference number because this screenshot doesn't match the payment details above.");
          } else if (sink._ref && !refIn.value.trim()) {
            refIn.value = sink._ref;
            // wireRefCheck() (app.js) listens for keyup/blur: this starts its
            // duplicate-reference lookup on the filled-in value.
            refIn.dispatchEvent(new Event('keyup', { bubbles: true }));
            setRefNote('ok', 'Filled in from your screenshot. Check that it matches your receipt.');
          } else if (!sink._ref && !refIn.value.trim()) {
            setRefNote('warn', REF_UNREAD_MSG);
          } else setRefNote(null);
        } else if (refIn && sink._noOcr && !refIn.value.trim()) {
          setRefNote('warn', REF_UNREAD_MSG);
        } else setRefNote(null);
        rcptChecking = false;
        syncReceiptGate();
      };

      const url = URL.createObjectURL(f);
      g('rcptPrev').innerHTML = '<img src="' + url + '" alt="receipt preview">';
      if (!RECEIPT_CHECKS) { scanEl.hidden = true; rcptChecking = false; syncReceiptGate(); return; }

      // Official GCash receipt screenshots are portrait (taller than wide).
      // A landscape or sideways-rotated upload is rejected outright — unlike
      // the blur/amount checks below, this is a plain width-vs-height
      // comparison, not an OCR guess, so it's safe to enforce as a real
      // requirement rather than just a warning.
      const probe = new Image();
      probe.onload = () => {
        if (probe.naturalWidth > probe.naturalHeight) {
          addReceiptWarning(warnEl, 'The screenshot must be portrait (vertical), not landscape or sideways');
          ev.target.value = '';
          g('rcptPrev').innerHTML = '<span>No receipt uploaded</span>';
          scanEl.hidden = true;
          rcptChecking = false; syncReceiptGate();
          return;
        }
        const method = getMethod();
        setRefNote('info', 'Reading the reference number from your screenshot…');
        scanReceiptForManipulation(f, sink).then((flagged) => {
          if (!flagged) return scanReceiptForReference(f, sink, scanEl, method);
        }).finally(done);
      };
      probe.onerror = () => {
        addReceiptWarning(warnEl, "This file couldn't be opened as an image (use JPG, PNG or WEBP)");
        scanEl.hidden = true;
        rcptChecking = false; syncReceiptGate();
      };
      probe.src = url;
    });
  }

  function payPanel(m) {
    const p = payInfo || {};
    const feeTxt = peso(RES_FEE);
    if (isExtra(m)) {
      const x = extraOf(m) || {};
      const rows = [['Account name', x.account_name], ['Account number', x.account_number]].filter((r) => r[1]);
      return '<div class="pm-box">' +
        (x.qr ? '<div class="pm-qr"><div class="pm-qr-cap">' + esc(x.name) + ' QR Code</div><img src="' + esc(x.qr) + '" alt="' + esc(x.name) + ' QR code"></div>' : '') +
        (rows.length ? '<div class="pm-bank-acct">' + rows.map((r) =>
          '<div class="pm-bank-row"><span>' + r[0] + '</span><strong>' + esc(r[1]) + '</strong></div>').join('') + '</div>' : '') +
        '<div class="pm-instructions"><strong>Instructions:</strong><ol>' +
        '<li>Open your ' + esc(x.name || 'payment') + ' app' + (x.qr ? ' and scan the QR code above' : ' and send to the account above') + '.</li>' +
        '<li>Complete the payment of <b>' + feeTxt + '</b>.</li>' +
        '<li>Upload a screenshot of the confirmation and enter the transaction reference number below.</li></ol></div></div>';
    }
    if (m === 'bank') {
      // Patients see only the QR and the account name (2026-10-18): the bank name
      // and account number stay in Settings but are not shown here.
      const acctName = String(p.bank_details || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean)[1] || '';
      const acct = acctName
        ? '<div class="pm-bank-acct"><div class="pm-bank-row"><span>Account name</span><strong>' + esc(acctName) + '</strong></div></div>'
        : '';
      const bqr = p.bank_qr;
      return '<div class="pm-box">' +
        (bqr ? '<div class="pm-qr"><div class="pm-qr-cap">Bank Transfer QR Code</div><img src="' + esc(bqr) + '" alt="Bank transfer QR code"></div>' : '') +
        acct +
        '<div class="pm-instructions"><strong>Instructions:</strong><ol>' +
        '<li>Open your banking or e-wallet app and scan the QR code above (InstaPay).</li>' +
        '<li>Complete the payment of <b>' + feeTxt + '</b>.</li>' +
        '<li>Upload a screenshot of the confirmation and enter the transaction reference number below.</li></ol></div></div>';
    }
    const name = p.gcash_name;
    const num  = p.gcash_number;
    const qr   = p.gcash_qr;
    return '<div class="pm-box">' +
      (qr
        ? '<div class="pm-qr"><div class="pm-qr-cap">GCash QR Code</div><img src="' + esc(qr) + '" alt="GCash QR code"></div>'
        : '<div class="pm-qr pm-qr-empty"><div class="pm-qr-cap">GCash QR Code</div><span>QR not available. Send to the number below.</span></div>') +
      ((name || num) ? '<div class="pm-acct">' + (name ? esc(name) + ' ' : '') + (num ? '<strong>' + esc(num) + '</strong>' : '') + '</div>' : '') +
      '<div class="pm-instructions"><strong>Instructions:</strong><ol>' +
      '<li>Open your GCash app.</li>' +
      (qr ? '<li>Tap &ldquo;Scan QR&rdquo; and scan the code above.</li>' : '<li>Send money to the number above.</li>') +
      '<li>Complete the payment of <b>' + feeTxt + '</b>.</li>' +
      '<li>Enter the 13-digit reference number below.</li></ol></div>' +
      '</div>';
  }

  function setErr(inputId, errId, msg) {
    const inp = g(inputId), err = g(errId);
    if (!inp || !err) return;
    if (msg) { inp.classList.add('is-invalid'); err.textContent = msg; err.hidden = false; }
    else { inp.classList.remove('is-invalid'); err.hidden = true; }
  }

  function validatePayment(method) {
    let ok = true;
    if (method === 'gcash') {
      const bad = !/^\d{13}$/.test(g('p_ref').value.trim());
      setErr('p_ref', 'err_ref', bad ? 'GCash Reference Number must be exactly 13 digits' : '');
      if (bad) ok = false;
    } else if (method === 'bank' || isExtra(method)) {
      const rule = refRule(method);
      const badR = !rule.re.test(g('p_ref').value.trim());
      setErr('p_ref', 'err_ref', badR ? rule.msg : '');
      ok = !badR;
    }
    return ok;
  }

  async function paymentModal(d) {
    await loadPayInfo();
    const svc = services.find((s) => String(s.service_id) === String(d.service_id));
    const startM = '';   // nothing pre-picked: choosing a method is step 1 (2026-10-10)

    openModal(modalShell('<span class="text-danger">Payment Information</span>',
      wizSteps(2) +
      '<div class="pay-summary">' +
      '<div class="pay-summary-h">Appointment Summary</div>' +
      '<div class="row-between"><span>Appointment Fee</span><strong>' + peso(RES_FEE) + '</strong></div>' +
      '<div class="row-between"><span>Service</span><span>' + esc(svc ? svc.service_name : (d._plan ? d._plan.service_name : 'N/A')) + '</span></div>' +
      (d._plan ? '<div class="row-between"><span>Dentist</span><span>Dr. ' + esc(d._plan.dentist_name) + '</span></div>' : '') +
      '<div class="row-between"><span>Date</span><span>' + esc(fmtDate(d.scheduled_date)) + '</span></div>' +
      '<div class="row-between"><span>Time</span><span>' + esc(fmtTime(d.time_slot)) + '</span></div>' +
      '<div class="row-between pay-summary-total"><span>Total Amount</span><strong>' + peso(RES_FEE) + '</strong></div>' +
      '<div class="' + HINT + '" style="margin-top:8px">This fee is credited toward your treatment cost. It is <strong>non-refundable</strong>, but it transfers to a rescheduled appointment if you reschedule at least 24 hours ahead.</div>' +
      '</div>' +

      '<div id="pmWrap"><div class="' + FGF + ' mt-1.5"><label class="' + LBL + ' guide-lbl">Select Payment Method' + REQ + '</label></div>' +
      '<div class="pm-cards">' +
      Object.keys(METHODS).map((v) =>
        '<label class="pm-card' + (v === startM ? ' on' : '') + '">' +
        '<input type="radio" name="pm" value="' + v + '"' + (v === startM ? ' checked' : '') + '>' +
        '<span class="pm-card-txt"><span class="pm-card-t">' + esc(METHODS[v]) + '</span>' +
        '<span class="pm-card-s">' + esc(METHOD_SUB[v]) + '</span></span></label>').join('') +
      '</div></div>' +
      '<div id="pm_panel">' + (startM ? payPanel(startM) : '<p class="' + HINT + ' mt-2">Choose a payment method above to see how to pay.</p>') + '</div>' +

      // The screenshot upload only appears while the admin requires it.
      (receiptRequired() ? '<div class="' + FGF + ' mt-3.5"><label class="' + LBL + '">Upload Payment Receipt' + REQ + '</label>' +
      '<p class="' + HINT + ' mb-2 mt-0">Upload a screenshot or photo of your payment for verification.</p>' +
      '<div class="rcpt-drop">' +
      '<div class="rcpt-drop-prev" id="rcptPrev"><span>No receipt uploaded</span></div>' +
      '<label class="' + BTN + PRIMARY + ' mt-2.5 cursor-pointer">Upload Image' +
      '<input type="file" id="p_file" accept="image/png,image/jpeg,image/webp" hidden></label>' +
      '<p class="' + HINT + ' mt-2">Accepted formats: JPG, PNG, WEBP &middot; up to 4 MB</p>' +
      RCPT_STATUS_HTML + RCPT_WARN_HTML +
      '</div></div>' : '') +
      '<div id="refWrap">' + (startM ? refFieldHtml(startM) : '') + '</div>',
      '<button class="' + BTN + GHOST + '" id="p_back">Back</button><button class="' + BTN + PRIMARY + '" id="p_go">Submit Request</button>'));

    function wirePanel(m) {
      g('p_go').disabled = false;
      wireRefCheck(g('p_ref'), {
        errEl: g('err_ref'), submitBtn: g('p_go'),
        format: refRule(m),
      });
    }
    if (startM) wirePanel(startM);
    const curMethod = () => (document.querySelector('input[name="pm"]:checked') || {}).value || '';

    document.getElementsByName('pm').forEach((r) => r.addEventListener('change', () => {
      const m = document.querySelector('input[name="pm"]:checked').value;
      document.querySelectorAll('.pm-card').forEach((c) => c.classList.toggle('on', c.querySelector('input').value === m));
      g('pm_panel').innerHTML = payPanel(m);
      g('refWrap').innerHTML = refFieldHtml(m);
      wirePanel(m);
      // The GCash content check depends on the method, so re-check the receipt.
      if (g('p_file') && g('p_file').files[0]) g('p_file').dispatchEvent(new Event('change'));
    }));

    wireReceiptUpload(() => (document.querySelector('input[name="pm"]:checked') || {}).value || 'gcash');
    gateSubmit(g('p_go'));

    g('p_back').addEventListener('click', () => { closeModal(); bookModal(d); });

    guideForm(g('p_go').closest('.modal-pane') || g('modalRoot'), null, [
      { label: 'Select a payment method', fields: [
        { wrap: () => g('pmWrap'), ctl: () => g('pmWrap').querySelector('.pm-cards'),
          focus: () => g('pmWrap').querySelector('input[name="pm"]'), ok: () => !!curMethod(), auto: true },
      ] },
      // Upload first when the screenshot is required: reading it fills in the
      // reference number. With the upload turned off the reference is next.
      { label: 'Upload your payment receipt', fields: [uploadField()] },
      { label: 'Enter the reference number', fields: [refField(curMethod)] },
      { label: 'Press "Submit Request"', fields: [{ wrap: () => g('p_go'), ok: false }] },
    ], { noScroll: true });

    g('p_go').addEventListener('click', async () => {
      if (!curMethod()) return toast('Please choose a payment method first.', 'err');
      if (receiptBlocked()) return toast(rcptChecking ? 'Please wait, your receipt is still being checked.' : 'Please fix the receipt error before submitting.', 'err');
      const method = curMethod();
      if (!validatePayment(method)) return toast('Please correct the highlighted payment fields.', 'err');
      const ref = g('p_ref').value.trim();
      const file = g('p_file') ? g('p_file').files[0] : null;
      const bank = method === 'bank' ? { bank_reference: ref } : {};
      if (!file && receiptRequired()) return toast('Attach a screenshot of your receipt.', 'err');

      const btn = g('p_go');
      btn.disabled = true; btn.textContent = 'Submitting…';
      try {
        const receipt = await uploadReceiptIfAny(file);

        const body = Object.assign({}, d, bank, {
          reservation_method: method,
          reservation_reference: ref,
          reservation_receipt: receipt,
          receipt_warnings: receiptWarningsText(),
          receipt_amount: receiptAmountRead,
        });
        delete body._plan;
        const { appointment_id } = await api('appointments.php?action=create', { method: 'POST', body });
        draftActive = false; clearDraft(); backToServices = false;
        submittedModal(appointment_id, d, svc, ref);
        loadAppts();
      } catch (e) {
        toast(e.message, 'err');
        btn.disabled = false; btn.textContent = 'Submit Request';
      }
    });
  }

  /* ---------- Payment Decline & Resubmission (2026-09) ----------
     A receptionist can reject just the online reservation receipt (blurry
     image, reference mismatch, invalid screenshot, ...) without cancelling
     the appointment — status becomes "payment_declined". This reopens the
     exact same Payment Information step from the booking wizard (payPanel(),
     wireReceiptUpload(), the OCR scan functions), just against the appointment
     already on file instead of a fresh one being created — the date, time and
     service are shown read-only and never re-collected, so the patient only
     ever has to fix the reference number / receipt. On success the server
     flips the appointment back to 'pending', so it simply reappears in the
     front desk's normal approval queue. */
  async function resubmitPaymentModal(id) {
    await loadPayInfo();
    const a = ALL.find((x) => x.appointment_id === id) || {};
    // The patient already sent the money — this is proof-only resubmission,
    // so the method stays whatever they originally paid with (no QR code, no
    // instructions, no method picker); only the reference + receipt can change.
    const method = (a.reservation_method === 'bank' || isExtra(a.reservation_method)) ? a.reservation_method : 'gcash';

    openModal(modalShell('Resubmit Payment',
      '<div class="' + window.alertCls('error') + ' !mt-0"><strong>Rejection Reason:</strong> '
        + esc(a.decline_reason || 'Your reservation payment could not be verified.') + '</div>' +

      (receiptRequired() ? '<div class="' + FGF + ' mt-3.5"><label class="' + LBL + '">Upload New Payment Receipt' + REQ + '</label>' +
      '<div class="rcpt-drop">' +
      '<div class="rcpt-drop-prev" id="rcptPrev"><span>No receipt uploaded</span></div>' +
      '<label class="' + BTN + PRIMARY + ' mt-2.5 cursor-pointer">Upload Image' +
      '<input type="file" id="p_file" accept="image/png,image/jpeg,image/webp" hidden></label>' +
      '<p class="' + HINT + ' mt-2">Accepted formats: JPG, PNG, WEBP &middot; up to 4 MB</p>' +
      RCPT_STATUS_HTML + RCPT_WARN_HTML +
      '</div></div>' : '') +
      refFieldHtml(method, a.reservation_reference || ''),
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + PRIMARY + '" id="rp_go">Resubmit Payment</button>'));

    g('rp_go').disabled = false;
    wireRefCheck(g('p_ref'), {
      errEl: g('err_ref'), submitBtn: g('rp_go'), excludePayment: a.payment_id,
      format: refRule(method),
    });

    wireReceiptUpload(() => method);
    gateSubmit(g('rp_go'));
    guideForm(g('rp_go').closest('.modal-pane') || g('modalRoot'), null, [
      { label: 'Upload a new payment receipt', fields: [uploadField()] },
      { label: 'Check the reference number', fields: [refField(() => method)] },
      { label: 'Press "Resubmit Payment"', fields: [{ wrap: () => g('rp_go'), ok: false }] },
    ], { noScroll: true });

    g('rp_go').addEventListener('click', async () => {
      if (receiptBlocked()) return toast(rcptChecking ? 'Please wait, your receipt is still being checked.' : 'Please fix the receipt error before submitting.', 'err');
      if (!validatePayment(method)) return toast('Please correct the highlighted payment fields.', 'err');
      const ref = g('p_ref').value.trim();
      const file = g('p_file') ? g('p_file').files[0] : null;
      const bank = method === 'bank' ? { bank_reference: ref } : {};
      if (!file && receiptRequired()) return toast('Attach a screenshot of your receipt.', 'err');

      const btn = g('rp_go');
      btn.disabled = true; btn.textContent = 'Resubmitting…';
      try {
        const receipt = await uploadReceiptIfAny(file);

        await api('appointments.php?action=resubmit_payment', { method: 'POST', body: Object.assign({ appointment_id: id }, bank, {
          reservation_method: method,
          reservation_reference: ref,
          reservation_receipt: receipt,
          receipt_warnings: receiptWarningsText(),
          receipt_amount: receiptAmountRead,
        })});
        closeModal();
        toast('Payment resubmitted, pending verification by the front desk.');
        loadAppts();
      } catch (e) {
        toast(e.message, 'err');
        btn.disabled = false; btn.textContent = 'Resubmit Payment';
      }
    });
  }

  /* ---------- Booking: step 3 — submitted + live status ---------- */
  const ICON_WAIT  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 0 1 7.38 16.75M12 6v6l4 2M2.5 8.875a10 10 0 0 0-.5 3M2.83 16a10 10 0 0 0 2.43 3.4M4.636 5.235a10 10 0 0 1 .891-.857M8.644 21.42a10 10 0 0 0 7.631-.38"/></svg>';
  const ICON_OK    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
  const ICON_NO    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>';
  const fullDate = (s) => { const x = new Date(s + 'T00:00:00'); return isNaN(x) ? s : x.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: '2-digit' }); };

  // "11:00 AM - 12:00 PM": every clinic slot is a one-hour visit.
  const visitRange = (s) => {
    const m = String(s || '').match(/^(\d{1,2}):(\d{2})/);
    if (!m) return fmtTime(s);
    const end = String((+m[1] + 1) % 24).padStart(2, '0') + ':00';   // ends on the next full hour
    return fmtTime(m[1].padStart(2, '0') + ':' + m[2]) + ' - ' + fmtTime(end);
  };

  const ICON_CAL_CARD = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="16.5" rx="2.5"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 17.5h.01M12 17.5h.01"/></svg>';

  function submittedModal(id, d, svc, payRef) {
    openModal(modalShell('Appointment Submitted',
      wizSteps(3) +
      '<div class="submitted">' +
      '<p class="submitted-sub">Waiting for front desk approval</p>' +
      '<div class="submitted-icon" id="s_icon">' + ICON_WAIT + '</div>' +
      '<div class="submitted-row">' +
      '<span class="submitted-ref">Ref: <strong>' + esc(payRef || refNo(id)) + '</strong></span>' +
      '<span class="submitted-badge" id="s_badge">Pending Approval</span>' +
      '</div>' +
      '<hr class="my-3.5 border-0 border-t border-steel-100 dark:border-white/10">' +
      '<div class="submitted-card">' +
      '<span class="submitted-card-ico">' + ICON_CAL_CARD + '</span>' +
      '<dl>' +
      '<div><dt>Name:</dt><dd>' + esc(d.patient_name) + '</dd></div>' +
      '<div><dt>Branch:</dt><dd>Bulacan</dd></div>' +
      '<div><dt>Service:</dt><dd>' + esc(svc ? svc.service_name : 'N/A') + '</dd></div>' +
      '<div><dt>Time:</dt><dd>' + esc(visitRange(d.time_slot)) + '</dd></div>' +
      '<div><dt>Date:</dt><dd>' + esc(fullDate(d.scheduled_date)) + '</dd></div>' +
      '</dl>' +
      '</div>' +
      '<div class="alert alert-warn" id="s_msg">Your appointment is waiting for approval from our front desk. We&rsquo;ll confirm it soon!</div>' +
      '<div class="submitted-poll" id="s_poll">' +
      '<div class="submitted-poll-t">Auto-checking status every 5 seconds</div>' +
      '<div class="submitted-poll-s"><span class="spin"></span> Still pending approval&hellip;</div>' +
      '</div>' +
      '<div class="submitted-notes">' +
      '<p class="submitted-notes-h">Note:</p>' +
      '<ol>' +
      '<li>Appointment time may adjust slightly due to ongoing procedures before you.</li>' +
      '<li>Minors must be accompanied by a guardian.</li>' +
      '<li>New orthodontic patients, please remember to bring one valid ID.</li>' +
      '</ol>' +
      '<p class="submitted-callout">Please take a screenshot for your easy reference.</p>' +
      '<p class="submitted-callout">Kindly arrive on time, and let us know if you are unable to attend.</p>' +
      '<p class="submitted-thanks">Thank you, and we look forward to seeing you!</p>' +
      '</div>' +
      '</div>',
      '<button class="' + BTN + PRIMARY + '" data-close id="s_done">Back to Appointments</button>'));

    if (pollTimer) clearInterval(pollTimer);
    const poll = async () => {
      try {
        const { appointment: a } = await api('appointments.php?action=poll&id=' + id);
        if (a.status === 'approved' || a.status === 'rescheduled') {
          g('s_icon').className = 'submitted-icon ok'; g('s_icon').innerHTML = ICON_OK;
          g('s_badge').textContent = 'Approved'; g('s_badge').className = 'submitted-badge ok';
          g('s_msg').className = 'alert alert-ok';
          g('s_msg').textContent = 'Approved! ' + (a.dentist_name ? a.dentist_name + ' will see you on ' : 'See you on ') + fullDate(a.scheduled_date) + ' at ' + fmtTime(a.time_slot) + '.';
          g('s_poll').hidden = true;
          clearInterval(pollTimer); pollTimer = null;
        } else if (a.status === 'cancelled') {
          g('s_icon').className = 'submitted-icon err'; g('s_icon').innerHTML = ICON_NO;
          g('s_badge').textContent = 'Declined'; g('s_badge').className = 'submitted-badge err';
          g('s_msg').className = 'alert alert-error';
          g('s_msg').textContent = a.decline_reason || 'Your request was declined. Please contact the clinic.';
          g('s_poll').hidden = true;
          clearInterval(pollTimer); pollTimer = null;
        }
      } catch { /* keep polling */ }
    };
    pollTimer = setInterval(poll, 5000);
    poll();
    g('s_done').addEventListener('click', () => { if (pollTimer) clearInterval(pollTimer); pollTimer = null; loadAppts(); refreshBellCount(); });
  }

  /* ---------- Reschedule request (reason + new date/time required) ---------- */
  // A full month-grid calendar picker inside the modal — the same green/amber/
  // red/hatched "Slots open / Full Schedule / Clinic closed"
  // language as the Calendar View page (includes/patient_calendar_style.php's
  // .pcal-* widget, required into this page's <head> above), scoped to the
  // appointment's own dentist via action=availability&dentist_id= (a
  // reschedule can belong to a roaming dentist with a different working week
  // than the online-booking Owner). The compact `.rs-pcal` modifier in that
  // same stylesheet shrinks the grid to fit a dialog instead of a full page.
  function reschedCalHtml() {
    return '<div class="rs-pcal rounded-md border border-dgt-ink-200 p-3 dark:border-dgt-ink-600">' +
      '<div class="pcal-toolbar"><button type="button" class="pcal-nav-btn" id="rs_cal_prev">&lsaquo;</button>' +
      '<h2 id="rs_cal_month">&nbsp;</h2><button type="button" class="pcal-nav-btn" id="rs_cal_next">&rsaquo;</button><button type="button" class="pcal-today-btn inline-flex cursor-pointer items-center justify-center rounded-md border border-steel-200 bg-transparent px-2.5 py-1.5 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5" id="rs_cal_today">Today</button></div>' +
      '<div class="pcal-legend"><span><i class="lg-open"></i> Available</span>' +
      '<span><i class="lg-full"></i> Fully Booked</span><span><i class="lg-closed"></i> Closed</span></div>' +
      '<div class="pcal-grid pcal-dow-row" id="rs_cal_dow"></div>' +
      '<div class="pcal-grid" id="rs_cal_body"><p class="col-span-full py-4 text-center text-[12px] text-dgt-ink-400">Loading&hellip;</p></div>' +
      '<div id="rs_day_panel" class="mt-3" aria-live="polite" hidden></div></div>';
  }

  // extraQ: extra availability query, e.g. '&plan_id=5' for a follow-up
  // (locked dentist + follow-up slot rules, see api/appointments.php).
  // Clicking a date opens its times in a pop-up (window.openSlotPopup, 2026-10-10,
  // adviser feedback: the list used to render below the calendar, out of view).
  // The date a time was picked on stays green with the time under it, and a
  // green "Selected" line with Change / Clear sits under the calendar.
  // preselect: { date, slot } re-picks a time (falls back to the day's first
  // free one if it filled meanwhile); { popupDay } re-opens that day's pop-up;
  // { date, findOpen } opens the first open day on or after date.
  // hooks: { onPopup(dayOrNull) } so the booking draft can remember it.
  function wireReschedCal(initialDentistId, minDateKey, onPick, preselect, extraQ, hooks) {
    hooks = hooks || {};
    // Mutable so a caller can swap which dentist's grid is shown (the
    // doctor-absence reassign picker in reschedModal()) without re-registering
    // any of this function's DOM listeners a second time.
    let dentistId = initialDentistId;
    const DOW = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const pad = (n) => String(n).padStart(2, '0');
    const keyOf = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    // Each clinic slot is a 1-hour chair booking — show it as a start-end
    // range ("10:00 AM – 11:00 AM") rather than just the start time.
    const slotRange = (s) => {
      const m = String(s).match(/^(\d{1,2}):(\d{2})/);
      if (!m) return fmtTime(s);
      const end = String((+m[1] + 1) % 24).padStart(2, '0') + ':00';   // ends on the next full hour
      return fmtTime(s) + ' – ' + fmtTime(end);
    };
    const longDay = (k) => new Date(k + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    const todayKey = keyOf(new Date());
    let view = new Date(); view.setDate(1);
    let pending = preselect && (preselect.date || preselect.popupDay) ? preselect : null;
    if (pending) { view = new Date((pending.popupDay || pending.date) + 'T00:00:00'); view.setDate(1); }
    let days = {}, slotOrder = [], popupDay = null, loadSeq = 0;
    let pickedDay = null, pickedSlot = '';

    g('rs_cal_dow').innerHTML = DOW.map((d) => '<div class="pcal-dow">' + d + '</div>').join('');

    function gridStart() {
      const first = new Date(view.getFullYear(), view.getMonth(), 1);
      const s = new Date(first); s.setDate(1 - first.getDay());
      return s;
    }
    const isFree = (info, s) => info.left ? (info.left[s] | 0) > 0 : !!(info.slots && info.slots[s]);
    const orderOf = (info) => slotOrder.length ? slotOrder : Object.keys(info.slots || {});
    const bookableDay = (k) => { const i = days[k]; return !!(i && !i.closed && !i.past && k >= minDateKey); };

    async function load() {
      const seq = ++loadSeq;
      g('rs_cal_month').textContent = view.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
      const start = gridStart();
      const end = new Date(start); end.setDate(start.getDate() + 41);
      try {
        const r = await api('appointments.php?action=availability&from=' + keyOf(start) + '&to=' + keyOf(end) + (dentistId ? '&dentist_id=' + dentistId : '') + (extraQ || ''));
        if (seq !== loadSeq) return;
        days = r.days || {}; slotOrder = r.slots || [];
        // Switching who the booking is for can turn the picked day into one
        // that person is already booked on: drop the pick then.
        if (pickedDay && (days[pickedDay] || {}).mine) { setPicked(null, ''); return; }
        render();
        if (pending) {
          const p = pending; pending = null;   // consume once
          if (p.findOpen) {
            // A follow-up's recommended date: that day, or the next day on or
            // after it that still has a free slot. Jumps months if needed.
            const ok = (x) => { const i = days[x]; return x >= minDateKey && i && !i.closed && !i.past && !i.full && !i.day_over; };
            const k = Object.keys(days).sort().find((x) => x >= p.date && ok(x)) || '';
            if (k && +k.slice(5, 7) !== view.getMonth() + 1) {
              view = new Date(k + 'T00:00:00'); view.setDate(1);
              pending = { date: k, popupDay: k };
              load();
              return;
            }
            if (k) openDay(k);
            return;
          }
          if (p.date && p.slot) {
            const i = days[p.date];
            if (!i) setPicked(p.date, p.slot);             // outside this grid: the server re-checks at submit
            else if (bookableDay(p.date)) {
              const s = isFree(i, p.slot) ? p.slot : (orderOf(i).find((x) => isFree(i, x)) || '');
              if (s) setPicked(p.date, s);
            }
          }
          const pd = p.popupDay || (p.date && !p.slot ? p.date : null);
          if (pd && days[pd] && (days[pd].closed || bookableDay(pd))) openDay(pd);
        }
      } catch (e) {
        if (seq === loadSeq) g('rs_cal_body').innerHTML = '<p class="col-span-full py-4 text-center text-[12px] text-danger">' + esc(e.message) + '</p>';
      }
    }

    function cellInfo(k, inMonth) {
      const info = days[k] || {};
      if (!inMonth) return { cls: 'out', tag: '', click: false };
      if (info.closed) return { cls: 'closed', tag: 'Closed', click: true };
      if (info.past || k < minDateKey) return { cls: 'out', tag: '', click: false };
      if (info.day_over) return { cls: 'closed', tag: 'Closed', click: true };
      if (info.mine) return { cls: 'full', tag: 'Booked', click: true };
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
          (ci.click ? ' data-day="' + k + '"' : '') +
          (picked ? ' aria-label="' + esc(longDay(k) + ', selected ' + slotRange(pickedSlot)) + '"' : '') + '>' +
          '<span class="pcal-date">' + d.getDate() + '</span>' + tag +
          '</' + (ci.click ? 'button' : 'div') + '>';
      }
      g('rs_cal_body').innerHTML = html;
    }

    function renderSummary() {
      const box = g('rs_day_panel');
      if (!pickedDay || !pickedSlot) { box.hidden = true; box.innerHTML = ''; return; }
      box.innerHTML = '<div class="pcal-picked-box"><span class="flex-1">&#10003; Selected: <strong>' + esc(longDay(pickedDay)) +
        '</strong> at <strong>' + esc(slotRange(pickedSlot)) + '</strong></span>' +
        '<span class="flex gap-2"><button type="button" class="pcal-today-btn inline-flex cursor-pointer items-center justify-center rounded-md border border-steel-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:bg-transparent dark:text-steel-100 dark:hover:bg-white/5" data-rs-change>Change time</button>' +
        '<button type="button" class="inline-flex cursor-pointer items-center justify-center rounded-md border border-transparent bg-transparent px-2.5 py-1.5 text-xs font-semibold text-danger hover:bg-danger-soft dark:hover:bg-danger/20" data-rs-clear>Clear</button></span></div>';
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
      if (hooks.onPopup) hooks.onPopup(k);
      let note = '', slots = [];
      if (info.closed) note = info.reason || 'The clinic is closed on this date.';
      else {
        const order = orderOf(info);
        // hooks.current = {date, slot} of the visit being rescheduled (and the
        // dentist it is with): that time shows as "Current time" and can't be
        // picked again, unless a different dentist is chosen.
        const cur = hooks.current;
        const isCur = (s) => !!(cur && cur.date === k && cur.slot === s && (!cur.dentistId || +cur.dentistId === +dentistId));
        slots = order.map((s) => isCur(s)
          ? { slot: s, label: slotRange(s), free: false, sub: 'Current time' }
          : isFree(info, s)
          ? { slot: s, label: slotRange(s), free: true, sub: info.left ? slotsLeftText(info.left[s]) : '' }
          : { slot: s, label: slotRange(s), free: false, sub: info.passed && info.passed[s] ? 'Passed' : info.soon && info.soon[s] ? 'Too soon' : (info.slots && !(s in info.slots)) ? 'Unavailable' : 'Occupied' });
        if (!slots.some((x) => x.free)) note = info.day_over ? 'Clinic hours are over for today. Please choose another day.' : ((info.passed && Object.keys(info.passed).length) || (info.soon && Object.keys(info.soon).length) ? 'No time slots are left today. Please choose another day.' : 'This day is fully booked. Please choose another day.');
      }
      openSlotPopup({
        title: longDay(k), note, slots,
        selected: k === pickedDay ? pickedSlot : '',
        onPick: (slot) => setPicked(k, slot),
        // Reschedule only: once Confirm closes the pop-up, bring the Reason box into view.
        onConfirm: (slot) => { if (slot && document.getElementById('rs_reason')) window.scrollToReason('rs_reason'); },
        onClose: () => { popupDay = null; render(); if (hooks.onPopup) hooks.onPopup(null); },
      });
    }

    g('rs_cal_body').addEventListener('click', (e) => {
      const cell = e.target.closest('[data-day]');
      if (cell && (days[cell.dataset.day] || {}).mine) return window.dayTakenPopup(/for=other/.test(extraQ || ''));
      if (cell) openDay(cell.dataset.day);
    });
    g('rs_day_panel').addEventListener('click', (e) => {
      if (e.target.closest('[data-rs-change]') && pickedDay) {
        const k = pickedDay;
        if (+k.slice(5, 7) !== view.getMonth() + 1 || +k.slice(0, 4) !== view.getFullYear()) {
          view = new Date(k + 'T00:00:00'); view.setDate(1);
          pending = { popupDay: k };
          load();
        } else openDay(k);
      }
      if (e.target.closest('[data-rs-clear]')) setPicked(null, '');
    });
    g('rs_cal_prev').addEventListener('click', () => { view.setMonth(view.getMonth() - 1); load(); });
    g('rs_cal_next').addEventListener('click', () => { view.setMonth(view.getMonth() + 1); load(); });
    // Today jumps back to this month and opens today's times when bookable.
    g('rs_cal_today').addEventListener('click', async () => {
      view = new Date(); view.setDate(1);
      await load();
      if (g('rs_cal_body').querySelector('[data-day="' + todayKey + '"]')) openDay(todayKey);
    });

    load();

    return {
      // Reload the whole grid against a different dentist's working schedule —
      // used when the patient picks another doctor after their assigned one
      // was marked absent. Clears any already-picked day/slot since it may no
      // longer be valid for the new dentist.
      setDentist(id) {
        dentistId = id;
        setPicked(null, '');
        load();
      },
      // Swap the extra query (e.g. who the booking is for) and reload.
      setExtra(q) {
        if (q === extraQ) return;
        extraQ = q;
        load();
      },
    };
  }

  async function reschedModal(id) {
    const appt = ALL.find((a) => a.appointment_id === id) || {};
    // When the currently-assigned dentist is marked absent on this appointment's
    // date, offer a doctor picker too — the patient may move to any other
    // active dentist instead of only the same (unavailable) one. The server
    // (action=reschedule) re-checks this is really an absence case before it
    // allows the dentist to actually change.
    // A follow-up stays with its dentist, so no doctor picker for it.
    const planAppt = !!(appt.treatment_plan_id && appt.dentist_id);
    const absent = !!appt.doctor_absent && !planAppt;
    // Ordinarily a patient reschedule needs tomorrow or later; a doctor-absence
    // reschedule may land the same day too, since that's the whole point of
    // offering another on-duty dentist for today's now-unavailable slot.
    // Forward only (migration 088): a date after the current one, never
    // earlier (the same date only when the dentist became unavailable).
    // Earlier or fully booked dates go through the front desk.
    const cur = String(appt.scheduled_date || '').slice(0, 10);
    const nextDay = (k) => { const d = new Date(k + 'T00:00:00'); d.setDate(d.getDate() + 1); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
    const later = (x, y) => (x > y ? x : y);
    const minDate = cur ? (absent ? later(todayISO(), cur) : later(todayISO(1), nextDay(cur))) : (absent ? todayISO() : todayISO(1));
    if (absent && !dentists.length) {
      try { dentists = (await api('appointments.php?action=dentists')).dentists; } catch (e) { dentists = []; }
    }

    openModal(modalShell('Reschedule Appointment',
      '<div class="flex flex-col gap-3.5">' +
      (absent
        ? '<div class="appt-c-notice !mt-0">Your doctor is unavailable on this date. Pick another dentist and an open time below, or cancel instead.</div>' +
          '<div class="' + FGF + '"><label class="' + LBL + '">Dentist</label>' +
          '<select class="' + INP + '" id="rs_dentist">' +
          dentists.map((d) => '<option value="' + d.user_id + '"' + (String(d.user_id) === String(appt.dentist_id) ? ' selected' : '') + '>' +
            esc(d.full_name) + ' · ' + (d.doctor_type === 'owner' ? 'In-House' : 'Roaming') + '</option>').join('') +
          '</select></div>'
        : '') +
      '<div class="' + FGF + '"><label class="' + LBL + '">Pick a New Date &amp; Time</label>' + reschedCalHtml() + '</div>' +
      '<input type="hidden" id="rs_date"><input type="hidden" id="rs_slot">' +
      // Forward-only note (migration 088): earlier or fully booked dates
      // go through the front desk.
      '<div class="' + window.alertCls('warn') + ' !mb-0"><strong>Note:</strong> You can only move this appointment to ' +
        esc(longDate(minDate)) + ' or later. If you want an earlier date than your appointment (or a fully booked day), please contact the clinic at ' +
        '<strong>0976 448 4252</strong> or <strong>customerservice@dgtonedentalph.com</strong>.</div>' +
      '<div class="' + FGF + '"><label class="' + LBL + '">Reason for Rescheduling</label>' +
      '<textarea class="' + INP + ' min-h-[72px] resize-y" id="rs_reason" placeholder="e.g. Something came up at work that morning.">' +
      (absent ? 'My previous dentist is unavailable on the original date.' : '') +
      '</textarea></div>' +
      '<div class="' + FGF + '" id="rs_msg"></div>' +
      '</div>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button>' +
      '<button class="' + BTN + PRIMARY + '" id="rs_go">Submit Request</button>'), { wide: true });

    const cal = wireReschedCal(appt.dentist_id, minDate, (date, slot) => { g('rs_date').value = date; g('rs_slot').value = slot; },
      null, (planAppt ? '&plan_id=' + appt.treatment_plan_id : '') + '&exclude_id=' + id,
      { current: { date: String(appt.scheduled_date).slice(0, 10), slot: String(appt.time_slot || '').slice(0, 5), dentistId: appt.dentist_id } });

    if (absent) {
      g('rs_dentist').addEventListener('change', () => {
        g('rs_date').value = ''; g('rs_slot').value = '';
        cal.setDentist(+g('rs_dentist').value);
      });
    }

    g('rs_go').addEventListener('click', async () => {
      const reason = g('rs_reason').value.trim();
      if (!g('rs_date').value) return toast('Pick a new date.', 'err');
      if (!g('rs_slot').value) return toast('Pick an available time slot.', 'err');
      if (!reason) return toast('Please enter a reason for rescheduling.', 'err');
      const body = { appointment_id: id, scheduled_date: g('rs_date').value, time_slot: g('rs_slot').value, reason };
      if (absent) body.dentist_id = +g('rs_dentist').value;
      try {
        await api('appointments.php?action=reschedule', { method: 'POST', body });
        closeModal(); toast('Appointment rescheduled.'); loadAppts(); refreshBellCount();
      } catch (err) { toast(err.message, 'err'); }
    });
  }

  /* ---------- Cancel (confirmation step + reason required — the reservation fee is forfeited) ---------- */
  function cancelModal(id, prevReason) {
    openModal(modalShell('Confirm Cancellation',
      '<p class="mt-0 text-[15px] font-medium text-dgt-ink-700 dark:text-dgt-ink-100">Are you sure you really want to cancel this appointment?</p>' +
      '<div class="' + window.alertCls('error') + ' !mt-2.5 mb-2.5">' +
      '<strong>Please note:</strong> According to clinic policy, appointment down payments are strictly <strong>NON-REFUNDABLE</strong> upon cancellation.' +
      '<div class="mt-1 text-[12.5px]">Notify the clinic at least <strong>24 hours</strong> ahead and <strong>reschedule</strong> instead to carry your fee over at no extra charge.</div>' +
      '</div>' +
      '<div class="' + FGF + '"><label class="' + LBL + '">Reason for Cancellation</label>' +
      '<textarea class="' + INP + ' min-h-[72px] resize-y" id="cx_reason" placeholder="e.g. I have a schedule conflict that day.">' + esc(prevReason || '') + '</textarea></div>',
      '<button class="' + BTN + GHOST + '" data-close>Keep Appointment</button>' +
      '<button class="' + BTN + DANGER + '" id="cx_go">Yes, Cancel Appointment</button>'));
    g('cx_go').addEventListener('click', () => {
      const reason = g('cx_reason').value.trim();
      if (!reason) return toast('Please enter a reason for cancelling.', 'err');
      cancelModalStep2(id, reason);
    });
  }

  // Second, final confirmation — spells out exactly what happens before the
  // API call actually fires, so a click on "Yes, Cancel Appointment" isn't
  // itself the point of no return.
  function cancelModalStep2(id, reason) {
    const appt = ALL.find((a) => a.appointment_id === id) || {};
    openModal(modalShell('Are You Absolutely Sure?',
      '<div class="' + window.alertCls('error') + ' !mt-0">' +
      '<strong>This cannot be undone.</strong> Confirming will:' +
      '<ul class="mt-1.5 list-disc space-y-1 pl-4 text-[12.5px]">' +
      '<li>Cancel your appointment on <strong>' + esc(longDate(appt.scheduled_date)) + '</strong> at <strong>' + esc(fmtTime(appt.time_slot)) + '</strong> immediately.</li>' +
      '<li>Forfeit your reservation fee: it will <strong>not</strong> be refunded.</li>' +
      '<li>Release this time slot, so you would need to make a brand-new booking (and pay a new reservation fee) to be seen.</li>' +
      '</ul></div>' +
      '<p class="mt-2.5 text-[13px] text-dgt-ink-500 dark:text-dgt-ink-300">Reason given: &ldquo;' + esc(reason) + '&rdquo;</p>',
      '<button class="' + BTN + GHOST + '" id="cx_back">No, Go Back</button>' +
      '<button class="' + BTN + DANGER + '" id="cx_final">Yes, I&rsquo;m Sure, Cancel It</button>'));
    g('cx_back').addEventListener('click', () => cancelModal(id, reason));
    g('cx_final').addEventListener('click', async () => {
      try {
        await api('appointments.php?action=cancel', { method: 'POST', body: { appointment_id: id, reason } });
        closeModal(); toast('Your appointment slot has been cancelled.'); loadAppts(); refreshBellCount();
      } catch (err) { toast(err.message, 'err'); }
    });
  }

  // Reopens an unfinished booking (e.g. the wizard was closed by an
  // accidental click outside it) instead of starting from blank.
  g('bookBtn').addEventListener('click', () => {
    backToServices = false;
    const d = readDraft();
    bookModal(d && draftHasInput(d) ? pfFromDraft(d) : undefined);
  });

  // Arriving from the "Calendar View" booking calendar: it stashes the chosen date + time
  // in sessionStorage (a query string would land after nav.js re-runs this
  // script). Open the wizard on step 1 with both fields pre-filled.
  (function bookingPrefill() {
    let pf = null;
    try {
      const raw = sessionStorage.getItem('dgt_book_prefill');
      if (raw) { sessionStorage.removeItem('dgt_book_prefill'); pf = JSON.parse(raw); }
    } catch (_) { /* ignore */ }
    // Either a date/slot (Calendar View) or a service (a card on the Services
    // page) can arrive, or both.
    const hasDate = pf && /^\d{4}-\d{2}-\d{2}$/.test(pf.date || '');
    const svcId = pf && /^\d+$/.test(String(pf.service_id || '')) ? String(pf.service_id) : '';
    if (!hasDate && !svcId) {
      // No hand-off from another page: bring back an unfinished booking.
      // Reopen only if the wizard was still open when the page went away.
      const d = readDraft();
      if (d && d.open !== false && draftHasInput(d)) bookModal(pfFromDraft(d));
      return;
    }
    clearDraft();   // a fresh hand-off replaces any older unfinished booking
    backToServices = !!svcId && pf.from === 'services';
    {
      bookModal({
        scheduled_date: hasDate ? pf.date : undefined,
        time_slot: hasDate ? (pf.slot || undefined) : undefined,
        service_id: svcId || undefined,
      });
    }
  })();
  g('apptStatus').addEventListener('change', () => { TAB = g('apptStatus').value; PAGE = 1; render(); });
  g('apptDate').addEventListener('change', () => { PAGE = 1; render(); });
  g('apptReset').addEventListener('click', () => {
    TAB = 'all'; g('apptStatus').value = 'all'; g('apptDate').value = ''; PAGE = 1; render();
  });
  // A resize (or the sidebar collapsing) can change the column count: keep
  // the first card on screen and re-page around it.
  if (window.__apptGridRO) window.__apptGridRO.disconnect();
  let roTimer = null;
  window.__apptGridRO = new ResizeObserver(() => {
    if (!g('apptGrid')) { window.__apptGridRO.disconnect(); return; }
    clearTimeout(roTimer);
    roTimer = setTimeout(() => {
      // Ignore the scrollbar appearing / disappearing (about 15px): a page
      // change can toggle it, and re-paging on that sent Next back to page 1.
      const w = g('apptGrid').clientWidth;
      if (Math.abs(w - GRID_W) < 24) return;
      const next = perPage();
      if (next === PER_PAGE || !ALL.length) { GRID_W = w; return; }
      PAGE = Math.floor(((PAGE - 1) * PER_PAGE) / next) + 1;
      render();
    }, 150);
  });
  window.__apptGridRO.observe(g('apptGrid'));
  g('apptPager').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-page]');
    if (!btn || btn.disabled) return;
    PAGE = +btn.dataset.page;
    render();
    // Bring the top of the list back into view when it scrolled away.
    if (g('apptFilters').getBoundingClientRect().top < 0) {
      g('apptFilters').scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    }
  });
  g('apptGrid').addEventListener('click', (e) => {
    const c = e.target.closest('[data-cancel]');
    const r = e.target.closest('[data-resched]');
    const p = e.target.closest('[data-resubmit-payment]');
    if (c) cancelModal(+c.dataset.cancel);
    if (r) reschedModal(+r.dataset.resched);
    if (p) resubmitPaymentModal(+p.dataset.resubmitPayment);
  });

  // Used by the notification pop-up's Reschedule button when already on this
  // page; loadAppts() picks up the id it left in sessionStorage.

  loadAppts();
})();
JS;
$PAGE_SCRIPT = str_replace(
    ['__ME__', '__PHONE__', '__FEE__', '__AGE__', '__LEAD_DAYS__'],
    [
        json_encode($me['full_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        json_encode((string) ($me['contact_number'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        json_encode((float) setting('reservation_fee', '500.00')),
        json_encode($selfAge, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        (string) onlineLeadDays(),
    ],
    $PAGE_SCRIPT
);
require __DIR__ . '/../../includes/layout.php';
