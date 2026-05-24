<?php
/**
 * Bealet Website - Home Page
 */

require_once __DIR__ . '/inc/header.php';

global $db;

// Get featured products
$productColumns = $db->fetchAll("SHOW COLUMNS FROM products");
$hasFeaturedProductsColumn = false;
foreach ($productColumns as $column) {
    if (($column['Field'] ?? '') === 'is_featured') {
        $hasFeaturedProductsColumn = true;
        break;
    }
}

if ($hasFeaturedProductsColumn) {
    $featuredProducts = $db->fetchAll(
        "SELECT * FROM products WHERE is_active = 1 AND is_featured = 1 ORDER BY id DESC LIMIT 6"
    );

    if (count($featuredProducts) < 6) {
        $excludeIds = array_map(static fn($product) => (int) $product['id'], $featuredProducts);
        $fallbackQuery = "SELECT * FROM products WHERE is_active = 1";
        $fallbackParams = [];

        if (!empty($excludeIds)) {
            $placeholders = implode(', ', array_fill(0, count($excludeIds), '?'));
            $fallbackQuery .= " AND id NOT IN ($placeholders)";
            $fallbackParams = $excludeIds;
        }

        $fallbackQuery .= " ORDER BY id DESC LIMIT " . max(0, 6 - count($featuredProducts));
        $featuredProducts = array_merge($featuredProducts, $db->fetchAll($fallbackQuery, $fallbackParams));
    }
} else {
    $featuredProducts = $db->fetchAll(
        "SELECT * FROM products WHERE is_active = 1 ORDER BY id DESC LIMIT 6"
    );
}

// Get blog posts
$blogPosts = $db->fetchAll(
    "SELECT * FROM blog_posts WHERE is_published = 1 ORDER BY created_at DESC LIMIT 3"
);

$homepageRawProductTestimonials = getRecentProductTestimonials(6);
$homepageAllProductTestimonials = getRecentProductTestimonials(null);

$homepageCustomerReviews = array_map(static function ($review) {
    $review['review_type'] = 'customer';
    $review['display_title'] = 'Customer Testimonial';
    $review['display_image_url'] = getUserProfileImageUrl($review['profile_image'] ?? '', $review['reviewer_name'] ?? 'Customer');
    $review['display_date'] = $review['updated_at'] ?? $review['created_at'] ?? null;

    return $review;
}, getApprovedCustomerReviews(6));

$homepageProductTestimonials = array_map(static function ($review) {
    $review['review_type'] = 'product';
    $review['display_title'] = !empty($review['product_name']) ? ((string) $review['product_name'] . ' testimonial') : 'Product Testimonial';
    $review['display_image_url'] = getProductReviewImageUrl($review['review_image'] ?? '');
    $review['display_date'] = $review['updated_at'] ?? $review['created_at'] ?? null;

    return $review;
}, $homepageRawProductTestimonials);

$homepageReviews = array_merge($homepageCustomerReviews, $homepageProductTestimonials);
usort($homepageReviews, static function ($left, $right) {
    $leftTime = strtotime((string) ($left['display_date'] ?? '')) ?: 0;
    $rightTime = strtotime((string) ($right['display_date'] ?? '')) ?: 0;

    return $rightTime <=> $leftTime;
});
$homepageReviews = array_slice($homepageReviews, 0, 3);

$homepageCustomerReviewSummary = getCustomerReviewSummary();
$homepageProductReviewCount = count($homepageAllProductTestimonials);
$homepageTotalPublishedTestimonials = (int) ($homepageCustomerReviewSummary['total_reviews'] ?? 0) + $homepageProductReviewCount;
$homepageProductRatingTotal = 0;
$homepageProductRatingWeighted = 0.0;

foreach ($homepageAllProductTestimonials as $productTestimonial) {
    $productRating = (int) ($productTestimonial['rating'] ?? 0);
    if ($productRating > 0) {
        $homepageProductRatingWeighted += $productRating;
        $homepageProductRatingTotal++;
    }
}

