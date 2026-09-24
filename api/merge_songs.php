<?php
// api/merge_songs.php — Secure Admin Duplicate Song Merge Engine for Ampache
// Hardened with JWT/Subsonic Admin Authentication + MySQL Transactional Integrity.
// Body (POST, JSON): { primary_song_id: int, duplicate_song_ids: int[], token?, u?, t?, s? }
//
// Reassigns play history (object_count), ratings, favorites (user_flag), and
// playlist entries (playlist_data) from each duplicate song to the primary
// song, then deletes the now-empty duplicate `song` rows.

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
function getAmpacheMySQLConnectionForMerge() {
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
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Administrator privileges required to merge songs.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required.']);
    exit;
}

// ─── VALIDATE INPUT ───
$primaryId = isset($data['primary_song_id']) ? (int)$data['primary_song_id'] : 0;
$duplicateIds = array_values(array_unique(array_map('intval', (array)($data['duplicate_song_ids'] ?? []))));
$duplicateIds = array_filter($duplicateIds, fn($id) => $id > 0 && $id !== $primaryId);

if ($primaryId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'A valid primary_song_id is required.']);
    exit;
}
if (empty($duplicateIds)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'duplicate_song_ids must contain at least one song id (excluding the primary).']);
    exit;
}

$pdo = getAmpacheMySQLConnectionForMerge();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Ampache database connection failed.']);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($duplicateIds), '?'));

    // Confirm the primary and every duplicate actually exist before touching anything.
    $stmt = $pdo->prepare("SELECT id, title FROM song WHERE id = ? LIMIT 1");
    $stmt->execute([$primaryId]);
    $primarySong = $stmt->fetch();
    if (!$primarySong) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Primary song not found.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM song WHERE id IN ({$placeholders})");
    $stmt->execute($duplicateIds);
    $foundDuplicates = array_map('intval', array_column($stmt->fetchAll(), 'id'));
    if (count($foundDuplicates) !== count($duplicateIds)) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'One or more duplicate_song_ids were not found.']);
        exit;
    }

    $pdo->beginTransaction();

    $affected = [
        'object_count' => 0,
        'rating' => 0,
        'user_flag' => 0,
        'playlist_data' => 0,
    ];

    // ── Reassign play history (object_count) ──
    $stmt = $pdo->prepare("UPDATE object_count SET object_id = ? WHERE object_type = 'song' AND object_id IN ({$placeholders})");
    $stmt->execute(array_merge([$primaryId], $duplicateIds));
    $affected['object_count'] += $stmt->rowCount();

    // ── Reassign ratings (unique per user+object, so re-point where possible
    //    then drop any leftover rows the user already rated the primary too) ──
    $stmt = $pdo->prepare("UPDATE IGNORE rating SET object_id = ? WHERE object_type = 'song' AND object_id IN ({$placeholders})");
    $stmt->execute(array_merge([$primaryId], $duplicateIds));
    $affected['rating'] += $stmt->rowCount();
    $pdo->prepare("DELETE FROM rating WHERE object_type = 'song' AND object_id IN ({$placeholders})")->execute($duplicateIds);

    // ── Reassign favorites/hearts (same unique-per-user caveat as ratings) ──
    $stmt = $pdo->prepare("UPDATE IGNORE user_flag SET object_id = ? WHERE object_type = 'song' AND object_id IN ({$placeholders})");
    $stmt->execute(array_merge([$primaryId], $duplicateIds));
    $affected['user_flag'] += $stmt->rowCount();
    $pdo->prepare("DELETE FROM user_flag WHERE object_type = 'song' AND object_id IN ({$placeholders})")->execute($duplicateIds);

    // ── Reassign playlist entries ──
    $stmt = $pdo->prepare("UPDATE playlist_data SET object_id = ? WHERE object_type = 'song' AND object_id IN ({$placeholders})");
    $stmt->execute(array_merge([$primaryId], $duplicateIds));
    $affected['playlist_data'] += $stmt->rowCount();

    // ── Delete the now-empty duplicate song records ──
    $stmt = $pdo->prepare("DELETE FROM song WHERE id IN ({$placeholders})");
    $stmt->execute($duplicateIds);
    $deletedCount = $stmt->rowCount();

    // ── Recalculate the primary song's cached play/skip counters (mirrors
    //    Ampache's own Stats::clear() aggregation) so total_count reflects
    //    the newly-merged history immediately. ──
    $pdo->prepare(
        "UPDATE song SET total_count = (
            SELECT COUNT(*) FROM object_count
            WHERE object_count.object_type = 'song' AND object_count.count_type = 'stream' AND object_count.object_id = song.id
         ) WHERE song.id = ?"
    )->execute([$primaryId]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'primary_song_id' => $primaryId,
        'primary_title' => $primarySong['title'],
        'merged_ids' => $duplicateIds,
        'deleted_count' => $deletedCount,
        'affected' => $affected,
    ]);
} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to merge songs: ' . $e->getMessage()]);
}
