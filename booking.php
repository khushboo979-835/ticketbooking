<?php
/**
 * booking.php - BookMyShow-Grade Interactive Seat Selection & Pass Booking Engine
 * Millennium India Group - Jashn-E-Garba 2026
 * Real-time cinema/arena seating map matching BookMyShow specification
 */
ob_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

send_no_cache_headers();
ensure_seat_inventory_table();

$eventId = (int)($_GET['event_id'] ?? 1);
$event = get_event($eventId);
if (!$event || (int)$event['is_published'] !== 1) {
    set_flash('error', 'The requested event is currently unavailable or unpublished.');
    header("Location: " . BASE_URL . "/events.php");
    exit;
}

$categories = get_event_categories($event['id'], true);
$allSeats = get_all_seats($event['id']);
$seatSummary = get_seat_summary($event['id']);
$errors = [];

// Handle Form POST: Seat Selection + Attendee Details -> Direct UPI Checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['seat_checkout', 'quick_checkout'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security session expired. Please refresh and try again.';
    } else {
        $selectedSeatsStr = trim($_POST['selected_seats'] ?? '');
        $seatCodes = array_filter(array_map('trim', explode(',', $selectedSeatsStr)));

        $db = getDB();

        if (!empty($seatCodes)) {
            // Validate all selected seats are actually available
            $inClause = implode(',', array_fill(0, count($seatCodes), '?'));
            $chkStmt = $db->prepare("SELECT * FROM event_seats WHERE event_id = ? AND seat_code IN ($inClause)");
            $chkParams = array_merge([$event['id']], $seatCodes);
            $chkStmt->execute($chkParams);
            $foundSeats = $chkStmt->fetchAll() ?: [];

            $totalAmount = 0.0;
            $totalTickets = count($foundSeats);
            $selectedItems = [];
            $totalPeople = 0;

            foreach ($foundSeats as $sRow) {
                if ($sRow['status'] !== 'available') {
                    $errors[] = "Seat {$sRow['seat_code']} was just booked by another attendee. Please choose an available seat.";
                    break;
                }

                $cId = (int)$sRow['category_id'];
                $sPrice = (float)$sRow['tier_price'];
                $totalAmount += $sPrice;

                // Lookup category details
                $catInfo = null;
                foreach ($categories as $cat) {
                    if ((int)$cat['id'] === $cId) { $catInfo = $cat; break; }
                }
                $ppl = $catInfo ? (int)$catInfo['people_allowed'] : 1;
                $totalPeople += $ppl;

                if (!isset($selectedItems[$cId])) {
                    $selectedItems[$cId] = [
                        'id'             => $cId,
                        'name'           => $sRow['category_name'],
                        'price'          => $sPrice,
                        'people_allowed' => $ppl,
                        'quantity'       => 1,
                        'subtotal'       => $sPrice,
                        'seats'          => [$sRow['seat_code']]
                    ];
                } else {
                    $selectedItems[$cId]['quantity']++;
                    $selectedItems[$cId]['subtotal'] += $sPrice;
                    $selectedItems[$cId]['seats'][] = $sRow['seat_code'];
                }
            }

            if (empty($errors)) {
                $name = trim($_POST['customer_name'] ?? '');
                $phone = preg_replace('/[^\d]/', '', trim($_POST['customer_phone'] ?? ''));
                $email = strtolower(trim($_POST['customer_email'] ?? ''));

                if (empty($name)) $name = 'Garba Attendee';
                if (empty($phone) || strlen($phone) < 10) $phone = '7004665804';
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = $phone . '@millenniumindiagroup.com';

                $_SESSION['booking_cart'] = [
                    'event_id'      => $event['id'],
                    'event_title'   => $event['title'],
                    'event_date'    => $event['date'],
                    'event_venue'   => $event['venue'],
                    'start_time'    => $event['start_time'],
                    'end_time'      => $event['end_time'],
                    'items'         => $selectedItems,
                    'seat_numbers'  => implode(', ', $seatCodes),
                    'total_amount'  => $totalAmount,
                    'total_tickets' => $totalTickets,
                    'total_people'  => $totalPeople,
                    'timestamp'     => time()
                ];

                $_SESSION['booking_customer'] = [
                    'customer_name'  => $name,
                    'customer_phone' => $phone,
                    'customer_email' => $email
                ];

                unset($_SESSION['current_booking_uid'], $_SESSION['current_booking_id'], $_SESSION['current_qr_token']);

                header("Location: " . BASE_URL . "/payment.php");
                exit;
            }
        } else {
            // Fallback: If user submitted without choosing specific seat squares, auto-pick from quantities
            $quantities = $_POST['quantities'] ?? [];
            $hasAny = false;
            foreach ($quantities as $q) { if ((int)$q > 0) { $hasAny = true; break; } }
            if (!$hasAny && !empty($categories)) {
                $quantities[$categories[0]['id']] = 2; // Default 2 seats like BookMyShow
            }

            $totalAmount = 0.0;
            $totalTickets = 0;
            $totalPeople = 0;
            $selectedItems = [];
            $assignedSeatCodes = [];

            foreach ($quantities as $catId => $qty) {
                $qty = (int)$qty;
                if ($qty <= 0) continue;

                $catStmt = $db->prepare("SELECT * FROM ticket_categories WHERE id = :id AND status = 'active' LIMIT 1");
                $catStmt->execute([':id' => (int)$catId]);
                $cRow = $catStmt->fetch();
                if ($cRow) {
                    $sub = $qty * (float)$cRow['price'];
                    $totalAmount += $sub;
                    $totalTickets += $qty;
                    $totalPeople += ($qty * (int)$cRow['people_allowed']);

                    // Auto-assign available seats in this category
                    $sStmt = $db->prepare("SELECT seat_code FROM event_seats WHERE category_id = :cid AND status = 'available' LIMIT :lim");
                    $sStmt->bindValue(':cid', (int)$catId, PDO::PARAM_INT);
                    $sStmt->bindValue(':lim', $qty, PDO::PARAM_INT);
                    $sStmt->execute();
                    $picked = $sStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
                    $assignedSeatCodes = array_merge($assignedSeatCodes, $picked);

                    $selectedItems[$cRow['id']] = [
                        'id'             => $cRow['id'],
                        'name'           => $cRow['name'],
                        'price'          => (float)$cRow['price'],
                        'people_allowed' => (int)$cRow['people_allowed'],
                        'quantity'       => $qty,
                        'subtotal'       => $sub,
                        'seats'          => $picked
                    ];
                }
            }

            $name = trim($_POST['customer_name'] ?? 'Garba Attendee');
            $phone = preg_replace('/[^\d]/', '', trim($_POST['customer_phone'] ?? '7004665804'));
            $email = $phone . '@millenniumindiagroup.com';

            $_SESSION['booking_cart'] = [
                'event_id'      => $event['id'],
                'event_title'   => $event['title'],
                'event_date'    => $event['date'],
                'event_venue'   => $event['venue'],
                'start_time'    => $event['start_time'],
                'end_time'      => $event['end_time'],
                'items'         => $selectedItems,
                'seat_numbers'  => implode(', ', $assignedSeatCodes),
                'total_amount'  => $totalAmount,
                'total_tickets' => $totalTickets,
                'total_people'  => $totalPeople,
                'timestamp'     => time()
            ];

            $_SESSION['booking_customer'] = [
                'customer_name'  => $name,
                'customer_phone' => $phone,
                'customer_email' => $email
            ];

            unset($_SESSION['current_booking_uid'], $_SESSION['current_booking_id'], $_SESSION['current_qr_token']);

            header("Location: " . BASE_URL . "/payment.php");
            exit;
        }
    }
}

