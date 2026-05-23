<?php
/**
 * Bealet Website - Registration Page
 */

session_start();

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

// Check if already logged in
if (isLoggedIn()) {
    redirect(APP_URL . '/');
}

$errors = [];
$formData = [
    'name' => '',
    'email' => '',
    'phone' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        // Sanitize input
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        $formData = ['name' => $name, 'email' => $email, 'phone' => $phone];
        
        // Validate name
        if (empty($name)) {
            $errors[] = 'Full name is required';
        } elseif (strlen($name) < 3) {
            $errors[] = 'Full name must be at least 3 characters';
        } elseif (strlen($name) > 255) {
            $errors[] = 'Full name must not exceed 255 characters';
        }
        
        // Validate email
        if (empty($email)) {
            $errors[] = 'Email address is required';
        } elseif (!validateEmail($email)) {
            $errors[] = 'Please enter a valid email address';
        }
        
        // Check if email already exists
        if (!empty($email) && validateEmail($email)) {
            $existingUser = $db->fetch(
                "SELECT id FROM users WHERE email = ?",
                [$email]
            );
            
            if ($existingUser) {
                $errors[] = 'This email address is already registered. Please login or use a different email.';
            }
        }
        
        // Validate phone
        if (empty($phone)) {
            $errors[] = 'Phone number is required';
        } elseif (!validatePhone($phone)) {
            $errors[] = 'Please enter a valid Ghana phone number (e.g., +233 24 000 0000 or 024 000 0000)';
        }
        
        // Validate password
        if (empty($password)) {
            $errors[] = 'Password is required';
        } else {
            $passwordErrors = validatePassword($password);
            if (!empty($passwordErrors)) {
                $errors = array_merge($errors, $passwordErrors);
            }
        }
        
        // Validate password confirmation
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match';
        }
        
        // If no errors, create account
        if (empty($errors)) {
            try {
                $passwordHash = hashPassword($password);
                
                $userId = $db->insert(
                    "INSERT INTO users (name, email, phone, password_hash, is_admin, is_active) 
                     VALUES (?, ?, ?, ?, 0, 1)",
                    [$name, $email, $phone, $passwordHash]
                );
                
                if ($userId) {
                    // Set session
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['is_admin'] = 0;
                    $_SESSION['last_activity'] = time();
                    
                    // Update last login
                    $db->update(
                        "UPDATE users SET last_login = NOW() WHERE id = ?",
                        [$userId]
                    );
                    
                    createLog('REGISTRATION_SUCCESS', 'New user registered', $userId);
                    setFlashMessage('success', 'Welcome to ' . APP_NAME . '! Your account has been created.');
                    
                    // Send welcome email
                    $subject = 'Welcome to ' . APP_NAME;
                    $body = "
                    <h2>Welcome to " . APP_NAME . "!</h2>
                    <p>Dear $name,</p>
                    <p>Thank you for registering with us. Your account has been created successfully.</p>
                    <p>You can now:</p>
                    <ul>
                        <li>Shop our premium eyewear collection</li>
                        <li>Use our AR Try-On feature</li>
                        <li>Book appointments with our specialists</li>
                        <li>View your order history</li>
                    </ul>
                    <p>Best regards,<br>" . APP_NAME . " Team</p>
                    ";
                    
                    sendEmail($email, $subject, $body);
                    
                    redirect(APP_URL . '/');
                } else {
                    $errors[] = 'Failed to create account. Please try again.';
                }
            } catch (Exception $e) {
                createLog('REGISTRATION_ERROR', 'Error creating user account: ' . $e->getMessage());
                $errors[] = 'An error occurred while creating your account. Please try again.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    
    <style>
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%);
            padding: 1rem;
        }
        
        .register-card {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .register-header {
            background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%);
            color: white;
            padding: 2rem 1.5rem;
            text-align: center;
        }
        
        .register-header h1 {
            font-size: 2rem;
            margin: 0;
            color: white;
        }
        
        .register-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        
        .register-body {
            padding: 2rem 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .password-input-group {
            position: relative;
        }
        
        .password-toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6B7280;
            cursor: pointer;
            padding: 0.5rem;
            font-size: 1rem;
            z-index: 10;
        }
        
        .password-toggle-btn:hover {
            color: #2563EB;
        }
        
        .password-strength-meter {
            height: 6px;
            background: #E5E7EB;
            border-radius: 3px;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        
        .password-strength-meter-fill {
            height: 100%;
            width: 0%;
            background: #EF4444;
            transition: all 0.3s;
        }
        
        .password-strength-meter-fill.fair {
            background: #F59E0B;
        }
        
        .password-strength-meter-fill.good {
            background: #06B6D4;
        }
        
        .password-strength-meter-fill.strong {
            background: #10B981;
        }
        
        .password-strength-text {
            font-size: 0.875rem;
            margin-top: 0.25rem;
            font-weight: 600;
        }
        
        .password-requirements {
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.875rem;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
            color: #6B7280;
        }
        
        .requirement.met {
            color: #10B981;
        }
        
        .requirement i {
            width: 20px;
            text-align: center;
        }
        
        .register-button {
            width: 100%;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            border: none;
            border-radius: 0.5rem;
            background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%);
            color: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .register-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.4);
        }
        
        .register-button:active {
            transform: translateY(0);
        }
        
        .register-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #E5E7EB;
        }
        
        .login-link p {
            margin: 0;
            color: #6B7280;
        }
        
        .login-link a {
            color: #2563EB;
            font-weight: 600;
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card fade-in">
            <!-- Header -->
            <div class="register-header">
                <h1><i class="fas fa-glasses"></i></h1>
                <h2 class="mt-2" style="font-size: 1.75rem; margin: 0.5rem 0 0 0;">Create Account</h2>
                <p>Join our eyewear community</p>
            </div>
            
            <!-- Body -->
            <div class="register-body">
                <!-- Errors -->
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Registration Failed</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <!-- Registration Form -->
                <form id="registerForm" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <!-- Full Name -->
                    <div class="form-group">
                        <label for="name" class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input 
                                type="text" 
                                id="name" 
                                name="name" 
                                class="form-control" 
                                placeholder="John Doe"
                                value="<?php echo sanitize($formData['name']); ?>"
                                required
                            >
                        </div>
                    </div>
                    
                    <!-- Email -->
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-control" 
                                placeholder="your@email.com"
                                value="<?php echo sanitize($formData['email']); ?>"
                                required
                                autocomplete="email"
                            >
                        </div>
                    </div>
                    
                    <!-- Phone -->
                    <div class="form-group">
                        <label for="phone" class="form-label">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-phone"></i>
                            </span>
                            <input 
                                type="tel" 
                                id="phone" 
                                name="phone" 
                                class="form-control" 
                                placeholder="+233 24 000 0000"
                                value="<?php echo sanitize($formData['phone']); ?>"
                                required
                                autocomplete="tel"
                                inputmode="tel"
                            >
                        </div>
                        <small class="text-muted">Use a Ghana number like +233 24 000 0000 or 024 000 0000.</small>
                    </div>
                    
                    <!-- Password -->
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group password-input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-control" 
                                placeholder="Enter a strong password"
                                required
                            >
                            <button type="button" class="password-toggle-btn" title="Show/Hide Password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        
                        <!-- Password Strength Meter -->
                        <div id="passwordStrengthMeter" class="password-strength-meter">
                            <div class="password-strength-meter-fill"></div>
                        </div>
                        <div id="passwordStrengthText" class="password-strength-text"></div>
                    </div>
                    
                    <!-- Confirm Password -->
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group password-input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="form-control" 
                                placeholder="Re-enter your password"
                                required
                            >
                            <button type="button" class="password-toggle-btn" title="Show/Hide Password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="register-button">
                        <i class="fas fa-user-plus me-2"></i> Create Account
                    </button>
                </form>
                
                <!-- Login Link -->
                <div class="login-link">
                    <p>Already have an account?</p>
                    <a href="<?php echo APP_URL; ?>/login.php">
                        Login here <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    
    <!-- Auth JS -->
    <script src="<?php echo APP_URL; ?>/assets/js/auth.js"></script>
    
    <script>
        // Initialize password strength checker
        document.addEventListener('DOMContentLoaded', function() {
            new PasswordStrengthChecker('password', 'passwordStrengthMeter', 'passwordStrengthText');
            
            // Password toggles
            document.querySelectorAll('.password-toggle-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const input = this.closest('.input-group').querySelector('input');
                    const isPassword = input.type === 'password';
                    input.type = isPassword ? 'text' : 'password';
                    this.querySelector('i').className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
                });
            });
        });
    </script>
</body>
</html>
