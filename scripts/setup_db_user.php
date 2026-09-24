<?php
// scripts/setup_db_user.php — Automated Provisioning for Dedicated MySQL User
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Access denied. Run via command line only.\n");
}

echo "=== Provisioning Dedicated Low-Privilege MySQL User ===\n";

$hosts = ['127.0.0.1', 'localhost'];
$port = 8889;
$rootUser = 'root';
$rootPass = 'root';

$pdo = null;
foreach ($hosts as $host) {
    try {
        $pdo = new PDO("mysql:host={$host};port={$port}", $rootUser, $rootPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2
        ]);
        echo "[+] Connected to MySQL root via {$host}:{$port}\n";
        break;
    } catch (Exception $e) {}
}

if (!$pdo) {
    echo "[-] Warning: Could not connect to MySQL as root:root on port 8889. Ensure MAMP MySQL is running.\n";
    exit(1);
}

$sqlFile = __DIR__ . '/setup_dedicated_db_user.sql';
if (!file_exists($sqlFile)) {
    echo "[-] Error: setup_dedicated_db_user.sql not found.\n";
    exit(1);
}

$queries = file_get_contents($sqlFile);
try {
    $pdo->exec($queries);
    echo "[+] Successfully provisioned user 'server_app' with restricted privileges!\n";
} catch (Exception $e) {
    echo "[-] Error executing SQL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== Provisioning Complete ===\n";
