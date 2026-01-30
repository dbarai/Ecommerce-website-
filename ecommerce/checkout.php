<?php
// checkout.php - Checkout Page
require_once 'includes/header.php';
require_once 'includes/auth_check.php'; // Require login for checkout

// Handle checkout submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // Validate and process order
    $errors = [];
    
    // Validate required fields
    $required = ['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'country', 'postal_code'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required.";
        }
    }
    
    if (empty($_POST['payment_method'])) {
        $errors[] = "Payment method is required.";
    }
    
    // Validate cart
    $cartStmt = $pdo->prepare("
        SELECT c.*, p.quantity as stock_quantity, p.name as product_name
        FROM cart c
        LEFT JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
    ");
    $cartStmt->execute([$_SESSION['user_id']]);
    $cartItems = $cartStmt->fetchAll();
    
    if (count($cartItems) === 0) {
        $errors[] = "Your cart is empty.";
    }
    
    // Check stock availability
    foreach ($cartItems as $item) {
        if ($item['stock_quantity'] < $item['quantity']) {
            $errors[] = "Insufficient stock for {$item['product_name']}. Only {$item['stock_quantity']} available.";
        }
    }
    
    if (empty($errors)) {
        // Calculate totals
        $subtotal = 0;
        foreach ($cartItems as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        
        // Apply coupon discount
        $discount = 0;
        if (isset($_SESSION['coupon_code'])) {
            $couponStmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ?");
            $couponStmt->execute([$_SESSION['coupon_code']]);
            $coupon = $couponStmt->fetch();
            
            if ($coupon) {
                if ($coupon['discount_type'] === 'percentage') {
                    $discount = $subtotal * ($coupon['discount_value'] / 100);
                } else {
                    $discount = $coupon['discount_value'];
                }
                
                // Update coupon usage
                $updateCoupon = $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?");
                $updateCoupon->execute([$coupon['id']]);
            }
        }
        
        $shipping = ($subtotal > 50) ? 0 : 5;
        $tax = $subtotal * 0.1;
        $total = $subtotal - $discount + $shipping + $tax;
        
        // Generate order number
        $orderNumber = 'ORD' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        try {
            $pdo->beginTransaction();
            
            // Create order
            $orderStmt = $pdo->prepare("
                INSERT INTO orders (order_number, user_id, vendor_id, coupon_id, subtotal, discount, tax, shipping, total, 
                                   payment_method, payment_status, order_status, shipping_address, billing_address, customer_note)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, ?)
            ");
            
            // For multi-vendor, we need to create separate orders per vendor
            // For simplicity, we'll create one order with first vendor or NULL
            $vendorId = $cartItems[0]['vendor_id'] ?? NULL;
            $couponId = isset($coupon) ? $coupon['id'] : NULL;
            
            $shippingAddress = json_encode([
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'email' => $_POST['email'],
                'phone' => $_POST['phone'],
                'address' => $_POST['address'],
                'apartment' => $_POST['apartment'] ?? '',
                'city' => $_POST['city'],
                'state' => $_POST['state'] ?? '',
                'country' => $_POST['country'],
                'postal_code' => $_POST['postal_code']
            ]);
            
            $billingAddress = $_POST['same_as_shipping'] ? $shippingAddress : json_encode([
                'first_name' => $_POST['billing_first_name'] ?? $_POST['first_name'],
                'last_name' => $_POST['billing_last_name'] ?? $_POST['last_name'],
                'email' => $_POST['billing_email'] ?? $_POST['email'],
                'phone' => $_POST['billing_phone'] ?? $_POST['phone'],
                'address' => $_POST['billing_address'] ?? $_POST['address'],
                'apartment' => $_POST['billing_apartment'] ?? $_POST['apartment'] ?? '',
                'city' => $_POST['billing_city'] ?? $_POST['city'],
                'state' => $_POST['billing_state'] ?? $_POST['state'] ?? '',
                'country' => $_POST['billing_country'] ?? $_POST['country'],
                'postal_code' => $_POST['billing_postal_code'] ?? $_POST['postal_code']
            ]);
            
            $orderStmt->execute([
                $orderNumber,
                $_SESSION['user_id'],
                $vendorId,
                $couponId,
                $subtotal,
                $discount,
                $tax,
                $shipping,
                $total,
                $_POST['payment_method'],
                $shippingAddress,
                $billingAddress,
                $_POST['notes'] ?? ''
            ]);
            
            $orderId = $pdo->lastInsertId();
            
            // Create order items
            foreach ($cartItems as $item) {
                $itemTotal = $item['price'] * $item['quantity'];
                
                $orderItemStmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, variation_id, product_name, variation_name, quantity, price, total)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $orderItemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['variation_id'],
                    $item['product_name'],
                    $item['variation_name'] ?? null,
                    $item['quantity'],
                    $item['price'],
                    $itemTotal
                ]);
                
                // Update product stock
                $updateStock = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                $updateStock->execute([$item['quantity'], $item['product_id']]);
                
                // If variation, update variation stock
                if ($item['variation_id']) {
                    $updateVariationStock = $pdo->prepare("UPDATE product_variations SET quantity = quantity - ? WHERE id = ?");
                    $updateVariationStock->execute([$item['quantity'], $item['variation_id']]);
                }
            }
            
            // Clear cart
            $clearCart = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $clearCart->execute([$_SESSION['user_id']]);
            
            // Clear coupon session
            unset($_SESSION['coupon_code']);
            unset($_SESSION['coupon_discount']);
            unset($_SESSION['coupon_type']);
            
            $pdo->commit();
            
            // Redirect to order confirmation
            header("Location: /ecommerce/order-confirmation.php?id=" . $orderId);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Order failed: " . $e->getMessage();
        }
    }
}

