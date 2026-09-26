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
$selected_service_id = intval($_GET['service_id'] ?? 0);

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
                    <option value="<?php echo $service['id']; ?>" data-price="<?php echo $service['price']; ?>" <?php echo $selected_service_id === (int)$service['id'] ? 'selected' : ''; ?>>
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
            <label for="appointment_date">3. Select Date * <small style="color: var(--text-muted); font-weight: normal;">(Mon – Sat • Closed Sundays)</small></label>
            <input type="date" name="appointment_date" id="appointment_date" min="<?php echo date('Y-m-d'); ?>" required>
            <div id="sunday-warning" style="display: none; margin-top: 8px; padding: 10px 14px; background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); border-radius: 8px; color: #ef4444; font-size: 0.88rem; font-weight: 500;">
                🚫 <strong>Stylecut is closed on Sundays.</strong> Please choose an appointment between Monday and Saturday.
            </div>
        </div>

        <div class="form-group">
            <label>4. Choose Time * <span id="day-hours-badge" style="font-size: 0.85rem; font-weight: normal; margin-left: 8px; color: #d4af37;"></span></label>
            <div class="time-grid">
                <?php
                $times = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'];
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

// If service was pre-selected via URL, update price immediately
if (serviceSelect.value) {
    updatePrice();
}


const sundayWarning = document.getElementById('sunday-warning');
const dayHoursBadge = document.getElementById('day-hours-badge');

function getDayOfWeek(dateStr) {
    if (!dateStr) return null;
    const parts = dateStr.split('-');
    if (parts.length === 3) {
        return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10)).getDay();
    }
    return null;
}

// Check all slots
function checkSlots() {

    if (!barberSelect.value || !dateInput.value) return;

    selectedTime.value = '';
    const day = getDayOfWeek(dateInput.value);

    // Update Hours Badge and Sunday Warning
    if (day === 0) {
        if (dayHoursBadge) {
            dayHoursBadge.textContent = '• Closed on Sundays';
            dayHoursBadge.style.color = '#ef4444';
        }
        if (sundayWarning) sundayWarning.style.display = 'block';
    } else if (day === 6) {
        if (dayHoursBadge) {
            dayHoursBadge.textContent = '• Saturday: 10:00 AM – 6:00 PM';
            dayHoursBadge.style.color = '#d4af37';
        }
        if (sundayWarning) sundayWarning.style.display = 'none';
    } else if (day >= 1 && day <= 5) {
        if (dayHoursBadge) {
            dayHoursBadge.textContent = '• Mon–Fri: 9:00 AM – 7:00 PM';
            dayHoursBadge.style.color = '#10b981';
        }
        if (sundayWarning) sundayWarning.style.display = 'none';
    } else {
        if (dayHoursBadge) dayHoursBadge.textContent = '';
        if (sundayWarning) sundayWarning.style.display = 'none';
    }

    document.querySelectorAll('.time-slot').forEach(slot => {

        slot.classList.remove('available', 'booked', 'selected');

        // Sunday: Closed completely
        if (day === 0) {
            slot.classList.add('booked');
            slot.disabled = true;
            slot.title = 'Stylecut is closed on Sundays';
            return;
        }

        // Saturday: 10AM - 6PM (09:00 AM and 06:00 PM slots closed)
        if (day === 6) {
            if (slot.dataset.time === '09:00:00') {
                slot.classList.add('booked');
                slot.disabled = true;
                slot.title = 'Stylecut opens at 10:00 AM on Saturdays';
                return;
            }
            if (slot.dataset.time === '18:00:00') {
                slot.classList.add('booked');
                slot.disabled = true;
                slot.title = 'Stylecut closes at 6:00 PM on Saturdays';
                return;
            }
        }

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

            if (data.status === 'booked' || data.status === 'past' || data.status === 'closed') {
                slot.classList.add('booked');
                slot.disabled = true;
                if (data.message) {
                    slot.title = data.message;
                }
            } else if (data.status === 'available') {
                slot.classList.add('available');
                slot.disabled = false;
                slot.title = 'Available';
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

        if (!dateInput.value) {
            alert('Please select an appointment date first.');
            dateInput.focus();
            return;
        }

        if (!barberSelect.value) {
            alert('Please select a barber first.');
            barberSelect.focus();
            return;
        }

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

    const day = getDayOfWeek(dateInput.value);
    if (day === 0) {
        alert('🚫 Stylecut is closed on Sundays. Please choose Monday through Saturday.');
        return;
    }
    if (day === 6 && (selectedTime.value === '09:00:00' || selectedTime.value === '18:00:00')) {
        alert('🚫 On Saturdays, Stylecut is open from 10:00 AM to 6:00 PM.');
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

        } else if (data.status === 'closed') {

            alert('🚫 ' + (data.message || 'Stylecut is closed at this time.'));
            checkSlots();

        } else if (data.status === 'past') {

            alert('⚠️ This time slot has already passed for today.');
            checkSlots();

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