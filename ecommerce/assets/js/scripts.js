// Main JavaScript File

$(document).ready(function() {
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Initialize popovers
    $('[data-bs-toggle="popover"]').popover();
    
    // Add to cart functionality
    $('.add-to-cart').click(function(e) {
        e.preventDefault();
        addToCart($(this));
    });
    
    // Add to wishlist functionality
    $('.add-to-wishlist').click(function(e) {
        e.preventDefault();
        addToWishlist($(this));
    });
    
    // Quantity increment/decrement
    $('.quantity-btn').click(function() {
        var input = $(this).siblings('.quantity-input');
        var currentVal = parseInt(input.val());
        
        if ($(this).hasClass('increment')) {
            input.val(currentVal + 1);
        } else if ($(this).hasClass('decrement') && currentVal > 1) {
            input.val(currentVal - 1);
        }
    });
    
    // Image zoom on hover
    $('.product-image-zoom').hover(function() {
        $(this).css('transform', 'scale(1.1)');
    }, function() {
        $(this).css('transform', 'scale(1)');
    });
    
    // Form validation
    $('form.needs-validation').on('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        $(this).addClass('was-validated');
    });
    
    // Auto-dismiss alerts
    $('.alert-dismissible').delay(3000).fadeOut('slow');
    
    // Smooth scroll
    $('a[href^="#"]').click(function(e) {
        e.preventDefault();
        var target = $(this.hash);
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 100
            }, 1000);
        }
    });
    
    // Price range slider
    if ($('#priceRange').length) {
        var priceSlider = document.getElementById('priceRange');
        noUiSlider.create(priceSlider, {
            start: [0, 1000],
            connect: true,
            range: {
                'min': 0,
                'max': 1000
            },
            format: {
                to: function(value) {
                    return '$' + Math.round(value);
                },
                from: function(value) {
                    return Number(value.replace('$', ''));
                }
            }
        });
        
        var priceValues = [
            document.getElementById('priceMin'),
            document.getElementById('priceMax')
        ];
        
        priceSlider.noUiSlider.on('update', function(values, handle) {
            priceValues[handle].value = values[handle];
        });
    }
    
    // Product filter toggle
    $('.filter-toggle').click(function() {
        $('.product-filters').toggleClass('show');
    });
});

// Add to cart function
function addToCart(button) {
    var productId = button.data('product-id');
    var variationId = button.data('variation-id') || null;
    var quantity = button.data('quantity') || 1;
    
    $.ajax({
        url: '/ecommerce/api/cart.php?action=add',
        method: 'POST',
        data: {
            product_id: productId,
            variation_id: variationId,
            quantity: quantity
        },
        success: function(response) {
            var result = JSON.parse(response);
            if (result.success) {
                updateCartCount(result.cart_count);
                showAlert('Product added to cart!', 'success');
            } else {
                showAlert(result.error, 'danger');
            }
        },
        error: function() {
            showAlert('Error adding to cart. Please try again.', 'danger');
        }
    });
}

// Add to wishlist function
function addToWishlist(button) {
    var productId = button.data('product-id');
    
    $.ajax({
        url: '/ecommerce/api/wishlist.php?action=add',
        method: 'POST',
        data: { product_id: productId },
        success: function(response) {
            var result = JSON.parse(response);
            if (result.success) {
                showAlert('Added to wishlist!', 'success');
            } else {
                showAlert(result.error, 'danger');
            }
        }
    });
}

// Update cart count in navbar
function updateCartCount(count) {
    var cartBadge = $('.cart-badge');
    if (cartBadge.length) {
        cartBadge.text(count);
    } else {
        $('.fa-shopping-cart').after('<span class="badge bg-danger rounded-pill cart-badge">'+count+'</span>');
    }
}

// Show alert message
function showAlert(message, type) {
    var alertHtml = '<div class="alert alert-'+type+' alert-dismissible fade show position-fixed top-0 end-0 m-3" style="z-index: 9999;" role="alert">' +
                   message +
                   '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                   '</div>';
    $('body').append(alertHtml);
    
    setTimeout(function() {
        $('.alert').alert('close');
    }, 3000);
}

// Load more products
function loadMoreProducts(page) {
    $.ajax({
        url: '/ecommerce/api/products.php?page=' + page,
        method: 'GET',
        success: function(response) {
            var result = JSON.parse(response);
            if (result.success) {
                // Append new products
                // Update pagination
            }
        }
    });
}

// Search products
function searchProducts(query) {
    $.ajax({
        url: '/ecommerce/api/products.php?search=' + encodeURIComponent(query),
        method: 'GET',
        success: function(response) {
            var result = JSON.parse(response);
            if (result.success) {
                // Display search results
            }
        }
    });
}

// Calculate discount
function calculateDiscount(price, discountType, discountValue) {
    if (discountType === 'percentage') {
        return price * (discountValue / 100);
    } else {
        return discountValue;
    }
}

// Format price
function formatPrice(price) {
    return '$' + parseFloat(price).toFixed(2);
}

// Product image gallery
function initImageGallery() {
    $('.product-thumbnail').click(function() {
        var mainImage = $('#mainProductImage');
        var newSrc = $(this).data('image');
        
        mainImage.fadeOut(200, function() {
            $(this).attr('src', newSrc).fadeIn(200);
        });
        
        $('.product-thumbnail').removeClass('active');
        $(this).addClass('active');
    });
}

// Initialize when document is ready
$(function() {
    initImageGallery();
});
