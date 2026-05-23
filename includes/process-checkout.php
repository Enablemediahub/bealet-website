<?php
/**
 * Bealet Website - Process Checkout
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

global $db;

function processCheckout($shippingData, $userId = null, $sessionId = null) {
    if (!$sessionId) $sessionId = session_id();
    
    try {
        $db->beginTransaction();
        
        // Get cart items
        $cartItems = $db->fetchAll(
            "SELECT c.id, c.product_id, c.quantity, p.price, p.name 
             FROM cart c
             JOIN products p ON c.product_id = p.id
             WHERE c.user_id = ? OR c.session_id = ?",
            [$userId, $sessionId]
        );
        
        if (empty($cartItems)) {
            throw new Exception('Cart is empty');
        }
        
        // Calculate totals
        $subtotal = 0;
        foreach ($cartItems as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        
        // Prices are tax-inclusive; derive VAT amount from gross total.
        $tax = $subtotal * (VAT_RATE / (1 + VAT_RATE));
        $total = $subtotal;
        
        // Create order
        $trackingCode = generateTrackingCode(trim(($shippingData['first_name'] ?? '') . ' ' . ($shippingData['last_name'] ?? '')));
        $region = $shippingData['region'] ?? ($shippingData['state'] ?? '');
        $zip = $shippingData['zip'] ?? '';
        $shippingAddress = "{$shippingData['first_name']} {$shippingData['last_name']}, {$shippingData['address']}, {$shippingData['city']}, {$region}";
        if (!empty($zip)) {
            $shippingAddress .= ", {$zip}";
        }
        
        $db->update(
            "INSERT INTO orders (user_id, order_phone, tracking_code, shipping_address, total_amount, tax_amount, status, payment_status, payment_method, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, NOW(), NOW())",
            [$userId, ($shippingData['phone'] ?? ''), $trackingCode, $shippingAddress, $total, $tax, $shippingData['payment_method']]
        );
        
        $orderId = $db->fetch("SELECT LAST_INSERT_ID() as id")['id'];
        
        // Add order items
        foreach ($cartItems as $item) {
            $db->update(
                "INSERT INTO order_items (order_id, product_id, quantity, price, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$orderId, $item['product_id'], $item['quantity'], $item['price']]
            );
        }
        
        // Clear cart
        $db->update("DELETE FROM cart WHERE user_id = ? OR session_id = ?", [$userId, $sessionId]);
        
        $db->commit();
        
        // Send confirmation email
        sendOrderConfirmationEmail([
            'id' => $orderId,
            'tracking_code' => $trackingCode,
            'total_amount' => $total,
            'customer_email' => $shippingData['email'],
            'customer_name' => "{$shippingData['first_name']} {$shippingData['last_name']}",
            'items' => $cartItems
        ]);
        
        return [
            'success' => true,
            'order_id' => $orderId,
            'tracking_code' => $trackingCode,
            'total' => $total
        ];
        
    } catch (Exception $e) {
        $db->rollBack();
        createLog('CHECKOUT_ERROR', 'Checkout error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

function sendOrderConfirmationEmail($order) {
    $orderDate = formatDate(date('Y-m-d H:i:s'));
    $itemsHtml = '';
    
    foreach ($order['items'] as $item) {
        $itemsHtml .= "
            <tr>
                <td>{$item['name']}</td>
                <td>{$item['quantity']}</td>
                <td>" . formatCurrency($item['price']) . "</td>
                <td>" . formatCurrency($item['price'] * $item['quantity']) . "</td>
            </tr>
        ";
    }
    
    $customerSubject = "Order Confirmation - " . APP_NAME;
    $customerMessage = "
        <h2>Order Confirmation</h2>
        <p>Dear {$order['customer_name']},</p>
        <p>Thank you for your order! We're excited to get your items to you.</p>
        <p><strong>Order Details:</strong></p>
        <p>Tracking Code: <strong>{$order['tracking_code']}</strong></p>
        <p>Order Date: $orderDate</p>
        <table style='width: 100%; border-collapse: collapse;'>
            <tr style='background: #f0f0f0;'>
                <th style='padding: 10px; text-align: left;'>Product</th>
                <th style='padding: 10px; text-align: left;'>Qty</th>
                <th style='padding: 10px; text-align: left;'>Price</th>
                <th style='padding: 10px; text-align: left;'>Total</th>
            </tr>
            $itemsHtml
        </table>
        <p><strong>Total Amount: " . formatCurrency($order['total_amount']) . "</strong></p>
        <p>You can track your order using your tracking code at: " . APP_URL . "/track-order.php</p>
        <p>Best regards,<br>" . APP_NAME . " Team</p>
    ";
    
    sendEmail($order['customer_email'], $customerSubject, $customerMessage);
    
    // Email to admin
    $adminSubject = "New Order - " . APP_NAME;
    $adminMessage = "
        <h2>New Order Received</h2>
        <p>Order Tracking Code: {$order['tracking_code']}</p>
        <p>Customer: {$order['customer_name']}</p>
        <p>Email: {$order['customer_email']}</p>
        <p>Total: " . formatCurrency($order['total_amount']) . "</p>
        <p><a href='" . APP_URL . "/admin/orders.php'>View in Admin Panel</a></p>
    ";
    
    sendEmail(ADMIN_EMAIL, $adminSubject, $adminMessage);
}
