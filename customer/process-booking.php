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

// Prevent booking past hours for today
if ($appointment_date === date('Y-m-d') && strtotime($appointment_time) <= strtotime(date('H:i:s'))) {
    $_SESSION['error'] = 'Cannot book a time slot that has already passed today';
    redirect('booking.php');
}

// Check Operating Hours:
// Mon-Fri: 9AM - 7PM (Slots 09:00 to 18:00)
// Saturday: 10AM - 6PM (Slots 10:00 to 17:00)
// Sunday: Closed
$day_of_week = date('w', $selected_date);

if ($day_of_week == 0) {
    $_SESSION['error'] = 'Stylecut is closed on Sundays. Please choose an appointment between Monday and Saturday.';
    redirect('booking.php');
}

if ($day_of_week == 6) {
    if (strtotime($appointment_time) < strtotime('10:00:00') || strtotime($appointment_time) >= strtotime('18:00:00')) {
        $_SESSION['error'] = 'On Saturdays, Stylecut is open from 10:00 AM to 6:00 PM (last appointment at 5:00 PM).';
        redirect('booking.php');
    }
}

if ($day_of_week >= 1 && $day_of_week <= 5) {
    if (strtotime($appointment_time) < strtotime('09:00:00') || strtotime($appointment_time) >= strtotime('19:00:00')) {
        $_SESSION['error'] = 'On weekdays, Stylecut is open from 9:00 AM to 7:00 PM (last appointment at 6:00 PM).';
        redirect('booking.php');
    }
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