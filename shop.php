<?php
/**
 * Bealet Website - Shop Page
 */

require_once __DIR__ . '/inc/header.php';

global $db;
ensureProductReviewSupport();

$currentUser = isLoggedIn() ? getCurrentUser() : null;
$productReviewErrors = [];
$productReviewPrefill = [];
$viewProductId = isset($_GET['view_product']) ? (int) $_GET['view_product'] : 0;
$reviewSaved = isset($_GET['review_saved']) && $_GET['review_saved'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_product_review'])) {
    $reviewProductId = (int) ($_POST['review_product_id'] ?? 0);
    $viewProductId = $reviewProductId > 0 ? $reviewProductId : $viewProductId;
    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim((string) ($_POST['comment'] ?? ''));
    $reviewImagePath = trim((string) ($_POST['existing_review_image'] ?? ''));
    $guestName = trim((string) ($_POST['guest_name'] ?? ''));
    $guestEmail = trim((string) ($_POST['guest_email'] ?? ''));

    $productReviewPrefill[$reviewProductId] = [
        'rating' => $rating,
        'comment' => $comment,
        'guest_name' => $guestName,
        'guest_email' => $guestEmail,
    ];

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $productReviewErrors[$reviewProductId][] = 'Invalid request. Please try again.';
    } else {
        if ($reviewProductId <= 0) {
            $productReviewErrors[$reviewProductId][] = 'We could not determine which product you reviewed.';
        }

        if ($rating < 1 || $rating > 5) {
            $productReviewErrors[$reviewProductId][] = 'Please choose a rating between 1 and 5 stars.';
        }

        if ($comment === '') {
            $productReviewErrors[$reviewProductId][] = 'Please share a short review before submitting.';
        } elseif (mb_strlen($comment) < 12) {
            $productReviewErrors[$reviewProductId][] = 'Please make your review a little more descriptive.';
        } elseif (mb_strlen($comment) > 1500) {
            $productReviewErrors[$reviewProductId][] = 'Please keep your review under 1500 characters.';
        }

        if (!$currentUser) {
            if ($guestName === '') {
                $productReviewErrors[$reviewProductId][] = 'Please share your name with this product review.';
            }

            if ($guestEmail === '') {
                $productReviewErrors[$reviewProductId][] = 'Please enter your email address for this product review.';
            } elseif (!validateEmail($guestEmail)) {
                $productReviewErrors[$reviewProductId][] = 'Please enter a valid email address.';
            }
        }

        $reviewImageError = (int) ($_FILES['review_image']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($reviewImageError === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['review_image'], 'reviews');
            if (!empty($upload['success'])) {
                $reviewImagePath = 'assets/uploads/reviews/' . $upload['filename'];
            } else {
                foreach (($upload['errors'] ?? ['Review image upload failed.']) as $uploadError) {
                    $productReviewErrors[$reviewProductId][] = $uploadError;
                }
            }
        } elseif ($reviewImageError !== UPLOAD_ERR_NO_FILE) {
            $productReviewErrors[$reviewProductId][] = 'Review image upload failed. Please try again.';
        }

        if (empty($productReviewErrors[$reviewProductId])) {
            $reviewerName = $currentUser ? (string) ($currentUser['name'] ?? 'Customer') : $guestName;
            $reviewerEmail = $currentUser ? (string) ($currentUser['email'] ?? '') : $guestEmail;
            $existingProductReview = $currentUser ? getProductReviewByUserId($reviewProductId, (int) $currentUser['id']) : null;

            if ($existingProductReview) {
                $db->update(
                    "UPDATE reviews
                     SET reviewer_name = ?, reviewer_email = ?, rating = ?, comment = ?, review_image = ?, is_approved = 0, updated_at = NOW()
                     WHERE id = ?",
                    [
                        $reviewerName !== '' ? $reviewerName : null,
                        $reviewerEmail !== '' ? $reviewerEmail : null,
                        $rating,
                        $comment,
                        $reviewImagePath !== '' ? $reviewImagePath : null,
                        (int) $existingProductReview['id'],
                    ]
                );
            } else {
                $db->insert(
                    "INSERT INTO reviews (product_id, user_id, reviewer_name, reviewer_email, rating, comment, review_image, is_approved)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 0)",
                    [
                        $reviewProductId,
                        $currentUser ? (int) $currentUser['id'] : null,
                        $reviewerName !== '' ? $reviewerName : null,
                        $reviewerEmail !== '' ? $reviewerEmail : null,
                        $rating,
                        $comment,
                        $reviewImagePath !== '' ? $reviewImagePath : null,
                    ]
                );
            }

            createLog('PRODUCT_REVIEW_SUBMITTED', 'Product review saved for product #' . $reviewProductId, $currentUser['id'] ?? null);
            setFlashMessage('success', 'Your product review has been saved and is awaiting admin approval.');
            redirect(APP_URL . '/shop.php?view_product=' . $reviewProductId . '&review_saved=1');
        }
    }
}

// Pagination
$perPage = 12;
$currentPage = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$offset = ($currentPage - 1) * $perPage;

// Get filter parameters
$category = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$frameTarget = isset($_GET['frame_target']) ? sanitize($_GET['frame_target']) : '';
$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 100000;
$searchQuery = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$sortBy = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';
$tryOnFilter = isset($_GET['try_on']) ? sanitize($_GET['try_on']) : '';

$productColumnMap = [];
try {
    foreach ($db->fetchAll("SHOW COLUMNS FROM products") as $column) {
        if (!empty($column['Field'])) {
            $productColumnMap[$column['Field']] = true;
        }
    }
} catch (Throwable $e) {
    $productColumnMap = [];
}

$hasArModel2dColumn = isset($productColumnMap['ar_model_2d']);
$hasArModel3dColumn = isset($productColumnMap['ar_model_3d']);

// Build query
$query = "SELECT * FROM products WHERE is_active = 1";
$countQuery = "SELECT COUNT(*) as total FROM products WHERE is_active = 1";
$params = [];

// Add filters
if (!empty($category)) {
    $query .= " AND category = ?";
    $countQuery .= " AND category = ?";
    $params[] = $category;
}

if (!empty($frameTarget)) {
    $query .= " AND frame_target = ?";
    $countQuery .= " AND frame_target = ?";
    $params[] = $frameTarget;
}

if (!empty($searchQuery)) {
    $query .= " AND (MATCH(name, description, brand) AGAINST(? IN BOOLEAN MODE) OR name LIKE ?)";
    $countQuery .= " AND (MATCH(name, description, brand) AGAINST(? IN BOOLEAN MODE) OR name LIKE ?)";
    $params[] = $searchQuery;
    $params[] = '%' . $searchQuery . '%';
}

