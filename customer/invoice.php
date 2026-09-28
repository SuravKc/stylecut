<?php
// ==============================================
// customer/invoice.php - Printable PDF Invoice & Bill
// ==============================================
require_once '../config.php';
require_once '../functions.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$appointment_id = intval($_GET['id'] ?? 0);
if (!$appointment_id) {
    redirect('my-bookings.php');
}

// Fetch appointment with user, barber, service, and payment details
$stmt = $pdo->prepare("
    SELECT a.*, 
           cu.name as customer_name, cu.email as customer_email, cu.phone as customer_phone, cu.address as customer_address, cu.bonus_points as current_points,
           bu.name as barber_name, bu.phone as barber_phone, b.specialty as barber_specialty,
           s.name as service_name, s.description as service_desc, s.duration_minutes, s.price as original_service_price,
           p.id as payment_id, p.payment_method, p.payment_status, p.amount as payment_amount, p.paid_at, p.transaction_id, p.pidx
    FROM appointments a
    JOIN users cu ON a.customer_id = cu.id
    JOIN barbers b ON a.barber_id = b.id
    JOIN users bu ON b.user_id = bu.id
    JOIN services s ON a.service_id = s.id
    LEFT JOIN payments p ON a.id = p.appointment_id
    WHERE a.id = ?
");
$stmt->execute([$appointment_id]);
$appt = $stmt->fetch();

if (!$appt) {
    die("Invoice not found.");
}

// Security: ensure only the appointment owner, admin, or assigned barber can view this bill
$is_owner = (isCustomer() && $appt['customer_id'] == $_SESSION['user_id']);
$is_admin = isAdmin();
$is_barber_assigned = (isBarber() && isset($_SESSION['barber_id']) && $appt['barber_id'] == $_SESSION['barber_id']);

if (!$is_owner && !$is_admin && !$is_barber_assigned) {
    die("Access denied to view this invoice.");
}

$invoice_number = 'INV-SC-' . str_pad($appt['id'], 5, '0', STR_PAD_LEFT);
$paid_amount = $appt['payment_amount'] ?? $appt['price_at_booking'];
$auto_print = isset($_GET['print']) && $_GET['print'] == 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill &bull; <?php echo $invoice_number; ?> - Stylecut Nepal</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #e2e8f0;
            color: #1e293b;
            padding: 30px 15px;
            font-size: 14px;
            line-height: 1.5;
        }
        /* Action toolbar (screen only) */
        .invoice-toolbar {
            max-width: 800px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 14px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.07);
        }
        .invoice-toolbar .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            border-radius: 6px;
            cursor: pointer;
            border: none;
            transition: opacity 0.2s;
        }
        .btn-print {
            background: #5c2d91;
            color: #ffffff;
        }
        .btn-print:hover {
            opacity: 0.9;
        }
        .btn-back {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1 !important;
        }
        .btn-back:hover {
            background: #e2e8f0;
        }

        /* Invoice Container */
        .invoice-paper {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            padding: 45px 50px;
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        /* Header Section */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 25px;
            border-bottom: 2px solid #0f172a;
        }
        .company-info h1 {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }
        .company-info .tagline {
            font-size: 13px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .company-info p {
            font-size: 12px;
            color: #475569;
            line-height: 1.4;
        }
        .invoice-meta {
            text-align: right;
        }
        .invoice-title {
            font-size: 24px;
            font-weight: 800;
            color: #5c2d91;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .invoice-num {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 4px;
        }
        .invoice-date {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }

        /* Status Badge / Stamp */
        .status-stamp {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .status-stamp.paid {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }
        .status-stamp.pending {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        /* Parties Info */
        .parties-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 25px 0;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .party-box h4 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 6px;
        }
        .party-box .party-name {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .party-box p {
            font-size: 13px;
            color: #475569;
            margin-bottom: 2px;
        }

        /* Table */
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0 25px 0;
        }
        .invoice-table th {
            background: #f8fafc;
            color: #334155;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
            padding: 12px 14px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #cbd5e1;
        }
        .invoice-table td {
            padding: 14px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }

        /* Summary & Totals */
        .summary-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-top: 10px;
            gap: 30px;
        }
        .payment-record-box {
            flex: 1;
            background: #faf5ff;
            border: 1px solid #e9d5ff;
            border-radius: 6px;
            padding: 16px;
        }
        .payment-record-box h4 {
            font-size: 13px;
            font-weight: 700;
            color: #5c2d91;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .payment-record-box .row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 5px;
            color: #475569;
        }
        .payment-record-box .row strong {
            color: #1e293b;
        }

        .totals-box {
            width: 300px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13.5px;
            color: #475569;
        }
        .totals-row.grand-total {
            border-top: 2px solid #0f172a;
            margin-top: 8px;
            padding-top: 10px;
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
        }

        /* Footer */
        .invoice-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px dashed #cbd5e1;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }
        .invoice-footer strong {
            color: #1e293b;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .invoice-toolbar {
                display: none !important;
            }
            .invoice-paper {
                box-shadow: none;
                border: none;
                padding: 20px 25px;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

<!-- Screen Action Toolbar -->
<div class="invoice-toolbar">
    <div>
        <a href="<?php echo isAdmin() ? '../admin/appointments.php' : 'my-bookings.php'; ?>" class="btn btn-back">
            &larr; Back to Appointments
        </a>
    </div>
    <div style="display: flex; gap: 10px;">
        <button onclick="window.print()" class="btn btn-print">
            🖨️ Print / Save as PDF
        </button>
    </div>
</div>

<!-- Printable Invoice Paper -->
<div class="invoice-paper" id="invoice">
    <!-- Header -->
    <div class="invoice-header">
        <div class="company-info">
            <h1>Stylecut Nepal</h1>
            <div class="tagline">Premium Haircut &amp; Grooming Lounge</div>
            <p>
                Kathmandu, Nepal &bull; PAN/VAT: 609823412<br>
                Tel: +977-1-4432100 &bull; Email: billing@stylecut.com<br>
                Web: www.stylecut.com.np
            </p>
        </div>
        <div class="invoice-meta">
            <div class="invoice-title">Tax Invoice</div>
            <div class="invoice-num"><?php echo $invoice_number; ?></div>
            <div class="invoice-date">Date: <?php echo date('M d, Y h:i A', strtotime($appt['created_at'])); ?></div>
            <div>
                <?php if ($appt['payment_status'] === 'completed'): ?>
                    <span class="status-stamp paid">✓ PAYMENT RECEIVED</span>
                <?php else: ?>
                    <span class="status-stamp pending">PAYMENT PENDING</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Parties Grid -->
    <div class="parties-grid">
        <div class="party-box">
            <h4>Billed To (Customer):</h4>
            <div class="party-name"><?php echo htmlspecialchars($appt['customer_name']); ?></div>
            <p>📞 Phone: <?php echo htmlspecialchars($appt['customer_phone'] ?? 'N/A'); ?></p>
            <p>✉️ Email: <?php echo htmlspecialchars($appt['customer_email']); ?></p>
            <?php if (!empty($appt['customer_address'])): ?>
            <p>📍 Address: <?php echo htmlspecialchars($appt['customer_address']); ?></p>
            <?php endif; ?>
        </div>
        <div class="party-box">
            <h4>Service Professional:</h4>
            <div class="party-name"><?php echo htmlspecialchars($appt['barber_name']); ?></div>
            <p>✂️ Role: <?php echo htmlspecialchars($appt['barber_specialty'] ?? 'Stylist'); ?></p>
            <p>📅 Appointment Date: <strong><?php echo date('l, F d, Y', strtotime($appt['appointment_date'])); ?></strong></p>
            <p>⏰ Appointment Time: <strong><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?></strong></p>
        </div>
    </div>

    <!-- Line Items Table -->
    <table class="invoice-table">
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th>Service Description</th>
                <th class="text-center" style="width: 100px;">Duration</th>
                <th class="text-right" style="width: 120px;">Price (NPR)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>
                    <strong><?php echo htmlspecialchars($appt['service_name']); ?></strong>
                    <?php if (!empty($appt['service_desc'])): ?>
                        <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                            <?php echo htmlspecialchars($appt['service_desc']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($appt['notes'])): ?>
                        <div style="font-size: 11px; color: #64748b; margin-top: 3px;">
                            Note: <?php echo htmlspecialchars($appt['notes']); ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?php echo $appt['duration_minutes']; ?> mins</td>
                <td class="text-right"><?php echo number_format($appt['original_service_price'] ?? $appt['price_at_booking'], 2); ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Totals & Payment Summary -->
    <div class="summary-section">
        <!-- Payment Information -->
        <div class="payment-record-box">
            <h4>💳 Payment Verification</h4>
            <div class="row">
                <span>Payment Method:</span>
                <strong><?php echo strtoupper($appt['payment_method'] ?? 'CASH'); ?></strong>
            </div>
            <div class="row">
                <span>Payment Status:</span>
                <strong style="color: #15803d; text-transform: uppercase;">
                    <?php echo htmlspecialchars($appt['payment_status'] ?? 'pending'); ?>
                </strong>
            </div>
            <?php if (!empty($appt['transaction_id'])): ?>
            <div class="row">
                <span>Transaction ID:</span>
                <strong style="font-family: monospace;"><?php echo htmlspecialchars($appt['transaction_id']); ?></strong>
            </div>
            <?php endif; ?>
            <?php if (!empty($appt['paid_at'])): ?>
            <div class="row">
                <span>Paid Timestamp:</span>
                <span><?php echo date('M d, Y h:i A', strtotime($appt['paid_at'])); ?></span>
            </div>
            <?php endif; ?>
            <div class="row" style="margin-top: 8px; border-top: 1px dashed #d8b4fe; padding-top: 6px;">
                <span>Loyalty Points Earned:</span>
                <strong style="color: #5c2d91;">+10 Points</strong>
            </div>
        </div>

        <!-- Totals Column -->
        <div class="totals-box">
            <div class="totals-row">
                <span>Subtotal:</span>
                <span>NPR <?php echo number_format($appt['original_service_price'] ?? $appt['price_at_booking'], 2); ?></span>
            </div>
            <?php if ($appt['bonus_used'] > 0): ?>
            <div class="totals-row" style="color: #15803d;">
                <span>Loyalty Bonus Discount:</span>
                <span>- NPR <?php echo number_format($appt['bonus_used'], 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="totals-row grand-total">
                <span>Total Paid:</span>
                <span>NPR <?php echo number_format($paid_amount, 2); ?></span>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="invoice-footer">
        <p><strong>Thank you for choosing Stylecut Nepal!</strong></p>
        <p>This is a computer-generated tax invoice and digital receipt. No signature is required.</p>
        <p style="margin-top: 4px; font-size: 11px; color: #94a3b8;">
            Appointment Status: <strong><?php echo strtoupper($appt['status']); ?></strong> &bull; Generated on <?php echo date('F d, Y h:i A'); ?>
        </p>
    </div>
</div>

<?php if ($auto_print): ?>
<script>
    window.addEventListener('load', function() {
        window.print();
    });
</script>
<?php endif; ?>

</body>
</html>
