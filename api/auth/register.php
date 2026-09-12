<?php
// api/auth/register.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db_connect.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: [];

$username = trim($data['username'] ?? $_POST['username'] ?? '');
$password = trim($data['password'] ?? $_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Username and password are required.']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters.']);
    exit;
}

try {
    // Ensure table exists just in case
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sys_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) DEFAULT 'user',
            storage_limit_mb INT DEFAULT 100,
            storage_used_mb FLOAT DEFAULT 0.0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    try { @$pdo->exec("ALTER TABLE sys_users ADD COLUMN storage_limit_mb INT DEFAULT 100"); } catch (\Exception $e) {}
    try { @$pdo->exec("ALTER TABLE sys_users ADD COLUMN storage_used_mb FLOAT DEFAULT 0.0"); } catch (\Exception $e) {}

    // Check if username exists
    $stmt = $pdo->prepare("SELECT id FROM sys_users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['status' => 'error', 'message' => 'Username already exists. Please choose another.']);
        exit;
    }

    // Auto-authenticate newly registered user
    $token = bin2hex(random_bytes(32));

    // Insert new user with token
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $insert = $pdo->prepare("INSERT INTO sys_users (username, password_hash, role, storage_limit_mb, auth_token) VALUES (:username, :hash, 'user', 100, :token)");
    $insert->execute([':username' => $username, ':hash' => $hash, ':token' => $token]);
    $newUserId = $pdo->lastInsertId();
    $_SESSION['auth_token'] = $token;
    $_SESSION['user_id'] = $newUserId;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = 'user';

    echo json_encode([
        'status'   => 'success',
        'token'    => $token,
        'role'     => 'user',
        'username' => $username,
        'storage'  => [
            'limit_mb'       => 100,
            'used_mb'        => 0.0,
            'used_bytes'     => 0,
            'limit_bytes'    => 104857600,
            'percent_used'   => 0,
            'formatted_used' => '0 MB',
            'formatted_limit'=> '100 MB'
        ],
        'message'  => 'Account created successfully! Logging you in...'
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    exit;
}
?>