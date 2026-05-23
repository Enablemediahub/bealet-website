/**
 * Bealet Website - Main JavaScript
 * Global functionality and utilities
 */

// Utility: Show Toast Notification
function showToastNotification(message, type = 'info', duration = 5000) {
    const toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        document.body.appendChild(container);
    }
    
    const iconMap = {
        'success': 'check-circle',
        'error': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    
    const toastHTML = `
        <div class="toast ${type} mb-2" style="animation: slideIn 0.3s ease-out;">
            <div class="d-flex align-items-center gap-2 p-3">
                <i class="fas fa-${iconMap[type] || 'info-circle'} fs-5"></i>
                <span>${message}</span>
                <button type="button" class="btn-close ms-auto" style="width: auto;"></button>
            </div>
        </div>
    `;
    
    const toastElement = document.createElement('div');
    toastElement.innerHTML = toastHTML;
    document.getElementById('toastContainer').appendChild(toastElement);
    
    const closeBtn = toastElement.querySelector('.btn-close');
    closeBtn.addEventListener('click', () => toastElement.remove());
    
    if (duration > 0) {
        setTimeout(() => {
            if (toastElement.parentElement) {
                toastElement.remove();
            }
        }, duration);
    }
}

function ensureMobileCartDrawer() {
    let drawer = document.getElementById('mobileCartDrawer');
    if (drawer) {
        return drawer;
    }

    drawer = document.createElement('div');
    drawer.className = 'offcanvas offcanvas-end';
    drawer.id = 'mobileCartDrawer';
    drawer.tabIndex = -1;
    drawer.setAttribute('aria-labelledby', 'mobileCartDrawerLabel');
    drawer.innerHTML = `
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="mobileCartDrawerLabel">Your Cart</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column">
            <div id="mobileCartDrawerContent" class="flex-grow-1"></div>
            <div class="pt-3 border-top mt-3">
                <a href="${window.BASE_URL || ''}/checkout" class="btn btn-primary w-100">
                    Go to Cart & Checkout
                </a>
            </div>
        </div>
    `;
    document.body.appendChild(drawer);
    return drawer;
}

function ensureQuickPurchaseModal() {
    let modal = document.getElementById('quickPurchaseModal');
    if (modal) {
        return modal;
    }

    modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'quickPurchaseModal';
    modal.tabIndex = -1;
    modal.setAttribute('aria-labelledby', 'quickPurchaseModalLabel');
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="quickPurchaseModalLabel">Quick Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="quickPurchaseModalBody"></div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    return modal;
}

let deferredInstallPrompt = null;
let installAppModalInstance = null;

function getCookieConsentBanner() {
    return document.getElementById('cookieConsentBanner');
}

function hideCookieConsentBanner() {
    const banner = getCookieConsentBanner();
    if (!banner) {
        return;
    }

    banner.classList.remove('is-visible');
    document.body.classList.remove('cookie-consent-visible');
    banner.setAttribute('hidden', 'hidden');
}

function showCookieConsentBanner() {
    const banner = getCookieConsentBanner();
    if (!banner) {
        return;
    }

    banner.removeAttribute('hidden');
    window.requestAnimationFrame(() => {
        banner.classList.add('is-visible');
        document.body.classList.add('cookie-consent-visible');
    });
}

function persistCookieConsent(value) {
    try {
        localStorage.setItem('bealet_cookie_consent', value);
    } catch (error) {
        console.error('Unable to save cookie consent:', error);
    }
}

function getInstallAppModal() {
    const modalElement = document.getElementById('installAppModal');
    if (!modalElement || !window.bootstrap) {
        return null;
    }

    if (!installAppModalInstance) {
        installAppModalInstance = new bootstrap.Modal(modalElement);
    }

    return installAppModalInstance;
}

function markInstallPromptDismissed() {
    try {
        localStorage.setItem('bealet_install_prompt_dismissed_at', String(Date.now()));
    } catch (error) {
        console.error('Unable to persist install prompt preference:', error);
    }
}

