<?php
// ==============================================
// admin/customers.php - Manage Customers (CRUD)
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
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? 'Kathmandu, Nepal');
            $bonus_points = intval($_POST['bonus_points'] ?? 10);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($name) || empty($email) || empty($password)) {
                throw new Exception('Name, email, and password are required.');
            }

            // Check if email already registered
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->rowCount() > 0) {
                throw new Exception("The email '{$email}' is already in use by another user.");
            }

            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password, phone, address, role, bonus_points, is_active)
                VALUES (?, ?, ?, ?, ?, 'customer', ?, ?)
            ");
            $stmt->execute([$name, $email, $hashed, $phone, $address, $bonus_points, $is_active]);

            $_SESSION['success'] = "Customer account '{$name}' created successfully!";

        } elseif ($action === 'edit') {
            $user_id = intval($_POST['user_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $bonus_points = max(0, intval($_POST['bonus_points'] ?? 0));
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($user_id <= 0 || empty($name) || empty($email)) {
                throw new Exception('Please provide valid customer details.');
            }

            // Check email collision
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $user_id]);
            if ($check->rowCount() > 0) {
                throw new Exception("The email '{$email}' is already in use by another user.");
            }

            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, password = ?, phone = ?, address = ?, bonus_points = ?, is_active = ?
                    WHERE id = ? AND role = 'customer'
                ");
                $stmt->execute([$name, $email, $hashed, $phone, $address, $bonus_points, $is_active, $user_id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, phone = ?, address = ?, bonus_points = ?, is_active = ?
                    WHERE id = ? AND role = 'customer'
                ");
                $stmt->execute([$name, $email, $phone, $address, $bonus_points, $is_active, $user_id]);
            }

            $_SESSION['success'] = "Customer '{$name}' updated successfully!";

        } elseif ($action === 'delete') {
            $user_id = intval($_POST['user_id'] ?? 0);

            // Check appointment and payment history
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE customer_id = ?");
            $stmt->execute([$user_id]);
            $appt_count = (int)$stmt->fetchColumn();

            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE customer_id = ?");
            $stmt2->execute([$user_id]);
            $pay_count = (int)$stmt2->fetchColumn();

            if ($appt_count > 0 || $pay_count > 0) {
                // Safely deactivate account
                $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND role = 'customer'")->execute([$user_id]);
                $_SESSION['success'] = "Customer has {$appt_count} booking(s) and {$pay_count} payment record(s). Account was safely deactivated to preserve audit and financial history.";
            } else {
                // Permanently remove
                $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'customer'")->execute([$user_id]);
                $_SESSION['success'] = "Customer account permanently removed.";
            }

        } elseif ($action === 'toggle_status') {
            $user_id = intval($_POST['user_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE users SET is_active = IF(is_active = 1, 0, 1) WHERE id = ? AND role = 'customer'");
            $stmt->execute([$user_id]);
            $_SESSION['success'] = "Customer account status toggled!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Operation failed: " . $e->getMessage();
    }

    $redirect_url = 'customers.php' . (!empty($_GET['search']) ? '?search=' . urlencode($_GET['search']) : '');
    redirect($redirect_url);
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$search = trim($_GET['search'] ?? '');
$customers = getAllCustomersAdmin($pdo, $search);

$total_customers = count($customers);
$active_customers = 0;
$total_points_sum = 0;
$total_bookings_sum = 0;
foreach ($customers as $c) {
    if ($c['is_active']) $active_customers++;
    $total_points_sum += $c['bonus_points'];
    $total_bookings_sum += $c['total_bookings'];
}

$page_title = 'Manage Customers - Admin';
require_once '../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px;">
    <div>
        <h1 class="page-title" style="margin-bottom: 5px; padding-bottom: 0; border-bottom: none; width: auto;">
            👥 Customer Accounts Management
        </h1>
        <p style="color: #666; margin: 0;">Add new customers, manage bonus points, review booking history, or update credentials.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button type="button" class="btn" style="background: #000000; color: #ffffff;" onclick="openAddCustomerModal()">
            + Add New Customer
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
        <div class="stat-number"><?php echo $total_customers; ?></div>
        <div class="stat-label">Total Customers</div>
    </div>
    <div class="stat-card accent-success">
        <div class="stat-number"><?php echo $active_customers; ?></div>
        <div class="stat-label">Active Accounts</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?php echo number_format($total_points_sum); ?></div>
        <div class="stat-label">Total Bonus Points</div>
    </div>
    <div class="stat-card accent-revenue">
        <div class="stat-number"><?php echo $total_bookings_sum; ?></div>
        <div class="stat-label">Bookings Placed</div>
    </div>
</div>

<!-- Search Customer Form -->
<form method="GET" action="" class="admin-filter-bar">
    <div class="admin-filter-group" style="flex: 3;">
        <label>Search Customer</label>
        <input type="text" name="search" placeholder="Search by name, email, phone, or Customer ID..." value="<?php echo htmlspecialchars($search); ?>">
    </div>
    <div class="admin-filter-actions">
        <button type="submit" class="btn">Search</button>
        <a href="customers.php" class="btn cancel-btn">Clear</a>
    </div>
</form>

<!-- Customers Data Table -->
<div class="admin-table-container">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Customer Name & Contact</th>
                <th>Address</th>
                <th>Bonus Points</th>
                <th>Bookings & Spent</th>
                <th>Joined Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($customers) > 0): foreach ($customers as $cust): ?>
            <tr>
                <td><strong>#<?php echo $cust['id']; ?></strong></td>
                <td>
                    <div class="customer-meta">
                        <span class="customer-name"><?php echo htmlspecialchars($cust['name']); ?></span>
                        <span class="customer-contact">✉️ <?php echo htmlspecialchars($cust['email']); ?></span>
                        <span class="customer-contact">📞 <?php echo htmlspecialchars($cust['phone'] ?? 'N/A'); ?></span>
                    </div>
                </td>
                <td style="color: #555; font-size: 13px;">
                    <?php echo htmlspecialchars($cust['address'] ?? 'Not set'); ?>
                </td>
                <td>
                    <strong style="font-size: 16px; color: #b7791f;"><?php echo $cust['bonus_points'] ?? 0; ?></strong> pts
                </td>
                <td>
                    <strong><?php echo $cust['total_bookings']; ?></strong> bookings<br>
                    <small style="color: #15803d; font-weight: 600;">NPR <?php echo number_format($cust['total_spent'], 2); ?></small>
                </td>
                <td style="color: #666; font-size: 13px;">
                    <?php echo date('M d, Y', strtotime($cust['created_at'])); ?>
                </td>
                <td>
                    <?php if ($cust['is_active']): ?>
                        <span class="status-badge status-confirmed">ACTIVE</span>
                    <?php else: ?>
                        <span class="status-badge status-cancelled">INACTIVE</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="action-btn-group">
                        <button type="button" class="btn-action-view" onclick='openEditCustomerModal(<?php echo htmlspecialchars(json_encode($cust), ENT_QUOTES, "UTF-8"); ?>)'>
                            ✏️ Edit
                        </button>

                        <form method="POST" style="display: inline;" onsubmit="return confirm('Toggle status for customer <?php echo htmlspecialchars(addslashes($cust['name'])); ?>?');">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="user_id" value="<?php echo $cust['id']; ?>">
                            <button type="submit" class="btn btn-small" style="padding: 6px 10px; font-size: 12px;">
                                <?php echo $cust['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </button>
                        </form>

                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete customer account for <?php echo htmlspecialchars(addslashes($cust['name'])); ?>? If appointments exist, account will be safely deactivated.');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?php echo $cust['id']; ?>">
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
                    No customer accounts found matching your search.
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add / Edit Customer Modal -->
<div id="customerModal" class="admin-modal-backdrop" onclick="closeModalOnBackdrop(event, 'customerModal')">
    <div class="admin-modal-content" style="max-width: 600px;" onclick="event.stopPropagation()">
        <span class="admin-modal-close" onclick="closeCustomerModal()">&times;</span>
        <h2 id="customerModalHeading" style="margin-bottom: 20px;">+ Add New Customer</h2>

        <form method="POST" id="customerForm" class="login-form" style="max-width: 100%; border: none; padding: 0;">
            <input type="hidden" name="action" id="customerFormAction" value="add">
            <input type="hidden" name="user_id" id="formCustomerId" value="">

            <div class="grid-2">
                <div class="form-group">
                    <label for="custName">Full Name *</label>
                    <input type="text" name="name" id="custName" required placeholder="e.g. Bipin Sharma">
                </div>

                <div class="form-group">
                    <label for="custEmail">Email Address *</label>
                    <input type="email" name="email" id="custEmail" required placeholder="bipin@customer.com">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="custPhone">Phone Number</label>
                    <input type="text" name="phone" id="custPhone" placeholder="98XXXXXXXX">
                </div>

                <div class="form-group">
                    <label for="custPassword">
                        Password <span id="custPasswordNotice" style="font-size: 12px; color: #888; font-weight: normal;">*</span>
                    </label>
                    <input type="password" name="password" id="custPassword" placeholder="Enter password (leave blank to keep current)">
                </div>
            </div>

            <div class="form-group">
                <label for="custAddress">Address</label>
                <input type="text" name="address" id="custAddress" placeholder="Kathmandu, Nepal">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="custBonus">Bonus Points Balance</label>
                    <input type="number" name="bonus_points" id="custBonus" min="0" value="10" required>
                </div>

                <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 12px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="is_active" id="custActive" value="1" checked style="width: 20px; height: 20px;">
                        <span style="font-weight: 700;">Account is Active</span>
                    </label>
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="submit" id="customerSubmitBtn" class="btn btn-large" style="flex: 2; background: #000; color: #fff;">
                    Save Customer
                </button>
                <button type="button" class="btn btn-large cancel-btn" style="flex: 1;" onclick="closeCustomerModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddCustomerModal() {
    document.getElementById('customerModalHeading').textContent = '+ Add New Customer';
    document.getElementById('customerFormAction').value = 'add';
    document.getElementById('formCustomerId').value = '';
    document.getElementById('custName').value = '';
    document.getElementById('custEmail').value = '';
    document.getElementById('custPhone').value = '';
    document.getElementById('custPassword').value = '';
    document.getElementById('custPassword').required = true;
    document.getElementById('custPasswordNotice').textContent = '(Required)';
    document.getElementById('custAddress').value = 'Kathmandu, Nepal';
    document.getElementById('custBonus').value = '10';
    document.getElementById('custActive').checked = true;
    document.getElementById('customerSubmitBtn').textContent = 'Create Customer';

    document.getElementById('customerModal').classList.add('active');
}

function openEditCustomerModal(c) {
    document.getElementById('customerModalHeading').textContent = '✏️ Edit Customer: ' + c.name;
    document.getElementById('customerFormAction').value = 'edit';
    document.getElementById('formCustomerId').value = c.id;
    document.getElementById('custName').value = c.name;
    document.getElementById('custEmail').value = c.email;
    document.getElementById('custPhone').value = c.phone || '';
    document.getElementById('custPassword').value = '';
    document.getElementById('custPassword').required = false;
    document.getElementById('custPasswordNotice').textContent = '(Leave blank to keep unchanged)';
    document.getElementById('custAddress').value = c.address || '';
    document.getElementById('custBonus').value = parseInt(c.bonus_points) || 0;
    document.getElementById('custActive').checked = (parseInt(c.is_active) === 1);
    document.getElementById('customerSubmitBtn').textContent = 'Update Customer';

    document.getElementById('customerModal').classList.add('active');
}

function closeCustomerModal() {
    document.getElementById('customerModal').classList.remove('active');
}

function closeModalOnBackdrop(e, modalId) {
    if (e.target.id === modalId) {
        document.getElementById(modalId).classList.remove('active');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCustomerModal();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
