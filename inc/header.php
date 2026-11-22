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
$manifestPath = __DIR__ . '/../manifest.webmanifest';
$manifestVersion = file_exists($manifestPath) ? (string) filemtime($manifestPath) : '1';
$currentPath = basename($_SERVER['PHP_SELF'] ?? '');
$productPreview = null;
if ($currentPath === 'shop.php' && !empty($_GET['view_product'])) {
    $viewProductId = (int) $_GET['view_product'];
    if ($viewProductId > 0) {
        $productPreview = $db->fetch(
            "SELECT * FROM products WHERE id = ? AND is_active = 1 LIMIT 1",
            [$viewProductId]
        );
    }
}

$metaTitle = $pageMetaTitle ?? $companyName;
$metaDescription = $pageMetaDescription ?? 'Premium eyewear and optical services with AR try-on';
$metaUrl = $pageMetaUrl ?? APP_URL;
$metaImage = $pageMetaImage ?? $logoUrl;

if ($productPreview) {
    $metaTitle = trim((string) ($productPreview['name'] ?? $companyName));
    $metaDescription = trim(strip_tags((string) ($productPreview['description'] ?? '')));
    if ($metaDescription === '') {
        $metaDescription = 'Discover this premium eyewear product from ' . $companyName . '.';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($metaDescription) > 180) {
        $metaDescription = mb_substr($metaDescription, 0, 177) . '...';
    } elseif (strlen($metaDescription) > 180) {
        $metaDescription = substr($metaDescription, 0, 177) . '...';
    }
    $metaUrl = APP_URL . '/shop.php?view_product=' . (int) $productPreview['id'];
    $metaImage = getProductImagePath($productPreview);
}

