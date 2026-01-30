<?php
// admin/vendors.php
require_once '../includes/auth_check.php';

// Only admin can access
if (!isAdmin()) {
    header("Location: ../index.php");
    exit();
}

// Handle actions
$action = $_GET['action'] ?? '';
$vendorId = $_GET['id'] ?? 0;
$message = '';
$error = '';

// Handle vendor actions
if ($action === 'approve' && $vendorId) {
    $stmt = $pdo->prepare("UPDATE vendors SET status = 'approved' WHERE id = ?");
    if ($stmt->execute([$vendorId])) {
        // Update user role to vendor if not already
        $userStmt = $pdo->prepare("
            UPDATE users u 
            JOIN vendors v ON u.id = v.user_id 
            SET u.role = 'vendor' 
            WHERE v.id = ?
        ");
        $userStmt->execute([$vendorId]);
        $message = "Vendor approved successfully!";
    }
}

if ($action === 'suspend' && $vendorId) {
    $stmt = $pdo->prepare("UPDATE vendors SET status = 'suspended' WHERE id = ?");
    if ($stmt->execute([$vendorId])) {
        $message = "Vendor suspended!";
    }
}

if ($action === 'delete' && $vendorId) {
    // Check if vendor has products or orders
    $checkProducts = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE vendor_id = ?");
    $checkProducts->execute([$vendorId]);
    $productCount = $checkProducts->fetch()['count'];
    
    $checkOrders = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE vendor_id = ?");
    $checkOrders->execute([$vendorId]);
    $orderCount = $checkOrders->fetch()['count'];
    
    if ($productCount > 0 || $orderCount > 0) {
        $error = "Cannot delete vendor with existing products or orders!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM vendors WHERE id = ?");
        if ($stmt->execute([$vendorId])) {
            $message = "Vendor deleted successfully!";
        }
    }
}

// Handle form submission for vendor details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_vendor'])) {
    $shopName = sanitize($_POST['shop_name']);
    $shopDescription = sanitize($_POST['shop_description']);
    $commissionRate = floatval($_POST['commission_rate']);
    $status = sanitize($_POST['status']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $state = sanitize($_POST['state']);
    $country = sanitize($_POST['country']);
    $postalCode = sanitize($_POST['postal_code']);
    $taxId = sanitize($_POST['tax_id']);
    
    $stmt = $pdo->prepare("
        UPDATE vendors SET 
            shop_name = ?, shop_description = ?, commission_rate = ?, 
            status = ?, phone = ?, address = ?, city = ?, state = ?, 
            country = ?, postal_code = ?, tax_id = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $shopName, $shopDescription, $commissionRate, $status,
        $phone, $address, $city, $state, $country, $postalCode, $taxId, $vendorId
    ]);
    $message = "Vendor details updated!";
}

