<?php
// ==============================================
// login.php - Login Page
// ==============================================
require_once 'config.php';
require_once 'functions.php';

if (isLoggedIn()) {
    if (isAdmin()) redirect('admin/dashboard.php');
    if (isCustomer()) redirect('customer/dashboard.php');
    if (isBarber()) redirect('barber/dashboard.php');
}

$error = '';
$redirect_target = $_GET['redirect'] ?? ($_POST['redirect'] ?? '');
if (!empty($redirect_target)) {
    if (strpos($redirect_target, '://') !== false || strpos($redirect_target, '//') === 0) {
        $redirect_target = '';
    }
}

$notice = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'login_required') {
    $notice = 'Please log in to your account or register to complete your appointment booking.';
}

$initial_role = $_GET['role'] ?? ($_POST['role'] ?? 'customer');
if (!in_array($initial_role, ['customer', 'barber', 'admin'])) {
    $initial_role = 'customer';
}

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
                
                if ($user['role'] === 'admin') {
                    redirect('admin/dashboard.php');
                } elseif ($user['role'] === 'barber') {
                    redirect('barber/dashboard.php');
                } else {
                    if (!empty($redirect_target)) {
                        redirect($redirect_target);
                    } else {
                        redirect('customer/dashboard.php');
                    }
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
    
    <?php if ($notice): ?>
        <div style="background: #fffbeb; border: 2px solid #f59e0b; color: #92400e; padding: 12px 15px; margin-bottom: 20px; font-weight: 600; font-size: 14px;">
            ℹ️ <?php echo htmlspecialchars($notice); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="" class="login-form">
        <input type="hidden" name="role" id="selectedRole" value="<?php echo htmlspecialchars($initial_role); ?>">
        <?php if (!empty($redirect_target)): ?>
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_target); ?>">
        <?php endif; ?>
        
        <div class="role-selector">
            <button type="button" id="customerRoleBtn" class="role-btn <?php echo $initial_role === 'customer' ? 'active' : ''; ?>">Customer</button>
            <button type="button" id="barberRoleBtn" class="role-btn <?php echo $initial_role === 'barber' ? 'active' : ''; ?>">Barber</button>
            <button type="button" id="adminRoleBtn" class="role-btn <?php echo $initial_role === 'admin' ? 'active' : ''; ?>">Admin</button>
        </div>
        
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
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
const adminBtn = document.getElementById('adminRoleBtn');
const roleInput = document.getElementById('selectedRole');

function selectRole(role) {
    roleInput.value = role;
    customerBtn.classList.toggle('active', role === 'customer');
    barberBtn.classList.toggle('active', role === 'barber');
    adminBtn.classList.toggle('active', role === 'admin');
}

customerBtn.addEventListener('click', function() { selectRole('customer'); });
barberBtn.addEventListener('click', function() { selectRole('barber'); });
adminBtn.addEventListener('click', function() { selectRole('admin'); });
</script>

<?php require_once 'includes/footer.php'; ?>