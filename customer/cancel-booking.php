<?php
// ==============================================
// customer/cancel-booking.php - Cancel Appointment
// ==============================================
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is customer
if (!isLoggedIn() || !isCustomer()) {
    redirect('../login.php');
}

// Get appointment ID from URL
$appointment_id = $_GET['id'] ?? 0;

if (!$appointment_id) {
    $_SESSION['error'] = 'Invalid appointment';
    redirect('my-bookings.php');
}

try {
    // Get appointment details - fetch ALL needed fields
    $stmt = $pdo->prepare("SELECT status, bonus_used, appointment_date FROM appointments WHERE id = ? AND customer_id = ?");
    $stmt->execute([$appointment_id, $_SESSION['user_id']]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        throw new Exception('Appointment not found');
    }
    
    // Check if appointment can be cancelled
    if (!in_array($appointment['status'], ['pending', 'confirmed'])) {
        throw new Exception('This appointment cannot be cancelled');
    }
    
    // Check if appointment is in the past
    if ($appointment['appointment_date'] && strtotime($appointment['appointment_date']) < strtotime(date('Y-m-d'))) {
        throw new Exception('Cannot cancel past appointments');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Update appointment status to cancelled
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND customer_id = ?");
    $stmt->execute([$appointment_id, $_SESSION['user_id']]);
    
    // Refund bonus points if any were used
    if ($appointment['bonus_used'] > 0) {
        $stmt = $pdo->prepare("UPDATE users SET bonus_points = bonus_points + ? WHERE id = ?");
        $stmt->execute([$appointment['bonus_used'], $_SESSION['user_id']]);
        
        // Update session bonus points
        $stmt = $pdo->prepare("SELECT bonus_points FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $_SESSION['bonus_points'] = $stmt->fetchColumn();
    }
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success'] = 'Appointment cancelled successfully';
    
} catch (Exception $e) {
    // Only rollback if a transaction is active
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Exception $rollbackError) {
        // Ignore rollback errors
    }
    
    $_SESSION['error'] = 'Cancellation failed: ' . $e->getMessage();
}

redirect('my-bookings.php');
?>