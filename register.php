<?php
// ==============================================
// register.php - Registration Page with Validation
// ==============================================
require_once 'config.php';
require_once 'functions.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $role = $_POST['role'] ?? 'customer';
    $barber_password = $_POST['barber_password'] ?? '';

    if (empty($name) || empty($email) || empty($password) || empty($phone) || empty($address)) {

    $error = 'All fields are required';

} elseif (!preg_match("/^[A-Za-z ]+$/", $name)) {

    $error = 'Full name must contain letters only';

} elseif (!preg_match("/^[a-zA-Z0-9._%+-]+@(gmail\.com|yahoo\.com|outlook\.com)$/", $email)) {

    $error = 'Please use a Gmail, Yahoo, or Outlook email address';

} elseif (strlen($password) < 8) {

    $error = 'Password must be at least 8 characters';

} elseif (!preg_match('/^9[678]\d{8}$/', $phone)) {

    $error = 'Please enter a valid 10-digit Nepali mobile number';

} elseif (strlen(trim($address)) < 5) {

    $error = 'Please enter a valid address';

} elseif ($role === 'barber' && $barber_password !== 'Stylecut123') {

    $error = 'Invalid barber registration password';

} else {

        try {

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->rowCount() > 0) {

                $error = 'Email already registered';

            } else {

                $pdo->beginTransaction();

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $bonus_points = ($role === 'customer') ? 10 : 0;

                $stmt = $pdo->prepare("
                    INSERT INTO users
                    (name, email, password, phone, address, role, bonus_points, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");

                if ($stmt->execute([
                    $name,
                    $email,
                    $hashed_password,
                    $phone,
                    $address,
                    $role,
                    $bonus_points
                ])) {

                    $user_id = $pdo->lastInsertId();

                    if ($role === 'barber') {

                        $barber_stmt = $pdo->prepare("
                            INSERT INTO barbers
                            (user_id, specialty, experience_years, bio, is_available)
                            VALUES (?, 'General Barber', 1, 'New barber', 1)
                        ");

                        $barber_stmt->execute([$user_id]);
                    }

                    $pdo->commit();

                    $success = 'Registration successful! You can now login.';

                } else {

                    $pdo->rollBack();
                    $error = 'Registration failed.';

                }

            }

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$page_title = 'Register - Stylecut Nepal';
require_once 'includes/header.php';
?>

<div class="form-container">

    <h1 class="page-title">📝 Create Account</h1>

    <?php if ($error): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>

        <div class="success-message">
            <?php echo htmlspecialchars($success); ?>
        </div>

        <div class="text-center" style="margin-top:20px;">
            <a href="login.php" class="btn btn-large">
                Login Now
            </a>
        </div>

    <?php else: ?>

    <form method="POST">

        <div class="form-group">
            <label>Full Name *</label>
            <input
    type="text"
    name="name"
    required
    pattern="[A-Za-z ]+"
    title="Full name should contain letters and spaces only"
    value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
        </div>

        <div class="form-group">
    <label>Email *</label>
    <input
    type="email"
    name="email"
    required
    pattern="[a-zA-Z0-9._%+-]+@(gmail\.com|yahoo\.com|outlook\.com)"
    title="Use a Gmail, Yahoo, or Outlook email address"
    placeholder="yourname@gmail.com"
    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
</div>

        <div class="form-group">
    <label>Password *</label>
    <input
        type="password"
        name="password"
        required
        minlength="8"
        maxlength="20"
        title="Password must be at least 8 characters">
</div>

        <div class="form-group">
    <label>Phone *</label>
    <input
        type="text"
        name="phone"
        required
        pattern="9[678][0-9]{8}"
        minlength="10"
        maxlength="10"
        title="Enter a valid 10-digit Nepali mobile number"
        placeholder="98XXXXXXXX"
        value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
</div>

        <div class="form-group">
    <label>Address *</label>
    <textarea
        name="address"
        rows="3"
        required
        minlength="5"
        maxlength="100"
        placeholder="Enter your address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
</div>

        <div class="form-group">

            <label>I am a:</label>

            <select name="role" id="role">

                <option value="customer"
                    <?php echo ($role ?? 'customer') == 'customer' ? 'selected' : ''; ?>>
                    Customer
                </option>

                <option value="barber"
                    <?php echo ($role ?? '') == 'barber' ? 'selected' : ''; ?>>
                    Barber
                </option>

            </select>

        </div>

        <div class="form-group" id="barberPasswordGroup" style="display:none;">

            <label>Barber Registration Password *</label>

            <input
                type="password"
                name="barber_password"
                id="barber_password"
                placeholder="Enter barber registration password">

        </div>

        <button type="submit" class="btn btn-large btn-block">
            Register
        </button>

    </form>

    <div class="register-link" style="margin-top:20px;">

        Already have an account?

        <a href="login.php">Login here</a>

    </div>

    <?php endif; ?>

</div>

<script>

const role = document.getElementById('role');
const barberGroup = document.getElementById('barberPasswordGroup');

function toggleBarberPassword(){

    if(role.value === 'barber'){

        barberGroup.style.display = 'block';

    }else{

        barberGroup.style.display = 'none';

    }

}

toggleBarberPassword();

role.addEventListener('change', toggleBarberPassword);

</script>

<?php require_once 'includes/footer.php'; ?>