$homepageCombinedAverageRating = 0;
$homepageCustomerReviewTotal = (int) ($homepageCustomerReviewSummary['total_reviews'] ?? 0);
if (($homepageCustomerReviewTotal + $homepageProductRatingTotal) > 0) {
    $homepageCombinedAverageRating = (
        (((float) ($homepageCustomerReviewSummary['average_rating'] ?? 0)) * $homepageCustomerReviewTotal)
        + $homepageProductRatingWeighted
    ) / ($homepageCustomerReviewTotal + $homepageProductRatingTotal);
}

$founderProfile = getFounderProfile();
$heroSlides = array_values(array_filter(getHeroSlides(), function ($slide) {
    return (int) ($slide['is_active'] ?? 0) === 1;
}));
$introVideoUrl = getIntroVideoUrl();

?>

<?php if ($introVideoUrl !== ''): ?>
<style>
    body.intro-video-active {
        overflow: hidden;
    }

    .site-intro-overlay {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        background:
            radial-gradient(circle at top, rgba(56, 189, 248, 0.18), transparent 40%),
            linear-gradient(180deg, rgba(2, 6, 23, 0.96), rgba(2, 6, 23, 1));
        opacity: 1;
        visibility: visible;
        transition: opacity 0.7s ease, visibility 0.7s ease;
    }

    .site-intro-overlay.is-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .site-intro-video {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        filter: saturate(1.08) contrast(1.04);
    }

    .site-intro-scrim {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(2, 6, 23, 0.08), rgba(2, 6, 23, 0.55));
    }

    .site-intro-content {
        position: relative;
        z-index: 2;
        width: min(92vw, 1120px);
        color: #fff;
        padding: 2rem 1.25rem;
    }

    .site-intro-brand {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .site-intro-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        padding: 0.55rem 0.9rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(10px);
        letter-spacing: 0.08em;
        text-transform: uppercase;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .site-intro-copy {
        max-width: 560px;
    }

    .site-intro-copy h1 {
        font-size: clamp(2.25rem, 4.6vw, 4.8rem);
        line-height: 1.02;
        font-weight: 800;
        margin: 0 0 0.9rem;
        color: #ffffff;
        text-shadow: 0 8px 24px rgba(2, 6, 23, 0.45);
    }

    .site-intro-copy p {
        margin: 0;
        color: rgba(255, 255, 255, 0.82);
        font-size: clamp(1rem, 1.4vw, 1.18rem);
    }

    .site-intro-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-top: 2rem;
        flex-wrap: wrap;
    }

    .site-intro-progress {
        width: min(360px, 100%);
    }

    .site-intro-progress-track {
        width: 100%;
        height: 6px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.22);
        overflow: hidden;
        margin-top: 0.45rem;
    }

    .site-intro-progress-bar {
        width: 0;
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #67e8f9, #e0f2fe);
        transition: width 0.12s linear;
    }

    .site-intro-skip {
        border: 1px solid rgba(255, 255, 255, 0.36);
        color: #fff;
        background: rgba(15, 23, 42, 0.3);
        backdrop-filter: blur(10px);
        padding-inline: 1.1rem;
    }

    .homepage-shell {
        transition: opacity 0.55s ease, transform 0.55s ease;
    }

    .homepage-shell.is-intro-hidden {
        opacity: 0;
        transform: translateY(18px);
    }

    @media (max-width: 767.98px) {
        .site-intro-brand {
            flex-direction: column;
        }
    }
</style>

