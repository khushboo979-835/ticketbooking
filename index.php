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

<!-- 1. VERY TOP SECTION: ALL 3 VIDEOS PLAYING CONTINUOUSLY AT THE VERY TOP (Client: Tino video chalte huye Daliy Sabse uper me) -->
<section style="background: linear-gradient(180deg, #18020e 0%, #2a0319 50%, #16010c 100%); padding: 18px 0 28px; border-bottom: 2px solid rgba(245, 183, 34, 0.3);">
    <div class="container" style="text-align: center;">

        <!-- 3 Videos Grid (All 3 playing continuously in loop, with one-tap sound buttons) -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 18px; max-width: 1140px; margin: 0 auto;">
            
            <!-- Video 0: Official Teaser Promo -->
            <div style="background: #0d0107; border: 2px solid var(--gold-accent); border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.75); display: flex; flex-direction: column;">
                <div style="padding: 10px 14px; background: rgba(30,3,17,0.95); display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(245,183,34,0.25); flex-wrap: wrap; gap: 6px;">
                    <div style="text-align: left;">
                        <span style="background: var(--gold-accent); color: #1f0411; font-weight: 800; font-size: 0.7rem; padding: 2px 8px; border-radius: 20px; text-transform: uppercase;">
                            &#9654; Live Teaser
                        </span>
                        <strong style="color: #fff; font-size: 0.9rem; margin-left: 5px;">Promo Video</strong>
                    </div>
                    <button type="button" onclick="playWithSound('top-v0-player')" id="btn-sound-v0" style="background: rgba(245,183,34,0.2); border: 1.5px solid var(--gold-accent); color: var(--gold-accent); font-weight: 800; font-size: 0.78rem; padding: 4px 12px; border-radius: 20px; cursor: pointer; transition: all 0.2s ease;">
                        &#128266; Sound ON
                    </button>
                </div>
                <video id="top-v0-player" controls autoplay muted loop playsinline preload="auto" style="width: 100%; display: block; aspect-ratio: 16/9; max-height: 250px; object-fit: cover; background: #000; flex: 1;">
                    <source src="<?= BASE_URL ?>/assets/uploads/Video0.mp4" type="video/mp4">
                    Your browser does not support HTML5 video.
                </video>
                <div style="padding: 8px 12px; background: rgba(20,2,11,0.95); text-align: center; font-size: 0.8rem; color: var(--gold-light); font-weight: 600;">
                    &#127915; Jashn-E-Garba Promo Teaser
                </div>
            </div>

            <!-- Video 1: Garba Beats & Vibes -->
            <div style="background: #0d0107; border: 2px solid var(--gold-accent); border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.75); display: flex; flex-direction: column;">
                <div style="padding: 10px 14px; background: rgba(30,3,17,0.95); display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(245,183,34,0.25); flex-wrap: wrap; gap: 6px;">
                    <div style="text-align: left;">
                        <span style="background: #e67e22; color: #fff; font-weight: 800; font-size: 0.7rem; padding: 2px 8px; border-radius: 20px; text-transform: uppercase;">
                            &#128131; Beats
                        </span>
                        <strong style="color: #fff; font-size: 0.9rem; margin-left: 5px;">Garba Beats &amp; Vibes</strong>
                    </div>
                    <button type="button" onclick="playWithSound('v1-player')" id="btn-sound-v1" style="background: rgba(245,183,34,0.2); border: 1.5px solid var(--gold-accent); color: var(--gold-accent); font-weight: 800; font-size: 0.78rem; padding: 4px 12px; border-radius: 20px; cursor: pointer; transition: all 0.2s ease;">
                        &#128266; Sound ON
                    </button>
                </div>
                <video id="v1-player" controls autoplay muted loop playsinline preload="auto" style="width: 100%; display: block; aspect-ratio: 16/9; max-height: 250px; object-fit: cover; background: #000; flex: 1;">
                    <source src="<?= BASE_URL ?>/assets/uploads/Video1.mp4" type="video/mp4">
                    Your browser does not support HTML5 video.
                </video>
                <div style="padding: 8px 12px; background: rgba(20,2,11,0.95); text-align: center; font-size: 0.8rem; color: var(--gold-light); font-weight: 600;">
                    &#129345; High Energy Folk Garba Beats
                </div>
            </div>

            <!-- Video 2: Saurabh Raj Jain Invitation -->
            <div style="background: #0d0107; border: 2px solid var(--gold-accent); border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.75); display: flex; flex-direction: column;">
                <div style="padding: 10px 14px; background: rgba(30,3,17,0.95); display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(245,183,34,0.25); flex-wrap: wrap; gap: 6px;">
                    <div style="text-align: left;">
                        <span style="background: #9b59b6; color: #fff; font-weight: 800; font-size: 0.7rem; padding: 2px 8px; border-radius: 20px; text-transform: uppercase;">
                            &#127881; Celebrity
                        </span>
                        <strong style="color: #fff; font-size: 0.9rem; margin-left: 5px;">Saurabh Raj Jain Invite</strong>
                    </div>
                    <button type="button" onclick="playWithSound('v2-player')" id="btn-sound-v2" style="background: rgba(245,183,34,0.2); border: 1.5px solid var(--gold-accent); color: var(--gold-accent); font-weight: 800; font-size: 0.78rem; padding: 4px 12px; border-radius: 20px; cursor: pointer; transition: all 0.2s ease;">
                        &#128266; Sound ON
                    </button>
                </div>
                <video id="v2-player" controls autoplay muted loop playsinline preload="auto" style="width: 100%; display: block; aspect-ratio: 16/9; max-height: 250px; object-fit: cover; background: #000; flex: 1;">
                    <source src="<?= BASE_URL ?>/assets/uploads/Video2.mp4" type="video/mp4">
                    Your browser does not support HTML5 video.
                </video>
                <div style="padding: 8px 12px; background: rgba(20,2,11,0.95); text-align: center; font-size: 0.8rem; color: var(--gold-light); font-weight: 600;">
                    &#127775; Saurabh Raj Jain Inviting You
                </div>
            </div>

        </div>

        <!-- Immediate Action CTAs Under Top Video Section -->
        <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; margin-top: 20px;">
            <a href="<?= BASE_URL ?>/booking.php?event_id=<?= $event['id'] ?? 1 ?>" class="btn btn-primary" style="font-size: 1.12rem; padding: 14px 34px; box-shadow: 0 6px 20px rgba(245,183,34,0.4); font-weight: 800;">
                &#127915; Book Passes Now (From ₹199)
            </a>
            <a href="tel:7004665804" class="btn btn-secondary" style="font-size: 1rem; padding: 14px 24px; font-weight: 700;">
                &#128222; Helpline: +91 7004665804
            </a>
        </div>

    </div>
