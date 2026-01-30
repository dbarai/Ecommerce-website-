<?php
// index.php
require_once 'includes/header.php';

// Fetch active banners
$bannerStmt = $pdo->prepare("SELECT * FROM banners WHERE is_active = 1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY position");
$bannerStmt->execute();
$banners = $bannerStmt->fetchAll();

// Fetch featured products (for example, products with status 'active' and maybe a flag for featured, but we don't have that column, so let's just get active products)
$productStmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, v.shop_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN vendors v ON p.vendor_id = v.id 
    WHERE p.status = 'active' 
    ORDER BY p.created_at DESC 
    LIMIT 12
");
$productStmt->execute();
$products = $productStmt->fetchAll();

// Fetch categories
$categoryStmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name");
$categoryStmt->execute();
$categories = $categoryStmt->fetchAll();
?>

<!-- Banner Slider -->
<div id="bannerCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
    <div class="carousel-inner">
        <?php foreach ($banners as $index => $banner): ?>
            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                <img src="<?php echo htmlspecialchars($banner['image_url']); ?>" class="d-block w-100" alt="<?php echo htmlspecialchars($banner['title']); ?>" style="height: 400px; object-fit: cover;">
                <div class="carousel-caption d-none d-md-block">
                    <h5><?php echo htmlspecialchars($banner['title']); ?></h5>
                    <p><?php echo htmlspecialchars($banner['subtitle']); ?></p>
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

<!-- Categories -->
<div class="row mb-4">
    <div class="col-12">
        <h2>Shop by Category</h2>
    </div>
    <?php foreach ($categories as $category): ?>
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                    <a href="/ecommerce/search.php?category=<?php echo $category['id']; ?>" class="btn btn-primary">Browse</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Featured Products -->
<div class="row">
    <div class="col-12">
        <h2>Featured Products</h2>
    </div>
    <?php foreach ($products as $product): ?>
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <!-- Product Image (using first image or placeholder) -->
                <?php
                $imageStmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? LIMIT 1");
                $imageStmt->execute([$product['id']]);
                $image = $imageStmt->fetch();
                ?>
                <img src="<?php echo $image ? htmlspecialchars($image['image_url']) : 'https://via.placeholder.com/300x200'; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>" style="height: 200px; object-fit: cover;">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                    <p class="card-text"><?php echo substr(htmlspecialchars($product['description']), 0, 100); ?>...</p>
                    <div class="mt-auto">
                        <p class="card-text">
                            <strong>$<?php echo number_format($product['price'], 2); ?></strong>
                            <?php if ($product['compare_price']): ?>
                                <span class="text-muted text-decoration-line-through">$<?php echo number_format($product['compare_price'], 2); ?></span>
                            <?php endif; ?>
                        </p>
                        <a href="/ecommerce/product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">View Details</a>
                        <button class="btn btn-outline-secondary add-to-cart" data-product-id="<?php echo $product['id']; ?>">Add to Cart</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
