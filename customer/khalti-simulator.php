<?php
// ==============================================
// customer/khalti-simulator.php - Khalti Sandbox Local Simulator
// ==============================================
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../login.php');
}

if (!isset($_SESSION['pending_booking'])) {
    $_SESSION['payment_error'] = 'No pending booking found to pay with Khalti.';
    redirect('booking.php');
}

$booking = $_SESSION['pending_booking'];

// Fetch service details
$stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
$stmt->execute([$booking['service_id']]);
$service = $stmt->fetch();

// Fetch barber details
$stmt = $pdo->prepare("
    SELECT u.name, b.specialty 
    FROM barbers b
    JOIN users u ON b.user_id = u.id
    WHERE b.id = ?
");
$stmt->execute([$booking['barber_id']]);
$barber = $stmt->fetch();

// Generate simulator PIDX
$sim_pidx = 'SIM_' . uniqid() . '_' . time();
$amount_npr = $booking['final_price'];
$amount_paisa = (int) round($amount_npr * 100);

$_SESSION['khalti_payment'] = [
    'pidx' => $sim_pidx,
    'order_id' => 'STYLECUT_SIM_' . time(),
    'amount' => $amount_npr,
    'amount_paisa' => $amount_paisa,
    'created_at' => time()
];

// Handle cancel
if (isset($_GET['action']) && $_GET['action'] === 'cancel') {
    $_SESSION['payment_error'] = 'Khalti sandbox payment was canceled by the user.';
    redirect('payment.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khalti ePayment Sandbox Portal</title>
    <link rel="stylesheet" href="/stylecut/assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        body {
            background: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .khalti-portal {
            max-width: 480px;
            width: 100%;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        .khalti-topbar {
            background: #5c2d91;
            padding: 20px 24px;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .khalti-brand-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .khalti-brand-title h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .khalti-sandbox-pill {
            background: #f59e0b;
            color: #111827;
            font-size: 11px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 9999px;
            letter-spacing: 0.5px;
        }
        .khalti-portal-body {
            padding: 24px;
        }
        .order-summary-box {
            background: #faf5ff;
            border: 1px solid #e9d5ff;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .order-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
            color: #4b5563;
        }
        .order-row.total {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed #d8b4fe;
            font-size: 18px;
            font-weight: 700;
            color: #5c2d91;
            margin-bottom: 0;
        }
        .form-group-khalti {
            margin-bottom: 16px;
        }
        .form-group-khalti label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .form-group-khalti input {
            width: 100%;
            padding: 10px 14px;
            font-size: 15px;
            border: 1.5px solid #d1d5db;
            border-radius: 6px;
            box-sizing: border-box;
            background: #f9fafb;
            color: #111827;
            font-family: inherit;
        }
        .form-group-khalti input:focus {
            border-color: #5c2d91;
            outline: none;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(92, 45, 145, 0.15);
        }
        .test-hint {
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
            display: block;
        }
        .khalti-btn-pay {
            width: 100%;
            padding: 14px;
            background: #5c2d91;
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 8px;
        }
        .khalti-btn-pay:hover {
            background: #4a2375;
        }
        .khalti-btn-cancel {
            width: 100%;
            padding: 12px;
            background: transparent;
            color: #6b7280;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: block;
            box-sizing: border-box;
            margin-top: 10px;
            transition: background 0.2s;
        }
        .khalti-btn-cancel:hover {
            background: #f3f4f6;
            color: #111827;
        }
        .portal-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>
<body>

<div class="khalti-portal">
    <div class="khalti-topbar">
        <div class="khalti-brand-title">
            <span style="font-size: 26px;">🟣</span>
            <h2>khalti</h2>
        </div>
        <span class="khalti-sandbox-pill" style="background: #22c55e; color: #ffffff;">SECURE PAYMENT CHECKOUT</span>
    </div>

    <div class="khalti-portal-body">
        <div class="order-summary-box">
            <div class="order-row">
                <span>Merchant:</span>
                <strong>Stylecut Nepal</strong>
            </div>
            <div class="order-row">
                <span>Service:</span>
                <span><?php echo htmlspecialchars($service['name'] ?? 'Service'); ?></span>
            </div>
            <div class="order-row">
                <span>Barber:</span>
                <span><?php echo htmlspecialchars($barber['name'] ?? 'Barber'); ?></span>
            </div>
            <div class="order-row total">
                <span>Amount to Pay:</span>
                <span>NPR <?php echo number_format($amount_npr, 2); ?></span>
            </div>
        </div>

        <form action="khalti-callback.php" method="GET">
            <input type="hidden" name="pidx" value="<?php echo htmlspecialchars($sim_pidx); ?>">
            <input type="hidden" name="status" value="Completed">
            <input type="hidden" name="amount" value="<?php echo $amount_paisa; ?>">
            <input type="hidden" name="total_amount" value="<?php echo $amount_paisa; ?>">
            <input type="hidden" name="transaction_id" value="KHALTI_TXN_<?php echo strtoupper(substr(uniqid(), 5, 8)); ?>">
            <input type="hidden" name="purchase_order_id" value="<?php echo htmlspecialchars($_SESSION['khalti_payment']['order_id']); ?>">

            <div class="form-group-khalti">
                <label for="mobile">Khalti Registered Mobile Number</label>
                <input type="text" id="mobile" name="mobile" value="9800000000" required>
                <span class="test-hint">💡 Demo Mobile: 9800000000 (pre-filled for project test)</span>
            </div>

            <div class="form-group-khalti">
                <label for="mpin">Wallet MPIN</label>
                <input type="password" id="mpin" name="mpin" value="1111" required>
                <span class="test-hint">💡 Demo MPIN: 1111</span>
            </div>

            <div class="form-group-khalti">
                <label for="otp">Confirmation OTP Code</label>
                <input type="text" id="otp" name="otp" value="987654" required>
                <span class="test-hint">💡 Demo OTP: 987654</span>
            </div>

            <button type="submit" class="khalti-btn-pay">
                Pay NPR <?php echo number_format($amount_npr, 2); ?> &rarr;
            </button>

            <a href="khalti-simulator.php?action=cancel" class="khalti-btn-cancel">
                Cancel &amp; Return to Shop
            </a>
        </form>

        <div class="portal-footer">
            🔒 256-Bit Encrypted Payment &bull; Khalti ePayment Gateway
        </div>
    </div>
</div>

</body>
</html>
