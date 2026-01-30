<?php
include 'api.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    echo json_encode(["status"=>"error","message"=>"Invalid request"]);
    exit;
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
$stmt->bind_param("s",$email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if($user && password_verify($password,$user['password'])){
    echo json_encode([
        "status" => "success",
        "token"  => $user['password'],
        "user"   => [
            "id"   => $user['id'],
            "name" => $user['name'],
            "role" => $user['role']
        ]
    ]);
} else {
    echo json_encode(["status"=>"error","message"=>"Invalid credentials"]);
}

