<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/seo_meta.php';
require_once __DIR__ . '/includes/mailer.php';

/**
 * Combined Sign In / Create Account page — a single sliding two-panel card
 * (the "double slider" pattern) instead of two separate pages. Both forms
 * post back to THIS file (`form=login` / `form=register` hidden fields tell
 * the two branches below apart); a full-page reload re-renders the same
 * merged page, with $activePanel deciding which side is showing so an error
 * or the post-registration success card reloads into view on the correct
 * panel instead of flashing back to Sign In. register.php is now just a
 * redirect stub to `login.php?mode=register` for old links/bookmarks.
 */

// Already signed in -> everyone (patients included) to their own portal home
// (or the forced password-change screen, if this account is still on a temp one).
if (isLoggedIn() && currentUser() !== null) {
    $u = currentUser();
    $dest = !empty($u['must_change_password'])
        ? '/dgt_dental_clinic/modules/shared/profile'
        : roleHome($_SESSION['role']);
    header('Location: ' . $dest);
    exit;
}

$pdo = getDB();

/* ---------------- Sign In state ---------------- */
$loginError      = '';
$unverifiedEmail = '';
$notice = isset($_GET['reset']) ? 'Your password has been updated. Please sign in with your new password.' : '';

/* ---------------- Create Account state ---------------- */
$regError     = '';
$regDone      = false;
$doneEmail    = '';
$doneUsername = '';
$devOtp       = null;
$old = ['first' => '', 'middle' => '', 'last' => '', 'bd_m' => 0, 'bd_d' => 0, 'bd_y' => 0, 'email' => '', 'phone' => ''];

$MONTHS = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
           7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];

$formSubmitted = $_POST['form'] ?? '';

/**
 * Sends a fresh verification code and redirects to verify.php. The session
 * keys tie that page (and api/verify_otp.php's sign-in on a correct code) to
 * THIS browser, so knowing the e-mail address alone is not enough.
 */
function goToVerify(PDO $pdo, int $uid, string $name, string $email, string $username, string $from): void
{
    $v = sendVerificationOtp($pdo, $uid, $name, $email);
    $_SESSION['pending_verify_user_id'] = $uid;
    $_SESSION['pending_verify_email']   = $email;
    $_SESSION['verify_flash'] = [
        'username'    => $username,
        'dev_otp'     => $v['dev_otp'],
        'from'        => $from,
        'mail_failed' => !$v['sent'] && $v['dev_otp'] === null,
    ];
    header('Location: /dgt_dental_clinic/verify?email=' . urlencode($email));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $formSubmitted === 'login') {
    $login    = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if ($login === '' || $password === '') {
        $loginError = 'Please enter your username and password.';
    } else {
        // Username is the only login credential (e-mail login is disabled).
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$login]);
        $user = $stmt->fetch();
        // Brute-force lock (2026-10-23): 5 wrong passwords in 15 minutes.
        $lockLeft = throttleLocked('login', $login);
        $lockMsg  = ' If you forgot your password, use "Forgot your password?".';

        if ($lockLeft > 0) {
            $loginError = 'Too many failed sign-in attempts. ' . throttleWaitText($lockLeft) . $lockMsg;
        } elseif (!$user || !password_verify($password, $user['password'])) {
            $triesLeft = throttleFail('login', $login);
            logActivity('auth', 'Failed sign-in attempt for "' . $login . '"', ['username' => $login]);
            if ($triesLeft <= 0) {
                $loginError = 'Too many failed sign-in attempts. ' . throttleWaitText(THROTTLE_WINDOW_MIN * 60) . $lockMsg;
                logActivity('auth', 'Sign-in locked for 15 minutes after repeated failed attempts for "' . $login . '"',
                    ['username' => $login]);
            } else {
                $loginError = 'Invalid username or password.' . ($triesLeft <= 2
                    ? ' ' . $triesLeft . ' attempt' . ($triesLeft === 1 ? '' : 's') . ' left before sign-in is locked for 15 minutes.'
                    : '');
            }
        } elseif ((int) $user['is_active'] !== 1) {
            $loginError = 'This account has been deactivated. Please contact the clinic.';
            logActivity('auth', 'Sign-in blocked: account deactivated', $user);
        } elseif ($user['role'] === 'patient' && (int) ($user['is_verified'] ?? 1) !== 1) {
            // Not verified yet: the right username + password proves it's the
            // account holder, so send a fresh code and go straight to the OTP
            // screen (which signs them in once the code checks out).
            goToVerify($pdo, (int) $user['user_id'], (string) $user['full_name'], (string) $user['email'],
                       (string) $user['username'], 'login');
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int) $user['user_id'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['username']  = $user['username'];

            // "Remember me" — keep the session cookie for 30 days instead of
            // clearing it when the browser closes.
            if ($remember) {
                $p = session_get_cookie_params();
                setcookie(session_name(), session_id(), [
                    'expires'  => time() + 60 * 60 * 24 * 30,
                    'path'     => $p['path'] ?: '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }

            // Transparently upgrade legacy hashes.
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $upd = $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?');
                $upd->execute([password_hash($password, PASSWORD_DEFAULT), $user['user_id']]);
            }

            throttleClear('login', $login);
            logActivity('auth', 'Signed in', $user);

            // A temp-password account (Add User / Reset Password / walk-in) goes
            // straight to the forced change screen; otherwise patients land on
            // their portal (Appointments page) and staff go to their dashboard.
            // A patient whose profile is still missing details (phone, date of
            // birth, emergency contact, allergies) lands on My Profile first.
            $dest = !empty($user['must_change_password'])
                ? '/dgt_dental_clinic/modules/shared/profile'
                : ($user['role'] === 'patient' ? patientLandingUrl((int) $user['user_id']) : roleHome($user['role']));
            header('Location: ' . $dest);
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $formSubmitted === 'register') {
    $first     = properCase($_POST['first_name'] ?? '');
    $middle    = '';   // no middle name field any more
    $last      = properCase($_POST['last_name'] ?? '');
    $bdM       = (int) ($_POST['bd_month'] ?? 0);
    $bdD       = (int) ($_POST['bd_day'] ?? 0);
    $bdY       = (int) ($_POST['bd_year'] ?? 0);
    $email     = trim($_POST['email'] ?? '');
    $phone     = digitsOnly($_POST['contact_number'] ?? '');
    $password  = $_POST['password'] ?? '';

    $birthdate = ($bdY && $bdM && $bdD) ? sprintf('%04d-%02d-%02d', $bdY, $bdM, $bdD) : '';
    $old = ['first' => $first, 'middle' => $middle, 'last' => $last, 'bd_m' => $bdM, 'bd_d' => $bdD,
            'bd_y' => $bdY, 'email' => $email, 'phone' => $phone];

    $dob   = null;
    $d     = date_create_from_format('Y-m-d', $birthdate);
    // DB clock, not PHP's date()/DateTime('today') — same convention as every
    // other "today" comparison in this app (see the dentalAssistantsOnDuty()
    // / touchPresence() / online-booking-lead-time notes in CLAUDE.md); the
    // birth-year <select> below is capped off this same value.
    $today = date_create_from_format('Y-m-d', (string) $pdo->query('SELECT CURDATE()')->fetchColumn());

    if ($first === '' || $last === '') {
        $regError = 'Please enter your first and last name.';
    } elseif (!validPersonName($first) || !validPersonName($last)) {
        $regError = PERSON_NAME_MSG;
    } elseif (!$d || $d->format('Y-m-d') !== $birthdate) {
        $regError = 'Please enter a valid birthdate.';
    } elseif ($d > $today) {
        $regError = 'Your birthdate cannot be in the future.';
    } elseif ($d > (clone $today)->modify('-18 years')) {
        // A self-registered portal account must belong to an adult — a minor
        // patient is still booked/served normally, just under a parent or
        // guardian's own account ("booking for someone else"), not their own
        // login. This does not affect staff-entered patient records (walk-ins,
        // dentist/receptionist "+ Create Record"), which have no birthdate
        // field and legitimately register patients of any age, Pedodontics
        // included.
        $regError = 'You must be at least 18 years old to create an account.';
    } elseif ($d <= (clone $today)->modify('-' . (ACCOUNT_MAX_AGE + 1) . ' years')) {
        // Upper bound (2026-10-18): account holders are 18 to 80.
        $regError = 'Account holders must be 18 to ' . ACCOUNT_MAX_AGE . ' years old.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $regError = 'Please enter a valid e-mail address.';
    } elseif (!isPhMobile($phone)) {
        $regError = PH_MOBILE_MSG;
    } elseif (phoneInUse($phone, (int) (function () use ($pdo, $email) {
        // A pending sign-up with this e-mail may keep its own number.
        $q = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1'); $q->execute([$email]); return $q->fetchColumn();
    })())) {
        $regError = DUPLICATE_RECORD_MSG;   // the contact number already has an account
    } elseif (existingPatientRecord($first, $last, $d->format('Y-m-d'), (int) (function () use ($pdo, $email) {
        // A pending (unverified) sign-up with this e-mail is the same person retrying.
        $q = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND role = 'patient' AND is_verified = 0 LIMIT 1"); $q->execute([$email]); return $q->fetchColumn();
    })())) {
        $regError = DUPLICATE_RECORD_MSG;   // same first + last name AND birthdate
    } elseif (!isStrongPassword($password)) {
        $regError = STRONG_PASSWORD_MSG;
    } elseif (!hash_equals($password, (string) ($_POST['confirm_password'] ?? ''))) {
        $regError = 'Passwords do not match.';
    } elseif (($_POST['agree_terms'] ?? '') !== '1') {
        $regError = 'Please agree to the Terms of Service and Privacy Policy to continue.';
    } else {
        $dob = $d->format('Y-m-d');

        $chk = $pdo->prepare('SELECT user_id, username, first_name, last_name, role, is_verified, is_active FROM users WHERE email = ? LIMIT 1');
        $chk->execute([$email]);
        $existing = $chk->fetch();
        if ($existing) {
            if ($existing['role'] === 'patient' && (int) $existing['is_verified'] !== 1 && (int) $existing['is_active'] === 1) {
                // Registered before but never verified: refresh that pending
                // account with what was just entered (the e-mailed code is
                // still the proof of ownership) and resume at the OTP screen.
                $pid      = (int) $existing['user_id'];
                $fullName = personName($first, $middle, $last);
                $username = (string) $existing['username'];
                if (strcasecmp($first, (string) $existing['first_name']) !== 0 || strcasecmp($last, (string) $existing['last_name']) !== 0) {
                    $username = makeUsername($pdo, $first, $last);
                }
                $pdo->beginTransaction();
                try {
                    $pdo->prepare(
                        'UPDATE users SET full_name = ?, first_name = ?, middle_name = ?, last_name = ?, username = ?,
                                          password = ?, contact_number = ? WHERE user_id = ?'
                    )->execute([
                        $fullName, $first, ($middle !== '' ? $middle : null), $last, $username,
                        password_hash($password, PASSWORD_DEFAULT), $phone, $pid,
                    ]);
                    $upd = $pdo->prepare('UPDATE patient_records SET date_of_birth = ? WHERE patient_id = ?');
                    $upd->execute([$dob, $pid]);
                    if ($upd->rowCount() === 0) {
                        $has = $pdo->prepare('SELECT 1 FROM patient_records WHERE patient_id = ?');
                        $has->execute([$pid]);
                        if (!$has->fetch()) {
                            $pdo->prepare('INSERT INTO patient_records (patient_id, date_of_birth) VALUES (?, ?)')->execute([$pid, $dob]);
                        }
                    }
                    $pdo->commit();
                } catch (Throwable $ex) {
                    $pdo->rollBack();
                    $regError = 'Something went wrong creating your account. Please try again.';
                }
                if ($regError === '') {
                    goToVerify($pdo, $pid, $fullName, $email, $username, 'register');
                }
            } else {
                $regError = DUPLICATE_RECORD_MSG;
            }
        }
    }

    if ($regError === '') {
        $fullName = personName($first, $middle, $last);
        $username = makeUsername($pdo, $first, $last);

        $pdo->beginTransaction();
        try {
            // is_verified defaults to 0 — the account cannot sign in until the
            // 6-digit code e-mailed to them is entered on verify.php. Patients
            // set their own password, so must_change_password stays 0.
            $pdo->prepare(
                "INSERT INTO users (full_name, first_name, middle_name, last_name, username, password,
                                    role, contact_number, email, is_verified, must_change_password)
                 VALUES (?, ?, ?, ?, ?, ?, 'patient', ?, ?, 0, 0)"
            )->execute([
                $fullName, $first, ($middle !== '' ? $middle : null), $last, $username,
                password_hash($password, PASSWORD_DEFAULT), $phone, $email,
            ]);

            $pid = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO patient_records (patient_id, date_of_birth) VALUES (?, ?)'
            )->execute([$pid, $dob]);

            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $regError = 'Something went wrong creating your account. Please try again.';
        }

        if ($regError === '') {
            // Do NOT sign the new patient in yet: send the code and go
            // straight to the OTP screen.
            goToVerify($pdo, $pid, $fullName, $email, $username, 'register');
        }
    }
}

