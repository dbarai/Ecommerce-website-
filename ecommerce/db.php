<?php
// db.php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create a database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Initialize database tables (for first time setup)
function initDatabase($pdo) {
    $sqls = [
        // Users table
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'vendor', 'customer') DEFAULT 'customer',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // Vendors table
        "CREATE TABLE IF NOT EXISTS vendors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            shop_name VARCHAR(100),
            shop_description TEXT,
            status ENUM('pending', 'approved', 'suspended') DEFAULT 'pending',
            FOREIGN KEY (user_id) REFERENCES users(id)
        )",

        // Categories table
        "CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            slug VARCHAR(50) UNIQUE NOT NULL,
            description TEXT,
            parent_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_id) REFERENCES categories(id)
        )",

        // Products table
        "CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vendor_id INT,
            category_id INT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            compare_price DECIMAL(10,2) DEFAULT NULL,
            cost_per_item DECIMAL(10,2) DEFAULT NULL,
            sku VARCHAR(100) UNIQUE,
            barcode VARCHAR(100),
            quantity INT DEFAULT 0,
            track_quantity BOOLEAN DEFAULT TRUE,
            continue_selling_when_out_of_stock BOOLEAN DEFAULT FALSE,
            physical_product BOOLEAN DEFAULT TRUE,
            weight DECIMAL(10,2) DEFAULT NULL,
            length DECIMAL(10,2) DEFAULT NULL,
            width DECIMAL(10,2) DEFAULT NULL,
            height DECIMAL(10,2) DEFAULT NULL,
            has_variations BOOLEAN DEFAULT FALSE,
            status ENUM('draft', 'active', 'archived') DEFAULT 'draft',
            published_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vendor_id) REFERENCES vendors(id),
            FOREIGN KEY (category_id) REFERENCES categories(id)
        )",

        // Product variations
        "CREATE TABLE IF NOT EXISTS product_variations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT,
            name VARCHAR(255) NOT NULL,
            sku VARCHAR(100) UNIQUE,
            price DECIMAL(10,2) NOT NULL,
            compare_price DECIMAL(10,2) DEFAULT NULL,
            quantity INT DEFAULT 0,
            image VARCHAR(255) DEFAULT NULL,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )",

        // Product images
        "CREATE TABLE IF NOT EXISTS product_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT,
            variation_id INT DEFAULT NULL,
            image_url VARCHAR(255) NOT NULL,
            is_primary BOOLEAN DEFAULT FALSE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (variation_id) REFERENCES product_variations(id) ON DELETE CASCADE
        )",

        // Coupons table
        "CREATE TABLE IF NOT EXISTS coupons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            discount_type ENUM('percentage', 'fixed') NOT NULL,
            discount_value DECIMAL(10,2) NOT NULL,
            min_order_amount DECIMAL(10,2) DEFAULT 0,
            max_discount_amount DECIMAL(10,2) DEFAULT NULL,
            usage_limit INT DEFAULT NULL,
            used_count INT DEFAULT 0,
            start_date DATE,
            end_date DATE,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // Orders table
        "CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(50) UNIQUE NOT NULL,
            user_id INT,
            coupon_id INT DEFAULT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            discount DECIMAL(10,2) DEFAULT 0,
            tax DECIMAL(10,2) DEFAULT 0,
            shipping DECIMAL(10,2) DEFAULT 0,
            total DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
            shipping_address TEXT,
            billing_address TEXT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (coupon_id) REFERENCES coupons(id)
        )",

        // Order items table
        "CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT,
            product_id INT,
            variation_id INT DEFAULT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id),
            FOREIGN KEY (variation_id) REFERENCES product_variations(id)
        )",

        // Banners table
        "CREATE TABLE IF NOT EXISTS banners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255),
            subtitle VARCHAR(255),
            image_url VARCHAR(255) NOT NULL,
            link VARCHAR(255),
            position VARCHAR(50),
            is_active BOOLEAN DEFAULT TRUE,
            start_date DATE,
            end_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    ];

    foreach ($sqls as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Log error or handle as needed
            error_log("Table creation failed: " . $e->getMessage());
        }
    }
}

// Call the function to initialize the database (comment out after first run)
// initDatabase($pdo);
