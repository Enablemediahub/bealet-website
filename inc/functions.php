<?php
/**
 * Bealet Website - Helper Functions
 */

/**
 * Generate CSRF Token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verifyCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

/**
 * Sanitize Input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    if (is_null($input)) {
        return '';
    }
    return htmlspecialchars(stripslashes(trim((string)$input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Decode text that may have been HTML-escaped before being stored.
 */
function decodeStoredText($input) {
    if (is_array($input)) {
        return array_map('decodeStoredText', $input);
    }

    if ($input === null) {
        return '';
    }

    $value = (string) $input;
    for ($i = 0; $i < 3; $i++) {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded === $value) {
            break;
        }
        $value = $decoded;
    }

    return $value;
}

/**
 * Validate Email
 */
function validateEmail($email) {
    $email = sanitize($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate Phone Number
 */
function validatePhone($phone) {
    $phone = sanitize($phone);
    // Ghana phone number formats:
    // +233XXXXXXXXX, 0XXXXXXXXX, or 10-digit local format.
    $normalized = str_replace([' ', '-', '(', ')'], '', $phone);
    $pattern = '/^(?:\+233[0-9]{9}|0[0-9]{9}|[0-9]{10})$/';
    return preg_match($pattern, $normalized) === 1;
}

/**
 * Normalize Ghana phone input to a consistent +233XXXXXXXXX format when possible.
 */
function normalizePhoneNumber($phone) {
    $normalized = str_replace([' ', '-', '(', ')'], '', (string) $phone);

    if (preg_match('/^\+233[0-9]{9}$/', $normalized) === 1) {
        return $normalized;
    }

    if (preg_match('/^233[0-9]{9}$/', $normalized) === 1) {
        return '+' . $normalized;
    }

    if (preg_match('/^0[0-9]{9}$/', $normalized) === 1) {
        return '+233' . substr($normalized, 1);
    }

    if (preg_match('/^[0-9]{9}$/', $normalized) === 1) {
        return '+233' . $normalized;
    }

    return $normalized;
}

/**
 * Format phone number for tel links using Ghana country code when possible.
 */
function formatPhoneForTel($phone) {
    $normalized = str_replace([' ', '-', '(', ')'], '', (string) $phone);

    if (preg_match('/^0[0-9]{9}$/', $normalized) === 1) {
        return '+233' . substr($normalized, 1);
    }

    if (preg_match('/^[0-9]{10}$/', $normalized) === 1 && strpos($normalized, '0') === 0) {
        return '+233' . substr($normalized, 1);
    }

    return $normalized;
}

/**
 * Validate Password Strength
 */
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors[] = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters long';
    }
    
    if (PASSWORD_UPPERCASE_REQUIRED && !preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    
    if (PASSWORD_NUMBER_REQUIRED && !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    
    if (PASSWORD_SPECIAL_CHAR_REQUIRED && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $errors[] = 'Password must contain at least one special character';
    }
    
    return $errors;
}

/**
 * Hash Password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

/**
 * Verify Password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Start an authenticated application session for a user.
 */
function signInUser(array $user, $rememberMe = false) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'] ?? '';
    $_SESSION['user_email'] = $user['email'] ?? '';
    $_SESSION['is_admin'] = $user['is_admin'] ?? 0;
    $_SESSION['admin_role'] = getUserAdminRole($user);
    $_SESSION['last_activity'] = time();

    if ($rememberMe) {
        $rememberToken = bin2hex(random_bytes(32));
        setcookie('remember_token', $rememberToken, time() + REMEMBER_ME_LIFETIME, '/', '', false, true);
        setcookie('remember_user_id', (string) $user['id'], time() + REMEMBER_ME_LIFETIME, '/', '', false, true);
    }
}

/**
 * Generate Tracking Code
 */
function generateTrackingCode($customerName = '') {
    global $db;

    $year = date('Y');
    $initials = 'X';
    $name = trim((string) $customerName);

    if ($name !== '') {
        $parts = preg_split('/\s+/', preg_replace('/[^A-Za-z\s]/', ' ', $name), -1, PREG_SPLIT_NO_EMPTY);
        if (!empty($parts)) {
            $letters = '';
            foreach ($parts as $part) {
                $letters .= strtoupper(substr($part, 0, 1));
                if (strlen($letters) >= 3) {
                    break;
                }
            }
            if ($letters !== '') {
                $initials = $letters;
            }
        }
    }

    $countResult = $db->fetch(
        "SELECT COUNT(*) AS total FROM orders WHERE YEAR(created_at) = ?",
        [$year]
    );
    $sequence = ((int) ($countResult['total'] ?? 0)) + 1;

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $candidate = 'BOC/' . str_pad((string) ($sequence + $attempt), 4, '0', STR_PAD_LEFT) . '/' . $year . '/' . $initials;
        $exists = $db->fetch("SELECT id FROM orders WHERE tracking_code = ?", [$candidate]);
        if (!$exists) {
            return $candidate;
        }
    }

    $fallback = 'BOC/' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)) . '/' . $year . '/' . $initials;
    return $fallback;
}

/**
 * Get Current User
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $db;
    $user = $db->fetch(
        "SELECT * FROM users WHERE id = ?",
        [$_SESSION['user_id']]
    );
    
    return $user ?: null;
}

/**
 * Ensure the users table supports admin role separation.
 */
function ensureAdminRoleColumn() {
    static $schemaReady = false;

    if ($schemaReady) {
        return;
    }

    global $db;

    $column = $db->fetch(
        "SELECT COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'users'
           AND COLUMN_NAME = 'admin_role'"
    );

    if (!$column) {
        $db->execute("ALTER TABLE users ADD COLUMN admin_role VARCHAR(30) NULL AFTER is_admin");
    }

    $db->execute(
        "UPDATE users
         SET admin_role = CASE
            WHEN is_admin = 1 THEN 'super_admin'
            ELSE 'customer'
         END
         WHERE admin_role IS NULL OR admin_role = ''"
    );

    $schemaReady = true;
}

/**
 * Check if User is Logged In
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if User is Admin
 */
function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

/**
 * Resolve admin role for a user array or the current session.
 */
function getUserAdminRole($user = null) {
    if (is_array($user)) {
        $isAdminUser = !empty($user['is_admin']);
        $role = strtolower(trim((string) ($user['admin_role'] ?? '')));
    } else {
        $isAdminUser = isAdmin();
        $role = strtolower(trim((string) ($_SESSION['admin_role'] ?? '')));
    }

    if (!$isAdminUser) {
        return 'customer';
    }

    if (!in_array($role, ['super_admin', 'sub_admin'], true)) {
        return 'super_admin';
    }

    return $role;
}

/**
 * Human-friendly admin role labels.
 */
function getAdminRoleLabel($role) {
    return $role === 'sub_admin' ? 'Sub Admin' : 'Super Admin';
}

/**
 * Check whether the active admin is a super admin.
 */
function isSuperAdmin($user = null) {
    if (!isAdmin()) {
        return false;
    }

    if ($user === null) {
        $user = getCurrentUser();
    }

    return getUserAdminRole($user) === 'super_admin';
}

/**
 * Guard a page for super admin only access.
 */
function requireSuperAdmin() {
    if (!isSuperAdmin()) {
        setFlashMessage('error', 'You do not have permission to manage that section.');
        redirect(APP_URL . '/admin/dashboard.php');
    }
}

/**
 * Check Session Timeout
 */
function checkSessionTimeout() {
    $timeout = SESSION_LIFETIME;
    
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > $timeout) {
            session_destroy();
            return false;
        }
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Redirect to URL
 */
function redirect($url, $statusCode = 302) {
    header('Location: ' . $url, true, $statusCode);
    exit;
}

/**
 * Set Flash Message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type, // success, error, warning, info
        'message' => $message
    ];
}

/**
 * Get Flash Message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Send Email
 */
function sendEmail($to, $subject, $body, $isHtml = true) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: " . ($isHtml ? "text/html" : "text/plain") . "; charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . MAIL_FROM . "\r\n";
    
    $result = mail($to, $subject, $body, $headers);
    
    if (!$result) {
        error_log("Email send failed to $to: $subject");
    }
    
    return $result;
}

/**
 * Send SMS using Arkesel.
 */
