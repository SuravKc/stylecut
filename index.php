<?php
// ==============================================
// index.php - Stylecut Nepal Homepage
// ==============================================

require_once 'config.php';
require_once 'functions.php';

$page_title = 'Stylecut Nepal - Home';

// Fetch all active grooming services
$services = getAllServices($pdo);

// Category & Icon & Photo mapping
$service_meta = [
    1 => ['category' => 'Haircuts', 'icon' => '✂️', 'image' => '/stylecut/assets/images/services/haircut.jpg'],
    2 => ['category' => 'Haircuts', 'icon' => '✂️', 'image' => '/stylecut/assets/images/services/haircut.jpg'],
    6 => ['category' => 'Haircuts', 'icon' => '✂️', 'image' => '/stylecut/assets/images/services/haircut.jpg'],
    3 => ['category' => 'Beard Grooming', 'icon' => '🧔', 'image' => '/stylecut/assets/images/services/beard.jpg'],
    4 => ['category' => 'Skin & Facial', 'icon' => '🧖', 'image' => '/stylecut/assets/images/services/facial.jpg'],
    5 => ['category' => 'Hair Styling', 'icon' => '🎨', 'image' => '/stylecut/assets/images/services/haircut.jpg'],
    7 => ['category' => 'Therapy & Massage', 'icon' => '💆', 'image' => '/stylecut/assets/images/services/massage.jpg'],
];

if (!function_exists('getServiceMeta')) {
    function getServiceMeta($service) {
        global $service_meta;
        if (isset($service_meta[$service['id']])) {
            return $service_meta[$service['id']];
        }
        $name = strtolower($service['name']);
        if (strpos($name, 'beard') !== false) {
            return ['category' => 'Beard Grooming', 'icon' => '🧔', 'image' => '/stylecut/assets/images/services/beard.jpg'];
        }
        if (strpos($name, 'facial') !== false) {
            return ['category' => 'Skin & Facial', 'icon' => '🧖', 'image' => '/stylecut/assets/images/services/facial.jpg'];
        }
        if (strpos($name, 'massage') !== false) {
            return ['category' => 'Therapy & Massage', 'icon' => '💆', 'image' => '/stylecut/assets/images/services/massage.jpg'];
        }
        return ['category' => 'Haircuts', 'icon' => '✂️', 'image' => '/stylecut/assets/images/services/haircut.jpg'];
    }
}

require_once 'includes/header.php';
?>


<!-- ==================================================
     HERO SECTION
================================================== -->

<section class="hero-section">

    <div class="hero-content">

        <div class="hero-label">
            ✂️ STYLECUT NEPAL
        </div>

        <h1 class="hero">
            Style that<br>
            <span>defines you.</span>
        </h1>

        <p class="hero-sub">
            Premium cuts · Beard · Facial · Heritage barber
        </p>

        <div class="hero-action">

            <?php if (isLoggedIn()): ?>

                <?php if (isCustomer()): ?>

                    <a href="customer/booking.php" class="btn btn-large">
                        📅 Book an Appointment
                    </a>

                <?php elseif (isBarber()): ?>

                    <a href="barber/appointments.php" class="btn btn-large">
                        📋 View Appointments
                    </a>

                <?php elseif (isAdmin()): ?>

                    <a href="admin/dashboard.php" class="btn btn-large">
                        ⚙️ Admin Dashboard
                    </a>

                <?php else: ?>

                    <a href="login.php" class="btn btn-large">
                        Login to Book
                    </a>

                <?php endif; ?>

            <?php else: ?>

                <a href="login.php" class="btn btn-large">
                    📅 Book Appointment
                </a>

            <?php endif; ?>

        </div>

    </div>

</section>


<!-- ==================================================
     SERVICES SECTION
================================================== -->

