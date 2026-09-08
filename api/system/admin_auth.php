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

    // Ensure default admin user exists (username: admin, password: 123456789)
    $stmt = $pdo->prepare("SELECT id, password_hash FROM admin_users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();

    $defaultPass = '123456789';
    if (!$admin) {
        // Insert admin user into database
        $hash = password_hash($defaultPass, PASSWORD_BCRYPT);
        $insert = $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES ('admin', :hash)");
        $insert->execute([':hash' => $hash]);
    } else {
        // Ensure default password '123456789' is valid hash if legacy
        if (!password_verify($defaultPass, $admin['password_hash']) && $admin['password_hash'] !== $defaultPass) {
            $hash = password_hash($defaultPass, PASSWORD_BCRYPT);
            $update = $pdo->prepare("UPDATE admin_users SET password_hash = :hash WHERE id = :id");
            $update->execute([':hash' => $hash, ':id' => $admin['id']]);
        }
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
    $isLoggedIn = !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    $username = $_SESSION['admin_user'] ?? null;
    echo json_encode([
        'status'    => 'success',
        'logged_in' => $isLoggedIn,
        'user'      => $username
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
        if ($userRow) {
            if (password_verify($password, $userRow['password_hash'])) {
                $authenticated = true;
            } elseif ($userRow['password_hash'] === $password) {
                // Legacy plaintext fallback & auto-upgrade to BCRYPT
                $authenticated = true;
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $upStmt = $pdo->prepare("UPDATE admin_users SET password_hash = :h WHERE id = :id");
                $upStmt->execute([':h' => $newHash, ':id' => $userRow['id']]);
            }
        }

        if ($authenticated) {
            // Record login timestamp in database
            $logStmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = :id");
            $logStmt->execute([':id' => $userRow['id']]);

            $token = bin2hex(random_bytes(32));
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $userRow['username'];
            $_SESSION['role'] = 'admin';
            $_SESSION['auth_token'] = $token;

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
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_user']);
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
