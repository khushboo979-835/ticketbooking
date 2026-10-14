<?php
/**
 * admin/index.php - BookMyShow-Style Organizer Console & Real-Time Analytics
 * Millennium India Group - Jashn-E-Garba 2026
 * Real-time monitoring of ticket sales, revenue, seat availability, and attendee verification.
 */
ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

require_admin_login();
send_no_cache_headers();

$adminCurrent = 'dashboard';
$db = getDB();

ensure_seat_inventory_table();

// Auto-purge disabled to ensure no client test bookings or user records are lost


// -------------------------------------------------------------
// POST Handlers: Actions (Verify, Delete, Walk-In, Quota, Clean, UTR Issue)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session token expired. Please refresh.');
        header("Location: " . BASE_URL . "/admin/index.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action 1: Single-click Verify UPI Payment
    if ($action === 'quick_verify') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        try {
            $db->beginTransaction();

            $bStmt = $db->prepare("SELECT * FROM bookings WHERE id = :id FOR UPDATE");
            $bStmt->execute([':id' => $bookingId]);
            $bRow = $bStmt->fetch();

            if ($bRow && $bRow['payment_status'] !== 'verified') {
                $itStmt = $db->prepare("SELECT ticket_category_id, quantity FROM booking_items WHERE booking_id = :b_id");
                $itStmt->execute([':b_id' => $bookingId]);
                $items = $itStmt->fetchAll() ?: [];

                foreach ($items as $item) {
                    $deductStmt = $db->prepare("UPDATE ticket_categories SET available_quantity = GREATEST(0, available_quantity - :q) WHERE id = :c_id");
                    $deductStmt->execute([':q' => (int)$item['quantity'], ':c_id' => (int)$item['ticket_category_id']]);
                }

                // If seats are assigned, mark them as SOLD in event_seats
                if (!empty($bRow['seat_numbers'])) {
                    $seatCodes = array_filter(array_map('trim', explode(',', $bRow['seat_numbers'])));
                    $upSeat = $db->prepare("UPDATE event_seats SET status = 'sold', booking_uid = :uid WHERE seat_code = :c");
                    foreach ($seatCodes as $sc) {
                        $upSeat->execute([':uid' => $bRow['booking_uid'], ':c' => $sc]);
                    }
                }

                $upStmt = $db->prepare("UPDATE bookings SET payment_status = 'verified' WHERE id = :id");
                $upStmt->execute([':id' => $bookingId]);

                $db->commit();
                set_flash('success', "Booking #{$bRow['booking_uid']} verified! Ticket pass issued and activated for {$bRow['customer_name']}.");
            } else {
                $db->rollBack();
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[admin/index.php quick_verify error]: ' . $e->getMessage());
            set_flash('error', "Database error verifying booking: " . $e->getMessage());
        }
    }

    // Action 2: Delete Booking Permanently
    elseif ($action === 'delete_booking') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        try {
            $db->beginTransaction();

            $bStmt = $db->prepare("SELECT * FROM bookings WHERE id = :id FOR UPDATE");
            $bStmt->execute([':id' => $bookingId]);
            $bRow = $bStmt->fetch();

            if ($bRow) {
                // Restore quota if it was verified
                if ($bRow['payment_status'] === 'verified') {
                    $itStmt = $db->prepare("SELECT ticket_category_id, quantity FROM booking_items WHERE booking_id = :b_id");
                    $itStmt->execute([':b_id' => $bookingId]);
                    $items = $itStmt->fetchAll() ?: [];
                    foreach ($items as $item) {
                        $db->prepare("UPDATE ticket_categories SET available_quantity = LEAST(total_quantity, available_quantity + :q) WHERE id = :c_id")
                           ->execute([':q' => (int)$item['quantity'], ':c_id' => (int)$item['ticket_category_id']]);
                    }
                }

                // Free up any assigned seats back to available
                if (!empty($bRow['seat_numbers'])) {
                    $seatCodes = array_filter(array_map('trim', explode(',', $bRow['seat_numbers'])));
                    $freeSeat = $db->prepare("UPDATE event_seats SET status = 'available', booking_uid = NULL WHERE seat_code = :c");
                    foreach ($seatCodes as $sc) {
                        $freeSeat->execute([':c' => $sc]);
                    }
                }
                // Extra safeguard: Free any seat tagged with this booking_uid
                $db->prepare("UPDATE event_seats SET status = 'available', booking_uid = NULL WHERE booking_uid = :uid")
                   ->execute([':uid' => $bRow['booking_uid']]);

                $db->prepare("DELETE FROM booking_items WHERE booking_id = :id")->execute([':id' => $bookingId]);
                $db->prepare("DELETE FROM bookings WHERE id = :id")->execute([':id' => $bookingId]);

                $db->commit();
                set_flash('success', "Booking #{$bRow['booking_uid']} ({$bRow['customer_name']}) removed from database.");
            } else {
                $db->rollBack();
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[admin/index.php delete_booking error]: ' . $e->getMessage());
            set_flash('error', "Error deleting booking: " . $e->getMessage());
        }
    }

    // Action 3: Purge All Dummy & Test Records
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
                set_flash('success', "Successfully deleted $delCount test/dummy bookings. All displayed records are now 100% genuine.");
            } else {
                set_flash('info', "No dummy records found. Your database is already 100% clean.");
            }
        } catch (PDOException $e) {
            set_flash('error', "Error purging dummy data: " . $e->getMessage());
        }
    }

    // Action 4: Issue Ticket Directly by Payment Reference / UTR
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

                // If no seats provided, auto-pick available seats in this category
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
                $waText = "Hello {$name}, greetings from Millennium India Group! Your payment (UTR: {$utr}) for Jashn-E-Garba 2026 has been verified. Here is your official Digital Entry Pass & Receipt: " . BASE_URL . "/ticket.php?uid={$bookingUid} (Allocated Seats: {$assignedSeats}). See you at Unique Garden, Patna!";
                $waShareUrl = "https://wa.me/91" . $phone . "?text=" . urlencode($waText);
                set_flash('success', "Ticket pass issued for {$name}! Booking UID: <strong>#{$bookingUid}</strong> (UTR: {$utr}) &bull; Allocated Seats: <strong>{$assignedSeats}</strong>.<br><div style='margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;'><a href='" . BASE_URL . "/ticket.php?uid={$bookingUid}' target='_blank' style='background:#10b981; color:#042f2e; padding:6px 14px; border-radius:6px; font-weight:800; text-decoration:none;'>&#127915; View / Print Pass &rarr;</a><a href='{$waShareUrl}' target='_blank' style='background:#25d366; color:#fff; padding:6px 14px; border-radius:6px; font-weight:800; text-decoration:none;'>&#128172; Send Ticket on WhatsApp &rarr;</a></div>");
            } else {
                $db->rollBack();
                set_flash('error', "Category not found.");
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[admin/index.php create_ticket_utr error]: ' . $e->getMessage());
            set_flash('error', "Error issuing ticket: " . $e->getMessage());
        }
    }

    // Action 5: Fast Quota / Capacity Adjustment
    elseif ($action === 'update_quota') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $totalQty = max(1, (int)($_POST['total_quantity'] ?? 100));
        $availQty = max(0, min($totalQty, (int)($_POST['available_quantity'] ?? $totalQty)));
        $catStatus = in_array($_POST['status'] ?? '', ['active', 'sold_out', 'hidden']) ? $_POST['status'] : 'active';

        try {
            $upCat = $db->prepare("UPDATE ticket_categories SET total_quantity = :tq, available_quantity = :aq, status = :st WHERE id = :id");
            $upCat->execute([':tq' => $totalQty, ':aq' => $availQty, ':st' => $catStatus, ':id' => $catId]);
            set_flash('success', "Ticket quota updated successfully.");
        } catch (PDOException $e) {
            error_log('[admin/index.php update_quota error]: ' . $e->getMessage());
            set_flash('error', "Error updating category quota: " . $e->getMessage());
        }
    }

    // Action 6: Issue Walk-In / Counter Pass
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

                // Auto-pick available seats in this category
                $seatStmt = $db->prepare("SELECT seat_code FROM event_seats WHERE category_id = :cid AND status = 'available' LIMIT :lim");
                $seatStmt->bindValue(':cid', $catId, PDO::PARAM_INT);
                $seatStmt->bindValue(':lim', $qty, PDO::PARAM_INT);
                $seatStmt->execute();
                $picked = $seatStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $assignedSeats = implode(', ', $picked);

                $insB = $db->prepare("
                    INSERT INTO bookings (booking_uid, event_id, customer_name, customer_phone, customer_email, total_amount, payment_method, payment_status, transaction_ref, qr_token, is_checked_in, seat_numbers, created_at)
                    VALUES (:uid, 1, :name, :phone, :email, :amt, :method, 'verified', :ref, :qr, 0, :seats, NOW())
                ");
                $insB->execute([
                    ':uid'    => $bookingUid,
                    ':name'   => $custName,
                    ':phone'  => $custPhone,
                    ':email'  => $custPhone . '@counter.mig',
                    ':amt'    => $totalAmt,
                    ':method' => $payMethod,
                    ':ref'    => $refNote,
                    ':qr'     => $qrToken,
                    ':seats'  => $assignedSeats
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

                // Mark seats sold
                if (!empty($picked)) {
                    $upSeat = $db->prepare("UPDATE event_seats SET status = 'sold', booking_uid = :uid WHERE seat_code = :c");
                    foreach ($picked as $sc) {
                        $upSeat->execute([':uid' => $bookingUid, ':c' => $sc]);
                    }
                }

                $db->commit();
                set_flash('success', "Walk-In Pass #{$bookingUid} generated for {$custName}! Seats: {$assignedSeats}. <a href='" . BASE_URL . "/ticket.php?uid={$bookingUid}' target='_blank' style='color:#fff; text-decoration:underline; font-weight:bold;'>View Digital Pass &rarr;</a>");
            } else {
                $db->rollBack();
                set_flash('error', "Selected category not found.");
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[admin/index.php walkin error]: ' . $e->getMessage());
            set_flash('error', "Error generating pass: " . $e->getMessage());
        }
    }

    header("Location: " . BASE_URL . "/admin/index.php");
    exit;
}

