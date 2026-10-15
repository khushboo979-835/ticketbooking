<?php
// index.php - Millennium India Group Homepage & Jashn-E-Garba 2026 Showcase
ob_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

send_no_cache_headers();

$pageTitle = 'Jashn-E-Garba 2026 | Official Ticket Booking | Millennium India Group';
$currentNav = 'home';

$event = get_event(1); // Priority Launch Event
$categories = $event ? get_event_categories($event['id']) : [];

ensure_seat_inventory_table();
$allSeats = get_all_seats(1);
$seatSummary = get_seat_summary(1);

// Group seats by tier and row for the BookMyShow visual grid
$tieredRows = [];
foreach ($allSeats as $s) {
    $tier = $s['category_name'] . ' &bull; ' . format_inr($s['tier_price']);
    $rName = $s['row_name'];
    $tieredRows[$tier][$rName][] = $s;
}

// Handle Direct Seat Selection Checkout from Homepage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'seat_checkout') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session token expired. Please refresh and try again.');
        header("Location: " . BASE_URL . "/index.php#bms-seat-arena");
        exit;
    } else {
        $selectedSeatsStr = trim($_POST['selected_seats'] ?? '');
        $seatCodes = array_values(array_filter(array_map('trim', explode(',', $selectedSeatsStr))));
        $db = getDB();

        if (empty($seatCodes)) {
            set_flash('error', 'Please tap on at least one available green seat on the seating layout to book.');
            header("Location: " . BASE_URL . "/index.php#bms-seat-arena");
            exit;
        }

        $inClause = implode(',', array_fill(0, count($seatCodes), '?'));
        $chkStmt = $db->prepare("SELECT * FROM event_seats WHERE event_id = 1 AND seat_code IN ($inClause)");
        $chkStmt->execute(array_values($seatCodes));
        $foundSeats = $chkStmt->fetchAll() ?: [];

        if (empty($foundSeats)) {
            set_flash('error', 'Selected seats could not be found. Please choose available seats on the map.');
            header("Location: " . BASE_URL . "/index.php#bms-seat-arena");
            exit;
        }

        $totalAmount = 0.0;
        $totalTickets = count($foundSeats);
        $selectedItems = [];
        $totalPeople = 0;
        $seatErr = '';

        foreach ($foundSeats as $sRow) {
            if ($sRow['status'] !== 'available') {
                $seatErr = "Seat {$sRow['seat_code']} is already booked. Please choose an available seat.";
                break;
            }
            $cId = (int)$sRow['category_id'];
            $sPrice = (float)$sRow['tier_price'];
            $totalAmount += $sPrice;

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

        if (!empty($seatErr)) {
            set_flash('error', $seatErr);
            header("Location: " . BASE_URL . "/index.php#bms-seat-arena");
            exit;
        }

        $rawName = trim($_POST['customer_name'] ?? '');
        $name = !empty($rawName) ? $rawName : 'Garba Attendee';

        $rawPhone = preg_replace('/[^\d]/', '', trim($_POST['customer_phone'] ?? ''));
        if (strlen($rawPhone) > 10 && str_starts_with($rawPhone, '91')) {
            $rawPhone = substr($rawPhone, 2);
        }
        $phone = (strlen($rawPhone) >= 10) ? substr($rawPhone, -10) : '7004665804';
        $email = $phone . '@millenniumindiagroup.com';

        $_SESSION['booking_cart'] = [
            'event_id'      => $event['id'] ?? 1,
            'event_title'   => $event['title'] ?? 'Jashn-E-Garba 2026',
            'event_date'    => $event['date'] ?? '2026-10-16',
            'event_venue'   => $event['venue'] ?? 'Unique Garden, Patna',
            'start_time'    => $event['start_time'] ?? '17:00:00',
            'end_time'      => $event['end_time'] ?? '21:00:00',
            'items'         => $selectedItems,
            'seat_numbers'  => implode(', ', $seatCodes),
            'total_amount'  => (float)$totalAmount,
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

include __DIR__ . '/includes/header.php';
?>

<!-- 1. VERY TOP SECTION: ONLY VIDEO 0 (Client: Or ek hi video rakhiy, Only ye) -->
<section style="background: linear-gradient(180deg, #18020e 0%, #2a0319 50%, #16010c 100%); padding: 18px 0 28px; border-bottom: 2px solid rgba(245, 183, 34, 0.3);">
    <div class="container" style="text-align: center;">

        <!-- Featured Video 0 Player -->
        <div style="max-width: 880px; margin: 0 auto; background: #0d0107; border: 2.5px solid var(--gold-accent); border-radius: 16px; overflow: hidden; box-shadow: 0 15px 45px rgba(0,0,0,0.85);">
            <div style="padding: 12px 18px; background: rgba(30,3,17,0.95); display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(245,183,34,0.25); flex-wrap: wrap; gap: 8px;">
                <div style="text-align: left;">
                    <span style="background: var(--gold-accent); color: #1f0411; font-weight: 800; font-size: 0.72rem; padding: 2px 10px; border-radius: 20px; text-transform: uppercase;">
                        &#9654; Official Teaser
                    </span>
                    <strong style="color: #fff; font-size: 1rem; margin-left: 6px;">&#127915; Jashn-E-Garba 2026 Promo Video</strong>
                </div>
                <button type="button" onclick="playWithSound('top-v0-player')" id="btn-sound-v0" style="background: rgba(245,183,34,0.2); border: 1.5px solid var(--gold-accent); color: var(--gold-accent); font-weight: 800; font-size: 0.85rem; padding: 6px 16px; border-radius: 20px; cursor: pointer; transition: all 0.2s ease;">
                    &#128266; Tap For Full Sound
                </button>
            </div>

            <!-- Video 0 autoplays on page load with sound toggle -->
            <video id="top-v0-player" controls autoplay muted loop playsinline preload="auto" style="width: 100%; display: block; aspect-ratio: 16/9; max-height: 480px; object-fit: cover; background: #000;">
                <source src="<?= BASE_URL ?>/assets/uploads/Video0.mp4" type="video/mp4">
                Your browser does not support HTML5 video.
            </video>

            <div style="padding: 10px 16px; background: rgba(20,2,11,0.95); text-align: center; font-size: 0.88rem; color: var(--gold-light); font-weight: 600;">
                &#127915; Patna's Biggest Dandiya Mahotsav &bull; 16 October 2026 &bull; Unique Garden, Bailey Road
            </div>
        </div>

        <!-- Immediate Action CTAs Under Top Video Section -->
        <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; margin-top: 20px;">
            <a href="<?= BASE_URL ?>/booking.php?event_id=<?= $event['id'] ?? 1 ?>" class="btn btn-primary" style="font-size: 1.12rem; padding: 14px 34px; box-shadow: 0 6px 20px rgba(245,183,34,0.4); font-weight: 800;">
                &#127915; Book Passes Now (From ₹199)
            </a>
            <a href="<?= BASE_URL ?>/ticket.php" class="btn btn-secondary" style="font-size: 1rem; padding: 14px 22px; font-weight: 700;">
                &#128269; Search My Ticket / Receipt
            </a>
            <a href="tel:7004665804" class="btn btn-secondary" style="font-size: 1rem; padding: 14px 22px; font-weight: 700;">
                &#128222; Helpline: +91 7004665804
            </a>
        </div>

        <!-- 1-Click Instant Pass & Receipt Lookup (BookMyShow-Style) -->
        <div style="max-width: 680px; margin: 22px auto 0; background: rgba(18,2,11,0.92); border: 1.5px solid var(--gold-accent); border-radius: 14px; padding: 16px 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.6);">
            <div style="font-size: 0.95rem; font-weight: 800; color: #fff; margin-bottom: 8px;">
                &#127915; Already Paid? Enter Mobile Number, Booking ID or UTR to Download Pass:
            </div>
            <form action="<?= BASE_URL ?>/ticket.php" method="GET" style="display: flex; gap: 8px; flex-wrap: wrap;">
                <input type="text" 
                       name="search" 
                       placeholder="Enter 10-Digit Mobile, Booking ID (e.g. MIG2026...) or UTR..." 
                       required
                       style="flex: 1; min-width: 220px; padding: 12px 14px; font-size: 1rem; border-radius: 8px; background: #0a0106; border: 1.5px solid var(--gold-accent); color: #fff; box-sizing: border-box;">
                <button type="submit" class="btn btn-primary" style="padding: 12px 22px; font-weight: 800; font-size: 0.95rem; border-radius: 8px; cursor: pointer; white-space: nowrap;">
                    &#128269; Download Pass &rarr;
                </button>
            </form>
        </div>

    </div>
</section>

<!-- Main Container Content -->
<div class="container" style="padding-top: 35px; padding-bottom: 60px;">

    <!-- 2. OFFICIAL BOARDING PASSES & EVENT SHOWCASE (5 Official Ticket Designs) -->
    <?php
    $officialPasses = [
        [
            'id'          => 1,
            'name'        => 'Single Entry (No Food)',
            'price'       => 199,
            'persons'     => 1,
            'food'        => false,
            'food_badge'  => 'No Food',
            'badge_color' => '#ef4444',
            'theme_color' => '#dc2626',
            'row'         => 'H',
            'image'       => 'assets/uploads/ticket-single-199.png',
            'tag'         => 'Single &bull; Economy'
        ],
        [
            'id'          => 2,
            'name'        => 'Couple Entry (No Food)',
            'price'       => 299,
            'persons'     => 2,
            'food'        => false,
            'food_badge'  => 'No Food',
            'badge_color' => '#ef4444',
            'theme_color' => '#7c3aed',
            'row'         => 'E',
            'image'       => 'assets/uploads/ticket-couple-299.png',
            'tag'         => 'Couple &bull; Prime'
        ],
        [
            'id'          => 3,
            'name'        => 'Single Entry (With Food Included)',
            'price'       => 399,
            'persons'     => 1,
            'food'        => true,
            'food_badge'  => 'Dinner Included',
            'badge_color' => '#22c55e',
            'theme_color' => '#16a34a',
            'row'         => 'C',
            'image'       => 'assets/uploads/ticket-single-food-399.png',
            'tag'         => 'Single &bull; Food Included'
        ],
        [
            'id'          => 4,
            'name'        => 'Couple Entry (With Food Included)',
            'price'       => 699,
            'persons'     => 2,
            'food'        => true,
            'food_badge'  => 'Dinner Included',
            'badge_color' => '#22c55e',
            'theme_color' => '#b91c1c',
            'row'         => 'A',
            'image'       => 'assets/uploads/ticket-couple-food-699.png',
            'tag'         => 'Couple &bull; VIP Food'
        ],
        [
            'id'          => 5,
            'name'        => 'Family Entry (With Food Included)',
            'price'       => 1299,
            'persons'     => 4,
            'food'        => true,
            'food_badge'  => 'Family Feast (4 Persons)',
            'badge_color' => '#22c55e',
            'theme_color' => '#2563eb',
            'row'         => 'VIP',
            'image'       => 'assets/uploads/ticket-family-food-1299.png',
            'tag'         => 'Family VIP &bull; 4 Persons'
        ],
    ];
    ?>

    <div class="festive-card" style="margin-bottom: 35px; padding: 25px 20px;">
        <div style="text-align: center; margin-bottom: 20px;">
            <span class="hero-badge" style="display:inline-block; margin-bottom: 6px;">Official Millennium India Group Tickets</span>
            <h1 style="font-size: 2.2rem; font-weight: 900; color: #fff; margin: 4px 0 8px;">
                <?= e($event['title'] ?? 'Jashn-E-Garba 2026') ?>
            </h1>
            <p style="color: var(--gold-light); font-size: 1.05rem; font-weight: 700; margin: 0;">
                Official BookMyShow Passes &bull; 5 Exclusive Pass Categories
            </p>
        </div>

        <!-- 5 Official Passes Quick Switcher Tabs -->
        <div style="display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; margin-bottom: 20px;" id="pass-tab-bar">
            <?php foreach ($officialPasses as $idx => $p): ?>
                <button type="button" 
                        onclick="switchOfficialPass(<?= $idx ?>)" 
                        id="pass-tab-btn-<?= $idx ?>" 
                        class="pass-tab-button <?= $idx === 0 ? 'active' : '' ?>"
                        style="background: <?= $idx === 0 ? 'var(--gold-accent)' : 'rgba(20,2,11,0.85)' ?>; color: <?= $idx === 0 ? '#1f0411' : '#cbd5e1' ?>; border: 1.5px solid <?= $idx === 0 ? 'var(--gold-accent)' : 'rgba(245,183,34,0.3)' ?>; font-weight: 800; font-size: 0.85rem; padding: 8px 16px; border-radius: 50px; cursor: pointer; transition: all 0.2s ease;">
                    <?= $p['price'] == 199 ? 'Single ₹199' : ($p['price'] == 299 ? 'Couple ₹299' : ($p['price'] == 399 ? 'Single+Food ₹399' : ($p['price'] == 699 ? 'Couple+Food ₹699' : 'Family+Food ₹1299'))) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Active Boarding Pass Hero Showcase -->
        <div style="max-width: 920px; margin: 0 auto 25px; background: #000; border-radius: 16px; overflow: hidden; border: 2.5px solid var(--gold-accent); box-shadow: 0 15px 45px rgba(0,0,0,0.85); position: relative;">
            
            <div id="active-pass-container" style="transition: opacity 0.25s ease;">
                <img id="active-pass-img" 
                     src="<?= asset_v($officialPasses[0]['image']) ?>" 
                     alt="<?= e($officialPasses[0]['name']) ?>" 
                     style="width: 100%; height: auto; display: block; object-fit: contain; cursor: pointer;"
                     onclick="openPassModal(this.src, document.getElementById('active-pass-title').innerText)">
            </div>

            <!-- Pass Info Strip Under Active Image -->
            <div style="background: rgba(22, 2, 13, 0.96); padding: 16px 20px; border-top: 1.5px solid rgba(245, 183, 34, 0.35); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span id="active-pass-tag" style="background: #e11d48; color: #fff; font-size: 0.72rem; font-weight: 800; padding: 2px 10px; border-radius: 12px; text-transform: uppercase;">
                            <?= $officialPasses[0]['tag'] ?>
                        </span>
                        <span id="active-pass-food-badge" style="background: rgba(239,68,68,0.2); color: #f87171; border: 1px solid #ef4444; font-size: 0.72rem; font-weight: 800; padding: 2px 10px; border-radius: 12px;">
                            <?= $officialPasses[0]['food_badge'] ?>
                        </span>
                    </div>
                    <h3 id="active-pass-title" style="color: #fff; font-size: 1.35rem; font-weight: 900; margin: 6px 0 2px;">
                        <?= $officialPasses[0]['name'] ?>
                    </h3>
                    <div style="font-size: 0.85rem; color: #cbd5e1;">
                        &#128197; 16 Oct 2026 &bull; &#128205; Unique Garden, Patna &bull; Permits entry for <strong id="active-pass-admit" style="color:#ffd966;"><?= $officialPasses[0]['persons'] ?> Person</strong>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                    <div style="text-align: right;">
                        <span style="font-size: 0.76rem; color: #94a3b8; text-transform: uppercase; font-weight: 700;">Official Pass Rate</span>
                        <div id="active-pass-price" style="font-size: 2rem; font-weight: 900; color: var(--gold-accent); line-height: 1;">
                            ₹<?= $officialPasses[0]['price'] ?>/-
                        </div>
                    </div>
                    <button type="button" 
                            id="btn-active-pass-book" 
                            onclick="selectTierAndScroll('<?= $officialPasses[0]['row'] ?>')" 
                            class="btn-bms-red" 
                            style="padding: 12px 22px; font-size: 0.95rem; font-weight: 800; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <span>&#9889; Book This Pass Live</span> <span>&darr;</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 5 Passes Mini-Card Grid (All Visible on Mobile & Desktop) -->
        <div style="margin-top: 25px; margin-bottom: 25px;">
            <div style="text-align: center; margin-bottom: 14px;">
                <span style="font-size: 0.84rem; font-weight: 800; color: var(--gold-accent); text-transform: uppercase; letter-spacing: 0.5px;">
                    &#127915; All 5 Official Pass Tiers Available for Instant Booking:
                </span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; max-width: 940px; margin: 0 auto;">
                <?php foreach ($officialPasses as $idx => $pass): ?>
                    <div style="background: rgba(18, 2, 11, 0.9); border: 1.5px solid rgba(245, 183, 34, 0.35); border-radius: 12px; padding: 10px; cursor: pointer; transition: all 0.2s ease; text-align: center;" 
                         onclick="switchOfficialPass(<?= $idx ?>); document.getElementById('pass-tab-bar').scrollIntoView({behavior:'smooth'});"
                         onmouseover="this.style.borderColor='var(--gold-accent)'; this.style.transform='translateY(-2px)';"
                         onmouseout="this.style.borderColor='rgba(245, 183, 34, 0.35)'; this.style.transform='translateY(0)';"
                         title="Click to view full pass details">
                        <div style="border-radius: 8px; overflow: hidden; margin-bottom: 8px; border: 1px solid rgba(255,255,255,0.1);">
                            <img src="<?= asset_v($pass['image']) ?>" alt="<?= e($pass['name']) ?>" style="width: 100%; height: auto; display: block; aspect-ratio: 16/9; object-fit: cover;">
                        </div>
                        <div style="font-weight: 800; font-size: 0.85rem; color: #fff; margin-bottom: 2px;">
                            <?= e($pass['name']) ?>
                        </div>
                        <div style="font-size: 1.15rem; font-weight: 900; color: var(--gold-accent);">
                            ₹<?= $pass['price'] ?>/-
                        </div>
                        <div style="font-size: 0.72rem; color: <?= $pass['food'] ? '#34d399' : '#f87171' ?>; font-weight: 700; margin: 2px 0 6px;">
                            <?= $pass['food'] ? '&#127869; Food Included' : '&#10060; No Food' ?>
                        </div>
                        <button type="button" 
                                onclick="event.stopPropagation(); selectTierAndScroll('<?= $pass['row'] ?>');"
                                style="width: 100%; background: var(--bms-red); color: #fff; border: none; font-weight: 800; font-size: 0.74rem; padding: 5px 8px; border-radius: 6px; cursor: pointer;">
                            Book Seats &darr;
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Event Key Meta Highlights -->
        <div class="hero-meta-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-top: 15px;">
            <div class="hero-meta-card" style="padding: 14px 18px;">
                <span style="font-size: 1.7rem;">&#128197;</span>
                <div>
                    <strong>Date &amp; Timing:</strong>
                    <div style="color: #fff; font-weight: 700;"><?= !empty($event['date']) ? date('l, d F Y', strtotime($event['date'])) : 'Friday, 16 October 2026' ?> &bull; <?= !empty($event['start_time']) ? date('h:i A', strtotime($event['start_time'])) : '05:00 PM' ?> Onwards</div>
                </div>
            </div>

            <div class="hero-meta-card" style="padding: 14px 18px;">
                <span style="font-size: 1.7rem;">&#128205;</span>
                <div>
                    <strong>Venue:</strong>
                    <div style="color: #fff; font-weight: 700;"><?= e($event['venue'] ?? 'Unique Garden, Pillar No. 1, Bailey Road, Patna') ?></div>
                </div>
            </div>

            <div class="hero-meta-card" style="padding: 14px 18px;">
                <span style="font-size: 1.7rem;">&#127869;</span>
                <div>
                    <strong>Food Privilege:</strong>
                    <div style="color: var(--gold-accent); font-weight: 800;">Food Included with Passes of ₹399, ₹699 &amp; ₹1299</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. OFFICIAL DIRECT UPI PAYMENT CARD -->
    <div class="festive-card" style="margin-bottom: 35px; border: 2.5px solid var(--gold-accent); background: linear-gradient(135deg, rgba(45,5,24,0.98), rgba(15,2,9,0.99)); padding: 25px 22px; border-radius: 16px; box-shadow: 0 10px 35px rgba(0,0,0,0.65);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 18px; border-bottom: 1.5px dashed rgba(245,183,34,0.3); padding-bottom: 14px;">
            <div>
                <span style="background: var(--gold-accent); color: #1f0411; font-weight: 800; font-size: 0.76rem; padding: 4px 14px; border-radius: 20px; text-transform: uppercase;">
                    &#9889; Official Direct UPI Transfer &bull; Zero Convenience Fee
                </span>
                <h3 style="font-size: 1.45rem; color: #fff; margin: 8px 0 0; font-weight: 900;">
                    Pay Directly via UPI ID: ashrafkhan0777-2@oksbi
                </h3>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.88rem; color: #cbd5e1;">Payee: <strong style="color: #fff; font-size: 1rem;">Ashraf Ali</strong> &bull; <span style="color:#ffd966;">Jana Small Finance 9105</span></div>
                <div style="display: flex; align-items: center; gap: 8px; justify-content: flex-end; margin-top: 4px;">
                    <code id="upi-id-text" style="color: var(--gold-accent); font-size: 0.95rem; font-weight: 800; background: rgba(0,0,0,0.5); padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(245,183,34,0.4);">ashrafkhan0777-2@oksbi</code>
                    <button type="button" onclick="copyUpiId()" id="btn-copy-upi" style="background: var(--gold-accent); color: #1f0411; border: none; font-weight: 800; font-size: 0.78rem; padding: 5px 12px; border-radius: 6px; cursor: pointer;">
                        Copy UPI
                    </button>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; align-items: center;">
            
            <!-- Left: Official UPI ID Account Card (No Scanner) -->
            <div style="text-align: center; background: linear-gradient(135deg, #0f172a, #1e293b); padding: 22px 18px; border-radius: 14px; border: 2px solid var(--gold-accent); box-shadow: 0 8px 25px rgba(0,0,0,0.4); max-width: 360px; margin: 0 auto; width: 100%; box-sizing: border-box;">
                <div style="font-size: 0.8rem; color: #ffd966; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;">
                    &#9889; Official Bank Account
                </div>
                <div style="font-size: 1.4rem; font-weight: 900; color: #fff; margin: 6px 0 2px;">
                    Ashraf Ali
                </div>
                <div style="font-size: 0.82rem; color: #94a3b8; margin-bottom: 14px;">
                    Jana Small Finance Bank (A/C: ...9105)
                </div>

                <div style="background: rgba(0,0,0,0.45); border: 1.5px solid var(--gold-accent); border-radius: 10px; padding: 12px; margin-bottom: 12px;">
                    <div style="font-size: 0.74rem; color: #cbd5e1; text-transform: uppercase; font-weight: 700;">Option 1: Direct Payee UPI ID</div>
                    <div style="font-size: 1.05rem; font-family: monospace; font-weight: 900; color: #ffd966; margin: 4px 0 8px; word-break: break-all;">
                        ashrafkhan0777-2@oksbi
                    </div>
                    <button type="button" onclick="copyUpiId()" class="btn btn-primary" style="padding: 7px 18px; font-size: 0.82rem; font-weight: 800; width: 100%;">
                        &#128203; Copy UPI ID
                    </button>
                </div>

                <div style="background: rgba(14,165,233,0.15); border: 1.5px solid #38bdf8; border-radius: 10px; padding: 12px; margin-bottom: 14px;">
                    <div style="font-size: 0.74rem; color: #7dd3fc; text-transform: uppercase; font-weight: 700;">Option 2: Pay to Mobile (PhonePe / GPay)</div>
                    <div style="font-size: 1.15rem; font-family: monospace; font-weight: 900; color: #fff; margin: 4px 0 8px;">
                        7004665804
                    </div>
                    <button type="button" onclick="copyIndexMobile()" id="btn-copy-index-mob" class="btn" style="padding: 7px 18px; font-size: 0.82rem; font-weight: 800; width: 100%; background: #38bdf8; color: #082f49; border: none; cursor: pointer;">
                        &#128203; Copy Mobile Number
                    </button>
                </div>

                <div style="font-size: 0.78rem; color: #94a3b8; line-height: 1.5;">
                    &#10004; Transfer via PhonePe, Google Pay, Paytm, or BHIM &bull; Paste UTR on right to get pass
                </div>
            </div>

            <!-- Right: Dynamic Pricing Guide + 1-Click Ticket Claim by UTR -->
            <div>
                <p style="font-size: 0.95rem; color: #e2e8f0; margin: 0 0 14px; line-height: 1.6;">
                    Pay directly using <strong>Google Pay, PhonePe, Paytm, BHIM, or any UPI App</strong> to UPI ID <code style="color:var(--gold-accent); font-weight:800;">ashrafkhan0777-2@oksbi</code>. Official passes pricing:
                </p>

                <ul style="color: #cbd5e1; font-size: 0.88rem; padding-left: 18px; margin: 0 0 18px; line-height: 1.7;">
                    <li>&#127915; <strong>Single Entry (No Food)</strong>: ₹199/- (Admit 1)</li>
                    <li>&#127915; <strong>Couple Entry (No Food)</strong>: ₹299/- (Admit 2)</li>
                    <li>&#127869; <strong>Single Entry (With Food)</strong>: ₹399/- (Admit 1 + Food Included)</li>
                    <li>&#127869; <strong>Couple Entry (With Food)</strong>: ₹699/- (Admit 2 + Food Included)</li>
                    <li>&#127869; <strong>Family Entry (With Food)</strong>: ₹1299/- (Admit 4 + Food Included)</li>
                </ul>

                <!-- 1-Click UTR Instant Claim Box -->
                <div style="background: rgba(10, 1, 6, 0.85); border: 1.5px solid var(--gold-accent); border-radius: 12px; padding: 14px 16px; margin-bottom: 16px;">
                    <div style="font-size: 0.88rem; font-weight: 800; color: #ffd966; margin-bottom: 8px;">
                        &#9889; Already Paid via UPI? Enter 12-Digit UTR to Download Pass:
                    </div>
                    <form action="<?= BASE_URL ?>/ticket.php" method="GET" style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <input type="text" 
                               name="search" 
                               placeholder="Enter 12-digit UPI UTR / Ref No..." 
                               required 
                               style="flex: 1; min-width: 190px; padding: 10px 14px; font-size: 0.95rem; font-family: monospace; border-radius: 8px; background: #000; border: 1.5px solid var(--gold-accent); color: #fff; box-sizing: border-box;">
                        <button type="submit" class="btn btn-primary" style="padding: 10px 18px; font-weight: 800; font-size: 0.9rem; border-radius: 8px; white-space: nowrap;">
                            &#10004; Get Ticket &rarr;
                        </button>
                    </form>
                </div>

                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="#bms-seat-arena" class="btn btn-primary" style="padding: 11px 22px; font-weight: 800; font-size: 0.92rem; border-radius: 8px; text-decoration: none;">
                        &#127915; Pick Seats in Arena &darr;
                    </a>
                    <a href="<?= BASE_URL ?>/ticket.php" class="btn btn-secondary" style="padding: 11px 18px; font-size: 0.88rem; border-radius: 8px; background: #222634; font-weight: 700;">
                        &#128269; Search by Mobile / Booking ID
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================
         BOOKMYSHOW CINEMA / ARENA SEAT LAYOUT MATRIX
         Pure Direct Multiple Seat Selection
         ======================================================== -->
    <div class="bms-arena-wrapper" id="bms-seat-arena">
        <!-- Event Header Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 14px; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
            <div>
                <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0;">
                    <?= e($event['title'] ?? 'Jashn-E-Garba 2026') ?>
                </h3>
                <div style="font-size: 0.85rem; color: #64748b; margin-top: 2px;">
                    &#128197; <?= date('l, d F Y', strtotime($event['date'] ?? '2026-10-16')) ?> &bull; &#9200; <?= date('h:i A', strtotime($event['start_time'] ?? '17:00:00')) ?> – <?= date('h:i A', strtotime($event['end_time'] ?? '21:00:00')) ?> &bull; <?= e($event['venue'] ?? 'Unique Garden, Patna') ?>
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <span id="selected-count-badge" style="background: rgba(16,185,129,0.15); color: #059669; border: 1px solid #10b981; padding: 5px 16px; border-radius: 20px; font-weight: 800; font-size: 0.84rem;">
                    &#9889; Tap Any Green Seat to Select
                </span>
            </div>
        </div>

        <!-- Live Arena Seat Availability Strip (Total Seats, Booked Seats, Khali Seats) -->
        <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 12px 18px; margin-bottom: 22px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="background: #0f172a; color: #fff; font-size: 0.74rem; font-weight: 800; padding: 3px 10px; border-radius: 14px; text-transform: uppercase;">
                    &#128186; Live Arena Status
                </span>
                <span style="font-size: 0.92rem; color: #334155; font-weight: 700;">
                    Total Seats: <strong style="color: #0f172a; font-size: 1.05rem;"><?= $seatSummary['total'] ?></strong>
                </span>
            </div>

            <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                <div style="font-size: 0.92rem; color: #334155; font-weight: 700;">
                    <span style="color: #d97706;">&#9203; Booked:</span> <strong style="color: #d97706; font-size: 1.05rem;"><?= $seatSummary['sold'] ?></strong>
                </div>
                <div style="font-size: 0.92rem; color: #334155; font-weight: 700;">
                    <span style="color: #059669;">&#9989; Khali (Available):</span> <strong style="color: #059669; font-size: 1.1rem;"><?= $seatSummary['available'] ?></strong>
                </div>
                <div style="font-size: 0.78rem; font-weight: 800; background: #e2e8f0; color: #475569; padding: 3px 10px; border-radius: 6px;">
                    <?= $seatSummary['occupancy_pct'] ?>% Occupied
                </div>
            </div>
        </div>

        <!-- Curved Central Performance Stage -->
        <div class="bms-arena-screen">
            <div class="bms-stage-curve"></div>
            <div class="bms-stage-text">&#9733; CENTRAL PERFORMANCE STAGE &#9733;</div>
        </div>

        <!-- Seating Rows Grouped By Category -->
        <form action="<?= BASE_URL ?>/index.php" method="POST" id="seat-checkout-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="seat_checkout">
            <input type="hidden" name="selected_seats" id="input-selected-seats" value="">

            <?php foreach ($tieredRows as $tierHeader => $rows): ?>
                <div class="bms-tier-section">
                    <div class="bms-tier-header">
                        <span><?= $tierHeader ?></span>
                    </div>

                    <?php foreach ($rows as $rowLetter => $seatsInRow): ?>
                        <div class="bms-seat-row" id="row-container-<?= e($rowLetter) ?>">
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

            <!-- Legend Bar Matching BookMyShow Specification -->
            <div class="bms-legend-bar">
                <div class="bms-legend-item">
                    <span class="bms-legend-swatch" style="border: 1.5px solid #10b981; background: #fff;"></span>
                    <span>Available (Khali)</span>
                </div>
                <div class="bms-legend-item">
                    <span class="bms-legend-swatch" style="border: 1px solid #94a3b8; background: #94a3b8;"></span>
                    <span>Sold (Booked)</span>
                </div>
                <div class="bms-legend-item">
                    <span class="bms-legend-swatch" style="background: #10b981; border: 1.5px solid #059669;"></span>
                    <span>Selected</span>
                </div>
            </div>

            <!-- Attendee Details & Dynamic Checkout Box -->
            <div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 22px; border-radius: 12px; margin-top: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 16px;">
                    <div>
                        <label style="display:block; font-size: 0.82rem; font-weight:700; color: #475569; margin-bottom: 4px;">Attendee Full Name (Optional / Default)</label>
                        <input type="text" name="customer_name" id="input-customer-name" placeholder="Full Name (e.g. Garba Attendee)" style="width:100%; padding:10px 14px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.95rem; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.82rem; font-weight:700; color: #475569; margin-bottom: 4px;">10-Digit Mobile Number (Ticket Delivery)</label>
                        <input type="tel" name="customer_phone" id="input-customer-phone" maxlength="15" placeholder="10 Digits (e.g. 7004665804)" style="width:100%; padding:10px 14px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.95rem; box-sizing:border-box;">
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
                    <div>
                        <div style="font-size: 0.84rem; color: #64748b; font-weight: 600;">Selected Seats:</div>
                        <div id="selected-seats-label" style="font-weight: 800; font-size: 1.12rem; color: #0f172a;">None selected (Tap any green seat above)</div>
                        <div id="food-privilege-indicator" style="font-size: 0.84rem; font-weight: 800; margin-top: 4px;"></div>
                    </div>

                    <div style="text-align: right;">
                        <div style="font-size: 0.84rem; color: #64748b; font-weight: 600;">Dynamic Total Amount:</div>
                        <div id="selected-total-label" style="font-size: 1.6rem; font-weight: 900; color: #f84464;">₹0.00</div>
                    </div>

                    <div>
                        <button type="submit" id="btn-submit-seat-pay" class="btn-bms-red" style="padding: 14px 28px; font-size: 1.05rem; font-weight: 900; border-radius: 10px; cursor: pointer; transition: all 0.2s ease;">
                            <span>🎟 Book Now (Tap Seats to Select) &rarr;</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- 6. EVENT HIGHLIGHTS & ATTRACTIONS -->
    <div class="festive-card" style="margin-top: 40px; padding: 25px 22px;">
        <h3 class="card-title">&#127926; Event Highlights &amp; Grand Attractions</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-top: 20px;">
            <div style="background: rgba(20, 2, 11, 0.6); padding: 18px; border-radius: 12px; border: 1px solid rgba(245, 183, 34, 0.2);">
                <div style="font-size: 2rem; color: var(--gold-accent); margin-bottom: 8px;">&#127931;</div>
                <h4 style="color: #fff; margin-bottom: 6px;">Authentic Gujarati Folk Band</h4>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">Live dhol, nagada, and vocalists singing traditional Raas-Garba melodies all evening.</p>
            </div>

            <div style="background: rgba(20, 2, 11, 0.6); padding: 18px; border-radius: 12px; border: 1px solid rgba(245, 183, 34, 0.2);">
                <div style="font-size: 2rem; color: var(--gold-accent); margin-bottom: 8px;">&#127911;</div>
                <h4 style="color: #fff; margin-bottom: 6px;">Celebrity DJ &amp; Sound</h4>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">High-octane Bollywood beats &amp; EDM fusion Dandiya in the second half of the night.</p>
            </div>

            <div style="background: rgba(20, 2, 11, 0.6); padding: 18px; border-radius: 12px; border: 1px solid rgba(245, 183, 34, 0.2);">
                <div style="font-size: 2rem; color: var(--gold-accent); margin-bottom: 8px;">&#127942;</div>
                <h4 style="color: #fff; margin-bottom: 6px;">Mega Contest &amp; Awards</h4>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">Prizes for Best Traditional Attire, Best Garba Performer, Best Couple Dancer &amp; Kids.</p>
            </div>

            <div style="background: rgba(20, 2, 11, 0.6); padding: 18px; border-radius: 12px; border: 1px solid rgba(245, 183, 34, 0.2);">
                <div style="font-size: 2rem; color: var(--gold-accent); margin-bottom: 8px;">&#128737;</div>
                <h4 style="color: #fff; margin-bottom: 6px;">Family-Safe Atmosphere</h4>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">CCTV surveillance, bouncers, strict digital QR ticket gate verification, and dedicated parking.</p>
            </div>
        </div>
    </div>

</div>

<!-- Interactive Video Sound & Copy UPI Scripts -->
<script>
function playWithSound(playerId) {
    const v = document.getElementById(playerId);
    const b = document.getElementById('btn-sound-v0');
    if (v) {
        if (v.muted) {
            v.muted = false;
            v.volume = 1.0;
            v.play().catch(function(e) { console.log('Play error:', e); });
            if (b) {
                b.innerHTML = '&#128264; Playing Sound';
                b.style.background = 'var(--gold-accent)';
                b.style.color = '#1f0411';
            }
        } else {
            v.muted = true;
            if (b) {
                b.innerHTML = '&#128266; Sound ON';
                b.style.background = 'rgba(245,183,34,0.2)';
                b.style.color = 'var(--gold-accent)';
            }
        }
    }
}

// 1. Continuous automatic play on page load
// 2. Unmute on first user touch/scroll/click anywhere
// 3. Reels-like auto pause when scrolled away, auto resume when visible
document.addEventListener('DOMContentLoaded', function() {
    const video = document.getElementById('top-v0-player');
    const soundBtn = document.getElementById('btn-sound-v0');

    if (video) {
        // Immediate play attempt
        video.muted = true;
        video.play().catch(function(e) {
            console.log('Autoplay muted start', e);
        });

        // Auto-unmute on first touch/click/scroll interaction
        let soundActivated = false;
        function tryUnmute() {
            if (soundActivated || !video) return;
            video.muted = false;
            video.volume = 1.0;
            soundActivated = true;
            if (soundBtn) {
                soundBtn.innerHTML = '&#128264; Playing Sound';
                soundBtn.style.background = 'var(--gold-accent)';
                soundBtn.style.color = '#1f0411';
            }
            window.removeEventListener('click', tryUnmute);
            window.removeEventListener('touchstart', tryUnmute);
            window.removeEventListener('scroll', tryUnmute);
        }

        window.addEventListener('click', tryUnmute, { once: true, passive: true });
        window.addEventListener('touchstart', tryUnmute, { once: true, passive: true });
        window.addEventListener('scroll', tryUnmute, { once: true, passive: true });

        // Reels-Style: Pause when page is scrolled down (video out of view), play when in view
        if ('IntersectionObserver' in window) {
            const reelsObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && entry.intersectionRatio >= 0.25) {
                        video.play().catch(function() {});
                    } else {
                        video.pause();
                    }
                });
            }, {
                threshold: [0, 0.25, 0.5]
            });
            reelsObserver.observe(video);
        } else {
            // Fallback for older browsers
            window.addEventListener('scroll', function() {
                const rect = video.getBoundingClientRect();
                const inView = (rect.bottom > 60 && rect.top < window.innerHeight - 60);
                if (inView && video.paused) {
                    video.play().catch(function() {});
                } else if (!inView && !video.paused) {
                    video.pause();
                }
            }, { passive: true });
        }
    }
});

