<?php
// profile.php
require_once 'includes/auth_check.php';
require_once 'includes/header.php';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitize($_POST['full_name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    
    // Update basic info
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?");
    $stmt->execute([$fullName, $phone, $address, $_SESSION['user_id']]);
    
    // Update password if provided
    if (!empty($currentPassword) && !empty($newPassword)) {
        $userStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $userStmt->execute([$_SESSION['user_id']]);
        $user = $userStmt->fetch();
        
        if (password_verify($currentPassword, $user['password'])) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $passStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $passStmt->execute([$hashedPassword, $_SESSION['user_id']]);
            $success = "Password updated successfully!";
        } else {
            $error = "Current password is incorrect!";
        }
    }
    
    $success = "Profile updated successfully!";
    $_SESSION['full_name'] = $fullName;
}

// Fetch user data
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$user = $userStmt->fetch();

// If vendor, fetch vendor data
$vendor = null;
if ($_SESSION['role'] === 'vendor') {
    $vendorStmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
    $vendorStmt->execute([$_SESSION['user_id']]);
    $vendor = $vendorStmt->fetch();
}
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-3">
            <!-- User Profile Sidebar -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <img src="/ecommerce/assets/images/avatar.jpg" 
                         alt="Profile" 
                         class="rounded-circle mb-3" 
                         style="width: 100px; height: 100px; object-fit: cover;">
                    <h5 class="card-title"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                    <p class="text-muted"><?php echo ucfirst($_SESSION['role']); ?></p>
                    <span class="badge bg-<?php echo $vendor && $vendor['status'] === 'approved' ? 'success' : 'warning'; ?>">
                        <?php echo $vendor ? ucfirst($vendor['status']) : 'Customer'; ?>
                    </span>
                </div>
            </div>
            
            <!-- Navigation -->
            <div class="list-group mb-4">
                <a href="/ecommerce/profile.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-user me-2"></i>Profile
                </a>
                <a href="/ecommerce/orders.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-shopping-bag me-2"></i>My Orders
                </a>
                <a href="/ecommerce/wishlist.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-heart me-2"></i>Wishlist
                </a>
                <a href="/ecommerce/addresses.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-map-marker-alt me-2"></i>Addresses
                </a>
                <?php if($_SESSION['role'] === 'vendor'): ?>
                <a href="/ecommerce/vendor/dashboard.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-store me-2"></i>Vendor Dashboard
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-lg-9">
            <!-- Profile Update Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Profile Information</h5>
                </div>
                <div class="card-body">
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
                    
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['phone']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5 class="mb-3">Change Password</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control">
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <?php if($_SESSION['role'] === 'customer'): ?>
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Orders</h5>
                    <a href="/ecommerce/orders.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php
                    $orderStmt = $pdo->prepare("
                        SELECT * FROM orders 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ");
                    $orderStmt->execute([$_SESSION['user_id']]);
                    $orders = $orderStmt->fetchAll();
                    
                    if (count($orders) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($orders as $order): ?>
                                <tr>
                                    <td><?php echo $order['order_number']; ?></td>
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
                                        <a href="/ecommerce/order-details.php?id=<?php echo $order['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center py-3">No orders yet. <a href="/ecommerce/search.php">Start shopping!</a></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