function sendSmsMessage($phoneNumber, $message) {
    $apiKey = defined('ARKESEL_API_KEY') ? trim((string) ARKESEL_API_KEY) : '';
    $senderId = defined('ARKESEL_SENDER_ID') ? trim((string) ARKESEL_SENDER_ID) : '';
    $endpoint = defined('ARKESEL_BASE_URL') ? trim((string) ARKESEL_BASE_URL) : '';

    if ($apiKey === '' || $apiKey === 'your_arkesel_api_key' || $endpoint === '') {
        return [
            'success' => false,
            'message' => 'Arkesel SMS is not configured.'
        ];
    }

    $normalizedPhone = normalizePhoneNumber($phoneNumber);
    if (!validatePhone($normalizedPhone)) {
        return [
            'success' => false,
            'message' => 'Invalid Ghana phone number for SMS.'
        ];
    }

    $payload = json_encode([
        'sender' => $senderId !== '' ? $senderId : 'Bealet',
        'message' => trim((string) $message),
        'recipients' => [$normalizedPhone],
    ]);

    if ($payload === false) {
        return [
            'success' => false,
            'message' => 'Unable to encode SMS payload.'
        ];
    }

    $responseBody = false;
    $httpStatus = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $responseBody = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            return [
                'success' => false,
                'message' => $curlError !== '' ? $curlError : 'Unable to contact Arkesel.'
            ];
        }
    } else {
        $httpOptions = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'api-key: ' . $apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ]),
                'content' => $payload,
                'timeout' => 20,
            ],
        ];

        $context = stream_context_create($httpOptions);
        $responseBody = @file_get_contents($endpoint, false, $context);

        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('/\s(\d{3})\s/', $headerLine, $matches)) {
                    $httpStatus = (int) $matches[1];
                    break;
                }
            }
        }

        if ($responseBody === false) {
            return [
                'success' => false,
                'message' => 'Unable to contact Arkesel.'
            ];
        }
    }

    $decoded = json_decode((string) $responseBody, true);
    $isSuccess = $httpStatus >= 200 && $httpStatus < 300;

    if (is_array($decoded)) {
        $apiSuccess = $decoded['success'] ?? $decoded['status'] ?? null;
        if ($apiSuccess !== null) {
            $isSuccess = $isSuccess && ($apiSuccess === true || $apiSuccess === 'success' || $apiSuccess === 1 || $apiSuccess === '1');
        }
    }

    return [
        'success' => $isSuccess,
        'message' => is_array($decoded) ? (string) ($decoded['message'] ?? 'SMS request processed.') : 'SMS request processed.',
        'response' => $decoded,
        'http_status' => $httpStatus,
    ];
}

/**
 * Send an order tracking SMS after successful payment.
 */
function sendOrderTrackingSms($order) {
    if (!is_array($order)) {
        return [
            'success' => false,
            'message' => 'Invalid order payload for SMS.'
        ];
    }

    $phone = trim((string) ($order['order_phone'] ?? ''));
    $trackingCode = trim((string) ($order['tracking_code'] ?? ''));
    $customerName = trim((string) ($order['shipping_address'] ?? ''));
    $totalAmount = formatCurrency((float) ($order['total_amount'] ?? 0));

    if ($phone === '' || $trackingCode === '') {
        return [
            'success' => false,
            'message' => 'Order phone or tracking code missing.'
        ];
    }

    $message = sprintf(
        'Thank you for shopping with %s. Your payment was successful. Tracking Code: %s. Total Paid: %s. Track your order anytime with your code or phone number.',
        getCompanyName(),
        $trackingCode,
        $totalAmount
    );

    return sendSmsMessage($phone, $message);
}

/**
 * Generate Password Reset Email
 */
function sendPasswordResetEmail($email, $resetToken) {
    $resetLink = APP_URL . '/reset-password.php?token=' . $resetToken;
    
    $subject = 'Password Reset Request - ' . APP_NAME;
    $body = "
    <h2>Password Reset Request</h2>
    <p>You requested a password reset for your account.</p>
    <p>Click the link below to reset your password:</p>
    <p><a href='" . $resetLink . "'>Reset Password</a></p>
    <p>This link will expire in 1 hour.</p>
    <p>If you didn't request this, please ignore this email.</p>
    ";
    
    return sendEmail($email, $subject, $body);
}

/**
 * Generate Order Confirmation Email
 */
function sendOrderConfirmationEmail($orderData, $userEmail) {
    global $db;
    
    $trackingCode = $orderData['tracking_code'];
    $totalAmount = $orderData['total_amount'];
    $orderId = $orderData['id'];
    
    $subject = 'Order Confirmation - ' . APP_NAME . ' (Tracking: ' . $trackingCode . ')';
    
    $body = "
    <h2>Order Confirmation</h2>
    <p>Thank you for your order!</p>
    <p><strong>Tracking Code:</strong> " . $trackingCode . "</p>
    <p><strong>Order ID:</strong> " . $orderId . "</p>
    <p><strong>Total Amount:</strong> " . CURRENCY . " " . number_format($totalAmount, 2) . "</p>
    <p><strong>Status:</strong> Pending</p>
    <br>
    <p>Track your order here: <a href='" . APP_URL . "/track-order.php?code=" . $trackingCode . "'>View Order Status</a></p>
    <br>
    <p>Thank you for shopping with " . APP_NAME . "!</p>
    ";
    
    return sendEmail($userEmail, $subject, $body);
}

/**
 * Generate Appointment Confirmation Email
 */
function sendAppointmentConfirmationEmail($appointmentData) {
    $email = $appointmentData['email'];
    $name = $appointmentData['name'];
    $date = $appointmentData['appointment_date'];
    $time = $appointmentData['appointment_time'];
    
    $subject = 'Appointment Confirmation - ' . APP_NAME;
    
    $body = "
    <h2>Appointment Confirmed</h2>
    <p>Dear " . $name . ",</p>
    <p>Your appointment has been scheduled:</p>
    <p><strong>Date:</strong> " . date('F d, Y', strtotime($date)) . "</p>
    <p><strong>Time:</strong> " . $time . "</p>
    <br>
    <p>Please arrive 10 minutes early.</p>
    <p>Thank you for choosing " . APP_NAME . "!</p>
    ";
    
    return sendEmail($email, $subject, $body);
}

/**
 * Validate File Upload
 */
function validateFileUpload($file, $allowedExtensions = null, $maxSize = null) {
    $errors = [];

    $allowedExtensions = is_array($allowedExtensions) && !empty($allowedExtensions)
        ? array_map('strtolower', $allowedExtensions)
        : ALLOWED_EXTENSIONS;
    $maxSize = is_numeric($maxSize) ? (int) $maxSize : UPLOAD_MAX_SIZE;
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed';
        return $errors;
    }
    
    if ($file['size'] > $maxSize) {
        $errors[] = 'File size exceeds maximum allowed size';
        return $errors;
    }
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions, true)) {
        $errors[] = 'File type not allowed';
        return $errors;
    }
    
    return $errors;
}

/**
 * Upload File
 */
function uploadFile($file, $subfolder = '', $allowedExtensions = null, $maxSize = null) {
    $errors = validateFileUpload($file, $allowedExtensions, $maxSize);
    
    if (!empty($errors)) {
        return [
            'success' => false,
            'errors' => $errors
        ];
    }
    
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    if ($subfolder) {
        $uploadPath = UPLOAD_DIR . $subfolder . '/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
    } else {
        $uploadPath = UPLOAD_DIR;
    }
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid() . '_' . time() . '.' . $fileExtension;
    $filepath = $uploadPath . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath
        ];
    }
    
    return [
        'success' => false,
        'errors' => ['Failed to move uploaded file']
    ];
}

/**
 * Ensure order prescriptions storage exists.
 */
function ensureOrderPrescriptionsTable() {
    global $db;

    static $initialized = false;

    if ($initialized || !isset($db)) {
        return;
    }

    try {
        $db->execute(
            "CREATE TABLE IF NOT EXISTS order_prescriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                prescription_source ENUM('manual', 'upload', 'camera', 'manual_upload') NOT NULL DEFAULT 'manual',
                frame_notes VARCHAR(255) NULL,
                manual_prescription JSON NULL,
                file_path VARCHAR(255) NULL,
                original_filename VARCHAR(255) NULL,
                customer_notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_order_prescriptions_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                KEY idx_order_prescriptions_order_id (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        // Keep checkout available even if the prescription table cannot initialize.
    }

    $initialized = true;
}

/**
 * Get a public URL for a stored prescription attachment.
 */
function getPrescriptionFileUrl($path) {
    if (empty($path)) {
        return null;
    }

    $path = trim((string) $path);
    if ($path === '') {
        return null;
    }

    if (filter_var($path, FILTER_VALIDATE_URL)) {
        return $path;
    }

    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    $basename = basename($normalized);

    $candidates = [
        __DIR__ . '/../' . $normalized,
        __DIR__ . '/../assets/uploads/prescriptions/' . $basename,
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return APP_URL . '/assets/uploads/prescriptions/' . rawurlencode($basename);
        }
    }

    return null;
}

/**
 * Save a base64/data-URI image into uploads.
 */
function uploadDataUriImage($dataUri, $subfolder = '', $allowedMimeTypes = null, $maxSize = null) {
    $allowedMimeTypes = is_array($allowedMimeTypes) && !empty($allowedMimeTypes)
        ? $allowedMimeTypes
        : ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = is_numeric($maxSize) ? (int) $maxSize : UPLOAD_MAX_SIZE;

    $dataUri = trim((string) $dataUri);
    if ($dataUri === '') {
        return [
            'success' => false,
            'errors' => ['Captured image is empty.']
        ];
    }

    if (!preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/', $dataUri, $matches)) {
        return [
            'success' => false,
            'errors' => ['Invalid captured image format.']
        ];
    }

    $mimeType = strtolower($matches[1]);
    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        return [
            'success' => false,
            'errors' => ['Captured image type is not allowed.']
        ];
    }

    $binaryData = base64_decode($matches[2], true);
    if ($binaryData === false) {
        return [
            'success' => false,
            'errors' => ['Unable to decode the captured image.']
        ];
    }

    if (strlen($binaryData) > $maxSize) {
        return [
            'success' => false,
            'errors' => ['Captured image exceeds maximum allowed size.']
        ];
    }

    $mimeToExtension = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $fileExtension = $mimeToExtension[$mimeType] ?? 'jpg';

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    if ($subfolder) {
        $uploadPath = UPLOAD_DIR . $subfolder . '/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
    } else {
        $uploadPath = UPLOAD_DIR;
    }

    $filename = uniqid('capture_', true) . '_' . time() . '.' . $fileExtension;
    $filepath = $uploadPath . $filename;

    if (file_put_contents($filepath, $binaryData) === false) {
        return [
            'success' => false,
            'errors' => ['Failed to save captured image.']
        ];
    }

    return [
        'success' => true,
        'filename' => $filename,
        'filepath' => $filepath,
        'mime_type' => $mimeType,
    ];
}

