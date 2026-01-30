<?php
// admin/orders.php
require_once '../includes/auth_check.php';

// Only admin can access
if (!isAdmin()) {
    header("Location: ../index.php");
    exit();
}

// Handle actions
$action = $_GET['action'] ?? '';
$orderId = $_GET['id'] ?? 0;
$message = '';
$error = '';

// Update order status
if ($action === 'update_status' && $orderId) {
    $status = $_POST['status'] ?? '';
    $trackingNumber = $_POST['tracking_number'] ?? '';
    $adminNote = $_POST['admin_note'] ?? '';
    
    if (in_array($status, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])) {
        $stmt = $pdo->prepare("
            UPDATE orders SET 
                order_status = ?, 
                tracking_number = ?,
                admin_note = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$status, $trackingNumber, $adminNote, $orderId]);
        $message = "Order status updated to " . ucfirst($status);
    }
}

// Delete order (only if no payment)
if ($action === 'delete' && $orderId) {
    $checkStmt = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
    $checkStmt->execute([$orderId]);
    $order = $checkStmt->fetch();
    
    if ($order['payment_status'] === 'pending') {
        // Delete order items first
        $deleteItems = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
        $deleteItems->execute([$orderId]);
        
        // Delete order
        $deleteOrder = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        if ($deleteOrder->execute([$orderId])) {
            $message = "Order deleted successfully!";
        }
    } else {
        $error = "Cannot delete paid orders!";
    }
}

// Fetch filters
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(o.order_number LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter) {
    $where[] = "o.order_status = ?";
    $params[] = $statusFilter;
}

if ($dateFrom) {
    $where[] = "DATE
