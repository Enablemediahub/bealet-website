<?php
/**
 * Bealet Website - Admin Orders Management
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(APP_URL . '/admin/login.php');
}

global $db;
ensureOrderPrescriptionsTable();

// Handle status update before any output.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $orderId = (int) $_POST['order_id'];
        $status = sanitize($_POST['status']);

        if (in_array($status, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'], true)) {
            $db->update(
                "UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?",
                [$status, $orderId]
            );

            createLog('ORDER_STATUS_UPDATED', "Order #$orderId status changed to $status");
            setFlashMessage('success', 'Order status updated successfully');
            redirect(APP_URL . '/admin/orders.php?view=' . $orderId);
        }
    }
}

$orders = $db->fetchAll(
    "SELECT
        o.*,
        COUNT(oi.id) AS item_count
     FROM orders o
     LEFT JOIN order_items oi ON o.id = oi.order_id
     GROUP BY o.id
     ORDER BY o.created_at DESC"
);

$orderItems = $db->fetchAll(
    "SELECT
        oi.order_id,
        oi.product_id,
        oi.product_name,
        oi.product_price,
        oi.quantity,
        p.main_image
     FROM order_items oi
     LEFT JOIN products p ON oi.product_id = p.id
     ORDER BY oi.order_id DESC, oi.id ASC"
);

$orderItemsByOrderId = [];
foreach ($orderItems as $item) {
    $orderItemsByOrderId[(int) $item['order_id']][] = [
        'product_name' => $item['product_name'] ?: 'Product',
        'quantity' => (int) $item['quantity'],
        'product_price' => formatCurrency((float) $item['product_price']),
        'line_total' => formatCurrency((float) $item['product_price'] * (int) $item['quantity']),
        'image' => getProductImageUrl($item['main_image'] ?? null),
    ];
}

$orderPrescriptions = $db->fetchAll(
    "SELECT *
     FROM order_prescriptions
     ORDER BY created_at DESC"
);

$orderPrescriptionsByOrderId = [];
foreach ($orderPrescriptions as $prescription) {
    $manualPrescription = json_decode((string) ($prescription['manual_prescription'] ?? ''), true);
    if (!is_array($manualPrescription)) {
        $manualPrescription = [];
    }

    $orderPrescriptionsByOrderId[(int) $prescription['order_id']] = [
        'source' => ucfirst(str_replace('_', ' + ', (string) ($prescription['prescription_source'] ?? 'manual'))),
        'frame_notes' => trim((string) ($prescription['frame_notes'] ?? '')) !== '' ? sanitize($prescription['frame_notes']) : 'No frame notes supplied',
        'customer_notes' => trim((string) ($prescription['customer_notes'] ?? '')) !== '' ? sanitize($prescription['customer_notes']) : 'No extra customer notes',
        'original_filename' => trim((string) ($prescription['original_filename'] ?? '')) !== '' ? sanitize($prescription['original_filename']) : 'No attachment uploaded',
        'file_url' => getPrescriptionFileUrl($prescription['file_path'] ?? ''),
        'manual' => [
            'right_eye' => [
                'sphere' => sanitize((string) ($manualPrescription['right_eye']['sphere'] ?? '')),
                'cylinder' => sanitize((string) ($manualPrescription['right_eye']['cylinder'] ?? '')),
                'axis' => sanitize((string) ($manualPrescription['right_eye']['axis'] ?? '')),
                'add' => sanitize((string) ($manualPrescription['right_eye']['add'] ?? '')),
            ],
            'left_eye' => [
                'sphere' => sanitize((string) ($manualPrescription['left_eye']['sphere'] ?? '')),
                'cylinder' => sanitize((string) ($manualPrescription['left_eye']['cylinder'] ?? '')),
                'axis' => sanitize((string) ($manualPrescription['left_eye']['axis'] ?? '')),
                'add' => sanitize((string) ($manualPrescription['left_eye']['add'] ?? '')),
            ],
            'pd' => [
                'far' => sanitize((string) ($manualPrescription['pd']['far'] ?? '')),
                'near' => sanitize((string) ($manualPrescription['pd']['near'] ?? '')),
            ],
        ],
    ];
}

$orderDetailsMap = [];
foreach ($orders as $order) {
    $orderId = (int) $order['id'];
    $customerName = trim((string) ($order['guest_name'] ?? ''));
    if ($customerName === '') {
        $customerName = trim((string) ($order['shipping_address'] ?? ''));
        $customerName = strtok($customerName, "\n") ?: 'Customer';
    }

    $customerEmail = trim((string) ($order['guest_email'] ?? ''));
    if ($customerEmail === '') {
        $customerEmail = 'No email recorded';
    }

    $customerPhone = trim((string) ($order['order_phone'] ?: $order['guest_phone']));
    if ($customerPhone === '') {
        $customerPhone = 'No phone recorded';
    }

    $orderDetailsMap[$orderId] = [
        'id' => $orderId,
        'tracking_code' => $order['tracking_code'],
        'customer_name' => $customerName,
        'customer_email' => $customerEmail,
        'customer_phone' => $customerPhone,
        'amount' => formatCurrency((float) $order['total_amount']),
        'subtotal' => formatCurrency((float) ($order['subtotal'] ?? 0)),
        'shipping_fee' => formatCurrency((float) ($order['shipping_fee'] ?? 0)),
        'tax' => formatCurrency((float) ($order['tax'] ?? 0)),
        'discount' => formatCurrency((float) ($order['discount'] ?? 0)),
        'status' => ucfirst((string) $order['status']),
        'payment_status' => ucfirst((string) $order['payment_status']),
        'created_at' => formatDate($order['created_at']),
        'shipping_address' => trim((string) $order['shipping_address']) !== '' ? nl2br(sanitize($order['shipping_address'])) : 'No shipping address recorded',
        'admin_notes' => trim((string) ($order['admin_notes'] ?? '')) !== '' ? sanitize($order['admin_notes']) : 'No admin notes yet',
        'items' => $orderItemsByOrderId[$orderId] ?? [],
        'prescription' => $orderPrescriptionsByOrderId[$orderId] ?? null,
    ];
}

$autoOpenOrderId = isset($_GET['view']) ? (int) $_GET['view'] : 0;

require_once __DIR__ . '/inc/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Orders Management</h2>
            <p class="text-muted mb-0">Manage and track all customer orders</p>
        </div>
        <span class="badge bg-primary" style="font-size: 1rem;"><?php echo count($orders); ?> Orders</span>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Tracking Code</th>
                        <th>Amount</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong><?php echo sanitize($order['tracking_code']); ?></strong></td>
                            <td><?php echo formatCurrency((float) $order['total_amount']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo (int) $order['item_count']; ?></span></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                    <select name="status" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                        <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <span class="badge <?php echo $order['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                    <?php echo ucfirst((string) $order['payment_status']); ?>
                                </span>
                            </td>
                            <td><?php echo formatDate($order['created_at']); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewOrderDetails(<?php echo (int) $order['id']; ?>)">
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

<div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <div>
                    <h5 class="modal-title mb-1" id="orderModalTitle">Order Details</h5>
                    <small class="text-white" id="orderModalSubtitle"></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-lg-4">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-uppercase text-muted d-block mb-2">Customer</small>
                            <div class="mb-2"><strong>Name:</strong> <span id="orderModalCustomerName"></span></div>
                            <div class="mb-2"><strong>Email:</strong> <span id="orderModalCustomerEmail"></span></div>
                            <div><strong>Phone:</strong> <span id="orderModalCustomerPhone"></span></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-uppercase text-muted d-block mb-2">Order Snapshot</small>
                            <div class="mb-2"><strong>Status:</strong> <span id="orderModalStatus"></span></div>
                            <div class="mb-2"><strong>Payment:</strong> <span id="orderModalPaymentStatus"></span></div>
                            <div><strong>Date:</strong> <span id="orderModalDate"></span></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-uppercase text-muted d-block mb-2">Totals</small>
                            <div class="mb-2"><strong>Subtotal:</strong> <span id="orderModalSubtotal"></span></div>
                            <div class="mb-2"><strong>Shipping:</strong> <span id="orderModalShipping"></span></div>
                            <div class="mb-2"><strong>Tax:</strong> <span id="orderModalTax"></span></div>
                            <div class="mb-2"><strong>Discount:</strong> <span id="orderModalDiscount"></span></div>
                            <div><strong>Total:</strong> <span id="orderModalAmount"></span></div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-7">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-uppercase text-muted d-block mb-2">Shipping Address</small>
                            <div id="orderModalAddress"></div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-uppercase text-muted d-block mb-2">Admin Notes</small>
                            <div id="orderModalAdminNotes"></div>
                        </div>
                    </div>
                </div>

                <div class="border rounded-3 p-3 mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <small class="text-uppercase text-muted d-block mb-0">Prescription Submission</small>
                        <span class="badge bg-info-subtle text-dark" id="orderModalPrescriptionSource">No prescription</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-5">
                            <div class="mb-2"><strong>Frame Notes:</strong> <span id="orderModalPrescriptionFrameNotes"></span></div>
                            <div class="mb-2"><strong>Customer Notes:</strong> <span id="orderModalPrescriptionCustomerNotes"></span></div>
                            <div><strong>Attachment:</strong> <span id="orderModalPrescriptionAttachment"></span></div>
                        </div>
                        <div class="col-lg-7">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th>Sphere</th>
                                            <th>Cylinder</th>
                                            <th>Axis</th>
                                            <th>Add</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <th>OD</th>
                                            <td id="orderModalPrescriptionOdSphere">-</td>
                                            <td id="orderModalPrescriptionOdCylinder">-</td>
                                            <td id="orderModalPrescriptionOdAxis">-</td>
                                            <td id="orderModalPrescriptionOdAdd">-</td>
                                        </tr>
                                        <tr>
                                            <th>OS</th>
                                            <td id="orderModalPrescriptionOsSphere">-</td>
                                            <td id="orderModalPrescriptionOsCylinder">-</td>
                                            <td id="orderModalPrescriptionOsAxis">-</td>
                                            <td id="orderModalPrescriptionOsAdd">-</td>
                                        </tr>
                                        <tr>
                                            <th>PD</th>
                                            <td id="orderModalPrescriptionPdFar" colspan="2">Far: -</td>
                                            <td id="orderModalPrescriptionPdNear" colspan="2">Near: -</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="mb-3">Order Items</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Unit Price</th>
                                <th>Qty</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="orderModalItems">
                            <tr>
                                <td colspan="4" class="text-muted">No order items found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const orderDetailsMap = <?php echo json_encode($orderDetailsMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let orderDetailsModalInstance = null;

    function getOrderDetailsModal() {
        if (!orderDetailsModalInstance && window.bootstrap) {
            orderDetailsModalInstance = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
        }

        return orderDetailsModalInstance;
    }

    function viewOrderDetails(orderId) {
        const details = orderDetailsMap[orderId];
        if (!details) {
            return;
        }

        document.getElementById('orderModalTitle').textContent = `Order ${details.tracking_code}`;
        document.getElementById('orderModalSubtitle').textContent = `Reference #${details.id}`;
        document.getElementById('orderModalCustomerName').textContent = details.customer_name;
        document.getElementById('orderModalCustomerEmail').textContent = details.customer_email;
        document.getElementById('orderModalCustomerPhone').textContent = details.customer_phone;
        document.getElementById('orderModalStatus').textContent = details.status;
        document.getElementById('orderModalPaymentStatus').textContent = details.payment_status;
        document.getElementById('orderModalDate').textContent = details.created_at;
        document.getElementById('orderModalSubtotal').textContent = details.subtotal;
        document.getElementById('orderModalShipping').textContent = details.shipping_fee;
        document.getElementById('orderModalTax').textContent = details.tax;
        document.getElementById('orderModalDiscount').textContent = details.discount;
        document.getElementById('orderModalAmount').textContent = details.amount;
        document.getElementById('orderModalAddress').innerHTML = details.shipping_address;
        document.getElementById('orderModalAdminNotes').textContent = details.admin_notes;

        const prescription = details.prescription;
        document.getElementById('orderModalPrescriptionSource').textContent = prescription ? prescription.source : 'No prescription';
        document.getElementById('orderModalPrescriptionFrameNotes').textContent = prescription ? prescription.frame_notes : 'No frame notes supplied';
        document.getElementById('orderModalPrescriptionCustomerNotes').textContent = prescription ? prescription.customer_notes : 'No extra customer notes';
        document.getElementById('orderModalPrescriptionOdSphere').textContent = prescription?.manual?.right_eye?.sphere || '-';
        document.getElementById('orderModalPrescriptionOdCylinder').textContent = prescription?.manual?.right_eye?.cylinder || '-';
        document.getElementById('orderModalPrescriptionOdAxis').textContent = prescription?.manual?.right_eye?.axis || '-';
        document.getElementById('orderModalPrescriptionOdAdd').textContent = prescription?.manual?.right_eye?.add || '-';
        document.getElementById('orderModalPrescriptionOsSphere').textContent = prescription?.manual?.left_eye?.sphere || '-';
        document.getElementById('orderModalPrescriptionOsCylinder').textContent = prescription?.manual?.left_eye?.cylinder || '-';
        document.getElementById('orderModalPrescriptionOsAxis').textContent = prescription?.manual?.left_eye?.axis || '-';
        document.getElementById('orderModalPrescriptionOsAdd').textContent = prescription?.manual?.left_eye?.add || '-';
        document.getElementById('orderModalPrescriptionPdFar').textContent = `Far: ${prescription?.manual?.pd?.far || '-'}`;
        document.getElementById('orderModalPrescriptionPdNear').textContent = `Near: ${prescription?.manual?.pd?.near || '-'}`;

        const attachmentTarget = document.getElementById('orderModalPrescriptionAttachment');
        attachmentTarget.innerHTML = '';
        if (prescription?.file_url) {
            const attachmentLink = document.createElement('a');
            attachmentLink.href = prescription.file_url;
            attachmentLink.target = '_blank';
            attachmentLink.rel = 'noopener noreferrer';
            attachmentLink.textContent = prescription.original_filename || 'Open attachment';
            attachmentTarget.appendChild(attachmentLink);
        } else {
            attachmentTarget.textContent = prescription ? prescription.original_filename : 'No attachment uploaded';
        }

        const itemsBody = document.getElementById('orderModalItems');
        itemsBody.innerHTML = '';

        if (!details.items || details.items.length === 0) {
            itemsBody.innerHTML = '<tr><td colspan="4" class="text-muted">No order items found.</td></tr>';
        } else {
            details.items.forEach((item) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            ${item.image ? `<img src="${item.image}" alt="${item.product_name}" style="width:48px;height:48px;object-fit:cover;border-radius:12px;">` : ''}
                            <strong>${item.product_name}</strong>
                        </div>
                    </td>
                    <td>${item.product_price}</td>
                    <td>${item.quantity}</td>
                    <td>${item.line_total}</td>
                `;
                itemsBody.appendChild(row);
            });
        }

        const modal = getOrderDetailsModal();
        if (modal) {
            modal.show();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const autoOpenOrderId = <?php echo $autoOpenOrderId; ?>;
        if (autoOpenOrderId > 0) {
            viewOrderDetails(autoOpenOrderId);
        }
    });
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
