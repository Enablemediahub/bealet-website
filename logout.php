<?php
/**
 * Bealet Website - Logout
 */

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/functions.php';

if (isLoggedIn()) {
    $userId = $_SESSION['user_id'];
    createLog('LOGOUT', 'User logged out', $userId);
}

// Clear session data before destroying the session storage.
$_SESSION = [];

$sessionCookieParams = session_get_cookie_params();
if (session_name() !== '') {
    setcookie(session_name(), '', time() - 3600, $sessionCookieParams['path'] ?: '/', $sessionCookieParams['domain'] ?? '', (bool) ($sessionCookieParams['secure'] ?? false), (bool) ($sessionCookieParams['httponly'] ?? true));
}
setcookie('PHPSESSID', '', time() - 3600, '/');

session_destroy();

// Clear remember me cookies
setcookie('remember_token', '', time() - 3600, '/');
setcookie('remember_user_id', '', time() - 3600, '/');

// Redirect to home
header('Location: ' . APP_URL . '/');
exit;
