<?php
// ==============================================
// customer/booking.php - Book Appointment
// ==============================================
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../login.php');
}

$user = getUserById($pdo, $_SESSION['user_id']);
$services = getAllServices($pdo);
$barbers = getAllBarbers($pdo);

$page_title = 'Book Appointment - Stylecut Nepal';
require_once '../includes/header.php';
?>

<h1 class="page-title">📅 Book Appointment</h1>

<div class="bonus-card">
    <div>
        <strong>Your Bonus Points:</strong>
        <span class="bonus-points"><?php echo $user['bonus_points'] ?? 0; ?></span>
    </div>
    <div>1 point = NPR 1 discount</div>
</div>

<div class="booking-container">
    <form action="process-booking.php" method="POST" class="booking-form" id="bookingForm">
        <div class="form-group">
            <label for="service_id">1. Choose Service *</label>
            <select name="service_id" id="service_id" required>
                <option value="">-- Select a service --</option>
                <?php foreach ($services as $service): ?>
                    <option value="<?php echo $service['id']; ?>" data-price="<?php echo $service['price']; ?>">
                        <?php echo htmlspecialchars($service['name']); ?> - NPR <?php echo $service['price']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="barber_id">2. Choose Barber *</label>
            <select name="barber_id" id="barber_id" required>
                <option value="">-- Select a barber --</option>
                <?php foreach ($barbers as $barber): ?>
                    <option value="<?php echo $barber['id']; ?>">
                        <?php echo htmlspecialchars($barber['name']); ?>
                        <?php if (!empty($barber['specialty'])): ?>
                            (<?php echo htmlspecialchars($barber['specialty']); ?>)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="appointment_date">3. Select Date *</label>
            <input type="date" name="appointment_date" id="appointment_date" required>
        </div>

        <div class="form-group">
            <label>4. Choose Time *</label>
            <div class="time-grid">
                <?php
                $times = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
                foreach ($times as $time):
                ?>
                <button type="button" class="time-slot" data-time="<?php echo $time; ?>:00">
                    <?php echo date('h:i A', strtotime($time)); ?>
                </button>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="appointment_time" id="selected_time" required>
        </div>

        <div class="form-group">
            <label for="notes">Additional Notes (optional)</label>
            <textarea name="notes" id="notes" rows="4" placeholder="Tell the barber your preferences..."></textarea>
        </div>

        <div class="price-preview">
            Total: <span id="price_display">NPR 0</span>
        </div>

        <div class="bonus-checkbox">
            <label>
                <input type="checkbox" name="use_bonus" id="use_bonus">
                Use bonus points? (<?php echo $user['bonus_points'] ?? 0; ?> points available)
            </label>
        </div>

        <button type="submit" id="continueBtn" class="btn btn-large btn-block">
    Continue to Payment
</button>
</div>

<script>
const serviceSelect = document.getElementById('service_id');
const priceDisplay = document.getElementById('price_display');
const useBonus = document.getElementById('use_bonus');
const bonusPoints = <?php echo $user['bonus_points'] ?? 0; ?>;

const barberSelect = document.getElementById('barber_id');
const dateInput = document.getElementById('appointment_date');
const selectedTime = document.getElementById('selected_time');
const bookingForm = document.getElementById('bookingForm');


// Price
function updatePrice() {
    const option = serviceSelect.options[serviceSelect.selectedIndex];
    let price = option ? parseInt(option.dataset.price || 0) : 0;

    if (useBonus.checked) {
        price = Math.max(0, price - Math.min(price, bonusPoints));
    }

    priceDisplay.textContent = 'NPR ' + price;
}

serviceSelect.addEventListener('change', updatePrice);
useBonus.addEventListener('change', updatePrice);


// Check all slots
function checkSlots() {

    if (!barberSelect.value || !dateInput.value) return;

    selectedTime.value = '';

    document.querySelectorAll('.time-slot').forEach(slot => {

        slot.classList.remove('available', 'booked', 'selected');

        fetch('check-slot.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body:
                'barber_id=' + encodeURIComponent(barberSelect.value) +
                '&appointment_date=' + encodeURIComponent(dateInput.value) +
                '&appointment_time=' + encodeURIComponent(slot.dataset.time)
        })
        .then(response => response.json())
        .then(data => {

           if (data.status === 'booked' || data.status === 'past') {

    slot.classList.add('booked');
    slot.disabled = true;

}
else if (data.status === 'available') {

    slot.classList.add('available');
    slot.disabled = false;

}

        });

    });
}


// Barber/date changed
barberSelect.addEventListener('change', checkSlots);
dateInput.addEventListener('change', checkSlots);


// Select available slot
document.querySelectorAll('.time-slot').forEach(slot => {

    slot.addEventListener('click', function() {

        if (this.disabled) return;

        document.querySelectorAll('.time-slot')
            .forEach(btn => btn.classList.remove('selected'));

        this.classList.add('selected');
        selectedTime.value = this.dataset.time;

    });

});


// Final check before payment
bookingForm.addEventListener('submit', function(e) {

    e.preventDefault();

    if (!barberSelect.value || !dateInput.value || !selectedTime.value) {
        alert('Please select barber, date and time.');
        return;
    }

    fetch('check-slot.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body:
            'barber_id=' + encodeURIComponent(barberSelect.value) +
            '&appointment_date=' + encodeURIComponent(dateInput.value) +
            '&appointment_time=' + encodeURIComponent(selectedTime.value)
    })
    .then(response => response.json())
    .then(data => {

        if (data.status === 'available') {

            bookingForm.submit();

        } else {

            alert('❌ This time slot is already booked.');
            checkSlots();

        }

    })
    .catch(() => {
        alert('Unable to check availability.');
    });

});
</script>

<?php require_once '../includes/footer.php'; ?>