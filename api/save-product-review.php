<?php
/**
 * Bealet Website - API: Save Product Review
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

global $db;

ensureProductReviewSupport();

$response = [
    'success' => false,
    'message' => '',
    'errors' => [],
    'product_id' => 0,
    'rating' => ['average' => 0, 'total' => 0],
    'reviews' => [],
    'review_form' => [
        'rating' => 5,
        'comment' => '',
        'existing_review_image' => '',
    ],
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Method not allowed.';
    echo json_encode($response);
    exit;
}

$currentUser = isLoggedIn() ? getCurrentUser() : null;
$productId = (int) ($_POST['review_product_id'] ?? 0);
$rating = (int) ($_POST['rating'] ?? 0);
$comment = trim((string) ($_POST['comment'] ?? ''));
$reviewImagePath = trim((string) ($_POST['existing_review_image'] ?? ''));

$response['product_id'] = $productId;
$response['review_form'] = [
    'rating' => $rating > 0 ? $rating : 5,
    'comment' => $comment,
    'existing_review_image' => $reviewImagePath,
];

if (!$currentUser) {
    http_response_code(401);
    $response['message'] = 'Please login before reviewing a product.';
    $response['errors'][] = $response['message'];
    echo json_encode($response);
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(422);
    $response['message'] = 'Invalid request. Please refresh and try again.';
    $response['errors'][] = $response['message'];
    echo json_encode($response);
    exit;
}

if ($productId <= 0) {
    $response['errors'][] = 'We could not determine which product you reviewed.';
}

if ($rating < 1 || $rating > 5) {
    $response['errors'][] = 'Please choose a rating between 1 and 5 stars.';
}

if ($comment === '') {
    $response['errors'][] = 'Please share a short review before submitting.';
} elseif (mb_strlen($comment) < 12) {
    $response['errors'][] = 'Please make your review a little more descriptive.';
} elseif (mb_strlen($comment) > 1500) {
    $response['errors'][] = 'Please keep your review under 1500 characters.';
}

if ($productId > 0) {
    $product = $db->fetch(
        "SELECT id FROM products WHERE id = ? AND is_active = 1 LIMIT 1",
        [$productId]
    );

    if (!$product) {
        $response['errors'][] = 'That product is no longer available for review.';
    }
}

$reviewImageError = (int) ($_FILES['review_image']['error'] ?? UPLOAD_ERR_NO_FILE);
if ($reviewImageError === UPLOAD_ERR_OK) {
    $upload = uploadFile($_FILES['review_image'], 'reviews');
    if (!empty($upload['success'])) {
        $reviewImagePath = 'assets/uploads/reviews/' . $upload['filename'];
        $response['review_form']['existing_review_image'] = $reviewImagePath;
    } else {
        foreach (($upload['errors'] ?? ['Review image upload failed.']) as $uploadError) {
            $response['errors'][] = $uploadError;
        }
    }
} elseif ($reviewImageError !== UPLOAD_ERR_NO_FILE) {
    $response['errors'][] = 'Review image upload failed. Please try again.';
}

if (!empty($response['errors'])) {
    http_response_code(422);
    $response['message'] = $response['errors'][0];
    echo json_encode($response);
    exit;
}

$existingProductReview = getProductReviewByUserId($productId, (int) $currentUser['id']);

if ($existingProductReview) {
    $db->update(
        "UPDATE reviews
         SET rating = ?, comment = ?, review_image = ?, updated_at = NOW()
         WHERE id = ?",
        [
            $rating,
            $comment,
            $reviewImagePath !== '' ? $reviewImagePath : null,
            (int) $existingProductReview['id'],
        ]
    );
} else {
    $db->insert(
        "INSERT INTO reviews (product_id, user_id, rating, comment, review_image)
         VALUES (?, ?, ?, ?, ?)",
        [
            $productId,
            (int) $currentUser['id'],
            $rating,
            $comment,
            $reviewImagePath !== '' ? $reviewImagePath : null,
        ]
    );
}

$productReviews = getProductReviews($productId, 8);
$response['success'] = true;
$response['message'] = 'Your product review was saved successfully.';
$response['rating'] = getProductRating($productId);
$response['reviews'] = array_map(static function ($review) {
    return [
        'reviewer_name' => (string) ($review['reviewer_name'] ?? 'Customer'),
        'profile_image_url' => getUserProfileImageUrl($review['reviewer_profile_image'] ?? '', $review['reviewer_name'] ?? 'Customer'),
        'review_image_url' => getProductReviewImageUrl($review['review_image'] ?? ''),
        'rating' => (int) ($review['rating'] ?? 0),
        'comment' => (string) ($review['comment'] ?? ''),
        'date' => formatDate($review['updated_at'] ?? $review['created_at']),
    ];
}, $productReviews);
$response['review_form'] = [
    'rating' => $rating,
    'comment' => $comment,
    'existing_review_image' => $reviewImagePath,
];

createLog('PRODUCT_REVIEW_SUBMITTED', 'Product review saved for product #' . $productId, (int) $currentUser['id']);

echo json_encode($response);
