<?php
// ==============================================
// customer/khalti-callback.php - Khalti Return & Verification Callback
// ==============================================
require_once '../config.php';
require_once '../functions.php';
require_once '../includes/khalti.php';

// Check if user is logged in and is customer
if (!isLoggedIn() || !isCustomer()) {
    $_SESSION['payment_error'] = 'Please log in to continue.';
    redirect('../login.php');
}

// Check if there is an active booking in session
if (!isset($_SESSION['pending_booking'])) {
    $_SESSION['payment_error'] = 'No pending booking found to finalize.';
    redirect('booking.php');
}

$booking = $_SESSION['pending_booking'];
$pidx = $_GET['pidx'] ?? ($_SESSION['khalti_payment']['pidx'] ?? '');
$status_from_query = $_GET['status'] ?? '';

// Check if user canceled or exited without paying
if (empty($pidx) || $status_from_query === 'User canceled' || $status_from_query === 'Canceled') {
    $_SESSION['payment_error'] = 'Khalti payment was canceled or not completed. Please try again or choose another payment method.';
    redirect('payment.php');
}

// Ensure database table supports khalti & transaction_id
ensureKhaltiSchema($pdo);

// Verify payment with Khalti Lookup API
$verify_res = khalti_lookup_payment($pidx);

if (!$verify_res['success']) {
    $_SESSION['payment_error'] = 'Payment verification failed: ' . ($verify_res['error'] ?? 'Unknown verification error.');
    redirect('payment.php');
}

$tx_data = $verify_res['data'];
$tx_status = $tx_data['status'] ?? '';

if ($tx_status !== 'Completed') {
    $_SESSION['payment_error'] = "Payment status is '$tx_status' (not completed). Please try again.";
    redirect('payment.php');
}

// Extract transaction ID
$transaction_id = $tx_data['transaction_id'] ?? ($_GET['transaction_id'] ?? ('KH_' . strtoupper(substr(uniqid(), 4, 8))));

try {
    $pdo->beginTransaction();

    // Re-check slot availability
    $stmt = $pdo->prepare("
        SELECT id FROM appointments 
        WHERE barber_id = ? AND appointment_date = ? AND appointment_time = ? 
        AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$booking['barber_id'], $booking['date'], $booking['time']]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('This time slot was just booked by another customer. Please contact our support with your Khalti Txn ID: ' . $transaction_id);
    }

    // Insert appointment as pending (consistent with other payment methods, awaiting barber/admin confirmation)
    $notes = 'Paid online via Khalti (Txn ID: ' . $transaction_id . ')';
    if (!empty($booking['notes'])) {
        $notes = $booking['notes'] . ' | ' . $notes;
    }

    $stmt = $pdo->prepare("
        INSERT INTO appointments 
        (customer_id, barber_id, service_id, appointment_date, appointment_time, 
         price_at_booking, bonus_used, bonus_earned, notes, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 10, ?, 'pending', NOW())
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $booking['barber_id'],
        $booking['service_id'],
        $booking['date'],
        $booking['time'],
        $booking['final_price'],
        $booking['bonus_used'],
        $notes
    ]);

    $appointment_id = $pdo->lastInsertId();

    // Update user bonus points
    $stmt = $pdo->prepare("
        UPDATE users 
        SET bonus_points = bonus_points - ? + 10 
        WHERE id = ?
    ");
    $stmt->execute([$booking['bonus_used'], $_SESSION['user_id']]);

    // Refresh session bonus points
    $stmt = $pdo->prepare("SELECT bonus_points FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['bonus_points'] = $stmt->fetchColumn();

    // Insert payment record
    try {
        $stmt = $pdo->prepare("
            INSERT INTO payments 
            (appointment_id, customer_id, amount, payment_method, payment_status, screenshot_path, paid_at, transaction_id, pidx)
            VALUES (?, ?, ?, 'khalti', 'completed', NULL, NOW(), ?, ?)
        ");
        $stmt->execute([
            $appointment_id,
            $_SESSION['user_id'],
            $booking['final_price'],
            $transaction_id,
            $pidx
        ]);
    } catch (Exception $payEx) {
        // Fallback if pidx column does not exist
        try {
            $stmt = $pdo->prepare("
                INSERT INTO payments 
                (appointment_id, customer_id, amount, payment_method, payment_status, screenshot_path, paid_at, transaction_id)
                VALUES (?, ?, ?, 'khalti', 'completed', NULL, NOW(), ?)
            ");
            $stmt->execute([
                $appointment_id,
                $_SESSION['user_id'],
                $booking['final_price'],
                $transaction_id
            ]);
        } catch (Exception $payEx2) {
            // Standard fallback
            $stmt = $pdo->prepare("
                INSERT INTO payments 
                (appointment_id, customer_id, amount, payment_method, payment_status, screenshot_path, paid_at)
                VALUES (?, ?, ?, 'khalti', 'completed', NULL, NOW())
            ");
            $stmt->execute([
                $appointment_id,
                $_SESSION['user_id'],
                $booking['final_price']
            ]);
        }
    }

    $pdo->commit();

    // Clear pending booking and khalti session data
    unset($_SESSION['pending_booking']);
    unset($_SESSION['khalti_payment']);

    $_SESSION['success'] = '🎉 Khalti payment successful! Your appointment is submitted (Status: PENDING approval). Txn ID: ' . $transaction_id;
    redirect('my-bookings.php');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['payment_error'] = 'Booking finalization failed: ' . $e->getMessage();
    redirect('payment.php');
}
