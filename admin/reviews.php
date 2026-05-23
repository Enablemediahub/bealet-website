<?php
/**
 * Bealet Website - Admin Testimonials Management
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(APP_URL . '/admin/login.php');
}

global $db;

ensureCustomerReviewsTable();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_review'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $reviewId = (int) ($_POST['review_id'] ?? 0);
        $reviewerName = trim((string) ($_POST['reviewer_name'] ?? ''));
        $reviewerEmail = trim((string) ($_POST['reviewer_email'] ?? ''));
        $rating = (int) ($_POST['rating'] ?? 0);
        $comment = trim((string) ($_POST['comment'] ?? ''));
        $isApproved = isset($_POST['is_approved']) ? 1 : 0;

        if ($reviewId > 0 && $reviewerName !== '' && $comment !== '' && $rating >= 1 && $rating <= 5) {
            $db->update(
                "UPDATE customer_reviews
                 SET reviewer_name = ?, reviewer_email = ?, rating = ?, comment = ?, is_approved = ?, updated_at = NOW()
                 WHERE id = ?",
                [
                    $reviewerName,
                    $reviewerEmail !== '' ? $reviewerEmail : null,
                    $rating,
                    $comment,
                    $isApproved,
                    $reviewId,
                ]
            );

            createLog('ADMIN_REVIEW_UPDATED', 'Admin updated customer review #' . $reviewId, $_SESSION['user_id'] ?? null);
            setFlashMessage('success', 'Testimonial updated successfully.');
        } else {
            setFlashMessage('error', 'Please complete the testimonial form correctly before saving.');
        }
    }

    redirect(APP_URL . '/admin/reviews.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $reviewId = (int) ($_POST['review_id'] ?? 0);
        if ($reviewId > 0) {
            $db->delete("DELETE FROM customer_reviews WHERE id = ?", [$reviewId]);
            createLog('ADMIN_REVIEW_DELETED', 'Admin deleted customer review #' . $reviewId, $_SESSION['user_id'] ?? null);
            setFlashMessage('success', 'Testimonial deleted successfully.');
        }
    }

    redirect(APP_URL . '/admin/reviews.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_review_approval'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $reviewId = (int) ($_POST['review_id'] ?? 0);
        $approveValue = isset($_POST['approve_value']) ? (int) $_POST['approve_value'] : 0;

        if ($reviewId > 0) {
            $db->update(
                "UPDATE customer_reviews SET is_approved = ?, updated_at = NOW() WHERE id = ?",
                [$approveValue === 1 ? 1 : 0, $reviewId]
            );

            createLog(
                $approveValue === 1 ? 'ADMIN_REVIEW_APPROVED' : 'ADMIN_REVIEW_UNAPPROVED',
                ($approveValue === 1 ? 'Admin approved customer review #' : 'Admin moved customer review back to pending #') . $reviewId,
                $_SESSION['user_id'] ?? null
            );
            setFlashMessage('success', $approveValue === 1 ? 'Testimonial approved successfully.' : 'Testimonial moved back to pending.');
        }
    }

    redirect(APP_URL . '/admin/reviews.php');
}

$editingReview = null;
if (isset($_GET['edit'])) {
    $editingReview = $db->fetch(
        "SELECT cr.*, u.name AS user_name, u.email AS user_email
         FROM customer_reviews cr
         LEFT JOIN users u ON cr.user_id = u.id
         WHERE cr.id = ?",
        [(int) $_GET['edit']]
    ) ?: null;
}

$reviewStats = $db->fetch(
    "SELECT
        COUNT(*) AS total_reviews,
        SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) AS approved_reviews,
        SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) AS pending_reviews,
        AVG(CASE WHEN is_approved = 1 THEN rating ELSE NULL END) AS average_rating
     FROM customer_reviews"
);

$reviews = $db->fetchAll(
    "SELECT cr.*, u.name AS account_name, u.email AS account_email
     FROM customer_reviews cr
     LEFT JOIN users u ON cr.user_id = u.id
     ORDER BY cr.updated_at DESC, cr.id DESC"
);

require_once __DIR__ . '/inc/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-1">Customer Testimonials</h2>
            <p class="text-muted mb-0">Approve, polish, or remove customer comments before they appear on the public site.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-primary"><?php echo (int) ($reviewStats['total_reviews'] ?? 0); ?> Total</span>
            <span class="badge bg-success"><?php echo (int) ($reviewStats['approved_reviews'] ?? 0); ?> Approved</span>
            <span class="badge bg-warning text-dark"><?php echo (int) ($reviewStats['pending_reviews'] ?? 0); ?> Pending</span>
            <span class="badge bg-info text-dark"><?php echo number_format((float) ($reviewStats['average_rating'] ?? 0), 1); ?>/5 Average</span>
        </div>
    </div>

    <?php if ($editingReview): ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Edit Testimonial</h5>
            <a href="<?php echo APP_URL; ?>/admin/reviews.php" class="btn btn-sm btn-outline-secondary">Close</a>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="save_review" value="1">
                <input type="hidden" name="review_id" value="<?php echo (int) $editingReview['id']; ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Reviewer Name</label>
                        <input type="text" class="form-control" name="reviewer_name" value="<?php echo sanitize($editingReview['reviewer_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Reviewer Email</label>
                        <input type="email" class="form-control" name="reviewer_email" value="<?php echo sanitize($editingReview['reviewer_email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Rating</label>
                        <select class="form-select" name="rating" required>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo (int) ($editingReview['rating'] ?? 0) === $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?> Star<?php echo $i === 1 ? '' : 's'; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="reviewApprovedSwitch" name="is_approved" value="1" <?php echo !empty($editingReview['is_approved']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="reviewApprovedSwitch">Approved for public display</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Comment</label>
                        <textarea class="form-control" name="comment" rows="6" required><?php echo sanitize($editingReview['comment'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Save Testimonial</button>
                    <a href="<?php echo APP_URL; ?>/admin/reviews.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Testimonial Queue</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($reviews)): ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Reviewer</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Comment</th>
                            <th>Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $review): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img
                                        src="<?php echo sanitize(getUserProfileImageUrl($review['profile_image'] ?? '', $review['reviewer_name'] ?? 'Customer')); ?>"
                                        alt="<?php echo sanitize($review['reviewer_name'] ?? 'Customer'); ?>"
                                        class="admin-review-thumb"
                                    >
                                    <div>
                                        <strong><?php echo sanitize($review['reviewer_name'] ?? ''); ?></strong>
                                        <div class="text-muted small"><?php echo sanitize($review['reviewer_email'] ?? ($review['account_email'] ?? '')); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="review-stars small">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="<?php echo $i <= (int) ($review['rating'] ?? 0) ? 'fas' : 'far'; ?> fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo !empty($review['is_approved']) ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                    <?php echo !empty($review['is_approved']) ? 'Approved' : 'Pending'; ?>
                                </span>
                            </td>
                            <td style="min-width: 280px;">
                                <?php $commentPreview = (string) ($review['comment'] ?? ''); ?>
                                <div class="text-muted small admin-review-comment"><?php echo sanitize(strlen($commentPreview) > 150 ? substr($commentPreview, 0, 147) . '...' : $commentPreview); ?></div>
                            </td>
                            <td><?php echo formatDate($review['updated_at'] ?? $review['created_at']); ?></td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="toggle_review_approval" value="1">
                                        <input type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
                                        <input type="hidden" name="approve_value" value="<?php echo !empty($review['is_approved']) ? '0' : '1'; ?>">
                                        <button type="submit" class="btn btn-sm <?php echo !empty($review['is_approved']) ? 'btn-outline-warning' : 'btn-outline-success'; ?>">
                                            <?php echo !empty($review['is_approved']) ? 'Unpublish' : 'Publish'; ?>
                                        </button>
                                    </form>
                                    <a href="<?php echo APP_URL; ?>/admin/reviews.php?edit=<?php echo (int) $review['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" onsubmit="return confirmDelete('Delete this customer testimonial?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="delete_review" value="1">
                                        <input type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted mb-0">No customer testimonials have been submitted yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
