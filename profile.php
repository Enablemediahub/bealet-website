<?php
/**
 * Bealet Website - User Profile Management
 */

session_start();

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

// Check login
if (!isLoggedIn()) {
    redirect(APP_URL . '/login.php');
}

require_once __DIR__ . '/inc/header.php';

global $db;

ensureUserProfileImageColumn();

$errors = [];
$success = false;
$user = getCurrentUser();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $profileImagePath = (string) ($user['profile_image'] ?? '');
        
        if (strlen($name) < 3) {
            $errors[] = 'Name must be at least 3 characters';
        } elseif (!validatePhone($phone)) {
            $errors[] = 'Please enter a valid Ghana phone number';
        } else {
            $profileImageError = (int) ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($profileImageError === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['profile_image'], 'profiles');
                if (!empty($upload['success'])) {
                    $profileImagePath = 'assets/uploads/profiles/' . $upload['filename'];
                } else {
                    foreach (($upload['errors'] ?? ['Profile image upload failed.']) as $uploadError) {
                        $errors[] = $uploadError;
                    }
                }
            } elseif ($profileImageError !== UPLOAD_ERR_NO_FILE) {
                $errors[] = 'Profile image upload failed. Please try again.';
            }
        }

        if (empty($errors)) {
            $db->update(
                "UPDATE users SET name = ?, phone = ?, profile_image = ?, updated_at = NOW() WHERE id = ?",
                [$name, $phone, $profileImagePath !== '' ? $profileImagePath : null, $user['id']]
            );
            $success = true;
            setFlashMessage('success', 'Profile updated successfully');
        }
    }
}

// Refresh user data
if ($success) {
    $user = getCurrentUser();
}

$profileImageUrl = getUserProfileImageUrl($user['profile_image'] ?? '', $user['name'] ?? 'Customer');

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword)) {
            $errors[] = 'Current password is required';
        } elseif (!verifyPassword($currentPassword, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect';
        } elseif (!validatePassword($newPassword)) {
            $errors[] = 'New password does not meet requirements';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match';
        } else {
            $db->update(
                "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?",
                [hashPassword($newPassword), $user['id']]
            );
            setFlashMessage('success', 'Password changed successfully');
            createLog('PASSWORD_CHANGED', 'User changed password', $user['id']);
        }
    }
}

?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1>My Profile</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item active">Profile</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Profile Content -->
    <div class="container my-5">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <img src="<?php echo sanitize($profileImageUrl); ?>" alt="Avatar" class="rounded-circle mb-3" width="100" height="100" style="object-fit: cover;">
                        <h5><?php echo sanitize($user['name']); ?></h5>
                        <p class="text-muted mb-0"><?php echo sanitize($user['email']); ?></p>
                        <p class="text-muted small">Member since <?php echo formatDate($user['created_at'], 'long'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-9">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                    <div><?php echo sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Update Profile Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="name" value="<?php echo sanitize($user['name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" value="<?php echo sanitize($user['email']); ?>" disabled>
                                <small class="text-muted">Email cannot be changed</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone" value="<?php echo sanitize($user['phone']); ?>" placeholder="+233 24 000 0000" inputmode="tel" required>
                                <small class="text-muted">Use a Ghana number like +233 24 000 0000 or 024 000 0000.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Profile Image</label>
                                <input type="file" class="form-control" name="profile_image" accept=".jpg,.jpeg,.png,.gif,.webp">
                                <small class="text-muted">Upload a small portrait photo. This can also be reused on your public review.</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                </div>
                
                <!-- Change Password Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="change_password" value="1">
                            
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" required>
                                <small class="text-muted">Min 8 chars, uppercase, number, special character</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
