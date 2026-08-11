<?php
// ==============================================
// customer/view-booking.php - View Single Booking
// ==============================================
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../login.php');
}

$appointment_id = $_GET['id'] ?? 0;
if (!$appointment_id) {
    $_SESSION['error'] = 'No appointment specified';
    redirect('my-bookings.php');
}

$stmt = $pdo->prepare("
    SELECT a.*, s.name as service_name, s.duration_minutes,
           u.name as barber_name,
           p.payment_method, p.payment_status, p.screenshot_path, p.amount as payment_amount, p.paid_at
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN barbers b ON a.barber_id = b.id
    JOIN users u ON b.user_id = u.id
    LEFT JOIN payments p ON a.id = p.appointment_id
    WHERE a.id = ? AND a.customer_id = ?
");
$stmt->execute([$appointment_id, $_SESSION['user_id']]);
$appointment = $stmt->fetch();

if (!$appointment) {
    $_SESSION['error'] = 'Appointment not found';
    redirect('my-bookings.php');
}

$page_title = 'Booking Details - Stylecut Nepal';
require_once '../includes/header.php';
?>

<div class="booking-details-container">
    <div class="details-header">
        <h1 class="page-title">📋 Booking Details</h1>
        <a href="my-bookings.php" class="btn btn-back">← Back to My Bookings</a>
    </div>

    <div class="status-banner">
        <div>
            <span class="status-label">Status:</span>
            <span class="status-badge <?php echo getStatusBadgeClass($appointment['status']); ?>">
                <?php echo strtoupper($appointment['status']); ?>
            </span>
        </div>
        <div><strong>Booked on:</strong> <?php echo date('F d, Y \a\t h:i A', strtotime($appointment['created_at'])); ?></div>
    </div>

    <div class="details-card">
        <h2 class="card-title">Appointment Summary</h2>
        <div class="details-grid">
            <div class="detail-item"><span class="detail-label">Service:</span><span class="detail-value"><?php echo htmlspecialchars($appointment['service_name']); ?></span></div>
            <div class="detail-item"><span class="detail-label">Barber:</span><span class="detail-value"><?php echo htmlspecialchars($appointment['barber_name']); ?></span></div>
            <div class="detail-item"><span class="detail-label">Date:</span><span class="detail-value"><?php echo date('l, F d, Y', strtotime($appointment['appointment_date'])); ?></span></div>
            <div class="detail-item"><span class="detail-label">Time:</span><span class="detail-value"><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></span></div>
            <div class="detail-item"><span class="detail-label">Duration:</span><span class="detail-value"><?php echo $appointment['duration_minutes']; ?> min</span></div>
            <div class="detail-item"><span class="detail-label">Price:</span><span class="detail-value">NPR <?php echo number_format($appointment['price_at_booking'], 2); ?></span></div>
            <?php if ($appointment['bonus_used'] > 0): ?>
            <div class="detail-item"><span class="detail-label">Bonus used:</span><span class="detail-value"><?php echo $appointment['bonus_used']; ?> pts</span></div>
            <?php endif; ?>
            <div class="detail-item"><span class="detail-label">Bonus earned:</span><span class="detail-value"><?php echo $appointment['bonus_earned'] ?? 10; ?> pts</span></div>
            <?php if (!empty($appointment['notes'])): ?>
            <div class="detail-item full-width"><span class="detail-label">Notes:</span><span class="detail-value"><?php echo htmlspecialchars($appointment['notes']); ?></span></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($appointment['payment_method']): ?>
    <div class="details-card">
        <h2 class="card-title">Payment Information</h2>
        <div class="details-grid">
            <div class="detail-item"><span class="detail-label">Method:</span><span class="detail-value"><?php echo ucfirst($appointment['payment_method']); ?></span></div>
            <div class="detail-item"><span class="detail-label">Status:</span><span class="detail-value payment-status <?php echo $appointment['payment_status']; ?>"><?php echo strtoupper($appointment['payment_status']); ?></span></div>
            <div class="detail-item"><span class="detail-label">Amount:</span><span class="detail-value">NPR <?php echo number_format($appointment['payment_amount'] ?? $appointment['price_at_booking'], 2); ?></span></div>
            <?php if ($appointment['paid_at']): ?>
            <div class="detail-item"><span class="detail-label">Paid on:</span><span class="detail-value"><?php echo date('F d, Y \a\t h:i A', strtotime($appointment['paid_at'])); ?></span></div>
            <?php endif; ?>
        </div>

        <?php 
        $screenshot_path = $appointment['screenshot_path'] ?? '';
        if ($screenshot_path):
            $full_url = '/stylecut/' . $screenshot_path;
            $full_abs_path = $_SERVER['DOCUMENT_ROOT'] . '/stylecut/' . $screenshot_path;
            $screenshot_exists = file_exists($full_abs_path);
        ?>
        <div class="screenshot-section">
            <h3 class="screenshot-title">Payment Screenshot</h3>
            <?php if ($screenshot_exists): ?>
                <div class="screenshot-preview">
                    <a href="<?php echo $full_url; ?>" target="_blank" class="btn btn-small">View Full Screenshot</a>
                    <div class="screenshot-thumb">
                        <img src="<?php echo $full_url; ?>" alt="Payment Screenshot" onclick="window.open('<?php echo $full_url; ?>', '_blank')" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <p class="error-text" style="display: none;">File not found</p>
                    </div>
                </div>
            <?php else: ?>
                <p class="error-text">⚠️ Screenshot file missing.</p>
            <?php endif; ?>
        </div>
        <?php elseif ($appointment['payment_method'] && in_array($appointment['payment_method'], ['esewa', 'bank'])): ?>
            <div class="screenshot-section"><p class="warning-text">⚠️ No screenshot uploaded for this <?php echo $appointment['payment_method']; ?> payment.</p></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($appointment['status'] === 'pending' || $appointment['status'] === 'confirmed'): ?>
    <div class="details-actions">
        <a href="cancel-booking.php?id=<?php echo $appointment['id']; ?>" class="btn btn-decline" onclick="return confirm('Cancel this appointment?')">Cancel Appointment</a>
        <a href="booking.php" class="btn">Book Another</a>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>