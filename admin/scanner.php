<?php
// admin/scanner.php - Mobile-Friendly QR Code Gate Entry Scanner
ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

require_admin_login();
send_no_cache_headers();

$adminCurrent = 'scanner';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gate QR Scanner | Millennium Admin</title>
    <link rel="stylesheet" href="<?= asset_v('assets/css/admin.css') ?>">
    <style>
        .scanner-container {
            max-width: 620px;
            margin: 0 auto;
        }
        #qr-reader {
            width: 100%;
            border-radius: 14px;
            overflow: hidden;
            background: #000;
            border: 2px solid var(--admin-primary);
            box-shadow: 0 8px 30px rgba(0,0,0,0.5);
        }
        #qr-reader video {
            object-fit: cover;
            border-radius: 12px;
        }
        .scanner-ctrls {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-header">
            <div>
                <h1 class="admin-title">&#128247; Gate QR Entry Scanner</h1>
                <div class="admin-subtitle">Live Camera Turnstile Validator for <strong>Jashn-E-Garba 2026</strong></div>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn-sm" style="background:#202434; border:1px solid #2e344a; color:#fff; text-decoration:none;">
                    &larr; Back to Bookings
                </a>
            </div>
        </div>

        <?= render_flash() ?>

        <div class="scanner-container">
            <!-- Camera Viewfinder -->
            <div id="qr-reader"></div>

            <div style="text-align: center; margin-top: 15px;">
                <div id="scanner-status" style="font-weight: 700; font-size: 1rem; color: #34d399;">
                    Initializing camera...
                </div>
            </div>

            <!-- Scanner Controls -->
            <div class="scanner-ctrls">
                <button type="button" id="btn-start-scanner" class="btn-verify" style="background:var(--admin-primary); color:#12141d; padding:10px 22px; font-size:0.95rem;">
                    &#9658; Start Scanner
                </button>
                <button type="button" id="btn-stop-scanner" class="btn-sm" style="background:#ef4444; color:#fff; border:none; padding:10px 22px; font-size:0.95rem; cursor:pointer; display:none;">
                    &#9632; Pause Camera
                </button>
            </div>

            <!-- Manual Input Fallback -->
            <div class="admin-card" style="margin-top: 25px; padding: 20px;">
                <h4 style="color: var(--admin-primary); margin-bottom: 10px; font-size: 1rem;">
                    &#9998; Manual Token or Booking UID Entry
                </h4>
                <p style="font-size: 0.82rem; color: var(--admin-text-muted); margin-bottom: 12px;">
                    If attendee's phone screen is cracked or camera cannot scan, type the Booking UID (e.g. MIG2026...) or QR Token below:
                </p>

                <div style="display: flex; gap: 10px;">
                    <input type="text" id="manual-token-input" class="form-control" placeholder="Paste Booking UID or Token..." style="background:#12141d; border:1px solid #2e344a; color:#fff; flex:1; padding:10px 14px; border-radius:8px;">
                    <button type="button" id="btn-manual-verify" class="btn-verify" style="background:var(--admin-primary); color:#12141d; padding:10px 18px;">
                        Validate &rarr;
                    </button>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Instant Modal for Scan Feedback (Color-Coded) -->
<div class="modal-overlay" id="scan-result-modal">
    <div class="modal-content" id="modal-result-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
            <h3 id="modal-result-title" style="margin: 0; font-size: 1.35rem;">Scan Result</h3>
            <span id="modal-status-badge" class="badge">STATUS</span>
        </div>

        <div id="modal-result-details" style="color: #e2e8f0; font-size: 0.95rem; line-height: 1.6; margin-bottom: 25px;">
            <!-- Dynamic Content Injected Here -->
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" class="btn-verify" style="background:var(--admin-primary); color:#12141d; padding:12px 24px; font-size:1rem; width:100%; justify-content:center;" onclick="dismissModal()">
                &#9654; Scan Next Ticket
            </button>
        </div>
    </div>
</div>

<!-- QR Scanner Libraries before closing body -->
<script src="https://unpkg.com/html5-qrcode"></script>
<script src="<?= asset_v('assets/js/scanner.js') ?>"></script>
</body>
</html>
