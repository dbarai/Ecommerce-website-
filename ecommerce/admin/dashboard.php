<?php
// admin/dashboard.php
require_once '../includes/auth_check.php';
require_once '../includes/header.php';

// Only admin can access
if (!isAdmin()) {
    redirect('/ecommerce/index.php');
}

// Fetch dashboard statistics
$stats = [];

// Total users
$userStmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
$stats['total_customers'] = $userStmt->fetch()['count'];

// Total vendors
$vendorStmt = $pdo->query("SELECT COUNT(*) as count FROM vendors WHERE status = 'approved'");
$stats['total_vendors'] = $vendorStmt->fetch()['count'];

// Total products
$productStmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE status = 'active'");
$stats['total_products'] = $productStmt->fetch()['count'];

// Total orders
$orderStmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
$stats['total_orders'] = $orderStmt->fetch()['count'];

// Total revenue
$revenueStmt = $pdo->query("SELECT SUM(total) as total FROM orders WHERE order_status = 'delivered'");
$stats['total_revenue'] = $revenueStmt->fetch()['total'] ?? 0;

// Pending vendors
$pendingVendorStmt = $pdo->query("SELECT COUNT(*) as count FROM vendors WHERE status = 'pending'");
$stats['pending_vendors'] = $pendingVendorStmt->fetch()['count'];

// Recent orders
$recentOrdersStmt = $pdo->prepare("
    SELECT o.*, u.username, u.email 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
");
$recentOrdersStmt->execute();
$recentOrders = $recentOrdersStmt->fetchAll();

// Recent products
$recentProductsStmt = $pdo->prepare("
    SELECT p.*, v.shop_name 
    FROM products p 
    LEFT JOIN vendors v ON p.vendor_id = v.id 
    WHERE p.status = 'active' 
    ORDER BY p.created_at DESC 
    LIMIT 10
");
$recentProductsStmt->execute();
$recentProducts = $recentProductsStmt->fetchAll();

// Sales chart data (last 7 days)
$salesData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayStmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as total 
        FROM orders 
        WHERE DATE(created_at) = ? AND order_status = 'delivered'
    ");
    $dayStmt->execute([$date]);
    $dayData = $dayStmt->fetch();
    $salesData[$date] = $dayData['total'];
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-2">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">Admin Panel</h6>
                </div>
                <div class="list-group list-group-flush">
                    <a href="/ecommerce/admin/dashboard.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="/ecommerce/admin/products.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-box me-2"></i>Products
                    </a>
                    <a href="/ecommerce/admin/categories.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tags me-2"></i>Categories
                    </a>
                    <a href="/ecommerce/admin/vendors.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-store me-2"></i>Vendors
                    </a>
                    <a href="/ecommerce/admin/orders.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-shopping-bag me-2"></i>Orders
                    </a>
                    <a href="/ecommerce/admin/coupons.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-ticket-alt me-2"></i>Coupons
                    </a>
                    <a href="/ecommerce/admin/reports.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-bar me-2"></i>Reports
                    </a>
                    <a href="/ecommerce/admin/settings.php" class="list-group-item list-group-item-action">
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
                    <h4 class="fw-bold">Welcome, <?php echo $_SESSION['full_name']; ?>!</h4>
                    <p class="text-muted mb-0">Here's what's happening with your store today.</p>
                </div>
                <div class="text-end">
                    <small class="text-muted"><?php echo date('l, F j, Y'); ?></small>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-primary border-top-0 border-end-0 border-bottom-0 border-5">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Customers</h6>
                                    <h3 class="fw-bold"><?php echo number_format($stats['total_customers']); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x text-primary"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="/ecommerce/admin/customers.php" class="small text-decoration-none">
                                    View Details <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-success border-top-0 border-end-0 border-bottom-0 border-5">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Vendors</h6>
                                    <h3 class="fw-bold"><?php echo number_format($stats['total_vendors']); ?></h3>
                                    <?php if($stats['pending_vendors'] > 0): ?>
                                    <small class="text-warning">
                                        <i class="fas fa-clock me-1"></i><?php echo $stats['pending_vendors']; ?> pending
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-store fa-2x text-success"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="/ecommerce/admin/vendors.php" class="small text-decoration-none">
                                    View Details <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-info border-top-0 border-end-0 border-bottom-0 border-5">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Products</h6>
                                    <h3 class="fw-bold"><?php echo number_format($stats['total_products']); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-box fa-2x text-info"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="/ecommerce/admin/products.php" class="small text-decoration-none">
                                    View Details <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-warning border-top-0 border-end-0 border-bottom-0 border-5">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Orders</h6>
                                    <h3 class="fw-bold"><?php echo number_format($stats['total_orders']); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-shopping-bag fa-2x text-warning"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="/ecommerce/admin/orders.php" class="small text-decoration-none">
                                    View Details <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-danger border-top-0 border-end-0 border-bottom-0 border-5">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Revenue</h6>
                                    <h3 class="fw-bold">$<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-dollar-sign fa-2x text-danger"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="/ecommerce/admin/reports.php" class="small text-decoration-none">
                                    View Details <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Sales Overview (Last 7 Days)</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="salesChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Order Status</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="orderStatusChart" height="250"></canvas>
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
                            <a href="/ecommerce/admin/orders.php" class="btn btn-sm btn-primary">View All</a>
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
                                                <a href="/ecommerce/admin/orders.php?action=view&id=<?php echo $order['id']; ?>" 
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
                                                <a href="/ecommerce/admin/orders.php?action=view&id=<?php echo $order['id']; ?>" 
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
                            <h6 class="mb-0">Recent Products</h6>
                            <a href="/ecommerce/admin/products.php" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach($recentProducts as $product): ?>
                                <a href="/ecommerce/admin/products.php?action=edit&id=<?php echo $product['id']; ?>" 
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
                                                <?php echo htmlspecialchars($product['shop_name']); ?> • 
                                                $<?php echo number_format($product['price'], 2); ?>
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
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Sales Chart
const salesCtx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_keys($salesData)); ?>,
        datasets: [{
            label: 'Sales ($)',
            data: <?php echo json_encode(array_values($salesData)); ?>,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    display: true
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Order Status Chart
const statusCtx = document.getElementById('orderStatusChart').getContext('2d');
const statusChart = new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'],
        datasets: [{
            data: [12, 19, 3, 5, 2], // These should be dynamic data from database
            backgroundColor: [
                '#f59e0b', // pending
                '#3b82f6', // processing
                '#8b5cf6', // shipped
                '#10b981', // delivered
                '#ef4444'  // cancelled
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
