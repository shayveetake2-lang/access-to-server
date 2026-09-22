<?php
// api/group_unknown_artists.php — Groups orphaned/untagged songs under a single
// canonical "Unknown Artist" profile in the Ampache MySQL database.
// Admin-only over HTTP; CLI invocation (trusted local admin) skips HTTP auth.
// Ensures the profile exists (creating it if needed), then reassigns every
// song with a missing/zero artist link (or a blank-named artist) to it via
// PDO prepared statements.

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/media/_request_auth.php'; // verifyAmpacheUser(), resolveMediaAdmin()

const UNKNOWN_ARTIST_NAME = 'Unknown Artist';

// ─── DATABASE CONNECTION TO AMPACHE MYSQL ───
function getAmpacheMySQLConnection(): ?PDO {
    static $ampachePdo = null;
    if ($ampachePdo !== null) return $ampachePdo;

    $host = defined('AMPACHE_DB_HOST') ? AMPACHE_DB_HOST : '127.0.0.1';
    $port = defined('AMPACHE_DB_PORT') ? AMPACHE_DB_PORT : '8889';
    $dbname = defined('AMPACHE_DB_NAME') ? AMPACHE_DB_NAME : 'ampache';
    $user = defined('AMPACHE_DB_USER') ? AMPACHE_DB_USER : 'ampache_user';
    $pass = defined('AMPACHE_DB_PASS') ? AMPACHE_DB_PASS : 'password';

    $hosts = array_unique([$host, '127.0.0.1', 'localhost']);
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 2,
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

/**
 * Finds the "Unknown Artist" profile by exact name, creating it if absent.
 * Returns its integer id.
 */
function ensureUnknownArtist(PDO $pdo): int {
    $stmt = $pdo->prepare("SELECT id FROM artist WHERE name = :name LIMIT 1");
    $stmt->execute([':name' => UNKNOWN_ARTIST_NAME]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return (int)$row['id'];
    }

    $insert = $pdo->prepare(
        "INSERT INTO artist (name, last_update, total_count, song_count, album_count, album_disk_count)
         VALUES (:name, :last_update, 0, 0, 0, 0)"
    );
    $insert->execute([
        ':name' => UNKNOWN_ARTIST_NAME,
        ':last_update' => time(),
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Reassigns every orphaned/untagged song (artist_id = 0, or artist_id
 * pointing at a blank-named artist row) to the "Unknown Artist" profile.
 * Also repoints artist_map rows so getArtists()/getSongs() stay consistent.
 * Returns the number of song rows updated.
 */
function reassignOrphanedSongs(PDO $pdo, int $unknownArtistId): int {
    // Blank-named artist rows (excluding the Unknown Artist row itself) whose
    // songs should also be folded into the canonical profile.
    $blankArtistStmt = $pdo->prepare(
        "SELECT id FROM artist WHERE id != :unknownId AND (name IS NULL OR TRIM(name) = '')"
    );
    $blankArtistStmt->execute([':unknownId' => $unknownArtistId]);
    $blankArtistIds = array_map('intval', array_column($blankArtistStmt->fetchAll(PDO::FETCH_ASSOC), 'id'));

    $targetArtistIds = array_unique(array_merge([0], $blankArtistIds));
    $placeholders = implode(',', array_fill(0, count($targetArtistIds), '?'));

    $updateSongs = $pdo->prepare(
        "UPDATE song SET artist = ? WHERE artist IN ($placeholders)"
    );
    $updateSongs->execute(array_merge([$unknownArtistId], $targetArtistIds));
    $updatedCount = $updateSongs->rowCount();

    // Keep artist_map in sync for the affected songs so browse/index views agree.
    $updateMap = $pdo->prepare(
        "UPDATE artist_map SET artist_id = ? WHERE artist_id IN ($placeholders) AND object_type = 'song'"
    );
    $updateMap->execute(array_merge([$unknownArtistId], $targetArtistIds));

    // Remove the now-empty blank-named artist rows (Unknown Artist itself is excluded).
    if (!empty($blankArtistIds)) {
        $delPlaceholders = implode(',', array_fill(0, count($blankArtistIds), '?'));
        $pdo->prepare("DELETE FROM artist WHERE id IN ($delPlaceholders)")->execute($blankArtistIds);
    }

    // Refresh aggregate counters on the Unknown Artist row.
    $refresh = $pdo->prepare(
        "UPDATE artist SET
            song_count = (SELECT COUNT(*) FROM song WHERE artist = :id1),
            album_count = (SELECT COUNT(DISTINCT album) FROM song WHERE artist = :id2)
         WHERE id = :id3"
    );
    $refresh->execute([':id1' => $unknownArtistId, ':id2' => $unknownArtistId, ':id3' => $unknownArtistId]);

    return $updatedCount;
}

/**
 * Accepts a trusted shared-secret header (`X-Admin-Key`) matching the
 * already-configured AMPACHE_ADMIN_API_KEY, for remote maintenance calls
 * where Subsonic session auth isn't available (e.g. this DB only accepts
 * local connections, so the script must run via HTTP loopback on the host).
 */
function isAuthorizedAdminKey(): bool {
    $expected = getEnvValue('AMPACHE_ADMIN_API_KEY', '');
    if ($expected === '') return false;
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $provided = $headers['X-Admin-Key'] ?? $headers['x-admin-key'] ?? $_SERVER['HTTP_X_ADMIN_KEY'] ?? '';
    return $provided !== '' && hash_equals($expected, $provided);
}

try {
    $pdo = getAmpacheMySQLConnection();
    if (!$pdo) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'message' => 'Unable to connect to the Ampache database.']);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!$isCli) {
        $admin = resolveMediaAdmin($pdo, $body);
        if (!$admin && !isAuthorizedAdminKey()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Administrator access required.']);
            exit;
        }
    }

    $pdo->beginTransaction();
    $unknownArtistId = ensureUnknownArtist($pdo);
    $updatedSongs = reassignOrphanedSongs($pdo, $unknownArtistId);
    $pdo->commit();

    echo json_encode([
        'status' => 'ok',
        'unknownArtistId' => $unknownArtistId,
        'songsReassigned' => $updatedSongs,
    ]);
} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Grouping failed: ' . $e->getMessage()]);
}
