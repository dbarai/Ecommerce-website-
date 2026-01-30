<?php
// index.php - Homepage
require_once 'includes/header.php';

// Fetch active banners
$bannerStmt = $pdo->prepare("SELECT * FROM banners WHERE is_active = 1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY sort_order");
$bannerStmt->execute();
$banners = $bannerStmt->fetchAll();

// Fetch featured categories
$categoryStmt = $pdo->prepare("SELECT * FROM categories WHERE is_featured = 1 AND status = 'active' ORDER BY sort_order LIMIT 8");
$categoryStmt->execute();
$categories = $categoryStmt->fetchAll();

// Fetch featured products
$productStmt = $pdo->prepare("
    SELECT p.*, v.shop_name, v.shop_slug, 
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM products p
    LEFT JOIN vendors v ON p.vendor_id = v.id
    WHERE p.status = 'active' AND p.is_featured = 1
    ORDER BY p.created_at DESC
    LIMIT 12
");
$productStmt->execute();
$featuredProducts = $productStmt->fetchAll();

// Fetch trending products
$trendingStmt = $pdo->prepare("
    SELECT p.*, v.shop_name, v.shop_slug,
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM products p
    LEFT JOIN vendors v ON p.vendor_id = v.id
    WHERE p.status = 'active' AND p.is_trending = 1
    ORDER BY p.views DESC
    LIMIT 8
");
$trendingStmt->execute();
$trendingProducts = $trendingStmt->fetchAll();

// Fetch best selling products
$bestSellingStmt = $pdo->prepare("
    SELECT p.*, v.shop_name, v.shop_slug,
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
           SUM(oi.quantity) as total_sold
    FROM products p
    LEFT JOIN vendors v ON p.vendor_id = v.id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.order_status = 'delivered'
    WHERE p.status = 'active'
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 8
");
$bestSellingStmt->execute();
$bestSellingProducts = $bestSellingStmt->fetchAll();
?>

<!-- Hero Banner/Slider -->
<section class="hero-slider mb-5">
    <div id="bannerCarousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <?php foreach($banners as $key => $banner): ?>
            <button type="button" data-bs-target="#bannerCarousel" data-bs-slide-to="<?php echo $key; ?>" 
                    class="<?php echo $key === 0 ? 'active' : ''; ?>"></button>
            <?php endforeach; ?>
        </div>
        
        <div class="carousel-inner rounded-3" style="height: 500px;">
            <?php foreach($banners as $key => $banner): ?>
            <div class="carousel-item <?php echo $key === 0 ? 'active' : ''; ?>">
                <img src="/ecommerce/<?php echo htmlspecialchars($banner['image_url']); ?>" 
                     class="d-block w-100 h-100 object-fit-cover" 
                     alt="<?php echo htmlspecialchars($banner['title']); ?>">
                <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 p-4 rounded">
                    <h2 class="display-5 fw-bold"><?php echo htmlspecialchars($banner['title']); ?></h2>
                    <p class="lead"><?php echo htmlspecialchars($banner['subtitle']); ?></p>
                    <?php if($banner['button_text']): ?>
                    <a href="<?php echo htmlspecialchars($banner['button_link']); ?>" 
                       class="btn btn-primary btn-lg mt-3">
                        <?php echo htmlspecialchars($banner['button_text']); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <button class="carousel-control-prev" type="button" data-bs-target="#bannerCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#bannerCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>
    </div>
</section>

<!-- Categories Section -->
<section class="categories mb-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Shop by Category</h2>
            <a href="/ecommerce/categories.php" class="btn btn-outline-primary">View All</a>
        </div>
        
        <div class="row g-4">
            <?php foreach($categories as $category): ?>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="/ecommerce/search.php?category=<?php echo $category['id']; ?>" class="text-decoration-none">
                    <div class="category-card shadow-sm">
                        <?php if($category['image']): ?>
                        <img src="/ecommerce/<?php echo htmlspecialchars($category['image']); ?>" 
                             alt="<?php echo htmlspecialchars($category['name']); ?>" 
                             class="img-fluid mb-3 rounded" style="height: 100px; width: 100%; object-fit: cover;">
                        <?php endif; ?>
                        <h5 class="fw-bold"><?php echo htmlspecialchars($category['name']); ?></h5>
                        <p class="small"><?php echo substr(htmlspecialchars($category['description']), 0, 50); ?>...</p>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Products -->
<section class="featured-products mb-5">
    <div class="container">
        <h2 class="fw-bold mb-4 text-center">Featured Products</h2>
        
        <div class="row g-4">
            <?php foreach($featuredProducts as $product): ?>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="product-card card h-100 shadow-sm">
                    <?php if($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                    <span class="badge bg-danger badge-custom">Sale</span>
                    <?php endif; ?>
                    
                    <a href="/ecommerce/product.php?id=<?php echo $product['id']; ?>">
                        <img src="/ecommerce/<?php echo $product['primary_image'] ?: 'assets/images/placeholder.jpg'; ?>" 
                             class="card-img-top" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             style="height: 200px; object-fit: cover;">
                    </a>
                    
                    <div class="card-body d-flex flex-column">
                        <small class="text-muted">
                            <a href="/ecommerce/vendor.php?id=<?php echo $product['vendor_id']; ?>" 
                               class="text-decoration-none">
                                <?php echo htmlspecialchars($product['shop_name']); ?>
                            </a>
                        </small>
                        
                        <h5 class="card-title mt-2">
                            <a href="/ecommerce/product.php?id=<?php echo $product['id']; ?>" 
                               class="text-decoration-none text-dark">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </a>
                        </h5>
                        
                        <div class="rating mb-2">
                            <?php
                            $rating = $product['rating'];
                            $fullStars = floor($rating);
                            $hasHalfStar = ($rating - $fullStars) >= 0.5;
                            ?>
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <?php if($i <= $fullStars): ?>
                                    <i class="fas fa-star text-warning"></i>
                                <?php elseif($hasHalfStar && $i == $fullStars + 1): ?>
                                    <i class="fas fa-star-half-alt text-warning"></i>
                                <?php else: ?>
                                    <i class="far fa-star text-warning"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <small class="text-muted">(<?php echo $product['total_reviews']; ?>)</small>
                        </div>
                        
                        <div class="price mb-3">
                            <span class="fw-bold fs-5 text-primary">$<?php echo number_format($product['price'], 2); ?></span>
                            <?php if($product['compare_price']): ?>
                            <span class="text-muted text-decoration-line-through ms-2">$<?php echo number_format($product['compare_price'], 2); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-auto d-flex gap-2">
                            <button class="btn btn-primary flex-grow-1 add-to-cart" 
                                    data-product-id="<?php echo $product['id']; ?>">
                                <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                            </button>
                            <button class="btn btn-outline-secondary add-to-wishlist" 
                                    data-product-id="<?php echo $product['id']; ?>"
                                    title="Add to Wishlist">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="/ecommerce/search.php" class="btn btn-outline-primary btn-lg">View All Products</a>
        </div>
    </div>
</section>

<!-- Trending Products -->
<section class="trending-products mb-5 bg-light py-5">
    <div class="container">
        <h2 class="fw-bold mb-4 text-center">Trending Now</h2>
        
        <div class="row g-4">
            <?php foreach($trendingProducts as $product): ?>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="product-card card h-100 shadow-sm">
                    <a href="/ecommerce/product.php?id=<?php echo $product['id']; ?>">
                        <img src="/ecommerce/<?php echo $product['primary_image'] ?: 'assets/images/placeholder.jpg'; ?>" 
                             class="card-img-top" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             style="height: 200px; object-fit: cover;">
                    </a>
                    
                    <div class="card-body">
                        <h6 class="card-title">
                            <a href="/ecommerce/product.php?id=<?php echo $product['id']; ?>" 
                               class="text-decoration-none text-dark">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </a>
                        </h6>
                        
                        <div class="price mb-2">
                            <span class="fw-bold text-primary">$<?php echo number_format($product['price'], 2); ?></span>
                        </div>
                        
                        <button class="btn btn-sm btn-primary w-100 add-to-cart" 
                                data-product-id="<?php echo $product['id']; ?>">
                            Add to Cart
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Best Selling Products -->
<section class="best-selling mb-5">
    <div class="container">
        <h2 class="fw-bold mb-4 text-center">Best Sellers</h2>
        
        <div class="row g-4">
            <?php foreach($bestSellingProducts as $product): ?>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="product-card card h-100 shadow-sm">
                    <span class="badge bg-success badge-custom">Best Seller</span>
                    
                    <a href="/ecommerce/product.php?id=<?php echo $product['id']; ?>">
                        <img src="/ecommerce/<?php echo $product['primary_image'] ?: 'assets/images/placeholder.jpg'; ?>" 
                             class="card-img-top" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             style="height: 200px; object-fit: cover;">
                    </a>
                    
                    <div class="card-body">
                        <h6 class="card-title">
                            <a href="/ecommerce/product.php?id=<?php echo $product['id']; ?>" 
                               class="text-decoration-none text-dark">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </a>
                        </h6>
                        
                        <div class="price mb-2">
                            <span class="fw-bold text-primary">$<?php echo number_format($product['price'], 2); ?></span>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-primary flex-grow-1 add-to-cart" 
                                    data-product-id="<?php echo $product['id']; ?>">
                                Add to Cart
                            </button>
                            <button class="btn btn-sm btn-outline-secondary add-to-wishlist" 
                                    data-product-id="<?php echo $product['id']; ?>">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Newsletter Subscription -->
<section class="newsletter bg-primary text-white py-5 mb-5 rounded-3">
    <div class="container text-center">
        <h2 class="fw-bold mb-3">Stay Updated</h2>
        <p class="lead mb-4">Subscribe to our newsletter for the latest products and exclusive offers.</p>
        
        <form class="row g-3 justify-content-center" id="newsletterForm">
            <div class="col-md-6">
                <div class="input-group">
                    <input type="email" class="form-control form-control-lg" 
                           placeholder="Enter your email address" required>
                    <button class="btn btn-light btn-lg" type="submit">Subscribe</button>
                </div>
            </div>
        </form>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