if ($minPrice > 0) {
    $query .= " AND price >= ?";
    $countQuery .= " AND price >= ?";
    $params[] = $minPrice;
}

if ($maxPrice < 100000) {
    $query .= " AND price <= ?";
    $countQuery .= " AND price <= ?";
    $params[] = $maxPrice;
}

if ($tryOnFilter === 'ready' && ($hasArModel2dColumn || $hasArModel3dColumn)) {
    $tryOnParts = [];
    if ($hasArModel2dColumn) {
        $tryOnParts[] = "(ar_model_2d IS NOT NULL AND ar_model_2d <> '')";
    }
    if ($hasArModel3dColumn) {
        $tryOnParts[] = "(ar_model_3d IS NOT NULL AND ar_model_3d <> '')";
    }

    if (!empty($tryOnParts)) {
        $tryOnSql = implode(' OR ', $tryOnParts);
        $query .= " AND ($tryOnSql)";
        $countQuery .= " AND ($tryOnSql)";
    }
}

// Add sorting
switch ($sortBy) {
    case 'price_low':
        $query .= " ORDER BY price ASC";
        break;
    case 'price_high':
        $query .= " ORDER BY price DESC";
        break;
    case 'popular':
        $query .= " ORDER BY id DESC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY created_at DESC";
}

$query .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

// Get products
$products = $db->fetchAll($query, $params);
$modalProducts = $products;

if ($viewProductId > 0) {
    $hasViewProduct = false;
    foreach ($modalProducts as $listedProduct) {
        if ((int) ($listedProduct['id'] ?? 0) === $viewProductId) {
            $hasViewProduct = true;
            break;
        }
    }

    if (!$hasViewProduct) {
        $focusedProduct = $db->fetch("SELECT * FROM products WHERE id = ? AND is_active = 1 LIMIT 1", [$viewProductId]);
        if ($focusedProduct) {
            $modalProducts[] = $focusedProduct;
        }
    }
}

// Get total count
$countParams = array_slice($params, 0, count($params) - 2);
$totalResult = $db->fetch($countQuery, $countParams);
$totalProducts = $totalResult['total'];
$totalPages = ceil($totalProducts / $perPage);
$paginationBaseQuery = http_build_query([
    'category' => $category,
    'frame_target' => $frameTarget,
    'min_price' => $minPrice,
    'max_price' => $maxPrice,
    'search' => $searchQuery,
    'sort' => $sortBy,
    'try_on' => $tryOnFilter,
]);
$paginationWindowStart = max(1, $currentPage - 2);
$paginationWindowEnd = min($totalPages, $currentPage + 2);

if ($paginationWindowEnd - $paginationWindowStart < 4) {
    $paginationWindowStart = max(1, $paginationWindowEnd - 4);
    $paginationWindowEnd = min($totalPages, $paginationWindowStart + 4);
}

// Get categories
$categories = getProductCategoryOptions();
$frameTargets = getProductAudienceOptions();

// Get brands
$brands = $db->fetchAll("SELECT DISTINCT brand FROM products WHERE is_active = 1 ORDER BY brand");

$productModalCatalog = [];
foreach ($modalProducts as $product) {
    $productId = (int) $product['id'];
    $existingUserReview = $currentUser ? getProductReviewByUserId($productId, (int) $currentUser['id']) : null;
    $reviewPrefill = $productReviewPrefill[$productId] ?? [];
    $productReviews = getProductReviews($productId, 8);
    $rating = getProductRating($productId);

    $productModalCatalog[$productId] = [
        'id' => $productId,
        'name' => (string) ($product['name'] ?? ''),
        'brand' => (string) ($product['brand'] ?? ''),
        'category_label' => formatProductCategoryLabel($product['category'] ?? ''),
        'frame_target_label' => !empty($product['frame_target']) ? formatProductAudienceLabel($product['frame_target']) : '',
        'description' => decodeStoredText($product['description'] ?? ''),
        'price' => formatCurrency($product['price'] ?? 0),
        'stock' => (int) ($product['stock'] ?? 0),
        'gallery_images' => array_values(getProductGalleryImages($productId, $product)),
        'try_on_link' => getProductTryOnLink($product),
        'rating' => $rating,
        'review_errors' => $productReviewErrors[$productId] ?? [],
        'review_form' => [
            'rating' => (int) ($reviewPrefill['rating'] ?? ($existingUserReview['rating'] ?? 5)),
            'comment' => (string) ($reviewPrefill['comment'] ?? ($existingUserReview['comment'] ?? '')),
            'existing_review_image' => (string) ($existingUserReview['review_image'] ?? ''),
            'guest_name' => (string) ($reviewPrefill['guest_name'] ?? ($currentUser['name'] ?? ($existingUserReview['reviewer_name'] ?? ''))),
            'guest_email' => (string) ($reviewPrefill['guest_email'] ?? ($currentUser['email'] ?? ($existingUserReview['reviewer_email'] ?? ''))),
        ],
        'review_success_message' => $reviewSaved && $viewProductId === $productId ? 'Your product review was saved and is awaiting admin approval.' : '',
        'reviews' => array_map(static function ($review) {
            return [
                'reviewer_name' => (string) ($review['reviewer_name'] ?? 'Customer'),
                'profile_image_url' => getUserProfileImageUrl($review['reviewer_profile_image'] ?? '', $review['reviewer_name'] ?? 'Customer'),
                'review_image_url' => getProductReviewImageUrl($review['review_image'] ?? ''),
                'rating' => (int) ($review['rating'] ?? 0),
                'comment' => (string) ($review['comment'] ?? ''),
                'date' => formatDate($review['updated_at'] ?? $review['created_at']),
            ];
        }, $productReviews),
    ];
}

$productModalCatalogJson = json_encode(
    $productModalCatalog,
    JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_APOS
    | JSON_HEX_AMP
    | JSON_HEX_QUOT
    | JSON_INVALID_UTF8_SUBSTITUTE
);

if ($productModalCatalogJson === false) {
    $productModalCatalogJson = '{}';
}

$buildTeaserCommentPreview = static function (?string $comment): array {
    $comment = trim((string) $comment);
    if ($comment === '') {
        return [
            'comment' => '',
            'comment_preview' => '',
            'has_more' => false,
        ];
    }

    $previewLimit = 145;
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        $hasMore = mb_strlen($comment) > $previewLimit;
        $preview = $hasMore ? rtrim(mb_substr($comment, 0, $previewLimit - 1)) . '…' : $comment;
    } else {
        $hasMore = strlen($comment) > $previewLimit;
        $preview = $hasMore ? rtrim(substr($comment, 0, $previewLimit - 1)) . '…' : $comment;
    }

    return [
        'comment' => $comment,
        'comment_preview' => $preview,
        'has_more' => $hasMore,
    ];
};

