<?php
/**
 * Bealet Website - Process Payment
 */

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($orderId <= 0) {
    setFlashMessage('error', 'Invalid payment request.');
    redirect(APP_URL . '/shop.php');
}

global $db;

$order = $db->fetch("SELECT o.*, u.email as user_email FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?", [$orderId]);

if (!$order) {
    setFlashMessage('error', 'Order not found.');
    redirect(APP_URL . '/shop.php');
}

if ($order['payment_status'] === 'paid') {
    setFlashMessage('success', 'Payment already completed.');
    redirect(APP_URL . '/order-confirmation.php?tracking_code=' . urlencode($order['tracking_code']));
}

$email = $order['user_email'] ?: $order['guest_email'];
if (empty($email)) {
    setFlashMessage('error', 'Valid email address is required for payment.');
    redirect(APP_URL . '/checkout.php');
}

$amount = (int)round($order['total_amount'] * 100);

// Initialize Paystack transaction
$paystackSecret = PAYSTACK_SECRET_KEY;
if (empty($paystackSecret) || $paystackSecret === 'your_paystack_secret_key') {
    setFlashMessage('error', 'Paystack is not configured. Please set the secret key in config.php.');
    redirect(APP_URL . '/checkout.php');
}

$callbackUrl = APP_URL . '/verify-payment.php';

$payload = json_encode([
    'email' => $email,
    'amount' => $amount,
    'currency' => 'GHS',
    'callback_url' => $callbackUrl,
    'metadata' => [
        'order_id' => $orderId,
        'tracking_code' => $order['tracking_code']
    ]
]);

$ch = curl_init('https://api.paystack.co/transaction/initialize');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $paystackSecret,
    'Content-Type: application/json'
]);

$result = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($result === false) {
    setFlashMessage('error', 'Unable to contact Paystack. Please try again.');
    redirect(APP_URL . '/checkout.php');
}

$response = json_decode($result, true);

if (!$response || !isset($response['status']) || $response['status'] !== true) {
    $message = $response['message'] ?? 'Payment initialization failed.';
    setFlashMessage('error', sanitize($message));
    redirect(APP_URL . '/checkout.php');
}

$authorizationUrl = $response['data']['authorization_url'] ?? null;
if (!$authorizationUrl) {
    setFlashMessage('error', 'Unable to initialize payment.');
    redirect(APP_URL . '/checkout.php');
}

// Redirect to Paystack payment page
header('Location: ' . $authorizationUrl);
exit;
