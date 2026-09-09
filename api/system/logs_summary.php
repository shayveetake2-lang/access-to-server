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

$logFile = __DIR__ . '/../../error_log';
if (!file_exists($logFile)) {
    echo json_encode(['status' => 'success', 'logs' => []]);
    exit;
}

$lines = array_slice(file($logFile), -50);
$filtered = array_map(function($line) {
    $line = preg_replace('/(password|token|key|secret)=[^&\s]+/', '$1=***', $line);
    return substr($line, 0, 300);
}, $lines);

echo json_encode(['status' => 'success', 'logs' => $filtered]);
