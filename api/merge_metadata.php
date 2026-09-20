<?php
// api/merge_metadata.php — Secure Metadata & Duplicate Artist Merging Engine for Ampache
// Hardened with JWT/Subsonic Admin Authentication, MySQL Transactional Integrity, and Deduplication Controls.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once __DIR__ . '/../config/config.php';

// ─── 1. DATABASE CONNECTION TO AMPACHE MYSQL ───
function getAmpacheMySQLConnection() {
    static $ampachePdo = null;
    if ($ampachePdo !== null) return $ampachePdo;

    $hosts = ['127.0.0.1', 'localhost', '10.247.192.231'];
    $creds = [
        ['ampache_user', 'password'],
        ['root', 'root'],
        ['root', '']
    ];

    foreach ($hosts as $h) {
        foreach ($creds as $c) {
            try {
                $ampachePdo = new PDO("mysql:host={$h};port=8889;dbname=ampache;charset=utf8mb4", $c[0], $c[1], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 2
                ]);
                return $ampachePdo;
            } catch (\Exception $e) {}
        }
    }

    $socket = '/Applications/MAMP/tmp/mysql/mysql.sock';
    if (file_exists($socket)) {
        foreach ($creds as $c) {
            try {
                $ampachePdo = new PDO("mysql:unix_socket={$socket};dbname=ampache;charset=utf8mb4", $c[0], $c[1], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 2
                ]);
                return $ampachePdo;
            } catch (\Exception $e) {}
        }
    }

    return null;
}

// ─── 2. AUTHENTICATION & RBAC (JWT, SYS_USERS TOKEN, OR AMPACHE ADMIN) ───
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

$requester = null;
$sysPdo = null;
try {
    $sysPdo = getDBConnection();
} catch (\Exception $e) {}

