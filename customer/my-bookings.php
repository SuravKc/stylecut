<?php
// ==============================================
// customer/my-bookings.php - View All Bookings
// ==============================================

require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../login.php');
}

$filter = $_GET['filter'] ?? 'all';

// Get customer bonus points
$stmt = $pdo->prepare("SELECT bonus_points FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get appointments
$sql = "
    SELECT
        a.*,
        s.name AS service_name,
        u.name AS barber_name,
        p.payment_method,
        p.payment_status
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN barbers b ON a.barber_id = b.id
    JOIN users u ON b.user_id = u.id
    LEFT JOIN payments p ON a.id = p.appointment_id
    WHERE a.customer_id = :customer_id
";

if ($filter !== 'all') {
    $sql .= " AND a.status = :filter";
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $pdo->prepare($sql);

if ($filter !== 'all') {
    $stmt->execute([
        ':customer_id' => $_SESSION['user_id'],
        ':filter' => $filter
    ]);
} else {
    $stmt->execute([
        ':customer_id' => $_SESSION['user_id']
    ]);
}

$appointments = $stmt->fetchAll();

// Count appointments by status
$count_stmt = $pdo->prepare("
    SELECT status, COUNT(*) AS count
    FROM appointments
    WHERE customer_id = ?
    GROUP BY status
");

$count_stmt->execute([$_SESSION['user_id']]);

$counts = [
    'pending' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0
];

while ($row = $count_stmt->fetch()) {
    $counts[$row['status']] = $row['count'];
}

$page_title = 'My Bookings - Stylecut Nepal';
require_once '../includes/header.php';
?>

<h1 class="page-title">📅 My Bookings</h1>

<div class="bonus-card">
    <div>
        <strong>Your Bonus Points:</strong>
        <span class="bonus-points">
            <?php echo $user['bonus_points'] ?? 0; ?>
        </span>
    </div>
    <div>1 point = NPR 1 discount</div>
</div>

<div class="filter-container">
    <a href="?filter=all" class="filter-btn <?php echo ($filter == 'all') ? 'active' : ''; ?>">
        All (<?php echo array_sum($counts); ?>)
    </a>

    <a href="?filter=pending" class="filter-btn <?php echo ($filter == 'pending') ? 'active' : ''; ?>">
        Pending (<?php echo $counts['pending']; ?>)
    </a>

    <a href="?filter=confirmed" class="filter-btn <?php echo ($filter == 'confirmed') ? 'active' : ''; ?>">
        Confirmed (<?php echo $counts['confirmed']; ?>)
    </a>

    <a href="?filter=completed" class="filter-btn <?php echo ($filter == 'completed') ? 'active' : ''; ?>">
        Completed (<?php echo $counts['completed']; ?>)
    </a>

    <a href="?filter=cancelled" class="filter-btn <?php echo ($filter == 'cancelled') ? 'active' : ''; ?>">
        Cancelled (<?php echo $counts['cancelled']; ?>)
    </a>
</div>

<?php
$success = $_SESSION['success'] ?? $_SESSION['payment_success'] ?? '';
$error = $_SESSION['error'] ?? $_SESSION['payment_error'] ?? '';

unset(
    $_SESSION['success'],
    $_SESSION['error'],
    $_SESSION['payment_success'],
    $_SESSION['payment_error']
);
?>

<?php if ($success): ?>
    <div class="success-message">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error-message">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if (!empty($appointments)): ?>

    <?php foreach ($appointments as $appt): ?>

        <div class="appointment-card">

            <div class="appointment-header">
                <div>
                    <span class="appointment-title">
                        <a href="view-booking.php?id=<?php echo $appt['id']; ?>">
                            <?php echo htmlspecialchars($appt['service_name']); ?>
                        </a>
                    </span>

                    <span class="status-badge <?php echo getStatusBadgeClass($appt['status']); ?>">
                        <?php echo strtoupper($appt['status']); ?>
                    </span>
                </div>

                <div class="appointment-date">
                    Booked:
                    <?php echo date('M d, Y', strtotime($appt['created_at'])); ?>
                </div>
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

                <?php if (!empty($appt['payment_method'])): ?>
                    <div class="appointment-detail-item">
                        <span class="appointment-detail-label">Payment:</span>
                        <?php echo ucfirst($appt['payment_method']); ?>
                        (<?php echo htmlspecialchars($appt['payment_status']); ?>)
                    </div>
                <?php endif; ?>

            </div>

            <div class="appointment-actions">

                <a href="view-booking.php?id=<?php echo $appt['id']; ?>" class="btn btn-small">
                    View Details
                </a>

                <?php if ($appt['status'] == 'pending' || $appt['status'] == 'confirmed'): ?>

                    <a href="cancel-booking.php?id=<?php echo $appt['id']; ?>"
                       class="btn btn-small btn-decline"
                       onclick="return confirm('Cancel this appointment?')">
                        Cancel
                    </a>

                <?php endif; ?>

            </div>

        </div>

    <?php endforeach; ?>

<?php else: ?>

    <div class="empty-state">
        <p>No appointments found.</p>

        <a href="booking.php" class="btn btn-large">
            Book Your First Appointment
        </a>
    </div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>