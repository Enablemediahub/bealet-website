<?php
/**
 * Bealet Website - Shop Page
 */

require_once __DIR__ . '/inc/header.php';

global $db;

// Pagination
$perPage = 12;
$currentPage = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$offset = ($currentPage - 1) * $perPage;

// Get filter parameters
$category = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 100000;
$searchQuery = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$sortBy = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';
$tryOnFilter = isset($_GET['try_on']) ? sanitize($_GET['try_on']) : '';

$productColumnMap = [];
try {
    foreach ($db->fetchAll("SHOW COLUMNS FROM products") as $column) {
        if (!empty($column['Field'])) {
            $productColumnMap[$column['Field']] = true;
        }
    }
} catch (Throwable $e) {
    $productColumnMap = [];
}

$hasArModel2dColumn = isset($productColumnMap['ar_model_2d']);
$hasArModel3dColumn = isset($productColumnMap['ar_model_3d']);

// Build query
$query = "SELECT * FROM products WHERE is_active = 1";
$countQuery = "SELECT COUNT(*) as total FROM products WHERE is_active = 1";
$params = [];

// Add filters
if (!empty($category)) {
    $query .= " AND category = ?";
    $countQuery .= " AND category = ?";
    $params[] = $category;
}

if (!empty($searchQuery)) {
    $query .= " AND (MATCH(name, description, brand) AGAINST(? IN BOOLEAN MODE) OR name LIKE ?)";
    $countQuery .= " AND (MATCH(name, description, brand) AGAINST(? IN BOOLEAN MODE) OR name LIKE ?)";
    $params[] = $searchQuery;
    $params[] = '%' . $searchQuery . '%';
}

if ($minPrice > 0) {
    $query .= " AND price >= ?";
    $countQuery .= " AND price >= ?";
    $params[] = $minPrice;
}

if ($maxPrice < 100000) {
    $query .= " AND price <= ?";
    $countQuery .= " AND price <= ?";
    $params[] = $maxPrice;
}

if ($tryOnFilter === 'ready' && ($hasArModel2dColumn || $hasArModel3dColumn)) {
    $tryOnParts = [];
    if ($hasArModel2dColumn) {
        $tryOnParts[] = "(ar_model_2d IS NOT NULL AND ar_model_2d <> '')";
    }
    if ($hasArModel3dColumn) {
        $tryOnParts[] = "(ar_model_3d IS NOT NULL AND ar_model_3d <> '')";
    }

    if (!empty($tryOnParts)) {
        $tryOnSql = implode(' OR ', $tryOnParts);
        $query .= " AND ($tryOnSql)";
        $countQuery .= " AND ($tryOnSql)";
    }
}

// Add sorting
switch ($sortBy) {
    case 'price_low':
        $query .= " ORDER BY price ASC";
        break;
    case 'price_high':
        $query .= " ORDER BY price DESC";
        break;
    case 'popular':
        $query .= " ORDER BY id DESC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY created_at DESC";
}

$query .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

// Get products
$products = $db->fetchAll($query, $params);

// Get total count
$countParams = array_slice($params, 0, count($params) - 2);
$totalResult = $db->fetch($countQuery, $countParams);
$totalProducts = $totalResult['total'];
$totalPages = ceil($totalProducts / $perPage);
$paginationBaseQuery = http_build_query([
    'category' => $category,
    'min_price' => $minPrice,
    'max_price' => $maxPrice,
    'search' => $searchQuery,
    'sort' => $sortBy,
    'try_on' => $tryOnFilter,
]);
$paginationWindowStart = max(1, $currentPage - 2);
$paginationWindowEnd = min($totalPages, $currentPage + 2);

if ($paginationWindowEnd - $paginationWindowStart < 4) {
    $paginationWindowStart = max(1, $paginationWindowEnd - 4);
    $paginationWindowEnd = min($totalPages, $paginationWindowStart + 4);
}

// Get categories
$categories = $db->fetchAll("SELECT DISTINCT category FROM products WHERE is_active = 1 ORDER BY category");

