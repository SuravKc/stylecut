<?php
// ==============================================
// admin/view-booking.php - Detailed Booking View
// ==============================================
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$appointment_id = intval($_GET['id'] ?? 0);
if (!$appointment_id) {
    $_SESSION['error'] = 'Invalid appointment ID.';
    redirect('appointments.php');
}

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action === 'confirm') {
            updateAppointmentStatusAdmin($pdo, $appointment_id, 'confirmed');
            $_SESSION['success'] = "Appointment #$appointment_id approved successfully!";
        } elseif ($action === 'decline') {
            updateAppointmentStatusAdmin($pdo, $appointment_id, 'cancelled');
            $_SESSION['success'] = "Appointment #$appointment_id declined and bonus points refunded if applicable.";
        } elseif ($action === 'complete') {
            updateAppointmentStatusAdmin($pdo, $appointment_id, 'completed');
            $_SESSION['success'] = "Appointment #$appointment_id marked as completed!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Action failed: " . $e->getMessage();
    }

    redirect("view-booking.php?id=$appointment_id");
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Query full appointment details
$stmt = $pdo->prepare("
    SELECT a.*, 
           cu.name as customer_name, cu.email as customer_email, cu.phone as customer_phone, cu.address as customer_address,
           bu.name as barber_name, bu.email as barber_email, bu.phone as barber_phone,
           b.specialty as barber_specialty, b.experience_years as barber_experience,
           s.name as service_name, s.description as service_desc, s.duration_minutes,
           p.id as payment_id, p.payment_method, p.payment_status, p.screenshot_path, p.amount as payment_amount, p.paid_at, p.transaction_id
    FROM appointments a
    JOIN users cu ON a.customer_id = cu.id
    JOIN barbers b ON a.barber_id = b.id
    JOIN users bu ON b.user_id = bu.id
    JOIN services s ON a.service_id = s.id
    LEFT JOIN payments p ON a.id = p.appointment_id
    WHERE a.id = ?
");
$stmt->execute([$appointment_id]);
$appt = $stmt->fetch();

if (!$appt) {
    $_SESSION['error'] = "Appointment #$appointment_id not found.";
    redirect('appointments.php');
}

$page_title = "Booking Details #$appointment_id - Admin";
require_once '../includes/header.php';
?>

<div class="booking-details-container">
    <div class="details-header">
        <h1 class="page-title" style="border-bottom: none; margin-bottom: 0; padding-bottom: 0;">
            📋 Appointment #<?php echo $appt['id']; ?>
        </h1>
        <a href="appointments.php" class="btn btn-back">← Back to All Bookings</a>
    </div>

    <?php if ($success): ?>
        <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Status Banner -->
    <div class="status-banner">
        <div>
            <span class="status-label">Current Status:</span>
            <span class="status-badge <?php echo getStatusBadgeClass($appt['status']); ?>" style="font-size: 15px;">
                <?php echo strtoupper($appt['status']); ?>
            </span>
        </div>
        <div>
            <strong>Booked On:</strong> <?php echo date('F d, Y \a\t h:i A', strtotime($appt['created_at'])); ?>
        </div>
    </div>

    <!-- Action Bar for Admin -->
    <div style="background: #f8f9fa; border: 2px solid #000; padding: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h3 style="margin-bottom: 5px;">Admin Controls</h3>
            <p style="margin: 0; font-size: 14px; color: #555;">Approve, decline, or update the status of this customer appointment.</p>
        </div>
        <div class="action-btn-group">
            <?php if ($appt['status'] === 'pending'): ?>
                <form method="POST" onsubmit="return confirm('Approve this appointment?');">
                    <input type="hidden" name="action" value="confirm">
                    <button type="submit" class="btn-action-approve" style="font-size: 15px; padding: 10px 20px;">
                        ✓ Approve Appointment
                    </button>
                </form>

                <form method="POST" onsubmit="return confirm('Decline this appointment? Customer bonus points will be refunded.');">
                    <input type="hidden" name="action" value="decline">
                    <button type="submit" class="btn-action-decline" style="font-size: 15px; padding: 10px 20px;">
                        ✗ Decline Appointment
                    </button>
                </form>

            <?php elseif ($appt['status'] === 'confirmed'): ?>
                <form method="POST" onsubmit="return confirm('Mark this appointment as completed?');">
                    <input type="hidden" name="action" value="complete">
                    <button type="submit" class="btn-action-complete" style="font-size: 15px; padding: 10px 20px;">
                        ✓ Mark Completed
                    </button>
                </form>

                <form method="POST" onsubmit="return confirm('Cancel this confirmed appointment? Customer bonus points will be refunded.');">
                    <input type="hidden" name="action" value="decline">
                    <button type="submit" class="btn-action-decline" style="font-size: 15px; padding: 10px 20px;">
                        ✗ Cancel Appointment
                    </button>
                </form>

            <?php else: ?>
                <form method="POST" onsubmit="return confirm('Re-open this appointment to Confirmed?');">
                    <input type="hidden" name="action" value="confirm">
                    <button type="submit" class="btn btn-small">
                        Re-open as Confirmed
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Details Grid -->
    <div class="grid-2" style="margin-bottom: 30px;">
        <!-- Customer Profile Card -->
        <div class="details-card">
            <h2 class="card-title">👤 Customer Profile</h2>
            <div class="details-grid">
                <div class="detail-item">
                    <span class="detail-label">Name:</span>
                    <span class="detail-value"><strong><?php echo htmlspecialchars($appt['customer_name']); ?></strong></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value">
                        <?php if (!empty($appt['customer_phone'])): ?>
                            <a href="tel:<?php echo htmlspecialchars($appt['customer_phone']); ?>" style="color: #000; font-weight: bold;">
                                📞 <?php echo htmlspecialchars($appt['customer_phone']); ?>
                            </a>
                        <?php else: ?>
                            Not provided
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">
                        <a href="mailto:<?php echo htmlspecialchars($appt['customer_email']); ?>" style="color: #000;">
                            ✉️ <?php echo htmlspecialchars($appt['customer_email']); ?>
                        </a>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Address:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($appt['customer_address'] ?? 'Not set'); ?></span>
                </div>
            </div>
        </div>

        <!-- Assigned Barber Card -->
        <div class="details-card">
            <h2 class="card-title">✂️ Assigned Barber</h2>
            <div class="details-grid">
                <div class="detail-item">
                    <span class="detail-label">Barber:</span>
                    <span class="detail-value"><strong><?php echo htmlspecialchars($appt['barber_name']); ?></strong></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Specialty:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($appt['barber_specialty'] ?? 'Hair Stylist'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Phone:</span>
                    <span class="detail-value">
                        <?php if (!empty($appt['barber_phone'])): ?>
                            <a href="tel:<?php echo htmlspecialchars($appt['barber_phone']); ?>" style="color: #000;">
                                📞 <?php echo htmlspecialchars($appt['barber_phone']); ?>
                            </a>
                        <?php else: ?>
                            Not set
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Experience:</span>
                    <span class="detail-value"><?php echo $appt['barber_experience'] ?? 1; ?> years</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Appointment & Service Details -->
    <div class="details-card" style="margin-bottom: 30px;">
        <h2 class="card-title">📅 Schedule & Service Details</h2>
        <div class="details-grid">
            <div class="detail-item">
                <span class="detail-label">Service:</span>
                <span class="detail-value"><strong><?php echo htmlspecialchars($appt['service_name']); ?></strong></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Duration:</span>
                <span class="detail-value"><?php echo $appt['duration_minutes']; ?> minutes</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Appointment Date:</span>
                <span class="detail-value"><strong><?php echo date('l, F d, Y', strtotime($appt['appointment_date'])); ?></strong></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Time Slot:</span>
                <span class="detail-value"><strong><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></strong></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Price at Booking:</span>
                <span class="detail-value">NPR <?php echo number_format($appt['price_at_booking'], 2); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Bonus Points Used:</span>
                <span class="detail-value"><?php echo $appt['bonus_used'] ?? 0; ?> pts</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Bonus Points Earned:</span>
                <span class="detail-value"><?php echo $appt['bonus_earned'] ?? 10; ?> pts</span>
            </div>
            <?php if (!empty($appt['notes'])): ?>
            <div class="detail-item full-width">
                <span class="detail-label">Customer Special Request / Notes:</span>
                <span class="detail-value" style="font-style: italic; background: #fffdf5; padding: 10px; border-left: 3px solid #000;">
                    <?php echo nl2br(htmlspecialchars($appt['notes'])); ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment & Verification -->
    <div class="details-card" style="margin-bottom: 30px;">
        <h2 class="card-title">💳 Payment & Verification</h2>
        <div class="details-grid">
            <div class="detail-item">
                <span class="detail-label">Method:</span>
                <span class="detail-value">
                    <span class="payment-badge <?php echo htmlspecialchars($appt['payment_method'] ?? 'cash'); ?>">
                        <?php echo ucfirst($appt['payment_method'] ?? 'cash on arrival'); ?>
                    </span>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Payment Status:</span>
                <span class="detail-value">
                    <strong><?php echo strtoupper($appt['payment_status'] ?? 'PENDING'); ?></strong>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Amount:</span>
                <span class="detail-value">
                    <strong>NPR <?php echo number_format($appt['payment_amount'] ?? $appt['price_at_booking'], 2); ?></strong>
                </span>
            </div>
            <?php if ($appt['paid_at']): ?>
            <div class="detail-item">
                <span class="detail-label">Paid Timestamp:</span>
                <span class="detail-value"><?php echo date('M d, Y h:i A', strtotime($appt['paid_at'])); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Screenshot Review -->
        <?php 
        $screenshot_path = $appt['screenshot_path'] ?? '';
        if ($screenshot_path):
            $full_url = '/stylecut/' . $screenshot_path;
            $full_abs_path = $_SERVER['DOCUMENT_ROOT'] . '/stylecut/' . $screenshot_path;
            $screenshot_exists = file_exists($full_abs_path);
        ?>
        <div class="screenshot-section">
            <h3 class="screenshot-title">📸 Attached Payment Screenshot</h3>
            <?php if ($screenshot_exists): ?>
                <div class="screenshot-preview">
                    <a href="<?php echo $full_url; ?>" target="_blank" class="btn btn-small">Open Full Size in New Tab</a>
                    <div class="screenshot-thumb">
                        <img src="<?php echo $full_url; ?>" alt="Payment Screenshot" onclick="window.open('<?php echo $full_url; ?>', '_blank')" style="max-width: 400px; border: 3px solid #000; cursor: pointer;">
                    </div>
                </div>
            <?php else: ?>
                <p class="error-text">⚠️ Screenshot file not found on disk (path: <?php echo htmlspecialchars($screenshot_path); ?>)</p>
            <?php endif; ?>
        </div>
        <?php elseif ($appt['payment_method'] && in_array($appt['payment_method'], ['esewa', 'bank'])): ?>
            <div class="screenshot-section">
                <p class="warning-text">⚠️ No payment screenshot uploaded by customer for this digital transaction.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
