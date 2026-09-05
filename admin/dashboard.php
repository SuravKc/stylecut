<?php
// ==============================================
// admin/dashboard.php - Admin Dashboard
// ==============================================
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$user = getUserById($pdo, $_SESSION['user_id']);

// Handle quick approve / decline actions from dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'], $_POST['action'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $action = $_POST['action'];

    try {
        if ($action === 'confirm') {
            updateAppointmentStatusAdmin($pdo, $appointment_id, 'confirmed');
            $_SESSION['success'] = "Appointment #$appointment_id has been approved successfully!";
        } elseif ($action === 'decline') {
            updateAppointmentStatusAdmin($pdo, $appointment_id, 'cancelled');
            $_SESSION['success'] = "Appointment #$appointment_id has been declined (bonus points refunded if used).";
        } elseif ($action === 'complete') {
            updateAppointmentStatusAdmin($pdo, $appointment_id, 'completed');
            $_SESSION['success'] = "Appointment #$appointment_id marked as completed!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Action failed: " . $e->getMessage();
    }

    redirect('dashboard.php');
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Get Dashboard Statistics
$stats = getAdminDashboardStats($pdo);

// Entity Counts
$total_services = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE is_active = 1")->fetchColumn();
$total_barbers = (int)$pdo->query("SELECT COUNT(*) FROM barbers b JOIN users u ON b.user_id = u.id WHERE u.is_active = 1")->fetchColumn();
$total_customers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND is_active = 1")->fetchColumn();

// Get Pending Appointments requiring attention
$pending_appointments = getAllAppointmentsAdmin($pdo, ['status' => 'pending'], 5);

// Get Today's Appointments
$today_date = date('Y-m-d');
$today_appointments = getAllAppointmentsAdmin($pdo, ['date' => $today_date], 5);

$page_title = 'Admin Dashboard - Stylecut Nepal';
require_once '../includes/header.php';
?>

<h1 class="page-title">⚙️ Admin Control Center</h1>

<?php if ($success): ?>
    <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Welcome & Profile Banner -->
<div class="welcome-banner">
    <div>
        <h2>Welcome, <?php echo htmlspecialchars($user['name'] ?? 'Administrator'); ?>!</h2>
        <p><strong>System Role:</strong> Master Administrator &nbsp;|&nbsp; <strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
        <p>Manage all barber schedules, monitor booking payments, and approve or decline appointments in real time.</p>
    </div>
    <div class="banner-contact">
        <p><strong>Today's Date:</strong> <?php echo date('l, M d, Y'); ?></p>
        <p><strong>Total System Bookings:</strong> <?php echo $stats['total']; ?></p>
    </div>
</div>

<!-- Urgent Pending Banner if bookings are waiting -->
<?php if ($stats['pending'] > 0): ?>
<div class="urgent-banner">
    <div class="urgent-banner-text">
        ⚠️ <strong>Action Required:</strong> You have <strong><?php echo $stats['pending']; ?></strong> customer <?php echo $stats['pending'] === 1 ? 'appointment' : 'appointments'; ?> waiting for your approval!
    </div>
    <a href="appointments.php?status=pending" class="btn btn-confirm">Review All Pending</a>
</div>
<?php endif; ?>

<!-- KPI Metrics Grid -->
<div class="stats-grid-admin">
    <div class="stat-card alert-pending">
        <div class="stat-number"><?php echo $stats['pending']; ?></div>
        <div class="stat-label">Pending Approval</div>
    </div>
    <div class="stat-card accent-success">
        <div class="stat-number"><?php echo $stats['confirmed']; ?></div>
        <div class="stat-label">Confirmed</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $stats['today']; ?></div>
        <div class="stat-label">Scheduled Today</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $stats['completed']; ?></div>
        <div class="stat-label">Completed</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $stats['cancelled']; ?></div>
        <div class="stat-label">Declined / Cancelled</div>
    </div>
    <div class="stat-card accent-revenue">
        <div class="stat-number">NPR <?php echo number_format($stats['total_revenue']); ?></div>
        <div class="stat-label">Confirmed Revenue</div>
    </div>
</div>

<!-- Quick Navigation Cards -->
<div class="actions-grid">
    <div class="action-card">
        <a href="appointments.php">📋 Manage All Bookings</a>
    </div>
    <div class="action-card">
        <a href="services.php">✂️ Manage Services (<?php echo $total_services; ?>)</a>
    </div>
    <div class="action-card">
        <a href="barbers.php">💈 Manage Barbers (<?php echo $total_barbers; ?>)</a>
    </div>
</div>

<div class="actions-grid" style="margin-top: -10px;">
    <div class="action-card">
        <a href="customers.php">👥 Manage Customers (<?php echo $total_customers; ?>)</a>
    </div>
    <div class="action-card">
        <a href="appointments.php?status=pending">⏳ Pending Approvals (<?php echo $stats['pending']; ?>)</a>
    </div>
    <div class="action-card">
        <a href="appointments.php?date=<?php echo date('Y-m-d'); ?>">📅 Today's Schedule</a>
    </div>
</div>

<!-- Pending Appointments Requiring Approval -->
<div style="display: flex; justify-content: space-between; align-items: baseline; margin-top: 40px; margin-bottom: 15px;">
    <h2 class="section-title" style="margin: 0; border-bottom: none;">⏳ Urgent: Pending Approvals</h2>
    <a href="appointments.php?status=pending" class="btn btn-small">View All Pending →</a>
</div>

<?php if (count($pending_appointments) > 0): ?>
<div class="admin-table-container">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Barber</th>
                <th>Service & Price</th>
                <th>Date & Time</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pending_appointments as $appt): ?>
            <tr>
                <td><strong>#<?php echo $appt['id']; ?></strong></td>
                <td>
                    <div class="customer-meta">
                        <span class="customer-name"><?php echo htmlspecialchars($appt['customer_name']); ?></span>
                        <span class="customer-contact">📞 <?php echo htmlspecialchars($appt['customer_phone'] ?? 'N/A'); ?></span>
                        <span class="customer-contact">✉️ <?php echo htmlspecialchars($appt['customer_email']); ?></span>
                    </div>
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($appt['barber_name']); ?></strong><br>
                    <small style="color: #666;"><?php echo htmlspecialchars($appt['barber_specialty'] ?? 'Stylist'); ?></small>
                </td>
                <td>
                    <div class="service-meta">
                        <span class="service-name"><?php echo htmlspecialchars($appt['service_name']); ?></span>
                        <span class="service-duration">⏱️ <?php echo $appt['duration_minutes']; ?> mins</span>
                        <strong>NPR <?php echo number_format($appt['price_at_booking'], 2); ?></strong>
                    </div>
                </td>
                <td>
                    <strong><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></strong><br>
                    <span style="color: #444; font-weight: 600;"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></span>
                </td>
                <td>
                    <div class="payment-meta">
                        <?php if ($appt['payment_method']): ?>
                            <span class="payment-badge <?php echo htmlspecialchars($appt['payment_method']); ?>">
                                <?php echo ucfirst($appt['payment_method']); ?>
                            </span>
                            <?php if ($appt['screenshot_path']): ?>
                                <button type="button" class="screenshot-link-btn" onclick="openScreenshotModal('/stylecut/<?php echo htmlspecialchars($appt['screenshot_path']); ?>')">
                                    📷 View Receipt
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #888;">Unrecorded</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <span class="status-badge <?php echo getStatusBadgeClass($appt['status']); ?>">
                        <?php echo strtoupper($appt['status']); ?>
                    </span>
                </td>
                <td>
                    <div class="action-btn-group">
                        <form method="POST" onsubmit="return confirm('Approve this appointment for <?php echo htmlspecialchars(addslashes($appt['customer_name'])); ?>?');">
                            <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                            <input type="hidden" name="action" value="confirm">
                            <button type="submit" class="btn-action-approve" title="Approve Appointment">
                                ✓ Approve
                            </button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Decline this appointment? Bonus points will be refunded to the customer.');">
                            <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                            <input type="hidden" name="action" value="decline">
                            <button type="submit" class="btn-action-decline" title="Decline Appointment">
                                ✗ Decline
                            </button>
                        </form>
                        <a href="view-booking.php?id=<?php echo $appt['id']; ?>" class="btn-action-view" title="Details">
                            Details
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="empty-state">
    <p>🎉 All caught up! There are no pending appointments waiting for approval.</p>
    <a href="appointments.php" class="btn">View All Bookings</a>
