<?php
// events.php - Upcoming Events Directory
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

$pageTitle = 'Upcoming Events | Millennium India Group';
$currentNav = 'events';

$db = getDB();
$stmt = $db->query("SELECT e.*, MIN(tc.price) AS min_price FROM events e LEFT JOIN ticket_categories tc ON e.id = tc.event_id AND tc.status = 'active' WHERE e.is_published = 1 GROUP BY e.id ORDER BY e.date ASC");
$events = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 40px; padding-bottom: 70px;">
    <div style="text-align: center; margin-bottom: 40px;">
        <span style="color: var(--gold-accent); font-weight: 700; text-transform: uppercase;">Millennium India Group</span>
        <h1 style="font-size: 2.5rem; font-weight: 800; color: #fff; margin-top: 6px;">Upcoming Events &amp; Festivals</h1>
        <p style="color: var(--text-muted); max-width: 600px; margin: 0 auto;">Explore upcoming cultural celebrations, concerts, and Dandiya events in Patna.</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 30px;">
        <?php foreach ($events as $ev): ?>
            <div class="festive-card" style="display: flex; flex-direction: column; justify-content: space-between; height: 100%;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <span style="background: rgba(245,183,34,0.15); border: 1px solid var(--gold-accent); color: var(--gold-light); font-size: 0.8rem; font-weight: 700; padding: 4px 10px; border-radius: 4px;">
                            &#128197; <?= date('d M Y', strtotime($ev['date'])) ?>
                        </span>
                        <span style="color: var(--gold-accent); font-weight: 700;">
                            <?= $ev['min_price'] ? 'From ' . format_inr($ev['min_price']) : 'Free Entry' ?>
                        </span>
                    </div>

                    <h2 style="font-size: 1.6rem; font-weight: 800; color: #fff; margin-bottom: 12px; line-height: 1.25;">
                        <a href="<?= BASE_URL ?>/event-detail.php?slug=<?= e($ev['slug']) ?>" style="color:#fff;">
                            <?= e($ev['title']) ?>
                        </a>
                    </h2>

                    <div style="color: var(--text-muted); font-size: 0.92rem; margin-bottom: 12px;">
                        <div>&#128205; <strong>Venue:</strong> <?= e($ev['venue']) ?></div>
                        <div>&#9200; <strong>Time:</strong> <?= date('h:i A', strtotime($ev['start_time'])) ?> &ndash; <?= date('h:i A', strtotime($ev['end_time'])) ?></div>
                    </div>

                    <p style="font-size: 0.9rem; color: #ccc; line-height: 1.5; margin-bottom: 20px;">
                        <?= e(substr($ev['description'] ?? '', 0, 160)) ?>...
                    </p>
                </div>

                <div style="display: flex; gap: 10px;">
                    <a href="<?= BASE_URL ?>/booking.php?event_id=<?= $ev['id'] ?>" class="btn btn-primary" style="flex: 1;">
                        &#127915; Book Passes
                    </a>
                    <a href="<?= BASE_URL ?>/event-detail.php?slug=<?= e($ev['slug']) ?>" class="btn btn-secondary">
                        Details &rarr;
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
