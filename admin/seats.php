<?php
/**
 * admin/seats.php - Interactive Seat Layout & Khali/Booked Matrix Manager
 * Millennium India Group - Jashn-E-Garba 2026
 * Real-time control of seat states (Available vs Sold vs Blocked)
 */
ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

require_admin_login();
send_no_cache_headers();

$adminCurrent = 'seats';
$db = getDB();

ensure_seat_inventory_table();

// Handle AJAX Seat Status Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_seat_ajax') {
    header('Content-Type: application/json');
    $seatCode = trim($_POST['seat_code'] ?? '');
    $newStatus = in_array($_POST['status'] ?? '', ['available', 'sold', 'blocked']) ? $_POST['status'] : null;

    if (!empty($seatCode)) {
        try {
            if (!$newStatus) {
                // Toggle: available -> sold -> blocked -> available
                $curStmt = $db->prepare("SELECT status FROM event_seats WHERE seat_code = :c LIMIT 1");
                $curStmt->execute([':c' => $seatCode]);
                $cur = $curStmt->fetchColumn();
                $newStatus = ($cur === 'available') ? 'sold' : (($cur === 'sold') ? 'blocked' : 'available');
            }

            $upd = $db->prepare("UPDATE event_seats SET status = :st, updated_at = NOW() WHERE seat_code = :c");
            $upd->execute([':st' => $newStatus, ':c' => $seatCode]);

            $summary = get_seat_summary(1);
            echo json_encode([
                'success' => true,
                'seat_code' => $seatCode,
                'status' => $newStatus,
                'summary' => $summary
            ]);
            exit;
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'error' => 'Invalid seat code']);
    exit;
}

// Handle Form POST Actions (Bulk row action, Create ticket by UTR, Reset)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session token expired.');
        header("Location: " . BASE_URL . "/admin/seats.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Action: Create Ticket By UTR / Reference ID
    if ($action === 'create_ticket_utr') {
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
                set_flash('success', "Ticket pass issued for {$name}! Booking UID: #{$bookingUid} (UTR: {$utr}) &bull; Seats: {$assignedSeats}. <a href='" . BASE_URL . "/ticket.php?uid={$bookingUid}' target='_blank' style='color:#fff; text-decoration:underline; font-weight:bold;'>View Pass &rarr;</a>");
            } else {
                $db->rollBack();
                set_flash('error', "Category not found.");
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[admin/seats.php create_ticket_utr error]: ' . $e->getMessage());
            set_flash('error', "Error issuing ticket: " . $e->getMessage());
        }
    }

    // Action: Bulk Row Update
    elseif ($action === 'update_row') {
        $rowName = trim($_POST['row_name'] ?? '');
        $status = $_POST['status'] ?? 'available';
        if (!empty($rowName) && in_array($status, ['available', 'sold', 'blocked'])) {
            try {
                $stmt = $db->prepare("UPDATE event_seats SET status = :st WHERE row_name = :r");
                $stmt->execute([':st' => $status, ':r' => $rowName]);
                set_flash('success', "Row {$rowName} marked as " . strtoupper($status) . ".");
            } catch (Throwable $e) {
                set_flash('error', $e->getMessage());
            }
        }
    }

    header("Location: " . BASE_URL . "/admin/seats.php");
    exit;
}

// Fetch seats & stats
$allSeats = get_all_seats(1);
$summary = get_seat_summary(1);
$categories = get_event_categories(1, true);

