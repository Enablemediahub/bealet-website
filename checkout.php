<?php
/**
 * Bealet Website - Checkout Page
 */

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

global $db;

$errors = [];
ensureOrderPrescriptionsTable();

/**
 * VAT amount included in a tax-inclusive gross amount.
 */
function getIncludedVatAmount($grossAmount) {
    return $grossAmount * (VAT_RATE / (1 + VAT_RATE));
}

// Handle cart adjustments from checkout summary controls.
$cartAction = sanitize($_GET['cart_action'] ?? '');
$cartId = (int) ($_GET['cart_id'] ?? 0);
if ($cartAction !== '' && $cartId > 0) {
    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = session_id();

    runCartQuerySafely(function () use ($db, $cartAction, $cartId, $userId, $sessionId) {
        $cartItem = $db->fetch(
            "SELECT id, quantity FROM cart WHERE id = ? AND (user_id = ? OR session_id = ?)",
            [$cartId, $userId, $sessionId]
        );

        if (!$cartItem) {
            return null;
        }

        if ($cartAction === 'increase') {
            $db->update("UPDATE cart SET quantity = quantity + 1 WHERE id = ?", [$cartId]);
        } elseif ($cartAction === 'decrease') {
            if ((int) $cartItem['quantity'] > 1) {
                $db->update("UPDATE cart SET quantity = quantity - 1 WHERE id = ?", [$cartId]);
            } else {
                $db->delete("DELETE FROM cart WHERE id = ?", [$cartId]);
            }
        } elseif ($cartAction === 'remove') {
            $db->delete("DELETE FROM cart WHERE id = ?", [$cartId]);
        }

        return null;
    }, null);

    redirect(APP_URL . '/checkout.php');
}

$cart = getCartItems();
$cartTotal = getCartTotal();
$vatIncludedTotal = getIncludedVatAmount($cartTotal);
$currentUserData = isLoggedIn() ? getCurrentUser() : null;
$defaultCheckoutPhone = $currentUserData['phone'] ?? '';
if ($defaultCheckoutPhone === '') {
    $defaultCheckoutPhone = '+233';
} elseif (strpos($defaultCheckoutPhone, '+234') === 0) {
    $defaultCheckoutPhone = '+233' . substr($defaultCheckoutPhone, 4);
}

// Redirect if cart is empty
if (empty($cart)) {
    setFlashMessage('warning', 'Your cart is empty');
    redirect(APP_URL . '/shop.php');
}

$checkoutFormData = [
    'first_name' => '',
    'last_name' => '',
    'email' => $currentUserData['email'] ?? '',
    'phone' => $defaultCheckoutPhone,
    'address' => '',
    'city' => '',
    'region' => '',
    'zip_code' => 'GHA-233',
    'payment_method' => 'paystack',
];

$prescriptionFormData = [
    'mode' => 'manual',
    'enabled' => false,
    'frame_notes' => '',
    'customer_notes' => '',
    'od_sphere' => '',
    'od_cylinder' => '',
    'od_axis' => '',
    'od_add' => '',
    'os_sphere' => '',
    'os_cylinder' => '',
    'os_axis' => '',
    'os_add' => '',
    'pd_far' => '',
    'pd_near' => '',
];

