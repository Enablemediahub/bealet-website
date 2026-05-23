<?php
/**
 * Bealet Website - Admin Staff Management
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(APP_URL . '/admin/login.php');
}

requireSuperAdmin();

global $db;

ensureStaffMembersTable();
$branches = getCompanyBranches();

$errors = [];
$mode = isset($_GET['mode']) ? (string) $_GET['mode'] : 'list';
$staffId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$staffRecord = null;
$formData = [
    'name' => '',
    'designation' => '',
    'branch_id' => '',
    'email' => '',
    'contact' => '',
    'bio' => '',
    'thumbnail' => '',
    'sort_order' => 0,
    'is_active' => 1,
];

if (isset($_GET['delete'])) {
    $staffId = (int) $_GET['delete'];
    $staffRecord = $db->fetch("SELECT thumbnail, name FROM staff_members WHERE id = ?", [$staffId]);

    if ($staffRecord) {
        $thumbnailPath = getStaffImageLocalPath($staffRecord['thumbnail'] ?? '');
        if ($thumbnailPath && is_file($thumbnailPath)) {
            unlink($thumbnailPath);
        }

        $db->delete("DELETE FROM staff_members WHERE id = ?", [$staffId]);
        createLog('STAFF_MEMBER_DELETED', "Staff member #{$staffId} deleted");
        setFlashMessage('success', 'Staff member deleted successfully.');
    }

    redirect(APP_URL . '/admin/staff.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $staffId = isset($_POST['staff_id']) ? (int) $_POST['staff_id'] : 0;
        $name = sanitize($_POST['name'] ?? '');
        $designation = sanitize($_POST['designation'] ?? '');
        $branchId = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int) $_POST['branch_id'] : null;
        $email = sanitize($_POST['email'] ?? '');
        $contact = sanitize($_POST['contact'] ?? '');
        $bio = sanitize($_POST['bio'] ?? '');
        $sortOrder = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $formData = [
            'name' => $name,
            'designation' => $designation,
            'branch_id' => $branchId,
            'email' => $email,
            'contact' => $contact,
            'bio' => $bio,
            'thumbnail' => '',
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ];

        if ($name === '') {
            $errors[] = 'Name is required.';
        }

        if ($designation === '') {
            $errors[] = 'Designation is required.';
        }

        if ($branchId !== null) {
            $branchExists = false;
            foreach ($branches as $branch) {
                if ((int) ($branch['id'] ?? 0) === $branchId) {
                    $branchExists = true;
                    break;
                }
            }

            if (!$branchExists) {
                $errors[] = 'Please select a valid branch.';
            }
        }

        if ($email !== '' && !validateEmail($email)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($email === '' && $contact === '') {
            $errors[] = 'Add at least an email or contact number.';
        }

        $thumbnail = '';
        if (isset($_FILES['thumbnail']) && (int) ($_FILES['thumbnail']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $uploadResult = uploadFile($_FILES['thumbnail'], 'staff');
            if (!empty($uploadResult['success'])) {
                $thumbnail = $uploadResult['filename'];
            } else {
                foreach (($uploadResult['errors'] ?? ['Thumbnail upload failed.']) as $uploadError) {
                    $errors[] = $uploadError;
                }
            }
        }

        if (empty($errors)) {
            if ($staffId > 0) {
                $existingRecord = $db->fetch("SELECT thumbnail FROM staff_members WHERE id = ?", [$staffId]);
                if (!$existingRecord) {
                    $errors[] = 'Staff member not found.';
                } else {
                    if ($thumbnail === '') {
                        $thumbnail = (string) ($existingRecord['thumbnail'] ?? '');
                    } elseif (!empty($existingRecord['thumbnail'])) {
                        $oldImagePath = getStaffImageLocalPath($existingRecord['thumbnail']);
                        if ($oldImagePath && is_file($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                }
            }
        }

        if (empty($errors)) {
            if ($staffId > 0) {
                $db->update(
                    "UPDATE staff_members
                     SET name = ?, designation = ?, branch_id = ?, email = ?, contact = ?, bio = ?, thumbnail = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                     WHERE id = ?",
                    [$name, $designation, $branchId, $email !== '' ? $email : null, $contact !== '' ? $contact : null, $bio !== '' ? $bio : null, $thumbnail !== '' ? $thumbnail : null, $sortOrder, $isActive, $staffId]
                );
                createLog('STAFF_MEMBER_UPDATED', "Staff member #{$staffId} updated");
                setFlashMessage('success', 'Staff member updated successfully.');
            } else {
                $db->insert(
                    "INSERT INTO staff_members (name, designation, branch_id, email, contact, bio, thumbnail, sort_order, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$name, $designation, $branchId, $email !== '' ? $email : null, $contact !== '' ? $contact : null, $bio !== '' ? $bio : null, $thumbnail !== '' ? $thumbnail : null, $sortOrder, $isActive]
                );
                createLog('STAFF_MEMBER_CREATED', "Staff member created: {$name}");
                setFlashMessage('success', 'Staff member added successfully.');
            }

            redirect(APP_URL . '/admin/staff.php');
        }
    }
}

require_once __DIR__ . '/inc/header.php';

$staffMembers = [];
if ($mode === 'list') {
    $staffMembers = $db->fetchAll(
        "SELECT sm.*, cb.branch_name
         FROM staff_members sm
         LEFT JOIN company_branches cb ON sm.branch_id = cb.id
         ORDER BY sm.sort_order ASC, sm.id ASC"
    );
} elseif ($mode === 'edit' && $staffId > 0) {
    $staffRecord = $db->fetch("SELECT * FROM staff_members WHERE id = ?", [$staffId]);

    if ($staffRecord) {
        $formData = [
            'name' => $staffRecord['name'] ?? '',
            'designation' => $staffRecord['designation'] ?? '',
            'branch_id' => isset($staffRecord['branch_id']) ? (int) $staffRecord['branch_id'] : '',
            'email' => $staffRecord['email'] ?? '',
            'contact' => $staffRecord['contact'] ?? '',
            'bio' => $staffRecord['bio'] ?? '',
            'thumbnail' => $staffRecord['thumbnail'] ?? '',
            'sort_order' => (int) ($staffRecord['sort_order'] ?? 0),
            'is_active' => (int) ($staffRecord['is_active'] ?? 1),
        ];
    } else {
        $mode = 'list';
        $errors[] = 'Staff member not found.';
        $staffMembers = $db->fetchAll(
            "SELECT sm.*, cb.branch_name
             FROM staff_members sm
             LEFT JOIN company_branches cb ON sm.branch_id = cb.id
             ORDER BY sm.sort_order ASC, sm.id ASC"
        );
    }
}
?>

<div class="container-fluid mt-4 mb-5">
    <?php if ($mode === 'list'): ?>
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="mb-1">Staff Management</h2>
            <p class="text-muted mb-0">Manage the staff cards shown on the public staff page.</p>
        </div>
        <a href="<?php echo APP_URL; ?>/admin/staff.php?mode=create" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i> Add Staff Member
        </a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
        <div><?php echo sanitize($error); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Designation</th>
                        <th>Branch</th>
                        <th>Contact</th>
                        <th>Visibility</th>
                        <th>Order</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($staffMembers)): ?>
                        <?php foreach ($staffMembers as $member): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img
                                        src="<?php echo getStaffImageUrl($member['thumbnail'] ?? '', $member['name'] ?? 'Staff'); ?>"
                                        alt="<?php echo sanitize($member['name'] ?? 'Staff'); ?>"
                                        class="admin-staff-thumb"
                                    >
                                    <div>
                                        <strong><?php echo sanitize($member['name'] ?? ''); ?></strong>
                                        <div class="text-muted small"><?php echo sanitize($member['email'] ?: ($member['contact'] ?: 'No contact added')); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo sanitize($member['designation'] ?? ''); ?></td>
                            <td><?php echo sanitize($member['branch_name'] ?? 'All branches'); ?></td>
                            <td>
                                <?php if (!empty($member['email'])): ?>
                                <div><?php echo sanitize($member['email']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($member['contact'])): ?>
                                <div><?php echo sanitize($member['contact']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo (int) ($member['is_active'] ?? 0) === 1 ? 'success' : 'secondary'; ?>">
                                    <?php echo (int) ($member['is_active'] ?? 0) === 1 ? 'Visible' : 'Hidden'; ?>
                                </span>
                            </td>
                            <td><?php echo (int) ($member['sort_order'] ?? 0); ?></td>
                            <td>
                                <a href="<?php echo APP_URL; ?>/admin/staff.php?mode=edit&id=<?php echo (int) $member['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <a href="<?php echo APP_URL; ?>/admin/staff.php?delete=<?php echo (int) $member['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirmDelete('Delete this staff member?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fas fa-id-badge d-block mb-3" style="font-size: 2rem;"></i>
                            No staff members yet. <a href="<?php echo APP_URL; ?>/admin/staff.php?mode=create">Add the first staff profile</a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo $staffId > 0 ? 'Edit Staff Member' : 'Add Staff Member'; ?></h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger mb-3">
                        <?php foreach ($errors as $error): ?>
                        <div><?php echo sanitize($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="staff_id" value="<?php echo (int) $staffId; ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" value="<?php echo sanitize($formData['name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Designation</label>
                                <input type="text" class="form-control" name="designation" value="<?php echo sanitize($formData['designation']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Branch</label>
                                <select class="form-select" name="branch_id">
                                    <option value="">All branches / not specified</option>
                                    <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo (int) $branch['id']; ?>" <?php echo (string) $formData['branch_id'] === (string) $branch['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($branch['branch_name'] ?? 'Branch'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo sanitize($formData['email']); ?>" placeholder="staff@example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Number</label>
                                <input type="text" class="form-control" name="contact" value="<?php echo sanitize($formData['contact']); ?>" placeholder="+233 000 000 000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Display Order</label>
                                <input type="number" class="form-control" name="sort_order" value="<?php echo (int) $formData['sort_order']; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Thumbnail</label>
                                <input type="file" class="form-control" name="thumbnail" accept="image/*">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Short Bio</label>
                                <textarea class="form-control" name="bio" rows="4" placeholder="Optional short intro for this staff member"><?php echo sanitize($formData['bio']); ?></textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="isActive" name="is_active" <?php echo (int) $formData['is_active'] === 1 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="isActive">Show this staff member on the website</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i><?php echo $staffId > 0 ? 'Update Staff Member' : 'Save Staff Member'; ?>
                            </button>
                            <a href="<?php echo APP_URL; ?>/admin/staff.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Preview</h5>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <img
                            src="<?php echo getStaffImageUrl($formData['thumbnail'] ?? '', $formData['name'] ?: 'Staff'); ?>"
                            alt="<?php echo sanitize($formData['name'] ?: 'Staff'); ?>"
                            class="admin-staff-preview mb-3"
                        >
                        <h5 class="mb-1"><?php echo sanitize($formData['name'] ?: 'Staff name'); ?></h5>
                        <p class="text-primary fw-semibold mb-3"><?php echo sanitize($formData['designation'] ?: 'Designation'); ?></p>
                        <?php if (!empty($formData['branch_id'])): ?>
                        <?php
                            $previewBranchName = '';
                            foreach ($branches as $branch) {
                                if ((string) ($branch['id'] ?? '') === (string) $formData['branch_id']) {
                                    $previewBranchName = (string) ($branch['branch_name'] ?? '');
                                    break;
                                }
                            }
                        ?>
                        <?php if ($previewBranchName !== ''): ?>
                        <p class="mb-2"><i class="fas fa-location-dot me-2 text-primary"></i><?php echo sanitize($previewBranchName); ?></p>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($formData['email'])): ?>
                        <p class="mb-2"><i class="fas fa-envelope me-2 text-primary"></i><?php echo sanitize($formData['email']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($formData['contact'])): ?>
                        <p class="mb-2"><i class="fas fa-phone me-2 text-primary"></i><?php echo sanitize($formData['contact']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($formData['bio'])): ?>
                        <p class="text-muted mb-0"><?php echo sanitize($formData['bio']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
