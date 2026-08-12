<?php
// ==============================================
// customer/profile.php - Update Profile
// ==============================================
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../login.php');
}

$user = getUserById($pdo, $_SESSION['user_id']);
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    if (empty($name)) {
        $error = 'Name cannot be empty';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?");
        if ($stmt->execute([$name, $phone, $address, $_SESSION['user_id']])) {
            $_SESSION['user_name'] = $name;
            $success = 'Profile updated successfully';
            $user = getUserById($pdo, $_SESSION['user_id']);
        } else {
            $error = 'Update failed';
        }
    }
}

$page_title = 'My Profile - Stylecut Nepal';
require_once '../includes/header.php';
?>

<h1 class="page-title">👤 My Profile</h1>
<?php if ($success): ?><div class="success-message"><?php echo $success; ?></div><?php endif; ?>
<?php if ($error): ?><div class="error-message"><?php echo $error; ?></div><?php endif; ?>

<div class="form-container">
    <form method="POST">
        <div class="form-group"><label>Email (cannot change)</label><input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled></div>
        <div class="form-group"><label>Full Name *</label><input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required></div>
        <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"></div>
        <div class="form-group"><label>Address</label><textarea name="address" rows="4"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea></div>
        <div class="form-group"><label>Member Since</label><input type="text" value="<?php echo date('F d, Y', strtotime($user['created_at'])); ?>" disabled></div>
        <div class="form-group"><label>Bonus Points</label><input type="text" value="<?php echo $user['bonus_points'] ?? 0; ?> points" disabled></div>
        <button type="submit" class="btn btn-large btn-block">Update Profile</button>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>