// Which side of the slider shows on load: an explicit ?mode=register link, or
// whichever form was just submitted (so a register error/success — or a login
// error — reloads back into view on the right panel instead of Sign In).
$activePanel = 'login';
if (($_GET['mode'] ?? '') === 'register') {
    $activePanel = 'register';
}
if ($formSubmitted === 'register') {
    $activePanel = 'register';
} elseif ($formSubmitted === 'login') {
    $activePanel = 'login';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $activePanel === 'register' ? 'Create Account' : 'Sign In' ?> &middot; DGT One Dental Center - Bulacan</title>
<?php seoMeta('Sign In or Create an Account | DGT One Dental Center - Bulacan', 'Sign in to DGT One Dental Center - Bulacan to book a dental appointment online, check your schedule, and view your treatment and billing history. New patients can create a free account.', 'login'); ?>
<link rel="icon" type="image/x-icon" href="<?= e(asset('assets/img/favicon/favicon.ico')) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('assets/img/favicon/favicon-32x32.png')) ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= e(asset('assets/img/favicon/favicon-16x16.png')) ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('assets/img/favicon/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(asset('assets/img/favicon/site.webmanifest')) ?>">
<meta name="theme-color" content="#102f51">
<?php require __DIR__ . '/includes/tailwind.php'; ?>
<?php require __DIR__ . '/includes/auth_style.php'; /* the ONE require of this file — defines AUTH_INPUT/AUTH_BTN/… and emits the .auth-bg <style> block */ ?>
<style>
    /* ---- the "double slider" sign-in/sign-up card, now full-bleed (fills
       the whole viewport — no floating card, no visible page background) ---- */
    html, body { height: 100%; }
    .auth2-wrap { width: 100%; height: 100vh; }
    .auth2-container {
        position: relative;
        width: 100%;
        height: 100%;
        overflow: hidden;
        background: #fff;
    }
    /* "Launch" transition on a successful sign-in — see the loginForm submit
       handler below. GPU-only properties (transform + opacity) throughout.
       Never applied at all when prefers-reduced-motion is set (guarded in
       JS, not just CSS, so a reduced-motion user's submit isn't delayed
       waiting on a transition that will never visibly play).
       Desktop (a real two-sided split screen): the two halves keep sliding
       apart in the direction they already face — like doors opening —
       instead of the whole card zooming. Whichever mode is showing decides
       which physical element is on which side, so both cases are covered:
       Sign In (form left / video right) and Create Account (video left /
       form right, since .is-register already swapped them). */
    @media (min-width: 861px) {
        .auth2-container.is-launching .auth2-signin,
        .auth2-container.is-launching .auth2-signup,
        .auth2-container.is-launching .auth2-overlay-container {
            transition: transform .9s cubic-bezier(.16,1,.3,1), opacity .9s cubic-bezier(.16,1,.3,1) !important;
            pointer-events: none;
        }
        .auth2-container.is-launching:not(.is-register) .auth2-signin { transform: translateX(-100%) !important; opacity: 0 !important; }
        .auth2-container.is-launching:not(.is-register) .auth2-overlay-container { transform: translateX(100%) !important; }
        .auth2-container.is-launching.is-register .auth2-signup { transform: translateX(200%) !important; opacity: 0 !important; }
        .auth2-container.is-launching.is-register .auth2-overlay-container { transform: translateX(-200%) !important; }
    }
    /* Mobile (stacked single column — there's no literal "two sides" once the
       panels stack, so keep the simpler whole-card zoom+fade). */
    @media (max-width: 860px) {
        .auth2-container.is-launching {
            transition: transform .9s cubic-bezier(.16,1,.3,1), opacity .9s cubic-bezier(.16,1,.3,1);
            transform: scale(1.06);
            opacity: 0;
            pointer-events: none;
        }
    }
    .auth2-form {
        position: absolute;
        top: 0; left: 0;
        width: 50%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 48px 56px;
        overflow-y: auto;
        transition: transform 0.6s cubic-bezier(.65,0,.35,1);
    }
    .auth2-form-inner { width: 100%; max-width: 460px; }
    /* Red "*" after a required field's placeholder (placed by JS, 2026-10-11). */
    .req-mark { position: absolute; top: 50%; transform: translateY(-52%); color: #d64545; font-weight: 600; line-height: 1; pointer-events: none; }
    .req-wrap input:not(:placeholder-shown) ~ .req-mark,
    .pw-field input:not(:placeholder-shown) ~ .req-mark { display: none; }
    /* Bigger controls than the shared AUTH_INPUT/AUTH_BTN default (those are
       also used by reset_password.php/verify.php, so scale up in-scope here
       instead of touching the shared constants) — tighter gaps than the
       reference screenshot, just larger text/tap targets. */
    .auth2-form-inner input:not([type="checkbox"]),
    .auth2-form-inner select {
        padding-top: 15px;
        padding-bottom: 15px;
        font-size: 15.5px;
    }
    .auth2-form-inner button[type="submit"] {
        padding-top: 15px;
        padding-bottom: 15px;
        font-size: 15.5px;
    }
    /* The outgoing form fades out fast (no delay) so it's gone well before
       the 0.6s slide finishes; the incoming one waits until the slide is
       mostly done, then fades in — so the two forms (very different heights
       now that the controls are bigger) never sit cross-faded on top of each
       other mid-slide, which was the "clunky" double-image look. */
    .auth2-signin { z-index: 2; transition: transform 0.6s cubic-bezier(.65,0,.35,1), opacity 0.3s ease-out 0.24s; }
    .auth2-signup { opacity: 0; z-index: 1; pointer-events: none; transition: transform 0.6s cubic-bezier(.65,0,.35,1), opacity 0.25s ease-in; }
    .auth2-container.is-register .auth2-signin { transform: translateX(100%); opacity: 0; pointer-events: none; transition: transform 0.6s cubic-bezier(.65,0,.35,1), opacity 0.25s ease-in; }
    .auth2-container.is-register .auth2-signup { transform: translateX(100%); opacity: 1; z-index: 5; pointer-events: auto; transition: transform 0.6s cubic-bezier(.65,0,.35,1), opacity 0.3s ease-out 0.24s; }

    .auth2-overlay-container {
        position: absolute; top: 0; left: 50%; width: 50%; height: 100%;
        overflow: hidden; z-index: 100;
        transition: transform 0.6s cubic-bezier(.65,0,.35,1);
    }
    .auth2-container.is-register .auth2-overlay-container { transform: translateX(-100%); }
    .auth2-overlay {
        position: relative; left: -100%; width: 200%; height: 100%;
        transition: transform 0.6s cubic-bezier(.65,0,.35,1);
    }
    .auth2-container.is-register .auth2-overlay { transform: translateX(50%); }
    .auth2-overlay video {
        position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover;
        /* Two stacked videos crossfade into each other near the loop point
           (see the script below) instead of the abrupt jump a single looping
           <video> makes when it restarts. */
        opacity: 0; transition: opacity .6s linear;
    }
    .auth2-overlay video.is-active { opacity: 1; }
    @media (prefers-reduced-motion: reduce) {
        .auth2-overlay video { transition: none; }
    }
    .auth2-mhead { display: none; }
    .auth2-overlay-scrim {
        position: absolute; inset: 0;
        /* Flat tint (same colour both stops — not a visual gradient, per the
           no-gradient rule), lightened so the clinic video reads clearly
           through it instead of going dark/moody. */
        background: linear-gradient(0deg, rgba(15,28,46,.4), rgba(15,28,46,.4));
    }
    .auth2-panel {
        position: absolute; top: 0; width: 50%; height: 100%;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 0 52px; text-align: center; color: #fff;
        /* The scrim is a light tint (so the video stays visible) — a text
           shadow, not more opacity, is what keeps the headline/copy legible
           over a light patch of footage. */
        text-shadow: 0 2px 12px rgba(9,17,30,.6), 0 1px 3px rgba(9,17,30,.55);
        transition: transform 0.6s cubic-bezier(.65,0,.35,1);
    }
    .auth2-panel-left { transform: translateX(-22%); }
    .auth2-container.is-register .auth2-panel-left { transform: translateX(0); }
    .auth2-panel-right { right: 0; transform: translateX(0); }
    .auth2-container.is-register .auth2-panel-right { transform: translateX(22%); }
    .auth2-ghost-btn {
        margin-top: 28px; border: 1.5px solid rgba(255,255,255,.85); background: rgba(15,28,46,.28);
        color: #fff; font-weight: 600; font-size: 16px; letter-spacing: .02em;
        padding: 14px 40px; border-radius: 999px; cursor: pointer;
        transition: background-color .2s ease, color .2s ease;
    }
    .auth2-ghost-btn:hover { background: rgba(15,28,46,.42); }
    /* These render as plain text links, not row-action buttons — opt out of
       the global button-hover box-shadow (includes/tailwind.php's
       "row-action buttons" lift), which otherwise frames the whole width in
       a shadowed box since they stretch full width as flex children. */
    .auth2-form-inner button[data-switch]:hover,
    #regResendBtn:hover,
    #regEditEmailBtn:hover,
    #regEditEmailCancel:hover {
        box-shadow: none !important;
    }

    @media (prefers-reduced-motion: reduce) {
        /* .auth2-signin/.auth2-signup and their .is-register variants now
           declare their own transition (for the split fade timing above) at
           higher specificity than a bare `.auth2-form` rule would reach —
           list them explicitly so this guard still wins for every one. */
        .auth2-form, .auth2-overlay-container, .auth2-overlay, .auth2-panel,
        .auth2-signin, .auth2-signup,
        .auth2-container.is-register .auth2-signin,
        .auth2-container.is-register .auth2-signup { transition: none; }
    }

    /* Create Account sizing scales with the viewport HEIGHT (clamp + vh) so the
       form fills the available space on a tall screen and shrinks to stay
       scrollbar-free on a short laptop one, instead of fixed size tiers. */
    @media (min-width: 861px) {
        #signupPanel.auth2-form { padding-top: clamp(12px, 2.5vh, 40px); padding-bottom: clamp(12px, 2.5vh, 40px); }
        #signupPanel .auth2-form-inner > .mb-5 { margin-bottom: clamp(6px, 1.2vh, 16px); }
        #signupPanel .auth2-form-inner > .mb-5 img { height: clamp(38px, 7.6vh, 82px); }
        #signupPanel .auth2-form-inner { max-width: 500px; }
        /* Sized so the form (phone field included, no middle name) fits a
           1280x600 laptop without scrolling (2026-10-11). */
        #regForm { gap: clamp(5px, calc(1.8vh - 3.5px), 18px); }
        #regForm input:not([type="checkbox"]),
        #regForm select {
            padding-top: clamp(6px, calc(2.6vh - 6.5px), 19px);
            padding-bottom: clamp(6px, calc(2.6vh - 6.5px), 19px);
            font-size: clamp(13.5px, calc(1.4vh + 4.5px), 17px);
            /* text-sm's fixed 20px line height clipped the tails of g / y / p
               once the font grew past ~15px on a tall screen (2026-10-17). */
            line-height: 1.4;
        }
        #regForm button[type="submit"] {
            padding-top: clamp(7px, calc(2.6vh - 6.5px), 19px);
            padding-bottom: clamp(7px, calc(2.6vh - 6.5px), 19px);
            font-size: clamp(13.5px, calc(1.4vh + 4.5px), 17px);
        }
    }

    /* Mobile fallback: the side-by-side slider doesn't work under ~860px, so
       stack a compact video banner above whichever form is active and swap
       via the same JS toggle (no sliding, no overlay text panels). */
    @media (max-width: 860px) {
        .auth2-wrap { height: auto; min-height: 100vh; min-height: 100dvh; }
        .auth2-container { height: auto; min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; background: #0f1c2e; }
        /* DOM order is form-panels then video — flex `order` puts the video
           banner first visually, matching the intended "banner above the
           active form" mobile layout without reordering the markup. */
        .auth2-overlay-container { order: -1; position: relative; left: 0; width: 100%; height: clamp(200px, 34dvh, 300px); flex: none; transform: none !important; }
        .auth2-overlay { position: absolute; inset: 0; left: 0; width: 100%; transform: none !important; }
        .auth2-panel { display: none; }
        /* The form is a white sheet with rounded top corners that overlaps
           the bottom of the banner and fills the rest of the screen. */
        .auth2-form {
            position: relative; z-index: 110 !important; width: 100%; height: auto; flex: 1;
            margin-top: -28px; border-radius: 28px 28px 0 0; background: #fff;
            box-shadow: 0 -8px 24px rgba(15,28,46,.18);
            align-items: flex-start;
            padding: 30px 22px calc(32px + env(safe-area-inset-bottom));
            transform: none !important; opacity: 1 !important; pointer-events: auto !important;
            overflow: visible;
        }
        .auth2-form-inner { max-width: 440px; margin: 0 auto; }
        /* Sign In is short: centre it in the sheet instead of leaving a blank bottom. */
        .auth2-signin { align-items: center; padding-bottom: calc(56px + env(safe-area-inset-bottom)); }
        .auth2-form-inner > .mb-7, .auth2-form-inner > .mb-5 { margin-bottom: 20px; }
        .auth2-form-inner > .mb-7 img, .auth2-form-inner > .mb-5 img { height: 56px; }
        /* Heading over the banner (the desktop side panels are hidden here). */
        .auth2-mhead {
            display: block; position: absolute; left: 22px; right: 22px; bottom: 44px; z-index: 2;
            color: #fff; text-shadow: 0 2px 12px rgba(9,17,30,.6), 0 1px 3px rgba(9,17,30,.55);
        }
        .auth2-mhead h2 { font-size: clamp(24px, 7vw, 30px); font-weight: 700; line-height: 1.15; }
        .auth2-mhead p { margin-top: 4px; font-size: 14px; color: rgba(255,255,255,.92); }
        .auth2-mhead .is-reg { display: none; }
        @media (min-width: 640px) { .auth2-mhead { text-align: center; } }
        .auth2-container.is-register .auth2-mhead .is-login { display: none; }
        .auth2-container.is-register .auth2-mhead .is-reg { display: block; }
        .auth2-signup { display: none; }
        .auth2-container.is-register .auth2-signin { display: none; }
        .auth2-container.is-register .auth2-signup { display: flex; }
    }

    /* Inline OTP boxes — shared by the registration-success screen
       (#regOtpBoxes) and the Forgot Password modal's in-modal OTP step
       (#fpOtpBoxes). Same look/motion as verify's standalone page (see
       CLAUDE.md's "input-otp-style motion" note); login.php never carries
       dark mode so there's no dark: pairing to add. */
    #regOtpBoxes .otp-box, #fpOtpBoxes .otp-box {
        background: #f1f4fa;
        transition: transform .18s cubic-bezier(.34,1.56,.64,1), background-color .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    #regOtpBoxes .otp-box.otp-filled, #fpOtpBoxes .otp-box.otp-filled { background: #fff; box-shadow: 0 2px 8px rgba(27,42,74,.10); }
    #regOtpBoxes.otp-error .otp-box, #fpOtpBoxes.otp-error .otp-box { border-color: #e5484d !important; box-shadow: 0 0 0 4px rgba(229,72,77,.15) !important; }
    @keyframes otp-pop { 0% { transform: scale(1); } 45% { transform: scale(1.16) translateY(-2px); } 100% { transform: scale(1); } }
    @keyframes otp-shake { 10%, 90% { transform: translateX(-1px); } 20%, 80% { transform: translateX(2px); } 30%, 50%, 70% { transform: translateX(-5px); } 40%, 60% { transform: translateX(5px); } }
    @keyframes otp-loading { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: .45; transform: scale(.92); } }
    @keyframes otp-success {
        0%   { transform: scale(1);     border-color: #d6dbe6; box-shadow: 0 0 0 0 rgba(31,157,99,0); }
        35%  { transform: scale(1.2);   border-color: #1f9d63; box-shadow: 0 0 0 6px rgba(31,157,99,.32); }
        70%  { transform: scale(.92);   border-color: #1f9d63; box-shadow: 0 0 0 2px rgba(31,157,99,.16); }
        100% { transform: scale(1);     border-color: #d6dbe6; box-shadow: 0 0 0 0 rgba(31,157,99,0); }
    }
    #regOtpBoxes .otp-box.anim-pop, #fpOtpBoxes .otp-box.anim-pop { animation: otp-pop .32s cubic-bezier(.34,1.56,.64,1); }
    #regOtpBoxes .otp-box.anim-shake, #fpOtpBoxes .otp-box.anim-shake { animation: otp-shake .4s ease; }
    #regOtpBoxes .otp-box.anim-success, #fpOtpBoxes .otp-box.anim-success { animation: otp-success .42s cubic-bezier(.34,1.56,.64,1); }
    #regOtpBoxes .otp-box.anim-loading, #fpOtpBoxes .otp-box.anim-loading { animation: otp-loading 1s ease-in-out infinite; }
    @media (prefers-reduced-motion: reduce) {
        #regOtpBoxes .otp-box, #fpOtpBoxes .otp-box { transition: none; }
        #regOtpBoxes .otp-box.anim-pop, #regOtpBoxes .otp-box.anim-shake,
        #regOtpBoxes .otp-box.anim-success, #regOtpBoxes .otp-box.anim-loading,
        #fpOtpBoxes .otp-box.anim-pop, #fpOtpBoxes .otp-box.anim-shake,
        #fpOtpBoxes .otp-box.anim-success, #fpOtpBoxes .otp-box.anim-loading { animation: none; }
    }
</style>
</head>
<?php
$inputCls = AUTH_INPUT;
$btnCls   = AUTH_BTN;
?>
<body class="m-0 min-h-screen bg-white font-sans text-[#1b2a4a] antialiased">
    <div class="auth2-wrap">
    <div class="auth2-container<?= $activePanel === 'register' ? ' is-register' : '' ?>" id="authContainer">

        <!-- Sign In panel -->
        <div class="auth2-form auth2-signin" id="signinPanel"<?= $activePanel === 'register' ? ' inert' : '' ?>>
            <div class="auth2-form-inner">
                <div class="mb-7 flex justify-center">
                    <a href="/dgt_dental_clinic/home">
                        <img src="<?= e(asset('assets/img/pic_login-sm.webp')) ?>" alt="DGT One Dental Center: back to home"
                             class="h-20 w-auto max-w-[280px] object-contain">
                    </a>
                </div>

                <?php if ($notice !== ''): ?>
                    <div class="mb-4 rounded-md bg-[#e2f4ec] px-4 py-3 text-[14px] text-ok"><?= e($notice) ?></div>
                <?php endif; ?>
                <?php if ($loginError !== ''): ?>
                    <div class="mb-4 rounded-md bg-[#fbe6e4] px-4 py-3 text-[14px] text-danger"><?= e($loginError) ?></div>
                <?php endif; ?>
                <?php if ($unverifiedEmail !== ''): ?>
                    <div class="mb-4 rounded-md bg-[#fbf0dd] px-4 py-3 text-center text-[13.5px] text-warn">
                        <a href="/dgt_dental_clinic/verify?email=<?= urlencode($unverifiedEmail) ?>"
                           class="font-semibold text-[#3a52d6] hover:underline">Enter verification code</a>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login" class="flex flex-col gap-3.5" autocomplete="off" id="loginForm">
                    <input type="hidden" name="form" value="login">
                    <input type="text" name="username" placeholder="Enter username" required
                           <?= $activePanel === 'login' ? 'autofocus' : '' ?>
                           autocapitalize="none" autocomplete="username" spellcheck="false"
                           value="<?= $formSubmitted === 'login' ? e($_POST['username'] ?? '') : '' ?>" class="<?= $inputCls ?>">

                    <div class="pw-field relative">
                        <input type="password" name="password" placeholder="Password" required class="<?= $inputCls ?> pr-10">
                        <?= pwToggleBtn() ?>
                    </div>

                    <div class="flex items-center justify-between text-[14px]">
                        <label class="flex items-center gap-2 text-[#5b6472]">
                            <input type="checkbox" name="remember" value="1" <?= ($formSubmitted === 'login' && isset($_POST['remember'])) ? 'checked' : '' ?>
                                   class="h-4 w-4 rounded border-[#d6dbe6] accent-[#4361ee]">
                            <span>Remember me</span>
                        </label>
                        <a href="#" id="forgotLink" class="<?= AUTH_LINK ?>">Forgot your password?</a>
                    </div>

                    <button type="submit" class="<?= $btnCls ?>">Log In</button>
                    <button type="button" class="mt-2 cursor-pointer appearance-none border-0 bg-transparent p-0 text-center text-[14px] <?= AUTH_LINK ?>" data-switch="register">Don't have an account? Sign Up</button>
                </form>
            </div>
        </div>

        <!-- Create Account panel -->
        <div class="auth2-form auth2-signup" id="signupPanel"<?= $activePanel === 'login' ? ' inert' : '' ?>>
            <div class="auth2-form-inner">
                <div class="mb-5 flex justify-center">
                    <a href="/dgt_dental_clinic/home">
                        <img src="<?= e(asset('assets/img/pic_login-sm.webp')) ?>" alt="DGT One Dental Center: back to home"
                             class="h-16 w-auto max-w-[220px] object-contain">
                    </a>
                </div>

                <?php if ($regDone): ?>
                    <div class="mb-4 rounded-lg border border-[#d6dbe6] bg-[#f4f6fb] px-4 py-3.5 text-center">
                        <p class="text-[13px] font-semibold uppercase tracking-wide text-steel-500">Your username</p>
                        <p class="mt-1 font-mono text-xl font-bold text-navy-900"><?= e($doneUsername) ?></p>
                        <p class="mt-1 text-[13px] text-steel-500">Use this to sign in.</p>
                    </div>
                    <div class="mb-4 rounded-md bg-[#e2f4ec] px-4 py-3.5 text-[14px] leading-relaxed text-ok" id="regAlert">
                        <span id="regAlertText">Your account was created. We've sent a 6-digit verification code to
                        <strong class="font-bold" id="regEmailText"><?= e($doneEmail) ?></strong>.
                        Enter it below to confirm your e-mail, then sign in.</span>
                        <button type="button" id="regEditEmailBtn"
                                class="cursor-pointer appearance-none border-0 bg-transparent p-0 font-semibold text-ok underline decoration-dotted underline-offset-2 hover:text-[#166a41]">Edit Email</button>

                        <div class="mt-3 hidden" id="regEditEmailForm">
                            <input type="email" id="regEditEmailInput" value="<?= e($doneEmail) ?>"
                                   class="min-w-0 flex-1 rounded-md border border-[#bfe2cf] bg-white px-3 py-2 text-[13.5px] text-[#1b2a4a] outline-none focus:border-ok focus:ring-4 focus:ring-ok/15">
                            <button type="button" id="regEditEmailSave"
                                    class="cursor-pointer appearance-none rounded-md border-0 bg-ok px-3 py-2 text-[13px] font-semibold text-white transition hover:bg-[#166a41]">Save &amp; Resend</button>
                            <button type="button" id="regEditEmailCancel"
                                    class="cursor-pointer appearance-none border-0 bg-transparent p-0 px-1 text-[13px] font-semibold text-steel-600 hover:underline">Cancel</button>
                        </div>
                        <p class="mt-2 hidden text-[13px]" id="regEditEmailMsg"></p>
                    </div>
                    <div class="mb-4 rounded-md bg-[#fbf0dd] px-4 py-3 text-[13.5px] leading-relaxed text-warn<?= $devOtp === null ? ' hidden' : '' ?>" id="regDevOtpBox">
                        <strong>Development mode</strong>: e-mail is not configured on this host, so use this code:
                        <span class="font-mono font-bold tracking-[0.3em]" id="regDevOtpCode"><?= e((string) $devOtp) ?></span>
                    </div>

                    <div class="flex justify-center gap-2" role="group" aria-label="6-digit verification code" id="regOtpBoxes">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" placeholder="0"
                                   autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
                                   class="otp-box h-14 w-12 rounded-xl border border-[#d6dbe6] text-center text-2xl font-mono font-bold text-[#1b2a4a] outline-none placeholder:text-[#b9c0cf] focus:border-[#4361ee] focus:ring-4 focus:ring-[#4361ee]/15">
                        <?php endfor; ?>
                    </div>
                    <p class="mt-2 hidden text-center text-[12.5px] text-danger" id="regOtpErr"></p>

                    <button type="button" id="regVerifyBtn" disabled class="mt-4 <?= $btnCls ?>">Verify &amp; Sign In</button>

                    <p class="mt-3.5 text-center text-[13.5px] text-steel-500">
                        Didn't get the code?
                        <button type="button" id="regResendBtn" disabled
                                class="cursor-pointer appearance-none border-0 bg-transparent p-0 font-semibold text-[#3a52d6] hover:underline disabled:cursor-not-allowed disabled:text-steel-400 disabled:no-underline"
                                data-email="<?= e($doneEmail) ?>">Resend it</button>
                    </p>
                    <p class="hidden mt-2 text-center text-[13.5px]" id="regResendMsg"></p>
                <?php else: ?>

                <?php if ($regError !== '' && $regError !== DUPLICATE_RECORD_MSG): ?>
                    <div class="mb-4 rounded-md bg-[#fbe6e4] px-4 py-3 text-[14px] text-danger"><?= e($regError) ?></div>
                <?php endif; ?>

                <form method="POST" action="login" class="flex flex-col gap-3.5" autocomplete="off" id="regForm">
                    <input type="hidden" name="form" value="register">
                    <div class="req-wrap relative"><input type="text" name="first_name" placeholder="First Name" required
                           <?= $activePanel === 'register' ? 'autofocus' : '' ?>
                           value="<?= e($old['first']) ?>" class="<?= $inputCls ?>"><span class="req-mark" aria-hidden="true">*</span></div>
                    <div class="req-wrap relative"><input type="text" name="last_name" placeholder="Last Name" required
                           value="<?= e($old['last']) ?>" class="<?= $inputCls ?>"><span class="req-mark" aria-hidden="true">*</span></div>

                    <label class="-mb-1 text-sm font-semibold text-steel-500">Birthdate <span class="text-danger">*</span></label>
                    <div class="flex gap-2.5">
                        <select name="bd_month" aria-label="Birth month" required class="<?= $inputCls ?> flex-[1.5] px-2.5">
                            <option value="" <?= $old['bd_m'] ? '' : 'selected' ?> disabled>Month</option>
                            <?php foreach ($MONTHS as $n => $mn): ?>
                                <option value="<?= $n ?>" <?= $old['bd_m'] === $n ? 'selected' : '' ?>><?= $mn ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="bd_day" aria-label="Birth day" required class="<?= $inputCls ?> flex-1 px-2.5">
                            <option value="" <?= $old['bd_d'] ? '' : 'selected' ?> disabled>Day</option>
                            <?php for ($i = 1; $i <= 31; $i++): ?>
                                <option value="<?= $i ?>" <?= $old['bd_d'] === $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="bd_year" aria-label="Birth year" required class="<?= $inputCls ?> flex-1 px-2.5">
                            <option value="" <?= $old['bd_y'] ? '' : 'selected' ?> disabled>Year</option>
                            <?php
                            // Capped at "18 years ago" (DB clock, not PHP's date() — same
                            // convention as the server-side check above) so the dropdown
                            // itself can't offer this year, last year, or any other year
                            // too recent to be an adult — not just future/current dates,
                            // the whole under-18 range is simply absent from the list.
                            $maxBirthYear = (int) $pdo->query('SELECT YEAR(CURDATE()) - 18')->fetchColumn();
                            // ...and goes back only to age 80 (2026-10-18).
                            for ($y = $maxBirthYear; $y >= $maxBirthYear - (ACCOUNT_MAX_AGE - 18) - 1; $y--):
                            ?>
                                <option value="<?= $y ?>" <?= $old['bd_y'] === $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="req-wrap relative"><input type="email" name="email" placeholder="Email" required
                           value="<?= e($old['email']) ?>" class="<?= $inputCls ?>"><span class="req-mark" aria-hidden="true">*</span></div>

                    <div class="req-wrap relative"><input type="tel" name="contact_number" placeholder="Phone Number (09XXXXXXXXX)" required
                           inputmode="numeric" maxlength="11" value="<?= e($old['phone'] ?? '') ?>" class="<?= $inputCls ?>"><span class="req-mark" aria-hidden="true">*</span></div>
                    <p id="phoneMsg" class="-mt-1 hidden text-[12.5px] text-danger md:whitespace-nowrap" aria-live="polite">Enter a valid 11-digit mobile number starting with 09 (e.g., 09171234567).</p>


                    <div class="pw-field relative">
                        <input type="password" name="password" placeholder="Password" required minlength="8" class="<?= $inputCls ?> pr-10"><span class="req-mark" aria-hidden="true">*</span>
                        <?= pwToggleBtn() ?>
                    </div>
                    <div class="-mt-1">
                        <div id="pwMeter" class="flex gap-1.5" aria-hidden="true">
                            <span class="pw-seg h-1 flex-1 rounded-full bg-steel-200 transition-colors"></span>
                            <span class="pw-seg h-1 flex-1 rounded-full bg-steel-200 transition-colors"></span>
                            <span class="pw-seg h-1 flex-1 rounded-full bg-steel-200 transition-colors"></span>
                            <span class="pw-seg h-1 flex-1 rounded-full bg-steel-200 transition-colors"></span>
                        </div>
                        <p class="mt-1.5 flex items-center justify-between gap-3 text-[12.5px] text-steel-400" aria-live="polite">
                            <span id="pwHint">Use 8+ characters with uppercase, lowercase, a number, and a symbol.</span>
                            <span id="pwStrengthLabel" class="shrink-0 font-semibold"></span>
                        </p>
                    </div>

                    <div class="pw-field relative">
                        <input type="password" name="confirm_password" placeholder="Confirm Password" required class="<?= $inputCls ?> pr-10"><span class="req-mark" aria-hidden="true">*</span>
                        <?= pwToggleBtn() ?>
                    </div>
                    <p id="pwMatchMsg" class="-mt-1 hidden text-[12.5px] text-danger" aria-live="polite">Passwords do not match.</p>

                    <label for="tcCheck" class="-mt-1 flex items-start gap-2 text-[12.5px] leading-relaxed text-steel-400">
                        <input type="checkbox" name="agree_terms" id="tcCheck" value="1" required
                               class="mt-[3px] h-[15px] w-[15px] shrink-0 accent-[#4361ee]">
                        <span>I agree to DGT One Dental Center's
                        <a href="<?= e(asset('terms')) ?>" id="termsLink" target="_blank" rel="noopener" class="font-semibold text-steel-500 underline hover:text-navy-700">Terms of Service</a>
                        and
                        <a href="<?= e(asset('privacy')) ?>" id="privacyLink" target="_blank" rel="noopener" class="font-semibold text-steel-500 underline hover:text-navy-700">Privacy Policy</a>. <span class="text-danger">*</span></span>
                    </label>
                    <button type="submit" class="<?= $btnCls ?>" id="submitBtn" disabled>Sign Up</button>
                    <button type="button" class="mt-2 cursor-pointer appearance-none border-0 bg-transparent p-0 text-center text-[14px] <?= AUTH_LINK ?>" data-switch="login">Already have an account?</button>
                </form>
                <?php endif; /* $regDone vs form */ ?>
            </div>
        </div>

        <!-- Overlay: the looping clinic video + the two invite panels -->
        <div class="auth2-overlay-container">
            <div class="auth2-overlay">
                <video muted loop playsinline preload="none" poster="<?= e(asset('assets/img/login-video-poster.jpg')) ?>" id="authVideo" aria-hidden="true" tabindex="-1" class="is-active">
                    <source src="<?= e(asset('assets/videos/clinic-loop.mp4')) ?>" type="video/mp4">
                </video>
                <video muted playsinline preload="none" poster="<?= e(asset('assets/img/login-video-poster.jpg')) ?>" id="authVideo2" aria-hidden="true" tabindex="-1">
                    <source src="<?= e(asset('assets/videos/clinic-loop.mp4')) ?>" type="video/mp4">
                </video>
                <div class="auth2-overlay-scrim"></div>

                <div class="auth2-panel auth2-panel-left">
                    <h2 class="text-[42px] font-bold leading-tight">Welcome Back!</h2>
                    <p class="mt-4 text-[17px] leading-relaxed text-white/95">Sign in to manage your appointments, treatment history and billing.</p>
                    <button type="button" class="auth2-ghost-btn" data-switch="login">Log In</button>
                </div>
                <div class="auth2-panel auth2-panel-right">
                    <h2 class="text-[42px] font-bold leading-tight">New Here?</h2>
                    <p class="mt-4 text-[17px] leading-relaxed text-white/95">Create an account to book appointments online at DGT One Dental Center.</p>
                    <button type="button" class="auth2-ghost-btn" data-switch="register">Sign Up</button>
                </div>
            </div>
            <!-- Phones / small tablets only (the side panels above are hidden there). -->
            <div class="auth2-mhead" aria-hidden="true">
                <div class="is-login"><h2>Welcome back!</h2><p>Sign in to manage your appointments.</p></div>
                <div class="is-reg"><h2>Create your account</h2><p>Book your dental visits online.</p></div>
            </div>
        </div>
    </div>
    </div>

    <?php if ($regError === DUPLICATE_RECORD_MSG): ?>
    <!-- Duplicate account prompt (2026-10-22): the e-mail or contact number
         already has an account, so sign-up is blocked and the person is sent
         to account recovery instead. -->
    <div class="fixed inset-0 z-[190] flex items-center justify-center bg-navy-900/55 p-6" id="dupOverlay">
        <div class="w-full max-w-[460px] overflow-hidden rounded-2xl bg-white shadow-[0_18px_45px_rgba(15,28,46,0.22)]"
             role="alertdialog" aria-modal="true" aria-labelledby="dupHeading" aria-describedby="dupBody">
            <div class="px-6 pb-2 pt-6 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-[#fbf0dd] text-warn">
                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                </div>
                <h2 id="dupHeading" class="text-lg font-bold text-navy-900">Record already exists</h2>
                <p id="dupBody" class="mt-2 text-[14px] leading-relaxed text-steel-600">
                    A patient record with these details already exists.
                    If you forgot your login details or credentials, reset your password below,
                    contact the clinic, or visit the clinic for account recovery.
                </p>
                <div class="mt-4 rounded-lg border border-steel-100 bg-steel-50 px-4 py-3 text-left text-[13px] leading-relaxed text-navy-800">
                    <div><span class="font-semibold">Phone:</span> <a href="tel:09764484252" class="text-accent hover:underline">09764484252</a></div>
                    <div><span class="font-semibold">E-mail:</span> <a href="mailto:customerservice@dgtonedentalph.com" class="text-accent hover:underline">customerservice@dgtonedentalph.com</a></div>
                    <div><span class="font-semibold">Clinic:</span> B-6L-47 Delrey Bldg., Bgy. Tungkong Mangga, San Jose Del Monte, Bulacan</div>
                </div>
            </div>
            <div class="flex flex-col gap-2 px-6 pb-6 pt-4 sm:flex-row-reverse">
                <button type="button" id="dupRecover" class="<?= $btnCls ?> sm:flex-1">Forgot Password / Recover Account</button>
                <button type="button" id="dupClose" class="h-11 rounded-full border border-steel-200 bg-white px-5 text-[14px] font-semibold text-navy-800 hover:bg-steel-50 sm:flex-1">Close</button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var ov = document.getElementById('dupOverlay');
        function hide() { ov.remove(); }
        document.getElementById('dupClose').addEventListener('click', hide);
        ov.addEventListener('click', function (e) { if (e.target === ov) hide(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && document.getElementById('dupOverlay')) hide(); });
        document.getElementById('dupRecover').addEventListener('click', function () {
            var em = <?= json_encode((string) ($old['email'] ?? '')) ?>;
            hide();
            if (window.openForgotPw) window.openForgotPw(em);
        });
        setTimeout(function () { document.getElementById('dupRecover').focus(); }, 50);
    })();
    </script>
    <?php endif; ?>

    <!-- Forgot-password modal — a two-step in-modal state machine: Step 1
         requests a 6-digit code by e-mail. Step 2 is itself two phases shown
         in place, one at a time: the OTP phase (enter + auto-verify the
         code) reveals the password phase (New/Confirm Password) only once
         the code checks out. See CLAUDE.md's "Forgot password" paragraph. -->
    <div class="hidden fixed inset-0 z-[200] items-center justify-center bg-navy-900/55 p-6" id="fpOverlay">
        <div class="flex w-full max-w-[520px] flex-col overflow-hidden rounded-2xl bg-white shadow-[0_18px_45px_rgba(15,28,46,0.22)]"
             role="dialog" aria-modal="true" aria-labelledby="fpHeading">
            <div class="flex items-center justify-between border-b border-steel-100 px-5 py-4">
                <h2 id="fpHeading" class="text-base font-bold text-navy-900">Reset your password</h2>
                <button type="button" id="fpClose" aria-label="Close"
                        class="border-0 bg-transparent text-2xl leading-none text-steel-400 hover:text-navy-800">&times;</button>
            </div>
            <div class="px-5 py-[18px]">
                <!-- Step 1: request a code -->
                <form id="fpForm" class="flex flex-col gap-3" autocomplete="off">
                    <p class="text-left text-[12.5px] text-steel-500">
                        Enter your registered e-mail address and we'll send you a 6-digit code to reset your password.
                    </p>
                    <input type="email" name="email" id="fpEmail" placeholder="Email" required class="<?= $inputCls ?>">
                    <p class="hidden text-center text-[12.5px]" id="fpMsg"></p>
                    <button type="submit" class="<?= $btnCls ?>" id="fpBtn">Send Reset Code</button>
                </form>

                <!-- Step 2 (revealed in place once Step 1 succeeds) -->
                <div class="hidden flex-col gap-3" id="fpStep2">
                    <div class="rounded-md bg-[#e2f4ec] px-3.5 py-3 text-[12.5px] leading-relaxed text-ok">
                        We've sent a 6-digit verification code to
                        <strong class="font-bold" id="fpStep2Email"></strong>.
                        <button type="button" id="fpEditEmailBtn"
                                class="cursor-pointer appearance-none border-0 bg-transparent p-0 font-semibold text-ok underline decoration-dotted underline-offset-2 hover:text-[#166a41]">Edit Email</button>
                    </div>
                    <div class="hidden rounded-md bg-[#fbf0dd] px-3.5 py-2.5 text-[12.5px] leading-relaxed text-warn" id="fpDevOtpBox">
                        <strong>Development mode</strong>: e-mail is not configured on this host, so use this code:
                        <span class="font-mono font-bold tracking-[0.3em]" id="fpDevOtpCode"></span>
                    </div>

                    <!-- Phase A: enter the code (auto-verified on the 6th digit) -->
                    <div class="flex flex-col gap-3" id="fpOtpPhase">
                        <div class="flex justify-center gap-2" role="group" aria-label="6-digit verification code" id="fpOtpBoxes">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" placeholder="0"
                                       autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
                                       class="otp-box h-12 w-10 rounded-lg border border-[#d6dbe6] text-center text-xl font-mono font-bold text-[#1b2a4a] outline-none placeholder:text-[#b9c0cf] focus:border-[#4361ee] focus:ring-4 focus:ring-[#4361ee]/15">
                            <?php endfor; ?>
                        </div>
                        <p class="hidden text-center text-[12.5px] text-danger" id="fpOtpErr"></p>

                        <p class="text-center text-[12.5px]" id="fpResendLine">
                            Didn't get the code?
                            <button type="button" id="fpResendBtn" disabled
                                    class="cursor-pointer appearance-none border-0 bg-transparent p-0 font-semibold text-[#3a52d6] hover:underline disabled:cursor-not-allowed disabled:text-steel-400 disabled:no-underline"></button>
                        </p>
                        <p class="hidden text-center text-[12.5px]" id="fpResendMsg"></p>
                    </div>

                    <!-- Phase B: new password (revealed only once the code above checks out) -->
                    <div class="hidden flex-col gap-3" id="fpPasswordPhase">
                        <p class="flex items-center gap-1.5 text-[12.5px] font-semibold text-ok">
                            <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            Code verified. Choose your new password.
                        </p>
                        <div class="pw-field relative">
                            <input type="password" id="fpPw1" placeholder="New Password" required minlength="8" class="<?= $inputCls ?> pr-10">
                            <?= pwToggleBtn() ?>
                        </div>
                        <div class="pw-field relative">
                            <input type="password" id="fpPw2" placeholder="Confirm New Password" required minlength="8" class="<?= $inputCls ?> pr-10">
                            <?= pwToggleBtn() ?>
                        </div>
                        <p class="hidden text-left text-[12.5px] text-danger" id="fpMatchMsg">The two passwords do not match.</p>
                        <button type="button" id="fpResetBtn" disabled class="<?= $btnCls ?>">Reset Password &amp; Sign In</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= e(asset('assets/js/password-strength.js')) ?>"></script>
    <script>
        // Slide toggle between Sign In / Create Account. `inert` keeps the
        // hidden panel out of the tab order and off-limits to screen readers
        // while it's slid out of view (kept in sync with .is-register).
        (function () {
            var container = document.getElementById('authContainer');
            var signin = document.getElementById('signinPanel');
            var signup = document.getElementById('signupPanel');
            var video  = document.getElementById('authVideo');
            var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            function setInert() {
                var isRegister = container.classList.contains('is-register');
                if (isRegister) { signin.setAttribute('inert', ''); signup.removeAttribute('inert'); }
                else { signup.setAttribute('inert', ''); signin.removeAttribute('inert'); }
            }
            function go(mode) {
                container.classList.toggle('is-register', mode === 'register');
                setInert();
                var focusEl = container.querySelector(mode === 'register' ? '#signupPanel input' : '#signinPanel input');
                if (focusEl) focusEl.focus();
                var url = new URL(window.location.href);
                if (mode === 'register') url.searchParams.set('mode', 'register'); else url.searchParams.delete('mode');
                window.history.replaceState(null, '', url);
            }
            document.querySelectorAll('[data-switch]').forEach(function (btn) {
                btn.addEventListener('click', function () { go(btn.getAttribute('data-switch')); });
            });

            if (reduceMotion && video) video.pause();
        })();

        /* Load the 8.6 MB clinic video only after the page is ready, so it
         * never competes with the form for a slow connection (2026-10-11).
         * Both <video>s are preload="none" with no autoplay: the poster shows
         * first. On Save-Data / 2G / 3G, or with reduced motion, the poster
         * simply stays. The standby copy (crossfade below) starts buffering
         * a few seconds after the first one is playing. */
        (function () {
            var vA = document.getElementById('authVideo');
            var vB = document.getElementById('authVideo2');
            if (!vA) return;
            var c = navigator.connection;
            if ((c && (c.saveData || /(^|-)(2g|3g)$/.test(c.effectiveType || '')))
                || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            function start() {
                vA.preload = 'auto';
                var p = vA.play();
                if (p && p.catch) p.catch(function () {});
                vA.addEventListener('playing', function () {
                    if (vB) setTimeout(function () { vB.preload = 'auto'; vB.load(); }, 3000);
                }, { once: true });
            }
            function later() { setTimeout(start, 300); }
            if (document.readyState === 'complete') later(); else window.addEventListener('load', later, { once: true });
        })();

        /* Seamless video loop via a two-video opacity crossfade. A single
         * looping <video> jumps visibly at the restart point; instead, a
         * second, identical <video> (#authVideo2, paused, opacity 0) sits
         * stacked on top of it via .auth2-overlay video's own absolute
         * inset:0 rule. As the active video nears its end we start playing
         * the standby one from 0:00 and cross-fade the CSS opacity of both
         * (transition already declared on .auth2-overlay video) — the two
         * blend into each other instead of a hard cut, then the roles swap
         * and the process repeats with the other video as the new standby.
         * Skipped entirely under prefers-reduced-motion, where #authVideo
         * just keeps its plain `loop` attribute (the ordinary, un-faded
         * jump) and #authVideo2 is removed outright since it would never be
         * used. */
        (function () {
            var vA = document.getElementById('authVideo');
            var vB = document.getElementById('authVideo2');
            if (!vA || !vB) return;

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                vB.remove();
                return;
            }

            var CROSSFADE_SEC = 0.6; // keep in sync with the CSS opacity transition above
            var active = vA, standby = vB, fading = false;

            vA.removeAttribute('loop'); // looping is now driven by the crossfade below

            function crossfadeWindow(v) {
                return (v.duration && isFinite(v.duration)) ? Math.min(CROSSFADE_SEC, v.duration * 0.25) : CROSSFADE_SEC;
            }

            function onTimeUpdate() {
                if (fading || !active.duration || !isFinite(active.duration)) return;
                if (active.currentTime < active.duration - crossfadeWindow(active)) return;

                fading = true;
                standby.currentTime = 0;
                var outgoing = active;

                // Only fade over once the standby video is really playing. If
                // the browser refuses to play it (power saver, a second muted
                // video blocked, data not loaded yet) the active one used to be
                // faded out anyway, leaving a blank grey panel. Now it just
                // keeps looping on its own instead.
                function swap() {
                    requestAnimationFrame(function () {
                        outgoing.classList.remove('is-active');
                        standby.classList.add('is-active');
                    });
                    setTimeout(function () {
                        outgoing.pause();
                        var tmp = active; active = standby; standby = tmp;
                        fading = false;
                    }, CROSSFADE_SEC * 1000);
                }
                function giveUp() {
                    vA.removeEventListener('timeupdate', onTimeUpdate);
                    vB.removeEventListener('timeupdate', onTimeUpdate);
                    outgoing.loop = true;
                    if (outgoing.ended || outgoing.paused) { outgoing.currentTime = 0; outgoing.play().catch(function () {}); }
                    standby.pause();
                    fading = false;
                }
                var playPromise = standby.play();
                if (playPromise && playPromise.then) playPromise.then(swap, giveUp);
                else swap();
            }

            vA.addEventListener('timeupdate', onTimeUpdate);
            vB.addEventListener('timeupdate', onTimeUpdate);
            // Safety net: if a video reaches its end without the crossfade
            // firing (e.g. a throttled background tab), restart it so the
            // panel never sits frozen or blank.
            [vA, vB].forEach(function (v) {
                v.addEventListener('ended', function () {
                    if (v === active && !fading) { v.currentTime = 0; v.play().catch(function () {}); }
                });
            });
            // Autoplay can be refused on first load; retry once it can play.
            vA.addEventListener('canplay', function () {
                if (vA === active && vA.paused) vA.play().catch(function () {});
            }, { once: true });
        })();

        /* "Launch" transition into the dashboard on a successful sign-in.
         *
         * This app is a classic multi-page PHP app — login.php POSTs, the
         * server validates and redirects with `header('Location: ...')` to
         * whatever roleHome() resolves to (a different role gets a different
         * URL/page entirely). There is no client-side router and nothing
         * "mounts" across that navigation, so a literal shared-element DOM
         * morph between the auth card and the dashboard isn't something this
         * stack can do — the two are separate documents. What *is* real: an
         * outro played here on submit — on desktop the two halves slide
         * apart off opposite edges, like doors opening (see the CSS above);
         * on the mobile stacked layout, which has no literal "two sides",
         * the whole card zooms+fades instead — and a matching staggered
         * fade/rise entrance played by the shared assets/js/app.js on the
         * next full page load (see the block near the theme toggle there),
         * handed off via one sessionStorage flag. Both stages use only
         * `transform`/`opacity` (GPU-accelerated) and the same easing curve.
         *
         * We can't gate the animation on the server's success/failure
         * response without turning this form into an AJAX/JSON endpoint
         * (a bigger, security-sensitive change to the auth flow) — so this
         * plays optimistically on every submit. On success it lines up with
         * the next page loading; on a login error the page reloads back to
         * this same form shortly after, which simply cuts the animation
         * short rather than looking broken. Skipped entirely — no delay, no
         * class added — for prefers-reduced-motion or if JS never runs at
         * all (a plain, instant form submit either way). */
        (function () {
            var form      = document.getElementById('loginForm');
            var container = document.getElementById('authContainer');
            if (!form || !container) return;
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            // Genuine fallback only — must be LONGER than the CSS's .9s so the
            // real transitionend always wins the race in a normal browser.
            // (A previous version set this shorter than the transition, which
            // meant the timeout won every time and submitted the form before
            // the animation had actually finished playing — the transition
            // never got to be seen through to its end.)
            var LAUNCH_MS = 1000;
            var launching = false;

            form.addEventListener('submit', function (e) {
                if (launching) return; // programmatic re-submit below — let it through
                e.preventDefault();
                launching = true;

                try { sessionStorage.setItem('dgt_just_logged_in', '1'); } catch (err) {}
                container.classList.add('is-launching');

                var done = false;
                var finish = function () {
                    if (done) return;
                    done = true;
                    form.submit();
                };
                // transitionend bubbles, so one listener on the container
                // catches both cases: the container's own transition on
                // mobile (zoom+fade), or one of the sliding halves on
                // desktop (the doors-apart effect targets the panels, not
                // the container itself there).
                container.addEventListener('transitionend', function (e) {
                    if (e.target === container
                        || (e.target.classList && (e.target.classList.contains('auth2-signin')
                            || e.target.classList.contains('auth2-signup')
                            || e.target.classList.contains('auth2-overlay-container')))) {
                        finish();
                    }
                });
                setTimeout(finish, LAUNCH_MS); // safety net if transitionend never fires
            });
        })();

        // Forgot Password modal — a two-step in-modal state machine (no
        // navigation): Step 1 requests a 6-digit code by e-mail. Step 2 is
        // itself two phases shown in place, one at a time — the OTP phase
        // (auto-verified as soon as the 6th digit is typed, via
        // api/check_reset_otp.php, which only checks the code without
        // consuming it) reveals the password phase only once the code
        // checks out; that phase's submit is what actually resets the
        // password + consumes the code (api/reset_password_otp.php) and
        // signs the account straight in. The OTP-box mechanics (auto-
        // advance, paste, shake-on-error, bounce-on-success) mirror the
        // registration-success screen's own script just below.
        (function () {
            var link    = document.getElementById('forgotLink');
            var overlay = document.getElementById('fpOverlay');
            var close   = document.getElementById('fpClose');

            // Step 1.
            var form  = document.getElementById('fpForm');
            var email = document.getElementById('fpEmail');
            var msg   = document.getElementById('fpMsg');
            var btn   = document.getElementById('fpBtn');
            var MSG_BASE = 'text-center text-[12.5px] ';

            // Step 2 (shared).
            var step2      = document.getElementById('fpStep2');
            var step2Email = document.getElementById('fpStep2Email');
            var editBtn    = document.getElementById('fpEditEmailBtn');
            var devBox     = document.getElementById('fpDevOtpBox');
            var devCode    = document.getElementById('fpDevOtpCode');

            // Step 2, Phase A — the OTP entry.
            var otpPhase   = document.getElementById('fpOtpPhase');
            var otpGroup   = document.getElementById('fpOtpBoxes');
            var boxes      = Array.prototype.slice.call(otpGroup.querySelectorAll('.otp-box'));
            var otpErr     = document.getElementById('fpOtpErr');
            var resendBtn  = document.getElementById('fpResendBtn');
            var resendMsg  = document.getElementById('fpResendMsg');

            // Step 2, Phase B — the new password, revealed once the code verifies.
            var pwPhase  = document.getElementById('fpPasswordPhase');
            var pw1      = document.getElementById('fpPw1');
            if (window.attachPwStrength) window.attachPwStrength(pw1);
            var pw2      = document.getElementById('fpPw2');
            var matchMsg = document.getElementById('fpMatchMsg');
            if (window.wirePwMatch) window.wirePwMatch(pw1, pw2, matchMsg);
            var resetBtn = document.getElementById('fpResetBtn');

            var reduceMotion = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

            var currentEmail   = '';
            var verifiedOtp    = '';
            var countdownTimer = null;

            function otpValue() { return boxes.map(function (b) { return b.value; }).join(''); }
            function pop(box) {
                if (reduceMotion) return;
                box.classList.remove('anim-pop');
                void box.offsetWidth;
                box.classList.add('anim-pop');
            }
            function updateResetBtn() {
                var pwOk = window.isStrongPassword(pw1.value) && pw2.value !== '' && pw1.value === pw2.value;
                matchMsg.classList.toggle('hidden', !(pw2.value !== '' && pw1.value !== pw2.value));
                resetBtn.disabled = !pwOk;
            }
            function clearOtpBoxes() {
                boxes.forEach(function (b) { b.value = ''; b.classList.remove('otp-filled'); b.readOnly = false; });
                boxes[0].focus();
            }
            function showOtpPhase() {
                pwPhase.classList.add('hidden');
                pwPhase.classList.remove('flex');
                otpPhase.classList.remove('hidden');
                otpPhase.classList.add('flex');
                clearOtpBoxes();
            }
            function showPasswordPhase() {
                otpPhase.classList.add('hidden');
                otpPhase.classList.remove('flex');
                pwPhase.classList.remove('hidden');
                pwPhase.classList.add('flex');
                pw1.value = '';
                pw2.value = '';
                updateResetBtn();
                pw1.focus();
            }

            function showStep1() {
                form.classList.remove('hidden');
                form.classList.add('flex');
                step2.classList.add('hidden');
                step2.classList.remove('flex');
                clearInterval(countdownTimer);
            }
            function showStep2(addr) {
                currentEmail = addr;
                verifiedOtp  = '';
                step2Email.textContent = addr;
                form.classList.add('hidden');
                form.classList.remove('flex');
                step2.classList.remove('hidden');
                step2.classList.add('flex');
                otpErr.classList.add('hidden');
                matchMsg.classList.add('hidden');
                resendMsg.classList.add('hidden');
                showOtpPhase();
                startResendCountdown();
            }

            function open(e) {
                e.preventDefault();
                overlay.classList.remove('hidden');
                overlay.classList.add('flex');
                showStep1();
                email.focus();
            }
            function shut() {
                overlay.classList.add('hidden');
                overlay.classList.remove('flex');
                clearInterval(countdownTimer);
            }

            link.addEventListener('click', open);
            // The duplicate-account prompt opens this with the sign-up e-mail.
            window.openForgotPw = function (addr) {
                open({ preventDefault: function () {} });
                if (addr) email.value = addr;
                email.focus();
            };
            close.addEventListener('click', shut);
            overlay.addEventListener('click', function (e) { if (e.target === overlay) shut(); });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !overlay.classList.contains('hidden')) shut();
            });

            // Close the modal and open the Create Account form with the
            // e-mail filled in.
            function goSignUp(addr) {
                shut();
                var sw = document.querySelector('[data-switch="register"]');
                if (sw) sw.click();
                var re = document.querySelector('#signupPanel input[name="email"]');
                if (re) {
                    re.value = addr;
                    re.dispatchEvent(new Event('input', { bubbles: true }));
                    re.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            function requestCode(addr) {
                return fetch('/dgt_dental_clinic/api/request_password_reset.php', {
                    method: 'POST', body: new URLSearchParams({ email: addr }),
                }).then(function (r) { return r.json(); });
            }

            // Step 1 submit — request the code, then transition in place to Step 2.
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var addr = email.value.trim();
                btn.disabled = true;
                btn.textContent = 'Sending...';
                msg.classList.add('hidden');

                requestCode(addr)
                    .then(function (d) {
                        btn.disabled = false;
                        btn.textContent = 'Send Reset Code';
                        if (!d || !d.ok) {
                            msg.classList.remove('hidden');
                            msg.className = MSG_BASE + 'text-danger';
                            msg.textContent = (d && d.error) || 'Something went wrong. Please try again.';
                            // No account at all: offer the sign-up form (2026-10-17).
                            if (d && d.not_registered) {
                                var su = document.createElement('button');
                                su.type = 'button';
                                su.className = 'mt-1 block w-full cursor-pointer appearance-none border-0 bg-transparent p-0 text-center text-[14px] font-semibold underline underline-offset-2 <?= AUTH_LINK ?>';
                                su.textContent = 'Create an account with this e-mail';
                                su.addEventListener('click', function () { goSignUp(addr); });
                                msg.appendChild(su);
                            }
                            email.focus();
                            return;
                        }
                        if (d && d.dev_otp) {
                            devCode.textContent = d.dev_otp;
                            devBox.classList.remove('hidden');
                        } else {
                            devBox.classList.add('hidden');
                        }
                        showStep2(addr);
                    })
                    .catch(function () {
                        btn.disabled = false;
                        btn.textContent = 'Send Reset Code';
                        msg.classList.remove('hidden');
                        msg.className = MSG_BASE + 'text-danger';
                        msg.textContent = 'Network error. Please try again.';
                    });
            });

            // "Edit Email" inside Step 2 — back to Step 1 with the address kept.
            editBtn.addEventListener('click', function () {
                var addr = currentEmail;
                showStep1();
                email.value = addr;
                email.focus();
                email.select();
            });

            function flashOtpError(text) {
                otpErr.textContent = text;
                otpErr.classList.remove('hidden');
                boxes.forEach(function (b) { b.readOnly = false; });
                if (reduceMotion) { clearOtpBoxes(); return; }
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
                    clearOtpBoxes();
                }, 450);
            }

            // Auto-verify the code the instant all 6 digits are entered — the
            // code is only CHECKED here (api/check_reset_otp.php never
            // touches the DB), so a wrong guess can simply be retried.
            function tryVerifyOtp() {
                var otp = otpValue();
                if (otp.length !== boxes.length) return;
                otpErr.classList.add('hidden');
                boxes.forEach(function (b) { b.readOnly = true; });

                fetch('/dgt_dental_clinic/api/check_reset_otp.php', {
                    method: 'POST', body: new URLSearchParams({ email: currentEmail, otp: otp }),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d && d.ok) {
                            verifiedOtp = otp;
                            if (reduceMotion) { showPasswordPhase(); return; }
                            var BOUNCE_STAGGER = 55, BOUNCE_DURATION = 420;
                            boxes.forEach(function (b, idx) {
                                setTimeout(function () { b.classList.add('anim-success'); }, idx * BOUNCE_STAGGER);
                            });
                            setTimeout(showPasswordPhase, (boxes.length - 1) * BOUNCE_STAGGER + BOUNCE_DURATION + 150);
                            return;
                        }
                        flashOtpError((d && d.error) ? d.error : 'Something went wrong. Please try again.');
                    })
                    .catch(function () {
                        flashOtpError('Network error. Please try again.');
                    });
            }

            // OTP boxes: auto-advance, backspace/arrow nav, paste, auto-verify.
            boxes.forEach(function (box, i) {
                box.addEventListener('input', function () {
                    if (takePaste(box)) return;
                    box.value = box.value.replace(/\D/g, '').slice(-1);
                    box.classList.toggle('otp-filled', !!box.value);
                    if (box.value) pop(box);
                    if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
                    tryVerifyOtp();
                });
                box.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace' && !box.value && i > 0) boxes[i - 1].focus();
                    else if (e.key === 'ArrowLeft' && i > 0) boxes[i - 1].focus();
                    else if (e.key === 'ArrowRight' && i < boxes.length - 1) boxes[i + 1].focus();
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
                    setTimeout(tryVerifyOtp, digits.length * 60 + 80);
                    return true;
                }
            });
            pw1.addEventListener('input', updateResetBtn);
            pw2.addEventListener('input', updateResetBtn);

            // Password-phase submit — actually resets the password and
            // consumes the already-verified code, then signs the account in.
            // Re-validates the code server-side too, so if it was somehow
            // invalidated between verifying and this submit (e.g. a resend
            // fired from another tab), this falls back to the OTP phase
            // instead of silently failing.
            resetBtn.addEventListener('click', function () {
                if (resetBtn.disabled) return;
                resetBtn.disabled = true;
                resetBtn.textContent = 'Resetting...';

                var body = new URLSearchParams({ email: currentEmail, otp: verifiedOtp, password: pw1.value });
                fetch('/dgt_dental_clinic/api/reset_password_otp.php', { method: 'POST', body: body })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d && d.ok) {
                            resetBtn.textContent = 'Signing you in...';
                            window.location.href = d.redirect || '/dgt_dental_clinic/login';
                            return;
                        }
                        resetBtn.textContent = 'Reset Password & Sign In';
                        resetBtn.disabled = false;
                        showOtpPhase();
                        flashOtpError((d && d.error) ? d.error : 'Something went wrong. Please try again.');
                    })
                    .catch(function () {
                        resetBtn.textContent = 'Reset Password & Sign In';
                        resetBtn.disabled = false;
                        showOtpPhase();
                        flashOtpError('Network error. Please try again.');
                    });
            });

            // 60-second resend countdown — "Resend (0:60)" counting down to "Resend".
            function fmtCountdown(sec) { return '0:' + (sec < 10 ? '0' : '') + sec; }
            function startResendCountdown() {
                var remaining = 60;
                resendBtn.disabled = true;
                resendBtn.textContent = 'Resend (' + fmtCountdown(remaining) + ')';
                clearInterval(countdownTimer);
                countdownTimer = setInterval(function () {
                    remaining--;
                    if (remaining <= 0) {
                        clearInterval(countdownTimer);
                        resendBtn.disabled = false;
                        resendBtn.textContent = 'Resend';
                    } else {
                        resendBtn.textContent = 'Resend (' + fmtCountdown(remaining) + ')';
                    }
                }, 1000);
            }
            resendBtn.addEventListener('click', function () {
                if (resendBtn.disabled || !currentEmail) return;
                resendBtn.disabled = true;
                requestCode(currentEmail)
                    .then(function (d) {
                        resendMsg.classList.remove('hidden');
                        if (!d || !d.ok) {
                            resendMsg.className = 'text-center text-[12.5px] text-danger';
                            resendMsg.textContent = (d && d.error) || 'Something went wrong. Please try again.';
                            startResendCountdown();
                            return;
                        }
                        resendMsg.className = 'text-center text-[12.5px] text-ok';
                        resendMsg.textContent = 'A new code has been sent to your e-mail.';
                        if (d && d.dev_otp) {
                            devCode.textContent = d.dev_otp;
                            devBox.classList.remove('hidden');
                        }
                        startResendCountdown();
                    })
                    .catch(function () {
                        resendMsg.classList.remove('hidden');
                        resendMsg.className = 'text-center text-[12.5px] text-danger';
                        resendMsg.textContent = 'Network error. Please try again.';
                        startResendCountdown();
                    });
            });
        })();

        // Registration-success screen: inline 6-box OTP entry, editable e-mail,
        // and a 60s resend countdown — a near-literal port of verify.php's own
        // OTP script, condensed since the e-mail here is already known (no
        // separate email <input> to manage) and extended with the edit-email
        // panel + a resend countdown + auto-sign-in on a successful verify.
        (function () {
            var boxes = Array.prototype.slice.call(document.querySelectorAll('#regOtpBoxes .otp-box'));
            if (!boxes.length) return;

            var otpGroup   = document.getElementById('regOtpBoxes');
            var err        = document.getElementById('regOtpErr');
            var verifyBtn  = document.getElementById('regVerifyBtn');
            var emailText  = document.getElementById('regEmailText');
            var editBtn    = document.getElementById('regEditEmailBtn');
            var editForm   = document.getElementById('regEditEmailForm');
            var editInput  = document.getElementById('regEditEmailInput');
            var editSave   = document.getElementById('regEditEmailSave');
            var editCancel = document.getElementById('regEditEmailCancel');
            var editMsg    = document.getElementById('regEditEmailMsg');
            var resendBtn  = document.getElementById('regResendBtn');
            var resendMsg  = document.getElementById('regResendMsg');
            var devBox     = document.getElementById('regDevOtpBox');
            var devCode    = document.getElementById('regDevOtpCode');
            var reduceMotion = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

            var currentEmail = resendBtn.dataset.email;

            function otpValue() { return boxes.map(function (b) { return b.value; }).join(''); }
            function updateVerifyBtn() { verifyBtn.disabled = otpValue().length !== boxes.length; }
            function clearBoxes() {
                boxes.forEach(function (b) { b.value = ''; b.classList.remove('otp-filled'); });
                boxes[0].focus();
                updateVerifyBtn();
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
                // Grace period so the just-typed 6th digit's own pop finishes
                // before the loading pulse cuts it off — same fix verify.php
                // needed for the identical race.
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
            function flashError(msg) {
                stopLoading();
                err.textContent = msg;
                err.classList.remove('hidden');
                verifyBtn.textContent = 'Verify & Sign In';
                updateVerifyBtn();
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

            function doVerify() {
                if (otpValue().length !== boxes.length) return;
                var otp = otpValue();
                err.classList.add('hidden');
                verifyBtn.disabled = true;
                verifyBtn.textContent = 'Verifying...';
                startLoading();

                // A local/dev request resolves in a few ms — hold the loading
                // pulse open at least this long so it's actually perceptible.
                var MIN_LOADING_MS = reduceMotion ? 0 : 650;
                var requestStartedAt = Date.now();
                function afterMinLoading(fn) {
                    var remaining = MIN_LOADING_MS - (Date.now() - requestStartedAt);
                    if (remaining > 0) { setTimeout(fn, remaining); } else { fn(); }
                }

                var body = new URLSearchParams({ email: currentEmail, otp: otp });
                fetch('/dgt_dental_clinic/api/verify_otp.php', { method: 'POST', body: body })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        afterMinLoading(function () {
                            if (d && d.ok) {
                                stopLoading();
                                verifyBtn.textContent = 'Signing you in...';
                                var finish = function () {
                                    window.location.href = d.redirect || '/dgt_dental_clinic/login';
                                };
                                if (reduceMotion) { finish(); return; }
                                var BOUNCE_STAGGER = 55, BOUNCE_DURATION = 420;
                                boxes.forEach(function (b, idx) {
                                    setTimeout(function () { b.classList.add('anim-success'); }, idx * BOUNCE_STAGGER);
                                });
                                // Wait for the LAST box's bounce to actually finish
                                // before navigating away, same reasoning as verify.php.
                                setTimeout(finish, (boxes.length - 1) * BOUNCE_STAGGER + BOUNCE_DURATION + 250);
                                return;
                            }
                            flashError((d && d.error) ? d.error : 'Something went wrong. Please try again.');
                        });
                    })
                    .catch(function () {
                        afterMinLoading(function () { flashError('Network error. Please try again.'); });
                    });
            }

            boxes.forEach(function (box, i) {
                box.addEventListener('input', function () {
                    if (takePaste(box)) return;
                    box.value = box.value.replace(/\D/g, '').slice(-1);
                    box.classList.toggle('otp-filled', !!box.value);
                    if (box.value) pop(box);
                    if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
                    updateVerifyBtn();
                    if (otpValue().length === boxes.length) doVerify();
                });
                box.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace' && !box.value && i > 0) boxes[i - 1].focus();
                    else if (e.key === 'ArrowLeft' && i > 0) boxes[i - 1].focus();
                    else if (e.key === 'ArrowRight' && i < boxes.length - 1) boxes[i + 1].focus();
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
                    updateVerifyBtn();
                    setTimeout(function () { if (otpValue().length === boxes.length) doVerify(); }, digits.length * 60 + 80);
                    return true;
                }
            });

            verifyBtn.addEventListener('click', doVerify);
            updateVerifyBtn();
            // Auto-focus the first box the instant this screen renders.
            boxes[0].focus();

            // Edit e-mail — lets a typo be fixed without re-registering. Gated
            // server-side to this exact browser session (see
            // api/update_pending_email.php), not just knowledge of the address.
            editBtn.addEventListener('click', function () {
                editForm.classList.remove('hidden');
                editForm.classList.add('flex', 'flex-wrap', 'items-center', 'gap-2');
                editBtn.classList.add('hidden');
                editInput.focus();
                editInput.select();
            });
            editCancel.addEventListener('click', function () {
                editForm.classList.add('hidden');
                editForm.classList.remove('flex', 'flex-wrap', 'items-center', 'gap-2');
                editBtn.classList.remove('hidden');
                editMsg.classList.add('hidden');
                editInput.value = currentEmail;
            });
            editSave.addEventListener('click', function () {
                var val = editInput.value.trim();
                if (!val) { editInput.focus(); return; }
                editSave.disabled = true;
                editSave.textContent = 'Saving...';
                fetch('/dgt_dental_clinic/api/update_pending_email.php', {
                    method: 'POST', body: new URLSearchParams({ email: val }),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        editSave.disabled = false;
                        editSave.textContent = 'Save & Resend';
                        editMsg.classList.remove('hidden');
                        if (d && d.ok) {
                            currentEmail = d.email;
                            emailText.textContent = d.email;
                            resendBtn.dataset.email = d.email;
                            editMsg.className = 'mt-2 text-[13px] ' + (d.mail_failed ? 'text-danger' : 'text-ok');
                            editMsg.textContent = d.mail_failed ? d.message : 'E-mail updated. A new code was sent.';
                            editForm.classList.add('hidden');
                            editForm.classList.remove('flex', 'flex-wrap', 'items-center', 'gap-2');
                            editBtn.classList.remove('hidden');
                            clearBoxes();
                            if (d.dev_otp) {
                                devCode.textContent = d.dev_otp;
                                devBox.classList.remove('hidden');
                            }
                            startResendCountdown();
                        } else {
                            editMsg.className = 'mt-2 text-[13px] text-danger';
                            editMsg.textContent = (d && d.error) ? d.error : 'Could not update e-mail. Please try again.';
                        }
                    })
                    .catch(function () {
                        editSave.disabled = false;
                        editSave.textContent = 'Save & Resend';
                        editMsg.classList.remove('hidden');
                        editMsg.className = 'mt-2 text-[13px] text-danger';
                        editMsg.textContent = 'Network error. Please try again.';
                    });
            });

            // 60-second resend countdown.
            var countdownTimer = null;
            function startResendCountdown() {
                var remaining = 60;
                resendBtn.disabled = true;
                resendBtn.textContent = 'Resend it (' + remaining + 's)';
                clearInterval(countdownTimer);
                countdownTimer = setInterval(function () {
                    remaining--;
                    if (remaining <= 0) {
                        clearInterval(countdownTimer);
                        resendBtn.disabled = false;
                        resendBtn.textContent = 'Resend it';
                    } else {
                        resendBtn.textContent = 'Resend it (' + remaining + 's)';
                    }
                }, 1000);
            }
            resendBtn.addEventListener('click', function () {
                if (resendBtn.disabled) return;
                resendBtn.disabled = true;
                fetch('/dgt_dental_clinic/api/request_verification.php', {
                    method: 'POST', body: new URLSearchParams({ email: currentEmail }),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        resendMsg.classList.remove('hidden');
                        if (!d || !d.ok) {
                            resendMsg.className = 'mt-2 text-center text-[13.5px] text-danger';
                            resendMsg.textContent = (d && d.error) ? d.error : 'Could not send a new code. Please try again.';
                            startResendCountdown();
                            return;
                        }
                        resendMsg.className = 'mt-2 text-center text-[13.5px] text-ok';
                        resendMsg.textContent = 'A new verification code has been sent to your e-mail.';
                        if (d && d.dev_otp) {
                            devCode.textContent = d.dev_otp;
                            devBox.classList.remove('hidden');
                        }
                        startResendCountdown();
                    })
                    .catch(function () {
                        resendMsg.classList.remove('hidden');
                        resendMsg.className = 'mt-2 text-center text-[13.5px] text-danger';
                        resendMsg.textContent = 'Network error. Please try again.';
                        startResendCountdown();
                    });
            });
            startResendCountdown();
        })();

        (function () {
            var form   = document.getElementById('regForm');
            if (!form) return;
            // Names: letters, spaces, hyphens, apostrophes, periods only (2026-10-18;
            // server: validPersonName()). This page doesn't load app.js.
            [form.first_name, form.last_name].forEach(function (el) {
                el.setAttribute('maxlength', '50');
                el.addEventListener('input', function () {
                    var c = el.value.replace(/[^\p{L}\p{M} .'-]+/gu, '').replace(/^[^\p{L}]+/u, '').replace(/ {2,}/g, ' ');
                    if (c !== el.value) el.value = c;
                });
            });
            var submit = document.getElementById('submitBtn');
            var pwHint = document.getElementById('pwHint');
            if (window.wirePwMatch) window.wirePwMatch(form.password, form.confirm_password, document.getElementById('pwMatchMsg'));
            var pwStrengthLabel = document.getElementById('pwStrengthLabel');
            var pwSegs = document.querySelectorAll('#pwMeter .pw-seg');
            var tcCheck = document.getElementById('tcCheck');

            function emailOk(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
            function phoneOk(v) { return /^09\d{9}$/.test(v); }

            // Digits-only for the contact number (app.js isn't loaded on public pages).
            form.contact_number.addEventListener('input', function () {
                var d = this.value.replace(/\D+/g, '').slice(0, 11);
                if (d !== this.value) this.value = d;
            });

            // Red "*" right after each required field's placeholder text
            // (placeholders can't be coloured in part). Hidden once typed in.
            var measure = document.createElement('canvas').getContext('2d');
            function placeMarks() {
                form.querySelectorAll('.req-mark').forEach(function (m) {
                    var inp = m.parentElement.querySelector('input');
                    if (!inp) return;
                    var cs = getComputedStyle(inp);
                    measure.font = cs.fontWeight + ' ' + cs.fontSize + ' ' + cs.fontFamily;
                    m.style.left = (parseFloat(cs.paddingLeft) + parseFloat(cs.borderLeftWidth) + measure.measureText(inp.placeholder).width + 3) + 'px';
                    m.style.fontSize = cs.fontSize;
                });
            }
            placeMarks();
            window.addEventListener('resize', placeMarks);
            if (document.fonts && document.fonts.ready) document.fonts.ready.then(placeMarks);

            // Mirrors the server's isStrongPassword() in includes/functions.php.
            function passwordChecks(pw) {
                return {
                    len: pw.length >= 8,
                    lower: /[a-z]/.test(pw),
                    upper: /[A-Z]/.test(pw),
                    digit: /[0-9]/.test(pw),
                    special: /[^a-zA-Z0-9]/.test(pw)
                };
            }
            // A segmented strength meter (the pattern most sign-up forms use
            // now — Dropbox, GitHub, most SaaS onboarding — rather than a
            // plain rules paragraph): the count of the 5 checks above maps to
            // how many of 4 bar segments light up plus a Weak/Fair/Good/
            // Strong word. Colour AND the word move together — never colour
            // alone — so it still reads correctly for a colour-blind visitor.
            var PW_METER = {
                1: { segs: 1, label: 'Weak',   bar: 'bg-danger',       text: 'text-danger' },
                2: { segs: 1, label: 'Weak',   bar: 'bg-danger',       text: 'text-danger' },
                3: { segs: 2, label: 'Fair',   bar: 'bg-warn',         text: 'text-warn' },
                4: { segs: 3, label: 'Good',   bar: 'bg-dgt-teal-500', text: 'text-dgt-teal-600' },
                5: { segs: 4, label: 'Strong', bar: 'bg-ok',           text: 'text-ok' }
            };
            function renderPwMeter(pw, c) {
                var score = (c.len ? 1 : 0) + (c.lower ? 1 : 0) + (c.upper ? 1 : 0) + (c.digit ? 1 : 0) + (c.special ? 1 : 0);
                var cfg = pw.length ? (PW_METER[score] || PW_METER[1]) : null;
                pwSegs.forEach(function (seg, i) {
                    seg.className = 'pw-seg h-1 flex-1 rounded-full transition-colors ' + ((cfg && i < cfg.segs) ? cfg.bar : 'bg-steel-200');
                });
                pwStrengthLabel.textContent = cfg ? cfg.label : '';
                pwStrengthLabel.className = 'shrink-0 font-semibold ' + (cfg ? cfg.text : '');
            }
            function refresh() {
                var pw = form.password.value;
                var c = passwordChecks(pw);
                var pwOk = c.len && c.lower && c.upper && c.digit && c.special;
                renderPwMeter(pw, c);
                if (pw.length === 0) {
                    pwHint.textContent = 'Use 8+ characters with uppercase, lowercase, a number, and a symbol.';
                } else if (pwOk) {
                    pwHint.textContent = '✓ Meets all requirements.';
                } else {
                    var missing = [];
                    if (!c.len) missing.push('8+ characters');
                    if (!c.upper) missing.push('an uppercase letter');
                    if (!c.lower) missing.push('a lowercase letter');
                    if (!c.digit) missing.push('a number');
                    if (!c.special) missing.push('a special character');
                    pwHint.textContent = 'Add: ' + missing.join(', ') + '.';
                }

                var ok = form.first_name.value.trim() !== ''
                      && form.last_name.value.trim() !== ''
                      && form.bd_month.value !== '' && form.bd_day.value !== '' && form.bd_year.value !== ''
                      && emailOk(form.email.value.trim())
                      && phoneOk(form.contact_number.value.trim())
                      && pwOk
                      && form.confirm_password.value === pw
                      && tcCheck.checked;
                var ph = form.contact_number.value.trim();
                document.getElementById('phoneMsg').classList.toggle('hidden', ph === '' || phoneOk(ph));
                form.contact_number.classList.toggle('is-invalid', ph !== '' && !phoneOk(ph));
                var cpw = form.confirm_password.value;
                document.getElementById('pwMatchMsg').classList.toggle('hidden', cpw === '' || cpw === pw);
                submit.disabled = !ok;
            }

            // Terms of Service / Privacy Policy are plain links to terms.php
            // (opened in a new tab so the half-filled form is kept); the actual
            // consent is the #tcCheck checkbox above.

            form.addEventListener('input', refresh);
            refresh();
        })();
    </script>
    <script src="<?= e(asset('assets/js/password-toggle.js')) ?>"></script>
    <script>
    // Proper capitalization of the sign-up names when leaving the field
    // ("rex" -> "Rex"). Same rule as properCase() in includes/functions.php,
    // which still applies it on save.
    (function () {
      function properCase(s) {
        return String(s || '').trim().replace(/\s+/g, ' ').split(' ').map(function (w) {
          if (!w || /\d/.test(w)) return w;
          if (/^(II|III|IV|VI|VII|VIII|IX)\.?$/i.test(w)) return w.toUpperCase();
          var lo = w.toLowerCase();
          if (w !== w.toUpperCase() && w !== lo) return w;
          return lo.replace(/(^|[-'\u2019(/])(\p{L})/gu, function (m, a, b) { return a + b.toUpperCase(); });
        }).join(' ');
      }
      document.addEventListener('focusout', function (e) {
        var el = e.target;
        if (!el || el.tagName !== 'INPUT' || !/^(first|middle|last)_name$/.test(el.name)) return;
        var v = properCase(el.value);
        if (v !== el.value) { el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); }
      });
    })();
    </script>
</body>
</html>
