<?php
// ==============================================
// includes/khalti.php - Khalti Sandbox Payment Gateway Integration
// ==============================================

require_once __DIR__ . '/../config.php';

/**
 * Ensures the database supports 'khalti' payment method and transaction ID tracking
 */
function ensureKhaltiSchema($pdo) {
    static $executed = false;
    if ($executed || !$pdo) {
        return;
    }

    try {
        // Change payment_method column from ENUM to VARCHAR(50) if needed
        $pdo->exec("ALTER TABLE payments MODIFY COLUMN payment_method VARCHAR(50) NOT NULL DEFAULT 'cash'");
    } catch (Exception $e) {
        // Ignored if already compatible
    }

    try {
        // Ensure transaction_id column exists
        $pdo->exec("ALTER TABLE payments ADD COLUMN transaction_id VARCHAR(100) NULL AFTER paid_at");
    } catch (Exception $e) {
        // Ignored if exists
    }

    try {
        // Ensure pidx column exists
        $pdo->exec("ALTER TABLE payments ADD COLUMN pidx VARCHAR(100) NULL AFTER transaction_id");
    } catch (Exception $e) {
        // Ignored if exists
    }

    $executed = true;
}

/**
 * Initiates an ePayment transaction with Khalti Sandbox API v2
 * 
 * @param string $order_id Unique order reference
 * @param string $order_name Name or description of the service
 * @param float $amount_npr Amount in Nepali Rupees (will be converted to paisa)
 * @param array $customer Customer information (name, email, phone)
 * @param string $return_url The callback URL Khalti redirects to after payment
 * @param string $website_url Application base URL
 * @return array ['success' => bool, 'pidx' => string, 'payment_url' => string, 'error' => string]
 */
function khalti_initiate_payment($order_id, $order_name, $amount_npr, $customer = [], $return_url = null, $website_url = null) {
    if (!$return_url) {
        $return_url = APP_URL . 'customer/khalti-callback.php';
    }
    if (!$website_url) {
        $website_url = APP_URL;
    }

    // Convert NPR to Paisa (1 NPR = 100 Paisa), Khalti requires minimum 1000 paisa (Rs 10)
    $amount_paisa = (int) round($amount_npr * 100);
    if ($amount_paisa < 1000) {
        $amount_paisa = 1000;
    }

    // Prepare customer info
    $customer_name = !empty($customer['name']) ? trim($customer['name']) : 'Stylecut Customer';
    $customer_email = !empty($customer['email']) ? trim($customer['email']) : 'customer@stylecut.com';
    $customer_phone = !empty($customer['phone']) ? trim($customer['phone']) : '9800000000';

    $payload = [
        'return_url' => $return_url,
        'website_url' => $website_url,
        'amount' => $amount_paisa,
        'purchase_order_id' => (string) $order_id,
        'purchase_order_name' => (string) $order_name,
        'customer_info' => [
            'name' => $customer_name,
            'email' => $customer_email,
            'phone' => $customer_phone
        ]
    ];

    $apiUrl = rtrim(KHALTI_BASE_URL, '/') . '/epayment/initiate/';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Key ' . KHALTI_SECRET_KEY,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    // Allow local XAMPP/localhost to communicate without local CA bundle issues
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return [
            'success' => false,
            'is_network_error' => true,
            'error' => "Could not reach Khalti server: $curlError"
        ];
    }

    $data = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && !empty($data['payment_url']) && !empty($data['pidx'])) {
        return [
            'success' => true,
            'pidx' => $data['pidx'],
            'payment_url' => $data['payment_url'],
            'expires_at' => $data['expires_at'] ?? null
        ];
    }

    // Extract helpful error message from Khalti response
    $errorMsg = 'Failed to initiate payment with Khalti.';
    if (is_array($data)) {
        if (!empty($data['detail'])) {
            $errorMsg = $data['detail'];
        } elseif (!empty($data['error_key'])) {
            $errorMsg = $data['error_key'] . ': ' . json_encode($data);
        } else {
            $first_val = reset($data);
            if (is_array($first_val)) {
                $errorMsg = implode(', ', $first_val);
            } elseif (is_string($first_val)) {
                $errorMsg = $first_val;
            }
        }
    }

    return [
        'success' => false,
        'is_network_error' => ($httpCode === 0 || $httpCode >= 500),
        'http_code' => $httpCode,
        'error' => $errorMsg,
        'raw' => $response
    ];
}

/**
 * Looks up and verifies an ePayment transaction with Khalti Sandbox API v2
 * 
 * @param string $pidx The payment index returned from initiate
 * @return array ['success' => bool, 'data' => array, 'error' => string]
 */
function khalti_lookup_payment($pidx) {
    if (empty($pidx)) {
        return ['success' => false, 'error' => 'Missing transaction PIDX.'];
    }

    // Handle offline simulator transaction
    if (strpos($pidx, 'SIM_') === 0) {
        return [
            'success' => true,
            'data' => [
                'pidx' => $pidx,
                'status' => 'Completed',
                'transaction_id' => 'KHALTI_SIM_' . substr(md5($pidx), 0, 10),
                'total_amount' => $_SESSION['khalti_payment']['amount'] ? (int)round($_SESSION['khalti_payment']['amount'] * 100) : 0,
                'fee' => 0,
                'refunded' => false
            ]
        ];
    }

    $apiUrl = rtrim(KHALTI_BASE_URL, '/') . '/epayment/lookup/';
    $payload = json_encode(['pidx' => $pidx]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Key ' . KHALTI_SECRET_KEY,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return [
            'success' => false,
            'error' => "Network error during Khalti lookup: $curlError"
        ];
    }

    $data = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && is_array($data) && !empty($data['status'])) {
        return [
            'success' => true,
            'data' => $data
        ];
    }

    $errMsg = $data['detail'] ?? ($data['error_key'] ?? 'Could not verify transaction with Khalti.');
    return [
        'success' => false,
        'http_code' => $httpCode,
        'error' => $errMsg,
        'raw' => $response
    ];
}
