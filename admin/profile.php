<?php
/**
 * Bealet Website - Admin Profile and Access Management
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(APP_URL . '/admin/login.php');
}

ensureAdminRoleColumn();

global $db;

$currentAdmin = getCurrentUser();
$currentAdminRole = getUserAdminRole($currentAdmin);
$isCurrentSuperAdmin = isSuperAdmin($currentAdmin);
$errors = [];

$profileFormData = [
    'name' => $currentAdmin['name'] ?? '',
    'email' => $currentAdmin['email'] ?? '',
    'phone' => $currentAdmin['phone'] ?? '',
];

$adminFormData = [
    'id' => 0,
    'name' => '',
    'email' => '',
    'phone' => '',
    'admin_role' => 'sub_admin',
    'is_active' => 1,
];

$editingAdminId = isset($_GET['edit_admin']) ? (int) $_GET['edit_admin'] : 0;

function countSuperAdmins() {
    global $db;

    $result = $db->fetch(
        "SELECT COUNT(*) AS total
         FROM users
         WHERE is_admin = 1
           AND COALESCE(NULLIF(admin_role, ''), 'super_admin') = 'super_admin'"
    );

    return (int) ($result['total'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh the page and try again.';
    } elseif (isset($_POST['update_profile'])) {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $password = $_POST['new_password'] ?? '';

        $profileFormData = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ];

        if ($name === '') {
            $errors[] = 'Your name is required.';
        }

        if (!validateEmail($email)) {
            $errors[] = 'Please provide a valid email address.';
        }

        if ($phone === '' || !validatePhone($phone)) {
            $errors[] = 'Please provide a valid Ghana phone number.';
        }

        $emailOwner = $db->fetch("SELECT id FROM users WHERE email = ? AND id <> ?", [$email, $currentAdmin['id']]);
        if ($emailOwner) {
            $errors[] = 'That email address is already in use.';
        }

        if ($password !== '') {
            $passwordErrors = validatePassword($password);
            $errors = array_merge($errors, $passwordErrors);
        }

        if (empty($errors)) {
            $params = [$name, $email, normalizePhoneNumber($phone)];
            $sql = "UPDATE users SET name = ?, email = ?, phone = ?";

            if ($password !== '') {
                $sql .= ", password_hash = ?";
                $params[] = hashPassword($password);
            }

            $sql .= " WHERE id = ?";
            $params[] = $currentAdmin['id'];

            $db->update($sql, $params);

            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;

            createLog('ADMIN_PROFILE_UPDATED', 'Admin updated their profile', $currentAdmin['id']);
            setFlashMessage('success', 'Your profile has been updated.');
            redirect(APP_URL . '/admin/profile.php');
        }
    } elseif (isset($_POST['save_admin_account'])) {
        if (!$isCurrentSuperAdmin) {
            $errors[] = 'Only a super admin can manage admin accounts.';
        } else {
            $adminId = (int) ($_POST['admin_id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $adminRole = sanitize($_POST['admin_role'] ?? 'sub_admin');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            $adminFormData = [
                'id' => $adminId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'admin_role' => in_array($adminRole, ['super_admin', 'sub_admin'], true) ? $adminRole : 'sub_admin',
                'is_active' => $isActive,
            ];

            if ($name === '') {
                $errors[] = 'Admin name is required.';
            }

            if (!validateEmail($email)) {
                $errors[] = 'A valid email address is required for the admin account.';
            }

            if ($phone === '' || !validatePhone($phone)) {
                $errors[] = 'Please provide a valid Ghana phone number for the admin account.';
            }

            $existingUser = $db->fetch("SELECT id FROM users WHERE email = ? AND id <> ?", [$email, $adminId]);
            if ($existingUser) {
                $errors[] = 'That email address already belongs to another account.';
            }

            if ($adminId === 0 || $password !== '') {
                $passwordErrors = validatePassword($password);
                $errors = array_merge($errors, $passwordErrors);
            }

            if ($adminId > 0) {
                $targetAdmin = $db->fetch("SELECT * FROM users WHERE id = ? AND is_admin = 1", [$adminId]);

                if (!$targetAdmin) {
                    $errors[] = 'The selected admin account could not be found.';
                } elseif ((int) $targetAdmin['id'] === (int) $currentAdmin['id'] && $adminFormData['admin_role'] !== 'super_admin') {
                    $errors[] = 'You cannot downgrade your own account while managing admins.';
                } elseif ((int) $targetAdmin['id'] === (int) $currentAdmin['id'] && $isActive !== 1) {
                    $errors[] = 'You cannot deactivate your own admin account.';
                } elseif (
                    getUserAdminRole($targetAdmin) === 'super_admin'
                    && $adminFormData['admin_role'] !== 'super_admin'
                    && countSuperAdmins() <= 1
                ) {
                    $errors[] = 'At least one super admin must remain active.';
                }
            }

            if (empty($errors)) {
                if ($adminId > 0) {
                    $params = [
                        $name,
                        $email,
                        normalizePhoneNumber($phone),
                        $adminFormData['admin_role'],
                        $isActive,
                    ];
                    $sql = "UPDATE users SET name = ?, email = ?, phone = ?, is_admin = 1, admin_role = ?, is_active = ?";

                    if ($password !== '') {
                        $sql .= ", password_hash = ?";
                        $params[] = hashPassword($password);
                    }

                    $sql .= " WHERE id = ?";
                    $params[] = $adminId;

                    $db->update($sql, $params);
                    createLog('ADMIN_ACCOUNT_UPDATED', 'Updated admin account #' . $adminId, $currentAdmin['id']);
                    setFlashMessage('success', 'Admin account updated successfully.');
                } else {
                    $newAdminId = $db->insert(
                        "INSERT INTO users (name, email, phone, password_hash, is_admin, admin_role, is_active)
                         VALUES (?, ?, ?, ?, 1, ?, ?)",
                        [
                            $name,
                            $email,
                            normalizePhoneNumber($phone),
                            hashPassword($password),
                            $adminFormData['admin_role'],
                            $isActive,
                        ]
                    );

                    createLog('ADMIN_ACCOUNT_CREATED', 'Created admin account #' . $newAdminId, $currentAdmin['id']);
                    setFlashMessage('success', 'New admin account created successfully.');
                }

                redirect(APP_URL . '/admin/profile.php');
            }
        }
    } elseif (isset($_POST['delete_admin_account'])) {
        if (!$isCurrentSuperAdmin) {
            $errors[] = 'Only a super admin can delete admin accounts.';
        } else {
            $adminId = (int) ($_POST['admin_id'] ?? 0);
            $targetAdmin = $db->fetch("SELECT * FROM users WHERE id = ? AND is_admin = 1", [$adminId]);

            if (!$targetAdmin) {
                $errors[] = 'The selected admin account could not be found.';
            } elseif ((int) $targetAdmin['id'] === (int) $currentAdmin['id']) {
                $errors[] = 'You cannot delete your own admin account.';
            } elseif (getUserAdminRole($targetAdmin) === 'super_admin' && countSuperAdmins() <= 1) {
                $errors[] = 'The last super admin account cannot be deleted.';
            } else {
                $db->delete("DELETE FROM users WHERE id = ? AND is_admin = 1", [$adminId]);
                createLog('ADMIN_ACCOUNT_DELETED', 'Deleted admin account #' . $adminId, $currentAdmin['id']);
                setFlashMessage('success', 'Admin account deleted successfully.');
                redirect(APP_URL . '/admin/profile.php');
            }
        }
    }
}

if ($isCurrentSuperAdmin && $editingAdminId > 0) {
    $editingAdmin = $db->fetch("SELECT * FROM users WHERE id = ? AND is_admin = 1", [$editingAdminId]);
    if ($editingAdmin) {
        $adminFormData = [
            'id' => (int) $editingAdmin['id'],
            'name' => $editingAdmin['name'],
            'email' => $editingAdmin['email'],
            'phone' => $editingAdmin['phone'],
            'admin_role' => getUserAdminRole($editingAdmin),
            'is_active' => (int) $editingAdmin['is_active'],
        ];
    }
}

$adminAccounts = $db->fetchAll(
    "SELECT id, name, email, phone, is_active, admin_role, created_at, last_login
     FROM users
     WHERE is_admin = 1
     ORDER BY
        CASE COALESCE(NULLIF(admin_role, ''), 'super_admin')
            WHEN 'super_admin' THEN 0
            ELSE 1
        END,
        created_at ASC"
);

$teamSummary = [
    'super_admins' => 0,
    'sub_admins' => 0,
    'active_admins' => 0,
];

foreach ($adminAccounts as $adminAccount) {
    $role = getUserAdminRole($adminAccount);
    if ($role === 'sub_admin') {
        $teamSummary['sub_admins']++;
    } else {
        $teamSummary['super_admins']++;
    }

    if (!empty($adminAccount['is_active'])) {
        $teamSummary['active_admins']++;
    }
}

require_once __DIR__ . '/inc/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="mb-1">Profile & Admin Access</h2>
            <p class="text-muted mb-0">Manage your profile and keep the admin team rights organized.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <span class="badge bg-primary"><?php echo $teamSummary['super_admins']; ?> Super Admins</span>
            <span class="badge bg-info text-dark"><?php echo $teamSummary['sub_admins']; ?> Sub Admins</span>
            <span class="badge bg-success"><?php echo $teamSummary['active_admins']; ?> Active</span>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Please fix the following:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo sanitize($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; font-size: 1.5rem;">
                            <?php echo strtoupper(substr($currentAdmin['name'] ?? 'A', 0, 1)); ?>
                        </div>
                        <div>
                            <h4 class="mb-1"><?php echo sanitize($currentAdmin['name'] ?? 'Admin'); ?></h4>
                            <div class="text-muted"><?php echo sanitize(getAdminRoleLabel($currentAdminRole)); ?></div>
                            <small class="text-muted">Last login: <?php echo !empty($currentAdmin['last_login']) ? formatDate($currentAdmin['last_login']) : 'First login pending'; ?></small>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="update_profile" value="1">

                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo sanitize($profileFormData['name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?php echo sanitize($profileFormData['email']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo sanitize($profileFormData['phone']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current password">
                            <small class="text-muted">Use at least 8 characters with uppercase, number, and symbol.</small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Save My Profile</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h4 class="mb-3">Access Levels</h4>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <h5 class="mb-2">Super Admin</h5>
                                <p class="text-muted mb-0">Can manage products, settings, staff, content, and create, edit, or delete other admin accounts.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <h5 class="mb-2">Sub Admin</h5>
                                <p class="text-muted mb-0">Can work on dashboard operations like orders, customers, appointments, and messages, but cannot change admin access or core site setup.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                        <div>
                            <h4 class="mb-1">Admin Team</h4>
                            <p class="text-muted mb-0">Keep the right people in control of the right parts of the store.</p>
                        </div>
                        <?php if ($isCurrentSuperAdmin): ?>
                            <a href="<?php echo APP_URL; ?>/admin/profile.php" class="btn btn-outline-secondary btn-sm">Reset Form</a>
                        <?php endif; ?>
                    </div>

                    <?php if ($isCurrentSuperAdmin): ?>
                        <form method="POST" class="border rounded-3 p-3 mb-4 bg-light">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="save_admin_account" value="1">
                            <input type="hidden" name="admin_id" value="<?php echo (int) $adminFormData['id']; ?>">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="name" class="form-control" value="<?php echo sanitize($adminFormData['name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo sanitize($adminFormData['email']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo sanitize($adminFormData['phone']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><?php echo $adminFormData['id'] > 0 ? 'Reset Password' : 'Password'; ?></label>
                                    <input type="password" name="password" class="form-control" <?php echo $adminFormData['id'] > 0 ? '' : 'required'; ?> placeholder="<?php echo $adminFormData['id'] > 0 ? 'Leave blank to keep current password' : 'Create a strong password'; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Access Level</label>
                                    <select name="admin_role" class="form-select">
                                        <option value="super_admin" <?php echo $adminFormData['admin_role'] === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                                        <option value="sub_admin" <?php echo $adminFormData['admin_role'] === 'sub_admin' ? 'selected' : ''; ?>>Sub Admin</option>
                                    </select>
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="adminIsActive" name="is_active" value="1" <?php echo !empty($adminFormData['is_active']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="adminIsActive">Account is active</label>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-3 flex-wrap">
                                <button type="submit" class="btn btn-primary">
                                    <?php echo $adminFormData['id'] > 0 ? 'Update Admin Account' : 'Create Admin Account'; ?>
                                </button>
                                <span class="text-muted small align-self-center">Create super admins with full control and sub admins with safer operational access.</span>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">Your account can update its own profile, but only a super admin can manage admin roles and access.</div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <?php if ($isCurrentSuperAdmin): ?>
                                        <th class="text-end">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($adminAccounts as $adminAccount): ?>
                                    <?php $teamRole = getUserAdminRole($adminAccount); ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo sanitize($adminAccount['name']); ?></strong>
                                            <div class="text-muted small"><?php echo sanitize($adminAccount['email']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $teamRole === 'super_admin' ? 'bg-primary' : 'bg-secondary'; ?>">
                                                <?php echo sanitize(getAdminRoleLabel($teamRole)); ?>
                                            </span>
                                        </td>
                                        <td><?php echo sanitize($adminAccount['phone']); ?></td>
                                        <td>
                                            <span class="badge <?php echo !empty($adminAccount['is_active']) ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                                <?php echo !empty($adminAccount['is_active']) ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo !empty($adminAccount['last_login']) ? formatDate($adminAccount['last_login']) : 'No login yet'; ?></td>
                                        <?php if ($isCurrentSuperAdmin): ?>
                                            <td class="text-end">
                                                <div class="d-flex justify-content-end gap-2 flex-wrap">
                                                    <a href="<?php echo APP_URL; ?>/admin/profile.php?edit_admin=<?php echo (int) $adminAccount['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                    <?php if ((int) $adminAccount['id'] !== (int) $currentAdmin['id']): ?>
                                                        <form method="POST" onsubmit="return confirmDelete('Delete this admin account permanently?');">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="delete_admin_account" value="1">
                                                            <input type="hidden" name="admin_id" value="<?php echo (int) $adminAccount['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
