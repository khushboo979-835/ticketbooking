<?php
/**
 * payment.php - High-Speed Dual Payment Engine (Direct UPI + Gateway)
 * Features Race-Condition Prevention (SELECT ... FOR UPDATE) & Instant UTR Validation
 * Millennium India Group - Jashn-E-Garba 2026
 */
ob_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

send_no_cache_headers();

// Validate active cart and customer session
if (empty($_SESSION['booking_cart']) || empty($_SESSION['booking_customer'])) {
    set_flash('error', 'Booking session expired. Please select your passes again.');
    header("Location: " . BASE_URL . "/booking.php?event_id=1&step=1");
    exit;
}

$cart = $_SESSION['booking_cart'];
$customer = $_SESSION['booking_customer'];
$db = getDB();

$upiId = get_setting('upi_id', 'millenniumindia@upi');
$payeeName = get_setting('upi_payee_name', 'Millennium India Group');
$razorpayKeyId = get_setting('razorpay_key_id', 'rzp_test_migDemoKey123');
$gatewayMode = get_setting('gateway_mode', 'test');

// Atomic Booking Reservation with Row-Level Lock
if (empty($_SESSION['current_booking_uid'])) {
    $bookingUid = generate_booking_uid();
    $qrToken = generate_qr_token();

    try {
        $db->beginTransaction();

        // 1. Check stock with FOR UPDATE to prevent race-condition overselling
        foreach ($cart['items'] as $item) {
            $lockStmt = $db->prepare("SELECT available_quantity FROM ticket_categories WHERE id = :id FOR UPDATE");
            $lockStmt->execute([':id' => (int)$item['id']]);
            $currentStock = $lockStmt->fetchColumn();

            if ($currentStock === false || (int)$currentStock < (int)$item['quantity']) {
                $db->rollBack();
                set_flash('error', "Sorry, '{$item['name']}' has insufficient available seats. Please adjust quantities.");
                header("Location: " . BASE_URL . "/booking.php?event_id=" . $cart['event_id'] . "&step=1");
                exit;
            }
        }

        // 2. Insert booking record in pending state
        $bStmt = $db->prepare("
            INSERT INTO bookings (
                booking_uid, event_id, customer_name, customer_phone, customer_email, 
                total_amount, payment_method, payment_status, qr_token
            ) VALUES (
                :uid, :event_id, :name, :phone, :email, 
                :total, 'direct_upi', 'pending', :qr_token
            )
        ");
        $bStmt->execute([
            ':uid'      => $bookingUid,
            ':event_id' => (int)$cart['event_id'],
            ':name'     => $customer['customer_name'],
            ':phone'    => $customer['customer_phone'],
            ':email'    => $customer['customer_email'],
            ':total'    => (float)$cart['total_amount'],
            ':qr_token' => $qrToken
        ]);
        $bookingId = $db->lastInsertId();

        // 3. Insert line items
        $iStmt = $db->prepare("
            INSERT INTO booking_items (booking_id, ticket_category_id, quantity, unit_price, subtotal)
            VALUES (:b_id, :cat_id, :qty, :price, :subtotal)
        ");
        foreach ($cart['items'] as $item) {
            $iStmt->execute([
                ':b_id'     => $bookingId,
                ':cat_id'   => (int)$item['id'],
                ':qty'      => (int)$item['quantity'],
                ':price'    => (float)$item['price'],
                ':subtotal' => (float)$item['subtotal']
            ]);
        }

        $db->commit();
        $_SESSION['current_booking_id'] = $bookingId;
        $_SESSION['current_booking_uid'] = $bookingUid;
        $_SESSION['current_qr_token'] = $qrToken;
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[payment.php reservation error]: ' . $e->getMessage());
        set_flash('error', 'Unable to secure booking reservation due to high server load. Please try again.');
        header("Location: " . BASE_URL . "/booking.php?event_id=" . $cart['event_id'] . "&step=1");
        exit;
    }
} else {
    $bookingUid = $_SESSION['current_booking_uid'];
    $bookingId = $_SESSION['current_booking_id'];
    $qrToken = $_SESSION['current_qr_token'];
}

// Handle Direct UPI UTR Form Submission (Supports both AJAX and standard POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_utr') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Security token expired. Please refresh.']);
            exit;
        }
        set_flash('error', 'Security token expired. Please try again.');
        header("Location: " . BASE_URL . "/payment.php");
        exit;
    }

    $utr = preg_replace('/[^A-Za-z0-9]/', '', trim($_POST['transaction_ref'] ?? ''));
    if (strlen($utr) < 6) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Please enter a valid 12-digit UPI UTR / Transaction Reference number.']);
            exit;
        }
        set_flash('error', 'Please enter a valid 12-digit UPI UTR / Transaction Reference number.');
        header("Location: " . BASE_URL . "/payment.php");
        exit;
    }

    try {
        $stmt = $db->prepare("
            UPDATE bookings 
            SET payment_method = 'direct_upi', transaction_ref = :ref, payment_status = 'pending' 
            WHERE id = :id AND booking_uid = :uid
        ");
        $stmt->execute([
            ':ref' => $utr,
            ':id'  => $bookingId,
            ':uid' => $bookingUid
        ]);

        // Clear cart session variables
        unset($_SESSION['booking_cart'], $_SESSION['booking_customer'], $_SESSION['current_booking_uid'], $_SESSION['current_booking_id'], $_SESSION['current_qr_token']);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'redirect' => BASE_URL . '/confirmation.php?uid=' . urlencode($bookingUid)
            ]);
            exit;
        }

        header("Location: " . BASE_URL . "/confirmation.php?uid=" . urlencode($bookingUid));
        exit;
    } catch (PDOException $e) {
        error_log('[payment.php UTR update error]: ' . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Database error recording payment. Please contact support.']);
            exit;
        }
        set_flash('error', 'Database error recording payment reference.');
        header("Location: " . BASE_URL . "/payment.php");
        exit;
    }
}

