<?php
// admin/tickets.php - Ticket Categories & Inventory Quota Management
ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

require_admin_login();
send_no_cache_headers();

$adminCurrent = 'tickets';
$db = getDB();

$eventId = (int)($_GET['event_id'] ?? 1);

// Handle Category Creation & Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session token expired.');
        header("Location: " . BASE_URL . "/admin/tickets.php?event_id=" . $eventId);
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_category') {
            $catId = (int)($_POST['category_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $peopleAllowed = (int)($_POST['people_allowed'] ?? 1);
            $totalQuantity = (int)($_POST['total_quantity'] ?? 100);
            $availableQuantity = (int)($_POST['available_quantity'] ?? $totalQuantity);
            $status = $_POST['status'] ?? 'active';

            if ($catId > 0) {
                $stmt = $db->prepare("
                    UPDATE ticket_categories 
                    SET name = :name, price = :price, people_allowed = :ppl, 
                        total_quantity = :total, available_quantity = :avail, status = :st 
                    WHERE id = :id AND event_id = :e_id
                ");
                $stmt->execute([
                    ':name'  => $name,
                    ':price' => $price,
                    ':ppl'   => $peopleAllowed,
                    ':total' => $totalQuantity,
                    ':avail' => $availableQuantity,
                    ':st'    => $status,
                    ':id'    => $catId,
                    ':e_id'  => $eventId
                ]);

                // Sync corresponding seats in event_seats matrix
                try {
                    $syncSeat = $db->prepare("UPDATE event_seats SET category_name = :name, tier_price = :price WHERE category_id = :cid");
                    $syncSeat->execute([':name' => $name, ':price' => $price, ':cid' => $catId]);
                } catch (Throwable $ex) {}

                set_flash('success', "Ticket pass '{$name}' updated successfully! Public website and seat map updated.");
            } else {
                $stmt = $db->prepare("
                    INSERT INTO ticket_categories (event_id, name, price, people_allowed, total_quantity, available_quantity, status)
                    VALUES (:e_id, :name, :price, :ppl, :total, :avail, :st)
                ");
                $stmt->execute([
                    ':e_id'  => $eventId,
                    ':name'  => $name,
                    ':price' => $price,
                    ':ppl'   => $peopleAllowed,
                    ':total' => $totalQuantity,
                    ':avail' => $availableQuantity,
                    ':st'    => $status
                ]);
                set_flash('success', "New ticket pass '{$name}' created.");
            }
        }
    } catch (PDOException $e) {
        error_log('[admin/tickets.php error]: ' . $e->getMessage());
        set_flash('error', "Database error saving category: " . $e->getMessage());
    }

    header("Location: " . BASE_URL . "/admin/tickets.php?event_id=" . $eventId);
    exit;
}

$events = [];
$categories = [];
try {
    $events = $db->query("SELECT id, title FROM events ORDER BY date ASC")->fetchAll() ?: [];
    $stmt = $db->prepare("SELECT * FROM ticket_categories WHERE event_id = :e_id ORDER BY price ASC");
    $stmt->execute([':e_id' => $eventId]);
    $categories = $stmt->fetchAll() ?: [];
} catch (PDOException $e) {
    error_log('[admin/tickets.php list error]: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Categories &amp; Quota | Millennium Admin</title>
    <link rel="stylesheet" href="<?= asset_v('assets/css/admin.css') ?>">
</head>
<body>
<div class="admin-wrapper">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-header">
            <div>
                <h1 class="admin-title">&#127915; Ticket Tiers &amp; Quota Management</h1>
                <div class="admin-subtitle">Set pricing, person allowance, and ticket inventories</div>
            </div>
            <button type="button" class="btn-verify" style="background:var(--admin-primary); color:#12141d; padding:10px 18px;" onclick="openCategoryModal()">
                &plus; Add Ticket Category
            </button>
        </div>

        <?= render_flash() ?>

        <!-- Event Filter Selector -->
        <div class="admin-card" style="padding: 16px 20px;">
            <form action="<?= BASE_URL ?>/admin/tickets.php" method="GET" style="display:flex; align-items:center; gap:12px;">
                <label style="color:#cbd5e1; font-weight:600; font-size:0.9rem;">Select Event:</label>
                <select name="event_id" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; padding:8px 14px; border-radius:6px; min-width:240px;" onchange="this.form.submit()">
                    <?php foreach ($events as $ev): ?>
                        <option value="<?= $ev['id'] ?>" <?= $ev['id'] == $eventId ? 'selected' : '' ?>>
                            <?= e($ev['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- Ticket Categories Table -->
        <div class="admin-card">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Price (INR)</th>
                            <th>Entry Allowance</th>
                            <th>Food Inclusion</th>
                            <th>Total Quota</th>
                            <th>Booked (Sold)</th>
                            <th>Khali (Available)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: var(--admin-text-muted); padding: 30px;">
                                    No ticket tiers created for this event yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                                <?php 
                                $hasFood = stripos($cat['name'], 'With Food') !== false;
                                $soldQty = max(0, (int)$cat['total_quantity'] - (int)$cat['available_quantity']);
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= e($cat['name']) ?></strong>
                                    </td>
                                    <td style="font-weight: 800; color: #ffd966; font-size: 1.1rem;">
                                        <?= format_inr($cat['price']) ?>
                                    </td>
                                    <td>
                                        <span style="background: rgba(245,183,34,0.15); color: #ffd966; padding: 3px 8px; border-radius: 4px; font-weight: 700;">
                                            <?= (int)$cat['people_allowed'] ?> Person<?= $cat['people_allowed'] > 1 ? 's' : '' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($hasFood): ?>
                                            <span class="badge-food-yes">&#127869; Food Included</span>
                                        <?php else: ?>
                                            <span class="badge-food-no">&#10060; No Food</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format($cat['total_quantity']) ?></td>
                                    <td>
                                        <strong style="color: #ffd966;"><?= number_format($soldQty) ?></strong>
                                    </td>
                                    <td>
                                        <span style="font-weight: 800; color: <?= $cat['available_quantity'] < 20 ? '#f87171' : '#34d399' ?>;">
                                            <?= number_format($cat['available_quantity']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($cat['status'] === 'active'): ?>
                                            <span class="badge badge-verified">Active</span>
                                        <?php elseif ($cat['status'] === 'sold_out'): ?>
                                            <span class="badge badge-failed">Sold Out</span>
                                        <?php else: ?>
                                            <span class="badge badge-pending">Hidden</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn-sm" style="background:var(--admin-primary); color:#12141d; border:none; cursor:pointer;" onclick='editCategory(<?= json_encode($cat) ?>)'>
                                            Edit &rarr;
                                        </button>
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

<!-- Category Modal -->
<div class="modal-overlay" id="category-modal">
    <div class="modal-content" style="max-width: 540px;">
        <h3 id="category-modal-title" style="color:var(--admin-primary); margin-bottom: 20px;">Add Ticket Category</h3>
        <form action="<?= BASE_URL ?>/admin/tickets.php?event_id=<?= $eventId ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_category">
            <input type="hidden" name="category_id" id="modal-cat-id" value="0">

            <div style="margin-bottom:15px;">
                <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:4px;">Category Name *</label>
                <input type="text" name="name" id="modal-cat-name" required class="form-control" placeholder="e.g. Early Bird Couple" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                <div>
                    <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:4px;">Price (&#8377;) *</label>
                    <input type="number" step="0.01" name="price" id="modal-cat-price" required class="form-control" placeholder="1800.00" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:4px;">People Allowed per Pass *</label>
                    <input type="number" name="people_allowed" id="modal-cat-people" min="1" max="20" required class="form-control" value="1" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                <div>
                    <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:4px;">Total Quota *</label>
                    <input type="number" name="total_quantity" id="modal-cat-total" required class="form-control" value="300" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:4px;">Available Remaining *</label>
                    <input type="number" name="available_quantity" id="modal-cat-avail" required class="form-control" value="300" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:4px;">Status *</label>
                <select name="status" id="modal-cat-status" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                    <option value="active">Active (Available for booking)</option>
                    <option value="sold_out">Sold Out (Displayed as Sold Out)</option>
                    <option value="hidden">Hidden (Not shown on public website)</option>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn-sm" style="background:#202434; border:1px solid #2e344a; color:#fff; cursor:pointer;" onclick="closeCategoryModal()">Cancel</button>
                <button type="submit" class="btn-verify" style="background:var(--admin-primary); color:#12141d; padding:10px 20px;">Save Category</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCategoryModal() {
    document.getElementById('modal-cat-id').value = '0';
    document.getElementById('modal-cat-name').value = '';
    document.getElementById('modal-cat-price').value = '999.00';
    document.getElementById('modal-cat-people').value = '1';
    document.getElementById('modal-cat-total').value = '500';
    document.getElementById('modal-cat-avail').value = '500';
    document.getElementById('modal-cat-status').value = 'active';
    document.getElementById('category-modal-title').innerText = 'Add Ticket Category';
    document.getElementById('category-modal').classList.add('active');
}

function editCategory(c) {
    document.getElementById('modal-cat-id').value = c.id;
    document.getElementById('modal-cat-name').value = c.name;
    document.getElementById('modal-cat-price').value = c.price;
    document.getElementById('modal-cat-people').value = c.people_allowed;
    document.getElementById('modal-cat-total').value = c.total_quantity;
    document.getElementById('modal-cat-avail').value = c.available_quantity;
    document.getElementById('modal-cat-status').value = c.status;
    document.getElementById('category-modal-title').innerText = 'Edit Category: ' + c.name;
    document.getElementById('category-modal').classList.add('active');
}

function closeCategoryModal() {
    document.getElementById('category-modal').classList.remove('active');
}
</script>
</body>
</html>
