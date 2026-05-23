<?php
/**
 * Bealet Website - API: Subscribe Newsletter
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Method not allowed';
    echo json_encode($response);
    exit;
}

// Handle both JSON and form data
$input = $_POST;
if (empty($input) && $_SERVER['CONTENT_TYPE'] === 'application/json') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}

if (!isset($input['email'])) {
    $response['message'] = 'Email is required';
    echo json_encode($response);
    exit;
}

$email = sanitize($input['email']);

if (!validateEmail($email)) {
    $response['message'] = 'Invalid email address';
    echo json_encode($response);
    exit;
}

global $db;

try {
    // Check if already subscribed
    $subscriber = $db->fetch("SELECT id FROM newsletter WHERE email = ?", [$email]);
    
    if ($subscriber) {
        $response['message'] = 'Already subscribed';
        echo json_encode($response);
        exit;
    }
    
    // Add to newsletter
    $db->update(
        "INSERT INTO newsletter (email, is_active, created_at) VALUES (?, 1, NOW())",
        [$email]
    );
    
    $response['success'] = true;
    $response['message'] = 'Successfully subscribed to newsletter';
    
} catch (Exception $e) {
    $response['message'] = 'Error subscribing to newsletter';
    createLog('NEWSLETTER_ERROR', 'Newsletter subscription error: ' . $e->getMessage());
}

echo json_encode($response);