// Get brands
$brands = $db->fetchAll("SELECT DISTINCT brand FROM products WHERE is_active = 1 ORDER BY brand");

?>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mt-4 mb-4">
        <div class="container-lg">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/">Home</a></li>
                <li class="breadcrumb-item active">Shop</li>
            </ol>
        </div>
    </nav>
    
    <!-- Page Header -->
    <section class="mb-5">
        <div class="container-lg">
            <h1 class="mb-2">Our Collections</h1>
            <p class="text-muted">Browse our premium selection of eyewear</p>
        </div>
    </section>
    
    <!-- Shop Section -->
    <section class="section-spacing">
        <div class="container-lg">
            <div class="row g-4">
                <!-- Sidebar - Filters -->
                <div class="col-lg-3">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-filter me-2"></i> Filters</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" id="filterForm">
                                <!-- Search -->
                                <div class="mb-4">
                                    <label class="form-label">Search Products</label>
                                    <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo sanitize($searchQuery); ?>">
                                </div>
                                
                                <!-- Category -->
                                <div class="mb-4">
                                    <label class="form-label">Category</label>
                                    <div class="form-check">
                                        <input type="radio" name="category" value="" id="cat-all" class="form-check-input" <?php echo empty($category) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="cat-all">All Categories</label>
                                    </div>
                                    <?php foreach ($categories as $cat): ?>
                                    <div class="form-check">
                                        <input type="radio" name="category" value="<?php echo sanitize($cat['category']); ?>" id="cat-<?php echo sanitize($cat['category']); ?>" class="form-check-input" <?php echo $category === $cat['category'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="cat-<?php echo sanitize($cat['category']); ?>">
                                            <?php echo sanitize($cat['category']); ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Price Range -->
                                <div class="mb-4">
                                    <label class="form-label">Price Range</label>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <input type="number" name="min_price" class="form-control" placeholder="Min" value="<?php echo $minPrice; ?>">
                                        </div>
                                        <div class="col-6">
                                            <input type="number" name="max_price" class="form-control" placeholder="Max" value="<?php echo $maxPrice; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Sort -->
                                <div class="mb-4">
                                    <label class="form-label">Sort By</label>
                                    <select name="sort" class="form-select">
                                        <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest</option>
                                        <option value="popular" <?php echo $sortBy === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                                        <option value="price_low" <?php echo $sortBy === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                        <option value="price_high" <?php echo $sortBy === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Try-On</label>
                                    <div class="form-check">
                                        <input type="radio" name="try_on" value="" id="tryon-all" class="form-check-input" <?php echo $tryOnFilter === '' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="tryon-all">All Products</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="radio" name="try_on" value="ready" id="tryon-ready" class="form-check-input" <?php echo $tryOnFilter === 'ready' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="tryon-ready">Try-On Ready</label>
                                    </div>
                                </div>
                                
                                <!-- Buttons -->
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                                    <a href="<?php echo APP_URL; ?>/shop.php" class="btn btn-outline-primary">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Products Grid -->
                <div class="col-lg-9">
                    <!-- Results Info -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <p class="mb-0 text-muted">
                                Showing <strong><?php echo $totalProducts > 0 ? ((($currentPage - 1) * $perPage) + 1) : 0; ?></strong> to 
                                <strong><?php echo $totalProducts > 0 ? min($currentPage * $perPage, $totalProducts) : 0; ?></strong> 
                                of <strong><?php echo $totalProducts; ?></strong> products
                            </p>
                        </div>
                    </div>
                    
                    <?php if (!empty($products)): ?>
                    <!-- Products -->
                    <div class="row g-4">
                        <?php foreach ($products as $product): ?>
                        <?php $galleryImages = getProductGalleryImages((int) $product['id'], $product); ?>
                        <?php $productTryOnLink = getProductTryOnLink($product); ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card product-card h-100">
                                <?php if (count($galleryImages) > 1): ?>
                                <div id="productGallery<?php echo (int) $product['id']; ?>" class="carousel slide product-gallery-carousel" data-bs-interval="false">
                                    <div class="carousel-inner">
                                        <?php foreach ($galleryImages as $imageIndex => $galleryImage): ?>
                                        <div class="carousel-item <?php echo $imageIndex === 0 ? 'active' : ''; ?>">
                                            <img src="<?php echo $galleryImage; ?>" alt="<?php echo sanitize($product['name']); ?> image <?php echo $imageIndex + 1; ?>" class="product-image">
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button class="carousel-control-prev" type="button" data-bs-target="#productGallery<?php echo (int) $product['id']; ?>" data-bs-slide="prev" aria-label="Previous image">
                                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    </button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#productGallery<?php echo (int) $product['id']; ?>" data-bs-slide="next" aria-label="Next image">
                                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    </button>
                                    <div class="product-gallery-dots">
                                        <?php foreach ($galleryImages as $imageIndex => $galleryImage): ?>
                                        <button type="button" data-bs-target="#productGallery<?php echo (int) $product['id']; ?>" data-bs-slide-to="<?php echo $imageIndex; ?>" class="<?php echo $imageIndex === 0 ? 'active' : ''; ?>" aria-label="View image <?php echo $imageIndex + 1; ?>"></button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php else: ?>
                                <img src="<?php echo $galleryImages[0] ?? getProductImagePath($product); ?>" alt="<?php echo sanitize($product['name']); ?>" class="product-image">
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
                                        <span class="rating-text"><?php echo $rating['average']; ?>/5</span>
                                    </div>
                                    
                                    <!-- Stock Status -->
                                    <div class="mb-3">
                                        <?php if ($product['stock'] > 0): ?>
                                        <span class="badge badge-success">In Stock</span>
                                        <?php else: ?>
                                        <span class="badge badge-danger">Out of Stock</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <?php if ($productTryOnLink !== ''): ?>
                                        <a class="btn btn-outline-dark" href="<?php echo sanitize($productTryOnLink); ?>">
                                            <i class="fas fa-vr-cardboard me-2"></i> Try On
                                        </a>
                                        <?php endif; ?>
                                        <button 
                                            class="btn btn-primary" 
                                            type="button"
                                            onclick="addToCart(<?php echo $product['id']; ?>, 1)"
                                            <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>
                                        >
                                            <i class="fas fa-shopping-cart me-2"></i> Add to Cart
                                        </button>
                                        <button
                                            class="btn btn-outline-primary wishlist-btn"
                                            type="button"
                                            data-product-id="<?php echo (int) $product['id']; ?>"
                                            onclick="toggleWishlist(<?php echo (int) $product['id']; ?>, this)"
                                        >
                                            <i class="fas fa-heart me-2"></i> Wishlist
                                        </button>
                                        <button class="btn btn-outline-secondary" type="button" onclick="openImageModal(<?php echo htmlspecialchars(json_encode($galleryImages), ENT_QUOTES, 'UTF-8'); ?>, '<?php echo sanitize(addslashes($product['name'])); ?>')">
                                            <i class="fas fa-eye me-2"></i> View
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-5">
                        <ul class="pagination justify-content-center">
                            <?php if ($currentPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo APP_URL; ?>/shop.php?page=<?php echo $currentPage - 1; ?>&<?php echo $paginationBaseQuery; ?>">
                                    Previous
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php if ($paginationWindowStart > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo APP_URL; ?>/shop.php?page=1&<?php echo $paginationBaseQuery; ?>">1</a>
                            </li>
                            <?php if ($paginationWindowStart > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $paginationWindowStart; $i <= $paginationWindowEnd; $i++): ?>
                            <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo APP_URL; ?>/shop.php?page=<?php echo $i; ?>&<?php echo $paginationBaseQuery; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($paginationWindowEnd < $totalPages): ?>
                            <?php if ($paginationWindowEnd < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo APP_URL; ?>/shop.php?page=<?php echo $totalPages; ?>&<?php echo $paginationBaseQuery; ?>"><?php echo $totalPages; ?></a>
                            </li>
                            <?php endif; ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo APP_URL; ?>/shop.php?page=<?php echo $currentPage + 1; ?>&<?php echo $paginationBaseQuery; ?>">
                                    Next
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <!-- No Products -->
                    <div class="text-center py-5">
                        <i class="fas fa-search" style="font-size: 4rem; color: #E5E7EB;"></i>
                        <h4 class="mt-3 text-muted">No products found</h4>
                        <p class="text-muted">Try adjusting your filters or search criteria</p>
                        <a href="<?php echo APP_URL; ?>/shop.php" class="btn btn-primary">View All Products</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Product Image Modal -->
    <div class="modal fade" id="productImageModal" tabindex="-1" aria-labelledby="productImageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productImageModalLabel">Product Images</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="productImageModalCarousel" class="carousel slide" data-bs-interval="false">
                        <div class="carousel-inner" id="productImageModalCarouselInner"></div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#productImageModalCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#productImageModalCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .product-modal-image {
        max-height: 70vh;
        object-fit: contain;
        width: 100%;
        background: #fff;
        border-radius: 0.75rem;
    }

    @media (max-width: 768px) {
        .modal-dialog.modal-lg {
            max-width: 98vw;
            margin: 0.5rem auto;
        }

        .product-modal-image {
            max-height: 45vh;
        }

        .modal-body {
            padding: 0.5rem 0.5rem;
        }
    }

    @media (max-width: 480px) {
        .modal-dialog.modal-lg {
            max-width: 100vw;
            margin: 0.25rem auto;
        }

        .product-modal-image {
            max-height: 32vh;
        }
    }
    </style>

    <script>
    (function () {
        var modalElement = document.getElementById('productImageModal');
        var modalTitle = document.getElementById('productImageModalLabel');
        var carouselInner = document.getElementById('productImageModalCarouselInner');
        var carouselElement = document.getElementById('productImageModalCarousel');
        var modalInstance = null;
        var activeCarouselInstance = null;
        var wheelLock = false;

        function moveModalCarousel(direction) {
            if (!activeCarouselInstance) {
                return;
            }

            if (direction > 0) {
                activeCarouselInstance.next();
            } else if (direction < 0) {
                activeCarouselInstance.prev();
            }
        }

        if (carouselElement) {
            carouselElement.addEventListener('wheel', function (event) {
                if (!modalElement || !modalElement.classList.contains('show')) {
                    return;
                }

                if (Math.abs(event.deltaY) < 8 || wheelLock) {
                    return;
                }

                event.preventDefault();
                wheelLock = true;
                moveModalCarousel(event.deltaY);

                window.setTimeout(function () {
                    wheelLock = false;
                }, 350);
            }, { passive: false });
        }

        window.openImageModal = function (images, productName) {
            if (!modalElement || !Array.isArray(images) || images.length === 0) {
                return;
            }

            var validImages = images.filter(function (image) {
                return typeof image === 'string' && image.trim() !== '';
            });

            if (validImages.length === 0) {
                return;
            }

            carouselInner.innerHTML = '';

            validImages.forEach(function (image, index) {
                var item = document.createElement('div');
                item.className = 'carousel-item' + (index === 0 ? ' active' : '');

                var img = document.createElement('img');
                img.className = 'd-block w-100 product-modal-image';
                img.src = image;
                img.alt = productName + ' image ' + (index + 1);

                item.appendChild(img);
                carouselInner.appendChild(item);
            });

            modalTitle.textContent = productName;

            if (typeof bootstrap === 'undefined') {
                return;
            }

            if (!modalInstance) {
                modalInstance = new bootstrap.Modal(modalElement);
            }

            var carouselInstance = bootstrap.Carousel.getInstance(carouselElement);
            if (carouselInstance) {
                carouselInstance.dispose();
            }

            activeCarouselInstance = new bootstrap.Carousel(carouselElement, {
                interval: false
            });

            modalInstance.show();
        };
    })();
    </script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
