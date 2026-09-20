<?php
// ==============================================
// header.php - Header Template
// ==============================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Stylecut Nepal'; ?></title>
    <link rel="stylesheet" href="/stylecut/assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="container">
        <header>
            <a href="/stylecut/index.php" class="logo">Stylecut Nepal</a>
            
            <ul class="nav-links">
                <?php if (isset($_SESSION['user_role'])): ?>
                    <?php if ($_SESSION['user_role'] === 'customer'): ?>
                        <li><a href="/stylecut/index.php">Home</a></li>
                        <li><a href="/stylecut/customer/booking.php">Book</a></li>
                        <li><a href="/stylecut/customer/my-bookings.php">My Bookings</a></li>
                        <li><a href="/stylecut/customer/profile.php">Profile</a></li>
                        <li class="user-badge"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></li>
                        <li><a href="/stylecut/logout.php" class="btn">Logout</a></li>
                    <?php elseif ($_SESSION['user_role'] === 'barber'): ?>
                        <li><a href="/stylecut/index.php">Home</a></li>
                        <li><a href="/stylecut/barber/dashboard.php">Dashboard</a></li>
                        <li><a href="/stylecut/barber/appointments.php">Appointments</a></li>
                        <li class="user-badge"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Barber'); ?></li>
                        <li><a href="/stylecut/logout.php" class="btn">Logout</a></li>
                    <?php elseif ($_SESSION['user_role'] === 'admin'): ?>
                        <li><a href="/stylecut/index.php">Home</a></li>
                        <li><a href="/stylecut/admin/dashboard.php">Dashboard</a></li>
                        <li><a href="/stylecut/admin/appointments.php">Bookings</a></li>
                        <li><a href="/stylecut/admin/services.php">Services</a></li>
                        <li><a href="/stylecut/admin/barbers.php">Barbers</a></li>
                        <li><a href="/stylecut/admin/customers.php">Customers</a></li>
                        <li class="user-badge" style="background:#000000; color:#ffffff;">Admin: <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></li>
                        <li><a href="/stylecut/logout.php" class="btn">Logout</a></li>
                    <?php endif; ?>
                <?php else: ?>
                    <li><a href="/stylecut/index.php">Home</a></li>
                    <li><a href="/stylecut/login.php" class="btn">Login</a></li>
                    <li><a href="/stylecut/register.php" class="btn">Register</a></li>
                <?php endif; ?>
            </ul>
        </header>