<?php
// ==============================================
// admin/appointments.php - Manage Customer Bookings
// ==============================================
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Handle Status Change Actions (Approve, Decline, Complete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'], $_POST['action'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $action = $_POST['action'];

    // Preserve current query parameters on redirect
    $redirect_params = $_GET;
    $redirect_url = 'appointments.php' . (!empty($redirect_params) ? '?' . http_build_query($redirect_params) : '');

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

    redirect($redirect_url);
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Filter parameters
$status_filter = $_GET['status'] ?? 'all';
$barber_filter = $_GET['barber_id'] ?? 'all';
$date_filter = $_GET['date'] ?? '';
$search_query = trim($_GET['search'] ?? '');

$filters = [
    'status' => $status_filter,
    'barber_id' => $barber_filter,
    'date' => $date_filter,
    'search' => $search_query
];

// Fetch filtered appointments
$appointments = getAllAppointmentsAdmin($pdo, $filters);

// Fetch all barbers for dropdown filter
$all_barbers = getAllBarbers($pdo);

// Calculate status counts for filter tabs
$counts = ['all' => 0, 'pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
$count_stmt = $pdo->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
while ($row = $count_stmt->fetch()) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = (int)$row['count'];
    }
}
$counts['all'] = array_sum(array_slice($counts, 1));

$page_title = 'Customer Bookings - Admin Panel';
require_once '../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px;">
    <h1 class="page-title" style="margin-bottom: 0; padding-bottom: 0; border-bottom: none; width: auto;">📋 Customer Bookings</h1>
    <a href="dashboard.php" class="btn btn-back">← Back to Dashboard</a>
</div>

<?php if ($success): ?>
    <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Status Filter Tabs -->
<div class="filter-tabs-wrapper">
    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'all'])); ?>" 
       class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
        All Bookings <span class="badge-pill"><?php echo $counts['all']; ?></span>
    </a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'pending'])); ?>" 
       class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
        ⏳ Pending <span class="badge-pill <?php echo $counts['pending'] > 0 ? 'yellow' : ''; ?>"><?php echo $counts['pending']; ?></span>
    </a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'confirmed'])); ?>" 
       class="filter-tab <?php echo $status_filter === 'confirmed' ? 'active' : ''; ?>">
        ✓ Confirmed <span class="badge-pill"><?php echo $counts['confirmed']; ?></span>
    </a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'completed'])); ?>" 
       class="filter-tab <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
        🎉 Completed <span class="badge-pill"><?php echo $counts['completed']; ?></span>
    </a>
    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'cancelled'])); ?>" 
       class="filter-tab <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
        ✗ Cancelled / Declined <span class="badge-pill"><?php echo $counts['cancelled']; ?></span>
    </a>
</div>