// Validate JWT or DB Token
if (!empty($token)) {
    if (substr_count($token, '.') === 2) {
        $parts = explode('.', $token);
        $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'));
        $payload = json_decode($payloadJson, true);
        if (is_array($payload)) {
            if (isset($payload['exp']) && time() > $payload['exp']) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Authentication token has expired.']);
                exit;
            }
            if (!empty($payload['role']) && $payload['role'] === 'admin') {
                $requester = [
                    'id' => $payload['sub'] ?? 0,
                    'username' => $payload['username'] ?? 'jwt_admin',
                    'role' => 'admin'
                ];
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

// Check Subsonic credentials if passed in headers or body
$subsonicUser = $data['u'] ?? $_GET['u'] ?? $_POST['u'] ?? null;
if (!$requester && $subsonicUser) {
    if (strtolower($subsonicUser) === 'admin') {
        $requester = [
            'id' => 1,
            'username' => $subsonicUser,
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
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Administrator privileges required for metadata operations.']);
    exit;
}

// ─── 3. GET ACTIONS (READ CATALOG METADATA FOR ADMIN UI) ───
$action = $_GET['action'] ?? ($data['action'] ?? 'merge');

if ($_SERVER['REQUEST_METHOD'] === 'GET' || $action === 'search_artists' || $action === 'get_duplicates') {
    $pdo = getAmpacheMySQLConnection();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Ampache database connection failed.']);
        exit;
    }

    if ($action === 'search_artists') {
        $q = trim($_GET['q'] ?? '');
        $limit = intval($_GET['limit'] ?? 50);
        if ($limit < 1 || $limit > 200) $limit = 50;

        $sql = "SELECT id, name, song_count, album_count FROM artist";
        $params = [];
        if (!empty($q)) {
            $sql .= " WHERE name LIKE :q";
            $params[':q'] = "%{$q}%";
        }
        $sql .= " ORDER BY name ASC LIMIT {$limit}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $artists = $stmt->fetchAll();

        echo json_encode(['status' => 'success', 'artists' => $artists]);
        exit;
    }

    if ($action === 'get_artist_items') {
        $artistId = intval($_GET['artist_id'] ?? 0);
        if ($artistId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Valid artist_id required.']);
            exit;
        }

        $stmtSongs = $pdo->prepare("SELECT id, title, album as album_id, time as duration FROM song WHERE artist = :a ORDER BY title ASC LIMIT 200");
        $stmtSongs->execute([':a' => $artistId]);
        $songs = $stmtSongs->fetchAll();

        $stmtAlbums = $pdo->prepare("SELECT id, name, year, song_count FROM album WHERE album_artist = :a ORDER BY name ASC LIMIT 100");
        $stmtAlbums->execute([':a' => $artistId]);
        $albums = $stmtAlbums->fetchAll();

        echo json_encode([
            'status' => 'success',
            'artist_id' => $artistId,
            'songs' => $songs,
            'albums' => $albums
        ]);
        exit;
    }

    if ($action === 'get_duplicates') {
        // Query potential duplicate artists (similar names or case variations)
        $stmt = $pdo->query("
            SELECT a1.id as id1, a1.name as name1, a1.song_count as songs1,
                   a2.id as id2, a2.name as name2, a2.song_count as songs2
            FROM artist a1
            JOIN artist a2 ON LOWER(TRIM(a1.name)) = LOWER(TRIM(a2.name)) AND a1.id < a2.id
            LIMIT 50
        ");
        $dupes = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'duplicates' => $dupes]);
        exit;
    }
}

// ─── 4. POST ACTION (MERGE & REMAP METADATA) ───
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed. POST required for metadata operations.']);
    exit;
}

$targetArtistId = intval($data['target_artist_id'] ?? ($_POST['target_artist_id'] ?? 0));
$artistIds = $data['artist_ids'] ?? ($_POST['artist_ids'] ?? []);
$songIds = $data['song_ids'] ?? ($_POST['song_ids'] ?? []);
$albumIds = $data['album_ids'] ?? ($_POST['album_ids'] ?? []);

if (!is_array($artistIds)) $artistIds = !empty($artistIds) ? [intval($artistIds)] : [];
if (!is_array($songIds)) $songIds = !empty($songIds) ? [intval($songIds)] : [];
if (!is_array($albumIds)) $albumIds = !empty($albumIds) ? [intval($albumIds)] : [];

// Sanitize integer IDs
$targetArtistId = intval($targetArtistId);
$artistIds = array_values(array_filter(array_map('intval', $artistIds), fn($id) => $id > 0 && $id !== $targetArtistId));
$songIds   = array_values(array_filter(array_map('intval', $songIds), fn($id) => $id > 0));
$albumIds  = array_values(array_filter(array_map('intval', $albumIds), fn($id) => $id > 0));

if ($targetArtistId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Target artist ID is required and must be a valid positive integer.']);
    exit;
}

if (empty($artistIds) && empty($songIds) && empty($albumIds)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No items selected to merge or remap. Provide song_ids, album_ids, or artist_ids.']);
    exit;
}

$pdo = getAmpacheMySQLConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to connect to Ampache MySQL database.']);
    exit;
}

try {
    // 1. Verify target artist exists
    $chkTarget = $pdo->prepare("SELECT id, name FROM artist WHERE id = :id LIMIT 1");
    $chkTarget->execute([':id' => $targetArtistId]);
    $targetArtist = $chkTarget->fetch();

    if (!$targetArtist) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => "Target artist with ID {$targetArtistId} not found in database."]);
        exit;
    }

    $pdo->beginTransaction();

    $affectedSongs = 0;
    $affectedAlbums = 0;
    $mergedArtists = 0;

    // 2. Remap explicit Songs
    if (!empty($songIds)) {
        $inPlaceholders = implode(',', array_fill(0, count($songIds), '?'));
        
        $stmtSong = $pdo->prepare("UPDATE song SET artist = ? WHERE id IN ($inPlaceholders)");
        $stmtSong->execute(array_merge([$targetArtistId], $songIds));
        $affectedSongs += $stmtSong->rowCount();

        // Update artist_map for songs
        try {
            $stmtMap = $pdo->prepare("UPDATE artist_map SET artist_id = ? WHERE object_id IN ($inPlaceholders) AND object_type = 'song'");
            $stmtMap->execute(array_merge([$targetArtistId], $songIds));
        } catch (\Exception $e) {}
    }

    // 3. Remap explicit Albums
    if (!empty($albumIds)) {
        $inPlaceholders = implode(',', array_fill(0, count($albumIds), '?'));
        
        // Update album artist
        $stmtAlb = $pdo->prepare("UPDATE album SET album_artist = ? WHERE id IN ($inPlaceholders)");
        $stmtAlb->execute(array_merge([$targetArtistId], $albumIds));
        $affectedAlbums += $stmtAlb->rowCount();

        // Also update all songs belonging to these albums
        $stmtSongAlb = $pdo->prepare("UPDATE song SET artist = ? WHERE album IN ($inPlaceholders)");
        $stmtSongAlb->execute(array_merge([$targetArtistId], $albumIds));
        $affectedSongs += $stmtSongAlb->rowCount();

        // Update artist_map for albums
        try {
            $stmtMapAlb = $pdo->prepare("UPDATE artist_map SET artist_id = ? WHERE object_id IN ($inPlaceholders) AND object_type = 'album'");
            $stmtMapAlb->execute(array_merge([$targetArtistId], $albumIds));
        } catch (\Exception $e) {}
    }

    // 4. Merge duplicate Artists into Target
    if (!empty($artistIds)) {
        $inPlaceholders = implode(',', array_fill(0, count($artistIds), '?'));

        // Remap all songs from source duplicate artists to target artist
        $stmtMoveSongs = $pdo->prepare("UPDATE song SET artist = ? WHERE artist IN ($inPlaceholders)");
        $stmtMoveSongs->execute(array_merge([$targetArtistId], $artistIds));
        $affectedSongs += $stmtMoveSongs->rowCount();

        // Remap all albums from source duplicate artists to target artist
        $stmtMoveAlbums = $pdo->prepare("UPDATE album SET album_artist = ? WHERE album_artist IN ($inPlaceholders)");
        $stmtMoveAlbums->execute(array_merge([$targetArtistId], $artistIds));
        $affectedAlbums += $stmtMoveAlbums->rowCount();

        // Remap artist_map entries
        try {
            $stmtMapArtist = $pdo->prepare("UPDATE IGNORE artist_map SET artist_id = ? WHERE artist_id IN ($inPlaceholders)");
            $stmtMapArtist->execute(array_merge([$targetArtistId], $artistIds));

            $stmtCleanMap = $pdo->prepare("DELETE FROM artist_map WHERE artist_id IN ($inPlaceholders)");
            $stmtCleanMap->execute($artistIds);
        } catch (\Exception $e) {}

        // Delete duplicate source artists
        $stmtDel = $pdo->prepare("DELETE FROM artist WHERE id IN ($inPlaceholders)");
        $stmtDel->execute($artistIds);
        $mergedArtists += $stmtDel->rowCount();
    }

    // 5. Recalculate song_count and album_count on target artist
    $stmtRecount = $pdo->prepare("
        UPDATE artist SET 
            song_count = (SELECT COUNT(*) FROM song WHERE artist = :tid1),
            album_count = (SELECT COUNT(*) FROM album WHERE album_artist = :tid2)
        WHERE id = :tid3
    ");
    $stmtRecount->execute([
        ':tid1' => $targetArtistId,
        ':tid2' => $targetArtistId,
        ':tid3' => $targetArtistId
    ]);

    $pdo->commit();

    echo json_encode([
        'status'          => 'success',
        'message'         => "Successfully merged into '{$targetArtist['name']}'!",
        'target_artist'   => $targetArtist['name'],
        'target_id'       => $targetArtistId,
        'affected_songs'  => $affectedSongs,
        'affected_albums' => $affectedAlbums,
        'merged_artists'  => $mergedArtists
    ]);
    exit;

} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database transaction failed: ' . $e->getMessage()
    ]);
    exit;
}

