<?php
// product.php - Product Detail Page
require_once 'includes/header.php';

if (!isset($_GET['id'])) {
    header("Location: /ecommerce/index.php");
    exit();
}

$productId = intval($_GET['id']);

// Fetch product details
$productStmt = $pdo->prepare("
    SELECT p.*, v.shop_name, v.shop_slug, v.rating as vendor_rating, 
           v.total_sales as vendor_sales, c.name as category_name, c.slug as category_slug
    FROM products p
    LEFT JOIN vendors v ON p.vendor_id = v.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ? AND p.status = 'active'
");

$productStmt->execute([$productId]);
$product = $productStmt->fetch();

if (!$product) {
    echo "<div class='container py-5 text-center'>
            <h2>Product Not Found</h2>
            <p>The product you're looking for doesn't exist or has been removed.</p>
            <a href='/ecommerce/index.php' class='btn btn-primary'>Continue Shopping</a>
          </div>";
    require_once 'includes/footer.php';
    exit();
}

// Increment product views
$viewStmt = $pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
$viewStmt->execute([$productId]);

// Fetch product images
$imageStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order");
$imageStmt->execute([$productId]);
$images = $imageStmt->fetchAll();

// Fetch product variations
$variationStmt = $pdo->prepare("SELECT * FROM product_variations WHERE product_id = ? AND status = 'active'");
$variationStmt->execute([$productId]);
$variations = $variationStmt->fetchAll();

// Fetch related products
$relatedStmt = $pdo->prepare("
    SELECT p.*, 
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM products p
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
    LIMIT 4
");
$relatedStmt->execute([$product['category_id'], $productId]);
$relatedProducts = $relatedStmt->fetchAll();

// Fetch reviews
$reviewStmt = $pdo->prepare("
    SELECT r.*, u.username, u.avatar
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.product_id = ? AND r.is_approved = 1
    ORDER BY r.created_at DESC
    LIMIT 5
");
$reviewStmt->execute([$productId]);
$reviews = $reviewStmt->fetchAll();

// Calculate average rating
$avgRatingStmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE product_id = ? AND is_approved = 1");
$avgRatingStmt->execute([$productId]);
$ratingData = $avgRatingStmt->fetch();
?>

<div class="container py-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/ecommerce/index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="/ecommerce/search.php">Products</a></li>
            <?php if($product['category_name']): ?>
            <li class="breadcrumb-item"><a href="/ecommerce/search.php?category=<?php echo $product['category_id']; ?>">
                <?php echo htmlspecialchars($product['category_name']); ?>
            </a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($product['name']); ?></li>
        </ol>
    </nav>
    
    <div class="row g-5">
        <!-- Product Images -->
        <div class="col-lg-6">
            <div class="row">
                <!-- Main Image -->
                <div class="col-12 mb-4">
                    <div class="border rounded-3 p-3">
                        <img id="mainProductImage" 
                             src="/ecommerce/<?php echo $images[0]['image_url'] ?? 'assets/images/placeholder.jpg'; ?>" 
                             class="img-fluid rounded-3" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             style="max-height: 500px; width: 100%; object-fit: contain;">
                    </div>
                </div>
                
                <!-- Thumbnails -->
                <?php if(count($images) > 1): ?>
                <div class="col-12">
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach($images as $image): ?>
                        <a href="javascript:void(0)" class="thumbnail" data-image="/ecommerce/<?php echo $image['image_url']; ?>">
                            <img src="/ecommerce/<?php echo $image['image_url']; ?>" 
                                 class="img-thumbnail" 
                                 style="width: 80px; height: 80px; object-fit: cover;"
                                 alt="Thumbnail">
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Product Info -->
        <div class="col-lg-6">
            <!-- Vendor Info -->
            <div class="d-flex align-items-center mb-3">
                <small class="text-muted me-3">Sold by:</small>
                <a href="/ecommerce/vendor.php?id=<?php echo $product['vendor_id']; ?>" 
                   class="text-decoration-none d-flex align-items-center">
                    <span class="fw-bold text-primary"><?php echo htmlspecialchars($product['shop_name']); ?></span>
                    <span class="badge bg-light text-dark ms-2">
                        <i class="fas fa-star text-warning"></i> <?php echo number_format($product['vendor_rating'], 1); ?>
                    </span>
                    <span class="badge bg-light text-dark ms-2">
                        <i class="fas fa-shopping-bag"></i> <?php echo $product['vendor_sales']; ?> sales
                    </span>
                </a>
            </div>
            
            <!-- Product Title -->
            <h1 class="fw-bold mb-3"><?php echo htmlspecialchars($product['name']); ?></h1>
            
            <!-- Rating -->
            <div class="d-flex align-items-center mb-3">
                <div class="rating me-3">
                    <?php
                    $rating = $ratingData['avg_rating'] ?? $product['rating'];
                    $fullStars = floor($rating);
                    $hasHalfStar = ($rating - $fullStars) >= 0.5;
                    ?>
                    <?php for($i = 1; $i <= 5; $i++): ?>
                        <?php if($i <= $fullStars): ?>
                            <i class="fas fa-star text-warning"></i>
                        <?php elseif($hasHalfStar && $i == $fullStars + 1): ?>
                            <i class="fas fa-star-half-alt text-warning"></i>
                        <?php else: ?>
                            <i class="far fa-star text-warning"></i>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <span class="text-muted">(<?php echo $ratingData['total_reviews'] ?? $product['total_reviews']; ?> reviews)</span>
                <span class="ms-3 text-muted">
                    <i class="fas fa-eye me-1"></i> <?php echo $product['views'] + 1; ?> views
                </span>
            </div>
            
            <!-- Price -->
            <div class="price-section mb-4">
                <h2 class="text-primary fw-bold">$<?php echo number_format($product['price'], 2); ?></h2>
                <?php if($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                <div class="d-flex align-items-center">
                    <span class="text-muted text-decoration-line-through me-2">$<?php echo number_format($product['compare_price'], 2); ?></span>
                    <span class="badge bg-danger">
                        Save <?php echo number_format((($product['compare_price'] - $product['price']) / $product['compare_price']) * 100, 0); ?>%
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Short Description -->
            <?php if($product['short_description']): ?>
            <div class="mb-4">
                <h5 class="fw-bold mb-2">Quick Overview</h5>
                <p class="text-muted"><?php echo nl2br(htmlspecialchars($product['short_description'])); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Product Form -->
            <form id="addToCartForm" class="mb-4">
                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                
                <?php if($product['has_variants'] && count($variations) > 0): ?>
                <!-- Variations -->
                <div class="mb-4">
                    <h5 class="fw-bold mb-3">Available Options</h5>
                    <div class="row g-3">
                        <?php
                        // Group variations by options
                        $options = [];
                        foreach($variations as $var) {
                            if($var['option1']) $options['option1'][] = $var['option1'];
                            if($var['option2']) $options['option2'][] = $var['option2'];
                            if($var['option3']) $options['option3'][] = $var['option3'];
                        }
                        
                        $optionCount = 1;
                        foreach($options as $optionKey => $optionValues): 
                            $uniqueValues = array_unique($optionValues);
                        ?>
                        <div class="col-md-4">
                            <label class="form-label"><?php echo ucfirst(str_replace('option', 'Option ', $optionKey)); ?></label>
                            <select name="<?php echo $optionKey; ?>" class="form-select variation-select" data-option="<?php echo $optionCount; ?>">
                                <option value="">Select <?php echo str_replace('option', '', $optionKey); ?></option>
                                <?php foreach($uniqueValues as $value): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php 
                        $optionCount++;
                        endforeach; 
                        ?>
                    </div>
                    
                    <!-- Variation Details (Hidden until selection) -->
                    <div id="variationDetails" class="mt-3 p-3 border rounded" style="display: none;">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h6 id="variationName" class="fw-bold"></h6>
                                <div class="price">
                                    <span id="variationPrice" class="fw-bold text-primary fs-4"></span>
                                    <span id="variationComparePrice" class="text-muted text-decoration-line-through ms-2"></span>
                                </div>
                                <div class="availability mt-2">
                                    <span id="variationStock" class="badge"></span>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <input type="hidden" id="selectedVariationId" name="variation_id">
                                <div class="input-group mb-3" style="max-width: 200px; margin-left: auto;">
                                    <button class="btn btn-outline-secondary" type="button" id="decrementQty">-</button>
                                    <input type="number" name="quantity" id="quantity" class="form-control text-center" value="1" min="1" max="100">
                                    <button class="btn btn-outline-secondary" type="button" id="incrementQty">+</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Simple Product -->
                <div class="row g-3 align-items-center mb-4">
                    <div class="col-auto">
                        <label class="form-label fw-bold">Quantity</label>
                    </div>
                    <div class="col-auto">
                        <div class="input-group" style="width: 150px;">
                            <button class="btn btn-outline-secondary" type="button" id="decrementQty">-</button>
                            <input type="number" name="quantity" id="quantity" class="form-control text-center" value="1" min="1" max="<?php echo $product['quantity']; ?>">
                            <button class="btn btn-outline-secondary" type="button" id="incrementQty">+</button>
                        </div>
                    </div>
                    <div class="col-auto">
                        <span class="text-muted">
                            <?php if($product['quantity'] > 0): ?>
                            <span class="badge bg-success">In Stock (<?php echo $product['quantity']; ?> available)</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Out of Stock</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <button type="submit" class="btn btn-primary btn-lg flex-grow-1 add-to-cart-btn" 
                            <?php echo ($product['quantity'] == 0 && !$product['has_variants']) ? 'disabled' : ''; ?>>
                        <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-lg add-to-wishlist" 
                            data-product-id="<?php echo $product['id']; ?>">
                        <i class="far fa-heart me-2"></i>Wishlist
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-share-alt me-2"></i>Share
                    </button>
                </div>
            </form>
            
            <!-- Product Meta -->
            <div class="row g-3 mb-4">
                <?php if($product['sku']): ?>
                <div class="col-md-6">
                    <div class="d-flex">
                        <span class="text-muted me-2">SKU:</span>
                        <span class="fw-bold"><?php echo htmlspecialchars($product['sku']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if($product['category_name']): ?>
                <div class="col-md-6">
                    <div class="d-flex">
                        <span class="text-muted me-2">Category:</span>
                        <span class="fw-bold">
                            <a href="/ecommerce/search.php?category=<?php echo $product['category_id']; ?>" 
                               class="text-decoration-none">
                                <?php echo htmlspecialchars($product['category_name']); ?>
                            </a>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Share & Guarantee -->
            <div class="border-top pt-4">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 class="fw-bold mb-2">Guaranteed Safe Checkout</h6>
                        <img src="/ecommerce/assets/images/payment-methods.png" alt="Payment Methods" class="img-fluid" style="max-height: 40px;">
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-2">Share this product</h6>
                        <div class="social-share">
                            <a href="#" class="btn btn-outline-primary btn-sm me-2"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="btn btn-outline-info btn-sm me-2"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="btn btn-outline-danger btn-sm me-2"><i class="fab fa-pinterest"></i></a>
                            <a href="#" class="btn btn-outline-success btn-sm"><i class="fab fa-whatsapp"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Product Tabs -->
    <div class="row mt-5">
        <div class="col-12">
            <ul class="nav nav-tabs" id="productTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button">
                        Description
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="specifications-tab" data-bs-toggle="tab" data-bs-target="#specifications" type="button">
                        Specifications
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button">
                        Reviews (<?php echo $ratingData['total_reviews'] ?? 0; ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="shipping-tab" data-bs-toggle="tab" data-bs-target="#shipping" type="button">
                        Shipping & Returns
                    </button>
                </li>
            </ul>
            
            <div class="tab-content p-4 border border-top-0 rounded-bottom" id="productTabContent">
                <!-- Description Tab -->
                <div class="tab-pane fade show active" id="description" role="tabpanel">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </div>
                
                <!-- Specifications Tab -->
                <div class="tab-pane fade" id="specifications" role="tabpanel">
                    <div class="row">
                        <?php if($product['weight']): ?>
                        <div class="col-md-6 mb-3">
                            <strong>Weight:</strong> <?php echo $product['weight']; ?> kg
                        </div>
                        <?php endif; ?>
                        <?php if($product['length']): ?>
                        <div class="col-md-6 mb-3">
                            <strong>Dimensions:</strong> <?php echo $product['length']; ?> x <?php echo $product['width']; ?> x <?php echo $product['height']; ?> cm
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Reviews Tab -->
                <div class="tab-pane fade" id="reviews" role="tabpanel">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center p-4 border rounded mb-4">
                                <h2 class="text-primary fw-bold"><?php echo number_format($ratingData['avg_rating'] ?? 0, 1); ?></h2>
                                <div class="rating mb-2 justify-content-center">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <?php if($i <= floor($ratingData['avg_rating'] ?? 0)): ?>
                                            <i class="fas fa-star text-warning"></i>
                                        <?php else: ?>
                                            <i class="far fa-star text-warning"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                <p class="text-muted">Based on <?php echo $ratingData['total_reviews'] ?? 0; ?> reviews</p>
                            </div>
                            
                            <?php if(isLoggedIn()): ?>
                            <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#reviewModal">
                                Write a Review
                            </button>
                            <?php else: ?>
                            <a href="/ecommerce/login.php" class="btn btn-outline-primary w-100">Login to Review</a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-8">
                            <?php if(count($reviews) > 0): ?>
                                <?php foreach($reviews as $review): ?>
                                <div class="review-item border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong><?php echo htmlspecialchars($review['username']); ?></strong>
                                            <div class="rating">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <?php if($i <= $review['rating']): ?>
                                                        <i class="fas fa-star text-warning"></i>
                                                    <?php else: ?>
                                                        <i class="far fa-star text-warning"></i>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></small>
                                    </div>
                                    <?php if($review['title']): ?>
                                    <h6 class="fw-bold"><?php echo htmlspecialchars($review['title']); ?></h6>
                                    <?php endif; ?>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <p class="text-muted text-center py-4">No reviews yet. Be the first to review this product!</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Shipping Tab -->
                <div class="tab-pane fade" id="shipping" role="tabpanel">
                    <h5>Shipping Information</h5>
                    <ul>
                        <li>Free shipping on orders over $50</li>
                        <li>Standard shipping: 3-5 business days</li>
                        <li>Express shipping: 1-2 business days (additional charge)</li>
                        <li>International shipping available</li>
                    </ul>
                    
                    <h5 class="mt-4">Return Policy</h5>
                    <ul>
                        <li>30-day return policy</li>
                        <li>Items must be in original condition</li>
                        <li>Return shipping paid by customer</li>
                        <li>Refunds processed within 5-7 business days</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Related Products -->
    <?php if(count($relatedProducts) > 0): ?>
    <div class="row mt-5">
        <div class="col-12">
            <h3 class="fw-bold mb-4">You May Also Like</h3>
            <div class="row g-4">
                <?php foreach($relatedProducts as $related): ?>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="product-card card h-100 shadow-sm">
                        <a href="/ecommerce/product.php?id=<?php echo $related['id']; ?>">
                            <img src="/ecommerce/<?php echo $related['primary_image'] ?: 'assets/images/placeholder.jpg'; ?>" 
                                 class="card-img-top" 
                                 alt="<?php echo htmlspecialchars($related['name']); ?>"
                                 style="height: 200px; object-fit: cover;">
                        </a>
                        
                        <div class="card-body">
                            <h6 class="card-title">
                                <a href="/ecommerce/product.php?id=<?php echo $related['id']; ?>" 
                                   class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars($related['name']); ?>
                                </a>
                            </h6>
                            
                            <div class="price mb-2">
                                <span class="fw-bold text-primary">$<?php echo number_format($related['price'], 2); ?></span>
                            </div>
                            
                            <button class="btn btn-sm btn-primary w-100 add-to-cart" 
                                    data-product-id="<?php echo $related['id']; ?>">
                                Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Write a Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="reviewForm">
                <div class="modal-body">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Rating</label>
                        <div class="rating-input">
                            <input type="hidden" name="rating" id="selectedRating" value="5">
                            <div class="stars">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star fa-2x star" data-value="<?php echo $i; ?>" style="cursor: pointer; color: #ffc107;"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" placeholder="Summary of your review">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Your Review</label>
                        <textarea name="comment" class="form-control" rows="4" placeholder="Share your experience with this product"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Thumbnail click
    $('.thumbnail').click(function() {
        var imageUrl = $(this).data('image');
        $('#mainProductImage').attr('src', imageUrl);
        $('.thumbnail').find('img').removeClass('border-primary');
        $(this).find('img').addClass('border-primary');
    });
    
    // Quantity controls
    $('#incrementQty').click(function() {
        var qty = parseInt($('#quantity').val());
        var max = <?php echo $product['has_variants'] ? '100' : $product['quantity']; ?>;
        if (qty < max) {
            $('#quantity').val(qty + 1);
        }
    });
    
    $('#decrementQty').click(function() {
        var qty = parseInt($('#quantity').val());
        if (qty > 1) {
            $('#quantity').val(qty - 1);
        }
    });
    
    // Variation selection
    <?php if($product['has_variants']): ?>
    var variations = <?php echo json_encode($variations); ?>;
    
    $('.variation-select').change(function() {
        var option1 = $('select[name="option1"]').val();
        var option2 = $('select[name="option2"]').val();
        var option3 = $('select[name="option3"]').val();
        
        // Find matching variation
        var selectedVariation = null;
        $.each(variations, function(index, variation) {
            if ((!option1 || variation.option1 === option1) &&
                (!option2 || variation.option2 === option2) &&
                (!option3 || variation.option3 === option3)) {
                selectedVariation = variation;
                return false; // break loop
            }
        });
        
        if (selectedVariation) {
            $('#variationDetails').show();
            $('#variationName').text(selectedVariation.option1 + ' ' + (selectedVariation.option2 || '') + ' ' + (selectedVariation.option3 || ''));
            $('#variationPrice').text('$' + parseFloat(selectedVariation.price).toFixed(2));
            
            if (selectedVariation.compare_price) {
                $('#variationComparePrice').text('$' + parseFloat(selectedVariation.compare_price).toFixed(2));
            } else {
                $('#variationComparePrice').text('');
            }
            
            if (selectedVariation.quantity > 0) {
                $('#variationStock').removeClass('bg-danger').addClass('bg-success').text('In Stock (' + selectedVariation.quantity + ' available)');
                $('#quantity').attr('max', selectedVariation.quantity);
                $('.add-to-cart-btn').prop('disabled', false);
            } else {
                $('#variationStock').removeClass('bg-success').addClass('bg-danger').text('Out of Stock');
                $('.add-to-cart-btn').prop('disabled', true);
            }
            
            $('#selectedVariationId').val(selectedVariation.id);
        } else {
            $('#variationDetails').hide();
            $('.add-to-cart-btn').prop('disabled', true);
        }
    });
    <?php endif; ?>
    
    // Star rating in review modal
    $('.star').hover(function() {
        var value = $(this).data('value');
        $('.star').each(function(i) {
            if (i < value) {
                $(this).addClass('fas').removeClass('far');
            } else {
                $(this).addClass('far').removeClass('fas');
            }
        });
    });
    
    $('.star').click(function() {
        var value = $(this).data('value');
        $('#selectedRating').val(value);
    });
    
    // Add to cart form submission
    $('#addToCartForm').submit(function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        
        $.ajax({
            url: '/ecommerce/api/cart.php?action=add',
            method: 'POST',
            data: formData,
            success: function(response) {
                var result = JSON.parse(response);
                if (result.success) {
                    // Update cart count
                    $('.cart-badge').text(result.cart_count);
                    if ($('.cart-badge').length === 0) {
                        $('.fa-shopping-cart').after('<span class="badge bg-danger rounded-pill cart-badge">'+result.cart_count+'</span>');
                    }
                    
                    // Show success message
                    alert('Product added to cart successfully!');
                } else {
                    alert('Error: ' + result.message);
                }
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