// -------------------------------------------------------------
// Live Real-Time Analytics & Quota Matrix Queries
// -------------------------------------------------------------
$tab = $_GET['tab'] ?? 'all'; // 'all', 'issued', 'pending'

$totalBookings = 0;
$verifiedBookingsCount = 0;
$pendingPaymentsCount = 0;
$totalRevenue = 0.0;
$pendingRevenue = 0.0;
$totalPassesSold = 0;
$totalPersonsAdmitted = 0;
$checkedInCount = 0;
$categoryMatrix = [];
$hallTotalCapacity = 0;
$hallTotalSold = 0;
$hallTotalAvailable = 0;
$recentBookings = [];
$seatSummary = get_seat_summary(1);
$rawCategories = [];

try {
    // 1. High-Level Booking KPI counts
    $totalBookings = (int)$db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $verifiedBookingsCount = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE payment_status = 'verified'")->fetchColumn();
    $pendingPaymentsCount = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE payment_status = 'pending'")->fetchColumn();
    $totalRevenue = (float)$db->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE payment_status = 'verified'")->fetchColumn();
    $pendingRevenue = (float)$db->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE payment_status = 'pending'")->fetchColumn();
    $checkedInCount = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE is_checked_in = 1")->fetchColumn();

    // 2. Headcount & Total Passes sold across verified bookings
    $headcountStmt = $db->query("
        SELECT 
            COALESCE(SUM(bi.quantity * tc.people_allowed), 0) AS total_persons,
            COALESCE(SUM(bi.quantity), 0) AS total_tickets
        FROM bookings b
        JOIN booking_items bi ON b.id = bi.booking_id
        JOIN ticket_categories tc ON bi.ticket_category_id = tc.id
        WHERE b.payment_status = 'verified'
    ");
    $headcountRow = $headcountStmt->fetch();
    $totalPersonsAdmitted = (int)($headcountRow['total_persons'] ?? 0);
    $totalPassesSold = (int)($headcountRow['total_tickets'] ?? 0);

    // 3. Category breakdown directly from verified booking items
    $catBookedCounts = [];
    $cbStmt = $db->query("
        SELECT bi.ticket_category_id, SUM(bi.quantity) AS booked_qty, SUM(bi.subtotal) AS item_rev
        FROM booking_items bi
        JOIN bookings b ON bi.booking_id = b.id
        WHERE b.payment_status = 'verified'
        GROUP BY bi.ticket_category_id
    ");
    while ($r = $cbStmt->fetch()) {
        $catBookedCounts[(int)$r['ticket_category_id']] = [
            'qty' => (int)$r['booked_qty'],
            'rev' => (float)$r['item_rev']
        ];
    }

    // 4. Fetch all ticket categories and build the "Kon seats khali hai, kon sa book hai" Matrix
    $catStmt = $db->query("
        SELECT * FROM ticket_categories 
        WHERE event_id = 1 
        ORDER BY price ASC
    ");
    $rawCategories = $catStmt->fetchAll() ?: [];

    foreach ($rawCategories as $cat) {
        $cId = (int)$cat['id'];
        $totalCap = (int)$cat['total_quantity'];
        $availFromDb = (int)$cat['available_quantity'];
        $actualBooked = $catBookedCounts[$cId]['qty'] ?? 0;

        $soldQty = max(0, $totalCap - $availFromDb);
        if ($actualBooked > $soldQty) {
            $soldQty = $actualBooked;
        }
        $availQty = max(0, $totalCap - $soldQty);
        $occupancyPct = $totalCap > 0 ? round(($soldQty / $totalCap) * 100, 1) : 0;
        $catRev = $soldQty * (float)$cat['price'];

        $hallTotalCapacity += $totalCap;
        $hallTotalSold += $soldQty;
        $hallTotalAvailable += $availQty;

        $hasFood = stripos($cat['name'], 'With Food') !== false;

        $categoryMatrix[] = [
            'id'                 => $cId,
            'name'               => $cat['name'],
            'price'              => (float)$cat['price'],
            'people_allowed'     => (int)$cat['people_allowed'],
            'total_quantity'     => $totalCap,
            'sold_quantity'      => $soldQty,
            'available_quantity' => $availQty,
            'occupancy_pct'      => $occupancyPct,
            'category_revenue'   => $catRev,
            'status'             => $cat['status'],
            'has_food'           => $hasFood
        ];
    }

    $hallOccupancyPct = $hallTotalCapacity > 0 ? round(($hallTotalSold / $hallTotalCapacity) * 100, 1) : 0;

    // 5. Recent Bookings query with Tab filter
    $tabSql = "1=1";
    if ($tab === 'issued') {
        $tabSql = "b.payment_status = 'verified'";
    } elseif ($tab === 'pending') {
        $tabSql = "b.payment_status = 'pending'";
    }

    $recentStmt = $db->query("
        SELECT b.*, MAX(e.title) AS event_title,
               GROUP_CONCAT(CONCAT(tc.name, ' (x', bi.quantity, ')') SEPARATOR ', ') AS ticket_summary,
               SUM(bi.quantity * tc.people_allowed) AS total_persons
        FROM bookings b
        LEFT JOIN events e ON b.event_id = e.id
        LEFT JOIN booking_items bi ON b.id = bi.booking_id
        LEFT JOIN ticket_categories tc ON bi.ticket_category_id = tc.id
        WHERE $tabSql
        GROUP BY b.id
        ORDER BY b.id DESC 
        LIMIT 25
    ");
    $recentBookings = $recentStmt->fetchAll() ?: [];
} catch (PDOException $e) {
    error_log('[admin/index.php analytics error]: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Console &amp; Seat Inventory | Millennium India Group</title>
    <link rel="stylesheet" href="<?= asset_v('assets/css/admin.css') ?>">
</head>
<body>
<div class="admin-wrapper">
    <!-- Admin Sidebar Navigation -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Console Content -->
    <main class="admin-main">
        <?= render_flash() ?>

        <!-- ========================================================
             BookMyShow-Grade Event Header & Quick Action Hub
             ======================================================== -->
        <div class="bms-hero-card">
            <div class="bms-hero-top">
                <div>
                    <div class="bms-event-tag">
                        <span class="pulse-dot"></span>
                        <span>Live Event Console &bull; BookMyShow Pro</span>
                    </div>
                    <h1 style="font-size: 1.85rem; font-weight: 800; color: #fff; margin: 10px 0 4px;">
                        Jashn-E-Garba 2026 &mdash; Organizer Dashboard
                    </h1>
                    <div class="bms-hero-meta">
                        <div class="bms-hero-meta-item">
                            <span>&#128197;</span> <strong>16 Oct 2026</strong> &bull; 05:00 PM – 09:00 PM
                        </div>
                        <div class="bms-hero-meta-item">
                            <span>&#128205;</span> Millennium Grand Arena, Patna
                        </div>
                        <div class="bms-hero-meta-item">
                            <span>&#128344;</span> <span id="live-clock"><?= date('h:i:s A') ?></span> IST
                        </div>
                    </div>
                </div>

                <div class="bms-hero-actions">
                    <button type="button" class="btn-bms-gold" style="background: linear-gradient(135deg, #10b981, #059669); color:#fff;" onclick="openUtrModal()">
                        <span>&#127915;</span> <span>Issue Ticket by UTR</span>
                    </button>
                    <a href="<?= BASE_URL ?>/admin/seats.php" class="btn-bms-gold" title="Open Visual Arena Seat Map">
                        <span>&#129681;</span> <span>Seat Matrix (Khali/Booked)</span>
                    </a>
                    <a href="<?= BASE_URL ?>/admin/scanner.php" class="btn-bms-dark" title="Open QR Code Turnstile Scanner">
                        <span>&#128247;</span> <span>Gate Scanner</span>
                    </a>
                    <a href="<?= BASE_URL ?>/api/export-csv.php" class="btn-bms-dark" title="Download Attendee Ledger as CSV">
                        <span>&#128196;</span> <span>Export CSV</span>
                    </a>
                    <form action="<?= BASE_URL ?>/admin/index.php" method="POST" style="margin:0;" onsubmit="return confirm('Purge all dummy and test records permanently? Real customer bookings will remain intact.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="clean_dummy_data">
                        <button type="submit" class="btn-bms-dark" style="background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.35); color: #f87171;" title="Remove Test / Dummy Data">
                            <span>&#129529;</span> <span>Clean Dummy Data</span>
                        </button>
                    </form>
                    <button type="button" class="btn-bms-dark" onclick="location.reload()" title="Refresh Live Data">
                        <span>&#128260;</span> <span>Refresh</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================
             Top KPI Metrics Strip (Total Booked & Total Revenue)
             "Kitna logo ko ticket mila, jitna payment hua wo sb show hona chahiye"
             ======================================================== -->
        <div class="bms-kpi-grid">
            <!-- 1. Tickets Issued (Kitna Logo Ko Ticket Mila) -->
            <div class="bms-kpi-card" style="border-top: 3px solid #10b981;">
                <div class="bms-kpi-header">
                    <span class="bms-kpi-title">Tickets Issued (Ticket Mila)</span>
                    <div class="bms-kpi-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">&#127915;</div>
                </div>
                <div class="bms-kpi-value" style="color: #34d399;">
                    <?= number_format($verifiedBookingsCount) ?> <span style="font-size:1rem; font-weight:600; color:#94a3b8;">Passes</span>
                </div>
                <div class="bms-kpi-sub">
                    <span style="color: #34d399; font-weight: 700;">&#10004; <?= number_format($totalPersonsAdmitted) ?> Persons Admitted</span> &bull; Active
                </div>
            </div>

            <!-- 2. Total Verified Revenue (Jitna Payment Hua) -->
            <div class="bms-kpi-card" style="border-top: 3px solid #f5b722;">
                <div class="bms-kpi-header">
                    <span class="bms-kpi-title">Total Payment Received</span>
                    <div class="bms-kpi-icon" style="background: rgba(245, 183, 34, 0.15); color: #f5b722;">&#8377;</div>
                </div>
                <div class="bms-kpi-value" style="color: #ffd966;"><?= format_inr($totalRevenue) ?></div>
                <div class="bms-kpi-sub">
                    <span>100% Verified in UPI (ashrafkhan0777-2@oksbi &bull; Ashraf Ali)</span>
                </div>
            </div>

            <!-- 3. Payment Pending (Ticket Nahi Mila) -->
            <div class="bms-kpi-card" style="border-top: 3px solid #f59e0b;">
                <div class="bms-kpi-header">
                    <span class="bms-kpi-title">Awaiting Payment (Pending)</span>
                    <div class="bms-kpi-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">&#9203;</div>
                </div>
                <div class="bms-kpi-value" style="color: #fbbf24;"><?= number_format($pendingPaymentsCount) ?></div>
                <div class="bms-kpi-sub">
                    <span style="color: #fbbf24; font-weight: 700;">Ticket Nahi Mila</span> &bull; Awaiting UTR
                </div>
            </div>

            <!-- 4. Gate Entry Check-Ins -->
            <div class="bms-kpi-card" style="border-top: 3px solid #8b5cf6;">
                <div class="bms-kpi-header">
                    <span class="bms-kpi-title">Gate Entered (Check-In)</span>
                    <div class="bms-kpi-icon" style="background: rgba(139, 92, 246, 0.15); color: #a78bfa;">&#9989;</div>
                </div>
                <div class="bms-kpi-value" style="color: #c4b5fd;"><?= number_format($checkedInCount) ?></div>
                <div class="bms-kpi-sub">
                    <?php 
                    $turnout = $verifiedBookingsCount > 0 ? round(($checkedInCount / $verifiedBookingsCount) * 100, 1) : 0;
                    ?>
                    <span>Turnout: <?= $turnout ?>% entry rate</span>
                </div>
            </div>

            <!-- 5. Real-Time Arena Seats Khali/Booked Summary -->
            <div class="bms-kpi-card" style="border-top: 3px solid #3b82f6;">
                <div class="bms-kpi-header">
                    <span class="bms-kpi-title">Arena Seats (Khali vs Booked)</span>
                    <div class="bms-kpi-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">&#129681;</div>
                </div>
                <div class="bms-kpi-value" style="color: #34d399;">
                    <?= number_format($seatSummary['available']) ?> <span style="font-size:0.9rem; color:#94a3b8;">Khali</span>
                </div>
                <div class="bms-kpi-sub">
                    <span style="color: #ffd966; font-weight: 700;"><?= $seatSummary['sold'] ?> Booked</span> &bull; <?= $seatSummary['total'] ?> Total Seats
                </div>
            </div>
        </div>

        <!-- ========================================================
             Direct UTR Pass Generator Console (Embedded Right on Dashboard)
             "admin panel ko jo payment hua wo reference id se uska ticket generate kare and de"
             ======================================================== -->
        <div class="admin-card" style="border: 2px solid #10b981; background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(22, 25, 38, 0.98)); padding: 22px 24px; border-radius: 14px; margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <span style="background: #10b981; color: #042f2e; font-weight: 800; font-size: 0.74rem; padding: 3px 12px; border-radius: 20px; text-transform: uppercase;">
                        &#9889; 1-Click Ticket Generator
                    </span>
                    <h3 style="color: #fff; font-size: 1.3rem; font-weight: 800; margin: 6px 0 0;">
                        &#9889; Issue Verified Ticket by Payment Reference / UTR
                    </h3>
                    <div style="font-size: 0.84rem; color: #94a3b8; margin-top: 2px;">
                        Payment received on PhonePe / Google Pay / SBI? Enter attendee details and 12-digit UTR to immediately generate verified pass and lock seats!
                    </div>
                </div>
                <div>
                    <a href="<?= BASE_URL ?>/admin/seats.php" class="btn-bms-dark" style="border-color: #3b82f6; color: #60a5fa; font-size: 0.85rem;">
                        <span>&#128186;</span> <span>View Seat Matrix</span>
                    </a>
                </div>
            </div>

            <form action="<?= BASE_URL ?>/admin/index.php" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 160px; gap: 12px; align-items: end;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_ticket_utr">

                <div>
                    <label style="display:block; font-size:0.8rem; color:#cbd5e1; font-weight:700; margin-bottom:4px;">12-Digit UPI UTR / Ref *</label>
                    <input type="text" name="transaction_ref" required placeholder="e.g. 005821998431" class="form-control" style="background:#10131f; border:1.5px solid #2e344a; color:#fff; width:100%; padding:9px 12px; border-radius:6px; font-family:monospace; font-size:0.95rem; box-sizing:border-box;">
                </div>

                <div>
                    <label style="display:block; font-size:0.8rem; color:#cbd5e1; font-weight:700; margin-bottom:4px;">Attendee Name *</label>
                    <input type="text" name="customer_name" required placeholder="Full Name" class="form-control" style="background:#10131f; border:1.5px solid #2e344a; color:#fff; width:100%; padding:9px 12px; border-radius:6px; font-size:0.95rem; box-sizing:border-box;">
                </div>

                <div>
                    <label style="display:block; font-size:0.8rem; color:#cbd5e1; font-weight:700; margin-bottom:4px;">Attendee Mobile *</label>
                    <input type="tel" name="customer_phone" required pattern="[0-9]{10}" placeholder="10 Digits" class="form-control" style="background:#10131f; border:1.5px solid #2e344a; color:#fff; width:100%; padding:9px 12px; border-radius:6px; font-size:0.95rem; box-sizing:border-box;">
                </div>

                <div>
                    <label style="display:block; font-size:0.8rem; color:#cbd5e1; font-weight:700; margin-bottom:4px;">Pass Category *</label>
                    <select name="category_id" class="form-control" style="background:#10131f; border:1.5px solid #2e344a; color:#fff; width:100%; padding:9px 12px; border-radius:6px; font-size:0.92rem; box-sizing:border-box;">
                        <?php foreach ($rawCategories as $rc): ?>
                            <option value="<?= $rc['id'] ?>">
                                <?= e($rc['name']) ?> (<?= format_inr($rc['price']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display:block; font-size:0.8rem; color:#cbd5e1; font-weight:700; margin-bottom:4px;">Seats / Qty *</label>
                    <input type="number" name="quantity" min="1" max="10" value="1" required class="form-control" style="background:#10131f; border:1.5px solid #2e344a; color:#fff; width:100%; padding:9px 12px; border-radius:6px; font-size:0.95rem; box-sizing:border-box;">
                </div>

                <div>
                    <label style="display:block; font-size:0.8rem; color:#cbd5e1; font-weight:700; margin-bottom:4px;">Seat Codes (Optional)</label>
                    <input type="text" name="seat_codes" placeholder="Auto-assign if blank" class="form-control" style="background:#10131f; border:1.5px solid #2e344a; color:#fff; width:100%; padding:9px 12px; border-radius:6px; font-size:0.95rem; box-sizing:border-box;">
                </div>

                <div>
                    <button type="submit" class="btn-bms-gold" style="background: linear-gradient(135deg, #10b981, #059669); border:none; padding:10px 14px; font-weight:800; font-size:0.92rem; width:100%; height:42px; border-radius:6px; cursor:pointer;">
                        <span>&#9889; Issue Pass</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Grand Hall Progress Meter -->
        <div class="bms-hall-banner">
            <div class="bms-hall-header">
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: #fff; margin-bottom: 2px;">
                        &#127881; Arena Seating Occupancy &bull; <?= $seatSummary['occupancy_pct'] ?>% Booked
                    </h3>
                    <div style="font-size: 0.85rem; color: #94a3b8;">
                        Total Seats: <strong><?= number_format($seatSummary['total']) ?></strong> &bull; 
                        Booked: <strong style="color: #ffd966;"><?= number_format($seatSummary['sold']) ?></strong> &bull; 
                        Remaining Khali: <strong style="color: #34d399;"><?= number_format($seatSummary['available']) ?></strong>
                    </div>
                </div>
                <div>
                    <a href="<?= BASE_URL ?>/admin/seats.php" class="btn-bms-gold" style="font-size:0.85rem; padding:6px 14px;">
                        <span>&#129681; Open Visual Seat Map Manager</span> &rarr;
                    </a>
                </div>
            </div>

            <div class="bms-progress-track">
                <div class="bms-progress-fill" style="width: <?= min(100, $seatSummary['occupancy_pct']) ?>%; background: linear-gradient(90deg, #10b981 0%, #f5b722 75%, #ef4444 100%);"></div>
            </div>
        </div>

        <!-- Category Matrix Breakdown -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h2 style="font-size: 1.35rem; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 8px;">
                    <span>&#129681;</span> <span>Pass Tier Breakdown &amp; Quota Matrix</span>
                </h2>
                <div style="font-size: 0.86rem; color: #94a3b8;">
                    Category pricing, food privileges, available quota, and revenue earned.
                </div>
            </div>
            <a href="<?= BASE_URL ?>/admin/tickets.php" class="btn-bms-dark" style="font-size: 0.82rem;">
                Manage All Categories &rarr;
            </a>
        </div>

        <div class="bms-matrix-grid">
            <?php foreach ($categoryMatrix as $cat): ?>
                <div class="bms-matrix-card">
                    <div>
                        <div class="bms-matrix-badge-strip">
                            <span style="background: rgba(245,183,34,0.14); color: #ffd966; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">
                                &#128100; <?= $cat['people_allowed'] ?> Person<?= $cat['people_allowed'] > 1 ? 's' : '' ?> per Pass
                            </span>
                            <?php if ($cat['has_food']): ?>
                                <span class="badge-food-yes">&#127869; Food Included</span>
                            <?php else: ?>
                                <span class="badge-food-no">&#10060; No Food</span>
                            <?php endif; ?>
                        </div>

                        <div class="bms-matrix-name"><?= e($cat['name']) ?></div>
                        <div class="bms-matrix-price"><?= format_inr($cat['price']) ?></div>

                        <div class="bms-matrix-stats">
                            <div>
                                <div class="bms-stat-box-num" style="color: #cbd5e1;"><?= number_format($cat['total_quantity']) ?></div>
                                <div class="bms-stat-box-lbl">Total Quota</div>
                            </div>
                            <div>
                                <div class="bms-stat-box-num" style="color: #ffd966;"><?= number_format($cat['sold_quantity']) ?></div>
                                <div class="bms-stat-box-lbl">Booked (Sold)</div>
                            </div>
                            <div>
                                <div class="bms-stat-box-num" style="color: <?= $cat['available_quantity'] < 20 ? '#f87171' : '#34d399' ?>;">
                                    <?= number_format($cat['available_quantity']) ?>
                                </div>
                                <div class="bms-stat-box-lbl" style="color: <?= $cat['available_quantity'] < 20 ? '#f87171' : '#34d399' ?>;">Khali (Seats Left)</div>
                            </div>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.76rem; color: #94a3b8; margin-bottom: 4px;">
                                <span>Occupancy Rate</span>
                                <strong style="color: #fff;"><?= $cat['occupancy_pct'] ?>%</strong>
                            </div>
                            <div class="bms-progress-track" style="height: 8px;">
                                <div class="bms-progress-fill" style="width: <?= min(100, $cat['occupancy_pct']) ?>%; background: <?= $cat['occupancy_pct'] > 85 ? '#ef4444' : ($cat['occupancy_pct'] > 50 ? '#f5b722' : '#10b981') ?>;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="bms-matrix-footer">
                        <div>
                            <div style="font-size: 0.72rem; color: #94a3b8; text-transform: uppercase;">Category Revenue</div>
                            <div style="font-size: 1.05rem; font-weight: 800; color: #34d399;"><?= format_inr($cat['category_revenue']) ?></div>
                        </div>
                        <button type="button" class="btn-sm" style="background:#202434; border:1px solid #2e344a; color:#cbd5e1; cursor:pointer;" onclick='openQuotaModal(<?= json_encode($cat) ?>)'>
                            &#9998; Edit Quota
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ========================================================
             ATTENDEE BOOKINGS LEDGER WITH SEAT NUMBERS
             ======================================================== -->
        <div class="admin-card">
            <div class="admin-card-header" style="flex-wrap: wrap; gap: 14px;">
                <div>
                    <h3 class="admin-card-title">&#128179; Attendee Bookings &amp; Verification Ledger</h3>
                    <div style="font-size: 0.85rem; color: var(--admin-text-muted); margin-top: 3px;">
                        Full real-time management of confirmed ticket passes, allocated seat numbers, and pending UPI verifications.
                    </div>
                </div>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn-bms-gold" style="font-size: 0.84rem; padding: 7px 14px;">
                        <span>Full Search Ledger (<?= $totalBookings ?>)</span> &rarr;
                    </a>
                </div>
            </div>

            <!-- Tab Filter Strip: All Bookings | Ticket Mila (Confirmed) | Ticket Nahi Mila (Pending) -->
            <div class="bms-tabs-bar">
                <a href="<?= BASE_URL ?>/admin/index.php?tab=all" class="bms-tab-btn <?= $tab === 'all' ? 'active' : '' ?>">
                    <span>&#128203; All Bookings</span>
                    <span class="bms-tab-count"><?= $totalBookings ?></span>
                </a>
                <a href="<?= BASE_URL ?>/admin/index.php?tab=issued" class="bms-tab-btn <?= $tab === 'issued' ? 'active' : '' ?>" style="<?= $tab === 'issued' ? 'background:rgba(16,185,129,0.2); border-color:#10b981; color:#34d399;' : '' ?>">
                    <span>&#9989; Ticket Mila (Pass Issued)</span>
                    <span class="bms-tab-count" style="background: rgba(16,185,129,0.3); color:#34d399;"><?= $verifiedBookingsCount ?></span>
                </a>
                <a href="<?= BASE_URL ?>/admin/index.php?tab=pending" class="bms-tab-btn <?= $tab === 'pending' ? 'active' : '' ?>" style="<?= $tab === 'pending' ? 'background:rgba(245,158,11,0.2); border-color:#f59e0b; color:#fbbf24;' : '' ?>">
                    <span>&#9203; Ticket Nahi Mila (Payment Pending)</span>
                    <span class="bms-tab-count" style="background: rgba(245,158,11,0.3); color:#fbbf24;"><?= $pendingPaymentsCount ?></span>
                </a>
            </div>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Booking ID</th>
                            <th>Attendee &amp; Mobile</th>
                            <th>Ticket Pass &amp; Seats</th>
                            <th>Amount Paid</th>
                            <th>UTR / Ref ID</th>
                            <th>Ticket Status</th>
                            <th>Gate Entry</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentBookings)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: var(--admin-text-muted); padding: 35px;">
                                    <?php if ($tab === 'issued'): ?>
                                        No tickets have been verified yet. When attendees complete UPI payment, verified passes will show here.
                                    <?php elseif ($tab === 'pending'): ?>
                                        Zero pending payments. All bookings have verified tickets!
                                    <?php else: ?>
                                        No booking records found.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($recentBookings as $b): ?>
                                <?php 
                                $cleanPhone = preg_replace('/[^\d]/', '', $b['customer_phone']);
                                $waUrl = "https://wa.me/91" . $cleanPhone . "?text=" . urlencode("Hello " . $b['customer_name'] . ", greetings from Millennium India Group! Your booking reference for Jashn-E-Garba 2026 is " . $b['booking_uid'] . ".");
                                $isVerified = ($b['payment_status'] === 'verified');
                                ?>
                                <tr>
                                    <td><?= $counter++ ?></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/ticket.php?uid=<?= urlencode($b['booking_uid']) ?>" target="_blank" style="font-family: monospace; font-weight: 800; color: var(--admin-primary); text-decoration: none;">
                                            <?= e($b['booking_uid']) ?>
                                        </a>
                                        <div style="font-size: 0.72rem; color: var(--admin-text-muted); margin-top: 2px;">
                                            <?= date('d M, h:i A', strtotime($b['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong style="color: #fff; font-size: 0.95rem;"><?= e($b['customer_name']) ?></strong>
                                        <div style="margin-top: 3px; display: flex; align-items: center; gap: 6px;">
                                            <span style="font-size: 0.82rem; color: #cbd5e1;"><?= e($b['customer_phone']) ?></span>
                                            <?php if (!empty($cleanPhone)): ?>
                                                <a href="<?= $waUrl ?>" target="_blank" class="btn-whatsapp" title="Message attendee on WhatsApp">
                                                    <span>&#128172;</span> WhatsApp
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.88rem; color: #fff;"><?= e($b['ticket_summary'] ?: 'Standard Entry') ?></div>
                                        <div style="display: flex; gap: 6px; align-items: center; margin-top: 3px; flex-wrap: wrap;">
                                            <span style="font-size: 0.76rem; color: var(--admin-primary); font-weight: 700;">
                                                &#128101; <?= (int)$b['total_persons'] ?> Persons
                                            </span>
                                            <?php if (!empty($b['seat_numbers'])): ?>
                                                <span style="background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3); font-size: 0.74rem; font-weight: 800; padding: 2px 6px; border-radius: 4px; font-family: monospace;">
                                                    &#129681; <?= e($b['seat_numbers']) ?>
                                                </span>
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
                                                <!-- 1-Click Verify & Issue Ticket -->
                                                <form action="<?= BASE_URL ?>/admin/index.php" method="POST" style="margin:0;" onsubmit="return confirm('Verify UPI payment and ISSUE TICKET PASS to <?= e($b['customer_name']) ?>?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="quick_verify">
                                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                    <button type="submit" class="btn-verify" title="Confirm Payment &amp; Issue Ticket Pass">
                                                        &#10004; Verify &amp; Issue Pass
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <!-- View Pass -->
                                                <a href="<?= BASE_URL ?>/ticket.php?uid=<?= urlencode($b['booking_uid']) ?>" target="_blank" class="btn-sm" style="background:#202434; border:1px solid #2e344a; color:#fff; text-decoration:none;" title="View Digital Pass &amp; Tax Invoice">
                                                    View Pass
                                                </a>
                                            <?php endif; ?>

                                            <!-- Delete Booking Button -->
                                            <form action="<?= BASE_URL ?>/admin/index.php" method="POST" style="margin:0;" onsubmit="return confirm('Permanently delete booking #<?= e($b['booking_uid']) ?> (<?= e($b['customer_name']) ?>)? This cannot be undone.');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_booking">
                                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                <button type="submit" class="btn-delete-row" title="Delete Booking permanently">
                                                    &#128465; Delete
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

<!-- ========================================================
     MODAL: Issue Ticket by Payment Reference / UTR
     ======================================================== -->
<div class="modal-overlay" id="utr-modal">
    <div class="modal-content" style="max-width: 540px;">
        <h3 style="color: var(--admin-primary); margin-bottom: 16px; font-size: 1.25rem;">
            &#127915; Issue Ticket by UTR / Reference ID
        </h3>
        <p style="font-size: 0.84rem; color: #94a3b8; margin-bottom: 16px;">
            Enter the customer's UPI Reference ID / UTR to generate an official digital ticket and auto-assign arena seats.
        </p>

        <form action="<?= BASE_URL ?>/admin/index.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_ticket_utr">

            <div style="margin-bottom: 14px;">
                <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">12-Digit UTR / Transaction Reference *</label>
                <input type="text" name="transaction_ref" required placeholder="e.g. 005821998431" class="form-control" style="background:#12141d; border:1px solid #f5b722; color:#fff; width:100%; padding:10px; border-radius:6px; font-family:monospace; font-weight:700;">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">Attendee Full Name *</label>
                    <input type="text" name="customer_name" required placeholder="e.g. Rishu raj" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">Mobile Number *</label>
                    <input type="tel" name="customer_phone" required pattern="[0-9]{10}" placeholder="6201954936" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">Ticket Category *</label>
                    <select name="category_id" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                        <?php foreach ($rawCategories as $c): ?>
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
                <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">Seat Codes (Optional: leave blank to auto-assign)</label>
                <input type="text" name="seat_codes" placeholder="e.g. E-07, E-08 (or leave empty)" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px; font-family:monospace;">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn-sm" style="background:#202434; border:1px solid #2e344a; color:#fff; cursor:pointer;" onclick="closeUtrModal()">Cancel</button>
                <button type="submit" class="btn-bms-gold">Issue Pass Now &rarr;</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================
     MODAL: Quick Quota / Inventory Adjustment
     ======================================================== -->
<div class="modal-overlay" id="quota-modal">
    <div class="modal-content" style="max-width: 480px;">
        <h3 id="quota-modal-title" style="color: var(--admin-primary); margin-bottom: 16px; font-size: 1.2rem;">
            &#9998; Adjust Category Quota
        </h3>
        <form action="<?= BASE_URL ?>/admin/index.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_quota">
            <input type="hidden" name="category_id" id="quota-cat-id" value="0">

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">Total Capacity *</label>
                    <input type="number" name="total_quantity" id="quota-cat-total" min="1" max="10000" required class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">Available Remaining *</label>
                    <input type="number" name="available_quantity" id="quota-cat-avail" min="0" max="10000" required class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display:block; font-size:0.85rem; color:#cbd5e1; margin-bottom:4px;">Availability Status</label>
                <select name="status" id="quota-cat-status" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                    <option value="active">Active (Available Online)</option>
                    <option value="sold_out">Sold Out</option>
                    <option value="hidden">Hidden</option>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn-sm" style="background:#202434; border:1px solid #2e344a; color:#fff; cursor:pointer;" onclick="closeQuotaModal()">Cancel</button>
                <button type="submit" class="btn-bms-gold">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// Live clock
setInterval(function() {
    var now = new Date();
    var timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    var el = document.getElementById('live-clock');
    if (el) el.innerText = timeString;
}, 1000);

// UTR modal
function openUtrModal() {
    document.getElementById('utr-modal').classList.add('active');
}
function closeUtrModal() {
    document.getElementById('utr-modal').classList.remove('active');
}

// Quota modal
function openQuotaModal(c) {
    document.getElementById('quota-cat-id').value = c.id;
    document.getElementById('quota-cat-total').value = c.total_quantity;
    document.getElementById('quota-cat-avail').value = c.available_quantity;
    document.getElementById('quota-cat-status').value = c.status;
    document.getElementById('quota-modal-title').innerText = 'Adjust Quota: ' + c.name;
    document.getElementById('quota-modal').classList.add('active');
}
function closeQuotaModal() {
    document.getElementById('quota-modal').classList.remove('active');
}
</script>
</body>
</html>