<section class="home-section" id="services">

    <div class="section-heading">

        <span>WHAT WE OFFER</span>

        <h2>Our Services</h2>

        <p>
            Precision haircuts, beard sculpting, and premium grooming. Click any service to view photos, details, and book your appointment.
        </p>

    </div>

    <div class="services-grid">

        <?php foreach ($services as $service): 
            $meta = getServiceMeta($service);
            $service_id = (int)$service['id'];
            $service_name = $service['name'];
            $service_desc = $service['description'] ?? 'Precision grooming experience with professional stylist.';
            $service_price = number_format($service['price'], 2);
            $service_duration = (int)$service['duration_minutes'];

            $service_payload = [
                'id' => $service_id,
                'name' => $service_name,
                'category' => $meta['category'],
                'icon' => $meta['icon'],
                'image' => $meta['image'],
                'duration' => $service_duration,
                'price' => $service_price,
                'description' => $service_desc
            ];
        ?>

        <div class="service-card" onclick='openServiceModal(<?php echo htmlspecialchars(json_encode($service_payload), ENT_QUOTES, "UTF-8"); ?>)'>

            <div>
                <div class="service-card-top">
                    <span class="service-category-tag">
                        <?php echo $meta['icon']; ?> <?php echo htmlspecialchars($meta['category']); ?>
                    </span>
                    <span class="service-duration-pill">
                        ⏱️ <?php echo $service_duration; ?> mins
                    </span>
                </div>

                <h3 class="service-card-title"><?php echo htmlspecialchars($service_name); ?></h3>
                <p class="service-card-desc"><?php echo htmlspecialchars($service_desc); ?></p>
            </div>

            <div class="service-card-bottom">
                <span class="service-card-price">NPR <?php echo $service_price; ?></span>
                <span class="service-card-action-btn">
                    View & Book →
                </span>
            </div>

        </div>

        <?php endforeach; ?>

    </div>

</section>


<!-- ==================================================
     VISIT US SECTION
================================================== -->

<section class="home-section info-section">

    <div class="section-heading">

        <span>VISIT US</span>

        <h2>Stylecut Nepal</h2>

    </div>


    <div class="home-grid">


        <!-- HOURS -->

        <div class="home-card">

            <h3 class="home-card-title">
                ⏰ Hours
            </h3>

            <ul class="hours-list">

                <li>
                    <span>Mon-Fri:</span>
                    9AM - 7PM
                </li>

                <li>
                    <span>Saturday:</span>
                    10AM - 6PM
                </li>

                <li>
                    <span>Sunday:</span>
                    Closed
                </li>

            </ul>

        </div>


        <!-- LOCATION -->

        <div class="home-card">

            <h3 class="home-card-title">
                📍 Location
            </h3>

            <ul class="location-list">

                <li>
                    Kathmandu, Nepal
                </li>

                <li>
                    📞 +977 9812345678
                </li>

                <li>
                    ✉️ info@stylecut.com
                </li>

            </ul>

        </div>


    </div>

</section>


<!-- ==================================================
     FINAL BOOKING CTA
================================================== -->

<section class="home-cta">

    <h2>
        Ready for your next look?
    </h2>

    <p>
        Book your appointment with Stylecut Nepal.
    </p>


    <?php if (isLoggedIn()): ?>

        <?php if (isCustomer()): ?>

            <a href="customer/booking.php" class="btn btn-large">
                📅 Book an Appointment
            </a>

        <?php elseif (isBarber()): ?>

            <a href="barber/appointments.php" class="btn btn-large">
                📋 View Appointments
            </a>

        <?php elseif (isAdmin()): ?>

            <a href="admin/dashboard.php" class="btn btn-large">
                ⚙️ Admin Dashboard
            </a>

        <?php else: ?>

            <a href="login.php" class="btn btn-large">
                Login to Book
            </a>

        <?php endif; ?>

    <?php else: ?>

        <a href="login.php" class="btn btn-large">
            📅 Book Appointment
        </a>

    <?php endif; ?>

</section>


<!-- ==================================================
     SERVICE DETAIL MODAL (SHOWS PHOTO ON CLICK)
