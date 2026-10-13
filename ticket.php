<?php
/**
 * ticket.php - Digital E-Ticket, Payment Receipt & Search Console
 * BookMyShow-style Instant Pass Generation & Verification
 * Millennium India Group - Jashn-E-Garba 2026
 */
ob_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

send_no_cache_headers();

$db = getDB();

// Handle direct UTR submission from ticket page (if user was pending)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_utr_ticket') {
    $submitUid = trim($_POST['booking_uid'] ?? '');
    $utr = preg_replace('/[^A-Za-z0-9]/', '', trim($_POST['transaction_ref'] ?? ''));
    if (strlen($utr) >= 6 && !empty($submitUid)) {
        try {
            $upd = $db->prepare("UPDATE bookings SET transaction_ref = :ref, payment_status = 'verified' WHERE booking_uid = :uid");
            $upd->execute([':ref' => $utr, ':uid' => $submitUid]);

            // Deduct category inventory atomically
            $selB = $db->prepare("SELECT id FROM bookings WHERE booking_uid = :uid");
            $selB->execute([':uid' => $submitUid]);
            $bId = $selB->fetchColumn();
            if ($bId) {
                $itemStmt = $db->prepare("SELECT ticket_category_id, quantity FROM booking_items WHERE booking_id = :id");
                $itemStmt->execute([':id' => $bId]);
                $bItems = $itemStmt->fetchAll();
                foreach ($bItems as $it) {
                    $updStock = $db->prepare("UPDATE ticket_categories SET available_quantity = GREATEST(0, available_quantity - :qty) WHERE id = :cid");
                    $updStock->execute([':qty' => (int)$it['quantity'], ':cid' => (int)$it['ticket_category_id']]);
                }
            }
            set_flash('success', 'Payment verified successfully! Your digital gate pass and receipt are ready.');
            header("Location: " . BASE_URL . "/ticket.php?uid=" . urlencode($submitUid) . "&verified=1");
            exit;
        } catch (PDOException $e) {
            error_log('[ticket.php UTR submit error]: ' . $e->getMessage());
        }
    } else {
        set_flash('error', 'Please enter a valid 12-digit UPI UTR reference number.');
        header("Location: " . BASE_URL . "/ticket.php?uid=" . urlencode($submitUid));
        exit;
    }
}

