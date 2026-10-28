<?php
/**
 * Session bootstrap + role-based access control.
 *
 * Usage (page):   require_once __DIR__ . '/../../includes/auth_middleware.php';
 *                 checkAuth(['admin']);
 *
 * Usage (API):    require_once ...; $user = apiAuth(['cashier','admin']);
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('DENTALSESID');
    session_start();
}

/**
 * Landing page for each role after login.
 */
function roleHome(string $role): string
{
    $base = '/dgt_dental_clinic/modules/';
    return match ($role) {
        'admin'            => $base . 'admin/dashboard',
        'dentist'          => $base . 'dentist/myschedule',
        'cashier'          => $base . 'cashier/transactions',
        'receptionist'     => $base . 'receptionist/appointments',
        'dental_assistant' => $base . 'dental_assistant/dashboard',
        'patient'          => $base . 'patient/appointments',
        default            => '/dgt_dental_clinic/login',
    };
}

/**
 * Record a lightweight "seen just now" timestamp for the logged-in user.
 *
 * Best-effort and self-throttling on the DB clock (PHP and MySQL run in
 * different timezones on this box, so the whole comparison must stay in SQL).
 * Runs at most once per request and never blocks it.
 */
function touchPresence(): void
{
    static $done = false;
    if ($done || !isLoggedIn()) {
        return;
    }
    $done = true;
    try {
        getDB()->prepare(
            'UPDATE users SET last_seen_at = NOW()
             WHERE user_id = ?
               AND (last_seen_at IS NULL OR last_seen_at < NOW() - INTERVAL 45 SECOND)'
        )->execute([$_SESSION['user_id']]);
    } catch (Throwable $e) {
        // presence is cosmetic — ignore (e.g. migration 026 not yet applied)
    }
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

/**
 * Currently authenticated user row (cached per request).
 */
function currentUser(): ?array
{
    static $cache = null;
    if (!isLoggedIn()) {
        return null;
    }
    if ($cache === null) {
        $stmt = getDB()->prepare('SELECT * FROM users WHERE user_id = ? AND is_active = 1');
        $stmt->execute([$_SESSION['user_id']]);
        $cache = $stmt->fetch() ?: null;
        if ($cache === null) {           // account disabled / deleted mid-session
            session_unset();
            session_destroy();
        } elseif (($_SESSION['role'] ?? '') !== $cache['role']) {
            $_SESSION['role'] = $cache['role'];   // an admin changed this account's role
        }
    }
    return $cache;
}

/**
 * Force-redirect to the "Change Your Password" screen while the signed-in
 * account is still on a system-generated temporary password
 * (must_change_password = 1 — set by the admin Add User flow, a Reset
 * Password action, or a receptionist walk-in / Create Record booking).
 * Every checkAuth()-guarded page funnels through this, so a fresh temp-
 * password account can't reach any dashboard until it sets its own password.
 * No-ops for a guest (checkAuth's own login check already handles that) and
 * for change_password.php itself (else it could never be reached).
 */
function requirePasswordChanged(): void
{
    $me = currentUser();
    if ($me === null || empty($me['must_change_password'])) {
        return;
    }
    // The My Profile page is where a temporary password gets replaced, so it
    // (and only it) stays reachable while the flag is set.
    $script = str_replace(chr(92), '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (substr($script, -strlen('/modules/shared/profile.php')) === '/modules/shared/profile.php') {
        return;
    }
    header('Location: /dgt_dental_clinic/modules/shared/profile');
    exit;
}

/**
 * Guard a full page. Redirects to login (or the user's own home) on failure.
 *
 * @param string[] $allowedRoles  empty = any authenticated user
 */
function checkAuth(array $allowedRoles = []): array
{
    if (!isLoggedIn() || currentUser() === null) {
        header('Location: /dgt_dental_clinic/login');
        exit;
    }
    requirePasswordChanged();
    if ($allowedRoles !== [] && !in_array($_SESSION['role'], $allowedRoles, true)) {
        auditAccessDenied();
        header('Location: ' . roleHome($_SESSION['role']));
        exit;
    }
    touchPresence();
    return currentUser();
}

/**
 * Record an unauthorized-access attempt in the History Log — an authenticated
 * user whose role is not allowed on the guarded page / endpoint. Best-effort.
 */
function auditAccessDenied(): void
{
    if (!function_exists('logActivity')) {
        return;
    }
    $target = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'a page'));
    $act    = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($_GET['action'] ?? ''));
    logActivity('auth',
        'Denied access to ' . $target . ($act !== '' ? '?action=' . $act : '')
        . ' (role ' . ($_SESSION['role'] ?? '?') . ')',
        [
            'user_id'  => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? '',
            'role'     => $_SESSION['role'] ?? '',
        ]);
}

/**
 * Guard a JSON API endpoint. Emits a JSON error + status code on failure.
 *
 * @param string[] $allowedRoles
 */
function apiAuth(array $allowedRoles = []): array
{
    header('Content-Type: application/json');
    if (!isLoggedIn() || currentUser() === null) {
        http_response_code(401);
        exit(json_encode(['ok' => false, 'error' => 'Not authenticated']));
    }
    if ($allowedRoles !== [] && !in_array($_SESSION['role'], $allowedRoles, true)) {
        auditAccessDenied();
        http_response_code(403);
        exit(json_encode(['ok' => false, 'error' => 'Forbidden']));
    }
    touchPresence();
    return currentUser();
}
