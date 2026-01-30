<?php
// db.php - Database Connection & Initialization
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'omnimart_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Create tables if they don't exist
function initializeDatabase($pdo) {
    $tables = [
        // Users table
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100),
            phone VARCHAR(20),
            address TEXT,
            avatar VARCHAR(255),
            role ENUM('admin', 'vendor', 'customer') DEFAULT 'customer',
            email_verified BOOLEAN DEFAULT FALSE,
            status ENUM('active', 'suspended', 'pending') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        // Vendors table
        "CREATE TABLE IF NOT EXISTS vendors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            shop_name VARCHAR(100) UNIQUE NOT NULL,
            shop_slug VARCHAR(100) UNIQUE NOT NULL,
            shop_description TEXT,
            logo VARCHAR(255),
            banner VARCHAR(255),
            phone VARCHAR(20),
            address TEXT,
            city VARCHAR(50),
            state VARCHAR(50),
            country VARCHAR(50),
            postal_code VARCHAR(20),
            tax_id VARCHAR(50),
            commission_rate DECIMAL(5,2) DEFAULT 10.00,
            balance DECIMAL(10,2) DEFAULT 0.00,
            status ENUM('pending', 'approved', 'suspended') DEFAULT 'pending',
            rating DECIMAL(3,2) DEFAULT 0.00,
            total_sales INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        // Categories table
        "CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) UNIQUE NOT NULL,
            description TEXT,
            parent_id INT DEFAULT NULL,
            image VARCHAR(255),
            sort_order INT DEFAULT 0,
            is_featured BOOLEAN DEFAULT FALSE,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        // Products table
        "CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vendor_id INT,
            category_id INT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            sku VARCHAR(100) UNIQUE,
            description LONGTEXT,
            short_description TEXT,
            price DECIMAL(10,2) NOT NULL,
            compare_price DECIMAL(10,2),
            cost_price DECIMAL(10,2),
            quantity INT DEFAULT 0,
            weight DECIMAL(10,2),
            length DECIMAL(10,2),
            width DECIMAL(10,2),
            height DECIMAL(10,2),
            has_variants BOOLEAN DEFAULT FALSE,
            type ENUM('physical', 'digital') DEFAULT 'physical',
            status ENUM('draft', 'active', 'out_of_stock', 'discontinued') DEFAULT 'draft',
            is_featured BOOLEAN DEFAULT FALSE,
            is_trending BOOLEAN DEFAULT FALSE,
            views INT DEFAULT 0,
            rating DECIMAL(3,2) DEFAULT 0.00,
            total_reviews INT DEFAULT 0,
            seo_title VARCHAR(255),
            seo_description TEXT,
            seo_keywords TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        // Product Variations
        "CREATE TABLE IF NOT EXISTS product_variations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT,
            sku VARCHAR(100),
            option1 VARCHAR(100),
            option2 VARCHAR(100),
            option3 VARCHAR(100),
            price DECIMAL(10,2),
            compare_price DECIMAL(10,2),
            cost_price DECIMAL(10,2),
            quantity INT DEFAULT 0,
            image VARCHAR(255),
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        // Product Images
        "CREATE TABLE IF NOT EXISTS product_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT,
            variation_id INT DEFAULT NULL,
            image_url VARCHAR(255) NOT NULL,
            is_primary BOOLEAN DEFAULT FALSE,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (variation_id) REFERENCES product_variations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        // Coupons/Discounts
        "CREATE TABLE IF NOT EXISTS coupons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(100),
            description TEXT,
            discount_type ENUM('percentage', 'fixed', 'free_shipping') NOT NULL,
            discount_value DECIMAL(10,2) NOT NULL,
            min_order_amount DECIMAL(10,2) DEFAULT 0,
            max_discount_amount DECIMAL(10,2),
            usage_limit INT DEFAULT NULL,
            used_count INT DEFAULT 0,
            per_user_limit INT DEFAULT 1,
            start_date DATE,
            end_date DATE,
            is_active BOOLEAN DEFAULT TRUE,
            applies_to ENUM('all', 'categories', 'products', 'vendors') DEFAULT 'all',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        // Orders table
        "CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(50) UNIQUE NOT NULL,
            user_id INT,
            vendor_id INT,
            coupon_id INT,
            subtotal DECIMAL(10,2) NOT NULL,
            discount DECIMAL(10,2) DEFAULT 0,
            tax DECIMAL(10,2) DEFAULT 0,
            shipping DECIMAL(10,2) DEFAULT 0,
            total DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50),
            payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
            order_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
            shipping_address TEXT,
            billing_address TEXT,
            customer_note TEXT,
            admin_note TEXT,
            tracking_number VARCHAR(100),
            estimated_delivery DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL,
            FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        // Order Items
        "CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT,
            product_id INT,
            variation_id INT,
            product_name VARCHAR(255) NOT NULL,
            variation_name VARCHAR(255),
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
            FOREIGN KEY (variation_id) REFERENCES product_variations(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        // Banners/Sliders
        "CREATE TABLE IF NOT EXISTS banners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255),
            subtitle VARCHAR(255),
            description TEXT,
            image_url VARCHAR(255) NOT NULL,
            button_text VARCHAR(50),
            button_link VARCHAR(255),
            position VARCHAR(50),
            type ENUM('slider', 'banner', 'promo') DEFAULT 'slider',
            is_active BOOLEAN DEFAULT TRUE,
            start_date DATE,
            end_date DATE,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        // Reviews
        "CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT,
            user_id INT,
            order_id INT,
            rating INT CHECK (rating >= 1 AND rating <= 5),
            title VARCHAR(255),
            comment TEXT,
            images TEXT,
            is_approved BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        // Cart table
        "CREATE TABLE IF NOT EXISTS cart (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            session_id VARCHAR(100),
            product_id INT,
            variation_id INT,
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (variation_id) REFERENCES product_variations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        // Wishlist
        "CREATE TABLE IF NOT EXISTS wishlist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            product_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            UNIQUE KEY unique_wishlist (user_id, product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];
    
    foreach ($tables as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Log error but continue
            error_log("Table creation error: " . $e->getMessage());
        }
    }
    
    // Insert admin user if not exists (password: admin123)
    $adminCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $adminCheck->execute(['admin@omnimart.com']);
    
    if ($adminCheck->rowCount() === 0) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $adminInsert = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
        $adminInsert->execute(['admin', 'admin@omnimart.com', $hashedPassword, 'Administrator']);
    }
}

// Uncomment to initialize database on first run
// initializeDatabase($pdo);

// Helper functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isVendor() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'vendor';
}

function redirect($url) {
    header("Location: $url");
    exit();
}
?>
