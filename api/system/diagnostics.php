<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Admin access required.']);
    exit;
}

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../helpers/DiagnosticHelper.php';

try {
    $helper = new DiagnosticHelper($pdo);
    $data = $helper->getDiagnostics();
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error']);
}
