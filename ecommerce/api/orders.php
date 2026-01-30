<?php
include 'api.php';

$user = checkToken($conn);

// ======================
// GET → User Orders
// ======================
if($_SERVER['REQUEST_METHOD'] === 'GET'){

    $stmt = $conn->prepare(
        "SELECT * FROM orders WHERE user_id=? ORDER BY id DESC"
    );
    $stmt->bind_param("i",$user['id']);
    $stmt->execute();

    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        "status"=>"success",
        "orders"=>$orders
    ]);
    exit;
}

// ======================
// POST → Place New Order
// ======================
if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $items = json_decode($_POST['items'], true);
    $total = $_POST['total'];

    $stmt = $conn->prepare(
        "INSERT INTO orders (user_id,total_amount,status)
         VALUES (?,?, 'Pending')"
    );
    $stmt->bind_param("id",$user['id'],$total);
    $stmt->execute();

    $order_id = $stmt->insert_id;

    foreach($items as $item){
        $stmt2 = $conn->prepare(
            "INSERT INTO order_items (order_id,product_id,quantity,price)
             VALUES (?,?,?,?)"
        );
        $stmt2->bind_param(
            "iiid",
            $order_id,
            $item['product_id'],
            $item['qty'],
            $item['price']
        );
        $stmt2->execute();
    }

    echo json_encode([
        "status"=>"success",
        "message"=>"Order placed",
        "order_id"=>$order_id
    ]);
    exit;
}

