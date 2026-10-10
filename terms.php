<?php
// terms.php - Terms & Conditions
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

$pageTitle = 'Terms & Conditions | Millennium India Group';
$currentNav = 'terms';

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 40px; padding-bottom: 70px;">
    <div class="festive-card" style="max-width: 900px; margin: 0 auto;">
        <h1 class="card-title">&#128220; Terms &amp; Conditions</h1>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 25px;">Last Updated: October 2026</p>

        <div style="font-size: 0.95rem; line-height: 1.8; color: #ddd;">
            <h3 style="color: var(--gold-light); margin: 20px 0 10px;">1. General Ticketing Rules</h3>
            <p>Every attendee must possess a valid Digital QR Ticket issued by Millennium India Group. Digital passes are scanned electronically at the entry gate. Re-entry without wristbands or authorization is prohibited.</p>

            <h3 style="color: var(--gold-light); margin: 20px 0 10px;">2. Age &amp; Entry Criteria</h3>
            <p>Children above 5 years of age require an independent entry pass. Persons under the age of 16 must be accompanied by an adult. Couple and Family passes must enter the venue together during check-in.</p>

            <h3 style="color: var(--gold-light); margin: 20px 0 10px;">3. Code of Conduct &amp; Security</h3>
            <p>Management reserves the right to refuse entry or evict any person behaving inappropriately, causing disruption, or carrying banned substances (including alcohol, tobacco, firearms, sharp items, and outside food). Bag checks and metal detector frisks are mandatory.</p>

            <h3 style="color: var(--gold-light); margin: 20px 0 10px;">4. Event Changes or Force Majeure</h3>
            <p>Millennium India Group reserves the right to alter the artist lineup, sound timing, or itinerary due to unavoidable weather disruptions, civic orders, or force majeure events.</p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