// Handle instant claim/activation of a direct UPI payment by UTR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_utr_ticket') {
    $custName = trim($_POST['customer_name'] ?? '');
    $custPhone = preg_replace('/[^\d]/', '', trim($_POST['customer_phone'] ?? ''));
    $catId = (int)($_POST['category_id'] ?? 1);
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    $utr = preg_replace('/[^A-Za-z0-9]/', '', trim($_POST['transaction_ref'] ?? ''));

    if (empty($custName)) {
        $custName = 'Garba Attendee';
    }
    if (empty($custPhone) || strlen($custPhone) < 10) {
        $custPhone = '7004665804';
    }
    if (strlen($utr) < 6) {
        set_flash('error', 'Please enter a valid 12-digit UPI UTR / Transaction Reference number.');
        header("Location: " . BASE_URL . "/ticket.php");
        exit;
    }

    try {
        $catStmt = $db->prepare("SELECT * FROM ticket_categories WHERE id = :id LIMIT 1");
        $catStmt->execute([':id' => $catId]);
        $cat = $catStmt->fetch();
        if (!$cat) {
            $catStmt = $db->query("SELECT * FROM ticket_categories WHERE status = 'active' ORDER BY price ASC LIMIT 1");
            $cat = $catStmt->fetch();
        }

        $totalAmount = (float)$cat['price'] * $qty;
        $newUid = generate_booking_uid();
        $qrToken = generate_qr_token();

        // Auto-assign available seats
        $assignedSeats = '';
        try {
            $seatStmt = $db->prepare("SELECT seat_code FROM event_seats WHERE category_id = :cid AND status = 'available' LIMIT :lim");
            $seatStmt->bindValue(':cid', (int)$cat['id'], PDO::PARAM_INT);
            $seatStmt->bindValue(':lim', $qty, PDO::PARAM_INT);
            $seatStmt->execute();
            $picked = $seatStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $assignedSeats = implode(', ', $picked);
        } catch (Throwable $se) {}

        $insB = $db->prepare("
            INSERT INTO bookings (
                booking_uid, event_id, customer_name, customer_phone, customer_email,
                total_amount, payment_method, payment_status, transaction_ref, qr_token,
                seat_numbers
            ) VALUES (
                :uid, :e_id, :name, :phone, :email,
                :total, 'direct_upi', 'verified', :utr, :qr_token,
                :seats
            )
        ");
        $insB->execute([
            ':uid'      => $newUid,
            ':e_id'     => (int)$cat['event_id'],
            ':name'     => $custName,
            ':phone'    => $custPhone,
            ':email'    => $custPhone . '@millenniumindiagroup.com',
            ':total'    => $totalAmount,
            ':utr'      => $utr,
            ':qr_token' => $qrToken,
            ':seats'    => $assignedSeats
        ]);
        $newBookingId = $db->lastInsertId();

        $insItem = $db->prepare("
            INSERT INTO booking_items (booking_id, ticket_category_id, quantity, unit_price, subtotal)
            VALUES (:b_id, :c_id, :qty, :price, :subtotal)
        ");
        $insItem->execute([
            ':b_id'     => $newBookingId,
            ':c_id'     => (int)$cat['id'],
            ':qty'      => $qty,
            ':price'    => (float)$cat['price'],
            ':subtotal' => $totalAmount
        ]);

        // Mark assigned seats as SOLD
        if (!empty($assignedSeats)) {
            $seatArr = array_map('trim', explode(',', $assignedSeats));
            $updSeat = $db->prepare("UPDATE event_seats SET status = 'sold', booking_uid = :uid WHERE seat_code = :code");
            foreach ($seatArr as $sCode) {
                $updSeat->execute([':uid' => $newUid, ':code' => $sCode]);
            }
        }

        // Deduct inventory
        $db->prepare("UPDATE ticket_categories SET available_quantity = GREATEST(0, available_quantity - :q) WHERE id = :c_id")
           ->execute([':q' => $qty, ':c_id' => (int)$cat['id']]);

        set_flash('success', 'Pass generated successfully! Your official digital gate pass and tax receipt are ready below.');
        header("Location: " . BASE_URL . "/ticket.php?uid=" . urlencode($newUid) . "&verified=1");
        exit;
    } catch (PDOException $e) {
        error_log('[ticket.php claim_utr_ticket error]: ' . $e->getMessage());
        set_flash('error', 'Database error generating ticket: ' . $e->getMessage());
        header("Location: " . BASE_URL . "/ticket.php");
        exit;
    }
}

$uid = trim($_GET['uid'] ?? '');
$searchQuery = trim($_GET['search'] ?? $_GET['query'] ?? $_GET['utr'] ?? $_GET['ref'] ?? $_GET['q'] ?? '');
$booking = null;
$items = [];
$totalPersons = 0;
$ticketTypeSummary = '';
$hasSearched = (!empty($uid) || !empty($searchQuery));

try {
    if (!empty($uid)) {
        $stmt = $db->prepare("
            SELECT b.*, e.title AS event_title, e.date AS event_date, e.venue AS event_venue, e.start_time, e.end_time
            FROM bookings b
            LEFT JOIN events e ON b.event_id = e.id
            WHERE b.booking_uid = :uid
            ORDER BY b.id DESC LIMIT 1
        ");
        $stmt->execute([':uid' => $uid]);
        $booking = $stmt->fetch();
    } elseif (!empty($searchQuery)) {
        $rawSearch = trim($searchQuery);
        $extractedDigits = preg_replace('/[^\d]/', '', $rawSearch);
        $extractedUid = '';
        if (preg_match('/(MIG\d{8}[A-Za-z0-9]+)/i', $rawSearch, $mUid)) {
            $extractedUid = strtoupper($mUid[1]);
        }

        $sql = "
            SELECT b.*, e.title AS event_title, e.date AS event_date, e.venue AS event_venue, e.start_time, e.end_time
            FROM bookings b
            LEFT JOIN events e ON b.event_id = e.id
            WHERE b.booking_uid = :q_raw
               OR b.booking_uid LIKE :q_like
               OR b.transaction_ref = :q_raw
               OR b.transaction_ref LIKE :q_like
               OR b.customer_phone = :q_raw
        ";
        $params = [
            ':q_raw'  => $rawSearch,
            ':q_like' => "%$rawSearch%"
        ];

        if (!empty($extractedUid)) {
            $sql .= " OR b.booking_uid = :q_uid";
            $params[':q_uid'] = $extractedUid;
        }

        if (!empty($extractedDigits) && strlen($extractedDigits) >= 6) {
            $sql .= " OR b.transaction_ref LIKE :q_dig OR REPLACE(b.transaction_ref, 'UTR-', '') LIKE :q_dig2";
            $params[':q_dig']  = "%$extractedDigits%";
            $params[':q_dig2'] = "%$extractedDigits%";
        }

        if (!empty($extractedDigits) && strlen($extractedDigits) >= 10) {
            $last10 = substr($extractedDigits, -10);
            $sql .= " OR b.customer_phone LIKE :q_p10 OR REPLACE(REPLACE(b.customer_phone, ' ', ''), '+91', '') LIKE :q_p10b";
            $params[':q_p10']  = "%$last10%";
            $params[':q_p10b'] = "%$last10%";
        }

        $sql .= " ORDER BY b.id DESC LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $booking = $stmt->fetch();
    }

    if ($booking) {
        // Auto-heal: If booking has a transaction_ref, ensure verified in DB
        if (!empty($booking['transaction_ref']) && $booking['payment_status'] !== 'verified') {
            try {
                $db->prepare("UPDATE bookings SET payment_status = 'verified' WHERE id = :id")->execute([':id' => (int)$booking['id']]);
                $booking['payment_status'] = 'verified';
            } catch (PDOException $ex) {}
        }

        $itemStmt = $db->prepare("
            SELECT bi.*, tc.name AS category_name, tc.people_allowed
            FROM booking_items bi
            JOIN ticket_categories tc ON bi.ticket_category_id = tc.id
            WHERE bi.booking_id = :b_id
        ");
        $itemStmt->execute([':b_id' => (int)$booking['id']]);
        $items = $itemStmt->fetchAll() ?: [];

        $typeArr = [];
        foreach ($items as $it) {
            $totalPersons += ((int)$it['quantity'] * (int)$it['people_allowed']);
            $typeArr[] = e($it['category_name']) . ($it['quantity'] > 1 ? " &times; " . (int)$it['quantity'] : '');
        }
        $ticketTypeSummary = implode(', ', $typeArr);
    }
} catch (PDOException $e) {
    error_log('[ticket.php error]: ' . $e->getMessage());
    $booking = null;
}

$isVerified = ($booking && ($booking['payment_status'] === 'verified' || !empty($booking['transaction_ref'])));
$qrPayload = '';
if ($booking) {
    $qrPayload = json_encode([
        'uid'   => $booking['booking_uid'],
        'token' => $booking['qr_token']
    ]);
}

$pageTitle = $booking ? ('Pass: ' . e($booking['booking_uid']) . ' | Millennium India Group') : 'Search Ticket / Receipt | Millennium India Group';
$currentNav = 'find_ticket';
$extraCss = 'ticket.css';

include __DIR__ . '/includes/header.php';
?>

<div class="ticket-page-container">

    <!-- SEARCH BAR CARD (Always available for attendees to search) -->
    <div class="festive-card no-print" style="border: 1.5px solid var(--gold-accent); padding: 24px 22px; margin-bottom: 30px; box-shadow: 0 8px 30px rgba(0,0,0,0.5);">
        <div style="text-align: center; margin-bottom: 16px;">
            <span style="background: rgba(245,183,34,0.15); color: var(--gold-accent); font-weight: 800; font-size: 0.76rem; padding: 3px 14px; border-radius: 20px; text-transform: uppercase;">
                &#128269; Pass &amp; Receipt Lookup
            </span>
            <h2 style="font-size: 1.6rem; color: #fff; margin: 8px 0 4px; font-weight: 900;">Find Your Booking Receipt &amp; Pass</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">
                Enter your <strong>Booking ID</strong> (e.g. <code>MIG2026...</code>) or <strong>10-digit Mobile Number</strong>
            </p>
        </div>

        <form action="<?= BASE_URL ?>/ticket.php" method="GET" style="max-width: 580px; margin: 0 auto; display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="text" 
                   name="search" 
                   placeholder="Enter Booking ID or 10-Digit Mobile..." 
                   value="<?= e($searchQuery ?: ($booking['booking_uid'] ?? '')) ?>" 
                   required
                   style="flex: 1; min-width: 240px; padding: 13px 16px; font-size: 1.05rem; border-radius: 8px; background: #141724; border: 1.5px solid var(--gold-accent); color: #fff; box-sizing: border-box;">
            <button type="submit" class="btn btn-primary" style="padding: 13px 24px; font-weight: 800; font-size: 1rem; border-radius: 8px; cursor: pointer;">
                &#128269; Search Pass &rarr;
            </button>
        </form>
    </div>

    <?php if ($hasSearched && !$booking): ?>
        <?php 
        $cleanSearchDigits = preg_replace('/[^\d]/', '', $searchQuery);
        $isUtrCandidate = (strlen($cleanSearchDigits) >= 6 && strlen($cleanSearchDigits) <= 25 && !preg_match('/^[6-9]\d{9}$/', trim($searchQuery)));
        ?>
        <?php if ($isUtrCandidate): ?>
            <!-- DIRECT UPI UTR INSTANT PASS ACTIVATION CARD -->
            <div class="festive-card" style="border: 2px solid var(--gold-accent); padding: 32px 24px; max-width: 660px; margin: 20px auto 35px; box-shadow: 0 10px 35px rgba(0,0,0,0.6);">
                <div style="text-align: center; margin-bottom: 20px;">
                    <span style="background: rgba(16,185,129,0.18); color: #10b981; font-weight: 800; font-size: 0.78rem; padding: 4px 14px; border-radius: 20px; text-transform: uppercase;">
                        &#9889; Direct UPI Payment Activation
                    </span>
                    <h3 style="color: #fff; font-size: 1.5rem; font-weight: 900; margin: 12px 0 6px;">
                        Generate Pass for Ref / UTR: <span style="font-family: monospace; color: var(--gold-accent);"><?= e($searchQuery) ?></span>
                    </h3>
                    <p style="color: #cbd5e1; font-size: 0.92rem; max-width: 520px; margin: 0 auto; line-height: 1.6;">
                        Paid directly to UPI ID <code style="color:var(--gold-accent);">chandni0984@oksbi</code>? Complete your name &amp; phone below to generate your official Gate Pass &amp; Tax Receipt immediately:
                    </p>
                </div>

                <form action="<?= BASE_URL ?>/ticket.php" method="POST" style="max-width: 460px; margin: 0 auto; text-align: left;">
                    <input type="hidden" name="action" value="claim_utr_ticket">
                    <input type="hidden" name="transaction_ref" value="<?= e($searchQuery) ?>">

                    <div style="margin-bottom: 14px;">
                        <label style="font-size: 0.85rem; color: #fff; font-weight: 700; display: block; margin-bottom: 6px;">Attendee Full Name *</label>
                        <input type="text" name="customer_name" placeholder="e.g. Rahul Kumar" required style="width: 100%; padding: 12px 14px; font-size: 1rem; border-radius: 8px; background: #0a0106; border: 1.5px solid var(--gold-accent); color: #fff; box-sizing: border-box;">
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="font-size: 0.85rem; color: #fff; font-weight: 700; display: block; margin-bottom: 6px;">10-Digit Mobile Number *</label>
                        <input type="tel" name="customer_phone" placeholder="e.g. 9876543210" maxlength="10" required style="width: 100%; padding: 12px 14px; font-size: 1rem; border-radius: 8px; background: #0a0106; border: 1.5px solid var(--gold-accent); color: #fff; box-sizing: border-box;">
                    </div>

                    <div style="margin-bottom: 18px;">
                        <label style="font-size: 0.85rem; color: #fff; font-weight: 700; display: block; margin-bottom: 6px;">Select Pass Category *</label>
                        <select name="category_id" style="width: 100%; padding: 12px 14px; font-size: 1rem; border-radius: 8px; background: #0a0106; border: 1.5px solid var(--gold-accent); color: #fff; box-sizing: border-box;">
                            <option value="1">Single Pass (No Food) - ₹199</option>
                            <option value="2">Couple Pass (No Food) - ₹299</option>
                            <option value="3">Single Pass (With Food) - ₹399</option>
                            <option value="4">Couple Pass (With Food) - ₹699</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" style="width: 100%; padding: 14px; font-weight: 800; font-size: 1.05rem; border-radius: 8px; cursor: pointer;">
                        &#10004; Generate My Pass &amp; Tax Receipt &rarr;
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- SEARCH NOT FOUND ALERT -->
            <div class="festive-card" style="text-align: center; border: 2px dashed #f87171; padding: 35px 25px; margin-bottom: 30px;">
                <div style="font-size: 3rem; color: #f87171; margin-bottom: 10px;">&#128680;</div>
                <h3 style="color: #fff; font-size: 1.5rem; margin-bottom: 8px;">No Booking Found</h3>
                <p style="color: #cbd5e1; font-size: 0.95rem; max-width: 540px; margin: 0 auto 20px; line-height: 1.6;">
                    We could not find any booking registered under <strong>"<?= e($searchQuery ?: $uid) ?>"</strong>.<br>
                    Please ensure you entered the exact 10-digit mobile number used while booking, or your Booking Reference ID.
                </p>
                <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                    <a href="<?= BASE_URL ?>/ticket.php" class="btn btn-secondary">Try Another Search</a>
                    <a href="<?= BASE_URL ?>/booking.php?event_id=1" class="btn btn-primary">&#127915; Book Passes Now (From ₹199)</a>
                </div>
            </div>
        <?php endif; ?>

    <?php elseif (!$hasSearched && !$booking): ?>
        <!-- FIRST VISIT DEFAULT VIEW: INSTANT PASS & RECEIPT DOWNLOAD GUIDE -->
        <div class="festive-card" style="max-width: 760px; margin: 20px auto; text-align: center; border: 2px solid var(--gold-accent); padding: 35px 25px;">
            <div style="width: 70px; height: 70px; background: rgba(245,183,34,0.15); border: 2px solid var(--gold-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 2.2rem; color: var(--gold-accent);">
                &#127915;
            </div>
            <span style="background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1.5px solid #10b981; font-size: 0.8rem; font-weight: 800; padding: 4px 14px; border-radius: 50px; text-transform: uppercase; letter-spacing: 0.5px;">
                Instant Pass &amp; Tax Invoice Retrieval
            </span>
            <h2 class="card-title" style="justify-content: center; margin-top: 14px; font-size: 1.8rem;">How to Download Your Pass &amp; Receipt</h2>
            
            <p style="color: #cbd5e1; font-size: 0.95rem; max-width: 580px; margin: 0 auto 24px; line-height: 1.6;">
                Every booking made with UPI ID (<code style="color:var(--gold-accent);">chandni0984@oksbi</code>) is stored securely in our official database. Enter your details in the search box above to access your pass.
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 25px; text-align: left;">
                <div style="background: rgba(20,2,11,0.9); border: 1px solid rgba(245,183,34,0.3); border-radius: 10px; padding: 18px 16px;">
                    <span style="color: var(--gold-accent); font-weight: 900; font-size: 1.1rem; display: block; margin-bottom: 6px;">Step 1: Enter ID</span>
                    <p style="font-size: 0.86rem; color: #cbd5e1; margin: 0; line-height: 1.5;">
                        Type your <strong>10-digit mobile number</strong>, <strong>Booking ID</strong> (e.g. <code>MIG2026...</code>), or <strong>UPI UTR</strong>.
                    </p>
                </div>
                <div style="background: rgba(20,2,11,0.9); border: 1px solid rgba(245,183,34,0.3); border-radius: 10px; padding: 18px 16px;">
                    <span style="color: var(--gold-accent); font-weight: 900; font-size: 1.1rem; display: block; margin-bottom: 6px;">Step 2: Instant QR Pass</span>
                    <p style="font-size: 0.86rem; color: #cbd5e1; margin: 0; line-height: 1.5;">
                        Your BookMyShow-style boarding pass with active Gate Entry QR code opens immediately.
                    </p>
                </div>
                <div style="background: rgba(20,2,11,0.9); border: 1px solid rgba(245,183,34,0.3); border-radius: 10px; padding: 18px 16px;">
                    <span style="color: var(--gold-accent); font-weight: 900; font-size: 1.1rem; display: block; margin-bottom: 6px;">Step 3: Save Receipt</span>
                    <p style="font-size: 0.86rem; color: #cbd5e1; margin: 0; line-height: 1.5;">
                        Click <strong>Print / Save PDF</strong> to keep your official Tax Invoice and gate pass on your phone.
                    </p>
                </div>
            </div>

            <!-- Quick Direct Search Input inside card -->
            <form action="<?= BASE_URL ?>/ticket.php" method="GET" style="max-width: 520px; margin: 0 auto 20px; display: flex; gap: 8px; flex-wrap: wrap;">
                <input type="text" 
                       name="search" 
                       placeholder="Enter Mobile or Booking ID..." 
                       required
                       style="flex: 1; min-width: 200px; padding: 12px 14px; font-size: 1rem; border-radius: 8px; background: #0a0106; border: 1.5px solid var(--gold-accent); color: #fff; box-sizing: border-box;">
                <button type="submit" class="btn btn-primary" style="padding: 12px 22px; font-weight: 800; font-size: 0.95rem; border-radius: 8px; cursor: pointer;">
                    &#128269; Get Pass &rarr;
                </button>
            </form>

            <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap;">
                <a href="<?= BASE_URL ?>/booking.php?event_id=1" class="btn btn-secondary" style="padding: 12px 24px; font-weight: 800; font-size: 0.95rem;">
                    &#127915; Book Passes (From ₹199)
                </a>
                <a href="https://wa.me/917004665804" target="_blank" class="btn btn-secondary" style="padding: 12px 20px; font-weight: 700; font-size: 0.95rem;">
                    &#128222; Helpline: +91 7004665804
                </a>
            </div>
        </div>

    <?php elseif (!$isVerified): ?>
        <!-- PENDING STATE: PROVIDE IMMEDIATE FORM TO UNLOCK PASS VIA UTR -->
        <div class="festive-card" style="border: 2px solid var(--gold-accent); padding: 35px 25px; max-width: 760px; margin: 20px auto;">
            
            <div style="text-align: center; margin-bottom: 25px;">
                <div style="font-size: 3.5rem; color: var(--gold-accent); margin-bottom: 10px;">&#9203;</div>
                <span class="badge badge-pending" style="font-size: 0.95rem; padding: 6px 18px; letter-spacing: 0.5px;">
                    ACTION REQUIRED: ENTER UTR TO UNLOCK PASS
                </span>
                <h2 style="font-size: 1.85rem; color: #fff; margin: 14px 0 6px; font-weight: 900;">
                    Complete Payment &amp; Get Pass
                </h2>
                <div style="color: var(--gold-light); font-family: monospace; font-size: 1.15rem; font-weight: 800;">
                    Booking Reference ID: <?= e($booking['booking_uid']) ?>
                </div>
            </div>

            <!-- Receipt Breakdown Table -->
            <div style="background: rgba(20,2,11,0.95); border: 1.5px solid rgba(245,183,34,0.3); border-radius: 12px; padding: 22px; margin-bottom: 25px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 16px; border-bottom: 1px solid rgba(245,183,34,0.2); padding-bottom: 16px;">
                    <div>
                        <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Attendee Name</span>
                        <div style="font-weight: 800; font-size: 1.1rem; color: #fff;"><?= e($booking['customer_name']) ?></div>
                    </div>
                    <div>
                        <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Registered Mobile</span>
                        <div style="font-weight: 800; font-size: 1.1rem; color: #fff;"><?= e($booking['customer_phone']) ?></div>
                    </div>
                    <div>
                        <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Passes Booked</span>
                        <div style="font-weight: 800; font-size: 1.1rem; color: #ffd966;"><?= $ticketTypeSummary ?: 'Standard Entry' ?> (Total <?= $totalPersons ?> Person<?= $totalPersons > 1 ? 's' : '' ?>)</div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1.5px dashed rgba(245,183,34,0.35); padding-top: 14px;">
                    <span style="font-size: 1rem; color: #fff; font-weight: 700;">Payable Amount:</span>
                    <span style="font-size: 1.6rem; color: var(--gold-accent); font-weight: 900;"><?= format_inr($booking['total_amount']) ?></span>
                </div>
            </div>

            <!-- Instant UTR Submission Form -->
            <div style="background: #ffffff; border-radius: 12px; padding: 24px; margin-bottom: 25px; color: #111;">
                <h4 style="margin: 0 0 8px; color: #7a1244; font-size: 1.15rem; font-weight: 900;">
                    &#9889; Already Paid via UPI? Unlock Pass Instantly:
                </h4>
                <p style="margin: 0 0 16px; font-size: 0.9rem; color: #475569;">
                    If you transferred to UPI ID <strong>chandni0984@oksbi</strong>, enter your 12-digit UTR / Reference ID below to unlock your Digital QR Pass immediately:
                </p>

                <form action="<?= BASE_URL ?>/ticket.php" method="POST">
                    <input type="hidden" name="action" value="submit_utr_ticket">
                    <input type="hidden" name="booking_uid" value="<?= e($booking['booking_uid']) ?>">
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <input type="text" 
                               name="transaction_ref" 
                               placeholder="Enter 12-digit UPI UTR / Ref ID..." 
                               required 
                               maxlength="35"
                               style="flex: 1; min-width: 240px; padding: 13px 16px; font-size: 1.1rem; font-family: monospace; border: 2px solid #7a1244; border-radius: 8px;">
                        <button type="submit" class="btn btn-primary" style="padding: 13px 24px; font-weight: 800; font-size: 1rem; background: #7a1244; border-color: #7a1244;">
                            &#10004; Unlock Pass Now &rarr;
                        </button>
                    </div>
                </form>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap;">
                <a href="<?= BASE_URL ?>/payment.php" class="btn btn-secondary" style="padding: 12px 22px; font-weight: 700;">
                    &#128241; Pay via UPI App
                </a>
                <a href="https://wa.me/917004665804?text=<?= urlencode("Hello Millennium India Group, I am booking for ID " . $booking['booking_uid'] . " (Amount: " . format_inr($booking['total_amount']) . "). Please guide me.") ?>" target="_blank" class="btn btn-primary" style="background:#25d366; color:#fff; border-color:#25d366; font-weight: 800; padding: 12px 24px;">
                    &#128172; Helpline (+91 7004665804)
                </a>
            </div>

        </div>

    <?php else: ?>
        <!-- ========================================== -->
        <!-- OFFICIAL VERIFIED DIGITAL PASS & RECEIPT   -->
        <!-- (BookMyShow-Style Instant Pass Generation) -->
        <!-- ========================================== -->

        <!-- Success Toast Banner & Reference Number Highlight (BookMyShow-Style) -->
        <div class="no-print" style="background: linear-gradient(135deg, #065f46, #047857); border: 1.5px solid #10b981; border-radius: 12px; padding: 18px 22px; margin-bottom: 22px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; box-shadow: 0 6px 20px rgba(16,185,129,0.35);">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 44px; height: 44px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #fff;">
                    &#10004;
                </div>
                <div>
                    <div style="font-size: 0.78rem; text-transform: uppercase; color: #a7f3d0; font-weight: 800; letter-spacing: 0.8px;">
                        BOOKING CONFIRMED &bull; PASS ACTIVE
                    </div>
                    <div style="color: #fff; font-size: 1.25rem; font-weight: 900; margin: 2px 0;">
                        Booking Reference: <span style="font-family: monospace; color: #ffd966;"><?= e($booking['booking_uid']) ?></span>
                    </div>
                    <div style="color: #d1fae5; font-size: 0.88rem;">
                        Payment Verified &bull; Transaction / UTR: <strong style="font-family: monospace; color: #fff;"><?= e($booking['transaction_ref'] ?: 'Direct UPI') ?></strong>
                    </div>
                </div>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" onclick="copyBookingId('<?= e($booking['booking_uid']) ?>')" class="btn" style="background: rgba(255,255,255,0.2); color: #fff; font-weight: 800; font-size: 0.85rem; padding: 8px 14px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.4); cursor: pointer;">
                    &#128203; Copy Booking ID
                </button>
                <button type="button" onclick="window.print()" class="btn" style="background: #fff; color: #065f46; font-weight: 900; font-size: 0.88rem; padding: 8px 18px; border-radius: 6px; border: none; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.2);">
                    &#128424; Print Pass &amp; Receipt
                </button>
            </div>
        </div>

        <!-- Action Buttons Bar (Hidden on Print) -->
        <div class="ticket-actions-bar">
            <div>
                <a href="<?= BASE_URL ?>/index.php" style="color: var(--gold-light); font-weight: 600; font-size: 0.9rem;">
                    &larr; Back to Events
                </a>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" onclick="window.print()" class="btn btn-primary btn-sm" style="font-weight: 800;">
                    &#128424; Print / Save PDF
                </button>
                <button type="button" onclick="copyTicketLink()" class="btn btn-secondary btn-sm" style="font-weight: 700;">
                    &#128279; Share Pass Link
                </button>
                <a href="https://wa.me/917004665804?text=<?= urlencode("Hello Millennium India Group, my Booking ID is " . $booking['booking_uid'] . ". Here is my verified pass: " . BASE_URL . "/ticket.php?uid=" . urlencode($booking['booking_uid'])) ?>" target="_blank" class="btn btn-sm" style="background: #25d366; color: #fff; font-weight: 700; text-decoration: none;">
                    &#128172; WhatsApp
                </a>
            </div>
        </div>

        <!-- Master Luxury Boarding Pass Card -->
        <div class="digital-ticket" id="printable-ticket">
            <!-- Left Main Pass Body -->
            <div class="ticket-main">
                <div class="ticket-brand">
                    <div>
                        <div class="ticket-org-name">&#10024; Millennium India Group</div>
                        <div style="font-size: 0.8rem; color: #777;">Official Digital Gate Pass</div>
                    </div>
                    <div>
                        <span class="ticket-badge" style="background: #10b981; color: #fff; font-weight: 900; font-size: 0.82rem; padding: 6px 12px; border-radius: 6px;">
                            &#10004; VERIFIED PASS
                        </span>
                    </div>
                </div>

                <div class="ticket-event-title"><?= e($booking['event_title']) ?></div>

                <div class="ticket-details-grid">
                    <div>
                        <div class="ticket-info-label">Booking ID</div>
                        <div class="ticket-info-value" style="font-family: monospace; color: #7a1244; font-size: 1.15rem; font-weight: 900;">
                            <?= e($booking['booking_uid']) ?>
                        </div>
                    </div>

                    <div>
                        <div class="ticket-info-label">Ticket Type</div>
                        <div class="ticket-info-value" style="color: #111;">
                            <?= $ticketTypeSummary ?: 'Standard Entry' ?>
                        </div>
                    </div>

                    <div>
                        <div class="ticket-info-label">Attendee Name</div>
                        <div class="ticket-info-value" style="font-size: 1.1rem; color: #000; font-weight: 800;">
                            <?= e($booking['customer_name']) ?>
                        </div>
                    </div>

                    <div>
                        <div class="ticket-info-label">Mobile Number</div>
                        <div class="ticket-info-value" style="font-family: monospace; font-size: 1.05rem;">
                            <?= e($booking['customer_phone']) ?>
                        </div>
                    </div>

                    <div>
                        <div class="ticket-info-label">Valid For</div>
                        <div class="ticket-info-value" style="color: #7a1244; font-size: 1.25rem; font-weight: 900;">
                            <?= $totalPersons ?> Person<?= $totalPersons > 1 ? 's' : '' ?>
                        </div>
                    </div>

                    <?php if (!empty($booking['seat_numbers'])): ?>
                    <div>
                        <div class="ticket-info-label">Allocated Seat(s)</div>
                        <div class="ticket-info-value" style="color: #059669; font-size: 1.25rem; font-weight: 900; font-family: monospace;">
                            &#129681; <?= e($booking['seat_numbers']) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div>
                        <div class="ticket-info-label">Date &amp; Timing</div>
                        <div class="ticket-info-value" style="font-size: 0.95rem;">
                            <?= date('d M Y', strtotime($booking['event_date'])) ?> &bull; <?= date('h:i A', strtotime($booking['start_time'])) ?> &ndash; <?= date('h:i A', strtotime($booking['end_time'])) ?>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 15px;">
                    <div class="ticket-info-label">Venue</div>
                    <div class="ticket-info-value" style="font-size: 1.05rem; color: #222; font-weight: 700;">
                        &#128205; <?= e($booking['event_venue']) ?>
                    </div>
                </div>

                <!-- Bottom Security Banner -->
                <div style="margin-top: 25px; padding: 10px; background: #7a1244; color: #ffd966; text-align: center; font-weight: 800; font-size: 0.85rem; letter-spacing: 1.5px; border-radius: 6px;">
                    ENTRY WITH VALID QR CODE ONLY &bull; FOOD PRIVILEGE (₹399 &amp; ₹699 PASSES)
                </div>
            </div>

            <!-- Right Stub / QR Code Section -->
            <div class="ticket-stub">
                <div style="font-size: 0.75rem; text-transform: uppercase; color: #888; font-weight: 700; margin-bottom: 8px;">
                    Gate Entry QR
                </div>

                <!-- Dynamic QR Container (with Image Fallback) -->
                <div class="ticket-qr-frame">
                    <div id="ticket-qrcode">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&amp;data=<?= urlencode($qrPayload) ?>" 
                             alt="Entry QR Code" 
                             style="width: 140px; height: 140px; display: block; border-radius: 4px;">
                    </div>
                </div>

                <div class="ticket-uid"><?= e($booking['booking_uid']) ?></div>
                
                <div style="font-size: 0.82rem; color: #059669; font-weight: 800; margin-top: 6px;">
                    <?= $booking['is_checked_in'] ? '&#10004; CHECKED IN' : '&#10004; ACTIVE PASS' ?>
                </div>

                <div class="ticket-security-note">
                    Official Hash Token:<br>
                    <span style="font-family: monospace; font-size: 0.65rem; color: #666; word-break: break-all;">
                        <?= substr($booking['qr_token'], 0, 16) ?>...
                    </span>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- BOOKMYSHOW STYLE: OFFICIAL TAX INVOICE & PAYMENT RECEIPT       -->
        <!-- ============================================================== -->
        <div class="festive-card" style="margin-top: 30px; border: 1.5px solid var(--gold-accent); padding: 26px 22px; box-shadow: 0 10px 35px rgba(0,0,0,0.5);">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px dashed rgba(245,183,34,0.35); padding-bottom: 15px; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <span style="background: rgba(16,185,129,0.15); color: #10b981; font-weight: 800; font-size: 0.75rem; padding: 3px 12px; border-radius: 20px; text-transform: uppercase;">
                        &#10004; Payment Successful
                    </span>
                    <h3 style="color: #fff; font-size: 1.4rem; font-weight: 900; margin: 6px 0 0;">
                        Official Tax Invoice &amp; Payment Receipt
                    </h3>
                </div>
                <div style="text-align: right;">
                    <div style="color: var(--text-muted); font-size: 0.82rem; text-transform: uppercase;">Receipt / Order ID</div>
                    <div style="color: var(--gold-accent); font-family: monospace; font-size: 1.1rem; font-weight: 800;"><?= e($booking['booking_uid']) ?></div>
                </div>
            </div>

            <!-- Receipt Meta Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; background: rgba(20,2,11,0.85); border: 1px solid rgba(245,183,34,0.25); border-radius: 10px; padding: 18px; margin-bottom: 20px;">
                <div>
                    <span style="font-size: 0.76rem; color: #888; text-transform: uppercase; font-weight: 700;">Transaction Ref (UTR)</span>
                    <div style="font-family: monospace; color: #ffd966; font-size: 1.05rem; font-weight: 800; margin-top: 2px;">
                        <?= e($booking['transaction_ref'] ?: 'Direct UPI') ?>
                    </div>
                </div>

                <div>
                    <span style="font-size: 0.76rem; color: #888; text-transform: uppercase; font-weight: 700;">Payment Mode</span>
                    <div style="color: #fff; font-size: 0.95rem; font-weight: 700; margin-top: 2px;">
                        Direct UPI (chandni0984@oksbi)
                    </div>
                </div>

                <div>
                    <span style="font-size: 0.76rem; color: #888; text-transform: uppercase; font-weight: 700;">Booking Date &amp; Time</span>
                    <div style="color: #fff; font-size: 0.95rem; font-weight: 700; margin-top: 2px;">
                        <?= date('d M Y, h:i A', strtotime($booking['created_at'])) ?>
                    </div>
                </div>

                <?php if (!empty($booking['seat_numbers'])): ?>
                <div>
                    <span style="font-size: 0.76rem; color: #888; text-transform: uppercase; font-weight: 700;">Allocated Arena Seats</span>
                    <div style="color: #34d399; font-size: 1.05rem; font-weight: 800; margin-top: 2px; font-family: monospace;">
                        &#129681; <?= e($booking['seat_numbers']) ?>
                    </div>
                </div>
                <?php endif; ?>

                <div>
                    <span style="font-size: 0.76rem; color: #888; text-transform: uppercase; font-weight: 700;">Payment Status</span>
                    <div style="color: #10b981; font-size: 0.95rem; font-weight: 900; margin-top: 2px;">
                        PAID &amp; CONFIRMED
                    </div>
                </div>
            </div>

            <!-- Itemized Table -->
            <div style="overflow-x: auto; margin-bottom: 20px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.92rem; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1.5px solid rgba(245,183,34,0.3); color: var(--gold-light); font-size: 0.82rem; text-transform: uppercase;">
                            <th style="padding: 10px 12px;">Pass Description</th>
                            <th style="padding: 10px 12px; text-align: center;">Qty</th>
                            <th style="padding: 10px 12px; text-align: right;">Unit Price</th>
                            <th style="padding: 10px 12px; text-align: right;">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $item): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.07); color: #e2e8f0;">
                                    <td style="padding: 12px;">
                                        <strong style="color: #fff;"><?= e($item['category_name']) ?></strong>
                                        <div style="font-size: 0.78rem; color: #94a3b8;">Admits <?= (int)$item['people_allowed'] ?> person<?= $item['people_allowed'] > 1 ? 's' : '' ?> per pass</div>
                                    </td>
                                    <td style="padding: 12px; text-align: center; font-weight: 700;"><?= (int)$item['quantity'] ?></td>
                                    <td style="padding: 12px; text-align: right;"><?= format_inr($item['unit_price']) ?></td>
                                    <td style="padding: 12px; text-align: right; font-weight: 800; color: #fff;"><?= format_inr($item['subtotal']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr style="color: #e2e8f0;">
                                <td style="padding: 12px;"><strong style="color: #fff;">Garba Entry Pass</strong></td>
                                <td style="padding: 12px; text-align: center;">1</td>
                                <td style="padding: 12px; text-align: right;"><?= format_inr($booking['total_amount']) ?></td>
                                <td style="padding: 12px; text-align: right; font-weight: 800; color: #fff;"><?= format_inr($booking['total_amount']) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr style="border-top: 1.5px solid rgba(245,183,34,0.4); color: #fff;">
                            <td colspan="3" style="padding: 12px; font-weight: 800; font-size: 1rem; text-align: right;">Total Paid:</td>
                            <td style="padding: 12px; font-weight: 900; font-size: 1.35rem; text-align: right; color: var(--gold-accent);">
                                <?= format_inr($booking['total_amount']) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Terms & Instructions -->
            <div style="background: rgba(20,2,11,0.7); border-radius: 8px; padding: 14px 18px; font-size: 0.85rem; color: #cbd5e1; line-height: 1.6;">
                <strong style="color: var(--gold-accent); display: block; margin-bottom: 4px;">Important Entry Instructions:</strong>
                &bull; Please keep this receipt &amp; QR pass on your mobile or bring a printed copy to the venue.<br>
                &bull; Entry is granted strictly upon successful scanning of the QR code at the gate.<br>
                &bull; Food privilege included strictly for ₹399 &amp; ₹699 passes.<br>
                &bull; For any entry or pass verification queries, contact Millennium India Group Helpline: <strong style="color: #fff;">+91 7004665804</strong>.
            </div>
        </div>

    <?php endif; ?>

</div>

<!-- Fast Local QR Generation (Zero External CDN Latency) -->
<script src="<?= asset_v('assets/js/qrcode.min.js') ?>"></script>
<script>
if (typeof QRCode === 'undefined') {
    document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"><\/script>');
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const qrContainer = document.getElementById('ticket-qrcode');
    <?php if ($isVerified && !empty($qrPayload)): ?>
        if (qrContainer && typeof QRCode !== 'undefined') {
            qrContainer.innerHTML = '';
            new QRCode(qrContainer, {
                text: <?= json_encode($qrPayload) ?>,
                width: 140,
                height: 140,
                colorDark: "#1b0410",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        }
    <?php endif; ?>
});

function copyBookingId(uid) {
    navigator.clipboard.writeText(uid).then(() => {
        alert('Booking Reference ID copied: ' + uid);
    }).catch(() => {
        prompt('Copy your Booking Reference ID:', uid);
    });
}

function copyTicketLink() {
    const link = window.location.href;
    navigator.clipboard.writeText(link).then(() => {
        alert('Ticket pass link copied to clipboard!');
    }).catch(() => {
        prompt('Copy your ticket pass link:', link);
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
