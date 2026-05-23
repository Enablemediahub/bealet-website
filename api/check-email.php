<?php
/**
 * Bealet Website - API: Check Email Availability
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

$response = ['available' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode($response);
    exit;
}

// Handle both JSON and form data
$input = $_POST;
if (empty($input) && $_SERVER['CONTENT_TYPE'] === 'application/json') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}

if (!isset($input['email'])) {
    echo json_encode($response);
    exit;
}

$email = sanitize($input['email']);

if (!validateEmail($email)) {
    echo json_encode($response);
    exit;
}

global $db;

// Check if email exists
$user = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);

$response['available'] = !$user;

echo json_encode($response);
