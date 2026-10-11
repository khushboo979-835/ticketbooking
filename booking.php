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

// Handle Step 1 POST: Ticket Selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'select_tickets') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security session expired. Please refresh and try again.';
    } else {
        $quantities = $_POST['quantities'] ?? [];
        $selectedItems = [];
        $totalAmount = 0;
        $totalTickets = 0;
        $totalPeople = 0;

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

                    header("Location: " . BASE_URL . "/booking.php?event_id={$event['id']}&step=2");
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log('[booking.php step1 error]: ' . $e->getMessage());
            $errors[] = "Database connectivity issue while checking inventory. Please try again.";
        }
    }
}

// Handle Step 2 POST: Customer Details & Transition to Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_customer') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token expired. Please refresh the page.';
    } elseif (empty($_SESSION['booking_cart'])) {
        header("Location: " . BASE_URL . "/booking.php?event_id={$event['id']}&step=1");
        exit;
    } else {
        $name = trim($_POST['customer_name'] ?? '');
        $phone = preg_replace('/[^\d]/', '', trim($_POST['customer_phone'] ?? ''));
        $email = strtolower(trim($_POST['customer_email'] ?? ''));

        if (strlen($name) < 2) {
            $errors[] = 'Please enter your full legal name.';
        }
        if (strlen($phone) < 10) {
            $errors[] = 'Please enter a valid 10-digit mobile number for ticket dispatch.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (empty($errors)) {
            $_SESSION['booking_customer'] = [
                'customer_name'  => $name,
                'customer_phone' => $phone,
                'customer_email' => $email
            ];

            // Reset current booking ID to ensure fresh atomic reservation in payment.php
            unset($_SESSION['current_booking_uid']);
            unset($_SESSION['current_booking_id']);
            unset($_SESSION['current_qr_token']);

            header("Location: " . BASE_URL . "/payment.php");
            exit;
        }
    }
}

$cart = $_SESSION['booking_cart'] ?? null;
if ($step === 2 && !$cart) {
    header("Location: " . BASE_URL . "/booking.php?event_id={$event['id']}&step=1");
    exit;
}

