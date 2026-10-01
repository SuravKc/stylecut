// ==============================================
// main.js - Main JavaScript
// ==============================================

document.addEventListener('DOMContentLoaded', function() {
    // Time slot selection
    const timeSlots = document.querySelectorAll('.time-slot');
    timeSlots.forEach(slot => {
        slot.addEventListener('click', function() {
            const parent = this.parentNode;
            parent.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
            this.classList.add('selected');
            const selectedTimeInput = document.getElementById('selected_time');
            if (selectedTimeInput) {
                selectedTimeInput.value = this.dataset.time;
            }
        });
    });

    // Select first time slot by default
    const firstSlot = document.querySelector('.time-slot');
    if (firstSlot && !document.querySelector('.time-slot.selected')) {
        firstSlot.classList.add('selected');
        const selectedTimeInput = document.getElementById('selected_time');
        if (selectedTimeInput) {
            selectedTimeInput.value = firstSlot.dataset.time;
        }
    }

    // Price preview
    const serviceSelect = document.getElementById('service_id');
    const priceDisplay = document.getElementById('price_display');
    if (serviceSelect && priceDisplay) {
        function updatePrice() {
            const selected = serviceSelect.options[serviceSelect.selectedIndex];
            const price = selected ? selected.dataset.price : 0;
            priceDisplay.textContent = price ? 'NPR ' + price : 'NPR 0';
        }
        serviceSelect.addEventListener('change', updatePrice);
        updatePrice();
    }

    // Date validation
    const dateInput = document.getElementById('appointment_date');
    if (dateInput) {
        function setMinDate() {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            dateInput.min = `${year}-${month}-${day}`;
            
            if (!dateInput.value) {
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                const ty = tomorrow.getFullYear();
                const tm = String(tomorrow.getMonth() + 1).padStart(2, '0');
                const td = String(tomorrow.getDate()).padStart(2, '0');
                dateInput.value = `${ty}-${tm}-${td}`;
            }
        }
        setMinDate();
        
        dateInput.addEventListener('change', function() {
            const selected = new Date(this.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            if (selected < today) {
                alert('Cannot select a date in the past');
                setMinDate();
            }
        });
    }

    // Payment method QR toggle
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const qrContainer = document.getElementById('qrContainer');
    const uploadSection = document.getElementById('uploadSection');
    
    if (paymentMethods.length) {
        function updatePaymentDisplay() {
            const checkedInput = document.querySelector('input[name="payment_method"]:checked');
            if (!checkedInput) return;
            const selected = checkedInput.value;
            if (qrContainer) {
                const khaltiBox = document.getElementById('khaltiBox');
                const esewaQr = document.getElementById('esewaQr');
                const bankQr = document.getElementById('bankQr');
                const cashNote = document.getElementById('cashNote');
                
                if (khaltiBox) khaltiBox.style.display = selected === 'khalti' ? 'block' : 'none';
                if (esewaQr) esewaQr.style.display = selected === 'esewa' ? 'block' : 'none';
                if (bankQr) bankQr.style.display = selected === 'bank' ? 'block' : 'none';
                if (cashNote) cashNote.style.display = selected === 'cash' ? 'block' : 'none';
                
                qrContainer.style.display = (selected === 'khalti' || selected === 'esewa' || selected === 'bank' || selected === 'cash') ? 'block' : 'none';
            }
            if (uploadSection) {
                uploadSection.style.display = (selected === 'esewa' || selected === 'bank') ? 'block' : 'none';
            }
        }
        
        paymentMethods.forEach(radio => {
            radio.addEventListener('change', updatePaymentDisplay);
        });
        updatePaymentDisplay();
    }
});

// Confirm cancellation
function confirmCancel(appointmentId) {
    if (confirm('Are you sure you want to cancel this appointment?')) {
        window.location.href = 'cancel-booking.php?id=' + appointmentId;
    }
}

// ==============================================
// Logout Confirmation Modal Handler
// ==============================================
(function() {
    function setupLogoutModal() {
        const logoutModal = document.getElementById('logoutModal');
        if (!logoutModal) return;

        const cancelBtn = document.getElementById('logoutCancelBtn');
        const closeBtn = document.getElementById('logoutModalCloseBtn');
        const confirmBtn = document.getElementById('logoutConfirmBtn');

        function openModal(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            logoutModal.style.display = 'flex';
            // Force browser reflow to trigger CSS opacity & transform transitions
            void logoutModal.offsetWidth;
            logoutModal.classList.add('active');
            document.body.style.overflow = 'hidden';
            if (cancelBtn) {
                cancelBtn.focus();
            }
        }

        function closeModal() {
            logoutModal.classList.remove('active');
            document.body.style.overflow = '';
            setTimeout(function() {
                if (!logoutModal.classList.contains('active')) {
                    logoutModal.style.display = 'none';
                }
            }, 250);
        }

        // Attach to all logout links (excluding the confirm button inside modal itself)
        document.querySelectorAll('a[href*="logout.php"]:not(#logoutConfirmBtn), .logout-trigger').forEach(function(link) {
            link.addEventListener('click', openModal);
        });

        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);

        // Click on backdrop (outside card) closes modal
        logoutModal.addEventListener('click', function(e) {
            if (e.target === logoutModal) {
                closeModal();
            }
        });

        // ESC key closes modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && logoutModal.classList.contains('active')) {
                closeModal();
            }
        });

        // Global function for direct invocation if needed
        window.openLogoutModal = openModal;
        window.closeLogoutModal = closeModal;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupLogoutModal);
    } else {
        setupLogoutModal();
    }
})();