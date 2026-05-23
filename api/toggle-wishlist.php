<?php
/**
 * Bealet Website - API: Toggle Wishlist
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

$response = ['success' => false, 'message' => '', 'action' => ''];

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
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    $response['message'] = 'Please login to use wishlist';
    echo json_encode($response);
    exit;
}

global $db;

// Check if product exists
$product = $db->fetch("SELECT id FROM products WHERE id = ? AND is_active = 1", [$productId]);
if (!$product) {
    $response['message'] = 'Product not found';
    echo json_encode($response);
    exit;
}

try {
    // Check if already in wishlist
    $wishlistItem = $db->fetch(
        "SELECT id FROM wishlist WHERE product_id = ? AND user_id = ?",
        [$productId, $userId]
    );
    
    if ($wishlistItem) {
        // Remove from wishlist
        $db->update("DELETE FROM wishlist WHERE id = ?", [$wishlistItem['id']]);
        $response['action'] = 'removed';
        $response['message'] = 'Removed from wishlist';
    } else {
        // Add to wishlist
        $db->update(
            "INSERT INTO wishlist (product_id, user_id, created_at) VALUES (?, ?, NOW())",
            [$productId, $userId]
        );
        $response['action'] = 'added';
        $response['message'] = 'Added to wishlist';
    }
    
    $response['success'] = true;
    
} catch (Exception $e) {
    $response['message'] = 'Error updating wishlist';
    createLog('WISHLIST_ERROR', 'Wishlist error: ' . $e->getMessage());
}

echo json_encode($response);