$floatingReviewTeasers = array_merge(
    array_map(static function ($review) use ($buildTeaserCommentPreview) {
        $commentPreview = $buildTeaserCommentPreview($review['comment'] ?? '');
        return [
            'reviewer_name' => (string) ($review['reviewer_name'] ?? 'Customer'),
            'product_name' => 'Customer Testimonial',
            'comment' => $commentPreview['comment'],
            'comment_preview' => $commentPreview['comment_preview'],
            'has_more' => $commentPreview['has_more'],
            'rating' => (int) ($review['rating'] ?? 0),
            'profile_image_url' => getUserProfileImageUrl($review['profile_image'] ?? '', $review['reviewer_name'] ?? 'Customer'),
            'review_image_url' => '',
            'target_url' => APP_URL . '/reviews',
        ];
    }, getApprovedCustomerReviews(8)),
    array_map(static function ($review) use ($buildTeaserCommentPreview) {
        $commentPreview = $buildTeaserCommentPreview($review['comment'] ?? '');
        return [
            'reviewer_name' => (string) ($review['reviewer_name'] ?? 'Customer'),
            'product_name' => (string) ($review['product_name'] ?? 'Bealet frame'),
            'comment' => $commentPreview['comment'],
            'comment_preview' => $commentPreview['comment_preview'],
            'has_more' => $commentPreview['has_more'],
            'rating' => (int) ($review['rating'] ?? 0),
            'profile_image_url' => getUserProfileImageUrl($review['reviewer_profile_image'] ?? '', $review['reviewer_name'] ?? 'Customer'),
            'review_image_url' => getProductReviewImageUrl($review['review_image'] ?? ''),
            'target_url' => !empty($review['product_id']) ? APP_URL . '/shop.php?view_product=' . (int) $review['product_id'] : APP_URL . '/reviews',
        ];
    }, getRecentProductTestimonials(12))
);

$floatingReviewTeasersJson = json_encode(
    $floatingReviewTeasers,
    JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_APOS
    | JSON_HEX_AMP
    | JSON_HEX_QUOT
    | JSON_INVALID_UTF8_SUBSTITUTE
);

if ($floatingReviewTeasersJson === false) {
    $floatingReviewTeasersJson = '[]';
}

$productGroups = [];

foreach ($products as $product) {
    $normalizedCategory = normalizeProductCategoryKey($product['category'] ?? 'other');
    $groupKey = $normalizedCategory !== '' ? $normalizedCategory : 'other';
    $groupLabel = formatProductCategoryLabel($groupKey);

    if ($normalizedCategory === 'frames') {
        $targetKey = trim((string) ($product['frame_target'] ?? ''));
        if ($targetKey === '') {
            $targetKey = 'unisex';
        }

        $groupKey = 'frames_' . $targetKey;
        $groupLabel = formatProductAudienceLabel($targetKey);
    }

    if (!isset($productGroups[$groupKey])) {
        $productGroups[$groupKey] = [
            'label' => $groupLabel,
            'products' => [],
        ];
    }

    $productGroups[$groupKey]['products'][] = $product;
}

