<?php
/**
 * Bealet Website - Wishlist
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

$user = getCurrentUser();

// Get wishlist items
$wishlistItems = $db->fetchAll(
    "SELECT p.*, p.main_image as image FROM wishlist w
     JOIN products p ON w.product_id = p.id
     WHERE w.user_id = ? AND p.is_active = 1
     ORDER BY w.created_at DESC",
    [$user['id']]
);

?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1>My Wishlist</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item active">Wishlist</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Wishlist Content -->
    <div class="container my-5">
        <?php if (empty($wishlistItems)): ?>
        <div class="text-center py-5">
            <i class="fas fa-heart" style="font-size: 4rem; color: #ccc; margin-bottom: 1rem;"></i>
            <h3>Your wishlist is empty</h3>
            <p class="text-muted mb-4">Add products to your wishlist to keep track of your favorite items</p>
            <a href="<?php echo APP_URL; ?>/shop.php" class="btn btn-primary">
                <i class="fas fa-shopping-bag me-2"></i> Browse Products
            </a>
        </div>
        <?php else: ?>
        <div class="row mb-3">
            <div class="col-12">
                <p class="text-muted"><?php echo count($wishlistItems); ?> item(s) in your wishlist</p>
            </div>
        </div>
        
        <div class="row g-4">
            <?php foreach ($wishlistItems as $product): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card product-card h-100">
                    <div class="product-image-wrapper">
                        <img src="<?php echo getProductImagePath($product); ?>" 
                             alt="<?php echo sanitize($product['name']); ?>" class="card-img-top">
                        <button class="wishlist-btn active" data-product-id="<?php echo (int) $product['id']; ?>" type="button" onclick="toggleWishlist(<?php echo (int) $product['id']; ?>, this)" title="Remove from wishlist">
                            <i class="fas fa-heart"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title"><?php echo sanitize($product['name']); ?></h5>
                        <p class="card-text text-muted small"><?php echo sanitize($product['brand']); ?></p>
                        
                        <!-- Rating -->
                        <div class="rating-display mb-2">
                            <?php 
                            $rating = getProductRating($product['id']);
                            for ($i = 0; $i < 5; $i++) {
                                echo $i < round((float) ($rating['average'] ?? 0)) ? '<i class="fas fa-star text-warning"></i>' : '<i class="far fa-star text-muted"></i>';
                            }
                            ?>
                            <span class="rating-text">(<?php echo (int) ($rating['total'] ?? 0); ?> reviews)</span>
                        </div>
                        
                        <p class="card-text">
                            <strong class="text-primary"><?php echo formatCurrency($product['price']); ?></strong>
                        </p>
                        
                        <div class="d-grid gap-2">
                            <?php if ($product['stock'] > 0): ?>
                            <button class="btn btn-sm btn-primary w-100" type="button" onclick="addToCart(<?php echo (int) $product['id']; ?>, 1)">
                                <i class="fas fa-shopping-cart me-2"></i> Add to Cart
                            </button>
                            <?php else: ?>
                            <button class="btn btn-sm btn-secondary w-100" disabled>Out of Stock</button>
                            <?php endif; ?>

                            <button class="btn btn-sm btn-outline-danger w-100" type="button" onclick="removeFromWishlist(<?php echo (int) $product['id']; ?>, this)">
                                <i class="fas fa-trash-alt me-2"></i> Delete from Wishlist
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
