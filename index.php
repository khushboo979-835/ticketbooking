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
<section class="hero-section" style="padding: 40px 0 60px;">
    <div class="container">
        
        <!-- Celebrity Guest Banner Top Badge -->
        <div style="display:inline-block; background: linear-gradient(135deg, #f5b722, #e67e22); color: #1f0411; font-weight: 800; padding: 8px 24px; border-radius: 50px; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 15px rgba(245,183,34,0.4); margin-bottom: 20px;">
            &#127775; Special Celebrity Guest: Saurabh Raj Jain (Mahabharat Fame Krishna) &#127775;
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 35px; align-items: center; text-align: left; margin-bottom: 35px;">
            <div>
                <span class="hero-badge" style="display:inline-block; margin-bottom: 10px;">&#10024; Millennium India Group Presents</span>
                <h1 class="hero-title" style="text-align: left; font-size: 2.8rem; line-height: 1.15; margin-bottom: 15px;">
                    <?= e($event['title'] ?? 'Jashn-E-Garba 2026') ?>
                </h1>
                <p class="hero-subtitle" style="text-align: left; font-size: 1.15rem; margin: 0 0 20px 0; color: #f8fafc;">
                    <strong>Dance &bull; Celebrate &bull; Feel the Vibes!</strong><br>
                    Patna's Grandest Dandiya & Garba Mahotsav. Live Gujarati folk band, authentic festive vibes, food included with passes, and special appearance by celebrated actor <strong>Saurabh Raj Jain</strong> and anchor <strong>Roshni</strong>!
                </p>

                <div class="hero-meta-grid" style="grid-template-columns: 1fr; gap: 12px; margin-bottom: 25px;">
                    <div class="hero-meta-card" style="padding: 12px 18px;">
                        <span style="font-size: 1.6rem;">&#128197;</span>
                        <div>
                            <strong>Date &amp; Timing:</strong>
                            <div>Friday, 16 October 2026 &bull; 05:00 PM Onwards</div>
                        </div>
                    </div>

                    <div class="hero-meta-card" style="padding: 12px 18px;">
                        <span style="font-size: 1.6rem;">&#128205;</span>
                        <div>
                            <strong>Venue:</strong>
                            <div>Unique Garden, Pillar No. 1, Bailey Road, Patna - 800014</div>
                        </div>
                    </div>

                    <div class="hero-meta-card" style="padding: 12px 18px;">
                        <span style="font-size: 1.6rem;">&#127869;</span>
                        <div>
                            <strong>Special Privilege:</strong>
                            <div style="color: var(--gold-accent); font-weight: 700;">Delicious Food Included with All Valid Passes!</div>
                        </div>
                    </div>
                </div>

                <div style="display:flex; gap:16px; flex-wrap:wrap;">
                    <a href="<?= BASE_URL ?>/booking.php?event_id=<?= $event['id'] ?? 1 ?>" class="btn btn-primary" style="font-size:1.15rem; padding:15px 36px; box-shadow: 0 6px 20px rgba(245,183,34,0.4);">
                        &#127915; Book Passes Now
                    </a>
                    <a href="tel:8603268304" class="btn btn-secondary" style="font-size:1rem; padding:15px 24px;">
                        &#128222; Helpline: 8603268304
                    </a>
                </div>
            </div>

            <!-- Official Poster Showcase -->
            <div style="text-align: center;">
                <div style="position: relative; display: inline-block; border-radius: 16px; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.8); border: 2px solid var(--gold-accent);">
                    <img src="<?= BASE_URL ?>/assets/uploads/jashn-poster-official.jpg" alt="Jashn-E-Garba 2026 Official Poster" style="width: 100%; max-width: 440px; display: block;">
                </div>
            </div>
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

    <!-- Direct Instant UPI Payment Card on Homepage -->
    <div class="festive-card" style="margin-top: 45px; border: 2px solid var(--gold-accent); background: linear-gradient(135deg, rgba(61,7,32,0.95), rgba(20,2,11,0.98)); padding: 30px 20px;">
        <div style="text-align: center; margin-bottom: 25px;">
            <span style="background: var(--gold-accent); color: #1f0411; font-weight: 800; font-size: 0.8rem; padding: 4px 14px; border-radius: 20px; text-transform: uppercase;">
                Fastest Checkout
            </span>
            <h2 style="font-size: 2rem; color: #fff; margin-top: 10px; font-weight: 800;">
                &#128242; Instant Scan &amp; Pay Pass
            </h2>
            <p style="color: var(--text-muted); max-width: 650px; margin: 0 auto; font-size: 0.95rem;">
                Pay directly to our official State Bank of India UPI QR code. Submit your 12-digit UTR number below to instantly reserve your passes!
            </p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; align-items: center; max-width: 850px; margin: 0 auto;">
            <div style="text-align: center;">
                <div style="background: #fff; padding: 15px; border-radius: 16px; display: inline-block; box-shadow: 0 10px 30px rgba(0,0,0,0.6); border: 2px solid var(--gold-accent);">
                    <img src="<?= BASE_URL ?>/assets/uploads/upi-qr-scanner.png" alt="Official SBI UPI Scanner" style="max-width: 210px; width: 100%; display: block; border-radius: 8px;">
                </div>
                <div style="margin-top: 12px; font-size: 0.85rem; color: #cbd5e1;">
                    Verified Merchant: <strong style="color: #fff;">chandni 0777 (SBI)</strong><br>
                    UPI ID: <strong style="color: var(--gold-accent); font-family: monospace;">chandni0984@oksbi</strong>
                </div>
            </div>

            <div>
                <form action="<?= BASE_URL ?>/booking.php?event_id=1&step=2" method="POST" style="background: rgba(15,2,9,0.7); padding: 22px; border-radius: 12px; border: 1px solid rgba(245,183,34,0.3);">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="select_tickets">
                    <input type="hidden" name="quantities[1]" value="1">
                    
                    <h3 style="color: var(--gold-light); font-size: 1.15rem; margin-bottom: 12px;">Quick Single Pass (₹999)</h3>
                    <p style="font-size: 0.85rem; color: #aaa; margin-bottom: 15px;">Includes Entry + Authentic Gujarati Beats + Food</p>
                    
                    <a href="<?= BASE_URL ?>/booking.php?event_id=1&step=1" class="btn btn-primary btn-block" style="padding: 13px; font-weight: 800; font-size: 1rem; text-align: center; display: block; margin-bottom: 10px;">
                        &#127915; Choose Passes &amp; Pay UTR
                    </a>
                    <a href="upi://pay?pa=chandni0984@oksbi&pn=chandni%200777&cu=INR" class="btn btn-secondary btn-block" style="padding: 11px; font-size: 0.9rem; text-align: center; display: block; background: #252836;">
                        &#128241; Open GPay / PhonePe App
                    </a>
                </form>
            </div>
        </div>
    </div>

    <!-- Official Garba Videos Section -->
    <div class="festive-card" style="margin-top: 45px;">
        <div style="text-align: center; margin-bottom: 25px;">
            <span style="color: var(--gold-accent); font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Watch The Excitement</span>
            <h2 style="font-size: 2rem; font-weight: 800; color: #fff; margin-top: 6px;">&#127916; Event Teasers &amp; Vibes</h2>
            <p style="color: var(--text-muted); max-width: 600px; margin: 0 auto;">Catch a glimpse of the grand Dandiya celebration and festive cultural mania!</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap: 24px;">
            <!-- Video 1 -->
            <div style="background: #0d0107; border: 1px solid var(--gold-accent); border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.6);">
                <video controls playsinline preload="metadata" style="width: 100%; display: block; max-height: 480px; object-fit: cover; background: #000;">
                    <source src="<?= BASE_URL ?>/assets/uploads/Video1%20.mp4" type="video/mp4">
                    Your browser does not support HTML5 video.
                </video>
                <div style="padding: 14px 18px;">
                    <strong style="color: #fff; font-size: 1rem;">&#127775; Jashn-E-Garba 2026 Promo Teaser</strong>
                    <div style="font-size: 0.85rem; color: var(--gold-light); margin-top: 4px;">Feel the high-energy Dandiya beats &amp; grandeur</div>
                </div>
            </div>

            <!-- Video 2 -->
            <div style="background: #0d0107; border: 1px solid var(--gold-accent); border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.6);">
                <video controls playsinline preload="metadata" style="width: 100%; display: block; max-height: 480px; object-fit: cover; background: #000;">
                    <source src="<?= BASE_URL ?>/assets/uploads/Video2.mp4" type="video/mp4">
                    Your browser does not support HTML5 video.
                </video>
                <div style="padding: 14px 18px;">
                    <strong style="color: #fff; font-size: 1rem;">&#127881; Celebrity Guest Invitation</strong>
                    <div style="font-size: 0.85rem; color: var(--gold-light); margin-top: 4px;">Saurabh Raj Jain inviting all Patna Dandiya lovers</div>
                </div>
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
