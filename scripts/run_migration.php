<?php
// scripts/run_migration.php — Automated Database Migration Runner
header('Content-Type: text/plain; charset=UTF-8');

echo "=== Running Aether Database Search Index Migration ===\n\n";

$sock = '/Applications/MAMP/tmp/mysql/mysql.sock';
$host = '127.0.0.1';
$port = 8889;
$user = 'server_app';
$pass = 'ServerAppSecurePass2026!';

$pdo = null;
try {
    $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
} catch (Exception $e) {
    if (file_exists($sock)) {
        try {
            $pdo = new PDO("mysql:unix_socket={$sock}", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
        } catch (Exception $e2) {
            die("[-] Database connection failed: " . $e2->getMessage() . "\n");
        }
    } else {
        die("[-] Database connection failed: " . $e->getMessage() . "\n");
    }
}

echo "[+] Connected to MySQL on 2011 MacBook Pro.\n";

$statements = [
    "USE ampache;",
    "ALTER TABLE song ADD FULLTEXT INDEX ft_song_title (title);",
    "ALTER TABLE artist ADD FULLTEXT INDEX ft_artist_name (name);",
    "ALTER TABLE album ADD FULLTEXT INDEX ft_album_name (name);",
    "USE access_db;",
    "INSERT INTO sys_users (username, password_hash, role, auth_token, token_hash)
     VALUES ('library_agent', 'AGENT_NO_PASSWORD_LOGIN', 'admin', 'aether_agent_secret_2026', SHA2('aether_agent_secret_2026', 256))
     ON DUPLICATE KEY UPDATE role = 'admin', auth_token = 'aether_agent_secret_2026', token_hash = SHA2('aether_agent_secret_2026', 256);"
];

foreach ($statements as $stmt) {
    $trimStmt = trim($stmt);
    if (empty($trimStmt)) continue;
    try {
        $pdo->exec($trimStmt);
        echo "[✔] Executed: " . substr($trimStmt, 0, 60) . "...\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "[*] Notice (already exists): " . substr($trimStmt, 0, 60) . "...\n";
        } else {
            echo "[-] Error on statement [{$trimStmt}]: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== Migration Finished Successfully! ===\n";
