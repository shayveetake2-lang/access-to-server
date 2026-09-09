<?php
// api/system/inspect_table.php — Safely fetch recent rows from a database table
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../auth/require_auth.php';
requireAuth();

require_once __DIR__ . '/../../config/config.php';

$tableName = isset($_GET['table']) ? trim($_GET['table']) : '';
$tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);

if (empty($tableName)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Table name is required.']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Validate table exists
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $checkStmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = :name");
        $checkStmt->execute([':name' => $tableName]);
        if (!$checkStmt->fetch()) {
            throw new Exception("Table does not exist.");
        }
    }

    $stmt = $pdo->query("SELECT * FROM `{$tableName}` ORDER BY 1 DESC LIMIT 25");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Redact sensitive password hashes
    foreach ($rows as &$row) {
        if (isset($row['password_hash'])) {
            $row['password_hash'] = '•••••••••••••••• (Encrypted Hash)';
        }
    }

    echo json_encode([
        'status' => 'success',
        'table' => $tableName,
        'count' => count($rows),
        'rows' => $rows
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error reading table: ' . $e->getMessage()
    ]);
}

