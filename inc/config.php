<?php
/**
 * Bealet Website - Configuration File
 * Contains all application constants and settings
 */

// Application Settings
define('APP_NAME', 'BEALET OPTICAL CENTER');

$appIsHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
    || (
        !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
    )
);

$appScheme = $appIsHttps ? 'https' : 'http';
$appHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$appBasePath = '';
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
$projectRoot = realpath(dirname(__DIR__));

if ($documentRoot && $projectRoot) {
    $normalizedDocumentRoot = str_replace('\\', '/', $documentRoot);
    $normalizedProjectRoot = str_replace('\\', '/', $projectRoot);

    if (strpos($normalizedProjectRoot, $normalizedDocumentRoot) === 0) {
        $appBasePath = str_replace('\\', '/', substr($normalizedProjectRoot, strlen($normalizedDocumentRoot)));
    }
}

if ($appBasePath === '' && !empty($_SERVER['SCRIPT_NAME'])) {
    $scriptName = str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']);
    $scriptDirectory = trim(str_replace('\\', '/', dirname($scriptName)), '/');

    if ($scriptDirectory !== '') {
        $pathParts = explode('/', $scriptDirectory);
        if (end($pathParts) === 'admin') {
            array_pop($pathParts);
        }
        $appBasePath = '/' . implode('/', $pathParts);
    }
}

$appBasePath = '/' . trim((string) $appBasePath, '/');
if ($appBasePath === '/') {
    $appBasePath = '';
}

define('APP_URL', $appScheme . '://' . $appHost . $appBasePath);
define('APP_ENV', 'production'); // development, production
define('DEBUG_MODE', APP_ENV === 'development');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'bealet_website');
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT', 3306);

// Email Configuration
define('MAIL_FROM', 'noreply@bealet.com');
define('MAIL_HOST', 'smtp.mailtrap.io'); // Change to your SMTP provider
define('MAIL_PORT', 2525);
define('MAIL_USERNAME', 'your_mailtrap_username');
define('MAIL_PASSWORD', 'your_mailtrap_password');
define('MAIL_ENCRYPTION', 'tls');

// Session Configuration
define('SESSION_LIFETIME', 3600); // 60 minutes in seconds
define('REMEMBER_ME_LIFETIME', 2592000); // 30 days in seconds
define('SESSION_NAME', 'bealet_session');

// Security
define('HASH_ALGORITHM', 'sha256');
define('CSRF_TOKEN_LENGTH', 32);
define('BCRYPT_COST', 10);

// Password Requirements
define('MIN_PASSWORD_LENGTH', 8);
define('PASSWORD_UPPERCASE_REQUIRED', true);
define('PASSWORD_NUMBER_REQUIRED', true);
define('PASSWORD_SPECIAL_CHAR_REQUIRED', true);

// Account Lockout Settings
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes in seconds

// File Upload Settings
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Paystack Configuration
define('PAYSTACK_PUBLIC_KEY', 'pk_test_8367f89c42e77b80cb4f6e292f797e00fc973a1d');
define('PAYSTACK_SECRET_KEY', 'sk_test_3bc60cc2c06c370052e8944c58f794a47e863b68');
define('PAYSTACK_VERIFY_URL', 'https://api.paystack.co/transaction/verify/');

// Arkesel SMS Configuration
define('ARKESEL_API_KEY', 'SVdIUllpcklhU3hpV0FxZHFTQWc');
define('ARKESEL_SENDER_ID', 'BEALETOC');
define('ARKESEL_BASE_URL', 'https://sms.arkesel.com/api/v2/sms/send');

// Currency
define('CURRENCY', 'GH₵');
define('CURRENCY_CODE', 'GHS');
define('VAT_RATE', 0.20);

// API Endpoints
define('API_BASE_URL', APP_URL . '/api/');
define('LOGO_URL', APP_URL . '/assets/images/logo/logo.png');

// Error Reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// Set Default Timezone
date_default_timezone_set('Africa/Accra');

// Session Configuration
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', $appIsHttps ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
}

if (session_status() === PHP_SESSION_ACTIVE && session_name() !== SESSION_NAME) {
    // Some pages still call session_start() before loading config.php, which creates
    // a default PHPSESSID session. Migrate that session into the app's canonical
    // bealet_session so login/logout stay consistent across public and admin pages.
    $migratedSessionData = $_SESSION ?? [];
    $legacySessionName = session_name();

    session_write_close();
    session_name(SESSION_NAME);
    session_start();

    foreach ($migratedSessionData as $sessionKey => $sessionValue) {
        if (!array_key_exists($sessionKey, $_SESSION)) {
            $_SESSION[$sessionKey] = $sessionValue;
        }
    }

    if ($legacySessionName !== SESSION_NAME) {
        setcookie($legacySessionName, '', time() - 3600, '/');
    }
} elseif (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Set Security Headers
if (session_status() === PHP_SESSION_ACTIVE) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
