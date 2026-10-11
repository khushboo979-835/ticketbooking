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

<!-- 1. TOP OFFICIAL GRAPHIC BANNER (Positioned Right Below Navbar) -->
<section style="background: linear-gradient(180deg, #18020e 0%, #2a0319 50%, #16010c 100%); padding: 25px 0 35px; border-bottom: 2px solid rgba(245, 183, 34, 0.3);">
    <div class="container" style="text-align: center;">

        <!-- Celebrity Guest Announcement Pill -->
        <div style="display:inline-block; background: linear-gradient(135deg, #f5b722, #e67e22); color: #1f0411; font-weight: 800; padding: 7px 22px; border-radius: 50px; font-size: 0.88rem; text-transform: uppercase; letter-spacing: 0.8px; box-shadow: 0 4px 15px rgba(245,183,34,0.4); margin-bottom: 18px;">
            &#127775; Special Celebrity Guest: Saurabh Raj Jain (Mahabharat Fame Krishna) &bull; Anchor Roshni &#127775;
        </div>

        <!-- Highlighted Official Poster Graphic (Full View under Navbar) -->
        <div style="max-width: 860px; margin: 0 auto; position: relative;">
            <div style="border-radius: 16px; overflow: hidden; box-shadow: 0 15px 45px rgba(0,0,0,0.85); border: 2.5px solid var(--gold-accent);">
                <img src="<?= BASE_URL ?>/assets/uploads/jashn-poster-official.jpg" 
                     alt="Jashn-E-Garba 2026 Official Poster - Saurabh Raj Jain & Anchor Roshni" 
                     style="width: 100%; display: block; object-fit: contain; max-height: 580px; margin: 0 auto; background: #000;">
            </div>
        </div>

        <!-- Immediate Action CTA Under Poster -->
        <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; margin-top: 22px;">
            <a href="<?= BASE_URL ?>/booking.php?event_id=<?= $event['id'] ?? 1 ?>" class="btn btn-primary" style="font-size: 1.12rem; padding: 14px 34px; box-shadow: 0 6px 20px rgba(245,183,34,0.4); font-weight: 800;">
                &#127915; Book Passes Now (From ₹999)
            </a>
            <a href="tel:7004665804" class="btn btn-secondary" style="font-size: 1rem; padding: 14px 24px; font-weight: 700;">
                &#128222; Helpline: +91 7004665804
            </a>
        </div>

    </div>
</section>

