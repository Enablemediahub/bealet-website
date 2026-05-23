<?php
/**
 * Bealet Website - Order Tracking Page
 */

require_once __DIR__ . '/inc/header.php';

global $db;

try {
    $db->update("
        ALTER TABLE orders
        ADD COLUMN order_phone VARCHAR(30) NULL AFTER guest_email
    ");
} catch (Throwable $e) {
    // Column likely already exists.
}

$order = null;
$orderItems = [];
$matchingOrders = [];
$errors = [];

$trackingCode = sanitize($_GET['code'] ?? $_POST['tracking_code'] ?? '');
$phoneLookup = sanitize($_GET['phone'] ?? $_POST['phone_number'] ?? '');
$normalizedPhoneLookup = $phoneLookup !== '' ? normalizePhoneNumber($phoneLookup) : '';
$selectedOrderId = (int) ($_GET['order_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trackingCode = sanitize($_POST['tracking_code'] ?? '');
    $phoneLookup = sanitize($_POST['phone_number'] ?? '');
    $normalizedPhoneLookup = $phoneLookup !== '' ? normalizePhoneNumber($phoneLookup) : '';

    if ($trackingCode === '' && $phoneLookup === '') {
        $errors[] = 'Enter your tracking code or phone number.';
    }
}

if ($trackingCode !== '' || $phoneLookup !== '') {
    if ($trackingCode !== '') {
        $matchingOrders = $db->fetchAll(
            "SELECT o.*,
                    COALESCE(o.order_phone, u.phone, '') AS tracking_phone
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             WHERE o.tracking_code = ?
             ORDER BY o.created_at DESC",
            [$trackingCode]
        );
    } else {
        $matchingOrders = $db->fetchAll(
            "SELECT o.*,
                    COALESCE(o.order_phone, u.phone, '') AS tracking_phone
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             WHERE (o.order_phone IN (?, ?)
                    OR u.phone IN (?, ?)
                    OR REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(o.order_phone, ''), ' ', ''), '-', ''), '(', ''), ')', '') IN (?, ?)
                    OR REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(u.phone, ''), ' ', ''), '-', ''), '(', ''), ')', '') IN (?, ?))
             ORDER BY o.created_at DESC",
            [$phoneLookup, $normalizedPhoneLookup, $phoneLookup, $normalizedPhoneLookup, $phoneLookup, ltrim($normalizedPhoneLookup, '+'), $phoneLookup, ltrim($normalizedPhoneLookup, '+')]
        );
    }

    if (empty($matchingOrders)) {
        $errors[] = 'No order found for the details entered.';
    } else {
        $order = $matchingOrders[0];
        if ($selectedOrderId > 0) {
            foreach ($matchingOrders as $candidate) {
                if ((int) $candidate['id'] === $selectedOrderId) {
                    $order = $candidate;
                    break;
                }
            }
        }

        $orderItems = $db->fetchAll(
            "SELECT oi.*, p.name, p.main_image
             FROM order_items oi
             JOIN products p ON oi.product_id = p.id
             WHERE oi.order_id = ?",
            [$order['id']]
        );
    }
}

$statusSteps = ['pending', 'processing', 'shipped', 'delivered'];
$currentStatusIndex = array_search($order['status'] ?? 'pending', $statusSteps, true);
if ($currentStatusIndex === false) {
    $currentStatusIndex = 0;
}
$progressPercent = (int) round((($currentStatusIndex + 1) / count($statusSteps)) * 100);

?>

<style>
    .tracking-cta-card {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
        border: none;
        border-radius: 20px;
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.15);
    }

    .tracking-cta-card .form-control {
        border-radius: 12px;
        min-height: 52px;
    }

    .tracking-progress-shell {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 14px;
    }

    .tracking-progress-bar {
        height: 10px;
        border-radius: 999px;
        overflow: hidden;
        background: #e2e8f0;
    }

    .tracking-progress-bar > span {
        display: block;
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #2563eb, #22c55e);
        transition: width 0.6s ease;
        animation: progressPulse 2.2s ease-in-out infinite;
    }

    .status-node {
        width: 62px;
        height: 62px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        border: 2px solid #dbeafe;
        background: #ffffff;
    }

    .status-node.done {
        background: #16a34a;
        border-color: #16a34a;
        color: #ffffff;
    }

    .status-node.current {
        background: #2563eb;
        border-color: #2563eb;
        color: #ffffff;
        animation: currentStepPulse 1.8s ease-in-out infinite;
    }

    @keyframes currentStepPulse {
        0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.45); }
        70% { box-shadow: 0 0 0 14px rgba(37, 99, 235, 0); }
        100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); }
    }

    @keyframes progressPulse {
        0%, 100% { filter: saturate(1); }
        50% { filter: saturate(1.25); }
    }
