<?php
// api/export-csv.php - Export Bookings & Attendee Ledger to CSV (Admin Only)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

require_admin_login();

$status = $_GET['status'] ?? 'all';
$db = getDB();

$sql = "
    SELECT 
        b.booking_uid,
        COALESCE(MAX(e.title), 'Jashn-E-Garba 2026') AS event_title,
        b.customer_name,
        b.customer_phone,
        b.customer_email,
        GROUP_CONCAT(CONCAT(tc.name, ' (x', bi.quantity, ')') SEPARATOR ', ') AS ticket_summary,
        SUM(bi.quantity * tc.people_allowed) AS total_persons,
        b.total_amount,
        b.payment_method,
        b.payment_status,
        b.transaction_ref,
        b.is_checked_in,
        b.checked_in_at,
        b.created_at
    FROM bookings b
    LEFT JOIN events e ON b.event_id = e.id
    LEFT JOIN booking_items bi ON b.id = bi.booking_id
    LEFT JOIN ticket_categories tc ON bi.ticket_category_id = tc.id
    WHERE 1=1
";

$params = [];
if ($status === 'verified') {
    $sql .= " AND b.payment_status = 'verified'";
} elseif ($status === 'pending') {
    $sql .= " AND b.payment_status = 'pending'";
} elseif ($status === 'checked_in') {
    $sql .= " AND b.is_checked_in = 1";
}
$sql .= " GROUP BY b.id ORDER BY b.id DESC";

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
    'Ticket Categories & Qty',
    'Headcount (Persons)',
    'Food Included',
    'Total Amount (INR)',
    'Payment Method',
    'Payment Status',
    'UTR / Transaction Ref',
    'Gate Entered?',
    'Check-in Timestamp',
    'Booking Date'
]);

foreach ($rows as $row) {
    $hasFood = stripos($row['ticket_summary'] ?? '', 'With Food') !== false ? 'YES' : 'NO';
    fputcsv($output, [
        $row['booking_uid'],
        $row['event_title'],
        $row['customer_name'],
        $row['customer_phone'],
        $row['customer_email'],
        $row['ticket_summary'] ?: 'Standard Entry',
        (int)($row['total_persons'] ?: 1),
        $hasFood,
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
