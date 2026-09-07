<?php
// /config/config.php — Secure Centralized Database & Environment Configuration

// Database Credentials (Override defaults using environment variables if set)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'access_db');
define('DB_USER', getenv('DB_USER') ?: 'server_app');
define('DB_PASS', getenv('DB_PASS') ?: 'SuperSecureDBP@ss2026!');
define('DB_CHARSET', 'utf8mb4');

/**
 * Instantiate and return a shared PDO Database connection
 *
 * @return PDO
 * @throws RuntimeException
 */
function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    // Auto-detect MAMP MySQL port (8889 for local MAMP, 3307 for SSH tunnel, 3306 for standard)
    $port = getenv('DB_PORT') ?: '8889';
    $s8889 = @fsockopen(DB_HOST, 8889, $errno, $errstr, 0.2);
    if ($s8889) {
        fclose($s8889);
        $port = '8889';
    } else {
        $s3307 = @fsockopen(DB_HOST, 3307, $errno, $errstr, 0.2);
        if ($s3307) {
            fclose($s3307);
            $port = '3307';
        }
    }

    $dsn = "mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (\PDOException $e) {
        $sockPath = '/Applications/MAMP/tmp/mysql/mysql.sock';
        if (file_exists($sockPath)) {
            try {
                $pdo = new PDO("mysql:unix_socket=$sockPath;dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, $options);
            } catch (\PDOException $sockErr) {
                error_log("Database Connection Error: " . $sockErr->getMessage());
                throw new \RuntimeException("Database Connection Failed.", 0, $sockErr);
            }
        } else {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new \RuntimeException("Database Connection Failed.", 0, $e);
        }
    }

    return $pdo;
}
?>