================================================== -->
<div id="serviceDetailModal" class="service-modal-backdrop" onclick="closeServiceModal(event)">
    <div class="service-modal-dialog" onclick="event.stopPropagation()">
        <button type="button" class="service-modal-close" onclick="closeServiceModal()">&times;</button>
        
        <div class="service-modal-img-wrap">
            <img id="modalServiceImg" src="" alt="Service Photo" class="service-modal-img">
        </div>
        
        <div class="service-modal-body">
            <div class="service-modal-meta">
                <span id="modalServiceCategory" class="service-modal-category">CATEGORY</span>
                <span id="modalServiceDuration" class="service-modal-duration">⏱️ 30 mins</span>
            </div>
            
            <h2 id="modalServiceTitle" class="service-modal-title">Service Title</h2>
            <p id="modalServiceDesc" class="service-modal-desc">Service description goes here.</p>
            
            <div class="service-modal-price-box">
                <span class="service-modal-price-label">Price per Session</span>
                <span id="modalServicePriceVal" class="service-modal-price-val">NPR 0.00</span>
            </div>
            
            <?php if (isCustomer()): ?>
                <div class="service-modal-actions">
                    <a id="modalBookDirectBtn" href="#" class="btn btn-large btn-block" style="background:#000000; color:#ffffff;">
                        📅 Book This Service Now
                    </a>
                </div>
            <?php elseif (isLoggedIn()): ?>
                <div class="service-modal-actions">
                    <?php if (isAdmin()): ?>
                        <a href="/stylecut/admin/dashboard.php" class="btn btn-large btn-block" style="background:#000000; color:#ffffff;">
                            ⚙️ Admin Dashboard
                        </a>
                    <?php else: ?>
                        <a href="/stylecut/barber/dashboard.php" class="btn btn-large btn-block" style="background:#000000; color:#ffffff;">
                            ✂️ Barber Dashboard
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="service-modal-auth-notice">
                    <div class="service-modal-auth-notice-icon">🔒</div>
                    <div class="service-modal-auth-notice-text">
                        <strong>Login Required to Book</strong>
                        Please log in with your customer account or register below to complete your appointment booking.
                    </div>
                </div>
                <div class="service-modal-actions">
                    <a id="modalLoginBtn" href="#" class="btn btn-large btn-block" style="background:#000000; color:#ffffff;">
                        🔑 Login to Book
                    </a>
                    <div class="service-modal-secondary-actions">
                        <a id="modalRegisterBtn" href="#" class="btn">
                            ✨ Register Account
                        </a>
                        <button type="button" class="btn cancel-btn" onclick="closeServiceModal()">
                            Close
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function openServiceModal(data) {
    document.getElementById('modalServiceImg').src = data.image;
    document.getElementById('modalServiceCategory').textContent = data.icon + ' ' + data.category;
    document.getElementById('modalServiceDuration').textContent = '⏱️ ' + data.duration + ' mins';
    document.getElementById('modalServiceTitle').textContent = data.name;
    document.getElementById('modalServiceDesc').textContent = data.description;
    document.getElementById('modalServicePriceVal').textContent = 'NPR ' + data.price;
    
    const targetBookingUrl = encodeURIComponent('customer/booking.php?service_id=' + data.id);
    
    const bookDirectBtn = document.getElementById('modalBookDirectBtn');
    if (bookDirectBtn) {
        bookDirectBtn.href = '/stylecut/customer/booking.php?service_id=' + data.id;
    }
    
    const loginBtn = document.getElementById('modalLoginBtn');
    if (loginBtn) {
        loginBtn.href = '/stylecut/login.php?redirect=' + targetBookingUrl + '&msg=login_required';
    }
    
    const regBtn = document.getElementById('modalRegisterBtn');
    if (regBtn) {
        regBtn.href = '/stylecut/register.php?redirect=' + targetBookingUrl;
    }
    
    document.getElementById('serviceDetailModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeServiceModal(event) {
    if (!event || event.target.id === 'serviceDetailModal' || event.target.classList.contains('service-modal-close') || event.target.classList.contains('cancel-btn')) {
        document.getElementById('serviceDetailModal').classList.remove('active');
        document.body.style.overflow = '';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeServiceModal();
    }
});
</script>

<?php
// ==============================================
// FOOTER
// ==============================================

require_once 'includes/footer.php';
?>