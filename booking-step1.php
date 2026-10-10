<?php
// booking-step1.php - Backward-compatible redirect to unified booking.php
require_once __DIR__ . '/config/config.php';
$eventId = (int)($_GET['event_id'] ?? 1);
$cat = (int)($_GET['cat'] ?? 0);
$url = BASE_URL . "/booking.php?event_id={$eventId}" . ($cat ? "&cat={$cat}" : "") . "&step=1";
header("Location: $url");
exit;