// Group seats by tier and row for the BookMyShow visual grid
$tieredRows = [];
foreach ($allSeats as $s) {
    $tier = $s['category_name'] . ' &bull; ' . format_inr($s['tier_price']);
    $rName = $s['row_name'];
    $tieredRows[$tier][$rName][] = $s;
}

$pageTitle = 'Select Seats | ' . e($event['title']);
$currentNav = 'garba';

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 25px; padding-bottom: 70px;">

    <!-- Top Progression Bar -->
    <div style="display: flex; justify-content: center; margin-bottom: 25px;">
        <div style="display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 0.95rem;">
            <span style="background: var(--gold-accent); color: #1f0411; padding: 6px 18px; border-radius: 50px; font-weight: 800;">
                1. Select Seats
            </span>
            <span style="color: #666;">&rarr;</span>
            <span style="background: rgba(255,255,255,0.1); color: #aaa; padding: 6px 16px; border-radius: 50px;">
                2. Direct UPI Payment
            </span>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="max-width: 880px; margin: 0 auto 20px;">
            <ul style="margin:0; padding-left:20px;">
                <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- ========================================================
         STEP 1: BookMyShow "How many seats?" Selector Card
         Matches Official Screenshot Specification
         ======================================================== -->
    <div class="bms-seat-modal-card" id="bms-step-count">
        <h2 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-bottom: 6px;">
            How many seats?
        </h2>
        <div style="font-size: 0.88rem; color: #64748b;">
            Choose your group size for <strong>Jashn-E-Garba 2026</strong>
        </div>

        <!-- Festive Graphic / Scooter Illustration -->
        <div style="margin: 16px auto; font-size: 3rem;">
            &#127949;&#65039;
        </div>

        <!-- 1 to 10 Seat Number Selector (2 Selected by Default like BMS) -->
        <div class="bms-seat-count-strip">
            <?php for ($num = 1; $num <= 10; $num++): ?>
                <div class="bms-seat-num-circle <?= $num === 2 ? 'active' : '' ?>" 
                     data-count="<?= $num ?>" 
                     onclick="setSeatTargetCount(<?= $num ?>)">
                    <?= $num ?>
                </div>
            <?php endfor; ?>
        </div>

        <!-- Tier Preview Tags (Matches BookMyShow Categories Dynamically) -->
        <div class="bms-tier-preview-grid">
            <?php foreach ($categories as $cat): ?>
                <div class="bms-tier-preview-item" onclick="selectTierAndScroll('<?= e($cat['name']) ?>')" style="cursor: pointer;">
                    <div class="bms-tier-preview-title"><?= e($cat['name']) ?></div>
                    <div class="bms-tier-preview-price"><?= format_inr($cat['price']) ?></div>
                    <div class="bms-tier-preview-status" style="color: <?= ((int)$cat['available_seats'] < 10) ? '#f84464' : '#10b981' ?>;">
                        <?= ((int)$cat['available_seats'] < 5) ? 'ALMOST FULL' : (((int)$cat['available_seats'] < 15) ? 'FILLING FAST' : 'AVAILABLE') ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="btn-bms-red" style="width: 100%; justify-content: center;" onclick="scrollToSeatMap()">
            Select Seats &rarr;
        </button>
    </div>

    <!-- ========================================================
         STEP 2: BookMyShow Cinema / Arena Seat Layout Matrix
         Matches Screenshot: media_1791887372542_1e1b0725.png
         ======================================================== -->
    <div class="bms-arena-wrapper" id="bms-seat-map">
        <!-- Event Header Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 14px; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
            <div>
                <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0;">
                    <?= e($event['title']) ?>
                </h3>
                <div style="font-size: 0.85rem; color: #64748b; margin-top: 2px;">
                    &#128197; <?= date('D, d M Y', strtotime($event['date'])) ?> &bull; &#9200; <?= !empty($event['start_time']) ? date('h:i A', strtotime($event['start_time'])) : '05:00 PM' ?> Onwards &bull; <?= e($event['venue']) ?>
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <span style="background: rgba(245,183,34,0.15); color: #d97706; padding: 4px 12px; border-radius: 20px; font-weight: 800; font-size: 0.82rem;">
                    &#9889; <span id="target-count-display">2</span> Seats Target
                </span>
            </div>
        </div>

        <!-- Live Real-Time Arena Capacity Strip (Booked, Khali, Total) -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,0.2);"></span>
                <span style="font-size: 0.88rem; font-weight: 700; color: #0f172a;">Live Arena Seating Status:</span>
            </div>
            <div style="display: flex; gap: 14px; font-size: 0.84rem; font-weight: 700; flex-wrap: wrap;">
                <span style="color: #475569;">Total Seats: <strong style="color: #0f172a;"><?= (int)$seatSummary['total'] ?></strong></span>
                <span style="color: #dc2626;">Booked: <strong style="color: #dc2626;"><?= (int)$seatSummary['sold'] ?></strong></span>
                <span style="color: #16a34a;">Khali (Available): <strong style="color: #16a34a;"><?= (int)$seatSummary['available'] ?></strong></span>
                <span style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 12px;"><?= $seatSummary['occupancy_pct'] ?>% Occupied</span>
            </div>
        </div>

        <!-- Curved Central Performance Stage -->
        <div class="bms-arena-screen">
            <div class="bms-stage-curve"></div>
            <div class="bms-stage-text">&#9733; CENTRAL PERFORMANCE STAGE &#9733;</div>
        </div>

        <!-- Seating Rows Grouped By Category -->
        <form action="<?= BASE_URL ?>/booking.php?event_id=<?= $event['id'] ?>" method="POST" id="seat-checkout-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="seat_checkout">
            <input type="hidden" name="selected_seats" id="input-selected-seats" value="">

            <?php foreach ($tieredRows as $tierHeader => $rows): ?>
                <div class="bms-tier-section">
                    <div class="bms-tier-header">
                        <span><?= $tierHeader ?></span>
                    </div>

                    <?php foreach ($rows as $rowLetter => $seatsInRow): ?>
                        <div class="bms-seat-row">
                            <span class="bms-row-letter"><?= e($rowLetter) ?></span>
                            
                            <!-- Left Block (Seats 01-05) -->
                            <div class="bms-seat-block">
                                <?php for ($i = 0; $i < min(5, count($seatsInRow)); $i++): ?>
                                    <?php $st = $seatsInRow[$i]; ?>
                                    <div class="bms-seat-box <?= e($st['status']) ?>" 
                                         id="seat-box-<?= e($st['seat_code']) ?>"
                                         data-code="<?= e($st['seat_code']) ?>"
                                         data-price="<?= (float)$st['tier_price'] ?>"
                                         data-status="<?= e($st['status']) ?>"
                                         onclick="handleSeatClick('<?= e($st['seat_code']) ?>', <?= (float)$st['tier_price'] ?>)">
                                        <?= str_pad($st['seat_num'], 2, '0', STR_PAD_LEFT) ?>
                                    </div>
                                <?php endfor; ?>
                            </div>

                            <!-- Center Walkway Aisle -->
                            <div class="bms-seat-aisle"></div>

                            <!-- Right Block (Seats 06-10) -->
                            <div class="bms-seat-block">
                                <?php for ($i = 5; $i < count($seatsInRow); $i++): ?>
                                    <?php $st = $seatsInRow[$i]; ?>
                                    <div class="bms-seat-box <?= e($st['status']) ?>" 
                                         id="seat-box-<?= e($st['seat_code']) ?>"
                                         data-code="<?= e($st['seat_code']) ?>"
                                         data-price="<?= (float)$st['tier_price'] ?>"
                                         data-status="<?= e($st['status']) ?>"
                                         onclick="handleSeatClick('<?= e($st['seat_code']) ?>', <?= (float)$st['tier_price'] ?>)">
                                        <?= str_pad($st['seat_num'], 2, '0', STR_PAD_LEFT) ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <!-- Legend Bar Matching BookMyShow Screenshot -->
            <div class="bms-legend-bar">
                <div class="bms-legend-item">
                    <span class="bms-legend-swatch" style="border: 1.5px solid #10b981; background: #fff;"></span>
                    <span>Available</span>
                </div>
                <div class="bms-legend-item">
                    <span class="bms-legend-swatch" style="background: #e2e8f0; border: 1.5px solid #cbd5e1;"></span>
                    <span>Sold (Booked)</span>
                </div>
                <div class="bms-legend-item">
                    <span class="bms-legend-swatch" style="background: #f5b722;"></span>
                    <span>Bestseller</span>
                </div>
                <div class="bms-legend-item">
                    <span class="bms-legend-swatch" style="background: #10b981;"></span>
                    <span>Selected</span>
                </div>
            </div>

            <!-- Floating / Bottom Checkout Bar -->
            <div class="bms-seat-checkout-card">
                <div>
                    <div style="font-size: 0.82rem; color: #94a3b8; text-transform: uppercase; font-weight: 700;">
                        Selected Seats
                    </div>
                    <div id="selected-seats-label" style="font-size: 1.2rem; font-weight: 800; color: #ffd966;">
                        None selected (Please tap seats above)
                    </div>
                </div>

                <div>
                    <div style="font-size: 0.82rem; color: #94a3b8; text-transform: uppercase; font-weight: 700;">
                        Total Payable
                    </div>
                    <div id="selected-total-label" style="font-size: 1.6rem; font-weight: 900; color: #34d399;">
                        ₹0.00
                    </div>
                </div>

                <div style="display: flex; gap: 10px; flex-wrap: wrap; width: 100%; max-width: 480px;">
                    <input type="text" name="customer_name" required placeholder="Attendee Full Name *" class="form-control" style="background: #12141d; border: 1px solid #2e344a; color: #fff; flex: 1; padding: 10px 14px; border-radius: 8px;">
                    <input type="tel" name="customer_phone" required pattern="[0-9]{10}" placeholder="Mobile (10 Digits) *" class="form-control" style="background: #12141d; border: 1px solid #2e344a; color: #fff; flex: 1; padding: 10px 14px; border-radius: 8px;">
                </div>

                <div>
                    <button type="submit" id="btn-submit-seat-pay" class="btn-bms-red" disabled style="opacity: 0.5;">
                        <span>Proceed to Pay (Direct UPI) &rarr;</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let targetCount = 2;
