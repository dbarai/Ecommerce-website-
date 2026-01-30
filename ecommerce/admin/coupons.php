<?php
// admin/coupons.php
require_once '../includes/auth_check.php';
require_once '../includes/header.php';

// Only admin can access
if (!isAdmin()) {
    redirect('/ecommerce/index.php');
}

$success = '';
$error = '';

// Handle coupon actions
$action = $_GET['action'] ?? '';
$couponId = $_GET['id'] ?? 0;

// Delete coupon
if ($action === 'delete' && $couponId) {
    $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
    if ($stmt->execute([$couponId])) {
        $success = "Coupon deleted successfully!";
    } else {
        $error = "Error deleting coupon.";
    }
}

// Toggle coupon status
if ($action === 'toggle' && $couponId) {
    $stmt = $pdo->prepare("UPDATE coupons SET is_active = NOT is_active WHERE id = ?");
    if ($stmt->execute([$couponId])) {
        $success = "Coupon status updated!";
    } else {
        $error = "Error updating coupon.";
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_coupon'])) {
        // Add new coupon
        $code = sanitize($_POST['code']);
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $discountType = sanitize($_POST['discount_type']);
        $discountValue = floatval($_POST['discount_value']);
        $minOrderAmount = floatval($_POST['min_order_amount']);
        $maxDiscount = floatval($_POST['max_discount_amount']) ?: null;
        $usageLimit = intval($_POST['usage_limit']) ?: null;
        $perUserLimit = intval($_POST['per_user_limit']) ?: 1;
        $startDate = $_POST['start_date'] ?: null;
        $endDate = $_POST['end_date'] ?: null;
        $appliesTo = sanitize($_POST['applies_to']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Check if coupon code already exists
        $checkStmt = $pdo->prepare("SELECT id FROM coupons WHERE code = ?");
        $checkStmt->execute([$code]);
        
        if ($checkStmt->rowCount() > 0) {
            $error = "Coupon code already exists!";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO coupons (
                        code, name, description, discount_type, discount_value, 
                        min_order_amount, max_discount_amount, usage_limit, 
                        per_user_limit, start_date, end_date, applies_to, is_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $code, $name, $description, $discountType, $discountValue,
                    $minOrderAmount, $maxDiscount, $usageLimit, $perUserLimit,
                    $startDate, $endDate, $appliesTo, $isActive
                ]);
                
                $success = "Coupon added successfully!";
                
            } catch (Exception $e) {
                $error = "Error adding coupon: " . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['edit_coupon'])) {
        // Edit existing coupon
        $code = sanitize($_POST['code']);
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $discountType = sanitize($_POST['discount_type']);
        $discountValue = floatval($_POST['discount_value']);
        $minOrderAmount = floatval($_POST['min_order_amount']);
        $maxDiscount = floatval($_POST['max_discount_amount']) ?: null;
        $usageLimit = intval($_POST['usage_limit']) ?: null;
        $perUserLimit = intval($_POST['per_user_limit']) ?: 1;
        $startDate = $_POST['start_date'] ?: null;
        $endDate = $_POST['end_date'] ?: null;
        $appliesTo = sanitize($_POST['applies_to']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Check if coupon code already exists (excluding current coupon)
        $checkStmt = $pdo->prepare("SELECT id FROM coupons WHERE code = ? AND id != ?");
        $checkStmt->execute([$code, $couponId]);
        
        if ($checkStmt->rowCount() > 0) {
            $error = "Coupon code already exists!";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE coupons SET
                        code = ?, name = ?, description = ?, discount_type = ?, discount_value = ?,
                        min_order_amount = ?, max_discount_amount = ?, usage_limit = ?,
                        per_user_limit = ?, start_date = ?, end_date = ?, applies_to = ?, is_active = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $code, $name, $description, $discountType, $discountValue,
                    $minOrderAmount, $maxDiscount, $usageLimit, $perUserLimit,
                    $startDate, $endDate, $appliesTo, $isActive, $couponId
                ]);
                
                $success = "Coupon updated successfully!";
                
            } catch (Exception $e) {
                $error = "Error updating coupon: " . $e->getMessage();
            }
        }
    }
}

