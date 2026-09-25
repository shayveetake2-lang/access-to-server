<?php
// api/auth/register.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db_connect.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: [];

// Strip any incoming role or isAdmin fields to prevent self-promotion
unset($data['role']);
unset($data['isAdmin']);
if (isset($_POST['role'])) unset($_POST['role']);
if (isset($_POST['isAdmin'])) unset($_POST['isAdmin']);

$username = trim($data['username'] ?? $_POST['username'] ?? '');
$password = trim($data['password'] ?? $_POST['password'] ?? '');
$email = trim($data['email'] ?? $_POST['email'] ?? '');

if (empty($username) || empty($password) || empty($email)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Username, email, and password are required.']);
    exit;
}

// Ensure username doesn't contain '@'
if (strpos($username, '@') !== false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Username cannot contain an @ symbol.']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_\-]{3,30}$/', $username)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Username must be between 3 and 30 alphanumeric characters.']);
    exit;
}

if (strlen($password) < 10) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 10 characters.']);
    exit;
}

function getAmpacheConnection() {
    $host = defined('AMPACHE_DB_HOST') ? AMPACHE_DB_HOST : '127.0.0.1';
    $port = defined('AMPACHE_DB_PORT') ? AMPACHE_DB_PORT : '8889';
    $dbname = defined('AMPACHE_DB_NAME') ? AMPACHE_DB_NAME : 'ampache';
    $user = defined('AMPACHE_DB_USER') ? AMPACHE_DB_USER : 'ampache_user';
    $pass = defined('AMPACHE_DB_PASS') ? AMPACHE_DB_PASS : 'password';

    $hosts = array_unique([$host, '127.0.0.1', 'localhost']);
    $creds = [
        [$user, $pass],
        ['server_app', 'password'],
        ['root', 'root'],
        ['root', '']
    ];

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 2
    ];

    foreach ($hosts as $h) {
        foreach ($creds as [$u, $pwd]) {
            try {
                return new PDO("mysql:host={$h};port={$port};dbname={$dbname};charset=utf8mb4", $u, $pwd, $options);
            } catch (\Exception $e) {}
        }
    }

    $socket = '/Applications/MAMP/tmp/mysql/mysql.sock';
    if (file_exists($socket)) {
        foreach ($creds as [$u, $pwd]) {
            try {
                return new PDO("mysql:unix_socket={$socket};dbname={$dbname};charset=utf8mb4", $u, $pwd, $options);
            } catch (\Exception $e) {}
        }
    }
    return null;
}

try {


    // Check if username exists
    $stmt = $pdo->prepare("SELECT id FROM sys_users WHERE username = :username OR email = :email");
    $stmt->execute([':username' => $username, ':email' => $email]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['status' => 'error', 'message' => 'Username or email already exists.']);
        exit;
    }

    $pdo->beginTransaction();

    // Auto-authenticate newly registered user
    $token = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $token);
    $ttlDays = (int)(getenv('AUTH_TOKEN_TTL_DAYS') ?: 7);
    $expiresAt = date('Y-m-d H:i:s', time() + ($ttlDays * 86400));

    // Insert new user with token and hashed token
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $insert = $pdo->prepare("INSERT INTO sys_users (username, email, password_hash, role, storage_limit_mb, auth_token, token_hash, token_expires_at) VALUES (:username, :email, :hash, 'member', 100, :token, :th, :exp)");
    $insert->execute([
        ':username' => $username, 
        ':email' => $email,
        ':hash' => $hash, 
        ':token' => $hashedToken, 
        ':th' => $hashedToken, 
        ':exp' => $expiresAt
    ]);
    $newUserId = $pdo->lastInsertId();

    // Ampache DB Sync
    $ampPdo = getAmpacheConnection();
    if ($ampPdo) {
        $ampStmt = $ampPdo->prepare("INSERT INTO user (username, password, access, creation_date) VALUES (:username, :password, 25, :created)");
        $ampStmt->execute([
            ':username' => $username,
            ':password' => hash('sha256', $password),
            ':created' => time()
        ]);
    }

    $pdo->commit();

    $jwt_header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $jwt_payload = base64_encode(json_encode(['user_id' => $newUserId, 'username' => $username, 'role' => 'member', 'exp' => time() + ($ttlDays * 86400)]));
    $jwt_secret = getenv('JWT_SECRET') ?: 'default-secret-key-change-me';
    $jwt_signature = base64_encode(hash_hmac('sha256', "$jwt_header.$jwt_payload", $jwt_secret, true));
    $jwt = "$jwt_header.$jwt_payload.$jwt_signature";

    $_SESSION['auth_token'] = $jwt;
    $_SESSION['user_id'] = $newUserId;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = 'member';

    http_response_code(201);
    echo json_encode([
        'status'   => 'success',
        'token'    => $jwt,
        'role'     => 'member',
        'username' => $username,
        'storage'  => [
            'limit_mb'       => 100,
            'used_mb'        => 0.0,
            'used_bytes'     => 0,
            'limit_bytes'    => 104857600,
            'percent_used'   => 0,
            'formatted_used' => '0 MB',
            'formatted_limit'=> '100 MB'
        ],
        'message'  => 'Account created successfully! Logging you in...'
    ]);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    if ($e->getCode() == 23000) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Username or email already taken.']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
?>
