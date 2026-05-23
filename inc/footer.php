<?php
/**
 * Bealet Website - Footer Include
 */

// Ensure we have the necessary functions
if (!function_exists('APP_NAME')) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/functions.php';
}

$mainJsPath = __DIR__ . '/../assets/js/main.js';
$mainJsVersion = file_exists($mainJsPath) ? (string) filemtime($mainJsPath) : '1';
$socialLinks = getSocialMediaLinks();
$businessHours = getBusinessHours();
$whatsAppContact = getWhatsAppContactConfig();
?>
    <!-- Footer -->
    <footer class="mt-5">
        <div class="container-lg">
            <div class="row gy-4 mb-4">
                <!-- About Section -->
                <div class="col-md-3 footer-section">
                    <h5>About <?php echo sanitize(getCompanyName()); ?></h5>
                    <p class="footer-link">Premium eyewear and optical services with cutting-edge AR technology for the perfect fit.</p>
                    <?php if (!empty($socialLinks)): ?>
                    <div class="social-links">
                        <?php foreach ($socialLinks as $socialLink): ?>
                        <a href="<?php echo sanitize($socialLink['url']); ?>" class="social-icon" title="<?php echo sanitize($socialLink['label']); ?>" target="_blank" rel="noopener noreferrer">
                            <i class="<?php echo sanitize($socialLink['icon']); ?>"></i>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Links -->
                <div class="col-md-3 footer-section">
                    <h5>Quick Links</h5>
                    <a href="<?php echo APP_URL; ?>/" class="footer-link d-block">
                        <i class="fas fa-chevron-right"></i> Home
                    </a>
                    <a href="<?php echo APP_URL; ?>/shop.php" class="footer-link d-block">
                        <i class="fas fa-chevron-right"></i> Shop
                    </a>
                    <a href="<?php echo APP_URL; ?>/ar-tryon.php" class="footer-link d-block">
                        <i class="fas fa-chevron-right"></i> AR Try-On
                    </a>
                    <a href="<?php echo APP_URL; ?>/appointment.php" class="footer-link d-block">
                        <i class="fas fa-chevron-right"></i> Book Appointment
                    </a>
                    <a href="<?php echo APP_URL; ?>/reviews" class="footer-link d-block">
                        <i class="fas fa-chevron-right"></i> Testimonials
                    </a>
                </div>
                
                <!-- Customer Service -->
                <div class="col-md-3 footer-section">
                    <h5>Customer Service</h5>
                    <a href="<?php echo APP_URL; ?>/contact.php" class="footer-link d-block">
                        <i class="fas fa-chevron-right"></i> Contact Us
                    </a>
                    <a href="<?php echo APP_URL; ?>/faq.php" class="footer-link d-block">
                        <i class="fas fa-chevron-right"></i> FAQ
                    </a>
                    <a href="<?php echo APP_URL; ?>/track-order.php" class="footer-link d-block">
                        <i class="fas fa-chevron-right"></i> Track Order
                    </a>
                    <a href="<?php echo APP_URL; ?>/shipping-policy.php" class="footer-link d-block">
                        <i class="fas fa-chevron-right"></i> Shipping Policy
                    </a>
                    <a href="<?php echo APP_URL; ?>/refund-policy.php" class="footer-link d-block">
                        <i class="fas fa-chevron-right"></i> Refund Policy
                    </a>
                </div>
                
                <!-- Newsletter -->
                <div class="col-md-3 footer-section">
                    <h5>Newsletter</h5>
                    <p class="footer-link">Subscribe to get special offers and updates!</p>
                    <form id="newsletterForm" class="mt-3">
                        <div class="input-group">
                            <input type="email" class="form-control" placeholder="Your email" required>
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                    <div class="mt-3">
                        <p class="footer-link mb-1"><strong>Hours</strong></p>
                        <?php foreach ($businessHours as $hours): ?>
                        <p class="footer-link mb-1"><?php echo sanitize($hours['label']); ?>: <?php echo sanitize($hours['hours']); ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Footer Bottom -->
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo sanitize(getCompanyName()); ?>. All rights reserved.</p>
                <p class="small mt-2 mb-0">Developed and Designed By DALE QUIST (Enable Tecgnologies)</p>
                <div class="footer-legal-links mt-3">
                    <a href="<?php echo APP_URL; ?>/shipping-policy.php" class="btn btn-outline-light btn-sm">Shipping Policy</a>
                    <a href="<?php echo APP_URL; ?>/refund-policy.php" class="btn btn-outline-light btn-sm">Refund Policy</a>
                    <a href="<?php echo APP_URL; ?>/privacy-policy.php" class="btn btn-outline-light btn-sm">Privacy Policy</a>
                    <a href="<?php echo APP_URL; ?>/terms-of-service.php" class="btn btn-outline-light btn-sm">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <?php if ($whatsAppContact !== null): ?>
    <a
        href="<?php echo sanitize($whatsAppContact['url']); ?>"
        class="floating-whatsapp-button"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Chat with us on WhatsApp"
        title="WhatsApp us"
    >
        <span class="floating-whatsapp-button__icon">
            <i class="fab fa-whatsapp"></i>
        </span>
        <span class="floating-whatsapp-button__text">WhatsApp Us</span>
    </a>
    <?php endif; ?>

    <div id="cookieConsentBanner" class="cookie-consent-banner" hidden>
        <div class="cookie-consent-card">
            <div class="cookie-consent-copy">
                <span class="cookie-consent-kicker"><i class="fas fa-cookie-bite me-2"></i>Cookie Notice</span>
                <h5>We use cookies to keep your cart, login, and shopping experience working smoothly.</h5>
                <p class="mb-0">By continuing, you agree to essential cookies for sessions, cart storage, and secure sign-in on <?php echo sanitize(getCompanyName()); ?>.</p>
            </div>
            <div class="cookie-consent-actions">
                <button type="button" class="btn btn-outline-light" id="cookieConsentDecline">Later</button>
                <button type="button" class="btn btn-primary" id="cookieConsentAccept">Accept Cookies</button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="installAppModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content install-app-modal-content">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <p class="text-uppercase text-muted small mb-1">Install App</p>
                        <h5 class="modal-title mb-0">Install <?php echo sanitize(getCompanyName()); ?></h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img src="<?php echo sanitize(getSiteLogoUrl()); ?>" alt="<?php echo sanitize(getCompanyName()); ?>" class="install-app-logo" onerror="this.style.display='none';">
                        <div>
                            <p class="mb-1 fw-semibold">Shop faster and track orders with one tap.</p>
                            <p class="text-muted mb-0 small">Add the website to your phone or desktop home screen for a cleaner app-like experience.</p>
                        </div>
                    </div>
                    <ul class="install-app-benefits mb-0">
                        <li>Open the store from your home screen</li>
                        <li>Keep checkout and tracking close by</li>
                        <li>Enjoy a full-screen app-style experience</li>
                    </ul>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="installAppLater">Maybe Later</button>
                    <button type="button" class="btn btn-primary" id="installAppConfirm">Install App</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Flatpickr Date Picker -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <!-- Main JS -->
    <script src="<?php echo APP_URL; ?>/assets/js/main.js?v=<?php echo $mainJsVersion; ?>"></script>
    
    <!-- Script for Toast Notifications -->
    <script>
        function showToast(message, type = 'info') {
            const toastHtml = `
                <div class="toast ${type} position-fixed bottom-0 end-0 m-3" role="alert">
                    <div class="d-flex align-items-center">
                        <div>
                            <i class="fas fa-${getToastIcon(type)} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="toast-close ms-auto" data-bs-dismiss="toast">×</button>
                    </div>
                </div>
            `;
            
            const toastElement = document.createElement('div');
            toastElement.innerHTML = toastHtml;
            document.body.appendChild(toastElement);
            
            const toast = new bootstrap.Toast(toastElement.querySelector('.toast'));
            toast.show();
            
            setTimeout(() => {
                toastElement.remove();
            }, 5000);
        }
        
        function getToastIcon(type) {
            const icons = {
                'success': 'check-circle',
                'error': 'exclamation-circle',
                'warning': 'exclamation-triangle',
                'info': 'info-circle'
            };
            return icons[type] || 'info-circle';
        }
    </script>
</body>
</html>
