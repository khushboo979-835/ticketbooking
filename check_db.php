<?php
// check_db.php - One-time diagnostic script
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

header('Content-Type: application/json');

try {
    $db = getDB();
    $stmt = $db->query("SELECT id, booking_uid, customer_name, customer_phone, total_amount, payment_status, transaction_ref, created_at FROM bookings ORDER BY id DESC LIMIT 10");
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $searchPhone = '6204954936';
    $searchStmt = $db->prepare("SELECT * FROM bookings WHERE customer_phone LIKE :p1 OR booking_uid LIKE :p2 OR transaction_ref LIKE :p3");
    $searchStmt->execute([':p1' => "%$searchPhone%", ':p2' => "%$searchPhone%", ':p3' => "%$searchPhone%"]);
    $found = $searchStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'ok',
        'customer_search' => $found,
        'recent_bookings' => $recent
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
