<?php
// index.php - Millennium India Group Homepage & Jashn-E-Garba 2026 Showcase
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/functions.php';

$pageTitle = 'Jashn-E-Garba 2026 | Official Ticket Booking | Millennium India Group';
$currentNav = 'home';

$event = get_event(1); // Priority Launch Event
$categories = $event ? get_event_categories($event['id']) : [];

include __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner Section -->
<section class="hero-section">
    <div class="container">
        <span class="hero-badge">&#10024; Millennium India Group Presents &#10024;</span>
        <h1 class="hero-title"><?= e($event['title'] ?? 'Jashn-E-Garba 2026') ?></h1>
        <p class="hero-subtitle">
            Patna's Grandest Dandiya & Garba Mahotsav. Feel the rhythm of authentic Gujarati beats, live singers, festive lights, and royal cultural celebration!
        </p>

        <div class="hero-meta-grid">
            <div class="hero-meta-card">
                <span>&#128197;</span>
                <div>
                    <strong>Date:</strong>
                    <div><?= date('l, d F Y', strtotime($event['date'] ?? '2026-10-16')) ?></div>
                </div>
            </div>

            <div class="hero-meta-card">
                <span>&#9200;</span>
                <div>
                    <strong>Timing:</strong>
                    <div><?= date('h:i A', strtotime($event['start_time'] ?? '17:00:00')) ?> &ndash; <?= date('h:i A', strtotime($event['end_time'] ?? '21:00:00')) ?></div>
                </div>
            </div>

            <div class="hero-meta-card">
                <span>&#128205;</span>
                <div>
                    <strong>Venue:</strong>
                    <div><?= e($event['venue'] ?? 'Unique Garden, Patna') ?></div>
                </div>
            </div>
        </div>

        <div style="display:flex; justify-content:center; gap:16px; flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>/booking.php?event_id=<?= $event['id'] ?? 1 ?>" class="btn btn-primary" style="font-size:1.15rem; padding:15px 35px;">
                &#127915; Book Your Passes Now
            </a>
            <a href="<?= BASE_URL ?>/event-detail.php?slug=<?= e($event['slug'] ?? 'jashn-e-garba-2026') ?>" class="btn btn-secondary">
                View Event Schedule &amp; Details
            </a>
        </div>
    </div>
</section>

