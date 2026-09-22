<?php
// api/media/update_request_status.php — Admin-only status toggle for a media
// request (e.g. 'Fulfilled', 'Dismissed'), used by <MusicRequestsManager />.
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/_request_auth.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];

if (!resolveMediaAdmin($pdo, $body)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Administrator privileges required.']);
    exit;
}

$id = isset($body['id']) ? (int)$body['id'] : 0;
$status = trim($body['status'] ?? '');
$allowed = ['Pending', 'Approved', 'Fulfilled', 'Dismissed'];

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'A valid request id is required.']);
    exit;
}
if (!in_array($status, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'status must be one of: ' . implode(', ', $allowed)]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE media_requests SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $status, ':id' => $id]);
    if ($stmt->rowCount() === 0) {
        // Either the id doesn't exist, or it was already set to this exact status —
        // confirm the row is actually there before reporting success either way.
        $check = $pdo->prepare("SELECT id FROM media_requests WHERE id = :id LIMIT 1");
        $check->execute([':id' => $id]);
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => "Request #{$id} not found."]);
            exit;
        }
    }
    echo json_encode(['status' => 'success', 'message' => "Request #{$id} marked as {$status}.", 'rowsAffected' => $stmt->rowCount()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to update request: ' . $e->getMessage()]);
}
