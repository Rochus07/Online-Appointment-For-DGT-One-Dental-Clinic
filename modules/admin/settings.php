<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions.php';

$me = checkAuth(['admin']);
$PAGE_TITLE = 'Settings';
$ACTIVE = 'settings';
$PAGE_FULL = true;   // admin screens fill the full viewport width

$btnBase    = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold disabled:cursor-not-allowed disabled:opacity-50';
$btnPrimary = "$btnBase bg-accent text-white hover:bg-accent-dark";
$btnDark    = "$btnBase bg-navy-700 text-white hover:brightness-110";
$btnDanger  = "$btnBase bg-danger text-white hover:brightness-95";
$cardCls    = 'rounded-[10px] border border-steel-100 bg-white p-[18px] shadow-sm dark:border-white/10 dark:bg-navy-800';
$headCls    = 'mb-3.5 flex flex-wrap items-center justify-between gap-3';
$h2Cls      = 'text-[15px] text-navy-800 dark:text-steel-50';
$fgCls      = 'flex flex-col gap-1.5';
$lblCls     = 'text-xs font-semibold text-steel-500 dark:text-steel-300';
$inputCls   = 'rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
$hintCls    = 'text-[11px] text-steel-400 dark:text-steel-500';
$sepCls     = 'my-4 border-0 border-t border-steel-100 dark:border-white/10';

ob_start();
?>
<!-- Settings layout (2026-10-19, third pass): page title + underlined tabs,
     then each group is "what it is" on the left and a white card with its
     controls on the right. Text fields save from the card footer, whose Save
     button only turns on once something was changed. -->
