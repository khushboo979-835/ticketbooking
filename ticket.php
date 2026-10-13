<?php
/**
 * ticket.php - Digital E-Ticket, Payment Receipt & Search Console
 * Millennium India Group - Jashn-E-Garba 2026
 */
ob_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

send_no_cache_headers();

$uid = trim($_GET['uid'] ?? '');
$searchQuery = trim($_GET['search'] ?? '');
$db = getDB();
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
            JOIN events e ON b.event_id = e.id
            WHERE b.booking_uid = :uid
            LIMIT 1
        ");
        $stmt->execute([':uid' => $uid]);
        $booking = $stmt->fetch();
    } elseif (!empty($searchQuery)) {
        $cleanSearch = trim($searchQuery);
        $cleanDigits = preg_replace('/[^\d]/', '', $cleanSearch);
        $last10 = (strlen($cleanDigits) >= 10) ? substr($cleanDigits, -10) : '';

        $sql = "
            SELECT b.*, e.title AS event_title, e.date AS event_date, e.venue AS event_venue, e.start_time, e.end_time
            FROM bookings b
            JOIN events e ON b.event_id = e.id
            WHERE b.booking_uid = :q 
               OR b.customer_phone = :q_raw
               OR b.transaction_ref = :q_raw
        ";
        $params = [':q' => $cleanSearch, ':q_raw' => $cleanSearch];

        if (!empty($last10)) {
            $sql .= " OR b.customer_phone LIKE :q_10";
            $params[':q_10'] = "%$last10%";
        }

        $sql .= " ORDER BY b.id DESC LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $booking = $stmt->fetch();
    }

    if ($booking) {
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

$isVerified = ($booking && $booking['payment_status'] === 'verified');
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
    <div class="festive-card" style="border: 1.5px solid var(--gold-accent); padding: 24px 22px; margin-bottom: 30px; box-shadow: 0 8px 30px rgba(0,0,0,0.5);">
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

    <?php elseif (!$hasSearched && !$booking): ?>
        <!-- FIRST VISIT DEFAULT VIEW: SECURITY POLICY & INSTRUCTIONS -->
        <div class="festive-card" style="max-width: 720px; margin: 20px auto; text-align: center; border: 2px solid var(--gold-accent); padding: 35px 25px;">
            <div style="font-size: 3.2rem; margin-bottom: 12px;">&#128274;</div>
            <span style="background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1.5px solid #ef4444; font-size: 0.8rem; font-weight: 800; padding: 4px 14px; border-radius: 50px; text-transform: uppercase; letter-spacing: 0.5px;">
                Verified Payment Mandatory For Passes
            </span>
            <h2 class="card-title" style="justify-content: center; margin-top: 14px; font-size: 1.7rem;">Official Ticket Security &amp; Verification Policy</h2>
            
            <div style="background: rgba(20, 2, 11, 0.85); border: 1.5px dashed var(--gold-accent); border-radius: 12px; padding: 20px; margin: 20px 0; text-align: left; font-size: 0.92rem; line-height: 1.8; color: #e2e8f0;">
                <strong style="color: var(--gold-accent); display: block; font-size: 1.05rem; margin-bottom: 8px;">
                    &#9888; केवल भुगतान (Payment) की पुष्टि होने के बाद ही मान्य QR टिकट जारी होता है:
                </strong>
                <p style="margin: 0 0 10px;">
                    1. <strong>Strict Payment Policy:</strong> Millennium India Group ke official niyam ke anusaar, Digital QR Gate Entry Pass keval unhi customers ko issue kiya jata hai jinka payment verify ho chuka hai.
                </p>
                <p style="margin: 0 0 10px;">
                    2. <strong>Instant Booking Receipt:</strong> Jab aap booking form bhar kar UPI ID (<code>chandni0984@oksbi</code>) par exact payment karte hain aur apna 12-digit UTR submit karte hain, tabhi aapka booking reference generate hota hai.
                </p>
                <p style="margin: 0;">
                    3. Bina payment confirmation ya bina valid UTR ke koi bhi pass gate entry ke liye aamany (invalid) mana jayega.
                </p>
            </div>

            <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; margin-top: 25px;">
                <a href="<?= BASE_URL ?>/booking.php?event_id=1" class="btn btn-primary" style="padding: 14px 28px; font-weight: 800; font-size: 1rem;">
                    &#127915; Book Passes (From ₹199)
                </a>
                <a href="https://wa.me/917004665804" target="_blank" class="btn btn-secondary" style="padding: 14px 22px; font-weight: 700; font-size: 0.95rem;">
                    &#128222; Helpline: +91 7004665804
                </a>
            </div>
        </div>

    <?php elseif (!$isVerified): ?>
        <!-- PENDING VERIFICATION STATE: OFFICIAL BOOKING RECEIPT & SLIP -->
        <div class="festive-card" style="border: 2px solid var(--gold-accent); padding: 35px 25px; max-width: 760px; margin: 20px auto;">
            
            <div style="text-align: center; margin-bottom: 25px;">
                <div style="font-size: 3.5rem; color: var(--gold-accent); margin-bottom: 10px;">&#9203;</div>
                <span class="badge badge-pending" style="font-size: 0.95rem; padding: 6px 18px; letter-spacing: 0.5px;">
                    PAYMENT VERIFICATION PENDING
                </span>
                <h2 style="font-size: 1.85rem; color: #fff; margin: 14px 0 6px; font-weight: 900;">
                    Official Booking Receipt
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
                        <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Transaction / UTR</span>
                        <div style="font-weight: 800; font-size: 1.1rem; font-family: monospace; color: #ffd966;"><?= e($booking['transaction_ref'] ?: 'Direct UPI') ?></div>
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Passes Booked:</span>
                    <div style="color: #fff; font-weight: 700; margin-top: 4px;"><?= $ticketTypeSummary ?: 'Standard Entry' ?> (Total <?= $totalPersons ?> Person<?= $totalPersons > 1 ? 's' : '' ?>)</div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1.5px dashed rgba(245,183,34,0.35); padding-top: 14px;">
                    <span style="font-size: 1rem; color: #fff; font-weight: 700;">Total Payable Amount:</span>
                    <span style="font-size: 1.6rem; color: var(--gold-accent); font-weight: 900;"><?= format_inr($booking['total_amount']) ?></span>
                </div>
            </div>

            <!-- Verification Notice Box -->
            <div style="background: rgba(245,183,34,0.12); border: 1.5px dashed var(--gold-accent); border-radius: 10px; padding: 18px 20px; margin-bottom: 25px; text-align: left; font-size: 0.92rem; color: #ddd; line-height: 1.7;">
                <strong style="color: #ffd966; display: block; margin-bottom: 6px; font-size: 1rem;">&#9888; Important Security Policy:</strong>
                Aapka booking reference generate ho gaya hai. Gate entry pass ka <strong>Digital QR Code</strong> tabhi unlock hoga jab hamari accounts team aapke payment UTR ko verify karegi. <strong>Keval verified payment par hi entry valid mani jayegi.</strong>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap;">
                <button onclick="window.location.reload();" class="btn btn-secondary" style="padding: 12px 22px; font-weight: 700;">
                    &#128472; Check Status Again
                </button>
                <a href="https://wa.me/917004665804?text=<?= urlencode("Hello Millennium India Group, I have submitted payment for Booking ID " . $booking['booking_uid'] . " (Amount: " . format_inr($booking['total_amount']) . ") with UTR: " . $booking['transaction_ref'] . ". Please verify and activate my ticket.") ?>" target="_blank" class="btn btn-primary" style="background:#25d366; color:#fff; border-color:#25d366; font-weight: 800; padding: 12px 24px;">
                    &#128172; Request WhatsApp Verification
                </a>
            </div>

        </div>

    <?php else: ?>
        <!-- OFFICIAL VERIFIED DIGITAL PASS (Matches Infographic Box 7) -->
        
        <!-- Action Buttons (Hidden on Print) -->
        <div class="ticket-actions-bar">
            <div>
                <a href="<?= BASE_URL ?>/index.php" style="color: var(--gold-light); font-weight: 600; font-size: 0.9rem;">
                    &larr; Back to Events
                </a>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" onclick="window.print()" class="btn btn-primary btn-sm" style="font-weight: 700;">
                    &#128424; Print / Save PDF
                </button>
                <button type="button" onclick="copyTicketLink()" class="btn btn-secondary btn-sm">
                    &#128279; Share Link
                </button>
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
                        <span class="ticket-badge" style="background: #10b981; color: #fff;">
                            &#10004; VERIFIED PASS
                        </span>
                    </div>
                </div>

                <div class="ticket-event-title"><?= e($booking['event_title']) ?></div>

                <div class="ticket-details-grid">
                    <div>
                        <div class="ticket-info-label">Booking ID</div>
                        <div class="ticket-info-value" style="font-family: monospace; color: #7a1244; font-size: 1.15rem;">
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
                        <div class="ticket-info-value"><?= e($booking['customer_name']) ?></div>
                    </div>

                    <div>
                        <div class="ticket-info-label">Mobile Number</div>
                        <div class="ticket-info-value"><?= e($booking['customer_phone']) ?></div>
                    </div>

                    <div>
                        <div class="ticket-info-label">Valid For</div>
                        <div class="ticket-info-value" style="color: #7a1244; font-size: 1.25rem; font-weight: 900;">
                            <?= $totalPersons ?> Person<?= $totalPersons > 1 ? 's' : '' ?>
                        </div>
                    </div>

                    <div>
                        <div class="ticket-info-label">Date &amp; Timing</div>
                        <div class="ticket-info-value" style="font-size: 0.95rem;">
                            <?= date('d Oct 2026', strtotime($booking['event_date'])) ?> &bull; <?= date('h:i A', strtotime($booking['start_time'])) ?> &ndash; <?= date('h:i A', strtotime($booking['end_time'])) ?>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 15px;">
                    <div class="ticket-info-label">Venue</div>
                    <div class="ticket-info-value" style="font-size: 1.05rem; color: #222;">
                        &#128205; <?= e($booking['event_venue']) ?>
                    </div>
                </div>

                <!-- Bottom Security Banner (Exact Infographic Copy) -->
                <div style="margin-top: 25px; padding: 10px; background: #7a1244; color: #ffd966; text-align: center; font-weight: 800; font-size: 0.85rem; letter-spacing: 1.5px; border-radius: 6px;">
                    ENTRY WITH VALID QR CODE ONLY
                </div>
            </div>

            <!-- Right Stub / QR Code Section -->
            <div class="ticket-stub">
                <div style="font-size: 0.75rem; text-transform: uppercase; color: #888; font-weight: 700; margin-bottom: 8px;">
                    Gate Entry QR
                </div>

                <!-- Dynamic QR Container -->
                <div class="ticket-qr-frame">
                    <div id="ticket-qrcode"></div>
                </div>

                <div class="ticket-uid"><?= e($booking['booking_uid']) ?></div>
                
                <div style="font-size: 0.78rem; color: #059669; font-weight: 800; margin-top: 6px;">
                    <?= $booking['is_checked_in'] ? '&#10004; CHECKED IN' : 'ACTIVE PASS' ?>
                </div>

                <div class="ticket-security-note">
                    Official Hash Token:<br>
                    <span style="font-family: monospace; font-size: 0.65rem; color: #888; word-break: break-all;">
                        <?= substr($booking['qr_token'], 0, 16) ?>...
                    </span>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>

<!-- Fast Client-Side QR Generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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

function copyTicketLink() {
    const link = window.location.href;
    navigator.clipboard.writeText(link).then(() => {
        alert('Ticket link copied to clipboard!');
    }).catch(() => {
        prompt('Copy your ticket link:', link);
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
