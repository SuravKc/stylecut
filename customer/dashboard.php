<?php
// ==============================================
// customer/dashboard.php - Customer Dashboard
// ==============================================
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../login.php');
}

$user = getUserById($pdo, $_SESSION['user_id']);

$stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count 
    FROM appointments 
    WHERE customer_id = ? 
    GROUP BY status
");
$stmt->execute([$_SESSION['user_id']]);
$counts = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
while ($row = $stmt->fetch()) {
    $counts[$row['status']] = $row['count'];
}
$completed_cancelled = ($counts['completed'] ?? 0) + ($counts['cancelled'] ?? 0);

$stmt = $pdo->prepare("
    SELECT a.*, s.name as service_name, u.name as barber_name
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN barbers b ON a.barber_id = b.id
    JOIN users u ON b.user_id = u.id
    WHERE a.customer_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$appointments = $stmt->fetchAll();

$page_title = 'Customer Dashboard - Stylecut Nepal';
require_once '../includes/header.php';
?>

<h1 class="page-title">Customer Dashboard</h1>

<div class="welcome-banner">
    <div>
        <h2>Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h2>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        <?php if (!empty($user['phone'])): ?>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone']); ?></p>
        <?php endif; ?>
    </div>
    <div class="banner-contact">
        <p><strong>Member since:</strong> <?php echo date('M Y', strtotime($user['created_at'])); ?></p>
    </div>
</div>

<div class="stats-grid-customer">
    <div class="stat-card highlight">
        <div class="stat-number"><?php echo $user['bonus_points'] ?? 0; ?></div>
        <div class="stat-label">Bonus Points</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $counts['pending']; ?></div>
        <div class="stat-label">Pending</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $counts['confirmed']; ?></div>
        <div class="stat-label">Confirmed</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $completed_cancelled; ?></div>
        <div class="stat-label">Completed/Cancelled</div>
    </div>
</div>

<div class="actions-grid">
    <div class="action-card">
        <a href="booking.php">📅 Book an Appointment</a>
    </div>
    <div class="action-card">
        <a href="my-bookings.php">📋 View All Bookings</a>
    </div>
    <div class="action-card">
        <a href="profile.php">👤 Update Profile</a>
    </div>
</div>

<h2 class="section-title">Recent Appointments</h2>

<?php if (count($appointments) > 0): ?>
    <?php foreach ($appointments as $appt): ?>
        <div class="appointment-card">
            <div class="appointment-header">
                <span class="appointment-title"><?php echo htmlspecialchars($appt['service_name']); ?></span>
                <span class="status-badge <?php echo getStatusBadgeClass($appt['status']); ?>">
                    <?php echo strtoupper($appt['status']); ?>
                </span>
            </div>
            <div class="appointment-details">
                <div class="appointment-detail-item">
                    <span class="appointment-detail-label">Barber:</span>
                    <?php echo htmlspecialchars($appt['barber_name']); ?>
                </div>
                <div class="appointment-detail-item">
                    <span class="appointment-detail-label">Date:</span>
                    <?php echo formatDate($appt['appointment_date']); ?>
                </div>
                <div class="appointment-detail-item">
                    <span class="appointment-detail-label">Time:</span>
                    <?php echo formatTime($appt['appointment_time']); ?>
                </div>
                <div class="appointment-detail-item">
                    <span class="appointment-detail-label">Price:</span>
                    NPR <?php echo number_format($appt['price_at_booking'], 2); ?>
                </div>
            </div>
            <div class="appointment-actions">
                <a href="view-booking.php?id=<?php echo $appt['id']; ?>" class="btn btn-small">View Details</a>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="text-center" style="margin-top: 20px;">
        <a href="my-bookings.php" class="btn">View All Appointments</a>
    </div>
<?php else: ?>
    <div class="empty-state">
        <p>You haven't booked any appointments yet.</p>
        <a href="booking.php" class="btn btn-large">Book Your First Appointment</a>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>