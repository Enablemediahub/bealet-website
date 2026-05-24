<?php
/**
 * Bealet Website - Admin Products Management
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(APP_URL . '/admin/login.php');
}

requireSuperAdmin();

global $db;

$errors = [];
ensureProductCategoryStorageSupport();
ensureProductGalleryTable();
ensureProductReviewSupport();
$productColumns = $db->fetchAll("SHOW COLUMNS FROM products");
$productColumnMap = [];

foreach ($productColumns as $column) {
    if (!empty($column['Field'])) {
        $productColumnMap[$column['Field']] = true;
    }
}

if (!isset($productColumnMap['is_featured'])) {
    try {
        $db->update("ALTER TABLE products ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0");
        $productColumns = $db->fetchAll("SHOW COLUMNS FROM products");
        $productColumnMap = [];
        foreach ($productColumns as $column) {
            if (!empty($column['Field'])) {
                $productColumnMap[$column['Field']] = true;
            }
        }
    } catch (Throwable $e) {
        $errors[] = 'Could not enable featured-product support: ' . $e->getMessage();
    }
}

foreach ([
    "ALTER TABLE products ADD COLUMN ar_model_2d_left VARCHAR(255) NULL",
    "ALTER TABLE products ADD COLUMN ar_model_2d_right VARCHAR(255) NULL",
] as $alterStatement) {
    try {
        $db->update($alterStatement);
    } catch (Throwable $e) {
        // Ignore when the column already exists or cannot be added in this pass.
    }
}

$productColumns = $db->fetchAll("SHOW COLUMNS FROM products");
$productColumnMap = [];
foreach ($productColumns as $column) {
    if (!empty($column['Field'])) {
        $productColumnMap[$column['Field']] = true;
    }
}

$imageColumn = isset($productColumnMap['main_image']) ? 'main_image' : (isset($productColumnMap['image']) ? 'image' : null);
$materialColumn = isset($productColumnMap['frame_material']) ? 'frame_material' : (isset($productColumnMap['material']) ? 'material' : null);
$colorColumn = isset($productColumnMap['frame_color']) ? 'frame_color' : (isset($productColumnMap['color']) ? 'color' : null);
$hasMaterialColumn = $materialColumn !== null;
$hasColorColumn = $colorColumn !== null;
$hasUpdatedAtColumn = isset($productColumnMap['updated_at']);
$hasCreatedAtColumn = isset($productColumnMap['created_at']);
$hasIsActiveColumn = isset($productColumnMap['is_active']);
$hasIsFeaturedColumn = isset($productColumnMap['is_featured']);
$hasFrameTargetColumn = isset($productColumnMap['frame_target']);
$hasArModel2dColumn = isset($productColumnMap['ar_model_2d']);
$hasArModel2dLeftColumn = isset($productColumnMap['ar_model_2d_left']);
$hasArModel2dRightColumn = isset($productColumnMap['ar_model_2d_right']);
$hasArModel3dColumn = isset($productColumnMap['ar_model_3d']);
$hasArPositionXColumn = isset($productColumnMap['ar_position_x']);
$hasArPositionYColumn = isset($productColumnMap['ar_position_y']);
$hasArScaleColumn = isset($productColumnMap['ar_scale']);
$hasSlugColumn = isset($productColumnMap['slug']);

/**
 * Build a unique product slug when the database enforces slug uniqueness.
 */
