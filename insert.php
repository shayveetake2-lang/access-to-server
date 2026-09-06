<?php
// insert.php
require_once 'api/config/init.php';
require_once __DIR__ . '/config/db_connect.php';

try {
    // Check if the form was submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle both standard POST data and JSON payloads
        $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
        if ($contentType === "application/json") {
            $content = trim(file_get_contents("php://input"));
            $decoded = json_decode($content, true);
            $name = isset($decoded['name']) ? htmlspecialchars(strip_tags($decoded['name'])) : '';
            $contact = isset($decoded['contact']) ? htmlspecialchars(strip_tags($decoded['contact'])) : '';
            $message = isset($decoded['message']) ? htmlspecialchars(strip_tags($decoded['message'])) : '';
        } else {
            // Sanitize and retrieve POST data
            $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
            $contact = filter_input(INPUT_POST, 'contact', FILTER_SANITIZE_STRING);
            $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
        }

        if (empty($name) || empty($contact) || empty($message)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "All fields are required."]);
            exit;
        }

        // Prepare the SQL statement
        $sql = "INSERT INTO user_inputs (name, email, message) VALUES (:name, :email, :message)";
        $stmt = $pdo->prepare($sql);

        // Execute the statement
        $stmt->execute([
            ':name' => $name,
            ':email' => $contact,
            ':message' => $message
        ]);

        // Success feedback
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Transmission Received",
            "name" => $name
        ]);

    } else {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        exit;
    }

} catch (PDOException $e) {
    // Error handling
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . $e->getMessage()]);
}
?>

