<?php
// ==============================================
// admin/barbers.php - Manage Barbers (CRUD)
// ==============================================
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Handle Form Submissions (Add, Edit, Delete, Toggle Availability)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? 'Kathmandu, Nepal');
            $specialty = trim($_POST['specialty'] ?? 'Hair Stylist');
            $experience = intval($_POST['experience_years'] ?? 1);
            $bio = trim($_POST['bio'] ?? '');
            $is_available = isset($_POST['is_available']) ? 1 : 0;

            if (empty($name) || empty($email) || empty($password)) {
                throw new Exception('Name, email, and password are required.');
            }

            // Check if email already registered
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->rowCount() > 0) {
                throw new Exception("The email '$email' is already in use by another user.");
            }

            $pdo->beginTransaction();

            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password, phone, address, role, bonus_points, is_active)
                VALUES (?, ?, ?, ?, ?, 'barber', 0, 1)
            ");
            $stmt->execute([$name, $email, $hashed, $phone, $address]);
            $user_id = $pdo->lastInsertId();

            $b_stmt = $pdo->prepare("
                INSERT INTO barbers (user_id, specialty, experience_years, bio, is_available)
                VALUES (?, ?, ?, ?, ?)
            ");
            $b_stmt->execute([$user_id, $specialty, $experience, $bio, $is_available]);

            $pdo->commit();
            $_SESSION['success'] = "Barber '{$name}' created and added to roster!";

        } elseif ($action === 'edit') {
            $barber_id = intval($_POST['barber_id'] ?? 0);
            $user_id = intval($_POST['user_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $specialty = trim($_POST['specialty'] ?? '');
            $experience = intval($_POST['experience_years'] ?? 1);
            $bio = trim($_POST['bio'] ?? '');
            $is_available = isset($_POST['is_available']) ? 1 : 0;
            $user_active = isset($_POST['user_active']) ? 1 : 0;

            if ($barber_id <= 0 || $user_id <= 0 || empty($name) || empty($email)) {
                throw new Exception('Please enter valid barber details.');
            }

            // Check email collision
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $user_id]);
            if ($check->rowCount() > 0) {
                throw new Exception("The email '$email' is already in use by another user.");
            }

            $pdo->beginTransaction();

            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, password = ?, phone = ?, address = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $email, $hashed, $phone, $address, $user_active, $user_id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, phone = ?, address = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $email, $phone, $address, $user_active, $user_id]);
            }

            $b_stmt = $pdo->prepare("
                UPDATE barbers 
                SET specialty = ?, experience_years = ?, bio = ?, is_available = ?
                WHERE id = ?
            ");
            $b_stmt->execute([$specialty, $experience, $bio, $is_available, $barber_id]);

            $pdo->commit();
            $_SESSION['success'] = "Barber '{$name}' updated successfully!";

        } elseif ($action === 'delete') {
            $barber_id = intval($_POST['barber_id'] ?? 0);
            $user_id = intval($_POST['user_id'] ?? 0);

            // Check appointment records
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE barber_id = ?");
            $stmt->execute([$barber_id]);
            $count = (int)$stmt->fetchColumn();

            if ($count > 0) {
                // Safely deactivate
                $pdo->prepare("UPDATE barbers SET is_available = 0 WHERE id = ?")->execute([$barber_id]);
                $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$user_id]);
                $_SESSION['success'] = "Barber has {$count} existing appointment records. Account was safely deactivated & marked off-duty to preserve booking history.";
            } else {
                // Hard delete
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM barbers WHERE id = ?")->execute([$barber_id]);
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
                $pdo->commit();
                $_SESSION['success'] = "Barber permanently removed.";
            }

        } elseif ($action === 'toggle_availability') {
            $barber_id = intval($_POST['barber_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE barbers SET is_available = IF(is_available = 1, 0, 1) WHERE id = ?");
            $stmt->execute([$barber_id]);
            $_SESSION['success'] = "Barber availability status toggled!";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Operation failed: " . $e->getMessage();
    }

    redirect('barbers.php');
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Query all barbers
$barbers = getAllBarbersAdmin($pdo);

$total_barbers = count($barbers);
$available_barbers = 0;
foreach ($barbers as $b) {
    if ($b['is_available'] && $b['user_active']) $available_barbers++;
}

$page_title = 'Manage Barbers - Admin';
require_once '../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px;">
    <div>
        <h1 class="page-title" style="margin-bottom: 5px; padding-bottom: 0; border-bottom: none; width: auto;">
            💈 Barbers Roster & Management
        </h1>
        <p style="color: #666; margin: 0;">Add new barbers, update specialties, manage schedules, and control availability.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button type="button" class="btn" style="background: #000000; color: #ffffff;" onclick="openAddBarberModal()">
            + Add New Barber
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
<div class="stats-grid-admin" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 25px;">
    <div class="stat-card">
        <div class="stat-number"><?php echo $total_barbers; ?></div>
        <div class="stat-label">Total Barbers</div>
    </div>
    <div class="stat-card accent-success">
        <div class="stat-number"><?php echo $available_barbers; ?></div>
        <div class="stat-label">Active & On-Duty</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo $total_barbers - $available_barbers; ?></div>
        <div class="stat-label">Off-Duty / Inactive</div>
    </div>
</div>

<!-- Barbers Data Table -->
<div class="admin-table-container">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Barber Name & Contact</th>
                <th>Specialty & Experience</th>
                <th>Bio</th>
                <th>Bookings Handled</th>
                <th>Availability</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($barbers) > 0): foreach ($barbers as $barber): ?>
            <tr>
                <td><strong>#<?php echo $barber['id']; ?></strong></td>
                <td>
                    <div class="customer-meta">
                        <span class="customer-name"><?php echo htmlspecialchars($barber['name']); ?></span>
                        <span class="customer-contact">✉️ <?php echo htmlspecialchars($barber['email']); ?></span>
                        <span class="customer-contact">📞 <?php echo htmlspecialchars($barber['phone'] ?? 'N/A'); ?></span>
                    </div>
                </td>
                <td>
                    <strong><?php echo htmlspecialchars($barber['specialty'] ?? 'General Barber'); ?></strong><br>
                    <small style="color: #666;">Experience: <?php echo $barber['experience_years']; ?> years</small>
                </td>
                <td style="max-width: 250px; color: #555; font-size: 13px;">
                    <?php echo htmlspecialchars($barber['bio'] ?? 'No bio provided'); ?>
                </td>
                <td>
                    <strong><?php echo $barber['total_bookings']; ?></strong> total
                    <?php if ($barber['pending_bookings'] > 0): ?>
                        <br><span style="color: #b7791f; font-weight: 700; font-size: 12px;">(<?php echo $barber['pending_bookings']; ?> pending)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($barber['is_available'] && $barber['user_active']): ?>
                        <span class="status-badge status-confirmed">AVAILABLE</span>
                    <?php else: ?>
                        <span class="status-badge status-completed">OFF-DUTY</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="action-btn-group">
                        <button type="button" class="btn-action-view" onclick='openEditBarberModal(<?php echo htmlspecialchars(json_encode($barber), ENT_QUOTES, "UTF-8"); ?>)'>
                            ✏️ Edit
                        </button>

                        <form method="POST" style="display: inline;" onsubmit="return confirm('Toggle availability for <?php echo htmlspecialchars(addslashes($barber['name'])); ?>?');">
                            <input type="hidden" name="action" value="toggle_availability">
                            <input type="hidden" name="barber_id" value="<?php echo $barber['id']; ?>">
                            <button type="submit" class="btn btn-small" style="padding: 6px 10px; font-size: 12px;">
                                <?php echo $barber['is_available'] ? 'Set Off-Duty' : 'Set Available'; ?>
                            </button>
                        </form>

                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete barber <?php echo htmlspecialchars(addslashes($barber['name'])); ?>? If appointments exist, account will be safely deactivated.');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="barber_id" value="<?php echo $barber['id']; ?>">
                            <input type="hidden" name="user_id" value="<?php echo $barber['user_id']; ?>">
                            <button type="submit" class="btn-action-decline" style="padding: 6px 10px; font-size: 12px;">
                                🗑️ Delete
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr>
                <td colspan="7" class="text-center" style="padding: 30px;">
                    No barbers found in roster.
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add / Edit Barber Modal -->
<div id="barberModal" class="admin-modal-backdrop" onclick="closeModalOnBackdrop(event, 'barberModal')">
    <div class="admin-modal-content" style="max-width: 620px;" onclick="event.stopPropagation()">
        <span class="admin-modal-close" onclick="closeBarberModal()">&times;</span>
        <h2 id="barberModalHeading" style="margin-bottom: 20px;">+ Add New Barber</h2>

        <form method="POST" id="barberForm" class="login-form" style="max-width: 100%; border: none; padding: 0;">
            <input type="hidden" name="action" id="barberFormAction" value="add">
            <input type="hidden" name="barber_id" id="formBarberId" value="">
            <input type="hidden" name="user_id" id="formUserId" value="">

            <div class="grid-2">
                <div class="form-group">
                    <label for="barberName">Full Name *</label>
                    <input type="text" name="name" id="barberName" required placeholder="e.g. Ramesh Shrestha">
                </div>

                <div class="form-group">
                    <label for="barberEmail">Email Address *</label>
                    <input type="email" name="email" id="barberEmail" required placeholder="ramesh@barber.com">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="barberPhone">Phone Number *</label>
                    <input type="text" name="phone" id="barberPhone" placeholder="98XXXXXXXX">
                </div>

                <div class="form-group">
                    <label for="barberPassword">
                        Password <span id="passwordNotice" style="font-size: 12px; color: #888; font-weight: normal;">*</span>
                    </label>
                    <input type="password" name="password" id="barberPassword" placeholder="Enter password (leave blank to keep current on edit)">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="barberSpecialty">Specialty *</label>
                    <input type="text" name="specialty" id="barberSpecialty" required placeholder="e.g. Fade Master, Beard Sculpt">
                </div>

                <div class="form-group">
                    <label for="barberExp">Experience (Years) *</label>
                    <input type="number" name="experience_years" id="barberExp" min="0" max="50" value="2" required>
                </div>
            </div>

            <div class="form-group">
                <label for="barberAddress">Address</label>
                <input type="text" name="address" id="barberAddress" placeholder="Kathmandu, Nepal">
            </div>

            <div class="form-group">
                <label for="barberBio">Bio / Profile Summary</label>
                <textarea name="bio" id="barberBio" rows="3" placeholder="Tell customers about their skill and background..."></textarea>
            </div>

            <div class="grid-2" style="margin: 15px 0;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="is_available" id="barberAvailable" value="1" checked style="width: 20px; height: 20px;">
                    <span style="font-weight: 700;">Available for Booking</span>
                </label>

                <label id="userActiveGroup" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="user_active" id="userActive" value="1" checked style="width: 20px; height: 20px;">
                    <span style="font-weight: 700;">User Account Active</span>
                </label>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="submit" id="barberSubmitBtn" class="btn btn-large" style="flex: 2; background: #000; color: #fff;">
                    Save Barber
                </button>
                <button type="button" class="btn btn-large cancel-btn" style="flex: 1;" onclick="closeBarberModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddBarberModal() {
    document.getElementById('barberModalHeading').textContent = '+ Add New Barber';
    document.getElementById('barberFormAction').value = 'add';
    document.getElementById('formBarberId').value = '';
    document.getElementById('formUserId').value = '';
    document.getElementById('barberName').value = '';
    document.getElementById('barberEmail').value = '';
    document.getElementById('barberPhone').value = '';
    document.getElementById('barberPassword').value = '';
    document.getElementById('barberPassword').required = true;
    document.getElementById('passwordNotice').textContent = '(Required)';
    document.getElementById('barberSpecialty').value = 'Hair Stylist';
    document.getElementById('barberExp').value = '2';
    document.getElementById('barberAddress').value = 'Kathmandu, Nepal';
    document.getElementById('barberBio').value = '';
    document.getElementById('barberAvailable').checked = true;
    document.getElementById('userActive').checked = true;
    document.getElementById('userActiveGroup').style.display = 'none';
    document.getElementById('barberSubmitBtn').textContent = 'Create Barber';

    document.getElementById('barberModal').classList.add('active');
}

