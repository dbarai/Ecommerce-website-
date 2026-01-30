<?php
// vendor/orders.php
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

// Handle order actions
$action = $_GET['action'] ?? '';
$orderId = $_GET['id'] ?? 0;

if ($action === 'update_status' && $orderId) {
    $newStatus = sanitize($_GET['status'] ?? '');
    $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    
    if (in_array($newStatus, $validStatuses)) {
        // Check if order belongs to this vendor
        $checkStmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND vendor_id = ?");
        $checkStmt->execute([$orderId, $vendor['id']]);
        
        if ($checkStmt->rowCount() > 0) {
            $updateStmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
            $updateStmt->execute([$newStatus, $orderId]);
            $success = "Order status updated successfully!";
        } else {
            $error = "Order not found or unauthorized.";
        }
    } else {
        $error = "Invalid order status.";
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bulk_update'])) {
        $selectedOrders = $_POST['selected_orders'] ?? [];
        $bulkStatus = sanitize($_POST['bulk_status']);
        
        if (!empty($selectedOrders) && in_array($bulkStatus, ['processing', 'shipped', 'cancelled'])) {
            $placeholders = str_repeat('?,', count($selectedOrders) - 1) . '?';
            
            // Verify orders belong to this vendor
            $verifyStmt = $pdo->prepare("SELECT id FROM orders WHERE id IN ($placeholders) AND vendor_id = ?");
            $verifyParams = array_merge($selectedOrders, [$vendor['id']]);
            $verifyStmt->execute($verifyParams);
            $validOrders = $verifyStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($validOrders) > 0) {
                $updateStmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE id IN ($placeholders)");
                $updateParams = array_merge([$bulkStatus], $validOrders);
                $updateStmt->execute($updateParams);
                $success = "Updated " . count($validOrders) . " order(s) to " . $bulkStatus . "!";
            }
        }
    }
    
    if (isset($_POST['update_tracking'])) {
        $orderId = intval($_POST['order_id']);
        $trackingNumber = sanitize($_POST['tracking_number']);
        $carrier = sanitize($_POST['carrier']);
        
        // Check if order belongs to this vendor
        $checkStmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND vendor_id = ?");
        $checkStmt->execute([$orderId, $vendor['id']]);
        
        if ($checkStmt->rowCount() > 0) {
            $updateStmt = $pdo->prepare("UPDATE orders SET tracking_number = ?, shipping_carrier = ? WHERE id = ?");
            $updateStmt->execute([$trackingNumber, $carrier, $orderId]);
            $success = "Tracking information updated!";
        }
    }
}

// Fetch orders with filters
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build filter query
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$where = ["o.vendor_id = ?"];
$params = [$vendor['id']];

if ($status && $status !== 'all') {
    $where[] = "o.order_status = ?";
    $params[] = $status;
}

if ($search) {
    $where[] = "(o.order_number LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($dateFrom) {
    $where[] = "DATE(o.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $where[] = "DATE(o.created_at) <= ?";
    $params[] = $dateTo;
}

$whereClause = "WHERE " . implode(" AND ", $where);

// Count total orders
$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    $whereClause
");
$countStmt->execute($params);
$totalOrders = $countStmt->fetch()['total'];
$totalPages = ceil($totalOrders / $limit);

