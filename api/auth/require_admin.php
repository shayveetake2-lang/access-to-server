<?php
// api/auth/require_admin.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAdmin() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    $token = '';
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = trim($matches[1]);
    }
    if (empty($token) && !empty($_REQUEST['token'])) {
        $token = trim($_REQUEST['token']);
    }
    
    $sessionToken = $_SESSION['auth_token'] ?? null;
    $adminLogged = !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

    if (!empty($token) && (empty($sessionToken) || empty($_SESSION['role']))) {
        try {
            require_once __DIR__ . '/../../config/config.php';
            $dbConn = function_exists('getDBConnection') ? getDBConnection() : null;
            if ($dbConn) {
                $stmt = $dbConn->prepare("SELECT id, username, role FROM sys_users WHERE auth_token = :t LIMIT 1");
                $stmt->execute([':t' => $token]);
                if ($u = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $_SESSION['auth_token'] = $token;
                    $_SESSION['user_id'] = $u['id'];
                    $_SESSION['username'] = $u['username'];
                    $_SESSION['role'] = $u['role'];
                    $_SESSION['admin_logged_in'] = ($u['role'] === 'admin');
                    $sessionToken = $token;
                    $adminLogged = ($u['role'] === 'admin');
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
