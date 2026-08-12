<?php
// ==============================================
// barber/dashboard.php - Barber Dashboard
// ==============================================
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn() || !isBarber()) {
    redirect('../login.php');
}

$stmt = $pdo->prepare("
    SELECT b.id as barber_id, b.specialty, b.experience_years,
           u.name, u.email, u.phone
    FROM barbers b
    JOIN users u ON b.user_id = u.id
    WHERE b.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$barber = $stmt->fetch();

if (!$barber) {
    die('Barber profile not found');
}
$barber_id = $barber['barber_id'];

$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT a.*, u.name as customer_name, s.name as service_name
    FROM appointments a
    JOIN users u ON a.customer_id = u.id
    JOIN services s ON a.service_id = s.id
    WHERE a.barber_id = ? AND a.appointment_date = ?
    ORDER BY a.appointment_time
");
$stmt->execute([$barber_id, $today]);
$today_appointments = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT a.*, u.name as customer_name, s.name as service_name
    FROM appointments a
    JOIN users u ON a.customer_id = u.id
    JOIN services s ON a.service_id = s.id
    WHERE a.barber_id = ? AND a.status = 'pending'
    ORDER BY a.appointment_date, a.appointment_time
    LIMIT 5
");
$stmt->execute([$barber_id]);
$pending_appointments = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM appointments WHERE barber_id = ? GROUP BY status");
$stmt->execute([$barber_id]);
$counts = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
while ($row = $stmt->fetch()) {
    $counts[$row['status']] = $row['count'];
}
$total = array_sum($counts);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'], $_POST['action'])) {
    $appointment_id = $_POST['appointment_id'];
    $action = $_POST['action'];
    $new_status = ($action === 'confirm') ? 'confirmed' : 'cancelled';
    $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ? AND barber_id = ?");
    if ($stmt->execute([$new_status, $appointment_id, $barber_id])) {
        $_SESSION['success'] = "Appointment " . ($action === 'confirm' ? 'confirmed' : 'declined');
        redirect('dashboard.php');
    }
}

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

$page_title = 'Barber Dashboard - Stylecut Nepal';
require_once '../includes/header.php';
?>

<h1 class="page-title">Barber Dashboard</h1>
<?php if ($success): ?><div class="success-message"><?php echo $success; ?></div><?php endif; ?>

<div class="welcome-banner">
    <div><h2>Welcome, <?php echo htmlspecialchars($barber['name']); ?>!</h2>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($barber['email']); ?></p>
        <p><strong>Phone:</strong> <?php echo htmlspecialchars($barber['phone'] ?? 'Not set'); ?></p>
    </div>
    <div class="banner-contact">
        <p><strong>Specialty:</strong> <?php echo htmlspecialchars($barber['specialty'] ?? 'General Barber'); ?></p>
        <p><strong>Experience:</strong> <?php echo $barber['experience_years'] ?? 1; ?> years</p>
    </div>
</div>

<div class="stats-grid-barber">
    <div class="stat-card"><div class="stat-number"><?php echo $counts['pending']; ?></div><div class="stat-label">Pending</div></div>
    <div class="stat-card"><div class="stat-number"><?php echo $counts['confirmed']; ?></div><div class="stat-label">Confirmed</div></div>
    <div class="stat-card"><div class="stat-number"><?php echo $counts['completed']; ?></div><div class="stat-label">Completed</div></div>
    <div class="stat-card"><div class="stat-number"><?php echo $counts['cancelled']; ?></div><div class="stat-label">Cancelled</div></div>
    <div class="stat-card highlight"><div class="stat-number"><?php echo $total; ?></div><div class="stat-label">Total</div></div>
</div>

<div class="actions-grid">
    <div class="action-card"><a href="appointments.php">📋 View Appointments</a></div>
    <div class="action-card"><a href="appointments.php?filter=pending">⏳ View Pending</a></div>
    <div class="action-card"><a href="../index.php">🏠 Back to Home</a></div>
</div>

<h2 class="section-title">Today's Appointments (<?php echo date('M d, Y'); ?>)</h2>
<?php if (count($today_appointments) > 0): foreach ($today_appointments as $appt): ?>
<div class="appointment-card">
    <div class="appointment-header"><span class="appointment-title"><?php echo htmlspecialchars($appt['customer_name']); ?></span>
        <span class="status-badge <?php echo getStatusBadgeClass($appt['status']); ?>"><?php echo strtoupper($appt['status']); ?></span>
    </div>
    <div class="appointment-details">
        <div><span class="appointment-detail-label">Service:</span><?php echo htmlspecialchars($appt['service_name']); ?></div>
        <div><span class="appointment-detail-label">Time:</span><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></div>
    </div>
</div>
<?php endforeach; else: ?>
<div class="empty-state"><p>No appointments scheduled for today.</p></div>
<?php endif; ?>

<h2 class="section-title">⏳ Pending Appointments</h2>
<?php if (count($pending_appointments) > 0): foreach ($pending_appointments as $appt): ?>
<div class="appointment-card">
    <div class="appointment-header"><span class="appointment-title"><?php echo htmlspecialchars($appt['customer_name']); ?></span><span class="status-badge status-pending">PENDING</span></div>
    <div class="appointment-details">
        <div><span class="appointment-detail-label">Service:</span><?php echo htmlspecialchars($appt['service_name']); ?></div>
        <div><span class="appointment-detail-label">Date:</span><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></div>
        <div><span class="appointment-detail-label">Time:</span><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></div>
        <?php if (!empty($appt['notes'])): ?><div><span class="appointment-detail-label">Notes:</span><?php echo htmlspecialchars($appt['notes']); ?></div><?php endif; ?>
    </div>
    <div class="appointment-actions">
        <form method="POST"><input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>"><input type="hidden" name="action" value="confirm"><button type="submit" class="btn btn-confirm">✓ Confirm</button></form>
        <form method="POST"><input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>"><input type="hidden" name="action" value="decline"><button type="submit" class="btn btn-decline">✗ Decline</button></form>
    </div>
</div>
<?php endforeach; else: ?>
<div class="empty-state"><p>No pending appointments.</p></div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>