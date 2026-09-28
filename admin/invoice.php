<?php
// ==============================================
// admin/invoice.php - Admin View Invoice Wrapper
// ==============================================
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

$id = intval($_GET['id'] ?? 0);
$print = isset($_GET['print']) ? '&print=1' : '';
header("Location: ../customer/invoice.php?id=$id$print");
exit;
