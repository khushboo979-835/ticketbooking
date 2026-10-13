<?php
/**
 * admin/bookings.php - BookMyShow-Grade Attendee Ledger & 1-Click UPI Verification Console
 * Millennium India Group - Jashn-E-Garba 2026
 * Comprehensive tracking of who booked, payment verification, and entry authorizations.
 */
ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

require_admin_login();
send_no_cache_headers();

$adminCurrent = 'bookings';
$db = getDB();

// Auto-purge legacy dummy/test records
try {
    $db->exec("
        DELETE bi FROM booking_items bi 
        JOIN bookings b ON bi.booking_id = b.id 
        WHERE b.customer_name LIKE '%Test%' 
           OR b.customer_name = 'Live Test Customer'
           OR b.customer_name = 'Live Test'
           OR b.customer_name = 'Test User'
           OR b.customer_phone = '9876543210' 
           OR b.transaction_ref = '123456789012'
    ");
    $db->exec("
        DELETE FROM bookings 
        WHERE customer_name LIKE '%Test%' 
           OR customer_name = 'Live Test Customer'
           OR customer_name = 'Live Test'
           OR customer_name = 'Test User'
           OR customer_phone = '9876543210' 
           OR transaction_ref = '123456789012'
    ");
} catch (Throwable $e) {}

// Handle Actions (Verify, Delete, Clean, Toggle Check-In, Walk-In)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session token expired. Please refresh and try again.');
        header("Location: " . BASE_URL . "/admin/bookings.php");
        exit;
    }

    $action = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    // 1-Click Verify UPI Payment
    if ($action === 'verify_payment' && $bookingId > 0) {
        try {
            $db->beginTransaction();

            $bStmt = $db->prepare("SELECT * FROM bookings WHERE id = :id FOR UPDATE");
            $bStmt->execute([':id' => $bookingId]);
            $booking = $bStmt->fetch();

            if ($booking && $booking['payment_status'] !== 'verified') {
                $itStmt = $db->prepare("SELECT ticket_category_id, quantity FROM booking_items WHERE booking_id = :b_id");
                $itStmt->execute([':b_id' => $bookingId]);
                $items = $itStmt->fetchAll() ?: [];

                foreach ($items as $item) {
                    $deductStmt = $db->prepare("UPDATE ticket_categories SET available_quantity = GREATEST(0, available_quantity - :q) WHERE id = :c_id");
                    $deductStmt->execute([':q' => (int)$item['quantity'], ':c_id' => (int)$item['ticket_category_id']]);
                }

                $upStmt = $db->prepare("UPDATE bookings SET payment_status = 'verified' WHERE id = :id");
                $upStmt->execute([':id' => $bookingId]);

                // Ensure allocated seats are marked as sold
                if (!empty($booking['seat_numbers'])) {
                    $seatArr = array_map('trim', explode(',', $booking['seat_numbers']));
                    $upSeat = $db->prepare("UPDATE event_seats SET status = 'sold', booking_uid = :uid WHERE seat_code = :code");
                    foreach ($seatArr as $sCode) {
                        $upSeat->execute([':uid' => $booking['booking_uid'], ':code' => $sCode]);
                    }
                }

                $db->commit();
                set_flash('success', "Booking #{$booking['booking_uid']} verified! Digital pass activated for {$booking['customer_name']}.");
            } else {
                $db->rollBack();
                set_flash('info', "Booking was already verified or not found.");
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[admin/bookings.php verification error]: ' . $e->getMessage());
            set_flash('error', "Database error approving booking: " . $e->getMessage());
        }
    } 
    // Delete Booking Permanently
    elseif ($action === 'delete_booking' && $bookingId > 0) {
        try {
            $db->beginTransaction();

            $bStmt = $db->prepare("SELECT * FROM bookings WHERE id = :id FOR UPDATE");
            $bStmt->execute([':id' => $bookingId]);
            $bRow = $bStmt->fetch();

            if ($bRow) {
                if ($bRow['payment_status'] === 'verified') {
                    $itStmt = $db->prepare("SELECT ticket_category_id, quantity FROM booking_items WHERE booking_id = :b_id");
                    $itStmt->execute([':b_id' => $bookingId]);
                    $items = $itStmt->fetchAll() ?: [];
                    foreach ($items as $item) {
                        $db->prepare("UPDATE ticket_categories SET available_quantity = LEAST(total_quantity, available_quantity + :q) WHERE id = :c_id")
                           ->execute([':q' => (int)$item['quantity'], ':c_id' => (int)$item['ticket_category_id']]);
                    }
                }

                // Free up any assigned seats
                if (!empty($bRow['seat_numbers'])) {
                    $seatArr = array_map('trim', explode(',', $bRow['seat_numbers']));
                    $freeSeat = $db->prepare("UPDATE event_seats SET status = 'available', booking_uid = NULL WHERE seat_code = :code");
                    foreach ($seatArr as $sCode) {
                        $freeSeat->execute([':code' => $sCode]);
                    }
                }

                $db->prepare("DELETE FROM booking_items WHERE booking_id = :id")->execute([':id' => $bookingId]);
                $db->prepare("DELETE FROM bookings WHERE id = :id")->execute([':id' => $bookingId]);

                $db->commit();
                set_flash('success', "Booking #{$bRow['booking_uid']} ({$bRow['customer_name']}) removed from database.");
            } else {
                $db->rollBack();
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[admin/bookings.php delete error]: ' . $e->getMessage());
            set_flash('error', "Error deleting booking: " . $e->getMessage());
        }
    }
    // Action: Direct Ticket Creation by UTR / Reference ID
    elseif ($action === 'create_ticket_utr') {
        $utr = preg_replace('/[^A-Za-z0-9]/', '', trim($_POST['transaction_ref'] ?? ''));
        $name = trim($_POST['customer_name'] ?? '');
        $phone = preg_replace('/[^\d]/', '', trim($_POST['customer_phone'] ?? ''));
        $catId = (int)($_POST['category_id'] ?? 1);
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $assignedSeats = trim($_POST['seat_codes'] ?? '');

        if (empty($name)) $name = 'Garba Attendee';
        if (empty($phone) || strlen($phone) < 10) $phone = '7004665804';
        if (empty($utr)) $utr = 'UTR-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        try {
            $db->beginTransaction();

            $cStmt = $db->prepare("SELECT * FROM ticket_categories WHERE id = :id");
            $cStmt->execute([':id' => $catId]);
            $category = $cStmt->fetch();

            if ($category) {
                $totalAmt = $qty * (float)$category['price'];
                $bookingUid = generate_booking_uid();
                $qrToken = generate_qr_token();

                // If seats not given, pick available
                if (empty($assignedSeats)) {
                    $seatStmt = $db->prepare("SELECT seat_code FROM event_seats WHERE category_id = :cid AND status = 'available' LIMIT :lim");
                    $seatStmt->bindValue(':cid', $catId, PDO::PARAM_INT);
                    $seatStmt->bindValue(':lim', $qty, PDO::PARAM_INT);
                    $seatStmt->execute();
                    $picked = $seatStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
                    $assignedSeats = implode(', ', $picked);
                }

                $insB = $db->prepare("
                    INSERT INTO bookings (
                        booking_uid, event_id, customer_name, customer_phone, customer_email,
                        total_amount, payment_method, payment_status, transaction_ref, qr_token,
                        is_checked_in, seat_numbers, created_at
                    ) VALUES (
                        :uid, 1, :name, :phone, :email,
                        :amt, 'direct_upi', 'verified', :utr, :qr,
                        0, :seats, NOW()
                    )
                ");
                $insB->execute([
                    ':uid'   => $bookingUid,
                    ':name'  => $name,
                    ':phone' => $phone,
                    ':email' => $phone . '@millenniumindiagroup.com',
                    ':amt'   => $totalAmt,
                    ':utr'   => $utr,
                    ':qr'    => $qrToken,
                    ':seats' => $assignedSeats
                ]);
                $newBookingId = (int)$db->lastInsertId();

                $insItem = $db->prepare("
                    INSERT INTO booking_items (booking_id, ticket_category_id, quantity, unit_price, subtotal)
                    VALUES (:b_id, :c_id, :qty, :price, :sub)
                ");
                $insItem->execute([
                    ':b_id'  => $newBookingId,
                    ':c_id'  => $catId,
                    ':qty'   => $qty,
                    ':price' => $category['price'],
                    ':sub'   => $totalAmt
                ]);

                // Mark assigned seats as SOLD
                if (!empty($assignedSeats)) {
                    $seatArr = array_map('trim', explode(',', $assignedSeats));
                    $updSeat = $db->prepare("UPDATE event_seats SET status = 'sold', booking_uid = :uid WHERE seat_code = :code");
                    foreach ($seatArr as $sCode) {
                        $updSeat->execute([':uid' => $bookingUid, ':code' => $sCode]);
                    }
                }

                // Deduct category available quantity
                $db->prepare("UPDATE ticket_categories SET available_quantity = GREATEST(0, available_quantity - :q) WHERE id = :id")
                   ->execute([':q' => $qty, ':id' => $catId]);

                $db->commit();
                set_flash('success', "Ticket pass issued for {$name}! Booking UID: #{$bookingUid} (UTR: {$utr}) &bull; Seats: {$assignedSeats}. <a href='" . BASE_URL . "/ticket.php?uid={$bookingUid}' target='_blank' style='color:#fff; text-decoration:underline; font-weight:bold;'>View Pass &rarr;</a>");
            } else {
                $db->rollBack();
                set_flash('error', "Category not found.");
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[admin/bookings.php create_ticket_utr error]: ' . $e->getMessage());
            set_flash('error', "Error issuing ticket: " . $e->getMessage());
        }
    }
    // Clean Dummy / Test Data
    elseif ($action === 'clean_dummy_data') {
        try {
            $dummyStmt = $db->query("
                SELECT id FROM bookings 
                WHERE customer_name LIKE '%Test%' 
                   OR customer_name = 'Live Test Customer' 
                   OR customer_name = 'Live Test' 
                   OR customer_name = 'Test User' 
                   OR customer_phone = '9876543210' 
                   OR transaction_ref = '123456789012'
            ");
            $dummyRows = $dummyStmt->fetchAll() ?: [];
            $delCount = count($dummyRows);
            if ($delCount > 0) {
                $ids = array_column($dummyRows, 'id');
                $inQuery = implode(',', array_map('intval', $ids));
                $db->exec("DELETE FROM booking_items WHERE booking_id IN ($inQuery)");
                $db->exec("DELETE FROM bookings WHERE id IN ($inQuery)");
                set_flash('success', "Purged $delCount dummy bookings successfully.");
            } else {
                set_flash('info', "No dummy or test records found.");
            }
        } catch (PDOException $e) {
            set_flash('error', "Error purging dummy data: " . $e->getMessage());
        }
    }
    // Toggle Gate Check-in
    elseif ($action === 'toggle_checkin' && $bookingId > 0) {
        try {
            $stmt = $db->prepare("
                UPDATE bookings 
                SET is_checked_in = CASE WHEN is_checked_in = 1 THEN 0 ELSE 1 END,
                    checked_in_at = CASE WHEN is_checked_in = 1 THEN NULL ELSE NOW() END 
                WHERE id = :id
            ");
            $stmt->execute([':id' => $bookingId]);
            set_flash('info', "Check-in status updated for booking #$bookingId.");
        } catch (PDOException $e) {
            error_log('[admin/bookings.php toggle_checkin error]: ' . $e->getMessage());
        }
    }
    // Issue Walk-In Pass
    elseif ($action === 'walkin_booking') {
        $custName = trim($_POST['customer_name'] ?? '');
        $custPhone = preg_replace('/[^\d]/', '', trim($_POST['customer_phone'] ?? ''));
        $catId = (int)($_POST['category_id'] ?? 1);
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $payMethod = in_array($_POST['payment_method'] ?? '', ['cash', 'upi']) ? $_POST['payment_method'] : 'cash';
        $refNote = trim($_POST['transaction_ref'] ?? ($payMethod === 'cash' ? 'CASH-COUNTER' : 'UPI-DIRECT'));

        if (empty($custName)) $custName = 'Walk-In Attendee';
        if (empty($custPhone) || strlen($custPhone) < 10) $custPhone = '7004665804';

        try {
            $db->beginTransaction();

            $cStmt = $db->prepare("SELECT * FROM ticket_categories WHERE id = :id FOR UPDATE");
            $cStmt->execute([':id' => $catId]);
            $category = $cStmt->fetch();

            if ($category) {
                $totalAmt = $qty * (float)$category['price'];
                $bookingUid = 'MIG' . date('Ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                $qrToken = 'MIG-QR-' . bin2hex(random_bytes(16));

                $insB = $db->prepare("
                    INSERT INTO bookings (booking_uid, event_id, customer_name, customer_phone, customer_email, total_amount, payment_method, payment_status, transaction_ref, qr_token, is_checked_in, created_at)
                    VALUES (:uid, 1, :name, :phone, :email, :amt, :method, 'verified', :ref, :qr, 0, NOW())
                ");
                $insB->execute([
                    ':uid'    => $bookingUid,
                    ':name'   => $custName,
                    ':phone'  => $custPhone,
                    ':email'  => $custPhone . '@counter.mig',
                    ':amt'    => $totalAmt,
                    ':method' => $payMethod,
                    ':ref'    => $refNote,
                    ':qr'     => $qrToken
                ]);
                $newBookingId = (int)$db->lastInsertId();

                $insItem = $db->prepare("
                    INSERT INTO booking_items (booking_id, ticket_category_id, quantity, unit_price, subtotal)
                    VALUES (:b_id, :c_id, :qty, :price, :sub)
                ");
                $insItem->execute([
                    ':b_id'  => $newBookingId,
                    ':c_id'  => $catId,
                    ':qty'   => $qty,
                    ':price' => $category['price'],
                    ':sub'   => $totalAmt
                ]);

                // Deduct inventory
                $deduct = $db->prepare("UPDATE ticket_categories SET available_quantity = GREATEST(0, available_quantity - :q) WHERE id = :id");
                $deduct->execute([':q' => $qty, ':id' => $catId]);

                $db->commit();
                set_flash('success', "Walk-In Pass #{$bookingUid} generated for {$custName}! <a href='" . BASE_URL . "/ticket.php?uid={$bookingUid}' target='_blank' style='color:#fff; text-decoration:underline; font-weight:bold;'>View Digital Pass &rarr;</a>");
            } else {
                $db->rollBack();
                set_flash('error', "Selected category not found.");
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[admin/bookings.php walkin error]: ' . $e->getMessage());
            set_flash('error', "Error generating walk-in pass: " . $e->getMessage());
        }
    }

    header("Location: " . BASE_URL . "/admin/bookings.php");
    exit;
}

// Filters & Search
$statusFilter = $_GET['status'] ?? 'all';
$categoryFilter = (int)($_GET['category'] ?? 0);
$search = trim($_GET['search'] ?? '');

$allCategories = [];
try {
    $allCategories = $db->query("SELECT id, name, price FROM ticket_categories WHERE event_id = 1 ORDER BY price ASC")->fetchAll() ?: [];
} catch (PDOException $e) {}

// Counts for tabs
$totalCount = 0;
$verifiedCount = 0;
$pendingCount = 0;
try {
    $totalCount = (int)$db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $verifiedCount = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE payment_status = 'verified'")->fetchColumn();
    $pendingCount = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE payment_status = 'pending'")->fetchColumn();
} catch (PDOException $e) {}

$bookings = [];
$filterTotalAmount = 0.0;
$filterTotalPersons = 0;

try {
    $sql = "
        SELECT b.*, MAX(e.title) AS event_title,
               GROUP_CONCAT(CONCAT(tc.name, ' (x', bi.quantity, ')') SEPARATOR ', ') AS ticket_summary,
               SUM(bi.quantity * tc.people_allowed) AS total_persons,
               SUM(bi.quantity) AS total_tickets
        FROM bookings b
        LEFT JOIN events e ON b.event_id = e.id
        LEFT JOIN booking_items bi ON b.id = bi.booking_id
        LEFT JOIN ticket_categories tc ON bi.ticket_category_id = tc.id
        WHERE 1=1
    ";

    $params = [];

    if ($statusFilter === 'pending') {
        $sql .= " AND b.payment_status = 'pending'";
    } elseif ($statusFilter === 'verified') {
        $sql .= " AND b.payment_status = 'verified'";
    } elseif ($statusFilter === 'checked_in') {
        $sql .= " AND b.is_checked_in = 1";
    }

    if ($categoryFilter > 0) {
        $sql .= " AND bi.ticket_category_id = :cat_id";
        $params[':cat_id'] = $categoryFilter;
    }

    if (!empty($search)) {
        $sql .= " AND (b.booking_uid LIKE :s1 OR b.customer_name LIKE :s2 OR b.customer_phone LIKE :s3 OR b.transaction_ref LIKE :s4)";
        $params[':s1'] = "%$search%";
        $params[':s2'] = "%$search%";
        $params[':s3'] = "%$search%";
        $params[':s4'] = "%$search%";
    }

    $sql .= " GROUP BY b.id ORDER BY b.id DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll() ?: [];

    foreach ($bookings as $bk) {
        if ($bk['payment_status'] === 'verified') {
            $filterTotalAmount += (float)$bk['total_amount'];
            $filterTotalPersons += (int)$bk['total_persons'];
        }
    }
} catch (PDOException $e) {
    error_log('[admin/bookings.php list error]: ' . $e->getMessage());
    $bookings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendee Ledger &amp; Verification | Millennium Admin</title>
    <link rel="stylesheet" href="<?= asset_v('assets/css/admin.css') ?>">
</head>
<body>
<div class="admin-wrapper">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-header">
            <div>
                <h1 class="admin-title">&#128179; Bookings Ledger &amp; Attendee Verification</h1>
                <div class="admin-subtitle">Live transactions, UTR receipt checks, and entry authorizations</div>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="btn-bms-gold" style="background: linear-gradient(135deg, #10b981, #059669); color:#fff;" onclick="openUtrModal()">
                    <span>&#9889;</span> <span>Issue Ticket by UTR</span>
                </button>
                <a href="<?= BASE_URL ?>/admin/seats.php" class="btn-bms-dark" style="border-color: #3b82f6; color: #60a5fa;" title="Manage Khali & Booked Seats">
                    <span>&#128186;</span> <span>Seat Matrix</span>
                </a>
                <button type="button" class="btn-bms-dark" onclick="openWalkinModal()">
                    <span>&#127915;</span> <span>Walk-In Pass</span>
                </button>
                <form action="<?= BASE_URL ?>/admin/bookings.php" method="POST" style="margin:0;" onsubmit="return confirm('Purge all dummy and test records permanently?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clean_dummy_data">
                    <button type="submit" class="btn-bms-dark" style="background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.35); color: #f87171;" title="Remove Test / Dummy Data">
                        <span>&#129529;</span> <span>Clean Dummy Data</span>
                    </button>
                </form>
                <a href="<?= BASE_URL ?>/api/export-csv.php?status=<?= urlencode($statusFilter) ?>" class="btn-bms-dark" title="Export Ledger as CSV">
                    <span>&#128196;</span> <span>Export CSV</span>
                </a>
            </div>
        </div>

        <?= render_flash() ?>

        <!-- Summary Strip for Filtered Results -->
        <div style="display: flex; justify-content: space-between; align-items: center; background: #191c2b; border: 1px solid #282f45; border-radius: 12px; padding: 14px 20px; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
            <div style="font-size: 0.92rem; color: #cbd5e1;">
                Showing <strong><?= count($bookings) ?></strong> Bookings &bull;
                Verified Revenue: <strong style="color: #34d399; font-size: 1.05rem;"><?= format_inr($filterTotalAmount) ?></strong> &bull;
                Total Headcount: <strong style="color: #60a5fa;"><?= number_format($filterTotalPersons) ?> Persons</strong>
            </div>
            <div style="font-size: 0.82rem; color: #94a3b8;">
                Real-time synchronized with Hostinger MySQL Database
            </div>
        </div>

        <!-- Filter Navigation Tabs -->
        <div class="bms-tabs-bar">
            <a href="<?= BASE_URL ?>/admin/bookings.php?status=all" class="bms-tab-btn <?= $statusFilter === 'all' ? 'active' : '' ?>">
                <span>&#128203; All Bookings</span>
                <span class="bms-tab-count"><?= $totalCount ?></span>
            </a>
            <a href="<?= BASE_URL ?>/admin/bookings.php?status=verified" class="bms-tab-btn <?= $statusFilter === 'verified' ? 'active' : '' ?>" style="<?= $statusFilter === 'verified' ? 'background:rgba(16,185,129,0.2); border-color:#10b981; color:#34d399;' : '' ?>">
                <span>&#9989; Ticket Mila (Pass Issued)</span>
                <span class="bms-tab-count" style="background: rgba(16,185,129,0.3); color:#34d399;"><?= $verifiedCount ?></span>
            </a>
            <a href="<?= BASE_URL ?>/admin/bookings.php?status=pending" class="bms-tab-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>" style="<?= $statusFilter === 'pending' ? 'background:rgba(245,158,11,0.2); border-color:#f59e0b; color:#fbbf24;' : '' ?>">
                <span>&#9203; Ticket Nahi Mila (Payment Pending)</span>
                <span class="bms-tab-count" style="background: rgba(245,158,11,0.3); color:#fbbf24;"><?= $pendingCount ?></span>
            </a>
        </div>

        <!-- Search & Filter Controls -->
        <div class="admin-card" style="padding: 18px 24px;">
            <form action="<?= BASE_URL ?>/admin/bookings.php" method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                <div style="flex: 2; min-width: 250px;">
                    <input type="text" name="search" class="form-control" placeholder="Search Attendee Name, Mobile, UTR, or Booking UID..." value="<?= e($search) ?>" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px 14px; border-radius:8px;">
                </div>

                <div>
                    <select name="status" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; padding:10px 14px; border-radius:8px;" onchange="this.form.submit()">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="verified" <?= $statusFilter === 'verified' ? 'selected' : '' ?>>Ticket Mila (Verified Only)</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Ticket Nahi Mila (Pending Only)</option>
                        <option value="checked_in" <?= $statusFilter === 'checked_in' ? 'selected' : '' ?>>Gate Checked-In Only</option>
                    </select>
                </div>

                <div>
                    <select name="category" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; padding:10px 14px; border-radius:8px;" onchange="this.form.submit()">
                        <option value="0">All Ticket Categories</option>
                        <?php foreach ($allCategories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $categoryFilter == $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?> (<?= format_inr($c['price']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-bms-gold" style="padding: 9px 18px;">
                    &#128269; Filter
                </button>

                <?php if (!empty($search) || $statusFilter !== 'all' || $categoryFilter > 0): ?>
                    <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn-bms-dark" style="font-size: 0.88rem;">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Ledger Table (Who booked, what pass, and payment details) -->
        <div class="admin-card">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Booking ID</th>
                            <th>Attendee &amp; Mobile</th>
                            <th>Ticket Pass &amp; Headcount</th>
                            <th>Amount Paid</th>
                            <th>UTR / Ref ID</th>
                            <th>Ticket Status</th>
                            <th>Gate Entry</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: var(--admin-text-muted); padding: 40px;">
                                    No booking records matched your search filters.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($bookings as $b): ?>
                                <?php 
                                $cleanPhone = preg_replace('/[^\d]/', '', $b['customer_phone']);
                                $waUrl = "https://wa.me/91" . $cleanPhone . "?text=" . urlencode("Hello " . $b['customer_name'] . ", greetings from Millennium India Group! Your booking reference for Jashn-E-Garba 2026 is " . $b['booking_uid'] . ".");
                                $hasFood = stripos($b['ticket_summary'] ?? '', 'With Food') !== false;
                                $isVerified = ($b['payment_status'] === 'verified');
                                ?>
                                <tr>
                                    <td><?= $counter++ ?></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/ticket.php?uid=<?= urlencode($b['booking_uid']) ?>" target="_blank" style="font-family: monospace; font-weight: 800; color: var(--admin-primary); text-decoration: none;">
                                            <?= e($b['booking_uid']) ?>
                                        </a>
                                        <div style="font-size: 0.72rem; color: var(--admin-text-muted); margin-top: 2px;">
                                            <?= date('d M Y, h:i A', strtotime($b['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong style="color: #fff; font-size: 0.95rem;"><?= e($b['customer_name']) ?></strong>
                                        <div style="margin-top: 4px; display: flex; align-items: center; gap: 6px;">
                                            <span style="font-size: 0.82rem; color: #cbd5e1;"><?= e($b['customer_phone']) ?></span>
                                            <?php if (!empty($cleanPhone)): ?>
                                                <a href="<?= $waUrl ?>" target="_blank" class="btn-whatsapp" title="Chat with attendee on WhatsApp">
                                                    <span>&#128172;</span> WhatsApp
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.88rem; color: #fff;"><?= e($b['ticket_summary'] ?: 'Entry Pass') ?></div>
                                        <div style="display: flex; gap: 6px; align-items: center; margin-top: 4px; flex-wrap: wrap;">
                                            <span style="font-size: 0.75rem; color: var(--admin-primary); font-weight: 700;">
                                                &#128101; <?= (int)$b['total_persons'] ?> Persons
                                            </span>
                                            <?php if (!empty($b['seat_numbers'])): ?>
                                                <span style="font-size: 0.74rem; background: rgba(59, 130, 246, 0.18); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.4); padding: 1px 6px; border-radius: 4px; font-weight: 700;">
                                                    &#128186; Seats: <?= e($b['seat_numbers']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($hasFood): ?>
                                                <span class="badge-food-yes">&#127869; Food Incl.</span>
                                            <?php else: ?>
                                                <span class="badge-food-no">&#10060; No Food</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="font-weight: 800; color: #fff; font-size: 1.05rem;">
                                        <?= format_inr($b['total_amount']) ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($b['transaction_ref'])): ?>
                                            <span class="btn-copy-tag" onclick="navigator.clipboard.writeText('<?= e($b['transaction_ref']) ?>'); alert('Copied UTR: <?= e($b['transaction_ref']) ?>');" title="Click to copy UTR">
                                                &#128203; <?= e($b['transaction_ref']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #64748b; font-size: 0.8rem;">Awaiting UTR</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <!-- TICKET MILA VS TICKET NAHI MILA -->
                                        <?php if ($isVerified): ?>
                                            <div class="ticket-status-badge ticket-status-issued">
                                                &#9989; TICKET MILA
                                            </div>
                                        <?php elseif ($b['payment_status'] === 'pending'): ?>
                                            <div class="ticket-status-badge ticket-status-pending">
                                                &#9203; NAHI MILA (Pending)
                                            </div>
                                        <?php else: ?>
                                            <div class="ticket-status-badge ticket-status-failed">
                                                &#10060; <?= strtoupper(e($b['payment_status'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($b['is_checked_in']): ?>
                                            <span class="badge badge-checked" title="<?= e($b['checked_in_at']) ?>">&#10004; Entered</span>
                                        <?php else: ?>
                                            <span style="color: #64748b; font-size: 0.8rem;">Not Checked In</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                            <?php if (!$isVerified): ?>
                                                <!-- 1-Click Verify Button -->
                                                <form action="<?= BASE_URL ?>/admin/bookings.php" method="POST" style="margin:0;" onsubmit="return confirm('Verify UTR receipt <?= e($b['transaction_ref']) ?> for <?= e($b['customer_name']) ?> and ISSUE TICKET PASS?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="verify_payment">
                                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                    <button type="submit" class="btn-verify" title="Confirm Payment &amp; Issue Ticket Pass">
                                                        &#10004; Verify &amp; Issue Pass
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <!-- View Pass -->
                                                <a href="<?= BASE_URL ?>/ticket.php?uid=<?= urlencode($b['booking_uid']) ?>" target="_blank" class="btn-sm" style="background:#202434; border:1px solid #2e344a; color:#fff; text-decoration:none;" title="View Digital Pass">
                                                    View Pass
                                                </a>
                                            <?php endif; ?>

                                            <!-- Delete Booking Button -->
                                            <form action="<?= BASE_URL ?>/admin/bookings.php" method="POST" style="margin:0;" onsubmit="return confirm('Permanently delete booking #<?= e($b['booking_uid']) ?> (<?= e($b['customer_name']) ?>)? This cannot be undone.');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_booking">
                                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                <button type="submit" class="btn-delete-row" title="Delete Booking permanently">
                                                    &#128465; Delete
                                                </button>
                                            </form>

                                            <!-- Manual Check-In Toggle -->
                                            <form action="<?= BASE_URL ?>/admin/bookings.php" method="POST" style="margin:0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_checkin">
                                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                <button type="submit" class="btn-sm" style="background:#1b1e2b; border:1px solid #2e344a; color:#9aa1b8; cursor:pointer;" title="Toggle Entry">
                                                    <?= $b['is_checked_in'] ? 'Undo' : 'Check-In' ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- MODAL: Issue Walk-In / Counter Pass -->
<div class="modal-overlay" id="walkin-modal">
    <div class="modal-content" style="max-width: 520px;">
        <h3 style="color: var(--admin-primary); margin-bottom: 16px; font-size: 1.25rem;">
            &#9889; Issue Walk-In Pass (Counter / Offline)
        </h3>
        <form action="<?= BASE_URL ?>/admin/bookings.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="walkin_booking">

            <div style="margin-bottom: 14px;">
                <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">Attendee Full Name *</label>
                <input type="text" name="customer_name" required class="form-control" placeholder="e.g. Rahul Sharma" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">Mobile Number (10 Digits) *</label>
                <input type="tel" name="customer_phone" required pattern="[0-9]{10}" class="form-control" placeholder="9876543210" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
            </div>

            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">Select Pass Category *</label>
                    <select name="category_id" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                        <?php foreach ($allCategories as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= e($c['name']) ?> &mdash; <?= format_inr($c['price']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">Quantity *</label>
                    <input type="number" name="quantity" min="1" max="10" value="1" required class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:20px;">
                <div>
                    <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">Payment Mode</label>
                    <select name="payment_method" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                        <option value="cash">Cash Paid at Counter</option>
                        <option value="upi">Direct UPI (PhonePe / GPay)</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">UTR or Counter Note</label>
                    <input type="text" name="transaction_ref" placeholder="CASH / UTR..." class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn-sm" style="background:#202434; border:1px solid #2e344a; color:#fff; cursor:pointer;" onclick="closeWalkinModal()">Cancel</button>
                <button type="submit" class="btn-bms-gold">Generate Pass &rarr;</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Issue Ticket by UTR / Reference ID -->
<div class="modal-overlay" id="utr-modal">
    <div class="modal-content" style="max-width: 540px; background: #161926; border: 1px solid #2e344a; border-radius: 14px; padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="color: var(--admin-primary); margin: 0; font-size: 1.25rem;">
                &#9889; Issue Ticket Pass by Payment Reference / UTR
            </h3>
            <button type="button" onclick="closeUtrModal()" style="background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <p style="font-size: 0.84rem; color: #94a3b8; margin-bottom: 16px;">
            Enter the 12-digit UPI UTR / Reference ID received on PhonePe / Google Pay / Paytm / SBI. This will immediately issue a verified digital pass with allocated seats.
        </p>
        <form action="<?= BASE_URL ?>/admin/bookings.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_ticket_utr">

            <div style="margin-bottom: 14px;">
                <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">12-Digit UTR / Transaction Reference *</label>
                <input type="text" name="transaction_ref" required placeholder="e.g. 005821998431" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px; font-family:monospace; font-size:1rem; letter-spacing:1px;">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">Attendee Full Name *</label>
                    <input type="text" name="customer_name" required placeholder="Full Name" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">Attendee Phone Number *</label>
                    <input type="tel" name="customer_phone" required pattern="[0-9]{10}" placeholder="10 Digits" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">Select Pass Category *</label>
                    <select name="category_id" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                        <?php foreach ($allCategories as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= e($c['name']) ?> &mdash; <?= format_inr($c['price']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">Quantity *</label>
                    <input type="number" name="quantity" min="1" max="10" value="1" required class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">Seat Codes (Optional, leave blank for auto-assignment)</label>
                <input type="text" name="seat_codes" placeholder="e.g. A-01, A-02 or C-05" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn-sm" style="background:#202434; border:1px solid #2e344a; color:#fff; cursor:pointer;" onclick="closeUtrModal()">Cancel</button>
                <button type="submit" class="btn-bms-gold" style="background: linear-gradient(135deg, #10b981, #059669); border:none; padding:10px 20px; font-weight:700;">Issue Pass &amp; Lock Seats &rarr;</button>
            </div>
        </form>
    </div>
</div>

<script>
function openWalkinModal() {
    document.getElementById('walkin-modal').classList.add('active');
}
function closeWalkinModal() {
    document.getElementById('walkin-modal').classList.remove('active');
}
function openUtrModal() {
    document.getElementById('utr-modal').classList.add('active');
}
function closeUtrModal() {
    document.getElementById('utr-modal').classList.remove('active');
}
</script>
</body>
</html>
