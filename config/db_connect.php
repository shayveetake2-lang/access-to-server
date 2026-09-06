<?php
// /config/db_connect.php

$host    = '127.0.0.1';
$port    = '3307';          // SSH tunnel → MAMP MySQL on Pro
$dbname  = 'access_db';
$user    = 'root';          // MAMP default
$pass    = 'root';          // MAMP default
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    // Re-throw so callers (e.g. auto_debug.php) can catch gracefully
    throw new \RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
}
?>