// Fetch coupons with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build search query
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(code LIKE ? OR name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status === 'active') {
    $where[] = "is_active = 1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE())";
} elseif ($status === 'inactive') {
    $where[] = "is_active = 0";
} elseif ($status === 'expired') {
    $where[] = "end_date < CURDATE()";
}

if ($type) {
    $where[] = "discount_type = ?";
    $params[] = $type;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Count total coupons
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM coupons $whereClause");
$countStmt->execute($params);
$totalCoupons = $countStmt->fetch()['total'];
$totalPages = ceil($totalCoupons / $limit);

// Fetch coupons
$couponStmt = $pdo->prepare("
    SELECT * FROM coupons 
    $whereClause 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");

$allParams = array_merge($params, [$limit, $offset]);
$couponStmt->execute($allParams);
$coupons = $couponStmt->fetchAll();

// If editing, fetch coupon data
$editCoupon = null;
if ($action === 'edit' && $couponId) {
    $editStmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ?");
    $editStmt->execute([$couponId]);
    $editCoupon = $editStmt->fetch();
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
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold">Coupon Management</h4>
                    <p class="text-muted mb-0">Create and manage discount coupons</p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCouponModal">
                    <i class="fas fa-plus me-2"></i>Add New Coupon
                </button>
            </div>
            
            <!-- Messages -->
            <?php if($success): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if($error): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="Search coupons..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="expired" <?php echo $status == 'expired' ? 'selected' : ''; ?>>Expired</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="type" class="form-select">
                                <option value="">All Types</option>
                                <option value="percentage" <?php echo $type == 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                                <option value="fixed" <?php echo $type == 'fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                                <option value="free_shipping" <?php echo $type == 'free_shipping' ? 'selected' : ''; ?>>Free Shipping</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Coupons Table -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Coupon Code</th>
                                    <th>Name</th>
                                    <th>Discount</th>
                                    <th>Usage</th>
                                    <th>Validity</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($coupons as $coupon): 
                                    $isExpired = $coupon['end_date'] && strtotime($coupon['end_date']) < time();
                                    $isActive = $coupon['is_active'] && !$isExpired;
                                ?>
                                <tr>
                                    <!-- Coupon Code -->
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="coupon-code bg-light border rounded p-2">
                                                <code class="fw-bold"><?php echo htmlspecialchars($coupon['code']); ?></code>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Name -->
                                    <td>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($coupon['name']); ?></h6>
                                        <small class="text-muted"><?php echo substr(htmlspecialchars($coupon['description']), 0, 50); ?>...</small>
                                    </td>
                                    
                                    <!-- Discount -->
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php 
                                            if ($coupon['discount_type'] === 'percentage') {
                                                echo $coupon['discount_value'] . '%';
                                            } elseif ($coupon['discount_type'] === 'fixed') {
                                                echo '$' . number_format($coupon['discount_value'], 2);
                                            } else {
                                                echo 'Free Shipping';
                                            }
                                            ?>
                                        </span>
                                        <?php if($coupon['min_order_amount'] > 0): ?>
                                        <br><small class="text-muted">Min: $<?php echo number_format($coupon['min_order_amount'], 2); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Usage -->
                                    <td>
                                        <?php if($coupon['usage_limit']): ?>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?php echo min(100, ($coupon['used_count'] / $coupon['usage_limit']) * 100); ?>%">
                                            </div>
                                        </div>
                                        <small>
                                            <?php echo $coupon['used_count']; ?>/<?php echo $coupon['usage_limit']; ?> used
                                        </small>
                                        <?php else: ?>
                                        <small><?php echo $coupon['used_count']; ?> used</small>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Validity -->
                                    <td>
                                        <?php if($coupon['start_date'] || $coupon['end_date']): ?>
                                        <small class="d-block">
                                            <?php if($coupon['start_date']): ?>
                                            From: <?php echo date('M d, Y', strtotime($coupon['start_date'])); ?>
                                            <?php endif; ?>
                                        </small>
                                        <small class="d-block">
                                            <?php if($coupon['end_date']): ?>
                                            To: <?php echo date('M d, Y', strtotime($coupon['end_date'])); ?>
                                            <?php endif; ?>
                                        </small>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">No expiry</span>
                                        <?php endif; ?>
                                        
                                        <?php if($isExpired): ?>
                                        <span class="badge bg-danger mt-1">Expired</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Status -->
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input coupon-toggle" 
                                                   type="checkbox" 
                                                   data-coupon-id="<?php echo $coupon['id']; ?>"
                                                   <?php echo $isActive ? 'checked' : ''; ?>
                                                   <?php echo $isExpired ? 'disabled' : ''; ?>>
                                        </div>
                                    </td>
                                    
                                    <!-- Actions -->
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="/ecommerce/admin/coupons.php?action=edit&id=<?php echo $coupon['id']; ?>" 
                                               class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCouponModal">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="/ecommerce/admin/coupons.php?action=delete&id=<?php echo $coupon['id']; ?>" 
                                               class="btn btn-outline-danger" 
                                               onclick="return confirm('Are you sure you want to delete this coupon?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" 
                                                    data-bs-toggle="dropdown">
                                                <i class="fas fa-cog"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#">
                                                        <i class="fas fa-copy me-2"></i>Duplicate
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#">
                                                        <i class="fas fa-chart-bar me-2"></i>Usage Report
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" 
                                                       href="/ecommerce/admin/coupons.php?action=delete&id=<?php echo $coupon['id']; ?>"
                                                       onclick="return confirm('Are you sure?')">
                                                        <i class="fas fa-trash me-2"></i>Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Empty State -->
                    <?php if(count($coupons) === 0): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                        <h5>No Coupons Found</h5>
                        <p class="text-muted">Create your first coupon to offer discounts to customers.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCouponModal">
                            <i class="fas fa-plus me-2"></i>Add New Coupon
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Pagination -->
                    <?php if($totalPages > 1): ?>
                    <nav class="px-3 py-3 border-top">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $status ? '&status='.$status : ''; ?><?php echo $type ? '&type='.$type : ''; ?>">
                                    Previous
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $status ? '&status='.$status : ''; ?><?php echo $type ? '&type='.$type : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                            
                            <?php if($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $status ? '&status='.$status : ''; ?><?php echo $type ? '&type='.$type : ''; ?>">
                                    Next
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Total Coupons</h6>
                                    <h3 class="fw-bold"><?php echo $totalCoupons; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-ticket-alt fa-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Active Coupons</h6>
                                    <?php 
                                    $activeStmt = $pdo->query("SELECT COUNT(*) as count FROM coupons WHERE is_active = 1 AND (end_date IS NULL OR end_date >= CURDATE())");
                                    $activeCount = $activeStmt->fetch()['count'];
                                    ?>
                                    <h3 class="fw-bold"><?php echo $activeCount; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check-circle fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Total Usage</h6>
                                    <?php 
                                    $usageStmt = $pdo->query("SELECT SUM(used_count) as total FROM coupons");
                                    $totalUsage = $usageStmt->fetch()['total'] ?? 0;
                                    ?>
                                    <h3 class="fw-bold"><?php echo $totalUsage; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-chart-line fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted mb-2">Total Discount</h6>
                                    <?php 
                                    $discountStmt = $pdo->query("
                                        SELECT SUM(discount_value) as total 
                                        FROM coupons 
                                        WHERE discount_type = 'fixed'
                                    ");
                                    $totalDiscount = $discountStmt->fetch()['total'] ?? 0;
                                    ?>
                                    <h3 class="fw-bold">$<?php echo number_format($totalDiscount, 2); ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-dollar-sign fa-2x text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Coupon Modal -->
<div class="modal fade" id="addCouponModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?php echo $action === 'edit' ? 'Edit Coupon' : 'Add New Coupon'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Coupon Code -->
                        <div class="col-md-6">
                            <label class="form-label">Coupon Code *</label>
                            <div class="input-group">
                                <input type="text" name="code" class="form-control" 
                                       value="<?php echo $editCoupon ? htmlspecialchars($editCoupon['code']) : generateCouponCode(); ?>" 
                                       required>
                                <button type="button" class="btn btn-outline-secondary" id="generateCodeBtn">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <small class="text-muted">Customers will enter this code at checkout</small>
                        </div>
                        
                        <!-- Coupon Name -->
                        <div class="col-md-6">
                            <label class="form-label">Coupon Name *</label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?php echo $editCoupon ? htmlspecialchars($editCoupon['name']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <!-- Description -->
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"><?php echo $editCoupon ? htmlspecialchars($editCoupon['description']) : ''; ?></textarea>
                        </div>
                        
                        <!-- Discount Type -->
                        <div class="col-md-6">
                            <label class="form-label">Discount Type *</label>
                            <select name="discount_type" class="form-select" id="discountType" required>
                                <option value="percentage" <?php echo ($editCoupon && $editCoupon['discount_type'] == 'percentage') ? 'selected' : ''; ?>>Percentage</option>
                                <option value="fixed" <?php echo ($editCoupon && $editCoupon['discount_type'] == 'fixed') ? 'selected' : ''; ?>>Fixed Amount</option>
                                <option value="free_shipping" <?php echo ($editCoupon && $editCoupon['discount_type'] == 'free_shipping') ? 'selected' : ''; ?>>Free Shipping</option>
                            </select>
                        </div>
                        
                        <!-- Discount Value -->
                        <div class="col-md-6">
                            <label class="form-label" id="discountValueLabel">
                                <?php echo ($editCoupon && $editCoupon['discount_type'] == 'percentage') ? 'Discount Percentage (%)' : 'Discount Amount ($)'; ?>
                            </label>
                            <input type="number" name="discount_value" class="form-control" 
                                   value="<?php echo $editCoupon ? $editCoupon['discount_value'] : '10'; ?>" 
                                   step="0.01" min="0" required>
                        </div>
                        
                        <!-- Maximum Discount (for percentage) -->
                        <div class="col-md-6" id="maxDiscountField" style="<?php echo ($editCoupon && $editCoupon['discount_type'] == 'percentage') ? '' : 'display: none;'; ?>">
                            <label class="form-label">Maximum Discount ($)</label>
                            <input type="number" name="max_discount_amount" class="form-control" 
                                   value="<?php echo $editCoupon ? $editCoupon['max_discount_amount'] : ''; ?>" 
                                   step="0.01" min="0">
                            <small class="text-muted">Leave empty for no maximum</small>
                        </div>
                        
                        <!-- Minimum Order Amount -->
                        <div class="col-md-6">
                            <label class="form-label">Minimum Order Amount ($)</label>
                            <input type="number" name="min_order_amount" class="form-control" 
                                   value="<?php echo $editCoupon ? $editCoupon['min_order_amount'] : '0'; ?>" 
                                   step="0.01" min="0">
                        </div>
                        
                        <!-- Usage Limit -->
                        <div class="col-md-6">
                            <label class="form-label">Usage Limit</label>
                            <input type="number" name="usage_limit" class="form-control" 
                                   value="<?php echo $editCoupon ? $editCoupon['usage_limit'] : ''; ?>" 
                                   min="0">
                            <small class="text-muted">Leave empty for unlimited usage</small>
                        </div>
                        
                        <!-- Per User Limit -->
                        <div class="col-md-6">
                            <label class="form-label">Uses Per Customer</label>
                            <input type="number" name="per_user_limit" class="form-control" 
                                   value="<?php echo $editCoupon ? $editCoupon['per_user_limit'] : '1'; ?>" 
                                   min="1">
                        </div>
                        
                        <!-- Start Date -->
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" 
                                   value="<?php echo $editCoupon ? $editCoupon['start_date'] : ''; ?>">
                            <small class="text-muted">Leave empty for immediate start</small>
                        </div>
                        
                        <!-- End Date -->
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" 
                                   value="<?php echo $editCoupon ? $editCoupon['end_date'] : ''; ?>">
                            <small class="text-muted">Leave empty for no expiry</small>
                        </div>
                        
                        <!-- Applies To -->
                        <div class="col-md-6">
                            <label class="form-label">Applies To</label>
                            <select name="applies_to" class="form-select">
                                <option value="all" <?php echo ($editCoupon && $editCoupon['applies_to'] == 'all') ? 'selected' : ''; ?>>All Products</option>
                                <option value="categories" <?php echo ($editCoupon && $editCoupon['applies_to'] == 'categories') ? 'selected' : ''; ?>>Specific Categories</option>
                                <option value="products" <?php echo ($editCoupon && $editCoupon['applies_to'] == 'products') ? 'selected' : ''; ?>>Specific Products</option>
                                <option value="vendors" <?php echo ($editCoupon && $editCoupon['applies_to'] == 'vendors') ? 'selected' : ''; ?>>Specific Vendors</option>
                            </select>
                        </div>
                        
                        <!-- Status -->
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                       <?php echo !$editCoupon || $editCoupon['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                        
                        <!-- Hidden field for edit -->
                        <?php if($action === 'edit'): ?>
                        <input type="hidden" name="coupon_id" value="<?php echo $couponId; ?>">
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="<?php echo $action === 'edit' ? 'edit_coupon' : 'add_coupon'; ?>" class="btn btn-primary">
                        <?php echo $action === 'edit' ? 'Update Coupon' : 'Create Coupon'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
// Helper function to generate coupon code
function generateCouponCode($length = 8) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}
?>

<script>
$(document).ready(function() {
    // Generate coupon code
    $('#generateCodeBtn').click(function() {
        $.ajax({
            url: '/ecommerce/api/coupons.php?action=generate',
            method: 'GET',
            success: function(response) {
                var result = JSON.parse(response);
                if (result.success) {
                    $('input[name="code"]').val(result.code);
                }
            }
        });
    });
    
    // Toggle coupon status
    $('.coupon-toggle').change(function() {
        var couponId = $(this).data('coupon-id');
        var isActive = $(this).is(':checked') ? 1 : 0;
        
        $.ajax({
            url: '/ecommerce/admin/coupons.php?action=toggle&id=' + couponId,
            method: 'GET',
            success: function() {
                // Success - do nothing
            }
        });
    });
    
    // Show/hide max discount field based on discount type
    $('#discountType').change(function() {
        var type = $(this).val();
        
        if (type === 'percentage') {
            $('#maxDiscountField').show();
            $('#discountValueLabel').text('Discount Percentage (%)');
            $('input[name="discount_value"]').attr('max', '100');
        } else {
            $('#maxDiscountField').hide();
            if (type === 'fixed') {
                $('#discountValueLabel').text('Discount Amount ($)');
            } else {
                $('#discountValueLabel').text('Free Shipping');
            }
            $('input[name="discount_value"]').removeAttr('max');
        }
    });
    
    // If editing, show modal
    <?php if($action === 'edit' && $editCoupon): ?>
    $(window).on('load', function() {
        $('#addCouponModal').modal('show');
    });
    <?php endif; ?>
    
    // Form validation
    $('form').submit(function(e) {
        var discountType = $('#discountType').val();
        var discountValue = parseFloat($('input[name="discount_value"]').val());
        
        if (discountType === 'percentage' && (discountValue < 0 || discountValue > 100)) {
            e.preventDefault();
            alert('Discount percentage must be between 0 and 100.');
            return false;
        }
        
        if (discountType === 'fixed' && discountValue < 0) {
            e.preventDefault();
            alert('Discount amount cannot be negative.');
            return false;
        }
        
        // Check end date is after start date
        var startDate = $('input[name="start_date"]').val();
        var endDate = $('input[name="end_date"]').val();
        
        if (startDate && endDate && new Date(endDate) < new Date(startDate)) {
            e.preventDefault();
            alert('End date must be after start date.');
            return false;
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
