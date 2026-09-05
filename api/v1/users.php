<?php
// /api/v1/users.php
require_once '../config/db.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        // Fetch all user messages for the iOS app to display
        $stmt = $db->prepare("SELECT id, name, email, message, created_at FROM user_inputs ORDER BY created_at DESC");
        $stmt->execute();
        
        // PDO::FETCH_ASSOC is default, so this returns a clean associative array
        $users = $stmt->fetchAll();
        
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "count" => count($users),
            "data" => $users
        ]);
        break;

    case 'POST':
        // Decode JSON payload from iOS URLSession
        $data = json_decode(file_get_contents("php://input"));
        
        if(!empty($data->name) && !empty($data->email) && !empty($data->message)) {
            $stmt = $db->prepare("INSERT INTO user_inputs (name, email, message) VALUES (:name, :email, :message)");
            
            // Clean inputs
            $name = htmlspecialchars(strip_tags($data->name));
            $email = htmlspecialchars(strip_tags($data->email));
            $message = htmlspecialchars(strip_tags($data->message));
            
            // Bind parameters
            $stmt->bindParam(":name", $name);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":message", $message);
            
            if($stmt->execute()) {
                http_response_code(201); // Created
                echo json_encode(["status" => "success", "message" => "Transmission recorded securely."]);
            } else {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Database execution failed."]);
            }
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(["status" => "error", "message" => "Incomplete payload. Name, email, and message are required."]);
        }
        break;

    default:
        http_response_code(405); // Method Not Allowed
        echo json_encode(["status" => "error", "message" => "Method not allowed. Use GET or POST."]);
        break;
}
?>

