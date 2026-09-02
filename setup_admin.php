<?php
// ==============================================
// setup_admin.php - One-time Admin Setup Script
// ==============================================
require_once __DIR__ . '/config.php';

$admin_email = 'admin@stylecut.com';
$admin_pass = 'admin123';
$admin_name = 'Stylecut Admin';
$admin_phone = '9800000000';
$admin_address = 'Kathmandu, Nepal';

try {
    $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE email = ?");
    $stmt->execute([$admin_email]);
    $existing = $stmt->fetch();

    if ($existing) {
        $hashed = password_hash($admin_pass, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE users SET password = ?, role = 'admin', is_active = 1 WHERE email = ?");
        $update->execute([$hashed, $admin_email]);
        echo "Admin account exists (ID: {$existing['id']}) - password updated to: $admin_pass\n";
    } else {
        $hashed = password_hash($admin_pass, PASSWORD_DEFAULT);
        $insert = $pdo->prepare("
            INSERT INTO users (name, email, password, phone, address, role, bonus_points, is_active)
            VALUES (?, ?, ?, ?, ?, 'admin', 0, 1)
        ");
        $insert->execute([$admin_name, $admin_email, $hashed, $admin_phone, $admin_address]);
        $new_id = $pdo->lastInsertId();
        echo "Admin account created successfully (ID: $new_id)!\nEmail: $admin_email\nPassword: $admin_pass\n";
    }
} catch (Exception $e) {
    echo "Error setting up admin: " . $e->getMessage() . "\n";
}
