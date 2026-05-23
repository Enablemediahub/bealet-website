-- Bealet Website Database Schema
-- MySQL 5.7+

-- Drop existing tables if they exist
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS wishlist;
DROP TABLE IF EXISTS contacts;
DROP TABLE IF EXISTS newsletter;
DROP TABLE IF EXISTS blog_posts;
DROP TABLE IF EXISTS staff_members;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS order_prescriptions;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    is_admin BOOLEAN DEFAULT 0,
    admin_role VARCHAR(30) NULL,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    reset_token VARCHAR(255) NULL,
    reset_expires TIMESTAMP NULL,
    login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_is_admin (is_admin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products table
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    category VARCHAR(50) NOT NULL,
    frame_target VARCHAR(50) DEFAULT NULL,
    brand VARCHAR(100),
    material VARCHAR(100),
    color VARCHAR(50),
    stock INT DEFAULT 0,
    image VARCHAR(255),
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_is_active (is_active),
    FULLTEXT INDEX ft_search (name, description, brand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    guest_email VARCHAR(255) NULL,
    tracking_code VARCHAR(50) UNIQUE NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    shipping_address TEXT NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tracking_code (tracking_code),
    INDEX idx_status (status),
    INDEX idx_user_id (user_id),
    INDEX idx_guest_email (guest_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order items table
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order prescriptions table
CREATE TABLE order_prescriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    prescription_source ENUM('manual', 'upload', 'camera', 'manual_upload') NOT NULL DEFAULT 'manual',
    frame_notes VARCHAR(255) NULL,
    manual_prescription JSON NULL,
    file_path VARCHAR(255) NULL,
    original_filename VARCHAR(255) NULL,
    customer_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    KEY idx_order_prescriptions_order_id (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Appointments table
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time VARCHAR(10) NOT NULL,
    notes TEXT,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_appointment_date (appointment_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wishlist table
CREATE TABLE wishlist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reviews table
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    review_image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product_review (product_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Newsletter table
CREATE TABLE newsletter (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT 1,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contacts table
CREATE TABLE contacts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT 0,
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blog posts table
CREATE TABLE blog_posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content TEXT NOT NULL,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_published BOOLEAN DEFAULT 1,
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Founder gallery table
CREATE TABLE founder_gallery (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_title VARCHAR(255) NOT NULL,
    item_type VARCHAR(40) NOT NULL DEFAULT 'portrait',
    item_description TEXT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_founder_gallery_sort (is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff members table
CREATE TABLE staff_members (
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

-- Cart table
CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(255),
    user_id INT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (session_id, user_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SAMPLE DATA

-- Admin user (password: Admin@123)
INSERT INTO users (name, email, phone, password_hash, is_admin) VALUES 
('Admin User', 'admin@bealet.com', '+233 24 000 0001', '$2y$10$QfNNpL1dNYZgKz0NfL5Rc.Lr8GnRzZzq8mX5yF5pK7qV3QvQZ3Sni', 1);

-- Sample customer (password: Customer@123)
INSERT INTO users (name, email, phone, password_hash, is_admin) VALUES 
('John Doe', 'john@example.com', '+233 24 000 0002', '$2y$10$W8QvE7KzX5qW8QvE7KzX5.Lr8GnRzZzq8mX5yF5pK7qV3QvQZ3Sni', 0);

-- Sample products
INSERT INTO products (name, description, price, category, frame_target, brand, material, color, stock, image, is_active) VALUES
('Classic Frame - Black', 'Elegant classic frames perfect for everyday wear', 25000, 'frames', 'male', 'Bealet', 'Acetate', 'Black', 50, 'classic-black.jpg', 1),
('Round Frame - Gold', 'Trendy round frames with vintage appeal', 28000, 'frames', 'female', 'Bealet', 'Metal', 'Gold', 35, 'round-gold.jpg', 1),
('Square Frame - Brown', 'Modern square frames for a sophisticated look', 26000, 'frames', 'unisex', 'Bealet', 'Acetate', 'Brown', 42, 'square-brown.jpg', 1),
('Cat-Eye Frame - Pink', 'Stylish cat-eye frames for a bold statement', 30000, 'frames', 'female', 'Bealet', 'Acetate', 'Pink', 28, 'cateye-pink.jpg', 1),
('Aviator Frame - Silver', 'Classic aviator frames in premium quality', 32000, 'frames', 'male', 'Bealet', 'Metal', 'Silver', 45, 'aviator-silver.jpg', 1),
('Clear Lenses', 'High-quality clear optical lenses', 15000, 'lenses', NULL, 'OpticalPro', 'Polycarbonate', 'Clear', 100, 'clear-lenses.jpg', 1),
('Blue Light Lenses', 'Anti-blue light lenses for digital screen users', 18000, 'lenses', NULL, 'OpticalPro', 'Polycarbonate', 'Clear', 75, 'bluelight-lenses.jpg', 1),
('Daily Contact Lenses', 'Comfortable daily disposable contact lenses', 12000, 'contact_lenses', NULL, 'ContactPro', 'Silicone', 'Clear', 200, 'daily-contacts.jpg', 1),
('Monthly Contact Lenses', 'Long-lasting monthly contact lenses', 10000, 'contact_lenses', NULL, 'ContactPro', 'Silicone', 'Clear', 150, 'monthly-contacts.jpg', 1),
('Lens Cleaning Kit', 'Complete lens cleaning and maintenance kit', 5000, 'accessories', NULL, 'ClearView', 'Various', 'Multi', 80, 'cleaning-kit.jpg', 1);

-- Sample appointments
INSERT INTO appointments (name, email, phone, appointment_date, appointment_time, notes, status, user_id) VALUES
('Jane Smith', 'jane@example.com', '+233 24 000 0003', '2026-04-25', '09:00', 'Frame fitting appointment', 'pending', NULL),
('Michael Johnson', 'michael@example.com', '+233 24 000 0004', '2026-04-26', '14:00', 'Eye test and consultation', 'confirmed', NULL);

-- Sample blog posts
INSERT INTO blog_posts (title, slug, content, image, created_at, is_published) VALUES
('The Evolution of Eyewear Fashion', 'evolution-eyewear-fashion', 'Eyewear has evolved from a simple medical necessity to a fashion statement. Discover how glasses have become an integral part of personal style.', 'blog-1.jpg', NOW(), 1),
('How to Choose the Right Frame for Your Face Shape', 'right-frame-face-shape', 'Your face shape plays an important role in determining which frames will look best on you. Learn about the different frame styles and what works best for each face shape.', 'blog-2.jpg', NOW(), 1),
('Understanding Lens Types and Their Benefits', 'lens-types-benefits', 'There are many different types of lenses available today, each with their own benefits. From progressive lenses to blue light blockers, understand what each type offers.', 'blog-3.jpg', NOW(), 1);

-- Sample staff members
INSERT INTO staff_members (name, designation, email, contact, bio, sort_order, is_active) VALUES
('Dr. Ama Mensah', 'Optometrist', 'ama.mensah@bealet.com', '+233 20 111 2222', 'Leads eye examinations and personalized vision care recommendations.', 1, 1),
('Kwame Asare', 'Dispensing Specialist', 'kwame.asare@bealet.com', '+233 24 333 4444', 'Supports frame fitting, lens selection, and comfortable final adjustments.', 2, 1),
('Efua Boateng', 'Customer Care Lead', 'support@bealet.com', '+233 55 555 6666', 'Helps customers with appointments, follow-up questions, and order support.', 3, 1);

-- Sample newsletter subscribers
INSERT INTO newsletter (email, is_active) VALUES
('subscriber1@example.com', 1),
('subscriber2@example.com', 1);