function shouldShowInstallPrompt() {
    if (!deferredInstallPrompt) {
        return false;
    }

    try {
        const installedDisplayMode = window.matchMedia('(display-mode: standalone)').matches;
        if (installedDisplayMode || window.navigator.standalone) {
            return false;
        }

        const dismissedAt = Number(localStorage.getItem('bealet_install_prompt_dismissed_at') || 0);
        const twoDays = 2 * 24 * 60 * 60 * 1000;
        return !dismissedAt || (Date.now() - dismissedAt) > twoDays;
    } catch (error) {
        return true;
    }
}

function maybeShowInstallPrompt() {
    if (!shouldShowInstallPrompt()) {
        return;
    }

    const modal = getInstallAppModal();
    if (modal) {
        window.setTimeout(() => {
            modal.show();
        }, 1200);
    }
}

async function installWebsiteApp() {
    if (!deferredInstallPrompt) {
        return;
    }

    deferredInstallPrompt.prompt();
    const { outcome } = await deferredInstallPrompt.userChoice;

    if (outcome !== 'accepted') {
        markInstallPromptDismissed();
    }

    deferredInstallPrompt = null;

    const modal = getInstallAppModal();
    if (modal) {
        modal.hide();
    }
}

function openQuickPurchaseModal(product) {
    const modal = ensureQuickPurchaseModal();
    modal.dataset.productId = String(product.id || 0);

    const body = modal.querySelector('#quickPurchaseModalBody');
    const safeName = sanitizeHTML(product.name || 'Product');
    const safeDescription = sanitizeHTML(product.description || 'Review the product details and continue when you are ready.');
    const safeImage = sanitizeHTML(product.image || '');
    const stock = Number(product.stock || 0);
    const isInStock = stock > 0;

    body.innerHTML = `
        <div class="row g-4 align-items-start">
            <div class="col-md-5">
                <img src="${safeImage}" alt="${safeName}" class="img-fluid rounded-4 border w-100" style="object-fit: cover; max-height: 360px;">
            </div>
            <div class="col-md-7">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                    <div>
                        <p class="text-uppercase small text-muted mb-1">Featured Product</p>
                        <h3 class="mb-0">${safeName}</h3>
                    </div>
                    <span class="badge ${isInStock ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'} border">${isInStock ? 'In Stock' : 'Out of Stock'}</span>
                </div>
                <div class="fs-4 fw-bold text-primary mb-3">${formatCurrency(product.price || 0)}</div>
                <p class="text-muted mb-4">${safeDescription}</p>
                <div class="mb-4">
                    <label class="form-label fw-semibold" for="quickPurchaseQty">Quantity</label>
                    <input type="number" min="1" max="${Math.max(stock, 1)}" value="1" id="quickPurchaseQty" class="form-control" ${isInStock ? '' : 'disabled'}>
                    ${isInStock ? `<small class="text-muted d-block mt-2">${stock} item(s) available right now.</small>` : '<small class="text-danger d-block mt-2">This item is currently unavailable.</small>'}
                </div>
                <div class="d-grid gap-2 d-sm-flex">
                    <button type="button" class="btn btn-primary btn-lg flex-fill" onclick="submitQuickPurchase('checkout')" ${isInStock ? '' : 'disabled'}>
                        <i class="fas fa-bolt me-2"></i> Buy It Now
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-lg flex-fill" onclick="submitQuickPurchase('drawer')" ${isInStock ? '' : 'disabled'}>
                        <i class="fas fa-shopping-cart me-2"></i> Add to Cart
                    </button>
                </div>
            </div>
        </div>
    `;

    const modalInstance = bootstrap.Modal.getOrCreateInstance(modal);
    modalInstance.show();
}

async function submitQuickPurchase(mode = 'drawer') {
    const modal = document.getElementById('quickPurchaseModal');
    if (!modal) {
        return;
    }

    const productId = Number(modal.dataset.productId || 0);
    const qtyInput = modal.querySelector('#quickPurchaseQty');
    const quantity = Math.max(1, Number(qtyInput?.value || 1));

    const modalInstance = bootstrap.Modal.getInstance(modal);
    if (modalInstance) {
        modalInstance.hide();
    }

    await addToCart(productId, quantity, mode);
}

