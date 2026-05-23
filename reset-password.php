<?php
/**
 * Bealet Website - Reset Password
 */

session_start();

require_once __DIR__ . '/inc/header.php';

$token = sanitize($_GET['token'] ?? '');
$errors = [];
$success = false;

if (empty($token)) {
    $errors[] = 'Invalid or expired reset token.';
} else {
    $user = $db->fetch(
        "SELECT id, email, name, reset_expires FROM users WHERE reset_token = ? AND reset_expires > NOW()",
        [$token]
    );
    if (!$user) {
        $errors[] = 'Invalid or expired reset token.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!validatePassword($password)) {
            $errors[] = 'Password must be at least 8 characters and include uppercase, number, and special character.';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        } else {
            $db->update(
                "UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL, updated_at = NOW() WHERE id = ?",
                [hashPassword($password), $user['id']]
            );
            createLog('PASSWORD_RESET', 'Password reset completed for user ID: ' . $user['id'], $user['id']);
            $success = true;
        }
    }
}
?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1>Reset Password</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item active">Reset Password</li>
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
                    Your password has been reset successfully. <a href="<?php echo APP_URL; ?>/login.php">Log in now</a>.
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Create a new password</h5>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
