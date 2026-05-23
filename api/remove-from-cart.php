<?php
/**
 * Bealet Website - API: Remove from Cart
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

$response = ['success' => false, 'message' => '', 'count' => 0];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Method not allowed';
    echo json_encode($response);
    exit;
}

// Handle both JSON and form data
$input = $_POST;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (empty($input) && stripos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}

if (!isset($input['cart_id'])) {
    $response['message'] = 'Cart ID is required';
    echo json_encode($response);
    exit;
}

$cartId = (int)$input['cart_id'];
$userId = $_SESSION['user_id'] ?? null;
$sessionId = session_id();

global $db;

try {
    // Verify cart item belongs to user
    $cartItem = $db->fetch(
        "SELECT id FROM cart WHERE id = ? AND (user_id = ? OR session_id = ?)",
        [$cartId, $userId, $sessionId]
    );
    
    if (!$cartItem) {
        http_response_code(403);
        $response['message'] = 'Unauthorized';
        echo json_encode($response);
        exit;
    }
    
    // Remove from cart
    $db->update("DELETE FROM cart WHERE id = ?", [$cartId]);
    
    $response['success'] = true;
    $response['message'] = 'Item removed from cart';
    $response['count'] = getCartCount();
    
} catch (Exception $e) {
    $response['message'] = 'Error removing from cart';
    createLog('CART_ERROR', 'Remove from cart error: ' . $e->getMessage());
}

echo json_encode($response);
