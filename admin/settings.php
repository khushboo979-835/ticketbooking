<?php
// admin/settings.php - Portal & Payment Settings Console
ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

require_admin_login();
send_no_cache_headers();

$adminCurrent = 'settings';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security token expired.');
        header("Location: " . BASE_URL . "/admin/settings.php");
        exit;
    }

    $settingsKeys = [
        'site_name',
        'site_tagline',
        'contact_email',
        'contact_phone',
        'contact_address',
        'upi_id',
        'upi_payee_name',
        'razorpay_key_id',
        'razorpay_key_secret',
        'gateway_mode',
        'terms_conditions'
    ];

    foreach ($settingsKeys as $key) {
        if (isset($_POST[$key])) {
            update_setting($key, trim($_POST[$key]));
        }
    }

    set_flash('success', 'Site settings and payment gateway configurations saved successfully.');
    header("Location: " . BASE_URL . "/admin/settings.php");
    exit;
}

$settings = get_all_settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site &amp; Payment Settings | Millennium Admin</title>
    <link rel="stylesheet" href="<?= asset_v('assets/css/admin.css') ?>">
</head>
<body>
<div class="admin-wrapper">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-header">
            <div>
                <h1 class="admin-title">&#9881; Site Settings &amp; Payment Credentials</h1>
                <div class="admin-subtitle">Configure organization details, UPI IDs, and Razorpay API credentials</div>
            </div>
        </div>

        <?= render_flash() ?>

        <form action="<?= BASE_URL ?>/admin/settings.php" method="POST">
            <?= csrf_field() ?>

            <!-- Direct UPI Settings Card -->
            <div class="admin-card">
                <h3 class="admin-card-title" style="color:var(--admin-primary); margin-bottom:15px;">
                    &#128242; Direct UPI Payment Settings (Day-1 Instant Launch)
                </h3>
                <p style="color:var(--admin-text-muted); font-size:0.88rem; margin-bottom:20px;">
                    Attendees scanning the payment QR code on checkout will pay directly into this VPA. Zero gateway commissions.
                </p>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                    <div>
                        <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:6px;">Merchant UPI VPA / ID *</label>
                        <input type="text" name="upi_id" required class="form-control" value="<?= e($settings['upi_id'] ?? 'millenniumindia@upi') ?>" placeholder="yourbusiness@bank" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px 14px; border-radius:6px;">
                        <small style="color:#777; font-size:0.75rem;">e.g. 9876543210@paytm, merchant@icici, etc.</small>
                    </div>

                    <div>
                        <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:6px;">UPI Payee / Business Display Name *</label>
                        <input type="text" name="upi_payee_name" required class="form-control" value="<?= e($settings['upi_payee_name'] ?? 'Millennium India Group') ?>" placeholder="Millennium India Group" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px 14px; border-radius:6px;">
                    </div>
                </div>
            </div>

            <!-- Razorpay Gateway Settings Card -->
            <div class="admin-card">
                <h3 class="admin-card-title" style="color:var(--admin-primary); margin-bottom:15px;">
                    &#128179; Payment Gateway Configuration (Razorpay / Cashfree)
                </h3>
                <p style="color:var(--admin-text-muted); font-size:0.88rem; margin-bottom:20px;">
                    Enables Credit Cards, Debit Cards, and NetBanking checkout via Razorpay JS checkout.
                </p>

                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; margin-bottom:15px;">
                    <div>
                        <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:6px;">Gateway Mode *</label>
                        <select name="gateway_mode" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px 14px; border-radius:6px;">
                            <option value="test" <?= ($settings['gateway_mode'] ?? 'test') === 'test' ? 'selected' : '' ?>>Test Mode (Sandbox / Demo)</option>
                            <option value="live" <?= ($settings['gateway_mode'] ?? 'test') === 'live' ? 'selected' : '' ?>>Live Mode (Production)</option>
                        </select>
                    </div>

                    <div>
                        <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:6px;">Razorpay Key ID *</label>
                        <input type="text" name="razorpay_key_id" class="form-control" value="<?= e($settings['razorpay_key_id'] ?? '') ?>" placeholder="rzp_test_..." style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px 14px; border-radius:6px;">
                    </div>

                    <div>
                        <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:6px;">Razorpay Key Secret</label>
                        <input type="password" name="razorpay_key_secret" class="form-control" value="<?= e($settings['razorpay_key_secret'] ?? '') ?>" placeholder="Key Secret" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px 14px; border-radius:6px;">
                    </div>
                </div>
            </div>

            <!-- Brand & Contact Settings -->
            <div class="admin-card">
                <h3 class="admin-card-title" style="color:var(--admin-primary); margin-bottom:15px;">
                    &#127970; Organization &amp; Support Contact
                </h3>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:15px;">
                    <div>
                        <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:6px;">Brand Name</label>
                        <input type="text" name="site_name" class="form-control" value="<?= e($settings['site_name'] ?? 'Millennium India Group') ?>" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px 14px; border-radius:6px;">
                    </div>

                    <div>
                        <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:6px;">Support Phone / WhatsApp</label>
                        <input type="text" name="contact_phone" class="form-control" value="<?= e($settings['contact_phone'] ?? '+91 98765 43210') ?>" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px 14px; border-radius:6px;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:15px;">
                    <div>
                        <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:6px;">Support Email</label>
                        <input type="email" name="contact_email" class="form-control" value="<?= e($settings['contact_email'] ?? 'support@millenniumindiagroup.com') ?>" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px 14px; border-radius:6px;">
                    </div>

                    <div>
                        <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:6px;">Venue / Office Address</label>
                        <input type="text" name="contact_address" class="form-control" value="<?= e($settings['contact_address'] ?? 'Unique Garden, Bailey Road, Patna') ?>" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px 14px; border-radius:6px;">
                    </div>
                </div>

                <div style="margin-bottom:15px;">
                    <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:6px;">Terms &amp; Instructions on Tickets</label>
                    <textarea name="terms_conditions" rows="4" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px 14px; border-radius:6px;"><?= e($settings['terms_conditions'] ?? '') ?></textarea>
                </div>
            </div>

            <div style="margin-top:20px; display:flex; justify-content:flex-end;">
                <button type="submit" class="btn-verify" style="background:var(--admin-primary); color:#12141d; font-size:1.05rem; padding:12px 30px;">
                    &#128190; Save All Settings
                </button>
            </div>
        </form>
    </main>
</div>
</body>
</html>
