<?php
/**
 * Bealet Website - Admin Dashboard
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Verify admin access
if (!isLoggedIn() || !isAdmin()) {
    redirect(APP_URL . '/admin/login.php');
}

require_once __DIR__ . '/inc/header.php';

global $db;

// Get dashboard statistics
$today = date('Y-m-d');
$thisWeek = date('Y-m-d', strtotime('-7 days'));
$thisMonth = date('Y-m-d', strtotime('-30 days'));

// Sales data
$todaySales = $db->fetch(
    "SELECT SUM(total_amount) as total FROM orders WHERE DATE(created_at) = ? AND payment_status = 'paid'",
    [$today]
);

$weekSales = $db->fetch(
    "SELECT SUM(total_amount) as total FROM orders WHERE created_at >= ? AND payment_status = 'paid'",
    [$thisWeek]
);

$monthSales = $db->fetch(
    "SELECT SUM(total_amount) as total FROM orders WHERE created_at >= ? AND payment_status = 'paid'",
    [$thisMonth]
);

// Orders data
$totalOrders = $db->fetch("SELECT COUNT(*) as total FROM orders");
$pendingOrders = $db->fetch("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
$processingOrders = $db->fetch("SELECT COUNT(*) as total FROM orders WHERE status = 'processing'");
$shippedOrders = $db->fetch("SELECT COUNT(*) as total FROM orders WHERE status = 'shipped'");
$deliveredOrders = $db->fetch("SELECT COUNT(*) as total FROM orders WHERE status = 'delivered'");

// Appointments data
$totalAppointments = $db->fetch("SELECT COUNT(*) as total FROM appointments");
$pendingAppointments = $db->fetch("SELECT COUNT(*) as total FROM appointments WHERE status = 'pending'");
$confirmedAppointments = $db->fetch("SELECT COUNT(*) as total FROM appointments WHERE status = 'confirmed'");

// Products data
$totalProducts = $db->fetch("SELECT COUNT(*) as total FROM products");
$lowStockProducts = $db->fetch("SELECT COUNT(*) as total FROM products WHERE stock < 10");

// Customers data
$totalCustomers = $db->fetch("SELECT COUNT(*) as total FROM users WHERE is_admin = 0");
$recentCustomers = $db->fetchAll(
    "SELECT id, name, email, created_at FROM users WHERE is_admin = 0 ORDER BY created_at DESC LIMIT 5"
);

// Recent orders
$recentOrders = $db->fetchAll(
    "SELECT id, tracking_code, total_amount, status, created_at FROM orders ORDER BY created_at DESC LIMIT 10"
);

$todayRevenue = (float) ($todaySales['total'] ?? 0);
$weeklyRevenue = (float) ($weekSales['total'] ?? 0);
$monthlyRevenue = (float) ($monthSales['total'] ?? 0);
$customerCount = (int) ($totalCustomers['total'] ?? 0);
$productCount = (int) ($totalProducts['total'] ?? 0);
$lowStockCount = (int) ($lowStockProducts['total'] ?? 0);
$pendingOrderCount = (int) ($pendingOrders['total'] ?? 0);
$confirmedAppointmentCount = (int) ($confirmedAppointments['total'] ?? 0);
$totalOrderCount = (int) ($totalOrders['total'] ?? 0);

?>

    <div class="admin-shell">
        <div class="dashboard-grid">
            <section class="dashboard-panel dashboard-hero">
                <span class="eyebrow"><i class="fas fa-sliders"></i> Refined control center</span>
                <h1>See the business at a glance.</h1>
                <p>
                    The dashboard is now organized like a calm workspace: sales momentum up front, operations in focus,
                    and quick actions kept close without cluttering the screen.
                </p>

                <div class="hero-actions">
                    <a href="<?php echo APP_URL; ?>/admin/orders.php" class="hero-btn hero-btn-primary">
                        <i class="fas fa-shopping-bag"></i>
                        <span>Review Orders</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/products.php?action=add" class="hero-btn hero-btn-secondary">
                        <i class="fas fa-plus"></i>
                        <span>Add Product</span>
                    </a>
                </div>

                <div class="hero-spotlight">
                    <div class="spotlight-card">
                        <span>Today</span>
                        <strong><?php echo formatCurrency($todayRevenue); ?></strong>
                        <small>Paid orders processed today</small>
                    </div>
                    <div class="spotlight-card">
                        <span>Attention Needed</span>
                        <strong><?php echo $pendingOrderCount + (int) $pendingAppointments['total']; ?></strong>
                        <small>Pending orders and appointments waiting</small>
                    </div>
                    <div class="spotlight-card">
                        <span>Catalog Health</span>
                        <strong><?php echo $productCount; ?></strong>
                        <small><?php echo $lowStockCount; ?> products are low in stock</small>
                    </div>
                </div>
            </section>

            <section class="metrics-grid">
                <article class="dashboard-panel metric-card">
                    <div class="metric-icon"><i class="fas fa-wallet"></i></div>
                    <span>Weekly Revenue</span>
                    <strong><?php echo formatCurrency($weeklyRevenue); ?></strong>
                    <small>Last 7 days of paid orders</small>
                </article>
                <article class="dashboard-panel metric-card">
                    <div class="metric-icon"><i class="fas fa-chart-line"></i></div>
                    <span>Monthly Revenue</span>
                    <strong><?php echo formatCurrency($monthlyRevenue); ?></strong>
                    <small>Trailing 30-day performance</small>
                </article>
                <article class="dashboard-panel metric-card">
                    <div class="metric-icon"><i class="fas fa-users"></i></div>
                    <span>Customers</span>
                    <strong><?php echo $customerCount; ?></strong>
                    <small>Registered customer accounts</small>
                </article>
                <article class="dashboard-panel metric-card">
                    <div class="metric-icon"><i class="fas fa-calendar-check"></i></div>
                    <span>Confirmed Visits</span>
                    <strong><?php echo $confirmedAppointmentCount; ?></strong>
                    <small>Appointments confirmed so far</small>
                </article>
            </section>

            <section class="content-grid">
                <div class="dashboard-grid">
                    <section class="dashboard-panel panel-section">
                        <div class="panel-head">
                            <div>
                                <h2>Operations Snapshot</h2>
                                <p>Key workflow counts grouped into clear, scannable cards.</p>
                            </div>
                        </div>

                        <div class="status-grid">
                            <article class="status-card">
                                <div class="status-card-header">
                                    <i class="fas fa-box-open"></i>
                                    <div>
                                        <strong>Orders</strong>
                                        <span class="muted-note"><?php echo $totalOrderCount; ?> orders in the system</span>
                                    </div>
                                </div>
                                <div class="status-list">
                                    <div class="status-item">
                                        <span>Pending</span>
                                        <strong><?php echo $pendingOrders['total']; ?></strong>
                                    </div>
                                    <div class="status-item">
                                        <span>Processing</span>
                                        <strong><?php echo $processingOrders['total']; ?></strong>
                                    </div>
                                    <div class="status-item">
                                        <span>Shipped</span>
                                        <strong><?php echo $shippedOrders['total']; ?></strong>
                                    </div>
                                    <div class="status-item">
                                        <span>Delivered</span>
                                        <strong><?php echo $deliveredOrders['total']; ?></strong>
                                    </div>
                                </div>
                            </article>

                            <article class="status-card">
                                <div class="status-card-header">
                                    <i class="fas fa-calendar"></i>
                                    <div>
                                        <strong>Appointments</strong>
                                        <span class="muted-note"><?php echo $totalAppointments['total']; ?> appointments total</span>
                                    </div>
                                </div>
                                <div class="status-list">
                                    <div class="status-item">
                                        <span>Total</span>
                                        <strong><?php echo $totalAppointments['total']; ?></strong>
                                    </div>
                                    <div class="status-item">
                                        <span>Pending</span>
                                        <strong><?php echo $pendingAppointments['total']; ?></strong>
                                    </div>
                                    <div class="status-item">
                                        <span>Confirmed</span>
                                        <strong><?php echo $confirmedAppointments['total']; ?></strong>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </section>

                    <section class="dashboard-panel panel-section">
                        <div class="panel-head">
                            <div>
                                <h2>Recent Orders</h2>
                                <p>Latest transactions with cleaner status labels and faster access.</p>
                            </div>
                            <a href="<?php echo APP_URL; ?>/admin/orders.php" class="panel-link">
                                <span>All orders</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>

                        <div class="table-shell">
                            <div class="table-responsive">
                                <table class="table table-modern align-middle">
                                    <thead>
                                        <tr>
                                            <th>Tracking Code</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentOrders as $order): ?>
                                        <tr>
                                            <td><strong><?php echo sanitize($order['tracking_code']); ?></strong></td>
                                            <td><?php echo formatCurrency($order['total_amount']); ?></td>
                                            <td>
                                                <span class="status-pill status-<?php echo sanitize($order['status']); ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($order['created_at']); ?></td>
                                            <td>
                                                <a href="<?php echo APP_URL; ?>/admin/orders.php?id=<?php echo $order['id']; ?>" class="table-action">
                                                    <span>Open</span>
                                                    <i class="fas fa-arrow-right"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="dashboard-grid">
                    <section class="dashboard-panel panel-section">
                        <div class="panel-head">
                            <div>
                                <h3>Inventory</h3>
                                <p>Keep the catalog healthy and ready to sell.</p>
                            </div>
                        </div>

                        <div class="admin-list">
                            <div class="inventory-card">
                                <span>Total Products</span>
                                <strong><?php echo $productCount; ?></strong>
                                <small>Live products available across the store.</small>
                            </div>
                            <div class="inventory-card">
                                <span>Low Stock Items</span>
                                <strong><?php echo $lowStockCount; ?></strong>
                                <small>These products need replenishment soon.</small>
                            </div>
                        </div>
                    </section>

                    <section class="dashboard-panel panel-section">
                        <div class="panel-head">
                            <div>
                                <h3>Quick Actions</h3>
                                <p>Most common admin tasks, one click away.</p>
                            </div>
                        </div>

                        <div class="admin-list">
                            <a href="<?php echo APP_URL; ?>/admin/products.php?action=add" class="action-link">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="fas fa-plus"></i>
                                    <div>
                                        <strong>Add a Product</strong>
                                        <span>Create something new for the catalog</span>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <a href="<?php echo APP_URL; ?>/admin/appointments.php" class="action-link">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="fas fa-calendar-check"></i>
                                    <div>
                                        <strong>Review Appointments</strong>
                                        <span>Confirm visits and manage scheduling</span>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <a href="<?php echo APP_URL; ?>/admin/staff.php" class="action-link">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="fas fa-id-badge"></i>
                                    <div>
                                        <strong>Manage Staff</strong>
                                        <span>Update profile cards shown on the staff page</span>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <a href="<?php echo APP_URL; ?>/admin/founder.php" class="action-link">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="fas fa-landmark"></i>
                                    <div>
                                        <strong>Edit Founder Story</strong>
                                        <span>Update the homepage spotlight and digital museum</span>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <a href="<?php echo APP_URL; ?>/admin/customers.php" class="action-link">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="fas fa-user-group"></i>
                                    <div>
                                        <strong>Open Customers</strong>
                                        <span>See recent registrations and activity</span>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    </section>

                    <section class="dashboard-panel panel-section">
                        <div class="panel-head">
                            <div>
                                <h3>Recent Customers</h3>
                                <p>Newest people added to the system.</p>
                            </div>
                            <a href="<?php echo APP_URL; ?>/admin/customers.php" class="panel-link">
                                <span>View all</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>

                        <div class="admin-list">
                            <?php foreach ($recentCustomers as $customer): ?>
                            <div class="customer-row">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="customer-avatar">
                                        <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo sanitize($customer['name']); ?></strong>
                                        <span><?php echo sanitize($customer['email']); ?></span>
                                    </div>
                                </div>
                                <span><?php echo formatDate($customer['created_at']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </aside>
            </section>
        </div>
    </div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
