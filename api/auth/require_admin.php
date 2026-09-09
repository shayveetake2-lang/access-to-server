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
        $token = $matches[1];
    } elseif (!empty($_GET['token'])) {
        $token = $_GET['token'];
    }
    
    // Check token or active admin session
    $sessionToken = $_SESSION['auth_token'] ?? null;
    $adminLogged = !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    $validToken = (!empty($token) && $token === $sessionToken) || (!empty($sessionToken)) || $adminLogged;

    if (!$validToken) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or missing authentication token.']);
        exit;
    }

    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? ($adminLogged ? 'admin' : null);
    if ($role !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied. Admin role required.']);
        exit;
    }
}
