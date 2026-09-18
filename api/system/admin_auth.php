<?php
// api/system/admin_auth.php — Admin Authentication API (Database Backed)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

try {
    require_once __DIR__ . '/../../config/db_connect.php';
    
    // Ensure admin_users table exists in access_db
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            last_login DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Ensure default admin user exists if table is freshly created
    $stmt = $pdo->prepare("SELECT id, password_hash FROM admin_users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();

    if (!$admin) {
        $initialPass = getenv('ADMIN_INITIAL_PASSWORD');
        $initialHash = getenv('ADMIN_INITIAL_HASH');
        if (!empty($initialPass)) {
            $hash = password_hash($initialPass, PASSWORD_BCRYPT, ['cost' => 10]);
        } elseif (!empty($initialHash)) {
            $hash = $initialHash;
        } else {
            $secretFile = dirname(__DIR__, 2) . '/storage/.admin_initial_password';
            if (file_exists($secretFile)) {
                $genPass = trim(file_get_contents($secretFile));
            } else {
                $genPass = bin2hex(random_bytes(8));
                @file_put_contents($secretFile, $genPass);
                @chmod($secretFile, 0600);
            }
            $hash = password_hash($genPass, PASSWORD_BCRYPT, ['cost' => 10]);
        }
        $insert = $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES ('admin', :hash)");
        $insert->execute([':hash' => $hash]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}

$action = $_REQUEST['action'] ?? 'status';

if ($action === 'status') {
    // Only accept Authorization header or POST body to prevent query param CSRF / token leakage
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = '';
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = trim($matches[1]);
    } elseif (!empty($_POST['token'])) {
        $token = trim($_POST['token']);
    }

    if (!empty($token) && empty($_SESSION['auth_token'])) {
        try {
            $tokenHash = hash('sha256', $token);
            $stmt = $pdo->prepare("SELECT id, username, role, token_expires_at FROM sys_users WHERE (token_hash = :h OR auth_token = :h OR auth_token = :t) LIMIT 1");
            $stmt->execute([':h' => $tokenHash, ':t' => $token]);
            if ($u = $stmt->fetch()) {
                $isExpired = !empty($u['token_expires_at']) && (strtotime($u['token_expires_at']) < time());
                if (!$isExpired) {
                    $_SESSION['auth_token'] = $token;
                    $_SESSION['user_id'] = $u['id'];
                    $_SESSION['username'] = $u['username'];
                    $_SESSION['role'] = $u['role'];
                    $_SESSION['admin_logged_in'] = ($u['role'] === 'admin');
                    if ($u['role'] === 'admin') {
                        $_SESSION['admin_user'] = $u['username'];
                    }
                }
            }
        } catch (\Exception $e) {}
    }

    $isLoggedIn = !empty($_SESSION['auth_token']) || (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
    $username = $_SESSION['username'] ?? $_SESSION['admin_user'] ?? null;
    $role = $_SESSION['role'] ?? (!empty($_SESSION['admin_logged_in']) ? 'admin' : 'guest');
    echo json_encode([
        'status'    => 'success',
        'logged_in' => (bool)$isLoggedIn,
        'user'      => $username,
        'role'      => $role
    ]);
    exit;
}

if ($action === 'login') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: [];
    
    $username = trim($data['username'] ?? $_POST['username'] ?? '');
    $password = trim($data['password'] ?? $_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Username and password are required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = :user LIMIT 1");
        $stmt->execute([':user' => $username]);
        $userRow = $stmt->fetch();

        $authenticated = false;
        if ($userRow && password_verify($password, $userRow['password_hash'])) {
            $authenticated = true;
        }

        if ($authenticated) {
            // Record login timestamp in database
            $logStmt = $pdo->prepare("UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE id = :id");
            $logStmt->execute([':id' => $userRow['id']]);

            session_regenerate_id(true);
            $token = bin2hex(random_bytes(32));
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $userRow['username'];
            $_SESSION['role'] = 'admin';
            $_SESSION['auth_token'] = $token;

            try {
                $tokenHash = hash('sha256', $token);
                $ttlDays = (int)(getenv('AUTH_TOKEN_TTL_DAYS') ?: 7);
                $expiresAt = date('Y-m-d H:i:s', time() + ($ttlDays * 86400));
                $uStmt = $pdo->prepare("UPDATE sys_users SET auth_token = :t, token_hash = :th, token_expires_at = :exp WHERE username = :u");
                $uStmt->execute([':t' => $tokenHash, ':th' => $tokenHash, ':exp' => $expiresAt, ':u' => $userRow['username']]);
            } catch (\Exception $e) {}

            echo json_encode([
                'status'   => 'success',
                'message'  => 'Admin authentication successful.',
                'token'    => $token,
                'role'     => 'admin',
                'username' => $userRow['username']
            ]);
            exit;
        } else {
            http_response_code(401);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Invalid username or password.'
            ]);
            exit;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }
}

if ($action === 'logout') {
    $token = trim($_POST['token'] ?? '');
    if (empty($token)) {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = trim($matches[1]);
        }
    }
    if (empty($token) && !empty($_SESSION['auth_token'])) {
        $token = $_SESSION['auth_token'];
    }
    if (!empty($token)) {
        try {
            $tokenHash = hash('sha256', $token);
            $upd = $pdo->prepare("UPDATE sys_users SET auth_token = NULL, token_expires_at = NULL WHERE auth_token = :h OR auth_token = :t");
            $upd->execute([':h' => $tokenHash, ':t' => $token]);
        } catch (\Exception $e) {}
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    echo json_encode([
        'status'  => 'success',
        'message' => 'Logged out successfully.'
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
?>
