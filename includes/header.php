<?php
// includes/header.php - Global public header with Anti-Stale Asset Versioning
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/functions.php';

$pageTitle = $pageTitle ?? (get_setting('site_name', 'Millennium India Group') . ' | Jashn-E-Garba 2026');
$currentNav = $currentNav ?? 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="Official ticket booking portal for Jashn-E-Garba 2026 by Millennium India Group. Patna's premier Dandiya & Garba Mahotsav at Unique Garden on 16 October 2026.">
    <!-- Immediate Cache-Busting Headers for Live Consistency -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <!-- Dynamic Asset Versioning (Anti-Stale Browser Caching) -->
    <link rel="stylesheet" href="<?= asset_v('assets/css/style.css') ?>">
    <?php if (isset($extraCss)): ?>
        <link rel="stylesheet" href="<?= asset_v('assets/css/' . $extraCss) ?>">
    <?php endif; ?>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="<?= BASE_URL ?>/index.php" class="brand-logo">
                <span>&#10024; Millennium</span>
                <span style="color:#fff; font-size:1.1rem; font-weight:600;">India Group</span>
                <span class="brand-badge">2026</span>
            </a>

            <button class="mobile-menu-toggle" onclick="document.querySelector('.nav-links').classList.toggle('show');" aria-label="Toggle navigation">&#9776;</button>

            <ul class="nav-links">
                <li><a href="<?= BASE_URL ?>/index.php" class="<?= $currentNav === 'home' ? 'active' : '' ?>">Home</a></li>
                <li><a href="<?= BASE_URL ?>/events.php" class="<?= $currentNav === 'events' ? 'active' : '' ?>">Events</a></li>
                <li><a href="<?= BASE_URL ?>/event-detail.php?slug=jashn-e-garba-2026" class="<?= $currentNav === 'garba' ? 'active' : '' ?>">Jashn-E-Garba</a></li>
                <li><a href="<?= BASE_URL ?>/contact.php" class="<?= $currentNav === 'contact' ? 'active' : '' ?>">Contact</a></li>
                <li><a href="<?= BASE_URL ?>/ticket.php" class="<?= $currentNav === 'find_ticket' ? 'active' : '' ?>">&#128269; Search Pass</a></li>
                <li><a href="<?= BASE_URL ?>/booking.php?event_id=1" class="nav-btn">&#127915; Book Passes</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top: 20px;">
        <?= render_flash() ?>
    </div>