<?php
$toggle = static function (string $id, bool $on): string {
    return '<span class="relative inline-flex shrink-0">'
        . '<input type="checkbox" id="' . $id . '" class="peer sr-only"' . ($on ? ' checked' : '') . '>'
        . '<span class="h-6 w-11 rounded-full bg-steel-200 transition-colors peer-checked:bg-dgt-blue-500 peer-focus-visible:ring-2 peer-focus-visible:ring-dgt-blue-500 peer-focus-visible:ring-offset-2 dark:bg-white/15"></span>'
        . '<span class="pointer-events-none absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-5"></span>'
        . '</span>';
};
$secCls   = '';
$hdrCls   = 'flex flex-col gap-2 pb-1 sm:flex-row sm:flex-wrap sm:items-end sm:gap-x-6';
$stackRow = 'flex flex-col items-start gap-2.5 py-3';
$secTitle = 'text-[15px] font-bold text-navy-800 dark:text-steel-50';
$secText  = 'mt-0.5 text-[12px] leading-relaxed text-steel-500 dark:text-steel-400';
$boxCls   = 'min-w-0';
$rowCls   = 'flex flex-col gap-2 py-3 sm:max-w-[860px] sm:flex-row sm:items-center sm:justify-between sm:gap-8';
$rowTitle = 'block text-[13.5px] font-semibold text-navy-800 dark:text-steel-50';
$rowText  = 'mt-0.5 block max-w-[620px] text-[12px] leading-relaxed text-steel-500 dark:text-steel-400';
$footCls  = 'flex flex-wrap items-center justify-start gap-3 pt-2';
$dirtyCls = 'empty:hidden text-[12px] font-medium text-steel-400 dark:text-steel-500';
$saveCls  = $btnPrimary . ' min-w-[96px]';
$ghostBtn = $btnBase . ' border border-steel-200 bg-white text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:bg-transparent dark:text-steel-100 dark:hover:bg-white/5';
$ghostSm  = $ghostBtn . ' !px-3 !py-1.5 !text-[12.5px]';
$fInput   = $inputCls . ' w-full';
$tabs = [
    'booking'  => 'Booking & Fees',
    'payments' => 'Payment Methods',
    'schedule' => 'Hours & Closed Dates',
    'promos'   => 'Promotions',
    'legal'    => 'Terms & Privacy',
    'backup'   => 'Backup',
];
$qrBlock = static function (string $p, string $hint) use ($ghostSm): string {
    return '<div class="flex items-center gap-4">'
        . '<div id="' . $p . 'Preview" class="grid h-[80px] w-[80px] shrink-0 place-items-center overflow-hidden rounded-lg border border-steel-200 bg-white p-1 text-center text-[11px] text-steel-400 dark:border-white/15">Loading&hellip;</div>'
        . '<div><span class="block text-[13.5px] font-semibold text-navy-800 dark:text-steel-50">QR code</span>'
        . '<span class="mt-0.5 block text-[12px] text-steel-500 dark:text-steel-400">' . $hint . '</span>'
        . '<div class="mt-2 flex flex-wrap items-center gap-2">'
        . '<label for="' . $p . 'File" class="' . $ghostSm . '"><svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M4 20h16"/></svg>Upload new</label>'
        . '<input type="file" id="' . $p . 'File" accept="image/jpeg,image/png,image/webp" class="sr-only">'
        . '<button type="button" id="' . $p . 'Remove" class="hidden rounded-md px-3 py-1.5 text-[12.5px] font-semibold text-danger hover:bg-danger-soft dark:hover:bg-danger/15">Remove</button>'
        . '</div></div></div>';
};
?>
<style>
  /* Settings reads 15% bigger than the rest of the app; together with the
     app-wide --uiz zoom it never goes past 1.3x. */
  #setPage { zoom: min(1.15, calc(1.3 / var(--uiz, 1))); }
  @media print { #setPage { zoom: 1; } }
</style>
<div class="w-full" id="setPage">
<div class="mb-1 flex flex-wrap items-end justify-between gap-3">
    <h1 class="text-[22px] font-bold tracking-tight text-navy-800 dark:text-steel-50">Settings</h1>
</div>
<nav aria-label="Settings sections" class="border-b border-steel-200 dark:border-white/10">
    <ul id="setNav" class="-mb-px flex gap-6 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <?php foreach ($tabs as $key => $label): ?>
        <li class="shrink-0"><button type="button" data-sec="<?= $key ?>" class="tab whitespace-nowrap rounded-none border-b-2 outline-none focus-visible:text-navy-800 focus-visible:underline border-transparent pb-2.5 pt-2 text-[13.5px] font-medium text-steel-500 transition-colors hover:border-steel-300 hover:text-navy-800 dark:text-steel-300 dark:hover:text-steel-50 [&.on]:border-dgt-blue-500 [&.on]:font-semibold [&.on]:text-dgt-blue-500 dark:[&.on]:border-dgt-blue-300 dark:[&.on]:text-dgt-blue-300"><?= e($label) ?></button></li>
        <?php endforeach; ?>
    </ul>
</nav>

<!-- Booking & Fees -->
<section data-panel="booking" class="pt-6 [&>*+*]:mt-8">
    <div class="grid grid-cols-1 gap-8">
        <div class="<?= $boxCls ?>">
                <div class="<?= $hdrCls ?>">
                    <div><h2 class="<?= $secTitle ?>">Reservation</h2>
                        <p class="<?= $secText ?>">The fee patients pay to hold a slot when they book online. It is taken off their final bill.</p></div>
                </div>
                <div class="<?= $stackRow ?>">
                    <label for="s_fee" class="<?= $rowTitle ?>">Reservation fee</label>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="<?= $dirtyCls ?>" data-dirty-for="saveFees"></span>
                        <div class="relative w-[160px]"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[13px] font-semibold text-steel-400">₱</span>
                            <input type="text" inputmode="decimal" data-money id="s_fee" value="<?= e(number_format((float) setting('reservation_fee', '500.00'), 2)) ?>" class="<?= $fInput ?> pl-7"></div>
                        <button class="<?= $saveCls ?>" id="saveFees">Save</button>
                    </div>
                </div>
        </div>
        <div class="<?= $boxCls ?>">
                <div class="<?= $hdrCls ?>">
                    <div><h2 class="<?= $secTitle ?>">Booking Rules</h2>
                        <p class="<?= $secText ?>">How patients book and pay online. Switches save right away.</p></div>
                </div>
                <label class="<?= $rowCls ?> cursor-pointer">
                    <span><span class="<?= $rowTitle ?>">Require a payment screenshot</span>
                        <span class="<?= $rowText ?>">GCash and bank payers upload a receipt screenshot with the reference number. When off, the reference number is enough.</span></span>
                    <?= $toggle('s_receipt', setting('enable_receipt_validation', '1') !== '0') ?>
                </label>
                <label class="<?= $rowCls ?> cursor-pointer">
                    <span><span class="<?= $rowTitle ?>">Book at least 1 day ahead</span>
                        <span class="<?= $rowText ?>">Online bookings can only pick tomorrow or later. Front-desk walk-ins can always book today.</span></span>
                    <?= $toggle('s_lead', setting('enforce_two_day_advance', '1') !== '0') ?>
                </label>
                <label class="<?= $rowCls ?> cursor-pointer">
                    <span><span class="<?= $rowTitle ?>">Open on Sundays <span class="ml-1.5 rounded-full bg-warn-soft px-2 py-[2px] align-middle text-[10px] font-bold uppercase tracking-wide text-warn dark:bg-warn/20">Temporary</span></span>
                        <span class="<?= $rowText ?>">Sundays can be booked online and by walk-in, and dentists work their Saturday hours.</span></span>
                    <?= $toggle('s_sunday', setting('sunday_open', '0') === '1') ?>
                </label>
        </div>
    </div>
</section>

<!-- Payment Methods -->
<?php $bankLines = array_pad(preg_split('/\r?\n/', setting('pay_bank_details', '')), 3, ''); ?>
<section data-panel="payments" class="pt-6 [&>*+*]:mt-8" hidden>
    <div class="grid grid-cols-1 gap-8">
        <div class="<?= $boxCls ?>">
                <div class="<?= $hdrCls ?>">
                    <div><h2 class="<?= $secTitle ?>">GCash</h2>
                        <p class="<?= $secText ?>">What patients see when they pick GCash on the payment step.</p></div>
                </div>
                <div class="py-3"><?= $qrBlock('qr', 'JPG, PNG or WEBP, up to 3 MB.') ?></div>
                <div class="grid grid-cols-1 gap-4 py-2 sm:grid-cols-[repeat(2,minmax(0,340px))] dark:border-white/10">
                    <div class="<?= $fgCls ?>"><label class="<?= $lblCls ?>" for="g_name">Account name</label>
                        <input type="text" id="g_name" maxlength="100" value="<?= e(setting('pay_gcash_name', '')) ?>" class="<?= $fInput ?>"></div>
                    <div class="<?= $fgCls ?>"><label class="<?= $lblCls ?>" for="g_num">Mobile number</label>
                        <input type="tel" inputmode="numeric" id="g_num" maxlength="11" placeholder="09XXXXXXXXX" value="<?= e(setting('pay_gcash_number', '')) ?>" class="<?= $fInput ?>">
                        <div class="field-err" id="g_numErr" hidden></div></div>
                </div>
                <div class="<?= $footCls ?>"><span class="<?= $dirtyCls ?>" data-dirty-for="saveGcash"></span>
                    <button class="<?= $saveCls ?>" id="saveGcash">Save</button></div>
        </div>
        <div class="<?= $boxCls ?>">
                <div class="<?= $hdrCls ?>">
                    <div><h2 class="<?= $secTitle ?>">Bank Transfer</h2>
                        <p class="<?= $secText ?>">What patients see when they pick Bank Transfer. Patients only see the QR code and account name.</p></div>
                </div>
                <div class="py-3"><?= $qrBlock('bqr', 'JPG, PNG or WEBP, up to 3 MB.') ?></div>
                <div class="grid grid-cols-1 gap-4 py-2 sm:grid-cols-[repeat(3,minmax(0,300px))] dark:border-white/10">
                    <div class="<?= $fgCls ?>"><label class="<?= $lblCls ?>" for="b_bank">Bank name</label>
                        <input type="text" id="b_bank" maxlength="80" value="<?= e(trim($bankLines[0])) ?>" class="<?= $fInput ?>"></div>
                    <div class="<?= $fgCls ?>"><label class="<?= $lblCls ?>" for="b_name">Account name</label>
                        <input type="text" id="b_name" maxlength="80" value="<?= e(trim($bankLines[1])) ?>" class="<?= $fInput ?>"></div>
                    <div class="<?= $fgCls ?>"><label class="<?= $lblCls ?>" for="b_acct">Account number</label>
                        <input type="text" id="b_acct" maxlength="60" value="<?= e(trim($bankLines[2])) ?>" class="<?= $fInput ?>"></div>
                </div>
                <div class="<?= $footCls ?>"><span class="<?= $dirtyCls ?>" data-dirty-for="saveBank"></span>
                    <button class="<?= $saveCls ?>" id="saveBank">Save</button></div>
        </div>
    </div>
    <div class="<?= $boxCls ?>">
            <div class="<?= $hdrCls ?>">
                <div><h2 class="<?= $secTitle ?>">More Payment Methods</h2>
                    <p class="<?= $secText ?>">Other ways to pay the fee, like Maya or another bank. Each one shows next to GCash and Bank Transfer while it is turned on.</p></div>
                <div class="flex shrink-0 flex-wrap items-center gap-3"><button class="<?= $ghostBtn ?>" id="pmAdd">+ Add payment method</button></div>
            </div>
            <div id="pmList" class="mt-2 max-h-[360px] overflow-y-auto rounded-lg border border-steel-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-navy-800"><p class="p-6 text-center text-steel-400 dark:text-steel-500">Loading&hellip;</p></div>
    </div>
</section>

<!-- Hours & Closed Dates -->
<section data-panel="schedule" class="pt-6 [&>*+*]:mt-8" hidden>
    <div class="grid grid-cols-1 gap-8">
        <div class="<?= $boxCls ?>">
                <div class="<?= $hdrCls ?>">
                    <div><h2 class="<?= $secTitle ?>">Clinic Time Slots</h2>
                        <p class="<?= $secText ?>">The hours patients can book. Each visit takes one slot.</p></div>
                </div>
                <div class="py-3">
                    <span class="<?= $lblCls ?>">Current slots</span>
                    <div id="slotList" class="mt-2 flex flex-wrap gap-2"><span class="text-steel-400 dark:text-steel-500">Loading&hellip;</span></div>
                </div>
                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <label for="slotInput" class="text-[12.5px] font-semibold text-steel-500 dark:text-steel-300">Add a slot</label>
                    <input type="time" id="slotInput" class="<?= $inputCls ?> w-[150px] bg-white">
                    <button class="<?= $ghostBtn ?>" id="slotAdd">Add</button>
                </div>
        </div>
        <div class="<?= $boxCls ?>">
                <div class="<?= $hdrCls ?>">
                    <div><h2 class="<?= $secTitle ?>">Closed Dates <span id="blockedCount" class="ml-1.5 rounded-full bg-steel-100 px-2 py-[2px] align-middle text-[11px] font-semibold text-steel-500 empty:hidden dark:bg-white/10 dark:text-steel-300"></span></h2>
                        <p class="<?= $secText ?>">Close the whole clinic for a day, like a holiday. Nobody can book on it.</p></div>
                </div>
                <div class="grid grid-cols-1 items-end gap-3 py-3 sm:grid-cols-[170px_minmax(0,380px)_auto] sm:justify-start">
                    <div class="<?= $fgCls ?>"><label class="<?= $lblCls ?>" for="b_date">Date</label>
                        <input type="date" id="b_date" min="<?= date('Y-m-d') ?>" class="<?= $fInput ?>"></div>
                    <div class="<?= $fgCls ?>"><label class="<?= $lblCls ?>" for="b_reason">Reason</label>
                        <input type="text" id="b_reason" placeholder="e.g. Holiday, Maintenance" class="<?= $fInput ?>"></div>
                    <button class="<?= $btnDark ?> " id="blockBtn">Block Date</button>
                </div>
                <div id="blockedList" class="max-h-[340px] overflow-y-auto rounded-lg border border-steel-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-navy-800"><p class="p-6 text-center text-steel-400 dark:text-steel-500">Loading&hellip;</p></div>
        </div>
    </div>
</section>

<!-- Promotions -->
<section data-panel="promos" class="pt-6 [&>*+*]:mt-8" hidden>
    <div class="<?= $boxCls ?>">
            <div class="<?= $hdrCls ?>">
                <div><h2 class="<?= $secTitle ?>">Promotions &amp; Discounts</h2>
                    <p class="<?= $secText ?>">Promos the cashier can apply on the Service Invoice, for all services or only the ones you pick. Turn one off to retire it without losing its history.</p></div>
                <div class="flex shrink-0 flex-wrap items-center gap-3"><p id="discCount" class="text-[12px] font-semibold text-steel-400 dark:text-steel-500"></p> <button class="<?= $ghostBtn ?>" id="newDiscBtn">+ New discount</button></div>
            </div>
            <div id="discList" class="mt-2 max-h-[560px] overflow-y-auto rounded-lg border border-steel-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-navy-800"><p class="p-6 text-center text-steel-400 dark:text-steel-500">Loading&hellip;</p></div>
    </div>
</section>

<!-- Terms & Privacy -->
<section data-panel="legal" class="pt-6 [&>*+*]:mt-8" hidden>
    <div class="<?= $boxCls ?>">
            <div class="<?= $hdrCls ?>">
                <div><h2 class="<?= $secTitle ?>">Terms &amp; Privacy</h2>
                    <p class="<?= $secText ?>">Shown on the public Terms and Privacy pages, linked from sign-up and the website footer.</p></div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                <div class="flex gap-1 rounded-lg bg-steel-100/70 p-1 dark:bg-white/5" role="tablist" aria-label="Document">
                    <button type="button" role="tab" class="tab rounded-md px-3 py-1.5 text-[13px] font-semibold text-steel-500 [&.active]:bg-white [&.active]:text-navy-800 [&.active]:shadow-sm dark:text-steel-300 dark:[&.active]:bg-navy-900 dark:[&.active]:text-steel-50 active" data-doc="terms" aria-selected="true">Terms &amp; Conditions</button>
                    <button type="button" role="tab" class="tab rounded-md px-3 py-1.5 text-[13px] font-semibold text-steel-500 [&.active]:bg-white [&.active]:text-navy-800 [&.active]:shadow-sm dark:text-steel-300 dark:[&.active]:bg-navy-900 dark:[&.active]:text-steel-50" data-doc="privacy" aria-selected="false">Privacy Policy</button>
                </div>
                <a id="viewLegal" href="/dgt_dental_clinic/terms" target="_blank" rel="noopener" class="text-[13px] font-semibold text-dgt-blue-500 hover:underline dark:text-dgt-blue-300">View page &#8599;</a>
            </div>
            <textarea id="legalText" rows="22" spellcheck="true" class="block w-full resize-y rounded-lg border border-steel-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-navy-800 px-4 py-3 font-mono text-[12.5px] leading-relaxed text-navy-800 outline-none dark:text-steel-100" aria-label="Document text"></textarea>
            <div class="<?= $footCls ?>">
                <span id="legalStatus" class="text-[12px] text-steel-400 dark:text-steel-500"></span>
                <button class="<?= $ghostBtn ?> ml-auto" id="resetLegal">Reset to Default</button>
                <button class="<?= $saveCls ?>" id="saveLegal">Save</button>
            </div>
    </div>
</section>

<!-- Backup -->
<section data-panel="backup" class="pt-6 [&>*+*]:mt-8" hidden>
    <div class="grid grid-cols-1 gap-8">
        <div class="<?= $boxCls ?>">
                <div class="<?= $hdrCls ?>">
                    <div><h2 class="<?= $secTitle ?>">Backup</h2>
                        <p class="<?= $secText ?>">A full copy of the <strong>dental_clinic_system</strong> database as one .sql file.</p></div>
                </div>
                <div class="<?= $stackRow ?>">
                    <span><span class="<?= $rowTitle ?>">Download a backup</span>
                        <span class="<?= $rowText ?>">Every patient, appointment, payment and setting. Keep it somewhere safe.</span></span>
                    <button class="<?= $ghostBtn ?> shrink-0" id="backupBtn"><svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v12M7 11l5 5 5-5M4 20h16"/></svg>Download .sql</button>
                </div>
        </div>
        <div class="<?= $boxCls ?> !border-danger/40">
                <div class="<?= $hdrCls ?>">
                    <div><h2 class="<?= $secTitle ?> !text-danger">Restore</h2>
                        <p class="<?= $secText ?>">Replaces the whole database with a backup file. This <strong class="text-danger">overwrites all current data</strong> and cannot be undone.</p></div>
                </div>
                <div class="<?= $stackRow ?>">
                    <span class="min-w-0"><label for="restoreFile" class="<?= $rowTitle ?>">Backup file</label>
                        <input type="file" id="restoreFile" accept=".sql,application/sql,text/plain" class="mt-2 block w-full max-w-[360px] text-[12.5px] text-steel-500 file:mr-3 file:cursor-pointer file:rounded-md file:border file:border-steel-200 file:bg-white file:px-3 file:py-1.5 file:text-[12.5px] file:font-semibold file:text-navy-700 hover:file:bg-steel-50 dark:text-steel-400 dark:file:border-white/15 dark:file:bg-transparent dark:file:text-steel-100"></span>
                    <button class="<?= $btnDanger ?> shrink-0" id="restoreBtn">Restore Database</button>
                </div>
        </div>
    </div>
</section>
</div>
<?php
$PAGE_CONTENT = ob_get_clean();
$PAGE_SCRIPT = <<<'JS'
(function () {
  // Section list: one section shows at a time, remembered per tab.
  (function () {
    const btns = [...document.querySelectorAll('#setNav [data-sec]')];
    const panels = [...document.querySelectorAll('[data-panel]')];
    function show(key) {
      if (!panels.some((p) => p.dataset.panel === key)) key = 'booking';
      btns.forEach((b) => { const on = b.dataset.sec === key; b.classList.toggle('on', on); if (on) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current'); });
      panels.forEach((p) => { p.hidden = p.dataset.panel !== key; });
      try { sessionStorage.setItem('dgt_settings_sec', key); } catch (_) {}
    }
    btns.forEach((b) => b.addEventListener('click', () => show(b.dataset.sec)));
    let start = 'booking';
    try { start = sessionStorage.getItem('dgt_settings_sec') || 'booking'; } catch (_) {}
    show(start);
  })();

  // Card Save buttons stay off until one of their fields changes; after a
  // successful save they show "Saved" and turn off again.
  const DIRTY = {};
  function wireDirty(btnId, ids) {
    const btn = document.getElementById(btnId);
    const note = document.querySelector('[data-dirty-for="' + btnId + '"]');
    const els = ids.map((i) => document.getElementById(i)).filter(Boolean);
    let base = els.map((el) => el.value);
    const check = () => {
      const changed = els.some((el, i) => el.value !== base[i]);
      btn.disabled = !changed;
      if (note) { note.textContent = changed ? 'Unsaved changes' : ''; note.classList.toggle('!text-warn', changed); }
    };
    els.forEach((el) => { el.addEventListener('input', check); el.addEventListener('change', check); });
    DIRTY[btnId] = () => {
      base = els.map((el) => el.value); check();
      if (note) { note.textContent = '\u2713 Saved'; note.classList.add('!text-ok'); setTimeout(() => { if (btn.disabled) note.textContent = ''; note.classList.remove('!text-ok'); }, 2500); }
    };
    check();
  }
  const markSaved = (btnId) => { if (DIRTY[btnId]) DIRTY[btnId](); };

  const BTN_SM_DANGER = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md bg-danger px-2.5 py-1.5 text-xs font-semibold text-white hover:brightness-95';
  const BTN     = 'inline-flex cursor-pointer items-center justify-center gap-[7px] rounded-md px-[15px] py-[9px] text-[13px] font-semibold disabled:opacity-50';
  const GHOST   = ' border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
  const DANGER  = ' bg-danger text-white hover:brightness-95';
  const TABLE   = 'w-full border-collapse text-[13px] [&_th]:sticky [&_th]:top-0 [&_th]:z-10 [&_th]:border-b [&_th]:border-steel-100 [&_th]:bg-steel-50 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-semibold [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-steel-500 [&_th]:dark:border-white/10 [&_th]:dark:bg-navy-800 [&_th]:dark:text-steel-300 [&_td]:border-b [&_td]:border-steel-100 [&_td]:px-3 [&_td]:py-2.5 [&_td]:dark:border-white/10';
  const EMPTY   = 'py-7 text-center text-steel-400 dark:text-steel-500';

  async function loadBlocked() {
    const wrap = document.getElementById('blockedList');
    const count = document.getElementById('blockedCount');
    try {
      const { blocked } = await api('settings.php?action=blocked_dates');
      count.textContent = blocked.length ? blocked.length + (blocked.length === 1 ? ' date' : ' dates') : '';
      wrap.innerHTML = blocked.length
        ? '<div class="overflow-x-auto"><table class="' + TABLE + '"><thead><tr><th>Date</th><th>Reason</th><th></th></tr></thead><tbody>' +
          blocked.map((b) => '<tr><td class="whitespace-nowrap">' + fmtDate(b.blocked_date) + '</td><td>' + esc(b.reason || 'N/A') +
          '</td><td class="text-right"><button class="' + BTN_SM_DANGER + '" data-unblock="' + esc(b.blocked_date) + '">Unblock</button></td></tr>').join('') +
          '</tbody></table></div>'
        : '<p class="' + EMPTY + '">No dates are blocked.</p>';
    } catch (e) { count.textContent = ''; wrap.innerHTML = '<p class="' + EMPTY + '">' + esc(e.message) + '</p>'; }
  }

  document.getElementById('saveFees').addEventListener('click', async () => {
    try {
      await api('settings.php?action=save_fees', { method: 'POST', body: {
        reservation_fee: window.moneyVal(document.getElementById('s_fee')),
      }});
      toast('Reservation fee saved.'); markSaved('saveFees');
    } catch (e) { toast(e.message, 'err'); }
  });

  document.getElementById('s_receipt').addEventListener('change', async (e) => {
    const box = e.target;
    try {
      await api('settings.php?action=save_receipt_validation', { method: 'POST', body: { enabled: box.checked } });
      toast(box.checked ? 'Payment screenshot is now required for online payments.' : 'Online payments now only need the reference number.');
    } catch (err) {
      box.checked = !box.checked;   // put the switch back if the save failed
      toast(err.message, 'err');
    }
  });

  document.getElementById('s_lead').addEventListener('change', async (e) => {
    const box = e.target;
    try {
      await api('settings.php?action=save_lead_time', { method: 'POST', body: { enabled: box.checked } });
      toast(box.checked ? 'Online bookings now need at least 1 day of notice.' : 'Patients can now book online for today.');
    } catch (err) {
      box.checked = !box.checked;
      toast(err.message, 'err');
    }
  });

  document.getElementById('s_sunday').addEventListener('change', async (e) => {
    const box = e.target;
    try {
      await api('settings.php?action=save_sunday_open', { method: 'POST', body: { enabled: box.checked } });
      window.SUNDAY_OPEN = box.checked;
      toast(box.checked ? 'The clinic is now open on Sundays.' : 'Sundays are closed again.');
    } catch (err) {
      box.checked = !box.checked;
      toast(err.message, 'err');
    }
  });

  document.getElementById('blockBtn').addEventListener('click', async () => {
    const d = document.getElementById('b_date').value;
    if (!d) return toast('Pick a date first.', 'err');
    try {
      await api('settings.php?action=block_date', { method: 'POST', body: {
        blocked_date: d, reason: document.getElementById('b_reason').value.trim(),
      }});
      document.getElementById('b_reason').value = '';
      toast('Date blocked.'); loadBlocked();
    } catch (e) { toast(e.message, 'err'); }
  });

  document.getElementById('blockedList').addEventListener('click', async (e) => {
    const b = e.target.closest('[data-unblock]');
    if (!b) return;
    try {
      await api('settings.php?action=unblock_date', { method: 'POST', body: { blocked_date: b.dataset.unblock } });
      loadBlocked();
    } catch (err) { toast(err.message, 'err'); }
  });

  const BASE = '/dgt_dental_clinic';

  document.getElementById('backupBtn').addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    btn.disabled = true;
    toast('Preparing backup…', 'info');
    try {
      const res = await fetch(BASE + '/api/settings.php?action=backup', { headers: { 'X-Requested-With': 'fetch' } });
      const type = res.headers.get('Content-Type') || '';
      if (!res.ok || type.includes('json')) {
        let msg = 'Backup failed.';
        try { msg = (await res.json()).error || msg; } catch (_) {}
        throw new Error(msg);
      }
      const name = ((res.headers.get('Content-Disposition') || '').match(/filename="([^"]+)"/) || [])[1] || 'dental_clinic_backup.sql';
      const url = URL.createObjectURL(await res.blob());
      const a = document.createElement('a');
      a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(url), 2000);
      toast('Backup downloaded.');
    } catch (err) {
      toast(err.message, 'err');
    } finally {
      btn.disabled = false;
    }
  });

  document.getElementById('restoreBtn').addEventListener('click', () => {
    const input = document.getElementById('restoreFile');
    const file = input.files[0];
    if (!file) return toast('Choose a .sql backup file first.', 'err');
    if (!/\.sql$/i.test(file.name)) return toast('The file must be a .sql backup.', 'err');

    openModal(modalShell('Restore Database',
      '<p>This will <strong>permanently overwrite every record</strong> in the database with the ' +
      'contents of <strong>' + esc(file.name) + '</strong>.</p><p>This cannot be undone. Continue?</p>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button>' +
      '<button class="' + BTN + DANGER + '" id="restoreGo">Overwrite &amp; Restore</button>'));

    document.getElementById('restoreGo').addEventListener('click', async () => {
      const btn = document.getElementById('restoreGo');
      btn.disabled = true; btn.textContent = 'Restoring…';
      try {
        const fd = new FormData();
        fd.append('sqlfile', file);
        const res = await fetch(BASE + '/api/settings.php?action=restore', {
          method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: fd,
        });
        const data = await res.json();
        if (!res.ok || data.ok === false) throw new Error(data.error || 'Restore failed.');
        closeModal();
        toast('Database restored. Reloading…');
        setTimeout(() => window.location.reload(), 1200);
      } catch (e) {
        btn.disabled = false; btn.textContent = 'Overwrite & Restore';
        toast(e.message, 'err');
      }
    });
  });

  /* ---------------------------------------------------------------- Promotions & Discounts */
  const BTN_SM        = 'inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold';
  const BTN_SM_GHOST  = BTN_SM + ' border border-steel-200 bg-transparent text-navy-700 hover:bg-steel-50 dark:border-white/15 dark:text-steel-100 dark:hover:bg-white/5';
  const PRIMARY       = ' bg-accent text-white hover:bg-accent-dark';
  const FG            = 'flex flex-col gap-1.5';
  const LBL           = 'text-xs font-semibold text-steel-500 dark:text-steel-300';
  const INP           = 'w-full rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
  const CHECK         = 'flex items-center gap-2 text-[13px] dark:text-steel-100';
  let SERVICES = [];

  const discValue = (d) => d.discount_type === 'percentage'
    ? (Number(d.discount_value) + '%')
    : ('₱' + Number(d.discount_value).toLocaleString('en-PH', { minimumFractionDigits: 2 }));
  const scopeText = (d) => d.target_scope === 'all'
    ? 'All services'
    : (d.service_ids || []).length + ' service' + ((d.service_ids || []).length === 1 ? '' : 's');

  async function loadDiscounts() {
    const wrap = document.getElementById('discList');
    const count = document.getElementById('discCount');
    try {
      const res = await api('discounts.php?action=list');
      SERVICES = res.services || [];
      const rows = res.discounts || [];
      const active = rows.filter((d) => d.is_active).length;
      count.textContent = rows.length ? active + ' active · ' + rows.length + ' total' : '';
      const nameById = Object.fromEntries(SERVICES.map((s) => [s.service_id, s.service_name]));
      wrap.innerHTML = rows.length
        ? '<div class="overflow-x-auto"><table class="' + TABLE + '"><thead><tr>' +
            '<th>Promotion</th><th>Type</th><th>Value</th><th>Scope</th><th>Status</th><th></th>' +
          '</tr></thead><tbody>' +
          rows.map((d) => {
            const svcTitle = d.target_scope === 'specific'
              ? ' title="' + esc((d.service_ids || []).map((id) => nameById[id] || ('#' + id)).join(', ')) + '"'
              : '';
            return '<tr>' +
              '<td class="font-medium text-navy-800 dark:text-steel-50">' + esc(d.discount_name) + '</td>' +
              '<td class="capitalize dark:text-steel-100">' + esc(d.discount_type) + '</td>' +
              '<td class="whitespace-nowrap dark:text-steel-100">' + esc(discValue(d)) + '</td>' +
              '<td class="whitespace-nowrap dark:text-steel-100"' + svcTitle + '>' + esc(scopeText(d)) + '</td>' +
              '<td><button class="' + (d.is_active
                  ? 'inline-block rounded-full bg-ok-soft px-2.5 py-[3px] text-[11px] font-bold text-ok dark:bg-ok/20'
                  : 'inline-block rounded-full bg-steel-100 px-2.5 py-[3px] text-[11px] font-bold text-steel-500 dark:bg-white/10 dark:text-steel-300') +
                '" data-toggle="' + d.id + '">' + (d.is_active ? 'Active' : 'Inactive') + '</button></td>' +
              '<td class="text-right"><button class="' + BTN_SM_GHOST + '" data-edit="' + d.id + '">Edit</button></td>' +
              '</tr>';
          }).join('') +
          '</tbody></table></div>'
        : '<p class="' + EMPTY + '">No promotions yet. Click <strong>+ New Discount</strong> to add one.</p>';
      wrap._rows = rows;
    } catch (e) { count.textContent = ''; wrap.innerHTML = '<p class="' + EMPTY + '">' + esc(e.message) + '</p>'; }
  }

  function discModal(d) {
    d = d || { discount_type: 'percentage', target_scope: 'all', is_active: 1, service_ids: [] };
    const sel = new Set(d.service_ids || []);
    const body =
      '<div class="' + FG + '"><label class="' + LBL + '">Promotion name</label>' +
        '<input class="' + INP + '" id="d_name" value="' + esc(d.discount_name || '') + '" placeholder="e.g. Back to School Promo"></div>' +
      '<div class="mt-3 grid grid-cols-2 gap-3.5">' +
        '<div class="' + FG + '"><label class="' + LBL + '">Discount type</label>' +
          '<div class="flex gap-4 pt-1">' +
          '<label class="' + CHECK + '"><input type="radio" name="d_type" value="percentage"' + (d.discount_type !== 'fixed' ? ' checked' : '') + '> Percentage</label>' +
          '<label class="' + CHECK + '"><input type="radio" name="d_type" value="fixed"' + (d.discount_type === 'fixed' ? ' checked' : '') + '> Fixed ₱</label>' +
          '</div></div>' +
        '<div class="' + FG + '"><label class="' + LBL + '" id="d_vallbl">Value</label>' +
          '<input class="' + INP + '" id="d_value" type="text" inputmode="decimal" data-money="nopad" value="' + (d.discount_value ? window.moneyFormat(String(+d.discount_value)) : '') + '"></div>' +
      '</div>' +
      '<div class="' + FG + ' mt-3"><label class="' + LBL + '">Applies to</label>' +
        '<div class="flex gap-4 pt-1">' +
        '<label class="' + CHECK + '"><input type="radio" name="d_scope" value="all"' + (d.target_scope !== 'specific' ? ' checked' : '') + '> All services</label>' +
        '<label class="' + CHECK + '"><input type="radio" name="d_scope" value="specific"' + (d.target_scope === 'specific' ? ' checked' : '') + '> Specific services</label>' +
        '</div></div>' +
      '<div id="d_svcwrap" class="mt-2 max-h-[220px] overflow-y-auto rounded-md border border-steel-100 p-2 dark:border-white/10" style="' + (d.target_scope === 'specific' ? '' : 'display:none') + '">' +
        (SERVICES.length ? SERVICES.map((s) =>
          '<label class="' + CHECK + ' rounded-md px-1.5 py-1 hover:bg-steel-50 dark:hover:bg-white/5"><input type="checkbox" class="d_svc" value="' + s.service_id + '"' + (sel.has(s.service_id) ? ' checked' : '') + '> ' +
          esc(s.service_name) + ' <span class="text-steel-400 dark:text-steel-500">₱' + Number(s.base_price).toLocaleString('en-PH') + '</span></label>').join('')
          : '<p class="text-steel-400 dark:text-steel-500 text-[13px]">No active services.</p>') +
      '</div>' +
      '<label class="' + CHECK + ' mt-3"><input type="checkbox" id="d_active"' + (d.is_active ? ' checked' : '') + '> Active (available to the cashier now)</label>';
    openModal(modalShell((d.id ? 'Edit' : 'New') + ' Promotion', body,
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button>' +
      '<button class="' + BTN + PRIMARY + '" id="d_save">Save Promotion</button>'), { wide: true });

    const root = document.getElementById('modalRoot');
    const setValLbl = () => {
      const t = root.querySelector('input[name="d_type"]:checked').value;
      document.getElementById('d_vallbl').textContent = t === 'percentage' ? 'Value (% off, 1-100)' : 'Value (₱ off)';
    };
    const setScope = () => {
      const sc = root.querySelector('input[name="d_scope"]:checked').value;
      document.getElementById('d_svcwrap').style.display = sc === 'specific' ? '' : 'none';
    };
    root.querySelectorAll('input[name="d_type"]').forEach((r) => r.addEventListener('change', setValLbl));
    root.querySelectorAll('input[name="d_scope"]').forEach((r) => r.addEventListener('change', setScope));
    setValLbl();

    document.getElementById('d_save').addEventListener('click', async () => {
      const payload = {
        id: d.id || 0,
        discount_name: document.getElementById('d_name').value.trim(),
        discount_type: root.querySelector('input[name="d_type"]:checked').value,
        discount_value: window.moneyVal(document.getElementById('d_value')),
        target_scope: root.querySelector('input[name="d_scope"]:checked').value,
        applied_service_ids: [...root.querySelectorAll('.d_svc:checked')].map((c) => +c.value),
        is_active: document.getElementById('d_active').checked,
      };
      const btn = document.getElementById('d_save');
      btn.disabled = true; btn.textContent = 'Saving…';
      try {
        await api('discounts.php?action=save', { method: 'POST', body: payload });
        closeModal(); toast('Promotion saved.'); loadDiscounts();
      } catch (e) { btn.disabled = false; btn.textContent = 'Save Promotion'; toast(e.message, 'err'); }
    });
  }

  document.getElementById('newDiscBtn').addEventListener('click', () => discModal(null));
  document.getElementById('discList').addEventListener('click', async (e) => {
    const ed = e.target.closest('[data-edit]');
    const tg = e.target.closest('[data-toggle]');
    const rows = document.getElementById('discList')._rows || [];
    if (ed) { const d = rows.find((x) => String(x.id) === ed.dataset.edit); if (d) discModal(d); return; }
    if (tg) {
      try { await api('discounts.php?action=toggle', { method: 'POST', body: { id: +tg.dataset.toggle } }); loadDiscounts(); }
      catch (err) { toast(err.message, 'err'); }
    }
  });

  /* ---------------------------------------------------------------- Clinic time slots */
  const CHIP = 'inline-flex items-center gap-1 rounded-full bg-info-soft py-[5px] pl-3 pr-1.5 text-xs font-bold text-info dark:bg-info/20 [&_button]:cursor-pointer [&_button]:border-0 [&_button]:bg-transparent [&_button]:px-[3px] [&_button]:text-[15px] [&_button]:leading-none [&_button]:opacity-60 hover:[&_button]:opacity-100';

  async function loadSlots() {
    const wrap = document.getElementById('slotList');
    try {
      const { slots } = await api('appointments.php?action=slot_list');
      wrap.innerHTML = slots.length
        ? slots.map((s) => '<span class="' + CHIP + '">' + fmtTime(s) +
            '<button type="button" data-del="' + esc(s) + '" aria-label="Remove ' + esc(fmtTime(s)) + '">&times;</button></span>').join('')
        : '<span class="text-steel-400 dark:text-steel-500">No time slots configured.</span>';
    } catch (e) { wrap.innerHTML = '<span class="text-steel-400 dark:text-steel-500">' + esc(e.message) + '</span>'; }
  }

  document.getElementById('slotAdd').addEventListener('click', async () => {
    const v = document.getElementById('slotInput').value;
    if (!/^\d{2}:\d{2}$/.test(v)) return toast('Pick a time first.', 'err');
    try {
      await api('settings.php?action=add_slot', { method: 'POST', body: { slot: v } });
      document.getElementById('slotInput').value = '';
      toast('Time slot added.'); loadSlots();
    } catch (e) { toast(e.message, 'err'); }
  });

  document.getElementById('slotList').addEventListener('click', async (e) => {
    const b = e.target.closest('[data-del]');
    if (!b) return;
    try {
      await api('settings.php?action=remove_slot', { method: 'POST', body: { slot: b.dataset.del } });
      toast('Time slot removed.'); loadSlots();
    } catch (err) { toast(err.message, 'err'); }
  });

  /* ---------- GCash + bank transfer QR codes ---------- */
  const QR_LABEL = { gcash: 'GCash', bank: 'Bank Transfer' };
  function wireQr(method, prefix) {
    const box = document.getElementById(prefix + 'Preview');
    const rm = document.getElementById(prefix + 'Remove');
    const show = (url) => {
      box.innerHTML = url
        ? '<img src="' + esc(url) + '" alt="Current ' + QR_LABEL[method] + ' QR code" class="h-full w-full object-contain">'
        : '<span class="px-3">No QR code uploaded</span>';
      rm.classList.toggle('hidden', !url);
    };
    document.getElementById(prefix + 'File').addEventListener('change', async (e) => {
      const file = e.target.files[0];
      e.target.value = '';
      if (!file) return;
      if (file.size > 3 * 1024 * 1024) return toast('Image must be 3 MB or smaller.', 'err');
      const fd = new FormData();
      fd.append('qr', file);
      try {
        const r = await fetch('/dgt_dental_clinic/api/settings.php?action=upload_payment_qr&method=' + method, {
          method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: fd,
        });
        const d = await r.json();
        if (!r.ok || d.ok === false) throw new Error(d.error || 'Upload failed.');
        show(d.url);
        toast(QR_LABEL[method] + ' QR code updated.');
      } catch (err) { toast(err.message, 'err'); }
    });
    rm.addEventListener('click', () => {
      openModal(modalShell('Remove QR Code',
        '<p>Patients will no longer see a QR code on the ' + QR_LABEL[method] + ' payment step. They can still pay using the account details.</p>',
        '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + DANGER + '" id="qrRmGo">Remove</button>'));
      document.getElementById('qrRmGo').addEventListener('click', async () => {
        try {
          await api('settings.php?action=clear_payment_qr', { method: 'POST', body: { method } });
          closeModal(); show(null); toast('QR code removed.');
        } catch (err) { toast(err.message, 'err'); }
      });
    });
    return { show, fail: (msg) => { box.innerHTML = '<span class="px-3">' + esc(msg) + '</span>'; } };
  }

  /* ---------- More payment methods (migration 078) ---------- */
  const PM_INP = 'w-full rounded-md border border-steel-200 bg-white px-2.5 py-2 text-[13px] text-navy-800 outline-none focus:border-accent dark:border-white/15 dark:bg-navy-900 dark:text-steel-50';
  const PM_LBL = 'text-xs font-semibold text-steel-500 dark:text-steel-300';
  const PM_BTN_SM = 'inline-flex cursor-pointer items-center justify-center rounded-md px-2.5 py-1.5 text-xs font-semibold whitespace-nowrap';
  let pmRows = [];
  async function loadPm() {
    const wrap = document.getElementById('pmList');
    if (!wrap) return;
    try {
      pmRows = (await api('settings.php?action=payment_methods')).methods || [];
      wrap.innerHTML = pmRows.length ? '<div class="flex flex-col gap-2.5">' + pmRows.map((m) =>
        '<div class="flex flex-wrap items-center gap-3 rounded-lg border border-steel-100 p-3 dark:border-white/10">' +
          '<div class="grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-md border border-steel-200 bg-white text-[10px] text-steel-400 dark:border-white/15">' +
            (m.qr ? '<img src="' + esc(m.qr) + '" alt="" class="h-full w-full object-contain">' : 'No QR') + '</div>' +
          '<div class="min-w-0 flex-1">' +
            '<div class="font-semibold text-navy-800 dark:text-steel-50">' + esc(m.name) + '</div>' +
            '<div class="text-[12px] text-steel-500 dark:text-steel-400">' + esc([m.account_name, m.account_number].filter(Boolean).join(' · ') || 'No account details') + '</div>' +
          '</div>' +
          '<button class="' + PM_BTN_SM + (+m.is_active ? ' bg-ok-soft text-ok dark:bg-ok/20' : ' bg-steel-100 text-steel-500 dark:bg-white/10 dark:text-steel-300') + '" data-pm-toggle="' + m.method_id + '" title="Click to turn ' + (+m.is_active ? 'off' : 'on') + '">' + (+m.is_active ? 'Shown to patients' : 'Hidden') + '</button>' +
          '<button class="' + PM_BTN_SM + GHOST + '" data-pm-edit="' + m.method_id + '">Edit</button>' +
          '<button class="' + PM_BTN_SM + DANGER + '" data-pm-del="' + m.method_id + '">Delete</button>' +
        '</div>').join('') + '</div>'
        : '<p class="' + EMPTY + '">No extra payment methods yet.</p>';
    } catch (e) { wrap.innerHTML = '<p class="' + EMPTY + '">' + esc(e.message) + '</p>'; }
  }
  function pmModal(m) {
    m = m || {};
    openModal(modalShell(m.method_id ? 'Edit Payment Method' : 'Add Payment Method',
      '<div class="flex flex-col gap-3">' +
        '<label class="flex flex-col gap-1.5"><span class="' + PM_LBL + '">Name <span class="text-danger">*</span></span>' +
          '<input id="pm_name" maxlength="60" class="' + PM_INP + '" placeholder="e.g. Maya, BDO" value="' + esc(m.name || '') + '"></label>' +
        '<label class="flex flex-col gap-1.5"><span class="' + PM_LBL + '">Account Name</span>' +
          '<input id="pm_acn" maxlength="100" class="' + PM_INP + '" value="' + esc(m.account_name || '') + '"></label>' +
        '<label class="flex flex-col gap-1.5"><span class="' + PM_LBL + '">Account Number</span>' +
          '<input id="pm_acno" maxlength="60" class="' + PM_INP + '" value="' + esc(m.account_number || '') + '"></label>' +
        '<div class="flex flex-col gap-1.5"><span class="' + PM_LBL + '">QR Code</span>' +
          (m.qr ? '<div class="flex items-center gap-3"><img src="' + esc(m.qr) + '" alt="" class="h-20 w-20 rounded-md border border-steel-200 object-contain dark:border-white/15">' +
            '<label class="inline-flex items-center gap-2 text-[13px] text-steel-500 dark:text-steel-300"><input type="checkbox" id="pm_qrRm"> Remove this QR</label></div>' : '') +
          '<input type="file" id="pm_qr" accept="image/jpeg,image/png,image/webp" class="' + PM_INP + '">' +
          '<span class="text-[11px] text-steel-400 dark:text-steel-500">JPG, PNG or WEBP, up to 3 MB. Patients scan it on the payment step.</span></div>' +
      '</div>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + ' bg-accent text-white hover:bg-accent-dark" id="pm_go">Save</button>'));
    document.getElementById('pm_go').addEventListener('click', async () => {
      const name = document.getElementById('pm_name').value.trim();
      if (!name) return toast('Enter a name for the payment method.', 'err');
      const file = document.getElementById('pm_qr').files[0];
      if (file && file.size > 3 * 1024 * 1024) return toast('Image must be 3 MB or smaller.', 'err');
      const btn = document.getElementById('pm_go');
      btn.disabled = true;
      try {
        const r = await api('settings.php?action=save_payment_method', { method: 'POST', body: {
          method_id: m.method_id || 0, name,
          account_name: document.getElementById('pm_acn').value.trim(),
          account_number: document.getElementById('pm_acno').value.trim(),
        }});
        const key = 'm' + r.method_id;
        const rmBox = document.getElementById('pm_qrRm');
        if (rmBox && rmBox.checked && !file) {
          await api('settings.php?action=clear_payment_qr', { method: 'POST', body: { method: key } });
        }
        if (file) {
          const fd = new FormData();
          fd.append('qr', file);
          const res = await fetch('/dgt_dental_clinic/api/settings.php?action=upload_payment_qr&method=' + key, {
            method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: fd,
          });
          const d = await res.json();
          if (!res.ok || d.ok === false) throw new Error(d.error || 'The QR upload failed.');
        }
        closeModal(); toast('Payment method saved.'); loadPm();
      } catch (e) { btn.disabled = false; toast(e.message, 'err'); }
    });
  }
  document.getElementById('pmAdd').addEventListener('click', () => pmModal());
  document.getElementById('pmList').addEventListener('click', async (e) => {
    const t = e.target.closest('[data-pm-toggle]'), ed = e.target.closest('[data-pm-edit]'), del = e.target.closest('[data-pm-del]');
    if (ed) return pmModal(pmRows.find((m) => +m.method_id === +ed.dataset.pmEdit));
    if (t) {
      try { await api('settings.php?action=toggle_payment_method', { method: 'POST', body: { method_id: +t.dataset.pmToggle } }); loadPm(); }
      catch (err) { toast(err.message, 'err'); }
      return;
    }
    if (del) {
      const m = pmRows.find((x) => +x.method_id === +del.dataset.pmDel) || {};
      openModal(modalShell('Delete Payment Method',
        '<p>Delete <strong>' + esc(m.name || '') + '</strong>? Patients will no longer see it. A method already used for a booking can only be turned off.</p>',
        '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + DANGER + '" id="pm_delGo">Delete</button>'));
      document.getElementById('pm_delGo').addEventListener('click', async () => {
        try { await api('settings.php?action=delete_payment_method', { method: 'POST', body: { method_id: m.method_id } }); closeModal(); toast('Payment method deleted.'); loadPm(); }
        catch (err) { toast(err.message, 'err'); }
      });
    }
  });
  loadPm();
  const gcashQr = wireQr('gcash', 'qr');
  const bankQr = wireQr('bank', 'bqr');
  async function loadQr() {
    try {
      const p = (await api('settings.php?action=payment_info')).payment;
      gcashQr.show(p.gcash_qr); bankQr.show(p.bank_qr);
    } catch (e) { gcashQr.fail(e.message); bankQr.fail(e.message); }
  }
  const gNum = document.getElementById('g_num');
  if (window.wirePhoneField) window.wirePhoneField(gNum, { errEl: document.getElementById('g_numErr') });
  wireDirty('saveFees', ['s_fee']);
  wireDirty('saveGcash', ['g_name', 'g_num']);
  wireDirty('saveBank', ['b_bank', 'b_name', 'b_acct']);

  document.getElementById('saveBank').addEventListener('click', async () => {
    const v = (id) => document.getElementById(id).value.replace(/[\r\n]+/g, ' ').trim();
    const lines = [v('b_bank'), v('b_name'), v('b_acct')];
    if (lines.some((x) => !x)) return toast('Fill in the bank name, account name and account number.', 'err');
    try {
      await api('settings.php?action=save_payment', { method: 'POST', body: { pay_bank_details: lines.join('\n') } });
      toast('Bank details saved.'); markSaved('saveBank');
    } catch (e) { toast(e.message, 'err'); }
  });

  document.getElementById('saveGcash').addEventListener('click', async () => {
    if (gNum._phoneOk && !gNum._phoneOk(true)) return toast('Enter a valid 11-digit mobile number (09XXXXXXXXX).', 'err');
    try {
      await api('settings.php?action=save_payment', { method: 'POST', body: {
        pay_gcash_name: document.getElementById('g_name').value.trim(),
        pay_gcash_number: gNum.value.trim(),
      }});
      toast('GCash details saved.'); markSaved('saveGcash');
    } catch (e) { toast(e.message, 'err'); }
  });

  /* ---------- Legal & Policy ---------- */
  let legalDocs = {};
  let curDoc = 'terms';
  const legalText = document.getElementById('legalText');
  const legalStatus = document.getElementById('legalStatus');
  const legalTabs = [...document.querySelectorAll('[data-doc]')];
  const docTitle = (k) => (k === 'privacy' ? 'Privacy Policy' : 'Terms & Conditions');
  function showDoc(k) {
    curDoc = k;
    legalTabs.forEach((t) => { const on = t.dataset.doc === k; t.classList.toggle('active', on); t.setAttribute('aria-selected', on ? 'true' : 'false'); });
    const d = legalDocs[k];
    if (!d) return;
    legalText.value = d.body;
    legalText.dataset.saved = d.body;
    document.getElementById('viewLegal').href = '/dgt_dental_clinic/' + (k === 'privacy' ? 'privacy' : 'terms');
    legalStatus.textContent = (d.custom ? '' : 'Default text · ') + (d.updated_at ? 'Last updated ' + fmtDate(String(d.updated_at).slice(0, 10)) : '');
  }
  async function loadLegal() {
    try { legalDocs = (await api('settings.php?action=legal')).docs; showDoc(curDoc); }
    catch (e) { legalStatus.textContent = e.message; }
  }
  legalTabs.forEach((t) => t.addEventListener('click', () => {
    const k = t.dataset.doc;
    if (k === curDoc) return;
    if (legalText.value === legalText.dataset.saved) return showDoc(k);
    openModal(modalShell('Unsaved Changes',
      '<p>You have unsaved changes to the ' + esc(docTitle(curDoc)) + '. Switch and discard them?</p>',
      '<button class="' + BTN + GHOST + '" data-close>Keep Editing</button><button class="' + BTN + DANGER + '" id="legalDiscard">Discard</button>'));
    document.getElementById('legalDiscard').addEventListener('click', () => { closeModal(); showDoc(k); });
  }));
  document.getElementById('saveLegal').addEventListener('click', async () => {
    if (!legalText.value.trim()) return toast('The text cannot be empty.', 'err');
    try {
      const { doc } = await api('settings.php?action=save_legal', { method: 'POST', body: { doc: curDoc, body: legalText.value } });
      legalDocs[curDoc] = Object.assign(legalDocs[curDoc] || {}, doc);
      showDoc(curDoc);
      toast(docTitle(curDoc) + ' saved.');
    } catch (e) { toast(e.message, 'err'); }
  });
  document.getElementById('resetLegal').addEventListener('click', () => {
    const title = docTitle(curDoc);
    openModal(modalShell('Reset to Default',
      '<p>Replace the current ' + esc(title) + ' with the original default text? Your edits will be lost.</p>',
      '<button class="' + BTN + GHOST + '" data-close>Cancel</button><button class="' + BTN + DANGER + '" id="legalRstGo">Reset</button>'));
    document.getElementById('legalRstGo').addEventListener('click', async () => {
      try {
        const { doc } = await api('settings.php?action=reset_legal', { method: 'POST', body: { doc: curDoc } });
        legalDocs[curDoc] = Object.assign(legalDocs[curDoc] || {}, doc);
        closeModal(); showDoc(curDoc); toast(title + ' reset to the default text.');
      } catch (err) { toast(err.message, 'err'); }
    });
  });

  loadBlocked();
  loadDiscounts();
  loadSlots();
  loadQr();
  loadLegal();
})();
JS;
require __DIR__ . '/../../includes/layout.php';
