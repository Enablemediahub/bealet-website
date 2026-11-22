-- Additive schema for the Bealet hiring portal.
-- Safe to run more than once; this does not alter existing tables.

CREATE TABLE IF NOT EXISTS hiring_openings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    requirements TEXT NULL,
    branch VARCHAR(180) NULL,
    schedule VARCHAR(180) NULL,
    candidate_preference VARCHAR(255) NULL,
    role_advantage VARCHAR(255) NULL,
    application_limit INT UNSIGNED NULL,
    is_open TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hiring_openings_slug (slug),
    KEY idx_hiring_openings_status (is_open, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hiring_applications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    opening_id INT UNSIGNED NOT NULL,
    opening_title_snapshot VARCHAR(180) NOT NULL,
    application_code VARCHAR(32) NOT NULL,
    applicant_name VARCHAR(180) NOT NULL,
    applicant_email VARCHAR(254) NOT NULL,
    applicant_phone VARCHAR(30) NOT NULL,
    cover_letter TEXT NULL,
    cv_storage_name VARCHAR(80) NOT NULL,
    cv_original_name VARCHAR(255) NOT NULL,
    status ENUM('submitted', 'reviewing', 'accepted', 'declined') NOT NULL DEFAULT 'submitted',
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hiring_application_code (application_code),
    KEY idx_hiring_applications_opening (opening_id, submitted_at),
    KEY idx_hiring_applications_status (status, submitted_at),
    CONSTRAINT fk_hiring_applications_opening
        FOREIGN KEY (opening_id) REFERENCES hiring_openings(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hiring_page_images (
    image_key VARCHAR(120) NOT NULL PRIMARY KEY,
    image_filename VARCHAR(80) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO hiring_openings (slug, title, sort_order, is_open)
VALUES
    ('optometrist-resident-locum', 'Optometrist (Resident or Locum)', 1, 0),
    ('receptionist', 'Receptionist', 2, 0),
    ('showroom-attendant', 'Showroom Attendant', 3, 0),
    ('technician-optician', 'Technician / Optician', 4, 0),
    ('account-manager', 'Account Manager', 5, 0),
    ('delivery-officer', 'Delivery Officer', 6, 0)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    sort_order = VALUES(sort_order);
