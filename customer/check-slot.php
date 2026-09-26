<?php
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn() || !isCustomer()) {
    http_response_code(403);
    exit;
}

$barber_id = $_POST['barber_id'] ?? 0;
$date = $_POST['appointment_date'] ?? '';
$time = $_POST['appointment_time'] ?? '';

if (!$barber_id || !$date || !$time) {
    echo json_encode([
        'status' => 'error'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Check operating hours:
| Mon-Fri: 9:00 AM - 7:00 PM (Last slot 6:00 PM)
| Saturday: 10:00 AM - 6:00 PM (Last slot 5:00 PM)
| Sunday: Closed
|--------------------------------------------------------------------------
*/
$day_of_week = date('w', strtotime($date));

// Sunday: Closed
if ($day_of_week == 0) {
    echo json_encode([
        'status' => 'closed',
        'message' => 'Stylecut is closed on Sundays'
    ]);
    exit;
}

// Saturday: 10AM - 6PM
if ($day_of_week == 6) {
    if (strtotime($time) < strtotime('10:00:00')) {
        echo json_encode([
            'status' => 'closed',
            'message' => 'Stylecut opens at 10:00 AM on Saturdays'
        ]);
        exit;
    }
    if (strtotime($time) >= strtotime('18:00:00')) {
        echo json_encode([
            'status' => 'closed',
            'message' => 'Stylecut closes at 6:00 PM on Saturdays'
        ]);
        exit;
    }
}

// Mon-Fri: 9AM - 7PM
if ($day_of_week >= 1 && $day_of_week <= 5) {
    if (strtotime($time) < strtotime('09:00:00') || strtotime($time) >= strtotime('19:00:00')) {
        echo json_encode([
            'status' => 'closed',
            'message' => 'Stylecut is open 9:00 AM - 7:00 PM on weekdays'
        ]);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Automatically disable past time slots for TODAY
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Kathmandu');

$today = date('Y-m-d');
$currentTime = date('H:i:s');

if ($date == $today && strtotime($time) <= strtotime($currentTime)) {
    echo json_encode([
        'status' => 'past'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Check booked appointments
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
SELECT id
FROM appointments
WHERE barber_id = ?
AND appointment_date = ?
AND appointment_time = ?
AND status IN ('pending','confirmed')
");

$stmt->execute([$barber_id,$date,$time]);

if($stmt->rowCount()>0){

    echo json_encode([
        "status"=>"booked"
    ]);

}else{

    echo json_encode([
        "status"=>"available"
    ]);

}