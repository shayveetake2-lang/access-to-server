<?php
// api/delete_song.php — Secure Admin Song Deletion Endpoint for Ampache
// Hardened with JWT/Subsonic Admin Authentication + MySQL Transactional Integrity.
// Body (POST, JSON): { song_id: int, delete_file?: bool, token?, u?, t?, s? }

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/media/_request_auth.php'; // shared verifyAmpacheUser() token+salt check

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── DATABASE CONNECTION TO AMPACHE MYSQL ───
function getAmpacheMySQLConnectionForDelete() {
    static $ampachePdo = null;
    if ($ampachePdo !== null) return $ampachePdo;

    $host = defined('AMPACHE_DB_HOST') ? AMPACHE_DB_HOST : '127.0.0.1';
    $port = defined('AMPACHE_DB_PORT') ? AMPACHE_DB_PORT : '8889';
    $dbname = defined('AMPACHE_DB_NAME') ? AMPACHE_DB_NAME : 'ampache';
    $user = defined('AMPACHE_DB_USER') ? AMPACHE_DB_USER : 'ampache_user';
    $pass = defined('AMPACHE_DB_PASS') ? AMPACHE_DB_PASS : 'password';

    $hosts = array_unique([$host, '127.0.0.1', 'localhost']);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 2
    ];

    foreach ($hosts as $h) {
        try {
            $ampachePdo = new PDO("mysql:host={$h};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, $options);
            return $ampachePdo;
        } catch (\Exception $e) {}
    }

    $socket = '/Applications/MAMP/tmp/mysql/mysql.sock';
    if (file_exists($socket)) {
        try {
            $ampachePdo = new PDO("mysql:unix_socket={$socket};dbname={$dbname};charset=utf8mb4", $user, $pass, $options);
            return $ampachePdo;
        } catch (\Exception $e) {}
    }

    return null;
}

if (defined('UNIT_TESTING') && UNIT_TESTING === true) {
    return;
}

// ─── AUTHENTICATION & RBAC (JWT, SYS_USERS TOKEN, SESSION, OR AMPACHE ADMIN) ───
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = [];
}

$token = '';
if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
    $token = trim($matches[1]);
}
if (empty($token) && !empty($data['token'])) {
    $token = trim($data['token']);
}
if (empty($token) && !empty($_POST['token'])) {
    $token = trim($_POST['token']);
}
if (empty($token) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);
}

$requester = null;
$sysPdo = null;
try {
    $sysPdo = getDBConnection();
} catch (\Exception $e) {}

// Validate JWT or DB Token
if (!empty($token)) {
    require_once __DIR__ . '/auth/jwt_utils.php';
    if (substr_count($token, '.') === 2) {
        $payload = verifyAndDecodeJwt($token);
        if ($payload !== null) {
            if (!empty($payload['role']) && $payload['role'] === 'admin') {
                $requester = [
                    'id' => $payload['user_id'] ?? $payload['sub'] ?? 0,
                    'username' => $payload['username'] ?? 'jwt_admin',
                    'role' => 'admin'
                ];
            }
        } else {
            // Check if token was malformed vs expired
            $parts = explode('.', $token);
            if (isset($parts[1])) {
                $pJson = base64UrlDecode($parts[1]);
                $pArr = json_decode($pJson, true);
                if (is_array($pArr) && isset($pArr['exp']) && time() > $pArr['exp']) {
                    http_response_code(401);
                    echo json_encode(['status' => 'error', 'message' => 'Authentication token has expired.']);
                    exit;
                }
            }
        }
    }

    if (!$requester && $sysPdo) {
        $tokenHash = hash('sha256', $token);
        $stmt = $sysPdo->prepare("SELECT id, username, role, token_expires_at FROM sys_users WHERE (token_hash = :th OR auth_token = :th OR auth_token = :t) LIMIT 1");
        $stmt->execute([':th' => $tokenHash, ':t' => $token]);
        $u = $stmt->fetch();
        if ($u && (empty($u['token_expires_at']) || strtotime($u['token_expires_at']) >= time())) {
            $requester = $u;
        }
    }
}

