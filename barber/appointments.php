<?php
// ==============================================
// barber/appointments.php - View All Appointments
// ==============================================
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn() || !isBarber()) {
    redirect('../login.php');
}

$barber_id = getBarberIdByUserId($pdo, $_SESSION['user_id']);
if (!$barber_id) {
    die('Barber profile not found');
}

$filter = $_GET['filter'] ?? 'all';

$sql = "
    SELECT a.*, u.name as customer_name, u.phone as customer_phone,
           s.name as service_name,
           p.payment_method, p.payment_status, p.screenshot_path, p.amount as payment_amount
    FROM appointments a
    JOIN users u ON a.customer_id = u.id
    JOIN services s ON a.service_id = s.id
    LEFT JOIN payments p ON a.id = p.appointment_id
    WHERE a.barber_id = :barber_id
";
if ($filter !== 'all') {
    $sql .= " AND a.status = :filter";
}
$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $pdo->prepare($sql);
$params = [':barber_id' => $barber_id];
if ($filter !== 'all') {
    $params[':filter'] = $filter;
}
$stmt->execute($params);
$appointments = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'], $_POST['action'])) {
    $appointment_id = $_POST['appointment_id'];
   $action = $_POST['action'];

$new_status = '';

if ($action === 'confirm') {
    $new_status = 'confirmed';
} elseif ($action === 'complete') {
    $new_status = 'completed';
} elseif ($action === 'cancel' || $action === 'decline') {
    $new_status = 'cancelled';
}

$stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ? AND barber_id = ?");

if ($stmt->execute([$new_status, $appointment_id, $barber_id])) {

    $message = [
    'confirm' => 'confirmed',
    'complete' => 'marked as completed',
    'decline' => 'declined',
    'cancel' => 'cancelled'
];

    $_SESSION['success'] = "Appointment " . $message[$action];

    redirect('appointments.php?filter=' . $filter);
}
}

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

$page_title = 'Appointments - Barber';
require_once '../includes/header.php';
?>

<h1 class="page-title">📋 Appointments</h1>

<div class="filter-container">
    <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
    <a href="?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
    <a href="?filter=confirmed" class="filter-btn <?php echo $filter === 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
    <a href="?filter=completed" class="filter-btn <?php echo $filter === 'completed' ? 'active' : ''; ?>">Completed</a>
    <a href="?filter=cancelled" class="filter-btn <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
</div>

<?php if ($success): ?><div class="success-message"><?php echo $success; ?></div><?php endif; ?>

<?php if (count($appointments) > 0): foreach ($appointments as $appt): ?>
<div class="appointment-card">
    <div class="appointment-header">
        <span class="appointment-title"><?php echo htmlspecialchars($appt['customer_name']); ?></span>
        <span class="status-badge <?php echo getStatusBadgeClass($appt['status']); ?>"><?php echo strtoupper($appt['status']); ?></span>
    </div>
    <div class="appointment-details">
        <div><span class="appointment-detail-label">Service:</span><?php echo htmlspecialchars($appt['service_name']); ?></div>
        <div><span class="appointment-detail-label">Date:</span><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></div>
        <div><span class="appointment-detail-label">Time:</span><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></div>
        <div><span class="appointment-detail-label">Phone:</span><?php echo htmlspecialchars($appt['customer_phone'] ?? 'N/A'); ?></div>
        <div><span class="appointment-detail-label">Price:</span>NPR <?php echo number_format($appt['price_at_booking'], 2); ?></div>
        <?php if ($appt['payment_method']): ?><div><span class="appointment-detail-label">Payment:</span><?php echo ucfirst($appt['payment_method']); ?> (<?php echo $appt['payment_status']; ?>)</div><?php endif; ?>
    </div>
    <?php if (!empty($appt['notes'])): ?><div class="appointment-notes"><strong>Notes:</strong> <?php echo htmlspecialchars($appt['notes']); ?></div><?php endif; ?>

    <?php 
    $screenshot_path = $appt['screenshot_path'] ?? '';
    if ($screenshot_path):
        $full_url = '/stylecut/' . $screenshot_path;
        $full_abs_path = $_SERVER['DOCUMENT_ROOT'] . '/stylecut/' . $screenshot_path;
        $screenshot_exists = file_exists($full_abs_path);
    ?>
    <div class="payment-screenshot">
        <strong>Payment Screenshot:</strong>
        <?php if ($screenshot_exists): ?>
            <a href="<?php echo $full_url; ?>" target="_blank" class="btn btn-small">View Full Screenshot</a>
            <div style="margin-top:10px;"><img src="<?php echo $full_url; ?>" alt="Screenshot" onclick="window.open('<?php echo $full_url; ?>', '_blank')" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"><p style="display:none; color:red;">Not found</p></div>
        <?php else: ?>
            <p style="color:red;">⚠️ File missing (path: <?php echo htmlspecialchars($screenshot_path); ?>)</p>
        <?php endif; ?>
    </div>
    <?php elseif ($appt['payment_method'] && in_array($appt['payment_method'], ['esewa', 'bank'])): ?>
        <div class="payment-screenshot"><p style="color:orange;">⚠️ No screenshot uploaded</p></div>
    <?php endif; ?>

    <?php if ($appt['status'] === 'pending'): ?>

<div class="appointment-actions">

    <form method="POST">
        <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
        <input type="hidden" name="action" value="confirm">
        <button type="submit" class="btn btn-confirm">✓ Confirm</button>
    </form>

    <form method="POST">
        <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
        <input type="hidden" name="action" value="decline">
        <button type="submit" class="btn btn-decline">✗ Decline</button>
    </form>

</div>

<?php elseif ($appt['status'] === 'confirmed'): ?>

<div class="appointment-actions">

    <!-- Complete Button -->
    <form method="POST">
        <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
        <input type="hidden" name="action" value="complete">
        <button type="submit" class="btn btn-confirm">
            ✓ Mark Completed
        </button>
    </form>

    <!-- Cancel Button -->
    <form method="POST">
        <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
        <input type="hidden" name="action" value="cancel">
        <button type="submit" class="btn btn-decline">
            ✗ Cancel
        </button>
    </form>

</div>

<?php endif; ?>

</div>

<?php endforeach; else: ?>

<div class="empty-state">
    <p>No appointments found.</p>
</div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>