<!-- Search & Advanced Filters Form -->
<form method="GET" action="" class="admin-filter-bar">
    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">

    <div class="admin-filter-group" style="flex: 2;">
        <label>Search Customer</label>
        <input type="text" name="search" placeholder="Customer name, phone, email, or #ID..." value="<?php echo htmlspecialchars($search_query); ?>">
    </div>

    <div class="admin-filter-group">
        <label>Filter Barber</label>
        <select name="barber_id">
            <option value="all">All Barbers</option>
            <?php foreach ($all_barbers as $barber): ?>
                <option value="<?php echo $barber['id']; ?>" <?php echo $barber_filter == $barber['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($barber['name']); ?> (<?php echo htmlspecialchars($barber['specialty'] ?? 'Stylist'); ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="admin-filter-group">
        <label>Filter Date</label>
        <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
    </div>

    <div class="admin-filter-actions">
        <button type="submit" class="btn btn-block">Filter</button>
        <a href="appointments.php?status=<?php echo urlencode($status_filter); ?>" class="btn cancel-btn" title="Reset Filters">Reset</a>
    </div>
</form>

<!-- Results Summary -->
<div style="margin-bottom: 15px; font-weight: 600; color: #555;">
    Showing <strong><?php echo count($appointments); ?></strong> 
    <?php echo $status_filter !== 'all' ? ucfirst($status_filter) : ''; ?> 
    <?php echo count($appointments) === 1 ? 'booking' : 'bookings'; ?>:
</div>

<!-- Bookings Data Table -->
<?php if (count($appointments) > 0): ?>
<div class="admin-table-container">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Assigned Barber</th>
                <th>Service & Duration</th>
                <th>Date & Time</th>
                <th>Price / Bonus</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($appointments as $appt): ?>
            <tr>
                <td>
                    <strong>#<?php echo $appt['id']; ?></strong><br>
                    <small style="color: #888;"><?php echo date('M d', strtotime($appt['created_at'])); ?></small>
                </td>
                <td>
                    <div class="customer-meta">
                        <span class="customer-name"><?php echo htmlspecialchars($appt['customer_name']); ?></span>
                        <span class="customer-contact">📞 <?php echo htmlspecialchars($appt['customer_phone'] ?? 'Not provided'); ?></span>
                        <span class="customer-contact">✉️ <?php echo htmlspecialchars($appt['customer_email']); ?></span>
                    </div>
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($appt['barber_name']); ?></strong><br>
                    <small style="color: #666;"><?php echo htmlspecialchars($appt['barber_specialty'] ?? 'Barber'); ?></small>
                </td>
                <td>
                    <div class="service-meta">
                        <span class="service-name"><?php echo htmlspecialchars($appt['service_name']); ?></span>
                        <span class="service-duration">⏱️ <?php echo $appt['duration_minutes']; ?> minutes</span>
                    </div>
                </td>
                <td>
                    <strong><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></strong><br>
                    <span style="font-weight: 600; color: #333;"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></span>
                </td>
                <td>
                    <strong>NPR <?php echo number_format($appt['price_at_booking'], 2); ?></strong>
                    <?php if ($appt['bonus_used'] > 0): ?>
                        <br><small style="color: #b7791f;">Bonus: -<?php echo $appt['bonus_used']; ?> pts</small>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="payment-meta">
                        <?php if ($appt['payment_method']): ?>
                            <span class="payment-badge <?php echo htmlspecialchars($appt['payment_method']); ?>">
                                <?php echo ucfirst($appt['payment_method']); ?>
                            </span>
                            <small style="color: #666; text-transform: capitalize;"><?php echo htmlspecialchars($appt['payment_status'] ?? 'pending'); ?></small>
                            <?php if ($appt['screenshot_path']): ?>
                                <button type="button" class="screenshot-link-btn" onclick="openScreenshotModal('/stylecut/<?php echo htmlspecialchars($appt['screenshot_path']); ?>')">
                                    📷 View Receipt
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #888;">Cash on arrival</span>
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
                        <?php if ($appt['status'] === 'pending'): ?>
                            <!-- Approve (Confirm) Button -->
                            <form method="POST" onsubmit="return confirm('Approve appointment #<?php echo $appt['id']; ?> for <?php echo htmlspecialchars(addslashes($appt['customer_name'])); ?>?');">
                                <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                <input type="hidden" name="action" value="confirm">
                                <button type="submit" class="btn-action-approve" title="Approve Appointment">
                                    ✓ Approve
                                </button>
                            </form>

                            <!-- Decline Button -->
                            <form method="POST" onsubmit="return confirm('Decline appointment #<?php echo $appt['id']; ?>? Customer bonus points will be refunded.');">
                                <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                <input type="hidden" name="action" value="decline">
                                <button type="submit" class="btn-action-decline" title="Decline Appointment">
                                    ✗ Decline
                                </button>
                            </form>

                        <?php elseif ($appt['status'] === 'confirmed'): ?>
                            <!-- Mark Completed Button -->
                            <form method="POST" onsubmit="return confirm('Mark appointment #<?php echo $appt['id']; ?> as completed?');">
                                <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                <input type="hidden" name="action" value="complete">
                                <button type="submit" class="btn-action-complete" title="Mark as Completed">
                                    ✓ Complete
                                </button>
                            </form>

                            <!-- Cancel Button -->
                            <form method="POST" onsubmit="return confirm('Cancel confirmed appointment #<?php echo $appt['id']; ?>? Customer bonus points will be refunded.');">
                                <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                <input type="hidden" name="action" value="decline">
                                <button type="submit" class="btn-action-decline" title="Cancel Appointment">
                                    ✗ Cancel
                                </button>
                            </form>

                        <?php endif; ?>

                        <a href="view-booking.php?id=<?php echo $appt['id']; ?>" class="btn-action-view" title="Full Booking Summary">
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
    <p>No bookings found matching your selected criteria.</p>
    <a href="appointments.php" class="btn">Clear Filters & View All</a>
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
