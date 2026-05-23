<?php
/**
 * Bealet Website - Admin Index (Redirect)
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';

// Redirect to dashboard if logged in, otherwise to login
if (isLoggedIn() && isAdmin()) {
    header('Location: ' . APP_URL . '/admin/dashboard.php');
} else {
    header('Location: ' . APP_URL . '/admin/login.php');
}
exit;
