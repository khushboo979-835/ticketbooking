<?php
/**
 * admin/index.php - Organizer Dashboard & Real-Time Performance Analytics
 * Matches Infographic Box 8 Specification
 * Millennium India Group - Jashn-E-Garba 2026
 */
ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

require_admin_login();
send_no_cache_headers();

$adminCurrent = 'dashboard';
$db = getDB();

// Quick action: Single-click Verify UPI Payment from Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_verify') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        try {
            $db->beginTransaction();

            $bStmt = $db->prepare("SELECT * FROM bookings WHERE id = :id FOR UPDATE");
            $bStmt->execute([':id' => $bookingId]);
            $bRow = $bStmt->fetch();

            if ($bRow && $bRow['payment_status'] !== 'verified') {
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
                set_flash('success', "Booking #{$bRow['booking_uid']} verified! Digital pass activated.");
            } else {
                $db->rollBack();
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('[admin/index.php quick_verify error]: ' . $e->getMessage());
            set_flash('error', "Database error verifying booking: " . $e->getMessage());
        }
    }
    header("Location: " . BASE_URL . "/admin/index.php");
    exit;
}

// Metrics initialization with PDOException guards
$totalBookings = 0;
$totalRevenue = 0.0;
$pendingPayments = 0;
$checkedInCount = 0;
$recentBookings = [];

try {
    $totalBookings = (int)$db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $totalRevenue = (float)$db->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE payment_status = 'verified'")->fetchColumn();
    $pendingPayments = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE payment_status = 'pending'")->fetchColumn();
    $checkedInCount = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE is_checked_in = 1")->fetchColumn();

    $recentStmt = $db->query("
        SELECT b.*, e.title AS event_title,
               GROUP_CONCAT(CONCAT(tc.name, ' (x', bi.quantity, ')') SEPARATOR ', ') AS ticket_summary
        FROM bookings b
        LEFT JOIN events e ON b.event_id = e.id
        LEFT JOIN booking_items bi ON b.id = bi.booking_id
        LEFT JOIN ticket_categories tc ON bi.ticket_category_id = tc.id
        GROUP BY b.id
        ORDER BY b.id DESC 
        LIMIT 10
    ");
    $recentBookings = $recentStmt->fetchAll() ?: [];
} catch (PDOException $e) {
    error_log('[admin/index.php metrics error]: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Millennium India Group</title>
    <link rel="stylesheet" href="<?= asset_v('assets/css/admin.css') ?>">
</head>
<body>
<div class="admin-wrapper">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="admin-main">
        <div class="admin-header">
            <div>
                <h1 class="admin-title">Dashboard Overview</h1>
                <div class="admin-subtitle">Live real-time analytics for <strong>Jashn-E-Garba 2026</strong></div>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="<?= BASE_URL ?>/admin/scanner.php" class="btn-verify" style="background: linear-gradient(135deg, #f5b722, #d89614); color: #12141d; font-size: 0.95rem; padding: 10px 18px;">
                    <span>&#128247;</span> <span>Open Gate Scanner</span>
                </a>
                <a href="<?= BASE_URL ?>/api/export-csv.php" class="btn-sm" style="background:#202434; border:1px solid #2e344a; color:#fff; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                    <span>&#128196;</span> <span>Export CSV</span>
                </a>
            </div>
        </div>

        <?= render_flash() ?>

        <!-- 4 Metric Cards (Matches Infographic Box 8) -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-icon-box metric-icon-gold">&#127915;</div>
                <div>
                    <div class="metric-val"><?= number_format($totalBookings) ?></div>
                    <div class="metric-lbl">Total Bookings</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon-box metric-icon-green">&#8377;</div>
                <div>
                    <div class="metric-val"><?= format_inr($totalRevenue) ?></div>
                    <div class="metric-lbl">Total Sales</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon-box metric-icon-amber">&#9203;</div>
                <div>
                    <div class="metric-val"><?= number_format($pendingPayments) ?></div>
                    <div class="metric-lbl">Pending Payments</div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-icon-box metric-icon-blue">&#9989;</div>
                <div>
                    <div class="metric-val"><?= number_format($checkedInCount) ?></div>
                    <div class="metric-lbl">Checked In</div>
                </div>
            </div>
        </div>

        <!-- Recent Bookings Table (Matches Box 8) -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">&#128179; Recent Bookings</h3>
                <a href="<?= BASE_URL ?>/admin/bookings.php" style="color: var(--admin-primary); font-size: 0.88rem; font-weight: 600; text-decoration: none;">
                    View All Bookings &rarr;
                </a>
            </div>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Ticket Type</th>
                            <th>Amount</th>
                            <th>Payment Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentBookings)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--admin-text-muted); padding: 30px;">
                                    No bookings recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($recentBookings as $b): ?>
                                <tr>
                                    <td><?= $counter++ ?></td>
                                    <td>
                                        <strong><?= e($b['customer_name']) ?></strong>
                                        <div style="font-size: 0.75rem; color: var(--admin-text-muted);"><?= e($b['booking_uid']) ?></div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.85rem;"><?= e($b['ticket_summary'] ?: 'Entry') ?></div>
                                    </td>
                                    <td style="font-weight: 700; color: #fff;">
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
                                        <div style="display: flex; gap: 6px; align-items: center;">
                                            <?php if ($b['payment_status'] === 'pending'): ?>
                                                <form action="<?= BASE_URL ?>/admin/index.php" method="POST" style="margin:0;" onsubmit="return confirm('Verify UPI payment for <?= e($b['customer_name']) ?>?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="quick_verify">
                                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                    <button type="submit" class="btn-verify" title="Verify UTR & Issue Pass">
                                                        Verify
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <a href="<?= BASE_URL ?>/ticket.php?uid=<?= urlencode($b['booking_uid']) ?>" target="_blank" class="btn-sm" style="background:#202434; border:1px solid #2e344a; color:#fff; text-decoration:none;" title="View E-Ticket">
                                                    View
                                                </a>
                                            <?php endif; ?>
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
