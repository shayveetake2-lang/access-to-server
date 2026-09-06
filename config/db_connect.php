<?php
// /config/db_connect.php
// Centralized PDO database connector with environment auto-detection

$host    = '127.0.0.1';
$dbname  = 'access_db';
$user    = 'root';
$pass    = 'root';
$charset = 'utf8mb4';

// Environment auto-detection:
// - Native MAMP on the 2011 MacBook Pro runs MySQL on port 8889.
// - Remote development from the M1 Mac uses SSH tunnel on port 3307.
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

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    $sockPath = '/Applications/MAMP/tmp/mysql/mysql.sock';
    if (file_exists($sockPath)) {
        try {
            $pdo = new PDO("mysql:unix_socket=$sockPath;dbname=$dbname;charset=$charset", $user, $pass, $options);
        } catch (\PDOException $sockErr) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    } else {
        error_log("Database Connection Error: " . $e->getMessage());
        throw new \RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
    }
}
?>
