<?php
/**
 * Bealet Website - Forgot Password
 */

session_start();

require_once __DIR__ . '/inc/header.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        if (empty($email) || !validateEmail($email)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $user = $db->fetch("SELECT id, name FROM users WHERE email = ?", [$email]);
            if ($user) {
                $token = bin2hex(random_bytes(16));
                $db->update(
                    "UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?",
                    [$token, $user['id']]
                );
                sendPasswordResetEmail($email, $token);
            }
            $success = true;
        }
    }
}
?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1>Forgot Password</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item active">Forgot Password</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    If that email address exists in our system, we have sent a password reset link.
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Reset your password</h5>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
                        </form>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <a href="<?php echo APP_URL; ?>/login.php">Back to Login</a>
                </div>
            </div>
        </div>
    </div>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
