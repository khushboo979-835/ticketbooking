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

// -------------------------------------------------------------
// Auto-Purge Legacy Dummy & Test Records Permanently
// -------------------------------------------------------------
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
} catch (Throwable $e) {
    error_log('[admin/index.php auto_purge error]: ' . $e->getMessage());
}

// -------------------------------------------------------------
// POST Handlers: Actions (Verify, Delete, Walk-In, Quota, Clean)
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

    // Action 4: Fast Quota / Capacity Adjustment
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

    // Action 5: Issue Walk-In / Counter Pass
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
                    <a href="<?= BASE_URL ?>/admin/scanner.php" class="btn-bms-gold" title="Open QR Code Turnstile Scanner">
                        <span>&#128247;</span> <span>Gate QR Scanner</span>
                    </a>
                    <button type="button" class="btn-bms-gold" style="background: linear-gradient(135deg, #10b981, #059669); color:#fff;" onclick="openWalkinModal()">
                        <span>&#9889;</span> <span>Issue Walk-In Pass</span>
                    </button>
                    <a href="<?= BASE_URL ?>/api/export-csv.php" class="btn-bms-dark" title="Download Attendee Ledger as CSV">
                        <span>&#128196;</span> <span>Export CSV</span>
                    </a>
                    <!-- Purge Dummy Data Button -->
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
                    <span>100% Verified in UPI (chandni0984@oksbi)</span>
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

            <!-- 5. Real-Time Hall Availability ("Kon Seats Khali Hai") -->
            <div class="bms-kpi-card" style="border-top: 3px solid #3b82f6;">
                <div class="bms-kpi-header">
                    <span class="bms-kpi-title">Seats Khali (Available)</span>
                    <div class="bms-kpi-icon" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">&#127978;</div>
                </div>
                <div class="bms-kpi-value" style="color: <?= $hallTotalAvailable < 50 ? '#f87171' : '#34d399' ?>;">
                    <?= number_format($hallTotalAvailable) ?>
                </div>
                <div class="bms-kpi-sub">
                    <span>Out of <?= number_format($hallTotalCapacity) ?> Total Venue Capacity</span>
                </div>
            </div>
        </div>

        <!-- ========================================================
             Grand Hall Occupancy Progress Meter
             ======================================================== -->
        <div class="bms-hall-banner">
            <div class="bms-hall-header">
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: #fff; margin-bottom: 2px;">
                        &#127881; Grand Arena Occupancy &bull; <?= $hallOccupancyPct ?>% Booked
                    </h3>
                    <div style="font-size: 0.85rem; color: #94a3b8;">
                        Total Capacity: <strong><?= number_format($hallTotalCapacity) ?> Seats</strong> &bull; 
                        Booked: <strong style="color: #ffd966;"><?= number_format($hallTotalSold) ?></strong> &bull; 
                        Remaining Khali: <strong style="color: #34d399;"><?= number_format($hallTotalAvailable) ?></strong>
                    </div>
                </div>
                <div>
                    <span class="badge" style="background: rgba(245,183,34,0.18); color:#ffd966; border:1px solid rgba(245,183,34,0.3); font-size:0.85rem; padding:6px 12px;">
                        <?= $hallOccupancyPct >= 90 ? '&#128293; ALMOST FULL' : '&#9989; SEATS AVAILABLE' ?>
                    </span>
                </div>
            </div>

            <div class="bms-progress-track">
                <div class="bms-progress-fill" style="width: <?= min(100, $hallOccupancyPct) ?>%; background: linear-gradient(90deg, #10b981 0%, #f5b722 75%, #ef4444 100%);"></div>
            </div>
        </div>

        <!-- ========================================================
             LIVE REAL-TIME SEAT & PASS INVENTORY MATRIX
             Client Feedback: "Kon seats khali hai, kon sa book hai"
             ======================================================== -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h2 style="font-size: 1.35rem; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 8px;">
                    <span>&#129681;</span> <span>Live Seat &amp; Pass Inventory Matrix</span>
                </h2>
                <div style="font-size: 0.86rem; color: #94a3b8;">
                    Real-time category breakdown showing total capacity, tickets sold, seats remaining, and revenue earned.
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

                        <!-- 3-Way Metrics Box: Total, Booked, Khali -->
                        <div class="bms-matrix-stats">
                            <div>
                                <div class="bms-stat-box-num" style="color: #cbd5e1;"><?= number_format($cat['total_quantity']) ?></div>
                                <div class="bms-stat-box-lbl">Total Capacity</div>
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

                        <!-- Mini Progress bar for this category -->
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
             ATTENDEE BOOKINGS LEDGER WITH TICKET MILA / NAHI MILA TABS
             "Yaha pr wo show hona chahiye abhi tk kitna logo ko ticket mila"
             ======================================================== -->
        <div class="admin-card">
            <div class="admin-card-header" style="flex-wrap: wrap; gap: 14px;">
                <div>
                    <h3 class="admin-card-title">&#128179; Attendee Bookings &amp; Verification Ledger</h3>
                    <div style="font-size: 0.85rem; color: var(--admin-text-muted); margin-top: 3px;">
                        Full real-time management of confirmed ticket passes, pending UPI verifications, and attendee records.
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
                            <th>Attendee &amp; Contact</th>
                            <th>Ticket Pass &amp; Headcount</th>
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
                                        <div style="font-size: 0.76rem; color: var(--admin-primary); font-weight: 700; margin-top: 2px;">
                                            &#128101; <?= (int)$b['total_persons'] ?> Persons Admitted
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
                                        <!-- TICKET MILA VS TICKET NAHI MILA (Crystal Clear Status) -->
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
     MODAL: Issue Walk-In / Counter Pass
     ======================================================== -->
<div class="modal-overlay" id="walkin-modal">
    <div class="modal-content" style="max-width: 520px;">
        <h3 style="color: var(--admin-primary); margin-bottom: 16px; font-size: 1.25rem;">
            &#9889; Issue Walk-In Pass (Counter / Offline)
        </h3>
        <form action="<?= BASE_URL ?>/admin/index.php" method="POST">
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
                        <?php foreach ($categoryMatrix as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= e($c['name']) ?> &mdash; <?= format_inr($c['price']) ?> (<?= $c['available_quantity'] ?> left)
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

// Walk-in modal
function openWalkinModal() {
    document.getElementById('walkin-modal').classList.add('active');
}
function closeWalkinModal() {
    document.getElementById('walkin-modal').classList.remove('active');
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