?>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mt-4 mb-4">
        <div class="container-lg">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/">Home</a></li>
                <li class="breadcrumb-item active">Shop</li>
            </ol>
        </div>
    </nav>
    
    <!-- Page Header -->
    <section class="mb-5">
        <div class="container-lg">
            <h1 class="mb-2">Our Collections</h1>
            <p class="text-muted">Browse our premium selection of eyewear</p>
        </div>
    </section>
    
    <!-- Shop Section -->
    <section class="section-spacing">
        <div class="container-lg">
            <div class="row g-4">
                <!-- Sidebar - Filters -->
                <div class="col-lg-3">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-filter me-2"></i> Filters</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" id="filterForm">
                                <!-- Search -->
                                <div class="mb-4">
                                    <label class="form-label">Search Products</label>
                                    <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo sanitize($searchQuery); ?>">
                                </div>
                                
                                <!-- Category -->
                                <div class="mb-4">
                                    <label class="form-label">Category</label>
                                    <div class="form-check">
                                        <input type="radio" name="category" value="" id="cat-all" class="form-check-input" <?php echo empty($category) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="cat-all">All Categories</label>
                                    </div>
                                    <?php foreach ($categories as $categoryValue => $categoryLabel): ?>
                                    <div class="form-check">
                                        <input type="radio" name="category" value="<?php echo sanitize($categoryValue); ?>" id="cat-<?php echo sanitize($categoryValue); ?>" class="form-check-input" <?php echo $category === $categoryValue ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="cat-<?php echo sanitize($categoryValue); ?>">
                                            <?php echo sanitize($categoryLabel); ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Frame Audience</label>
                                    <div class="form-check">
                                        <input type="radio" name="frame_target" value="" id="frame-all" class="form-check-input" <?php echo empty($frameTarget) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="frame-all">All Audiences</label>
                                    </div>
                                    <?php foreach ($frameTargets as $targetValue => $targetLabel): ?>
                                    <div class="form-check">
                                        <input type="radio" name="frame_target" value="<?php echo sanitize($targetValue); ?>" id="frame-<?php echo sanitize($targetValue); ?>" class="form-check-input" <?php echo $frameTarget === $targetValue ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="frame-<?php echo sanitize($targetValue); ?>">
                                            <?php echo sanitize($targetLabel); ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Price Range -->
                                <div class="mb-4">
                                    <label class="form-label">Price Range</label>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <input type="number" name="min_price" class="form-control" placeholder="Min" value="<?php echo $minPrice; ?>">
                                        </div>
                                        <div class="col-6">
                                            <input type="number" name="max_price" class="form-control" placeholder="Max" value="<?php echo $maxPrice; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Sort -->
                                <div class="mb-4">
                                    <label class="form-label">Sort By</label>
                                    <select name="sort" class="form-select">
                                        <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest</option>
                                        <option value="popular" <?php echo $sortBy === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                                        <option value="price_low" <?php echo $sortBy === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                        <option value="price_high" <?php echo $sortBy === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Try-On</label>
                                    <div class="form-check">
                                        <input type="radio" name="try_on" value="" id="tryon-all" class="form-check-input" <?php echo $tryOnFilter === '' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="tryon-all">All Products</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="try_on" value="ready" id="tryon-ready" class="form-check-input" <?php echo $tryOnFilter === 'ready' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="tryon-ready">Try-On Ready</label>
                                    </div>
                                </div>
                                
                                <!-- Buttons -->
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                                    <a href="<?php echo APP_URL; ?>/shop.php" class="btn btn-outline-primary">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Products Grid -->
                <div class="col-lg-9">
                    <!-- Results Info -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <p class="mb-0 text-muted">
                                Showing <strong><?php echo $totalProducts > 0 ? ((($currentPage - 1) * $perPage) + 1) : 0; ?></strong> to 
                                <strong><?php echo $totalProducts > 0 ? min($currentPage * $perPage, $totalProducts) : 0; ?></strong> 
                                of <strong><?php echo $totalProducts; ?></strong> products
                            </p>
                        </div>
                    </div>
                    
                    <?php if (!empty($products)): ?>
                    <?php foreach ($productGroups as $groupKey => $group): ?>
                    <section class="shop-group-section mb-5">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <div>
                                <h2 class="h4 mb-1"><?php echo sanitize($group['label']); ?></h2>
                                <p class="text-muted mb-0"><?php echo count($group['products']); ?> product<?php echo count($group['products']) === 1 ? '' : 's'; ?> in this section.</p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <?php foreach ($group['products'] as $product): ?>
                            <?php $productId = (int) $product['id']; ?>
                            <?php $galleryImages = getProductGalleryImages($productId, $product); ?>
                            <?php $productTryOnLink = getProductTryOnLink($product); ?>
                            <?php
                            $productPayloadJson = htmlspecialchars(
                                json_encode(
                                    $productModalCatalog[$productId] ?? [],
                                    JSON_UNESCAPED_SLASHES
                                    | JSON_HEX_TAG
                                    | JSON_HEX_APOS
                                    | JSON_HEX_AMP
                                    | JSON_HEX_QUOT
                                    | JSON_INVALID_UTF8_SUBSTITUTE
                                ) ?: '{}',
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card product-card h-100">
                                    <?php if (count($galleryImages) > 1): ?>
                                    <div id="productGallery<?php echo (int) $product['id']; ?>" class="carousel slide product-gallery-carousel" data-bs-interval="false">
                                        <div class="carousel-inner">
                                            <?php foreach ($galleryImages as $imageIndex => $galleryImage): ?>
                                            <div class="carousel-item <?php echo $imageIndex === 0 ? 'active' : ''; ?>">
                                                <img src="<?php echo $galleryImage; ?>" alt="<?php echo sanitize($product['name']); ?> image <?php echo $imageIndex + 1; ?>" class="product-image">
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button class="carousel-control-prev" type="button" data-bs-target="#productGallery<?php echo (int) $product['id']; ?>" data-bs-slide="prev" aria-label="Previous image">
                                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                        </button>
                                        <button class="carousel-control-next" type="button" data-bs-target="#productGallery<?php echo (int) $product['id']; ?>" data-bs-slide="next" aria-label="Next image">
                                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                        </button>
                                        <div class="product-gallery-dots">
                                            <?php foreach ($galleryImages as $imageIndex => $galleryImage): ?>
                                            <button type="button" data-bs-target="#productGallery<?php echo (int) $product['id']; ?>" data-bs-slide-to="<?php echo $imageIndex; ?>" class="<?php echo $imageIndex === 0 ? 'active' : ''; ?>" aria-label="View image <?php echo $imageIndex + 1; ?>"></button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <img src="<?php echo $galleryImages[0] ?? getProductImagePath($product); ?>" alt="<?php echo sanitize($product['name']); ?>" class="product-image">
                                    <?php endif; ?>
                                    <div class="product-info">
                                        <div class="product-name"><?php echo sanitize($product['name']); ?></div>
                                        <div class="product-price"><?php echo formatCurrency($product['price']); ?></div>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="badge text-bg-light border"><?php echo sanitize(formatProductCategoryLabel($product['category'] ?? '')); ?></span>
                                            <?php if (!empty($product['frame_target'])): ?>
                                            <span class="badge text-bg-light border"><?php echo sanitize(formatProductAudienceLabel($product['frame_target'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php
                                        $rating = getProductRating($product['id']);
                                        ?>
                                        <div class="product-rating">
                                            <div>
                                                <?php for ($i = 0; $i < 5; $i++): ?>
                                                <i class="fas fa-star star" style="color: <?php echo $i < $rating['average'] ? 'var(--warning)' : '#E5E7EB'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="rating-text"><?php echo $rating['average']; ?>/5</span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <?php if ($product['stock'] > 0): ?>
                                            <span class="badge badge-success">In Stock</span>
                                            <?php else: ?>
                                            <span class="badge badge-danger">Out of Stock</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <?php if ($productTryOnLink !== ''): ?>
                                            <a class="btn product-tryon-cta" href="<?php echo sanitize($productTryOnLink); ?>">
                                                <i class="fas fa-vr-cardboard me-2"></i> Try On
                                            </a>
                                            <?php endif; ?>
                                            <button 
                                                class="btn btn-primary" 
                                                type="button"
                                                onclick="addToCart(<?php echo $product['id']; ?>, 1)"
                                                <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>
                                            >
                                                <i class="fas fa-shopping-cart me-2"></i> Add to Cart
                                            </button>
                                            <button
                                                class="btn btn-outline-primary"
                                                type="button"
                                                data-product-modal="<?php echo $productId; ?>"
                                                data-product-payload="<?php echo $productPayloadJson; ?>"
                                                onclick="stopProductCardGalleryAutoplay(this); return openProductModalFromButton(this);"
                                            >
                                                <i class="fas fa-eye me-2"></i> View Product
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endforeach; ?>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-5">
                        <ul class="pagination justify-content-center">
                            <?php if ($currentPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo APP_URL; ?>/shop.php?page=<?php echo $currentPage - 1; ?>&<?php echo $paginationBaseQuery; ?>">
                                    Previous
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php if ($paginationWindowStart > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo APP_URL; ?>/shop.php?page=1&<?php echo $paginationBaseQuery; ?>">1</a>
                            </li>
                            <?php if ($paginationWindowStart > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $paginationWindowStart; $i <= $paginationWindowEnd; $i++): ?>
                            <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo APP_URL; ?>/shop.php?page=<?php echo $i; ?>&<?php echo $paginationBaseQuery; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($paginationWindowEnd < $totalPages): ?>
                            <?php if ($paginationWindowEnd < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo APP_URL; ?>/shop.php?page=<?php echo $totalPages; ?>&<?php echo $paginationBaseQuery; ?>"><?php echo $totalPages; ?></a>
                            </li>
                            <?php endif; ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo APP_URL; ?>/shop.php?page=<?php echo $currentPage + 1; ?>&<?php echo $paginationBaseQuery; ?>">
                                    Next
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <!-- No Products -->
                    <div class="text-center py-5">
                        <i class="fas fa-search" style="font-size: 4rem; color: #E5E7EB;"></i>
                        <h4 class="mt-3 text-muted">No products found</h4>
                        <p class="text-muted">Try adjusting your filters or search criteria</p>
                        <a href="<?php echo APP_URL; ?>/shop.php" class="btn btn-primary">View All Products</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($floatingReviewTeasers)): ?>
    <aside class="shop-review-teaser" id="shopReviewTeaser" aria-live="polite" aria-atomic="true" hidden>
        <div class="shop-review-teaser__panel">
            <div class="shop-review-teaser__label">Customer spotlight</div>
            <div class="shop-review-teaser__content">
                <div class="shop-review-teaser__image-shell">
                    <img src="" alt="" class="shop-review-teaser__image" id="shopReviewTeaserImage">
                </div>
                <div class="shop-review-teaser__copy">
                    <div class="shop-review-teaser__heading">
                        <strong id="shopReviewTeaserName">Customer</strong>
                        <span id="shopReviewTeaserProduct">Bealet frame</span>
                    </div>
                    <div class="shop-review-teaser__stars" id="shopReviewTeaserStars"></div>
                    <p id="shopReviewTeaserComment" class="mb-0"></p>
                    <a class="shop-review-teaser__action" id="shopReviewTeaserLink" href="<?php echo APP_URL; ?>/reviews">Read more</a>
                </div>
            </div>
        </div>
    </aside>
    <?php endif; ?>

    <div class="modal fade" id="productImageModal" tabindex="-1" aria-labelledby="productImageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content product-detail-modal">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="productImageModalLabel">Product Details</h5>
                        <p class="text-muted small mb-0" id="productModalMeta"></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div id="productImageModalCarousel" class="carousel slide product-detail-carousel" data-bs-interval="false">
                                <div class="carousel-inner" id="productImageModalCarouselInner"></div>
                                <button class="carousel-control-prev" type="button" data-bs-target="#productImageModalCarousel" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Previous</span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#productImageModalCarousel" data-bs-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Next</span>
                                </button>
                            </div>
                            <div class="product-modal-thumbs" id="productModalThumbs"></div>
                        </div>
                        <div class="col-lg-6">
                            <div class="product-modal-summary">
                                <div class="d-flex flex-wrap gap-2 mb-3" id="productModalBadges"></div>
                                <div class="product-modal-price" id="productModalPrice"></div>
                                <div class="product-rating mb-3" id="productModalRating"></div>
                                <p class="text-muted mb-3" id="productModalDescription"></p>
                                <div class="d-flex flex-wrap gap-2 mb-4">
                                    <button class="btn btn-primary" type="button" id="productModalAddToCartButton">
                                        <i class="fas fa-shopping-cart me-2"></i> Add to Cart
                                    </button>
                                    <a class="btn product-tryon-cta d-none" href="#" id="productModalTryOnLink">
                                        <i class="fas fa-vr-cardboard me-2"></i> Try On
                                    </a>
                                </div>
                            </div>

                            <div class="product-modal-review-block mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h6 class="mb-1">Customer Photos & Reviews</h6>
                                        <p class="text-muted small mb-0">Customers can share a thumbnail of the frame they bought or a photo of themselves wearing it.</p>
                                    </div>
                                </div>
                                <div id="productModalReviewList" class="d-grid gap-3"></div>
                            </div>

                            <div class="product-modal-review-form-block">
                                <h6 class="mb-3">Add Your Product Review</h6>
                                <form method="POST" enctype="multipart/form-data" id="productReviewForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="submit_product_review" value="1">
                                    <input type="hidden" name="review_product_id" id="reviewProductId" value="">
                                    <input type="hidden" name="existing_review_image" id="existingReviewImage" value="">

                                    <?php if (!$currentUser): ?>
                                    <div class="row g-3">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label" for="productReviewGuestName">Your Name</label>
                                            <input type="text" class="form-control" name="guest_name" id="productReviewGuestName" placeholder="Enter your name">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label" for="productReviewGuestEmail">Your Email</label>
                                            <input type="email" class="form-control" name="guest_email" id="productReviewGuestEmail" placeholder="Enter your email">
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label class="form-label" for="productReviewRating">Your Rating</label>
                                        <select class="form-select" name="rating" id="productReviewRating" required>
                                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?> Star<?php echo $i === 1 ? '' : 's'; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="productReviewComment">Your Comment</label>
                                        <textarea class="form-control" name="comment" id="productReviewComment" rows="4" placeholder="Tell other shoppers how the frame looked, fit, and felt." required></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="productReviewImage">Photo Thumbnail</label>
                                        <input type="file" class="form-control" name="review_image" id="productReviewImage" accept=".jpg,.jpeg,.png,.gif,.webp">
                                        <small class="text-muted">A small image of the product or you wearing it will appear as a neat thumbnail beside your review.</small>
                                    </div>

                                    <div id="productReviewSuccess" class="alert alert-success d-none"></div>
                                    <div id="productReviewErrors" class="alert alert-danger d-none"></div>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-paper-plane me-2"></i> Save Product Review
                                    </button>
                                </form>
                                <?php if (!$currentUser): ?>
                                <p class="text-muted small mt-3 mb-0">Guests can submit a product review with their name and email. If you already have an account, you can still login and your review stays tied to your profile.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .product-detail-modal {
        border-radius: 1.5rem;
        overflow: hidden;
    }

    .product-detail-modal .modal-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        border-bottom: none;
    }

    .product-detail-modal .modal-title,
    .product-detail-modal #productModalMeta {
        color: #fff !important;
    }

    .product-detail-modal .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
    }

    .product-detail-carousel {
        background: #f8fafc;
        border-radius: 1.25rem;
        overflow: hidden;
    }

    .product-modal-image {
        max-height: 58vh;
        object-fit: contain;
        width: 100%;
        background: #fff;
    }

    .product-modal-thumbs {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(72px, 1fr));
        gap: 0.75rem;
        margin-top: 1rem;
    }

    .product-modal-thumb {
        width: 100%;
        aspect-ratio: 1;
        object-fit: cover;
        border-radius: 0.95rem;
        border: 2px solid transparent;
        background: #fff;
        cursor: pointer;
    }

    .product-modal-thumb.is-active {
        border-color: #2563eb;
        box-shadow: 0 10px 24px rgba(37, 99, 235, 0.16);
    }

    .product-modal-price {
        font-size: 1.7rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
    }

    .product-modal-review-card {
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 1.1rem;
        padding: 1rem;
        background: #fff;
    }

    .product-review-photo {
        width: 82px;
        height: 82px;
        object-fit: cover;
        border-radius: 1rem;
        border: 1px solid rgba(148, 163, 184, 0.22);
        background: #f8fafc;
        flex-shrink: 0;
    }

    .shop-review-teaser {
        position: fixed;
        right: 0;
        top: 50%;
        bottom: auto;
        transform: translate3d(calc(100% - 38px), -50%, 0);
        z-index: 1030;
        transition: transform 0.7s cubic-bezier(0.22, 1, 0.36, 1);
    }

    .shop-review-teaser.is-visible {
        transform: translate3d(0, -50%, 0);
    }

    .shop-review-teaser__panel {
        display: block;
        width: min(214px, calc(100vw - 18px));
        background: rgba(15, 23, 42, 0.96);
        color: #fff;
        border-radius: 1.4rem 0 0 1.4rem;
        box-shadow: 0 28px 55px rgba(15, 23, 42, 0.24);
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .shop-review-teaser__label {
        font-size: 0.72rem;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        padding: 0.7rem 1rem 0.25rem;
        color: rgba(255, 255, 255, 0.72);
    }

    .shop-review-teaser__content {
        display: block;
        padding: 0 0.9rem 1rem;
    }

    .shop-review-teaser__image-shell {
        width: calc(100% + 1.8rem);
        height: 148px;
        margin: 0 -0.9rem 0.85rem;
        padding: 0.45rem;
        background: linear-gradient(180deg, rgba(30, 41, 59, 0.96), rgba(15, 23, 42, 1));
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .shop-review-teaser__image {
        width: 100%;
        height: 100%;
        margin: 0;
        border-radius: 0.95rem;
        object-fit: contain;
        border: 0;
        background: rgba(255, 255, 255, 0.08);
    }

    .shop-review-teaser__copy {
        min-width: 0;
    }

    .shop-review-teaser__heading {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
        margin-bottom: 0.35rem;
    }

    .shop-review-teaser__heading strong,
    .shop-review-teaser__heading span {
        white-space: normal;
        overflow: visible;
        text-overflow: unset;
    }

    .shop-review-teaser__heading span {
        color: rgba(255, 255, 255, 0.72);
        font-size: 0.88rem;
    }

    .shop-review-teaser__stars {
        color: #fbbf24;
        font-size: 0.82rem;
        margin-bottom: 0.45rem;
    }

    .shop-review-teaser__copy p {
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.86rem;
        line-height: 1.45;
        white-space: normal;
        overflow: hidden;
        min-height: 4.2rem;
        margin-bottom: 0.7rem !important;
    }

    .shop-review-teaser__action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 0.45rem 0.8rem;
        border-radius: 999px;
        background: #f8d07a;
        color: #152235;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        text-decoration: none;
    }

    .shop-review-teaser__action:hover,
    .shop-review-teaser__action:focus {
        color: #152235;
        background: #ffe09f;
    }

    @media (max-width: 768px) {
        .modal-dialog.modal-xl {
            max-width: 98vw;
            margin: 0.5rem auto;
        }

        .product-modal-image {
            max-height: 42vh;
        }

        .shop-review-teaser {
            top: 50%;
            bottom: auto;
            transform: translate3d(calc(100% - 42px), -50%, 0);
        }

        .shop-review-teaser.is-visible {
            transform: translate3d(0, -50%, 0);
        }

        .shop-review-teaser__panel {
            width: min(198px, calc(100vw - 12px));
        }

        .shop-review-teaser__image-shell {
            height: 132px;
        }
    }

    @media (max-width: 480px) {
        .product-review-photo {
            width: 68px;
            height: 68px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .shop-review-teaser {
            transition: none;
        }
    }
    </style>

    <script>
    (function () {
        var productCatalog = <?php echo $productModalCatalogJson; ?>;
        var floatingReviewTeasers = <?php echo $floatingReviewTeasersJson; ?>;
        var modalElement = document.getElementById('productImageModal');
        var modalTitle = document.getElementById('productImageModalLabel');
        var modalMeta = document.getElementById('productModalMeta');
        var carouselInner = document.getElementById('productImageModalCarouselInner');
        var carouselElement = document.getElementById('productImageModalCarousel');
        var thumbsElement = document.getElementById('productModalThumbs');
        var badgesElement = document.getElementById('productModalBadges');
        var priceElement = document.getElementById('productModalPrice');
        var ratingElement = document.getElementById('productModalRating');
        var descriptionElement = document.getElementById('productModalDescription');
        var reviewListElement = document.getElementById('productModalReviewList');
        var tryOnLinkElement = document.getElementById('productModalTryOnLink');
        var addToCartButton = document.getElementById('productModalAddToCartButton');
        var reviewFormElement = document.getElementById('productReviewForm');
        var reviewProductIdInput = document.getElementById('reviewProductId');
        var existingReviewImageInput = document.getElementById('existingReviewImage');
        var reviewRatingInput = document.getElementById('productReviewRating');
        var reviewCommentInput = document.getElementById('productReviewComment');
        var reviewImageInput = document.getElementById('productReviewImage');
        var reviewGuestNameInput = document.getElementById('productReviewGuestName');
        var reviewGuestEmailInput = document.getElementById('productReviewGuestEmail');
        var reviewSubmitButton = reviewFormElement ? reviewFormElement.querySelector('button[type="submit"]') : null;
        var reviewSuccessElement = document.getElementById('productReviewSuccess');
        var reviewErrorsElement = document.getElementById('productReviewErrors');
        var reviewTeaserElement = document.getElementById('shopReviewTeaser');
        var reviewTeaserLinkElement = document.getElementById('shopReviewTeaserLink');
        var reviewTeaserImageElement = document.getElementById('shopReviewTeaserImage');
        var reviewTeaserNameElement = document.getElementById('shopReviewTeaserName');
        var reviewTeaserProductElement = document.getElementById('shopReviewTeaserProduct');
        var reviewTeaserStarsElement = document.getElementById('shopReviewTeaserStars');
        var reviewTeaserCommentElement = document.getElementById('shopReviewTeaserComment');
        var modalInstance = null;
        var activeCarouselInstance = null;
        var activeProductId = 0;
        var reviewTeaserIndex = -1;
        var reviewTeaserVisibleTimeout = null;
        var reviewTeaserCycleTimeout = null;

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderStars(rating) {
            var html = '';
            for (var i = 1; i <= 5; i++) {
                html += '<i class="' + (i <= rating ? 'fas' : 'far') + ' fa-star star" style="color: ' + (i <= rating ? 'var(--warning)' : '#E5E7EB') + ';"></i>';
            }
            return html;
        }

        function renderTinyStars(rating) {
            var html = '';
            for (var i = 1; i <= 5; i++) {
                html += '<i class="' + (i <= rating ? 'fas' : 'far') + ' fa-star"></i>';
            }
            return html;
        }

        function updateThumbState(activeIndex) {
            thumbsElement.querySelectorAll('.product-modal-thumb').forEach(function (thumb, index) {
                thumb.classList.toggle('is-active', index === activeIndex);
            });
        }

        function buildGallery(product) {
            var images = Array.isArray(product.gallery_images) ? product.gallery_images.filter(Boolean) : [];
            if (images.length === 0) {
                images = ['<?php echo getProductImageUrl(null); ?>'];
            }

            carouselInner.innerHTML = '';
            thumbsElement.innerHTML = '';

            images.forEach(function (image, index) {
                var item = document.createElement('div');
                item.className = 'carousel-item' + (index === 0 ? ' active' : '');
                item.innerHTML = '<img class="d-block w-100 product-modal-image" src="' + escapeHtml(image) + '" alt="' + escapeHtml(product.name) + ' image ' + (index + 1) + '">';
                carouselInner.appendChild(item);

                var thumb = document.createElement('img');
                thumb.src = image;
                thumb.alt = product.name + ' thumbnail ' + (index + 1);
                thumb.className = 'product-modal-thumb' + (index === 0 ? ' is-active' : '');
                thumb.addEventListener('click', function () {
                    if (activeCarouselInstance) {
                        activeCarouselInstance.to(index);
                    }
                });
                thumbsElement.appendChild(thumb);
            });

            var carouselInstance = bootstrap.Carousel.getInstance(carouselElement);
            if (carouselInstance) {
                carouselInstance.dispose();
            }

            activeCarouselInstance = new bootstrap.Carousel(carouselElement, { interval: false });
            carouselElement.addEventListener('slid.bs.carousel', function (event) {
                updateThumbState(event.to || 0);
            });
        }

        function buildReviews(product) {
            var reviews = Array.isArray(product.reviews) ? product.reviews : [];
            if (reviews.length === 0) {
                reviewListElement.innerHTML = '<div class="product-modal-review-card text-muted">No product reviews yet. Be the first to share how this frame looked and felt.</div>';
                return;
            }

            reviewListElement.innerHTML = reviews.map(function (review) {
                var reviewImage = review.review_image_url
                    ? '<img class="product-review-photo" src="' + escapeHtml(review.review_image_url) + '" alt="' + escapeHtml(review.reviewer_name) + ' review photo">'
                    : '';
                return '' +
                    '<article class="product-modal-review-card">' +
                        '<div class="d-flex gap-3 align-items-start">' +
                            '<img class="review-avatar" src="' + escapeHtml(review.profile_image_url) + '" alt="' + escapeHtml(review.reviewer_name) + '">' +
                            '<div class="flex-grow-1">' +
                                '<div class="d-flex flex-wrap justify-content-between gap-2 mb-2">' +
                                    '<div><strong>' + escapeHtml(review.reviewer_name) + '</strong><div class="small">' + renderStars(Number(review.rating || 0)) + '</div></div>' +
                                    '<small class="text-muted">' + escapeHtml(review.date) + '</small>' +
                                '</div>' +
                                '<p class="mb-0 text-muted">' + escapeHtml(review.comment) + '</p>' +
                            '</div>' +
                            reviewImage +
                        '</div>' +
                    '</article>';
            }).join('');
        }

        function renderRatingSummary(product) {
            var rating = product.rating || {};
            ratingElement.innerHTML = '<div>' + renderStars(Number(rating.average ? Math.round(rating.average) : 0)) + '</div><span class="rating-text">' + escapeHtml(rating.average || 0) + '/5 (' + escapeHtml(rating.total || 0) + ' reviews)</span>';
        }

        function showReviewErrors(errors) {
            if (!reviewErrorsElement) {
                return;
            }

            var errorList = Array.isArray(errors) ? errors.filter(Boolean) : [];
            if (errorList.length === 0) {
                reviewErrorsElement.classList.add('d-none');
                reviewErrorsElement.innerHTML = '';
                return;
            }

            reviewErrorsElement.classList.remove('d-none');
            reviewErrorsElement.innerHTML = errorList.map(function (error) {
                return '<div>' + escapeHtml(error) + '</div>';
            }).join('');
        }

        function showReviewSuccess(message) {
            if (!reviewSuccessElement) {
                return;
            }

            if (message) {
                reviewSuccessElement.classList.remove('d-none');
                reviewSuccessElement.textContent = message;
                return;
            }

            reviewSuccessElement.classList.add('d-none');
            reviewSuccessElement.textContent = '';
        }

        function setReviewSubmittingState(isSubmitting) {
            if (!reviewSubmitButton) {
                return;
            }

            reviewSubmitButton.disabled = isSubmitting;
            reviewSubmitButton.innerHTML = isSubmitting
                ? '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Saving Review...'
                : '<i class="fas fa-paper-plane me-2"></i> Save Product Review';
        }

        function initializeProductCardGalleries() {
            if (typeof bootstrap === 'undefined') {
                return;
            }

            document.querySelectorAll('.product-gallery-carousel').forEach(function (carouselNode) {
                var carousel = bootstrap.Carousel.getOrCreateInstance(carouselNode, {
                    interval: 2600,
                    ride: false,
                    pause: false,
                    wrap: true,
                    touch: true
                });

                carousel.cycle();

                carouselNode.addEventListener('mouseenter', function () {
                    carousel.pause();
                });

                carouselNode.addEventListener('mouseleave', function () {
                    carousel.cycle();
                });
            });
        }

        window.stopProductCardGalleryAutoplay = function (button) {
            if (typeof bootstrap === 'undefined' || !button) {
                return;
            }

            var card = button.closest('.product-card');
            var carouselNode = card ? card.querySelector('.product-gallery-carousel') : null;
            if (!carouselNode) {
                return;
            }

            var carousel = bootstrap.Carousel.getInstance(carouselNode);
            if (carousel) {
                carousel.pause();
            }
        };

        function scheduleReviewTeaser() {
            if (!reviewTeaserElement || floatingReviewTeasers.length === 0) {
                return;
            }

            clearTimeout(reviewTeaserVisibleTimeout);
            clearTimeout(reviewTeaserCycleTimeout);
            reviewTeaserCycleTimeout = window.setTimeout(showNextReviewTeaser, 10000);
        }

        function hideReviewTeaser(scheduleNext) {
            if (!reviewTeaserElement) {
                return;
            }

            reviewTeaserElement.classList.remove('is-visible');
            if (scheduleNext) {
                scheduleReviewTeaser();
            }
        }

        function showNextReviewTeaser() {
            if (!reviewTeaserElement || floatingReviewTeasers.length === 0) {
                return;
            }

            reviewTeaserIndex = (reviewTeaserIndex + 1) % floatingReviewTeasers.length;
            var teaser = floatingReviewTeasers[reviewTeaserIndex] || {};
            var imageUrl = teaser.review_image_url || teaser.profile_image_url || '';

            reviewTeaserElement.hidden = false;
            if (reviewTeaserImageElement) {
                reviewTeaserImageElement.src = imageUrl;
                reviewTeaserImageElement.alt = (teaser.reviewer_name || 'Customer') + ' review photo';
            }
            if (reviewTeaserLinkElement) {
                reviewTeaserLinkElement.href = teaser.target_url || '<?php echo APP_URL; ?>/reviews';
            }
            if (reviewTeaserNameElement) {
                reviewTeaserNameElement.textContent = teaser.reviewer_name || 'Customer';
            }
            if (reviewTeaserProductElement) {
                reviewTeaserProductElement.textContent = teaser.product_name || 'Bealet frame';
            }
            if (reviewTeaserStarsElement) {
                reviewTeaserStarsElement.innerHTML = renderTinyStars(Number(teaser.rating || 0));
            }
            if (reviewTeaserCommentElement) {
                reviewTeaserCommentElement.textContent = teaser.comment_preview || teaser.comment || 'A recent customer shared a lovely Bealet moment.';
            }
            if (reviewTeaserLinkElement) {
                reviewTeaserLinkElement.textContent = teaser.has_more ? 'Read more' : 'Open review';
            }

            window.requestAnimationFrame(function () {
                reviewTeaserElement.classList.add('is-visible');
            });

            clearTimeout(reviewTeaserVisibleTimeout);
            reviewTeaserVisibleTimeout = window.setTimeout(function () {
                hideReviewTeaser(true);
            }, 8000);
        }

        function buildBadges(product) {
            var badges = [];
            if (product.category_label) {
                badges.push('<span class="badge text-bg-light border">' + escapeHtml(product.category_label) + '</span>');
            }
            if (product.frame_target_label) {
                badges.push('<span class="badge text-bg-light border">' + escapeHtml(product.frame_target_label) + '</span>');
            }
            if (product.stock > 0) {
                badges.push('<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">In Stock</span>');
            } else {
                badges.push('<span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">Out of Stock</span>');
            }
            badgesElement.innerHTML = badges.join('');
        }

        function populateReviewForm(product) {
            if (!reviewProductIdInput) {
                return;
            }

            reviewProductIdInput.value = product.id || '';
            var reviewForm = product.review_form || {};
            existingReviewImageInput.value = reviewForm.existing_review_image || '';
            reviewRatingInput.value = String(reviewForm.rating || 5);
            reviewCommentInput.value = reviewForm.comment || '';
            if (reviewGuestNameInput) {
                reviewGuestNameInput.value = reviewForm.guest_name || '';
            }
            if (reviewGuestEmailInput) {
                reviewGuestEmailInput.value = reviewForm.guest_email || '';
            }
            if (reviewImageInput) {
                reviewImageInput.value = '';
            }

            showReviewSuccess(product.review_success_message || '');
            showReviewErrors(product.review_errors || []);
        }

        function openProductModal(product) {
            if (!product || !modalElement || typeof bootstrap === 'undefined') {
                return false;
            }

            activeProductId = Number(product.id || 0);
            modalTitle.textContent = product.name || 'Product Details';
            modalMeta.textContent = product.brand ? (product.brand + ' collection') : 'Bealet collection';
            priceElement.textContent = product.price || '';
            renderRatingSummary(product);
            descriptionElement.textContent = product.description || 'Premium eyewear from our latest collection.';
            buildBadges(product);
            buildGallery(product);
            buildReviews(product);
            populateReviewForm(product);

            addToCartButton.disabled = Number(product.stock || 0) <= 0;
            addToCartButton.onclick = function () {
                addToCart(Number(product.id || 0), 1);
            };

            if (product.try_on_link) {
                tryOnLinkElement.href = product.try_on_link;
                tryOnLinkElement.classList.remove('d-none');
            } else {
                tryOnLinkElement.href = '#';
                tryOnLinkElement.classList.add('d-none');
            }

            if (!modalInstance) {
                modalInstance = new bootstrap.Modal(modalElement);
            }

            modalInstance.show();
            return false;
        }

        window.openProductModalFromButton = function (button) {
            try {
                var rawPayload = button?.getAttribute('data-product-payload') || '{}';
                var product = JSON.parse(rawPayload);
                var cachedProduct = productCatalog[product.id];
                if (cachedProduct) {
                    product = Object.assign({}, product, cachedProduct);
                }
                return openProductModal(product);
            } catch (error) {
                console.error('Unable to open product modal.', error);
                return false;
            }
        };

        async function saveProductReview(event) {
            event.preventDefault();
            if (!reviewFormElement) {
                return;
            }

            showReviewSuccess('');
            showReviewErrors([]);
            setReviewSubmittingState(true);

            try {
                var response = await fetch(buildApiUrl('save-product-review'), {
                    method: 'POST',
                    body: new FormData(reviewFormElement)
                });
                var data = await response.json();

                if (!response.ok || !data.success) {
                    showReviewErrors(data.errors || [data.message || 'We could not save your product review right now.']);
                    showToastNotification(data.message || 'Unable to save product review', 'error');
                    return;
                }

                var updatedProductId = Number(data.product_id || reviewProductIdInput.value || activeProductId || 0);
                var cachedProduct = productCatalog[updatedProductId] || {};
                cachedProduct.id = updatedProductId;
                cachedProduct.rating = data.rating || cachedProduct.rating || { average: 0, total: 0 };
                cachedProduct.reviews = Array.isArray(data.reviews) ? data.reviews : [];
                cachedProduct.review_form = data.review_form || cachedProduct.review_form || {};
                cachedProduct.review_errors = [];
                cachedProduct.review_success_message = data.message || 'Your product review was saved and is awaiting admin approval.';
                productCatalog[updatedProductId] = cachedProduct;

                if (activeProductId === updatedProductId) {
                    renderRatingSummary(cachedProduct);
                    buildReviews(cachedProduct);
                    populateReviewForm(cachedProduct);
                }

                showReviewSuccess(cachedProduct.review_success_message);
                if (reviewImageInput) {
                    reviewImageInput.value = '';
                }
                showToastNotification(cachedProduct.review_success_message, 'success');
            } catch (error) {
                console.error('Unable to save product review.', error);
                showReviewErrors(['We could not save your product review right now. Please try again.']);
                showToastNotification('Unable to save product review', 'error');
            } finally {
                setReviewSubmittingState(false);
            }
        }

        if (reviewFormElement) {
            reviewFormElement.addEventListener('submit', saveProductReview);
        }

        document.addEventListener('DOMContentLoaded', function () {
            initializeProductCardGalleries();
            scheduleReviewTeaser();

            var requestedProductId = <?php echo (int) $viewProductId; ?>;
            if (requestedProductId > 0) {
                var requestedProduct = productCatalog[String(requestedProductId)] || productCatalog[requestedProductId];
                if (requestedProduct) {
                    openProductModal(requestedProduct);
                    if (window.history && typeof window.history.replaceState === 'function') {
                        window.history.replaceState({}, document.title, '<?php echo APP_URL; ?>/shop.php');
                    }
                }
            }
        });
    })();
    </script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
