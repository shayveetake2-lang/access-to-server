<?php
// api/media/get_requests.php — Admin-only listing of pending/fulfilled media
// requests, readable by both the ServerFlow dashboard and the Aether admin portal.
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/_request_auth.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$body = json_decode(file_get_contents('php://input'), true) ?: [];

if (!resolveMediaAdmin($pdo, $body)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Administrator privileges required.']);
    exit;
}

try {
    $stmt = $pdo->query(
        "SELECT m.id, m.track_title, m.artist_name, m.notes, m.media_type, m.status, m.created_at,
                COALESCE(NULLIF(m.requester_name, ''), u.username, 'Unknown User') AS username
         FROM media_requests m
         LEFT JOIN sys_users u ON m.user_id = u.id
         ORDER BY m.created_at DESC"
    );
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'requests' => $requests]);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch requests: ' . $e->getMessage()]);
}