// Handle payout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payout'])) {
    $amount = floatval($_POST['amount']);
    $method = sanitize($_POST['method']);
    $notes = sanitize($_POST['notes']);
    
    if ($amount > 0) {
        // Create payout record
        $payoutStmt = $pdo->prepare("
            INSERT INTO vendor_payouts (vendor_id, amount, method, notes, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $payoutStmt->execute([$vendorId, $amount, $method, $notes]);
        
        // Update vendor balance
        $balanceStmt = $pdo->prepare("UPDATE vendors SET balance = balance - ? WHERE id = ?");
        $balanceStmt->execute([$amount, $vendorId]);
        
        $message = "Payout of $" . number_format($amount, 2) . " processed!";
    }
}

// Fetch filters
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(v.shop_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter) {
    $where[] = "v.status = ?";
    $params[] = $statusFilter;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Count total
$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM vendors v
    LEFT JOIN users u ON v.user_id = u.id
    $whereClause
");
$countStmt->execute($params);
$totalVendors = $countStmt->fetch()['total'];
$totalPages = ceil($totalVendors / $limit);

// Fetch vendors
$vendorStmt = $pdo->prepare("
    SELECT v.*, u.username, u.email, u.full_name, u.phone as user_phone,
           (SELECT COUNT(*) FROM products WHERE vendor_id = v.id) as product_count,
           (SELECT COUNT(*) FROM orders WHERE vendor_id = v.id) as order_count,
           (SELECT SUM(total) FROM orders WHERE vendor_id = v.id AND order_status = 'delivered') as total_sales
    FROM vendors v
    LEFT JOIN users u ON v.user_id = u.id
    $whereClause
    ORDER BY v.created_at DESC
    LIMIT ? OFFSET ?
");

$allParams = array_merge($params, [$limit, $offset]);
$vendorStmt->execute($allParams);
$vendors = $vendorStmt->fetchAll();

// Fetch vendor details for editing
$vendorDetails = null;
if ($vendorId) {
    $detailsStmt = $pdo->prepare("
        SELECT v.*, u.username, u.email, u.full_name, u.created_at as user_created
        FROM vendors v
        LEFT JOIN users u ON v.user_id = u.id
        WHERE v.id = ?
    ");
    $detailsStmt->execute([$vendorId]);
    $vendorDetails = $detailsStmt->fetch();
    
    // Fetch vendor products
    $productsStmt = $pdo->prepare("
        SELECT p.*, 
               (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
        FROM products p
        WHERE p.vendor_id = ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $productsStmt->execute([$vendorId]);
    $vendorProducts = $productsStmt->fetchAll();
    
    // Fetch vendor orders
    $ordersStmt = $pdo->prepare("
        SELECT o.*, u.username
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.vendor_id = ?
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $ordersStmt->execute([$vendorId]);
    $vendorOrders = $ordersStmt->fetchAll();
    
    // Fetch payout history
    $payoutsStmt = $pdo->prepare("
        SELECT * FROM vendor_payouts 
        WHERE vendor_id = ? 
        ORDER BY created_at DESC
    ");
    $payoutsStmt->execute([$vendorId]);
    $vendorPayouts = $payoutsStmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Vendors - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(180deg, #2c3e50 0%, #1a1a2e 100%);
            color: white;
            padding: 0;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 0;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .sidebar-menu .nav-link:hover,
        .sidebar-menu .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            border-left-color: #dc3545;
        }
        
        .sidebar-menu .nav-link i {
            width: 24px;
            text-align: center;
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .vendor-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        
        .vendor-card:hover {
            transform: translateY(-2px);
        }
        
        .vendor-avatar {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }
        
        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        
        .stats-value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4 class="mb-0"><i class="fas fa-user-shield me-2"></i>Admin Panel</h4>
        </div>
        <div class="sidebar-menu">
            <?php include 'admin-sidebar.php'; ?>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="fw-bold mb-1">Manage Vendors</h2>
                    <p class="text-muted mb-0">Total <?php echo number_format($totalVendors); ?> vendors in system</p>
                </div>
                <div class="text-end">
                    <?php
                    $pendingCount = $pdo->query("SELECT COUNT(*) as count FROM vendors WHERE status = 'pending'")->fetch()['count'];
                    if ($pendingCount > 0): ?>
                    <span class="badge bg-warning me-2">
                        <i class="fas fa-clock me-1"></i><?php echo $pendingCount; ?> pending approval
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if($action === 'view' && $vendorDetails): ?>
        <!-- Vendor Details View -->
        <div class="row">
            <!-- Vendor Profile -->
            <div class="col-lg-4">
                <div class="vendor-card">
                    <div class="text-center mb-4">
                        <div class="vendor-avatar mx-auto mb-3">
                            <i class="fas fa-store"></i>
                        </div>
                        <h4 class="fw-bold"><?php echo htmlspecialchars($vendorDetails['shop_name']); ?></h4>
                        <span class="badge-status bg-<?php 
                            switch($vendorDetails['status']) {
                                case 'approved': echo 'success'; break;
                                case 'pending': echo 'warning'; break;
                                case 'suspended': echo 'danger'; break;
                                default: echo 'secondary';
                            }
                        ?>"><?php echo ucfirst($vendorDetails['status']); ?></span>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="fw-bold mb-3">Owner Information</h6>
                        <div class="mb-2">
                            <i class="fas fa-user me-2 text-muted"></i>
                            <span><?php echo htmlspecialchars($vendorDetails['full_name']); ?></span>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-envelope me-2 text-muted"></i>
                            <span><?php echo htmlspecialchars($vendorDetails['email']); ?></span>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-calendar me-2 text-muted"></i>
                            <span>Joined: <?php echo date('M d, Y', strtotime($vendorDetails['user_created'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="fw-bold mb-3">Contact Information</h6>
                        <div class="mb-2">
                            <i class="fas fa-phone me-2 text-muted"></i>
                            <span><?php echo htmlspecialchars($vendorDetails['phone'] ?: 'Not provided'); ?></span>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-map-marker-alt me-2 text-muted"></i>
                            <span><?php echo htmlspecialchars($vendorDetails['address'] ?: 'Not provided'); ?></span>
                        </div>
                        <div>
                            <i class="fas fa-id-card me-2 text-muted"></i>
                            <span>Tax ID: <?php echo htmlspecialchars($vendorDetails['tax_id'] ?: 'Not provided'); ?></span>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="fw-bold mb-3">Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <?php if($vendorDetails['status'] === 'pending'): ?>
                            <a href="vendors.php?action=approve&id=<?php echo $vendorId; ?>" 
                               class="btn btn-success">
                                <i class="fas fa-check me-2"></i>Approve Vendor
                            </a>
                            <?php endif; ?>
                            
                            <?php if($vendorDetails['status'] === 'approved'): ?>
                            <a href="vendors.php?action=suspend&id=<?php echo $vendorId; ?>" 
                               class="btn btn-warning">
                                <i class="fas fa-pause me-2"></i>Suspend Vendor
                            </a>
                            <?php endif; ?>
                            
                            <?php if($vendorDetails['status'] === 'suspended'): ?>
                            <a href="vendors.php?action=approve&id=<?php echo $vendorId; ?>" 
                               class="btn btn-success">
                                <i class="fas fa-play me-2"></i>Activate Vendor
                            </a>
                            <?php endif; ?>
                            
                            <a href="vendors.php?action=delete&id=<?php echo $vendorId; ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('Are you sure? This will delete the vendor account permanently.')">
                                <i class="fas fa-trash me-2"></i>Delete Vendor
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Commission Settings -->
                <div class="vendor-card">
                    <h6 class="fw-bold mb-3">Commission Settings</h6>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Commission Rate (%)</label>
                            <input type="number" name="commission_rate" class="form-control" step="0.01" min="0" max="50"
                                   value="<?php echo $vendorDetails['commission_rate']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Balance</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" class="form-control" 
                                       value="<?php echo number_format($vendorDetails['balance'], 2); ?>" readonly>
                            </div>
                        </div>
                        <button type="submit" name="update_vendor" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Update Settings
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Vendor Statistics & Details -->
            <div class="col-lg-8">
                <!-- Statistics -->
                <div class="row mb-4 g-3">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-value text-primary">
                                <?php echo number_format($vendorDetails['product_count'] ?? 0); ?>
                            </div>
                            <div class="stats-label">Products</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-value text-success">
                                <?php echo number_format($vendorDetails['order_count'] ?? 0); ?>
                            </div>
                            <div class="stats-label">Total Orders</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-value text-warning">
                                $<?php echo number_format($vendorDetails['total_sales'] ?? 0, 2); ?>
                            </div>
                            <div class="stats-label">Total Sales</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-value text-info">
                                <?php echo number_format($vendorDetails['rating'] ?? 0, 1); ?>/5
                            </div>
                            <div class="stats-label">Rating</div>
                        </div>
                    </div>
                </div>
                
                <!-- Edit Form -->
                <div class="vendor-card">
                    <h5 class="fw-bold mb-4">Edit Vendor Details</h5>
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Shop Name *</label>
                                <input type="text" name="shop_name" class="form-control" required
                                       value="<?php echo htmlspecialchars($vendorDetails['shop_name']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="pending" <?php echo $vendorDetails['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $vendorDetails['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="suspended" <?php echo $vendorDetails['status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Shop Description</label>
                                <textarea name="shop_description" class="form-control" rows="3"><?php echo htmlspecialchars($vendorDetails['shop_description']); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control"
                                       value="<?php echo htmlspecialchars($vendorDetails['phone']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tax ID</label>
                                <input type="text" name="tax_id" class="form-control"
                                       value="<?php echo htmlspecialchars($vendorDetails['tax_id']); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <input type="text" name="address" class="form-control"
                                       value="<?php echo htmlspecialchars($vendorDetails['address']); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control"
                                       value="<?php echo htmlspecialchars($vendorDetails['city']); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">State</label>
                                <input type="text" name="state" class="form-control"
                                       value="<?php echo htmlspecialchars($vendorDetails['state']); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Country</label>
                                <input type="text" name="country" class="form-control"
                                       value="<?php echo htmlspecialchars($vendorDetails['country']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Postal Code</label>
                                <input type="text" name="postal_code" class="form-control"
                                       value="<?php echo htmlspecialchars($vendorDetails['postal_code']); ?>">
                            </div>
                            <div class="col-12 mt-3">
                                <button type="submit" name="update_vendor" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Vendor Details
                                </button>
                                <a href="vendors.php" class="btn btn-outline-secondary ms-2">Back to List</a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Recent Products -->
                <div class="vendor-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">Recent Products</h6>
                        <a href="products.php?vendor=<?php echo $vendorId; ?>" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($vendorProducts)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-3">No products found</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach($vendorProducts as $product): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="../<?php echo $product['image'] ?: 'assets/images/placeholder.jpg'; ?>" 
                                                     alt="Product" 
                                                     class="rounded me-2"
                                                     style="width: 30px; height: 30px; object-fit: cover;">
                                                <span><?php echo htmlspecialchars(substr($product['name'], 0, 30)); ?></span>
                                            </div>
                                        </td>
                                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                                        <td><?php echo $product['quantity']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $product['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($product['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Payout Management -->
                <div class="vendor-card">
                    <h6 class="fw-bold mb-3">Payout Management</h6>
                    
                    <!-- Process Payout Form -->
                    <form method="POST" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Amount ($)</label>
                                <input type="number" name="amount" class="form-control" step="0.01" min="0.01" 
                                       max="<?php echo $vendorDetails['balance']; ?>" required>
                                <small class="text-muted">Available: $<?php echo number_format($vendorDetails['balance'], 2); ?></small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Payment Method</label>
                                <select name="method" class="form-select" required>
                                    <option value="">Select Method</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="paypal">PayPal</option>
                                    <option value="check">Check</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" class="form-control" placeholder="Payment reference">
                            </div>
                            <div class="col-12">
                                <button type="submit" name="process_payout" class="btn btn-success">
                                    <i class="fas fa-money-bill-wave me-2"></i>Process Payout
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Payout History -->
                    <h6 class="fw-bold mb-3">Payout History</h6>
                    <?php if(empty($vendorPayouts)): ?>
                    <p class="text-muted">No payout history</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($vendorPayouts as $payout): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($payout['created_at'])); ?></td>
                                    <td class="fw-bold">$<?php echo number_format($payout['amount'], 2); ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $payout['method'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $payout['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($payout['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($payout['notes']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Vendors List -->
        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Search vendors..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $statusFilter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="suspended" <?php echo $statusFilter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="vendors.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Vendors Grid -->
        <div class="row">
            <?php if(empty($vendors)): ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-store fa-3x text-muted mb-3"></i>
                    <h5>No vendors found</h5>
                    <p class="text-muted">Try changing your search criteria</p>
                </div>
            </div>
            <?php else: ?>
                <?php foreach($vendors as $vendor): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="vendor-card">
                        <div class="d-flex align-items-start">
                            <div class="vendor-avatar me-3">
                                <i class="fas fa-store"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="fw-bold mb-1">
                                    <a href="vendors.php?action=view&id=<?php echo $vendor['id']; ?>" 
                                       class="text-decoration-none">
                                        <?php echo htmlspecialchars($vendor['shop_name']); ?>
                                    </a>
                                </h5>
                                <div class="mb-2">
                                    <span class="badge-status bg-<?php 
                                        switch($vendor['status']) {
                                            case 'approved': echo 'success'; break;
                                            case 'pending': echo 'warning'; break;
                                            case 'suspended': echo 'danger'; break;
                                            default: echo 'secondary';
                                        }
                                    ?>"><?php echo ucfirst($vendor['status']); ?></span>
                                    <span class="badge bg-primary ms-1">Comm: <?php echo $vendor['commission_rate']; ?>%</span>
                                </div>
                                <div class="small text-muted mb-2">
                                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($vendor['full_name']); ?>
                                </div>
                                <div class="small text-muted">
                                    <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($vendor['email']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3 g-2">
                            <div class="col-6">
                                <div class="text-center p-2 border rounded">
                                    <div class="fw-bold text-primary"><?php echo number_format($vendor['product_count']); ?></div>
                                    <small class="text-muted">Products</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-2 border rounded">
                                    <div class="fw-bold text-success"><?php echo number_format($vendor['order_count']); ?></div>
                                    <small class="text-muted">Orders</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-2 border rounded">
                                    <div class="fw-bold text-warning">$<?php echo number_format($vendor['total_sales'] ?? 0, 2); ?></div>
                                    <small class="text-muted">Sales</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-2 border rounded">
                                    <div class="fw-bold text-info">$<?php echo number_format($vendor['balance'], 2); ?></div>
                                    <small class="text-muted">Balance</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3 pt-3 border-top">
                            <div class="d-flex justify-content-between">
                                <a href="vendors.php?action=view&id=<?php echo $vendor['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i>View
                                </a>
                                <?php if($vendor['status'] === 'pending'): ?>
                                <a href="vendors.php?action=approve&id=<?php echo $vendor['id']; ?>" 
                                   class="btn btn-sm btn-success">
                                    <i class="fas fa-check me-1"></i>Approve
                                </a>
                                <?php elseif($vendor['status'] === 'approved'): ?>
                                <a href="vendors.php?action=suspend&id=<?php echo $vendor['id']; ?>" 
                                   class="btn btn-sm btn-warning">
                                    <i class="fas fa-pause me-1"></i>Suspend
                                </a>
                                <?php else: ?>
                                <a href="vendors.php?action=approve&id=<?php echo $vendor['id']; ?>" 
                                   class="btn btn-sm btn-success">
                                    <i class="fas fa-play me-1"></i>Activate
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" 
                       href="?page=<?php echo $page-1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $statusFilter ? '&status='.$statusFilter : ''; ?>">
                        Previous
                    </a>
                </li>
                <?php endif; ?>
                
                <?php for($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" 
                       href="?page=<?php echo $i; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $statusFilter ? '&status='.$statusFilter : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" 
                       href="?page=<?php echo $page+1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $statusFilter ? '&status='.$statusFilter : ''; ?>">
                        Next
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
