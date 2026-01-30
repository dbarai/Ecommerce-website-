<?php
// includes/footer.php
?>
    </main>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h4 class="mb-4">OmniMart</h4>
                    <p>Your one-stop multi-vendor e-commerce platform. Buy from thousands of trusted vendors.</p>
                    <div class="social-icons mt-3">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin fa-lg"></i></a>
                    </div>
                </div>
                
                <div class="col-md-2 mb-4">
                    <h5 class="mb-4">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="/ecommerce/index.php" class="text-white-50 text-decoration-none">Home</a></li>
                        <li><a href="/ecommerce/search.php" class="text-white-50 text-decoration-none">Products</a></li>
                        <li><a href="/ecommerce/about.php" class="text-white-50 text-decoration-none">About Us</a></li>
                        <li><a href="/ecommerce/contact.php" class="text-white-50 text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                
                <div class="col-md-3 mb-4">
                    <h5 class="mb-4">Customer Service</h5>
                    <ul class="list-unstyled">
                        <li><a href="/ecommerce/faq.php" class="text-white-50 text-decoration-none">FAQ</a></li>
                        <li><a href="/ecommerce/shipping.php" class="text-white-50 text-decoration-none">Shipping Policy</a></li>
                        <li><a href="/ecommerce/returns.php" class="text-white-50 text-decoration-none">Return Policy</a></li>
                        <li><a href="/ecommerce/privacy.php" class="text-white-50 text-decoration-none">Privacy Policy</a></li>
                    </ul>
                </div>
                
                <div class="col-md-3 mb-4">
                    <h5 class="mb-4">Contact Info</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-map-marker-alt me-2"></i> 123 Business Street, City, Country</li>
                        <li><i class="fas fa-phone me-2"></i> +1 (555) 123-4567</li>
                        <li><i class="fas fa-envelope me-2"></i> support@omnimart.com</li>
                    </ul>
                </div>
            </div>
            
            <hr class="bg-light">
            
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> OmniMart. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-end">
                    <img src="/ecommerce/assets/images/payment-methods.png" alt="Payment Methods" height="30">
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script src="/ecommerce/assets/js/main.js"></script>
    
    <script>
        // Add to cart functionality
        $(document).ready(function() {
            $('.add-to-cart').click(function(e) {
                e.preventDefault();
                var productId = $(this).data('product-id');
                var variationId = $(this).data('variation-id') || null;
                var quantity = $(this).data('quantity') || 1;
                
                $.ajax({
                    url: '/ecommerce/api/cart.php',
                    method: 'POST',
                    data: {
                        action: 'add',
                        product_id: productId,
                        variation_id: variationId,
                        quantity: quantity
                    },
                    success: function(response) {
                        var result = JSON.parse(response);
                        if (result.success) {
                            // Update cart count
                            $('.cart-badge').text(result.cart_count);
                            if ($('.cart-badge').length === 0) {
                                $('.fa-shopping-cart').after('<span class="badge bg-danger rounded-pill cart-badge">'+result.cart_count+'</span>');
                            }
                            
                            // Show success message
                            showAlert('Product added to cart!', 'success');
                        } else {
                            showAlert(result.message, 'danger');
                        }
                    }
                });
            });
            
            function showAlert(message, type) {
                var alertHtml = '<div class="alert alert-'+type+' alert-dismissible fade show position-fixed top-0 end-0 m-3" role="alert">' +
                               message +
                               '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                               '</div>';
                $('body').append(alertHtml);
                setTimeout(function() {
                    $('.alert').alert('close');
                }, 3000);
            }
        });
    </script>
</body>
</html>
