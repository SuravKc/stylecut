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
$khalti_key_invalid = $_SESSION['khalti_key_invalid'] ?? false;
unset($_SESSION['payment_error'], $_SESSION['payment_success'], $_SESSION['khalti_key_invalid']);

$page_title = 'Payment - Stylecut Nepal';
require_once '../includes/header.php';
?>

<h1 class="page-title">💳 Payment</h1>

<!-- Error Messages -->
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
                <label class="method-option method-khalti">
                    <input type="radio" name="payment_method" value="khalti" checked>
                    <span class="method-label-content">
                        <span class="method-icon">🟣</span>
                        <strong>Khalti</strong>
                        <span class="khalti-sandbox-tag" style="background: #5c2d91;">DIGITAL WALLET</span>
                    </span>
                </label>

                <label class="method-option">
                    <input type="radio" name="payment_method" value="esewa">
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

            <!-- Payment Details & QR Code Container -->
            <div id="qrContainer" style="display: block; margin: 25px 0;">
                <!-- Khalti ePayment Card -->
                <div id="khaltiBox" class="qr-box khalti-box" style="display: block;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; border-bottom: 1px solid #e9d5ff; padding-bottom: 10px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <img src="../assets/images/khalti-logo.svg" alt="Khalti Logo" style="height: 32px;">
                            <h3 style="margin: 0; color: #5c2d91;">Khalti ePayment Gateway</h3>
                        </div>
                        <span class="khalti-pill" style="background: #e9d5ff; color: #5c2d91;">INSTANT PAY</span>
                    </div>

                    <p style="color: #4b5563; font-size: 14px; margin-bottom: 14px; line-height: 1.5;">
                        Pay directly and securely via <strong>Khalti Wallet / Mobile Banking</strong>. You will be redirected to complete payment with test or personal credentials, and your appointment will be processed automatically.
                    </p>

                    <div class="khalti-test-card">
                        <div class="test-card-title">🔑 Demo Credentials (BCA Project Presentation)</div>
                        <div class="khalti-test-grid">
                            <div><strong>Test Wallet:</strong> <code>9800000000</code> - <code>9800000005</code></div>
                            <div><strong>MPIN:</strong> <code>1111</code></div>
                            <div><strong>OTP:</strong> <code>987654</code></div>
                        </div>
                        <small style="display: block; margin-top: 8px; color: #6b7280; font-size: 12px;">
                            💡 Direct automated payment verification. No screenshot upload required.
                        </small>
                    </div>

                    <div style="margin-top: 16px; padding: 14px; background: #ffffff; border: 1.5px solid #d8b4fe; border-radius: 8px;">
                        <strong style="color: #5c2d91; display: block; margin-bottom: 8px; font-size: 14px;">Select Payment Checkout:</strong>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button type="submit" class="btn" style="flex: 1; min-width: 180px; background: #5c2d91; border-color: #5c2d91; color: #ffffff; padding: 10px 14px; font-weight: 700; cursor: pointer;">
                                🟣 Pay with Khalti Checkout &rarr;
                            </button>
                            <a href="khalti-simulator.php" class="btn" style="flex: 1; min-width: 180px; background: #faf5ff; border: 2px solid #5c2d91; color: #5c2d91; padding: 10px 14px; font-weight: 700; text-align: center; text-decoration: none;">
                                ⚡ Local Portal View 🧪
                            </a>
                        </div>
                        <small style="color: #6b7280; font-size: 12px; display: block; margin-top: 8px;">
                            💡 Both options connect to your database, register the payment, and confirm the appointment.
                        </small>
                    </div>
                </div>

                <!-- Esewa QR -->
                <div id="esewaQr" class="qr-box" style="display: none; text-align: center;">
                    <h3>Scan with Esewa App</h3>
                    <img src="../assets/images/esewa-qr.png" alt="Esewa QR Code" class="qr-image">
                    <p>Merchant: Stylecut Nepal<br>Amount: NPR <?php echo number_format($booking['final_price'], 2); ?></p>
                </div>
                
                <!-- Bank QR -->
                <div id="bankQr" class="qr-box" style="display: none; text-align: center;">
                    <h3>Bank Transfer Details</h3>
                    <img src="../assets/images/bank-qr.png" alt="Bank QR Code" class="qr-image">
                    <p>Account: Stylecut Nepal<br>Bank: Everest Bank<br>Amount: NPR <?php echo number_format($booking['final_price'], 2); ?></p>
                </div>
                
                <!-- Cash Note -->
                <div id="cashNote" class="qr-box" style="display: none; text-align: center;">
                    <p class="cash-note">💵 Pay cash at the shop upon your appointment arrival</p>
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
                <button type="submit" class="btn btn-large btn-block" id="proceedBtn">
                    Pay with Khalti (NPR <?php echo number_format($booking['final_price'], 2); ?>) &rarr;
                </button>
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
    const khaltiBox = document.getElementById('khaltiBox');
    const esewaQr = document.getElementById('esewaQr');
    const bankQr = document.getElementById('bankQr');
    const cashNote = document.getElementById('cashNote');
    const uploadSection = document.getElementById('uploadSection');
    const proceedBtn = document.getElementById('proceedBtn');
    const finalPrice = '<?php echo number_format($booking['final_price'], 2); ?>';
    
    function updateDisplay() {
        const checkedInput = document.querySelector('input[name="payment_method"]:checked');
        if (!checkedInput) return;
        const selected = checkedInput.value;
        
        // Hide all method boxes
        if (khaltiBox) khaltiBox.style.display = 'none';
        if (esewaQr) esewaQr.style.display = 'none';
        if (bankQr) bankQr.style.display = 'none';
        if (cashNote) cashNote.style.display = 'none';
        
        if (selected === 'khalti') {
            if (khaltiBox) khaltiBox.style.display = 'block';
            if (qrContainer) qrContainer.style.display = 'block';
            if (uploadSection) uploadSection.style.display = 'none';
            if (proceedBtn) {
                proceedBtn.innerHTML = 'Pay with Khalti (NPR ' + finalPrice + ') &rarr;';
                proceedBtn.style.background = '#5c2d91';
                proceedBtn.style.borderColor = '#5c2d91';
                proceedBtn.style.color = '#ffffff';
            }
        } else if (selected === 'esewa') {
            if (esewaQr) esewaQr.style.display = 'block';
            if (qrContainer) qrContainer.style.display = 'block';
            if (uploadSection) uploadSection.style.display = 'block';
            if (proceedBtn) {
                proceedBtn.innerHTML = 'Confirm Esewa Payment';
                proceedBtn.style.background = '#000000';
                proceedBtn.style.borderColor = '#000000';
                proceedBtn.style.color = '#ffffff';
            }
        } else if (selected === 'bank') {
            if (bankQr) bankQr.style.display = 'block';
            if (qrContainer) qrContainer.style.display = 'block';
            if (uploadSection) uploadSection.style.display = 'block';
            if (proceedBtn) {
                proceedBtn.innerHTML = 'Confirm Bank Transfer';
                proceedBtn.style.background = '#000000';
                proceedBtn.style.borderColor = '#000000';
                proceedBtn.style.color = '#ffffff';
            }
        } else if (selected === 'cash') {
            if (cashNote) cashNote.style.display = 'block';
            if (qrContainer) qrContainer.style.display = 'block';
            if (uploadSection) uploadSection.style.display = 'none';
            if (proceedBtn) {
                proceedBtn.innerHTML = 'Confirm Cash Appointment';
                proceedBtn.style.background = '#000000';
                proceedBtn.style.borderColor = '#000000';
                proceedBtn.style.color = '#ffffff';
            }
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