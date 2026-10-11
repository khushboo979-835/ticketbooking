<?php
// config/config.php - Global configuration for Millennium India Group ticketing platform

// Set timezone for event scheduling and bookings
date_default_timezone_set('Asia/Kolkata');

// Environment & Base URL configuration
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Millennium India Group');
}
if (!defined('APP_TAGLINE')) {
    define('APP_TAGLINE', 'Jashn-E-Garba 2026 Ticketing Portal');
}

// Auto-detect base URL for seamless portability between localhost and cPanel domains
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
// Trim subdirectories if we are in admin/ or api/
$baseDir = preg_replace('/(\/admin|\/api|\/public)$/', '', $scriptDir);
$baseDir = rtrim($baseDir, '/');
define('BASE_URL', $protocol . $host . $baseDir);

// Database Credentials - Configured for Hostinger (millenniumindiagroup.com)
// Automatically falls back if DB environment variables are set
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'u980414876_eventdb');
define('DB_USER', getenv('DB_USER') ?: 'u980414876_admin');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'U9:h9x6Y#Ph');
define('DB_CHARSET', 'utf8mb4');

// Security & Session Settings
define('SESSION_LIFETIME', 86400 * 2); // 48 hours
define('CSRF_TOKEN_KEY', 'mig_csrf_token');
define('ADMIN_SESSION_KEY', 'mig_admin_session');

// Upload directory path
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
