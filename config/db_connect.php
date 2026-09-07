<?php
// /config/db_connect.php
// Centralized PDO database connector with environment auto-detection
require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    $host = DB_HOST;
    $dbname = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;
} catch (Exception $e) {
    error_log("Database Connection Failure: " . $e->getMessage());
    throw $e;
}
?>
