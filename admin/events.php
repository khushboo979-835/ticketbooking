<?php
// admin/events.php - Events Management (CRUD)
ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

require_admin_login();
send_no_cache_headers();

$adminCurrent = 'events';
$db = getDB();

// Handle Event Add / Edit / Toggle Publish / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session token expired.');
        header("Location: " . BASE_URL . "/admin/events.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_or_update') {
            $id = (int)($_POST['event_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $date = trim($_POST['date'] ?? '');
            $startTime = trim($_POST['start_time'] ?? '');
            $endTime = trim($_POST['end_time'] ?? '');
            $venue = trim($_POST['venue'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $isPublished = isset($_POST['is_published']) ? 1 : 0;

            if (empty($slug)) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            }

            // Banner Image Upload Handling
            $bannerPath = $_POST['existing_banner'] ?? 'assets/uploads/garba-banner.jpg';
            if (!empty($_FILES['banner_image']['name']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    if (!is_dir(UPLOAD_DIR)) {
                        mkdir(UPLOAD_DIR, 0755, true);
                    }
                    $newFilename = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $targetFile = UPLOAD_DIR . $newFilename;
                    if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $targetFile)) {
                        $bannerPath = 'assets/uploads/' . $newFilename;
                    }
                }
            }

            if ($id > 0) {
                $stmt = $db->prepare("
                    UPDATE events 
                    SET title = :title, slug = :slug, date = :date, start_time = :s_time, end_time = :e_time, 
                        venue = :venue, description = :desc, banner_image = :banner, is_published = :pub
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':title'  => $title,
                    ':slug'   => $slug,
                    ':date'   => $date,
                    ':s_time' => $startTime,
                    ':e_time' => $endTime,
                    ':venue'  => $venue,
                    ':desc'   => $description,
                    ':banner' => $bannerPath,
                    ':pub'    => $isPublished,
                    ':id'     => $id
                ]);
                set_flash('success', "Event '{$title}' updated successfully.");
            } else {
                $stmt = $db->prepare("
                    INSERT INTO events (title, slug, date, start_time, end_time, venue, description, banner_image, is_published)
                    VALUES (:title, :slug, :date, :s_time, :e_time, :venue, :desc, :banner, :pub)
                ");
                $stmt->execute([
                    ':title'  => $title,
                    ':slug'   => $slug,
                    ':date'   => $date,
                    ':s_time' => $startTime,
                    ':e_time' => $endTime,
                    ':venue'  => $venue,
                    ':desc'   => $description,
                    ':banner' => $bannerPath,
                    ':pub'    => $isPublished
                ]);
                set_flash('success', "Event '{$title}' created successfully.");
            }
        } elseif ($action === 'toggle_publish') {
            $id = (int)($_POST['event_id'] ?? 0);
            $stmt = $db->prepare("UPDATE events SET is_published = CASE WHEN is_published = 1 THEN 0 ELSE 1 END WHERE id = :id");
            $stmt->execute([':id' => $id]);
            set_flash('info', "Event publication status toggled.");
        }
    } catch (PDOException $e) {
        error_log('[admin/events.php action error]: ' . $e->getMessage());
        set_flash('error', "Database error: " . $e->getMessage());
    }

    header("Location: " . BASE_URL . "/admin/events.php");
    exit;
}

$events = [];
try {
    $events = $db->query("SELECT e.*, COUNT(tc.id) AS category_count FROM events e LEFT JOIN ticket_categories tc ON e.id = tc.event_id GROUP BY e.id ORDER BY e.date ASC")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/events.php list error]: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events | Millennium Admin</title>
    <link rel="stylesheet" href="<?= asset_v('assets/css/admin.css') ?>">