/**
 * Ensure the cart table exists and can be queried safely.
 */
function ensureCartTable($forceRepair = false) {
    global $db;

    static $initialized = false;

    if (!isset($db)) {
        return;
    }

    if ($initialized && !$forceRepair) {
        return;
    }

    try {
        if ($forceRepair) {
            $db->execute("DROP TABLE IF EXISTS cart");
        }

        $db->execute(
            "CREATE TABLE IF NOT EXISTS cart (
                id INT PRIMARY KEY AUTO_INCREMENT,
                session_id VARCHAR(255) DEFAULT NULL,
                user_id INT NULL,
                product_id INT NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                UNIQUE KEY unique_cart_item (session_id, user_id, product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $initialized = true;
    } catch (Throwable $exception) {
        error_log(
            '[CART_TABLE_ENSURE_FAILED] ' . $exception->getMessage() . "\n",
            3,
            __DIR__ . '/../logs/error.log'
        );
    }
}

/**
 * Detect known cart-table failure modes.
 */
function isCartTableUnavailable(PDOException $exception) {
    $message = $exception->getMessage();

    return strpos($message, "Table '" . DB_NAME . ".cart' doesn't exist in engine") !== false
        || strpos($message, "Table '" . DB_NAME . ".cart' doesn't exist") !== false
        || strpos($message, "Base table or view not found") !== false;
}

/**
 * Run a cart query without letting a broken cart table crash the storefront.
 */
function runCartQuerySafely(callable $callback, $fallback) {
    ensureCartTable();

    try {
        return $callback();
    } catch (PDOException $exception) {
        if (!isCartTableUnavailable($exception)) {
            throw $exception;
        }

        ensureCartTable(true);

        try {
            return $callback();
        } catch (PDOException $retryException) {
            if (!isCartTableUnavailable($retryException)) {
                throw $retryException;
            }
        }

        error_log(
            '[CART_TABLE_UNAVAILABLE] ' . $exception->getMessage() . "\n",
            3,
            __DIR__ . '/../logs/error.log'
        );

        return $fallback;
    }
}

/**
 * Get Cart Count
 */
function getCartCount() {
    global $db;

    $result = runCartQuerySafely(function () use ($db) {
        if (isLoggedIn()) {
            return $db->fetch(
                "SELECT SUM(quantity) as count FROM cart WHERE user_id = ?",
                [$_SESSION['user_id']]
            );
        }

        return $db->fetch(
            "SELECT SUM(quantity) as count FROM cart WHERE session_id = ?",
            [session_id()]
        );
    }, ['count' => 0]);

    return $result['count'] ?? 0;
}

/**
 * Get Cart Items
 */
function getCartItems() {
    global $db;

    return runCartQuerySafely(function () use ($db) {
        if (isLoggedIn()) {
            return $db->fetchAll(
                "SELECT c.id, c.quantity, p.id as product_id, p.name, p.price, p.main_image as image
                 FROM cart c
                 JOIN products p ON c.product_id = p.id
                 WHERE c.user_id = ? AND p.is_active = 1
                 ORDER BY c.created_at DESC",
                [$_SESSION['user_id']]
            );
        }

        return $db->fetchAll(
            "SELECT c.id, c.quantity, p.id as product_id, p.name, p.price, p.main_image as image
             FROM cart c
             JOIN products p ON c.product_id = p.id
             WHERE c.session_id = ? AND p.is_active = 1
             ORDER BY c.created_at DESC",
            [session_id()]
        );
    }, []);
}

/**
 * Get Cart Total
 */
function getCartTotal() {
    $cartItems = getCartItems();
    $total = 0;
    
    foreach ($cartItems as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    
    return $total;
}

/**
 * Format Currency
 */
function formatCurrency($amount) {
    return CURRENCY . ' ' . number_format($amount, 2);
}

/**
 * Get Product Image Path for storefront product arrays
 */
function getProductImagePath($product) {
    if (!empty($product['main_image'])) {
        return getProductImageUrl($product['main_image']);
    }

    if (!empty($product['image'])) {
        return getProductImageUrl($product['image']);
    }

    return getProductImageUrl(null);
}

/**
 * Get Product Image URL
 */
function getProductImageUrl($image) {
    if (!empty($image)) {
        $image = trim((string) $image);

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        $normalized = ltrim(str_replace('\\', '/', $image), '/');
        $basename = basename($normalized);

        if (str_starts_with($normalized, 'assets/')) {
            $fullPath = __DIR__ . '/../' . $normalized;
            if (is_file($fullPath)) {
                return APP_URL . '/' . $normalized;
            }
        }

        $candidates = [
            [
                'path' => __DIR__ . '/../assets/uploads/products/' . $basename,
                'url' => APP_URL . '/assets/uploads/products/' . rawurlencode($basename),
            ],
            [
                'path' => __DIR__ . '/../assets/images/' . $basename,
                'url' => APP_URL . '/assets/images/' . rawurlencode($basename),
            ],
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate['path'])) {
                return $candidate['url'];
            }
        }
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="560" height="360" viewBox="0 0 560 360"><rect width="560" height="360" fill="#F8FAFC"/><text x="50%" y="45%" dominant-baseline="middle" text-anchor="middle" fill="#94A3B8" font-family="Arial, Helvetica, sans-serif" font-size="24">Image not available</text><text x="50%" y="58%" dominant-baseline="middle" text-anchor="middle" fill="#CBD5E1" font-family="Arial, Helvetica, sans-serif" font-size="18">Bealet Product</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Resolve a try-on asset path to a public URL when possible.
 */
function getTryOnAssetUrl($asset) {
    if (empty($asset)) {
        return '';
    }

    $asset = trim((string) $asset);
    if (filter_var($asset, FILTER_VALIDATE_URL)) {
        return $asset;
    }

    $normalized = ltrim(str_replace('\\', '/', $asset), '/');
    $basename = basename($normalized);
    $candidates = [
        [
            'path' => __DIR__ . '/../' . $normalized,
            'url' => APP_URL . '/' . $normalized,
        ],
        [
            'path' => __DIR__ . '/../assets/uploads/ar-models/' . $basename,
            'url' => APP_URL . '/assets/uploads/ar-models/' . rawurlencode($basename),
        ],
        [
            'path' => __DIR__ . '/../assets/images/ar-models/' . $basename,
            'url' => APP_URL . '/assets/images/ar-models/' . rawurlencode($basename),
        ],
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate['path'])) {
            return $candidate['url'];
        }
    }

    return '';
}

/**
 * Determine whether a product has at least one usable try-on asset.
 */
function productHasTryOnAssets($product) {
    $product = (array) $product;

    $frontAsset = getTryOnAssetUrl($product['ar_model_2d'] ?? '');
    $glbAsset = getTryOnAssetUrl($product['ar_model_3d'] ?? '');

    return $frontAsset !== '' || $glbAsset !== '';
}

/**
 * Build the public try-on link for a product when try-on assets exist.
 */
function getProductTryOnLink($product) {
    $product = (array) $product;
    if (!productHasTryOnAssets($product) || empty($product['id'])) {
        return '';
    }

    return APP_URL . '/ar-tryon.php?frame=' . (int) $product['id'];
}

/**
 * Ensure the product gallery table exists.
 */
function ensureProductGalleryTable() {
    global $db;

    static $initialized = false;

    if ($initialized || !isset($db)) {
        return;
    }

    try {
        $db->execute(
            "CREATE TABLE IF NOT EXISTS product_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                image_slot TINYINT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_product_slot (product_id, image_slot),
                KEY idx_product_images_product (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        // Keep storefront/admin usable even if gallery support cannot initialize.
    }

    $initialized = true;
}

/**
 * Get additional product gallery images keyed by slot.
 */
function getProductAdditionalImages($productId) {
    global $db;

    ensureProductGalleryTable();

    if (!isset($db) || (int) $productId <= 0) {
        return [];
    }

    try {
        $rows = $db->fetchAll(
            "SELECT image_slot, image_path
             FROM product_images
             WHERE product_id = ?
             ORDER BY image_slot ASC",
            [(int) $productId]
        );
    } catch (Throwable $e) {
        return [];
    }

    $images = [];
    foreach ($rows as $row) {
        $slot = (int) ($row['image_slot'] ?? 0);
        if ($slot >= 1 && $slot <= 4 && !empty($row['image_path'])) {
            $images[$slot] = (string) $row['image_path'];
        }
    }

    return $images;
}

/**
 * Get the full storefront gallery for a product: main image first, then up to 4 extra images.
 */
function getProductGalleryImages($productId, $product = null) {
    $gallery = [];
    $seen = [];

    $mainImage = getProductImagePath((array) $product);
    if ($mainImage !== '') {
        $gallery[] = $mainImage;
        $seen[$mainImage] = true;
    }

    foreach (getProductAdditionalImages($productId) as $imagePath) {
        $imageUrl = getProductImageUrl($imagePath);
        if (!isset($seen[$imageUrl])) {
            $gallery[] = $imageUrl;
            $seen[$imageUrl] = true;
        }
    }

    return array_slice($gallery, 0, 5);
}

/**
 * Canonical product categories used across the storefront and admin.
 */
function getProductCategoryOptions() {
    return [
        'frames' => 'Frames',
        'premium' => 'Premium',
        'assorted' => 'Assorted',
        'casuals' => 'Casuals',
        'lenses' => 'Lenses',
        'contact_lenses' => 'Contact Lenses',
        'accessories' => 'Accessories',
    ];
}

/**
 * Normalize product category values so legacy labels still map to canonical keys.
 */
function normalizeProductCategoryKey($category) {
    $category = strtolower(trim(decodeStoredText((string) $category)));
    $category = preg_replace('/[^a-z0-9]+/', '_', $category);
    $category = trim((string) $category, '_');

    $aliases = [
        'frame' => 'frames',
        'frames' => 'frames',
        'premium' => 'premium',
        'assorted' => 'assorted',
        'casual' => 'casuals',
        'casuals' => 'casuals',
        'lense' => 'lenses',
        'lens' => 'lenses',
        'lenses' => 'lenses',
        'contact_lens' => 'contact_lenses',
        'contact_lenses' => 'contact_lenses',
        'accessory' => 'accessories',
        'accessories' => 'accessories',
    ];

    return $aliases[$category] ?? $category;
}

/**
 * Ensure the product category column can store custom category keys.
 */
function ensureProductCategoryStorageSupport() {
    global $db;

    static $initialized = false;

    if ($initialized || !isset($db)) {
        return;
    }

    try {
        $categoryColumn = $db->fetch("SHOW COLUMNS FROM products LIKE 'category'");
        $columnType = strtolower((string) ($categoryColumn['Type'] ?? ''));

        if ($columnType !== '' && str_starts_with($columnType, 'enum(')) {
            $db->update("ALTER TABLE products MODIFY COLUMN category VARCHAR(50) NOT NULL");
        }
    } catch (Throwable $e) {
        // Keep product pages available even if the schema upgrade cannot run in this pass.
    }

    $initialized = true;
}

/**
 * Audience/group options for frame-heavy product browsing.
 */
function getProductAudienceOptions() {
    return [
        'male' => 'Male Frames',
        'female' => 'Female Frames',
        'kids' => 'Kids Frames',
        'unisex' => 'Unisex Frames',
    ];
}

/**
 * Human-readable product category label.
 */
function formatProductCategoryLabel($category) {
    $category = normalizeProductCategoryKey($category);
    $options = getProductCategoryOptions();

    if (isset($options[$category])) {
        return $options[$category];
    }

    return ucwords(str_replace('_', ' ', $category));
}

/**
 * Human-readable frame audience label.
 */
function formatProductAudienceLabel($audience) {
    $audience = trim((string) $audience);
    $options = getProductAudienceOptions();

    if (isset($options[$audience])) {
        return $options[$audience];
    }

    return ucwords(str_replace('_', ' ', $audience));
}

/**
 * Resolve a review image stored on disk.
 */
function getProductReviewImageLocalPath($image) {
    if (empty($image)) {
        return null;
    }

    $image = trim((string) $image);
    if (filter_var($image, FILTER_VALIDATE_URL)) {
        return null;
    }

    $normalized = ltrim(str_replace('\\', '/', $image), '/');
    $basename = basename($normalized);
    $candidates = [
        __DIR__ . '/../' . $normalized,
        __DIR__ . '/../assets/uploads/reviews/' . $basename,
        __DIR__ . '/../assets/images/' . $basename,
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Public URL for a product review image.
 */
function getProductReviewImageUrl($image) {
    if (empty($image)) {
        return '';
    }

    $image = trim((string) $image);
    if (filter_var($image, FILTER_VALIDATE_URL)) {
        return $image;
    }

    $normalized = ltrim(str_replace('\\', '/', $image), '/');
    $basename = basename($normalized);

    if (str_starts_with($normalized, 'assets/')) {
        $localPath = __DIR__ . '/../' . $normalized;
        if (is_file($localPath)) {
            return APP_URL . '/' . $normalized;
        }
    }

    $localPath = getProductReviewImageLocalPath($image);
    if ($localPath && str_contains(str_replace('\\', '/', $localPath), '/assets/uploads/reviews/')) {
        return APP_URL . '/assets/uploads/reviews/' . rawurlencode($basename);
    }

    if ($localPath && str_contains(str_replace('\\', '/', $localPath), '/assets/images/')) {
        return APP_URL . '/assets/images/' . rawurlencode($basename);
    }

    return '';
}

/**
 * Resolve a blog image to a local absolute path when possible.
 */
function getBlogImageLocalPath($image) {
    if (empty($image)) {
        return null;
    }

    $image = trim((string) $image);

    // Remote images are not local filesystem paths.
    if (filter_var($image, FILTER_VALIDATE_URL)) {
        return null;
    }

    $normalized = ltrim(str_replace('\\', '/', $image), '/');
    $basename = basename($normalized);

    $candidates = [
        __DIR__ . '/../' . $normalized,
        __DIR__ . '/../assets/uploads/blog/' . $basename,
        __DIR__ . '/../assets/images/' . $basename,
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Get Blog Image URL
 */
function getBlogImageUrl($image) {
    if (!empty($image)) {
        $image = trim((string) $image);

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        $normalized = ltrim(str_replace('\\', '/', $image), '/');
        $basename = basename($normalized);

        // If stored as full relative path (e.g. assets/uploads/blog/file.jpg)
        if (str_starts_with($normalized, 'assets/')) {
            $localPath = __DIR__ . '/../' . $normalized;
            if (is_file($localPath)) {
                return APP_URL . '/' . $normalized;
            }
        }

        $localPath = getBlogImageLocalPath($image);
        if ($localPath) {
            if (str_contains($localPath, 'assets/uploads/blog/')) {
                return APP_URL . '/assets/uploads/blog/' . rawurlencode($basename);
            }
            if (str_contains($localPath, 'assets/images/')) {
                return APP_URL . '/assets/images/' . rawurlencode($basename);
            }
        }
    }

    return 'https://via.placeholder.com/700x400?text=Blog';
}

/**
 * Get configured hero slides from local JSON storage.
 */
function getHeroSlides() {
    $dataDir = __DIR__ . '/../assets/data';
    $slidesFile = $dataDir . '/hero_slides.json';

    if (!is_file($slidesFile)) {
        return [];
    }

    $raw = file_get_contents($slidesFile);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $slides = [];
    foreach ($decoded as $item) {
        if (!is_array($item) || empty($item['image'])) {
            continue;
        }

        $slides[] = [
            'id' => isset($item['id']) ? (string)$item['id'] : uniqid('hero_', true),
            'image' => (string)$item['image'],
            'title' => isset($item['title']) ? (string)$item['title'] : '',
            'subtitle' => isset($item['subtitle']) ? (string)$item['subtitle'] : '',
            'cta_text' => isset($item['cta_text']) ? (string)$item['cta_text'] : '',
            'cta_url' => isset($item['cta_url']) ? (string)$item['cta_url'] : '',
            'is_active' => isset($item['is_active']) ? (int)$item['is_active'] : 1,
            'sort_order' => isset($item['sort_order']) ? (int)$item['sort_order'] : 0,
        ];
    }

    usort($slides, function ($a, $b) {
        return $a['sort_order'] <=> $b['sort_order'];
    });

    return $slides;
}

/**
 * Check whether a table exists in the current database.
 */
function tableExists($tableName) {
    global $db;

    static $cache = [];
    $tableName = trim((string) $tableName);
    if ($tableName === '') {
        return false;
    }

    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    try {
        $result = $db->fetch(
            "SELECT COUNT(*) AS total
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?",
            [$tableName]
        );
        $cache[$tableName] = ((int) ($result['total'] ?? 0)) > 0;
    } catch (Throwable $e) {
        $cache[$tableName] = false;
    }

    return $cache[$tableName];
}

/**
 * Get site-wide settings with DB-backed overrides.
 */
function getSiteSettings() {
    global $db;

    if (array_key_exists('__site_settings_cache', $GLOBALS) && is_array($GLOBALS['__site_settings_cache'])) {
        return $GLOBALS['__site_settings_cache'];
    }

    $settings = [
        'company_name' => APP_NAME,
        'tagline' => '',
        'primary_phone' => '',
        'secondary_phone' => '',
        'whatsapp_phone' => '',
        'email' => MAIL_FROM,
        'logo_path' => LOGO_URL,
        'login_wallpaper' => '',
        'intro_video' => '',
        'google_client_id' => '',
        'staff_hero_image' => '',
        'contact_hero_image' => '',
        'blog_hero_image' => '',
        'founder_name' => '',
        'founder_role' => '',
        'founder_short_bio' => '',
        'founder_story' => '',
        'founder_quote' => '',
        'founder_thumbnail' => '',
        'founder_hero_image' => '',
        'facebook_url' => '',
        'instagram_url' => '',
        'twitter_url' => '',
        'linkedin_url' => '',
        'tiktok_url' => '',
    ];

    try {
        // Prefer direct read; this avoids false negatives from metadata checks.
        $rows = $db->fetchAll("SELECT setting_key, setting_value FROM site_settings");
        foreach ($rows as $row) {
            $key = (string) ($row['setting_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $settings[$key] = (string) ($row['setting_value'] ?? '');
        }
    } catch (Throwable $e) {
        $GLOBALS['__site_settings_cache'] = $settings;
        return $settings;
    }

    $GLOBALS['__site_settings_cache'] = $settings;
    return $settings;
}

/**
 * Clear cached site settings for the current request.
 */
function resetSiteSettingsCache() {
    unset($GLOBALS['__site_settings_cache']);
}

/**
 * Get a single site setting.
 */
function getSiteSetting($key, $default = '') {
    $settings = getSiteSettings();
    return $settings[$key] ?? $default;
}

/**
 * Get company name.
 */
function getCompanyName() {
    return trim((string) getSiteSetting('company_name', APP_NAME)) ?: APP_NAME;
}

/**
 * Get company tagline.
 */
function getCompanyTagline() {
    return trim((string) getSiteSetting('tagline', ''));
}

/**
 * Get configured business hours.
 */
function getBusinessHours() {
    return [
        ['label' => 'Monday - Friday', 'hours' => '8:00 AM - 5:00 PM'],
        ['label' => 'Saturday', 'hours' => '8:00 AM - 2:00 PM'],
        ['label' => 'Sunday', 'hours' => 'Closed'],
    ];
}

/**
 * Get configured social media links.
 */
function getSocialMediaLinks() {
    $platforms = [
        'facebook' => ['label' => 'Facebook', 'icon' => 'fab fa-facebook-f'],
        'twitter' => ['label' => 'Twitter', 'icon' => 'fab fa-twitter'],
        'instagram' => ['label' => 'Instagram', 'icon' => 'fab fa-instagram'],
        'linkedin' => ['label' => 'LinkedIn', 'icon' => 'fab fa-linkedin-in'],
        'tiktok' => ['label' => 'TikTok', 'icon' => 'fab fa-tiktok'],
    ];

    $links = [];

    foreach ($platforms as $platform => $meta) {
        $url = trim((string) getSiteSetting($platform . '_url', ''));
        if ($url === '') {
            continue;
        }

        $links[] = [
            'platform' => $platform,
            'label' => $meta['label'],
            'icon' => $meta['icon'],
            'url' => $url,
        ];
    }

    return $links;
}

/**
 * Normalize a WhatsApp phone number to digits-only international format.
 */
function normalizeWhatsAppPhoneNumber($phone) {
    $phone = trim((string) $phone);
    if ($phone === '') {
        return '';
    }

    if (strpos($phone, '+') === 0) {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') {
        return '';
    }

    if (strpos($digits, '00') === 0) {
        return substr($digits, 2);
    }

    if (strpos($digits, '0') === 0 && strlen($digits) === 10) {
        return '233' . substr($digits, 1);
    }

    return $digits;
}

/**
 * Build the floating WhatsApp contact configuration from site settings.
 */
function getWhatsAppContactConfig() {
    $rawPhone = trim((string) getSiteSetting('whatsapp_phone', ''));
    $normalizedPhone = normalizeWhatsAppPhoneNumber($rawPhone);

    if ($normalizedPhone === '') {
        return null;
    }

    $message = rawurlencode('Hello ' . getCompanyName() . ', I would like to make an enquiry.');

    return [
        'raw_phone' => $rawPhone,
        'phone' => $normalizedPhone,
        'url' => 'https://wa.me/' . $normalizedPhone . '?text=' . $message,
    ];
}

/**
 * Get logo URL from settings or fallback constant.
 */
function getSiteLogoUrl() {
    $logo = trim((string) getSiteSetting('logo_path', LOGO_URL));
    if ($logo === '') {
        return LOGO_URL;
    }

    if (filter_var($logo, FILTER_VALIDATE_URL)) {
        return $logo;
    }

    $normalized = ltrim(str_replace('\\', '/', $logo), '/');
    $candidates = [
        __DIR__ . '/../' . $normalized,
        __DIR__ . '/../assets/uploads/branding/' . basename($normalized),
        __DIR__ . '/../assets/images/logo/' . basename($normalized),
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            if (str_contains(str_replace('\\', '/', $candidate), '/assets/uploads/branding/')) {
                return APP_URL . '/assets/uploads/branding/' . rawurlencode(basename($candidate));
            }
            if (str_contains(str_replace('\\', '/', $candidate), '/assets/images/logo/')) {
                return APP_URL . '/assets/images/logo/' . rawurlencode(basename($candidate));
            }
            return APP_URL . '/' . $normalized;
        }
    }

    return LOGO_URL;
}

/**
 * Resolve a staff hero image URL from site settings or fallback.
 */
function getStaffHeroImageUrl() {
    $heroImage = trim((string) getSiteSetting('staff_hero_image', ''));

    if ($heroImage !== '') {
        if (filter_var($heroImage, FILTER_VALIDATE_URL)) {
            return $heroImage;
        }

        $normalized = ltrim(str_replace('\\', '/', $heroImage), '/');
        $candidates = [
            __DIR__ . '/../' . $normalized,
            __DIR__ . '/../assets/uploads/branding/' . basename($normalized),
            __DIR__ . '/../assets/images/' . basename($normalized),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                if (str_contains(str_replace('\\', '/', $candidate), '/assets/uploads/branding/')) {
                    return APP_URL . '/assets/uploads/branding/' . rawurlencode(basename($candidate));
                }

                if (str_contains(str_replace('\\', '/', $candidate), '/assets/images/')) {
                    return APP_URL . '/assets/images/' . rawurlencode(basename($candidate));
                }

                return APP_URL . '/' . $normalized;
            }
        }
    }

    return 'https://images.unsplash.com/photo-1574258495973-f010dfbb5371?auto=format&fit=crop&w=1600&q=80';
}

/**
 * Resolve a contact hero image URL from site settings or fallback.
 */
function getContactHeroImageUrl() {
    $heroImage = trim((string) getSiteSetting('contact_hero_image', ''));

    if ($heroImage !== '') {
        if (filter_var($heroImage, FILTER_VALIDATE_URL)) {
            return $heroImage;
        }

        $normalized = ltrim(str_replace('\\', '/', $heroImage), '/');
        $candidates = [
            __DIR__ . '/../' . $normalized,
            __DIR__ . '/../assets/uploads/branding/' . basename($normalized),
            __DIR__ . '/../assets/images/' . basename($normalized),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                if (str_contains(str_replace('\\', '/', $candidate), '/assets/uploads/branding/')) {
                    return APP_URL . '/assets/uploads/branding/' . rawurlencode(basename($candidate));
                }

                if (str_contains(str_replace('\\', '/', $candidate), '/assets/images/')) {
                    return APP_URL . '/assets/images/' . rawurlencode(basename($candidate));
                }

                return APP_URL . '/' . $normalized;
            }
        }
    }

    return 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?auto=format&fit=crop&w=1600&q=80';
}

/**
 * Resolve a blog hero image URL from site settings or fallback.
 */
function getBlogHeroImageUrl() {
    $heroImage = trim((string) getSiteSetting('blog_hero_image', ''));

    if ($heroImage !== '') {
        if (filter_var($heroImage, FILTER_VALIDATE_URL)) {
            return $heroImage;
        }

        $normalized = ltrim(str_replace('\\', '/', $heroImage), '/');
        $candidates = [
            __DIR__ . '/../' . $normalized,
            __DIR__ . '/../assets/uploads/branding/' . basename($normalized),
            __DIR__ . '/../assets/images/' . basename($normalized),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                if (str_contains(str_replace('\\', '/', $candidate), '/assets/uploads/branding/')) {
                    return APP_URL . '/assets/uploads/branding/' . rawurlencode(basename($candidate));
                }

                if (str_contains(str_replace('\\', '/', $candidate), '/assets/images/')) {
                    return APP_URL . '/assets/images/' . rawurlencode(basename($candidate));
                }

                return APP_URL . '/' . $normalized;
            }
        }
    }

    return 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=1600&q=80';
}

/**
 * Resolve an image URL stored in site settings or return a fallback.
 */
function resolveSiteSettingImageUrl($settingKey, $fallbackUrl) {
    $imagePath = trim((string) getSiteSetting($settingKey, ''));

    return resolveMediaPathUrl($imagePath, $fallbackUrl);
}

/**
 * Resolve a stored local/remote media path into a public URL or fallback.
 */
function resolveMediaPathUrl($imagePath, $fallbackUrl = '') {
    $imagePath = trim((string) $imagePath);

    if ($imagePath !== '') {
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            return $imagePath;
        }

        $normalized = ltrim(str_replace('\\', '/', $imagePath), '/');
        $candidates = [
            __DIR__ . '/../' . $normalized,
            __DIR__ . '/../assets/uploads/branding/' . basename($normalized),
            __DIR__ . '/../assets/images/' . basename($normalized),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                if (str_contains(str_replace('\\', '/', $candidate), '/assets/uploads/branding/')) {
                    return APP_URL . '/assets/uploads/branding/' . rawurlencode(basename($candidate));
                }

                if (str_contains(str_replace('\\', '/', $candidate), '/assets/images/')) {
                    return APP_URL . '/assets/images/' . rawurlencode(basename($candidate));
                }

                return APP_URL . '/' . $normalized;
            }
        }
    }

    return $fallbackUrl;
}

/**
 * Get founder content fields from site settings.
 */
function getFounderProfile() {
    return [
        'name' => trim((string) getSiteSetting('founder_name', 'Madam Founder')),
        'role' => trim((string) getSiteSetting('founder_role', 'Founder')),
        'short_bio' => trim((string) getSiteSetting('founder_short_bio', 'A short introduction to the founder will appear here once it has been added from the admin panel.')),
        'story' => trim((string) getSiteSetting('founder_story', 'The founder story has not been added yet.')),
        'quote' => trim((string) getSiteSetting('founder_quote', '')),
        'thumbnail_url' => getFounderThumbnailUrl(),
        'hero_image_url' => getFounderHeroImageUrl(),
    ];
}

/**
 * Resolve founder thumbnail image URL.
 */
function getFounderThumbnailUrl() {
    return resolveSiteSettingImageUrl(
        'founder_thumbnail',
        'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=900&q=80'
    );
}

/**
 * Resolve founder hero image URL.
 */
function getFounderHeroImageUrl() {
    return resolveSiteSettingImageUrl(
        'founder_hero_image',
        'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=1600&q=80'
    );
}

/**
 * Ensure the founder gallery table exists.
 */
function ensureFounderGalleryTable() {
    global $db;

    static $initialized = false;

    if ($initialized || !isset($db)) {
        return;
    }

    try {
        $db->execute(
            "CREATE TABLE IF NOT EXISTS founder_gallery (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_title VARCHAR(255) NOT NULL,
                item_type VARCHAR(40) NOT NULL DEFAULT 'portrait',
                item_description TEXT DEFAULT NULL,
                image_path VARCHAR(255) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_founder_gallery_sort (is_active, sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        // Keep the site available even if founder gallery storage cannot initialize.
    }

    $initialized = true;
}

/**
 * Get founder gallery items for the museum page.
 */
function getFounderGalleryItems($includeInactive = false) {
    global $db;

    ensureFounderGalleryTable();

    if (!isset($db)) {
        return [];
    }

    try {
        $query = "SELECT * FROM founder_gallery";
        if (!$includeInactive) {
            $query .= " WHERE is_active = 1";
        }
        $query .= " ORDER BY sort_order ASC, id ASC";

        return $db->fetchAll($query);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Resolve founder gallery image URL.
 */
function getFounderGalleryImageUrl($imagePath) {
    return resolveMediaPathUrl(
        $imagePath,
        'https://images.unsplash.com/photo-1517048676732-d65bc937f952?auto=format&fit=crop&w=900&q=80'
    );
}

/**
 * Resolve a login wallpaper image URL from site settings or fallback.
 */
function getLoginWallpaperUrl() {
    $wallpaper = trim((string) getSiteSetting('login_wallpaper', ''));

    if ($wallpaper !== '') {
        if (filter_var($wallpaper, FILTER_VALIDATE_URL)) {
            return $wallpaper;
        }

        $normalized = ltrim(str_replace('\\', '/', $wallpaper), '/');
        $candidates = [
            __DIR__ . '/../' . $normalized,
            __DIR__ . '/../assets/uploads/branding/' . basename($normalized),
            __DIR__ . '/../assets/images/' . basename($normalized),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                if (str_contains(str_replace('\\', '/', $candidate), '/assets/uploads/branding/')) {
                    return APP_URL . '/assets/uploads/branding/' . rawurlencode(basename($candidate));
                }

                if (str_contains(str_replace('\\', '/', $candidate), '/assets/images/')) {
                    return APP_URL . '/assets/images/' . rawurlencode(basename($candidate));
                }

                return APP_URL . '/' . $normalized;
            }
        }
    }

    return 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?auto=format&fit=crop&w=1600&q=80';
}

/**
 * Resolve the homepage intro video URL from site settings.
 */
function getIntroVideoUrl() {
    return resolveMediaPathUrl(
        getSiteSetting('intro_video', ''),
        ''
    );
}

/**
 * Get Google Client ID used for Google Sign-In.
 */
function getGoogleClientId() {
    return trim((string) getSiteSetting('google_client_id', ''));
}

/**
 * Get configured branch records.
 */
function getCompanyBranches() {
    global $db;

    if (!tableExists('company_branches')) {
        return [];
    }

    try {
        return $db->fetchAll("SELECT * FROM company_branches WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Persist hero slides to local JSON storage.
 */
function saveHeroSlides($slides) {
    if (!is_array($slides)) {
        return false;
    }

    $dataDir = __DIR__ . '/../assets/data';
    $slidesFile = $dataDir . '/hero_slides.json';

    if (!is_dir($dataDir) && !mkdir($dataDir, 0755, true)) {
        return false;
    }

    $normalized = [];
    foreach ($slides as $index => $slide) {
        if (!is_array($slide) || empty($slide['image'])) {
            continue;
        }

        $normalized[] = [
            'id' => isset($slide['id']) ? (string)$slide['id'] : uniqid('hero_', true),
            'image' => basename((string)$slide['image']),
            'title' => isset($slide['title']) ? trim((string)$slide['title']) : '',
            'subtitle' => isset($slide['subtitle']) ? trim((string)$slide['subtitle']) : '',
            'cta_text' => isset($slide['cta_text']) ? trim((string)$slide['cta_text']) : '',
            'cta_url' => isset($slide['cta_url']) ? trim((string)$slide['cta_url']) : '',
            'is_active' => isset($slide['is_active']) ? (int)$slide['is_active'] : 1,
            'sort_order' => isset($slide['sort_order']) ? (int)$slide['sort_order'] : $index,
        ];
    }

    $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents($slidesFile, $json, LOCK_EX) !== false;
}

/**
 * Get a public URL for a hero slide image.
 */
function getHeroSlideImageUrl($image) {
    if (empty($image)) {
        return 'https://via.placeholder.com/1600x900?text=Bealet+Hero';
    }

    if (filter_var($image, FILTER_VALIDATE_URL)) {
        return $image;
    }

    $basename = basename((string)$image);
    $localPath = __DIR__ . '/../assets/uploads/hero/' . $basename;
    if (is_file($localPath)) {
        return APP_URL . '/assets/uploads/hero/' . rawurlencode($basename);
    }

    return 'https://via.placeholder.com/1600x900?text=Bealet+Hero';
}

/**
 * Ensure the staff members table exists.
 */
function ensureStaffMembersTable() {
    global $db;

    static $initialized = false;

    if ($initialized || !isset($db)) {
        return;
    }

    try {
        $db->execute(
            "CREATE TABLE IF NOT EXISTS staff_members (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                designation VARCHAR(255) NOT NULL,
                branch_id INT DEFAULT NULL,
                email VARCHAR(255) DEFAULT NULL,
                contact VARCHAR(50) DEFAULT NULL,
                thumbnail VARCHAR(255) DEFAULT NULL,
                bio TEXT DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_staff_active_sort (is_active, sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        // Keep the site available even if staff storage cannot initialize.
    }

    try {
        $branchColumn = $db->fetch(
            "SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'staff_members'
               AND COLUMN_NAME = 'branch_id'"
        );

        if (!$branchColumn) {
            $db->execute("ALTER TABLE staff_members ADD COLUMN branch_id INT DEFAULT NULL AFTER designation");
        }
    } catch (Throwable $e) {
        // Keep the site available even if branch support cannot initialize.
    }

    $initialized = true;
}

/**
 * Resolve a staff image to a local absolute path when possible.
 */
function getStaffImageLocalPath($image) {
    if (empty($image)) {
        return null;
    }

    $image = trim((string) $image);

    if (filter_var($image, FILTER_VALIDATE_URL)) {
        return null;
    }

    $normalized = ltrim(str_replace('\\', '/', $image), '/');
    $basename = basename($normalized);

    $candidates = [
        __DIR__ . '/../' . $normalized,
        __DIR__ . '/../assets/uploads/staff/' . $basename,
        __DIR__ . '/../assets/images/' . $basename,
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Get Staff Image URL
 */
function getStaffImageUrl($image, $name = 'Staff Member') {
    if (!empty($image)) {
        $image = trim((string) $image);

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        $normalized = ltrim(str_replace('\\', '/', $image), '/');
        $basename = basename($normalized);

        if (str_starts_with($normalized, 'assets/')) {
            $localPath = __DIR__ . '/../' . $normalized;
            if (is_file($localPath)) {
                return APP_URL . '/' . $normalized;
            }
        }

        $localPath = getStaffImageLocalPath($image);
        if ($localPath) {
            if (str_contains(str_replace('\\', '/', $localPath), '/assets/uploads/staff/')) {
                return APP_URL . '/assets/uploads/staff/' . rawurlencode($basename);
            }

            if (str_contains(str_replace('\\', '/', $localPath), '/assets/images/')) {
                return APP_URL . '/assets/images/' . rawurlencode($basename);
            }
        }
    }

    $initial = strtoupper(substr(trim((string) $name), 0, 1));
    if ($initial === '') {
        $initial = 'S';
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="480" height="480" viewBox="0 0 480 480"><rect width="480" height="480" rx="40" fill="#DBEAFE"/><circle cx="240" cy="180" r="78" fill="#93C5FD"/><path d="M120 390c18-64 72-104 120-104s102 40 120 104" fill="#60A5FA"/><text x="50%" y="52%" dominant-baseline="middle" text-anchor="middle" fill="#1D4ED8" font-family="Arial, Helvetica, sans-serif" font-size="96" font-weight="700">' . $initial . '</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Get active staff members for the public site.
 */
function getActiveStaffMembers() {
    global $db;

    ensureStaffMembersTable();

    if (!isset($db)) {
        return [];
    }

    try {
        return $db->fetchAll(
            "SELECT sm.*, cb.branch_name
             FROM staff_members sm
             LEFT JOIN company_branches cb ON sm.branch_id = cb.id
             WHERE sm.is_active = 1
               AND (sm.branch_id IS NULL OR cb.is_active = 1)
             ORDER BY sm.sort_order ASC, sm.id ASC"
        );
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Ensure the users table can store a profile image for customers.
 */
function ensureUserProfileImageColumn() {
    global $db;

    static $initialized = false;

    if ($initialized || !isset($db)) {
        return;
    }

    try {
        $column = $db->fetch(
            "SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'profile_image'"
        );

        if (!$column) {
            $db->execute("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL AFTER phone");
        }
    } catch (Throwable $e) {
        // Keep the site available even if the profile image column cannot initialize.
    }

    $initialized = true;
}

/**
 * Ensure customer review storage exists.
 */
function ensureCustomerReviewsTable() {
    global $db;

    static $initialized = false;

    if ($initialized || !isset($db)) {
        return;
    }

    ensureUserProfileImageColumn();

    try {
        $db->execute(
            "CREATE TABLE IF NOT EXISTS customer_reviews (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                reviewer_name VARCHAR(255) NOT NULL,
                reviewer_email VARCHAR(255) DEFAULT NULL,
                profile_image VARCHAR(255) DEFAULT NULL,
                rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
                comment TEXT NOT NULL,
                is_approved TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_customer_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                UNIQUE KEY uniq_customer_reviews_user (user_id),
                KEY idx_customer_reviews_status (is_approved, created_at),
                KEY idx_customer_reviews_rating (rating)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        // Keep the site available even if the reviews table cannot initialize.
    }

    try {
        $reviewColumns = $db->fetchAll("SHOW COLUMNS FROM customer_reviews");
        $reviewColumnMap = [];
        foreach ($reviewColumns as $column) {
            if (!empty($column['Field'])) {
                $reviewColumnMap[$column['Field']] = true;
            }
        }

        if (!isset($reviewColumnMap['reviewer_name'])) {
            $db->update("ALTER TABLE customer_reviews ADD COLUMN reviewer_name VARCHAR(255) NOT NULL DEFAULT 'Customer' AFTER user_id");
        }

        if (!isset($reviewColumnMap['reviewer_email'])) {
            $db->update("ALTER TABLE customer_reviews ADD COLUMN reviewer_email VARCHAR(255) DEFAULT NULL AFTER reviewer_name");
        }

        if (!isset($reviewColumnMap['profile_image'])) {
            $db->update("ALTER TABLE customer_reviews ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL AFTER reviewer_email");
        }

        if (!isset($reviewColumnMap['rating'])) {
            $db->update("ALTER TABLE customer_reviews ADD COLUMN rating TINYINT UNSIGNED NOT NULL DEFAULT 5 AFTER profile_image");
        }

        if (!isset($reviewColumnMap['comment'])) {
            $db->update("ALTER TABLE customer_reviews ADD COLUMN comment TEXT NOT NULL AFTER rating");
        }

        if (!isset($reviewColumnMap['is_approved'])) {
            $db->update("ALTER TABLE customer_reviews ADD COLUMN is_approved TINYINT(1) NOT NULL DEFAULT 0 AFTER comment");
        }

        if (!isset($reviewColumnMap['updated_at'])) {
            $db->update("ALTER TABLE customer_reviews ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        }
    } catch (Throwable $e) {
        // Ignore partial upgrade issues so review pages keep loading.
    }

    $initialized = true;
}

/**
 * Ensure product review and audience support exists on older local databases.
 */
function ensureProductReviewSupport() {
    global $db;

    static $initialized = false;

    if ($initialized || !isset($db)) {
        return;
    }

    try {
        $db->execute(
            "CREATE TABLE IF NOT EXISTS reviews (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                user_id INT NULL,
                reviewer_name VARCHAR(255) DEFAULT NULL,
                reviewer_email VARCHAR(255) DEFAULT NULL,
                rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
                comment TEXT DEFAULT NULL,
                review_image VARCHAR(255) DEFAULT NULL,
                is_approved TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_product_review (product_id, user_id),
                KEY idx_reviews_product_created (product_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        // Keep the storefront available if review support cannot be upgraded in this pass.
    }

    try {
        $reviewColumns = $db->fetchAll("SHOW COLUMNS FROM reviews");
        $reviewColumnMap = [];
        foreach ($reviewColumns as $column) {
            if (!empty($column['Field'])) {
                $reviewColumnMap[$column['Field']] = true;
            }
        }

        if (!isset($reviewColumnMap['review_image'])) {
            $db->update("ALTER TABLE reviews ADD COLUMN review_image VARCHAR(255) DEFAULT NULL AFTER comment");
        }

        if (!isset($reviewColumnMap['reviewer_name'])) {
            $db->update("ALTER TABLE reviews ADD COLUMN reviewer_name VARCHAR(255) DEFAULT NULL AFTER user_id");
        }

        if (!isset($reviewColumnMap['reviewer_email'])) {
            $db->update("ALTER TABLE reviews ADD COLUMN reviewer_email VARCHAR(255) DEFAULT NULL AFTER reviewer_name");
        }

        if (!isset($reviewColumnMap['updated_at'])) {
            $db->update("ALTER TABLE reviews ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        }

        if (!isset($reviewColumnMap['is_approved'])) {
            $db->update("ALTER TABLE reviews ADD COLUMN is_approved TINYINT(1) NOT NULL DEFAULT 1 AFTER review_image");
        }

        $db->update("ALTER TABLE reviews MODIFY COLUMN user_id INT NULL");
    } catch (Throwable $e) {
        // Ignore partial upgrade issues to avoid taking down the page.
    }

    try {
        $productColumns = $db->fetchAll("SHOW COLUMNS FROM products");
        $productColumnMap = [];
        foreach ($productColumns as $column) {
            if (!empty($column['Field'])) {
                $productColumnMap[$column['Field']] = true;
            }
        }

        if (!isset($productColumnMap['frame_target'])) {
            $db->update("ALTER TABLE products ADD COLUMN frame_target VARCHAR(50) DEFAULT NULL AFTER category");
        }

        $db->update(
            "UPDATE products
             SET frame_target = CASE
                 WHEN LOWER(CONCAT(COALESCE(name, ''), ' ', COALESCE(description, ''))) REGEXP 'kid|kids|child|children|youth|junior|teen' THEN 'kids'
                 WHEN LOWER(CONCAT(COALESCE(name, ''), ' ', COALESCE(description, ''))) REGEXP 'female|women|woman|ladies|lady|girls|girl|cat-eye' THEN 'female'
                 WHEN LOWER(CONCAT(COALESCE(name, ''), ' ', COALESCE(description, ''))) REGEXP 'male|men|man|gent|gents|boys|boy' THEN 'male'
                 ELSE 'unisex'
             END
             WHERE category = 'frames' AND (frame_target IS NULL OR frame_target = '')"
        );
    } catch (Throwable $e) {
        // Ignore product audience upgrade issues and continue with existing schema.
    }

    $initialized = true;
}

/**
 * Resolve a stored user profile image to a local path when possible.
 */
function getUserProfileImageLocalPath($image) {
    if (empty($image)) {
        return null;
    }

    $image = trim((string) $image);

    if (filter_var($image, FILTER_VALIDATE_URL)) {
        return null;
    }

    $normalized = ltrim(str_replace('\\', '/', $image), '/');
    $basename = basename($normalized);

    $candidates = [
        __DIR__ . '/../' . $normalized,
        __DIR__ . '/../assets/uploads/profiles/' . $basename,
        __DIR__ . '/../assets/images/' . $basename,
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Resolve a public URL for a user's profile image.
 */
function getUserProfileImageUrl($image, $name = 'Customer') {
    if (!empty($image)) {
        $image = trim((string) $image);

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        $normalized = ltrim(str_replace('\\', '/', $image), '/');
        $basename = basename($normalized);

        if (str_starts_with($normalized, 'assets/')) {
            $localPath = __DIR__ . '/../' . $normalized;
            if (is_file($localPath)) {
                return APP_URL . '/' . $normalized;
            }
        }

        $localPath = getUserProfileImageLocalPath($image);
        if ($localPath) {
            if (str_contains(str_replace('\\', '/', $localPath), '/assets/uploads/profiles/')) {
                return APP_URL . '/assets/uploads/profiles/' . rawurlencode($basename);
            }

            if (str_contains(str_replace('\\', '/', $localPath), '/assets/images/')) {
                return APP_URL . '/assets/images/' . rawurlencode($basename);
            }
        }
    }

    $initial = strtoupper(substr(trim((string) $name), 0, 1));
    if ($initial === '') {
        $initial = 'C';
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="240" viewBox="0 0 240 240"><defs><linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#dbeafe"/><stop offset="100%" stop-color="#bfdbfe"/></linearGradient></defs><rect width="240" height="240" rx="120" fill="url(#bg)"/><circle cx="120" cy="92" r="44" fill="#60a5fa"/><path d="M46 202c16-39 42-62 74-62s58 23 74 62" fill="#2563eb"/><text x="50%" y="49%" dominant-baseline="middle" text-anchor="middle" fill="#eff6ff" font-family="Arial, Helvetica, sans-serif" font-size="56" font-weight="700">' . $initial . '</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Get the current review row for a registered customer.
 */
function getCustomerReviewByUserId($userId) {
    global $db;

    ensureCustomerReviewsTable();

    if (!isset($db) || (int) $userId <= 0) {
        return null;
    }

    try {
        return $db->fetch(
            "SELECT * FROM customer_reviews WHERE user_id = ? LIMIT 1",
            [(int) $userId]
        ) ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Get one user's product review if it exists.
 */
function getProductReviewByUserId($productId, $userId) {
    global $db;

    ensureProductReviewSupport();

    if (!isset($db) || (int) $productId <= 0 || (int) $userId <= 0) {
        return null;
    }

    try {
        return $db->fetch(
            "SELECT * FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1",
            [(int) $productId, (int) $userId]
        ) ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Get product reviews with user context for storefront display.
 */
function getProductReviews($productId, $limit = null, $includeUnapproved = false) {
    global $db;

    ensureProductReviewSupport();

    if (!isset($db) || (int) $productId <= 0) {
        return [];
    }

    $query = "SELECT r.*, COALESCE(NULLIF(r.reviewer_name, ''), u.name, 'Customer') AS reviewer_name,
                     u.profile_image AS reviewer_profile_image
              FROM reviews r
              LEFT JOIN users u ON r.user_id = u.id
              WHERE r.product_id = ?";
    $params = [(int) $productId];

    if (!$includeUnapproved) {
        $query .= " AND COALESCE(r.is_approved, 1) = 1";
    }

    $query .= " ORDER BY COALESCE(r.updated_at, r.created_at) DESC, r.id DESC";

    if ($limit !== null) {
        $query .= " LIMIT " . max(1, (int) $limit);
    }

    try {
        return $db->fetchAll($query, $params) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get recent product reviews for cross-storefront testimonial surfaces.
 */
function getRecentProductTestimonials($limit = null, $includeUnapproved = false) {
    global $db;

    ensureProductReviewSupport();

    if (!isset($db)) {
        return [];
    }

    $query = "SELECT r.*, COALESCE(NULLIF(r.reviewer_name, ''), u.name, 'Customer') AS reviewer_name,
                     u.profile_image AS reviewer_profile_image,
                     p.name AS product_name, p.category, p.frame_target
              FROM reviews r
              LEFT JOIN users u ON r.user_id = u.id
              LEFT JOIN products p ON r.product_id = p.id
              WHERE p.id IS NOT NULL AND p.is_active = 1";

    if (!$includeUnapproved) {
        $query .= " AND COALESCE(r.is_approved, 1) = 1";
    }

    $query .= " ORDER BY COALESCE(r.updated_at, r.created_at) DESC, r.id DESC";

    if ($limit !== null) {
        $query .= " LIMIT " . max(1, (int) $limit);
    }

    try {
        return $db->fetchAll($query) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get approved customer reviews for public display.
 */
function getApprovedCustomerReviews($limit = null) {
    global $db;

    ensureCustomerReviewsTable();

    if (!isset($db)) {
        return [];
    }

    $query = "SELECT * FROM customer_reviews WHERE is_approved = 1 ORDER BY updated_at DESC, id DESC";
    $params = [];

    if ($limit !== null) {
        $query .= " LIMIT " . max(1, (int) $limit);
    }

    try {
        return $db->fetchAll($query, $params) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get a summary of approved customer reviews.
 */
function getCustomerReviewSummary() {
    global $db;

    ensureCustomerReviewsTable();

    if (!isset($db)) {
        return [
            'average_rating' => 0,
            'total_reviews' => 0,
        ];
    }

    try {
        $result = $db->fetch(
            "SELECT AVG(rating) AS average_rating, COUNT(*) AS total_reviews
             FROM customer_reviews
             WHERE is_approved = 1"
        );

        return [
            'average_rating' => !empty($result['average_rating']) ? round((float) $result['average_rating'], 1) : 0,
            'total_reviews' => (int) ($result['total_reviews'] ?? 0),
        ];
    } catch (Throwable $e) {
        return [
            'average_rating' => 0,
            'total_reviews' => 0,
        ];
    }
}

/**
 * Get Product Rating
 */
function getProductRating($productId, $includeUnapproved = false) {
    global $db;

    ensureProductReviewSupport();
    
    $query = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE product_id = ?";
    $params = [$productId];

    if (!$includeUnapproved) {
        $query .= " AND COALESCE(is_approved, 1) = 1";
    }

    $result = $db->fetch($query, $params);
    
    return [
        'average' => $result['avg_rating'] ? round($result['avg_rating'], 1) : 0,
        'total' => $result['total_reviews'] ?? 0
    ];
}

/**
 * Check if User is Locked Out
 */
function isUserLockedOut($userId) {
    global $db;
    
    $user = $db->fetch("SELECT locked_until FROM users WHERE id = ?", [$userId]);
    
    if (!$user) {
        return false;
    }
    
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        return true;
    }
    
    return false;
}

/**
 * Increment Login Attempts
 */
function incrementLoginAttempts($userId) {
    global $db;
    
    $db->update(
        "UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?",
        [$userId]
    );
    
    $user = $db->fetch("SELECT login_attempts FROM users WHERE id = ?", [$userId]);
    
    if ($user['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
        $db->update(
            "UPDATE users SET locked_until = ? WHERE id = ?",
            [$lockedUntil, $userId]
        );
    }
}

/**
 * Reset Login Attempts
 */
function resetLoginAttempts($userId) {
    global $db;
    
    $db->update(
        "UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?",
        [$userId]
    );
}

/**
 * Format Date
 */
function formatDate($date, $format = 'F d, Y') {
    return date($format, strtotime($date));
}

/**
 * Get Disabled Dates for Appointment
 */
function getDisabledDates() {
    global $db;
    
    $appointments = $db->fetchAll(
        "SELECT DISTINCT appointment_date FROM appointments 
         WHERE status != 'cancelled' AND appointment_date >= CURDATE()"
    );
    
    $disabledDates = [];
    
    foreach ($appointments as $app) {
        $disabledDates[] = $app['appointment_date'];
    }
    
    return $disabledDates;
}

/**
 * Get Available Time Slots
 */
function getAvailableTimeSlots($date) {
    global $db;
    
    $allSlots = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'];
    
    $bookedSlots = $db->fetchAll(
        "SELECT appointment_time FROM appointments 
         WHERE appointment_date = ? AND status != 'cancelled'",
        [$date]
    );
    
    $bookedTimes = array_column($bookedSlots, 'appointment_time');
    
    $availableSlots = array_diff($allSlots, $bookedTimes);
    
    return array_values($availableSlots);
}

/**
 * Create Log Entry
 */
function createLog($action, $details = '', $userId = null) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $logEntry = "[$timestamp] [$ip] [User: " . ($userId ?? 'Guest') . "] $action: $details\n";
    
    error_log($logEntry, 3, $logFile);
}

/**
 * Generate Slug from Title
 */
function generateSlug($title) {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

/**
 * Get Page Title
 */
function getPageTitle() {
    $pageTitle = APP_NAME;
    
    if (isset($_SESSION['page_title'])) {
        $pageTitle = $_SESSION['page_title'] . ' - ' . APP_NAME;
    }
    
    return $pageTitle;
}

/**
 * Check if Product in Wishlist
 */
function isInWishlist($productId) {
    if (!isLoggedIn()) {
        return false;
    }
    
    global $db;
    
    $result = $db->fetch(
        "SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?",
        [$_SESSION['user_id'], $productId]
    );
    
    return $result !== null;
}

/**
 * Get Wishlist Count
 */
function getWishlistCount() {
    if (!isLoggedIn()) {
        return 0;
    }
    
    global $db;
    
    $result = $db->fetch(
        "SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?",
        [$_SESSION['user_id']]
    );
    
    return $result['count'] ?? 0;
}

/**
 * Sanitize Filename
 */
function sanitizeFilename($filename) {
    $filename = basename($filename);
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    return $filename;
}
