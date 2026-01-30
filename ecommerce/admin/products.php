<?php
// admin/products.php
require_once '../includes/auth_check.php';
require_once '../includes/header.php';

// Only admin can access
if (!isAdmin()) {
    redirect('/ecommerce/index.php');
}

// Handle actions
$action = $_GET['action'] ?? '';
$productId = $_GET['id'] ?? 0;

if ($action === 'delete' && $productId) {
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $success = "Product deleted successfully!";
}

if ($action === 'status' && $productId) {
    $status = $_GET['status'] ?? '';
    if (in_array($status, ['active', 'draft', 'out_of_stock', 'discontinued'])) {
        $stmt = $pdo->prepare("UPDATE products SET status = ? WHERE id = ?");
        $stmt->execute([$status, $productId]);
        $success = "Product status updated!";
    }
}

// Handle form submission for add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product'])) {
        // Add new product
        $name = sanitize($_POST['name']);
        $slug = generateSlug($name);
        $categoryId = intval($_POST['category_id']);
        $vendorId = intval($_POST['vendor_id']);
        $price = floatval($_POST['price']);
        $comparePrice = floatval($_POST['compare_price']) ?: NULL;
        $quantity = intval($_POST['quantity']);
        $description = sanitize($_POST['description']);
        $status = sanitize($_POST['status']);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO products (name, slug, category_id, vendor_id, price, compare_price, quantity, description, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $slug, $categoryId, $vendorId, $price, $comparePrice, $quantity, $description, $status]);
            $productId = $pdo->lastInsertId();
            $success = "Product added successfully!";
        } catch (Exception $e) {
            $error = "Error adding product: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['edit_product'])) {
        // Update product
        $name = sanitize($_POST['name']);
        $slug = generateSlug($name);
        $categoryId = intval($_POST['category_id']);
        $vendorId = intval($_POST['vendor_id']);
        $price = floatval($_POST['price']);
        $comparePrice = floatval($_POST['compare_price']) ?: NULL;
        $quantity = intval($_POST['quantity']);
        $description = sanitize($_POST['description']);
        $status = sanitize($_POST['status']);
        
        $stmt = $pdo->prepare("
            UPDATE products SET 
                name = ?, slug = ?, category_id = ?, vendor_id = ?, price = ?, 
                compare_price = ?, quantity = ?, description = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $slug, $categoryId, $vendorId, $price, $comparePrice, $quantity, $description, $status, $productId]);
        $success = "Product updated successfully!";
    }
}

// Fetch products with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build search query
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$vendor = $_GET['vendor'] ?? '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $where[] = "p.category_id = ?";
    $params[] = $category;
}

if ($statusFilter) {
    $where[] = "p.status = ?";
    $params[] = $statusFilter;
}