async function showCartDrawer() {
    const drawer = ensureMobileCartDrawer();
    const body = drawer.querySelector('#mobileCartDrawerContent');
    body.innerHTML = '<p class="text-muted">Loading cart...</p>';

    try {
        const response = await fetch(buildApiUrl('cart-preview'));
        const data = await response.json();

        if (!data.success || !Array.isArray(data.items) || data.items.length === 0) {
            body.innerHTML = '<p class="text-muted mb-0">Your cart is empty.</p>';
        } else {
            const itemsHtml = data.items.map(item => `
                <div class="d-flex align-items-center gap-2 mb-3 pb-3 border-bottom">
                    <img src="${item.image_url}" alt="${item.name}" style="width: 54px; height: 54px; border-radius: 8px; object-fit: cover;">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">${sanitizeHTML(item.name)}</div>
                        <small class="text-muted">Qty: ${item.quantity}</small>
                    </div>
                    <div class="fw-semibold">${formatCurrency(item.line_total)}</div>
                </div>
            `).join('');

            body.innerHTML = `
                ${itemsHtml}
                <div class="d-flex justify-content-between">
                    <span class="fw-semibold">Total</span>
                    <span class="fw-bold text-primary">${formatCurrency(data.total || 0)}</span>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading mobile cart drawer:', error);
        body.innerHTML = '<p class="text-danger mb-0">Unable to load cart preview right now.</p>';
    }

    const offcanvas = bootstrap.Offcanvas.getOrCreateInstance(drawer);
    offcanvas.show();
}

// Utility: Update Cart Count
async function updateCartCount() {
    try {
        const response = await fetch(buildApiUrl('cart-count'));
        const data = await response.json();
        
        if (data.success) {
            const cartBadge = document.querySelector('.navbar-utils-item[title="Shopping Cart"] .badge-counter');
            if (cartBadge) {
                if (data.count > 0) {
                    cartBadge.textContent = data.count;
                    cartBadge.style.display = 'flex';
                } else {
                    cartBadge.style.display = 'none';
                }
            } else if (data.count > 0) {
                const cartBtn = document.querySelector('.navbar-utils-item[title="Shopping Cart"]');
                if (cartBtn) {
                    const badge = document.createElement('span');
                    badge.className = 'badge-counter';
                    badge.textContent = data.count;
                    cartBtn.appendChild(badge);
                }
            }
        }
    } catch (error) {
        console.error('Error updating cart count:', error);
    }
}

// Utility: Add to Cart
async function addToCart(productId, quantity = 1, mode = 'drawer') {
    try {
        const baseUrl = window.BASE_URL || '';
        const response = await fetch(buildApiUrl('add-to-cart'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: quantity
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToastNotification('Product added to cart!', 'success');
            await updateCartCount();
            const baseUrl = window.BASE_URL || '';
            if (mode === 'checkout') {
                window.location.href = `${baseUrl}/checkout`;
            } else {
                await showCartDrawer();
            }
        } else {
            showToastNotification(data.message || 'Error adding to cart', 'error');
        }
    } catch (error) {
        console.error('Error adding to cart:', error);
        showToastNotification('Error adding to cart', 'error');
    }
}

// Utility: Remove from Cart
async function removeFromCart(cartId) {
    if (!confirm('Are you sure you want to remove this item from cart?')) {
        return;
    }
    
    try {
        const response = await fetch(buildApiUrl('remove-from-cart'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                cart_id: cartId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToastNotification('Item removed from cart', 'success');
            updateCartCount();
            // Reload page to update cart display
            location.reload();
        } else {
            showToastNotification(data.message || 'Error removing item', 'error');
        }
    } catch (error) {
        console.error('Error removing from cart:', error);
        showToastNotification('Error removing item', 'error');
    }
}

// Utility: Format Currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-GH', {
        style: 'currency',
        currency: 'GHS'
    }).format(amount);
}

// Utility: Add to Wishlist
function getWishlistButton(productId, trigger = null) {
    if (trigger) {
        return trigger;
    }

    return document.querySelector(`[data-product-id="${productId}"].wishlist-btn`);
}

async function addToWishlist(productId, trigger = null) {
    try {
        const baseUrl = window.BASE_URL || '';
        const response = await fetch(buildApiUrl('toggle-wishlist'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                product_id: productId
            })
        });
        
        const data = await response.json();

        if (response.status === 401) {
            showToastNotification(data.message || 'Please login to use wishlist', 'info');
            setTimeout(() => {
                window.location.href = `${baseUrl}/login.php`;
            }, 900);
            return;
        }
        
        if (data.success) {
            showToastNotification('Added to wishlist!', 'success');
            const wishlistBtn = getWishlistButton(productId, trigger);
            if (wishlistBtn) {
                wishlistBtn.classList.add('active');
            }
        } else {
            showToastNotification(data.message || 'Error adding to wishlist', 'error');
        }
    } catch (error) {
        console.error('Error adding to wishlist:', error);
        showToastNotification('Error adding to wishlist', 'error');
    }
}

// Utility: Remove from Wishlist
async function removeFromWishlist(productId, trigger = null) {
    try {
        const baseUrl = window.BASE_URL || '';
        const response = await fetch(buildApiUrl('toggle-wishlist'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                product_id: productId
            })
        });
        
        const data = await response.json();

        if (response.status === 401) {
            showToastNotification(data.message || 'Please login to use wishlist', 'info');
            setTimeout(() => {
                window.location.href = `${baseUrl}/login.php`;
            }, 900);
            return;
        }
        
        if (data.success) {
            showToastNotification('Removed from wishlist', 'success');
            const wishlistBtn = getWishlistButton(productId, trigger);
            if (wishlistBtn) {
                wishlistBtn.classList.remove('active');
            }
            // Reload if on wishlist page
            if (window.location.pathname.includes('wishlist')) {
                location.reload();
            }
        } else {
            showToastNotification(data.message || 'Error removing from wishlist', 'error');
        }
    } catch (error) {
        console.error('Error removing from wishlist:', error);
        showToastNotification('Error removing from wishlist', 'error');
    }
}

// Utility: Toggle Wishlist
async function toggleWishlist(productId, trigger = null) {
    const wishlistBtn = getWishlistButton(productId, trigger);
    
    if (wishlistBtn && wishlistBtn.classList.contains('active')) {
        removeFromWishlist(productId, wishlistBtn);
    } else {
        addToWishlist(productId, wishlistBtn);
    }
}

// Utility: Sanitize HTML
function sanitizeHTML(html) {
    const div = document.createElement('div');
    div.textContent = html;
    return div.innerHTML;
}

// Utility: Format Date
function formatDate(dateString, format = 'short') {
    const date = new Date(dateString);
    
    if (format === 'short') {
        return date.toLocaleDateString('en-NG', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }
    
    return date.toLocaleDateString('en-NG', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Utility: Disable form submit button
function disableFormButton(formId, message = 'Processing...') {
    const form = document.getElementById(formId);
    if (!form) return;
    
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>${message}`;
    }
}

// Utility: Enable form submit button
function enableFormButton(formId, originalText) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

// Utility: Validate Email
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Utility: Validate Phone
function validatePhone(phone) {
    const re = /^(?:\+233[0-9]{9}|0[0-9]{9}|[0-9]{10})$/;
    return re.test(phone.replace(/[\s-()]/g, ''));
}

// Initialize on document ready
document.addEventListener('DOMContentLoaded', function() {
    try {
        const cookieConsent = localStorage.getItem('bealet_cookie_consent');
        if (!cookieConsent) {
            showCookieConsentBanner();
        }
    } catch (error) {
        showCookieConsentBanner();
    }

    document.getElementById('cookieConsentAccept')?.addEventListener('click', function () {
        persistCookieConsent('accepted');
        hideCookieConsentBanner();
    });

    document.getElementById('cookieConsentDecline')?.addEventListener('click', function () {
        persistCookieConsent('dismissed');
        hideCookieConsentBanner();
    });

    document.getElementById('installAppConfirm')?.addEventListener('click', function () {
        installWebsiteApp().catch(error => {
            console.error('Install prompt failed:', error);
        });
    });

    document.getElementById('installAppLater')?.addEventListener('click', function () {
        markInstallPromptDismissed();
    });

    // Update cart count on page load
    updateCartCount();
    
    // Header scroll behavior
    const siteHeader = document.querySelector('.site-header-wrap');
    const navCollapse = document.getElementById('navbarNav');
    let lastScrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    let revealHeaderTimeout = null;
    const handleHeaderOnScroll = () => {
        if (!siteHeader) {
            return;
        }

        const currentScrollPosition = window.pageYOffset || document.documentElement.scrollTop;
        const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
        const collapseIsOpen = navCollapse && navCollapse.classList.contains('show');
        const scrollingDown = currentScrollPosition > lastScrollPosition;
        const hideThreshold = viewportWidth <= 767 ? 72 : (viewportWidth <= 991 ? 96 : 144);
        const shouldHide = currentScrollPosition > hideThreshold && scrollingDown && !collapseIsOpen;

        siteHeader.classList.toggle('scrolled', currentScrollPosition > 10);
        siteHeader.classList.toggle('hidden', shouldHide);

        if (revealHeaderTimeout) {
            clearTimeout(revealHeaderTimeout);
        }

        revealHeaderTimeout = setTimeout(() => {
            siteHeader.classList.remove('hidden');
        }, viewportWidth <= 991 ? 120 : 180);

        lastScrollPosition = currentScrollPosition;
    };

    handleHeaderOnScroll();
    window.addEventListener('scroll', handleHeaderOnScroll, { passive: true });
    window.addEventListener('resize', handleHeaderOnScroll);

    if (navCollapse) {
        navCollapse.addEventListener('show.bs.collapse', function () {
            siteHeader.classList.remove('hidden');
        });
        navCollapse.addEventListener('hide.bs.collapse', function () {
            lastScrollPosition = window.pageYOffset || document.documentElement.scrollTop;
        });
    }
    
    // Handle newsletter form
    const newsletterForm = document.getElementById('newsletterForm');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = this.querySelector('input[type="email"]').value;
            
            try {
            const response = await fetch(buildApiUrl('subscribe-newsletter'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ email })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToastNotification('Subscribed to newsletter!', 'success');
                    this.reset();
                } else {
                    showToastNotification(data.message || 'Error subscribing', 'error');
                }
            } catch (error) {
                console.error('Error subscribing:', error);
                showToastNotification('Error subscribing', 'error');
            }
        });
    }
    
    // Add smooth scroll to anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#' && document.querySelector(href)) {
                e.preventDefault();
                document.querySelector(href).scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
    
    // Close mobile menu when clicking a link
    if (navCollapse) {
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                const bsCollapse = new bootstrap.Collapse(navCollapse, {
                    toggle: false
                });
                bsCollapse.hide();
            });
        });
    }
});

