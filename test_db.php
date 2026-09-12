<?php
require_once __DIR__ . '/api/auth/require_admin.php';
requireAdmin();
$host = '127.0.0.1';
$port = '8889';

$users = [
    ['root', 'root'],
    ['root', ''],
    ['server_app', 'SuperSecureDBP@ss2026!'],
];

foreach ($users as $u) {
    try {
        $pdo = new PDO("mysql:host=$host;port=$port", $u[0], $u[1]);
        echo "Success with {$u[0]} / {$u[1]}\n";
        break;
    } catch (Exception $e) {
        echo "Failed with {$u[0]} / {$u[1]}: " . $e->getMessage() . "\n";
    }
}
