<?php
/**
 * Bealet Website - Login Page
 */

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

// Check if already logged in
if (isLoggedIn()) {
    redirect(APP_URL . '/');
}

$errors = [];
$email = '';
$rememberMe = false;
$loginWallpaperUrl = getLoginWallpaperUrl();
$googleClientId = getGoogleClientId();
$styleCssVersion = @filemtime(__DIR__ . '/assets/css/style.css') ?: time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);
        
        // Validate input
        if (empty($email)) {
            $errors[] = 'Email is required';
        } elseif (!validateEmail($email)) {
            $errors[] = 'Please enter a valid email address';
        }
        
        if (empty($password)) {
            $errors[] = 'Password is required';
        }
        
        // If validation passed, check credentials
        if (empty($errors)) {
            $user = $db->fetch(
                "SELECT id, name, email, password_hash, is_admin, is_active, login_attempts, locked_until FROM users WHERE email = ?",
                [$email]
            );
            
            if (!$user) {
                createLog('LOGIN_FAILED', 'Email not found: ' . $email);
                $errors[] = 'Invalid email or password';
            } else {
                // Check if account is active
                if (!$user['is_active']) {
                    $errors[] = 'Your account has been deactivated. Please contact support.';
                }
                // Check if account is locked
                elseif (isUserLockedOut($user['id'])) {
                    $errors[] = 'Your account is temporarily locked. Please try again later.';
                }
                // Verify password
                elseif (verifyPassword($password, $user['password_hash'])) {
                    // Reset login attempts
                    resetLoginAttempts($user['id']);
                    
                    signInUser($user, $rememberMe);
                    
                    // Update last login
                    $db->update(
                        "UPDATE users SET last_login = NOW() WHERE id = ?",
                        [$user['id']]
                    );
                    
                    createLog('LOGIN_SUCCESS', 'User logged in', $user['id']);
                    setFlashMessage('success', 'Welcome back, ' . $user['name'] . '!');
                    
                    // Redirect to dashboard or home
                    if ($user['is_admin']) {
                        redirect(APP_URL . '/admin/');
                    } else {
                        redirect(APP_URL . '/');
                    }
                } else {
                    // Increment login attempts
                    incrementLoginAttempts($user['id']);
                    createLog('LOGIN_FAILED', 'Invalid password for: ' . $email);
                    $errors[] = 'Invalid email or password';
                }
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
    <title>Login - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css?v=<?php echo $styleCssVersion; ?>">
    
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image:
                linear-gradient(135deg, rgba(15, 23, 42, 0.76), rgba(37, 99, 235, 0.52)),
                url('<?php echo sanitize($loginWallpaperUrl); ?>');
            background-size: cover;
            background-position: center;
            padding: 1rem;
        }
        
        .login-card {
            width: 100%;
            max-width: 460px;
            background: rgba(255, 255, 255, 0.96);
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%);
            color: white;
            padding: 2rem 1.5rem;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 2rem;
            margin: 0;
            color: white;
        }
        
        .login-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        
        .login-body {
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
        
        .remember-me {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .forgot-password {
            color: #2563EB;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .forgot-password:hover {
            text-decoration: underline;
        }
        
        .login-button {
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
        
        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.4);
        }
        
        .login-button:active {
            transform: translateY(0);
        }
        
        .login-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .signup-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #E5E7EB;
        }
        
        .signup-link p {
            margin: 0;
            color: #6B7280;
        }
        
        .signup-link a {
            color: #2563EB;
            font-weight: 600;
            text-decoration: none;
        }
        
        .signup-link a:hover {
            text-decoration: underline;
        }
        
        .guest-checkout {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #E5E7EB;
            text-align: center;
        }
        
        .guest-checkout p {
            color: #6B7280;
            font-size: 0.875rem;
            margin-bottom: 0.75rem;
        }
        
        .guest-checkout a {
            color: #2563EB;
            font-weight: 600;
            text-decoration: none;
        }
        
        .alert {
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
        }

        .social-login-divider {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            color: #64748B;
            font-size: 0.875rem;
            margin: 1.25rem 0;
        }

        .social-login-divider::before,
        .social-login-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #E2E8F0;
        }

        .google-login-shell {
            min-height: 48px;
        }

        .google-login-help {
            color: #64748B;
            font-size: 0.82rem;
            margin-top: 0.65rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <script>window.BASE_URL = '<?php echo APP_URL; ?>';</script>
    <div class="login-container">
        <div class="login-card fade-in">
            <!-- Header -->
            <div class="login-header">
                <h1><i class="fas fa-glasses"></i></h1>
                <h2 class="mt-2" style="font-size: 1.75rem; margin: 0.5rem 0 0 0;">Welcome Back</h2>
                <p>Login to your account</p>
            </div>
            
            <!-- Body -->
            <div class="login-body">
                <!-- Errors -->
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Login Failed</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <!-- Login Form -->
                <form id="loginForm" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
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
                                value="<?php echo sanitize($email); ?>"
                                required
                                autocomplete="email"
                            >
                        </div>
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
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                            >
                            <button type="button" class="password-toggle-btn" title="Show/Hide Password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Remember Me & Forgot Password -->
                    <div class="remember-me">
                        <label class="form-check-label" style="margin: 0;">
                            <input 
                                type="checkbox" 
                                name="remember_me" 
                                class="form-check-input"
                                <?php echo $rememberMe ? 'checked' : ''; ?>
                            >
                            Remember me for 30 days
                        </label>
                        <a href="<?php echo APP_URL; ?>/forgot-password.php" class="forgot-password">
                            Forgot password?
                        </a>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="login-button">
                        <i class="fas fa-sign-in-alt me-2"></i> Login to Account
                    </button>
                </form>

                <?php if ($googleClientId !== ''): ?>
                <div class="social-login-divider"><span>or continue with</span></div>
                <div class="google-login-shell d-flex justify-content-center">
                    <div id="googleLoginButton"></div>
                </div>
                <p class="google-login-help">Use your Google account to sign in instantly. New customers will get an account automatically.</p>
                <?php endif; ?>
                
                <!-- Sign Up Link -->
                <div class="signup-link">
                    <p>Don't have an account yet?</p>
                    <a href="<?php echo APP_URL; ?>/register.php">
                        Create an account <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
                
                <!-- Guest Checkout -->
                <div class="guest-checkout">
                    <p>Want to shop without an account?</p>
                    <a href="<?php echo APP_URL; ?>/shop.php">Continue as Guest</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <?php if ($googleClientId !== ''): ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <?php endif; ?>
    
    <!-- Auth JS -->
    <script src="<?php echo APP_URL; ?>/assets/js/auth.js"></script>
    
    <script>
        // Password toggle for this page
        document.addEventListener('DOMContentLoaded', function() {
            const passwordToggle = document.querySelector('.password-toggle-btn');
            const passwordInput = document.getElementById('password');
            
            if (passwordToggle && passwordInput) {
                passwordToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const isPassword = passwordInput.type === 'password';
                    passwordInput.type = isPassword ? 'text' : 'password';
                    this.querySelector('i').className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
                });
            }
        });

        <?php if ($googleClientId !== ''): ?>
        async function handleGoogleCredentialResponse(response) {
            if (!response || !response.credential) {
                return;
            }

            try {
                const loginResponse = await fetch(`${window.BASE_URL}/api/google-login`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        credential: response.credential
                    })
                });

                const data = await loginResponse.json();
                if (data.success) {
                    window.location.href = data.redirect || `${window.BASE_URL}/`;
                    return;
                }

                alert(data.message || 'Google login failed. Please try again.');
            } catch (error) {
                console.error('Google login failed:', error);
                alert('Google login failed. Please try again.');
            }
        }

        window.addEventListener('load', function () {
            if (!window.google || !google.accounts || !google.accounts.id) {
                return;
            }

            google.accounts.id.initialize({
                client_id: '<?php echo sanitize($googleClientId); ?>',
                callback: handleGoogleCredentialResponse,
                auto_select: false,
                cancel_on_tap_outside: true
            });

            google.accounts.id.renderButton(
                document.getElementById('googleLoginButton'),
                {
                    theme: 'outline',
                    size: 'large',
                    width: 360,
                    shape: 'pill',
                    text: 'continue_with'
                }
            );
        });
        <?php endif; ?>
    </script>
</body>
</html>
