<?php
// api/media/submit_request.php — Shared endpoint for both ServerFlow's media
// portal (Movies/TV) and the Aether SPA's "Request a Song" feature.
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

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: [];

$requester = resolveMediaRequester($pdo, $data);
if (!$requester) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Please log in to submit a media request.']);
    exit;
}

// Accept both the legacy ServerFlow fields (title/type) and the Aether fields
// (trackTitle/artistName/notes/mediaType) so either caller works unchanged.
$trackTitle = trim($data['trackTitle'] ?? $data['title'] ?? '');
$artistName = trim($data['artistName'] ?? '');
$notes      = trim($data['notes'] ?? '');
$mediaType  = trim($data['mediaType'] ?? $data['type'] ?? 'Song');

if ($trackTitle === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Title is required.']);
    exit;
}

try {
    // media_title/media_type are legacy NOT-NULL columns from before the
    // track_title/created_at migration (see config.php) — mirrored here since
    // this server's SQLite (3.19.3) can't rename them away.
    $stmt = $pdo->prepare(
        "INSERT INTO media_requests (user_id, requester_name, media_title, track_title, artist_name, notes, media_type, created_at, status)
         VALUES (:uid, :requester, :title, :title, :artist, :notes, :type, CURRENT_TIMESTAMP, 'Pending')"
    );
    $stmt->execute([
        ':uid'       => $requester['id'],
        ':requester' => $requester['username'],
        ':title'     => $trackTitle,
        ':artist'    => $artistName,
        ':notes'     => $notes,
        ':type'      => $mediaType,
    ]);
    echo json_encode(['status' => 'success', 'message' => 'Request submitted successfully!', 'id' => (int)$pdo->lastInsertId()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to submit request: ' . $e->getMessage()]);
}
