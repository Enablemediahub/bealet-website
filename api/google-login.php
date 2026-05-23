<?php
/**
 * Bealet Website - API: Google Login
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

$response = ['success' => false, 'message' => 'Unable to continue with Google login.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Method not allowed';
    echo json_encode($response);
    exit;
}

$input = $_POST;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (empty($input) && stripos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}

$credential = trim((string) ($input['credential'] ?? ''));
$googleClientId = getGoogleClientId();

if ($googleClientId === '') {
    http_response_code(503);
    $response['message'] = 'Google login is not configured yet.';
    echo json_encode($response);
    exit;
}

if ($credential === '') {
    http_response_code(422);
    $response['message'] = 'Google credential is required.';
    echo json_encode($response);
    exit;
}

$verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
$googlePayload = null;

if (function_exists('curl_init')) {
    $ch = curl_init($verifyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $result = curl_exec($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($result !== false && $httpStatus >= 200 && $httpStatus < 300) {
        $googlePayload = json_decode($result, true);
    }
}

if (!is_array($googlePayload)) {
    $fallback = @file_get_contents($verifyUrl);
    if ($fallback !== false) {
        $googlePayload = json_decode($fallback, true);
    }
}

if (!is_array($googlePayload)) {
    http_response_code(502);
    $response['message'] = 'Unable to verify your Google account right now.';
    echo json_encode($response);
    exit;
}

$audience = trim((string) ($googlePayload['aud'] ?? ''));
$email = trim((string) ($googlePayload['email'] ?? ''));
$emailVerified = trim((string) ($googlePayload['email_verified'] ?? ''));
$googleName = trim((string) ($googlePayload['name'] ?? ''));

if ($audience !== $googleClientId) {
    http_response_code(403);
    $response['message'] = 'Google account verification failed.';
    echo json_encode($response);
    exit;
}

if ($email === '' || $emailVerified !== 'true' || !validateEmail($email)) {
    http_response_code(403);
    $response['message'] = 'Your Google account email could not be verified.';
    echo json_encode($response);
    exit;
}

global $db;

try {
    $user = $db->fetch(
        "SELECT id, name, email, password_hash, is_admin, is_active, login_attempts, locked_until FROM users WHERE email = ?",
        [$email]
    );

    if ($user) {
        if (!(int) ($user['is_active'] ?? 0)) {
            http_response_code(403);
            $response['message'] = 'Your account has been deactivated. Please contact support.';
            echo json_encode($response);
            exit;
        }

        resetLoginAttempts($user['id']);
    } else {
        $displayName = $googleName !== '' ? $googleName : ucfirst(strtok($email, '@'));
        $userId = $db->insert(
            "INSERT INTO users (name, email, password_hash, is_admin, is_active, created_at) VALUES (?, ?, ?, 0, 1, NOW())",
            [$displayName, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])]
        );

        $user = $db->fetch(
            "SELECT id, name, email, password_hash, is_admin, is_active, login_attempts, locked_until FROM users WHERE id = ?",
            [$userId]
        );
    }

    if (!$user) {
        throw new RuntimeException('User record could not be loaded after Google sign-in.');
    }

    signInUser($user, false);
    $db->update("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);

    createLog('GOOGLE_LOGIN_SUCCESS', 'User logged in with Google', $user['id']);

    $response['success'] = true;
    $response['message'] = 'Signed in successfully.';
    $response['redirect'] = !empty($user['is_admin']) ? APP_URL . '/admin/' : APP_URL . '/';
} catch (Throwable $e) {
    createLog('GOOGLE_LOGIN_ERROR', 'Google login error: ' . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Unable to finish Google login right now.';
}

echo json_encode($response);
