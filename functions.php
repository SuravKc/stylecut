<?php
// ==============================================
// functions.php - Helper Functions
// ==============================================

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('isCustomer')) {
    function isCustomer() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer';
    }
}

if (!function_exists('isBarber')) {
    function isBarber() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'barber';
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
}

if (!function_exists('redirect')) {
    function redirect($page) {
        header("Location: $page");
        exit;
    }
}

if (!function_exists('getUserById')) {
    function getUserById($pdo, $user_id) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
}

if (!function_exists('getBarberIdByUserId')) {
    function getBarberIdByUserId($pdo, $user_id) {
        $stmt = $pdo->prepare("SELECT id FROM barbers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $barber = $stmt->fetch();
        return $barber ? $barber['id'] : null;
    }
}

if (!function_exists('getAllServices')) {
    function getAllServices($pdo) {
        $stmt = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY price");
        return $stmt->fetchAll();
    }
}

if (!function_exists('getServiceById')) {
    function getServiceById($pdo, $service_id) {
        $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
        $stmt->execute([$service_id]);
        return $stmt->fetch();
    }
}

if (!function_exists('getAllBarbers')) {
    function getAllBarbers($pdo) {
        $stmt = $pdo->query("
            SELECT b.id, u.name, b.specialty 
            FROM barbers b
            JOIN users u ON b.user_id = u.id
            WHERE b.is_available = 1
            ORDER BY u.name
        ");
        return $stmt->fetchAll();
    }
}

if (!function_exists('getCustomerAppointments')) {
    function getCustomerAppointments($pdo, $customer_id, $filter = 'all', $limit = null) {
        $sql = "
            SELECT a.*, s.name as service_name, u.name as barber_name
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            JOIN barbers b ON a.barber_id = b.id
            JOIN users u ON b.user_id = u.id
            WHERE a.customer_id = ?
        ";
        if ($filter !== 'all') {
            $sql .= " AND a.status = :filter";
        }
        $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        $stmt = $pdo->prepare($sql);
        if ($filter !== 'all') {
            $stmt->execute(['customer_id' => $customer_id, 'filter' => $filter]);
        } else {
            $stmt->execute([$customer_id]);
        }
        return $stmt->fetchAll();
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date) {
        return date('M d, Y', strtotime($date));
    }
}

if (!function_exists('formatTime')) {
    function formatTime($time) {
        return date('h:i A', strtotime($time));
    }
}

if (!function_exists('getStatusBadgeClass')) {
    function getStatusBadgeClass($status) {
        $classes = [
            'pending' => 'status-pending',
            'confirmed' => 'status-confirmed',
            'cancelled' => 'status-cancelled',
            'completed' => 'status-completed'
        ];
        return $classes[$status] ?? 'status-pending';
    }
}
?>