// Lightweight standard UPI Intent String
$cleanAmount = number_format($cart['total_amount'], 2, '.', '');
$upiPayload = "upi://pay?pa=" . rawurlencode($upiId) . 
              "&pn=" . rawurlencode($payeeName) . 
              "&am=" . $cleanAmount . 
              "&cu=INR" . 
              "&tn=" . rawurlencode("MIG-" . $bookingUid);

$pageTitle = 'Choose Payment Mode | ' . e($cart['event_title']);
$currentNav = 'garba';

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 35px; padding-bottom: 70px;">

    <!-- Top Step Progression Indicator -->
    <div style="display: flex; justify-content: center; margin-bottom: 25px;">
        <div style="display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 0.95rem;">
            <a href="<?= BASE_URL ?>/booking.php?event_id=<?= $cart['event_id'] ?>&step=1" style="background: rgba(245,183,34,0.2); color: var(--gold-light); padding: 6px 16px; border-radius: 50px;">
                1. Select Tickets
            </a>
            <span style="color: #666;">&rarr;</span>
            <a href="<?= BASE_URL ?>/booking.php?event_id=<?= $cart['event_id'] ?>&step=2" style="background: rgba(245,183,34,0.2); color: var(--gold-light); padding: 6px 16px; border-radius: 50px;">
                2. Customer Details
            </a>
            <span style="color: #666;">&rarr;</span>
            <span style="background: var(--gold-accent); color: #1f0411; padding: 6px 16px; border-radius: 50px;">
                3. Payment
            </span>
        </div>
    </div>

    <div style="max-width: 860px; margin: 0 auto;">

        <!-- Top Order Info Pill -->
        <div style="background: linear-gradient(135deg, rgba(122,18,68,0.7), rgba(40,6,22,0.95)); border: 1px solid var(--gold-accent); border-radius: 12px; padding: 18px 24px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <span style="font-size: 0.8rem; color: var(--gold-light); text-transform: uppercase; font-weight: 700;">Booking Reference ID</span>
                <div style="font-size: 1.35rem; font-family: monospace; font-weight: 900; color: #fff;"><?= e($bookingUid) ?></div>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 0.8rem; color: #aaa; text-transform: uppercase;">Total Payable Amount</span>
                <div style="font-size: 1.8rem; font-weight: 900; color: var(--gold-accent);"><?= format_inr($cart['total_amount']) ?></div>
            </div>
        </div>

        <!-- Payment Mode Tabs (Matches Infographic Box 4 & Box 5) -->
        <div class="payment-tabs">
            <button type="button" class="payment-tab-btn active" id="tab-btn-upi" onclick="switchPaymentTab('upi')">
                <span>&#128242;</span> <span>UPI Payment</span>
            </button>
            <button type="button" class="payment-tab-btn" id="tab-btn-gateway" onclick="switchPaymentTab('gateway')">
                <span>&#128179;</span> <span>Payment Gateway</span>
            </button>
        </div>

        <!-- TAB 1: UPI PAYMENT (Matches Infographic Box 4) -->
        <div class="payment-tab-pane active" id="pane-upi">
            <div class="festive-card" style="border: 2px solid var(--gold-accent); padding: 30px 25px;">
                
                <div style="text-align: center; margin-bottom: 20px;">
                    <h2 style="color: #fff; font-size: 1.6rem; font-weight: 800; margin-bottom: 6px;">Scan &amp; Pay Using UPI</h2>
                    <p style="color: var(--text-muted); font-size: 0.95rem;">Pay directly to Millennium India Group with zero gateway fees</p>
                </div>

                <!-- UPI White Card (Scan Box + App Badges) -->
                <div class="upi-pay-card" style="border: 2px solid #e2e8f0;">
                    
                    <!-- Dynamic Fast Canvas QR Container -->
                    <div id="upi-qrcode" class="upi-qr-box"></div>

                    <!-- Accepted UPI Apps Indicators -->
                    <div style="display: flex; justify-content: center; align-items: center; gap: 14px; margin: 15px 0 10px; font-weight: 700; font-size: 0.9rem; color: #475569;">
                        <span><strong style="color: #4285F4;">G</strong>Pay</span> &bull; 
                        <span style="color: #5f259f;">PhonePe</span> &bull; 
                        <span style="color: #00baf2;">Paytm</span> &bull; 
                        <span style="color: #0c2340;">BHIM</span>
                    </div>

                    <!-- UPI ID with Copy Button -->
                    <div style="margin-top: 10px;">
                        <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;">UPI ID:</span><br>
                        <span class="upi-vpa" id="upi-id-text"><?= e($upiId) ?></span>
                        <button type="button" class="btn btn-secondary btn-sm" style="margin-left: 8px; color: #1e293b; border-color: #cbd5e1; background: #f1f5f9;" onclick="copyUpiId()">
                            &#128203; Copy
                        </button>
                    </div>

                    <!-- Open UPI App Directly on Mobile -->
                    <div style="margin-top: 15px;">
                        <a href="<?= e($upiPayload) ?>" class="btn btn-primary btn-sm" style="background: #7a1244; color: #fff; border-radius: 20px; padding: 8px 24px; font-weight: 700;">
                            &#128241; Pay via Installed UPI App
                        </a>
                    </div>
                </div>

                <!-- Steps List (Exact Infographic Copy) -->
                <div style="background: rgba(20, 2, 11, 0.7); border: 1px solid rgba(245, 183, 34, 0.3); border-radius: 10px; padding: 18px 22px; margin: 25px 0;">
                    <ol style="margin: 0; padding-left: 20px; font-size: 0.95rem; line-height: 1.8; color: #e2e8f0;">
                        <li><strong>Scan the QR code</strong> and pay the exact amount: <span style="color: var(--gold-accent); font-weight: bold;"><?= format_inr($cart['total_amount']) ?></span>.</li>
                        <li>After payment, enter your 12-digit UTR/Ref ID and click on <strong>I Have Paid</strong>.</li>
                        <li>Your booking will be verified by our team and your digital QR pass will be issued immediately.</li>
                    </ol>
                </div>

                <!-- UTR Input & "I Have Paid" Form -->
                <form action="<?= BASE_URL ?>/payment.php" method="POST" id="utr-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="submit_utr">

                    <div class="form-group">
                        <label class="form-label" for="transaction_ref">12-Digit UTR / Transaction Reference ID *</label>
                        <input type="text" 
                               name="transaction_ref" 
                               id="transaction_ref" 
                               class="form-control" 
                               placeholder="e.g. 428901234567" 
                               maxlength="30" 
                               required 
                               style="font-size: 1.15rem; font-family: monospace; letter-spacing: 1px;">
                        <small style="color: #aaa; font-size: 0.8rem; margin-top: 4px; display: block;">Found in your banking/UPI receipt under "UPI Ref No" or "UTR".</small>
                    </div>

                    <div style="margin-top: 20px;">
                        <button type="submit" id="btn-i-have-paid" class="btn btn-primary btn-block" style="padding: 15px; font-size: 1.15rem; font-weight: 800; text-transform: uppercase;">
                            &#10004; I Have Paid &rarr;
                        </button>
                    </div>
                </form>

            </div>
        </div>

        <!-- TAB 2: PAYMENT GATEWAY (Matches Infographic Box 5) -->
        <div class="payment-tab-pane" id="pane-gateway">
            <div class="festive-card" style="border: 2px solid var(--gold-accent); padding: 30px 25px; text-align: center;">
                
                <h2 style="color: #fff; font-size: 1.6rem; font-weight: 800; margin-bottom: 6px;">Pay Securely with Razorpay</h2>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 25px;">Instant automated digital QR pass issuance</p>

                <!-- Gateway Cards & Badges (Matches Infographic Box 5) -->
                <div style="background: rgba(30, 4, 17, 0.85); border: 1px solid var(--gold-accent); border-radius: 12px; padding: 26px; max-width: 520px; margin: 0 auto;">
                    
                    <div style="display: flex; justify-content: center; gap: 20px; margin-bottom: 20px; flex-wrap: wrap;">
                        <div style="background: #fff; padding: 8px 16px; border-radius: 6px; font-weight: 800; color: #0c2340; font-size: 1.1rem;">
                            Razorpay
                        </div>
                        <div style="background: #fff; padding: 8px 16px; border-radius: 6px; font-weight: 800; color: #10b981; font-size: 1.1rem;">
                            Cashfree
                        </div>
                    </div>

                    <div style="display: flex; justify-content: center; gap: 14px; margin-bottom: 25px; color: #cbd5e1; font-size: 0.88rem; flex-wrap: wrap;">
                        <span>&#128179; Card</span> &bull; 
                        <span>&#128242; UPI</span> &bull; 
                        <span>&#127974; Netbanking</span> &bull; 
                        <span>&#128091; Wallet</span>
                    </div>

                    <button type="button" id="razorpay-trigger-btn" class="btn btn-primary btn-block" style="padding: 15px; font-size: 1.2rem; font-weight: 800;">
                        Pay <?= format_inr($cart['total_amount']) ?> Securely
                    </button>

                    <div style="margin-top: 15px; color: #34d399; font-size: 0.88rem; display: flex; align-items: center; justify-content: center; gap: 6px;">
                        <span>&#10004;</span> <span>100% Secure 256-Bit SSL Encrypted Payment</span>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- Non-Blocking Libraries placed before body end -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
