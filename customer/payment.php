<?php
// ==============================================
// customer/payment.php - Payment Page
// ==============================================
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is customer
if (!isLoggedIn() || !isCustomer()) {
    redirect('../login.php');
}

// Check if there's a pending booking
if (!isset($_SESSION['pending_booking'])) {
    $_SESSION['payment_error'] = 'No pending booking found';
    redirect('booking.php');
}

$booking = $_SESSION['pending_booking'];

// Get service details
$stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
$stmt->execute([$booking['service_id']]);
$service = $stmt->fetch();

// Get barber details
$stmt = $pdo->prepare("
    SELECT u.name, b.specialty 
    FROM barbers b
    JOIN users u ON b.user_id = u.id
    WHERE b.id = ?
");
$stmt->execute([$booking['barber_id']]);
$barber = $stmt->fetch();

// Get user bonus points
$stmt = $pdo->prepare("SELECT bonus_points FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// ✅ CHECK FOR ERROR MESSAGES FROM SESSION AND CLEAR THEM
$error = $_SESSION['payment_error'] ?? '';
$success = $_SESSION['payment_success'] ?? '';
unset($_SESSION['payment_error'], $_SESSION['payment_success']);

$page_title = 'Payment - Stylecut Nepal';
require_once '../includes/header.php';
?>

<h1 class="page-title">💳 Payment</h1>

<!-- ✅ DISPLAY ERROR MESSAGE -->
<?php if ($error): ?>
    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="payment-container">
    <!-- Appointment Summary -->
    <div class="payment-summary">
        <h2 class="section-title">Appointment Summary</h2>
        
        <div class="summary-row">
            <span class="summary-label">Service:</span>
            <span class="summary-value"><?php echo htmlspecialchars($service['name']); ?></span>
        </div>
        
        <div class="summary-row">
            <span class="summary-label">Barber:</span>
            <span class="summary-value">
                <?php echo htmlspecialchars($barber['name']); ?>
                <?php if (!empty($barber['specialty'])): ?>
                    (<?php echo htmlspecialchars($barber['specialty']); ?>)
                <?php endif; ?>
            </span>
        </div>
        
        <div class="summary-row">
            <span class="summary-label">Date:</span>
            <span class="summary-value"><?php echo date('l, F d, Y', strtotime($booking['date'])); ?></span>
        </div>
        
        <div class="summary-row">
            <span class="summary-label">Time:</span>
            <span class="summary-value"><?php echo date('h:i A', strtotime($booking['time'])); ?></span>
        </div>
        
        <div class="summary-row">
            <span class="summary-label">Duration:</span>
            <span class="summary-value"><?php echo $service['duration_minutes']; ?> minutes</span>
        </div>
        
        <div class="summary-row">
            <span class="summary-label">Price:</span>
            <span class="summary-value">NPR <?php echo number_format($service['price'], 2); ?></span>
        </div>
        
        <?php if ($booking['bonus_used'] > 0): ?>
        <div class="summary-row" style="color: #155724;">
            <span class="summary-label">Bonus discount:</span>
            <span class="summary-value">- NPR <?php echo number_format($booking['bonus_used'], 2); ?></span>
        </div>
        <?php endif; ?>
        
        <div class="final-amount">
            Final Amount: NPR <?php echo number_format($booking['final_price'], 2); ?>
        </div>
        
        <div class="bonus-info">
            <p>⭐ You will earn 10 bonus points after payment</p>
            <?php if ($booking['bonus_used'] > 0): ?>
                <p>✨ You used <?php echo $booking['bonus_used']; ?> bonus points</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="payment-methods-section">
        <h2 class="section-title">Select Payment Method</h2>
        
        <form action="process-payment.php" method="POST" enctype="multipart/form-data" id="paymentForm">
            <div class="payment-methods">
                <label class="method-option">
                    <input type="radio" name="payment_method" value="esewa" checked>
                    <span>💰 Esewa</span>
                </label>
                
                <label class="method-option">
                    <input type="radio" name="payment_method" value="bank">
                    <span>🏦 Bank Transfer</span>
                </label>
                
                <label class="method-option">
                    <input type="radio" name="payment_method" value="cash">
                    <span>💵 Cash Payment</span>
                </label>
            </div>

            <!-- QR Code Container -->
            <div id="qrContainer" style="display: none; margin: 30px 0; text-align: center;">
                <div id="esewaQr" class="qr-box" style="display: none;">
                    <h3>Scan with Esewa App</h3>
                    <img src="../assets/images/esewa-qr.png" alt="Esewa QR Code" class="qr-image">
                    <p>Merchant: Stylecut Nepal<br>Amount: NPR <?php echo number_format($booking['final_price'], 2); ?></p>
                </div>
                
                <div id="bankQr" class="qr-box" style="display: none;">
                    <h3>Bank Transfer Details</h3>
                    <img src="../assets/images/bank-qr.png" alt="Bank QR Code" class="qr-image">
                    <p>Account: Stylecut Nepal<br>Bank: Everest Bank<br>Amount: NPR <?php echo number_format($booking['final_price'], 2); ?></p>
                </div>
                
                <div id="cashNote" class="qr-box" style="display: none;">
                    <p class="cash-note">💵 Pay cash at the shop</p>
                </div>
            </div>

            <!-- Screenshot Upload Section -->
            <div id="uploadSection" style="display: none;">
                <h3>📸 Upload Payment Screenshot</h3>
                <p>Please upload a screenshot of your payment confirmation.</p>
                <input type="file" name="payment_screenshot" id="payment_screenshot" accept="image/*">
                <div class="file-help">Accepted formats: JPG, PNG, GIF (max 2MB)</div>
            </div>

            <div class="payment-actions">
                <button type="submit" class="btn btn-large btn-block" id="proceedBtn">Confirm Payment</button>
                <a href="booking.php" class="btn btn-large btn-block cancel-btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
// Payment method toggle
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const qrContainer = document.getElementById('qrContainer');
    const esewaQr = document.getElementById('esewaQr');
    const bankQr = document.getElementById('bankQr');
    const cashNote = document.getElementById('cashNote');
    const uploadSection = document.getElementById('uploadSection');
    
    function updateDisplay() {
        const selected = document.querySelector('input[name="payment_method"]:checked').value;
        
        // Hide all QR boxes
        if (esewaQr) esewaQr.style.display = 'none';
        if (bankQr) bankQr.style.display = 'none';
        if (cashNote) cashNote.style.display = 'none';
        
        // Show appropriate QR
        if (selected === 'esewa') {
            if (esewaQr) esewaQr.style.display = 'block';
            if (qrContainer) qrContainer.style.display = 'block';
            if (uploadSection) uploadSection.style.display = 'block';
        } else if (selected === 'bank') {
            if (bankQr) bankQr.style.display = 'block';
            if (qrContainer) qrContainer.style.display = 'block';
            if (uploadSection) uploadSection.style.display = 'block';
        } else if (selected === 'cash') {
            if (cashNote) cashNote.style.display = 'block';
            if (qrContainer) qrContainer.style.display = 'block';
            if (uploadSection) uploadSection.style.display = 'none';
        } else {
            if (qrContainer) qrContainer.style.display = 'none';
            if (uploadSection) uploadSection.style.display = 'none';
        }
    }
    
    paymentMethods.forEach(radio => {
        radio.addEventListener('change', updateDisplay);
    });
    
    // Initial display
    updateDisplay();
});
</script>

<?php require_once '../includes/footer.php'; ?>