</head>
<body>
<div class="admin-wrapper">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-header">
            <div>
                <h1 class="admin-title">Manage Events</h1>
                <div class="admin-subtitle">Create and update events, venue details, and schedules</div>
            </div>
            <button type="button" class="btn-verify" style="background:var(--admin-primary); color:#12141d; padding:10px 18px;" onclick="openEventModal()">
                &plus; Add New Event
            </button>
        </div>

        <?= render_flash() ?>

        <div class="admin-card">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Event Title</th>
                            <th>Date &amp; Time</th>
                            <th>Venue</th>
                            <th>Categories</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $ev): ?>
                            <tr>
                                <td><?= (int)$ev['id'] ?></td>
                                <td>
                                    <strong><?= e($ev['title']) ?></strong>
                                    <div style="font-size:0.75rem; color:var(--admin-text-muted);">Slug: <?= e($ev['slug']) ?></div>
                                </td>
                                <td>
                                    <div><?= date('d M Y', strtotime($ev['date'])) ?></div>
                                    <div style="font-size:0.75rem; color:var(--admin-text-muted);"><?= date('h:i A', strtotime($ev['start_time'])) ?> &ndash; <?= date('h:i A', strtotime($ev['end_time'])) ?></div>
                                </td>
                                <td><?= e($ev['venue']) ?></td>
                                <td>
                                    <a href="<?= BASE_URL ?>/admin/tickets.php?event_id=<?= $ev['id'] ?>" style="color:var(--admin-primary); font-weight:700;">
                                        <?= (int)$ev['category_count'] ?> Passes &rarr;
                                    </a>
                                </td>
                                <td>
                                    <?php if ($ev['is_published']): ?>
                                        <span class="badge badge-verified">Published</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">Draft / Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex; gap:6px;">
                                        <form action="<?= BASE_URL ?>/admin/events.php" method="POST" style="margin:0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_publish">
                                            <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                                            <button type="submit" class="btn-sm" style="background:#202434; border:1px solid #2e344a; color:#fff; cursor:pointer;">
                                                <?= $ev['is_published'] ? 'Unpublish' : 'Publish' ?>
                                            </button>
                                        </form>
                                        <button type="button" class="btn-sm" style="background:var(--admin-primary); color:#12141d; border:none; cursor:pointer;" onclick='editEvent(<?= json_encode($ev) ?>)'>
                                            Edit
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Event Add/Edit Modal -->
<div class="modal-overlay" id="event-modal">
    <div class="modal-content" style="max-width: 650px;">
        <h3 id="event-modal-title" style="color:var(--admin-primary); margin-bottom: 20px;">Add New Event</h3>
        <form action="<?= BASE_URL ?>/admin/events.php" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_or_update">
            <input type="hidden" name="event_id" id="modal-event-id" value="0">
            <input type="hidden" name="existing_banner" id="modal-event-banner" value="">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                <div>
                    <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:4px;">Event Title *</label>
                    <input type="text" name="title" id="modal-title" required class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:4px;">Slug (URL friendly)</label>
                    <input type="text" name="slug" id="modal-slug" class="form-control" placeholder="e.g. jashn-e-garba-2026" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px; margin-bottom:15px;">
                <div>
                    <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:4px;">Event Date *</label>
                    <input type="date" name="date" id="modal-date" required class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:4px;">Start Time *</label>
                    <input type="time" name="start_time" id="modal-start-time" required class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:4px;">End Time *</label>
                    <input type="time" name="end_time" id="modal-end-time" required class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
                </div>
            </div>

            <div style="margin-bottom:15px;">
                <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:4px;">Venue Name &amp; Address *</label>
                <input type="text" name="venue" id="modal-venue" required class="form-control" placeholder="e.g. Unique Garden, Patna" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;">
            </div>

            <div style="margin-bottom:15px;">
                <label style="display:block; font-size:0.85rem; color:#ccc; margin-bottom:4px;">Event Description</label>
                <textarea name="description" id="modal-desc" rows="3" class="form-control" style="background:#12141d; border:1px solid #2e344a; color:#fff; width:100%; padding:10px; border-radius:6px;"></textarea>
            </div>

            <div style="margin-bottom:20px; display:flex; align-items:center; gap:8px;">
                <input type="checkbox" name="is_published" id="modal-is-published" value="1" checked style="width:18px; height:18px;">
                <label for="modal-is-published" style="color:#fff; font-size:0.9rem;">Publish immediately on public website</label>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn-sm" style="background:#202434; border:1px solid #2e344a; color:#fff; cursor:pointer;" onclick="closeEventModal()">Cancel</button>
                <button type="submit" class="btn-verify" style="background:var(--admin-primary); color:#12141d; padding:10px 20px;">Save Event</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEventModal() {
    document.getElementById('modal-event-id').value = '0';
    document.getElementById('modal-title').value = '';
    document.getElementById('modal-slug').value = '';
    document.getElementById('modal-date').value = '2026-10-16';
    document.getElementById('modal-start-time').value = '17:00';
    document.getElementById('modal-end-time').value = '21:00';
    document.getElementById('modal-venue').value = 'Unique Garden, Patna';
    document.getElementById('modal-desc').value = '';
    document.getElementById('modal-is-published').checked = true;
    document.getElementById('event-modal-title').innerText = 'Add New Event';
    document.getElementById('event-modal').classList.add('active');
}

function editEvent(ev) {
    document.getElementById('modal-event-id').value = ev.id;
    document.getElementById('modal-title').value = ev.title;
    document.getElementById('modal-slug').value = ev.slug;
    document.getElementById('modal-date').value = ev.date;
    document.getElementById('modal-start-time').value = ev.start_time;
    document.getElementById('modal-end-time').value = ev.end_time;
    document.getElementById('modal-venue').value = ev.venue;
    document.getElementById('modal-desc').value = ev.description;
    document.getElementById('modal-is-published').checked = ev.is_published == 1;
    document.getElementById('modal-event-banner').value = ev.banner_image || '';
    document.getElementById('event-modal-title').innerText = 'Edit Event: ' + ev.title;
    document.getElementById('event-modal').classList.add('active');
}

function closeEventModal() {
    document.getElementById('event-modal').classList.remove('active');
}
</script>
</body>
</html>
