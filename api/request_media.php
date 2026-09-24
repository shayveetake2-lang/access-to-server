<?php
require_once __DIR__ . '/config/init.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 1. Verify JWT (Requires standard user access)
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Missing authorization token']);
    exit;
}

$parts = explode('.', $token);
if (count($parts) !== 3) {
    http_response_code(401);
    echo json_encode(['error' => 'Malformed token']);
    exit;
}
[$header, $payload, $signature] = $parts;
$jwt_secret = getenv('JWT_SECRET') ?: 'default-secret-key-change-me';
$expected_signature = base64_encode(hash_hmac('sha256', "$header.$payload", $jwt_secret, true));

// simple base64 signature compare (in real life you'd use hash_equals and handle base64 padding)
if (rtrim($signature, '=') !== rtrim($expected_signature, '=')) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid token signature']);
    exit;
}

$decoded_payload = json_decode(base64_decode($payload), true);
if (!$decoded_payload || ($decoded_payload['exp'] < time())) {
    http_response_code(401);
    echo json_encode(['error' => 'Token expired']);
    exit;
}
$username = $decoded_payload['username'] ?? 'unknown';

// 2. Setup DB connection
require_once __DIR__ . '/../config/db_connect.php';
$db = $pdo;

// Ensure table exists
$db->exec("CREATE TABLE IF NOT EXISTS media_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    request_type VARCHAR(50) NOT NULL DEFAULT 'song',
    title VARCHAR(255) NOT NULL,
    artist VARCHAR(255),
    notes TEXT,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$data = json_decode(file_get_contents('php://input'), true);
$title = trim($data['title'] ?? '');
$artist = trim($data['artist'] ?? '');
$notes = trim($data['notes'] ?? '');

if (empty($title)) {
    http_response_code(400);
    echo json_encode(['error' => 'Title is required']);
    exit;
}

$stmt = $db->prepare("INSERT INTO media_requests (username, request_type, title, artist, notes) VALUES (:user, 'song', :title, :artist, :notes)");
$stmt->execute([
    ':user' => $username,
    ':title' => $title,
    ':artist' => $artist,
    ':notes' => $notes
]);

echo json_encode(['status' => 'success', 'message' => 'Request submitted successfully']);