let selectedSeats = [];
let seatPrices = {};

function setSeatTargetCount(n) {
    targetCount = parseInt(n);
    document.querySelectorAll('.bms-seat-num-circle').forEach(el => {
        el.classList.toggle('active', parseInt(el.getAttribute('data-count')) === targetCount);
    });
    const disp = document.getElementById('target-count-display');
    if (disp) disp.innerText = targetCount;

    // Reset current selection if count changes
    clearSelectedSeats();
}

function scrollToSeatMap() {
    const el = document.getElementById('bms-seat-map');
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
    }
}

function selectTierAndScroll(tierName) {
    scrollToSeatMap();
}

function handleSeatClick(code, price) {
    const box = document.getElementById('seat-box-' + code);
    if (!box || box.classList.contains('sold')) return;

    const idx = selectedSeats.indexOf(code);
    if (idx > -1) {
        // Deselect
        selectedSeats.splice(idx, 1);
        delete seatPrices[code];
        box.classList.remove('selected');
        box.classList.add('available');
    } else {
        // Select up to target count
        if (selectedSeats.length >= targetCount) {
            // Remove first selected seat
            const oldest = selectedSeats.shift();
            delete seatPrices[oldest];
            const oldBox = document.getElementById('seat-box-' + oldest);
            if (oldBox) {
                oldBox.classList.remove('selected');
                oldBox.classList.add('available');
            }
        }
        selectedSeats.push(code);
        seatPrices[code] = price;
        box.classList.remove('available');
        box.classList.add('selected');
    }

    updateCheckoutSummary();
}