$primaryNav = [
    ['label' => 'Home', 'href' => APP_URL . '/', 'active' => $currentPath === 'index.php' || $currentPath === ''],
    ['label' => 'Shop', 'href' => APP_URL . '/shop.php', 'active' => $currentPath === 'shop.php'],
    ['label' => 'Try-On', 'href' => APP_URL . '/ar-tryon.php', 'active' => $currentPath === 'ar-tryon.php'],
    ['label' => 'Staff', 'href' => APP_URL . '/team.php', 'active' => $currentPath === 'team.php'],
    ['label' => 'Contact', 'href' => APP_URL . '/contact.php', 'active' => $currentPath === 'contact.php'],
    [
        'label' => 'More',
        'active' => in_array($currentPath, ['reviews.php', 'track-order.php', 'appointment.php', 'blog.php', 'blog-post.php', 'hiring.php', 'founder.php'], true),
        'items' => [
            ['label' => 'Testimonials', 'href' => APP_URL . '/reviews.php', 'active' => $currentPath === 'reviews.php'],
            ['label' => 'Founder Spotlight', 'href' => APP_URL . '/founder.php', 'active' => $currentPath === 'founder.php'],
            ['label' => 'Track Shipment', 'href' => APP_URL . '/track-order.php', 'active' => $currentPath === 'track-order.php'],
            ['label' => 'Appointment', 'href' => APP_URL . '/appointment.php', 'active' => $currentPath === 'appointment.php'],
            ['label' => 'Blog', 'href' => APP_URL . '/blog.php', 'active' => $currentPath === 'blog.php' || $currentPath === 'blog-post.php'],
            ['label' => 'Careers', 'href' => APP_URL . '/hiring.php', 'active' => $currentPath === 'hiring.php'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo sanitize($metaDescription); ?>">
    <meta name="keywords" content="eyewear, glasses, frames, lenses, optical, AR try-on">
    <meta name="author" content="<?php echo sanitize($companyName); ?>">
    <meta property="og:title" content="<?php echo sanitize($metaTitle); ?>">
    <meta property="og:description" content="<?php echo sanitize($metaDescription); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo sanitize($metaUrl); ?>">
    <meta property="og:image" content="<?php echo sanitize($metaImage); ?>">
    <meta property="og:image:alt" content="<?php echo sanitize($metaTitle); ?>">
    <meta property="og:site_name" content="<?php echo sanitize($companyName); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo sanitize($metaTitle); ?>">
    <meta name="twitter:description" content="<?php echo sanitize($metaDescription); ?>">
    <meta name="twitter:image" content="<?php echo sanitize($metaImage); ?>">
    <?php if (!empty($pageMetaUrl)): ?>
    <link rel="canonical" href="<?php echo sanitize($pageMetaUrl); ?>">
    <?php endif; ?>
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo sanitize($companyName); ?>">
    
    <title><?php echo sanitize($pageMetaTitle ?? getPageTitle()); ?></title>
    
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
    <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.webmanifest?v=<?php echo $manifestVersion; ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $faviconUrl; ?>?v=<?php echo $faviconVersion; ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $faviconUrl; ?>?v=<?php echo $faviconVersion; ?>">
    <link rel="apple-touch-icon" href="<?php echo $faviconUrl; ?>?v=<?php echo $faviconVersion; ?>">
</head>
<body<?php echo ($currentPath === 'index.php' || $currentPath === '') ? ' class="home-page"' : ''; ?>>
    <script>window.BASE_URL = '<?php echo APP_URL; ?>';</script>
    <header class="site-header-wrap">
        <nav class="navbar navbar-expand-lg site-navbar" aria-label="Main navigation">
            <div class="container-lg">
                <a class="navbar-brand site-brand d-flex align-items-center gap-2" href="<?php echo APP_URL; ?>">
                    <span class="brand-mark">
                        <img class="site-logo-image" src="<?php echo $logoUrl; ?>" alt="<?php echo sanitize($companyName); ?>" onerror="this.closest('.brand-mark').classList.add('brand-mark-fallback'); this.style.display='none';">
                    </span>
                    <span class="brand-copy">
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
                            <?php if (isset($navItem['items'])): ?>
                            <li class="nav-item dropdown">
                                <button class="nav-link dropdown-toggle <?php echo $navItem['active'] ? 'active' : ''; ?> px-3 py-2" type="button" id="moreNavDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?php echo $navItem['label']; ?>
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="moreNavDropdown">
                                    <?php foreach ($navItem['items'] as $moreNavItem): ?>
                                    <li>
                                        <a class="dropdown-item <?php echo $moreNavItem['active'] ? 'active' : ''; ?>" href="<?php echo $moreNavItem['href']; ?>">
                                            <?php echo $moreNavItem['label']; ?>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                            <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $navItem['active'] ? 'active' : ''; ?> px-3 py-2" href="<?php echo $navItem['href']; ?>" tabindex="0">
                                    <?php echo $navItem['label']; ?>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>

                        <div class="navbar-utils d-flex align-items-center gap-2 mt-2 mt-lg-0">
                            <?php if (isLoggedIn()): ?>
                            <a href="<?php echo APP_URL; ?>/wishlist.php" class="navbar-utils-item btn-icon" title="Wishlist" aria-label="Wishlist" tabindex="0">
                                <i class="fas fa-heart"></i>
                                <span class="badge-counter"><?php echo getWishlistCount(); ?></span>
                            </a>
                            <?php endif; ?>

                            <?php if (defined('CART_SYSTEM_ENABLED') && CART_SYSTEM_ENABLED): ?>
                            <a href="<?php echo APP_URL; ?>/checkout.php" class="navbar-utils-item btn-icon" title="Shopping Cart" aria-label="Shopping cart" tabindex="0">
                                <i class="fas fa-bag-shopping"></i>
                                <?php if ($cartCount > 0): ?>
                                <span class="badge-counter"><?php echo $cartCount; ?></span>
                                <?php endif; ?>
                            </a>
                            <?php endif; ?>

                            <div class="dropdown">
                                <button class="navbar-utils-item account-trigger dropdown-toggle d-flex align-items-center gap-1" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Account" tabindex="0">
                                    <i class="fas fa-user"></i>
                                    <span class="d-none d-sm-inline"><?php echo isLoggedIn() ? 'Account' : 'Login'; ?></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <?php if (isLoggedIn()): ?>
                                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/my-orders.php"><i class="fas fa-box"></i> My Orders</a></li>
                                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/wishlist.php"><i class="fas fa-heart"></i> Wishlist</a></li>
                                    <?php if (isAdmin()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/register.php"><i class="fas fa-user-plus"></i> Register</a></li>
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
