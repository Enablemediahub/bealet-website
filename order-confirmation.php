<?php
/**
 * Bealet Website - Order Confirmation
 */

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

$trackingCode = sanitize($_GET['tracking_code'] ?? $_SESSION['tracking_code'] ?? '');
if (empty($trackingCode)) {
    setFlashMessage('error', 'Order tracking information is missing.');
    redirect(APP_URL . '/shop.php');
}

global $db;

$order = $db->fetch("SELECT * FROM orders WHERE tracking_code = ?", [$trackingCode]);
if (!$order) {
    setFlashMessage('error', 'Order not found.');
    redirect(APP_URL . '/shop.php');
}

$orderItems = $db->fetchAll(
    "SELECT oi.*, p.name, p.main_image as image
     FROM order_items oi
     JOIN products p ON oi.product_id = p.id
     WHERE oi.order_id = ?",
    [$order['id']]
);

$subtotal = 0;
foreach ($orderItems as $item) {
    $linePrice = (float) ($item['product_price'] ?? 0);
    $subtotal += ((int) ($item['quantity'] ?? 0)) * $linePrice;
}

$taxIncluded = $order['total_amount'] * (VAT_RATE / (1 + VAT_RATE));

require_once __DIR__ . '/inc/header.php';
?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1>Order Confirmation</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item active">Order Confirmation</li>
                </ol>
            </nav>
        </div>
    </div>

    <section class="section-spacing">
        <div class="container-lg">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h2 class="mb-3">Thank you for your order!</h2>
                            <p class="text-muted">Your order has been received and is now being processed. You can track it using your unique tracking code or the phone number used at checkout.</p>
                            <div class="p-4 rounded-3" style="background: rgba(37, 99, 235, 0.05);">
                                <p class="mb-1 text-muted">Tracking Code</p>
                                <h3 class="mb-0 text-primary"><?php echo sanitize($order['tracking_code']); ?></h3>
                            </div>
                            <?php if (!empty($order['order_phone'])): ?>
                            <div class="p-4 rounded-3 mt-3 border" style="background: rgba(15, 23, 42, 0.03);">
                                <p class="mb-1 text-muted">Tracking Phone Number</p>
                                <h5 class="mb-0"><?php echo sanitize($order['order_phone']); ?></h5>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="mb-3">Order Summary</h5>
                            <?php foreach ($orderItems as $item): ?>
                            <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?php echo !empty($item['image']) ? APP_URL . '/' . $item['image'] : 'https://via.placeholder.com/80'; ?>" alt="<?php echo sanitize($item['name']); ?>" width="80" height="80" class="rounded">
                                    <div>
                                        <h6 class="mb-1"><?php echo sanitize((string) ($item['product_name'] ?? $item['name'] ?? 'Product')); ?></h6>
                                        <small class="text-muted">Qty: <?php echo $item['quantity']; ?></small>
                                    </div>
                                </div>
                                <div><strong><?php echo formatCurrency(((float) ($item['product_price'] ?? 0)) * ((int) ($item['quantity'] ?? 0))); ?></strong></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="mb-3">Payment & Shipping</h5>
                            <p class="mb-1"><strong>Status:</strong> <span class="badge bg-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>"><?php echo ucfirst($order['payment_status']); ?></span></p>
                            <p class="mb-1"><strong>Order Status:</strong> <?php echo ucfirst($order['status']); ?></p>
                            <p class="mb-1"><strong>Total (Tax Inclusive):</strong> <?php echo formatCurrency($order['total_amount']); ?></p>
                            <p class="mb-1"><strong>VAT Included (20%):</strong> <?php echo formatCurrency($taxIncluded); ?></p>
                            <hr>
                            <p class="mb-1"><strong>Shipping Address:</strong></p>
                            <p class="text-muted small" style="white-space: pre-wrap;"><?php echo sanitize($order['shipping_address']); ?></p>
                        </div>
                    </div>
                    
                    <a href="<?php echo APP_URL; ?>/track-order.php?code=<?php echo urlencode($order['tracking_code']); ?>" class="btn btn-outline-primary w-100 mb-3">
                        <i class="fas fa-truck me-2"></i> Track Your Order
                    </a>
                    <?php if (!empty($order['order_phone'])): ?>
                    <a href="<?php echo APP_URL; ?>/track-order.php?phone=<?php echo urlencode($order['order_phone']); ?>" class="btn btn-outline-secondary w-100 mb-3">
                        <i class="fas fa-phone me-2"></i> Track With Phone Number
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo APP_URL; ?>/shop.php" class="btn btn-primary w-100">
                        <i class="fas fa-shopping-bag me-2"></i> Continue Shopping
                    </a>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
