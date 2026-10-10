<?php
// refund-policy.php - Cancellation & Refund Policy
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

$pageTitle = 'Cancellation & Refund Policy | Millennium India Group';
$currentNav = 'refund';

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 40px; padding-bottom: 70px;">
    <div class="festive-card" style="max-width: 900px; margin: 0 auto;">
        <h1 class="card-title">&#128176; Cancellation &amp; Refund Policy</h1>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 25px;">Last Updated: October 2026</p>

        <div style="font-size: 0.95rem; line-height: 1.8; color: #ddd;">
            <h3 style="color: var(--gold-light); margin: 20px 0 10px;">1. Non-Refundable Passes</h3>
            <p>Due to limited venue capacity and the live entertainment nature of "Jashn-E-Garba 2026", all ticket purchases are strictly <strong>non-refundable and non-cancellable</strong> once issued or approved.</p>

            <h3 style="color: var(--gold-light); margin: 20px 0 10px;">2. Failed Transactions &amp; Duplicate Debits</h3>
            <p>In case an amount is debited from your bank account but your booking is not confirmed due to gateway or bank timeout:</p>
            <ul style="padding-left: 20px;">
                <li>The debited amount is usually reversed automatically by your issuing bank within 3 to 7 working days.</li>
                <li>If the amount is received in our account, our support team can verify the UTR and instantly issue your digital pass or initiate a manual reversal.</li>
            </ul>

            <h3 style="color: var(--gold-light); margin: 20px 0 10px;">3. Event Postponement or Cancellation</h3>
            <p>In the event that the concert or festival is called off due to natural calamity or government directives, tickets will be transferred to the rescheduled date or refunded pro-rata as per organizers' terms.</p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
