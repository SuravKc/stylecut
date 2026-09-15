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

if (!function_exists('getAllAppointmentsAdmin')) {
    function getAllAppointmentsAdmin($pdo, $filters = [], $limit = null, $offset = null) {
        $sql = "
            SELECT a.*, 
                   cu.name as customer_name, cu.email as customer_email, cu.phone as customer_phone,
                   bu.name as barber_name, b.specialty as barber_specialty,
                   s.name as service_name, s.duration_minutes,
                   p.payment_method, p.payment_status, p.screenshot_path, p.amount as payment_amount, p.paid_at
            FROM appointments a
            JOIN users cu ON a.customer_id = cu.id
            JOIN barbers b ON a.barber_id = b.id
            JOIN users bu ON b.user_id = bu.id
            JOIN services s ON a.service_id = s.id
            LEFT JOIN payments p ON a.id = p.appointment_id
            WHERE 1=1
        ";
        
        $params = [];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= " AND a.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['barber_id']) && $filters['barber_id'] !== 'all') {
            $sql .= " AND a.barber_id = :barber_id";
            $params[':barber_id'] = intval($filters['barber_id']);
        }

        if (!empty($filters['date'])) {
            $sql .= " AND a.appointment_date = :date";
            $params[':date'] = $filters['date'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (cu.name LIKE :search OR cu.email LIKE :search OR cu.phone LIKE :search OR a.id = :exact_id)";
            $params[':search'] = '%' . trim($filters['search']) . '%';
            $params[':exact_id'] = intval($filters['search']);
        }

        $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.id DESC";

        if ($limit !== null) {
            $sql .= " LIMIT " . intval($limit);
            if ($offset !== null) {
                $sql .= " OFFSET " . intval($offset);
            }
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

if (!function_exists('getAdminDashboardStats')) {
    function getAdminDashboardStats($pdo) {
        $stats = [
            'total' => 0,
            'pending' => 0,
            'confirmed' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'today' => 0,
            'total_revenue' => 0.0
        ];

        // Status counts
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
        while ($row = $stmt->fetch()) {
            if (isset($stats[$row['status']])) {
                $stats[$row['status']] = (int)$row['count'];
            }
        }
        $stats['total'] = $stats['pending'] + $stats['confirmed'] + $stats['completed'] + $stats['cancelled'];

        // Today's appointments count
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = ?");
        $stmt->execute([$today]);
        $stats['today'] = (int)$stmt->fetchColumn();

        // Total revenue from completed & confirmed appointments
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(price_at_booking), 0) as revenue 
            FROM appointments 
            WHERE status IN ('confirmed', 'completed')
        ");
        $stats['total_revenue'] = (float)$stmt->fetchColumn();

        return $stats;
    }
}

if (!function_exists('updateAppointmentStatusAdmin')) {
    function updateAppointmentStatusAdmin($pdo, $appointment_id, $new_status) {
        $allowed = ['confirmed', 'cancelled', 'completed', 'pending'];
        if (!in_array($new_status, $allowed)) {
            throw new Exception('Invalid appointment status.');
        }

        $pdo->beginTransaction();
        try {
            // Fetch appointment details
            $stmt = $pdo->prepare("SELECT id, customer_id, status, bonus_used FROM appointments WHERE id = ? FOR UPDATE");
            $stmt->execute([$appointment_id]);
            $appt = $stmt->fetch();

            if (!$appt) {
                throw new Exception('Appointment not found.');
            }

            $prev_status = $appt['status'];

            // Update status
            $stmt = $pdo->prepare("UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $appointment_id]);

            // If declining or cancelling, refund bonus points if redeemed and not already refunded
            if ($new_status === 'cancelled' && $prev_status !== 'cancelled') {
                if ($appt['bonus_used'] > 0) {
                    $refund_stmt = $pdo->prepare("UPDATE users SET bonus_points = bonus_points + ? WHERE id = ?");
                    $refund_stmt->execute([$appt['bonus_used'], $appt['customer_id']]);
                }
            }

            // Update payment status if needed (e.g. if cancelled and payment pending)
            if ($new_status === 'cancelled') {
                $pdo->prepare("UPDATE payments SET payment_status = 'refunded' WHERE appointment_id = ? AND payment_status = 'completed'")
                    ->execute([$appointment_id]);
            }

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

if (!function_exists('getAllServicesAdmin')) {
    function getAllServicesAdmin($pdo) {
        $stmt = $pdo->query("
            SELECT s.*, 
                   (SELECT COUNT(*) FROM appointments a WHERE a.service_id = s.id) as total_bookings
            FROM services s
            ORDER BY s.is_active DESC, s.id ASC
        ");
        return $stmt->fetchAll();
    }
}

if (!function_exists('getAllBarbersAdmin')) {
    function getAllBarbersAdmin($pdo) {
        $stmt = $pdo->query("
            SELECT b.*, u.name, u.email, u.phone, u.address, u.is_active as user_active,
                   (SELECT COUNT(*) FROM appointments a WHERE a.barber_id = b.id) as total_bookings,
                   (SELECT COUNT(*) FROM appointments a WHERE a.barber_id = b.id AND a.status = 'pending') as pending_bookings
            FROM barbers b
            JOIN users u ON b.user_id = u.id
            ORDER BY u.is_active DESC, b.is_available DESC, u.name ASC
        ");
        return $stmt->fetchAll();
    }
}

if (!function_exists('getAllCustomersAdmin')) {
    function getAllCustomersAdmin($pdo, $search = '') {
        $sql = "
            SELECT u.*,
                   (SELECT COUNT(*) FROM appointments a WHERE a.customer_id = u.id) as total_bookings,
                   (SELECT COALESCE(SUM(price_at_booking), 0) FROM appointments a WHERE a.customer_id = u.id AND a.status IN ('confirmed','completed')) as total_spent
            FROM users u
            WHERE u.role = 'customer'
        ";
        $params = [];
        if (!empty($search)) {
            $sql .= " AND (u.name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search OR u.id = :exact_id)";
            $params[':search'] = '%' . trim($search) . '%';
            $params[':exact_id'] = intval($search);
        }
        $sql .= " ORDER BY u.is_active DESC, u.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
?>