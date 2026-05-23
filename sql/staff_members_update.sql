CREATE TABLE IF NOT EXISTS staff_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    designation VARCHAR(255) NOT NULL,
    branch_id INT NULL,
    email VARCHAR(255) NULL,
    contact VARCHAR(50) NULL,
    thumbnail VARCHAR(255) NULL,
    bio TEXT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_staff_active_sort (is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_staff_branch_id := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'staff_members'
      AND COLUMN_NAME = 'branch_id'
);
SET @add_staff_branch_id_sql := IF(
    @has_staff_branch_id = 0,
    'ALTER TABLE staff_members ADD COLUMN branch_id INT NULL AFTER designation',
    'SELECT 1'
);
PREPARE stmt_staff_branch FROM @add_staff_branch_id_sql;
EXECUTE stmt_staff_branch;
DEALLOCATE PREPARE stmt_staff_branch;
