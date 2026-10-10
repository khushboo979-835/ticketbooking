<?php
// api/check-ticket.php - QR Code Validation Endpoint for Gate Scanner
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

send_no_cache_headers();

// Check rate limit for scanner check
if (!check_rate_limit('qr_scan_check', 100, 60)) {
    echo json_encode(['status' => 'error', 'message' => 'Rate limit exceeded. Please wait a moment.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$token = trim($data['token'] ?? '');
$uid = trim($data['uid'] ?? '');

if (empty($token) && empty($uid)) {
    echo json_encode(['status' => 'invalid', 'message' => 'Empty QR token or Booking UID received.']);
    exit;
}

$db = getDB();

try {
    // Look up by token or booking UID
    $sql = "
        SELECT b.*, e.title AS event_title 
        FROM bookings b
        JOIN events e ON b.event_id = e.id
        WHERE ";
    
    $params = [];
    if (!empty($token)) {
        $sql .= "b.qr_token = :token";
        $params[':token'] = $token;
    } else {
        $sql .= "b.booking_uid = :uid";
        $params[':uid'] = $uid;
    }
    $sql .= " LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $booking = $stmt->fetch();

    if (!$booking) {
        echo json_encode([
            'status'  => 'invalid',
            'message' => 'Ticket token not recognized. Invalid or forged QR code.'
        ]);
        exit;
    }

    // Check payment verification status
    if ($booking['payment_status'] !== 'verified') {
        echo json_encode([
            'status'         => 'unpaid',
            'payment_status' => $booking['payment_status'],
            'customer_name'  => $booking['customer_name'],
            'booking_uid'    => $booking['booking_uid'],
            'message'        => 'Payment is ' . strtoupper($booking['payment_status']) . '. Ticket is not yet approved for entry.'
        ]);
        exit;
    }

    // Fetch booking line items and total people allowed
    $itemStmt = $db->prepare("
        SELECT bi.quantity, tc.name, tc.people_allowed
        FROM booking_items bi
        JOIN ticket_categories tc ON bi.ticket_category_id = tc.id
        WHERE bi.booking_id = :b_id
    ");
    $itemStmt->execute([':b_id' => $booking['id']]);
    $items = $itemStmt->fetchAll();

    $totalPeople = 0;
    $summaryParts = [];
    foreach ($items as $it) {
        $qty = (int)$it['quantity'];
        $ppl = (int)$it['people_allowed'];
        $totalPeople += ($qty * $ppl);
        $summaryParts[] = "{$it['name']} &times; {$qty}";
    }
    $summaryText = implode(', ', $summaryParts);

    // Check if ticket was ALREADY checked in
    if ((int)$booking['is_checked_in'] === 1) {
        echo json_encode([
            'status'          => 'already_used',
            'booking_uid'     => $booking['booking_uid'],
            'customer_name'   => $booking['customer_name'],
            'event_title'     => $booking['event_title'],
            'tickets_summary' => $summaryText,
            'total_people'    => $totalPeople,
            'checked_in_at'   => $booking['checked_in_at'] ? date('d M Y, h:i:s A', strtotime($booking['checked_in_at'])) : 'Earlier today',
            'message'         => 'This ticket was already checked in.'
        ]);
        exit;
    }

    // Mark ticket as CHECKED IN
    $updateStmt = $db->prepare("
        UPDATE bookings 
        SET is_checked_in = 1, checked_in_at = NOW() 
        WHERE id = :id AND is_checked_in = 0
    ");
    $updateStmt->execute([':id' => $booking['id']]);

    echo json_encode([
        'status'          => 'valid',
        'booking_uid'     => $booking['booking_uid'],
        'customer_name'   => $booking['customer_name'],
        'event_title'     => $booking['event_title'],
        'tickets_summary' => $summaryText,
        'total_people'    => $totalPeople,
        'checked_in_at'   => date('d M Y, h:i:s A'),
        'message'         => 'Valid Ticket! Gate entry permitted.'
    ]);
} catch (Exception $e) {
    error_log("Error in api/check-ticket.php: " . $e->getMessage());
    echo json_encode([
        'status'  => 'error',
        'message' => 'Internal server error validating ticket.'
    ]);
}
