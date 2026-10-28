<?php
/**
 * Shared helpers: output escaping, JSON responses, settings, notifications.
 */

declare(strict_types=1);

// Days of notice an ONLINE booking needs: 1 (changed from 2, 2026-10-11) while the admin's "Enforce 1-Day
// Advance Booking" switch is on (Settings > Booking Rules, setting
// enforce_two_day_advance, default on), else 0 (same-day). Walk-ins and
// reschedules are never affected. See onlineLeadDays() below.
// true = a time slot that has already started today can't be booked (shown as
// "Passed"). ON again (2026-10-04, at the user's request).
if (!defined('BLOCK_PASSED_SLOTS')) define('BLOCK_PASSED_SLOTS', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/health_form.php';

/** Days of notice an online booking needs (2, or 0 when the admin turned the rule off). */
/**
 * Patient online-booking limits (2026-10-18): one appointment per date per
 * account, and no date more than ONLINE_MAX_MONTHS months ahead (also for a
 * patient's own reschedule).
 * Staff bookings and walk-ins are not limited.
 */
const ONLINE_MAX_MONTHS  = 6;
/**
 * Dates (Y-m-d) on which one PERSON already has a live booking (one per day
 * per person, 2026-10-20). $treatedId = 0 means the account holder's own
 * bookings ("For Myself"); otherwise the person booked "For Someone Else", so
 * the account can book itself and someone else on the same day.
 */
function patientBookedDates(int $uid, int $excludeId = 0, int $treatedId = 0): array
{
    $who = $treatedId > 0 ? 'treated_patient_id = ?' : "patient_id = ? AND booking_for <> 'other'";
    $st = getDB()->prepare("SELECT DISTINCT scheduled_date FROM appointments WHERE $who AND appointment_id <> ?
        AND status IN ('pending','approved','rescheduled','payment_declined') AND scheduled_date >= CURDATE()");
    $st->execute([$treatedId > 0 ? $treatedId : $uid, $excludeId]);
    return array_map(fn ($d) => substr((string) $d, 0, 10), $st->fetchAll(PDO::FETCH_COLUMN));
}
function onlineLatestDate(string $today): string
{
    return date('Y-m-d', strtotime($today . ' +' . ONLINE_MAX_MONTHS . ' months'));
}

/**
 * TEMPORARY admin switch (Settings > Booking Rules, `sunday_open`): when on,
 * Sundays are bookable like any other day and dentists work their Saturday
 * hours on them. Off (default) = closed every Sunday.
 */
function sundayOpen(): bool
{
    static $o = null;
    if ($o === null) {
        try { $o = setting('sunday_open', '0') === '1'; }
        catch (Throwable $e) { $o = false; }
    }
    return $o;
}

/** Weekday to read from the weekly grid: an open Sunday uses Saturday's hours. */
function scheduleDow(string $date): int
{
    $dow = (int) date('w', strtotime($date));
    return ($dow === 0 && sundayOpen()) ? 6 : $dow;
}

function onlineLeadDays(): int
{
    static $d = null;
    if ($d === null) {
        try { $d = setting('enforce_two_day_advance', '1') !== '0' ? 1 : 0; }
        catch (Throwable $e) { $d = 1; }
    }
    return $d;
}

/**
 * Latest slot start (HH:MM) an online booking can no longer take today: now +
 * 60 minutes on the DB clock. A slot at or before this is closed for patients.
 */
function onlineCutoffHm(): string
{
    static $c = null;
    if ($c === null) {
        $r = getDB()->query("SELECT DATE(NOW() + INTERVAL 60 MINUTE) > CURDATE() AS late,
                                    DATE_FORMAT(NOW() + INTERVAL 60 MINUTE, '%H:%i') AS t")->fetch();
        $c = !empty($r['late']) ? '23:59' : (string) $r['t'];   // past 11 PM: rest of today is closed
    }
    return $c;
}

/*
 * Brute-force protection (migration 087, 2026-10-23). After THROTTLE_MAX_FAILS
 * wrong tries for one username / e-mail, or THROTTLE_MAX_IP_FAILS from one IP
 * address, within THROTTLE_WINDOW_MIN minutes, that scope is locked until the
 * window has passed since the last failure. All times on the DB clock; every
 * helper fails open (never blocks a sign-in) if the table is missing.
 */
const THROTTLE_MAX_FAILS    = 5;
const THROTTLE_MAX_IP_FAILS = 20;
const THROTTLE_WINDOW_MIN   = 15;

function throttleIp(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** Seconds until $scope / $ident may be tried again, or 0 when not locked. */
function throttleLocked(string $scope, string $ident): int
{
    try {
        $w = THROTTLE_WINDOW_MIN;
        $sql = "SELECT COUNT(*) n, TIMESTAMPDIFF(SECOND, NOW(), MAX(created_at) + INTERVAL $w MINUTE) left_s
                FROM login_attempts WHERE scope = ? AND %s = ? AND created_at > NOW() - INTERVAL $w MINUTE";
        $st = getDB()->prepare(sprintf($sql, 'ident'));
        $st->execute([$scope, mb_strtolower(trim($ident))]);
        $a = $st->fetch();
        $b = ['n' => 0, 'left_s' => 0];
        $ip = throttleIp();
        if ($ip !== '') {
            $st = getDB()->prepare(sprintf($sql, 'ip'));
            $st->execute([$scope, $ip]);
            $b = $st->fetch();
        }
        $left = 0;
        if ((int) $a['n'] >= THROTTLE_MAX_FAILS)    $left = max($left, (int) $a['left_s']);
        if ((int) $b['n'] >= THROTTLE_MAX_IP_FAILS) $left = max($left, (int) $b['left_s']);
        return max(0, $left);
    } catch (Throwable $e) {
        return 0;
    }
}

/** Record one failed try; returns how many tries are left before the lock. */
function throttleFail(string $scope, string $ident): int
{
    try {
        $id = mb_strtolower(trim($ident));
        getDB()->prepare('INSERT INTO login_attempts (scope, ident, ip) VALUES (?, ?, ?)')
            ->execute([$scope, $id, throttleIp()]);
        if (random_int(1, 50) === 1) {   // old rows only matter inside the window
            getDB()->exec('DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 1 DAY');
        }
        $w = THROTTLE_WINDOW_MIN;
        $st = getDB()->prepare(
            "SELECT COUNT(*) FROM login_attempts WHERE scope = ? AND ident = ? AND created_at > NOW() - INTERVAL $w MINUTE"
        );
        $st->execute([$scope, $id]);
        return max(0, THROTTLE_MAX_FAILS - (int) $st->fetchColumn());
    } catch (Throwable $e) {
        return THROTTLE_MAX_FAILS;
    }
}

/** A successful try clears that username / e-mail's failures. */
function throttleClear(string $scope, string $ident): void
{
    try {
        getDB()->prepare('DELETE FROM login_attempts WHERE scope = ? AND ident = ?')
            ->execute([$scope, mb_strtolower(trim($ident))]);
    } catch (Throwable $e) {
    }
}

/** "Please try again in 14 minutes." */
function throttleWaitText(int $seconds): string
{
    $m = max(1, (int) ceil($seconds / 60));
    return 'Please try again in ' . $m . ' minute' . ($m === 1 ? '' : 's') . '.';
}

/** HTML-escape for safe output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Markup for the reveal-password eye button used next to a password
 * `<input>`. The caller must wrap both the input and this button in one
 * `.pw-field.relative` element and give the input `pr-10` — see
 * assets/js/password-toggle.js for the click-delegated wiring (loaded via
 * includes/layout.php for app pages, and directly via <script src> on the
 * standalone auth pages).
 */
function pwToggleBtn(): string
{
    return <<<'HTML'
<button type="button" class="pw-toggle absolute right-1.5 top-1/2 -translate-y-1/2 rounded p-1.5 text-[#9aa3b4] hover:text-[#1b2a4a] dark:text-steel-500 dark:hover:text-steel-100" aria-label="Show password" aria-pressed="false">
    <svg class="pw-eye hidden h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12Z"/>
        <circle cx="12" cy="12" r="3"/>
    </svg>
    <svg class="pw-eye-off h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 3l18 18"/>
        <path d="M10.6 5.2A10.9 10.9 0 0 1 12 5c7 0 10.5 7 10.5 7a13.4 13.4 0 0 1-3.2 4.1M6.6 6.6C3.6 8.5 1.5 12 1.5 12s3.5 7 10.5 7a10.6 10.6 0 0 0 4.6-1"/>
        <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
    </svg>
</button>
HTML;
}

/** Peso currency formatting. */
/**
 * A service's usual time as patient-facing text: "about 45 min", "about 1 hr",
 * "about 1 hr 30 min", plus " per tooth" for a per-unit service. Mirrors
 * window.fmtDuration() in app.js.
 */
function fmtDuration(int|string|null $minutes, ?string $unit = null): string
{
    $m = (int) $minutes;
    if ($m <= 0) {
        return '';
    }
    $h = intdiv($m, 60);
    $r = $m % 60;
    $t = $h ? $h . ' hr' . ($r ? ' ' . $r . ' min' : '') : $r . ' min';
    return 'about ' . $t . ($unit ? ' per ' . $unit : '');
}

function peso(float|int|string|null $amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

/** Digits only, from a possibly formatted phone / GCash number. */
function digitsOnly(?string $s): string
{
    return preg_replace('/\D+/', '', (string) $s) ?? '';
}

/** A valid Philippine mobile number: exactly 11 digits, starting 09. */
function isPhMobile(string $s): bool
{
    return preg_match('/^09\d{9}$/', $s) === 1;
}

/** Standard error string for a bad PH mobile number (mirrors PH_MOBILE_MSG in app.js). */
const PH_MOBILE_MSG = 'Please enter a valid 11-digit Philippine mobile number starting with 09 (e.g., 09171234567).';

/**
 * One phone number per account (2026-10-20). True when another account
 * already uses $phone. A person booked "For Someone Else" shares the booker's
 * number, so the booker and the people they booked for never block each other.
 */
const PHONE_TAKEN_MSG = 'This phone number is already used by another account.';

/**
 * Duplicate patient prevention (2026-10-22). Shown on sign-up when the e-mail
 * or mobile number already belongs to an account, or the same name + birthdate does.
 */
const DUPLICATE_RECORD_MSG = 'Record already exists. If you forgot your login details or credentials, please contact '
    . '09764484252 or customerservice@dgtonedentalph.com, or visit the clinic for account recovery.';

/**
 * An active patient with the same first + last name (any case) AND the same
 * birthdate, or null. A same-name patient with a different birthdate is a
 * different person. $excludeUid skips one account (a pending sign-up).
 */
function existingPatientRecord(string $first, string $last, string $dob, int $excludeUid = 0): ?array
{
    if ($first === '' || $last === '' || $dob === '') {
        return null;
    }
    $st = getDB()->prepare(
        "SELECT u.user_id, u.full_name, r.date_of_birth
         FROM users u JOIN patient_records r ON r.patient_id = u.user_id
         WHERE u.role = 'patient' AND u.is_active = 1 AND u.user_id <> ?
           AND LOWER(TRIM(u.first_name)) = LOWER(?) AND LOWER(TRIM(u.last_name)) = LOWER(?)
           AND r.date_of_birth = ?
         LIMIT 1"
    );
    $st->execute([$excludeUid, trim($first), trim($last), $dob]);
    return $st->fetch() ?: null;
}

function phoneInUse(string $phone, int $excludeUid = 0): bool
{
    $phone = digitsOnly($phone);
    if ($phone === '') return false;
    try {
        $st = getDB()->prepare(
            "SELECT 1 FROM users u WHERE u.contact_number = ? AND u.user_id <> ?
               AND NOT EXISTS (SELECT 1 FROM appointments a WHERE a.booking_for = 'other'
                   AND ((a.patient_id = ? AND a.treated_patient_id = u.user_id)
                     OR (a.patient_id = u.user_id AND a.treated_patient_id = ?)))
             LIMIT 1"
        );
        $st->execute([$phone, $excludeUid, $excludeUid, $excludeUid]);
        return (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/** For an edit: only a NEW number is checked, so old duplicates can still save. */
function phoneChangeTaken(string $phone, int $uid): bool
{
    $phone = digitsOnly($phone);
    if ($phone === '') return false;
    $cur = getDB()->prepare('SELECT contact_number FROM users WHERE user_id = ?');
    $cur->execute([$uid]);
    if (digitsOnly((string) $cur->fetchColumn()) === $phone) return false;
    return phoneInUse($phone, $uid);
}

/**
 * Validate a patient's Date of Birth (YYYY-MM-DD). Returns the date, or null
 * and sets $err. Age is never stored: it is always computed from this date
 * (TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) in SQL). "Today" is the DB clock.
 */
function validBirthDate(string $dob, ?string &$err = null): ?string
{
    $dob = trim($dob);
    $d = DateTime::createFromFormat('!Y-m-d', $dob);
    if (!$d || $d->format('Y-m-d') !== $dob) {
        $err = 'Enter a valid date of birth.';
        return null;
    }
    $today = (string) getDB()->query('SELECT CURDATE()')->fetchColumn();
    if ($dob > $today) {
        $err = 'The date of birth cannot be in the future.';
        return null;
    }
    if ($dob < date('Y-m-d', strtotime($today . ' -130 years'))) {
        $err = 'Enter a valid date of birth.';
        return null;
    }
    return $dob;
}

/** Whole years between a date of birth and today (DB clock); null without a date. */
/**
 * What a patient still has to fill in on My Profile (2026-10-17): phone,
 * date of birth, emergency contact and allergies ("None" counts as answered).
 * Returns the labels, in form order; [] when complete.
 */
function patientProfileMissing(int $uid): array
{
    try {
        $st = getDB()->prepare(
            'SELECT u.contact_number, pr.date_of_birth, pr.emergency_contact_name,
                    pr.emergency_contact_number, pr.allergies
             FROM users u LEFT JOIN patient_records pr ON pr.patient_id = u.user_id
             WHERE u.user_id = ?'
        );
        $st->execute([$uid]);
        $r = $st->fetch();
    } catch (Throwable $e) {
        return [];   // never block a sign-in on this
    }
    if (!$r) return [];
    // Emergency contact, allergies and the rest moved to the Patient
    // Information Record (2026-10-20); the profile only needs the phone.
    $missing = [];
    if (trim((string) $r['contact_number']) === '') $missing[] = 'Contact Number';
    return $missing;
}

/**
 * Where a patient lands after signing in: the Patient Information Record until
 * it is answered, then My Profile while the phone is missing, else Appointments.
 */
function patientLandingUrl(int $uid): string
{
    // Not answered yet, or its review (HF_REVIEW_MONTHS) is due (2026-10-20).
    if (!healthFormReady($uid)) return '/dgt_dental_clinic/modules/patient/health_form';
    return patientProfileMissing($uid) ? '/dgt_dental_clinic/modules/shared/profile' : roleHome('patient');
}

/**
 * Senior citizen / PWD discount IDs (2026-10-18).
 * - TIN (BIR): 9-digit base + optional 3- or 5-digit branch code, e.g.
 *   123-456-789 / 123-456-789-000 / 123-456-789-00000.
 * - PWD ID (DOH/NCDA registry): RR-PPMM-BBB-NNNNNNN, e.g. 13-7405-000-0001234
 *   (region, province + city, barangay, 7-digit sequence; older cards use 3).
 * - OSCA (senior citizen) ID: no national format (each city numbers its own),
 *   so 3 to 20 letters, digits and hyphens.
 */
const SC_MIN_AGE = 60;

/**
 * The main (owner) admin account: the oldest active admin, i.e. the lowest
 * active admin user_id (2026-10-18). No one can archive it; other admins can
 * archive each other but never themselves.
 */
function ownerAdminId(): int
{
    static $id = null;
    if ($id === null) {
        $id = (int) getDB()->query("SELECT MIN(user_id) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
    }
    return $id;
}
function validTin(string $s): bool  { return (bool) preg_match('/^\d{3}-\d{3}-\d{3}(-\d{3}|-\d{5})?$/', $s); }
function validPwdId(string $s): bool { return (bool) preg_match('/^\d{2}-\d{4}-\d{3}-(\d{7}|\d{3})$/', $s); }
function validOscaId(string $s): bool { return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{2,19}$/', $s); }

/**
 * A first / last name (2026-10-18): letters (accents and ñ included), spaces,
 * hyphens, apostrophes and periods only, starting with a letter, 2 to 50
 * characters. Mirrors the typing filter in app.js (window.cleanName).
 */
function validPersonName(string $s): bool
{
    $s = trim($s);
    return mb_strlen($s) >= 2 && mb_strlen($s) <= 50 && (bool) preg_match("/^\p{L}[\p{L}\p{M} .'-]*$/u", $s);
}
const PERSON_NAME_MSG = 'Names may only use letters, spaces, hyphens (-), apostrophes (\') and periods, 2 to 50 characters.';

/** Account holders (sign-up, admin-added staff + patients) must be 18 to 80 (2026-10-18). */
const STAFF_MIN_AGE = 18;
const ACCOUNT_MAX_AGE = 80;

/**
 * validBirthDate() plus the 18-to-80 account age range. Returns 'YYYY-MM-DD'
 * or null (with $err set). Used for sign-up and every admin-added account.
 */
function staffBirthDate(string $dob, ?string &$err = null): ?string
{
    $v = validBirthDate($dob, $err);
    if ($v === null) return null;
    $age = (int) ageFromBirthDate($v);
    if ($age < STAFF_MIN_AGE || $age > ACCOUNT_MAX_AGE) {
        $err = 'The account holder must be ' . STAFF_MIN_AGE . ' to ' . ACCOUNT_MAX_AGE . ' years old.';
        return null;
    }
    return $v;
}

/**
 * On an edit: a birthdate that is already on file stays valid even if it is
 * outside 18-80 (it can't be changed from the forms); a new one must be in range.
 */
function editBirthDate(string $dob, ?string $saved, ?string &$err = null): ?string
{
    $dob = trim($dob);
    if ($saved && $dob === substr($saved, 0, 10)) {
        return validBirthDate($dob, $err);
    }
    return staffBirthDate($dob, $err);
}

/** Age range for a new walk-in patient (2026-10-18, user request). */
const WALKIN_MIN_AGE = 1;
const WALKIN_MAX_AGE = 80;
// "For Someone Else" online bookings are for children only (2026-10-23): an
// adult (18 or older) can create their own account.
const OTHER_MAX_AGE = 17;
const OTHER_ADULT_MSG = 'For My Child is only for children under 18. A person 18 or older can create their own account and book for themselves.';

/** Minutes after an approved visit's start before a patient who has not
 *  checked in becomes "Delayed / Standby" and the seat goes to walk-ins. */
const GRACE_MINUTES = 15;

/** A row that holds a seat in its hour: live status, and not a delayed
 *  patient who hasn't arrived (their seat was released to walk-ins). */
/** Patient places per clinic hour (2026-10-26): 1 in-house + 1 roaming. slotCapacity() returns it. */
const SLOT_CAPACITY = 2;

const SEAT_SQL = "status IN ('pending','approved','rescheduled') AND (delayed_at IS NULL OR checked_in_at IS NOT NULL)";

/** What the patient portal counts as taken: every held seat plus seats freed
 *  by cancelled approved online bookings (those go to walk-ins only). */
const ONLINE_SEAT_SQL = '((' . SEAT_SQL . ') OR seat_released_online = 1)';

/**
 * validBirthDate() plus the walk-in new-patient age range (1 to 80).
 * Returns 'YYYY-MM-DD' or null (with $err set).
 */
function walkInBirthDate(string $dob, ?string &$err = null): ?string
{
    $v = validBirthDate($dob, $err);
    if ($v === null) return null;
    $age = (int) ageFromBirthDate($v);
    if ($age < WALKIN_MIN_AGE || $age > WALKIN_MAX_AGE) {
        $err = 'A new walk-in patient must be ' . WALKIN_MIN_AGE . ' to ' . WALKIN_MAX_AGE . ' years old.';
        return null;
    }
    return $v;
}

function ageFromBirthDate(?string $dob): ?int
{
    if (!$dob) {
        return null;
    }
    $st = getDB()->prepare('SELECT TIMESTAMPDIFF(YEAR, ?, CURDATE())');
    $st->execute([$dob]);
    return (int) $st->fetchColumn();
}

/** A strong password: 8+ chars, at least one lowercase, one uppercase, one digit, one special character. */
function isStrongPassword(string $p): bool
{
    return strlen($p) >= 8
        && preg_match('/[a-z]/', $p) === 1
        && preg_match('/[A-Z]/', $p) === 1
        && preg_match('/[0-9]/', $p) === 1
        && preg_match('/[^a-zA-Z0-9]/', $p) === 1;
}

/** Standard error string for a weak password (mirrors the client-side rule in login.php). */
const STRONG_PASSWORD_MSG = 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a special character.';

/** "14:00" / "14:00:00" -> "2:00 PM". Mirrors fmtTime() in app.js. */
function fmtTime(?string $slot): string
{
    if (!$slot || !preg_match('/^(\d{1,2}):(\d{2})/', $slot, $m)) {
        return (string) ($slot ?? '');
    }
    $h  = (int) $m[1];
    $ap = $h < 12 ? 'AM' : 'PM';
    $h  = $h % 12 ?: 12;
    return $h . ':' . $m[2] . ' ' . $ap;
}

/** Audit-trail categories accepted by logActivity() (admin "Activity Log" page). */
const ACTIVITY_CATEGORIES = ['auth', 'appointments', 'records', 'billing'];

/**
 * Append one row to the staff activity / audit trail.
 *
 * Best-effort: any failure (missing table, DB hiccup) is swallowed so auditing
 * never breaks the request it is recording. username / role are stored as a
 * snapshot so the log stays readable after a rename or archive.
 *
 * The $action text should read as a standalone description that starts with a
 * verb ("Checked in Rosa Lim", "Signed in") — do NOT prefix the actor's
 * username; the log's User column already shows who did it. As a safety net this
 * function strips a leading "<username> " if one slips through and capitalises
 * the first letter.
 *
 * @param string     $category One of ACTIVITY_CATEGORIES.
 * @param string     $action   Human-readable description, e.g. "Checked in Rosa Lim".
 * @param array|null $actor    ['user_id','username','role']; defaults to currentUser().
 */
function logActivity(string $category, string $action, ?array $actor = null): void
{
    try {
        if ($actor === null && function_exists('currentUser')) {
            $actor = currentUser();
        }
        $username = substr((string) ($actor['username'] ?? ''), 0, 60);
        $action   = trim($action);
        if ($username !== '' && stripos($action, $username . ' ') === 0) {
            $action = substr($action, strlen($username) + 1);
        }
        $action = ($action === '' ? '' : mb_strtoupper(mb_substr($action, 0, 1)) . mb_substr($action, 1));

        getDB()->prepare(
            'INSERT INTO activity_log (user_id, username, role, category, action, ip)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            isset($actor['user_id']) ? (int) $actor['user_id'] : null,
            $username,
            substr((string) ($actor['role'] ?? ''), 0, 30),
            $category,
            substr($action, 0, 255),
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        // auditing is best-effort — never disrupt the caller
    }
}

/** Send a JSON payload and stop. */
function jsonOut(array $data, int $status = 200): never
{
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code($status);
    }
    echo json_encode($data);
    exit;
}

/** Read the JSON or form body of a request as an array. */
function requestBody(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $ct  = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($ct, 'application/json')) {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
    return $_POST;
}

/** Fetch a single setting value (with optional default). */
function setting(string $key, ?string $default = null): ?string
{
    static $cache = [];
    if (!array_key_exists($key, $cache)) {
        $stmt = getDB()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $cache[$key] = $stmt->fetchColumn();
    }
    $val = $cache[$key];
    return $val === false ? $default : (string) $val;
}

/** Upsert a setting. */
function setSetting(string $key, string $value): void
{
    $stmt = getDB()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

/**
 * The bookable time slots, as an ordered list of "HH:MM" strings.
 * Uses the `time_slots` setting (CSV, managed from the dentist's Manage
 * Appointments page); falls back to an hourly range between the legacy
 * `clinic_open` / `clinic_close` settings (defaults 09:00–17:00 → 09:00…16:00).
 */
/**
 * Same-day walk-in cut-off (2026-10-22): one hour before the last slot of the
 * day starts (last slot 16:15 -> 15:15). After it, no new walk-in for today.
 */
function walkinCutoffHm(): string
{
    $slots = generateSlots();
    $last = $slots ? (string) end($slots) : '16:15';
    $m = (int) substr($last, 0, 2) * 60 + (int) substr($last, 3, 2) - 60;
    return sprintf('%02d:%02d', intdiv(max($m, 0), 60), max($m, 0) % 60);
}

/** True once today's walk-in cut-off has passed (DB clock). */
function walkinSameDayClosed(): bool
{
    $now = (string) getDB()->query("SELECT DATE_FORMAT(NOW(), '%H:%i')")->fetchColumn();
    return $now > walkinCutoffHm();
}

/** Message shown when the same-day walk-in cut-off has passed. */
function walkinCutoffMsg(): string
{
    return 'Same-day walk-in booking is closed for today (Cut-off: ' . fmtTime(walkinCutoffHm())
        . '). Please schedule the appointment for a future date.';
}

/** A visit ends at the next full hour: 10:00 -> 11:00, 11:15 -> 12:00 (2026-10-18). */
function slotEndHm(string $slot): string
{
    return sprintf('%02d:00', ((int) substr($slot, 0, 2) + 1) % 24);
}

function generateSlots(): array
{
    $raw = trim((string) setting('time_slots', ''));
    if ($raw !== '') {
        $slots = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $raw)),
            static fn ($s) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $s) === 1
        )));
        sort($slots);
        if ($slots) {
            return $slots;
        }
    }
    $open  = setting('clinic_open', '09:00');
    $close = setting('clinic_close', '17:00');
    $slots = [];
    $t   = strtotime($open);
    $end = strtotime($close);
    while ($t < $end) {
        $slots[] = date('H:i', $t);
        $t = strtotime('+1 hour', $t);
    }
    return $slots;
}

/* --------------------------------------------------------------------------
 *  Per-dentist working schedule (migration 031, `dentist_schedules` table).
 *  A recurring weekly grid of day-of-week + time-slot rows. The grid is the
 *  single source of truth for when a dentist works: an empty grid (or a grid
 *  with nothing active on a given weekday) means the dentist is NOT on duty and
 *  NOT bookable then. Migration 031 + the schema seed backfill every active
 *  dentist with the full Mon–Sat grid, so this only bites once an admin
 *  deliberately clears days on the Dentist Schedules page.
 * ------------------------------------------------------------------------ */

/**
 * The clinic time slots a dentist actually works on the weekday of $date, as an
 * ordered list of "HH:MM" strings (already intersected with generateSlots()).
 * Returns [] when the dentist works nothing that weekday — including when their
 * whole schedule grid is empty, or when a one-off dentist_absences row (migration
 * 040 — a sick day / emergency leave marked for this exact date) overrides an
 * otherwise-working day. Only a genuine DB / missing-table error falls back to
 * every clinic slot, so booking never hard-crashes on a broken install.
 */
function dentistBookableSlots(int $dentistId, string $date, bool $ignoreAbsence = false): array
{
    $all = generateSlots();
    if ($dentistId <= 0) {
        return $all;
    }
    try {
        $pdo = getDB();

        if (!$ignoreAbsence) {
            $abs = $pdo->prepare('SELECT 1 FROM dentist_absences WHERE dentist_id = ? AND absence_date = ?');
            $abs->execute([$dentistId, $date]);
            if ($abs->fetchColumn() !== false) {
                return [];                    // marked out for this date — overrides the grid
            }
        }

        // Roaming (on-call) dentists have no fixed weekly working grid by
        // design (2026-09) — a recurring schedule is an In-House concept
        // (see the admin Dentist Schedules page, which excludes roaming
        // dentists from fixed-schedule management entirely). Their whole
        // role is to absorb overflow whenever the Owner is full, any hour,
        // any day — so they're treated as bookable at every clinic slot
        // unconditionally, unless marked absent above for that exact date.
        // Any dentist_schedules rows a roaming dentist happens to have (e.g.
        // left over from testing, or a stale admin edit from before this
        // exclusion existed) are simply ignored for them from here on.
        $type = $pdo->prepare('SELECT doctor_type FROM users WHERE user_id = ?');
        $type->execute([$dentistId]);
        if ($type->fetchColumn() === 'roaming') {
            return $all;
        }

        // A per-date change (migration 079) replaces the weekly grid for that date.
        $day = dentistDayOverride($dentistId, $date);
        if ($day !== null) {
            $set = array_flip($day);
            return array_values(array_filter($all, static fn ($s) => isset($set[$s])));
        }

        $dow = scheduleDow($date);
        $st = $pdo->prepare(
            'SELECT time_slot FROM dentist_schedules
             WHERE dentist_id = ? AND day_of_week = ? AND is_active = 1'
        );
        $st->execute([$dentistId, $dow]);
        $set = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
        return array_values(array_filter($all, static fn ($s) => isset($set[$s])));
    } catch (Throwable $e) {
        return $all;                          // DB / schema error → stay permissive
    }
}

/**
 * The hours set for one exact date on the admin Dentist Schedules calendar
 * (migration 079), or null when that date follows the usual weekly grid.
 * An empty array means the dentist is off that day.
 */
function dentistDayOverride(int $dentistId, string $date): ?array
{
    try {
        $st = getDB()->prepare('SELECT slots FROM dentist_day_schedules WHERE dentist_id = ? AND work_date = ?');
        $st->execute([$dentistId, $date]);
        $v = $st->fetchColumn();
    } catch (Throwable $e) {
        return null;                          // table missing on an old install
    }
    if ($v === false) {
        return null;
    }
    return array_values(array_filter(explode(',', (string) $v), 'strlen'));
}

/** True when a dentist has a one-off absence (dentist_absences) marked for $date. */
function dentistAbsentOn(int $dentistId, string $date): bool
{
    try {
        $st = getDB()->prepare('SELECT 1 FROM dentist_absences WHERE dentist_id = ? AND absence_date = ?');
        $st->execute([$dentistId, $date]);
        return $st->fetchColumn() !== false;
    } catch (Throwable $e) {
        return false;
    }
}

/** True when the dentist is scheduled on duty for this exact date + time slot. */
function dentistOnDuty(int $dentistId, string $date, string $slot): bool
{
    return in_array($slot, dentistBookableSlots($dentistId, $date), true);
}

/** True when the dentist works at least one slot on the weekday of $date. */
function dentistWorksOn(int $dentistId, string $date): bool
{
    return dentistBookableSlots($dentistId, $date) !== [];
}

/**
 * Give a newly-designated In-House (owner) dentist a full Mon–Sat working
 * grid by default — an in-house doctor is in the clinic full time, every
 * clinic slot, so that's the sensible starting point. A Roaming associate
 * gets no such default and starts with an empty grid until the admin sets
 * one explicitly. A no-op when the dentist already has any schedule rows,
 * so it never clobbers an admin's existing customization (e.g. switching a
 * dentist back and forth between owner/roaming doesn't keep re-filling it).
 */
function seedDefaultOwnerSchedule(int $dentistId): void
{
    try {
        $pdo = getDB();
        $exists = $pdo->prepare('SELECT 1 FROM dentist_schedules WHERE dentist_id = ? LIMIT 1');
        $exists->execute([$dentistId]);
        if ($exists->fetchColumn() !== false) {
            return;
        }
        $ins = $pdo->prepare(
            'INSERT INTO dentist_schedules (dentist_id, day_of_week, time_slot) VALUES (?, ?, ?)'
        );
        foreach (range(1, 6) as $day) {
            foreach (generateSlots() as $slot) {
                $ins->execute([$dentistId, $day, $slot]);
            }
        }
    } catch (Throwable $e) {
        // Best-effort — a failed default-seed shouldn't block account creation;
        // the admin can always fill the grid in by hand on Dentist Schedules.
    }
}

/**
 * Create a notification for a user. $appointmentId + $type (migration 062) are
 * optional: when given, the bell / notification pages render a card with live
 * appointment details and action buttons instead of just the message text.
 */
function notify(int $userId, string $title, string $message, ?int $appointmentId = null, ?string $type = null, bool $email = true): void
{
    $stmt = getDB()->prepare(
        'INSERT INTO notifications (user_id, appointment_id, type, title, message) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $appointmentId ?: null, $type, $title, $message]);
    // Every patient notification is e-mailed too (2026-10-19). $email = false
    // when the caller sends its own richer e-mail (e.g. the payment receipt).
    if ($email) {
        queuePatientNotificationEmail($userId, $title, $message);
    }
}

/**
 * Queue an e-mail copy of a notification when the recipient is an active
 * patient with an e-mail on file. Mail goes out after the response has been
 * sent (see flushQueuedEmails()), so SMTP never slows a page or an action.
 */
function queuePatientNotificationEmail(int $userId, string $title, string $message): void
{
    try {
        $st = getDB()->prepare("SELECT email, first_name FROM users WHERE user_id = ? AND role = 'patient' AND is_active = 1");
        $st->execute([$userId]);
        $u = $st->fetch();
        $to = (string) ($u['email'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        require_once __DIR__ . '/mailer.php';
        $h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $html = mailShell($h($title), nl2br($h($message)), 'Open My Account',
            appBaseUrl() . '/modules/patient/appointments',
            'You received this because you have an account at DGT One Dental Center - Bulacan. Questions? Call us at 09764484252.');
        queueEmail($to, $title . ' | DGT One Dental Center', $html);
    } catch (Throwable $e) {
        // best-effort: a notification never fails because of e-mail
    }
}

/** Add an e-mail to the after-response send queue. */
function queueEmail(string $to, string $subject, string $html): void
{
    static $registered = false;
    $GLOBALS['__dgtMailQueue'][] = [$to, $subject, $html];
    if (!$registered) {
        $registered = true;
        register_shutdown_function('flushQueuedEmails');
    }
}

/** Finish the HTTP response first, then send the queued e-mails. */
function flushQueuedEmails(): void
{
    $queue = $GLOBALS['__dgtMailQueue'] ?? [];
    $GLOBALS['__dgtMailQueue'] = [];
    if (!$queue) {
        return;
    }
    ignore_user_abort(true);
    @set_time_limit(120);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    }
    require_once __DIR__ . '/mailer.php';
    foreach ($queue as [$to, $subject, $html]) {
        try {
            sendAppMail($to, $subject, $html);
        } catch (Throwable $e) {
            // keep going with the rest
        }
    }
}

/** Random temporary password for newly created staff accounts. */
function tempPassword(int $length = 10): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

/** Trimmed "First Middle Last" for the full_name mirror column. */
/**
 * Proper capitalization for a name / address typed by a person, applied before
 * it is saved. A word typed ALL CAPS or all lowercase becomes "Title Case"
 * ("JUAN DELA CRUZ" -> "Juan Dela Cruz", "o'neil" -> "O'Neil",
 * "mary-ann" -> "Mary-Ann"). A word already in mixed case is kept as typed
 * ("McDonald", "dela Cruz"), a word with a digit is kept ("B-6L-47"), and a
 * roman-numeral suffix stays upper case ("III").
 */
function properCase(?string $s): string
{
    $s = trim(preg_replace('/\s+/u', ' ', (string) $s));
    if ($s === '') return '';
    $words = explode(' ', $s);
    foreach ($words as &$w) {
        if (preg_match('/\d/', $w)) continue;
        if (preg_match('/^(II|III|IV|VI|VII|VIII|IX)\.?$/i', $w)) { $w = mb_strtoupper($w); continue; }
        $up = mb_strtoupper($w); $lo = mb_strtolower($w);
        if ($w !== $up && $w !== $lo) continue;          // mixed case: keep as typed
        $w = preg_replace_callback("/(^|[-'\x{2019}(\/])(\p{L})/u",
            fn ($m) => $m[1] . mb_strtoupper($m[2]), $lo);
    }
    unset($w);
    return implode(' ', $words);
}

// Middle names are no longer collected (2026-10-11): full_name is "First Last".
// $middle is kept in the signature for the existing callers and ignored.
function personName(string $first, ?string $middle, string $last): string
{
    return trim(preg_replace('/\s+/', ' ', trim($first) . ' ' . trim($last)));
}

/**
 * "First Last" for a `users` row — used in the header greeting and the sidebar
 * footer, where the middle name(s) just make a long name overflow. Falls back to
 * the `full_name` mirror for any row that predates the split columns.
 */
function shortName(array $u): string
{
    $short = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    return $short !== '' ? $short : (string) ($u['full_name'] ?? '');
}

/**
 * A unique lowercase "first.last" username (Rex Carbonel -> rex.carbonel).
 * Accented letters become plain ones; other non-alphanumerics are stripped;
 * a numeric suffix is added on collision (first.last, first.last2, ...).
 */
function makeUsername(PDO $pdo, string $first, string $last, ?int $excludeId = null): string
{
    // Accented letters become their plain letter first (Peña -> pena).
    $plain = static fn(string $s): string => strtr(mb_strtolower($s, 'UTF-8'), [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c',
    ]);
    $slug = static fn(string $s): string => preg_replace('/[^a-z0-9]+/', '', $plain($s)) ?? '';
    $base = trim(($slug($first) ?: 'user') . '.' . ($slug($last) ?: 'user'), '.');
    $base = substr($base, 0, 50);

    $sql = 'SELECT 1 FROM users WHERE username = ?' . ($excludeId !== null ? ' AND user_id <> ?' : '');
    $chk = $pdo->prepare($sql);

    $try = $base;
    $n   = 1;
    while (true) {
        $chk->execute($excludeId !== null ? [$try, $excludeId] : [$try]);
        if (!$chk->fetch()) {
            return $try;
        }
        $try = $base . (++$n);
    }
}

/** True when a request came from the AJAX navigation loader. */
function isPartialRequest(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch'
        || isset($_GET['partial']);
}

/** Time-of-day greeting for the header. */
function greeting(): string
{
    $h = (int) date('G');
    return match (true) {
        $h < 12 => 'Good morning',
        $h < 18 => 'Good afternoon',
        default => 'Good evening',
    };
}

/** Spell out a peso amount, e.g. 2400 -> "Two Thousand Four Hundred Pesos Only". */
function pesosInWords(float|int|string $amount): string
{
    $amount = (float) $amount;
    $pesos  = (int) floor($amount);
    $cents  = (int) round(($amount - $pesos) * 100);

    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
             'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen',
             'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    $below1000 = static function (int $n) use ($ones, $tens): string {
        $out = '';
        if ($n >= 100) {
            $out .= $ones[intdiv($n, 100)] . ' Hundred';
            $n %= 100;
            if ($n) {
                $out .= ' ';
            }
        }
        if ($n >= 20) {
            $out .= $tens[intdiv($n, 10)];
            if ($n % 10) {
                $out .= '-' . $ones[$n % 10];
            }
        } elseif ($n > 0) {
            $out .= $ones[$n];
        }
        return $out;
    };

    if ($pesos === 0) {
        $words = 'Zero';
    } else {
        $scales = [1_000_000_000 => 'Billion', 1_000_000 => 'Million', 1_000 => 'Thousand', 1 => ''];
        $parts = [];
        foreach ($scales as $value => $name) {
            if ($pesos >= $value) {
                $chunk = intdiv($pesos, $value);
                $pesos %= $value;
                $parts[] = trim($below1000($chunk) . ($name ? ' ' . $name : ''));
            }
        }
        $words = implode(' ', $parts);
    }

    $result = $words . ' Peso' . ($words === 'One' ? '' : 's');
    $result .= $cents > 0 ? ' and ' . $below1000($cents) . ' Centavos' : ' Only';
    return $result;
}

/**
 * Every revenue line, as a derived table (migration 083, 2026-10-21). The
 * reservation fee counts on the day it was paid, once the front desk accepted
 * it (online Approve, or a walk-in at the counter), even if the visit is later
 * cancelled (the fee is non-refundable). The rest of the bill counts on the day
 * the cashier settles it in full. Columns: payment_id, appointment_id, kind
 * ('reservation' / 'bill' / 'refund', a negative row for an excess reservation
 * handed back as change at settlement, migration 090), rev_at, rev_date, amount, method, method_label.
 */
function revenueLinesSql(): string
{
    return "(SELECT p.payment_id, p.appointment_id, 'reservation' AS kind,
                    COALESCE(p.reservation_paid_at, p.created_at) AS rev_at,
                    DATE(COALESCE(p.reservation_paid_at, p.created_at)) AS rev_date,
                    p.reservation_fee_paid AS amount, p.reservation_method AS method,
                    p.reservation_method_label AS method_label
             FROM payments p
             WHERE p.reservation_fee_paid > 0 AND p.reservation_confirmed_at IS NOT NULL
             UNION ALL
             SELECT p.payment_id, p.appointment_id, 'bill',
                    p.paid_at, COALESCE(DATE(p.paid_at), p.payment_date),
                    GREATEST(p.total_amount - p.discount
                             - IF(p.reservation_confirmed_at IS NOT NULL, p.reservation_fee_paid, 0), 0),
                    p.payment_mode, NULL
             FROM payments p
             WHERE p.payment_status = 'fully_paid'
             UNION ALL
             SELECT p.payment_id, p.appointment_id, 'refund',
                    p.paid_at, COALESCE(DATE(p.paid_at), p.payment_date),
                    -p.reservation_refund, 'cash', NULL
             FROM payments p
             WHERE p.payment_status = 'fully_paid' AND p.reservation_refund > 0)";
}

/**
 * Resolve a [from, to] YYYY-MM-DD window for the Revenue Reports screen from the
 * request: a `period` keyword (day/week/month/range) anchored on an optional
 * `date`, or an explicit `from`/`to` pair when `period=range`.
 */
function reportWindow(): array
{
    $period = $_GET['period'] ?? 'month';
    $anchor = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
    $ts = strtotime($anchor) ?: time();

    return match ($period) {
        'day'   => [$anchor, $anchor],
        'week'  => [
            date('Y-m-d', strtotime('monday this week', $ts)),
            date('Y-m-d', strtotime('sunday this week', $ts)),
        ],
        'range' => (static function (array $r): array { sort($r); return $r; })([   // a reversed range is swapped (2026-10-18)
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01', $ts),
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-t', $ts),
        ]),
        default => [date('Y-m-01', $ts), date('Y-m-t', $ts)],   // month
    };
}

/** Web path for a bundled asset, cache-busted with its file modification time. */
function asset(string $path): string
{
    $rel  = '/dgt_dental_clinic/' . ltrim($path, '/');
    $file = __DIR__ . '/../' . ltrim($path, '/');
    $ver  = is_file($file) ? filemtime($file) : null;
    return $ver ? $rel . '?v=' . $ver : $rel;
}

/** Public URL for a stored avatar filename, or null when none is set. */
function avatarUrl(?string $avatar): ?string
{
    if ($avatar === null || $avatar === '') {
        return null;
    }
    return '/dgt_dental_clinic/uploads/avatars/' . rawurlencode($avatar);
}

/** Dentist specialty keys -> display labels (shared by services and doctor accounts). */
function dentistSpecialties(): array
{
    return [
        'general'        => 'General Dentist',
        'endodontist'    => 'Endodontist',
        'orthodontist'   => 'Orthodontist',
        'periodontist'   => 'Periodontist',
        'prosthodontist' => 'Prosthodontist',
        'oral_surgeon'   => 'Oral Surgeon',
        'pediatric'      => 'Pediatric Dentist',
    ];
}

/** Dentist account types -> display labels for the Add Doctor form and dropdowns. */
function doctorTypes(): array
{
    return [
        'owner'   => 'In-House Doctor',
        'roaming' => 'Roaming Doctor',
    ];
}

/** "general,orthodontist" -> "General Dentist, Orthodontist". */
function specialtyLabels(?string $csv): string
{
    if ($csv === null || $csv === '') {
        return '';
    }
    $map = dentistSpecialties();
    $out = array_map(
        static fn (string $k) => $map[$k] ?? ucwords(str_replace('_', ' ', $k)),
        array_filter(array_map('trim', explode(',', $csv)))
    );
    return implode(', ', $out);
}

/** Human label for a role. */
function roleLabel(string $role): string
{
    return match ($role) {
        'admin'            => 'Administrator',
        'dentist'          => 'Dentist',
        'cashier'          => 'Cashier',
        'receptionist'     => 'Receptionist',
        'dental_assistant' => 'Assistant Dentist',
        'patient'          => 'Patient',
        default            => ucfirst(str_replace('_', ' ', $role)),
    };
}

/** The non-patient LOGIN roles, i.e. every staff member who signs in. */
function staffRoles(): array
{
    return ['admin', 'dentist', 'cashier', 'receptionist', 'dental_assistant'];
}

/** The dental_assistants roster id linked to a login account, or null (migration 058). */
function assistantIdForUser(int $userId): ?int
{
    $st = getDB()->prepare('SELECT assistant_id FROM dental_assistants WHERE user_id = ? AND is_active = 1');
    $st->execute([$userId]);
    $id = $st->fetchColumn();
    return $id === false ? null : (int) $id;
}

/**
 * The dental assistants marked on duty for a given date (default: today).
 * Returns [{assistant_id, full_name, user_id}], active roster only (`user_id`
 * is null for a legacy roster row with no linked login — migration 058).
 * Assistants are non-account staff by default — this shift table is the only
 * "who is here today" signal.
 */
function dentalAssistantsOnDuty(?string $date = null): array
{
    $pdo = getDB();
    // "Today" must come from the DB clock, not PHP's — this box runs PHP and
    // MySQL in different timezones, and assistant_shifts rows are written with
    // CURDATE() (api/assistants.php action=set_shift), so a PHP-clock default
    // here would disagree with what was actually stored whenever the two
    // clocks are on different calendar days (same pitfall documented for
    // todayISO(), touchPresence(), and the treatment-log date guard).
    $date = $date ?: (string) $pdo->query('SELECT CURDATE()')->fetchColumn();
    $stmt = $pdo->prepare(
        'SELECT da.assistant_id, da.full_name, da.user_id
         FROM assistant_shifts s
         JOIN dental_assistants da ON da.assistant_id = s.assistant_id AND da.is_active = 1
         WHERE s.shift_date = ?
         ORDER BY da.full_name'
    );
    $stmt->execute([$date]);
    return $stmt->fetchAll();
}

/**
 * The on-duty dental assistant with the fewest "checked-in and not yet
 * completed/cancelled" patients today — used to auto-assign a checked-in
 * patient when the front desk leaves the Assisting Staff picker blank at
 * check-in, instead of leaving the visit with no assistant at all whenever
 * someone is actually on duty to take it. Ties break on the lowest
 * assistant_id, the same tiebreak convention assignAppointmentDentist() uses
 * for a least-loaded pick. Returns null when nobody is on duty for the date
 * (the caller then leaves checkin_assistant_id NULL — direct-to-doctor).
 */
function leastLoadedOnDutyAssistant(?string $date = null): ?array
{
    $pdo = getDB();
    // DB clock, not PHP's — same reasoning as dentalAssistantsOnDuty() above.
    $date = $date ?: (string) $pdo->query('SELECT CURDATE()')->fetchColumn();
    $stmt = $pdo->prepare(
        "SELECT da.assistant_id, da.full_name, da.user_id,
                (SELECT COUNT(*) FROM appointments a
                 WHERE a.checkin_assistant_id = da.assistant_id
                   AND a.scheduled_date = ?
                   AND a.checked_in_at IS NOT NULL
                   AND a.status NOT IN ('completed', 'cancelled')) AS active_patients
         FROM dental_assistants da
         JOIN assistant_shifts s ON s.assistant_id = da.assistant_id AND s.shift_date = ?
         WHERE da.is_active = 1
         ORDER BY active_patients ASC, da.assistant_id ASC
         LIMIT 1"
    );
    $stmt->execute([$date, $date]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Best-effort check for AI-generation or image-editing-software evidence left
 * in an uploaded payment receipt screenshot's own metadata. Checks, in order
 * of how reliable the signal is:
 *
 * 1) C2PA "Content Credentials" provenance data. Modern AI image generators
 *    (ChatGPT/DALL-E's "gpt-image", Adobe Firefly, Google, Microsoft
 *    Designer, …) embed a standardized C2PA manifest (a JUMBF box) recording
 *    who/what made the image. The single most reliable field in it is
 *    `digitalSourceType`, an IPTC-standard vocabulary — a value containing
 *    "trainedAlgorithmicMedia" is the industry-standard marker for "this
 *    asset was produced by a generative AI model," regardless of which tool
 *    made it. This is a real, verified signal — confirmed directly against a
 *    genuine ChatGPT-generated image, whose manifest contained exactly
 *    `"softwareAgent":{"name":"ChatGPT","version":"gpt-image"}` and
 *    `"digitalSourceType":"...trainedAlgorithmicMedia"`.
 * 2) A known AI-generator name recorded elsewhere in that same manifest
 *    (claim_generator / softwareAgent), for a manifest that for some reason
 *    doesn't carry the digitalSourceType field.
 * 3) A conventional desktop-editing-tool signature (EXIF "Software" tag, or
 *    a raw Photoshop/Canva/GIMP/etc. signature elsewhere in the file).
 *
 * Deliberately does NOT treat a receipt with no metadata at all as
 * suspicious: these uploads are phone screenshots, not camera photos, and a
 * genuine screenshot never carries camera EXIF (no lens/GPS/device info) —
 * that absence is the normal case, not a red flag. Only a positive match on
 * one of the signals above counts as suspicious.
 *
 * Returns ['suspected' => bool, 'reason' => ?string]. Never throws — a
 * read/parse failure just reports not-suspected, since this is an advisory
 * flag for manual review, not a hard gate on the upload.
 */
function inspectReceiptForManipulation(string $filePath): array
{
    $none = ['suspected' => false, 'reason' => null];

    if (!is_file($filePath) || !is_readable($filePath)) {
        return $none;
    }

    // Receipts are capped at 4MB at upload time, so reading the whole file
    // for a byte-scan is cheap and safe.
    $bytes = @file_get_contents($filePath);
    if ($bytes === false || $bytes === '') {
        return $none;
    }

    // The C2PA manifest is CBOR-encoded binary, not plain JSON/XML text, so
    // its key/value structure can't be pulled apart with a text regex — but
    // the tool-name strings inside it are still stored as literal, searchable
    // ASCII substrings, which is all a plain stripos() scan needs.
    $aiTools = [
        'ChatGPT', 'gpt-image', 'DALL-E', 'DALL·E', 'OpenAI',
        'Midjourney', 'Stable Diffusion', 'Stability AI',
        'Adobe Firefly', 'Google Imagen', 'Microsoft Designer',
        'Bing Image Creator', 'Copilot Designer', 'Leonardo.Ai', 'NightCafe',
    ];
    $matchedTool = null;
    foreach ($aiTools as $tool) {
        if (stripos($bytes, $tool) !== false) {
            $matchedTool = $tool;
            break;
        }
    }

    // 1) The official IPTC "generated/composited by AI" marker inside a C2PA
    //    manifest, if present — the strongest, most standardized signal.
    if (stripos($bytes, 'trainedAlgorithmicMedia') !== false
        || stripos($bytes, 'compositeWithTrainedAlgorithmicMedia') !== false) {
        return [
            'suspected' => true,
            'reason' => 'AI-generated image detected: the file\'s embedded content-provenance data marks it as AI-generated/AI-composited media'
                . ($matchedTool ? ' (tool: ' . $matchedTool . ')' : '') . '.',
        ];
    }

    // 2) A known AI image generator named in a C2PA claim_generator /
    //    softwareAgent field even without the digitalSourceType above.
    if ($matchedTool !== null && (stripos($bytes, 'c2pa') !== false || stripos($bytes, 'claim_generator') !== false)) {
        return ['suspected' => true, 'reason' => 'AI-generated image detected: embedded metadata names the AI tool "' . $matchedTool . '".'];
    }

    // 3) EXIF "Software" tag, when the format/extension supports it.
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($filePath);
        if (is_array($exif) && !empty($exif['Software'])) {
            $sw = trim((string) $exif['Software']);
            if ($sw !== '') {
                return ['suspected' => true, 'reason' => 'Editing software recorded in image metadata: ' . $sw];
            }
        }
    }

    // 4) Raw signature scan for editors that skip the plain EXIF Software
    //    tag; works for PNG/WEBP too, which have no EXIF support at all in
    //    exif_read_data(). Deliberately excludes "Photoshop"/"Adobe
    //    Photoshop" and "Adobe XMP Core" here — those specific strings are
    //    also the generic APP13/XMP-toolkit boilerplate that plenty of
    //    unrelated JPEG-writing libraries (phone OS image pipelines,
    //    messaging-app re-encoders, compression tools) copy verbatim with no
    //    actual Photoshop editing involved. Confirmed as a real false
    //    positive: a genuine, unedited GCash screenshot the user tested with
    //    carried a literal "Photoshop 3.0" APP13 marker purely from
    //    whatever re-encoded the file, and was wrongly flagged before this
    //    exclusion. A real Photoshop edit is still caught via the EXIF
    //    Software tag above, which is a structured, reliably-set field
    //    rather than a raw string that generic encoders happen to echo.
    $signatures = [
        'Canva', 'GIMP', 'Paint.NET',
        'Affinity Photo', 'Adobe Illustrator', 'CorelDRAW', 'Pixlr',
    ];
    foreach ($signatures as $sig) {
        if (stripos($bytes, $sig) !== false) {
            return ['suspected' => true, 'reason' => 'Editing software signature found in the file: ' . $sig];
        }
    }

    return $none;
}

/**
 * "Last, First" for the Service Invoice "Sold To" line. Uses the real
 * name fields of the person treated (the "for someone else" patient record
 * when there is one, else the booking account for a self-booking); falls back
 * to the free-text appointment name, last word taken as the last name.
 */
function invoiceSoldTo(array $a): string
{
    $uid = (int) ($a['treated_patient_id'] ?? 0);
    if (!$uid && ($a['booking_for'] ?? 'self') !== 'other') {
        $uid = (int) ($a['patient_id'] ?? 0);
    }
    if ($uid) {
        try {
            $st = getDB()->prepare('SELECT first_name, middle_name, last_name FROM users WHERE user_id = ?');
            $st->execute([$uid]);
            $u = $st->fetch();
            if ($u && trim((string) $u['last_name']) !== '' && trim((string) $u['first_name']) !== '') {
                return trim((string) $u['last_name']) . ', ' . trim((string) $u['first_name']);
            }
        } catch (Throwable $e) {
            // fall through to the free-text name
        }
    }
    $parts = preg_split('/\s+/', trim((string) ($a['patient_name'] ?? ''))) ?: [];
    $parts = array_values(array_filter($parts, 'strlen'));
    if (count($parts) < 2) {
        return implode(' ', $parts);
    }
    $last = array_pop($parts);
    return $last . ', ' . implode(' ', $parts);
}