<!-- Main Container Content -->
<div class="container" style="padding-top: 35px; padding-bottom: 60px;">

    <!-- 2. EVENT DETAILS & HIGHLIGHTS CARD (Placed Cleanly Below Graphic) -->
    <div class="festive-card" style="margin-bottom: 35px; padding: 25px 22px;">
        <div style="text-align: center; margin-bottom: 22px;">
            <span class="hero-badge" style="display:inline-block; margin-bottom: 6px;">Millennium India Group Presents</span>
            <h1 style="font-size: 2.2rem; font-weight: 900; color: #fff; margin: 4px 0 8px;">
                <?= e($event['title'] ?? 'Jashn-E-Garba 2026') ?>
            </h1>
            <p style="color: var(--gold-light); font-size: 1.05rem; font-weight: 700; margin: 0;">
                Dance &bull; Celebrate &bull; Feel the Vibes!
            </p>
        </div>

        <div class="hero-meta-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
            <div class="hero-meta-card" style="padding: 14px 18px;">
                <span style="font-size: 1.7rem;">&#128197;</span>
                <div>
                    <strong>Date &amp; Timing:</strong>
                    <div style="color: #fff; font-weight: 700;">Friday, 16 October 2026 &bull; 05:00 PM Onwards</div>
                </div>
            </div>

            <div class="hero-meta-card" style="padding: 14px 18px;">
                <span style="font-size: 1.7rem;">&#128205;</span>
                <div>
                    <strong>Venue:</strong>
                    <div style="color: #fff; font-weight: 700;">Unique Garden, Pillar No. 1, Bailey Road, Patna</div>
                </div>
            </div>

            <div class="hero-meta-card" style="padding: 14px 18px;">
                <span style="font-size: 1.7rem;">&#127869;</span>
                <div>
                    <strong>Special Privilege:</strong>
                    <div style="color: var(--gold-accent); font-weight: 800;">Delicious Food Included with All Valid Passes!</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. OFFICIAL 3-VIDEO ROTATIONAL SHOWCASE (Video0 at top, then Video1 & Video2) -->
    <div class="festive-card" style="margin-bottom: 35px; padding: 25px 20px;">
        <div style="text-align: center; margin-bottom: 20px;">
            <span style="color: var(--gold-accent); font-weight: 700; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px;">Watch The Excitement</span>
            <h2 style="font-size: 1.85rem; font-weight: 800; color: #fff; margin-top: 4px;">&#127916; Official Videos &amp; Teasers</h2>
            <p style="color: var(--text-muted); max-width: 600px; margin: 0 auto; font-size: 0.9rem;">
                Catch a glimpse of the grand Dandiya beats, celebrity guest invitation &amp; festive vibes!
            </p>
        </div>

        <!-- Video Rotational Switcher Tabs -->
        <div style="display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
            <button type="button" class="video-tab-btn active" onclick="switchVideo(0)" id="vtab-0" style="background: var(--gold-accent); color: #1f0411; border: none; font-weight: 800; padding: 9px 18px; border-radius: 50px; cursor: pointer; font-size: 0.88rem; transition: all 0.2s ease;">
                &#127775; Video 0: Celebrity Teaser
            </button>
            <button type="button" class="video-tab-btn" onclick="switchVideo(1)" id="vtab-1" style="background: rgba(255,255,255,0.08); color: #cbd5e1; border: 1px solid rgba(245,183,34,0.3); font-weight: 700; padding: 9px 18px; border-radius: 50px; cursor: pointer; font-size: 0.88rem; transition: all 0.2s ease;">
                &#128131; Video 1: Grand Garba Promo
            </button>
            <button type="button" class="video-tab-btn" onclick="switchVideo(2)" id="vtab-2" style="background: rgba(255,255,255,0.08); color: #cbd5e1; border: 1px solid rgba(245,183,34,0.3); font-weight: 700; padding: 9px 18px; border-radius: 50px; cursor: pointer; font-size: 0.88rem; transition: all 0.2s ease;">
                &#127881; Video 2: Saurabh Raj Jain Invite
            </button>
        </div>

        <!-- Main Featured Video Player (Autoplay Muted Loop with Controls) -->
        <div style="max-width: 820px; margin: 0 auto; background: #0d0107; border: 2px solid var(--gold-accent); border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.7);">
            <video id="featured-video-player" controls autoplay muted loop playsinline preload="auto" style="width: 100%; display: block; aspect-ratio: 16/9; max-height: 440px; object-fit: cover; background: #000;">
                <source id="featured-video-src" src="<?= BASE_URL ?>/assets/uploads/Video0.mp4" type="video/mp4">
                Your browser does not support HTML5 video.
            </video>
            <div style="padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; background: rgba(20,2,11,0.95); border-top: 1px solid rgba(245,183,34,0.2);">
                <div>
                    <strong id="video-title-display" style="color: #fff; font-size: 1rem;">&#127775; Video 0: Official Event Teaser</strong>
                    <div id="video-desc-display" style="font-size: 0.82rem; color: var(--gold-light); margin-top: 2px;">Featuring Grand Dandiya Mahotsav &amp; Star Celebrities</div>
                </div>
                <button type="button" onclick="toggleAudio()" id="btn-audio-toggle" style="background: rgba(245,183,34,0.15); border: 1px solid var(--gold-accent); color: var(--gold-accent); font-weight: 700; font-size: 0.82rem; padding: 6px 14px; border-radius: 20px; cursor: pointer;">
                    &#128266; Unmute Sound
                </button>
            </div>
        </div>

        <!-- 3 Side-by-Side Mini Video Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-top: 22px;">
            <div onclick="switchVideo(0)" style="cursor: pointer; background: rgba(30,3,17,0.8); border: 1px solid rgba(245,183,34,0.3); border-radius: 10px; padding: 12px; transition: all 0.2s ease;">
                <div style="font-weight: 800; color: #fff; font-size: 0.92rem;">&#9654; Video 0: Star Teaser</div>
                <div style="font-size: 0.78rem; color: var(--gold-light); margin-top: 2px;">Tap to watch featured trailer</div>
            </div>
            <div onclick="switchVideo(1)" style="cursor: pointer; background: rgba(30,3,17,0.8); border: 1px solid rgba(245,183,34,0.3); border-radius: 10px; padding: 12px; transition: all 0.2s ease;">
                <div style="font-weight: 800; color: #fff; font-size: 0.92rem;">&#9654; Video 1: Garba Beats</div>
                <div style="font-size: 0.78rem; color: var(--gold-light); margin-top: 2px;">Feel the live orchestra energy</div>
            </div>
            <div onclick="switchVideo(2)" style="cursor: pointer; background: rgba(30,3,17,0.8); border: 1px solid rgba(245,183,34,0.3); border-radius: 10px; padding: 12px; transition: all 0.2s ease;">
                <div style="font-weight: 800; color: #fff; font-size: 0.92rem;">&#9654; Video 2: Saurabh Raj Jain</div>
                <div style="font-size: 0.78rem; color: var(--gold-light); margin-top: 2px;">Personal message for Patna fans</div>
            </div>
        </div>
    </div>

    <!-- 4. DIRECT UPI PAYMENT CARD (Scanner Image Removed, Direct UPI Details Added) -->
    <div class="festive-card" style="margin-bottom: 35px; border: 2px solid var(--gold-accent); background: linear-gradient(135deg, rgba(61,7,32,0.96), rgba(20,2,11,0.98)); padding: 22px 20px; border-radius: 14px; box-shadow: 0 8px 30px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 14px; border-bottom: 1px dashed rgba(245,183,34,0.3); padding-bottom: 12px;">
            <div>
                <span style="background: var(--gold-accent); color: #1f0411; font-weight: 800; font-size: 0.74rem; padding: 3px 12px; border-radius: 20px; text-transform: uppercase;">
                    &#9889; Quick Direct UPI Payment
                </span>
                <h3 style="font-size: 1.35rem; color: #fff; margin: 6px 0 0; font-weight: 800;">
                    Direct UPI Transfer &bull; Zero Convenience Fee
                </h3>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.85rem; color: #cbd5e1;">Payee: <strong style="color: #fff;">chandni 0777 (SBI)</strong></div>
                <div style="display: flex; align-items: center; gap: 8px; justify-content: flex-end; margin-top: 3px;">
                    <code id="upi-id-text" style="color: var(--gold-accent); font-size: 0.92rem; font-weight: 800; background: rgba(0,0,0,0.4); padding: 3px 8px; border-radius: 6px; border: 1px solid rgba(245,183,34,0.3);">chandni0984@oksbi</code>
                    <button type="button" onclick="copyUpiId()" id="btn-copy-upi" style="background: var(--gold-accent); color: #1f0411; border: none; font-weight: 800; font-size: 0.75rem; padding: 4px 10px; border-radius: 6px; cursor: pointer;">
                        Copy
                    </button>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <p style="font-size: 0.9rem; color: #e2e8f0; margin: 0; line-height: 1.5; flex: 1; min-width: 260px;">
                Pay exact amount using any app (<strong>GPay, PhonePe, Paytm or BHIM</strong>) to UPI ID <code style="color: var(--gold-accent);">chandni0984@oksbi</code>. Enter your 12-digit UTR in booking form for instant QR entry ticket!
            </p>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="upi://pay?pa=chandni0984@oksbi&pn=chandni%200777&cu=INR" class="btn btn-secondary" style="padding: 10px 18px; font-size: 0.88rem; border-radius: 8px; background: #222634; font-weight: 700;">
                    &#128241; Open UPI App
                </a>
                <a href="<?= BASE_URL ?>/booking.php?event_id=1&step=1" class="btn btn-primary" style="padding: 10px 22px; font-weight: 800; font-size: 0.92rem; border-radius: 8px;">
                    &#127915; Book Passes (From ₹999)
                </a>
            </div>
        </div>
    </div>

    <!-- 5. SELECT YOUR ENTRY CATEGORY (Single Stacked Vertical Cards Matching Client Image 3) -->
    <div style="text-align: center; margin-bottom: 25px;">
        <span style="color: var(--gold-accent); font-weight: 800; text-transform: uppercase; font-size: 0.82rem; letter-spacing: 1px;">Instant Booking &bull; Limited Slots</span>
        <h2 style="font-size: 2.1rem; font-weight: 900; color: #fff; margin-top: 4px;">Select Your Entry Category</h2>
        <p style="color: var(--text-muted); max-width: 600px; margin: 0 auto; font-size: 0.92rem;">
            Secure your digital pass now. Delicious Food included with all passes!
        </p>
    </div>

    <!-- Single Stacked Cards (Single single rakhiy booking ka) -->
    <div style="display: flex; flex-direction: column; gap: 16px; max-width: 820px; margin: 0 auto 40px;">
        <?php foreach ($categories as $cat): ?>
            <?php 
                $isCouple = stripos($cat['name'], 'Couple') !== false;
                $isFamily = stripos($cat['name'], 'Family') !== false;
                $isVip = stripos($cat['name'], 'VIP') !== false;
                $isSoldOut = ($cat['available_quantity'] <= 0 || $cat['status'] === 'sold_out');
            ?>
            <div style="background: rgba(26, 3, 14, 0.95); border: 1.5px solid <?= ($isCouple || $isVip) ? 'var(--gold-accent)' : 'rgba(245, 183, 34, 0.35)' ?>; border-radius: 14px; padding: 20px 22px; box-shadow: 0 6px 20px rgba(0,0,0,0.5); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; position: relative;">
                
                <?php if ($isCouple): ?>
                    <span style="position: absolute; top: -10px; right: 20px; background: var(--gold-accent); color: #1f0411; font-size: 0.72rem; font-weight: 800; padding: 2px 12px; border-radius: 20px; text-transform: uppercase;">
                        Most Popular
                    </span>
                <?php elseif ($isVip): ?>
                    <span style="position: absolute; top: -10px; right: 20px; background: #ff4757; color: #fff; font-size: 0.72rem; font-weight: 800; padding: 2px 12px; border-radius: 20px; text-transform: uppercase;">
                        Exclusive VIP
                    </span>
                <?php endif; ?>

                <!-- Pass Details -->
                <div style="flex: 1; min-width: 250px;">
                    <div style="display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap;">
                        <h3 style="color: #fff; font-size: 1.25rem; font-weight: 800; margin: 0;"><?= e($cat['name']) ?></h3>
                    </div>
                    <div style="font-size: 0.88rem; color: var(--gold-light); margin-top: 6px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span>&#128101; Admits <strong><?= (int)$cat['people_allowed'] ?> Person<?= $cat['people_allowed'] > 1 ? 's' : '' ?></strong></span>
                        <span style="color: #666;">&bull;</span>
                        <span style="color: #4ade80; font-weight: 700;">&#127869; Food Included</span>
                    </div>
                    <div style="font-size: 0.82rem; color: <?= $cat['available_quantity'] < 50 ? '#f87171' : '#34d399' ?>; margin-top: 5px; font-weight: 700;">
                        &#9889; <?= $isSoldOut ? 'Sold Out' : ((int)$cat['available_quantity'] . ' passes remaining') ?>
                    </div>
                </div>

                <!-- Price and Action Button -->
                <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                    <div style="text-align: right;">
                        <div style="font-size: 1.6rem; font-weight: 900; color: var(--gold-accent); line-height: 1;">
                            <?= format_inr($cat['price']) ?>
                        </div>
                        <div style="font-size: 0.75rem; color: #aaa; margin-top: 2px;">all taxes incl.</div>
                    </div>

                    <div>
                        <?php if ($isSoldOut): ?>
                            <button class="btn btn-secondary" disabled style="opacity: 0.6; cursor: not-allowed; padding: 10px 20px;">Sold Out</button>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>/booking.php?event_id=<?= $event['id'] ?>&cat=<?= $cat['id'] ?>" class="btn btn-primary" style="padding: 12px 24px; font-weight: 800; font-size: 0.95rem; border-radius: 8px; box-shadow: 0 4px 15px rgba(245,183,34,0.3);">
                                Select Pass &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

    <!-- 6. EVENT HIGHLIGHTS & ATTRACTIONS -->
    <div class="festive-card" style="margin-top: 40px; padding: 25px 22px;">
        <h3 class="card-title">&#127926; Event Highlights &amp; Grand Attractions</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-top: 20px;">
            <div style="background: rgba(20, 2, 11, 0.6); padding: 18px; border-radius: 12px; border: 1px solid rgba(245, 183, 34, 0.2);">
                <div style="font-size: 2rem; color: var(--gold-accent); margin-bottom: 8px;">&#127931;</div>
                <h4 style="color: #fff; margin-bottom: 6px;">Authentic Gujarati Folk Band</h4>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">Live dhol, nagada, and vocalists singing traditional Raas-Garba melodies all evening.</p>
            </div>

            <div style="background: rgba(20, 2, 11, 0.6); padding: 18px; border-radius: 12px; border: 1px solid rgba(245, 183, 34, 0.2);">
                <div style="font-size: 2rem; color: var(--gold-accent); margin-bottom: 8px;">&#127911;</div>
                <h4 style="color: #fff; margin-bottom: 6px;">Celebrity DJ &amp; Sound</h4>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">High-octane Bollywood beats &amp; EDM fusion Dandiya in the second half of the night.</p>
            </div>

            <div style="background: rgba(20, 2, 11, 0.6); padding: 18px; border-radius: 12px; border: 1px solid rgba(245, 183, 34, 0.2);">
                <div style="font-size: 2rem; color: var(--gold-accent); margin-bottom: 8px;">&#127942;</div>
                <h4 style="color: #fff; margin-bottom: 6px;">Mega Contest &amp; Awards</h4>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">Prizes for Best Traditional Attire, Best Garba Performer, Best Couple Dancer &amp; Kids.</p>
            </div>

            <div style="background: rgba(20, 2, 11, 0.6); padding: 18px; border-radius: 12px; border: 1px solid rgba(245, 183, 34, 0.2);">
                <div style="font-size: 2rem; color: var(--gold-accent); margin-bottom: 8px;">&#128737;</div>
                <h4 style="color: #fff; margin-bottom: 6px;">Family-Safe Atmosphere</h4>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">CCTV surveillance, bouncers, strict digital QR ticket gate verification, and dedicated parking.</p>
            </div>
        </div>
    </div>

