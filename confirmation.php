<?php
/**
 * confirmation.php - Booking Confirmation & Payment Verification Status
 * Matches Infographic Box 6 Specification
 * Millennium India Group - Jashn-E-Garba 2026
 */
ob_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

send_no_cache_headers();

$uid = trim($_GET['uid'] ?? '');
if (empty($uid)) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$db = getDB();
$booking = null;
$items = [];

try {
    $stmt = $db->prepare("
        SELECT b.*, e.title AS event_title, e.date AS event_date, e.venue AS event_venue, e.start_time, e.end_time
        FROM bookings b
        JOIN events e ON b.event_id = e.id
        WHERE b.booking_uid = :uid
        LIMIT 1
    ");
    $stmt->execute([':uid' => $uid]);
    $booking = $stmt->fetch();

    if ($booking) {
        $itemStmt = $db->prepare("
            SELECT bi.*, tc.name AS category_name, tc.people_allowed
            FROM booking_items bi
            JOIN ticket_categories tc ON bi.ticket_category_id = tc.id
            WHERE bi.booking_id = :b_id
        ");
        $itemStmt->execute([':b_id' => (int)$booking['id']]);
        $items = $itemStmt->fetchAll() ?: [];
    }
} catch (PDOException $e) {
    error_log('[confirmation.php error]: ' . $e->getMessage());
}

if (!$booking) {
    set_flash('error', 'Booking reference not found.');
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$isVerified = ($booking['payment_status'] === 'verified');
$pageTitle = ($isVerified ? 'Booking Successful!' : 'Payment Received - Under Verification') . ' | Millennium India Group';
$currentNav = 'garba';

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 40px; padding-bottom: 70px;">
    <div style="max-width: 760px; margin: 0 auto;">
        
        <?php if ($isVerified): ?>
            <!-- VERIFIED STATE (Matches Infographic Box 6) -->
            <div class="festive-card" style="text-align: center; border: 2px solid #10b981; padding: 40px 25px;">
                <div style="width: 80px; height: 80px; background: rgba(16,185,129,0.15); border: 2px solid #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 2.5rem; color: #10b981;">
                    &#10004;
                </div>
                <h1 style="font-size: 2.2rem; font-weight: 900; color: #fff; margin-bottom: 8px;">Booking Successful!</h1>
                <div style="font-size: 1.15rem; color: var(--gold-light); font-family: monospace; font-weight: 800; margin-bottom: 12px;">
                    Booking ID: <?= e($booking['booking_uid']) ?>
                </div>
                <p style="color: var(--text-muted); font-size: 1rem; max-width: 520px; margin: 0 auto 30px;">
                    Your tickets have been confirmed and sent to your email &amp; WhatsApp (<?= e($booking['customer_phone']) ?>).
                </p>

                <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
                    <a href="<?= BASE_URL ?>/ticket.php?uid=<?= urlencode($booking['booking_uid']) ?>" class="btn btn-primary" style="font-size: 1.1rem; padding: 14px 32px;">
                        &#127915; Download Ticket
                    </a>
                    <a href="<?= BASE_URL ?>/ticket.php?uid=<?= urlencode($booking['booking_uid']) ?>" class="btn btn-secondary" style="font-size: 1.1rem; padding: 14px 32px;">
                        View My Pass / Ticket
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- PENDING DIRECT UPI STATE -->
            <div class="festive-card" style="text-align: center; border: 2px solid var(--gold-accent); padding: 40px 25px;">
                <div style="font-size: 3.5rem; color: var(--gold-accent); margin-bottom: 12px;">&#9203;</div>
                <span class="badge badge-pending" style="font-size: 0.95rem; padding: 6px 16px; margin-bottom: 15px;">
                    PAYMENT RECEIVED &bull; UNDER VERIFICATION
                </span>
                <h1 style="font-size: 2rem; color: #fff; margin-top: 10px; margin-bottom: 12px;">Thank You, <?= e($booking['customer_name']) ?>!</h1>
                
                <div style="background: rgba(245,183,34,0.12); border: 1px solid var(--gold-accent); border-radius: 12px; padding: 20px; max-width: 600px; margin: 20px auto 30px; text-align: left;">
                    <div style="display: flex; align-items: center; gap: 10px; color: var(--gold-light); font-weight: 700; margin-bottom: 8px;">
                        <span>&#128712;</span> <span>Verification Notice:</span>
                    </div>
                    <p style="font-size: 0.95rem; color: #ddd; line-height: 1.6; margin: 0;">
                        Your booking request has been recorded with Transaction / UTR: <strong style="color: #ffd966; font-family: monospace;"><?= e($booking['transaction_ref'] ?: 'Submitted') ?></strong>.<br>
                        Our accounts team will verify the bank receipt against your UTR. Your official <strong>Digital QR Ticket</strong> will be unlocked immediately upon verification.
                    </p>
                </div>

                <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
                    <a href="<?= BASE_URL ?>/ticket.php?uid=<?= urlencode($booking['booking_uid']) ?>" class="btn btn-secondary">
                        &#128269; Check Verification Status
                    </a>
                    <a href="https://wa.me/918603268304?text=<?= urlencode("Hello Millennium India Group, I have submitted UPI payment for Booking ID " . $booking['booking_uid'] . " with UTR " . $booking['transaction_ref'] . ". Please verify.") ?>" target="_blank" class="btn btn-primary" style="background:#25d366; color:#fff; border-color:#25d366;">
                        &#128172; Instant WhatsApp Support
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Booking Details Card -->
        <div class="festive-card" style="margin-top: 30px;">
            <h3 class="card-title">&#128221; Booking Summary</h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px; border-bottom: 1px solid rgba(245,183,34,0.2); padding-bottom: 18px;">
                <div>
                    <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Attendee Name</span>
                    <div style="font-weight: 700; font-size: 1.05rem;"><?= e($booking['customer_name']) ?></div>
                </div>
                <div>
                    <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Mobile Number</span>
                    <div style="font-weight: 700; font-size: 1.05rem;"><?= e($booking['customer_phone']) ?></div>
                </div>
                <div>
                    <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Email</span>
                    <div style="font-weight: 700; font-size: 1.05rem;"><?= e($booking['customer_email']) ?></div>
                </div>
            </div>

            <!-- Items -->
            <div style="margin-bottom: 20px;">
                <h4 style="color: var(--gold-light); margin-bottom: 12px;">Passes Included:</h4>
                <?php foreach ($items as $it): ?>
                    <div class="summary-row">
                        <div>
                            <strong><?= e($it['category_name']) ?></strong>
                            <div style="font-size: 0.82rem; color: #aaa;">Qty: <?= (int)$it['quantity'] ?> (Entry for <?= (int)$it['quantity'] * (int)$it['people_allowed'] ?> persons)</div>
                        </div>
                        <div style="font-weight: 700; color: #ffd966;">
                            <?= format_inr($it['subtotal']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="summary-row total-row">
                    <span>Total Amount:</span>
                    <span><?= format_inr($booking['total_amount']) ?></span>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
