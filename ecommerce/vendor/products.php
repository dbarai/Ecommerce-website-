<?php
// vendor/products.php
require_once '../includes/auth_check.php';
require_once '../includes/header.php';

// Only vendors can access
if (!isVendor()) {
    redirect('/ecommerce/index.php');
}

// Get vendor details
$vendorStmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
$vendorStmt->execute([$_SESSION['user_id']]);
$vendor = $vendorStmt->fetch();

if (!$vendor || $vendor['status'] !== 'approved') {
    redirect('/ecommerce/profile.php?message=vendor_pending');
}

// Handle product actions
$action = $_GET['action'] ?? '';
$productId = $_GET['id'] ?? 0;

if ($action === 'delete' && $productId) {
    // Check if product belongs to this vendor
    $checkStmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND vendor_id = ?");
    $checkStmt->execute([$productId, $vendor['id']]);
    
    if ($checkStmt->rowCount() > 0) {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $success = "Product deleted successfully!";
    } else {
        $error = "Product not found or unauthorized.";
    }
}

if ($action === 'status' && $productId) {
    $status = sanitize($_GET['status'] ?? '');
    $validStatuses = ['draft', 'active', 'out_of_stock', 'discontinued'];
    
    if (in_array($status, $validStatuses)) {
        $checkStmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND vendor_id = ?");
        $checkStmt->execute([$productId, $vendor['id']]);
        
        if ($checkStmt->rowCount() > 0) {
            $stmt = $pdo->prepare("UPDATE products SET status = ? WHERE id = ?");
            $stmt->execute([$status, $productId]);
            $success = "Product status updated!";
        }
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bulk_update'])) {
        $selectedProducts = $_POST['selected_products'] ?? [];
        $bulkStatus = sanitize($_POST['bulk_status']);
        $bulkCategory = intval($_POST['bulk_category']) ?: null;
        
        if (!empty($selectedProducts) && in_array($bulkStatus, ['active', 'draft', 'out_of_stock', 'discontinued'])) {
            $placeholders = str_repeat('?,', count($selectedProducts) - 1) . '?';
            
            // Verify products belong to this vendor
            $verifyStmt = $pdo->prepare("SELECT id FROM products WHERE id IN ($placeholders) AND vendor_id = ?");
            $verifyParams = array_merge($selectedProducts, [$vendor['id']]);
            $verifyStmt->execute($verifyParams);
            $validProducts = $verifyStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($validProducts) > 0) {
                // Update status
                $updateStmt = $pdo->prepare("UPDATE products SET status = ? WHERE id IN ($placeholders)");
                $updateParams = array_merge([$bulkStatus], $validProducts);
                $updateStmt->execute($updateParams);
                
                // Update category if provided
                if ($bulkCategory) {
                    $categoryStmt = $pdo->prepare("UPDATE products SET category_id = ? WHERE id IN ($placeholders)");
                    $categoryParams = array_merge([$bulkCategory], $validProducts);
                    $categoryStmt->execute($categoryParams);
                }
                
                $success = "Updated " . count($validProducts) . " product(s)!";
            }
        }
    }
    
    if (isset($_POST['add_product'])) {
        // Add new product
        $name = sanitize($_POST['name']);
        $sku = sanitize($_POST['sku'] ?? '');
        $categoryId = intval($_POST['category_id']);
        $price = floatval($_POST['price']);
        $comparePrice = floatval($_POST['compare_price']) ?: null;
        $costPrice = floatval($_POST['cost_price']) ?: null;
        $quantity = intval($_POST['quantity']);
        $weight = floatval($_POST['weight']) ?: null;
        $description = sanitize($_POST['description']);
        $shortDescription = sanitize($_POST['short_description'] ?? '');
        $status = sanitize($_POST['status']);
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $isTrending = isset($_POST['is_trending']) ? 1 : 0;
        $hasVariants = isset($_POST['has_variants']) ? 1 : 0;
        $type = sanitize($_POST['type']) ?? 'physical';
        
        // Generate slug
        $slug = generateSlug($name);
        
        // Generate SKU if not provided
        if (empty($sku)) {
            $sku = 'PROD-' . strtoupper(uniqid());
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO products (
                    vendor_id, category_id, name, slug, sku, description, short_description,
                    price, compare_price, cost_price, quantity, weight, type, has_variants,
                    status, is_featured, is_trending
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $vendor['id'], $categoryId, $name, $slug, $sku, $description, $shortDescription,
                $price, $comparePrice, $costPrice, $quantity, $weight, $type, $hasVariants,
                $status, $isFeatured, $isTrending
            ]);
            
            $productId = $pdo->lastInsertId();
            
            // Handle image uploads
            if (isset($_FILES['images']) && count($_FILES['images']['name']) > 0) {
                $uploadDir = '../uploads/products/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                    if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                        $fileName = time() . '_' . uniqid() . '_' . basename($_FILES['images']['name'][$i]);
                        $targetPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $targetPath)) {
                            $imageStmt = $pdo->prepare("
                                INSERT INTO product_images (product_id, image_url, is_primary, sort_order)
                                VALUES (?, ?, ?, ?)
                            ");
                            $isPrimary = ($i === 0) ? 1 : 0;
                            $imageStmt->execute([$productId, 'uploads/products/' . $fileName, $isPrimary, $i]);
                        }
                    }
                }
            }
            
            $success = "Product added successfully!";
            
        } catch (Exception $e) {
            $error = "Error adding product: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['edit_product'])) {
        // Update existing product
        $name = sanitize($_POST['name']);
        $sku = sanitize($_POST['sku'] ?? '');
        $categoryId = intval($_POST['category_id']);
        $price = floatval($_POST['price']);
        $comparePrice = floatval($_POST['compare_price']) ?: null;
        $costPrice = floatval($_POST['cost_price']) ?: null;
        $quantity = intval($_POST['quantity']);
        $weight = floatval($_POST['weight']) ?: null;
        $description = sanitize($_POST['description']);
        $shortDescription = sanitize($_POST['short_description'] ?? '');
        $status = sanitize($_POST['status']);
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $isTrending = isset($_POST['is_trending']) ? 1 : 0;
        $hasVariants = isset($_POST['has_variants']) ? 1 : 0;
        $type = sanitize($_POST['type']) ?? 'physical';
        
        // Check if product belongs to this vendor
        $checkStmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND vendor_id = ?");
        $checkStmt->execute([$productId, $vendor['id']]);
        
        if ($checkStmt->rowCount() > 0) {
            $slug = generateSlug($name);
            
            $stmt = $pdo->prepare("
                UPDATE products SET
                    name = ?, slug = ?, sku = ?, category_id = ?, description = ?, short_description = ?,
                    price = ?, compare_price = ?, cost_price = ?, quantity = ?, weight = ?, type = ?, has_variants = ?,
                    status = ?, is_featured = ?, is_trending = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $stmt->execute([
                $name, $slug, $sku, $categoryId, $description, $shortDescription,
                $price, $comparePrice, $costPrice, $quantity, $weight, $type, $hasVariants,
                $status, $isFeatured, $isTrending, $productId
            ]);
            
            $success = "Product updated successfully!";
        } else {
            $error = "Product not found or unauthorized.";
        }
    }
}

