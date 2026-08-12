<?php
// ==============================================
// login.php - Login Page
// ==============================================
require_once 'config.php';
require_once 'functions.php';

if (isLoggedIn()) {
    if (isCustomer()) redirect('customer/dashboard.php');
    if (isBarber()) redirect('barber/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'customer';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter email and password';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
        $stmt->execute([$email, $role]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Check password (supports hashed and plain text)
            $password_match = false;
            if (strlen($user['password']) == 60 && strpos($user['password'], '$2y$') === 0) {
                $password_match = password_verify($password, $user['password']);
            } else {
                $password_match = ($password === $user['password']);
            }
            
            if ($password_match) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['bonus_points'] = $user['bonus_points'];
                
                if ($user['role'] === 'barber') {
                    redirect('barber/dashboard.php');
                } else {
                    redirect('customer/dashboard.php');
                }
            } else {
                $error = 'Invalid email or password';
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}

$page_title = 'Login - Stylecut Nepal';
require_once 'includes/header.php';
?>

<div class="login-container">
    <h1 class="page-title">Login</h1>
    
    <?php if ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="" class="login-form">
        <input type="hidden" name="role" id="selectedRole" value="customer">
        
        <div class="role-selector">
            <button type="button" id="customerRoleBtn" class="role-btn active">Customer</button>
            <button type="button" id="barberRoleBtn" class="role-btn">Barber</button>
        </div>
        
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required placeholder="Enter your email">
        </div>
        
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="Enter your password">
        </div>
        
        <button type="submit" class="btn btn-large btn-block">Login</button>
        
        <div class="register-link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </form>
</div>

<script>
const customerBtn = document.getElementById('customerRoleBtn');
const barberBtn = document.getElementById('barberRoleBtn');
const roleInput = document.getElementById('selectedRole');

customerBtn.addEventListener('click', function() {
    customerBtn.classList.add('active');
    barberBtn.classList.remove('active');
    roleInput.value = 'customer';
});

barberBtn.addEventListener('click', function() {
    barberBtn.classList.add('active');
    customerBtn.classList.remove('active');
    roleInput.value = 'barber';
});
</script>

<?php require_once 'includes/footer.php'; ?>