function copyUpiId() {
    const upiText = 'ashrafkhan0777-2@oksbi';
    const btn = document.getElementById('btn-copy-upi');
    
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(upiText).then(onSuccess).catch(fallback);
    } else {
        fallback();
    }
    
    function fallback() {
        const temp = document.createElement('textarea');
        temp.value = upiText;
        document.body.appendChild(temp);
        temp.select();
        try {
            document.execCommand('copy');
            onSuccess();
        } catch(e) {
            alert('UPI ID: ' + upiText);
        }
        document.body.removeChild(temp);
    }
    
    function onSuccess() {
        if (btn) {
            btn.innerText = 'Copied!';
            btn.style.background = '#4ade80';
            btn.style.color = '#000';
            setTimeout(function() {
                btn.innerText = 'Copy UPI';
                btn.style.background = 'var(--gold-accent)';
                btn.style.color = '#1f0411';
            }, 2500);
        }
    }
}

function copyIndexMobile() {
    const mobileText = '7004665804';
    const btn = document.getElementById('btn-copy-index-mob');
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(mobileText).then(onSuccess).catch(fallback);
    } else {
        fallback();
    }
    function fallback() {
        const temp = document.createElement('textarea');
        temp.value = mobileText;
        document.body.appendChild(temp);
        temp.select();
        try {
            document.execCommand('copy');
            onSuccess();
        } catch(e) {
            alert('Mobile Number: ' + mobileText);
        }
        document.body.removeChild(temp);
    }
    function onSuccess() {
        if (btn) {
            btn.innerText = 'Copied!';
            btn.style.background = '#4ade80';
            btn.style.color = '#000';
            setTimeout(function() {
                btn.innerText = '📋 Copy Mobile Number';
                btn.style.background = '#38bdf8';
                btn.style.color = '#082f49';
            }, 2500);
        }
    }
}