function buildUniqueProductSlug($name, $productId = 0) {
    global $db;

    $baseSlug = generateSlug(decodeStoredText($name));
    if ($baseSlug === '') {
        $baseSlug = 'product';
    }

    $slug = $baseSlug;
    $suffix = 2;

    while (true) {
        $params = [$slug];
        $query = "SELECT id FROM products WHERE slug = ?";

        if ((int) $productId > 0) {
            $query .= " AND id <> ?";
            $params[] = (int) $productId;
        }

        $existing = $db->fetch($query . " LIMIT 1", $params);
        if (!$existing) {
            return $slug;
        }

        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

/**
 * Resolve a product image to a local filesystem path when possible.
 */
function getAdminProductImageLocalPath($image) {
    if (empty($image)) {
        return null;
    }

    $image = trim((string) $image);
    if (filter_var($image, FILTER_VALIDATE_URL)) {
        return null;
    }

    $normalized = ltrim(str_replace('\\', '/', $image), '/');
    $basename = basename($normalized);

    $candidates = [
        __DIR__ . '/../' . $normalized,
        __DIR__ . '/../assets/uploads/products/' . $basename,
        __DIR__ . '/../assets/images/' . $basename,
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Resolve an uploaded try-on asset to a local filesystem path when possible.
 */
function getAdminTryOnAssetLocalPath($asset) {
    if (empty($asset)) {
        return null;
    }

    $asset = trim((string) $asset);
    if (filter_var($asset, FILTER_VALIDATE_URL)) {
        return null;
    }

    $normalized = ltrim(str_replace('\\', '/', $asset), '/');
    $basename = basename($normalized);

    $candidates = [
        __DIR__ . '/../' . $normalized,
        __DIR__ . '/../assets/uploads/ar-models/' . $basename,
        __DIR__ . '/../assets/images/ar-models/' . $basename,
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

// Handle product deletion
if (isset($_GET['delete'])) {
    $productId = (int) $_GET['delete'];

    foreach (getProductAdditionalImages($productId) as $galleryImagePath) {
        $localGalleryPath = getAdminProductImageLocalPath($galleryImagePath);
        if ($localGalleryPath && str_contains(str_replace('\\', '/', $localGalleryPath), '/assets/uploads/products/')) {
            unlink($localGalleryPath);
        }
    }

    $db->delete("DELETE FROM product_images WHERE product_id = ?", [$productId]);

    if ($imageColumn) {
        $product = $db->fetch("SELECT {$imageColumn} AS product_image FROM products WHERE id = ?", [$productId]);
        if (!empty($product['product_image'])) {
            $imagePath = getAdminProductImageLocalPath($product['product_image']);
            if ($imagePath && str_contains(str_replace('\\', '/', $imagePath), '/assets/uploads/products/')) {
                unlink($imagePath);
            }
        }
    }

    if ($hasArModel2dColumn || $hasArModel2dLeftColumn || $hasArModel2dRightColumn || $hasArModel3dColumn) {
        $tryOnFields = [];
        if ($hasArModel2dColumn) {
            $tryOnFields[] = 'ar_model_2d';
        }
        if ($hasArModel2dLeftColumn) {
            $tryOnFields[] = 'ar_model_2d_left';
        }
        if ($hasArModel2dRightColumn) {
            $tryOnFields[] = 'ar_model_2d_right';
        }
        if ($hasArModel3dColumn) {
            $tryOnFields[] = 'ar_model_3d';
        }

        if (!empty($tryOnFields)) {
            $tryOnAssetRow = $db->fetch(
                "SELECT " . implode(', ', $tryOnFields) . " FROM products WHERE id = ?",
                [$productId]
            );

            foreach ($tryOnFields as $assetField) {
                if (empty($tryOnAssetRow[$assetField])) {
                    continue;
                }

                $assetPath = getAdminTryOnAssetLocalPath($tryOnAssetRow[$assetField]);
                if ($assetPath && str_contains(str_replace('\\', '/', $assetPath), '/assets/uploads/ar-models/')) {
                    unlink($assetPath);
                }
            }
        }
    }

    if ($hasIsActiveColumn) {
        $db->update("UPDATE products SET is_active = 0 WHERE id = ?", [$productId]);
    } else {
        $db->delete("DELETE FROM products WHERE id = ?", [$productId]);
    }

    createLog('PRODUCT_DELETED', "Product #$productId deactivated");
    setFlashMessage('success', 'Product deleted successfully');
    redirect(APP_URL . '/admin/products.php');
}

// Handle product addition/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $brand = sanitize($_POST['brand'] ?? '');
        $category = normalizeProductCategoryKey($_POST['category'] ?? '');
        $frameTarget = sanitize($_POST['frame_target'] ?? '');
        $description = trim(decodeStoredText($_POST['description'] ?? ''));
        $price = (float) ($_POST['price'] ?? 0);
        $stock = (int) ($_POST['stock'] ?? 0);
        $material = sanitize($_POST['material'] ?? '');
        $color = sanitize($_POST['color'] ?? '');
        $arPositionX = (int) ($_POST['ar_position_x'] ?? 0);
        $arPositionY = (int) ($_POST['ar_position_y'] ?? 0);
        $arScale = (float) ($_POST['ar_scale'] ?? 1);
        $removeProductImage = isset($_POST['remove_product_image']);
        $removeArModel2d = isset($_POST['remove_ar_model_2d']);
        $removeArModel2dLeft = isset($_POST['remove_ar_model_2d_left']);
        $removeArModel2dRight = isset($_POST['remove_ar_model_2d_right']);
        $removeArModel3d = isset($_POST['remove_ar_model_3d']);
        $isFeatured = $hasIsFeaturedColumn && isset($_POST['is_featured']) ? 1 : 0;
        $productSlug = $hasSlugColumn ? buildUniqueProductSlug($name, $productId) : '';
        $removeGalleryImages = [];
        for ($slot = 1; $slot <= 4; $slot++) {
            $removeGalleryImages[$slot] = isset($_POST['remove_gallery_image_' . $slot]);
        }

        if (empty($name)) {
            $errors[] = 'Product name is required';
        }
        if (empty($category)) {
            $errors[] = 'Category is required';
        }
        if ($frameTarget !== '' && !isset(getProductAudienceOptions()[$frameTarget])) {
            $errors[] = 'Please select a valid frame audience.';
        }
        if ($price <= 0) {
            $errors[] = 'Price must be greater than 0';
        }

        $existingProduct = null;
        if ($productId > 0) {
            $selectFields = ['id'];
            if ($imageColumn) {
                $selectFields[] = $imageColumn . ' AS product_image';
            }
            if ($hasArModel2dColumn) {
                $selectFields[] = 'ar_model_2d';
            }
            if ($hasArModel2dLeftColumn) {
                $selectFields[] = 'ar_model_2d_left';
            }
            if ($hasArModel2dRightColumn) {
                $selectFields[] = 'ar_model_2d_right';
            }
            if ($hasArModel3dColumn) {
                $selectFields[] = 'ar_model_3d';
            }

            $existingProduct = $db->fetch(
                "SELECT " . implode(', ', $selectFields) . " FROM products WHERE id = ?",
                [$productId]
            );

            if (!$existingProduct) {
                $errors[] = 'Product not found.';
            }
        }

        $productImage = $existingProduct['product_image'] ?? '';
        $arModel2d = $existingProduct['ar_model_2d'] ?? '';
        $arModel2dLeft = $existingProduct['ar_model_2d_left'] ?? '';
        $arModel2dRight = $existingProduct['ar_model_2d_right'] ?? '';
        $arModel3d = $existingProduct['ar_model_3d'] ?? '';
        $existingGalleryImages = $productId > 0 ? getProductAdditionalImages($productId) : [];
        if ($imageColumn) {
            if (isset($_FILES['product_image']) && ($_FILES['product_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES['product_image'], 'products');
                if (!empty($uploadResult['success'])) {
                    $productImage = 'assets/uploads/products/' . $uploadResult['filename'];
                } else {
                    $uploadErrors = $uploadResult['errors'] ?? ['Unable to upload product image.'];
                    foreach ($uploadErrors as $uploadError) {
                        $errors[] = $uploadError;
                    }
                }
            } elseif ($removeProductImage) {
                $productImage = '';
            }
        }

        if ($hasArModel2dColumn) {
            if (isset($_FILES['ar_model_2d']) && ($_FILES['ar_model_2d']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES['ar_model_2d'], 'ar-models', ['png', 'webp'], 10 * 1024 * 1024);
                if (!empty($uploadResult['success'])) {
                    $arModel2d = 'assets/uploads/ar-models/' . $uploadResult['filename'];
                } else {
                    foreach (($uploadResult['errors'] ?? ['Unable to upload front try-on image.']) as $uploadError) {
                        $errors[] = $uploadError;
                    }
                }
            } elseif ($removeArModel2d) {
                $arModel2d = '';
            }
        }

        if ($hasArModel2dLeftColumn) {
            if (isset($_FILES['ar_model_2d_left']) && ($_FILES['ar_model_2d_left']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES['ar_model_2d_left'], 'ar-models', ['png', 'webp'], 10 * 1024 * 1024);
                if (!empty($uploadResult['success'])) {
                    $arModel2dLeft = 'assets/uploads/ar-models/' . $uploadResult['filename'];
                } else {
                    foreach (($uploadResult['errors'] ?? ['Unable to upload left try-on image.']) as $uploadError) {
                        $errors[] = $uploadError;
                    }
                }
            } elseif ($removeArModel2dLeft) {
                $arModel2dLeft = '';
            }
        }

        if ($hasArModel2dRightColumn) {
            if (isset($_FILES['ar_model_2d_right']) && ($_FILES['ar_model_2d_right']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES['ar_model_2d_right'], 'ar-models', ['png', 'webp'], 10 * 1024 * 1024);
                if (!empty($uploadResult['success'])) {
                    $arModel2dRight = 'assets/uploads/ar-models/' . $uploadResult['filename'];
                } else {
                    foreach (($uploadResult['errors'] ?? ['Unable to upload right try-on image.']) as $uploadError) {
                        $errors[] = $uploadError;
                    }
                }
            } elseif ($removeArModel2dRight) {
                $arModel2dRight = '';
            }
        }

        if ($hasArModel3dColumn) {
            if (isset($_FILES['ar_model_3d']) && ($_FILES['ar_model_3d']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES['ar_model_3d'], 'ar-models', ['glb'], 30 * 1024 * 1024);
                if (!empty($uploadResult['success'])) {
                    $arModel3d = 'assets/uploads/ar-models/' . $uploadResult['filename'];
                } else {
                    foreach (($uploadResult['errors'] ?? ['Unable to upload GLB model.']) as $uploadError) {
                        $errors[] = $uploadError;
                    }
                }
            } elseif ($removeArModel3d) {
                $arModel3d = '';
            }
        }

        $galleryImagePaths = $existingGalleryImages;
        for ($slot = 1; $slot <= 4; $slot++) {
            $fileKey = 'gallery_image_' . $slot;
            $fileError = $_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE;

            if ($fileError === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES[$fileKey], 'products');
                if (!empty($uploadResult['success'])) {
                    $galleryImagePaths[$slot] = 'assets/uploads/products/' . $uploadResult['filename'];
                } else {
                    foreach (($uploadResult['errors'] ?? ['Unable to upload gallery image.']) as $uploadError) {
                        $errors[] = $uploadError;
                    }
                }
            } elseif ($removeGalleryImages[$slot]) {
                $galleryImagePaths[$slot] = '';
            }
        }

        if (empty($errors)) {
            if ($productId > 0) {
                $setClauses = [
                    'name = ?',
                    'brand = ?',
                    'category = ?',
                    'description = ?',
                    'price = ?',
                    'stock = ?',
                ];
                $params = [$name, $brand, $category, $description, $price, $stock];

                if ($hasMaterialColumn) {
                    $setClauses[] = "{$materialColumn} = ?";
                    $params[] = $material;
                }

                if ($hasColorColumn) {
                    $setClauses[] = "{$colorColumn} = ?";
                    $params[] = $color;
                }

                if ($hasFrameTargetColumn) {
                    $setClauses[] = "frame_target = ?";
                    $params[] = $category === 'frames' ? ($frameTarget !== '' ? $frameTarget : null) : null;
                }

                if ($hasSlugColumn) {
                    $setClauses[] = "slug = ?";
                    $params[] = $productSlug;
                }

                if ($imageColumn) {
                    $setClauses[] = "{$imageColumn} = ?";
                    $params[] = $productImage;
                }

                if ($hasIsFeaturedColumn) {
                    $setClauses[] = "is_featured = ?";
                    $params[] = $isFeatured;
                }

                if ($hasArModel2dColumn) {
                    $setClauses[] = 'ar_model_2d = ?';
                    $params[] = $arModel2d;
                }

                if ($hasArModel2dLeftColumn) {
                    $setClauses[] = 'ar_model_2d_left = ?';
                    $params[] = $arModel2dLeft;
                }

                if ($hasArModel2dRightColumn) {
                    $setClauses[] = 'ar_model_2d_right = ?';
                    $params[] = $arModel2dRight;
                }

                if ($hasArModel3dColumn) {
                    $setClauses[] = 'ar_model_3d = ?';
                    $params[] = $arModel3d;
                }

                if ($hasArPositionXColumn) {
                    $setClauses[] = 'ar_position_x = ?';
                    $params[] = $arPositionX;
                }

                if ($hasArPositionYColumn) {
                    $setClauses[] = 'ar_position_y = ?';
                    $params[] = $arPositionY;
                }

                if ($hasArScaleColumn) {
                    $setClauses[] = 'ar_scale = ?';
                    $params[] = $arScale;
                }

                if ($hasUpdatedAtColumn) {
                    $setClauses[] = 'updated_at = NOW()';
                }

                $params[] = $productId;

                $db->update(
                    "UPDATE products SET " . implode(', ', $setClauses) . " WHERE id = ?",
                    $params
                );

                foreach ($galleryImagePaths as $slot => $imagePath) {
                    $previousImagePath = $existingGalleryImages[$slot] ?? '';
                    $slot = (int) $slot;

                    if ($imagePath !== '') {
                        $db->update(
                            "INSERT INTO product_images (product_id, image_path, image_slot)
                             VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE image_path = VALUES(image_path)",
                            [$productId, $imagePath, $slot]
                        );
                    } else {
                        $db->delete(
                            "DELETE FROM product_images WHERE product_id = ? AND image_slot = ?",
                            [$productId, $slot]
                        );
                    }

                    if ($previousImagePath && $previousImagePath !== $imagePath) {
                        $oldGalleryPath = getAdminProductImageLocalPath($previousImagePath);
                        if ($oldGalleryPath && str_contains(str_replace('\\', '/', $oldGalleryPath), '/assets/uploads/products/')) {
                            unlink($oldGalleryPath);
                        }
                    }
                }

                if ($imageColumn && !empty($existingProduct['product_image']) && $existingProduct['product_image'] !== $productImage) {
                    $oldImagePath = getAdminProductImageLocalPath($existingProduct['product_image']);
                    if ($oldImagePath && str_contains(str_replace('\\', '/', $oldImagePath), '/assets/uploads/products/')) {
                        unlink($oldImagePath);
                    }
                }

                foreach ([
                    ['previous' => $existingProduct['ar_model_2d'] ?? '', 'current' => $arModel2d],
                    ['previous' => $existingProduct['ar_model_2d_left'] ?? '', 'current' => $arModel2dLeft],
                    ['previous' => $existingProduct['ar_model_2d_right'] ?? '', 'current' => $arModel2dRight],
                    ['previous' => $existingProduct['ar_model_3d'] ?? '', 'current' => $arModel3d],
                ] as $assetChange) {
                    if (empty($assetChange['previous']) || $assetChange['previous'] === $assetChange['current']) {
                        continue;
                    }

                    $oldAssetPath = getAdminTryOnAssetLocalPath($assetChange['previous']);
                    if ($oldAssetPath && str_contains(str_replace('\\', '/', $oldAssetPath), '/assets/uploads/ar-models/')) {
                        unlink($oldAssetPath);
                    }
                }

                createLog('PRODUCT_UPDATED', "Product #$productId updated");
                setFlashMessage('success', 'Product updated successfully');
            } else {
                $columns = ['name', 'brand', 'category', 'description', 'price', 'stock'];
                $placeholders = ['?', '?', '?', '?', '?', '?'];
                $params = [$name, $brand, $category, $description, $price, $stock];

                if ($hasMaterialColumn) {
                    $columns[] = $materialColumn;
                    $placeholders[] = '?';
                    $params[] = $material;
                }

                if ($hasColorColumn) {
                    $columns[] = $colorColumn;
                    $placeholders[] = '?';
                    $params[] = $color;
                }

                if ($hasFrameTargetColumn) {
                    $columns[] = 'frame_target';
                    $placeholders[] = '?';
                    $params[] = $category === 'frames' ? ($frameTarget !== '' ? $frameTarget : null) : null;
                }

                if ($hasSlugColumn) {
                    $columns[] = 'slug';
                    $placeholders[] = '?';
                    $params[] = $productSlug;
                }

                if ($imageColumn) {
                    $columns[] = $imageColumn;
                    $placeholders[] = '?';
                    $params[] = $productImage;
                }

                if ($hasIsFeaturedColumn) {
                    $columns[] = 'is_featured';
                    $placeholders[] = '?';
                    $params[] = $isFeatured;
                }

                if ($hasArModel2dColumn) {
                    $columns[] = 'ar_model_2d';
                    $placeholders[] = '?';
                    $params[] = $arModel2d;
                }

                if ($hasArModel2dLeftColumn) {
                    $columns[] = 'ar_model_2d_left';
                    $placeholders[] = '?';
                    $params[] = $arModel2dLeft;
                }

                if ($hasArModel2dRightColumn) {
                    $columns[] = 'ar_model_2d_right';
                    $placeholders[] = '?';
                    $params[] = $arModel2dRight;
                }

                if ($hasArModel3dColumn) {
                    $columns[] = 'ar_model_3d';
                    $placeholders[] = '?';
                    $params[] = $arModel3d;
                }

                if ($hasArPositionXColumn) {
                    $columns[] = 'ar_position_x';
                    $placeholders[] = '?';
                    $params[] = $arPositionX;
                }

                if ($hasArPositionYColumn) {
                    $columns[] = 'ar_position_y';
                    $placeholders[] = '?';
                    $params[] = $arPositionY;
                }

                if ($hasArScaleColumn) {
                    $columns[] = 'ar_scale';
                    $placeholders[] = '?';
                    $params[] = $arScale;
                }

                if ($hasIsActiveColumn) {
                    $columns[] = 'is_active';
                    $placeholders[] = '1';
                }

                if ($hasCreatedAtColumn) {
                    $columns[] = 'created_at';
                    $placeholders[] = 'NOW()';
                }

                if ($hasUpdatedAtColumn) {
                    $columns[] = 'updated_at';
                    $placeholders[] = 'NOW()';
                }

                $db->insert(
                    "INSERT INTO products (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")",
                    $params
                );

                $productId = (int) $db->getConnection()->lastInsertId();

                foreach ($galleryImagePaths as $slot => $imagePath) {
                    if ($imagePath === '') {
                        continue;
                    }

                    $db->insert(
                        "INSERT INTO product_images (product_id, image_path, image_slot) VALUES (?, ?, ?)",
                        [$productId, $imagePath, (int) $slot]
                    );
                }

                createLog('PRODUCT_CREATED', "New product added: $name");
                setFlashMessage('success', 'Product added successfully');
            }

            redirect(APP_URL . '/admin/products.php');
        }
    }
}

$searchQuery = trim((string) ($_GET['search'] ?? ''));
$categoryFilter = sanitize($_GET['category'] ?? '');
$featuredFilter = sanitize($_GET['featured'] ?? '');
$stockFilter = sanitize($_GET['stock'] ?? '');

$conditions = [];
$params = [];

if ($hasIsActiveColumn) {
    $conditions[] = 'is_active = 1';
}

if ($searchQuery !== '') {
    $conditions[] = '(name LIKE ? OR brand LIKE ? OR category LIKE ?)';
    $searchLike = '%' . $searchQuery . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

if ($categoryFilter !== '') {
    $conditions[] = 'category = ?';
    $params[] = $categoryFilter;
}

if ($hasIsFeaturedColumn && ($featuredFilter === 'featured' || $featuredFilter === 'regular')) {
    $conditions[] = 'is_featured = ?';
    $params[] = $featuredFilter === 'featured' ? 1 : 0;
}

if ($stockFilter === 'in_stock') {
    $conditions[] = 'stock > 0';
} elseif ($stockFilter === 'low_stock') {
    $conditions[] = 'stock BETWEEN 1 AND 10';
} elseif ($stockFilter === 'out_of_stock') {
    $conditions[] = 'stock <= 0';
}

$productsQuery = "SELECT * FROM products";
if (!empty($conditions)) {
    $productsQuery .= ' WHERE ' . implode(' AND ', $conditions);
}
$productsQuery .= " ORDER BY " . ($hasIsFeaturedColumn ? 'is_featured DESC, ' : '') . ($hasCreatedAtColumn ? 'created_at DESC' : 'id DESC');

$products = $db->fetchAll($productsQuery, $params);
$categories = getProductCategoryOptions();
$productCatalog = [];

foreach ($products as $product) {
    $productCatalog[$product['id']] = [
        'id' => (int) $product['id'],
        'name' => $product['name'] ?? '',
        'brand' => $product['brand'] ?? '',
        'category' => normalizeProductCategoryKey($product['category'] ?? ''),
        'frame_target' => $product['frame_target'] ?? '',
        'description' => decodeStoredText($product['description'] ?? ''),
        'price' => isset($product['price']) ? (string) $product['price'] : '',
        'stock' => isset($product['stock']) ? (string) $product['stock'] : '0',
        'material' => $materialColumn ? ($product[$materialColumn] ?? '') : '',
        'color' => $colorColumn ? ($product[$colorColumn] ?? '') : '',
        'is_featured' => !empty($product['is_featured']) ? 1 : 0,
        'image' => $imageColumn ? ($product[$imageColumn] ?? '') : '',
        'image_url' => getProductImagePath($product),
        'ar_model_2d' => $product['ar_model_2d'] ?? '',
        'ar_model_2d_url' => getTryOnAssetUrl($product['ar_model_2d'] ?? ''),
        'ar_model_2d_left' => $product['ar_model_2d_left'] ?? '',
        'ar_model_2d_left_url' => getTryOnAssetUrl($product['ar_model_2d_left'] ?? ''),
        'ar_model_2d_right' => $product['ar_model_2d_right'] ?? '',
        'ar_model_2d_right_url' => getTryOnAssetUrl($product['ar_model_2d_right'] ?? ''),
        'ar_model_3d' => $product['ar_model_3d'] ?? '',
        'ar_position_x' => isset($product['ar_position_x']) ? (string) $product['ar_position_x'] : '0',
        'ar_position_y' => isset($product['ar_position_y']) ? (string) $product['ar_position_y'] : '0',
        'ar_scale' => isset($product['ar_scale']) ? (string) $product['ar_scale'] : '1',
        'gallery_images' => array_map(function ($path) {
            return [
                'path' => $path,
                'url' => getProductImageUrl($path),
            ];
        }, getProductAdditionalImages((int) $product['id'])),
    ];
}

$productCatalogJson = json_encode(
    $productCatalog,
    JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_APOS
    | JSON_HEX_AMP
    | JSON_HEX_QUOT
    | JSON_INVALID_UTF8_SUBSTITUTE
);

if ($productCatalogJson === false) {
    $productCatalogJson = '{}';
}

$initialEditProductJson = 'null';
$initialEditProductData = null;
$isEditModalRequested = false;
$requestedEditProductId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
if ($requestedEditProductId > 0 && isset($productCatalog[$requestedEditProductId])) {
    $initialEditProductData = $productCatalog[$requestedEditProductId];
    $isEditModalRequested = true;
    $initialEditProductJson = json_encode(
        $initialEditProductData,
        JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_APOS
        | JSON_HEX_AMP
        | JSON_HEX_QUOT
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($initialEditProductJson === false) {
        $initialEditProductJson = 'null';
    }
}

require_once __DIR__ . '/inc/header.php';
?>

        <!-- Products Management -->
        <div class="container-fluid mt-4 mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Products Management</h2>
                    <p class="text-muted">Manage your product inventory and homepage featured products</p>
                </div>
                <button class="btn btn-primary" type="button" id="addProductButton" data-bs-toggle="modal" data-bs-target="#productModal">
                    <i class="fas fa-plus me-2"></i> Add Product
                </button>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label" for="productSearch">Search</label>
                            <input type="text" class="form-control" id="productSearch" name="search" value="<?php echo sanitize($searchQuery); ?>" placeholder="Search by name, brand, category">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="filterCategory">Category</label>
                            <select class="form-select" id="filterCategory" name="category">
                                <option value="">All categories</option>
                                <?php foreach ($categories as $categoryValue => $categoryLabel): ?>
                                <option value="<?php echo sanitize($categoryValue); ?>" <?php echo $categoryFilter === $categoryValue ? 'selected' : ''; ?>>
                                    <?php echo sanitize($categoryLabel); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="filterFeatured">Featured</label>
                            <select class="form-select" id="filterFeatured" name="featured">
                                <option value="">All</option>
                                <option value="featured" <?php echo $featuredFilter === 'featured' ? 'selected' : ''; ?>>Featured</option>
                                <option value="regular" <?php echo $featuredFilter === 'regular' ? 'selected' : ''; ?>>Regular</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="filterStock">Stock</label>
                            <select class="form-select" id="filterStock" name="stock">
                                <option value="">All</option>
                                <option value="in_stock" <?php echo $stockFilter === 'in_stock' ? 'selected' : ''; ?>>In stock</option>
                                <option value="low_stock" <?php echo $stockFilter === 'low_stock' ? 'selected' : ''; ?>>Low stock</option>
                                <option value="out_of_stock" <?php echo $stockFilter === 'out_of_stock' ? 'selected' : ''; ?>>Out of stock</option>
                            </select>
                        </div>
                        <div class="col-md-1 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                <div><?php echo sanitize($error); ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Products Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Brand</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Featured</th>
                                <th>Rating</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <?php
                            $rowProductPayload = $productCatalog[$product['id']] ?? [];
                            $rowProductPayloadJson = htmlspecialchars(
                                json_encode(
                                    $rowProductPayload,
                                    JSON_UNESCAPED_SLASHES
                                    | JSON_HEX_TAG
                                    | JSON_HEX_APOS
                                    | JSON_HEX_AMP
                                    | JSON_HEX_QUOT
                                    | JSON_INVALID_UTF8_SUBSTITUTE
                                ) ?: '{}',
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img
                                            src="<?php echo getProductImagePath($product); ?>"
                                            alt="<?php echo sanitize($product['name']); ?>"
                                            style="width: 56px; height: 56px; object-fit: cover; border-radius: 12px; border: 1px solid #e2e8f0;"
                                        >
                                        <div>
                                            <strong class="d-block"><?php echo sanitize($product['name']); ?></strong>
                                            <small class="text-muted">#<?php echo (int) $product['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo sanitize($product['brand'] ?? ''); ?></td>
                                <td>
                                    <div class="fw-semibold"><?php echo sanitize(formatProductCategoryLabel($product['category'] ?? '')); ?></div>
                                    <?php if (!empty($product['frame_target'])): ?>
                                    <small class="text-muted"><?php echo sanitize(formatProductAudienceLabel($product['frame_target'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatCurrency($product['price']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo ((int) ($product['stock'] ?? 0)) > 10 ? 'success' : 'warning'; ?>">
                                        <?php echo (int) ($product['stock'] ?? 0); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($hasIsFeaturedColumn && !empty($product['is_featured'])): ?>
                                    <span class="badge bg-primary">Featured</span>
                                    <?php else: ?>
                                    <span class="badge bg-light text-dark border">Standard</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <i class="fas fa-star text-warning"></i>
                                    <?php
                                    $rating = $db->fetch(
                                        "SELECT AVG(rating) AS avg FROM reviews WHERE product_id = ?",
                                        [$product['id']]
                                    );
                                    echo number_format((float) ($rating['avg'] ?? 0), 1);
                                    ?>
                                </td>
                                <td>
                                    <a
                                        class="btn btn-sm btn-outline-primary"
                                        href="?edit=<?php echo (int) $product['id']; ?>"
                                        data-edit-product="<?php echo (int) $product['id']; ?>"
                                        data-product-payload="<?php echo $rowProductPayloadJson; ?>"
                                        onclick="return window.openProductEditorFromButton(this);"
                                    >
                                        Edit
                                    </a>
                                    <a href="?delete=<?php echo (int) $product['id']; ?>" data-delete-product="<?php echo (int) $product['id']; ?>" class="btn btn-sm btn-outline-danger">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add/Edit Product Modal -->
        <div class="modal fade<?php echo $isEditModalRequested ? ' show' : ''; ?>" id="productModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="<?php echo $isEditModalRequested ? 'false' : 'true'; ?>"<?php echo $isEditModalRequested ? ' style="display: block;"' : ''; ?>>
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle"><?php echo $isEditModalRequested ? 'Edit Product' : 'Add Product'; ?></h5>
                        <?php if ($isEditModalRequested): ?>
                        <a href="products.php" class="btn-close" aria-label="Close"></a>
                        <?php else: ?>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        <?php endif; ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data" id="productForm" class="admin-product-form">
                        <div class="modal-body" id="productModalBody" style="max-height: calc(100dvh - 220px); overflow-y: auto; overflow-x: hidden;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="product_id" id="productId" value="<?php echo $isEditModalRequested ? (int) ($initialEditProductData['id'] ?? 0) : ''; ?>">

                            <div class="mb-3">
                                <label class="form-label" for="productName">Product Name</label>
                                <input type="text" class="form-control" name="name" id="productName" value="<?php echo sanitize($initialEditProductData['name'] ?? ''); ?>" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="productBrand">Brand</label>
                                    <input type="text" class="form-control" name="brand" id="productBrand" value="<?php echo sanitize($initialEditProductData['brand'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="productCategory">Category</label>
                                    <select class="form-select" name="category" id="productCategory" required>
                                        <option value="">Select Category</option>
                                        <?php foreach (getProductCategoryOptions() as $categoryValue => $categoryLabel): ?>
                                        <option value="<?php echo sanitize($categoryValue); ?>" <?php echo (($initialEditProductData['category'] ?? '') === $categoryValue) ? 'selected' : ''; ?>>
                                            <?php echo sanitize($categoryLabel); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <?php if ($hasFrameTargetColumn): ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="productFrameTarget">Frame Audience</label>
                                    <select class="form-select" name="frame_target" id="productFrameTarget">
                                        <option value="">General / Not specified</option>
                                        <?php foreach (getProductAudienceOptions() as $audienceValue => $audienceLabel): ?>
                                        <option value="<?php echo sanitize($audienceValue); ?>" <?php echo (($initialEditProductData['frame_target'] ?? '') === $audienceValue) ? 'selected' : ''; ?>>
                                            <?php echo sanitize($audienceLabel); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Use this for male, female, kids, or unisex frame browsing on the shop page.</small>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($hasMaterialColumn || $hasColorColumn): ?>
                            <div class="row">
                                <?php if ($hasMaterialColumn): ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="productMaterial">Material</label>
                                    <input type="text" class="form-control" name="material" id="productMaterial" value="<?php echo sanitize($initialEditProductData['material'] ?? ''); ?>">
                                </div>
                                <?php endif; ?>

                                <?php if ($hasColorColumn): ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="productColor">Color</label>
                                    <input type="text" class="form-control" name="color" id="productColor" value="<?php echo sanitize($initialEditProductData['color'] ?? ''); ?>">
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label" for="productDescription">Description</label>
                                <textarea class="form-control" name="description" id="productDescription" rows="4"><?php echo sanitize(decodeStoredText($initialEditProductData['description'] ?? '')); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="productPrice">Price (GHS)</label>
                                    <input type="number" class="form-control" name="price" id="productPrice" step="0.01" min="0.01" value="<?php echo sanitize($initialEditProductData['price'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="productStock">Stock Quantity</label>
                                    <input type="number" class="form-control" name="stock" id="productStock" min="0" value="<?php echo sanitize($initialEditProductData['stock'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <?php if ($hasIsFeaturedColumn): ?>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="is_featured" id="productIsFeatured" value="1" <?php echo !empty($initialEditProductData['is_featured']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="productIsFeatured">
                                    Feature this product on the homepage
                                </label>
                            </div>
                            <?php endif; ?>

                            <?php if ($hasArModel2dColumn || $hasArModel2dLeftColumn || $hasArModel2dRightColumn || $hasArModel3dColumn): ?>
                            <div class="border rounded-3 p-3 bg-light mb-3">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                    <div>
                                        <h6 class="mb-1">AR Try-On Assets</h6>
                                        <small class="text-muted">Upload transparent PNGs for the front and sides. The live try-on uses these assets to follow the customer face on camera.</small>
                                    </div>
                                    <span class="badge bg-dark-subtle text-dark border">Frames only</span>
                                </div>

                                <div class="row g-3">
                                    <?php if ($hasArModel2dColumn): ?>
                                    <div class="col-md-6">
                                        <label class="form-label" for="arModel2d">Front PNG</label>
                                        <input type="file" class="form-control" name="ar_model_2d" id="arModel2d" accept=".png,.webp">
                                        <div class="border rounded-3 bg-white p-2 mt-2" id="arModel2dCurrentBlock" style="<?php echo !empty($initialEditProductData['ar_model_2d']) ? 'display: block;' : 'display: none;'; ?>">
                                            <small class="text-muted d-block" id="arModel2dCurrentName"><?php echo sanitize($initialEditProductData['ar_model_2d'] ?? ''); ?></small>
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" name="remove_ar_model_2d" id="removeArModel2d">
                                                <label class="form-check-label" for="removeArModel2d">Remove front asset</label>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($hasArModel2dLeftColumn): ?>
                                    <div class="col-md-6">
                                        <label class="form-label" for="arModel2dLeft">Left Side PNG</label>
                                        <input type="file" class="form-control" name="ar_model_2d_left" id="arModel2dLeft" accept=".png,.webp">
                                        <div class="border rounded-3 bg-white p-2 mt-2" id="arModel2dLeftCurrentBlock" style="<?php echo !empty($initialEditProductData['ar_model_2d_left']) ? 'display: block;' : 'display: none;'; ?>">
                                            <small class="text-muted d-block" id="arModel2dLeftCurrentName"><?php echo sanitize($initialEditProductData['ar_model_2d_left'] ?? ''); ?></small>
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" name="remove_ar_model_2d_left" id="removeArModel2dLeft">
                                                <label class="form-check-label" for="removeArModel2dLeft">Remove left asset</label>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($hasArModel2dRightColumn): ?>
                                    <div class="col-md-6">
                                        <label class="form-label" for="arModel2dRight">Right Side PNG</label>
                                        <input type="file" class="form-control" name="ar_model_2d_right" id="arModel2dRight" accept=".png,.webp">
                                        <div class="border rounded-3 bg-white p-2 mt-2" id="arModel2dRightCurrentBlock" style="<?php echo !empty($initialEditProductData['ar_model_2d_right']) ? 'display: block;' : 'display: none;'; ?>">
                                            <small class="text-muted d-block" id="arModel2dRightCurrentName"><?php echo sanitize($initialEditProductData['ar_model_2d_right'] ?? ''); ?></small>
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" name="remove_ar_model_2d_right" id="removeArModel2dRight">
                                                <label class="form-check-label" for="removeArModel2dRight">Remove right asset</label>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($hasArModel3dColumn): ?>
                                    <div class="col-md-6">
                                        <label class="form-label" for="arModel3d">Optional GLB Model</label>
                                        <input type="file" class="form-control" name="ar_model_3d" id="arModel3d" accept=".glb,model/gltf-binary">
                                        <div class="border rounded-3 bg-white p-2 mt-2" id="arModel3dCurrentBlock" style="<?php echo !empty($initialEditProductData['ar_model_3d']) ? 'display: block;' : 'display: none;'; ?>">
                                            <small class="text-muted d-block" id="arModel3dCurrentName"><?php echo sanitize($initialEditProductData['ar_model_3d'] ?? ''); ?></small>
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" name="remove_ar_model_3d" id="removeArModel3d">
                                                <label class="form-check-label" for="removeArModel3d">Remove GLB asset</label>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="row g-3 mt-1">
                                    <?php if ($hasArPositionXColumn): ?>
                                    <div class="col-md-4">
                                        <label class="form-label" for="arPositionX">Horizontal Offset</label>
                                        <input type="number" class="form-control" name="ar_position_x" id="arPositionX" value="<?php echo sanitize($initialEditProductData['ar_position_x'] ?? '0'); ?>">
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($hasArPositionYColumn): ?>
                                    <div class="col-md-4">
                                        <label class="form-label" for="arPositionY">Vertical Offset</label>
                                        <input type="number" class="form-control" name="ar_position_y" id="arPositionY" value="<?php echo sanitize($initialEditProductData['ar_position_y'] ?? '0'); ?>">
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($hasArScaleColumn): ?>
                                    <div class="col-md-4">
                                        <label class="form-label" for="arScale">Base Scale</label>
                                        <input type="number" class="form-control" name="ar_scale" id="arScale" value="<?php echo sanitize($initialEditProductData['ar_scale'] ?? '1'); ?>" min="0.4" max="3" step="0.05">
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($imageColumn): ?>
                            <div class="mb-3">
                                <label class="form-label" for="productImage">Product Image</label>
                                <input type="file" class="form-control" name="product_image" id="productImage" accept=".jpg,.jpeg,.png,.gif,.webp">
                                <div class="form-text">Upload a main product image. Supported formats: JPG, PNG, GIF, WEBP.</div>
                            </div>

                            <div class="border rounded-3 p-3 bg-light mb-2" id="currentImageBlock" style="<?php echo (!empty($initialEditProductData['image']) && !empty($initialEditProductData['image_url'])) ? 'display: block;' : 'display: none;'; ?>">
                                <div class="d-flex align-items-center gap-3">
                                    <img id="currentImagePreview" src="<?php echo sanitize($initialEditProductData['image_url'] ?? ''); ?>" alt="Current product image" style="width: 84px; height: 84px; object-fit: cover; border-radius: 12px; border: 1px solid #e2e8f0;">
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">Current image</div>
                                        <small class="text-muted d-block" id="currentImageName"><?php echo sanitize($initialEditProductData['image'] ?? ''); ?></small>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" name="remove_product_image" id="removeProductImage">
                                            <label class="form-check-label" for="removeProductImage">
                                                Remove current image
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="border rounded-3 p-3 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <div class="fw-semibold">Extra Gallery Images</div>
                                        <small class="text-muted">Add up to 4 more images customers can scroll through in the shop.</small>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <?php for ($slot = 1; $slot <= 4; $slot++): ?>
                                    <?php $galleryItem = $initialEditProductData['gallery_images'][$slot] ?? $initialEditProductData['gallery_images'][(string) $slot] ?? null; ?>
                                    <div class="col-md-6">
                                        <label class="form-label" for="galleryImage<?php echo $slot; ?>">Gallery Image <?php echo $slot; ?></label>
                                        <input type="file" class="form-control mb-2" name="gallery_image_<?php echo $slot; ?>" id="galleryImage<?php echo $slot; ?>" accept=".jpg,.jpeg,.png,.gif,.webp">
                                        <div class="border rounded-3 bg-white p-2" id="galleryPreviewBlock<?php echo $slot; ?>" style="<?php echo !empty($galleryItem['url']) ? 'display: block;' : 'display: none;'; ?>">
                                            <div class="d-flex align-items-center gap-3">
                                                <img id="galleryPreviewImage<?php echo $slot; ?>" src="<?php echo sanitize($galleryItem['url'] ?? ''); ?>" alt="Gallery image <?php echo $slot; ?>" style="width: 72px; height: 72px; object-fit: cover; border-radius: 10px; border: 1px solid #e2e8f0;">
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block" id="galleryPreviewName<?php echo $slot; ?>"><?php echo sanitize($galleryItem['path'] ?? ''); ?></small>
                                                    <div class="form-check mt-2">
                                                        <input class="form-check-input" type="checkbox" name="remove_gallery_image_<?php echo $slot; ?>" id="removeGalleryImage<?php echo $slot; ?>">
                                                        <label class="form-check-label" for="removeGalleryImage<?php echo $slot; ?>">
                                                            Remove this gallery image
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <button
                            type="button"
                            class="product-modal-scroll-button"
                            id="productModalScrollButton"
                            aria-label="Scroll down in product form"
                            title="Scroll down"
                            hidden
                        >
                            <span aria-hidden="true">&darr;</span>
                        </button>
                        <div class="modal-footer">
                            <?php if ($isEditModalRequested): ?>
                            <a href="products.php" class="btn btn-secondary">Cancel</a>
                            <?php else: ?>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary" id="productSubmitButton"><?php echo $isEditModalRequested ? 'Update Product' : 'Save Product'; ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php if ($isEditModalRequested): ?>
        <div class="modal-backdrop fade show"></div>
        <script>document.body.classList.add('modal-open');</script>
        <?php endif; ?>

        <script>
        const productCatalog = <?php echo $productCatalogJson; ?>;
        const initialEditProduct = <?php echo $initialEditProductJson; ?>;
        let productModalElement = null;
        let productModal = null;
        let productModalBody = null;
        let productModalScrollButton = null;
        let manualModalBackdrop = null;

        function initializeProductModal() {
            if (!productModalElement) {
                productModalElement = document.getElementById('productModal');
            }

            if (!productModalElement || !window.bootstrap || !bootstrap.Modal) {
                return null;
            }

            if (!productModal) {
                productModal = bootstrap.Modal.getOrCreateInstance(productModalElement);
            }

            return productModal;
        }

        function showProductModal() {
            const modalInstance = initializeProductModal();

            if (modalInstance && typeof modalInstance.show === 'function') {
                modalInstance.show();
                setTimeout(syncProductModalLayout, 50);
                return true;
            }

            productModalElement = productModalElement || document.getElementById('productModal');
            if (!productModalElement) {
                return false;
            }

            productModalElement.style.display = 'block';
            productModalElement.classList.add('show');
            productModalElement.removeAttribute('aria-hidden');
            productModalElement.setAttribute('aria-modal', 'true');
            document.body.classList.add('modal-open');

            if (!manualModalBackdrop) {
                manualModalBackdrop = document.createElement('div');
                manualModalBackdrop.className = 'modal-backdrop fade show';
                document.body.appendChild(manualModalBackdrop);
            }

            setTimeout(syncProductModalLayout, 50);
            return true;
        }

        function hideManualProductModal() {
            if (!productModalElement || initializeProductModal()) {
                return;
            }

            productModalElement.style.display = 'none';
            productModalElement.classList.remove('show');
            productModalElement.setAttribute('aria-hidden', 'true');
            productModalElement.removeAttribute('aria-modal');
            document.body.classList.remove('modal-open');

            if (manualModalBackdrop) {
                manualModalBackdrop.remove();
                manualModalBackdrop = null;
            }
        }

        function syncProductModalLayout() {
            productModalElement = productModalElement || document.getElementById('productModal');
            if (!productModalElement) {
                return;
            }

            const dialog = productModalElement.querySelector('.modal-dialog');
            const content = productModalElement.querySelector('.modal-content');
            const form = productModalElement.querySelector('.admin-product-form');
            const header = productModalElement.querySelector('.modal-header');
            const body = productModalElement.querySelector('.modal-body');
            const footer = productModalElement.querySelector('.modal-footer');

            if (!dialog || !content || !form || !header || !body || !footer) {
                return;
            }

            dialog.style.height = '';
            dialog.style.maxHeight = '';
            content.style.height = '';
            content.style.maxHeight = '';
            form.style.height = '';
            form.style.maxHeight = '';
            body.style.maxHeight = 'calc(100dvh - 220px)';
            body.style.overflowY = 'auto';
            body.style.overflowX = 'hidden';
            updateProductModalScrollCue();
        }

        function updateProductModalScrollCue() {
            productModalElement = productModalElement || document.getElementById('productModal');
            productModalBody = productModalBody || productModalElement?.querySelector('.modal-body');
            productModalScrollButton = productModalScrollButton || document.getElementById('productModalScrollButton');

            if (!productModalBody || !productModalScrollButton) {
                return;
            }

            const remainingScroll = productModalBody.scrollHeight - productModalBody.clientHeight - productModalBody.scrollTop;
            const hasOverflow = productModalBody.scrollHeight > (productModalBody.clientHeight + 24);
            const shouldShow = hasOverflow && remainingScroll > 48;

            productModalScrollButton.hidden = !shouldShow;
            productModalScrollButton.classList.toggle('is-visible', shouldShow);
        }

        function scrollProductModalDown() {
            if (!productModalBody) {
                return;
            }

            productModalBody.scrollBy({
                top: Math.max(productModalBody.clientHeight * 0.8, 280),
                behavior: 'smooth'
            });
        }

        function syncFrameTargetField() {
            const categorySelect = document.getElementById('productCategory');
            const frameTargetSelect = document.getElementById('productFrameTarget');

            if (!categorySelect || !frameTargetSelect) {
                return;
            }

            const enabled = categorySelect.value === 'frames';
            frameTargetSelect.disabled = !enabled;

            if (!enabled) {
                frameTargetSelect.value = '';
            }
        }

        function resetProductForm() {
            const form = document.getElementById('productForm');
            if (!form) {
                return;
            }

            form.reset();
            document.getElementById('modalTitle').textContent = 'Add Product';
            document.getElementById('productId').value = '';
            document.getElementById('productSubmitButton').textContent = 'Save Product';

            const currentImageBlock = document.getElementById('currentImageBlock');
            const currentImagePreview = document.getElementById('currentImagePreview');
            const currentImageName = document.getElementById('currentImageName');
            const removeProductImage = document.getElementById('removeProductImage');

            if (currentImageBlock) {
                currentImageBlock.style.display = 'none';
            }
            if (currentImagePreview) {
                currentImagePreview.src = '';
            }
            if (currentImageName) {
                currentImageName.textContent = '';
            }
            if (removeProductImage) {
                removeProductImage.checked = false;
            }

            [
                ['block' => 'arModel2dCurrentBlock', 'name' => 'arModel2dCurrentName', 'checkbox' => 'removeArModel2d'],
                ['block' => 'arModel2dLeftCurrentBlock', 'name' => 'arModel2dLeftCurrentName', 'checkbox' => 'removeArModel2dLeft'],
                ['block' => 'arModel2dRightCurrentBlock', 'name' => 'arModel2dRightCurrentName', 'checkbox' => 'removeArModel2dRight'],
                ['block' => 'arModel3dCurrentBlock', 'name' => 'arModel3dCurrentName', 'checkbox' => 'removeArModel3d'],
            ].forEach((entry) => {
                const block = document.getElementById(entry.block);
                const name = document.getElementById(entry.name);
                const checkbox = document.getElementById(entry.checkbox);

                if (block) {
                    block.style.display = 'none';
                }
                if (name) {
                    name.textContent = '';
                }
                if (checkbox) {
                    checkbox.checked = false;
                }
            });

            const arPositionX = document.getElementById('arPositionX');
            const arPositionY = document.getElementById('arPositionY');
            const arScale = document.getElementById('arScale');
            if (arPositionX) {
                arPositionX.value = '0';
            }
            if (arPositionY) {
                arPositionY.value = '0';
            }
            if (arScale) {
                arScale.value = '1';
            }

            for (let slot = 1; slot <= 4; slot++) {
                const previewBlock = document.getElementById(`galleryPreviewBlock${slot}`);
                const previewImage = document.getElementById(`galleryPreviewImage${slot}`);
                const previewName = document.getElementById(`galleryPreviewName${slot}`);
                const removeCheckbox = document.getElementById(`removeGalleryImage${slot}`);

                if (previewBlock) {
                    previewBlock.style.display = 'none';
                }
                if (previewImage) {
                    previewImage.src = '';
                }
                if (previewName) {
                    previewName.textContent = '';
                }
                if (removeCheckbox) {
                    removeCheckbox.checked = false;
                }
            }

            if (productModalBody) {
                productModalBody.scrollTop = 0;
            }

            syncFrameTargetField();
            window.requestAnimationFrame(updateProductModalScrollCue);
        }

        function populateProductForm(product) {
            if (!product) {
                return false;
            }

            resetProductForm();

            document.getElementById('modalTitle').textContent = 'Edit Product';
            document.getElementById('productId').value = product.id || '';
            document.getElementById('productSubmitButton').textContent = 'Update Product';
            document.getElementById('productName').value = product.name || '';
            document.getElementById('productBrand').value = product.brand || '';
            document.getElementById('productCategory').value = product.category || '';
            const productFrameTarget = document.getElementById('productFrameTarget');
            if (productFrameTarget) {
                productFrameTarget.value = product.frame_target || '';
            }
            document.getElementById('productDescription').value = product.description || '';
            document.getElementById('productPrice').value = product.price || '';
            document.getElementById('productStock').value = product.stock || 0;

            const productIsFeatured = document.getElementById('productIsFeatured');
            if (productIsFeatured) {
                productIsFeatured.checked = Number(product.is_featured || 0) === 1;
            }

            const productMaterial = document.getElementById('productMaterial');
            if (productMaterial) {
                productMaterial.value = product.material || '';
            }

            const productColor = document.getElementById('productColor');
            if (productColor) {
                productColor.value = product.color || '';
            }

            const arPositionX = document.getElementById('arPositionX');
            const arPositionY = document.getElementById('arPositionY');
            const arScale = document.getElementById('arScale');
            if (arPositionX) {
                arPositionX.value = product.ar_position_x || '0';
            }
            if (arPositionY) {
                arPositionY.value = product.ar_position_y || '0';
            }
            if (arScale) {
                arScale.value = product.ar_scale || '1';
            }

            const currentImageBlock = document.getElementById('currentImageBlock');
            const currentImagePreview = document.getElementById('currentImagePreview');
            const currentImageName = document.getElementById('currentImageName');
            const removeProductImage = document.getElementById('removeProductImage');

            if (currentImageBlock && product.image_url && product.image) {
                currentImageBlock.style.display = 'block';
                currentImagePreview.src = product.image_url;
                currentImageName.textContent = product.image;
                if (removeProductImage) {
                    removeProductImage.checked = false;
                }
            }

            [
                ['urlKey' => 'ar_model_2d_url', 'nameKey' => 'ar_model_2d', 'block' => 'arModel2dCurrentBlock', 'name' => 'arModel2dCurrentName'],
                ['urlKey' => 'ar_model_2d_left_url', 'nameKey' => 'ar_model_2d_left', 'block' => 'arModel2dLeftCurrentBlock', 'name' => 'arModel2dLeftCurrentName'],
                ['urlKey' => 'ar_model_2d_right_url', 'nameKey' => 'ar_model_2d_right', 'block' => 'arModel2dRightCurrentBlock', 'name' => 'arModel2dRightCurrentName'],
                ['urlKey' => 'ar_model_3d', 'nameKey' => 'ar_model_3d', 'block' => 'arModel3dCurrentBlock', 'name' => 'arModel3dCurrentName'],
            ].forEach((entry) => {
                const block = document.getElementById(entry.block);
                const name = document.getElementById(entry.name);
                const assetName = product[entry.nameKey] || '';
                const assetUrl = product[entry.urlKey] || '';

                if (block && (assetName || assetUrl)) {
                    block.style.display = 'block';
                }
                if (name && assetName) {
                    name.textContent = assetName;
                }
            });

            const galleryImages = product.gallery_images || {};
            for (let slot = 1; slot <= 4; slot++) {
                const galleryItem = galleryImages[String(slot)] || galleryImages[slot];
                const previewBlock = document.getElementById(`galleryPreviewBlock${slot}`);
                const previewImage = document.getElementById(`galleryPreviewImage${slot}`);
                const previewName = document.getElementById(`galleryPreviewName${slot}`);
                const removeCheckbox = document.getElementById(`removeGalleryImage${slot}`);

                if (previewBlock && galleryItem && galleryItem.url) {
                    previewBlock.style.display = 'block';
                    if (previewImage) {
                        previewImage.src = galleryItem.url;
                    }
                    if (previewName) {
                        previewName.textContent = galleryItem.path || `Gallery image ${slot}`;
                    }
                    if (removeCheckbox) {
                        removeCheckbox.checked = false;
                    }
                }
            }

            syncFrameTargetField();
            showProductModal();

            return false;
        }

        function editProduct(productId) {
            const product = productCatalog[String(productId)] || productCatalog[productId];
            return populateProductForm(product);
        }

        document.addEventListener('DOMContentLoaded', function () {
            productModalElement = document.getElementById('productModal');
            productModalBody = productModalElement?.querySelector('.modal-body') || null;
            productModalScrollButton = document.getElementById('productModalScrollButton');
            window.openProductEditor = function (productId) {
                return editProduct(productId);
            };
            window.openProductEditorFromButton = function (button) {
                try {
                    const rawPayload = button?.getAttribute('data-product-payload') || '{}';
                    const product = JSON.parse(rawPayload);
                    return populateProductForm(product);
                } catch (error) {
                    console.error('Unable to open product editor.', error);
                    return false;
                }
            };
            initializeProductModal();
            syncProductModalLayout();

            document.getElementById('addProductButton')?.addEventListener('click', resetProductForm);
            document.getElementById('productCategory')?.addEventListener('change', syncFrameTargetField);
            productModalElement?.addEventListener('hidden.bs.modal', resetProductForm);
            productModalElement?.addEventListener('shown.bs.modal', function () {
                syncProductModalLayout();
                window.requestAnimationFrame(updateProductModalScrollCue);
            });
            productModalElement?.querySelectorAll('[data-bs-dismiss="modal"]').forEach((button) => {
                button.addEventListener('click', function () {
                    hideManualProductModal();
                });
            });
            window.addEventListener('resize', syncProductModalLayout);
            productModalBody?.addEventListener('scroll', updateProductModalScrollCue);
            productModalScrollButton?.addEventListener('click', scrollProductModalDown);

            if (initialEditProduct && typeof initialEditProduct === 'object') {
                window.requestAnimationFrame(function () {
                    populateProductForm(initialEditProduct);
                    if (window.history && typeof window.history.replaceState === 'function') {
                        window.history.replaceState({}, document.title, 'products.php');
                    }
                });
                window.addEventListener('load', function () {
                    populateProductForm(initialEditProduct);
                }, { once: true });
            }

            syncFrameTargetField();

            document.addEventListener('click', function (event) {
                const editButton = event.target.closest('[data-edit-product]');
                if (editButton) {
                    event.preventDefault();
                    editProduct(Number(editButton.dataset.editProduct || 0));
                    return;
                }

                const deleteLink = event.target.closest('[data-delete-product]');
                if (deleteLink && !confirmDelete('Delete this product?')) {
                    event.preventDefault();
                }
            });
        });
        </script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
