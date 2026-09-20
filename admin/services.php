<?php
// ==============================================
// admin/services.php - Manage Grooming Services
// ==============================================
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Handle Actions (Add, Edit, Delete, Toggle Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $duration = intval($_POST['duration_minutes'] ?? 30);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($name) || $price <= 0 || $duration <= 0) {
                throw new Exception('Please enter valid service name, price, and duration.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO services (name, description, price, duration_minutes, is_active)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $price, $duration, $is_active]);
            $_SESSION['success'] = "Service '{$name}' created successfully!";

        } elseif ($action === 'edit') {
            $id = intval($_POST['service_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $duration = intval($_POST['duration_minutes'] ?? 30);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($id <= 0 || empty($name) || $price <= 0 || $duration <= 0) {
                throw new Exception('Please enter valid service details.');
            }

            $stmt = $pdo->prepare("
                UPDATE services 
                SET name = ?, description = ?, price = ?, duration_minutes = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $price, $duration, $is_active, $id]);
            $_SESSION['success'] = "Service '{$name}' updated successfully!";

        } elseif ($action === 'delete') {
            $id = intval($_POST['service_id'] ?? 0);

            // Check if service has existing appointments
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE service_id = ?");
            $stmt->execute([$id]);
            $bookings_count = (int)$stmt->fetchColumn();

            if ($bookings_count > 0) {
                // Safely deactivate instead of hard delete to preserve financial & booking records
                $stmt = $pdo->prepare("UPDATE services SET is_active = 0 WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = "Service has {$bookings_count} linked booking records. It was safely deactivated (hidden from new bookings) to protect database integrity.";
            } else {
                // Permanently delete if no appointments exist
                $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = "Service permanently deleted.";
            }

        } elseif ($action === 'toggle_status') {
            $id = intval($_POST['service_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE services SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Service status toggled successfully!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Operation failed: " . $e->getMessage();
    }

    redirect('services.php');
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Query all services with total booking statistics
$services = getAllServicesAdmin($pdo);

$total_services = count($services);
$active_services = 0;
$total_bookings_all = 0;
foreach ($services as $s) {
    if ($s['is_active']) $active_services++;
    $total_bookings_all += $s['total_bookings'];
}

$page_title = 'Manage Services - Admin';
require_once '../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px;">
    <div>
        <h1 class="page-title" style="margin-bottom: 5px; padding-bottom: 0; border-bottom: none; width: auto;">
            ✂️ Services Management
        </h1>
        <p style="color: #666; margin: 0;">Add, edit prices, durations, or manage availability of salon services.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button type="button" class="btn" style="background: #000000; color: #ffffff;" onclick="openAddServiceModal()">
            + Add New Service
        </button>
        <a href="dashboard.php" class="btn btn-back">← Dashboard</a>
    </div>
</div>

<?php if ($success): ?>
    <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Quick Stats Bar -->
<div class="stats-grid-admin" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 25px;">
    <div class="stat-card">
        <div class="stat-number"><?php echo $total_services; ?></div>
        <div class="stat-label">Total Services</div>
    </div>
    <div class="stat-card accent-success">
        <div class="stat-number"><?php echo $active_services; ?></div>
        <div class="stat-label">Active & Bookable</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $total_services - $active_services; ?></div>
        <div class="stat-label">Inactive / Archived</div>
    </div>
    <div class="stat-card accent-revenue">
        <div class="stat-number"><?php echo $total_bookings_all; ?></div>
        <div class="stat-label">Total Bookings</div>
    </div>
</div>

<!-- Services Data Table -->
<div class="admin-table-container">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Service Name</th>
                <th>Description</th>
                <th>Price (NPR)</th>
                <th>Duration</th>
                <th>Bookings</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($services) > 0): foreach ($services as $srv): ?>
            <tr>
                <td><strong>#<?php echo $srv['id']; ?></strong></td>
                <td>
                    <strong style="font-size: 15px;"><?php echo htmlspecialchars($srv['name']); ?></strong>
                </td>
                <td style="max-width: 320px; color: #555;">
                    <?php echo htmlspecialchars($srv['description'] ?? 'No description'); ?>
                </td>
                <td>
                    <strong style="font-size: 15px;">NPR <?php echo number_format($srv['price'], 2); ?></strong>
                </td>
                <td>
                    <span style="font-weight: 600;">⏱️ <?php echo $srv['duration_minutes']; ?> mins</span>
                </td>
                <td>
                    <span class="badge-pill" style="margin-left: 0; background: #e2e8f0; color: #1e293b; font-size: 13px;">
                        <?php echo $srv['total_bookings']; ?>
                    </span>
                </td>
                <td>
                    <?php if ($srv['is_active']): ?>
                        <span class="status-badge status-confirmed">ACTIVE</span>
                    <?php else: ?>
                        <span class="status-badge status-completed">INACTIVE</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="action-btn-group">
                        <button type="button" class="btn-action-view" onclick='openEditServiceModal(<?php echo htmlspecialchars(json_encode($srv), ENT_QUOTES, "UTF-8"); ?>)'>
                            ✏️ Edit
                        </button>
                        
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Toggle status for <?php echo htmlspecialchars(addslashes($srv['name'])); ?>?');">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="service_id" value="<?php echo $srv['id']; ?>">
                            <button type="submit" class="btn btn-small" style="padding: 6px 10px; font-size: 12px;">
                                <?php echo $srv['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </button>
                        </form>

                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete <?php echo htmlspecialchars(addslashes($srv['name'])); ?>? If it has prior bookings, it will be safely deactivated.');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="service_id" value="<?php echo $srv['id']; ?>">
                            <button type="submit" class="btn-action-decline" style="padding: 6px 10px; font-size: 12px;">
                                🗑️ Delete
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr>
                <td colspan="8" class="text-center" style="padding: 30px;">
                    No services found. Click "+ Add New Service" to create one!
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add / Edit Service Modal -->
<div id="serviceModal" class="admin-modal-backdrop" onclick="closeModalOnBackdrop(event, 'serviceModal')">
    <div class="admin-modal-content" onclick="event.stopPropagation()">
        <span class="admin-modal-close" onclick="closeServiceModal()">&times;</span>
        <h2 id="serviceModalHeading" style="margin-bottom: 20px;">+ Add New Service</h2>

        <form method="POST" id="serviceForm" class="login-form" style="max-width: 100%; border: none; padding: 0;">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="service_id" id="formServiceId" value="">

            <div class="form-group">
                <label for="srvName">Service Name *</label>
                <input type="text" name="name" id="srvName" required placeholder="e.g. Beard Sculpt & Hot Towel">
            </div>

            <div class="form-group">
                <label for="srvDesc">Description</label>
                <textarea name="description" id="srvDesc" rows="3" placeholder="Brief details about what is included in this service..."></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="srvPrice">Price (NPR) *</label>
                    <input type="number" name="price" id="srvPrice" step="10" min="50" required placeholder="e.g. 500">
                </div>

                <div class="form-group">
                    <label for="srvDuration">Duration (Minutes) *</label>
                    <input type="number" name="duration_minutes" id="srvDuration" step="5" min="10" required placeholder="e.g. 30">
                </div>
            </div>

            <div class="form-group" style="margin: 15px 0;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="srvActive" value="1" checked style="width: 20px; height: 20px;">
                    <span style="font-weight: 700;">Service is Active (Available for customer booking)</span>
                </label>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="submit" id="serviceSubmitBtn" class="btn btn-large" style="flex: 2; background: #000; color: #fff;">
                    Save Service
                </button>
                <button type="button" class="btn btn-large cancel-btn" style="flex: 1;" onclick="closeServiceModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddServiceModal() {
    document.getElementById('serviceModalHeading').textContent = '+ Add New Service';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formServiceId').value = '';
    document.getElementById('srvName').value = '';
    document.getElementById('srvDesc').value = '';
    document.getElementById('srvPrice').value = '';
    document.getElementById('srvDuration').value = '30';
    document.getElementById('srvActive').checked = true;
    document.getElementById('serviceSubmitBtn').textContent = 'Create Service';

    document.getElementById('serviceModal').classList.add('active');
}

function openEditServiceModal(srv) {
    document.getElementById('serviceModalHeading').textContent = '✏️ Edit Service: ' + srv.name;
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formServiceId').value = srv.id;
    document.getElementById('srvName').value = srv.name;
    document.getElementById('srvDesc').value = srv.description || '';
    document.getElementById('srvPrice').value = parseFloat(srv.price);
    document.getElementById('srvDuration').value = parseInt(srv.duration_minutes);
    document.getElementById('srvActive').checked = (parseInt(srv.is_active) === 1);
    document.getElementById('serviceSubmitBtn').textContent = 'Update Service';

    document.getElementById('serviceModal').classList.add('active');
}

function closeServiceModal() {
    document.getElementById('serviceModal').classList.remove('active');
}

function closeModalOnBackdrop(e, modalId) {
    if (e.target.id === modalId) {
        document.getElementById(modalId).classList.remove('active');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeServiceModal();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
