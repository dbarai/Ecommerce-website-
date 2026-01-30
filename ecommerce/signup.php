<?php
// signup.php - Registration Page
require_once 'db.php';

if (isLoggedIn()) {
    redirect('/ecommerce/index.php');
}

$error = '';
$success = '';

// Check if vendor registration
$isVendorRegistration = isset($_GET['vendor']) && $_GET['vendor'] == 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $fullName = sanitize($_POST['full_name']);
    $phone = sanitize($_POST['phone']);
    $agreeTerms = isset($_POST['agree_terms']);
    
    // Vendor specific fields
    $shopName = sanitize($_POST['shop_name'] ?? '');
    $shopDescription = sanitize($_POST['shop_description'] ?? '');
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword) || empty($fullName)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!$agreeTerms) {
        $error = "You must agree to the terms and conditions.";
    } else {
        // Check if username exists
        $checkUser = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $checkUser->execute([$username, $email]);
        
        if ($checkUser->rowCount() > 0) {
            $error = "Username or email already exists.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Determine role
                $role = $isVendorRegistration ? 'vendor' : 'customer';
                
                // Create user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $userStmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, full_name, phone, role, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'active')
                ");
                $userStmt->execute([$username, $email, $hashedPassword, $fullName, $phone, $role]);
                $userId = $pdo->lastInsertId();
                
                // If vendor, create vendor profile
                if ($isVendorRegistration && !empty($shopName)) {
                    $shopSlug = generateSlug($shopName);
                    $vendorStmt = $pdo->prepare("
                        INSERT INTO vendors (user_id, shop_name, shop_slug, shop_description, status) 
                        VALUES (?, ?, ?, ?, 'pending')
                    ");
                    $vendorStmt->execute([$userId, $shopName, $shopSlug, $shopDescription]);
                }
                
                $pdo->commit();
                
                // Auto login
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = $role;
                $_SESSION['full_name'] = $fullName;
                
                $success = "Registration successful!";
                
                // Redirect
                if ($role === 'vendor') {
                    redirect('/ecommerce/vendor/register-success.php');
                } else {
                    redirect('/ecommerce/index.php');
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isVendorRegistration ? 'Vendor Registration' : 'Sign Up'; ?> - OmniMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .signup-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }
        
        .brand-logo {
            color: #667eea;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .vendor-badge {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7">
                <div class="signup-card p-5">
                    <!-- Brand Logo -->
                    <div class="text-center mb-4">
                        <a href="/ecommerce/index.php" class="brand-logo text-decoration-none">
                            <i class="fas fa-store me-2"></i>OmniMart
                        </a>
                        <h4 class="mt-3">
                            <?php if($isVendorRegistration): ?>
                            <span class="badge vendor-badge">Vendor Registration</span>
                            <?php else: ?>
                            Create Your Account
                            <?php endif; ?>
                        </h4>
                        <p class="text-muted">
                            <?php if($isVendorRegistration): ?>
                            Join our marketplace and start selling today
                            <?php else: ?>
                            Sign up to enjoy personalized shopping experience
                            <?php endif; ?>
                        </p>
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
                    
                    <!-- Registration Form -->
                    <form method="POST">
                        <div class="row g-3">
                            <!-- Personal Information -->
                            <div class="col-md-6">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-control" required 
                                       value="<?php echo $_POST['full_name'] ?? ''; ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Username *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" name="username" class="form-control" required 
                                           value="<?php echo $_POST['username'] ?? ''; ?>">
                                </div>
                                <small class="text-muted">This will be your public display name</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Email Address *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" required 
                                           value="<?php echo $_POST['email'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?php echo $_POST['phone'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" class="form-control" id="password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Confirm Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="confirm_password" class="form-control" id="confirmPassword" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Vendor Specific Fields -->
                            <?php if($isVendorRegistration): ?>
                            <div class="col-12 mt-3">
                                <h5 class="border-bottom pb-2">Store Information</h5>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Shop Name *</label>
                                <input type="text" name="shop_name" class="form-control" required 
                                       value="<?php echo $_POST['shop_name'] ?? ''; ?>">
                                <small class="text-muted">This will be your store name on the platform</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Shop Category</label>
                                <select name="shop_category" class="form-select">
                                    <option value="">Select Category</option>
                                    <option value="fashion">Fashion & Clothing</option>
                                    <option value="electronics">Electronics</option>
                                    <option value="home">Home & Garden</option>
                                    <option value="beauty">Beauty & Health</option>
                                    <option value="sports">Sports & Outdoors</option>
                                    <option value="books">Books & Media</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Shop Description</label>
                                <textarea name="shop_description" class="form-control" rows="3" 
                                          placeholder="Tell customers about your store..."><?php echo $_POST['shop_description'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Business Type</label>
                                <select name="business_type" class="form-select">
                                    <option value="individual">Individual/Sole Proprietor</option>
                                    <option value="partnership">Partnership</option>
                                    <option value="llc">LLC</option>
                                    <option value="corporation">Corporation</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Tax ID / VAT Number</label>
                                <input type="text" name="tax_id" class="form-control" 
                                       value="<?php echo $_POST['tax_id'] ?? ''; ?>">
                            </div>
                            <?php endif; ?>
                            
                            <!-- Terms and Conditions -->
                            <div class="col-12 mt-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="agree_terms" id="agree_terms" 
                                           <?php echo isset($_POST['agree_terms']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="agree_terms">
                                        I agree to the <a href="/ecommerce/terms.php" target="_blank">Terms and Conditions</a> 
                                        and <a href="/ecommerce/privacy.php" target="_blank">Privacy Policy</a>
                                    </label>
                                </div>
                                
                                <?php if($isVendorRegistration): ?>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="agree_vendor_terms" id="agree_vendor_terms">
                                    <label class="form-check-label" for="agree_vendor_terms">
                                        I agree to the <a href="/ecommerce/vendor-terms.php" target="_blank">Vendor Agreement</a> 
                                        and understand the commission structure
                                    </label>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="col-12 mt-4">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-user-plus me-2"></i>
                                        <?php echo $isVendorRegistration ? 'Apply as Vendor' : 'Create Account'; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Already have account -->
                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="mb-0">Already have an account? 
                            <a href="/ecommerce/login.php" class="text-decoration-none fw-bold">Sign in</a>
                        </p>
                        
                        <?php if(!$isVendorRegistration): ?>
                        <p class="mt-3">
                            Want to sell on OmniMart? 
                            <a href="/ecommerce/signup.php?vendor=1" class="text-decoration-none fw-bold">Register as Vendor</a>
                        </p>
                        <?php else: ?>
                        <p class="mt-3">
                            Want to shop only? 
                            <a href="/ecommerce/signup.php" class="text-decoration-none fw-bold">Register as Customer</a>
                        </p>
                        <?php endif; ?>
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
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('confirmPassword');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
    </script>
</body>
</html>