function openEditBarberModal(b) {
    document.getElementById('barberModalHeading').textContent = '✏️ Edit Barber: ' + b.name;
    document.getElementById('barberFormAction').value = 'edit';
    document.getElementById('formBarberId').value = b.id;
    document.getElementById('formUserId').value = b.user_id;
    document.getElementById('barberName').value = b.name;
    document.getElementById('barberEmail').value = b.email;
    document.getElementById('barberPhone').value = b.phone || '';
    document.getElementById('barberPassword').value = '';
    document.getElementById('barberPassword').required = false;
    document.getElementById('passwordNotice').textContent = '(Leave blank to keep unchanged)';
    document.getElementById('barberSpecialty').value = b.specialty || '';
    document.getElementById('barberExp').value = parseInt(b.experience_years) || 1;
    document.getElementById('barberAddress').value = b.address || '';
    document.getElementById('barberBio').value = b.bio || '';
    document.getElementById('barberAvailable').checked = (parseInt(b.is_available) === 1);
    document.getElementById('userActive').checked = (parseInt(b.user_active) === 1);
    document.getElementById('userActiveGroup').style.display = 'flex';
    document.getElementById('barberSubmitBtn').textContent = 'Update Barber';

    document.getElementById('barberModal').classList.add('active');
}

function closeBarberModal() {
    document.getElementById('barberModal').classList.remove('active');
}

function closeModalOnBackdrop(e, modalId) {
    if (e.target.id === modalId) {
        document.getElementById(modalId).classList.remove('active');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeBarberModal();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