$selectedFrameNames = [];
foreach ($cart as $cartItem) {
    $selectedFrameNames[] = $cartItem['name'];
}
$selectedFrameNames = array_values(array_unique(array_filter($selectedFrameNames)));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        // Get checkout data
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $normalizedPhone = normalizePhoneNumber($phone);
        $address = sanitize($_POST['address'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $region = sanitize($_POST['region'] ?? '');
        $zipCode = sanitize($_POST['zip_code'] ?? '');
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'paystack');

        $checkoutFormData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'city' => $city,
            'region' => $region,
            'zip_code' => $zipCode,
            'payment_method' => $paymentMethod !== '' ? $paymentMethod : 'paystack',
        ];

        $prescriptionEnabled = ($_POST['prescription_enabled'] ?? '') === '1';
        $prescriptionMode = sanitize($_POST['prescription_mode'] ?? 'manual');
        if (!in_array($prescriptionMode, ['manual', 'upload', 'camera'], true)) {
            $prescriptionMode = 'manual';
        }

        $prescriptionFormData = [
            'mode' => $prescriptionMode,
            'enabled' => $prescriptionEnabled,
            'frame_notes' => sanitize($_POST['prescription_frame_notes'] ?? ''),
            'customer_notes' => sanitize($_POST['prescription_customer_notes'] ?? ''),
            'od_sphere' => sanitize($_POST['od_sphere'] ?? ''),
            'od_cylinder' => sanitize($_POST['od_cylinder'] ?? ''),
            'od_axis' => sanitize($_POST['od_axis'] ?? ''),
            'od_add' => sanitize($_POST['od_add'] ?? ''),
            'os_sphere' => sanitize($_POST['os_sphere'] ?? ''),
            'os_cylinder' => sanitize($_POST['os_cylinder'] ?? ''),
            'os_axis' => sanitize($_POST['os_axis'] ?? ''),
            'os_add' => sanitize($_POST['os_add'] ?? ''),
            'pd_far' => sanitize($_POST['pd_far'] ?? ''),
            'pd_near' => sanitize($_POST['pd_near'] ?? ''),
        ];

        $manualPrescription = [
            'right_eye' => [
                'sphere' => $prescriptionFormData['od_sphere'],
                'cylinder' => $prescriptionFormData['od_cylinder'],
                'axis' => $prescriptionFormData['od_axis'],
                'add' => $prescriptionFormData['od_add'],
            ],
            'left_eye' => [
                'sphere' => $prescriptionFormData['os_sphere'],
                'cylinder' => $prescriptionFormData['os_cylinder'],
                'axis' => $prescriptionFormData['os_axis'],
                'add' => $prescriptionFormData['os_add'],
            ],
            'pd' => [
                'far' => $prescriptionFormData['pd_far'],
                'near' => $prescriptionFormData['pd_near'],
            ],
        ];
        $manualPrescriptionProvided = false;
        foreach ($manualPrescription as $section) {
            foreach ($section as $value) {
                if (trim((string) $value) !== '') {
                    $manualPrescriptionProvided = true;
                    break 2;
                }
            }
        }

        $prescriptionAllowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $prescriptionUpload = null;
        $prescriptionOriginalName = null;
        $fileSource = null;
        $prescriptionCameraCapture = trim((string) ($_POST['prescription_camera_capture'] ?? ''));

        if (!empty($_FILES['prescription_file']['name'])) {
            $prescriptionUpload = uploadFile($_FILES['prescription_file'], 'prescriptions', $prescriptionAllowedExtensions);
            $prescriptionOriginalName = $_FILES['prescription_file']['name'];
            $fileSource = 'upload';
        } elseif ($prescriptionCameraCapture !== '') {
            $prescriptionUpload = uploadDataUriImage($prescriptionCameraCapture, 'prescriptions');
            $prescriptionOriginalName = 'live-camera-capture.jpg';
            $fileSource = 'camera';
        } elseif (!empty($_FILES['prescription_camera_image']['name'])) {
            $prescriptionUpload = uploadFile($_FILES['prescription_camera_image'], 'prescriptions', $prescriptionAllowedExtensions);
            $prescriptionOriginalName = $_FILES['prescription_camera_image']['name'];
            $fileSource = 'camera';
        }

        if ($prescriptionUpload && empty($prescriptionUpload['success'])) {
            foreach (($prescriptionUpload['errors'] ?? ['Prescription upload failed.']) as $uploadError) {
                $errors[] = $uploadError;
            }
        }
        
        // Validate inputs
        if (empty($firstName) || empty($lastName)) {
            $errors[] = 'Please enter your full name';
        }
        
        if (empty($email) || !validateEmail($email)) {
            $errors[] = 'Please enter a valid email address';
        }
        
        if (empty($phone) || !validatePhone($phone)) {
            $errors[] = 'Please enter a valid Ghana phone number';
        }
        
        if (empty($city)) {
            $errors[] = 'Please enter your city';
        }
        
        if (empty($region)) {
            $errors[] = 'Please select your region';
        }

        if ($prescriptionEnabled && !$manualPrescriptionProvided && !$prescriptionUpload) {
            $errors[] = 'Add your prescription details in the form, upload a prescription file, or take a photo before proceeding.';
        }
        
        // If no errors, create order
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Create tracking code from customer initials.
                $trackingCode = generateTrackingCode(trim($firstName . ' ' . $lastName));
                
                // Build shipping address
                $shippingAddress = "$firstName $lastName\n";
                if (!empty($address)) {
                    $shippingAddress .= "$address\n";
                }
                $shippingAddress .= "$city, $region";
                if (!empty($zipCode)) {
                    $shippingAddress .= " $zipCode";
                }
                
                // Create order
                $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
                $guestEmail = !isLoggedIn() ? $email : null;
                $guestName = trim($firstName . ' ' . $lastName);
                $shippingFee = 0;
                $discount = 0;
                $taxAmount = $vatIncludedTotal;
                
                $orderId = $db->insert(
                    "INSERT INTO orders (
                        user_id,
                        guest_name,
                        guest_email,
                        order_phone,
                        guest_phone,
                        tracking_code,
                        subtotal,
                        shipping_fee,
                        tax,
                        discount,
                        total_amount,
                        status,
                        payment_status,
                        shipping_address,
                        shipping_city,
                        shipping_state,
                        shipping_zip,
                        shipping_country
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, ?, ?, ?)",
                    [
                        $userId,
                        $guestName,
                        $guestEmail,
                        $normalizedPhone,
                        $normalizedPhone,
                        $trackingCode,
                        $cartTotal,
                        $shippingFee,
                        $taxAmount,
                        $discount,
                        $cartTotal,
                        $shippingAddress,
                        $city,
                        $region,
                        $zipCode !== '' ? $zipCode : null,
                        'Ghana'
                    ]
                );
                
                // Add order items
                foreach ($cart as $item) {
                    $db->insert(
                        "INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity)
                         VALUES (?, ?, ?, ?, ?)",
                        [
                            $orderId,
                            $item['product_id'],
                            $item['name'],
                            $item['price'],
                            $item['quantity']
                        ]
                    );
                }

                if ($prescriptionEnabled && ($manualPrescriptionProvided || $prescriptionUpload)) {
                    $prescriptionSource = 'manual';
                    if ($manualPrescriptionProvided && $prescriptionUpload) {
                        $prescriptionSource = 'manual_upload';
                    } elseif ($prescriptionUpload && $fileSource === 'camera') {
                        $prescriptionSource = 'camera';
                    } elseif ($prescriptionUpload) {
                        $prescriptionSource = 'upload';
                    }

                    $db->insert(
                        "INSERT INTO order_prescriptions (
                            order_id,
                            prescription_source,
                            frame_notes,
                            manual_prescription,
                            file_path,
                            original_filename,
                            customer_notes
                         ) VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [
                            $orderId,
                            $prescriptionSource,
                            $prescriptionFormData['frame_notes'] !== '' ? $prescriptionFormData['frame_notes'] : implode(', ', $selectedFrameNames),
                            $manualPrescriptionProvided ? json_encode($manualPrescription, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                            !empty($prescriptionUpload['success']) ? 'assets/uploads/prescriptions/' . $prescriptionUpload['filename'] : null,
                            !empty($prescriptionUpload['success']) ? $prescriptionOriginalName : null,
                            $prescriptionFormData['customer_notes'] !== '' ? $prescriptionFormData['customer_notes'] : null,
                        ]
                    );
                }
                
                $db->commit();
                
                // Get order details for email
                $order = $db->fetch("SELECT * FROM orders WHERE id = ?", [$orderId]);
                $order['email'] = $email;
                
                // Send order confirmation email
                sendOrderConfirmationEmail($order, $email);
                
                // Clear cart
                if (isLoggedIn()) {
                    $db->delete("DELETE FROM cart WHERE user_id = ?", [$userId]);
                } else {
                    $db->delete("DELETE FROM cart WHERE session_id = ?", [session_id()]);
                }
                
                createLog('ORDER_CREATED', 'Order ID: ' . $orderId . ', Tracking: ' . $trackingCode, $userId);
                
                // Store order ID in session for redirect
                $_SESSION['order_id'] = $orderId;
                $_SESSION['tracking_code'] = $trackingCode;
                
                // Initialize Paystack payment
                redirect(APP_URL . '/process-payment.php?order_id=' . $orderId);
                
            } catch (Exception $e) {
                $db->rollBack();
                createLog('CHECKOUT_ERROR', 'Error during checkout: ' . $e->getMessage());
                $errors[] = 'An error occurred while processing your order. Please try again.';
            }
        }
    }
}