function clearSelectedSeats() {
    selectedSeats.forEach(code => {
        const box = document.getElementById('seat-box-' + code);
        if (box) {
            box.classList.remove('selected');
            box.classList.add('available');
        }
    });
    selectedSeats = [];
    seatPrices = {};
    updateCheckoutSummary();
}

function updateCheckoutSummary() {
    const inputSeats = document.getElementById('input-selected-seats');
    const labelSeats = document.getElementById('selected-seats-label');
    const labelTotal = document.getElementById('selected-total-label');
    const btnPay = document.getElementById('btn-submit-seat-pay');

    inputSeats.value = selectedSeats.join(',');

    let total = 0;
    for (let c in seatPrices) {
        total += seatPrices[c];
    }

    if (selectedSeats.length > 0) {
        labelSeats.innerText = selectedSeats.join(', ') + ' (' + selectedSeats.length + ' Seats)';
        labelTotal.innerText = '₹' + total.toFixed(2);
        btnPay.disabled = false;
        btnPay.style.opacity = '1';
        btnPay.innerHTML = '<span>Pay ₹' + total.toFixed(2) + ' &rarr;</span>';
    } else {
        labelSeats.innerText = 'None selected (Please tap seats above)';
        labelTotal.innerText = '₹0.00';
        btnPay.disabled = true;
        btnPay.style.opacity = '0.5';
        btnPay.innerHTML = '<span>Proceed to Pay (Direct UPI) &rarr;</span>';
    }
}

// Auto-select first 2 available seats on load for frictionless booking
document.addEventListener('DOMContentLoaded', function() {
    const availBoxes = document.querySelectorAll('.bms-seat-box.available');
    if (availBoxes.length >= 2) {
        handleSeatClick(availBoxes[0].getAttribute('data-code'), parseFloat(availBoxes[0].getAttribute('data-price')));
        handleSeatClick(availBoxes[1].getAttribute('data-code'), parseFloat(availBoxes[1].getAttribute('data-price')));
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
