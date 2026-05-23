<?php
/**
 * Bealet Website - Founder Story and Museum Management
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(APP_URL . '/admin/login.php');
}

requireSuperAdmin();

global $db;

ensureFounderGalleryTable();

try {
    $db->execute(
        "CREATE TABLE IF NOT EXISTS site_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
} catch (Throwable $e) {
    // Keep page available; save will surface errors if needed.
}

function getFounderMediaLocalPath($imagePath) {
    $imagePath = trim((string) $imagePath);
    if ($imagePath === '' || filter_var($imagePath, FILTER_VALIDATE_URL)) {
        return null;
    }

    $normalized = ltrim(str_replace('\\', '/', $imagePath), '/');
    $candidates = [
        __DIR__ . '/../' . $normalized,
        __DIR__ . '/../assets/uploads/branding/' . basename($normalized),
        __DIR__ . '/../assets/images/' . basename($normalized),
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

$errors = [];
$galleryErrors = [];
$editingGalleryId = isset($_GET['edit_gallery']) ? (int) $_GET['edit_gallery'] : 0;

$profileForm = [
    'founder_name' => (string) getSiteSetting('founder_name', ''),
    'founder_role' => (string) getSiteSetting('founder_role', ''),
    'founder_short_bio' => (string) getSiteSetting('founder_short_bio', ''),
    'founder_story' => (string) getSiteSetting('founder_story', ''),
    'founder_quote' => (string) getSiteSetting('founder_quote', ''),
    'founder_thumbnail' => (string) getSiteSetting('founder_thumbnail', ''),
    'founder_hero_image' => (string) getSiteSetting('founder_hero_image', ''),
];

$galleryForm = [
    'id' => 0,
    'item_title' => '',
    'item_type' => 'portrait',
    'item_description' => '',
    'sort_order' => 0,
    'is_active' => 1,
    'image_path' => '',
];

if (isset($_GET['delete_gallery'])) {
    $galleryId = (int) $_GET['delete_gallery'];
    $galleryItem = $db->fetch("SELECT image_path FROM founder_gallery WHERE id = ?", [$galleryId]);

    if ($galleryItem) {
        $localPath = getFounderMediaLocalPath($galleryItem['image_path'] ?? '');
        if ($localPath && is_file($localPath)) {
            unlink($localPath);
        }

        $db->delete("DELETE FROM founder_gallery WHERE id = ?", [$galleryId]);
        createLog('FOUNDER_GALLERY_DELETED', 'Founder gallery item deleted #' . $galleryId);
        setFlashMessage('success', 'Museum item deleted successfully.');
    }

    redirect(APP_URL . '/admin/founder.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    } else {
        $action = sanitize($_POST['action'] ?? '');

        if ($action === 'save_founder_profile') {
            $profileForm = [
                'founder_name' => sanitize($_POST['founder_name'] ?? ''),
                'founder_role' => sanitize($_POST['founder_role'] ?? ''),
                'founder_short_bio' => sanitize($_POST['founder_short_bio'] ?? ''),
                'founder_story' => sanitize($_POST['founder_story'] ?? ''),
                'founder_quote' => sanitize($_POST['founder_quote'] ?? ''),
                'founder_thumbnail' => sanitize($_POST['existing_founder_thumbnail'] ?? ''),
                'founder_hero_image' => sanitize($_POST['existing_founder_hero_image'] ?? ''),
            ];

            if ($profileForm['founder_name'] === '') {
                $errors[] = 'Founder name is required.';
            }

            if ($profileForm['founder_role'] === '') {
                $errors[] = 'Founder role or title is required.';
            }

            if ($profileForm['founder_short_bio'] === '') {
                $errors[] = 'Add a short homepage introduction for the founder.';
            }

            if ($profileForm['founder_story'] === '') {
                $errors[] = 'Add the dedicated founder story for the museum page.';
            }

            if (isset($_FILES['founder_thumbnail']) && ($_FILES['founder_thumbnail']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['founder_thumbnail'], 'branding');
                if (!empty($upload['success'])) {
                    $oldPath = getFounderMediaLocalPath($profileForm['founder_thumbnail']);
                    if ($oldPath && is_file($oldPath)) {
                        unlink($oldPath);
                    }
                    $profileForm['founder_thumbnail'] = 'assets/uploads/branding/' . $upload['filename'];
                } else {
                    foreach (($upload['errors'] ?? ['Founder thumbnail upload failed.']) as $uploadError) {
                        $errors[] = $uploadError;
                    }
                }
            } elseif (isset($_POST['remove_founder_thumbnail'])) {
                $oldPath = getFounderMediaLocalPath($profileForm['founder_thumbnail']);
                if ($oldPath && is_file($oldPath)) {
                    unlink($oldPath);
                }
                $profileForm['founder_thumbnail'] = '';
            }

            if (isset($_FILES['founder_hero_image']) && ($_FILES['founder_hero_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['founder_hero_image'], 'branding');
                if (!empty($upload['success'])) {
                    $oldPath = getFounderMediaLocalPath($profileForm['founder_hero_image']);
                    if ($oldPath && is_file($oldPath)) {
                        unlink($oldPath);
                    }
                    $profileForm['founder_hero_image'] = 'assets/uploads/branding/' . $upload['filename'];
                } else {
                    foreach (($upload['errors'] ?? ['Founder hero image upload failed.']) as $uploadError) {
                        $errors[] = $uploadError;
                    }
                }
            } elseif (isset($_POST['remove_founder_hero_image'])) {
                $oldPath = getFounderMediaLocalPath($profileForm['founder_hero_image']);
                if ($oldPath && is_file($oldPath)) {
                    unlink($oldPath);
                }
                $profileForm['founder_hero_image'] = '';
            }

            if (empty($errors)) {
                foreach ($profileForm as $key => $value) {
                    $db->insert(
                        "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP",
                        [$key, $value]
                    );
                }

                resetSiteSettingsCache();
                createLog('FOUNDER_PROFILE_UPDATED', 'Founder profile updated');
                setFlashMessage('success', 'Founder profile updated successfully.');
                redirect(APP_URL . '/admin/founder.php');
            }
        }

        if ($action === 'save_gallery_item') {
            $galleryForm = [
                'id' => (int) ($_POST['gallery_id'] ?? 0),
                'item_title' => sanitize($_POST['item_title'] ?? ''),
                'item_type' => sanitize($_POST['item_type'] ?? 'portrait'),
                'item_description' => sanitize($_POST['item_description'] ?? ''),
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'image_path' => sanitize($_POST['existing_image_path'] ?? ''),
            ];

            if ($galleryForm['item_title'] === '') {
                $galleryErrors[] = 'Museum item title is required.';
            }

            if (!in_array($galleryForm['item_type'], ['portrait', 'instrument', 'milestone'], true)) {
                $galleryErrors[] = 'Please choose a valid museum item type.';
            }

            if (isset($_FILES['gallery_image']) && ($_FILES['gallery_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['gallery_image'], 'branding');
                if (!empty($upload['success'])) {
                    $oldPath = getFounderMediaLocalPath($galleryForm['image_path']);
                    if ($oldPath && is_file($oldPath)) {
                        unlink($oldPath);
                    }
                    $galleryForm['image_path'] = 'assets/uploads/branding/' . $upload['filename'];
                } else {
                    foreach (($upload['errors'] ?? ['Museum item upload failed.']) as $uploadError) {
                        $galleryErrors[] = $uploadError;
                    }
                }
            }

            if ($galleryForm['image_path'] === '') {
                $galleryErrors[] = 'Please upload an image for this museum item.';
            }

            if (empty($galleryErrors)) {
                if ($galleryForm['id'] > 0) {
                    $db->update(
                        "UPDATE founder_gallery
                         SET item_title = ?, item_type = ?, item_description = ?, image_path = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                         WHERE id = ?",
                        [
                            $galleryForm['item_title'],
                            $galleryForm['item_type'],
                            $galleryForm['item_description'] !== '' ? $galleryForm['item_description'] : null,
                            $galleryForm['image_path'],
                            $galleryForm['sort_order'],
                            $galleryForm['is_active'],
                            $galleryForm['id'],
                        ]
                    );
                    createLog('FOUNDER_GALLERY_UPDATED', 'Founder gallery item updated #' . $galleryForm['id']);
                    setFlashMessage('success', 'Museum item updated successfully.');
                } else {
                    $newId = $db->insert(
                        "INSERT INTO founder_gallery (item_title, item_type, item_description, image_path, sort_order, is_active)
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [
                            $galleryForm['item_title'],
                            $galleryForm['item_type'],
                            $galleryForm['item_description'] !== '' ? $galleryForm['item_description'] : null,
                            $galleryForm['image_path'],
                            $galleryForm['sort_order'],
                            $galleryForm['is_active'],
                        ]
                    );
                    createLog('FOUNDER_GALLERY_CREATED', 'Founder gallery item created #' . $newId);
                    setFlashMessage('success', 'Museum item added successfully.');
                }

                redirect(APP_URL . '/admin/founder.php');
            }
        }
    }
}

if ($editingGalleryId > 0) {
    $galleryItem = $db->fetch("SELECT * FROM founder_gallery WHERE id = ?", [$editingGalleryId]);
    if ($galleryItem) {
        $galleryForm = [
            'id' => (int) $galleryItem['id'],
            'item_title' => (string) $galleryItem['item_title'],
            'item_type' => (string) $galleryItem['item_type'],
            'item_description' => (string) ($galleryItem['item_description'] ?? ''),
            'sort_order' => (int) ($galleryItem['sort_order'] ?? 0),
            'is_active' => (int) ($galleryItem['is_active'] ?? 1),
            'image_path' => (string) ($galleryItem['image_path'] ?? ''),
        ];
    }
}

$founderProfile = getFounderProfile();
$galleryItems = getFounderGalleryItems(true);

require_once __DIR__ . '/inc/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="mb-1">Founder Story & Digital Museum</h2>
            <p class="text-muted mb-0">Manage the homepage spotlight, the dedicated founder page, and the museum gallery items.</p>
        </div>
        <a href="<?php echo APP_URL; ?>/founder" class="btn btn-outline-primary">
            <i class="fas fa-arrow-up-right-from-square me-2"></i> Preview Founder Page
        </a>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Founder Profile</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                        <div><?php echo sanitize($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="save_founder_profile">
                        <input type="hidden" name="existing_founder_thumbnail" value="<?php echo sanitize($profileForm['founder_thumbnail']); ?>">
                        <input type="hidden" name="existing_founder_hero_image" value="<?php echo sanitize($profileForm['founder_hero_image']); ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Founder Name</label>
                                <input type="text" name="founder_name" class="form-control" value="<?php echo sanitize($profileForm['founder_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role or Title</label>
                                <input type="text" name="founder_role" class="form-control" value="<?php echo sanitize($profileForm['founder_role']); ?>" placeholder="Founder and Visionary Leader" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Homepage Short Writing</label>
                                <textarea name="founder_short_bio" class="form-control" rows="4" placeholder="Short teaser for the landing page" required><?php echo sanitize($profileForm['founder_short_bio']); ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Founder Quote</label>
                                <input type="text" name="founder_quote" class="form-control" value="<?php echo sanitize($profileForm['founder_quote']); ?>" placeholder="Optional signature quote">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Dedicated Story</label>
                                <textarea name="founder_story" class="form-control" rows="8" placeholder="Full founder story for the museum page" required><?php echo sanitize($profileForm['founder_story']); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Landing Page Thumbnail</label>
                                <input type="file" name="founder_thumbnail" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_founder_thumbnail" id="removeFounderThumbnail">
                                    <label class="form-check-label" for="removeFounderThumbnail">Remove current thumbnail</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Founder Page Hero Image</label>
                                <input type="file" name="founder_hero_image" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_founder_hero_image" id="removeFounderHeroImage">
                                    <label class="form-check-label" for="removeFounderHeroImage">Remove current hero image</label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary mt-4">Save Founder Profile</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Museum Gallery Items</h5>
                    <a href="<?php echo APP_URL; ?>/admin/founder.php" class="btn btn-sm btn-outline-secondary">New Item</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($galleryErrors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($galleryErrors as $error): ?>
                        <div><?php echo sanitize($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="border rounded-4 p-3 bg-light mb-4">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="save_gallery_item">
                        <input type="hidden" name="gallery_id" value="<?php echo (int) $galleryForm['id']; ?>">
                        <input type="hidden" name="existing_image_path" value="<?php echo sanitize($galleryForm['image_path']); ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Item Title</label>
                                <input type="text" name="item_title" class="form-control" value="<?php echo sanitize($galleryForm['item_title']); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Item Type</label>
                                <select name="item_type" class="form-select">
                                    <option value="portrait" <?php echo $galleryForm['item_type'] === 'portrait' ? 'selected' : ''; ?>>Portrait</option>
                                    <option value="instrument" <?php echo $galleryForm['item_type'] === 'instrument' ? 'selected' : ''; ?>>Instrument</option>
                                    <option value="milestone" <?php echo $galleryForm['item_type'] === 'milestone' ? 'selected' : ''; ?>>Milestone</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sort Order</label>
                                <input type="number" name="sort_order" class="form-control" value="<?php echo (int) $galleryForm['sort_order']; ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Short Description</label>
                                <textarea name="item_description" class="form-control" rows="3" placeholder="Short note about this picture or instrument"><?php echo sanitize($galleryForm['item_description']); ?></textarea>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Item Image</label>
                                <input type="file" name="gallery_image" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp" <?php echo $galleryForm['id'] > 0 ? '' : 'required'; ?>>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="galleryIsActive" <?php echo (int) $galleryForm['is_active'] === 1 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="galleryIsActive">Visible on founder page</label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary mt-3"><?php echo $galleryForm['id'] > 0 ? 'Update Museum Item' : 'Add Museum Item'; ?></button>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Type</th>
                                    <th>Visibility</th>
                                    <th>Order</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($galleryItems)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No museum items yet. Add portraits, instruments, or milestones.</td>
                                </tr>
                                <?php endif; ?>
                                <?php foreach ($galleryItems as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="<?php echo getFounderGalleryImageUrl($item['image_path'] ?? ''); ?>" alt="<?php echo sanitize($item['item_title']); ?>" style="width: 64px; height: 64px; object-fit: cover; border-radius: 16px;">
                                            <div>
                                                <strong><?php echo sanitize($item['item_title']); ?></strong>
                                                <div class="text-muted small"><?php echo sanitize(substr((string) ($item['item_description'] ?? ''), 0, 90)); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo sanitize(ucfirst((string) ($item['item_type'] ?? 'portrait'))); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo (int) ($item['is_active'] ?? 0) === 1 ? 'success' : 'secondary'; ?>">
                                            <?php echo (int) ($item['is_active'] ?? 0) === 1 ? 'Visible' : 'Hidden'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo (int) ($item['sort_order'] ?? 0); ?></td>
                                    <td>
                                        <a href="<?php echo APP_URL; ?>/admin/founder.php?edit_gallery=<?php echo (int) $item['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <a href="<?php echo APP_URL; ?>/admin/founder.php?delete_gallery=<?php echo (int) $item['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirmDelete('Delete this museum item?')">Delete</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Homepage Founder Spotlight</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-5">
                            <img src="<?php echo sanitize($founderProfile['thumbnail_url']); ?>" alt="<?php echo sanitize($founderProfile['name']); ?>" class="img-fluid rounded-4" style="height: 220px; width: 100%; object-fit: cover;">
                        </div>
                        <div class="col-md-7">
                            <p class="text-uppercase small text-muted mb-1">Founder Spotlight</p>
                            <h4 class="mb-1"><?php echo sanitize($founderProfile['name']); ?></h4>
                            <p class="text-primary fw-semibold mb-3"><?php echo sanitize($founderProfile['role']); ?></p>
                            <p class="text-muted mb-0"><?php echo sanitize($founderProfile['short_bio']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Founder Page Hero</h5>
                </div>
                <div class="card-body">
                    <img src="<?php echo sanitize($founderProfile['hero_image_url']); ?>" alt="<?php echo sanitize($founderProfile['name']); ?>" class="img-fluid rounded-4 mb-3" style="height: 240px; width: 100%; object-fit: cover;">
                    <?php if ($founderProfile['quote'] !== ''): ?>
                    <blockquote class="blockquote mb-0">
                        <p class="mb-2">"<?php echo sanitize($founderProfile['quote']); ?>"</p>
                        <footer class="blockquote-footer"><?php echo sanitize($founderProfile['name']); ?></footer>
                    </blockquote>
                    <?php else: ?>
                    <p class="text-muted mb-0">Add a founder quote to give the museum page more voice and personality.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Museum Guidance</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0 ps-3 text-muted">
                        <li>Use the homepage short writing for a compact teaser.</li>
                        <li>Use the dedicated story for the full biography and legacy overview.</li>
                        <li>Mix portraits, old instruments, and milestone items to make the page feel like a digital museum.</li>
                        <li>Keep gallery sort order low for the most important legacy items.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
