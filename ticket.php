<?php
/**
 * ticket.php - Digital E-Ticket & Printable Luxury Pass (Matches Infographic Box 7)
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
        $cleanSearch = preg_replace('/[^\w@.-]/', '', $searchQuery);
        $stmt = $db->prepare("
            SELECT b.*, e.title AS event_title, e.date AS event_date, e.venue AS event_venue, e.start_time, e.end_time
            FROM bookings b
            JOIN events e ON b.event_id = e.id
            WHERE b.booking_uid = :q OR b.customer_phone = :q2 OR b.customer_email = :q3
            ORDER BY b.id DESC
            LIMIT 1
        ");
        $stmt->execute([':q' => $cleanSearch, ':q2' => $cleanSearch, ':q3' => $cleanSearch]);
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

$pageTitle = $booking ? ('E-Ticket: ' . e($booking['booking_uid']) . ' | Millennium India Group') : 'Find My Ticket';
$currentNav = 'find_ticket';
$extraCss = 'ticket.css';

include __DIR__ . '/includes/header.php';
?>

<div class="ticket-page-container">

    <!-- Payment Verification & Security Policy Statement -->
    <?php if (!$booking): ?>
        <div class="festive-card" style="max-width: 680px; margin: 40px auto; text-align: center; border: 2px solid var(--gold-accent); padding: 35px 25px;">
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
        <!-- PENDING VERIFICATION STATE (STRONG NOTICE: TICKET ISSUED ONLY AFTER PAYMENT VERIFICATION) -->
        <div class="festive-card" style="text-align: center; border: 2px solid var(--gold-accent); padding: 35px 25px; max-width: 720px; margin: 40px auto;">
            <span class="badge badge-pending" style="font-size: 0.95rem; padding: 6px 16px;">PAYMENT VERIFICATION PENDING</span>
            <h2 style="font-size: 1.8rem; color: #fff; margin: 15px 0 10px;">Pass Awaiting Admin Verification</h2>
            <p style="color: var(--text-muted); font-size: 1rem; line-height: 1.6;">
                Booking ID: <strong style="color: var(--gold-accent); font-family: monospace;"><?= e($booking['booking_uid']) ?></strong><br>
                Attendee: <strong><?= e($booking['customer_name']) ?></strong> (<?= e($booking['customer_phone']) ?>)<br>
                Submitted UTR: <span style="font-family: monospace; color: #fff;"><?= e($booking['transaction_ref'] ?: 'Direct UPI') ?></span>
            </p>

            <div style="background: rgba(245,183,34,0.12); border: 1.5px dashed var(--gold-accent); border-radius: 10px; padding: 18px; margin: 20px auto; text-align: left; font-size: 0.92rem; color: #ddd; line-height: 1.7;">
                <strong style="color: #ffd966; display: block; margin-bottom: 6px;">&#9888; Important Payment Notice:</strong>
                Aapka payment UTR review me hai. Bank receipt verify hote hi digital QR entry pass activate ho jayega. Keval valid payment par hi gate entry pass unlock hota hai.
            </div>

            <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                <button onclick="window.location.reload();" class="btn btn-secondary">
                    &#128472; Check Status Again
                </button>
                <a href="https://wa.me/917004665804?text=<?= urlencode("Hello Millennium India Group, please verify payment for Booking ID " . $booking['booking_uid'] . " (UTR: " . $booking['transaction_ref'] . ")") ?>" target="_blank" class="btn btn-primary" style="background:#25d366; color:#fff; border-color:#25d366;">
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
