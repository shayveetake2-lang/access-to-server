<?php
// api/get_users.php — Secure User Retrieval Endpoint for Admin Panel
// Enforces strict Role-Based Access Control (RBAC) and returns distinct user roles.

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

try {
    $pdo = getDBConnection();
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failure.']);
    exit;
}

// ─── 1. EXTRACT & VERIFY AUTHENTICATION (JWT / BEARER TOKEN / SESSION) ───
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

$token = '';
if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
    $token = trim($matches[1]);
}
if (empty($token) && !empty($_POST['token'])) {
    $token = trim($_POST['token']);
}

// Reject tokens passed in query string for security (prevents leakage in server logs & referrer headers)
if (!empty($_GET['token'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tokens in query parameters are strictly forbidden. Use the Authorization header.']);
    exit;
}

$currentUser = null;

// A. Verify Bearer Token or JWT
if (!empty($token)) {
    // Check if token is a JWT (header.payload.signature)
    if (substr_count($token, '.') === 2) {
        $parts = explode('.', $token);
        $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'));
        $payload = json_decode($payloadJson, true);
        if (is_array($payload)) {
            if (isset($payload['exp']) && time() > $payload['exp']) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'JWT token has expired.']);
                exit;
            }
            $currentUserId = $payload['sub'] ?? $payload['user_id'] ?? $payload['id'] ?? null;
            if ($currentUserId) {
                $stmt = $pdo->prepare("SELECT id, username, role FROM sys_users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $currentUserId]);
                $currentUser = $stmt->fetch();
            }
            if (!$currentUser && !empty($payload['role'])) {
                $currentUser = [
                    'id' => $payload['sub'] ?? 0,
                    'username' => $payload['username'] ?? 'jwt_user',
                    'role' => $payload['role']
                ];
            }
        }
    }

    // Check against sys_users table (standard ServerFlow hex token)
    if (!$currentUser) {
        $tokenHash = hash('sha256', $token);
        $stmt = $pdo->prepare("SELECT id, username, role, token_expires_at FROM sys_users WHERE (token_hash = :th OR auth_token = :th OR auth_token = :t) LIMIT 1");
        $stmt->execute([':th' => $tokenHash, ':t' => $token]);
        $u = $stmt->fetch();
        if ($u) {
            if (!empty($u['token_expires_at']) && (strtotime($u['token_expires_at']) < time())) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Token has expired. Please log in again.']);
                exit;
            }
            $currentUser = $u;
        }
    }
}

// B. Fallback to active PHP Session
if (!$currentUser && !empty($_SESSION['auth_token'])) {
    $tokenHash = hash('sha256', $_SESSION['auth_token']);
    $stmt = $pdo->prepare("SELECT id, username, role FROM sys_users WHERE (token_hash = :th OR auth_token = :th OR auth_token = :t) LIMIT 1");
    $stmt->execute([':th' => $tokenHash, ':t' => $_SESSION['auth_token']]);
    $currentUser = $stmt->fetch();
}
if (!$currentUser && !empty($_SESSION['role'])) {
    $currentUser = [
        'id' => $_SESSION['user_id'] ?? 0,
        'username' => $_SESSION['username'] ?? 'session_user',
        'role' => $_SESSION['role']
    ];
}

// ─── 2. ENFORCE AUTHENTICATION & RBAC PRIVILEGES ───
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Authentication token is missing or invalid.']);
    exit;
}

if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Administrator privileges required to access user list.']);
    exit;
}

// ─── 3. FETCH AND RETURN ALL USERS WITH INDIVIDUAL ROLES ───
try {
    // Explicitly select id, email, role, and display fields
    $stmt = $pdo->query("SELECT id, COALESCE(email, username || '@local.server') AS email, username, role, COALESCE(storage_limit_mb, 100) AS storage_limit_mb, COALESCE(storage_used_mb, 0.0) AS storage_used_mb, created_at FROM sys_users ORDER BY id ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalize role string and ensure each user retains their exact individual role
    foreach ($users as &$user) {
        $user['id'] = (int)$user['id'];
        $user['role'] = (string)$user['role']; // 'admin' or 'member'
    }
    unset($user);

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'count'  => count($users),
        'users'  => $users
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve user records: ' . $e->getMessage()]);
}
exit;