// Fetch products with filters
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build filter query
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$stock = $_GET['stock'] ?? '';

$where = ["p.vendor_id = ?"];
$params = [$vendor['id']];

if ($status && $status !== 'all') {
    $where[] = "p.status = ?";
    $params[] = $status;
}

if ($category) {
    $where[] = "p.category_id = ?";
    $params[] = $category;
}

if ($search) {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($stock === 'low') {
    $where[] = "p.quantity <= 10 AND p.quantity > 0";
} elseif ($stock === 'out') {
    $where[] = "p.quantity = 0";
} elseif ($stock === 'in') {
    $where[] = "p.quantity > 10";
}

$whereClause = "WHERE " . implode(" AND ", $where);

// Count total products
$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM products p
    $whereClause
");
$countStmt->execute($params);
$totalProducts = $countStmt->fetch()['total'];
$totalPages = ceil($totalProducts / $limit);

// Fetch products
$productStmt = $pdo->prepare("
    SELECT p.*, c.name as category_name,
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
           (SELECT COUNT(*) FROM order_items WHERE product_id = p.id) as total_sales
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    $whereClause
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");

$allParams = array_merge($params, [$limit, $offset]);
$productStmt->execute($allParams);
$products = $productStmt->fetchAll();

// Fetch categories for filter
$categories = $pdo->query("SELECT id, name FROM categories WHERE status = 'active'")->fetchAll();

// Fetch product statistics
$productStats = [];

// Total products
$totalStmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE vendor_id = ?");
$totalStmt->execute([$vendor['id']]);
$productStats['total'] = $totalStmt->fetch()['count'];

// Active products
$activeStmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE vendor_id = ? AND status = 'active'");
$activeStmt->execute([$vendor['id']]);
$productStats['active'] = $activeStmt->fetch()['count'];

// Low stock products
$lowStockStmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE vendor_id = ? AND quantity <= 10 AND quantity > 0");
$lowStockStmt->execute([$vendor['id']]);
$productStats['low_stock'] = $lowStockStmt->fetch()['count'];

// Out of stock products
$outStockStmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE vendor_id = ? AND quantity = 0");
$outStockStmt->execute([$vendor['id']]);
$productStats['out_stock'] = $outStockStmt->fetch()['count'];

// If editing, fetch product data
$editProduct = null;
if ($action === 'edit' && $productId) {
    $editStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND vendor_id = ?");
    $editStmt->execute([$productId, $vendor['id']]);
    $editProduct = $editStmt->fetch();
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Vendor Sidebar -->
        <div class="col-lg-2">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">Vendor Panel</h6>
                </div>
                <div class="list-group list-group-flush">
                    <a href="/ecommerce/vendor/dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="/ecommerce/vendor/products.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-box me-2"></i>Products
                    </a>
                    <a href="/ecommerce/vendor/orders.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-shopping-bag me-2"></i>Orders
                    </a>
                    <a href="/ecommerce/vendor/sales.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-line me-2"></i>Sales Report
                    </a>
                    <a href="/ecommerce/vendor/profile.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-store me-2"></i>Store Profile
                    </a>
                    <a href="/ecommerce/index.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-store me-2"></i>View Store
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-10">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold">Product Management</h4>
                    <p class="text-muted mb-0">Manage your products and inventory</p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="fas fa-plus me-2"></i>Add New Product
                </button>
            </div>
            
            <!-- Messages -->
            <?php if(isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Product Statistics -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-primary border-top-0 border-end-0 border-bottom-0 border-5">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Total Products</h6>
                                    <h3 class="fw-bold"><?php echo $productStats['total']; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-box fa-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card border-success border-top-0 border-end-0 border-bottom-0 border-5">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Active Products</h6>
                                    <h3 class="fw-bold"><?php echo $productStats['active']; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check-circle fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card border-warning border-top-0 border-end-0 border-bottom-0 border-5">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Low Stock</h6>
                                    <h3 class="fw-bold"><?php echo $productStats['low_stock']; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card border-danger border-top-0 border-end-0 border-bottom-0 border-5">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Out of Stock</h6>
                                    <h3 class="fw-bold"><?php echo $productStats['out_stock']; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-times-circle fa-2x text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <input type="text" name="search" class="form-control" placeholder="Search products..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" 
                                        <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="draft" <?php echo $status == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="out_of_stock" <?php echo $status == 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                <option value="discontinued" <?php echo $status == 'discontinued' ? 'selected' : ''; ?>>Discontinued</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="stock" class="form-select">
                                <option value="">All Stock</option>
                                <option value="in" <?php echo $stock == 'in' ? 'selected' : ''; ?>>In Stock (>10)</option>
                                <option value="low" <?php echo $stock == 'low' ? 'selected' : ''; ?>>Low Stock (≤10)</option>
                                <option value="out" <?php echo $stock == 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                        </div>
                        <div class="col-md-1">
                            <a href="/ecommerce/vendor/products.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-redo"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Bulk Actions -->
            <form method="POST" id="bulkForm" class="mb-3">
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group">
                            <select name="bulk_status" class="form-select">
                                <option value="">Bulk Actions</option>
                                <option value="active">Activate</option>
                                <option value="draft">Mark as Draft</option>
                                <option value="out_of_stock">Mark as Out of Stock</option>
                                <option value="discontinued">Discontinue</option>
                            </select>
                            <button type="submit" name="bulk_update" class="btn btn-outline-primary">
                                Apply
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="bulk_category" class="form-select">
                            <option value="">Change Category</option>
                            <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAllProducts">
                            <label class="form-check-label" for="selectAllProducts">
                                Select All
                            </label>
                        </div>
                    </div>
                </div>
            </form>
            
            <!-- Products Table -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="selectAllCheckbox">
                                    </th>
                                    <th width="80">Image</th>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Sales</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($products as $product): ?>
                                <tr>
                                    <!-- Checkbox -->
                                    <td>
                                        <input type="checkbox" class="product-checkbox" 
                                               name="selected_products[]" value="<?php echo $product['id']; ?>">
                                    </td>
                                    
                                    <!-- Image -->
                                    <td>
                                        <img src="/ecommerce/<?php echo $product['primary_image'] ?: 'assets/images/placeholder.jpg'; ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             class="rounded" 
                                             style="width: 50px; height: 50px; object-fit: cover;">
                                    </td>
                                    
                                    <!-- Product Info -->
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <h6 class="mb-1">
                                                    <a href="/ecommerce/product.php?id=<?php echo $product['id']; ?>" 
                                                       target="_blank" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($product['name']); ?>
                                                    </a>
                                                </h6>
                                                <small class="text-muted">SKU: <?php echo $product['sku'] ?: 'N/A'; ?></small>
                                                <br>
                                                <small class="text-muted">
                                                    Created: <?php echo date('M d, Y', strtotime($product['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Category -->
                                    <td>
                                        <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                    </td>
                                    
                                    <!-- Price -->
                                    <td>
                                        <div class="price">
                                            <span class="fw-bold text-primary">$<?php echo number_format($product['price'], 2); ?></span>
                                            <?php if($product['compare_price']): ?>
                                            <br>
                                            <small class="text-muted text-decoration-line-through">
                                                $<?php echo number_format($product['compare_price'], 2); ?>
                                            </small>
                                            <?php endif; ?>
                                            <?php if($product['cost_price']): ?>
                                            <br>
                                            <small class="text-success">
                                                Cost: $<?php echo number_format($product['cost_price'], 2); ?>
                                            </small>
                                            <?php endif; ?>
                                       
