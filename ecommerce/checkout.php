<?php
// login.php - Login Page
require_once 'db.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('/ecommerce/admin/dashboard.php');
    } elseif (isVendor()) {
        redirect('/ecommerce/vendor/dashboard.php');
    } else {
        redirect('/ecommerce/index.php');
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Check user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Verify password
            if (password_verify($password, $user['password'])) {
                if ($user['status'] === 'active') {
                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    
                    // Set session ID for cart
                    if (!isset($_SESSION['session_id'])) {
                        $_SESSION['session_id'] = session_id();
                    }
                    
                    // Transfer cart items from session to user
                    $transferStmt = $pdo->prepare("UPDATE cart SET user_id = ?, session_id = NULL WHERE session_id = ?");
                    $transferStmt->execute([$user['id'], $_SESSION['session_id']]);
                    
                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        redirect('/ecommerce/admin/dashboard.php');
                    } elseif ($user['role'] === 'vendor') {
                        // Check if vendor profile exists
                        $vendorStmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
                        $vendorStmt->execute([$user['id']]);
                        $vendor = $vendorStmt->fetch();
                        
                        if ($vendor && $vendor['status'] === 'approved') {
                            redirect('/ecommerce/vendor/dashboard.php');
                        } else {
                            redirect('/ecommerce/profile.php');
                        }
                    } else {
                        redirect('/ecommerce/index.php');
                    }
                } else {
                    $error = "Your account has been suspended. Please contact support.";
                }
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - OmniMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }
        
        .brand-logo {
            color: #667eea;
            font-size: 2rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-card p-5">
                    <!-- Brand Logo -->
                    <div class="text-center mb-5">
                        <a href="/ecommerce/index.php" class="brand-logo text-decoration-none">
                            <i class="fas fa-store me-2"></i>OmniMart
                        </a>
                        <h4 class="mt-3">Welcome Back</h4>
                        <p class="text-muted">Please sign in to your account</p>
                    </div>
                    
                    <!-- Messages -->
                    <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Login Form -->
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" name="email" class="form-control" required 
                                       value="<?php echo $_POST['email'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" name="password" class="form-control" id="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            <a href="/ecommerce/forgot-password.php" class="text-decoration-none">Forgot password?</a>
                        </div>
                        
                        <div class="d-grid mb-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>
                        </div>
                        
                        <div class="text-center">
                            <p class="mb-0">Don't have an account? 
                                <a href="/ecommerce/signup.php" class="text-decoration-none fw-bold">Sign up</a>
                            </p>
                        </div>
                    </form>
                    
                    <!-- Vendor Registration -->
                    <div class="mt-4 pt-4 border-top">
                        <p class="text-center text-muted mb-3">Are you a vendor?</p>
                        <div class="d-grid">
                            <a href="/ecommerce/signup.php?vendor=1" class="btn btn-outline-primary">
                                <i class="fas fa-store-alt me-2"></i>Register as Vendor
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
    </script>
</body>
</html>
