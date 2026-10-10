<?php
/**
 * config/db.php - High-Performance PDO Database Connection Singleton
 * Optimized for cPanel / Shared Hosting and High-Concurrency Checkouts
 * Millennium India Group - Jashn-E-Garba 2026
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    /**
     * Private constructor to enforce singleton pattern
     */
    private function __construct() {}

    /**
     * Retrieve static PDO connection instance (reused per request lifecycle)
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_TIMEOUT            => 5
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Log technical trace securely to server log (never expose DB credentials to end-users)
                error_log('[Database Connection Failure]: ' . $e->getMessage());

                // Prevent blank screen with clear, brand-styled recovery screen
                if (!headers_sent()) {
                    http_response_code(503);
                }
                echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Service Temporarily Unavailable</title>' .
                     '<style>body{background:#1f0411;color:#ffd966;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}' .
                     '.box{background:rgba(56,10,31,0.9);padding:30px;border-radius:12px;border:1px solid #f5b722;text-align:center;max-width:500px;}' .
                     'h2{color:#fff;margin-top:0;}p{color:#ddd;line-height:1.6;}</style></head>' .
                     '<body><div class="box"><h2>&#127881; Millennium India Group</h2>' .
                     '<p>The ticketing portal is undergoing high-speed optimization. Please refresh in a few moments or contact our WhatsApp helpline.</p>' .
                     '<button onclick="location.reload()" style="background:#f5b722;border:none;padding:10px 20px;font-weight:bold;border-radius:6px;cursor:pointer;">Refresh Page</button>' .
                     '</div></body></html>';
                exit;
            }
        }

        return self::$instance;
    }

    /**
     * Prevent object cloning
     */
    private function __clone() {}

    /**
     * Prevent unserializing
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Global helper function to get PDO instance
 */
function getDB(): PDO {
    return Database::getConnection();
}
