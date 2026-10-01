<?php
// ==============================================
// logout.php - Logout Handler & Confirmation
// ==============================================
require_once 'config.php';
require_once 'functions.php';

// If user is not logged in, simply redirect to home page
if (!isLoggedIn()) {
    redirect('index.php');
}

// If confirmation is present (via GET confirm=1 or POST)
if (isset($_GET['confirm']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
    redirect('index.php?msg=logged_out');
}

// Fallback: Standalone Confirmation Page (for direct access or when JS is disabled)
$page_title = 'Confirm Logout - Stylecut Nepal';
$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? 'customer';

// Determine dashboard URL to return to if cancelled
$return_url = 'index.php';
if ($user_role === 'admin') {
    $return_url = 'admin/dashboard.php';
} elseif ($user_role === 'barber') {
    $return_url = 'barber/dashboard.php';
} elseif ($user_role === 'customer') {
    $return_url = 'customer/dashboard.php';
}

require_once 'includes/header.php';
?>

<div style="max-width: 480px; margin: 60px auto 80px; padding: 36px 30px; background: #ffffff; border: 3px solid #000000; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.12); text-align: center;">
    <div style="width: 72px; height: 72px; margin: 0 auto 18px; border-radius: 50%; background: #fef2f2; border: 2px solid #fecaca; display: flex; align-items: center; justify-content: center; font-size: 34px;">
        🚪
    </div>
    <h2 style="font-size: 24px; font-weight: 700; color: #000000; margin-bottom: 12px;">Confirm Logout</h2>
    <p style="font-size: 15px; color: #333333; line-height: 1.6; margin-bottom: 8px;">
        Are you sure you want to end your active session, <strong><?php echo htmlspecialchars($user_name); ?></strong>?
    </p>
    <p style="font-size: 13px; color: #666666; margin-bottom: 28px;">
        You will need to sign in again to manage your bookings or access your dashboard.
    </p>
    
    <div style="display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;">
        <a href="<?php echo htmlspecialchars($return_url); ?>" class="btn" style="flex: 1; min-width: 140px; padding: 12px 18px; text-decoration: none; border-radius: 6px; text-align: center;">
            ← No, Keep Me Logged In
        </a>
        <a href="logout.php?confirm=1" class="btn" style="flex: 1; min-width: 140px; padding: 12px 18px; background: #dc2626; border-color: #dc2626; color: #ffffff; text-decoration: none; border-radius: 6px; text-align: center;">
            Yes, Log Out
        </a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>