<?php
/**
 * Bealet Website - Blog Post Detail
 */

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

$slug = sanitize($_GET['slug'] ?? '');
if (empty($slug)) {
    redirect(APP_URL . '/404.php');
}

$post = $db->fetch("SELECT * FROM blog_posts WHERE slug = ? AND is_published = 1", [$slug]);
if (!$post) {
    redirect(APP_URL . '/404.php');
}

$relatedPosts = $db->fetchAll(
    "SELECT id, title, slug, featured_image, created_at FROM blog_posts WHERE is_published = 1 AND slug != ? ORDER BY created_at DESC LIMIT 3",
    [$slug]
);

require_once __DIR__ . '/inc/header.php';
?>

    <div class="page-header">
        <div class="container">
            <h1><?php echo sanitize($post['title']); ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/blog.php">Blog</a></li>
                    <li class="breadcrumb-item active"><?php echo sanitize($post['title']); ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <section class="section-spacing">
        <div class="container-lg">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="blog-post-media">
                            <img src="<?php echo getBlogImageUrl($post['featured_image'] ?? ''); ?>" class="card-img-top blog-post-image" alt="<?php echo sanitize($post['title']); ?>">
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Published on <?php echo formatDate($post['created_at']); ?></p>
                            <div class="blog-content">
                                <?php echo nl2br(sanitize($post['content'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card p-4">
                        <h5 class="mb-3">Related Posts</h5>
                        <?php foreach ($relatedPosts as $related): ?>
                        <div class="related-post-card mb-3">
                            <a href="<?php echo APP_URL; ?>/blog-post.php?slug=<?php echo urlencode($related['slug']); ?>" class="related-post-link text-decoration-none">
                                <img src="<?php echo getBlogImageUrl($related['featured_image'] ?? ''); ?>" alt="<?php echo sanitize($related['title']); ?>" class="related-post-thumb">
                                <div class="related-post-copy">
                                    <h6 class="mb-1"><?php echo sanitize($related['title']); ?></h6>
                                    <small class="text-muted"><?php echo formatDate($related['created_at']); ?></small>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
