<?php
// api/auth/require_auth.php — Ensures user is authenticated (standard user or admin)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAuth() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    $token = '';
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
    } elseif (!empty($_GET['token'])) {
        $token = $_GET['token'];
    } elseif (!empty($_POST['token'])) {
        $token = $_POST['token'];
    }
    
    $sessionToken = $_SESSION['auth_token'] ?? null;
    $adminLogged = !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    $validToken = (!empty($token) && $token === $sessionToken) || (!empty($sessionToken)) || $adminLogged;

    if (!$validToken) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Authentication required. Logged-out users cannot perform this action.']);
        exit;
    }
}
