<?php
/**
 * admin/bookings.php - Real-Time Ledger & 1-Click UPI Verification Console
 * Matches Infographic Box 8 Specification
 * Millennium India Group - Jashn-E-Garba 2026
 */
ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

require_admin_login();
send_no_cache_headers();

$adminCurrent = 'bookings';
$db = getDB();

// 1-Click Payment Verification & Status Transitions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security token expired. Please refresh.');
        header("Location: " . BASE_URL . "/admin/bookings.php");
        exit;
    }

    $action = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    if ($action === 'verify_payment' && $bookingId > 0) {
        try {
            $db->beginTransaction();

            $bStmt = $db->prepare("SELECT * FROM bookings WHERE id = :id FOR UPDATE");
            $bStmt->execute([':id' => $bookingId]);
            $booking = $bStmt->fetch();

            if ($booking && $booking['payment_status'] !== 'verified') {
                // Deduct ticket category quantities atomically
                $itStmt = $db->prepare("SELECT ticket_category_id, quantity FROM booking_items WHERE booking_id = :b_id");
                $itStmt->execute([':b_id' => $bookingId]);
                $items = $itStmt->fetchAll() ?: [];

                foreach ($items as $item) {
                    $deductStmt = $db->prepare("UPDATE ticket_categories SET available_quantity = GREATEST(0, available_quantity - :q) WHERE id = :c_id");
                    $deductStmt->execute([':q' => (int)$item['quantity'], ':c_id' => (int)$item['ticket_category_id']]);
                }

                $upStmt = $db->prepare("UPDATE bookings SET payment_status = 'verified' WHERE id = :id");
                $upStmt->execute([':id' => $bookingId]);

                $db->commit();
                set_flash('success', "Booking #{$booking['booking_uid']} verified! Digital pass activated for attendee {$booking['customer_name']}.");
            } else {
                $db->rollBack();
                set_flash('info', "Booking was already verified or not found.");
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[admin/bookings.php verification error]: ' . $e->getMessage());
            set_flash('error', "Database error approving booking: " . $e->getMessage());
        }
    } elseif ($action === 'mark_failed' && $bookingId > 0) {
        try {
            $stmt = $db->prepare("UPDATE bookings SET payment_status = 'failed' WHERE id = :id");
            $stmt->execute([':id' => $bookingId]);
            set_flash('warning', "Booking #$bookingId marked as Failed/Unpaid.");
        } catch (PDOException $e) {
            error_log('[admin/bookings.php mark_failed error]: ' . $e->getMessage());
        }
    } elseif ($action === 'toggle_checkin' && $bookingId > 0) {
        try {
            $stmt = $db->prepare("
                UPDATE bookings 
                SET is_checked_in = CASE WHEN is_checked_in = 1 THEN 0 ELSE 1 END,
                    checked_in_at = CASE WHEN is_checked_in = 1 THEN NULL ELSE NOW() END 
                WHERE id = :id
            ");
            $stmt->execute([':id' => $bookingId]);
            set_flash('info', "Check-in status updated for booking #$bookingId.");
        } catch (PDOException $e) {
            error_log('[admin/bookings.php toggle_checkin error]: ' . $e->getMessage());
        }
    }

    header("Location: " . BASE_URL . "/admin/bookings.php");
    exit;
}

// Filters & Search
$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$bookings = [];
try {
    $sql = "
        SELECT b.*, e.title AS event_title,
               GROUP_CONCAT(CONCAT(tc.name, ' (x', bi.quantity, ')') SEPARATOR ', ') AS ticket_summary,
               SUM(bi.quantity * tc.people_allowed) AS total_persons
        FROM bookings b
        LEFT JOIN events e ON b.event_id = e.id
        LEFT JOIN booking_items bi ON b.id = bi.booking_id
        LEFT JOIN ticket_categories tc ON bi.ticket_category_id = tc.id
        WHERE 1=1
    ";

    $params = [];

    if ($statusFilter === 'pending') {
        $sql .= " AND b.payment_status = 'pending'";
    } elseif ($statusFilter === 'verified') {
        $sql .= " AND b.payment_status = 'verified'";
    } elseif ($statusFilter === 'checked_in') {
        $sql .= " AND b.is_checked_in = 1";
    }

    if (!empty($search)) {
        $sql .= " AND (b.booking_uid LIKE :s1 OR b.customer_name LIKE :s2 OR b.customer_phone LIKE :s3 OR b.transaction_ref LIKE :s4)";
        $params[':s1'] = "%$search%";
        $params[':s2'] = "%$search%";
        $params[':s3'] = "%$search%";
        $params[':s4'] = "%$search%";
    }

    $sql .= " GROUP BY b.id ORDER BY b.id DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll() ?: [];
} catch (PDOException $e) {
    error_log('[admin/bookings.php list error]: ' . $e->getMessage());
    $bookings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings Ledger &amp; Verification | Millennium Admin</title>
    <link rel="stylesheet" href="<?= asset_v('assets/css/admin.css') ?>">
</head>
<body>
<div class="admin-wrapper">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-header">
            <div>
                <h1 class="admin-title">Bookings &amp; Verification Ledger</h1>
                <div class="admin-subtitle">Live transactions, UTR receipt checks, and entry authorizations</div>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="<?= BASE_URL ?>/api/export-csv.php?status=<?= urlencode($statusFilter) ?>" class="btn-verify" style="background:#202434; border:1px solid #2e344a; color:#fff; text-decoration:none;">
                    <span>&#128196;</span> <span>Export CSV</span>
                </a>
            </div>
        </div>

        <?= render_flash() ?>

        <!-- Search & Status Filter Bar -->
        <div class="admin-card" style="padding: 18px 24px;">
            <form action="<?= BASE_URL ?>/admin/bookings.php" method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                <div style="flex: 2; min-width: 250px;">
                    <input type="text" name="search" class="form-control" placeholder="Search Booking UID, Attendee Name, Mobile, or UTR..." value="<?= e($search) ?>" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px 14px; border-radius:8px;">
                </div>

                <div>
                    <select name="status" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; padding:10px 14px; border-radius:8px;" onchange="this.form.submit()">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Verification Only</option>
                        <option value="verified" <?= $statusFilter === 'verified' ? 'selected' : '' ?>>Verified &amp; Paid Only</option>
                        <option value="checked_in" <?= $statusFilter === 'checked_in' ? 'selected' : '' ?>>Gate Checked-In Only</option>
                    </select>
                </div>

                <button type="submit" class="btn-verify" style="background:var(--admin-primary); color:#12141d;">
                    &#128269; Filter
                </button>

                <?php if (!empty($search) || $statusFilter !== 'all'): ?>
                    <a href="<?= BASE_URL ?>/admin/bookings.php" style="color: #9aa1b8; font-size: 0.9rem; text-decoration: none;">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Ledger Table (Matches Box 8 in Infographic) -->
        <div class="admin-card">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Booking ID</th>
                            <th>Attendee Name</th>
                            <th>Ticket Type &amp; Qty</th>
                            <th>Amount</th>
                            <th>Payment Status</th>
                            <th>UTR / Ref ID</th>
                            <th>Gate Entry</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: var(--admin-text-muted); padding: 35px;">
                                    No booking records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($bookings as $b): ?>
                                <tr>
                                    <td><?= $counter++ ?></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/ticket.php?uid=<?= urlencode($b['booking_uid']) ?>" target="_blank" style="font-family: monospace; font-weight: 800; color: var(--admin-primary);">
                                            <?= e($b['booking_uid']) ?>
                                        </a>
                                        <div style="font-size: 0.72rem; color: var(--admin-text-muted);">
                                            <?= date('d M Y, h:i A', strtotime($b['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= e($b['customer_name']) ?></strong>
                                        <div style="font-size: 0.8rem; color: #cbd5e1;"><?= e($b['customer_phone']) ?></div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.85rem;"><?= e($b['ticket_summary'] ?: 'Pass') ?></div>
                                        <div style="font-size: 0.75rem; color: var(--admin-primary); font-weight: 700;">
                                            Valid for: <?= (int)$b['total_persons'] ?> Persons
                                        </div>
                                    </td>
                                    <td style="font-weight: 800; color: #fff; font-size: 1.05rem;">
                                        <?= format_inr($b['total_amount']) ?>
                                    </td>
                                    <td>
                                        <?php if ($b['payment_status'] === 'verified'): ?>
                                            <span class="badge badge-verified">Verified</span>
                                        <?php elseif ($b['payment_status'] === 'pending'): ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php else: ?>
                                            <span class="badge badge-failed"><?= e($b['payment_status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($b['transaction_ref'])): ?>
                                            <div style="font-family: monospace; font-size: 0.9rem; color: #ffd966; background: rgba(245,183,34,0.12); padding: 3px 8px; border-radius: 4px; display: inline-block;">
                                                <?= e($b['transaction_ref']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #64748b; font-size: 0.8rem;">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($b['is_checked_in']): ?>
                                            <span class="badge badge-checked" title="<?= e($b['checked_in_at']) ?>">&#10004; Entered</span>
                                        <?php else: ?>
                                            <span style="color: #64748b; font-size: 0.8rem;">Not Checked In</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                            <?php if ($b['payment_status'] === 'pending'): ?>
                                                <!-- 1-Click Verify Button -->
                                                <form action="<?= BASE_URL ?>/admin/bookings.php" method="POST" style="margin:0;" onsubmit="return confirm('Verify UTR receipt <?= e($b['transaction_ref']) ?> for <?= e($b['customer_name']) ?>?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="verify_payment">
                                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                    <button type="submit" class="btn-verify" title="Confirm Payment & Unlock Ticket">
                                                        &#10004; Verify
                                                    </button>
                                                </form>

                                                <form action="<?= BASE_URL ?>/admin/bookings.php" method="POST" style="margin:0;" onsubmit="return confirm('Mark as Unpaid/Failed?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="mark_failed">
                                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                    <button type="submit" class="btn-sm" style="background:#ef4444; color:#fff; border:none; cursor:pointer;" title="Reject">
                                                        &times;
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- View / Print Pass -->
                                            <a href="<?= BASE_URL ?>/ticket.php?uid=<?= urlencode($b['booking_uid']) ?>" target="_blank" class="btn-sm" style="background:#202434; border:1px solid #2e344a; color:#fff; text-decoration:none;" title="View Digital Pass">
                                                View
                                            </a>

                                            <!-- Manual Check-In Toggle -->
                                            <form action="<?= BASE_URL ?>/admin/bookings.php" method="POST" style="margin:0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_checkin">
                                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                <button type="submit" class="btn-sm" style="background:#1b1e2b; border:1px solid #2e344a; color:#9aa1b8; cursor:pointer;" title="Toggle Entry">
                                                    <?= $b['is_checked_in'] ? 'Undo' : 'Check-In' ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>
