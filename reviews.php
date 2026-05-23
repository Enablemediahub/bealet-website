<?php
/**
 * Bealet Website - Customer Testimonials Page
 */

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

global $db;

ensureCustomerReviewsTable();

$currentUser = isLoggedIn() ? getCurrentUser() : null;
$existingReview = $currentUser ? getCustomerReviewByUserId((int) $currentUser['id']) : null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isLoggedIn() || !$currentUser) {
        $errors[] = 'Please login or register before leaving a testimonial.';
    } elseif (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $rating = (int) ($_POST['rating'] ?? 0);
        $comment = trim((string) ($_POST['comment'] ?? ''));
        $profileImagePath = (string) ($currentUser['profile_image'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $errors[] = 'Please choose a star rating between 1 and 5.';
        }

        if ($comment === '') {
            $errors[] = 'Please write a short testimonial before submitting.';
        } elseif (mb_strlen($comment) < 12) {
            $errors[] = 'Please make your review a little more descriptive.';
        } elseif (mb_strlen($comment) > 1500) {
            $errors[] = 'Please keep your review under 1500 characters.';
        }

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

        if (empty($errors)) {
            if ($profileImagePath !== '') {
                $db->update(
                    "UPDATE users SET profile_image = ?, updated_at = NOW() WHERE id = ?",
                    [$profileImagePath, (int) $currentUser['id']]
                );
            }

            $existingReview = getCustomerReviewByUserId((int) $currentUser['id']);

            if ($existingReview) {
                $db->update(
                    "UPDATE customer_reviews
                     SET reviewer_name = ?, reviewer_email = ?, profile_image = ?, rating = ?, comment = ?, is_approved = 0, updated_at = NOW()
                     WHERE id = ?",
                    [
                        (string) ($currentUser['name'] ?? ''),
                        (string) ($currentUser['email'] ?? ''),
                        $profileImagePath !== '' ? $profileImagePath : null,
                        $rating,
                        $comment,
                        (int) $existingReview['id'],
                    ]
                );
            } else {
                $db->insert(
                    "INSERT INTO customer_reviews (user_id, reviewer_name, reviewer_email, profile_image, rating, comment, is_approved)
                     VALUES (?, ?, ?, ?, ?, ?, 0)",
                    [
                        (int) $currentUser['id'],
                        (string) ($currentUser['name'] ?? ''),
                        (string) ($currentUser['email'] ?? ''),
                        $profileImagePath !== '' ? $profileImagePath : null,
                        $rating,
                        $comment,
                    ]
                );
            }

            createLog('CUSTOMER_REVIEW_SUBMITTED', 'Customer testimonial submitted for moderation', (int) $currentUser['id']);
            setFlashMessage('success', 'Your testimonial has been saved and sent for admin approval.');
            redirect(APP_URL . '/reviews');
        }
    }
}

$summary = getCustomerReviewSummary();
$approvedReviews = getApprovedCustomerReviews();
$productTestimonials = getRecentProductTestimonials(12);
$existingReview = $currentUser ? getCustomerReviewByUserId((int) $currentUser['id']) : null;
$formRating = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])
    ? max(1, min(5, (int) ($_POST['rating'] ?? 5)))
    : (int) ($existingReview['rating'] ?? 5);
$formComment = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])
    ? trim((string) ($_POST['comment'] ?? ''))
    : (string) ($existingReview['comment'] ?? '');
$viewerImageUrl = $currentUser
    ? getUserProfileImageUrl($currentUser['profile_image'] ?? ($existingReview['profile_image'] ?? ''), $currentUser['name'] ?? 'Customer')
    : getUserProfileImageUrl('', 'Customer');

require_once __DIR__ . '/inc/header.php';
?>

