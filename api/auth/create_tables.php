<?php
// api/auth/create_tables.php
// This script creates the MySQL database and users table for the authentication system.

$host = '127.0.0.1';
$port = '3307';
$user = 'root';
$pass = 'root';

// New DSN – uses the SSH tunnel on the Air
$dsn = "mysql:host=$host;port=$port;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Create database if it doesn't exist
$pdo->exec("CREATE DATABASE IF NOT EXISTS access_control CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
$pdo->exec("USE access_control;");

// Create users table
$pdo->exec("CREATE TABLE IF NOT EXISTS users (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    username VARCHAR(50) UNIQUE NOT NULL,\n    password_hash VARCHAR(255) NOT NULL,\n    is_admin TINYINT(1) DEFAULT 0,\n    reset_token VARCHAR(64) DEFAULT NULL,\n    reset_expires DATETIME DEFAULT NULL,\n    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n) ENGINE=InnoDB;");

// Insert default admin account (admin / 123456789) if not exists
$defaultAdminUser = 'admin';
$defaultAdminPass = '123456789';
$hash = password_hash($defaultAdminPass, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password_hash, is_admin) VALUES (:u, :p, 1)");
$stmt->execute([':u' => $defaultAdminUser, ':p' => $hash]);

echo "Database and default admin account created (if not already present).\n";
?>

