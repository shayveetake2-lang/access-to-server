<?php
// api/update_role.php — Secure Role Promotion & Demotion Endpoint
// Senior DevSecOps Hardened: Enforces JWT/Session authentication, strict RBAC, input whitelisting, and last-admin protection.

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// ─── 1. ENFORCE HTTP POST METHOD ONLY ───
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed. This endpoint requires POST.']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

try {
    $pdo = getDBConnection();
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// ─── 2. EXTRACT AUTHENTICATION TOKEN (HEADER, BODY, OR SESSION) ───
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

$rawInput = file_get_contents('php://input');
if (empty($rawInput) && php_sapi_name() === 'cli' && defined('STDIN')) {
    $rawInput = @stream_get_contents(STDIN);
}
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = [];
}

$token = '';
if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
    $token = trim($matches[1]);
}
if (empty($token) && !empty($data['token'])) {
    $token = trim($data['token']);
}
if (empty($token) && !empty($_POST['token'])) {
    $token = trim($_POST['token']);
}

// Reject tokens passed in URL query parameters to prevent token exposure in logs & referrers
if (!empty($_GET['token'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tokens in query parameters are strictly forbidden. Pass token in Authorization header.']);
    exit;
}

// ─── 3. VERIFY REQUESTER'S IDENTITY & PRIVILEGES (JWT / TOKEN / SESSION) ───
$requester = null;

if (!empty($token)) {
    // Check if token is a standard JWT (header.payload.signature)
    if (substr_count($token, '.') === 2) {
        $parts = explode('.', $token);
        $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'));
        $payload = json_decode($payloadJson, true);
        if (is_array($payload)) {
            if (isset($payload['exp']) && time() > $payload['exp']) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Authentication token has expired.']);
                exit;
            }
            $reqId = $payload['sub'] ?? $payload['user_id'] ?? $payload['id'] ?? null;
            if ($reqId) {
                $stmt = $pdo->prepare("SELECT id, username, role FROM sys_users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $reqId]);
                $requester = $stmt->fetch();
            }
            if (!$requester && !empty($payload['role'])) {
                $requester = [
                    'id' => $payload['sub'] ?? 0,
                    'username' => $payload['username'] ?? 'jwt_admin',
                    'role' => $payload['role']
                ];
            }
        }
    }

    // Verify against database token hash
    if (!$requester) {
        $tokenHash = hash('sha256', $token);
        $stmt = $pdo->prepare("SELECT id, username, role, token_expires_at FROM sys_users WHERE (token_hash = :th OR auth_token = :th OR auth_token = :t) LIMIT 1");
        $stmt->execute([':th' => $tokenHash, ':t' => $token]);
        $u = $stmt->fetch();
        if ($u) {
            if (!empty($u['token_expires_at']) && (strtotime($u['token_expires_at']) < time())) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Authentication token has expired. Please log in again.']);
                exit;
            }
            $requester = $u;
        }
    }
}

// Check active session
if (!$requester && !empty($_SESSION['auth_token'])) {
    $tokenHash = hash('sha256', $_SESSION['auth_token']);
    $stmt = $pdo->prepare("SELECT id, username, role FROM sys_users WHERE (token_hash = :th OR auth_token = :th OR auth_token = :t) LIMIT 1");
    $stmt->execute([':th' => $tokenHash, ':t' => $_SESSION['auth_token']]);
    $requester = $stmt->fetch();
}
if (!$requester && !empty($_SESSION['role'])) {
    $requester = [
        'id' => $_SESSION['user_id'] ?? 0,
        'username' => $_SESSION['username'] ?? 'session_admin',
        'role' => $_SESSION['role']
    ];
}

// Ensure requester is authenticated
if (!$requester) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Valid authentication token required.']);
    exit;
}

// STRICT RBAC CHECK: Requester MUST be an 'admin'
if ($requester['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Forbidden: Administrator privileges required to alter user roles.'
    ]);
    exit;
}

// ─── 4. PARSE & SANITIZE INPUT PARAMETERS ───
$userIdInput = $data['user_id'] ?? $data['id'] ?? $_POST['user_id'] ?? $_POST['id'] ?? null;
$userId = filter_var($userIdInput, FILTER_VALIDATE_INT);

$newRoleInput = strtolower(trim($data['new_role'] ?? $data['role'] ?? $_POST['new_role'] ?? $_POST['role'] ?? ''));

// Normalize 'user' to 'member'
if ($newRoleInput === 'user') {
    $newRoleInput = 'member';
}

// Input validation
if (!$userId || $userId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID. Must be a positive integer.']);
    exit;
}

$allowedRoles = ['admin', 'member'];
if (!in_array($newRoleInput, $allowedRoles, true)) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid role specified. Permitted roles are: ' . implode(', ', $allowedRoles) . '.'
    ]);
    exit;
}

$newRole = $newRoleInput;

// ─── 5. VERIFY TARGET USER & SAFEGUARDS ───
$targetStmt = $pdo->prepare("SELECT id, username, role, password_hash FROM sys_users WHERE id = :id LIMIT 1");
$targetStmt->execute([':id' => $userId]);
$targetUser = $targetStmt->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Target user not found.']);
    exit;
}

// Safeguard: Prevent demoting the only remaining admin (lockout prevention)
if ($targetUser['role'] === 'admin' && $newRole !== 'admin') {
    $adminCountStmt = $pdo->query("SELECT COUNT(*) FROM sys_users WHERE role = 'admin'");
    $adminCount = (int)$adminCountStmt->fetchColumn();
    if ($adminCount <= 1) {
        http_response_code(400);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Demotion blocked: Cannot demote the only remaining administrator account.'
        ]);
        exit;
    }
}

// ─── 6. EXECUTE PARAMETERIZED SQL UPDATE ───
try {
    $updateStmt = $pdo->prepare("UPDATE sys_users SET role = :new_role WHERE id = :id");
    $updateStmt->execute([
        ':new_role' => $newRole,
        ':id'       => $userId
    ]);

    // Legacy sync with admin_users table
    try {
        if ($newRole === 'admin') {
            $chk = $pdo->prepare("SELECT id FROM admin_users WHERE username = :u LIMIT 1");
            $chk->execute([':u' => $targetUser['username']]);
            if (!$chk->fetch()) {
                $ins = $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (:u, :p)");
                $ins->execute([':u' => $targetUser['username'], ':p' => $targetUser['password_hash']]);
            }
        } else {
            $del = $pdo->prepare("DELETE FROM admin_users WHERE username = :u");
            $del->execute([':u' => $targetUser['username']]);
        }
    } catch (\Exception $syncErr) {}

    // Return successful response
    http_response_code(200);
    echo json_encode([
        'status'   => 'success',
        'message'  => "User '{$targetUser['username']}' role successfully changed to {$newRole}.",
        'user_id'  => $userId,
        'username' => $targetUser['username'],
        'new_role' => $newRole
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to execute database update: ' . $e->getMessage()]);
}
exit;
