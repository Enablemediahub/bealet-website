<?php
/**
 * Bealet Website - Process Order Tracking
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

global $db;

function getOrderByTrackingCode($trackingCode) {
    return $db->fetch(
        "SELECT * FROM orders WHERE tracking_code = ?",
        [$trackingCode]
    );
}

function getOrderItems($orderId) {
    return $db->fetchAll(
        "SELECT oi.*, p.name as product_name FROM order_items oi
         JOIN products p ON oi.product_id = p.id
         WHERE oi.order_id = ?",
        [$orderId]
    );
}

function getOrderStatus($status) {
    $statuses = [
        'pending' => ['label' => 'Pending', 'icon' => 'clock', 'color' => 'warning', 'step' => 1],
        'processing' => ['label' => 'Processing', 'icon' => 'box', 'color' => 'info', 'step' => 2],
        'shipped' => ['label' => 'Shipped', 'icon' => 'truck', 'color' => 'primary', 'step' => 3],
        'delivered' => ['label' => 'Delivered', 'icon' => 'check-circle', 'color' => 'success', 'step' => 4],
        'cancelled' => ['label' => 'Cancelled', 'icon' => 'times-circle', 'color' => 'danger', 'step' => 0],
    ];
    
    return $statuses[$status] ?? $statuses['pending'];
}

function getEstimatedDeliveryDate($orderId, $orderDate) {
    // Add 7 days to order date for estimated delivery
    $estimatedDate = date('Y-m-d', strtotime($orderDate . ' +7 days'));
    return $estimatedDate;
}
