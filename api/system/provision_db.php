<?php
// provision_db.php
header('Content-Type: application/json');

// MAMP MySQL hardcoded credentials
$host = '127.0.0.1';
$port = '3307'; // SSH tunnel → MAMP MySQL on Pro
$username = 'root';
$password = 'root';

$dbName = isset($_POST['db_name']) ? trim($_POST['db_name']) : '';

// Sanitize DB name (only alphanumeric and underscores allowed)
$dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);

if (empty($dbName)) {
    echo json_encode(["status" => "error", "message" => "Invalid database name provided."]);
    exit;
}

try {
    // Connect to MySQL server without specifying a database
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $username, $password);
    
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create Database if it does not exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName`");
    
    echo json_encode(["status" => "success", "message" => "Database '$dbName' provisioned and ready."]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "DB Error: " . $e->getMessage()]);
}
?>