</div>
<?php endif; ?>

<!-- Today's Schedule Overview -->
<div style="display: flex; justify-content: space-between; align-items: baseline; margin-top: 40px; margin-bottom: 15px;">
    <h2 class="section-title" style="margin: 0; border-bottom: none;">📅 Today's Appointments (<?php echo date('M d, Y'); ?>)</h2>
    <a href="appointments.php?date=<?php echo date('Y-m-d'); ?>" class="btn btn-small">View Full Today Schedule →</a>
</div>

<?php if (count($today_appointments) > 0): ?>
<div class="admin-table-container">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Time</th>
                <th>Customer</th>
                <th>Barber</th>
                <th>Service</th>
                <th>Price</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($today_appointments as $appt): ?>
            <tr>
                <td><strong><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></strong></td>
                <td>
                    <div class="customer-meta">
                        <span class="customer-name"><?php echo htmlspecialchars($appt['customer_name']); ?></span>
                        <span class="customer-contact">📞 <?php echo htmlspecialchars($appt['customer_phone'] ?? 'N/A'); ?></span>
                    </div>
                </td>
                <td><?php echo htmlspecialchars($appt['barber_name']); ?></td>
                <td><?php echo htmlspecialchars($appt['service_name']); ?></td>
                <td>NPR <?php echo number_format($appt['price_at_booking'], 2); ?></td>
                <td>
                    <span class="status-badge <?php echo getStatusBadgeClass($appt['status']); ?>">
                        <?php echo strtoupper($appt['status']); ?>
                    </span>
                </td>
                <td>
                    <div class="action-btn-group">
                        <?php if ($appt['status'] === 'pending'): ?>
                            <form method="POST" onsubmit="return confirm('Approve appointment #<?php echo $appt['id']; ?>?');">
                                <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                <input type="hidden" name="action" value="confirm">
                                <button type="submit" class="btn-action-approve">✓ Approve</button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Decline appointment #<?php echo $appt['id']; ?>?');">
                                <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                <input type="hidden" name="action" value="decline">
                                <button type="submit" class="btn-action-decline">✗ Decline</button>
                            </form>
                        <?php elseif ($appt['status'] === 'confirmed'): ?>
                            <form method="POST" onsubmit="return confirm('Mark appointment #<?php echo $appt['id']; ?> as completed?');">
                                <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                <input type="hidden" name="action" value="complete">
                                <button type="submit" class="btn-action-complete">✓ Complete</button>
                            </form>
                        <?php endif; ?>
                        <a href="view-booking.php?id=<?php echo $appt['id']; ?>" class="btn-action-view">Details</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="empty-state">
    <p>No appointments booked for today yet.</p>
