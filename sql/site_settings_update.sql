-- Site settings and branch management schema update

CREATE TABLE IF NOT EXISTS site_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_branches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    branch_name VARCHAR(255) NOT NULL,
    phone_primary VARCHAR(50) NULL,
    phone_secondary VARCHAR(50) NULL,
    address TEXT NULL,
    google_maps_url TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    image_slot TINYINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_product_slot (product_id, image_slot),
    KEY idx_product_images_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS founder_gallery (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_title VARCHAR(255) NOT NULL,
    item_type VARCHAR(40) NOT NULL DEFAULT 'portrait',
    item_description TEXT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_founder_gallery_sort (is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_is_featured := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'is_featured'
);
SET @add_is_featured_sql := IF(
    @has_is_featured = 0,
    'ALTER TABLE products ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @add_is_featured_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_order_phone := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'order_phone'
);
SET @add_order_phone_sql := IF(
    @has_order_phone = 0,
    'ALTER TABLE orders ADD COLUMN order_phone VARCHAR(30) NULL AFTER guest_email',
    'SELECT 1'
);
PREPARE stmt2 FROM @add_order_phone_sql;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SET @has_admin_role := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'admin_role'
);
SET @add_admin_role_sql := IF(
    @has_admin_role = 0,
    'ALTER TABLE users ADD COLUMN admin_role VARCHAR(30) NULL AFTER is_admin',
    'SELECT 1'
);
PREPARE stmt3 FROM @add_admin_role_sql;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

UPDATE users
SET admin_role = CASE
    WHEN is_admin = 1 THEN 'super_admin'
    ELSE 'customer'
END
WHERE admin_role IS NULL OR admin_role = '';

INSERT INTO site_settings (setting_key, setting_value) VALUES
('company_name', 'BEALET OPTICAL CENTER'),
('tagline', ''),
('primary_phone', ''),
('secondary_phone', ''),
('email', 'noreply@bealet.com'),
('facebook_url', ''),
('instagram_url', ''),
('twitter_url', ''),
('linkedin_url', ''),
('tiktok_url', ''),
('logo_path', ''),
('login_wallpaper', ''),
('google_client_id', ''),
('contact_hero_image', ''),
('blog_hero_image', ''),
('founder_name', ''),
('founder_role', ''),
('founder_short_bio', ''),
('founder_story', ''),
('founder_quote', ''),
('founder_thumbnail', ''),
('founder_hero_image', ''),
('staff_hero_image', '')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