// 5 Official Boarding Passes Switcher & Modal
const officialPassesData = <?= json_encode($officialPasses) ?>;

function switchOfficialPass(idx) {
    const p = officialPassesData[idx];
    if (!p) return;

    // Update Tab Buttons
    document.querySelectorAll('.pass-tab-button').forEach((btn, i) => {
        if (i === idx) {
            btn.style.background = 'var(--gold-accent)';
            btn.style.color = '#1f0411';
            btn.style.borderColor = 'var(--gold-accent)';
        } else {
            btn.style.background = 'rgba(20,2,11,0.85)';
            btn.style.color = '#cbd5e1';
            btn.style.borderColor = 'rgba(245,183,34,0.3)';
        }
    });

    const img = document.getElementById('active-pass-img');
    const container = document.getElementById('active-pass-container');
    const title = document.getElementById('active-pass-title');
    const tag = document.getElementById('active-pass-tag');
    const foodBadge = document.getElementById('active-pass-food-badge');
    const admit = document.getElementById('active-pass-admit');
    const price = document.getElementById('active-pass-price');
    const bookBtn = document.getElementById('btn-active-pass-book');

    if (container) {
        container.style.opacity = '0.3';
        setTimeout(() => {
            if (img) img.src = p.image;
            if (title) title.innerText = p.name;
            if (tag) {
                tag.innerHTML = p.tag;
                tag.style.background = p.theme_color || '#e11d48';
            }
            if (foodBadge) {
                foodBadge.innerText = p.food_badge;
                foodBadge.style.color = p.badge_color;
                foodBadge.style.borderColor = p.badge_color;
                foodBadge.style.background = p.food ? 'rgba(34,197,94,0.2)' : 'rgba(239,68,68,0.2)';
            }
            if (admit) admit.innerText = p.persons + ' Person' + (p.persons > 1 ? 's' : '');
            if (price) price.innerText = '₹' + p.price + '/-';
            if (bookBtn) {
                bookBtn.setAttribute('onclick', "selectTierAndScroll('" + p.row + "')");
            }
            container.style.opacity = '1';
        }, 120);
    }
}