// Ghana regions
$regions = [
    'Ahafo',
    'Ashanti',
    'Bono',
    'Bono East',
    'Central',
    'Eastern',
    'Greater Accra',
    'North East',
    'Northern',
    'Oti',
    'Savannah',
    'Upper East',
    'Upper West',
    'Volta',
    'Western',
    'Western North'
];

require_once __DIR__ . '/inc/header.php';

?>

<style>
    .prescription-launch-card {
        border: 1px dashed rgba(14, 116, 144, 0.35);
        background: linear-gradient(135deg, rgba(240, 249, 255, 0.95), rgba(236, 253, 245, 0.9));
    }

    .prescription-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.45rem 0.75rem;
        border-radius: 999px;
        background: rgba(14, 165, 233, 0.12);
        color: #0f766e;
        font-size: 0.92rem;
        font-weight: 600;
    }

    .prescription-item-pill {
        display: inline-flex;
        align-items: center;
        padding: 0.45rem 0.8rem;
        border-radius: 999px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 0.92rem;
        margin: 0 0.45rem 0.45rem 0;
    }

    .prescription-mode-panel {
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 1rem;
        background: #f8fafc;
    }

    .prescription-capture-box {
        border: 1px dashed #94a3b8;
        border-radius: 16px;
        padding: 1rem;
        background: #fff;
    }

    .prescription-camera-shell {
        position: relative;
        overflow: hidden;
        border-radius: 18px;
        background: #0f172a;
        min-height: 280px;
    }

    .prescription-camera-video,
    .prescription-camera-preview {
        width: 100%;
        min-height: 280px;
        max-height: 420px;
        object-fit: cover;
        display: block;
        background: #0f172a;
    }

    .prescription-camera-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgba(255, 255, 255, 0.92);
        text-align: center;
        padding: 1.5rem;
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.25), rgba(15, 23, 42, 0.65));
        pointer-events: none;
    }

    .prescription-camera-overlay.is-hidden {
        display: none;
    }

    .prescription-camera-preview-frame {
        border: 2px solid rgba(255, 255, 255, 0.7);
        border-radius: 16px;
        width: min(88%, 340px);
        aspect-ratio: 3 / 4;
        box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.18);
    }
