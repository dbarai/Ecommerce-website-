<?php
// api/cart.php
header('Content-Type: application/json');
require_once '../db.php';

session_start();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch($action) {
    case 'add':
        addToCart();
        break;
    case 'update':
        updateCart();
        break;
    case 'remove':
        removeFromCart();
        break;
    case 'clear':
        clearCart();
        break;
    case 'count':
        getCartCount();
        break;
    default:
        getCart();
}

function getCart() {
    global $pdo;
    
    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = $_SESSION['session_id'] ?? session_id();
    
    $where = $userId ? "user_id = ?" : "session_id = ?";
    $param = $userId ?: $sessionId;
    
    $stmt = $pdo->prepare("
        SELECT c.*, p.name, p.slug, p.price as product_price, p.quantity as stock_quantity,
               v.shop_name, 
               (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
        FROM cart c
        LEFT JOIN products p ON c.product_id = p.id
        LEFT JOIN vendors v ON p.vendor_id = v.id
        WHERE $where
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$param]);
    $items = $stmt->fetchAll();
    
    // Calculate totals
    $subtotal = 0;
    $totalItems = 0;
    
    foreach ($items as $item) {
        $itemTotal = $item['price'] * $item['quantity'];
        $subtotal += $itemTotal;
        $totalItems += $item['quantity'];
    }
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'subtotal' => $subtotal,
        'total_items' => $totalItems
    ]);
}

function addToCart() {
    global $pdo;
    
    $productId = intval($_POST['product_id'] ?? 0);
    $variationId = intval($_POST['variation_id'] ?? null);
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if ($productId <= 0 || $quantity <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        return;
    }
    
    // Check product availability
    $productStmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
    $productStmt->execute([$productId]);
    $product = $productStmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Product not available']);
        return;
    }
    
    if ($variationId) {
        $varStmt = $pdo->prepare("SELECT * FROM product_variations WHERE id = ? AND product_id = ?");
        $varStmt->execute([$variationId, $productId]);
        $variation = $varStmt->fetch();
        
        if (!$variation) {
            echo json_encode(['success' => false, 'error' => 'Invalid variation']);
            return;
        }
        
        $price = $variation['price'];
        $stock = $variation['quantity'];
    } else {
        $price = $product['price'];
        $stock = $product['quantity'];
    }
    
    // Check stock
    if ($stock < $quantity) {
        echo json_encode(['success' => false, 'error' => 'Insufficient stock']);
        return;
    }
    
    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = $_SESSION['session_id'] ?? session_id();
    
    // Check if item already in cart
    $checkStmt = $pdo->prepare("
        SELECT * FROM cart 
        WHERE product_id = ? AND variation_id = ? AND 
              (user_id = ? OR session_id = ?)
    ");
    $checkStmt->execute([$productId, $variationId, $userId, $sessionId]);
    $existingItem = $checkStmt->fetch();
    
    try {
        if ($existingItem) {
            // Update quantity
            $newQuantity = $existingItem['quantity'] + $quantity;
            $updateStmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $updateStmt->execute([$newQuantity, $existingItem['id']]);
        } else {
            // Add new item
            $insertStmt = $pdo->prepare("
                INSERT INTO cart (user_id, session_id, product_id, variation_id, quantity, price)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([$userId, $sessionId, $productId, $variationId, $quantity, $price]);
        }
        
        // Get updated cart count
        $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ? OR session_id = ?");
        $countStmt->execute([$userId, $sessionId]);
        $cartCount = $countStmt->fetch()['count'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Added to cart',
            'cart_count' => $cartCount
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getCartCount() {
    global $pdo;
    
    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = $_SESSION['session_id'] ?? session_id();
    
    $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ? OR session_id = ?");
    $countStmt->execute([$userId, $sessionId]);
    $cartCount = $countStmt->fetch()['count'];
    
    echo json_encode(['success' => true, 'count' => $cartCount]);
}
?>
