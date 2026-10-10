<?php
// privacy.php - Privacy Policy
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

$pageTitle = 'Privacy Policy | Millennium India Group';
$currentNav = 'privacy';

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 40px; padding-bottom: 70px;">
    <div class="festive-card" style="max-width: 900px; margin: 0 auto;">
        <h1 class="card-title">&#128274; Privacy Policy</h1>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 25px;">Last Updated: October 2026</p>

        <div style="font-size: 0.95rem; line-height: 1.8; color: #ddd;">
            <p>Millennium India Group ("we", "us", or "our") respects your privacy and is committed to protecting your personal data when you use our official ticketing portal.</p>

            <h3 style="color: var(--gold-light); margin: 20px 0 10px;">1. Information We Collect</h3>
            <p>We collect essential attendee information solely to issue entry passes and verify tickets at the gate:</p>
            <ul style="padding-left: 20px;">
                <li>Full Name (for printed boarding passes and gate identification).</li>
                <li>Mobile / WhatsApp Phone Number (to dispatch digital ticket links).</li>
                <li>Email address (for booking confirmations and receipts).</li>
                <li>Payment transaction identifiers (UTR numbers or Gateway transaction tokens). We do NOT store debit/credit card numbers or banking passwords.</li>
            </ul>

            <h3 style="color: var(--gold-light); margin: 20px 0 10px;">2. Data Security</h3>
            <p>All transactions, database connections, and ticket hash tokens are encrypted using industry-standard SSL and cryptographic algorithms.</p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
