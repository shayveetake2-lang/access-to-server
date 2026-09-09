<?php
// api/system/get_databases.php — Retrieve database engine, tables, counts, and health
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../auth/require_auth.php';
requireAuth();

require_once __DIR__ . '/../../config/config.php';

try {
    $pdo = getDBConnection();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    $tables = [];
    $totalRecords = 0;
    $dbSize = '0 B';
    
    if ($driver === 'sqlite') {
        $sqlitePath = defined('DB_SQLITE_PATH') ? DB_SQLITE_PATH : sys_get_temp_dir() . '/access_db.sqlite';
        if (file_exists($sqlitePath)) {
            $bytes = filesize($sqlitePath);
            $units = ['B', 'KB', 'MB', 'GB'];
            $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
            $pow = min($pow, count($units) - 1);
            $dbSize = round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
        }

        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name ASC");
        $tableNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tableNames as $tName) {
            $cStmt = $pdo->query("SELECT COUNT(*) FROM `{$tName}`");
            $count = (int)$cStmt->fetchColumn();
            $totalRecords += $count;

            $colStmt = $pdo->query("PRAGMA table_info(`{$tName}`)");
            $cols = $colStmt->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_map(function($c) { return $c['name']; }, $cols);

            $tables[] = [
                'name' => $tName,
                'rows' => $count,
                'columns' => $columnNames,
                'type' => 'Standard Table'
            ];
        }

        $dbInfo = [
            'engine' => 'SQLite 3 (Server Engine)',
            'database' => 'access_db',
            'host' => 'localhost (embedded)',
            'file_path' => $sqlitePath,
            'size' => $dbSize,
            'driver' => $driver,
            'status' => 'online'
        ];

    } else {
        // MySQL
        $dbName = defined('DB_NAME') ? DB_NAME : 'access_db';
        $dbHost = defined('DB_HOST') ? DB_HOST : '127.0.0.1';

        $sizeStmt = $pdo->query("SELECT SUM(data_length + index_length) as dbsize FROM information_schema.tables WHERE table_schema = " . $pdo->quote($dbName));
        $bytes = (int)$sizeStmt->fetchColumn();
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $dbSize = round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];

        $stmt = $pdo->query("SHOW TABLES");
        $tableNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tableNames as $tName) {
            $cStmt = $pdo->query("SELECT COUNT(*) FROM `{$tName}`");
            $count = (int)$cStmt->fetchColumn();
            $totalRecords += $count;

            $tables[] = [
                'name' => $tName,
                'rows' => $count,
                'type' => 'InnoDB Table'
            ];
        }

        $dbInfo = [
            'engine' => 'MySQL Server',
            'database' => $dbName,
            'host' => $dbHost,
            'size' => $dbSize,
            'driver' => $driver,
            'status' => 'online'
        ];
    }

    echo json_encode([
        'status' => 'success',
        'info' => $dbInfo,
        'tables' => $tables,
        'total_tables' => count($tables),
        'total_records' => $totalRecords
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch database telemetry: ' . $e->getMessage()
    ]);
}

