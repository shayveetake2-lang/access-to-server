<?php
// api/auth/require_admin.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAdmin() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    $token = '';
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
    } elseif (!empty($_GET['token'])) {
        $token = $_GET['token'];
    }
    
    // Fallback to checking session if no Bearer token provided
    $sessionToken = $_SESSION['auth_token'] ?? null;
    $validToken = (!empty($token) && $token === $sessionToken) || (!empty($sessionToken));

    if (!$validToken) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or missing authentication token.']);
        exit;
    }

    if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied. Admin role required.']);
        exit;
    }
}
