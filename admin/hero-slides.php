<?php
/**
 * Bealet Website - Admin Hero Slides Management
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(APP_URL . '/admin/login.php');
}

requireSuperAdmin();

$errors = [];
$slides = getHeroSlides();

if (isset($_GET['delete'])) {
    $slideId = sanitize($_GET['delete']);
    $nextSlides = [];

    foreach ($slides as $slide) {
        if ($slide['id'] === $slideId) {
            $path = __DIR__ . '/../assets/uploads/hero/' . basename($slide['image']);
            if (is_file($path)) {
                unlink($path);
            }
            continue;
        }
        $nextSlides[] = $slide;
    }

    if (saveHeroSlides($nextSlides)) {
        setFlashMessage('success', 'Hero slide deleted successfully.');
    } else {
        setFlashMessage('danger', 'Unable to delete hero slide.');
    }

    redirect(APP_URL . '/admin/hero-slides.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $subtitle = sanitize($_POST['subtitle'] ?? '');
        $ctaText = sanitize($_POST['cta_text'] ?? '');
        $ctaUrl = sanitize($_POST['cta_url'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (!isset($_FILES['slide_image']) || $_FILES['slide_image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Please choose an image to upload.';
        } else {
            $upload = uploadFile($_FILES['slide_image'], 'hero');
            if (empty($upload['success'])) {
                $errors = array_merge($errors, $upload['errors'] ?? ['Image upload failed.']);
            } else {
                $maxSort = -1;
                foreach ($slides as $existing) {
                    if ((int) $existing['sort_order'] > $maxSort) {
                        $maxSort = (int) $existing['sort_order'];
                    }
                }

                $slides[] = [
                    'id' => uniqid('hero_', true),
                    'image' => $upload['filename'],
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'cta_text' => $ctaText,
                    'cta_url' => $ctaUrl,
                    'is_active' => $isActive,
                    'sort_order' => $maxSort + 1,
                ];

                if (saveHeroSlides($slides)) {
                    setFlashMessage('success', 'Hero slide uploaded successfully.');
                    redirect(APP_URL . '/admin/hero-slides.php');
                }

                $errors[] = 'Unable to save hero slide configuration.';
            }
        }
    }
}

require_once __DIR__ . '/inc/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Hero Slides</h2>
            <p class="text-muted">Upload and manage homepage hero carousel images.</p>
        </div>
        <span class="badge bg-primary" style="font-size: 1rem;"><?php echo count($slides); ?> Slides</span>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
        <div><?php echo sanitize($error); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Add New Slide</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="mb-3">
                            <label class="form-label">Slide Image</label>
                            <input type="file" class="form-control" name="slide_image" accept="image/*" required>
                            <small class="text-muted">Recommended size: 1600x900</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Title (Optional)</label>
                            <input type="text" class="form-control" name="title" maxlength="120">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Subtitle (Optional)</label>
                            <textarea class="form-control" name="subtitle" rows="3" maxlength="200"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Button Text (Optional)</label>
                            <input type="text" class="form-control" name="cta_text" maxlength="40">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Button URL (Optional)</label>
                            <input type="text" class="form-control" name="cta_url" placeholder="/shop.php">
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="isActive" name="is_active" checked>
                            <label class="form-check-label" for="isActive">Show this slide in hero carousel</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-upload me-2"></i> Upload Slide
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Current Slides</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($slides)): ?>
                    <p class="text-muted mb-0">No hero slides uploaded yet.</p>
                    <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($slides as $slide): ?>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-2 h-100">
                                <img src="<?php echo getHeroSlideImageUrl($slide['image']); ?>" alt="Hero slide" class="img-fluid rounded-3 mb-2" style="height: 160px; width: 100%; object-fit: cover;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="d-block"><?php echo sanitize($slide['title'] ?: 'Untitled slide'); ?></strong>
                                        <small class="text-muted"><?php echo (int) $slide['is_active'] === 1 ? 'Active' : 'Hidden'; ?></small>
                                    </div>
                                    <a href="<?php echo APP_URL; ?>/admin/hero-slides.php?delete=<?php echo urlencode($slide['id']); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirmDelete('Delete this hero slide?')">Delete</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
