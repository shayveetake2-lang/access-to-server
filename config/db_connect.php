<?php
/**
 * db_connect.php
 * Central database connection file for access_db using PDO
 */
require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit;
}
