<?php
require_once __DIR__ . '/api/auth/require_admin.php';
requireAdmin();
require_once __DIR__ . '/config/config.php';
$host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
$port = defined('DB_PORT') ? DB_PORT : (getenv('DB_PORT') ?: '8889');
$user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'server_app');
$pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: '');

try {
    $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass);
    echo "Success connecting with configured user {$user} on port {$port}\n";
} catch (Exception $e) {
    echo "Connection failed for user {$user} on port {$port}: " . $e->getMessage() . "\n";
}
