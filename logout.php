<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/functions.php';

// Drop the presence signal so the user leaves "Logged-In Staff" immediately.
if (isset($_SESSION['user_id'])) {
    try {
        getDB()->prepare('UPDATE users SET last_seen_at = NULL WHERE user_id = ?')
               ->execute([$_SESSION['user_id']]);
    } catch (Throwable $e) {
        // best-effort
    }
    logActivity('auth', 'Signed out', [
        'user_id'  => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'role'     => $_SESSION['role'] ?? '',
    ]);
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header('Location: /dgt_dental_clinic/home');
exit;
