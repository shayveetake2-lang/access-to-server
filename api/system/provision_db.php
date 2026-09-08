<?php require_once __DIR__ . "/../auth/require_auth.php"; requireAuth(); ?>
<?php
// provision_db.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';

$dbName = isset($_POST['db_name']) ? trim($_POST['db_name']) : '';

// Sanitize DB name (only alphanumeric and underscores allowed)
$dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);

if (empty($dbName)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid database name provided."]);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Create Database if it does not exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    echo json_encode(["status" => "success", "message" => "Database '$dbName' provisioned and ready."]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "DB Error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
}
?>