$pageTitle = ($step === 1 ? 'Select Your Tickets' : 'Attendee Details') . ' | ' . e($event['title']);
$currentNav = 'garba';

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 35px; padding-bottom: 70px;">

    <!-- Top Step Progression Indicator -->
    <div style="display: flex; justify-content: center; margin-bottom: 30px;">
        <div style="display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 0.95rem;">
            <a href="<?= BASE_URL ?>/booking.php?event_id=<?= $event['id'] ?>&step=1" style="background: <?= $step === 1 ? 'var(--gold-accent)' : 'rgba(245,183,34,0.2)' ?>; color: <?= $step === 1 ? '#1f0411' : 'var(--gold-light)' ?>; padding: 6px 16px; border-radius: 50px;">
                1. Select Tickets
            </a>
            <span style="color: #666;">&rarr;</span>
            <span style="background: <?= $step === 2 ? 'var(--gold-accent)' : 'rgba(255,255,255,0.1)' ?>; color: <?= $step === 2 ? '#1f0411' : '#aaa' ?>; padding: 6px 16px; border-radius: 50px;">
                2. Customer Details
            </span>
            <span style="color: #666;">&rarr;</span>
            <span style="background: rgba(255,255,255,0.1); color: #aaa; padding: 6px 16px; border-radius: 50px;">
                3. Payment
            </span>
        </div>
    </div>

    <!-- Error Alerts -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="max-width: 800px; margin: 0 auto 25px;">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Main Container Card (Layout matching User Infographic Box 2 & Box 3) -->
    <div class="festive-card" style="max-width: 860px; margin: 0 auto; border: 2px solid var(--gold-accent); padding: 30px 25px;">

        <!-- Event Header Banner Section -->
        <div style="display: flex; align-items: center; gap: 20px; border-bottom: 1px solid rgba(245,183,34,0.3); padding-bottom: 20px; margin-bottom: 25px; flex-wrap: wrap;">
            <img src="<?= BASE_URL ?>/assets/uploads/garba-banner.jpg" alt="<?= e($event['title']) ?>" style="width: 140px; height: 90px; object-fit: cover; border-radius: 8px; border: 1px solid var(--gold-accent);" onerror="this.style.display='none'">
            <div style="flex: 1; min-width: 250px;">
                <span class="hero-badge" style="margin-bottom: 4px; padding: 3px 10px; font-size: 0.75rem;">Millennium India Group</span>
                <h1 style="font-size: 1.8rem; font-weight: 900; color: #fff; margin: 2px 0 6px;"><?= e($event['title']) ?></h1>
                <div style="color: var(--gold-light); font-size: 0.92rem; display: flex; flex-wrap: wrap; gap: 15px;">
                    <span>&#128197; <?= date('d F Y (l)', strtotime($event['date'])) ?></span>
                    <span>&#9200; <?= date('h:i A', strtotime($event['start_time'])) ?> &ndash; <?= date('h:i A', strtotime($event['end_time'])) ?></span>
                    <span>&#128205; <?= e($event['venue']) ?></span>
                </div>
            </div>
        </div>

        <?php if ($step === 1): ?>
            <!-- STEP 1: SELECT YOUR TICKETS (Matches Infographic Box 2) -->
            <form action="<?= BASE_URL ?>/booking.php?event_id=<?= $event['id'] ?>&step=1" method="POST" id="ticket-selection-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="select_tickets">

                <h3 style="color: var(--gold-accent); font-size: 1.3rem; margin-bottom: 16px;">Select Your Tickets</h3>

                <!-- Desktop Ticket Table -->
                <div class="table-responsive desktop-only-view" style="margin-bottom: 25px;">
                    <table class="admin-table" style="background: rgba(20,2,11,0.6); border-radius: 8px; overflow: hidden; width: 100%;">
                        <thead>
                            <tr style="background: rgba(122,18,68,0.4);">
                                <th style="color: var(--gold-light); font-size: 0.95rem;">Ticket Type</th>
                                <th style="color: var(--gold-light); font-size: 0.95rem; text-align: center;">Admits</th>
                                <th style="color: var(--gold-light); font-size: 0.95rem; text-align: right;">Price</th>
                                <th style="color: var(--gold-light); font-size: 0.95rem; text-align: center; width: 140px;">Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <?php 
                                    $isPre = ($cat['id'] == $preselectedCatId);
                                    $prevQty = 0;
                                    if ($cart && isset($cart['items'][$cat['id']])) {
                                        $prevQty = $cart['items'][$cat['id']]['quantity'];
                                    } elseif ($isPre) {
                                        $prevQty = 1;
                                    }
                                    $maxAllowed = min(10, (int)$cat['available_quantity']);
                                    $isSoldOut = ($cat['available_quantity'] <= 0 || $cat['status'] === 'sold_out');
                                ?>
                                <tr>
                                    <td>
                                        <strong style="color: #fff; font-size: 1.05rem;"><?= e($cat['name']) ?></strong>
                                        <div style="font-size: 0.8rem; color: <?= $cat['available_quantity'] < 25 ? '#f87171' : '#34d399' ?>;">
                                            <?= $isSoldOut ? 'Sold Out' : ((int)$cat['available_quantity'] . ' passes remaining') ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center; color: #ddd; font-weight: 600;">
                                        <?= (int)$cat['people_allowed'] ?> Person<?= $cat['people_allowed'] > 1 ? 's' : '' ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 800; color: var(--gold-accent); font-size: 1.15rem;">
                                        <?= format_inr($cat['price']) ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($isSoldOut): ?>
                                            <span style="color: #ef4444; font-weight: bold; font-size: 0.85rem;">SOLD OUT</span>
                                        <?php else: ?>
                                            <select name="quantities[<?= $cat['id'] ?>]" 
                                                    class="form-control qty-select-field" 
                                                    data-price="<?= (float)$cat['price'] ?>"
                                                    data-people="<?= (int)$cat['people_allowed'] ?>"
                                                    style="background: #12141d; border: 1px solid var(--gold-dark); color: #fff; font-weight: bold; padding: 8px 12px; border-radius: 6px; cursor: pointer; width: 100%;">
                                                <?php for ($i = 0; $i <= $maxAllowed; $i++): ?>
                                                    <option value="<?= $i ?>" <?= $i === $prevQty ? 'selected' : '' ?>><?= $i ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile-Optimized Ticket Cards (Zero Overlap & Touch Friendly) -->
                <div class="mobile-only-cards" style="display: none; margin-bottom: 20px;">
                    <?php foreach ($categories as $cat): ?>
                        <?php 
                            $isPre = ($cat['id'] == $preselectedCatId);
                            $prevQty = 0;
                            if ($cart && isset($cart['items'][$cat['id']])) {
                                $prevQty = $cart['items'][$cat['id']]['quantity'];
                            } elseif ($isPre) {
                                $prevQty = 1;
                            }
                            $maxAllowed = min(10, (int)$cat['available_quantity']);
                            $isSoldOut = ($cat['available_quantity'] <= 0 || $cat['status'] === 'sold_out');
                        ?>
                        <div class="ticket-selection-mobile-card">
                            <div class="head">
                                <div>
                                    <strong style="color: #fff; font-size: 1.1rem;"><?= e($cat['name']) ?></strong>
                                    <div style="font-size: 0.8rem; color: var(--gold-light); margin-top: 3px;">
                                        &#128101; Admits <?= (int)$cat['people_allowed'] ?> Person<?= $cat['people_allowed'] > 1 ? 's' : '' ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: <?= $cat['available_quantity'] < 25 ? '#f87171' : '#34d399' ?>; margin-top: 2px;">
                                        <?= $isSoldOut ? 'Sold Out' : ((int)$cat['available_quantity'] . ' passes remaining') ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 1.3rem; font-weight: 900; color: var(--gold-accent);">
                                        <?= format_inr($cat['price']) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="bottom">
                                <span style="font-size: 0.85rem; color: #cbd5e1; font-weight: 600;">Select Quantity:</span>
                                <div>
                                    <?php if ($isSoldOut): ?>
                                        <span style="color: #ef4444; font-weight: bold; font-size: 0.85rem;">SOLD OUT</span>
                                    <?php else: ?>
                                        <select name="quantities[<?= $cat['id'] ?>]" 
                                                class="form-control qty-select-field" 
                                                data-price="<?= (float)$cat['price'] ?>"
                                                data-people="<?= (int)$cat['people_allowed'] ?>"
                                                style="background: #12141d; border: 1px solid var(--gold-accent); color: #fff; font-weight: bold; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-size: 1.05rem;">
                                            <?php for ($i = 0; $i <= $maxAllowed; $i++): ?>
                                                <option value="<?= $i ?>" <?= $i === $prevQty ? 'selected' : '' ?>><?= $i ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Total Calculation Bar -->
                <div style="background: rgba(31,4,17,0.9); border: 1px solid var(--gold-accent); border-radius: 10px; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <span style="color: var(--text-muted); font-size: 0.9rem;">Total Entry Count:</span>
                        <strong id="live-people-count" style="color: #fff; margin-left: 6px;">0 Persons</strong>
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 900; color: #fff;">
                        Total Amount: <span id="live-total-price" style="color: var(--gold-accent);">₹0</span>
                    </div>
                </div>

                <div>
                    <button type="submit" id="step1-proceed-btn" class="btn btn-primary btn-block" style="padding: 15px; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.5px;">
                        Proceed to Customer Details &rarr;
                    </button>
                </div>
            </form>

        <?php else: ?>
            <!-- STEP 2: CUSTOMER DETAILS (Matches Infographic Box 3) -->
            <form action="<?= BASE_URL ?>/booking.php?event_id=<?= $event['id'] ?>&step=2" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_customer">

                <h3 style="color: var(--gold-accent); font-size: 1.3rem; margin-bottom: 16px;">Customer Details</h3>

                <div class="form-group">
                    <label class="form-label" for="customer_name">Full Name *</label>
                    <input type="text" name="customer_name" id="customer_name" class="form-control" placeholder="Enter your full name" value="<?= e($_SESSION['booking_customer']['customer_name'] ?? '') ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="customer_phone">Mobile Number *</label>
                    <input type="tel" name="customer_phone" id="customer_phone" class="form-control" placeholder="Enter mobile number" value="<?= e($_SESSION['booking_customer']['customer_phone'] ?? '') ?>" maxlength="15" required>
                    <small style="color: #aaa; font-size: 0.8rem; margin-top: 4px; display: block;">Official QR pass and WhatsApp entry updates will be dispatched to this number.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="customer_email">Email Address *</label>
                    <input type="email" name="customer_email" id="customer_email" class="form-control" placeholder="Enter email address" value="<?= e($_SESSION['booking_customer']['customer_email'] ?? '') ?>" required>
                </div>

                <!-- Order Summary Mini Pill -->
                <div style="background: rgba(20,2,11,0.6); border: 1px dashed var(--gold-accent); border-radius: 8px; padding: 14px 18px; margin: 20px 0 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                    <div style="font-size: 0.9rem; color: #ddd;">
                        <strong><?= $cart['total_tickets'] ?> Ticket(s)</strong> &bull; Total Entry: <strong><?= $cart['total_people'] ?> Persons</strong>
                    </div>
                    <div style="font-size: 1.3rem; font-weight: 900; color: var(--gold-accent);">
                        <?= format_inr($cart['total_amount']) ?>
                    </div>
                </div>

                <div style="display: flex; gap: 15px;">
                    <a href="<?= BASE_URL ?>/booking.php?event_id=<?= $event['id'] ?>&step=1" class="btn btn-secondary" style="flex: 1;">
                        &larr; Back
                    </a>
                    <button type="submit" class="btn btn-primary" style="flex: 2; padding: 14px; font-size: 1.1rem; text-transform: uppercase;">
                        Proceed to Payment &rarr;
                    </button>
                </div>
            </form>
        <?php endif; ?>

    </div>
</div>

<script>
// Zero-Lag Client Side Real-Time Price Calculation
document.addEventListener('DOMContentLoaded', function() {
    const selects = document.querySelectorAll('.qty-select-field');
    const totalEl = document.getElementById('live-total-price');
    const peopleEl = document.getElementById('live-people-count');
    const proceedBtn = document.getElementById('step1-proceed-btn');

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

        if (totalEl) totalEl.textContent = '₹' + grandTotal.toLocaleString('en-IN', { minimumFractionDigits: 0 });
        if (peopleEl) peopleEl.textContent = totalPeople + ' Person' + (totalPeople > 1 ? 's' : '');

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