</div>
<?php endif; ?>

<!-- Payment Screenshot Modal -->
<div id="screenshotModal" class="admin-modal-backdrop" onclick="closeScreenshotModal(event)">
    <div class="admin-modal-content" onclick="event.stopPropagation()">
        <span class="admin-modal-close" onclick="closeScreenshotModal()">&times;</span>
        <h3 style="margin-bottom: 15px;">🔍 Payment Screenshot Preview</h3>
        <img id="modalImg" src="" alt="Payment Screenshot" class="admin-modal-img">
        <div style="text-align: right; margin-top: 15px;">
            <a id="modalFullLink" href="#" target="_blank" class="btn btn-small">Open in New Tab</a>
            <button type="button" class="btn btn-small" onclick="closeScreenshotModal()">Close</button>
        </div>
    </div>
</div>

<script>
function openScreenshotModal(url) {
    const modal = document.getElementById('screenshotModal');
    const img = document.getElementById('modalImg');
    const fullLink = document.getElementById('modalFullLink');
    img.src = url;
    fullLink.href = url;
    modal.classList.add('active');
}

function closeScreenshotModal(event) {
    if (!event || event.target.id === 'screenshotModal' || event.target.classList.contains('admin-modal-close') || event.target.tagName === 'BUTTON') {
        document.getElementById('screenshotModal').classList.remove('active');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeScreenshotModal();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