</style>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mt-4 mb-4">
        <div class="container-lg">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/">Home</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/shop.php">Shop</a></li>
                <li class="breadcrumb-item active">Checkout</li>
            </ol>
        </div>
    </nav>
    
    <!-- Checkout Section -->
    <section class="section-spacing">
        <div class="container-lg">
            <h1 class="mb-4">Checkout</h1>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo sanitize($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="row g-4">
                <!-- Order Summary (Right) -->
                <div class="col-lg-4 order-lg-last">
                    <div class="card sticky-top" style="top: 20px;">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-shopping-bag me-2"></i> Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <!-- Cart Items -->
                            <div class="mb-4">
                                <?php foreach ($cart as $item): ?>
                                <?php
                                    $itemTotal = $item['price'] * $item['quantity'];
                                    $itemVatIncluded = getIncludedVatAmount($itemTotal);
                                ?>
                                <div class="d-flex justify-content-between align-items-start mb-3 pb-3 border-bottom">
                                    <div>
                                        <strong><?php echo sanitize($item['name']); ?></strong>
                                        <div class="text-muted small mb-2">Qty: <?php echo (int) $item['quantity']; ?> · VAT incl.: <?php echo formatCurrency($itemVatIncluded); ?></div>
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Quantity controls">
                                            <a class="btn btn-outline-secondary" href="<?php echo APP_URL; ?>/checkout.php?cart_action=decrease&cart_id=<?php echo (int) $item['id']; ?>">-</a>
                                            <a class="btn btn-outline-secondary disabled" href="#" tabindex="-1"><?php echo (int) $item['quantity']; ?></a>
                                            <a class="btn btn-outline-secondary" href="<?php echo APP_URL; ?>/checkout.php?cart_action=increase&cart_id=<?php echo (int) $item['id']; ?>">+</a>
                                        </div>
                                        <a class="btn btn-link btn-sm text-danger ps-0 ms-2" href="<?php echo APP_URL; ?>/checkout.php?cart_action=remove&cart_id=<?php echo (int) $item['id']; ?>">
                                            <i class="fas fa-trash-alt me-1"></i> Remove
                                        </a>
                                    </div>
                                    <div class="text-right">
                                        <div><?php echo formatCurrency($itemTotal); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Totals -->
                            <div class="mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal (Tax Inclusive):</span>
                                    <strong><?php echo formatCurrency($cartTotal); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Shipping:</span>
                                    <strong><?php echo formatCurrency(0); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>VAT Included (20%):</span>
                                    <strong><?php echo formatCurrency($vatIncludedTotal); ?></strong>
                                </div>
                            </div>
                            
                            <!-- Total -->
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">Payable Total:</h5>
                                <h4 class="mb-0 text-primary"><?php echo formatCurrency($cartTotal); ?></h4>
                            </div>
                            
                            <!-- Continue Shopping -->
                            <a href="<?php echo APP_URL; ?>/shop.php" class="btn btn-outline-primary btn-sm w-100">
                                <i class="fas fa-arrow-left me-2"></i> Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Checkout Form (Left) -->
                <div class="col-lg-8">
                    <form method="POST" id="checkoutForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="prescription_enabled" id="prescription_enabled" value="<?php echo $prescriptionFormData['enabled'] ? '1' : '0'; ?>">
                        <input type="hidden" name="prescription_mode" id="prescription_mode" value="<?php echo sanitize($prescriptionFormData['mode']); ?>">
                        
                        <!-- Shipping Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-truck me-2"></i> Shipping Address</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo sanitize($checkoutFormData['first_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo sanitize($checkoutFormData['last_name']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo sanitize($checkoutFormData['email']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo sanitize($checkoutFormData['phone']); ?>" placeholder="+233 24 000 0000" inputmode="tel" required>
                                    <small class="text-muted">Use a Ghana number like +233 24 000 0000 or 024 000 0000.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">Street Address <span class="text-muted">(Optional)</span></label>
                                    <input type="text" class="form-control" id="address" name="address" value="<?php echo sanitize($checkoutFormData['address']); ?>" placeholder="123 Main St">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="city" name="city" value="<?php echo sanitize($checkoutFormData['city']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="region" class="form-label">Region <span class="text-danger">*</span></label>
                                        <select class="form-select" id="region" name="region" required>
                                            <option value="">Select region...</option>
                                            <?php foreach ($regions as $region): ?>
                                            <option value="<?php echo $region; ?>" <?php echo $checkoutFormData['region'] === $region ? 'selected' : ''; ?>><?php echo $region; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="zip_code" class="form-label">Zip Code <span class="text-muted">(Optional)</span></label>
                                    <input type="text" class="form-control" id="zip_code" name="zip_code" value="<?php echo sanitize($checkoutFormData['zip_code']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4 prescription-launch-card">
                            <div class="card-body p-4">
                                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                                    <div>
                                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                                            <h5 class="mb-0"><i class="fas fa-glasses me-2"></i>Prescription & Frame Notes</h5>
                                            <span class="prescription-status-badge" id="prescriptionStatusBadge">
                                                <?php echo $prescriptionFormData['enabled'] ? 'Prescription added' : 'Optional step'; ?>
                                            </span>
                                        </div>
                                        <p class="text-muted mb-2">Open the popup to fill in your prescription, upload a prescription file, or take a photo directly from your phone or tablet camera.</p>
                                        <?php if (!empty($selectedFrameNames)): ?>
                                        <div>
                                            <?php foreach ($selectedFrameNames as $frameName): ?>
                                            <span class="prescription-item-pill"><?php echo sanitize($frameName); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-lg"
                                        data-bs-toggle="modal"
                                        data-bs-target="#prescriptionModal"
                                    >
                                        <i class="fas fa-file-medical me-2"></i>
                                        <?php echo $prescriptionFormData['enabled'] ? 'Edit Prescription' : 'Add Prescription Form'; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Method -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i> Payment Method</h5>
                            </div>
                            <div class="card-body">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="payment_method" id="paystack" value="paystack" <?php echo $checkoutFormData['payment_method'] === 'paystack' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="paystack">
                                        <strong>Paystack</strong>
                                        <small class="d-block text-muted">Secure payment with debit/credit card</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Agreement -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" class="text-primary">terms and conditions</a> and <a href="#" class="text-primary">privacy policy</a>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Proceed to Payment -->
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-lock me-2"></i> Proceed to Payment
                        </button>

                        <div class="modal fade" id="prescriptionModal" tabindex="-1" aria-labelledby="prescriptionModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <div>
                                            <h5 class="modal-title" id="prescriptionModalLabel">Prescription Form</h5>
                                            <small class="text-muted">Fill it in manually, upload a file, or take a live photo with your device camera.</small>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-check form-switch mb-4">
                                            <input class="form-check-input" type="checkbox" role="switch" id="prescriptionEnabledSwitch" <?php echo $prescriptionFormData['enabled'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-semibold" for="prescriptionEnabledSwitch">Submit prescription with this order</label>
                                        </div>

                                        <div class="row g-4">
                                            <div class="col-lg-7">
                                                <div class="prescription-mode-panel h-100">
                                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                                        <button type="button" class="btn btn-outline-primary prescription-mode-button" data-mode="manual">Fill Form</button>
                                                        <button type="button" class="btn btn-outline-primary prescription-mode-button" data-mode="upload">Upload File</button>
                                                        <button type="button" class="btn btn-outline-primary prescription-mode-button" data-mode="camera">Use Camera</button>
                                                    </div>

                                                    <div class="prescription-panel" data-panel="manual">
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <h6 class="mb-3">Right Eye (OD)</h6>
                                                                <div class="row g-2">
                                                                    <div class="col-6">
                                                                        <label class="form-label" for="od_sphere">Sphere</label>
                                                                        <input type="text" class="form-control" id="od_sphere" name="od_sphere" value="<?php echo sanitize($prescriptionFormData['od_sphere']); ?>" placeholder="-1.25">
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <label class="form-label" for="od_cylinder">Cylinder</label>
                                                                        <input type="text" class="form-control" id="od_cylinder" name="od_cylinder" value="<?php echo sanitize($prescriptionFormData['od_cylinder']); ?>" placeholder="-0.50">
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <label class="form-label" for="od_axis">Axis</label>
                                                                        <input type="text" class="form-control" id="od_axis" name="od_axis" value="<?php echo sanitize($prescriptionFormData['od_axis']); ?>" placeholder="180">
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <label class="form-label" for="od_add">Add</label>
                                                                        <input type="text" class="form-control" id="od_add" name="od_add" value="<?php echo sanitize($prescriptionFormData['od_add']); ?>" placeholder="+1.00">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6 class="mb-3">Left Eye (OS)</h6>
                                                                <div class="row g-2">
                                                                    <div class="col-6">
                                                                        <label class="form-label" for="os_sphere">Sphere</label>
                                                                        <input type="text" class="form-control" id="os_sphere" name="os_sphere" value="<?php echo sanitize($prescriptionFormData['os_sphere']); ?>" placeholder="-1.25">
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <label class="form-label" for="os_cylinder">Cylinder</label>
                                                                        <input type="text" class="form-control" id="os_cylinder" name="os_cylinder" value="<?php echo sanitize($prescriptionFormData['os_cylinder']); ?>" placeholder="-0.50">
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <label class="form-label" for="os_axis">Axis</label>
                                                                        <input type="text" class="form-control" id="os_axis" name="os_axis" value="<?php echo sanitize($prescriptionFormData['os_axis']); ?>" placeholder="180">
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <label class="form-label" for="os_add">Add</label>
                                                                        <input type="text" class="form-control" id="os_add" name="os_add" value="<?php echo sanitize($prescriptionFormData['os_add']); ?>" placeholder="+1.00">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="pd_far">PD Far</label>
                                                                <input type="text" class="form-control" id="pd_far" name="pd_far" value="<?php echo sanitize($prescriptionFormData['pd_far']); ?>" placeholder="64">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="pd_near">PD Near</label>
                                                                <input type="text" class="form-control" id="pd_near" name="pd_near" value="<?php echo sanitize($prescriptionFormData['pd_near']); ?>" placeholder="61">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="prescription-panel d-none" data-panel="upload">
                                                        <div class="prescription-capture-box">
                                                            <label for="prescription_file" class="form-label fw-semibold">Upload prescription file</label>
                                                            <input type="file" class="form-control" id="prescription_file" name="prescription_file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf">
                                                            <small class="text-muted d-block mt-2">Accepted formats: JPG, PNG, WEBP, or PDF. Max size 5MB.</small>
                                                        </div>
                                                    </div>

                                                    <div class="prescription-panel d-none" data-panel="camera">
                                                        <div class="prescription-capture-box">
                                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                                                <label class="form-label fw-semibold mb-0">Live camera capture</label>
                                                                <button type="button" class="btn btn-outline-secondary btn-sm" id="prescriptionCameraFlipButton">
                                                                    <i class="fas fa-camera-rotate me-1"></i>Switch Camera
                                                                </button>
                                                            </div>
                                                            <div class="prescription-camera-shell mb-3">
                                                                <video id="prescriptionCameraVideo" class="prescription-camera-video" autoplay playsinline muted></video>
                                                                <img id="prescriptionCameraPreview" class="prescription-camera-preview d-none" alt="Captured prescription preview">
                                                                <div id="prescriptionCameraOverlay" class="prescription-camera-overlay">
                                                                    <div>
                                                                        <div class="prescription-camera-preview-frame mb-3"></div>
                                                                        <div>Position the hardcopy prescription inside the frame, then capture.</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <canvas id="prescriptionCameraCanvas" class="d-none"></canvas>
                                                            <input type="hidden" id="prescription_camera_capture" name="prescription_camera_capture">
                                                            <input type="file" class="form-control d-none" id="prescription_camera_image" name="prescription_camera_image" accept="image/*" capture="environment">
                                                            <div class="d-flex flex-wrap gap-2">
                                                                <button type="button" class="btn btn-primary" id="prescriptionCameraStartButton">
                                                                    <i class="fas fa-play me-2"></i>Start Camera
                                                                </button>
                                                                <button type="button" class="btn btn-success" id="prescriptionCameraCaptureButton">
                                                                    <i class="fas fa-camera me-2"></i>Capture
                                                                </button>
                                                                <button type="button" class="btn btn-outline-primary d-none" id="prescriptionCameraRetakeButton">
                                                                    <i class="fas fa-rotate-left me-2"></i>Retake
                                                                </button>
                                                            </div>
                                                            <small class="text-muted d-block mt-3" id="prescriptionCameraStatus">Use the live preview to review the hardcopy before saving it to the order.</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-lg-5">
                                                <div class="prescription-mode-panel h-100">
                                                    <h6 class="mb-3">Frame & Order Notes</h6>
                                                    <div class="mb-3">
                                                        <label class="form-label" for="prescription_frame_notes">Chosen frame notes</label>
                                                        <input type="text" class="form-control" id="prescription_frame_notes" name="prescription_frame_notes" value="<?php echo sanitize($prescriptionFormData['frame_notes']); ?>" placeholder="<?php echo !empty($selectedFrameNames) ? sanitize(implode(', ', $selectedFrameNames)) : 'Mention the frame or lens preferences'; ?>">
                                                        <small class="text-muted">Reference the frame you are buying, preferred lens type, or fitting instructions.</small>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label" for="prescription_customer_notes">Extra notes</label>
                                                        <textarea class="form-control" id="prescription_customer_notes" name="prescription_customer_notes" rows="6" placeholder="Any special lens request, coating preference, or note for the optical team"><?php echo sanitize($prescriptionFormData['customer_notes']); ?></textarea>
                                                    </div>
                                                    <?php if (!empty($selectedFrameNames)): ?>
                                                    <div>
                                                        <small class="text-uppercase text-muted d-block mb-2">Selected items in cart</small>
                                                        <?php foreach ($selectedFrameNames as $frameName): ?>
                                                        <span class="prescription-item-pill"><?php echo sanitize($frameName); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="button" class="btn btn-primary" id="savePrescriptionButton" data-bs-dismiss="modal">
                                            <i class="fas fa-check me-2"></i>Save Prescription
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Guest Checkout Info -->
                    <?php if (!isLoggedIn()): ?>
                    <div class="alert alert-info mt-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Guest Checkout</strong>
                        <p class="mb-0">You can proceed without creating an account. A confirmation email will be sent to the email address provided.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const prescriptionModal = document.getElementById('prescriptionModal');
        const modeInput = document.getElementById('prescription_mode');
        const enabledInput = document.getElementById('prescription_enabled');
        const enabledSwitch = document.getElementById('prescriptionEnabledSwitch');
        const statusBadge = document.getElementById('prescriptionStatusBadge');
        const modeButtons = document.querySelectorAll('.prescription-mode-button');
        const panels = document.querySelectorAll('.prescription-panel');
        const cameraVideo = document.getElementById('prescriptionCameraVideo');
        const cameraPreview = document.getElementById('prescriptionCameraPreview');
        const cameraCanvas = document.getElementById('prescriptionCameraCanvas');
        const cameraOverlay = document.getElementById('prescriptionCameraOverlay');
        const cameraStartButton = document.getElementById('prescriptionCameraStartButton');
        const cameraCaptureButton = document.getElementById('prescriptionCameraCaptureButton');
        const cameraRetakeButton = document.getElementById('prescriptionCameraRetakeButton');
        const cameraFlipButton = document.getElementById('prescriptionCameraFlipButton');
        const cameraStatus = document.getElementById('prescriptionCameraStatus');
        const cameraCaptureInput = document.getElementById('prescription_camera_capture');

        let prescriptionCameraStream = null;
        let preferredCameraMode = 'environment';

        function applyPrescriptionMode(mode) {
            modeInput.value = mode;

            modeButtons.forEach((button) => {
                const active = button.dataset.mode === mode;
                button.classList.toggle('btn-primary', active);
                button.classList.toggle('btn-outline-primary', !active);
            });

            panels.forEach((panel) => {
                panel.classList.toggle('d-none', panel.dataset.panel !== mode);
            });

            if (mode === 'camera' && prescriptionModal && prescriptionModal.classList.contains('show')) {
                ensureCameraStarted();
            } else {
                stopPrescriptionCamera();
            }
        }

        function syncPrescriptionEnabledState() {
            const enabled = enabledSwitch.checked;
            enabledInput.value = enabled ? '1' : '0';
            statusBadge.textContent = enabled ? 'Prescription added' : 'Optional step';
        }

        function resetCameraPreviewState() {
            cameraPreview.classList.add('d-none');
            cameraVideo.classList.remove('d-none');
            cameraRetakeButton.classList.add('d-none');
            cameraCaptureButton.classList.remove('d-none');
            cameraOverlay.classList.remove('is-hidden');
        }

        function stopPrescriptionCamera() {
            if (prescriptionCameraStream) {
                prescriptionCameraStream.getTracks().forEach((track) => track.stop());
                prescriptionCameraStream = null;
            }
            cameraVideo.srcObject = null;
        }

        async function ensureCameraStarted() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                cameraStatus.textContent = 'Live camera preview is not supported on this device. You can still upload a photo manually.';
                return;
            }

            if (prescriptionCameraStream) {
                return;
            }

            cameraStatus.textContent = 'Starting camera...';

            const tryConstraints = async (constraints) => navigator.mediaDevices.getUserMedia({ video: constraints, audio: false });

            try {
                resetCameraPreviewState();

                try {
                    prescriptionCameraStream = await tryConstraints({
                        facingMode: { ideal: preferredCameraMode },
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    });
                } catch (primaryError) {
                    prescriptionCameraStream = await tryConstraints({
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    });
                }

                cameraVideo.srcObject = prescriptionCameraStream;
                await cameraVideo.play();
                cameraStatus.textContent = 'Camera ready. Review the page and tap Capture when the prescription is clear.';
            } catch (error) {
                console.error('Prescription camera error:', error);
                stopPrescriptionCamera();
                cameraStatus.textContent = 'Unable to access the live camera preview. Please allow camera access or use the upload option.';
            }
        }

        function capturePrescriptionFrame() {
            if (!cameraVideo.videoWidth || !cameraVideo.videoHeight) {
                cameraStatus.textContent = 'Camera is not ready yet. Please wait a moment and try again.';
                return;
            }

            cameraCanvas.width = cameraVideo.videoWidth;
            cameraCanvas.height = cameraVideo.videoHeight;

            const ctx = cameraCanvas.getContext('2d');
            ctx.drawImage(cameraVideo, 0, 0, cameraCanvas.width, cameraCanvas.height);

            const dataUrl = cameraCanvas.toDataURL('image/jpeg', 0.92);
            cameraCaptureInput.value = dataUrl;
            cameraPreview.src = dataUrl;
            cameraPreview.classList.remove('d-none');
            cameraVideo.classList.add('d-none');
            cameraRetakeButton.classList.remove('d-none');
            cameraCaptureButton.classList.add('d-none');
            cameraOverlay.classList.add('is-hidden');
            enabledSwitch.checked = true;
            syncPrescriptionEnabledState();
            cameraStatus.textContent = 'Captured successfully. Review the image, retake if needed, then save the prescription.';
        }

        async function flipPrescriptionCamera() {
            preferredCameraMode = preferredCameraMode === 'environment' ? 'user' : 'environment';
            stopPrescriptionCamera();
            await ensureCameraStarted();
        }

        modeButtons.forEach((button) => {
            button.addEventListener('click', function () {
                applyPrescriptionMode(button.dataset.mode || 'manual');
            });
        });

        enabledSwitch.addEventListener('change', syncPrescriptionEnabledState);
        document.getElementById('savePrescriptionButton').addEventListener('click', function () {
            enabledSwitch.checked = true;
            syncPrescriptionEnabledState();
        });
        cameraStartButton.addEventListener('click', ensureCameraStarted);
        cameraCaptureButton.addEventListener('click', capturePrescriptionFrame);
        cameraRetakeButton.addEventListener('click', function () {
            cameraCaptureInput.value = '';
            cameraPreview.src = '';
            resetCameraPreviewState();
            ensureCameraStarted();
            cameraStatus.textContent = 'Camera ready. Capture again when the prescription is fully visible.';
        });
        cameraFlipButton.addEventListener('click', flipPrescriptionCamera);

        if (prescriptionModal) {
            prescriptionModal.addEventListener('shown.bs.modal', function () {
                if ((modeInput.value || 'manual') === 'camera' && !cameraCaptureInput.value) {
                    ensureCameraStarted();
                }
            });

            prescriptionModal.addEventListener('hidden.bs.modal', function () {
                stopPrescriptionCamera();
            });
        }

        applyPrescriptionMode(modeInput.value || 'manual');
        syncPrescriptionEnabledState();

        if (cameraCaptureInput.value) {
            cameraPreview.src = cameraCaptureInput.value;
            cameraPreview.classList.remove('d-none');
            cameraVideo.classList.add('d-none');
            cameraRetakeButton.classList.remove('d-none');
            cameraCaptureButton.classList.add('d-none');
            cameraOverlay.classList.add('is-hidden');
            cameraStatus.textContent = 'Captured image is ready. You can retake it if needed.';
        }
    });
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
