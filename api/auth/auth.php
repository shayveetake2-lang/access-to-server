<?php
// api/auth/auth.php
// Authentication helper functions for the Access-to-Server project.

session_start(); // Ensure session started for every request that includes this file.

function getPdo() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=127.0.0.1;port=3307;charset=utf8mb4;dbname=access_control";
        try {
            $pdo = new PDO($dsn, 'root', 'root', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $e) {
            http_response_code(503);
            die(json_encode(['status' => 'error', 'message' => 'Auth DB unavailable: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

function login($username, $password) {
    $pdo = getPdo();
    $stmt = $pdo->prepare('SELECT id, password_hash, is_admin FROM users WHERE username = :u');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $username;
        $_SESSION['is_admin'] = (bool)$user['is_admin'];
        return true;
    }
    return false;
}

function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        // Preserve original request URL for redirect after login
        $redirect = urlencode($_SERVER['REQUEST_URI']);
        header('Location: /login.php?redirect=' . $redirect);
        exit;
    }
}

function isAdmin() {
    return !empty($_SESSION['is_admin']);
}

function logout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function generateResetToken($userId) {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 1800); // 30 minutes
    $pdo = getPdo();
    $stmt = $pdo->prepare('UPDATE users SET reset_token = :t, reset_expires = :e WHERE id = :id');
    $stmt->execute([':t' => $token, ':e' => $expires, ':id' => $userId]);
    return $token;
}

function validateResetToken($token) {
    $pdo = getPdo();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE reset_token = :t AND reset_expires > NOW()');
    $stmt->execute([':t' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['id'] : false;
}

function clearResetToken($userId) {
    $pdo = getPdo();
    $stmt = $pdo->prepare('UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE id = :id');
    $stmt->execute([':id' => $userId]);
}
?>

