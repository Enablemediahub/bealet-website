<?php
/**
 * Bealet Website - API: Cart Preview
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

$items = getCartItems();
$total = getCartTotal();

$payloadItems = array_map(function ($item) {
    return [
        'id' => (int) ($item['id'] ?? 0),
        'product_id' => (int) ($item['product_id'] ?? 0),
        'name' => (string) ($item['name'] ?? ''),
        'price' => (float) ($item['price'] ?? 0),
        'quantity' => (int) ($item['quantity'] ?? 0),
        'line_total' => (float) (($item['price'] ?? 0) * ($item['quantity'] ?? 0)),
        'image_url' => getProductImagePath($item),
    ];
}, $items);

echo json_encode([
    'success' => true,
    'count' => getCartCount(),
    'total' => (float) $total,
    'items' => $payloadItems,
]);
