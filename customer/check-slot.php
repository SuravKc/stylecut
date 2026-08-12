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