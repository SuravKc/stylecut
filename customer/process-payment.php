<?php
// ==============================================
// customer/process-payment.php - Process Payment
// ==============================================
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is customer
if (!isLoggedIn() || !isCustomer()) {
    $_SESSION['payment_error'] = 'Please login first';
    redirect('../login.php');
}

// Check if there's a pending booking
if (!isset($_SESSION['pending_booking'])) {
    $_SESSION['payment_error'] = 'No pending booking found. Please book again.';
    redirect('booking.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('payment.php');
}

$payment_method = $_POST['payment_method'] ?? '';
if (!in_array($payment_method, ['esewa', 'bank', 'cash'])) {
    $_SESSION['payment_error'] = 'Invalid payment method selected';
    redirect('payment.php');
}

$booking = $_SESSION['pending_booking'];
$screenshot_path = null;

// ✅ VALIDATE FILE UPLOAD FOR ESEWA/BANK
if (in_array($payment_method, ['esewa', 'bank'])) {
    // Check if file was uploaded
    if (!isset($_FILES['payment_screenshot']) || $_FILES['payment_screenshot']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['payment_error'] = 'Please upload a payment screenshot before proceeding.';
        redirect('payment.php');
    }
    
    $file = $_FILES['payment_screenshot'];
    
    // Check file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        $_SESSION['payment_error'] = 'Only JPG, PNG, and GIF images are allowed.';
        redirect('payment.php');
    }
    
    // Check file size (max 2MB)
    $max_size = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $max_size) {
        $_SESSION['payment_error'] = 'File size must be less than 2MB.';
        redirect('payment.php');
    }
    
    // Create uploads directory if not exists
$upload_dir = __DIR__ . '/../uploads/payments/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$filename = 'payment_' . time() . '_' . uniqid() . '.' . $extension;
$destination = $upload_dir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    $_SESSION['payment_error'] = 'Failed to upload screenshot. Please try again.';
    redirect('payment.php');
}

$screenshot_path = 'uploads/payments/' . $filename;
}

try {
    $pdo->beginTransaction();
    
    // Check if time slot is still available
    $stmt = $pdo->prepare("
        SELECT id FROM appointments 
        WHERE barber_id = ? AND appointment_date = ? AND appointment_time = ? 
        AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$booking['barber_id'], $booking['date'], $booking['time']]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('This time slot was just booked by someone else. Please choose another.');
    }
    
    // Insert appointment
    $stmt = $pdo->prepare("
        INSERT INTO appointments 
        (customer_id, barber_id, service_id, appointment_date, appointment_time, 
         price_at_booking, bonus_used, bonus_earned, notes, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $result = $stmt->execute([
        $_SESSION['user_id'],
        $booking['barber_id'],
        $booking['service_id'],
        $booking['date'],
        $booking['time'],
        $booking['final_price'],
        $booking['bonus_used'],
        10,
        $booking['notes'] ?? ''
    ]);
    
    if (!$result) {
        throw new Exception('Failed to save appointment');
    }
    
    $appointment_id = $pdo->lastInsertId();
    
    // Update user bonus points
    $stmt = $pdo->prepare("
        UPDATE users 
        SET bonus_points = bonus_points - ? + 10 
        WHERE id = ?
    ");
    $stmt->execute([$booking['bonus_used'], $_SESSION['user_id']]);
    
    // Update session bonus
    $stmt = $pdo->prepare("SELECT bonus_points FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['bonus_points'] = $stmt->fetchColumn();
    
    // Record payment
    $stmt = $pdo->prepare("
        INSERT INTO payments 
        (appointment_id, customer_id, amount, payment_method, payment_status, screenshot_path, paid_at)
        VALUES (?, ?, ?, ?, 'completed', ?, NOW())
    ");
    $stmt->execute([
        $appointment_id,
        $_SESSION['user_id'],
        $booking['final_price'],
        $payment_method,
        $screenshot_path
    ]);
    
    $pdo->commit();
    
    // Clear pending booking
    unset($_SESSION['pending_booking']);
    
    $_SESSION['success'] = 'Payment successful! Your appointment is being processed.';
    redirect('my-bookings.php');
    
} catch (Exception $e) {
    // Rollback only if transaction is active
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Delete uploaded file if exists
    if ($screenshot_path && file_exists('../' . $screenshot_path)) {
        unlink('../' . $screenshot_path);
    }
    
    $_SESSION['payment_error'] = 'Payment failed: ' . $e->getMessage();
    redirect('payment.php');
}
?>