// Fetch orders
$orderStmt = $pdo->prepare("
    SELECT o.*, u.username, u.email, u.phone,
           COUNT(oi.id) as item_count,
           SUM(oi.total) as items_total
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    $whereClause
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
");

$allParams = array_merge($params, [$limit, $offset]);
$orderStmt->execute($allParams);
$orders = $orderStmt->fetchAll();

// Calculate statistics
$stats = [];
$statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

foreach ($statuses as $statusType) {
    $statStmt = $pdo->prepare("
        SELECT COUNT(*) as count, SUM(total) as total 
        FROM orders 
        WHERE vendor_id = ? AND order_status = ?
    ");
    $statStmt->execute([$vendor['id'], $statusType]);
    $statData = $statStmt->fetch();
    $stats[$statusType] = [
        'count' => $statData['count'],
        'total' => $statData['total'] ?? 0
    ];
}

// Today's orders
$todayStmt = $pdo->prepare("
    SELECT COUNT(*) as count, SUM(total) as total 
    FROM orders 
    WHERE vendor_id = ? AND DATE(created_at) = CURDATE()
");
$todayStmt->execute([$vendor['id']]);
$todayStats = $todayStmt->fetch();

// This month's revenue
$monthStmt = $pdo->prepare("
    SELECT SUM(total) as total 
    FROM orders 
    WHERE vendor_id = ? 
      AND MONTH(created_at) = MONTH(CURDATE()) 
      AND YEAR(created_at) = YEAR(CURDATE())
      AND order_status = 'delivered'
");
$monthStmt->execute([$vendor['id']]);
$monthRevenue = $monthStmt->fetch()['total'] ?? 0;
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
                    <a href="/ecommerce/vendor/products.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-box me-2"></i>Products
                    </a>
                    <a href="/ecommerce/vendor/orders.php" class="list-group-item list-group-item-action active">
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
                    <h4 class="fw-bold">Order Management</h4>
                    <p class="text-muted mb-0">Manage and track your orders</p>
                </div>
                <div>
                    <span class="badge bg-success">Today: <?php echo $todayStats['count']; ?> orders</span>
                    <span class="badge bg-primary ms-2">Monthly: $<?php echo number_format($monthRevenue, 2); ?></span>
                </div>
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
            
            <!-- Order Statistics -->
            <div class="row g-3 mb-4">
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-warning border-top-0 border-end-0 border-bottom-0 border-5">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Pending</h6>
                                    <h3 class="fw-bold"><?php echo $stats['pending']['count']; ?></h3>
                                    <small class="text-muted">$<?php echo number_format($stats['pending']['total'], 2); ?></small>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clock fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-info border-top-0 border-end-0 border-bottom-0 border-5">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Processing</h6>
                                    <h3 class="fw-bold"><?php echo $stats['processing']['count']; ?></h3>
                                    <small class="text-muted">$<?php echo number_format($stats['processing']['total'], 2); ?></small>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-cogs fa-2x text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-primary border-top-0 border-end-0 border-bottom-0 border-5">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Shipped</h6>
                                    <h3 class="fw-bold"><?php echo $stats['shipped']['count']; ?></h3>
                                    <small class="text-muted">$<?php echo number_format($stats['shipped']['total'], 2); ?></small>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-shipping-fast fa-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-success border-top-0 border-end-0 border-bottom-0 border-5">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Delivered</h6>
                                    <h3 class="fw-bold"><?php echo $stats['delivered']['count']; ?></h3>
                                    <small class="text-muted">$<?php echo number_format($stats['delivered']['total'], 2); ?></small>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check-circle fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-danger border-top-0 border-end-0 border-bottom-0 border-5">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Cancelled</h6>
                                    <h3 class="fw-bold"><?php echo $stats['cancelled']['count']; ?></h3>
                                    <small class="text-muted">$<?php echo number_format($stats['cancelled']['total'], 2); ?></small>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-times-circle fa-2x text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-2 col-md-4 col-sm-6">
                    <div class="card border-secondary border-top-0 border-end-0 border-bottom-0 border-5">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Total</h6>
                                    <h3 class="fw-bold"><?php echo $totalOrders; ?></h3>
                                    <?php 
                                    $totalRevenue = array_sum(array_column($stats, 'total'));
                                    ?>
                                    <small class="text-muted">$<?php echo number_format($totalRevenue, 2); ?></small>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-shopping-bag fa-2x text-secondary"></i>
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
                            <input type="text" name="search" class="form-control" placeholder="Search orders..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $status == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $status == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $status == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="date_from" class="form-control" 
                                   value="<?php echo htmlspecialchars($dateFrom); ?>"
                                   placeholder="From Date">
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="date_to" class="form-control" 
                                   value="<?php echo htmlspecialchars($dateTo); ?>"
                                   placeholder="To Date">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                        </div>
                        <div class="col-md-1">
                            <a href="/ecommerce/vendor/orders.php" class="btn btn-outline-secondary w-100">
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
                                <option value="processing">Mark as Processing</option>
                                <option value="shipped">Mark as Shipped</option>
                                <option value="delivered">Mark as Delivered</option>
                                <option value="cancelled">Mark as Cancelled</option>
                            </select>
                            <button type="submit" name="bulk_update" class="btn btn-outline-primary">
                                Apply
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAllOrders">
                            <label class="form-check-label" for="selectAllOrders">
                                Select All
                            </label>
                        </div>
                    </div>
                </div>
            </form>
            
            <!-- Orders Table -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="selectAllCheckbox">
                                    </th>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($orders as $order): ?>
                                <tr>
                                    <!-- Checkbox -->
                                    <td>
                                        <input type="checkbox" class="order-checkbox" 
                                               name="selected_orders[]" value="<?php echo $order['id']; ?>">
                                    </td>
                                    
                                    <!-- Order Number -->
                                    <td>
                                        <a href="/ecommerce/vendor/orders.php?action=view&id=<?php echo $order['id']; ?>" 
                                           class="text-decoration-none fw-bold">
                                            <?php echo $order['order_number']; ?>
                                        </a>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                        </small>
                                    </td>
                                    
                                    <!-- Customer -->
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-user-circle fa-lg text-muted"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-2">
                                                <div class="fw-bold"><?php echo htmlspecialchars($order['username']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Date -->
                                    <td>
                                        <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                        <br>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($order['created_at'])); ?></small>
                                    </td>
                                    
                                    <!-- Items -->
                                    <td>
                                        <span class="badge bg-light text-dark"><?php echo $order['item_count']; ?> items</span>
                                        <br>
                                        <small class="text-muted">$<?php echo number_format($order['items_total'], 2); ?></small>
                                    </td>
                                    
                                    <!-- Total -->
                                    <td>
                                        <span class="fw-bold text-primary">$<?php echo number_format($order['total'], 2); ?></span>
                                        <?php if($order['discount'] > 0): ?>
                                        <br>
                                        <small class="text-success">- $<?php echo number_format($order['discount'], 2); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Payment -->
                                    <td>
                                        <span class="badge bg-<?php echo $order['payment_status'] == 'paid' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                        <br>
                                        <small class="text-muted"><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></small>
                                    </td>
                                    
                                    <!-- Status -->
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-<?php 
                                                switch($order['order_status']) {
                                                    case 'pending': echo 'warning'; break;
                                                    case 'processing': echo 'info'; break;
                                                    case 'shipped': echo 'primary'; break;
                                                    case 'delivered': echo 'success'; break;
                                                    case 'cancelled': echo 'danger'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?> dropdown-toggle" 
                                                    type="button" 
                                                    data-bs-toggle="dropdown">
                                                <?php echo ucfirst($order['order_status']); ?>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" 
                                                       href="/ecommerce/vendor/orders.php?action=update_status&id=<?php echo $order['id']; ?>&status=pending">
                                                        <i class="fas fa-clock text-warning me-2"></i>Pending
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" 
                                                       href="/ecommerce/vendor/orders.php?action=update_status&id=<?php echo $order['id']; ?>&status=processing">
                                                        <i class="fas fa-cogs text-info me-2"></i>Processing
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" 
                                                       href="/ecommerce/vendor/orders.php?action=update_status&id=<?php echo $order['id']; ?>&status=shipped">
                                                        <i class="fas fa-shipping-fast text-primary me-2"></i>Shipped
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" 
                                                       href="/ecommerce/vendor/orders.php?action=update_status&id=<?php echo $order['id']; ?>&status=delivered">
                                                        <i class="fas fa-check-circle text-success me-2"></i>Delivered
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" 
                                                       href="/ecommerce/vendor/orders.php?action=update_status&id=<?php echo $order['id']; ?>&status=cancelled">
                                                        <i class="fas fa-times-circle text-danger me-2"></i>Cancelled
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        
                                        <?php if($order['tracking_number']): ?>
                                        <small class="d-block mt-1">
                                            <i class="fas fa-truck"></i> <?php echo substr($order['tracking_number'], 0, 10); ?>...
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Actions -->
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="/ecommerce/vendor/orders.php?action=view&id=<?php echo $order['id']; ?>" 
                                               class="btn btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#trackingModal"
                                                    data-order-id="<?php echo $order['id']; ?>"
                                                    data-tracking="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>"
                                                    data-carrier="<?php echo htmlspecialchars($order['shipping_carrier'] ?? ''); ?>">
                                                <i class="fas fa-truck"></i>
                                            </button>
                                            <a href="/ecommerce/vendor/orders.php?action=print&id=<?php echo $order['id']; ?>" 
                                               target="_blank" class="btn btn-outline-secondary">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Empty State -->
                    <?php if(count($orders) === 0): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                        <h5>No Orders Found</h5>
                        <p class="text-muted">You haven't received any orders yet.</p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Pagination -->
                    <?php if($totalPages > 1): ?>
                    <nav class="px-3 py-3 border-top">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $status ? '&status='.$status : ''; ?>">
                                    Previous
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $status ? '&status='.$status : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                            
                            <?php if($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $status ? '&status='.$status : ''; ?>">
                                    Next
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Order Details Modal (Loaded via AJAX) -->
            <div class="modal fade" id="orderDetailsModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Order Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="orderDetailsContent">
                            <!-- Content loaded via AJAX -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tracking Modal -->
            <div class="modal fade" id="trackingModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST">
                            <div class="modal-header">
                                <h5 class="modal-title">Update Tracking Information</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="order_id" id="modalOrderId">
                                
                                <div class="mb-3">
                                    <label class="form-label">Shipping Carrier</label>
                                    <select name="carrier" class="form-select" id="modalCarrier">
                                        <option value="">Select Carrier</option>
                                        <option value="USPS">USPS</option>
                                        <option value="UPS">UPS</option>
                                        <option value="FedEx">FedEx</option>
                                        <option value="DHL">DHL</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Tracking Number</label>
                                    <input type="text" name="tracking_number" class="form-control" 
                                           id="modalTracking" placeholder="Enter tracking number">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="update_tracking" class="btn btn-primary">Save Tracking</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Select all checkboxes
    $('#selectAllCheckbox').change(function() {
        $('.order-checkbox').prop('checked', $(this).prop('checked'));
    });
    
    $('#selectAllOrders').change(function() {
        $('.order-checkbox').prop('checked', $(this).prop('checked'));
    });
    
    // View order details
    $('a[href*="action=view"]').click(function(e) {
        e.preventDefault();
        var orderId = $(this).attr('href').split('id=')[1];
        
        $.ajax({
            url: '/ecommerce/vendor/ajax/order-details.php?id=' + orderId,
            method: 'GET',
            success: function(response) {
                $('#orderDetailsContent').html(response);
                $('#orderDetailsModal').modal('show');
            }
        });
    });
    
    // Tracking modal
    $('#trackingModal').on('show.bs.modal', function(event) {
        var button = $(event.relatedTarget);
        var orderId = button.data('order-id');
        var tracking = button.data('tracking');
        var carrier = button.data('carrier');
        
        var modal = $(this);
        modal.find('#modalOrderId').val(orderId);
        modal.find('#modalTracking').val(tracking);
        modal.find('#modalCarrier').val(carrier);
    });
    
    // Bulk form submission
    $('#bulkForm').submit(function(e) {
        var selectedCount = $('.order-checkbox:checked').length;
        var bulkAction = $('select[name="bulk_status"]').val();
        
        if (selectedCount === 0) {
            e.preventDefault();
            alert('Please select at least one order.');
            return false;
        }
        
        if (!bulkAction) {
            e.preventDefault();
            alert('Please select a bulk action.');
            return false;
        }
        
        if (!confirm('Apply "' + bulkAction + '" to ' + selectedCount + ' order(s)?')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
