<?php
require_once __DIR__ . '/../auth/require_auth.php';
requireAuth();

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db_connect.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: [];

$title = trim($data['title'] ?? '');
$type = trim($data['type'] ?? '');
$userId = $_SESSION['user_id'] ?? null;

if (empty($title) || empty($type) || !$userId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Title and Type are required.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO media_requests (user_id, media_title, media_type) VALUES (:uid, :title, :type)");
    $stmt->execute([
        ':uid' => $userId,
        ':title' => $title,
        ':type' => $type
    ]);
    echo json_encode(['status' => 'success', 'message' => 'Media request submitted successfully!']);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to submit request: ' . $e->getMessage()]);
}
