<?php
/**
 * Bealet Website - Admin Login
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

// Redirect if already logged in as admin
if (isLoggedIn() && isAdmin()) {
    redirect(APP_URL . '/admin/dashboard.php');
}

$errors = [];
$styleCssVersion = @filemtime(__DIR__ . '/../assets/css/style.css') ?: time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email)) {
            $errors[] = 'Email is required';
        }
        
        if (empty($password)) {
            $errors[] = 'Password is required';
        }
        
        if (empty($errors)) {
            $user = $db->fetch(
                "SELECT * FROM users WHERE email = ? AND is_admin = 1",
                [$email]
            );
            
            if (!$user) {
                createLog('ADMIN_LOGIN_FAILED', 'User not found or not admin: ' . $email);
                $errors[] = 'Invalid credentials';
            } else {
                if (!$user['is_active']) {
                    $errors[] = 'Your account has been deactivated.';
                } elseif (isUserLockedOut($user['id'])) {
                    $errors[] = 'Your account is temporarily locked. Please try again later.';
                } elseif (verifyPassword($password, $user['password_hash'])) {
                    resetLoginAttempts($user['id']);
                    
                    signInUser($user, false);
                    
                    $db->update(
                        "UPDATE users SET last_login = NOW() WHERE id = ?",
                        [$user['id']]
                    );
                    
                    createLog('ADMIN_LOGIN_SUCCESS', 'Admin logged in', $user['id']);
                    setFlashMessage('success', 'Welcome to Admin Dashboard!');
                    redirect(APP_URL . '/admin/dashboard.php');
                } else {
                    incrementLoginAttempts($user['id']);
                    createLog('ADMIN_LOGIN_FAILED', 'Invalid password for: ' . $email);
                    $errors[] = 'Invalid credentials';
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
    <title>Admin Login - <?php echo APP_NAME; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css?v=<?php echo $styleCssVersion; ?>">
    
    <style>
        .admin-login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%);
            padding: 1rem;
        }
        
        .admin-login-card {
            width: 100%;
            max-width: 420px;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .admin-login-header {
            background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%);
            color: white;
            padding: 2rem 1.5rem;
            text-align: center;
        }
        
        .admin-login-header h1 {
            font-size: 1.75rem;
            margin: 0;
            color: white;
        }
        
        .admin-login-body {
            padding: 2rem 1.5rem;
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <div class="admin-login-card fade-in">
            <!-- Header -->
            <div class="admin-login-header">
                <h1><i class="fas fa-lock"></i></h1>
                <h2 class="mt-2" style="font-size: 1.5rem; margin: 0.5rem 0 0 0;">Admin Portal</h2>
                <p>Secure Login</p>
            </div>
            
            <!-- Body -->
            <div class="admin-login-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Login Failed</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-group mb-3">
                        <label for="email" class="form-label">Admin Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="this.previousElementSibling.type = this.previousElementSibling.type === 'password' ? 'text' : 'password'">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i> Login
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
