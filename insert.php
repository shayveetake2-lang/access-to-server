<?php
// insert.php — Handles Access Request form submissions
require_once __DIR__ . '/config/db_connect.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        exit;
    }

    // Parse JSON or form POST body
    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
    if (strpos($contentType, "application/json") !== false) {
        $decoded = json_decode(file_get_contents("php://input"), true);
        $decoded = $decoded ? $decoded : [];
        $name    = isset($decoded['name'])    ? trim(strip_tags($decoded['name']))    : '';
        $contact = isset($decoded['contact']) ? trim(strip_tags($decoded['contact'])) : '';
        $message = isset($decoded['message']) ? trim(strip_tags($decoded['message'])) : '';
    } else {
        $name    = isset($_POST['name'])    ? trim(strip_tags($_POST['name']))    : '';
        $contact = isset($_POST['contact']) ? trim(strip_tags($_POST['contact'])) : '';
        $message = isset($_POST['message']) ? trim(strip_tags($_POST['message'])) : '';
    }

    if (empty($name) || empty($contact) || empty($message)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "All fields are required."]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO user_inputs (name, email, message) VALUES (:name, :email, :message)");
    $stmt->execute([
        ':name'    => $name,
        ':email'   => $contact,
        ':message' => $message
    ]);

    http_response_code(200);
    echo json_encode([
        "status"  => "success",
        "message" => "Transmission Received",
        "name"    => $name
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
}
?>
