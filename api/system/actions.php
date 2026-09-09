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

require_once __DIR__ . '/../helpers/ServerActionHelper.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$actionId = $data['action_id'] ?? null;
$params = $data['params'] ?? [];

if (!$actionId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing action_id']);
    exit;
}

$result = ServerActionHelper::executeAction($actionId, $params);

echo json_encode($result);
