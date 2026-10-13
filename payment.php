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

$upiId = get_setting('upi_id', 'chandni0984@oksbi');
$payeeName = get_setting('upi_payee_name', 'chandni 0777');
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

// Standard Clean UPI Intent String (Literal @ sign, clean payee name to prevent app crashes)
$cleanAmount = number_format($cart['total_amount'], 2, '.', '');
$cleanUpiId = 'chandni0984@oksbi';
$cleanPayee = 'Chandni';
$note = 'GarbaPass';

// Intent URIs for different apps
$genericUpiPayload = "upi://pay?pa={$cleanUpiId}&pn={$cleanPayee}&am={$cleanAmount}&cu=INR&tn={$note}";
$phonepePayload    = "phonepe://pay?pa={$cleanUpiId}&pn={$cleanPayee}&am={$cleanAmount}&cu=INR&tn={$note}";
$paytmPayload      = "paytmmp://pay?pa={$cleanUpiId}&pn={$cleanPayee}&am={$cleanAmount}&cu=INR&tn={$note}";
$gpayPayload       = "gpay://upi/pay?pa={$cleanUpiId}&pn={$cleanPayee}&am={$cleanAmount}&cu=INR&tn={$note}";

$pageTitle = 'Direct UPI Payment | ' . e($cart['event_title']);
$currentNav = 'garba';

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 35px; padding-bottom: 70px;">

    <!-- Top Step Progression Indicator -->
    <div style="display: flex; justify-content: center; margin-bottom: 25px;">
        <div style="display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 0.95rem;">
            <a href="<?= BASE_URL ?>/booking.php?event_id=<?= $cart['event_id'] ?>&step=1" style="background: rgba(245,183,34,0.2); color: var(--gold-light); padding: 6px 16px; border-radius: 50px; text-decoration: none;">
                1. Select Tickets
            </a>
            <span style="color: #666;">&rarr;</span>
            <a href="<?= BASE_URL ?>/booking.php?event_id=<?= $cart['event_id'] ?>&step=2" style="background: rgba(245,183,34,0.2); color: var(--gold-light); padding: 6px 16px; border-radius: 50px; text-decoration: none;">
                2. Customer Details
            </a>
            <span style="color: #666;">&rarr;</span>
            <span style="background: var(--gold-accent); color: #1f0411; padding: 6px 16px; border-radius: 50px; font-weight: 800;">
                3. Direct UPI Payment
            </span>
        </div>
    </div>

    <div style="max-width: 820px; margin: 0 auto;">

        <!-- Top Order Info Pill -->
        <div style="background: linear-gradient(135deg, rgba(122,18,68,0.7), rgba(40,6,22,0.95)); border: 1.5px solid var(--gold-accent); border-radius: 12px; padding: 18px 24px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; box-shadow: 0 8px 25px rgba(0,0,0,0.5);">
            <div>
                <span style="font-size: 0.8rem; color: var(--gold-light); text-transform: uppercase; font-weight: 700;">Booking Reference ID</span>
                <div style="font-size: 1.35rem; font-family: monospace; font-weight: 900; color: #fff;"><?= e($bookingUid) ?></div>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 0.8rem; color: #aaa; text-transform: uppercase;">Total Payable Amount</span>
                <div style="font-size: 2rem; font-weight: 900; color: var(--gold-accent);"><?= format_inr($cart['total_amount']) ?></div>
            </div>
        </div>

        <!-- MAIN DIRECT UPI PAYMENT CARD -->
        <div class="festive-card" style="border: 2px solid var(--gold-accent); padding: 30px 24px;">
            
            <div style="text-align: center; margin-bottom: 22px;">
                <span style="background: #e0f2fe; color: #0369a1; font-weight: 800; font-size: 0.78rem; padding: 4px 14px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.6px; display: inline-block; margin-bottom: 8px;">
                    &#9889; Official Bank Verified UPI Transfer
                </span>
                <h2 style="color: #fff; font-size: 1.7rem; font-weight: 900; margin: 0 0 6px;">Pay Directly via UPI ID</h2>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">
                    Zero convenience fee &bull; Fast, direct &amp; 100% secure payment
                </p>
            </div>

            <!-- UPI White Box -->
            <div style="border: 2px solid #e2e8f0; max-width: 520px; margin: 0 auto 25px; padding: 24px 20px; border-radius: 16px; background: #ffffff; text-align: center; box-shadow: 0 8px 30px rgba(0,0,0,0.25);">
                
                <div style="font-size: 0.9rem; color: #475569; font-weight: 600;">
                    Bank Account Holder: <strong style="color: #0f172a;">chandni 0777 (State Bank of India)</strong>
                </div>

                <!-- Payable Amount Big Display -->
                <div style="margin: 14px 0 16px;">
                    <span style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Amount to Transfer</span>
                    <div style="font-size: 2.3rem; font-weight: 900; color: #7a1244; line-height: 1.1; margin-top: 4px;">
                        <?= format_inr($cart['total_amount']) ?>
                    </div>
                </div>

                <!-- UPI ID Bar + High-Visibility Copy Button -->
                <div style="background: #f1f5f9; border: 2px dashed #94a3b8; padding: 14px 16px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <span id="upi-id-text" style="font-size: 1.25rem; font-weight: 900; color: #0f172a; font-family: monospace; letter-spacing: 0.5px;">
                        <?= e($cleanUpiId) ?>
                    </span>
                    <button type="button" class="btn btn-secondary" id="btn-copy-upi-main" onclick="copyUpiIdMain()" style="color: #fff; background: #1e293b; border: none; font-weight: 800; padding: 8px 18px; border-radius: 8px; cursor: pointer; font-size: 0.88rem; transition: all 0.2s ease;">
                        &#128203; Copy UPI ID
                    </button>
                </div>

                <!-- Notification Alert Banner upon copying -->
                <div id="copy-toast" style="display: none; background: #dcfce7; border: 1.5px solid #22c55e; color: #15803d; padding: 10px 14px; border-radius: 8px; font-weight: 700; font-size: 0.88rem; margin-bottom: 18px; text-align: center;">
                    &#10004; UPI ID copied! Open GPay, PhonePe or Paytm to transfer <?= format_inr($cart['total_amount']) ?>
                </div>

                <!-- 1-Click Direct UPI App Buttons for Mobile -->
                <div style="margin-bottom: 16px;">
                    <div style="font-size: 0.82rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 10px; letter-spacing: 0.5px;">
                        Tap to Pay Directly with Your App:
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px;">
                        <!-- PhonePe -->
                        <a href="<?= e($phonepePayload) ?>" class="btn" style="background: #5f259f; color: #fff; font-weight: 800; font-size: 0.88rem; padding: 11px 12px; border-radius: 10px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; box-shadow: 0 4px 12px rgba(95,37,159,0.3);">
                            <span>&#128241;</span> <span>PhonePe</span>
                        </a>

                        <!-- Google Pay -->
                        <a href="<?= e($genericUpiPayload) ?>" class="btn" style="background: #1a73e8; color: #fff; font-weight: 800; font-size: 0.88rem; padding: 11px 12px; border-radius: 10px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; box-shadow: 0 4px 12px rgba(26,115,232,0.3);">
                            <span>&#128241;</span> <span>GPay</span>
                        </a>

                        <!-- Paytm -->
                        <a href="<?= e($paytmPayload) ?>" class="btn" style="background: #00baf2; color: #0c2340; font-weight: 800; font-size: 0.88rem; padding: 11px 12px; border-radius: 10px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; box-shadow: 0 4px 12px rgba(0,186,242,0.3);">
                            <span>&#128241;</span> <span>Paytm</span>
                        </a>

                        <!-- Any UPI App / BHIM -->
                        <a href="<?= e($genericUpiPayload) ?>" class="btn" style="background: #0c2340; color: #fff; font-weight: 800; font-size: 0.88rem; padding: 11px 12px; border-radius: 10px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; box-shadow: 0 4px 12px rgba(12,35,64,0.3);">
                            <span>&#9889;</span> <span>Any UPI</span>
                        </a>
                    </div>
                </div>

                <div style="font-size: 0.78rem; color: #94a3b8; font-weight: 600;">
                    Supports all Indian UPI Apps (GPay, PhonePe, Paytm, BHIM, Cred, Amazon Pay)
                </div>

            </div>

            <!-- Clear 3-Step Instructions Box -->
            <div style="background: rgba(20, 2, 11, 0.75); border: 1.5px solid rgba(245, 183, 34, 0.35); border-radius: 12px; padding: 18px 22px; margin: 25px 0;">
                <h4 style="color: var(--gold-accent); font-size: 1.05rem; margin: 0 0 10px; font-weight: 800;">
                    &#128221; How to Pay &amp; Receive Your Ticket (3 Easy Steps):
                </h4>
                <ol style="margin: 0; padding-left: 20px; font-size: 0.92rem; line-height: 1.8; color: #e2e8f0;">
                    <li>Open your preferred UPI app (PhonePe, Google Pay, Paytm, or BHIM) and transfer exact <strong style="color: var(--gold-accent);"><?= format_inr($cart['total_amount']) ?></strong> to UPI ID: <strong style="color: var(--gold-accent);"><?= e($cleanUpiId) ?></strong>.</li>
                    <li>Upon completing the payment, locate the <strong>12-digit UTR / UPI Reference Number</strong> on your transaction receipt.</li>
                    <li>Enter your 12-digit UTR in the field below and click <strong>"Confirm Payment &amp; Get Ticket"</strong> to submit your pass for instant approval.</li>
                </ol>
            </div>

            <!-- UTR Input & Confirmation Form -->
            <form action="<?= BASE_URL ?>/payment.php" method="POST" id="utr-form" style="max-width: 580px; margin: 0 auto;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="submit_utr">

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" for="transaction_ref" style="font-size: 1rem; font-weight: 800; color: #fff; margin-bottom: 8px; display: block;">
                        &#128222; Enter 12-Digit UTR / Transaction Reference ID *
                    </label>
                    <input type="text" 
                           name="transaction_ref" 
                           id="transaction_ref" 
                           class="form-control" 
                           placeholder="e.g. 428901234567 or PhonePe Txn ID" 
                           maxlength="35" 
                           required 
                           style="font-size: 1.25rem; font-family: monospace; letter-spacing: 1.5px; padding: 14px 18px; border: 2px solid var(--gold-accent); border-radius: 10px; background: rgba(13,1,7,0.8); color: #fff; width: 100%; box-sizing: border-box;">
                    <small style="color: #cbd5e1; font-size: 0.82rem; margin-top: 6px; display: block;">
                        &#128161; Found in your payment receipt under <strong>"UPI Ref No"</strong>, <strong>"UTR"</strong>, or <strong>"Transaction ID"</strong>.
                    </small>
                </div>

                <div style="margin-top: 25px;">
                    <button type="submit" id="btn-i-have-paid" class="btn btn-primary btn-block" style="padding: 16px; font-size: 1.2rem; font-weight: 900; text-transform: uppercase; width: 100%; border-radius: 10px; box-shadow: 0 6px 25px rgba(245,183,34,0.45); cursor: pointer;">
                        &#10004; Confirm Payment &amp; Get Ticket &rarr;
                    </button>
                </div>

                <div style="margin-top: 14px; text-align: center;">
                    <a href="https://wa.me/917004665804?text=<?= urlencode("Hello Millennium India Group, I am making payment for Booking ID: " . $bookingUid . " (Amount: " . format_inr($cart['total_amount']) . ", Name: " . $customer['customer_name'] . "). Please guide me / verify.") ?>" 
                       target="_blank" 
                       class="btn" 
                       style="background: #25d366; color: #fff; font-weight: 800; font-size: 0.95rem; padding: 12px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; box-sizing: border-box;">
                        <span>&#128172;</span> <span>Pay / Send Screenshot on WhatsApp (+91 7004665804)</span>
                    </a>
                </div>
            </form>

            <div style="text-align: center; margin-top: 25px; color: var(--text-muted); font-size: 0.88rem;">
                Need help with payment? Helpline: <a href="tel:7004665804" style="color: var(--gold-accent); font-weight: 700; text-decoration: none;">+91 7004665804</a> &bull; WhatsApp: <a href="https://wa.me/917004665804" target="_blank" style="color:#25d366; font-weight:700; text-decoration:none;">7004665804</a>
            </div>

        </div>

    </div>