</div>

<!-- Interactive Video Switcher & Copy UPI Scripts -->
<script>
const videoSources = [
    {
        src: '<?= BASE_URL ?>/assets/uploads/Video0.mp4',
        title: '🌟 Video 0: Official Event Teaser',
        desc: 'Featuring Grand Dandiya Mahotsav & Star Celebrities'
    },
    {
        src: '<?= BASE_URL ?>/assets/uploads/Video1.mp4',
        title: '💃 Video 1: Jashn-E-Garba High-Energy Promo',
        desc: 'Feel the high-energy Dandiya beats, dhol & grand festive vibes'
    },
    {
        src: '<?= BASE_URL ?>/assets/uploads/Video2.mp4',
        title: '🎭 Video 2: Saurabh Raj Jain Invitation',
        desc: 'Celebrity Guest Saurabh Raj Jain inviting all Patna Dandiya lovers'
    }
];

function switchVideo(index) {
    const player = document.getElementById('featured-video-player');
    const titleEl = document.getElementById('video-title-display');
    const descEl = document.getElementById('video-desc-display');
    
    // Update tabs
    for (let i = 0; i < 3; i++) {
        const btn = document.getElementById('vtab-' + i);
        if (btn) {
            if (i === index) {
                btn.style.background = 'var(--gold-accent)';
                btn.style.color = '#1f0411';
                btn.style.border = 'none';
            } else {
                btn.style.background = 'rgba(255,255,255,0.08)';
                btn.style.color = '#cbd5e1';
                btn.style.border = '1px solid rgba(245,183,34,0.3)';
            }
        }
    }
    
    if (videoSources[index]) {
        player.pause();
        player.src = videoSources[index].src;
        titleEl.innerHTML = videoSources[index].title;
        descEl.innerHTML = videoSources[index].desc;
        player.load();
        player.play().catch(function(err){ console.log('Autoplay deferred:', err); });
    }
}

function toggleAudio() {
    const player = document.getElementById('featured-video-player');
    const btn = document.getElementById('btn-audio-toggle');
    if (player.muted) {
        player.muted = false;
        btn.innerHTML = '&#128264; Mute Sound';
    } else {
        player.muted = true;
        btn.innerHTML = '&#128266; Unmute Sound';
    }
}

function copyUpiId() {
    const upiText = 'chandni0984@oksbi';
    navigator.clipboard.writeText(upiText).then(function() {
        const btn = document.getElementById('btn-copy-upi');
        btn.innerText = 'Copied!';
        btn.style.background = '#4ade80';
        btn.style.color = '#000';
        setTimeout(function() {
            btn.innerText = 'Copy';
            btn.style.background = 'var(--gold-accent)';
            btn.style.color = '#1f0411';
        }, 2000);
    }).catch(function() {
        alert('UPI ID: ' + upiText);
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
