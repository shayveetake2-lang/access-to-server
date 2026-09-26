<?php
// /config/config.php — Secure Centralized Database & Environment Configuration

if (!function_exists('getEnvValue')) {
    function getEnvValue($key, $default = '') {
        $val = getenv($key);
        if ($val !== false && $val !== '') return $val;
        static $envCache = null;
        if ($envCache === null) {
            $envCache = [];
            $envFile = dirname(__DIR__) . '/.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, '#') === 0) continue;
                    if (strpos($line, '=') !== false) {
                        list($name, $value) = explode('=', $line, 2);
                        $envCache[trim($name)] = trim($value, " \"'");
                    }
                }
            }
        }
        return $envCache[$key] ?? $default;
    }
}

// Database Credentials (Override defaults using environment variables or .env if set)
define('DB_HOST', getEnvValue('DB_HOST', '127.0.0.1'));
define('DB_PORT', getEnvValue('DB_PORT', '8889'));
define('DB_NAME', getEnvValue('DB_NAME', 'access_db'));
define('DB_USER', getEnvValue('DB_USER', 'server_app'));
define('DB_PASS', getEnvValue('DB_PASS', 'ServerAppSecurePass2026!'));
define('DB_CHARSET', 'utf8mb4');
define('DB_SQLITE_PATH', getEnvValue('DB_SQLITE_PATH', (dirname(__DIR__) . '/storage/access_db.sqlite')));

// Ampache Music Database Configuration
define('AMPACHE_DB_HOST', getEnvValue('AMPACHE_DB_HOST', DB_HOST));
define('AMPACHE_DB_PORT', getEnvValue('AMPACHE_DB_PORT', DB_PORT));
define('AMPACHE_DB_NAME', getEnvValue('AMPACHE_DB_NAME', 'ampache'));
define('AMPACHE_DB_USER', getEnvValue('AMPACHE_DB_USER', 'server_app'));
define('AMPACHE_DB_PASS', getEnvValue('AMPACHE_DB_PASS', 'password'));

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

        #[\ReturnTypeWillChange]
        public function exec($statement) {
            $trimmed = trim($statement);
            if (preg_match("/^\s*(CREATE DATABASE|USE)\b/i", $trimmed)) {
                return 0;
            }
            return parent::exec($this->cleanSql($statement));
        }

        #[\ReturnTypeWillChange]
        public function prepare($query, $options = array()) {
            return parent::prepare($this->cleanSql($query), $options);
        }

        #[\ReturnTypeWillChange]
        public function query($query, $fetchMode = null, ...$args) {
            $trimmed = trim($query);
            $clean = $this->cleanSql($query);
            if (preg_match("/^\s*(CREATE DATABASE|USE)\b/i", $trimmed)) {
                $clean = "SELECT 1";
            }
            if ($fetchMode === null) {
                return parent::query($clean);
            }
            return parent::query($clean, $fetchMode, ...$args);
        }
    }
}

/**
 * Initialize SQLite database tables and default accounts
 */
