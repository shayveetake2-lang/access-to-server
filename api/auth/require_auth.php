<?php
// api/auth/require_auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAuth() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    $token = '';
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
    }
    
    $sessionToken = $_SESSION['auth_token'] ?? null;
    $adminLogged = !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    
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
            echo json_encode(['status' => 'error', 'message' => 'Authentication required. Logged-out users cannot perform this action.']);
            exit;
        }
    }
}
