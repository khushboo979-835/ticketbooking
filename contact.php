<?php
// contact.php - Contact Us & Venue Details
ob_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

$pageTitle = 'Contact Us | Millennium India Group';
$currentNav = 'contact';

$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('success', 'Thank you! Your message has been received. Our event coordinator will get in touch shortly.');
        $submitted = true;
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 40px; padding-bottom: 70px;">
    <div style="text-align: center; margin-bottom: 40px;">
        <span style="color: var(--gold-accent); font-weight: 700; text-transform: uppercase;">Reach Out To Us</span>
        <h1 style="font-size: 2.5rem; font-weight: 800; color: #fff; margin-top: 6px;">Contact Millennium India Group</h1>
        <p style="color: var(--text-muted); max-width: 600px; margin: 0 auto;">Have queries regarding bulk passes, sponsorships, stall bookings, or ticketing support? We are here to help!</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 30px;">
        <!-- Contact Information Card -->
        <div class="festive-card">
            <h3 class="card-title">&#128222; Official Event Desk</h3>
            
            <div style="margin-top: 20px;">
                <div style="margin-bottom: 20px;">
                    <div style="color: var(--gold-light); font-weight: 700; font-size: 0.9rem; text-transform: uppercase;">Helpline Numbers</div>
                    <div style="font-size: 1.2rem; font-weight: 800; color: #fff; margin-top: 4px;">
                        <?= e(get_setting('contact_phone', '+91 98765 43210')) ?>
                    </div>
                    <small style="color: #aaa;">Mon – Sun: 09:00 AM – 10:00 PM IST</small>
                </div>

                <div style="margin-bottom: 20px;">
                    <div style="color: var(--gold-light); font-weight: 700; font-size: 0.9rem; text-transform: uppercase;">Official Support Email</div>
                    <div style="font-size: 1.1rem; font-weight: 700; color: #fff; margin-top: 4px;">
                        <?= e(get_setting('contact_email', 'support@millenniumindiagroup.com')) ?>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <div style="color: var(--gold-light); font-weight: 700; font-size: 0.9rem; text-transform: uppercase;">Event Venue</div>
                    <div style="font-size: 1.05rem; font-weight: 700; color: #fff; margin-top: 4px;">
                        Unique Garden, Patna
                    </div>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 4px;">
                        Near Saguna More / Bailey Road, Danapur - Patna, Bihar 801503
                    </p>
                </div>

                <div style="background: rgba(245,183,34,0.1); border: 1px dashed var(--gold-accent); border-radius: 8px; padding: 14px;">
                    <strong style="color: var(--gold-light);">&#128172; Stall Bookings &amp; Corporate Sponsorships</strong>
                    <p style="font-size: 0.85rem; color: #ccc; margin-top: 4px;">
                        For food stalls, brand banners, or group corporate pass discounts, contact our marketing desk directly at <a href="mailto:support@millenniumindiagroup.com">support@millenniumindiagroup.com</a>.
                    </p>
                </div>
            </div>
        </div>

        <!-- Contact Form Card -->
        <div class="festive-card">
            <h3 class="card-title">&#9993; Send Us a Message</h3>

            <form action="<?= BASE_URL ?>/contact.php" method="POST" style="margin-top: 20px;">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="contact_name">Your Name *</label>
                    <input type="text" name="name" id="contact_name" class="form-control" placeholder="Enter your full name" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="contact_phone">Phone Number *</label>
                    <input type="tel" name="phone" id="contact_phone" class="form-control" placeholder="Enter your mobile number" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="contact_subject">Subject *</label>
                    <input type="text" name="subject" id="contact_subject" class="form-control" placeholder="e.g. Bulk Ticket Inquiry / Stall Booking" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="contact_message">Your Message *</label>
                    <textarea name="message" id="contact_message" class="form-control" rows="4" placeholder="How can we assist you?" required></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="padding: 12px;">
                    Submit Inquiry &rarr;
                </button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
