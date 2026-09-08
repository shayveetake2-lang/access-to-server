<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAuth() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    $token = '';
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
    }
    
    $sessionToken = $_SESSION['auth_token'] ?? null;
    $validToken = (!empty($token) && $token === $sessionToken) || (!empty($sessionToken));

    if (!$validToken) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or missing authentication token.']);
        exit;
    }
}
