<?php
// /config/config.php — Secure Centralized Database & Environment Configuration

// Database Credentials (Override defaults using environment variables if set)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'access_db');
define('DB_USER', getenv('DB_USER') ?: 'server_app');
define('DB_PASS', getenv('DB_PASS') ?: 'SuperSecureDBP@ss2026!');
define('DB_CHARSET', 'utf8mb4');
define('DB_SQLITE_PATH', __DIR__ . '/../access_db.sqlite');

/**
 * SQLite PDO extension wrapper to ensure compatibility with MySQL DDL statements
 */
if (!class_exists('SQLitePDO')) {
    class SQLitePDO extends PDO {
        private function cleanSql($sql) {
            $sql = preg_replace("/ENGINE\s*=\s*\w+/i", "", $sql);
            $sql = preg_replace("/DEFAULT\s+CHARSET\s*=\s*\w+/i", "", $sql);
            $sql = preg_replace("/COLLATE\s*=\s*[\w_]+/i", "", $sql);
            $sql = str_replace("INT AUTO_INCREMENT PRIMARY KEY", "INTEGER PRIMARY KEY AUTOINCREMENT", $sql);
            $sql = str_replace("NOW()", "CURRENT_TIMESTAMP", $sql);
            return $sql;
        }

        public function exec($statement) {
            $trimmed = trim($statement);
            if (preg_match("/^\s*(CREATE DATABASE|USE)\b/i", $trimmed)) {
                return 0;
            }
            return parent::exec($this->cleanSql($statement));
        }

        public function prepare($query, $options = array()) {
            return parent::prepare($this->cleanSql($query), $options);
        }

        public function query($query, $fetchMode = null, ...$args) {
            $trimmed = trim($query);
            if (preg_match("/^\s*(CREATE DATABASE|USE)\b/i", $trimmed)) {
                return parent::query("SELECT 1");
            }
            return parent::query($this->cleanSql($query), $fetchMode, ...$args);
        }
    }
}

/**
 * Initialize SQLite database tables and default accounts
 */
function initSQLiteSchema(PDO $pdo) {
    static $initialized = false;
    if ($initialized) return;

    $tables = [
        "CREATE TABLE IF NOT EXISTS sys_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            last_login DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS sys_deploy_logs (
            log_id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_name VARCHAR(100) NOT NULL,
            github_url VARCHAR(255) NOT NULL DEFAULT '',
            deploy_status VARCHAR(50) NOT NULL DEFAULT 'pending',
            deploy_message TEXT,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS web_contact_forms (
            submission_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL DEFAULT '',
            contact_info VARCHAR(255) NOT NULL DEFAULT '',
            project_name VARCHAR(100) NOT NULL DEFAULT '',
            visitor_name VARCHAR(100) NOT NULL DEFAULT '',
            visitor_email VARCHAR(150) NOT NULL DEFAULT '',
            message TEXT NOT NULL,
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS ios_app_users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            app_name VARCHAR(100) NOT NULL DEFAULT '',
            device_id VARCHAR(100) DEFAULT '',
            username VARCHAR(50),
            password_hash VARCHAR(255) DEFAULT '',
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS ios_app_sessions (
            session_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            device_token VARCHAR(255) NOT NULL,
            last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS user_inputs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    ];

    foreach ($tables as $sql) {
        try {
            $pdo->exec($sql);
        } catch (\Exception $e) {}
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM sys_users WHERE username = 'admin'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $hash = password_hash('123456789', PASSWORD_BCRYPT);
            $pdo->exec("INSERT INTO sys_users (username, password_hash, role) VALUES ('admin', '$hash', 'admin')");
        }

        $stmt = $pdo->prepare("SELECT id FROM sys_users WHERE username = 'user'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $hash = password_hash('password', PASSWORD_BCRYPT);
            $pdo->exec("INSERT INTO sys_users (username, password_hash, role) VALUES ('user', '$hash', 'user')");
        }

        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = 'admin'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $hash = password_hash('123456789', PASSWORD_BCRYPT);
            $pdo->exec("INSERT INTO admin_users (username, password_hash) VALUES ('admin', '$hash')");
        }
    } catch (\Exception $e) {}

    $initialized = true;
}

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

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $hostsToCheck = array_unique([
        getenv('DB_HOST') ?: '127.0.0.1',
        '127.0.0.1',
        '10.247.192.231'
    ]);

    $portsToCheck = array_unique([
        getenv('DB_PORT') ?: '8889',
        '8889',
        '3307',
        '3306'
    ]);

    foreach ($hostsToCheck as $testHost) {
        foreach ($portsToCheck as $testPort) {
            $sock = @fsockopen($testHost, (int)$testPort, $errno, $errstr, 0.15);
            if ($sock) {
                fclose($sock);
                $dsn = "mysql:host=" . $testHost . ";port=" . $testPort . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                try {
                    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                    return $pdo;
                } catch (\PDOException $e) {
                    // Host permissions restricted or credentials invalid; fallthrough to next/fallback
                }
            }
        }
    }

    $sockPath = '/Applications/MAMP/tmp/mysql/mysql.sock';
    if (file_exists($sockPath)) {
        try {
            $pdo = new PDO("mysql:unix_socket=$sockPath;dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, $options);
            return $pdo;
        } catch (\PDOException $sockErr) {
            // Fallthrough to SQLite
        }
    }

    // SQLite Fallback
    try {
        $pdo = new SQLitePDO("sqlite:" . DB_SQLITE_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        initSQLiteSchema($pdo);
        return $pdo;
    } catch (\Exception $sqe) {
        error_log("Database Connection Error: " . $sqe->getMessage());
        throw new \RuntimeException("Database Connection Failed: " . $sqe->getMessage(), 0, $sqe);
    }
}
?>