<?php
/**
 * Bealet Website - 500 Server Error
 */

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/header.php';

?>

    <!-- 500 Error -->
    <div style="min-height: 80vh; display: flex; align-items: center; justify-content: center;">
        <div class="container text-center">
            <h1 style="font-size: 6rem; font-weight: 700; color: #DC2626; margin-bottom: 1rem;">500</h1>
            <h2 style="font-size: 2rem; margin-bottom: 1rem;">Server Error</h2>
            <p class="text-muted mb-4">Oops! Something went wrong on our end. Our team has been notified.</p>
            <div class="d-flex gap-3 justify-content-center">
                <a href="<?php echo APP_URL; ?>" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i> Go Home
                </a>
                <a href="<?php echo APP_URL; ?>/contact.php" class="btn btn-outline-primary">
                    <i class="fas fa-envelope me-2"></i> Contact Support
                </a>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
