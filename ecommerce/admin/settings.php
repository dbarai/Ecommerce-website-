<?php
// admin/settings.php
require_once '../includes/auth_check.php';
require_once '../includes/header.php';

// Only admin can access
if (!isAdmin()) {
    redirect('/ecommerce/index.php');
}

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle different setting groups
    if (isset($_POST['save_general'])) {
        // General Settings
        $siteName = sanitize($_POST['site_name']);
        $siteEmail = sanitize($_POST['site_email']);
        $sitePhone = sanitize($_POST['site_phone']);
        $siteAddress = sanitize($_POST['site_address']);
        $currency = sanitize($_POST['currency']);
        $timezone = sanitize($_POST['timezone']);
        
        // Save to database (simplified - in real app, use settings table)
        $success = "General settings saved successfully!";
    }
    
    if (isset($_POST['save_payment'])) {
        // Payment Settings
        $stripeEnabled = isset($_POST['stripe_enabled']) ? 1 : 0;
        $stripePublicKey = sanitize($_POST['stripe_public_key']);
        $stripeSecretKey = sanitize($_POST['stripe_secret_key']);
        $paypalEnabled = isset($_POST['paypal_enabled']) ? 1 : 0;
        $paypalClientId = sanitize($_POST['paypal_client_id']);
        $paypalSecret = sanitize($_POST['paypal_secret']);
        $codEnabled = isset($_POST['cod_enabled']) ? 1 : 0;
        
        $success = "Payment settings saved successfully!";
    }
    
    if (isset($_POST['save_shipping'])) {
        // Shipping Settings
        $shippingMethod = sanitize($_POST['shipping_method']);
        $flatRate = floatval($_POST['flat_rate']);
        $freeShippingMin = floatval($_POST['free_shipping_min']);
        $localPickup = isset($_POST['local_pickup']) ? 1 : 0;
        
        $success = "Shipping settings saved successfully!";
    }
    
    if (isset($_POST['save_email'])) {
        // Email Settings
        $smtpHost = sanitize($_POST['smtp_host']);
        $smtpPort = intval($_POST['smtp_port']);
        $smtpUsername = sanitize($_POST['smtp_username']);
        $smtpPassword = $_POST['smtp_password'];
        $smtpEncryption = sanitize($_POST['smtp_encryption']);
        $fromEmail = sanitize($_POST['from_email']);
        $fromName = sanitize($_POST['from_name']);
        
        $success = "Email settings saved successfully!";
    }
    
    if (isset($_POST['save_tax'])) {
        // Tax Settings
        $taxEnabled = isset($_POST['tax_enabled']) ? 1 : 0;
        $taxRate = floatval($_POST['tax_rate']);
        $taxInclusive = isset($_POST['tax_inclusive']) ? 1 : 0;
        $euVatEnabled = isset($_POST['eu_vat_enabled']) ? 1 : 0;
        
        $success = "Tax settings saved successfully!";
    }
    
    if (isset($_POST['save_seo'])) {
        // SEO Settings
        $metaTitle = sanitize($_POST['meta_title']);
        $metaDescription = sanitize($_POST['meta_description']);
        $metaKeywords = sanitize($_POST['meta_keywords']);
        $googleAnalytics = sanitize($_POST['google_analytics']);
        $facebookPixel = sanitize($_POST['facebook_pixel']);
        
        $success = "SEO settings saved successfully!";
    }
    
    if (isset($_POST['save_maintenance'])) {
        // Maintenance Mode
        $maintenanceMode = isset($_POST['maintenance_mode']) ? 1 : 0;
        $maintenanceMessage = sanitize($_POST['maintenance_message']);
        
        $success = "Maintenance settings saved successfully!";
    }
}

