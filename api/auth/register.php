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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Check if username exists
    $stmt = $pdo->prepare("SELECT id FROM sys_users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['status' => 'error', 'message' => 'Username already exists. Please choose another.']);
        exit;
    }

    // Insert new user
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $insert = $pdo->prepare("INSERT INTO sys_users (username, password_hash, role) VALUES (:username, :hash, 'user')");
    $insert->execute([':username' => $username, ':hash' => $hash]);
    $newUserId = $pdo->lastInsertId();

    // Auto-authenticate newly registered user
    $token = bin2hex(random_bytes(32));
    $_SESSION['auth_token'] = $token;
    $_SESSION['user_id'] = $newUserId;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = 'user';

    echo json_encode([
        'status'  => 'success',
        'token'   => $token,
        'role'    => 'user',
        'username'=> $username,
        'message' => 'Account created successfully! Logging you in...'
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    exit;
}
?>