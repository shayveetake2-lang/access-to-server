<?php
require_once __DIR__ . '/../auth/require_admin.php';
requireAdmin();

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db_connect.php';

try {
    $stmt = $pdo->query("SELECT m.id, m.media_title, m.media_type, m.status, m.request_date, u.username FROM media_requests m JOIN sys_users u ON m.user_id = u.id ORDER BY m.request_date DESC");
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'requests' => $requests]);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch requests: ' . $e->getMessage()]);
}