if ($vendor) {
    $where[] = "p.vendor_id = ?";
    $params[] = $vendor;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

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
    SELECT p.*, c.name as category_name, v.shop_name, v.shop_slug,
           (SELECT COUNT(*) FROM order_items WHERE product_id = p.id) as total_sales
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN vendors v ON p.vendor_id = v.id
    $whereClause
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");

$allParams = array_merge($params, [$limit, $offset]);
$productStmt->execute($allParams);
$products = $productStmt->fetchAll();

// Fetch categories for filter
$categories = $pdo->query("SELECT id, name FROM categories WHERE status = 'active'")->fetchAll();

// Fetch vendors for filter
$vendors = $pdo->query("SELECT id, shop_name FROM vendors WHERE status = 'approved'")->fetchAll();

// If editing, fetch product data
$editProduct = null;
if ($action === 'edit' && $productId) {
    $editStmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $editStmt->execute([$productId]);
    $editProduct = $editStmt->fetch();
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-2">
            <?php include 'admin-sidebar.php'; ?>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-10">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold">Product Management</h4>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="fas fa-plus me-2"></i>Add New Product
                </button>
            </div>
            
            <!-- Messages -->
            <?php if(isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
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
                            <select name="vendor" class="form-select">
                                <option value="">All Vendors</option>
                                <?php foreach($vendors as $ven): ?>
                                <option value="<?php echo $ven['id']; ?>" 
                                        <?php echo $vendor == $ven['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ven['shop_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $statusFilter == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="draft" <?php echo $statusFilter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="out_of_stock" <?php echo $statusFilter == 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                <option value="discontinued" <?php echo $statusFilter == 'discontinued' ? 'selected' : ''; ?>>Discontinued</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                        </div>
                        <div class="col-md-1">
                            <a href="/ecommerce/admin/products.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-redo"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Products Table -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="60">
                                        <input type="checkbox" id="selectAll">
                                    </th>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Vendor</th>
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
                                    <td>
                                        <input type="checkbox" class="product-checkbox" value="<?php echo $product['id']; ?>">
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <img src="/ecommerce/assets/images/placeholder.jpg" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                     class="rounded" 
                                                     style="width: 40px; height: 40px; object-fit: cover;">
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-0">
                                                    <a href="/ecommerce/product.php?id=<?php echo $product['id']; ?>" 
                                                       target="_blank" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($product['name']); ?>
                                                    </a>
                                                </h6>
                                                <small class="text-muted">SKU: <?php echo $product['sku'] ?: 'N/A'; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td>
                                        <a href="/ecommerce/vendor.php?id=<?php echo $product['vendor_id']; ?>" 
                                           target="_blank" class="text-decoration-none">
                                            <?php echo htmlspecialchars($product['shop_name'] ?? 'N/A'); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <strong class="text-primary">$<?php echo number_format($product['price'], 2); ?></strong>
                                        <?php if($product['compare_price']): ?>
                                        <br><small class="text-muted text-decoration-line-through">
                                            $<?php echo number_format($product['compare_price'], 2); ?>
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($product['quantity'] > 10): ?>
                                        <span class="badge bg-success"><?php echo $product['quantity']; ?> in stock</span>
                                        <?php elseif($product['quantity'] > 0): ?>
                                        <span class="badge bg-warning">Low stock (<?php echo $product['quantity']; ?>)</span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">Out of stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $product['total_sales']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            switch($product['status']) {
                                                case 'active': echo 'success'; break;
                                                case 'draft': echo 'secondary'; break;
                                                case 'out_of_stock': echo 'warning'; break;
                                                case 'discontinued': echo 'danger'; break;
                                                default: echo 'secondary';
                                            }
                                        ?>"><?php echo ucfirst($product['status']); ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="/ecommerce/admin/products.php?action=edit&id=<?php echo $product['id']; ?>" 
                                               class="btn btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="/ecommerce/admin/products.php?action=delete&id=<?php echo $product['id']; ?>" 
                                               class="btn btn-outline-danger" 
                                               onclick="return confirm('Are you sure you want to delete this product?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-outline-secondary dropdown-toggle" 
                                                        data-bs-toggle="dropdown">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="#">
                                                            <i class="fas fa-eye me-2"></i>View
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="#">
                                                            <i class="fas fa-copy me-2"></i>Duplicate
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <h6 class="dropdown-header">Change Status</h6>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" 
                                                           href="/ecommerce/admin/products.php?action=status&id=<?php echo $product['id']; ?>&status=active">
                                                            <i class="fas fa-circle text-success me-2"></i>Active
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" 
                                                           href="/ecommerce/admin/products.php?action=status&id=<?php echo $product['id']; ?>&status=draft">
                                                            <i class="fas fa-circle text-secondary me-2"></i>Draft
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" 
                                                           href="/ecommerce/admin/products.php?action=status&id=<?php echo $product['id']; ?>&status=out_of_stock">
                                                            <i class="fas fa-circle text-warning me-2"></i>Out of Stock
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" 
                                                           href="/ecommerce/admin/products.php?action=status&id=<?php echo $product['id']; ?>&status=discontinued">
                                                            <i class="fas fa-circle text-danger me-2"></i>Discontinued
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if($totalPages > 1): ?>
                    <nav class="px-3 py-3 border-top">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
                                    Previous
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>">
                                    Next
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Product Name *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">SKU (Stock Keeping Unit)</label>
                            <input type="text" name="sku" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category *</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vendor *</label>
                            <select name="vendor_id" class="form-select" required>
                                <option value="">Select Vendor</option>
                                <?php foreach($vendors as $ven): ?>
                                <option value="<?php echo $ven['id']; ?>"><?php echo htmlspecialchars($ven['shop_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Price ($) *</label>
                            <input type="number" name="price" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Compare at Price ($)</label>
                            <input type="number" name="compare_price" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Quantity *</label>
                            <input type="number" name="quantity" class="form-control" min="0" required value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                                <option value="out_of_stock">Out of Stock</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Select all checkbox
    $('#selectAll').change(function() {
        $('.product-checkbox').prop('checked', $(this).prop('checked'));
    });
    
    // If editing, show edit modal
    <?php if($action === 'edit' && $editProduct): ?>
    $(window).on('load', function() {
        $('#addProductModal').modal('show');
    });
    <?php endif; ?>
});
</script>

<?php require_once '../includes/footer.php'; ?>
