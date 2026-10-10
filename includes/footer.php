<?php
// includes/footer.php - Global public footer
?>
    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h4>Millennium India Group</h4>
                    <p style="font-size: 0.95rem; line-height: 1.6; margin-bottom: 12px;">
                        Patna's premier event management company presenting grand cultural festivals, festive Dandiya nights, and celebrity entertainment spectacles.
                    </p>
                    <p style="color: var(--gold-accent); font-weight: 600;">
                        &#128222; Hotline: <?= e(get_setting('contact_phone', '+91 98765 43210')) ?>
                    </p>
                    <p style="font-size: 0.9rem;">
                        &#9993; Email: <?= e(get_setting('contact_email', 'support@millenniumindiagroup.com')) ?>
                    </p>
                </div>

                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                        <li><a href="<?= BASE_URL ?>/events.php">All Events</a></li>
                        <li><a href="<?= BASE_URL ?>/event-detail.php?slug=jashn-e-garba-2026">Jashn-E-Garba 2026</a></li>
                        <li><a href="<?= BASE_URL ?>/booking.php?event_id=1">Book Entry Passes</a></li>
                        <li><a href="<?= BASE_URL ?>/ticket.php">Download / Print Ticket</a></li>
                        <li><a href="<?= BASE_URL ?>/contact.php">Contact & Venue Location</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Policies & Trust</h4>
                    <ul>
                        <li><a href="<?= BASE_URL ?>/terms.php">Terms & Conditions</a></li>
                        <li><a href="<?= BASE_URL ?>/privacy.php">Privacy Policy</a></li>
                        <li><a href="<?= BASE_URL ?>/refund-policy.php">Cancellation & Refund Policy</a></li>
                        <li><a href="<?= BASE_URL ?>/admin/login.php" style="color:rgba(255,255,255,0.4); font-size:0.85rem;">&#128274; Organizer Admin Portal</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Event Venue & Timing</h4>
                    <p style="font-size: 0.95rem; margin-bottom: 10px;">
                        <strong>Unique Garden, Patna</strong><br>
                        Near Saguna More / Bailey Road, Patna, Bihar
                    </p>
                    <p style="font-size: 0.9rem; color: var(--gold-light);">
                        &#128197; Friday, 16 October 2026<br>
                        &#9200; 05:00 PM – 09:00 PM IST
                    </p>
                    <div style="margin-top: 15px; font-size: 0.85rem; color: #aaa;">
                        Accepted Payment Methods: UPI (GPay, PhonePe, Paytm, BHIM), NetBanking, Credit & Debit Cards.
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                &copy; <?= date('Y') ?> <strong><?= e(get_setting('site_name', 'Millennium India Group')) ?></strong>. All rights reserved. Secure Event Ticketing Platform.
            </div>
        </div>
    </footer>
</body>
</html>
