<?php
// provision_db.php
header('Content-Type: application/json');

// MAMP MySQL hardcoded credentials
$host = '127.0.0.1';

$port = '8889';
$s8889 = @fsockopen('127.0.0.1', 8889, $errno, $errstr, 0.5);
if ($s8889) {
    fclose($s8889);
    $port = '8889';
} else {
    $s3307 = @fsockopen('127.0.0.1', 3307, $errno, $errstr, 0.5);
    if ($s3307) {
        fclose($s3307);
        $port = '3307';
    }
}
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

