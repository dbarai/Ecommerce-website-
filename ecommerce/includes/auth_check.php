<?php
// includes/auth_check.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /ecommerce/login.php");
        exit();
    }
}

// Check if user is admin
function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header("Location: /ecommerce/index.php");
        exit();
    }
}

// Check if user is vendor
function requireVendor() {
    requireLogin();
    if ($_SESSION['role'] !== 'vendor') {
        header("Location: /ecommerce/index.php");
        exit();
    }
}

// Check if user is customer
function requireCustomer() {
    requireLogin();
    if ($_SESSION['role'] !== 'customer') {
        header("Location: /ecommerce/index.php");
        exit();
    }
}

// Generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
