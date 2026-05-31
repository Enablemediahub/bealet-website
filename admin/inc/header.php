<?php
/**
 * Bealet Website - Admin Header
 */

require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/functions.php';

// Verify admin access
if (!isLoggedIn() || !isAdmin()) {
    session_destroy();
    redirect(APP_URL . '/admin/login.php');
}

$adminUser = getCurrentUser();
$adminRole = getUserAdminRole($adminUser);
$isSuperAdminUser = isSuperAdmin($adminUser);

$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
$pageTitleMap = [
    'dashboard.php' => 'Dashboard Overview',
    'products.php' => 'Product Catalog',
    'orders.php' => 'Order Management',
    'appointments.php' => 'Appointments',
    'staff.php' => 'Staff Directory',
    'customers.php' => 'Customer Directory',
    'reviews.php' => 'Customer Reviews',
    'contacts.php' => 'Inbox',
    'blog.php' => 'Content Studio',
    'founder.php' => 'Founder Museum',
    'hero-slides.php' => 'Hero Slides',
    'settings.php' => 'Site Settings',
    'profile.php' => 'Profile',
];
$currentPageTitle = $pageTitleMap[$currentScript] ?? 'Admin Panel';
$profileActive = $currentScript === 'profile.php' ? 'active' : '';
$adminCssVersion = @filemtime(__DIR__ . '/../../assets/css/admin.css') ?: time();
$styleCssVersion = @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time();
$faviconPath = __DIR__ . '/../../assets/images/favicon.png';
$faviconUrl = APP_URL . '/assets/images/favicon.png';
$faviconVersion = file_exists($faviconPath) ? (string) filemtime($faviconPath) : '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Admin - <?php echo getCompanyName(); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css?v=<?php echo $styleCssVersion; ?>">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css?v=<?php echo $adminCssVersion; ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $faviconUrl; ?>?v=<?php echo $faviconVersion; ?>">
    <link rel="apple-touch-icon" href="<?php echo $faviconUrl; ?>?v=<?php echo $faviconVersion; ?>">
    <script>window.BASE_URL = '<?php echo APP_URL; ?>';</script>
</head>
<body class="admin-body">
    <!-- Admin Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-brand">
            <div class="logo d-flex align-items-center gap-3">
                <div class="sidebar-logo-mark">
                    <img src="<?php echo getSiteLogoUrl(); ?>" alt="<?php echo getCompanyName(); ?>" onerror="this.style.display='none';">
                </div>
                <div class="sidebar-brand-copy">
                    <span class="text-nowrap d-block"><?php echo getCompanyName(); ?></span>
                    <small>Control Suite</small>
                </div>
            </div>
            <div class="sidebar-rail-accent" aria-hidden="true"></div>
        </div>

        <div class="sidebar-user-card">
            <div class="sidebar-user-avatar">
                <?php echo strtoupper(substr($adminUser['name'], 0, 1)); ?>
            </div>
            <div class="sidebar-user-copy">
                <strong><?php echo sanitize($adminUser['name']); ?></strong>
                <span><?php echo sanitize(getAdminRoleLabel($adminRole)); ?></span>
            </div>
            <div class="sidebar-user-status">
                <span class="sidebar-status-dot"></span>
                <small>Live</small>
            </div>
        </div>
        
        <nav class="nav flex-column">
            <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <div class="nav-section-title">Shop</div>
                <?php if ($isSuperAdminUser): ?>
                    <a href="<?php echo APP_URL; ?>/admin/products.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'products.php') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-boxes"></i>
                        <span>Products</span>
                    </a>
                <?php endif; ?>
                <a href="<?php echo APP_URL; ?>/admin/orders.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'orders.php') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-bag"></i>
                    <span>Orders</span>
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Bookings</div>
                <a href="<?php echo APP_URL; ?>/admin/appointments.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'appointments.php') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-calendar"></i>
                    <span>Appointments</span>
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Users</div>
                <?php if ($isSuperAdminUser): ?>
                    <a href="<?php echo APP_URL; ?>/admin/staff.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'staff.php') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-id-badge"></i>
                        <span>Staff</span>
                    </a>
                <?php endif; ?>
                <a href="<?php echo APP_URL; ?>/admin/customers.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'customers.php') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
                <a href="<?php echo APP_URL; ?>/admin/contacts.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'contacts.php') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                </a>
                <a href="reviews.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'reviews.php') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-star-half-stroke"></i>
                    <span>Reviews</span>
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Content</div>
                <?php if ($isSuperAdminUser): ?>
                    <a href="<?php echo APP_URL; ?>/admin/blog.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'blog.php') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-pen-fancy"></i>
                        <span>Blog</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/founder.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'founder.php') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-landmark"></i>
                        <span>Founder</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/hero-slides.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'hero-slides.php') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-images"></i>
                        <span>Hero Slides</span>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/settings.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'settings.php') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-gear"></i>
                        <span>Site Settings</span>
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Account</div>
                <a href="<?php echo APP_URL; ?>/admin/profile.php" class="nav-link <?php echo $profileActive; ?>">
                    <i class="fas fa-user-circle"></i>
                    <span>Profile</span>
                </a>
                <a href="<?php echo APP_URL; ?>/admin/logout.php" class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-footer-card">
            <small>Workspace Mode</small>
            <strong><?php echo $currentPageTitle; ?></strong>
            <span>Designed to stay compact even when the page is zoomed in.</span>
        </div>
    </aside>
    
    <!-- Admin Content -->
    <div class="admin-content">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h5 class="mb-0"><?php echo $currentPageTitle; ?></h5>
                    <small class="admin-topbar-subtitle">Calm, focused control for the store.</small>
                </div>
            </div>
            <div class="admin-topbar-user">
                <a href="<?php echo APP_URL; ?>" class="admin-visit-site">
                    <i class="fas fa-arrow-up-right-from-square"></i>
                    <span>View Site</span>
                </a>
                <div class="text-end">
                    <p class="mb-0"><strong><?php echo sanitize($adminUser['name']); ?></strong></p>
                    <small class="text-muted"><?php echo sanitize(getAdminRoleLabel($adminRole)); ?></small>
                </div>
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($adminUser['name']); ?>&background=2563EB&color=fff" alt="Avatar" class="rounded-circle" width="40" height="40">
            </div>
        </div>

    <script>
        // Sidebar toggle for mobile
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const adminSidebar = document.getElementById('adminSidebar');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    adminSidebar.classList.toggle('show');
                });
            }
            
            // Close sidebar when a link is clicked on mobile
            if (window.innerWidth <= 768) {
                document.querySelectorAll('.admin-sidebar .nav-link').forEach(link => {
                    link.addEventListener('click', function() {
                        adminSidebar.classList.remove('show');
                    });
                });
            }
        });
    </script>
