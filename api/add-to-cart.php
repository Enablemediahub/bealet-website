<?php
/**
 * Bealet Website - API: Add to Cart
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

if (!isset($input['product_id'])) {
    $response['message'] = 'Product ID is required';
    echo json_encode($response);
    exit;
}

$productId = (int)$input['product_id'];
$quantity = (int)($input['quantity'] ?? 1);
$userId = $_SESSION['user_id'] ?? null;
$sessionId = session_id();

if ($quantity < 1) {
    $response['message'] = 'Invalid quantity';
    echo json_encode($response);
    exit;
}

global $db;

// Check if product exists
$product = $db->fetch("SELECT id, stock FROM products WHERE id = ? AND is_active = 1", [$productId]);
if (!$product) {
    $response['message'] = 'Product not found';
    echo json_encode($response);
    exit;
}

// Check stock
if ($product['stock'] < $quantity) {
    $response['message'] = 'Insufficient stock';
    echo json_encode($response);
    exit;
}

try {
    // Check if item already in cart
    $cartItem = $db->fetch(
        "SELECT id FROM cart WHERE product_id = ? AND (user_id = ? OR session_id = ?)",
        [$productId, $userId, $sessionId]
    );
    
    if ($cartItem) {
        $db->update(
            "UPDATE cart SET quantity = quantity + ? WHERE id = ?",
            [$quantity, $cartItem['id']]
        );
    } else {
        $db->update(
            "INSERT INTO cart (product_id, quantity, user_id, session_id, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$productId, $quantity, $userId, $sessionId]
        );
    }
    
    $response['success'] = true;
    $response['message'] = 'Item added to cart';
    $response['count'] = getCartCount();
    
} catch (Exception $e) {
    $response['message'] = 'Error adding to cart';
    createLog('CART_ERROR', 'Add to cart error: ' . $e->getMessage());
}

echo json_encode($response);
