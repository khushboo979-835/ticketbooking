<?php
// booking.php - Streamlined Dynamic Ticket Booking Engine (Matches Official Infographic Specification)
ob_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

send_no_cache_headers();

$eventId = (int)($_GET['event_id'] ?? 1);
$preselectedCatId = (int)($_GET['cat'] ?? 0);

$event = get_event($eventId);
if (!$event || (int)$event['is_published'] !== 1) {
    set_flash('error', 'The requested event is currently unavailable or unpublished.');
    header("Location: " . BASE_URL . "/events.php");
    exit;
}

$categories = get_event_categories($event['id'], true);
$step = isset($_GET['step']) && $_GET['step'] === '2' ? 2 : 1;
$errors = [];

// Handle Unified 1-Step POST: Ticket Selection + Attendee Details -> Direct UPI Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['quick_checkout', 'select_tickets', 'save_customer'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security session expired. Please refresh and try again.';
    } else {
        $quantities = $_POST['quantities'] ?? [];
        $selectedItems = [];
        $totalAmount = 0;
        $totalTickets = 0;
        $totalPeople = 0;

        // Auto-select 1 ticket if user submitted without choosing any quantity
        $hasAnyQty = false;
        foreach ($quantities as $q) {
            if ((int)$q > 0) { $hasAnyQty = true; break; }
        }
        if (!$hasAnyQty && !empty($categories)) {
            $quantities[$categories[0]['id']] = 1;
        }

        try {
            $db = getDB();
            foreach ($quantities as $catId => $qty) {
                $qty = (int)$qty;
                if ($qty <= 0) continue;

                $stmt = $db->prepare("SELECT * FROM ticket_categories WHERE id = :id AND event_id = :e_id AND status = 'active' LIMIT 1");
                $stmt->execute([':id' => (int)$catId, ':e_id' => $event['id']]);
                $cat = $stmt->fetch();

                if (!$cat) {
                    $errors[] = "One of the selected categories is no longer available.";
                    break;
                }

                if ($qty > (int)$cat['available_quantity']) {
                    $errors[] = "Only {$cat['available_quantity']} tickets left for '{$cat['name']}'. Please adjust your selection.";
                    break;
                }

                $subtotal = $qty * (float)$cat['price'];
                $totalAmount += $subtotal;
                $totalPeople += ($qty * (int)$cat['people_allowed']);
                $totalTickets += $qty;

                $selectedItems[$cat['id']] = [
                    'id'             => $cat['id'],
                    'name'           => $cat['name'],
                    'price'          => (float)$cat['price'],
                    'people_allowed' => (int)$cat['people_allowed'],
                    'quantity'       => $qty,
                    'subtotal'       => $subtotal
                ];
            }

            if (empty($errors)) {
                if ($totalTickets <= 0) {
                    $errors[] = "Please select at least 1 ticket quantity to proceed.";
                } else {
                    $name = trim($_POST['customer_name'] ?? '');
                    $phone = preg_replace('/[^\d]/', '', trim($_POST['customer_phone'] ?? ''));
                    $email = strtolower(trim($_POST['customer_email'] ?? ''));

                    if (empty($name)) {
                        $name = 'Guest Attendee';
                    }
                    if (empty($phone) || strlen($phone) < 10) {
                        $phone = '8603268304';
                    }
                    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $email = 'guest@millenniumindiagroup.com';
                    }

                    $_SESSION['booking_cart'] = [
                        'event_id'      => $event['id'],
                        'event_title'   => $event['title'],
                        'event_date'    => $event['date'],
                        'event_venue'   => $event['venue'],
                        'start_time'    => $event['start_time'],
                        'end_time'      => $event['end_time'],
                        'items'         => $selectedItems,
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
        } catch (PDOException $e) {
            error_log('[booking.php error]: ' . $e->getMessage());
            $errors[] = "Database connectivity issue while checking inventory. Please try again.";
        }
    }
}

$pageTitle = 'Book Passes | ' . e($event['title']);
$currentNav = 'garba';

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 30px; padding-bottom: 70px;">

    <!-- Top Step Progression Indicator -->
    <div style="display: flex; justify-content: center; margin-bottom: 25px;">
        <div style="display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 0.95rem;">
            <span style="background: var(--gold-accent); color: #1f0411; padding: 6px 18px; border-radius: 50px; font-weight: 800;">
                1. Select Tickets &amp; Details
            </span>
            <span style="color: #666;">&rarr;</span>
            <span style="background: rgba(255,255,255,0.1); color: #aaa; padding: 6px 16px; border-radius: 50px;">
                2. Direct UPI Payment
            </span>
        </div>
    </div>

    <!-- Error Alerts -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="max-width: 840px; margin: 0 auto 25px;">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Main Container Card -->
    <div class="festive-card" style="max-width: 860px; margin: 0 auto; border: 2px solid var(--gold-accent); padding: 28px 24px;">

        <!-- Event Header Banner Section -->
        <div style="display: flex; align-items: center; gap: 20px; border-bottom: 1px solid rgba(245,183,34,0.3); padding-bottom: 20px; margin-bottom: 25px; flex-wrap: wrap;">
            <img src="<?= BASE_URL ?>/assets/uploads/garba-banner.jpg" alt="<?= e($event['title']) ?>" style="width: 130px; height: 85px; object-fit: cover; border-radius: 8px; border: 1.5px solid var(--gold-accent);" onerror="this.style.display='none'">
            <div style="flex: 1; min-width: 250px;">
                <span class="hero-badge" style="margin-bottom: 4px; padding: 3px 10px; font-size: 0.75rem;">Millennium India Group Presents</span>
                <h1 style="font-size: 1.8rem; font-weight: 900; color: #fff; margin: 2px 0 6px;"><?= e($event['title']) ?></h1>
                <div style="color: var(--gold-light); font-size: 0.9rem; display: flex; flex-wrap: wrap; gap: 15px;">
                    <span>&#128197; <?= date('d F Y (l)', strtotime($event['date'])) ?></span>
                    <span>&#9200; <?= date('h:i A', strtotime($event['start_time'])) ?> &ndash; <?= date('h:i A', strtotime($event['end_time'])) ?></span>
                    <span>&#128205; <?= e($event['venue']) ?></span>
                </div>
            </div>
        </div>

        <!-- UNIFIED 1-STEP BOOKING FORM -->
        <form action="<?= BASE_URL ?>/booking.php?event_id=<?= $event['id'] ?>" method="POST" id="unified-booking-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="quick_checkout">

            <!-- SECTION 1: PASS SELECTION -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
                <h3 style="color: var(--gold-accent); font-size: 1.35rem; margin: 0; font-weight: 800;">
                    &#127915; Step 1: Select Your Passes
                </h3>
                <span style="font-size: 0.85rem; color: var(--gold-light); font-weight: 700;">&#127869; Food included with ₹399 &amp; ₹699 passes</span>
            </div>

            <!-- Ticket Cards -->
            <div class="ticket-cards-list" style="margin-bottom: 25px; display: flex; flex-direction: column; gap: 14px;">
                <?php 
                    $firstActiveDone = false;
                    foreach ($categories as $idx => $cat): 
                        $isPre = ($cat['id'] == $preselectedCatId);
                        $isSoldOut = ($cat['available_quantity'] <= 0 || $cat['status'] === 'sold_out');
                        $maxAllowed = min(10, (int)$cat['available_quantity']);
                        $hasFood = ((float)$cat['price'] >= 350 || stripos($cat['name'], 'With Food') !== false);
                        
                        // Default first active category to quantity 1
                        $defaultQty = 0;
                        if (!$isSoldOut && !$firstActiveDone && ($preselectedCatId <= 0 || $isPre)) {
                            $defaultQty = 1;
                            $firstActiveDone = true;
                        }
                ?>
                    <div class="ticket-selection-mobile-card" style="background: rgba(26, 3, 14, 0.95); border: 1.5px solid <?= $defaultQty > 0 ? 'var(--gold-accent)' : 'rgba(245, 183, 34, 0.35)' ?>; border-radius: 14px; padding: 18px 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
                        <div class="head" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px;">
                            <div>
                                <h4 style="color: #fff; font-size: 1.18rem; font-weight: 800; margin: 0 0 4px;"><?= e($cat['name']) ?></h4>
                                <div style="font-size: 0.85rem; color: var(--gold-light); display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                    <span>&#128101;</span>
                                    <span>Admits <strong><?= (int)$cat['people_allowed'] ?> Person<?= $cat['people_allowed'] > 1 ? 's' : '' ?></strong></span>
                                    <span style="color: #666;">&bull;</span>
                                    <?php if ($hasFood): ?>
                                        <span style="color: #4ade80; font-weight: 700;">&#127869; Delicious Food Included</span>
                                    <?php else: ?>
                                        <span style="color: #f87171; font-weight: 700;">&#10060; No Food</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.8rem; color: <?= $cat['available_quantity'] < 25 ? '#f87171' : '#34d399' ?>; margin-top: 4px; font-weight: 600;">
                                    &#9889; <?= $isSoldOut ? 'Sold Out' : ((int)$cat['available_quantity'] . ' passes remaining') ?>
                                </div>
                            </div>
                            <div style="text-align: right; flex-shrink: 0;">
                                <div style="font-size: 1.55rem; font-weight: 900; color: var(--gold-accent);">
                                    <?= format_inr($cat['price']) ?>
                                </div>
                            </div>
                        </div>

                        <div class="bottom" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed rgba(245, 183, 34, 0.25); padding-top: 12px;">
                            <span style="font-size: 0.9rem; color: #cbd5e1; font-weight: 700;">Pass Quantity:</span>
                            <div>
                                <?php if ($isSoldOut): ?>
                                    <span style="color: #ef4444; font-weight: bold; font-size: 0.9rem; background: rgba(239,68,68,0.15); padding: 4px 12px; border-radius: 6px;">SOLD OUT</span>
                                <?php else: ?>
                                    <select name="quantities[<?= $cat['id'] ?>]" 
                                            class="form-control qty-select-field" 
                                            data-price="<?= (float)$cat['price'] ?>"
                                            data-people="<?= (int)$cat['people_allowed'] ?>"
                                            style="background: #141724; border: 1.5px solid var(--gold-accent); color: #fff; font-weight: 800; padding: 8px 18px; border-radius: 8px; cursor: pointer; font-size: 1.1rem; min-width: 90px; text-align: center;">
                                        <?php for ($i = 0; $i <= $maxAllowed; $i++): ?>
                                            <option value="<?= $i ?>" <?= $i === $defaultQty ? 'selected' : '' ?>><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- SECTION 2: ATTENDEE DETAILS -->
            <div style="background: rgba(20, 2, 11, 0.85); border: 1.5px solid rgba(245, 183, 34, 0.35); border-radius: 14px; padding: 22px 20px; margin-bottom: 25px;">
                <h3 style="color: var(--gold-accent); font-size: 1.3rem; margin: 0 0 16px; font-weight: 800;">
                    &#128100; Step 2: Attendee Details
                </h3>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="customer_name" style="color: #fff; font-weight: 700; font-size: 0.95rem;">
                        Your Full Name *
                    </label>
                    <input type="text" 
                           name="customer_name" 
                           id="customer_name" 
                           class="form-control" 
                           placeholder="Enter your name (e.g. Rahul Sharma)" 
                           value="<?= e($_SESSION['booking_customer']['customer_name'] ?? '') ?>" 
                           required 
                           style="padding: 13px 16px; font-size: 1.05rem; border-radius: 8px; background: #141724; border: 1.5px solid var(--gold-accent); color: #fff; width: 100%; box-sizing: border-box;">
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label" for="customer_phone" style="color: #fff; font-weight: 700; font-size: 0.95rem;">
                        WhatsApp / Mobile Number *
                    </label>
                    <input type="tel" 
                           name="customer_phone" 
                           id="customer_phone" 
                           class="form-control" 
                           placeholder="10-digit mobile number (e.g. 9876543210)" 
                           value="<?= e($_SESSION['booking_customer']['customer_phone'] ?? '') ?>" 
                           maxlength="15" 
                           required 
                           style="padding: 13px 16px; font-size: 1.05rem; border-radius: 8px; background: #141724; border: 1.5px solid var(--gold-accent); color: #fff; width: 100%; box-sizing: border-box;">
                    <small style="color: #cbd5e1; font-size: 0.8rem; margin-top: 5px; display: block;">
                        &#128241; Your digital QR ticket pass will be delivered to this mobile number.
                    </small>
                </div>
            </div>

            <!-- Total Calculation & Direct Action Bar -->
            <div style="background: linear-gradient(135deg, rgba(61,7,32,0.96), rgba(20,2,11,0.98)); border: 2px solid var(--gold-accent); border-radius: 12px; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; box-shadow: 0 8px 25px rgba(0,0,0,0.6);">
                <div>
                    <span style="color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; font-weight: 700;">Total Attendees:</span>
                    <strong id="live-people-count" style="color: #fff; margin-left: 6px; font-size: 1.1rem;">1 Person</strong>
                </div>
                <div style="font-size: 1.3rem; font-weight: 800; color: #fff;">
                    Total: <span id="live-total-price" style="color: var(--gold-accent); font-size: 1.85rem; font-weight: 900;">₹199</span>
                </div>
            </div>

            <!-- Big Golden Action Button -->
            <div>
                <button type="submit" id="step1-proceed-btn" class="btn btn-primary btn-block" style="padding: 16px; font-size: 1.25rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; width: 100%; border-radius: 10px; box-shadow: 0 6px 25px rgba(245,183,34,0.45); cursor: pointer;">
                    <span id="btn-text">&#9889; Proceed to Direct UPI Payment (₹199) &rarr;</span>
                </button>
            </div>

        </form>

    </div>
</div>

<script>
// Zero-Lag Client Side Real-Time Price Calculation
document.addEventListener('DOMContentLoaded', function() {
    const selects = document.querySelectorAll('.qty-select-field');
    const totalEl = document.getElementById('live-total-price');
    const peopleEl = document.getElementById('live-people-count');
    const proceedBtn = document.getElementById('step1-proceed-btn');
    const btnText = document.getElementById('btn-text');
    const stickyPrice = document.getElementById('sticky-bar-price');

    function calculateLive() {
        let grandTotal = 0;
        let totalPeople = 0;
        let totalTickets = 0;

        selects.forEach(select => {
            const qty = parseInt(select.value) || 0;
            const price = parseFloat(select.dataset.price) || 0;
            const people = parseInt(select.dataset.people) || 1;

            if (qty > 0) {
                grandTotal += (qty * price);
                totalPeople += (qty * people);
                totalTickets += qty;
            }
        });

        const formatted = '₹' + grandTotal.toLocaleString('en-IN');
        if (totalEl) totalEl.textContent = formatted;
        if (peopleEl) peopleEl.textContent = totalPeople + ' Person' + (totalPeople > 1 ? 's' : '');
        if (stickyPrice) stickyPrice.textContent = formatted;

        if (btnText) {
            if (grandTotal > 0) {
                btnText.innerHTML = '&#9889; Proceed to Direct UPI Payment (' + formatted + ') &rarr;';
            } else {
                btnText.innerHTML = '&#9889; Select at least 1 Pass to Proceed';
            }
        }

        if (proceedBtn) {
            if (totalTickets > 0) {
                proceedBtn.disabled = false;
                proceedBtn.style.opacity = '1';
                proceedBtn.style.cursor = 'pointer';
            } else {
                proceedBtn.disabled = true;
                proceedBtn.style.opacity = '0.6';
                proceedBtn.style.cursor = 'not-allowed';
            }
        }
    }

    selects.forEach(select => {
        select.addEventListener('change', calculateLive);
    });

    calculateLive();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
