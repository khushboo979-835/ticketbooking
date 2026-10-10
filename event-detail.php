<?php
// event-detail.php - Full Event Details & Ticket Tier Overview
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

$slug = $_GET['slug'] ?? 'jashn-e-garba-2026';
$event = get_event($slug);

if (!$event) {
    header("Location: " . BASE_URL . "/events.php");
    exit;
}

$pageTitle = e($event['title']) . ' | Millennium India Group';
$currentNav = ($slug === 'jashn-e-garba-2026') ? 'garba' : 'events';
$categories = get_event_categories($event['id']);

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 40px; padding-bottom: 70px;">
    <!-- Breadcrumb -->
    <div style="font-size: 0.9rem; margin-bottom: 20px; color: var(--text-muted);">
        <a href="<?= BASE_URL ?>/index.php">Home</a> &raquo; 
        <a href="<?= BASE_URL ?>/events.php">Events</a> &raquo; 
        <span style="color: var(--gold-light);"><?= e($event['title']) ?></span>
    </div>

    <div style="display: grid; grid-template-columns: 1fr; gap: 30px;">
        <div class="festive-card" style="border: 2px solid var(--gold-accent);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                <div>
                    <span class="hero-badge" style="margin-bottom: 8px;">Official Millennium India Group Event</span>
                    <h1 style="font-size: 2.5rem; font-weight: 900; color: #fff; margin-top: 6px;"><?= e($event['title']) ?></h1>
                </div>
                <a href="<?= BASE_URL ?>/booking.php?event_id=<?= $event['id'] ?>" class="btn btn-primary" style="font-size: 1.1rem; padding: 12px 28px;">
                    &#127915; Book Passes Now
                </a>
            </div>

            <!-- Key Info Bar -->
            <div class="hero-meta-grid" style="justify-content: flex-start; margin-bottom: 25px;">
                <div class="hero-meta-card">
                    <span>&#128197;</span>
                    <div>
                        <strong>Date:</strong>
                        <div><?= date('l, d F Y', strtotime($event['date'])) ?></div>
                    </div>
                </div>

                <div class="hero-meta-card">
                    <span>&#9200;</span>
                    <div>
                        <strong>Timing:</strong>
                        <div><?= date('h:i A', strtotime($event['start_time'])) ?> &ndash; <?= date('h:i A', strtotime($event['end_time'])) ?> IST</div>
                    </div>
                </div>

                <div class="hero-meta-card">
                    <span>&#128205;</span>
                    <div>
                        <strong>Venue:</strong>
                        <div><?= e($event['venue']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div style="font-size: 1.05rem; line-height: 1.8; color: #eee; margin-bottom: 30px; border-top: 1px solid rgba(245,183,34,0.2); padding-top: 20px;">
                <h3 style="color: var(--gold-accent); margin-bottom: 12px;">About The Celebration</h3>
                <p><?= nl2br(e($event['description'])) ?></p>
            </div>

            <!-- Guidelines & Notes -->
            <div style="background: rgba(20, 3, 12, 0.7); border: 1px dashed var(--gold-accent); border-radius: 12px; padding: 20px; margin-bottom: 35px;">
                <h4 style="color: var(--gold-light); margin-bottom: 10px;">&#128712; Important Attendee Guidelines</h4>
                <ul style="padding-left: 20px; font-size: 0.95rem; line-height: 1.7; color: #ddd;">
                    <li>Please carry the <strong>Digital QR Ticket</strong> on your mobile phone or printed copy for entry scanning at the gate.</li>
                    <li>Traditional Dandiya / Ethnic Indian attire is highly recommended to participate in contest prizes.</li>
                    <li>Gates open promptly at <strong><?= date('h:i A', strtotime($event['start_time'])) ?></strong>. Please arrive early to avoid queue delays.</li>
                    <li>Strict security screening, metal detectors, and bag checking in effect for family safety.</li>
                </ul>
            </div>

            <!-- Ticket Tiers Overview -->
            <div>
                <h3 class="card-title">&#127915; Available Ticket Categories</h3>
                <div class="tickets-grid" style="margin-top: 15px;">
                    <?php foreach ($categories as $cat): ?>
                        <div class="ticket-item">
                            <div>
                                <h3 class="ticket-title"><?= e($cat['name']) ?></h3>
                                <div class="ticket-price"><?= format_inr($cat['price']) ?></div>
                                <div class="ticket-capacity">
                                    <span>&#128101;</span>
                                    <span>Permits entry for <strong><?= (int)$cat['people_allowed'] ?> Person<?= $cat['people_allowed'] > 1 ? 's' : '' ?></strong></span>
                                </div>
                                <div style="font-size: 0.85rem; color: #aaa; margin-bottom: 15px;">
                                    Quota: <?= (int)$cat['available_quantity'] ?> passes available
                                </div>
                            </div>
                            <a href="<?= BASE_URL ?>/booking.php?event_id=<?= $event['id'] ?>&cat=<?= $cat['id'] ?>" class="btn btn-primary btn-block">
                                Book This Tier &rarr;
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
