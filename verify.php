<?php
/**
 * E-mail-address verification page.
 *
 * verify.php?email=<prefill>
 *   - Shows a form for the 6-digit OTP e-mailed by api/request_verification.php
 *     (also sent right after registering). Submits to api/verify_otp.php via
 *     fetch; on success swaps in the same "E-mail verified" card the old
 *     link-based flow used to land on.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo_meta.php';

$prefillEmail = trim((string) ($_GET['email'] ?? ''));
$emailKnown   = $prefillEmail !== '' && filter_var($prefillEmail, FILTER_VALIDATE_EMAIL);

// Set by login.php right before it redirects here (after registering, or a
// sign-in attempt on a not-yet-verified account). One-shot.
$flash = $_SESSION['verify_flash'] ?? null;
unset($_SESSION['verify_flash']);
$flashUser = is_array($flash) ? (string) ($flash['username'] ?? '') : '';
$flashDev  = is_array($flash) ? ($flash['dev_otp'] ?? null) : null;
$flashFrom = is_array($flash) ? (string) ($flash['from'] ?? '') : '';
$flashMailFailed = is_array($flash) && !empty($flash['mail_failed']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Your Email &middot; DGT One Dental Center - Bulacan</title>
<?php seoMeta('Verify Your Email | DGT One Dental Center - Bulacan', 'Enter the 6-digit code sent to your email to finish creating your DGT One Dental Center - Bulacan account.', 'verify', false); ?>
<link rel="icon" type="image/x-icon" href="<?= e(asset('assets/img/favicon/favicon.ico')) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('assets/img/favicon/favicon-32x32.png')) ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= e(asset('assets/img/favicon/favicon-16x16.png')) ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('assets/img/favicon/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(asset('assets/img/favicon/site.webmanifest')) ?>">
<meta name="theme-color" content="#102f51">
<script>
(function () {
    try {
        var stored = localStorage.getItem('dgt_theme');
        var dark = stored === 'dark' || (stored !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches);
        if (dark) document.documentElement.classList.add('dark');
    } catch (e) {}
})();
</script>
<?php require __DIR__ . '/includes/tailwind.php'; ?>
<?php require __DIR__ . '/includes/auth_style.php'; ?>
<style>
    #otpBoxes .otp-box {
        background: #f1f4fa;
        transition: transform .18s cubic-bezier(.34,1.56,.64,1), background-color .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    html.dark #otpBoxes .otp-box { background: rgba(255,255,255,.04); }
    #otpBoxes .otp-box.otp-filled { background: #fff; box-shadow: 0 2px 8px rgba(27,42,74,.10); }
    html.dark #otpBoxes .otp-box.otp-filled { background: rgba(255,255,255,.08); }
    #otpBoxes.otp-error .otp-box { border-color: #e5484d !important; box-shadow: 0 0 0 4px rgba(229,72,77,.15) !important; }
    @keyframes otp-pop { 0% { transform: scale(1); } 45% { transform: scale(1.16) translateY(-2px); } 100% { transform: scale(1); } }
    @keyframes otp-shake { 10%, 90% { transform: translateX(-1px); } 20%, 80% { transform: translateX(2px); } 30%, 50%, 70% { transform: translateX(-5px); } 40%, 60% { transform: translateX(5px); } }
    @keyframes otp-loading { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: .45; transform: scale(.92); } }
    @keyframes otp-success {
        0%   { transform: scale(1);     border-color: #d6dbe6; box-shadow: 0 0 0 0 rgba(31,157,99,0); }
        35%  { transform: scale(1.2);   border-color: #1f9d63; box-shadow: 0 0 0 6px rgba(31,157,99,.32); }
        70%  { transform: scale(.92);   border-color: #1f9d63; box-shadow: 0 0 0 2px rgba(31,157,99,.16); }
        100% { transform: scale(1);     border-color: #d6dbe6; box-shadow: 0 0 0 0 rgba(31,157,99,0); }
    }
    .otp-box.anim-pop { animation: otp-pop .32s cubic-bezier(.34,1.56,.64,1); }
    .otp-box.anim-shake { animation: otp-shake .4s ease; }
    .otp-box.anim-success { animation: otp-success .42s cubic-bezier(.34,1.56,.64,1); }
    .otp-box.anim-loading { animation: otp-loading 1s ease-in-out infinite; }
    @media (prefers-reduced-motion: reduce) {
        #otpBoxes .otp-box { transition: none; }
        .otp-box.anim-pop, .otp-box.anim-shake, .otp-box.anim-success, .otp-box.anim-loading { animation: none; }
    }
</style>
</head>
<?php
$fieldCls = AUTH_INPUT;
$btnCls   = AUTH_BTN;
?>
<body class="<?= AUTH_BODY ?>">
    <?= AUTH_SQUARES ?>
    <div class="<?= AUTH_CARD ?> text-center">
        <div class="mb-7 flex justify-center">
            <img src="<?= e(asset('assets/img/pic_login-sm.webp')) ?>" alt="DGT One Dental Center"
                 class="h-16 w-auto max-w-[260px] object-contain">
        </div>

        <div id="verifyForm">
            <h1 class="mb-2 text-xl font-bold text-navy-900 dark:text-steel-50">Verify Your Email</h1>
            <?php if ($emailKnown): ?>
                <?php if ($flashMailFailed): ?>
                    <p class="mb-3 rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] leading-relaxed text-danger dark:bg-danger/20">
                        <?= $flashFrom === 'login' ? 'Your account is not verified yet. ' : '' ?>We couldn't send the code to your e-mail right now. Please tap "Resend it" below in a moment.
                    </p>
                <?php elseif ($flashFrom === 'login'): ?>
                    <p class="mb-3 rounded-lg bg-[#fff6e5] px-3 py-2 text-[12.5px] leading-relaxed text-[#8a5a00] dark:bg-warn/20 dark:text-steel-100">
                        Your account is not verified yet. We just sent you a new code.
                    </p>
                <?php endif; ?>
                <p class="mb-5 text-[13px] leading-relaxed text-steel-500 dark:text-steel-300">
                    <?= $flashMailFailed ? 'The code will be sent to' : 'Enter the 6-digit code we sent to' ?>
                    <strong class="font-semibold text-navy-900 dark:text-steel-50"><?= e($prefillEmail) ?></strong>.
                </p>
                <?php if ($flashUser !== ''): ?>
                    <p class="-mt-2 mb-5 text-[12.5px] text-steel-500 dark:text-steel-300">
                        Your username:
                        <span class="rounded bg-steel-100 px-1.5 py-0.5 font-mono font-semibold text-navy-900 dark:bg-white/10 dark:text-steel-50"><?= e($flashUser) ?></span>
                        <span class="block">Use this to sign in, not your e-mail address.</span>
                    </p>
                <?php endif; ?>
                <?php if ($flashDev): ?>
                    <p class="-mt-2 mb-5 text-[12.5px] text-steel-500">(Dev code: <?= e((string) $flashDev) ?>)</p>
                <?php endif; ?>
            <?php else: ?>
                <p class="mb-5 text-[13px] leading-relaxed text-steel-500 dark:text-steel-300">
                    Enter your e-mail and the 6-digit code we sent you to activate your account.
                </p>
            <?php endif; ?>
            <form id="otpForm" class="flex flex-col gap-3 text-left" autocomplete="off">
                <?php if ($emailKnown): ?>
                    <input type="hidden" name="email" id="vEmail" value="<?= e($prefillEmail) ?>">
                <?php else: ?>
                    <input type="email" name="email" id="vEmail" placeholder="Email" required
                           value="<?= e($prefillEmail) ?>" class="<?= $fieldCls ?>">
                <?php endif; ?>
                <div class="flex justify-center gap-2" role="group" aria-label="6-digit verification code" id="otpBoxes">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                        <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" placeholder="0"
                               autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
                               class="otp-box h-14 w-12 rounded-xl border border-[#d6dbe6] text-center text-2xl font-mono font-bold text-[#1b2a4a] outline-none placeholder:text-[#b9c0cf] focus:border-[#4361ee] focus:ring-4 focus:ring-[#4361ee]/15 dark:border-white/15 dark:text-steel-50 dark:placeholder:text-white/20">
                    <?php endfor; ?>
                </div>
                <p class="hidden text-[12.5px] text-danger" id="vErr"></p>
                <button type="submit" class="<?= $btnCls ?>" id="vBtn">Verify</button>
            </form>
            <p class="mt-3 text-center text-[12.5px] text-steel-500 dark:text-steel-300">
                Didn't get a code?
                <button type="button" id="resendBtn" class="font-semibold text-[#3a52d6] hover:underline dark:text-steel-100">Resend it</button>
            </p>
            <p class="hidden mt-2 text-center text-[12.5px]" id="resendMsg"></p>
        </div>

        <div class="hidden" id="verifySuccess">
            <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-full bg-[#e2f4ec] text-ok dark:bg-ok/20">
                <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            </div>
            <h1 class="mb-2 text-xl font-bold text-navy-900 dark:text-steel-50">E-mail verified</h1>
            <p class="mb-5 text-[13px] leading-relaxed text-steel-500 dark:text-steel-300">
                Your e-mail address is confirmed. You can now sign in and book appointments.
            </p>
            <a href="<?= e(asset('login')) ?>" class="<?= AUTH_BTN ?>">Sign In</a>
        </div>
    </div>

    <script>
        (function () {
            var formWrap    = document.getElementById('verifyForm');
            var successWrap = document.getElementById('verifySuccess');
            var form        = document.getElementById('otpForm');
            var emailInput  = document.getElementById('vEmail');
            var boxes       = Array.prototype.slice.call(document.querySelectorAll('.otp-box'));
            var err         = document.getElementById('vErr');
            var btn         = document.getElementById('vBtn');
            var resendBtn   = document.getElementById('resendBtn');
            var resendMsg   = document.getElementById('resendMsg');
            var otpGroup    = document.getElementById('otpBoxes');
            var reduceMotion = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

            function otpValue() {
                return boxes.map(function (b) { return b.value; }).join('');
            }
            function focusFirstEmpty() {
                var target = boxes.filter(function (b) { return !b.value; })[0] || boxes[boxes.length - 1];
                target.focus();
            }
            function clearBoxes() {
                boxes.forEach(function (b) { b.value = ''; b.classList.remove('otp-filled'); });
                boxes[0].focus();
            }
            function pop(box) {
                if (reduceMotion) return;
                box.classList.remove('anim-pop');
                void box.offsetWidth;
                box.classList.add('anim-pop');
            }
            function startLoading() {
                boxes.forEach(function (b) { b.readOnly = true; });
                if (reduceMotion) return;
                // A short grace period before the pulse kicks in — the box that was
                // *just* typed into is still mid-way through its own "pop" (see
                // pop() above); starting the pulse in the same tick would instantly
                // cut that pop off, so the last keystroke never visibly registers.
                setTimeout(function () {
                    boxes.forEach(function (b, idx) {
                        b.style.animationDelay = (idx * 0.1) + 's';
                        b.classList.add('anim-loading');
                    });
                }, 180);
            }
            function stopLoading() {
                boxes.forEach(function (b) {
                    b.readOnly = false;
                    b.classList.remove('anim-loading');
                    b.style.animationDelay = '';
                });
            }
            function trySubmit() {
                if (otpValue().length === boxes.length) {
                    form.requestSubmit ? form.requestSubmit() : btn.click();
                }
            }
            function flashError(msg) {
                stopLoading();
                err.textContent = msg;
                err.classList.remove('hidden');
                btn.disabled = false;
                btn.textContent = 'Verify';
                if (reduceMotion) { clearBoxes(); return; }
                otpGroup.classList.add('otp-error');
                boxes.forEach(function (b, idx) {
                    setTimeout(function () {
                        b.classList.remove('anim-shake');
                        void b.offsetWidth;
                        b.classList.add('anim-shake');
                    }, idx * 25);
                });
                setTimeout(function () {
                    otpGroup.classList.remove('otp-error');
                    clearBoxes();
                }, 450);
            }

            boxes.forEach(function (box, i) {
                box.addEventListener('input', function () {
                    if (takePaste(box)) return;
                    box.value = box.value.replace(/\D/g, '').slice(-1);
                    box.classList.toggle('otp-filled', !!box.value);
                    if (box.value) pop(box);
                    if (box.value && i < boxes.length - 1) {
                        boxes[i + 1].focus();
                    }
                    trySubmit();
                });
                box.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace' && !box.value && i > 0) {
                        boxes[i - 1].focus();
                    } else if (e.key === 'ArrowLeft' && i > 0) {
                        boxes[i - 1].focus();
                    } else if (e.key === 'ArrowRight' && i < boxes.length - 1) {
                        boxes[i + 1].focus();
                    }
                });
                // The paste itself is not blocked (no preventDefault: Lighthouse flags that
                // as "prevents pasting"). The pasted code is spread over the boxes on the
                // input event that follows, or on the next tick when nothing was inserted.
                box.addEventListener('paste', function (e) {
                    box._paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, boxes.length);
                    setTimeout(function () { takePaste(box); }, 0);
                });
                function takePaste(b) {
                    if (b._paste === undefined) return false;
                    var digits = b._paste; delete b._paste;
                    if (!digits) { b.value = b.value.replace(/\D/g, '').slice(-1); return true; }
                    digits.split('').forEach(function (d, j) {
                        var target = boxes[j];
                        if (!target) return;
                        target.value = d;
                        target.classList.add('otp-filled');
                        setTimeout(function () { pop(target); }, j * 60);
                    });
                    (boxes[Math.min(digits.length, boxes.length - 1)] || boxes[boxes.length - 1]).focus();
                    setTimeout(trySubmit, digits.length * 60 + 80);
                    return true;
                }
            });

            if (emailInput.value.trim()) {
                boxes[0].focus();
            } else {
                emailInput.focus();
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var otp = otpValue();
                if (otp.length !== boxes.length) {
                    err.textContent = 'Please enter all 6 digits.';
                    err.classList.remove('hidden');
                    focusFirstEmpty();
                    return;
                }
                err.classList.add('hidden');
                btn.disabled = true;
                btn.textContent = 'Verifying...';
                startLoading();

                // A local/dev request can resolve in a few ms, which makes the loading
                // pulse flash by unnoticed. Hold the loading state open for at least
                // MIN_LOADING_MS so the user always sees it check before it reveals
                // whether the code was right or wrong.
                var MIN_LOADING_MS = reduceMotion ? 0 : 650;
                var requestStartedAt = Date.now();
                function afterMinLoading(fn) {
                    var remaining = MIN_LOADING_MS - (Date.now() - requestStartedAt);
                    if (remaining > 0) { setTimeout(fn, remaining); } else { fn(); }
                }

                var body = new URLSearchParams({ email: emailInput.value.trim(), otp: otp });
                fetch('/dgt_dental_clinic/api/verify_otp.php', { method: 'POST', body: body })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        afterMinLoading(function () {
                            if (d && d.ok) {
                                stopLoading();
                                // Same browser that registered / signed in: the
                                // API already signed the account in.
                                var finish = (d.signed_in && d.redirect)
                                    ? function () { location.href = d.redirect; }
                                    : function () { formWrap.classList.add('hidden'); successWrap.classList.remove('hidden'); };
                                if (reduceMotion) { finish(); return; }
                                var BOUNCE_STAGGER = 55;
                                var BOUNCE_DURATION = 420;
                                boxes.forEach(function (b, idx) {
                                    setTimeout(function () { b.classList.add('anim-success'); }, idx * BOUNCE_STAGGER);
                                });
                                // Wait for the LAST box's own bounce to finish, not a flat
                                // 420ms — otherwise the sweep gets cut off mid-animation on
                                // every box after the first, and the whole thing reads as an
                                // abrupt jump-cut instead of a full sweep across all six.
                                setTimeout(finish, (boxes.length - 1) * BOUNCE_STAGGER + BOUNCE_DURATION);
                                return;
                            }
                            flashError((d && d.error) ? d.error : 'Something went wrong. Please try again.');
                        });
                    })
                    .catch(function () {
                        afterMinLoading(function () {
                            flashError('Network error. Please try again.');
                        });
                    });
            });

            resendBtn.addEventListener('click', function () {
                var email = emailInput.value.trim();
                if (!email) { emailInput.focus(); return; }
                resendBtn.disabled = true;
                fetch('/dgt_dental_clinic/api/request_verification.php', {
                    method: 'POST', body: new URLSearchParams({ email: email }),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        resendMsg.classList.remove('hidden');
                        if (!d || !d.ok) {
                            resendMsg.className = 'mt-2 text-center text-[12.5px] text-danger';
                            resendMsg.textContent = (d && d.error) ? d.error : 'Could not send a new code. Please try again.';
                            if (d && d.verified) {
                                var a = document.createElement('a');
                                a.href = '/dgt_dental_clinic/login';
                                a.className = 'ml-1 font-semibold underline';
                                a.textContent = 'Sign in';
                                resendMsg.appendChild(a);
                            } else {
                                emailInput.focus();
                            }
                            resendBtn.disabled = false;
                            return;
                        }
                        resendMsg.className = 'mt-2 text-center text-[12.5px] text-ok';
                        resendMsg.textContent = d.message || 'A new verification code has been sent to your e-mail.';
                        if (d && d.dev_otp) {
                            resendMsg.textContent += ' (Dev code: ' + d.dev_otp + ')';
                        }
                        setTimeout(function () { resendBtn.disabled = false; }, 4000);
                    })
                    .catch(function () {
                        resendMsg.classList.remove('hidden');
                        resendMsg.className = 'mt-2 text-center text-[12.5px] text-danger';
                        resendMsg.textContent = 'Network error. Please try again.';
                        resendBtn.disabled = false;
                    });
            });
        })();
    </script>
</body>
</html>