// Default settings (in real app, fetch from database)
$settings = [
    'general' => [
        'site_name' => 'OmniMart',
        'site_email' => 'support@omnimart.com',
        'site_phone' => '+1 (555) 123-4567',
        'site_address' => '123 Business Street, City, Country',
        'currency' => 'USD',
        'timezone' => 'America/New_York',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i:s'
    ],
    'payment' => [
        'stripe_enabled' => true,
        'stripe_public_key' => 'pk_test_...',
        'stripe_secret_key' => 'sk_test_...',
        'paypal_enabled' => true,
        'paypal_client_id' => 'client_id_here',
        'paypal_secret' => 'secret_here',
        'cod_enabled' => true
    ],
    'shipping' => [
        'shipping_method' => 'flat_rate',
        'flat_rate' => 5.00,
        'free_shipping_min' => 50.00,
        'local_pickup' => true
    ],
    'email' => [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_username' => 'email@example.com',
        'smtp_password' => '',
        'smtp_encryption' => 'tls',
        'from_email' => 'noreply@omnimart.com',
        'from_name' => 'OmniMart'
    ],
    'tax' => [
        'tax_enabled' => true,
        'tax_rate' => 10.0,
        'tax_inclusive' => false,
        'eu_vat_enabled' => false
    ],
    'seo' => [
        'meta_title' => 'OmniMart - Multi-Vendor E-Commerce Platform',
        'meta_description' => 'Shop from thousands of vendors on OmniMart',
        'meta_keywords' => 'ecommerce, shopping, online store, multi-vendor',
        'google_analytics' => 'UA-XXXXX-Y',
        'facebook_pixel' => 'XXXXXXXXXXXXXXX'
    ],
    'maintenance' => [
        'maintenance_mode' => false,
        'maintenance_message' => 'Site is under maintenance. Please check back soon.'
    ]
];
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-2">
            <?php include 'admin-sidebar.php'; ?>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-10">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold">Settings</h4>
                    <p class="text-muted mb-0">Configure your store settings</p>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if($success): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if($error): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Settings Tabs -->
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs" id="settingsTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                                <i class="fas fa-cog me-2"></i>General
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button">
                                <i class="fas fa-credit-card me-2"></i>Payment
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="shipping-tab" data-bs-toggle="tab" data-bs-target="#shipping" type="button">
                                <i class="fas fa-shipping-fast me-2"></i>Shipping
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button">
                                <i class="fas fa-envelope me-2"></i>Email
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tax-tab" data-bs-toggle="tab" data-bs-target="#tax" type="button">
                                <i class="fas fa-percentage me-2"></i>Tax
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="seo-tab" data-bs-toggle="tab" data-bs-target="#seo" type="button">
                                <i class="fas fa-search me-2"></i>SEO
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button">
                                <i class="fas fa-tools me-2"></i>Maintenance
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content p-4" id="settingsTabContent">
                        <!-- General Settings Tab -->
                        <div class="tab-pane fade show active" id="general" role="tabpanel">
                            <form method="POST">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Site Name</label>
                                        <input type="text" name="site_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['general']['site_name']); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Site Email</label>
                                        <input type="email" name="site_email" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['general']['site_email']); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Site Phone</label>
                                        <input type="text" name="site_phone" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['general']['site_phone']); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Currency</label>
                                        <select name="currency" class="form-select">
                                            <option value="USD" <?php echo $settings['general']['currency'] == 'USD' ? 'selected' : ''; ?>>US Dollar ($)</option>
                                            <option value="EUR" <?php echo $settings['general']['currency'] == 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                                            <option value="GBP" <?php echo $settings['general']['currency'] == 'GBP' ? 'selected' : ''; ?>>British Pound (£)</option>
                                            <option value="CAD" <?php echo $settings['general']['currency'] == 'CAD' ? 'selected' : ''; ?>>Canadian Dollar (C$)</option>
                                            <option value="AUD" <?php echo $settings['general']['currency'] == 'AUD' ? 'selected' : ''; ?>>Australian Dollar (A$)</option>
                                            <option value="JPY" <?php echo $settings['general']['currency'] == 'JPY' ? 'selected' : ''; ?>>Japanese Yen (¥)</option>
                                            <option value="INR" <?php echo $settings['general']['currency'] == 'INR' ? 'selected' : ''; ?>>Indian Rupee (₹)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Timezone</label>
                                        <select name="timezone" class="form-select">
                                            <?php
                                            $timezones = [
                                                'America/New_York' => 'Eastern Time (ET)',
                                                'America/Chicago' => 'Central Time (CT)',
                                                'America/Denver' => 'Mountain Time (MT)',
                                                'America/Los_Angeles' => 'Pacific Time (PT)',
                                                'Europe/London' => 'London',
                                                'Europe/Paris' => 'Paris',
                                                'Asia/Tokyo' => 'Tokyo',
                                                'Asia/Dubai' => 'Dubai',
                                                'Asia/Kolkata' => 'India',
                                                'Australia/Sydney' => 'Sydney'
                                            ];
                                            
                                            foreach ($timezones as $tz => $label):
                                            ?>
                                            <option value="<?php echo $tz; ?>" 
                                                    <?php echo $settings['general']['timezone'] == $tz ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Date Format</label>
                                        <select name="date_format" class="form-select">
                                            <option value="Y-m-d" <?php echo $settings['general']['date_format'] == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                            <option value="d/m/Y" <?php echo $settings['general']['date_format'] == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                            <option value="m/d/Y" <?php echo $settings['general']['date_format'] == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                            <option value="d M, Y" <?php echo $settings['general']['date_format'] == 'd M, Y' ? 'selected' : ''; ?>>DD Mon, YYYY</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label class="form-label">Site Address</label>
                                        <textarea name="site_address" class="form-control" rows="3"><?php echo htmlspecialchars($settings['general']['site_address']); ?></textarea>
                                    </div>
                                    
                                    <div class="col-12 mt-4">
                                        <button type="submit" name="save_general" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save General Settings
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Payment Settings Tab -->
                        <div class="tab-pane fade" id="payment" role="tabpanel">
                            <form method="POST">
                                <div class="row g-3">
                                    <h5 class="mb-3">Stripe Payment Gateway</h5>
                                    
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" name="stripe_enabled" id="stripe_enabled" 
                                                   <?php echo $settings['payment']['stripe_enabled'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="stripe_enabled">Enable Stripe Payment</label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Stripe Public Key</label>
                                        <input type="text" name="stripe_public_key" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['payment']['stripe_public_key']); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Stripe Secret Key</label>
                                        <input type="password" name="stripe_secret_key" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['payment']['stripe_secret_key']); ?>">
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <h5 class="mb-3">PayPal Payment Gateway</h5>
                                    
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" name="paypal_enabled" id="paypal_enabled"
                                                   <?php echo $settings['payment']['paypal_enabled'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="paypal_enabled">Enable PayPal Payment</label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">PayPal Client ID</label>
                                        <input type="text" name="paypal_client_id" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['payment']['paypal_client_id']); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">PayPal Secret</label>
                                        <input type="password" name="paypal_secret" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['payment']['paypal_secret']); ?>">
                                    </div>
                                    
                                    <hr class="my-4">
                                    
           