function initSQLiteSchema(PDO $pdo) {
    static $initialized = false;
    if ($initialized) return;

    // Apply hardware-efficient WAL journal mode and reduced disk sync for 2011 MacBook
    try {
        $pdo->exec("PRAGMA journal_mode = WAL;");
        $pdo->exec("PRAGMA synchronous = NORMAL;");
    } catch (\Exception $e) {}

    $tables = [
        "CREATE TABLE IF NOT EXISTS sys_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(255) DEFAULT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) DEFAULT 'user',
            storage_limit_mb INTEGER DEFAULT 100,
            storage_used_mb FLOAT DEFAULT 0.0,
            auth_token VARCHAR(64) DEFAULT NULL,
            token_hash VARCHAR(64) DEFAULT NULL,
            token_expires_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            last_login DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS media_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            media_title VARCHAR(255) NOT NULL,
            media_type VARCHAR(50) NOT NULL,
            status VARCHAR(50) DEFAULT 'Pending',
            request_date DATETIME DEFAULT CURRENT_TIMESTAMP
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

    // Auto-migrate storage columns and token expiration if missing
    try {
        @$pdo->exec("ALTER TABLE sys_users ADD COLUMN email VARCHAR(255) DEFAULT NULL");
        @$pdo->exec("UPDATE sys_users SET email = username || '@local.server' WHERE email IS NULL OR email = ''");
    } catch (\Exception $e) {}
    try {
        @$pdo->exec("ALTER TABLE sys_users ADD COLUMN storage_limit_mb INTEGER DEFAULT 100");
    } catch (\Exception $e) {}
    try {
        @$pdo->exec("ALTER TABLE sys_users ADD COLUMN storage_used_mb FLOAT DEFAULT 0.0");
    } catch (\Exception $e) {}
    try {
    try {
        @$pdo->exec("ALTER TABLE sys_users ADD COLUMN email VARCHAR(255) DEFAULT NULL");
    } catch (\Exception $e) {}

        @$pdo->exec("ALTER TABLE sys_users ADD COLUMN auth_token VARCHAR(64) DEFAULT NULL");
    } catch (\Exception $e) {}
    try {
        @$pdo->exec("ALTER TABLE sys_users ADD COLUMN token_hash VARCHAR(64) DEFAULT NULL");
    } catch (\Exception $e) {}
    try {
        @$pdo->exec("ALTER TABLE sys_users ADD COLUMN token_expires_at DATETIME DEFAULT NULL");
    } catch (\Exception $e) {}

    // Indexes for fast O(1) lookups on 2011 hardware
    try {
        @$pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_token_hash ON sys_users(token_hash)");
        @$pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_username ON sys_users(username)");
        @$pdo->exec("CREATE INDEX IF NOT EXISTS idx_media_requests_user ON media_requests(user_id)");
    } catch (\Exception $e) {}

    // media_requests migration: unify ServerFlow (movie/TV) and Aether (song) requests
    // on shared track_title/artist_name/notes/created_at columns.
    // NOTE: this server's PHP (7.4.33) ships SQLite 3.19.3, which predates
    // ALTER TABLE ... RENAME COLUMN (added in SQLite 3.25.0) — that statement
    // fails with a syntax error here, so we ADD new columns and backfill from
    // the legacy media_title/request_date columns instead of renaming them.
    try { @$pdo->exec("ALTER TABLE media_requests ADD COLUMN track_title VARCHAR(255) DEFAULT ''"); } catch (\Exception $e) {}
    try { @$pdo->exec("ALTER TABLE media_requests ADD COLUMN created_at DATETIME DEFAULT NULL"); } catch (\Exception $e) {}
    try { @$pdo->exec("UPDATE media_requests SET track_title = media_title WHERE track_title IS NULL OR track_title = ''"); } catch (\Exception $e) {}
    try { @$pdo->exec("UPDATE media_requests SET created_at = request_date WHERE created_at IS NULL"); } catch (\Exception $e) {}
    try { @$pdo->exec("ALTER TABLE media_requests ADD COLUMN artist_name VARCHAR(255) DEFAULT ''"); } catch (\Exception $e) {}
    try { @$pdo->exec("ALTER TABLE media_requests ADD COLUMN notes TEXT DEFAULT ''"); } catch (\Exception $e) {}
    // Aether/Ampache-only requesters have no sys_users row (user_id stored as 0),
    // so the display name must be captured at submit time or it's lost forever.
    try { @$pdo->exec("ALTER TABLE media_requests ADD COLUMN requester_name VARCHAR(255) DEFAULT ''"); } catch (\Exception $e) {}

    // Initial admin user provisioning without hardcoded backdoors
    try {
        $initialPass = getenv('ADMIN_INITIAL_PASSWORD');
        $initialHash = getenv('ADMIN_INITIAL_HASH');
        if (!empty($initialPass)) {
            $hash = password_hash($initialPass, PASSWORD_BCRYPT, ['cost' => 10]);
        } elseif (!empty($initialHash)) {
            $hash = $initialHash;
        } else {
            $secretFile = dirname(__DIR__) . '/storage/.admin_initial_password';
            if (file_exists($secretFile)) {
                $genPass = trim(file_get_contents($secretFile));
            } else {
                $genPass = bin2hex(random_bytes(8));
                @file_put_contents($secretFile, $genPass);
                @chmod($secretFile, 0600);
            }
            $hash = password_hash($genPass, PASSWORD_BCRYPT, ['cost' => 10]);
        }

        $stmt = $pdo->prepare("SELECT id FROM sys_users WHERE username = 'admin'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $pdo->exec("INSERT INTO sys_users (username, password_hash, role, storage_limit_mb) VALUES ('admin', " . $pdo->quote($hash) . ", 'admin', 100)");
        }

        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = 'admin'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $pdo->exec("INSERT INTO admin_users (username, password_hash) VALUES ('admin', " . $pdo->quote($hash) . ")");
        }
    } catch (\Exception $e) {}

    $initialized = true;
}

/**
 * Storage Calculator Helper:
 * Calculates total size of a directory in Megabytes (MB).
 *
 * @param string $dirPath Target directory path
 * @return float Total size in MB (rounded to 2 decimal places)
 */
function getDirectorySizeMB(string $dirPath): float {
    if (!is_dir($dirPath) || !is_readable($dirPath)) {
        return 0.0;
    }
    $totalBytes = 0;
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $totalBytes += $file->getSize();
            }
        }
    } catch (\Exception $e) {
        return 0.0;
    }
    return round($totalBytes / (1024 * 1024), 2);
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
        'localhost'
    ]);

    $portsToCheck = array_unique([
        getenv('DB_PORT') ?: '8889',
        '8889',
        '3307',
        '3306'
    ]);

    $credPairs = [
        [DB_USER, DB_PASS],
        ['root', 'root'],
        ['root', '']
    ];

    foreach ($hostsToCheck as $testHost) {
        foreach ($portsToCheck as $testPort) {
            $sock = @fsockopen($testHost, (int)$testPort, $errno, $errstr, 0.15);
            if ($sock) {
                fclose($sock);
                $dsn = "mysql:host=" . $testHost . ";port=" . $testPort . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                foreach ($credPairs as [$u, $p]) {
                    try {
                        $pdo = new PDO($dsn, $u, $p, $options);
                        return $pdo;
                    } catch (\PDOException $e) {
                        // Try next credential combination
                    }
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
        $pdo->query("PRAGMA user_version");
        initSQLiteSchema($pdo);
        return $pdo;
    } catch (\Exception $sqe) {
        // Handle SMB network mount file-locking constraint on macOS
        try {
            $pdo = new SQLitePDO("sqlite:file:" . DB_SQLITE_PATH . "?vfs=unix-none");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->query("PRAGMA user_version");
            initSQLiteSchema($pdo);
            return $pdo;
        } catch (\Exception $smbErr) {
            try {
                $tmpPath = '/tmp/access_db.sqlite';
                $pdo = new SQLitePDO("sqlite:" . $tmpPath);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                initSQLiteSchema($pdo);
                return $pdo;
            } catch (\Exception $sqe2) {
                error_log("Database Connection Error: " . $sqe2->getMessage());
                throw new \RuntimeException("Database Connection Failed: " . $sqe2->getMessage(), 0, $sqe2);
            }
        }
    }
}
?>