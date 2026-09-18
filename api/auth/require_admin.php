<?php
// api/auth/require_admin.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAdmin() {
    // Explicitly reject tokens in query parameters to prevent token leakage and CSRF bypass
    if (!empty($_GET['token'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Tokens in query parameters are strictly forbidden. Pass tokens in Authorization header.']);
        exit;
    }

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    $token = '';
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = trim($matches[1]);
    }
    if (empty($token) && !empty($_POST['token'])) {
        $token = trim($_POST['token']);
    }
    
    $sessionToken = $_SESSION['auth_token'] ?? null;
    $adminLogged = !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

    if (!empty($token)) {
        try {
            require_once __DIR__ . '/../../config/config.php';
            $dbConn = function_exists('getDBConnection') ? getDBConnection() : null;
            if ($dbConn) {
                $tokenHash = hash('sha256', $token);
                $stmt = $dbConn->prepare("SELECT id, username, role, token_expires_at FROM sys_users WHERE (token_hash = :h OR auth_token = :h OR auth_token = :t) LIMIT 1");
                $stmt->execute([':h' => $tokenHash, ':t' => $token]);
                if ($u = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $isExpired = !empty($u['token_expires_at']) && (strtotime($u['token_expires_at']) < time());
                    if ($isExpired) {
                        $_SESSION = [];
                        http_response_code(401);
                        echo json_encode(['status' => 'error', 'message' => 'Authentication token has expired. Please log in again.']);
                        exit;
                    }
                    $_SESSION['auth_token'] = $token;
                    $_SESSION['user_id'] = $u['id'];
                    $_SESSION['username'] = $u['username'];
                    $_SESSION['role'] = $u['role'];
                    $_SESSION['admin_logged_in'] = ($u['role'] === 'admin');
                    $sessionToken = $token;
                    $adminLogged = ($u['role'] === 'admin');
                } else {
                    http_response_code(401);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid authentication token.']);
                    exit;
                }
            }
        } catch (\Exception $e) {}
    }
    
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'GET') {
        if (empty($token) || $token !== $sessionToken) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'CSRF validation failed or token missing.']);
            exit;
        }
    } else {
        $validToken = (!empty($token) && $token === $sessionToken) || (!empty($sessionToken)) || $adminLogged;
        if (!$validToken) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Invalid or missing authentication token.']);
            exit;
        }
    }

    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? ($adminLogged ? 'admin' : null);
    if ($role !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied. Admin role required.']);
        exit;
    }
}
