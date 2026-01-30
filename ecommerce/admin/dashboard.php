<?php
// admin/dashboard.php
require_once '../includes/auth_check.php';
require_once '../includes/header.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: /ecommerce/index.php');
    exit();
}

// Fetch statistics
$stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM vendors) as total_vendors,
        (SELECT COUNT(*) FROM products) as total_products,
        (SELECT COUNT(*) FROM orders) as total_orders,
        (SELECT SUM(total) FROM orders WHERE status = 'delivered') as total_sales
");
$stats = $stmt->fetch();

// Recent orders
$orderStmt = $pdo->prepare("
    SELECT o.*, u.username 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
");
$orderStmt->execute();
$recentOrders = $orderStmt->fetchAll();
?>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">Total Users</h5>
                <p class="card-text display-6"><?php echo $stats['total_users']; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Total Vendors</h5>
                <p class="card-text display-6"><?php echo $stats['total_vendors']; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">Total Products</h5>
                <p class="card-text display-6"><?php echo $stats['total_products']; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h5 class="card-title">Total Sales</h5>
                <p class="card-text display-6">$<?php echo number_format($stats['total_sales'] ?? 0, 2); ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <h2>Recent Orders</h2>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                        <td><?php echo htmlspecialchars($order['username']); ?></td>
                        <td>$<?php echo number_format($order['total'], 2); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                switch($order['status']) {
                                    case 'pending': echo 'warning'; break;
                                    case 'processing': echo 'info'; break;
                                    case 'shipped': echo 'primary'; break;
                                    case 'delivered': echo 'success'; break;
                                    case 'cancelled': echo 'danger'; break;
                                }
                            ?>"><?php echo ucfirst($order['status']); ?></span>
                        </td>
                        <td><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></td>
                        <td>
                            <a href="/ecommerce/admin/orders.php?action=view&id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