// Fetch user details
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$user = $userStmt->fetch();

// Fetch cart items for summary
$cartStmt = $pdo->prepare("
    SELECT c.*, p.name, p.slug, p.price as product_price,
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as product_image
    FROM cart c
    LEFT JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
");
$cartStmt->execute([$_SESSION['user_id']]);
$cartItems = $cartStmt->fetchAll();

// Calculate totals
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$discount = 0;
if (isset($_SESSION['coupon_code'])) {
    if ($_SESSION['coupon_type'] === 'percentage') {
        $discount = $subtotal * ($_SESSION['coupon_discount'] / 100);
    } else {
        $discount = $_SESSION['coupon_discount'];
    }
}

$shipping = ($subtotal > 50) ? 0 : 5;
$tax = $subtotal * 0.1;
$total = $subtotal - $discount + $shipping + $tax;
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8">
            <!-- Checkout Progress -->
            <div class="mb-5">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-center">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" 
                             style="width: 40px; height: 40px;">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="mt-2">Cart</div>
                    </div>
                    <div class="flex-grow-1 border-top border-primary"></div>
                    <div class="text-center">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" 
                             style="width: 40px; height: 40px;">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="mt-2">Information</div>
                    </div>
                    <div class="flex-grow-1 border-top"></div>
                    <div class="text-center">
                        <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center" 
                             style="width: 40px; height: 40px;">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="mt-2">Payment</div>
                    </div>
                </div>
            </div>
            
            <!-- Errors -->
            <?php if(!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <form method="POST" id="checkoutForm">
                <!-- Shipping Information -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-shipping-fast me-2"></i>Shipping Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone *</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address *</label>
                                <input type="text" name="address" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Apartment, Suite, etc.</label>
                                <input type="text" name="apartment" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">City *</label>
                                <input type="text" name="city" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">State/Province</label>
                                <input type="text" name="state" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Country *</label>
                                <select name="country" class="form-select" required>
                                    <option value="">Select Country</option>
                                    <option value="US">United States</option>
                                    <option value="CA">Canada</option>
                                    <option value="UK">United Kingdom</option>
                                    <option value="AU">Australia</option>
                                    <!-- Add more countries as needed -->
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Postal Code *</label>
                                <input type="text" name="postal_code" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="sameAsShipping" checked>
                            <label class="form-check-label" for="sameAsShipping">
                                Billing address same as shipping address
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Billing Information (Hidden by default) -->
                <div class="card mb-4" id="billingSection" style="display: none;">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Billing Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name</label>
                                <input type="text" name="billing_first_name" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="billing_last_name" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="billing_email" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="billing_phone" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <input type="text" name="billing_address" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">City</label>
                                <input type="text" name="billing_city" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Country</label>
                                <select name="billing_country" class="form-select">
                                    <option value="">Select Country</option>
                                    <option value="US">United States</option>
                                    <option value="CA">Canada</option>
                                    <option value="UK">United Kingdom</option>
                                    <option value="AU">Australia</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Postal Code</label>
                                <input type="text" name="billing_postal_code" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Method -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Method</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" 
                                           value="credit_card" id="creditCard" required>
                                    <label class="form-check-label" for="creditCard">
                                        <i class="fab fa-cc-visa me-2"></i>Credit/Debit Card
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" 
                                           value="paypal" id="paypal">
                                    <label class="form-check-label" for="paypal">
                                        <i class="fab fa-paypal me-2"></i>PayPal
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" 
                                           value="cod" id="cod">
                                    <label class="form-check-label" for="cod">
                                        <i class="fas fa-money-bill-wave me-2"></i>Cash on Delivery
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" 
                                           value="bank_transfer" id="bankTransfer">
                                    <label class="form-check-label" for="bankTransfer">
                                        <i class="fas fa-university me-2"></i>Bank Transfer
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Credit Card Form (Shown when credit card selected) -->
                        <div id="creditCardForm" style="display: none;" class="mt-4">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Card Number</label>
                                    <input type="text" class="form-control" placeholder="1234 5678 9012 3456">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Expiry Date</label>
                                    <input type="text" class="form-control" placeholder="MM/YY">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">CVV</label>
                                    <input type="text" class="form-control" placeholder="123">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Name on Card</label>
                                    <input type="text" class="form-control" placeholder="John Doe">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Order Notes -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Order Notes (Optional)</h5>
                    </div>
                    <div class="card-body">
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Notes about your order, e.g., special delivery instructions"></textarea>
                    </div>
                </div>
                
                <!-- Terms and Conditions -->
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="/ecommerce/terms.php">Terms and Conditions</a> and 
                        <a href="/ecommerce/privacy.php">Privacy Policy</a>
                    </label>
                </div>
                
                <!-- Submit Button -->
                <div class="d-grid">
                    <button type="submit" name="place_order" class="btn btn-success btn-lg">
                        <i class="fas fa-lock me-2"></i>Place Order
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Order Summary Sidebar -->
        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                
                <!-- Cart Items -->
                <div class="card-body">
                    <div class="order-items">
                        <?php foreach($cartItems as $item): ?>
                        <div class="d-flex mb-3">
                            <div class="flex-shrink-0">
                                <img src="/ecommerce/<?php echo $item['product_image'] ?: 'assets/images/placeholder.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     class="rounded" 
                                     style="width: 60px; height: 60px; object-fit: cover;">
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">Qty: <?php echo $item['quantity']; ?></small>
                                    <span class="fw-bold">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Order Totals -->
                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <span class="fw-bold">$<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        
                        <?php if($discount > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>Discount</span>
                            <span class="fw-bold">-$<?php echo number_format($discount, 2); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping</span>
                            <span class="fw-bold">
                                <?php if($shipping == 0): ?>
                                <span class="text-success">FREE</span>
                                <?php else: ?>
                                $<?php echo number_format($shipping, 2); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax</span>
                            <span class="fw-bold">$<?php echo number_format($tax, 2); ?></span>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-4">
                            <span class="fs-5 fw-bold">Total</span>
                            <span class="fs-5 fw-bold text-primary">$<?php echo number_format($total, 2); ?></span>
                        </div>
                    </div>
                    
                    <!-- Return to Cart -->
                    <div class="text-center">
                        <a href="/ecommerce/cart.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-edit me-2"></i>Edit Cart
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Security Assurance -->
            <div class="card mt-3">
                <div class="card-body text-center">
                    <i class="fas fa-lock fa-2x text-success mb-3"></i>
                    <h6 class="fw-bold">Secure Checkout</h6>
                    <p class="small text-muted mb-0">
                        Your payment information is encrypted and secure. We never store your credit card details.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Show/hide billing section
    $('#sameAsShipping').change(function() {
        if ($(this).is(':checked')) {
            $('#billingSection').slideUp();
        } else {
            $('#billingSection').slideDown();
        }
    });
    
    // Show/hide credit card form
    $('input[name="payment_method"]').change(function() {
        if ($(this).val() === 'credit_card') {
            $('#creditCardForm').slideDown();
        } else {
            $('#creditCardForm').slideUp();
        }
    });
    
    // Form validation
    $('#checkoutForm').submit(function(e) {
        var valid = true;
        
        // Check if cart is empty
        <?php if(count($cartItems) === 0): ?>
        alert('Your cart is empty. Please add items to your cart before checking out.');
        valid = false;
        <?php endif; ?>
        
        // Check payment method selected
        if (!$('input[name="payment_method"]:checked').val()) {
            alert('Please select a payment method.');
            valid = false;
        }
        
        // Check terms accepted
        if (!$('#terms').is(':checked')) {
            alert('You must agree to the terms and conditions.');
            valid = false;
        }
        
        if (!valid) {
            e.preventDefault();
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