// Session check
if (!$requester && !empty($_SESSION['role'])) {
    $requester = [
        'id' => $_SESSION['user_id'] ?? 0,
        'username' => $_SESSION['username'] ?? 'session_user',
        'role' => $_SESSION['role']
    ];
}

// Subsonic credentials (u/t/s) verified via Ampache's own REST API loopback —
// requires the same t/s proof-of-password every other Subsonic call needs.
$subsonicUser  = trim($data['u'] ?? $_GET['u'] ?? $_POST['u'] ?? '');
$subsonicToken = trim($data['t'] ?? $_GET['t'] ?? $_POST['t'] ?? '');
$subsonicSalt  = trim($data['s'] ?? $_GET['s'] ?? $_POST['s'] ?? '');
if (!$requester && $subsonicUser && $subsonicToken && $subsonicSalt) {
    $ampacheUser = verifyAmpacheUser($subsonicUser, $subsonicToken, $subsonicSalt);
    if ($ampacheUser && !empty($ampacheUser['adminRole'])) {
        $requester = [
            'id' => 0,
            'username' => $ampacheUser['username'],
            'role' => 'admin'
        ];
    }
}

if (!$requester) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Valid admin authentication token required.']);
    exit;
}

if ($requester['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Administrator privileges required to delete songs.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required.']);
    exit;
}

// ─── VALIDATE INPUT ───
$songId = isset($data['song_id']) ? (int)$data['song_id'] : 0;
$deleteFile = !empty($data['delete_file']); // opt-in physical file removal via unlink()

if ($songId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'A valid song_id is required.']);
    exit;
}

$pdo = getAmpacheMySQLConnectionForDelete();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Ampache database connection failed.']);
    exit;
}

// Music mount root — unlink() is only ever attempted on files inside this
// directory, so a compromised/garbage `song`.`file` value can never be used
// to delete arbitrary files elsewhere on disk.
define('MUSIC_MOUNT_ROOT', '/Volumes/Music');

try {
    $stmt = $pdo->prepare("SELECT id, title, file FROM song WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $songId]);
    $song = $stmt->fetch();

    if (!$song) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Song not found.']);
        exit;
    }

    $pdo->beginTransaction();

    // Clean up rows in tables that reference this song so no orphaned data
    // is left behind (play history, ratings, favorites, playlist entries).
    $pdo->prepare("DELETE FROM object_count WHERE object_type = 'song' AND object_id = :id")->execute([':id' => $songId]);
    $pdo->prepare("DELETE FROM rating WHERE object_type = 'song' AND object_id = :id")->execute([':id' => $songId]);
    $pdo->prepare("DELETE FROM user_flag WHERE object_type = 'song' AND object_id = :id")->execute([':id' => $songId]);
    $pdo->prepare("DELETE FROM playlist_data WHERE object_type = 'song' AND object_id = :id")->execute([':id' => $songId]);

    $delStmt = $pdo->prepare("DELETE FROM song WHERE id = :id");
    $delStmt->execute([':id' => $songId]);
    $deleted = $delStmt->rowCount() > 0;

    $pdo->commit();

    // Physical file removal is opt-in and best-effort — a failure here must
    // never roll back the already-committed database deletion.
    $fileDeleted = false;
    $fileError = null;
    if ($deleted && $deleteFile && !empty($song['file'])) {
        $realPath = realpath($song['file']);
        $realRoot = realpath(MUSIC_MOUNT_ROOT);
        if ($realPath && $realRoot && strpos($realPath, $realRoot) === 0) {
            if (is_writable($realPath)) {
                $fileDeleted = @unlink($realPath);
                if (!$fileDeleted) {
                    $fileError = 'unlink() failed — check filesystem permissions.';
                }
            } else {
                $fileError = 'File is not writable by the web server user.';
            }
        } else {
            $fileError = 'File path is outside the permitted music mount and was not removed.';
        }
    }

    echo json_encode([
        'status' => 'success',
        'deleted_id' => $songId,
        'title' => $song['title'],
        'file_deleted' => $fileDeleted,
        'file_error' => $fileError,
    ]);
} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete song: ' . $e->getMessage()]);
}