window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    deferredInstallPrompt = event;
    maybeShowInstallPrompt();
});

window.addEventListener('appinstalled', function () {
    deferredInstallPrompt = null;
    try {
        localStorage.setItem('bealet_install_prompt_dismissed_at', String(Date.now()));
    } catch (error) {
        console.error('Unable to persist install state:', error);
    }
});

window.addEventListener('load', function () {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(`${window.BASE_URL || ''}/sw.js`).catch(error => {
            console.error('Service worker registration failed:', error);
        });
    }
});

// Initialize tooltips if Bootstrap tooltips are used
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });
});

// Utility: Load Skeleton Screen
function showSkeleton(elementId, rows = 3) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    let skeletonHTML = '';
    for (let i = 0; i < rows; i++) {
        skeletonHTML += '<div class="skeleton skeleton-text"></div>';
    }
    
    element.innerHTML = skeletonHTML;
}

// Utility: Get CSRF Token from Meta Tag
function getCSRFToken() {
    const token = document.querySelector('meta[name="csrf-token"]');
    return token ? token.getAttribute('content') : '';
}

// Prevent XSS by escaping user input
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Utility: Debounce function for search/input
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Log page view for analytics
if (typeof gtag === 'undefined') {
    // Google Analytics not loaded, that's okay
}

console.log('Bealet Website - Main JS Loaded');
function buildApiUrl(endpoint) {
    const baseUrl = window.BASE_URL || '';
    return `${baseUrl}/api/${endpoint}`;
}
