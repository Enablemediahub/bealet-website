<?php
/**
 * Bealet Website - Founder Digital Museum
 */

require_once __DIR__ . '/inc/header.php';

$founderProfile = getFounderProfile();
$founderGalleryItems = getFounderGalleryItems();
?>

<nav aria-label="breadcrumb" class="mt-4 mb-4">
    <div class="container-lg">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/">Home</a></li>
            <li class="breadcrumb-item active">Founder</li>
        </ol>
    </div>
</nav>

<section class="section-spacing pt-0">
    <div class="container-lg">
        <div class="founder-hero" style="--founder-hero-image: url('<?php echo sanitize($founderProfile['hero_image_url']); ?>');">
            <div class="founder-hero-copy">
                <span class="hero-kicker founder-kicker"><i class="fas fa-landmark me-2"></i>Digital Museum</span>
                <h1 class="mb-3"><?php echo sanitize($founderProfile['name']); ?></h1>
                <p class="lead mb-2"><?php echo sanitize($founderProfile['role']); ?></p>
                <?php if ($founderProfile['quote'] !== ''): ?>
                <p class="founder-hero-quote mb-0">"<?php echo sanitize($founderProfile['quote']); ?>"</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="section-spacing pt-0">
    <div class="container-lg">
        <div class="row g-4 align-items-start">
            <div class="col-lg-4">
                <div class="card founder-profile-card h-100">
                    <div class="card-body">
                        <img src="<?php echo sanitize($founderProfile['thumbnail_url']); ?>" alt="<?php echo sanitize($founderProfile['name']); ?>" class="img-fluid founder-profile-thumb mb-4">
                        <h2 class="h4 mb-1"><?php echo sanitize($founderProfile['name']); ?></h2>
                        <p class="text-primary fw-semibold mb-3"><?php echo sanitize($founderProfile['role']); ?></p>
                        <p class="text-muted mb-0"><?php echo sanitize($founderProfile['short_bio']); ?></p>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card founder-story-card h-100">
                    <div class="card-body">
                        <span class="text-uppercase small text-muted d-inline-block mb-2">Legacy Story</span>
                        <h2 class="mb-3">A life behind the vision</h2>
                        <?php foreach (preg_split("/\r\n|\n|\r/", (string) $founderProfile['story']) as $paragraph): ?>
                            <?php if (trim($paragraph) !== ''): ?>
                            <p class="text-muted founder-story-paragraph"><?php echo sanitize($paragraph); ?></p>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-spacing bg-light">
    <div class="container-lg">
        <div class="section-title">
            <h2>Founder Museum Collection</h2>
            <p>Portraits, milestones, and historic instruments that tell the story behind the brand.</p>
        </div>

        <?php if (!empty($founderGalleryItems)): ?>
        <div class="row g-4">
            <?php foreach ($founderGalleryItems as $item): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card founder-museum-card h-100">
                    <img src="<?php echo sanitize(getFounderGalleryImageUrl($item['image_path'] ?? '')); ?>" alt="<?php echo sanitize($item['item_title'] ?? 'Museum item'); ?>" class="card-img-top founder-museum-image">
                    <div class="card-body">
                        <span class="badge bg-primary-subtle text-primary border mb-3"><?php echo sanitize(ucfirst((string) ($item['item_type'] ?? 'portrait'))); ?></span>
                        <h5 class="card-title"><?php echo sanitize($item['item_title'] ?? 'Museum item'); ?></h5>
                        <?php if (!empty($item['item_description'])): ?>
                        <p class="card-text text-muted mb-0"><?php echo sanitize((string) $item['item_description']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="card founder-empty-state">
            <div class="card-body text-center py-5">
                <i class="fas fa-landmark d-block mb-3" style="font-size: 2rem; color: #2563eb;"></i>
                <h3 class="h4">The museum collection is coming soon</h3>
                <p class="text-muted mb-0">Upload portraits, instruments, and milestone images from the admin founder page to build the digital museum.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
