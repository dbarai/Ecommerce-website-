<?php
// api/products.php
header('Content-Type: application/json');
require_once '../db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch($method) {
    case 'GET':
        handleGetRequest();
        break;
    case 'POST':
        handlePostRequest();
        break;
    case 'PUT':
        handlePutRequest();
        break;
    case 'DELETE':
        handleDeleteRequest();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function handleGetRequest() {
    global $pdo;
    
    $productId = $_GET['id'] ?? 0;
    $category = $_GET['category'] ?? '';
    $vendor = $_GET['vendor'] ?? '';
    $search = $_GET['search'] ?? '';
    $limit = min(intval($_GET['limit'] ?? 20), 100);
    $page = max(intval($_GET['page'] ?? 1), 1);
    $offset = ($page - 1) * $limit;
    
    if ($productId) {
        // Get single product
        $stmt = $pdo->prepare("
            SELECT p.*, v.shop_name, v.shop_slug, c.name as category_name,
                   (SELECT GROUP_CONCAT(image_url) FROM product_images WHERE product_id = p.id) as images
            FROM products p
            LEFT JOIN vendors v ON p.vendor_id = v.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ? AND p.status = 'active'
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if ($product) {
            // Fetch variations
            $varStmt = $pdo->prepare("SELECT * FROM product_variations WHERE product_id = ?");
            $varStmt->execute([$productId]);
            $variations = $varStmt->fetchAll();
            
            $product['variations'] = $variations;
            $product['images'] = $product['images'] ? explode(',', $product['images']) : [];
            
            echo json_encode(['success' => true, 'product' => $product]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Product not found']);
        }
    } else {
        // Get product list
        $where = ["p.status = 'active'"];
        $params = [];
        
        if ($category) {
            $where[] = "p.category_id = ?";
            $params[] = $category;
        }
        
        if ($vendor) {
            $where[] = "p.vendor_id = ?";
            $params[] = $vendor;
        }
        
        if ($search) {
            $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
        
        // Count total
        $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM products p $whereClause");
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];
        
        // Get products
        $stmt = $pdo->prepare("
            SELECT p.*, v.shop_name, v.shop_slug, c.name as category_name,
                   (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
            FROM products p
            LEFT JOIN vendors v ON p.vendor_id = v.id
            LEFT JOIN categories c ON p.category_id = c.id
            $whereClause
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $allParams = array_merge($params, [$limit, $offset]);
        $stmt->execute($allParams);
        $products = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'products' => $products,
            'total' => $total,
            'page' => $page,
            'total_pages' => ceil($total / $limit)
        ]);
    }
}

function handlePostRequest() {
    global $pdo;
    
    // Only admin/vendor can add products
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }
    
    $name = sanitize($data['name'] ?? '');
    $categoryId = intval($data['category_id'] ?? 0);
    $price = floatval($data['price'] ?? 0);
    $quantity = intval($data['quantity'] ?? 0);
    $description = sanitize($data['description'] ?? '');
    
    if (empty($name) || $price <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        return;
    }
    
    try {
        // Determine vendor ID
        $vendorId = $_SESSION['vendor_id'] ?? null;
        if (!$vendorId && $_SESSION['role'] === 'vendor') {
            $vendorStmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ?");
            $vendorStmt->execute([$_SESSION['user_id']]);
            $vendor = $vendorStmt->fetch();
            $vendorId = $vendor['id'] ?? null;
        }
        
        if (!$vendorId) {
            $vendorId = $data['vendor_id'] ?? null;
        }
        
        $slug = generateSlug($name);
        $sku = $data['sku'] ?? generateSKU();
        
        $stmt = $pdo->prepare("
            INSERT INTO products (name, slug, sku, category_id, vendor_id, price, quantity, description, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft')
        ");
        $stmt->execute([$name, $slug, $sku, $categoryId, $vendorId, $price, $quantity, $description]);
        
        $productId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Product created',
            'product_id' => $productId
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function generateSKU() {
    return 'SKU-' . strtoupper(uniqid());
}
?>
