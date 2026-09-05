<?php
// /api/v1/status.php
require_once '../config/db.php';

$database = new Database();
$db = $database->getConnection();

if($db) {
    http_response_code(200);
    echo json_encode([
        "status" => "success", 
        "message" => "ZeroTier Mesh Server is online. Database connected.",
        "timestamp" => time()
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Server online, but database connection failed."
    ]);
}
?>

