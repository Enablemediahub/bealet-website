<?php
/**
 * Bealet Website - Admin Logout
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';

if (isLoggedIn()) {
    createLog('ADMIN_LOGOUT', 'Admin logged out', $_SESSION['user_id']);
}

$_SESSION = [];

$sessionCookieParams = session_get_cookie_params();
if (session_name() !== '') {
    setcookie(session_name(), '', time() - 3600, $sessionCookieParams['path'] ?: '/', $sessionCookieParams['domain'] ?? '', (bool) ($sessionCookieParams['secure'] ?? false), (bool) ($sessionCookieParams['httponly'] ?? true));
}
setcookie('PHPSESSID', '', time() - 3600, '/');

session_destroy();
setcookie('remember_token', '', time() - 3600, '/');
setcookie('remember_user_id', '', time() - 3600, '/');

header('Location: ' . APP_URL . '/admin/login.php');
exit;
