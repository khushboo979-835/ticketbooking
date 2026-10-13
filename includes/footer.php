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
                        &#128222; Hotline: <a href="tel:7004665804" style="color: var(--gold-accent); text-decoration: none;">+91 7004665804</a>
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
                        <li><a href="<?= BASE_URL ?>/ticket.php">&#128269; Search Ticket / Receipt</a></li>
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
                    <?php 
                        $fEvent = function_exists('get_event') ? get_event(1) : null;
                        $fVenue = $fEvent['venue'] ?? 'Unique Garden, Patna';
                        $fDate = !empty($fEvent['date']) ? date('l, d F Y', strtotime($fEvent['date'])) : 'Friday, 16 October 2026';
                        $fTime = (!empty($fEvent['start_time']) && !empty($fEvent['end_time'])) ? (date('h:i A', strtotime($fEvent['start_time'])) . ' – ' . date('h:i A', strtotime($fEvent['end_time']))) : '05:00 PM – 09:00 PM';
                    ?>
                    <p style="font-size: 0.95rem; margin-bottom: 10px;">
                        <strong><?= e($fVenue) ?></strong><br>
                        Near Saguna More / Bailey Road, Patna, Bihar
                    </p>
                    <p style="font-size: 0.9rem; color: var(--gold-light);">
                        &#128197; <?= $fDate ?><br>
                        &#9200; <?= $fTime ?> IST
                    </p>
                    <div style="margin-top: 15px; font-size: 0.85rem; color: #aaa;">
                        Accepted Payment Methods: UPI (GPay, PhonePe, Paytm, BHIM), NetBanking, Credit & Debit Cards.
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <!-- Compact Visitor Hit Counter -->
                <div class="visitor-counter-badge">
                    <span>&#128065;</span>
                    <span>Total Visits:</span>
                    <span class="count-num"><?= number_format(get_and_increment_visitor_count()) ?></span>
                </div>

                <div style="font-size: 0.88rem; color: #94a3b8;">
                    &copy; <?= date('Y') ?> <strong><?= e(get_setting('site_name', 'Millennium India Group')) ?></strong>. All rights reserved.
                </div>
            </div>
        </div>
    </footer>

    <!-- BookMyShow-Style Floating Quick Action Bar on Mobile -->
    <?php if (empty($currentNav) || $currentNav !== 'garba' || !str_contains($_SERVER['REQUEST_URI'] ?? '', 'payment.php')): ?>
    <div class="mobile-sticky-book-bar">
        <div>
            <div style="font-size:0.75rem; color:#aaa; text-transform:uppercase;">Passes From</div>
            <div style="font-size:1.25rem; font-weight:900; color:var(--gold-accent);" id="sticky-bar-price">₹199</div>
        </div>
        <button type="button" onclick="handleMobileStickyAction()" class="btn btn-primary" id="mobile-sticky-book-btn" style="padding:10px 24px; font-weight:800; font-size:1rem; border-radius:50px; box-shadow:0 4px 15px rgba(245,183,34,0.4); cursor:pointer; border:none;">
            &#127915; Book Now
        </button>
    </div>
    <script>
    function handleMobileStickyAction() {
        const bookingForm = document.getElementById('unified-booking-form') || document.getElementById('ticket-selection-form');
        if (bookingForm) {
            const nameInput = document.getElementById('customer_name');
            const phoneInput = document.getElementById('customer_phone');
            if (nameInput && !nameInput.value.trim()) {
                nameInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                nameInput.focus();
                return;
            }
            if (phoneInput && !phoneInput.value.trim()) {
                phoneInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                phoneInput.focus();
                return;
            }
            if (typeof bookingForm.requestSubmit === 'function') {
                bookingForm.requestSubmit();
            } else {
                bookingForm.submit();
            }
        } else {
            window.location.href = '<?= BASE_URL ?>/booking.php?event_id=1';
        }
    }
    </script>
    <?php endif; ?>
</body>
</html>
