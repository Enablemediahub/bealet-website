<?php
/**
 * Bealet Website - Blog Listing
 */

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

$posts = $db->fetchAll("SELECT * FROM blog_posts WHERE is_published = 1 ORDER BY created_at DESC");
$blogHeroImage = getBlogHeroImageUrl();

require_once __DIR__ . '/inc/header.php';
?>

    <nav aria-label="breadcrumb" class="mt-4 mb-4">
        <div class="container-lg">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/">Home</a></li>
                <li class="breadcrumb-item active">Blog</li>
            </ol>
        </div>
    </nav>

    <section class="contact-hero mb-5">
        <div class="container-lg">
            <div class="contact-hero-panel contact-hero-panel--image text-center" style="--page-hero-image: url('<?php echo sanitize($blogHeroImage); ?>');">
                <h1 class="mb-3">Our Blog</h1>
                <p class="lead mb-0">Stories, eyewear guides, and optical care insights from the Bealet team.</p>
            </div>
        </div>
    </section>

    <section class="section-spacing">
        <div class="container-lg">
            <div class="row g-4">
                <?php foreach ($posts as $post): ?>
                <div class="col-md-6">
                    <div class="card blog-card h-100">
                        <div class="blog-card-media">
                            <img src="<?php echo getBlogImageUrl($post['featured_image'] ?? ''); ?>" class="card-img-top blog-card-image" alt="<?php echo sanitize($post['title']); ?>">
                        </div>
                        <div class="card-body d-flex flex-column">
                            <span class="badge bg-primary mb-2"><?php echo formatDate($post['created_at'], 'short'); ?></span>
                            <h5 class="card-title"><?php echo sanitize($post['title']); ?></h5>
                            <p class="card-text text-muted"><?php echo sanitize(substr($post['content'], 0, 140)); ?>...</p>
                            <a href="<?php echo APP_URL; ?>/blog-post.php?slug=<?php echo urlencode($post['slug']); ?>" class="mt-auto btn btn-outline-primary">Read More</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
