<?php
// ==============================================
// config.php - Database Configuration
// ==============================================

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set default timezone for Nepal
date_default_timezone_set('Asia/Kathmandu');

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
$host = 'localhost';
$dbname = 'project';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ==============================================
// Khalti Sandbox Payment Gateway Configuration
// ==============================================
if (!defined('KHALTI_BASE_URL')) {
    define('KHALTI_BASE_URL', 'https://dev.khalti.com/api/v2/');
}

// Khalti Sandbox Secret Key (from test-admin.khalti.com)
if (!defined('KHALTI_SECRET_KEY')) {
    define('KHALTI_SECRET_KEY', 'live_secret_key_6821c82c732e4112b88f40b0dec3b5e4');
}

// Khalti Sandbox Public Key
if (!defined('KHALTI_PUBLIC_KEY')) {
    define('KHALTI_PUBLIC_KEY', 'live_public_key_7c7a72d3e0b240ff8d1dbdbfdbda1ca0');
}

// Application Base URL for return callbacks
if (!defined('APP_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('APP_URL', $protocol . $host . '/stylecut/');
}
?>