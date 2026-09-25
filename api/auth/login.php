<?php
// api/auth/login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db_connect.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: [];

$username = trim($data['username'] ?? $data['email'] ?? $_POST['username'] ?? $_POST['email'] ?? '');
$password = trim($data['password'] ?? $_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Username/email and password are required.']);
    exit;
}

function getAmpacheConnectionLogin() {
    $ampCfgFile = __DIR__ . '/../../ampache/config/ampache.cfg.php';
    $ampCfg = file_exists($ampCfgFile) ? @parse_ini_file($ampCfgFile) : [];
    $ampHost = $ampCfg['database_hostname'] ?? '127.0.0.1';
    $ampPort = $ampCfg['database_port'] ?? '8889';
    $ampDb   = $ampCfg['database_name'] ?? 'ampache';
    $ampUser = $ampCfg['database_username'] ?? 'server_app';
    $ampPass = $ampCfg['database_password'] ?? 'password';
    
    $ampHosts = array_unique(array_filter([$ampHost, '127.0.0.1', 'localhost']));
    $ampCreds = [
        [$ampUser, $ampPass],
        ['root', 'root'],
        ['server_app', 'password']
    ];
    $ampPdo = null;
    foreach ($ampHosts as $ah) {
        foreach ($ampCreds as [$au, $ap]) {
            try {
                return new PDO("mysql:host={$ah};port={$ampPort};dbname={$ampDb};charset=utf8mb4", $au, $ap, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 1
                ]);
            } catch (\Exception $e) {}
        }
    }
    return null;
}

try {
    $stmt = $pdo->prepare("SELECT id, username, password_hash, role, COALESCE(storage_limit_mb, 100) AS storage_limit_mb, COALESCE(storage_used_mb, 0.0) AS storage_used_mb FROM sys_users WHERE username = :identifier LIMIT 1");
    $stmt->execute([':identifier' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        // AUTO-REGISTER IF USER DOES NOT EXIST
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters.']);
            exit;
        }
        
        $pdo->beginTransaction();
        
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        $email = $username . '@local.host'; // placeholder
        
        $insert = $pdo->prepare("INSERT INTO sys_users (username, email, password_hash, role, storage_limit_mb) VALUES (:u, :e, :h, 'member', 100)");
        $insert->execute([':u' => $username, ':e' => $email, ':h' => $hash]);
        $newId = $pdo->lastInsertId();
        
        // Ampache DB Sync
        $ampPdo = getAmpacheConnectionLogin();
        if ($ampPdo) {
            $ampStmt = $ampPdo->prepare("INSERT INTO user (username, password, access, create_date) VALUES (:username, :password, 25, :created)");
            $ampStmt->execute([
                ':username' => $username,
                ':password' => hash('sha256', $password),
                ':created' => time()
            ]);
        }
        $pdo->commit();
        
        // Re-fetch the new user
        $stmt->execute([':identifier' => $username]);
        $user = $stmt->fetch();
    }

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        $ttlDays = (int)(getenv('AUTH_TOKEN_TTL_DAYS') ?: 7);
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttlDays * 86400));
        
        $jwt_header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $jwt_payload = base64_encode(json_encode(['user_id' => $user['id'], 'username' => $user['username'], 'role' => $user['role'], 'exp' => time() + ($ttlDays * 86400)]));
        $jwt_secret = getenv('JWT_SECRET') ?: 'default-secret-key-change-me';
        $jwt_signature = base64_encode(hash_hmac('sha256', "$jwt_header.$jwt_payload", $jwt_secret, true));
        $jwt = "$jwt_header.$jwt_payload.$jwt_signature";
        
        $salt = bin2hex(random_bytes(6));
        $subsonic_token = md5($password . $salt);

        try {
            $ampPdo = getAmpacheConnectionLogin();
            if ($ampPdo) {
                $aStmt = $ampPdo->prepare("SELECT apikey FROM user WHERE username = :u LIMIT 1");
                $aStmt->execute([':u' => $user['username']]);
                $foundApiKey = $aStmt->fetchColumn();
                if (!empty($foundApiKey)) {
                    $subsonic_token = md5($foundApiKey . $salt);
                } else if ($user['username'] === (getenv('AMPACHE_ADMIN_USER') ?: 'admin')) {
                    $adminKey = getenv('AMPACHE_ADMIN_API_KEY') ?: '18e499b984c75ad09e233f6d8fe0228d';
                    $subsonic_token = md5($adminKey . $salt);
                }
            } else if ($user['username'] === (getenv('AMPACHE_ADMIN_USER') ?: 'admin')) {
                $adminKey = getenv('AMPACHE_ADMIN_API_KEY') ?: '18e499b984c75ad09e233f6d8fe0228d';
                $subsonic_token = md5($adminKey . $salt);
            }
        } catch (\Exception $ae) {
            // fallback
        }
        
        $_SESSION['auth_token'] = $jwt;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $role = ($user['role'] === 'admin') ? 'admin' : 'user';
        $_SESSION['role'] = $role;
        if ($role === 'admin') {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $user['username'];
        } else {
            $_SESSION['admin_logged_in'] = false;
            unset($_SESSION['admin_user']);
        } 

        // Calculate current real-time directory storage usage for user
        $userSitesDir = realpath(__DIR__ . '/../../sites') . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $user['username']);
        $actualUsedMB = function_exists('getDirectorySizeMB') ? getDirectorySizeMB($userSitesDir) : 0.0;
        
        // Update database with latest usage and persist hashed auth_token and expiration
        try {
            $upd = $pdo->prepare("UPDATE sys_users SET storage_used_mb = :used, auth_token = :token, token_hash = :th, token_expires_at = :exp WHERE id = :id");
            $upd->execute([':used' => $actualUsedMB, ':token' => $hashedToken, ':th' => $hashedToken, ':exp' => $expiresAt, ':id' => $user['id']]);
        } catch (\Exception $ue) {}

        $limitMB = (float)$user['storage_limit_mb'];
        $usedMB = max((float)$actualUsedMB, (float)$user['storage_used_mb']);
        $percentUsed = $limitMB > 0 ? min(100, round(($usedMB / $limitMB) * 100, 1)) : 0;

        echo json_encode([
            'status'         => 'success',
            'token'          => $jwt,
            'role'           => $user['role'],
            'subsonic_token' => $subsonic_token,
            'subsonic_salt'  => $salt,
            'username'       => $user['username'],
            'storage'        => [
                'limit_mb'       => $limitMB,
                'used_mb'        => $usedMB,
                'used_bytes'     => round($usedMB * 1024 * 1024),
                'limit_bytes'    => round($limitMB * 1024 * 1024),
                'percent_used'   => $percentUsed,
                'formatted_used' => $usedMB . ' MB',
                'formatted_limit'=> $limitMB . ' MB'
            ],
            'message' => 'Authentication successful.'
        ]);
        exit;
    } else {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid username or password.']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
