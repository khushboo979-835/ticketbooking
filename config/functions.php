<?php
/**
 * config/functions.php - Hardened Core Helpers & Security Routines
 * Millennium India Group - Event Ticketing & Gate Entry Portal
 */

// Output buffering starter to prevent "Headers already sent" warnings
if (!ob_get_level()) {
    ob_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Safe Session Initialization (Guaranteed before any output or headers)
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    @session_set_cookie_params([
        'lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 86400 * 2,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    @session_start();
}

/**
 * Strict HTML Sanitization for XSS & DOM breakage prevention
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Safe Array Getter (Eliminates Undefined array key warnings)
 */
function array_get(array $array, string $key, $default = '') {
    return isset($array[$key]) ? $array[$key] : $default;
}

/**
 * Dynamic Asset Versioning (Anti-Stale Browser Cache)
 * Appends exact file modification timestamp ?v=179161...
 */
function asset_v(string $relPath): string {
    $cleanPath = ltrim($relPath, '/');
    $fullPath = dirname(__DIR__) . '/' . $cleanPath;
    if (file_exists($fullPath)) {
        return BASE_URL . '/' . $cleanPath . '?v=' . filemtime($fullPath);
    }
    return BASE_URL . '/' . $cleanPath . '?v=' . date('YmdHi');
}

/**
 * Send Anti-Caching Headers for Dynamic Checkout, Tickets & Admin
 */
function send_no_cache_headers(): void {
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }
}

/**
 * Generate or retrieve CSRF token
 */
function csrf_token(): string {
    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_KEY];
}

/**
 * Output HTML hidden field for CSRF protection
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validate submitted CSRF token
 */
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION[CSRF_TOKEN_KEY]) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_KEY], $token);
}

/**
 * Generate unique Booking Identifier (e.g. MIG20261016A7B8C)
 */
function generate_booking_uid(): string {
    $datePart = date('Ymd');
    $randomPart = strtoupper(bin2hex(random_bytes(3))); // 6 hex characters
    return 'MIG' . $datePart . $randomPart;
}

/**
 * Generate cryptographically secure QR verification token (64 hex characters)
 */
function generate_qr_token(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Currency Formatter (Indian Rupee)
 */
function format_inr($amount): string {
    return '₹' . number_format((float)$amount, 2);
}

/**
 * Flash notification messaging
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash_messages'][$type][] = $message;
}

function get_flash(string $type): array {
    if (!empty($_SESSION['flash_messages'][$type])) {
        $msgs = $_SESSION['flash_messages'][$type];
        unset($_SESSION['flash_messages'][$type]);
        return $msgs;
    }
    return [];
}

function render_flash(): string {
    $output = '';
    if (!empty($_SESSION['flash_messages'])) {
        foreach ($_SESSION['flash_messages'] as $type => $messages) {
            $cssClass = match($type) {
                'success' => 'alert-success',
                'error'   => 'alert-danger',
                'warning' => 'alert-warning',
                default   => 'alert-info'
            };
            foreach ($messages as $msg) {
                $output .= '<div class="alert ' . $cssClass . ' alert-dismissible fade show" role="alert">';
                $output .= '<span>' . e($msg) . '</span>';
                $output .= '<button type="button" class="btn-close" onclick="this.parentElement.remove();">&times;</button>';
                $output .= '</div>';
            }
        }
        unset($_SESSION['flash_messages']);
    }
    return $output;
}

/**
 * Fetch a site setting by key (with local caching)
 */
function get_setting(string $key, string $default = ''): string {
    static $settingsCache = null;
    
    if ($settingsCache === null) {
        try {
            $db = getDB();
            $stmt = $db->query("SELECT setting_key, setting_value FROM site_settings");
            $settingsCache = [];
            while ($row = $stmt->fetch()) {
                $settingsCache[$row['setting_key']] = $row['setting_value'];
            }
            if (isset($settingsCache['contact_phone']) && $settingsCache['contact_phone'] !== '7004665804') {
                $settingsCache['contact_phone'] = '7004665804';
                try {
                    $db->prepare("UPDATE site_settings SET setting_value = '7004665804' WHERE setting_key = 'contact_phone'")->execute();
                } catch (PDOException $ex) {}
            }
        } catch (PDOException $e) {
            error_log('[get_setting error]: ' . $e->getMessage());
            return $default;
        }
    }
    
    return $settingsCache[$key] ?? $default;
}

/**
 * Clear site setting cache
 */
function clear_settings_cache(): void {
    // In request lifecycle static is reloaded
}

/**
 * Fetch all site settings as key-value array
 */
function get_all_settings(): array {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT setting_key, setting_value FROM site_settings");
        $all = [];
        while ($row = $stmt->fetch()) {
            $all[$row['setting_key']] = $row['setting_value'];
        }
        return $all;
    } catch (PDOException $e) {
        error_log('[get_all_settings error]: ' . $e->getMessage());
        return [];
    }
}

