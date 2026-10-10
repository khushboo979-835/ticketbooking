<?php
// api/export-csv.php - Export Bookings to CSV (Admin Only)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

require_admin_login();

$status = $_GET['status'] ?? 'all';
$db = getDB();

$sql = "
    SELECT 
        b.booking_uid,
        e.title AS event_title,
        b.customer_name,
        b.customer_phone,
        b.customer_email,
        b.total_amount,
        b.payment_method,
        b.payment_status,
        b.transaction_ref,
        b.is_checked_in,
        b.checked_in_at,
        b.created_at
    FROM bookings b
    JOIN events e ON b.event_id = e.id
";

$params = [];
if ($status === 'verified') {
    $sql .= " WHERE b.payment_status = 'verified'";
} elseif ($status === 'pending') {
    $sql .= " WHERE b.payment_status = 'pending'";
} elseif ($status === 'checked_in') {
    $sql .= " WHERE b.is_checked_in = 1";
}
$sql .= " ORDER BY b.id DESC";

$rows = [];
try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];
} catch (PDOException $e) {
    error_log('[export-csv.php error]: ' . $e->getMessage());
}

$filename = 'millennium_bookings_' . date('Y-m-d_His') . '.csv';

// Output headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Column Header Row
fputcsv($output, [
    'Booking UID',
    'Event',
    'Attendee Name',
    'Phone',
    'Email',
    'Total Amount (INR)',
    'Payment Method',
    'Payment Status',
    'UTR / Transaction Ref',
    'Checked In?',
    'Check-in Timestamp',
    'Booking Date'
]);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['booking_uid'],
        $row['event_title'],
        $row['customer_name'],
        $row['customer_phone'],
        $row['customer_email'],
        number_format((float)$row['total_amount'], 2, '.', ''),
        strtoupper($row['payment_method']),
        strtoupper($row['payment_status']),
        $row['transaction_ref'] ?: 'N/A',
        $row['is_checked_in'] ? 'YES' : 'NO',
        $row['checked_in_at'] ?: 'N/A',
        $row['created_at']
    ]);
}

fclose($output);
exit;
