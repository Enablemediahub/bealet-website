<?php
/**
 * Bealet Website - Header Include
 * Navigation bar and page head
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Check session timeout
if (isLoggedIn() && !checkSessionTimeout()) {
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$cartCount = getCartCount();
$currentUser = isLoggedIn() ? getCurrentUser() : null;
$companyName = getCompanyName();
$companyTagline = getCompanyTagline();
$displayTagline = $companyTagline !== '' ? $companyTagline : 'Premium Eyewear and Optical Care';
$logoUrl = getSiteLogoUrl();
$faviconPath = __DIR__ . '/../assets/images/favicon.png';
$faviconUrl = APP_URL . '/assets/images/favicon.png';
$faviconVersion = file_exists($faviconPath) ? (string) filemtime($faviconPath) : '1';
$styleCssPath = __DIR__ . '/../assets/css/style.css';
$styleCssVersion = file_exists($styleCssPath) ? (string) filemtime($styleCssPath) : '1';
$currentPath = basename($_SERVER['PHP_SELF'] ?? '');
$primaryNav = [
    ['label' => 'Home', 'href' => APP_URL . '/', 'active' => $currentPath === 'index.php' || $currentPath === ''],
    ['label' => 'Shop', 'href' => APP_URL . '/shop', 'active' => $currentPath === 'shop.php'],
    ['label' => 'Try-On', 'href' => APP_URL . '/ar-tryon', 'active' => $currentPath === 'ar-tryon.php'],
    ['label' => 'Appointments', 'href' => APP_URL . '/appointment', 'active' => $currentPath === 'appointment.php'],
    ['label' => 'Staff', 'href' => APP_URL . '/team', 'active' => $currentPath === 'team.php'],
    ['label' => 'Testimonials', 'href' => APP_URL . '/reviews', 'active' => $currentPath === 'reviews.php'],
    ['label' => 'Blog', 'href' => APP_URL . '/blog', 'active' => $currentPath === 'blog.php' || $currentPath === 'blog-post.php'],
    ['label' => 'Contact', 'href' => APP_URL . '/contact', 'active' => $currentPath === 'contact.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo sanitize($companyName); ?> - Premium Eyewear and Optical Services">
    <meta name="keywords" content="eyewear, glasses, frames, lenses, optical, AR try-on">
    <meta name="author" content="<?php echo sanitize($companyName); ?>">
    <meta property="og:title" content="<?php echo sanitize($companyName); ?>">
    <meta property="og:description" content="Premium eyewear and optical services with AR try-on">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo APP_URL; ?>">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo sanitize($companyName); ?>">
    
    <title><?php echo getPageTitle(); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Flatpickr Date Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css?v=<?php echo $styleCssVersion; ?>">
    <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $faviconUrl; ?>?v=<?php echo $faviconVersion; ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $faviconUrl; ?>?v=<?php echo $faviconVersion; ?>">
    <link rel="apple-touch-icon" href="<?php echo $faviconUrl; ?>?v=<?php echo $faviconVersion; ?>">
</head>
<body>
    <script>window.BASE_URL = '<?php echo APP_URL; ?>';</script>
    <header class="site-header-wrap">
        <nav class="navbar navbar-expand-lg site-navbar" aria-label="Main navigation">
            <div class="container-lg">
                <a class="navbar-brand site-brand d-flex align-items-center gap-2" href="<?php echo APP_URL; ?>">
                    <span class="brand-mark">
                        <img class="site-logo-image" src="<?php echo $logoUrl; ?>" alt="<?php echo sanitize($companyName); ?>" onerror="this.closest('.brand-mark').classList.add('brand-mark-fallback'); this.style.display='none';">
                    </span>
                    <span class="brand-copy d-none d-sm-block">
                        <span class="brand-text"><?php echo sanitize($companyName); ?></span>
                        <span class="brand-subtext"><?php echo sanitize($displayTagline); ?></span>
                    </span>
                </a>

                <button class="navbar-toggler site-navbar-toggle ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation" tabindex="0">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <div class="site-nav-panel ms-auto d-flex flex-column flex-lg-row align-items-lg-center w-100">
                        <ul class="navbar-nav site-nav-links flex-column flex-lg-row w-100 mb-2 mb-lg-0">
                            <?php foreach ($primaryNav as $navItem): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $navItem['active'] ? 'active' : ''; ?> px-3 py-2" href="<?php echo $navItem['href']; ?>" tabindex="0">
                                    <?php echo $navItem['label']; ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>

                        <a href="<?php echo APP_URL; ?>/track-order" class="btn track-cta-btn mb-2 mb-lg-0 ms-lg-2 w-100 w-lg-auto d-flex align-items-center justify-content-center" style="min-width:44px;min-height:44px;" tabindex="0">
                            <i class="fas fa-location-dot me-2"></i> <span class="d-none d-sm-inline">Track Shipment</span>
                        </a>

                        <div class="navbar-utils d-flex align-items-center gap-2 mt-2 mt-lg-0">
                            <?php if (isLoggedIn()): ?>
                            <a href="<?php echo APP_URL; ?>/wishlist" class="navbar-utils-item btn-icon" title="Wishlist" aria-label="Wishlist" tabindex="0">
                                <i class="fas fa-heart"></i>
                                <span class="badge-counter"><?php echo getWishlistCount(); ?></span>
                            </a>
                            <?php endif; ?>

                            <a href="<?php echo APP_URL; ?>/checkout" class="navbar-utils-item btn-icon" title="Shopping Cart" aria-label="Shopping cart" tabindex="0">
                                <i class="fas fa-bag-shopping"></i>
                                <?php if ($cartCount > 0): ?>
                                <span class="badge-counter"><?php echo $cartCount; ?></span>
                                <?php endif; ?>
                            </a>

                            <div class="dropdown">
                                <button class="navbar-utils-item account-trigger dropdown-toggle d-flex align-items-center gap-1" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Account" tabindex="0">
                                    <i class="fas fa-user"></i>
                                    <span class="d-none d-sm-inline"><?php echo isLoggedIn() ? 'Account' : 'Login'; ?></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <?php if (isLoggedIn()): ?>
                                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/profile"><i class="fas fa-user"></i> My Profile</a></li>
                                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/my-orders"><i class="fas fa-box"></i> My Orders</a></li>
                                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/wishlist"><i class="fas fa-heart"></i> Wishlist</a></li>
                                    <?php if (isAdmin()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/login"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/register"><i class="fas fa-user-plus"></i> Register</a></li>
                                <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>
    <?php
    $flashMessage = getFlashMessage();
    if ($flashMessage):
    ?>
    <div class="container-lg mt-3">
        <div class="alert alert-<?php echo $flashMessage['type']; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php
                if ($flashMessage['type'] === 'success') echo 'check-circle';
                elseif ($flashMessage['type'] === 'error' || $flashMessage['type'] === 'danger') echo 'exclamation-circle';
                elseif ($flashMessage['type'] === 'warning') echo 'exclamation-triangle';
                else echo 'info-circle';
            ?>"></i>
            <?php echo $flashMessage['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>