</style>

    <nav aria-label="breadcrumb" class="mt-4 mb-4">
        <div class="container-lg">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/">Home</a></li>
                <li class="breadcrumb-item active">Track Shipment</li>
            </ol>
        </div>
    </nav>

    <section class="mb-4">
        <div class="container-lg">
            <h1 class="mb-2">Track Your Shipment</h1>
            <p class="text-muted mb-0">Use your tracking code format like <strong>BOC/0001/2026/JD</strong> or your checkout phone number.</p>
        </div>
    </section>

    <section class="section-spacing pt-2">
        <div class="container-lg">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="card tracking-cta-card text-white mb-4">
                        <div class="card-body p-4 p-lg-5">
                            <h4 class="mb-2">Track Shipping Progress</h4>
                            <p class="mb-4 text-white-50">Enter either tracking code or phone number to view live order movement.</p>
                            <form method="POST" class="mb-0">
                                <div class="row g-2">
                                    <div class="col-lg-5">
                                        <input type="text" class="form-control" name="tracking_code" placeholder="BOC/0001/2026/XXX" value="<?php echo sanitize($trackingCode); ?>">
                                    </div>
                                    <div class="col-lg-4">
                                        <input type="text" class="form-control" name="phone_number" placeholder="+233 24 000 0000" value="<?php echo sanitize($phoneLookup); ?>" inputmode="tel">
                                    </div>
                                    <div class="col-lg-3 d-grid">
                                        <button class="btn btn-warning btn-lg fw-bold" type="submit">
                                            <i class="fas fa-location-arrow me-2"></i>Track Now
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php foreach ($errors as $error): ?>
                        <div><?php echo sanitize($error); ?></div>
                        <?php endforeach; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($matchingOrders) && count($matchingOrders) > 1): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h6 class="mb-3">Matching Orders</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($matchingOrders as $match): ?>
                                <a class="btn btn-sm <?php echo ((int) $match['id'] === (int) ($order['id'] ?? 0)) ? 'btn-primary' : 'btn-outline-primary'; ?>"
                                   href="<?php echo APP_URL; ?>/track-order.php?phone=<?php echo urlencode($phoneLookup); ?>&order_id=<?php echo (int) $match['id']; ?>">
                                    <?php echo sanitize($match['tracking_code']); ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($order): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-box-open me-2"></i>Shipment In Progress</h5>
                        </div>
                        <div class="card-body">
                            <div class="tracking-progress-shell mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>Delivery Progress</strong>
                                    <span class="badge bg-dark"><?php echo $progressPercent; ?>%</span>
                                </div>
                                <div class="tracking-progress-bar">
                                    <span style="width: <?php echo $progressPercent; ?>%;"></span>
                                </div>
                            </div>

                            <div class="row g-4 mb-4">
                                <?php for ($i = 0; $i < count($statusSteps); $i++): ?>
                                <?php
                                $statusClass = '';
                                if ($i < $currentStatusIndex) {
                                    $statusClass = 'done';
                                } elseif ($i === $currentStatusIndex) {
                                    $statusClass = 'current';
                                }
                                ?>
                                <div class="col-md-6 col-lg-3 text-center">
                                    <div class="status-node <?php echo $statusClass; ?>">
                                        <i class="fas <?php
                                            if ($statusSteps[$i] === 'pending') echo 'fa-receipt';
                                            elseif ($statusSteps[$i] === 'processing') echo 'fa-gears';
                                            elseif ($statusSteps[$i] === 'shipped') echo 'fa-truck-fast';
                                            else echo 'fa-check';
                                        ?>"></i>
                                    </div>
                                    <div class="mt-2 text-capitalize fw-semibold"><?php echo sanitize($statusSteps[$i]); ?></div>
                                    <?php if ($i === $currentStatusIndex): ?>
                                    <small class="text-primary fw-semibold">Current Step</small>
                                    <?php endif; ?>
                                </div>
                                <?php endfor; ?>
                            </div>

                            <div class="row g-3 border-top pt-3">
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Tracking Code</small>
                                    <strong><?php echo sanitize($order['tracking_code']); ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Phone</small>
                                    <strong><?php echo sanitize((string) ($order['tracking_phone'] ?? $order['order_phone'] ?? '')); ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Order Date</small>
                                    <strong><?php echo formatDate($order['created_at']); ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Estimated Delivery</small>
                                    <strong><?php echo formatDate(date('Y-m-d', strtotime($order['created_at'] . ' +7 days'))); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-map-pin me-2"></i>Shipping Address</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-0" style="white-space: pre-wrap;"><?php echo sanitize($order['shipping_address']); ?></p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-shopping-bag me-2"></i>Order Items</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Qty</th>
                                            <th>Price</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orderItems as $item): ?>
                                        <?php
                                            $itemName = (string) ($item['product_name'] ?? $item['name'] ?? 'Product');
                                            $itemPrice = (float) ($item['product_price'] ?? 0);
                                            $itemQuantity = (int) ($item['quantity'] ?? 0);
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="<?php echo getProductImagePath($item); ?>" alt="<?php echo sanitize($itemName); ?>" style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover;">
                                                    <strong><?php echo sanitize($itemName); ?></strong>
                                                </div>
                                            </td>
                                            <td><?php echo $itemQuantity; ?></td>
                                            <td><?php echo formatCurrency($itemPrice); ?></td>
                                            <td><strong><?php echo formatCurrency($itemPrice * $itemQuantity); ?></strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