// Switch Payment Tabs
function switchPaymentTab(type) {
    document.querySelectorAll('.payment-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.payment-tab-pane').forEach(pane => pane.classList.remove('active'));

    if (type === 'upi') {
        document.getElementById('tab-btn-upi').classList.add('active');
        document.getElementById('pane-upi').classList.add('active');
    } else {
        document.getElementById('tab-btn-gateway').classList.add('active');
        document.getElementById('pane-gateway').classList.add('active');
    }
}

// Generate UPI QR Code locally into Fast Canvas
document.addEventListener('DOMContentLoaded', function() {
    const qrContainer = document.getElementById('upi-qrcode');
    if (qrContainer && typeof QRCode !== 'undefined') {
        qrContainer.innerHTML = '';
        new QRCode(qrContainer, {
            text: <?= json_encode($upiPayload) ?>,
            width: 170,
            height: 170,
            colorDark: "#1f0411",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });
    }

    // Instant UTR Submission via Fetch with Fallback
    const utrForm = document.getElementById('utr-form');
    const iHavePaidBtn = document.getElementById('btn-i-have-paid');

    if (utrForm && iHavePaidBtn) {
        utrForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const utrVal = document.getElementById('transaction_ref').value.trim();
            if (utrVal.length < 6) {
                alert('Please enter a valid 12-digit UPI UTR number.');
                return;
            }

            iHavePaidBtn.disabled = true;
            iHavePaidBtn.textContent = 'Verifying Reference...';

            const formData = new FormData(utrForm);

            fetch('<?= BASE_URL ?>/payment.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    alert(data.message || 'Error recording UTR. Falling back to submission...');
                    utrForm.submit();
                }
            })
            .catch(() => {
                // Fallback to standard form post
                utrForm.submit();
            });
        });
    }

    // Razorpay Checkout Trigger
    const rzpBtn = document.getElementById('razorpay-trigger-btn');
    if (rzpBtn) {
        rzpBtn.addEventListener('click', function(e) {
            e.preventDefault();

            const options = {
                "key": <?= json_encode($razorpayKeyId) ?>,
                "amount": <?= round($cart['total_amount'] * 100) ?>,
                "currency": "INR",
                "name": <?= json_encode($payeeName) ?>,
                "description": <?= json_encode("Tickets for " . $cart['event_title']) ?>,
                "image": "<?= BASE_URL ?>/assets/uploads/garba-banner.jpg",
                "handler": function (response){
                    fetch('<?= BASE_URL ?>/api/verify-payment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            booking_uid: <?= json_encode($bookingUid) ?>,
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_order_id: response.razorpay_order_id || '',
                            razorpay_signature: response.razorpay_signature || ''
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            window.location.href = '<?= BASE_URL ?>/confirmation.php?uid=' + encodeURIComponent(data.booking_uid);
                        } else {
                            alert(data.message || 'Payment verification failed.');
                        }
                    })
                    .catch(() => {
                        alert('Network issue verifying transaction. Please contact event desk.');
                    });
                },
                "prefill": {
                    "name": <?= json_encode($customer['customer_name']) ?>,
                    "email": <?= json_encode($customer['customer_email']) ?>,
                    "contact": <?= json_encode($customer['customer_phone']) ?>
                },
                "theme": {
                    "color": "#7a1244"
                }
            };

            const rzp = new Razorpay(options);
            rzp.open();
        });
    }
});

function copyUpiId() {
    const text = document.getElementById('upi-id-text').innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert('UPI ID copied to clipboard: ' + text);
    }).catch(() => {
        prompt('Copy UPI ID:', text);
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