<div id="siteIntroOverlay" class="site-intro-overlay" aria-hidden="true">
    <video id="siteIntroVideo" class="site-intro-video" autoplay muted playsinline preload="auto">
        <source src="<?php echo sanitize($introVideoUrl); ?>">
    </video>
    <div class="site-intro-scrim"></div>
    <div class="site-intro-content">
        <div class="site-intro-brand">
            <span class="site-intro-kicker"><i class="fas fa-circle-notch fa-spin"></i> Opening Experience</span>
            <button type="button" class="btn site-intro-skip" id="siteIntroSkipButton">Skip Intro</button>
        </div>
        <div class="site-intro-copy">
            <h1>Focus is about to click into place.</h1>
            <p>Your Bealet experience is loading with clarity, precision, and a sharper first impression.</p>
        </div>
        <div class="site-intro-actions">
            <div class="site-intro-progress">
                <small id="siteIntroStatus">Preparing the homepage...</small>
                <div class="site-intro-progress-track">
                    <div id="siteIntroProgressBar" class="site-intro-progress-bar"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="homepageShell" class="homepage-shell<?php echo $introVideoUrl !== '' ? ' is-intro-hidden' : ''; ?>">
    <!-- Hero Section -->
    <section class="section-spacing">
        <div class="container-lg">
            <div class="hero-shell">
                <div id="heroCarousel" class="carousel slide hero-carousel" data-bs-ride="carousel" data-bs-interval="5000">
                    <div class="carousel-indicators">
                        <?php if (!empty($heroSlides)): ?>
                            <?php foreach ($heroSlides as $idx => $slide): ?>
                            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?php echo $idx; ?>" class="<?php echo $idx === 0 ? 'active' : ''; ?>" aria-current="<?php echo $idx === 0 ? 'true' : 'false'; ?>" aria-label="Slide <?php echo $idx + 1; ?>"></button>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                        <?php endif; ?>
                    </div>

                    <div class="carousel-inner">
                        <?php if (!empty($heroSlides)): ?>
                            <?php foreach ($heroSlides as $idx => $slide): ?>
                            <div class="carousel-item <?php echo $idx === 0 ? 'active' : ''; ?>">
                                <img src="<?php echo getHeroSlideImageUrl($slide['image']); ?>" class="d-block w-100 hero-media" alt="<?php echo sanitize($slide['title'] ?: ('Hero slide ' . ($idx + 1))); ?>">
                                <div class="hero-gradient-layer"></div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <div class="carousel-item active">
                            <img src="https://via.placeholder.com/1600x900?text=Bealet+Hero" class="d-block w-100 hero-media" alt="Bealet Hero">
                            <div class="hero-gradient-layer"></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>

                <div class="hero-copy">
                    <span class="hero-kicker"><i class="fas fa-circle-notch"></i> Precision Eyewear Experience</span>
                    <h1>See the world in perfect focus.</h1>
                    <p>
                        Premium eyewear with a modern fitting experience. Explore bold frames, use AR try-on, and book specialists in seconds.
                    </p>
                    <div class="hero-cta-row">
                        <a href="<?php echo APP_URL; ?>/shop.php" class="btn btn-light btn-lg">
                            <i class="fas fa-shopping-bags me-2"></i> Shop Frames
                        </a>
                        <a href="<?php echo APP_URL; ?>/ar-tryon.php" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-vr-cardboard me-2"></i> Start AR Try-On
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="section-spacing">
        <div class="container-lg">
            <div class="section-title">
                <h2>Why Choose Bealet?</h2>
                <p>Experience eyewear shopping reimagined</p>
            </div>
            
            <div class="row g-4">
                <!-- Feature 1 -->
                <div class="col-md-6 col-lg-3">
                    <div class="card text-center home-feature-card h-100">
                        <div class="card-body">
                            <span class="home-feature-icon"><i class="fas fa-vr-cardboard"></i></span>
                            <h5 class="card-title">AR Try-On</h5>
                            <p class="card-text">See how frames look on your face before buying using our advanced AR technology.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 2 -->
                <div class="col-md-6 col-lg-3">
                    <div class="card text-center home-feature-card h-100">
                        <div class="card-body">
                            <span class="home-feature-icon"><i class="fas fa-truck"></i></span>
                            <h5 class="card-title">Fast Shipping</h5>
                            <p class="card-text">Get your orders delivered quickly with real-time tracking updates.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 3 -->
                <div class="col-md-6 col-lg-3">
                    <div class="card text-center home-feature-card h-100">
                        <div class="card-body">
                            <span class="home-feature-icon"><i class="fas fa-calendar"></i></span>
                            <h5 class="card-title">Easy Booking</h5>
                            <p class="card-text">Schedule appointments with our optometry specialists easily.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Feature 4 -->
                <div class="col-md-6 col-lg-3">
                    <div class="card text-center home-feature-card h-100">
                        <div class="card-body">
                            <span class="home-feature-icon"><i class="fas fa-headset"></i></span>
                            <h5 class="card-title">Support 24/7</h5>
                            <p class="card-text">Our customer service team is always ready to help you.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Products Section -->
    <section class="section-spacing pt-0 bg-light">
        <div class="container-lg">
            <div class="section-title">
                <h2>Featured Products</h2>
                <p>Discover our latest collection of premium eyewear</p>
            </div>
            
            <div class="row g-4">
                <?php foreach ($featuredProducts as $product): ?>
                <?php
                $featuredProductId = (int) $product['id'];
                $featuredGalleryImages = getProductGalleryImages($featuredProductId, $product);
                $productTryOnLink = getProductTryOnLink($product);
                $quickProductPayload = [
                    'id' => $featuredProductId,
                    'name' => (string) $product['name'],
                    'price' => (float) $product['price'],
                    'description' => decodeStoredText($product['description'] ?? ''),
                    'stock' => (int) ($product['stock'] ?? 0),
                    'image' => getProductImagePath($product),
                ];
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card product-card">
                        <?php if (count($featuredGalleryImages) > 1): ?>
                        <div id="featuredProductGallery<?php echo $featuredProductId; ?>" class="carousel slide product-gallery-carousel featured-product-gallery-carousel" data-bs-interval="false">
                            <div class="carousel-inner">
                                <?php foreach ($featuredGalleryImages as $imageIndex => $galleryImage): ?>
                                <div class="carousel-item <?php echo $imageIndex === 0 ? 'active' : ''; ?>">
                                    <img src="<?php echo $galleryImage; ?>" alt="<?php echo sanitize($product['name']); ?> image <?php echo $imageIndex + 1; ?>" class="product-image">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#featuredProductGallery<?php echo $featuredProductId; ?>" data-bs-slide="prev" aria-label="Previous image">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#featuredProductGallery<?php echo $featuredProductId; ?>" data-bs-slide="next" aria-label="Next image">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            </button>
                            <div class="product-gallery-dots">
                                <?php foreach ($featuredGalleryImages as $imageIndex => $galleryImage): ?>
                                <button type="button" data-bs-target="#featuredProductGallery<?php echo $featuredProductId; ?>" data-bs-slide-to="<?php echo $imageIndex; ?>" class="<?php echo $imageIndex === 0 ? 'active' : ''; ?>" aria-label="View image <?php echo $imageIndex + 1; ?>"></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <img src="<?php echo $featuredGalleryImages[0] ?? getProductImagePath($product); ?>" alt="<?php echo sanitize($product['name']); ?>" class="product-image">
                        <?php endif; ?>
                        <div class="product-info">
                            <div class="product-name"><?php echo sanitize($product['name']); ?></div>
                            <div class="product-price"><?php echo formatCurrency($product['price']); ?></div>
                            
                            <?php
                            $rating = getProductRating($product['id']);
                            ?>
                            <div class="product-rating">
                                <div>
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                    <i class="fas fa-star star" style="color: <?php echo $i < $rating['average'] ? 'var(--warning)' : '#E5E7EB'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-text"><?php echo $rating['average']; ?>/5 (<?php echo $rating['total']; ?> reviews)</span>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <?php if ($productTryOnLink !== ''): ?>
                                <a class="btn product-tryon-cta" href="<?php echo sanitize($productTryOnLink); ?>">
                                    <i class="fas fa-vr-cardboard me-2"></i> Try On
                                </a>
                                <?php endif; ?>
                                <button class="btn btn-primary" type="button" onclick='openQuickPurchaseModal(<?php echo json_encode($quickProductPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                    <i class="fas fa-bolt me-2"></i> Buy It Now
                                </button>
                                <a class="btn btn-outline-primary" href="<?php echo APP_URL; ?>/shop.php?view_product=<?php echo $featuredProductId; ?>" onclick="stopFeaturedProductGalleryAutoplay(this)">
                                    <i class="fas fa-eye me-2"></i> View Product
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-5">
                <a href="<?php echo APP_URL; ?>/shop.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-arrow-right me-2"></i> View All Products
                </a>
            </div>
        </div>
    </section>

    <script>
    (function () {
        function initializeFeaturedProductGalleries() {
            if (typeof bootstrap === 'undefined') {
                return;
            }

            document.querySelectorAll('.featured-product-gallery-carousel').forEach(function (carouselNode) {
                var carousel = bootstrap.Carousel.getOrCreateInstance(carouselNode, {
                    interval: 2400,
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

        window.stopFeaturedProductGalleryAutoplay = function (button) {
            if (typeof bootstrap === 'undefined' || !button) {
                return true;
            }

            var card = button.closest('.product-card');
            var carouselNode = card ? card.querySelector('.featured-product-gallery-carousel') : null;
            if (!carouselNode) {
                return true;
            }

            var carousel = bootstrap.Carousel.getInstance(carouselNode);
            if (carousel) {
                carousel.pause();
            }

            return true;
        };

        document.addEventListener('DOMContentLoaded', initializeFeaturedProductGalleries);
    })();
    </script>

    <section class="section-spacing pt-0">
        <div class="container-lg">
            <div class="reviews-showcase">
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                    <div class="section-title text-start mb-0">
                        <h2>What Customers Are Saying</h2>
                        <p>Browse both general testimonials and product-specific feedback from customers who have shopped with Bealet.</p>
                    </div>
                    <a href="<?php echo APP_URL; ?>/reviews" class="btn btn-outline-primary">
                        <i class="fas fa-star me-2"></i> Open Testimonials
                    </a>
                </div>

                <div class="reviews-showcase-summary mb-4">
                    <div class="reviews-summary-mini">
                        <strong><?php echo number_format($homepageCombinedAverageRating, 1); ?>/5</strong>
                        <span>Average approved rating across testimonials and product reviews</span>
                    </div>
                    <div class="reviews-summary-mini">
                        <strong><?php echo $homepageTotalPublishedTestimonials; ?></strong>
                        <span>Published testimonials and product reviews</span>
                    </div>
                    <div class="reviews-summary-mini">
                        <strong><?php echo count($homepageCustomerReviews); ?> + <?php echo count($homepageProductTestimonials); ?></strong>
                        <span>General testimonials and product testimonials blended on one landing-page feed</span>
                    </div>
                </div>

                <?php if (!empty($homepageReviews)): ?>
                <div class="row g-3">
                    <?php foreach ($homepageReviews as $review): ?>
                    <div class="col-lg-4">
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
                                        <div class="small text-muted mb-1">
                                            <?php echo sanitize($review['display_title'] ?? 'Customer Testimonial'); ?>
                                        </div>
                                        <div class="review-stars small">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="<?php echo $i <= (int) ($review['rating'] ?? 0) ? 'fas' : 'far'; ?> fa-star"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted"><?php echo formatDate($review['display_date'] ?? $review['updated_at'] ?? $review['created_at']); ?></small>
                            </div>
                            <?php if (($review['review_type'] ?? '') === 'product' && !empty($review['display_image_url'])): ?>
                            <div class="mb-3">
                                <img
                                    src="<?php echo sanitize($review['display_image_url']); ?>"
                                    alt="<?php echo sanitize(($review['product_name'] ?? 'Product') . ' testimonial photo'); ?>"
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
                        <h3 class="h5">The customer review wall is ready.</h3>
                        <p class="text-muted mb-3">Once the first reviews are approved by admin, they will begin appearing here on the landing page.</p>
                        <a href="<?php echo APP_URL; ?>/reviews" class="btn btn-primary">
                            <i class="fas fa-arrow-right me-2"></i> Visit Testimonials
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section-spacing pt-0">
        <div class="container-lg">
            <div class="founder-spotlight">
                <div class="row align-items-center g-4">
                    <div class="col-lg-4">
                        <img src="<?php echo sanitize($founderProfile['thumbnail_url']); ?>" alt="<?php echo sanitize($founderProfile['name']); ?>" class="img-fluid founder-spotlight-image">
                    </div>
                    <div class="col-lg-8">
                        <span class="hero-kicker founder-kicker"><i class="fas fa-landmark me-2"></i>Founder Spotlight</span>
                        <h2 class="mb-2"><?php echo sanitize($founderProfile['name']); ?></h2>
                        <p class="text-primary fw-semibold mb-3"><?php echo sanitize($founderProfile['role']); ?></p>
                        <p class="lead text-muted mb-3"><?php echo sanitize($founderProfile['short_bio']); ?></p>
                        <?php if ($founderProfile['quote'] !== ''): ?>
                        <blockquote class="founder-quote mb-4">"<?php echo sanitize($founderProfile['quote']); ?>"</blockquote>
                        <?php endif; ?>
                        <a href="<?php echo APP_URL; ?>/founder" class="btn btn-primary btn-lg">
                            <i class="fas fa-book-open me-2"></i> Read Her Story
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
<?php if ($introVideoUrl !== ''): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const introOverlay = document.getElementById('siteIntroOverlay');
        const introVideo = document.getElementById('siteIntroVideo');
        const introSkipButton = document.getElementById('siteIntroSkipButton');
        const introProgressBar = document.getElementById('siteIntroProgressBar');
        const introStatus = document.getElementById('siteIntroStatus');
        const homepageShell = document.getElementById('homepageShell');
        const introSessionKey = 'bealet-home-intro-played';

        if (!introOverlay || !introVideo || !homepageShell) {
            return;
        }

        let introFinished = false;
        let progressTimer = null;

        function finishIntro() {
            if (introFinished) {
                return;
            }

            introFinished = true;
            if (progressTimer) {
                window.clearInterval(progressTimer);
            }

            sessionStorage.setItem(introSessionKey, '1');
            introOverlay.classList.add('is-hidden');
            homepageShell.classList.remove('is-intro-hidden');
            document.body.classList.remove('intro-video-active');

            window.setTimeout(function () {
                introVideo.pause();
                introVideo.removeAttribute('src');
                introVideo.load();
            }, 850);
        }

        function revealWithoutIntro() {
            introOverlay.classList.add('is-hidden');
            homepageShell.classList.remove('is-intro-hidden');
            document.body.classList.remove('intro-video-active');
        }

        if (sessionStorage.getItem(introSessionKey) === '1') {
            revealWithoutIntro();
            return;
        }

        document.body.classList.add('intro-video-active');

        function syncProgress() {
            if (!introVideo.duration || Number.isNaN(introVideo.duration)) {
                return;
            }
            const ratio = Math.min(introVideo.currentTime / introVideo.duration, 1);
            introProgressBar.style.width = (ratio * 100).toFixed(1) + '%';
            introStatus.textContent = ratio >= 0.96 ? 'Focus locked. Opening homepage...' : 'Loading in sharp focus...';
        }

        introSkipButton.addEventListener('click', finishIntro);
        introVideo.addEventListener('ended', finishIntro);
        introVideo.addEventListener('timeupdate', syncProgress);
        introVideo.addEventListener('canplay', function () {
            introStatus.textContent = 'Opening sequence is now playing...';
        });
        introVideo.addEventListener('error', revealWithoutIntro);

        introVideo.play().catch(function () {
            introStatus.textContent = 'Tap anywhere to play the opening sequence.';
            const resumePlayback = function () {
                introVideo.play().finally(function () {
                    document.removeEventListener('click', resumePlayback);
                });
            };
            document.addEventListener('click', resumePlayback);
        });

        progressTimer = window.setInterval(syncProgress, 120);
    });
</script>
<?php endif; ?>
    
    <!-- Services Section -->
    <section class="section-spacing">
        <div class="container-lg">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <div class="services-panel">
                    <h2 class="mb-4">Our Services</h2>
                    <div class="mb-4">
                        <h5 class="mb-3">
                            <i class="fas fa-check-circle text-success me-2"></i> Professional Eye Testing
                        </h5>
                        <p>Our certified optometrists provide comprehensive eye exams to determine your exact prescription.</p>
                    </div>
                    <div class="mb-4">
                        <h5 class="mb-3">
                            <i class="fas fa-check-circle text-success me-2"></i> Frame Fitting & Styling
                        </h5>
                        <p>Get expert advice on choosing frames that suit your face shape and style.</p>
                    </div>
                    <div class="mb-4">
                        <h5 class="mb-3">
                            <i class="fas fa-check-circle text-success me-2"></i> Comprehensive Eye Examination
                        </h5>
                        <p>Choose from various lens types including progressive, photochromic, and blue light blocking.</p>
                    </div>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="<?php echo APP_URL; ?>/appointment.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-calendar me-2"></i> Book an Appointment
                        </a>
                        <a href="<?php echo APP_URL; ?>/team.php" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-people-group me-2"></i> Meet Our Staff
                        </a>
                    </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="services-visual text-center d-flex align-items-center justify-content-center">
                        <i class="fas fa-user-md"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Blog Section -->
    <section class="section-spacing bg-light">
        <div class="container-lg">
            <div class="section-title">
                <h2>Latest from Our Blog</h2>
                <p>Tips, trends, and insights about eyewear</p>
            </div>
            
            <div class="row g-4">
                <?php foreach ($blogPosts as $post): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 home-blog-card">
                        <img src="<?php echo getBlogImageUrl($post['featured_image'] ?? ''); ?>" alt="<?php echo sanitize($post['title']); ?>" class="card-img-top">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo sanitize($post['title']); ?></h5>
                            <p class="card-text text-muted"><?php echo substr(strip_tags($post['content']), 0, 100) . '...'; ?></p>
                            <small class="text-muted">
                                <i class="fas fa-calendar me-2"></i><?php echo formatDate($post['created_at']); ?>
                            </small>
                        </div>
                        <div class="card-footer">
                            <a href="<?php echo APP_URL; ?>/blog-post.php?slug=<?php echo urlencode($post['slug']); ?>" class="btn btn-sm btn-primary w-100">
                                <i class="fas fa-arrow-right me-2"></i> Read More
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="section-spacing">
        <div class="container-lg text-center">
            <div class="home-cta-panel">
                <h2 class="mb-4">Ready to Experience Better Vision?</h2>
                <p class="lead mb-4">
                    Join thousands of satisfied customers who have found their perfect eyewear with Bealet.
                </p>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="<?php echo APP_URL; ?>/shop.php" class="btn btn-light btn-lg">
                        <i class="fas fa-shopping-bags me-2"></i> Shop Now
                    </a>
                    <a href="<?php echo APP_URL; ?>/ar-tryon.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-vr-cardboard me-2"></i> Try AR Try-On
                    </a>
                </div>
            </div>
        </div>
    </section>

    </div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
