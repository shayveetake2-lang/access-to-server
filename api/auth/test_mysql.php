<?php
// api/auth/test_mysql.php
// Simple script to test MySQL connection using PDO.
// It tries the MAMP socket, then /tmp/mysql.sock, then TCP host/port.

$host = '127.0.0.1';
$port = '3307';
$user = 'root';
$pass = 'root';
$dsn = '';

if (file_exists('/Applications/MAMP/tmp/mysql/mysql.sock')) {
    $dsn = "mysql:unix_socket=/Applications/MAMP/tmp/mysql/mysql.sock;charset=utf8mb4";
} elseif (file_exists('/tmp/mysql.sock')) {
    $dsn = "mysql:unix_socket=/tmp/mysql.sock;charset=utf8mb4";
} else {
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
}

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "✅ Connection successful using DSN: $dsn\n";
    // Show current MySQL version
    $stmt = $pdo->query('SELECT VERSION()');
    $version = $stmt->fetchColumn();
    echo "MySQL version: $version\n";
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    echo "DSN used: $dsn\n";
}
?>

