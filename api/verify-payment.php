<?php
// api/verify-payment.php - Webhook & Server Verification for Gateway Payments
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

send_no_cache_headers();

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$bookingUid = trim($data['booking_uid'] ?? '');
$paymentId = trim($data['razorpay_payment_id'] ?? '');
$orderId = trim($data['razorpay_order_id'] ?? '');
$signature = trim($data['razorpay_signature'] ?? '');

if (empty($bookingUid) || empty($paymentId)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing booking reference or payment ID.']);
    exit;
}

$db = getDB();

try {
    $db->beginTransaction();

    // Retrieve booking record
    $stmt = $db->prepare("SELECT * FROM bookings WHERE booking_uid = :uid LIMIT 1 FOR UPDATE");
    $stmt->execute([':uid' => $bookingUid]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $db->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Booking record not found.']);
        exit;
    }

    if ($booking['payment_status'] === 'verified') {
        $db->commit();
        echo json_encode(['status' => 'success', 'booking_uid' => $bookingUid, 'message' => 'Booking is already verified.']);
        exit;
    }

    // Razorpay Signature Verification
    $keySecret = get_setting('razorpay_key_secret', 'migSecretLiveDemoKey987654321');
    $gatewayMode = get_setting('gateway_mode', 'test');
    
    // In live mode, verify hash if orderId is provided
    if ($gatewayMode === 'live' && !empty($orderId) && !empty($signature)) {
        $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $keySecret);
        if (!hash_equals($expectedSignature, $signature)) {
            $db->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Cryptographic signature mismatch.']);
            exit;
        }
    }

    // Deduct ticket category quantities safely
    $itemsStmt = $db->prepare("SELECT ticket_category_id, quantity FROM booking_items WHERE booking_id = :b_id");
    $itemsStmt->execute([':b_id' => $booking['id']]);
    $items = $itemsStmt->fetchAll();

    foreach ($items as $item) {
        $deductStmt = $db->prepare("
            UPDATE ticket_categories 
            SET available_quantity = GREATEST(0, available_quantity - :qty) 
            WHERE id = :cat_id
        ");
        $deductStmt->execute([
            ':qty'    => (int)$item['quantity'],
            ':cat_id' => (int)$item['ticket_category_id']
        ]);
    }

    // Mark booking as verified
    $updateStmt = $db->prepare("
        UPDATE bookings 
        SET payment_method = 'gateway', 
            transaction_ref = :ref, 
            payment_status = 'verified' 
        WHERE id = :id
    ");
    $updateStmt->execute([
        ':ref' => $paymentId,
        ':id'  => $booking['id']
    ]);

    $db->commit();

    // Clear session cart
    unset($_SESSION['booking_cart']);
    unset($_SESSION['booking_customer']);
    unset($_SESSION['current_booking_uid']);
    unset($_SESSION['current_booking_id']);
    unset($_SESSION['current_qr_token']);

    echo json_encode([
        'status'      => 'success',
        'booking_uid' => $bookingUid,
        'message'     => 'Payment verified successfully. Tickets issued!'
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error in api/verify-payment.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error verifying payment.']);
}
