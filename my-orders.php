<?php
/**
 * Bealet Website - My Orders
 */

session_start();

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

// Check login
if (!isLoggedIn()) {
    redirect(APP_URL . '/login.php');
}

require_once __DIR__ . '/inc/header.php';

global $db;

$user = getCurrentUser();

// Get user's orders
$orders = $db->fetchAll(
    "SELECT id, tracking_code, total_amount, status, payment_status, created_at, 
            (SELECT COUNT(*) FROM order_items WHERE order_id = orders.id) as item_count
     FROM orders 
     WHERE user_id = ?
     ORDER BY created_at DESC",
    [$user['id']]
);

?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1>My Orders</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item active">Orders</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Orders Content -->
    <div class="container my-5">
        <?php if (empty($orders)): ?>
        <div class="text-center py-5">
            <i class="fas fa-shopping-bag" style="font-size: 4rem; color: #ccc; margin-bottom: 1rem;"></i>
            <h3>You haven't placed any orders yet</h3>
            <p class="text-muted mb-4">Start shopping and discover our amazing collection of eyewear</p>
            <a href="<?php echo APP_URL; ?>/shop.php" class="btn btn-primary">
                <i class="fas fa-shopping-bag me-2"></i> Start Shopping
            </a>
        </div>
        <?php else: ?>
        <div class="row mb-4">
            <div class="col-12">
                <p class="text-muted"><?php echo count($orders); ?> order(s) found</p>
            </div>
        </div>
        
        <!-- Orders Table -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Tracking Code</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong><?php echo sanitize($order['tracking_code']); ?></strong></td>
                        <td><?php echo formatDate($order['created_at']); ?></td>
                        <td><span class="badge bg-secondary"><?php echo $order['item_count']; ?></span></td>
                        <td><?php echo formatCurrency($order['total_amount']); ?></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo $order['status'] === 'pending' ? 'warning' : 
                                     ($order['status'] === 'processing' ? 'info' : 
                                      ($order['status'] === 'shipped' ? 'secondary' : 'success'));
                            ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo APP_URL; ?>/track-order.php?code=<?php echo $order['tracking_code']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
