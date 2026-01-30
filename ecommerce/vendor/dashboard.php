<?php
// vendor/dashboard.php
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

// Fetch vendor stats
$stats = [];

// Total products
$productStmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE vendor_id = ?");
$productStmt->execute([$vendor['id']]);
$stats['total_products'] = $productStmt->fetch()['count'];

// Total orders
$orderStmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM orders 
    WHERE vendor_id = ? AND order_status IN ('processing', 'shipped', 'delivered')
");
$orderStmt->execute([$vendor['id']]);
$stats['total_orders'] = $orderStmt->fetch()['count'];

// Total revenue
$revenueStmt = $pdo->prepare("
    SELECT SUM(total) as total 
    FROM orders 
    WHERE vendor_id = ? AND order_status = 'delivered'
");
$revenueStmt->execute([$vendor['id']]);
$stats['total_revenue'] = $revenueStmt->fetch()['total'] ?? 0;

// Pending orders
$pendingStmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM orders 
    WHERE vendor_id = ? AND order_status = 'pending'
");
$pendingStmt->execute([$vendor['id']]);
$stats['pending_orders'] = $pendingStmt->fetch()['count'];

// Recent orders
$recentOrdersStmt = $pdo->prepare("
    SELECT o.*, u.username, u.email 
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.vendor_id = ?
    ORDER BY o.created_at DESC
    LIMIT 10
");
$recentOrdersStmt->execute([$vendor['id']]);
$recentOrders = $recentOrdersStmt->fetchAll();

// Recent products
$recentProductsStmt = $pdo->prepare("
    SELECT * FROM products 
    WHERE vendor_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$recentProductsStmt->execute([$vendor['id']]);
$recentProducts = $recentProductsStmt->fetchAll();
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
                    <a href="/ecommerce/vendor/dashboard.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="/ecommerce/vendor/products.php" class="list-group-item list-group-item-action">
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
                    <a href="/ecommerce/vendor/settings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>
                    <a href="/ecommerce/index.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-store me-2"></i>View Store
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-10">
            <!-- Welcome Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold">Welcome, <?php echo $vendor['shop_name']; ?>!</h4>
                    <p class="text-muted mb-0">Your vendor dashboard</p>
                </div>
                <div class="text-end">
                    <span class="badge bg-success">Balance: $<?php echo number_format($vendor['balance'], 2); ?></span>
                    <span class="badge bg-secondary ms-2">Commission: <?php echo $vendor['commission_rate']; ?>%</span>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Total Products</h6>
                                    <h3 class="fw-bold"><?php echo number_format($stats['total_products']); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-box fa-2x text-primary"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="/ecommerce/vendor/products.php" class="small text-decoration-none">
                                    Manage Products <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card border-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Total Orders</h6>
                                    <h3 class="fw-bold"><?php echo number_format($stats['total_orders']); ?></h3>
                                    <?php if($stats['pending_orders'] > 0): ?>
                                    <small class="text-warning">
                                        <i class="fas fa-clock me-1"></i><?php echo $stats['pending_orders']; ?> pending
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-shopping-bag fa-2x text-success"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="/ecommerce/vendor/orders.php" class="small text-decoration-none">
                                    View Orders <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card border-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Total Revenue</h6>
                                    <h3 class="fw-bold">$<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-dollar-sign fa-2x text-warning"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="/ecommerce/vendor/sales.php" class="small text-decoration-none">
                                    View Report <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card border-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Store Rating</h6>
                                    <h3 class="fw-bold"><?php echo number_format($vendor['rating'], 1); ?>/5</h3>
                                    <div class="rating">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <?php if($i <= floor($vendor['rating'])): ?>
                                                <i class="fas fa-star text-warning"></i>
                                            <?php else: ?>
                                                <i class="far fa-star text-warning"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-star fa-2x text-info"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="/ecommerce/vendor/profile.php" class="small text-decoration-none">
                                    View Profile <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Recent Orders</h6>
                            <a href="/ecommerce/vendor/orders.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Order #</th>
                                            <th>Customer</th>
                                            <th>Date</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recentOrders as $order): ?>
                                        <tr>
                                            <td>
                                                <a href="/ecommerce/vendor/orders.php?action=view&id=<?php echo $order['id']; ?>" 
                                                   class="text-decoration-none">
                                                    <?php echo $order['order_number']; ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($order['username']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                            <td>$<?php echo number_format($order['total'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch($order['order_status']) {
                                                        case 'pending': echo 'warning'; break;
                                                        case 'processing': echo 'info'; break;
                                                        case 'shipped': echo 'primary'; break;
                                                        case 'delivered': echo 'success'; break;
                                                        case 'cancelled': echo 'danger'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>"><?php echo ucfirst($order['order_status']); ?></span>
                                            </td>
                                            <td>
                                                <a href="/ecommerce/vendor/orders.php?action=view&id=<?php echo $order['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Products -->
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Your Products</h6>
                            <a href="/ecommerce/vendor/products.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach($recentProducts as $product): ?>
                                <a href="/ecommerce/vendor/products.php?action=edit&id=<?php echo $product['id']; ?>" 
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <img src="/ecommerce/assets/images/placeholder.jpg" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                 class="rounded" 
                                                 style="width: 40px; height: 40px; object-fit: cover;">
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                            <small class="text-muted">
                                                $<?php echo number_format($product['price'], 2); ?> • 
                                                Stock: <?php echo $product['quantity']; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?php echo $product['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($product['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <a href="/ecommerce/vendor/products.php?action=add" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-plus me-2"></i>Add Product
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="/ecommerce/vendor/orders.php" class="btn btn-outline-success w-100">
                                        <i class="fas fa-shopping-bag me-2"></i>Manage Orders
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="/ecommerce/vendor/profile.php" class="btn btn-outline-info w-100">
                                        <i class="fas fa-store me-2"></i>Edit Store
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="/ecommerce/vendor/sales.php" class="btn btn-outline-warning w-100">
                                        <i class="fas fa-chart-line me-2"></i>View Reports
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