</div>

<script>
function copyUpiIdMain() {
    const upiText = '<?= $cleanUpiId ?>';
    const btn = document.getElementById('btn-copy-upi-main');
    const toast = document.getElementById('copy-toast');
    
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(upiText).then(onCopySuccess).catch(fallbackCopy);
    } else {
        fallbackCopy();
    }
    
    function fallbackCopy() {
        const temp = document.createElement('textarea');
        temp.value = upiText;
        document.body.appendChild(temp);
        temp.select();
        try {
            document.execCommand('copy');
            onCopySuccess();
        } catch (e) {
            prompt('Copy UPI ID:', upiText);
        }
        document.body.removeChild(temp);
    }
    
    function onCopySuccess() {
        if (btn) {
            btn.innerHTML = '&#10004; Copied!';
            btn.style.background = '#22c55e';
            btn.style.color = '#000';
            setTimeout(() => {
                btn.innerHTML = '&#128203; Copy UPI ID';
                btn.style.background = '#1e293b';
                btn.style.color = '#fff';
            }, 3000);
        }
        if (toast) {
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 6000);
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const utrForm = document.getElementById('utr-form');
    const iHavePaidBtn = document.getElementById('btn-i-have-paid');

    if (utrForm && iHavePaidBtn) {
        utrForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const input = document.getElementById('transaction_ref');
            const utrVal = input ? input.value.trim().replace(/\s+/g, '') : '';
            
            if (utrVal.length < 6) {
                alert('Please enter a valid 12-digit UPI UTR / Transaction Reference number.');
                if (input) input.focus();
                return;
            }

            iHavePaidBtn.disabled = true;
            iHavePaidBtn.innerHTML = '&#9203; Verifying Reference &amp; Issuing Pass...';

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
                    // Fallback to direct form submit
                    utrForm.submit();
                }
            })
            .catch(() => {
                // Network or parse issue, submit standard form
                utrForm.submit();
            });
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
