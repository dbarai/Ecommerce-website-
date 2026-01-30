
<?php
// login.php
require_once 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validate input
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Check user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on role
            if ($user['role'] === 'admin') {
                header('Location: /ecommerce/admin/dashboard.php');
            } elseif ($user['role'] === 'vendor') {
                header('Location: /ecommerce/vendor/dashboard.php');
            } else {
                header('Location: /ecommerce/index.php');
            }
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <h2>Login</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
        <p class="mt-3">Don't have an account? <a href="/ecommerce/signup.php">Sign up here</a>.</p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