// Group seats by tier and row
$tieredRows = [];
foreach ($allSeats as $s) {
    $tier = $s['category_name'] . ' (' . format_inr($s['tier_price']) . ')';
    $rName = $s['row_name'];
    $tieredRows[$tier][$rName][] = $s;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seat Matrix &amp; Khali/Booked Manager | Millennium Admin</title>
    <link rel="stylesheet" href="<?= asset_v('assets/css/admin.css') ?>">
    <style>
        .seat-admin-box {
            width: 38px;
            height: 36px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.78rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.15s ease;
            user-select: none;
            margin: 3px;
        }
        .seat-admin-box.available {
            background: rgba(16, 185, 129, 0.15);
            border: 2px solid #10b981;
            color: #34d399;
        }
        .seat-admin-box.available:hover {
            background: #10b981;
            color: #fff;
            transform: scale(1.1);
        }
        .seat-admin-box.sold {
            background: #2e344a;
            border: 2px solid #4b5563;
            color: #9ca3af;
        }
        .seat-admin-box.sold:hover {
            background: #ef4444;
            color: #fff;
            border-color: #ef4444;
        }
        .seat-admin-box.blocked {
            background: rgba(239, 68, 68, 0.2);
            border: 2px solid #ef4444;
            color: #f87171;
        }
        .seat-admin-row {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        .seat-admin-row-label {
            width: 70px;
            font-weight: 800;
            color: #f5b722;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-header">
            <div>
                <h1 class="admin-title">&#129681; Real-Time Seat Matrix &amp; Khali/Booked Layout</h1>
                <div class="admin-subtitle">Click ANY seat below to toggle between Available (Khali) &amp; Sold (Booked)</div>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="btn-bms-gold" onclick="openUtrModal()">
                    <span>&#127915;</span> <span>Issue Ticket by UTR / Ref ID</span>
                </button>
                <a href="<?= BASE_URL ?>/booking.php" target="_blank" class="btn-bms-dark">
                    <span>&#127760;</span> <span>View Live Seat Map &rarr;</span>
                </a>
            </div>
        </div>

        <?= render_flash() ?>

        <!-- Seat KPI Summary Strip -->
        <div class="bms-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
            <div class="bms-kpi-card">
                <div class="bms-kpi-header">
                    <span class="bms-kpi-title">Total Arena Seats</span>
                    <div class="bms-kpi-icon" style="background:rgba(59,130,246,0.15); color:#60a5fa;">&#129681;</div>
                </div>
                <div class="bms-kpi-value" id="kpi-total"><?= $summary['total'] ?></div>
                <div class="bms-kpi-sub">Grand Arena Capacity</div>
            </div>

            <div class="bms-kpi-card">
                <div class="bms-kpi-header">
                    <span class="bms-kpi-title">Seats Khali (Available)</span>
                    <div class="bms-kpi-icon" style="background:rgba(16,185,129,0.15); color:#34d399;">&#9989;</div>
                </div>
                <div class="bms-kpi-value" style="color: #34d399;" id="kpi-avail"><?= $summary['available'] ?></div>
                <div class="bms-kpi-sub">Online for Booking</div>
            </div>

            <div class="bms-kpi-card">
                <div class="bms-kpi-header">
                    <span class="bms-kpi-title">Booked / Sold Seats</span>
                    <div class="bms-kpi-icon" style="background:rgba(245,183,34,0.15); color:#f5b722;">&#127915;</div>
                </div>
                <div class="bms-kpi-value" style="color: #ffd966;" id="kpi-sold"><?= $summary['sold'] ?></div>
                <div class="bms-kpi-sub">Occupancy: <strong id="kpi-pct"><?= $summary['occupancy_pct'] ?>%</strong></div>
            </div>

            <div class="bms-kpi-card">
                <div class="bms-kpi-header">
                    <span class="bms-kpi-title">Blocked / VIP</span>
                    <div class="bms-kpi-icon" style="background:rgba(239,68,68,0.15); color:#f87171;">&#128274;</div>
                </div>
                <div class="bms-kpi-value" style="color: #f87171;" id="kpi-blocked"><?= $summary['blocked'] ?></div>
                <div class="bms-kpi-sub">Reserved by Management</div>
            </div>
        </div>

        <!-- Interactive Admin Seat Matrix View -->
        <div class="admin-card" style="padding: 26px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h3 style="color: #fff; font-size: 1.2rem; font-weight: 800; margin-bottom: 4px;">
                        Interactive Arena Stage &amp; Seat Layout
                    </h3>
                    <div style="font-size: 0.84rem; color: #94a3b8;">
                        Clicking any seat instantly toggles it between <strong>Available (Green)</strong>, <strong>Sold (Gray)</strong>, and <strong>Blocked (Red)</strong>.
                    </div>
                </div>

                <!-- Legend -->
                <div style="display: flex; gap: 16px; align-items: center; font-size: 0.85rem; font-weight: 700;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span class="seat-admin-box available" style="width:20px; height:20px; font-size:9px;"></span>
                        <span style="color:#34d399;">Khali (Available)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span class="seat-admin-box sold" style="width:20px; height:20px; font-size:9px;"></span>
                        <span style="color:#9ca3af;">Booked (Sold)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span class="seat-admin-box blocked" style="width:20px; height:20px; font-size:9px;"></span>
                        <span style="color:#f87171;">Blocked (VIP)</span>
                    </div>
                </div>
            </div>

            <!-- Virtual Stage Screen -->
            <div style="text-align: center; margin: 15px auto 30px; max-width: 650px;">
                <div style="height: 6px; background: linear-gradient(90deg, #f5b722, #d97706); border-radius: 20px; box-shadow: 0 0 14px rgba(245,183,34,0.6);"></div>
                <div style="font-size: 0.72rem; color: #94a3b8; text-transform: uppercase; font-weight: 800; margin-top: 6px; letter-spacing: 2px;">
                    &#9733; GARBA DANDIYA CENTRAL PERFORMANCE STAGE &#9733;
                </div>
            </div>

            <!-- Tiered Seat Rows -->
            <div style="overflow-x: auto; padding-bottom: 20px;">
                <?php foreach ($tieredRows as $tierLabel => $rows): ?>
                    <div style="margin-bottom: 24px; background: rgba(18, 20, 29, 0.6); padding: 16px 20px; border-radius: 12px; border: 1px solid #282f45;">
                        <div style="font-size: 0.95rem; font-weight: 800; color: #ffd966; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
                            <span><?= e($tierLabel) ?></span>
                        </div>

                        <?php foreach ($rows as $rName => $seats): ?>
                            <div class="seat-admin-row">
                                <div class="seat-admin-row-label">Row <?= e($rName) ?>:</div>
                                <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                    <?php foreach ($seats as $idx => $st): ?>
                                        <?php if ($idx === 5): ?>
                                            <div style="width: 20px;"></div> <!-- Center walkway gap -->
                                        <?php endif; ?>
                                        <div class="seat-admin-box <?= e($st['status']) ?>" 
                                             id="seat-<?= e($st['seat_code']) ?>"
                                             data-code="<?= e($st['seat_code']) ?>"
                                             data-status="<?= e($st['status']) ?>"
                                             onclick="toggleSeat('<?= e($st['seat_code']) ?>')"
                                             title="Seat <?= e($st['seat_code']) ?> &bull; Click to toggle status">
                                            <?= str_pad($st['seat_num'], 2, '0', STR_PAD_LEFT) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
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
            Enter the customer's UPI Reference ID / UTR to generate an official digital ticket and auto-assign seats.
        </p>

        <form action="<?= BASE_URL ?>/admin/seats.php" method="POST">
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
                        <?php foreach ($categories as $c): ?>
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

<script>
function toggleSeat(code) {
    const el = document.getElementById('seat-' + code);
    if (!el) return;

    fetch('<?= BASE_URL ?>/admin/seats.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=toggle_seat_ajax&seat_code=' + encodeURIComponent(code)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            el.className = 'seat-admin-box ' + data.status;
            el.setAttribute('data-status', data.status);
            // Update KPIs
            if (data.summary) {
                document.getElementById('kpi-total').innerText = data.summary.total;
                document.getElementById('kpi-avail').innerText = data.summary.available;
                document.getElementById('kpi-sold').innerText = data.summary.sold;
                document.getElementById('kpi-blocked').innerText = data.summary.blocked;
                document.getElementById('kpi-pct').innerText = data.summary.occupancy_pct + '%';
            }
        } else {
            alert('Error updating seat: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error updating seat.');
    });
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
