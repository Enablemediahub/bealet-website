<?php
/**
 * Bealet Website - API: Get Cart Count
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

$response = ['success' => true, 'count' => getCartCount()];

echo json_encode($response);
