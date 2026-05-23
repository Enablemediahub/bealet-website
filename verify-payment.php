<?php
/**
 * Bealet Website - Verify Payment
 */

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

$reference = sanitize($_GET['reference'] ?? '');
if (empty($reference)) {
    setFlashMessage('error', 'Payment reference is missing.');
    redirect(APP_URL . '/shop.php');
}

global $db;

$paystackSecret = PAYSTACK_SECRET_KEY;
if (empty($paystackSecret) || $paystackSecret === 'your_paystack_secret_key') {
    setFlashMessage('error', 'Paystack is not configured.');
    redirect(APP_URL . '/checkout.php');
}

$ch = curl_init(PAYSTACK_VERIFY_URL . $reference);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $paystackSecret,
    'Content-Type: application/json'
]);

$result = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($result === false) {
    setFlashMessage('error', 'Unable to verify payment. Please try again.');
    redirect(APP_URL . '/checkout.php');
}

$response = json_decode($result, true);

if (!$response || !isset($response['status']) || $response['status'] !== true) {
    $message = $response['message'] ?? 'Payment verification failed.';
    setFlashMessage('error', sanitize($message));
    redirect(APP_URL . '/checkout.php');
}

$paymentData = $response['data'];
$orderId = $paymentData['metadata']['order_id'] ?? null;
$trackingCode = $paymentData['metadata']['tracking_code'] ?? null;
$status = $paymentData['status'] ?? 'failed';

if (!$orderId || $status !== 'success') {
    setFlashMessage('error', 'Payment did not complete successfully.');
    redirect(APP_URL . '/checkout.php');
}

$order = $db->fetch("SELECT * FROM orders WHERE id = ?", [$orderId]);
if (!$order) {
    setFlashMessage('error', 'Order record not found.');
    redirect(APP_URL . '/shop.php');
}

if ($order['payment_status'] !== 'paid') {
    $db->update(
        "UPDATE orders SET payment_status = 'paid', status = 'processing', updated_at = NOW() WHERE id = ?",
        [$orderId]
    );
    createLog('PAYMENT_VERIFIED', 'Payment verified for order ID: ' . $orderId, $order['user_id']);

    $updatedOrder = $db->fetch("SELECT * FROM orders WHERE id = ?", [$orderId]);
    $smsResult = sendOrderTrackingSms($updatedOrder ?: $order);
    if (!($smsResult['success'] ?? false)) {
        createLog('SMS_ERROR', 'Order SMS failed for order ID ' . $orderId . ': ' . ($smsResult['message'] ?? 'Unknown SMS error'), $order['user_id']);
    } else {
        createLog('SMS_SENT', 'Order SMS sent successfully for order ID ' . $orderId, $order['user_id']);
    }
}

setFlashMessage('success', 'Payment successful. Your order is now being processed.');
redirect(APP_URL . '/order-confirmation.php?tracking_code=' . urlencode($trackingCode));
