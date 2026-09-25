<?php
// api.php — Swift / iOS Native App Backend Endpoint
// Uses centralized PDO credentials from config/config.php (never hardcoded root/root)

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config/db_connect.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    switch ($method) {
        case 'GET':
            // Display recent recorded transmissions for the iOS client
            $stmt = $pdo->query("SELECT id, name, email, message, created_at FROM user_inputs ORDER BY created_at DESC LIMIT 50");
            $records = $stmt->fetchAll();
            echo json_encode([
                'status' => 'success',
                'count'  => count($records),
                'data'   => $records
            ]);
            break;

        case 'POST':
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            if (!$data && !empty($_POST)) {
                $data = $_POST;
            }

            $name = trim($data['name'] ?? '');
            $contact = trim($data['email'] ?? $data['contact'] ?? '');
            $message = trim($data['message'] ?? '');

            if (empty($name) || empty($contact) || empty($message)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Name, email, and message are required.']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO user_inputs (name, email, message) VALUES (:name, :email, :message)");
            $stmt->execute([
                ':name'    => $name,
                ':email'   => $contact,
                ':message' => $message
            ]);

            http_response_code(201);
            echo json_encode([
                'status'  => 'success',
                'message' => 'Transmission recorded securely.'
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
