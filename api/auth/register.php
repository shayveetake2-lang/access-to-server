<?php
// api/auth/register.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db_connect.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: [];

// Strip any incoming role or isAdmin fields to prevent self-promotion
unset($data['role']);
unset($data['isAdmin']);
if (isset($_POST['role'])) unset($_POST['role']);
if (isset($_POST['isAdmin'])) unset($_POST['isAdmin']);

$username = trim($data['username'] ?? $_POST['username'] ?? '');
$password = trim($data['password'] ?? $_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Username and password are required.']);
    exit;
}

// Admin-only registration policy for personal server
$isAdmin = (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true)
        || (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin');

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Public registration is disabled on this server. Contact the administrator to create an account.']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_\-]{3,30}$/', $username)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Username must be between 3 and 30 alphanumeric characters.']);
    exit;
}

if (strlen($password) < 10) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 10 characters.']);
    exit;
}

try {
    // Ensure table exists just in case
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sys_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) DEFAULT 'member',
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
    $hashedToken = hash('sha256', $token);
    $ttlDays = (int)(getenv('AUTH_TOKEN_TTL_DAYS') ?: 7);
    $expiresAt = date('Y-m-d H:i:s', time() + ($ttlDays * 86400));

    // Insert new user with token and hashed token
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $insert = $pdo->prepare("INSERT INTO sys_users (username, password_hash, role, storage_limit_mb, auth_token, token_hash, token_expires_at) VALUES (:username, :hash, 'member', 100, :token, :th, :exp)");
    $insert->execute([':username' => $username, ':hash' => $hash, ':token' => $hashedToken, ':th' => $hashedToken, ':exp' => $expiresAt]);
    $newUserId = $pdo->lastInsertId();
    $_SESSION['auth_token'] = $token;
    $_SESSION['user_id'] = $newUserId;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = 'member';

    echo json_encode([
        'status'   => 'success',
        'token'    => $token,
        'role'     => 'member',
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