</section>

<!-- Main Container Content -->
<div class="container" style="padding-top: 35px; padding-bottom: 60px;">

    <!-- 2. OFFICIAL POSTER GRAPHIC & EVENT DETAILS -->
    <div class="festive-card" style="margin-bottom: 35px; padding: 25px 22px;">
        <div style="text-align: center; margin-bottom: 20px;">
            <span class="hero-badge" style="display:inline-block; margin-bottom: 6px;">Millennium India Group Presents</span>
            <h1 style="font-size: 2.2rem; font-weight: 900; color: #fff; margin: 4px 0 8px;">
                <?= e($event['title'] ?? 'Jashn-E-Garba 2026') ?>
            </h1>
            <p style="color: var(--gold-light); font-size: 1.05rem; font-weight: 700; margin: 0;">
                Dance &bull; Celebrate &bull; Feel the Vibes!
            </p>
        </div>

        <!-- Official Poster Graphic Showcase -->
        <div style="max-width: 820px; margin: 0 auto 24px; border-radius: 14px; overflow: hidden; border: 2px solid var(--gold-accent); box-shadow: 0 10px 30px rgba(0,0,0,0.7);">
            <img src="<?= asset_v('assets/uploads/jashn-poster-new.jpg') ?>" 
                 alt="Jashn-E-Garba 2026 Official Poster" 
                 style="width: 100%; display: block; object-fit: contain; max-height: 540px; margin: 0 auto; background: #000;">
        </div>

        <!-- Event Key Meta Highlights -->
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



    <!-- 4. DIRECT UPI PAYMENT CARD (No QR Code Image, Clean UPI Bar) -->
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
                    &#127915; Book Passes (From ₹199)
                </a>
            </div>
        </div>
    </div>

    <!-- 5. SELECT YOUR ENTRY CATEGORY (Single Stacked Vertical Cards Matching Client Screenshot) -->
    <div style="text-align: center; margin-bottom: 25px;">
        <span style="color: var(--gold-accent); font-weight: 800; text-transform: uppercase; font-size: 0.82rem; letter-spacing: 1px;">Official Poster Pricing &bull; Instant Passes</span>
        <h2 style="font-size: 2.1rem; font-weight: 900; color: #fff; margin-top: 4px;">Select Your Entry Category</h2>
        <p style="color: var(--text-muted); max-width: 600px; margin: 0 auto; font-size: 0.92rem;">
            Secure your digital pass now. Official pricing as shown on poster!
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

<!-- Interactive Video Sound & Copy UPI Scripts -->
<script>
function playWithSound(playerId) {
    const list = [
        { id: 'top-v0-player', btn: 'btn-sound-v0' },
        { id: 'v1-player', btn: 'btn-sound-v1' },
        { id: 'v2-player', btn: 'btn-sound-v2' }
    ];
    
    list.forEach(function(item) {
        const v = document.getElementById(item.id);
        const b = document.getElementById(item.btn);
        if (v) {
            if (item.id === playerId) {
                v.muted = false;
                v.volume = 1.0;
                v.play().catch(function(e) { console.log('Play triggered:', e); });
                if (b) {
                    b.innerHTML = '&#128264; Playing Sound';
                    b.style.background = 'var(--gold-accent)';
                    b.style.color = '#1f0411';
                }
            } else {
                v.muted = true; // Keep playing visually, mute audio to avoid clashing
                if (b) {
                    b.innerHTML = '&#128266; Sound ON';
                    b.style.background = 'rgba(245,183,34,0.2)';
                    b.style.color = 'var(--gold-accent)';
                }
            }
        }
    });
}

// Ensure all 3 videos run continuously on page load
document.addEventListener('DOMContentLoaded', function() {
    const videoIds = ['top-v0-player', 'v1-player', 'v2-player'];
    videoIds.forEach(function(id) {
        const video = document.getElementById(id);
        if (video) {
            video.muted = true;
            video.play().catch(function(e) {
                console.log('Autoplay notice for', id, e);
            });
        }
    });
});

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
