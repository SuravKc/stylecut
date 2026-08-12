<?php
// ==============================================
// index.php - Stylecut Nepal Homepage
// ==============================================

require_once 'config.php';
require_once 'functions.php';

$page_title = 'Stylecut Nepal - Home';

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

<section class="home-section">

    <div class="section-heading">

        <span>WHAT WE OFFER</span>

        <h2>Our Services</h2>

        <p>
            Quality grooming at simple, affordable prices.
        </p>

    </div>


    <div class="home-grid">


        <!-- HAIRCUT SERVICES -->

        <div class="home-card">

            <h3 class="home-card-title">
                ✂️ Haircuts
            </h3>

            <ul class="service-list">

                <li>
                    <span>Kid's Haircut</span>
                    <strong>NPR 300</strong>
                </li>

                <li>
                    <span>Haircut - Basic</span>
                    <strong>NPR 400</strong>
                </li>

                <li>
                    <span>Haircut - Premium</span>
                    <strong>NPR 700</strong>
                </li>

            </ul>

        </div>


        <!-- GROOMING SERVICES -->

        <div class="home-card">

            <h3 class="home-card-title">
                🧔 Grooming
            </h3>

            <ul class="service-list">

                <li>
                    <span>Head Massage</span>
                    <strong>NPR 350</strong>
                </li>

                <li>
                    <span>Beard Sculpt</span>
                    <strong>NPR 500</strong>
                </li>

                <li>
                    <span>Facial Deluxe</span>
                    <strong>NPR 1100</strong>
                </li>

                <li>
                    <span>Hair Color</span>
                    <strong>NPR 2000</strong>
                </li>

            </ul>

        </div>


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


<?php
// ==============================================
// FOOTER
// ==============================================

require_once 'includes/footer.php';
?>