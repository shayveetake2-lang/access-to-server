<?php
// insert.php
require_once 'api/config/init.php';

// MAMP Hardcoded Credentials
$host = '127.0.0.1';
$port = '8889';
$dbname = 'access_db';
$username = 'root';
$password = 'root';

try {
    // Data Source Name for PDO
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    // Create a PDO instance
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Check if the form was submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle both standard POST data and JSON payloads
        $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
        if ($contentType === "application/json") {
            $content = trim(file_get_contents("php://input"));
            $decoded = json_decode($content, true);
            $name = isset($decoded['name']) ? htmlspecialchars(strip_tags($decoded['name'])) : '';
            $email = isset($decoded['email']) ? filter_var($decoded['email'], FILTER_SANITIZE_EMAIL) : '';
            $message = isset($decoded['message']) ? htmlspecialchars(strip_tags($decoded['message'])) : '';
        } else {
            // Sanitize and retrieve POST data
            $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
        }

        if (empty($name) || empty($email) || empty($message)) {
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
            ':email' => $email,
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

