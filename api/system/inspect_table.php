<?php
// api/system/inspect_table.php — Safely fetch recent rows from a database table
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../auth/require_admin.php';
requireAdmin();

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
    
    $allowedTables = ['sys_users', 'admin_users', 'sys_deploy_logs', 'web_contact_forms', 'ios_app_users', 'ios_app_sessions', 'user_inputs', 'media_requests'];
    if (!in_array($tableName, $allowedTables, true)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Inspection not permitted for this table.']);
        exit;
    }

    $stmt = $pdo->query("SELECT * FROM `{$tableName}` ORDER BY 1 DESC LIMIT 25");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Redact sensitive password hashes and active session tokens
    foreach ($rows as &$row) {
        if (isset($row['password_hash'])) {
            $row['password_hash'] = '•••••••••••••••• (Encrypted Hash)';
        }
        if (isset($row['auth_token'])) {
            $row['auth_token'] = '•••••••••••••••• (Active Session Token)';
        }
        if (isset($row['token_hash'])) {
            $row['token_hash'] = '•••••••••••••••• (Hashed Token)';
        }
        if (isset($row['device_token'])) {
            $row['device_token'] = '•••••••••••••••• (Device Token)';
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