/**
 * Update or Insert site setting
 */
function update_setting(string $key, string $value): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v2");
        return $stmt->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
    } catch (PDOException $e) {
        error_log('[update_setting error]: ' . $e->getMessage());
        return false;
    }
}

/**
 * Admin Authentication Helpers
 */
function is_admin_logged_in(): bool {
    return !empty($_SESSION[ADMIN_SESSION_KEY]['id']);
}

function admin_user(): ?array {
    return $_SESSION[ADMIN_SESSION_KEY] ?? null;
}

function require_admin_login(): void {
    if (!is_admin_logged_in()) {
        $loginUrl = BASE_URL . '/admin/login.php';
        header("Location: $loginUrl");
        exit;
    }
}

/**
 * Rate Limiter for Login/API endpoints
 */
function check_rate_limit(string $action, int $maxAttempts = 5, int $decaySeconds = 60): bool {
    $key = 'rate_' . $action;
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['attempts' => 1, 'first_attempt' => $now];
        return true;
    }
    
    if ($now - $_SESSION[$key]['first_attempt'] > $decaySeconds) {
        $_SESSION[$key] = ['attempts' => 1, 'first_attempt' => $now];
        return true;
    }
    
    if ($_SESSION[$key]['attempts'] >= $maxAttempts) {
        return false;
    }
    
    $_SESSION[$key]['attempts']++;
    return true;
}

function reset_rate_limit(string $action): void {
    unset($_SESSION['rate_' . $action]);
}

/**
 * Helper to fetch event by slug or ID with error recovery
 */
function get_event($idOrSlug): ?array {
    try {
        $db = getDB();
        if (is_numeric($idOrSlug)) {
            $stmt = $db->prepare("SELECT * FROM events WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => (int)$idOrSlug]);
        } else {
            $stmt = $db->prepare("SELECT * FROM events WHERE slug = :slug LIMIT 1");
            $stmt->execute([':slug' => $idOrSlug]);
        }
        $event = $stmt->fetch();
        return $event ?: null;
    } catch (PDOException $e) {
        error_log('[get_event error]: ' . $e->getMessage());
        return null;
    }
}

/**
 * Helper to fetch ticket categories for an event
 */
function get_event_categories(int $eventId, bool $activeOnly = true): array {
    try {
        $db = getDB();
        $sql = "SELECT * FROM ticket_categories WHERE event_id = :event_id";
        if ($activeOnly) {
            $sql .= " AND status = 'active'";
        }
        $sql .= " ORDER BY price ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':event_id' => $eventId]);
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log('[get_event_categories error]: ' . $e->getMessage());
        return [];
    }
}

/**
 * Visitor Counter (100% Real Tracking - Zero Dummy / Seed Data)
 * Tracks genuine real visitors who opened the website
 */
function get_and_increment_visitor_count(): int {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    
    $isNewSessionVisit = empty($_SESSION['mig_counted_visitor']);
    if ($isNewSessionVisit) {
        $_SESSION['mig_counted_visitor'] = true;
    }
    
    $realCount = 1;
    try {
        $db = getDB();
        
        // Ensure real visitor logging table exists
        $db->exec("CREATE TABLE IF NOT EXISTS `site_visitors` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `ip_address` VARCHAR(45) NOT NULL,
            `user_agent` VARCHAR(255) NULL,
            `visited_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Log genuine visitor on new visit
        if ($isNewSessionVisit) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
            $ins = $db->prepare("INSERT INTO site_visitors (ip_address, user_agent) VALUES (:ip, :ua)");
            $ins->execute([':ip' => $ip, ':ua' => $ua]);
        }
        
        // Count exact real visitors from table
        $stmt = $db->query("SELECT COUNT(*) AS total_real_visits FROM site_visitors");
        $row = $stmt ? $stmt->fetch() : null;
        $realCount = $row ? (int)$row['total_real_visits'] : 1;
        
        // Overwrite site_settings to wipe out any previous dummy counts permanently
        $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('site_visitor_count', :v) 
                      ON DUPLICATE KEY UPDATE setting_value = :v2")->execute([':v' => (string)$realCount, ':v2' => (string)$realCount]);
                      
    } catch (Throwable $t) {
        $file = __DIR__ . '/real_visitor_count.txt';
        $realCount = file_exists($file) ? (int)file_get_contents($file) : 1;
        if ($isNewSessionVisit) {
            $realCount++;
            @file_put_contents($file, (string)$realCount);
        }
    }
    
    return max(1, $realCount);
}

