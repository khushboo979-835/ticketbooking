<?php
// booking-step2.php - Backward-compatible redirect to unified booking.php
require_once __DIR__ . '/config/config.php';
$eventId = (int)($_GET['event_id'] ?? 1);
$url = BASE_URL . "/booking.php?event_id={$eventId}&step=2";
header("Location: $url");
exit;
