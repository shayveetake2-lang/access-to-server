<?php
// api/auth/login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db_connect.php';

// Ensure sys_users exists for unified login
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sys_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Create default admin and standard user if they don't exist
    $stmt = $pdo->prepare("SELECT id FROM sys_users WHERE username = 'admin'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $hash = password_hash('123456789', PASSWORD_BCRYPT);
        $pdo->exec("INSERT INTO sys_users (username, password_hash, role) VALUES ('admin', '$hash', 'admin')");
    }

    $stmt = $pdo->prepare("SELECT id FROM sys_users WHERE username = 'user'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $hash = password_hash('password', PASSWORD_BCRYPT);
        $pdo->exec("INSERT INTO sys_users (username, password_hash, role) VALUES ('user', '$hash', 'user')");
    }
} catch (Exception $e) {
    // Ignore schema errors here
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: [];

$username = trim($data['username'] ?? $_POST['username'] ?? '');
$password = trim($data['password'] ?? $_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Username and password are required.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM sys_users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $token = bin2hex(random_bytes(32));
        
        $_SESSION['auth_token'] = $token;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role']; 

        echo json_encode([
            'status' => 'success',
            'token'  => $token,
            'role'   => $user['role'],
            'message'=> 'Authentication successful.'
        ]);
        exit;
    } else {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid username or password.']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    exit;
}