<section class="section-spacing">
    <div class="container-lg">
        <div class="reviews-hero">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <span class="hero-kicker"><i class="fas fa-star me-2"></i>Customer Stories</span>
                    <h1 class="mt-3 mb-3">See what customers are saying about their Bealet experience.</h1>
                    <p class="lead text-muted mb-0">
                        Browse recent customer feedback, then add your own star rating and comment once you have a registered account.
                    </p>
                </div>
                <div class="col-lg-5">
                    <div class="reviews-summary-card">
                        <div class="reviews-summary-score">
                            <strong><?php echo number_format((float) ($summary['average_rating'] ?? 0), 1); ?></strong>
                            <span>out of 5</span>
                        </div>
                        <div>
                            <div class="review-stars mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="<?php echo $i <= round((float) ($summary['average_rating'] ?? 0)) ? 'fas' : 'far'; ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                            <p class="mb-1 fw-semibold"><?php echo (int) ($summary['total_reviews'] ?? 0); ?> approved customer testimonials</p>
                            <p class="text-muted mb-0">Each new or edited review is checked by admin before it appears here.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-spacing pt-0">
    <div class="container-lg">
        <div class="row g-4 align-items-start">
            <div class="col-xl-4">
                <div class="review-form-panel">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img src="<?php echo sanitize($viewerImageUrl); ?>" alt="Your profile" class="review-avatar review-avatar-lg">
                        <div>
                            <h2 class="h4 mb-1">Add Your Testimonial</h2>
                            <p class="text-muted mb-0">Registered customers can leave one testimonial and update it anytime.</p>
                        </div>
                    </div>

                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                        <div><?php echo sanitize($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($currentUser): ?>
                    <form method="POST" enctype="multipart/form-data" class="review-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="submit_review" value="1">

                        <div class="mb-3">
                            <label class="form-label">Your Rating</label>
                            <select name="rating" class="form-select" required>
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?php echo $i; ?>" <?php echo $formRating === $i ? 'selected' : ''; ?>>
                                    <?php echo $i; ?> Star<?php echo $i === 1 ? '' : 's'; ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Your Comment</label>
                            <textarea name="comment" class="form-control" rows="6" placeholder="Tell other customers what stood out for you." required><?php echo sanitize($formComment); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Profile Image</label>
                            <input type="file" name="profile_image" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp">
                            <small class="text-muted">Use a small portrait photo. Rounded thumbnails will be shown on public reviews.</small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-paper-plane me-2"></i>
                            <?php echo $existingReview ? 'Update Testimonial' : 'Submit Testimonial'; ?>
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="review-login-card">
                        <p class="mb-3">Login or create an account to leave a testimonial with your profile image.</p>
                        <div class="d-grid gap-2">
                            <a href="<?php echo APP_URL; ?>/login" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i> Login
                            </a>
                            <a href="<?php echo APP_URL; ?>/register" class="btn btn-outline-primary">
                                <i class="fas fa-user-plus me-2"></i> Register
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="section-title text-start mb-4">
                    <h2>Recent Testimonials</h2>
                    <p>Compact cards and small rounded portraits keep the page easy to scan even when there are many customer stories.</p>
                </div>

                <?php if (!empty($approvedReviews)): ?>
                <div class="row g-3">
                    <?php foreach ($approvedReviews as $review): ?>
                    <div class="col-md-6">
                        <article class="review-card h-100">
                            <div class="review-card-head">
                                <div class="d-flex align-items-center gap-3">
                                    <img
                                        src="<?php echo sanitize(getUserProfileImageUrl($review['profile_image'] ?? '', $review['reviewer_name'] ?? 'Customer')); ?>"
                                        alt="<?php echo sanitize($review['reviewer_name'] ?? 'Customer'); ?>"
                                        class="review-avatar"
                                    >
                                    <div>
                                        <h3 class="h6 mb-1"><?php echo sanitize($review['reviewer_name'] ?? 'Customer'); ?></h3>
                                        <div class="review-stars small">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="<?php echo $i <= (int) ($review['rating'] ?? 0) ? 'fas' : 'far'; ?> fa-star"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted"><?php echo formatDate($review['updated_at'] ?? $review['created_at']); ?></small>
                            </div>
                            <p class="mb-0 review-comment"><?php echo nl2br(sanitize($review['comment'] ?? '')); ?></p>
                        </article>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-5 text-center">
                        <i class="fas fa-comments review-empty-icon mb-3"></i>
                        <h3 class="h4">Customer testimonials will appear here soon</h3>
                        <p class="text-muted mb-0">Once approved by admin, the first published customer stories will show up in this space.</p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="section-title text-start mt-5 mb-4">
                    <h2>Product Testimonials</h2>
                    <p>Recent customer feedback collected directly from individual product pages and frame galleries.</p>
                </div>

                <?php if (!empty($productTestimonials)): ?>
                <div class="row g-3">
                    <?php foreach ($productTestimonials as $review): ?>
                    <div class="col-md-6">
                        <article class="review-card h-100">
                            <div class="review-card-head">
                                <div class="d-flex align-items-center gap-3">
                                    <img
                                        src="<?php echo sanitize(getUserProfileImageUrl($review['reviewer_profile_image'] ?? '', $review['reviewer_name'] ?? 'Customer')); ?>"
                                        alt="<?php echo sanitize($review['reviewer_name'] ?? 'Customer'); ?>"
                                        class="review-avatar"
                                    >
                                    <div>
                                        <h3 class="h6 mb-1"><?php echo sanitize($review['reviewer_name'] ?? 'Customer'); ?></h3>
                                        <div class="review-stars small">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="<?php echo $i <= (int) ($review['rating'] ?? 0) ? 'fas' : 'far'; ?> fa-star"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted"><?php echo formatDate($review['updated_at'] ?? $review['created_at']); ?></small>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge text-bg-light border"><?php echo sanitize($review['product_name'] ?? 'Product'); ?></span>
                                <?php if (!empty($review['frame_target'])): ?>
                                <span class="badge text-bg-light border"><?php echo sanitize(formatProductAudienceLabel($review['frame_target'])); ?></span>
                                <?php elseif (!empty($review['category'])): ?>
                                <span class="badge text-bg-light border"><?php echo sanitize(formatProductCategoryLabel($review['category'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($review['review_image'])): ?>
                            <div class="mb-3">
                                <img
                                    src="<?php echo sanitize(getProductReviewImageUrl($review['review_image'])); ?>"
                                    alt="<?php echo sanitize(($review['reviewer_name'] ?? 'Customer') . ' product photo'); ?>"
                                    class="product-testimonial-thumb"
                                >
                            </div>
                            <?php endif; ?>
                            <p class="mb-0 review-comment"><?php echo nl2br(sanitize($review['comment'] ?? '')); ?></p>
                        </article>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-4 text-center">
                        <h3 class="h5">Product testimonials will appear here soon</h3>
                        <p class="text-muted mb-0">Once customers start leaving reviews from product pages, they will also show up in this section.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