<!-- Main Container Content -->
<div class="container" style="padding-top: 50px; padding-bottom: 60px;">

    <!-- Ticket Categories Grid -->
    <div style="text-align: center; margin-bottom: 35px;">
        <span style="color: var(--gold-accent); font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Instant Booking &bull; Limited Slots</span>
        <h2 style="font-size: 2.2rem; font-weight: 800; color: #fff; margin-top: 6px;">Select Your Entry Category</h2>
        <p style="color: var(--text-muted); max-width: 600px; margin: 0 auto;">Secure your digital pass now. Instant QR confirmation via Direct UPI or Payment Gateway.</p>
    </div>

    <div class="tickets-grid">
        <?php foreach ($categories as $cat): ?>
            <?php 
                $isCouple = stripos($cat['name'], 'Couple') !== false;
                $isFamily = stripos($cat['name'], 'Family') !== false;
                $isVip = stripos($cat['name'], 'VIP') !== false;
            ?>
            <div class="ticket-item <?= ($isCouple || $isVip) ? 'featured' : '' ?>">
                <?php if ($isCouple): ?>
                    <div class="featured-ribbon">Most Popular</div>
                <?php elseif ($isVip): ?>
                    <div class="featured-ribbon" style="background:#ff4757; color:#fff;">Exclusive</div>
                <?php endif; ?>

                <div>
                    <h3 class="ticket-title"><?= e($cat['name']) ?></h3>
                    <div class="ticket-price"><?= format_inr($cat['price']) ?></div>
                    <div class="ticket-capacity">
                        <span>&#128101;</span>
                        <span>Admits: <strong><?= (int)$cat['people_allowed'] ?> Person<?= $cat['people_allowed'] > 1 ? 's' : '' ?></strong></span>
                    </div>
                    <p style="font-size: 0.88rem; color: #ccc; margin-bottom: 20px;">
                        Includes grand entry, Dandiya sticks access, live orchestra, and contest eligibility.
                    </p>
                </div>

                <div>
                    <div style="font-size:0.8rem; color: <?= $cat['available_quantity'] < 50 ? '#f87171' : '#34d399' ?>; margin-bottom: 12px; font-weight: 600;">
                        &#9889; <?= (int)$cat['available_quantity'] ?> passes remaining
                    </div>
                    <a href="<?= BASE_URL ?>/booking.php?event_id=<?= $event['id'] ?>&cat=<?= $cat['id'] ?>" class="btn btn-primary btn-block">
                        Select Pass &rarr;
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Event Highlights Grid -->
    <div class="festive-card" style="margin-top: 40px;">
        <h3 class="card-title">&#127926; Event Highlights &amp; Attractions</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-top: 20px;">
            <div style="background: rgba(20, 2, 11, 0.6); padding: 18px; border-radius: 12px; border: 1px solid rgba(245, 183, 34, 0.2);">
                <div style="font-size: 2rem; color: var(--gold-accent); margin-bottom: 8px;">&#127931;</div>
                <h4 style="color: #fff; margin-bottom: 6px;">Authentic Gujarati Folk Band</h4>
                <p style="font-size: 0.9rem; color: var(--text-muted);">Live dhol, nagada, and vocalists singing traditional Raas-Garba melodies all evening.</p>
            </div>

            <div style="background: rgba(20, 2, 11, 0.6); padding: 18px; border-radius: 12px; border: 1px solid rgba(245, 183, 34, 0.2);">
                <div style="font-size: 2rem; color: var(--gold-accent); margin-bottom: 8px;">&#127911;</div>
                <h4 style="color: #fff; margin-bottom: 6px;">Celebrity DJ &amp; Sound</h4>
                <p style="font-size: 0.9rem; color: var(--text-muted);">High-octane Bollywood beats &amp; EDM fusion Dandiya in the second half of the night.</p>
            </div>

            <div style="background: rgba(20, 2, 11, 0.6); padding: 18px; border-radius: 12px; border: 1px solid rgba(245, 183, 34, 0.2);">
                <div style="font-size: 2rem; color: var(--gold-accent); margin-bottom: 8px;">&#127942;</div>
                <h4 style="color: #fff; margin-bottom: 6px;">Mega Contest &amp; Awards</h4>
                <p style="font-size: 0.9rem; color: var(--text-muted);">Prizes for Best Traditional Attire, Best Garba Performer, Best Couple Dancer &amp; Kids.</p>
            </div>

            <div style="background: rgba(20, 2, 11, 0.6); padding: 18px; border-radius: 12px; border: 1px solid rgba(245, 183, 34, 0.2);">
                <div style="font-size: 2rem; color: var(--gold-accent); margin-bottom: 8px;">&#128737;</div>
                <h4 style="color: #fff; margin-bottom: 6px;">Family-Safe Atmosphere</h4>
                <p style="font-size: 0.9rem; color: var(--text-muted);">CCTV surveillance, bouncers, strict digital QR ticket gate verification, and dedicated parking.</p>
            </div>
        </div>
    </div>

    <!-- Quick Booking CTA Banner -->
    <div style="background: linear-gradient(135deg, #7a1244, #410521); border: 2px solid var(--gold-accent); border-radius: 16px; padding: 40px 20px; text-align: center; margin-top: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
        <h2 style="font-size: 2.2rem; color: #fff; margin-bottom: 12px;">Don't Miss Patna's Biggest Dandiya Night!</h2>
        <p style="color: var(--text-muted); max-width: 650px; margin: 0 auto 25px; font-size: 1.1rem;">
            Limited passes available. Early Bird pricing closes soon. Reserve your spot today!
        </p>
        <a href="<?= BASE_URL ?>/booking.php?event_id=<?= $event['id'] ?? 1 ?>" class="btn btn-primary" style="font-size: 1.1rem; padding: 14px 36px;">
            &#127915; Grab Early Bird Tickets
        </a>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
