<?php
/**
 * Bealet Website - Admin Customers Management
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(APP_URL . '/admin/login.php');
}

global $db;

function buildGuestCustomerKey($email, $phone, $name) {
    $emailKey = strtolower(trim((string) $email));
    $phoneKey = normalizePhoneNumber((string) $phone);
    $nameKey = strtolower(trim((string) $name));

    if ($emailKey !== '') {
        return 'guest_email_' . md5($emailKey);
    }

    if ($phoneKey !== '') {
        return 'guest_phone_' . md5($phoneKey);
    }

    if ($nameKey !== '') {
        return 'guest_name_' . md5($nameKey);
    }

    return 'guest_unknown_' . md5('guest');
}

// Handle customer status update for registered users only.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $customerId = (int) $_POST['customer_id'];
        $isActive = (int) $_POST['is_active'];

        $db->update(
            "UPDATE users SET is_active = ? WHERE id = ? AND is_admin = 0",
            [$isActive, $customerId]
        );

        createLog('CUSTOMER_STATUS_UPDATED', "Customer #$customerId status changed");
        setFlashMessage('success', 'Customer status updated');
        redirect(APP_URL . '/admin/customers.php');
    }
}

$registeredCustomers = $db->fetchAll(
    "SELECT
        CONCAT('user_', u.id) AS customer_key,
        'registered' AS customer_type,
        u.id AS user_id,
        u.name,
        u.email,
        u.phone,
        u.created_at,
        u.is_active,
        COUNT(o.id) AS order_count,
        COALESCE(SUM(o.total_amount), 0) AS total_spent,
        MAX(o.created_at) AS last_order_at
     FROM users u
     LEFT JOIN orders o ON o.user_id = u.id
     WHERE u.is_admin = 0
     GROUP BY u.id
     ORDER BY u.created_at DESC"
);

$guestCustomers = $db->fetchAll(
    "SELECT
        LOWER(TRIM(
            COALESCE(
                NULLIF(guest_email, ''),
                CONCAT('phone:', COALESCE(NULLIF(order_phone, ''), NULLIF(guest_phone, ''))),
                CONCAT('name:', COALESCE(NULLIF(guest_name, ''), CONCAT('guest-', id)))
            )
        )) AS guest_group_key,
        COALESCE(NULLIF(MAX(guest_name), ''), 'Guest Customer') AS name,
        MAX(NULLIF(guest_email, '')) AS email,
        MAX(COALESCE(NULLIF(order_phone, ''), NULLIF(guest_phone, ''))) AS phone,
        MIN(created_at) AS created_at,
        COUNT(*) AS order_count,
        COALESCE(SUM(total_amount), 0) AS total_spent,
        MAX(created_at) AS last_order_at
     FROM orders
     WHERE user_id IS NULL
       AND (
            COALESCE(guest_name, '') <> ''
            OR COALESCE(guest_email, '') <> ''
            OR COALESCE(order_phone, '') <> ''
            OR COALESCE(guest_phone, '') <> ''
       )
     GROUP BY guest_group_key
     ORDER BY last_order_at DESC"
);

$ordersForDirectory = $db->fetchAll(
    "SELECT
        id,
        user_id,
        guest_name,
        guest_email,
        COALESCE(NULLIF(order_phone, ''), NULLIF(guest_phone, '')) AS customer_phone,
        tracking_code,
        total_amount,
        status,
        payment_status,
        created_at
     FROM orders
     ORDER BY created_at DESC"
);

$customerOrderMap = [];

foreach ($ordersForDirectory as $orderRow) {
    if (!empty($orderRow['user_id'])) {
        $customerOrderMap['user_' . $orderRow['user_id']][] = $orderRow;
        continue;
    }

    $guestKey = buildGuestCustomerKey($orderRow['guest_email'] ?? '', $orderRow['customer_phone'] ?? '', $orderRow['guest_name'] ?? '');
    $customerOrderMap[$guestKey][] = $orderRow;
}

$customers = [];
$customerDetailsMap = [];
$registeredCount = 0;
$guestCount = 0;

foreach ($registeredCustomers as $customer) {
    $customerKey = $customer['customer_key'];
    $recentOrders = array_slice($customerOrderMap[$customerKey] ?? [], 0, 5);

    $customers[] = [
        'customer_key' => $customerKey,
        'customer_type' => 'registered',
        'user_id' => (int) $customer['user_id'],
        'name' => $customer['name'],
        'email' => $customer['email'],
        'phone' => $customer['phone'],
        'created_at' => $customer['created_at'],
        'is_active' => (int) $customer['is_active'],
        'order_count' => (int) $customer['order_count'],
        'total_spent' => (float) $customer['total_spent'],
        'last_order_at' => $customer['last_order_at'],
    ];

    $customerDetailsMap[$customerKey] = [
        'name' => $customer['name'],
        'type_label' => 'Registered Customer',
        'email' => $customer['email'],
        'phone' => $customer['phone'],
        'joined_at' => formatDate($customer['created_at']),
        'status_label' => !empty($customer['is_active']) ? 'Active account' : 'Inactive account',
        'order_count' => (int) $customer['order_count'],
        'total_spent' => formatCurrency((float) $customer['total_spent']),
        'last_order_at' => !empty($customer['last_order_at']) ? formatDate($customer['last_order_at']) : 'No orders yet',
        'orders' => array_map(static function ($orderRow) {
            return [
                'tracking_code' => $orderRow['tracking_code'],
                'date' => formatDate($orderRow['created_at']),
                'amount' => formatCurrency((float) $orderRow['total_amount']),
                'status' => ucfirst((string) $orderRow['status']),
                'payment_status' => ucfirst((string) $orderRow['payment_status']),
            ];
        }, $recentOrders),
    ];

    $registeredCount++;
}

foreach ($guestCustomers as $guestCustomer) {
    $customerKey = buildGuestCustomerKey($guestCustomer['email'] ?? '', $guestCustomer['phone'] ?? '', $guestCustomer['name'] ?? '');
    $recentOrders = array_slice($customerOrderMap[$customerKey] ?? [], 0, 5);

    $customers[] = [
        'customer_key' => $customerKey,
        'customer_type' => 'guest',
        'user_id' => null,
        'name' => $guestCustomer['name'],
        'email' => $guestCustomer['email'] ?? '',
        'phone' => $guestCustomer['phone'] ?? '',
        'created_at' => $guestCustomer['created_at'],
        'is_active' => 1,
        'order_count' => (int) $guestCustomer['order_count'],
        'total_spent' => (float) $guestCustomer['total_spent'],
        'last_order_at' => $guestCustomer['last_order_at'],
    ];

    $customerDetailsMap[$customerKey] = [
        'name' => $guestCustomer['name'],
        'type_label' => 'Guest Checkout Customer',
        'email' => $guestCustomer['email'] !== '' ? $guestCustomer['email'] : 'No email recorded',
        'phone' => $guestCustomer['phone'] !== '' ? $guestCustomer['phone'] : 'No phone recorded',
        'joined_at' => formatDate($guestCustomer['created_at']),
        'status_label' => 'Guest checkout record',
        'order_count' => (int) $guestCustomer['order_count'],
        'total_spent' => formatCurrency((float) $guestCustomer['total_spent']),
        'last_order_at' => !empty($guestCustomer['last_order_at']) ? formatDate($guestCustomer['last_order_at']) : 'No orders yet',
        'orders' => array_map(static function ($orderRow) {
            return [
                'tracking_code' => $orderRow['tracking_code'],
                'date' => formatDate($orderRow['created_at']),
                'amount' => formatCurrency((float) $orderRow['total_amount']),
                'status' => ucfirst((string) $orderRow['status']),
                'payment_status' => ucfirst((string) $orderRow['payment_status']),
            ];
        }, $recentOrders),
    ];

    $guestCount++;
}

usort($customers, static function ($left, $right) {
    $leftTime = !empty($left['last_order_at']) ? strtotime($left['last_order_at']) : strtotime($left['created_at']);
    $rightTime = !empty($right['last_order_at']) ? strtotime($right['last_order_at']) : strtotime($right['created_at']);
    return $rightTime <=> $leftTime;
});

require_once __DIR__ . '/inc/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="mb-1">Customers Management</h2>
            <p class="text-muted mb-0">Registered customers and guest buyers now live in one directory.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <span class="badge bg-primary" style="font-size: 0.95rem;"><?php echo count($customers); ?> Customer Records</span>
            <span class="badge bg-info text-dark" style="font-size: 0.95rem;"><?php echo $registeredCount; ?> Registered</span>
            <span class="badge bg-warning text-dark" style="font-size: 0.95rem;"><?php echo $guestCount; ?> Guest Buyers</span>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                        <th>Joined</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><strong><?php echo sanitize($customer['name']); ?></strong></td>
                            <td>
                                <span class="badge <?php echo $customer['customer_type'] === 'registered' ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $customer['customer_type'] === 'registered' ? 'Registered' : 'Guest'; ?>
                                </span>
                            </td>
                            <td><?php echo sanitize($customer['email'] !== '' ? $customer['email'] : 'No email recorded'); ?></td>
                            <td><?php echo sanitize($customer['phone'] !== '' ? $customer['phone'] : 'No phone recorded'); ?></td>
                            <td><span class="badge bg-info"><?php echo (int) $customer['order_count']; ?></span></td>
                            <td><?php echo formatCurrency((float) $customer['total_spent']); ?></td>
                            <td><?php echo formatDate($customer['created_at']); ?></td>
                            <td>
                                <?php if ($customer['customer_type'] === 'registered'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="update_customer" value="1">
                                        <input type="hidden" name="customer_id" value="<?php echo (int) $customer['user_id']; ?>">
                                        <select name="is_active" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                            <option value="1" <?php echo !empty($customer['is_active']) ? 'selected' : ''; ?>>Active</option>
                                            <option value="0" <?php echo empty($customer['is_active']) ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border">Guest record</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewCustomerDetails('<?php echo sanitize($customer['customer_key']); ?>')">
                                    View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="customerDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="customerModalTitle">Customer Details</h5>
                    <small class="text-muted" id="customerModalType"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-uppercase text-muted d-block mb-2">Contact</small>
                            <div class="mb-2"><strong>Email:</strong> <span id="customerModalEmail"></span></div>
                            <div><strong>Phone:</strong> <span id="customerModalPhone"></span></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-uppercase text-muted d-block mb-2">Account Snapshot</small>
                            <div class="mb-2"><strong>Joined:</strong> <span id="customerModalJoined"></span></div>
                            <div class="mb-2"><strong>Status:</strong> <span id="customerModalStatus"></span></div>
                            <div><strong>Last Order:</strong> <span id="customerModalLastOrder"></span></div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-uppercase text-muted d-block mb-2">Orders</small>
                            <div class="display-6 fw-semibold" id="customerModalOrderCount">0</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-uppercase text-muted d-block mb-2">Total Spent</small>
                            <div class="display-6 fw-semibold" id="customerModalTotalSpent"></div>
                        </div>
                    </div>
                </div>

                <h6 class="mb-3">Recent Orders</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Tracking</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody id="customerModalOrders">
                            <tr>
                                <td colspan="5" class="text-muted">No recent orders found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const customerDirectoryDetails = <?php echo json_encode($customerDetailsMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let customerDetailsModalInstance = null;

    function getCustomerDetailsModal() {
        if (!customerDetailsModalInstance && window.bootstrap) {
            customerDetailsModalInstance = new bootstrap.Modal(document.getElementById('customerDetailsModal'));
        }

        return customerDetailsModalInstance;
    }

    function viewCustomerDetails(customerKey) {
        const details = customerDirectoryDetails[customerKey];
        if (!details) {
            return;
        }

        document.getElementById('customerModalTitle').textContent = details.name;
        document.getElementById('customerModalType').textContent = details.type_label;
        document.getElementById('customerModalEmail').textContent = details.email;
        document.getElementById('customerModalPhone').textContent = details.phone;
        document.getElementById('customerModalJoined').textContent = details.joined_at;
        document.getElementById('customerModalStatus').textContent = details.status_label;
        document.getElementById('customerModalLastOrder').textContent = details.last_order_at;
        document.getElementById('customerModalOrderCount').textContent = details.order_count;
        document.getElementById('customerModalTotalSpent').textContent = details.total_spent;

        const ordersBody = document.getElementById('customerModalOrders');
        ordersBody.innerHTML = '';

        if (!details.orders || details.orders.length === 0) {
            ordersBody.innerHTML = '<tr><td colspan="5" class="text-muted">No recent orders found.</td></tr>';
        } else {
            details.orders.forEach((order) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${order.tracking_code}</strong></td>
                    <td>${order.date}</td>
                    <td>${order.amount}</td>
                    <td>${order.status}</td>
                    <td>${order.payment_status}</td>
                `;
                ordersBody.appendChild(row);
            });
        }

        const modal = getCustomerDetailsModal();
        if (modal) {
            modal.show();
        }
    }
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