function openPassModal(src, title) {
    let modal = document.getElementById('pass-lightbox-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'pass-lightbox-modal';
        modal.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.92); z-index:99999; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:20px; box-sizing:border-box; cursor:zoom-out;';
        modal.innerHTML = `
            <div style="position:relative; max-width:1100px; width:100%; text-align:center;" onclick="event.stopPropagation()">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <span id="modal-pass-title" style="color:#ffd966; font-weight:800; font-size:1.1rem;"></span>
                    <button type="button" onclick="document.getElementById('pass-lightbox-modal').style.display='none'" style="background:var(--gold-accent); color:#1f0411; border:none; border-radius:50%; width:36px; height:36px; font-weight:900; font-size:1.2rem; cursor:pointer;">&times;</button>
                </div>
                <img id="modal-pass-img" src="" style="width:100%; height:auto; max-height:85vh; object-fit:contain; border-radius:12px; border:2px solid var(--gold-accent); box-shadow:0 10px 40px rgba(0,0,0,0.9);">
            </div>
        `;
        modal.onclick = function() { modal.style.display = 'none'; };
        document.body.appendChild(modal);
    }
    document.getElementById('modal-pass-img').src = src;
    document.getElementById('modal-pass-title').innerText = title || 'Official Boarding Pass';
    modal.style.display = 'flex';
}

