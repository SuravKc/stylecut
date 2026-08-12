<?php
// ==============================================
// customer/process-booking.php - Process Booking
// ==============================================
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('booking.php');
}

$service_id = $_POST['service_id'] ?? 0;
$barber_id = $_POST['barber_id'] ?? 0;
$appointment_date = $_POST['appointment_date'] ?? '';
$appointment_time = $_POST['appointment_time'] ?? '';
$notes = $_POST['notes'] ?? '';
$use_bonus = isset($_POST['use_bonus']);

if (!$service_id || !$barber_id || !$appointment_date || !$appointment_time) {
    $_SESSION['error'] = 'Please fill all required fields';
    redirect('booking.php');
}

$selected_date = strtotime($appointment_date);
$today = strtotime(date('Y-m-d'));
if ($selected_date < $today) {
    $_SESSION['error'] = 'Cannot book appointment in the past';
    redirect('booking.php');
}

try {
    $stmt = $pdo->prepare("SELECT price FROM services WHERE id = ? AND is_active = 1");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch();
    if (!$service) throw new Exception('Invalid service');
    $price = $service['price'];

    $stmt = $pdo->prepare("SELECT bonus_points FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $bonus_points = $user['bonus_points'] ?? 0;
    
    $bonus_used = 0;
    if ($use_bonus && $bonus_points > 0) {
        $bonus_used = min($bonus_points, $price);
    }
    $final_price = $price - $bonus_used;

    $stmt = $pdo->prepare("
        SELECT id FROM appointments 
        WHERE barber_id = ? AND appointment_date = ? AND appointment_time = ? 
        AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$barber_id, $appointment_date, $appointment_time]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('This time slot is already booked');
    }

    $_SESSION['pending_booking'] = [
        'service_id' => $service_id,
        'barber_id' => $barber_id,
        'date' => $appointment_date,
        'time' => $appointment_time,
        'notes' => $notes,
        'bonus_used' => $bonus_used,
        'final_price' => $final_price
    ];
    
    redirect('payment.php');
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Booking failed: ' . $e->getMessage();
    redirect('booking.php');
}
?>