// ==========================================
// BookMyShow Pure Multi-Seat Selection Script
// ==========================================
let selectedSeats = [];
let seatPrices = {};

function scrollToSeatMap() {
    const el = document.getElementById('bms-seat-arena') || document.getElementById('bms-seat-map');
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
    }
}

function selectTierAndScroll(rowLetter) {
    let rowEl = document.getElementById('row-container-' + rowLetter);
    if (!rowEl && (rowLetter === 'VIP' || rowLetter === 'VIP 1')) {
        rowEl = document.getElementById('row-container-VIP 1') || document.getElementById('row-container-VIP');
    }
    if (rowEl) {
        rowEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        rowEl.style.transition = 'background 0.3s ease';
        rowEl.style.background = 'rgba(245, 183, 34, 0.15)';
        setTimeout(() => { rowEl.style.background = 'transparent'; }, 800);
    } else {
        scrollToSeatMap();
    }
}

function handleSeatClick(code, price) {
    const box = document.getElementById('seat-box-' + code);
    if (!box || box.classList.contains('sold') || box.classList.contains('blocked')) return;

    const idx = selectedSeats.indexOf(code);
    if (idx > -1) {
        // Deselect seat
        selectedSeats.splice(idx, 1);
        delete seatPrices[code];
        box.classList.remove('selected');
        box.classList.add('available');
    } else {
        // Multi-select seat freely (no limits or deselection!)
        selectedSeats.push(code);
        seatPrices[code] = parseFloat(price);
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
    const badge = document.getElementById('selected-count-badge');
    const foodInd = document.getElementById('food-privilege-indicator');

    if (!inputSeats || !labelSeats || !labelTotal || !btnPay) return;

    inputSeats.value = selectedSeats.join(',');

    let total = 0;
    let hasFood = false;
    for (let c in seatPrices) {
        total += seatPrices[c];
        if (seatPrices[c] >= 350) {
            hasFood = true;
        }
    }

    const count = selectedSeats.length;

    if (count > 0) {
        labelSeats.innerText = selectedSeats.join(', ') + ' (' + count + ' Seat' + (count > 1 ? 's' : '') + ')';
        labelTotal.innerText = '₹' + total.toFixed(2);
        btnPay.disabled = false;
        btnPay.style.opacity = '1';
        btnPay.style.background = '#f84464';
        btnPay.innerHTML = '<span>🎟 Book Now &bull; Pay ₹' + total.toFixed(2) + ' via UPI &rarr;</span>';

        if (badge) {
            badge.innerHTML = '&#10004; ' + count + ' Seat' + (count > 1 ? 's' : '') + ' Selected';
            badge.style.background = 'rgba(16, 185, 129, 0.2)';
            badge.style.color = '#059669';
            badge.style.borderColor = '#10b981';
        }

        if (foodInd) {
            if (hasFood) {
                foodInd.innerHTML = '<span style="color:#059669;">&#127869; Food Privilege: Delicious Food Included</span>';
            } else {
                foodInd.innerHTML = '<span style="color:#e11d48;">&#10060; Food Privilege: Entry Only (No Food Included)</span>';
            }
        }
    } else {
        labelSeats.innerText = 'None selected (Tap any green seat above)';
        labelTotal.innerText = '₹0.00';
        btnPay.disabled = false; // Always keep clickable to guide attendee!
        btnPay.style.opacity = '0.85';
        btnPay.style.background = '#e11d48';
        btnPay.innerHTML = '<span>🎟 Book Now (Tap Seats to Select) &rarr;</span>';

        if (badge) {
            badge.innerHTML = '&#9889; Tap Any Green Seat to Select';
            badge.style.background = 'rgba(16,185,129,0.15)';
            badge.style.color = '#059669';
            badge.style.borderColor = '#10b981';
        }

        if (foodInd) {
            foodInd.innerHTML = '';
        }
    }
}

// Intercept form submission to guarantee smooth booking with zero friction
document.getElementById('seat-checkout-form')?.addEventListener('submit', function(e) {
    if (!selectedSeats || selectedSeats.length === 0) {
        e.preventDefault();
        alert('Please tap on at least one available green seat on the arena map above before clicking Book Now.');
        scrollToSeatMap();
        return false;
    }

    const nameInp = document.getElementById('input-customer-name');
    const phoneInp = document.getElementById('input-customer-phone');
    if (nameInp && !nameInp.value.trim()) {
        nameInp.value = 'Garba Attendee';
    }
    if (phoneInp) {
        let cleanDigits = phoneInp.value.replace(/\D/g, '');
        if (cleanDigits.length > 10 && cleanDigits.startsWith('91')) {
            cleanDigits = cleanDigits.slice(2);
        }
        if (cleanDigits.length >= 10) {
            phoneInp.value = cleanDigits.slice(-10);
        } else {
            phoneInp.value = '7004665804';
        }
    }

    const btn = document.getElementById('btn-submit-seat-pay');
    if (btn) {
        btn.innerHTML = '<span>⏳ Booking ' + selectedSeats.length + ' Seat(s)... Opening Direct UPI Payment</span>';
    }
});

// Clean start with 0 seats selected so attendee has complete control
updateCheckoutSummary();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
