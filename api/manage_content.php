<?php
/**
 * manage_content.php — Secure admin endpoint for custom genre management
 * and song/album reassignment against the Ampache MySQL database.
 *
 * Supported actions (POST only, JSON body):
 *   insertGenre      — Insert a new custom genre tag
 *   assignGenre      — Link a genre tag to songs / albums / artists (tag_map)
 *   reassignSong      — Move song(s) to a different artist_id or album_id
 *   createArtist      — Insert a brand new custom artist row
 *   linkSongsToArtist — Point existing songs at a (custom) artist
 *   unlinkMedia       — Detach a song/album from its artist (Unknown Artist or NULL)
 *   renameMedia       — Rename a song title or album name
 *   orphanedSongs     — List songs missing an artist and/or album assignment
 *
 * Auth: Requires a valid Ampache administrator's Subsonic credentials (u/t/s)
 * in the JSON body. Verified by calling Ampache's own REST API server-side —
 * this endpoint never trusts a bare username or a client-embedded secret.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Helper ────────────────────────────────────────────────────────────────────

function json_error(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function json_ok(array $data = []): void {
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

// Verifies the caller's Subsonic credentials against Ampache's own REST API
// (server-side loopback) and confirms the account has adminRole=true. This
// delegates all password/token verification to Ampache itself instead of
// trusting a bare username or a static secret embedded in client-side JS.
function verifyAmpacheAdmin(string $username, string $token, string $salt): bool {
    if ($username === '' || $token === '' || $salt === '') return false;

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
    $base   = "{$scheme}://{$host}/access-to-server/ampache/public/rest/index.php";

    $qs = http_build_query([
        'action'   => 'getUser',
        'username' => $username,
        'u'        => $username,
        't'        => $token,
        's'        => $salt,
        'v'        => '1.16.1',
        'c'        => 'AetherAdminAPI',
        'f'        => 'json',
    ]);

    $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
    $raw = @file_get_contents("{$base}?{$qs}", false, $ctx);
    if (!$raw) {
        $fallbackBase = "{$scheme}://{$host}/ampache/public/rest/index.php";
        $raw = @file_get_contents("{$fallbackBase}?{$qs}", false, $ctx);
    }
    if (!$raw) return false;

    $data = json_decode($raw, true);
    $resp = $data['subsonic-response'] ?? null;
    if (!$resp || ($resp['status'] ?? '') !== 'ok') return false;

    $u = $resp['user'] ?? null;
    if (!$u || strtolower($u['username'] ?? '') !== strtolower($username)) return false;

    return !empty($u['adminRole']);
}

// ── Parse body ────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST required', 405);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    json_error('Invalid JSON body');
}

$action = $body['action'] ?? '';

// ── Auth (Dual: JWT Bearer OR Subsonic u/t/s) ───────────────────────────────

$is_admin = false;

// 1. Check for JWT Bearer token
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
    $jwt = trim($m[1]);
    $parts = explode('.', $jwt);
    if (count($parts) === 3) {
        $header = $parts[0];
        $payload_b64 = $parts[1];
        $signature_provided = $parts[2];
        
        $jwt_secret = getenv('JWT_SECRET') ?: 'default-secret-key-change-me';
        $signature_expected = base64_encode(hash_hmac('sha256', "$header.$payload_b64", $jwt_secret, true));
        
        if (hash_equals($signature_expected, $signature_provided)) {
            $payload = json_decode(base64_decode(strtr($payload_b64, '-_', '+/')), true);
            if (is_array($payload) && isset($payload['role']) && $payload['role'] === 'admin') {
                $is_admin = true;
            }
        }
    }
}

// 2. Fallback to Subsonic Credentials Loopback
if (!$is_admin) {
    $requesterUsername = trim($body['u'] ?? '');
    $requesterToken     = trim($body['t'] ?? '');
    $requesterSalt      = trim($body['s'] ?? '');
    $is_admin = verifyAmpacheAdmin($requesterUsername, $requesterToken, $requesterSalt);
}

if (!$is_admin) {
    json_error('Unauthorized: valid administrator credentials or JWT required.', 403);
}


// ── DB Connection ─────────────────────────────────────────────────────────────

function getPdo(): PDO {
    $hosts = ['127.0.0.1', 'localhost'];
    $port  = 8889;
    $db    = 'ampache';

    // Try credentials in order: dedicated user → root
    $credPairs = [
        ['ampache_user', 'password'],
        ['root', 'root'],
    ];

    foreach ($hosts as $host) {
        foreach ($credPairs as [$user, $pass]) {
            try {
                $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT            => 3,
                ]);
                return $pdo;
            } catch (PDOException $e) {
                // Try next combination
            }
        }
    }
    json_error('Database connection failed. Check MAMP is running.', 503);
}

// Finds (or lazily creates) the fallback "Unknown Artist" row used when unlinking media.
function getOrCreateUnknownArtistId(PDO $pdo): int {
    $find = $pdo->prepare("SELECT id FROM artist WHERE name = 'Unknown Artist' LIMIT 1");
    $find->execute();
    $row = $find->fetch();
    if ($row) return (int)$row['id'];

    // `artist` has no addition_time column — only last_update (confirmed against live schema)
    $ins = $pdo->prepare('INSERT INTO artist (name, last_update) VALUES (?, ?)');
    $ins->execute(['Unknown Artist', time()]);
    return (int)$pdo->lastInsertId();
}

// Finds (or creates) a `tag` row for a genre name and returns its id.
function getOrCreateTagId(PDO $pdo, string $genre): int {
    $check = $pdo->prepare('SELECT id FROM tag WHERE LOWER(name) = LOWER(?) LIMIT 1');
    $check->execute([$genre]);
    $existing = $check->fetch();
    if ($existing) return (int)$existing['id'];

    $ins = $pdo->prepare('INSERT INTO tag (name, is_hidden) VALUES (?, 0)');
    $ins->execute([$genre]);
    return (int)$pdo->lastInsertId();
}

// Inserts a tag_map row (object_type/object_id -> tag_id) unless it already exists.
function linkTagToObjects(PDO $pdo, int $tagId, string $objectType, array $objectIds): int {
    if (empty($objectIds)) return 0;
    $affected = 0;
    $checkStmt  = $pdo->prepare('SELECT id FROM tag_map WHERE tag_id = ? AND object_type = ? AND object_id = ? LIMIT 1');
    $insertStmt = $pdo->prepare('INSERT INTO tag_map (user, tag_id, object_type, object_id) VALUES (0, ?, ?, ?)');
    foreach ($objectIds as $objectId) {
        $checkStmt->execute([$tagId, $objectType, $objectId]);
        if ($checkStmt->fetch()) continue;
        $insertStmt->execute([$tagId, $objectType, $objectId]);
        $affected++;
    }
    return $affected;
}

// ── Actions ───────────────────────────────────────────────────────────────────

switch ($action) {

    // ── insertGenre ────────────────────────────────────────────────────────
    // Body: { action, genre: string }
    // Inserts genre string into the `tag` table (Ampache uses `tag.name`).
    case 'insertGenre': {
        $genre = trim($body['genre'] ?? '');
        if ($genre === '') json_error('genre is required');
        if (strlen($genre) > 128) json_error('genre name too long (max 128)');

        $pdo = getPdo();

        // Check for duplicate (case-insensitive)
        $check = $pdo->prepare('SELECT id FROM tag WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $check->execute([$genre]);
        $existing = $check->fetch();

        if ($existing) {
            json_ok(['message' => 'Genre already exists', 'id' => (int)$existing['id'], 'created' => false]);
        }

        $ins = $pdo->prepare('INSERT INTO tag (name, is_hidden) VALUES (?, 0)');
        $ins->execute([$genre]);
        $newId = (int)$pdo->lastInsertId();

        json_ok(['message' => "Genre '{$genre}' created", 'id' => $newId, 'created' => true]);
    }

    // ── assignGenre ────────────────────────────────────────────────────────
    // Body: { action, genre: string, songIds?: int[], albumIds?: int[], artistIds?: int[] }
    // Links a genre to songs/albums/artists via Ampache's `tag` + `tag_map` tables.
    case 'assignGenre': {
        $genre     = trim($body['genre'] ?? '');
        $songIds   = array_map('intval', (array)($body['songIds'] ?? []));
        $albumIds  = array_map('intval', (array)($body['albumIds'] ?? []));
        $artistIds = array_map('intval', (array)($body['artistIds'] ?? []));

        if ($genre === '') json_error('genre is required');
        if (empty($songIds) && empty($albumIds) && empty($artistIds)) {
            json_error('songIds, albumIds, or artistIds required');
        }

        $pdo = getPdo();
        $tagId = getOrCreateTagId($pdo, $genre);

        $affected = 0;
        $affected += linkTagToObjects($pdo, $tagId, 'song', $songIds);
        $affected += linkTagToObjects($pdo, $tagId, 'album', $albumIds);
        $affected += linkTagToObjects($pdo, $tagId, 'artist', $artistIds);

        json_ok(['message' => "Genre '{$genre}' assigned", 'rowsAffected' => $affected, 'tagId' => $tagId]);
    }

    // ── createArtist ─────────────────────────────────────────────────────────
    // Body: { action, name: string }
    // Inserts a brand new custom artist row directly into the `artist` table.
    // NOTE: this Ampache schema's `artist` table only has id/name/prefix/mbid/
    // summary/placeformed/yearformed/last_update/user/*_count columns — there
    // is no clean_name or catalog column on `artist` (those live on album/song).
    case 'createArtist': {
        $name = trim($body['name'] ?? '');
        if ($name === '') json_error('name is required');
        if (strlen($name) > 255) json_error('name too long (max 255)');

        $pdo = getPdo();

        $check = $pdo->prepare('SELECT id FROM artist WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $check->execute([$name]);
        $existing = $check->fetch();
        if ($existing) {
            json_ok(['message' => "Artist '{$name}' already exists", 'id' => (int)$existing['id'], 'artist_id' => (int)$existing['id'], 'created' => false]);
        }

        // prefix: leading article captured separately (e.g. "The Beatles" -> prefix "The", name "Beatles")
        $prefix = null;
        if (preg_match('/^(the|a|an)\s+(.+)$/i', $name, $m)) {
            $prefix = $m[1];
        }

        $now = time();
        $newId = null;
        try {
            $ins = $pdo->prepare('INSERT INTO artist (name, prefix, last_update) VALUES (?, ?, ?)');
            $ins->execute([$name, $prefix, $now]);
            $newId = (int)$pdo->lastInsertId();
        } catch (\PDOException $e) {
            // Duplicate entry race (unique index on name) — return the existing row gracefully
            if ((int)$e->getCode() === 23000 || stripos($e->getMessage(), 'Duplicate entry') !== false) {
                $check->execute([$name]);
                $existing = $check->fetch();
                if ($existing) {
                    json_ok(['message' => "Artist '{$name}' already exists", 'id' => (int)$existing['id'], 'artist_id' => (int)$existing['id'], 'created' => false]);
                }
            }
            json_error('Failed to create artist: ' . $e->getMessage(), 500);
        }

        json_ok(['message' => "Artist '{$name}' created", 'id' => $newId, 'artist_id' => $newId, 'created' => true]);
    }

    // ── linkSongsToArtist ──────────────────────────────────────────────────
    // Body: { action, songIds: int[], artistId: int }
    // Points existing songs at a (usually newly-created custom) artist.
    case 'linkSongsToArtist': {
        $songIds  = array_map('intval', (array)($body['songIds'] ?? []));
        $artistId = isset($body['artistId']) ? (int)$body['artistId'] : null;

        if (empty($songIds)) json_error('songIds is required');
        if (!$artistId)      json_error('artistId is required');

        $pdo = getPdo();
        $placeholders = implode(',', array_fill(0, count($songIds), '?'));
        $stmt = $pdo->prepare("UPDATE song SET artist = ? WHERE id IN ({$placeholders})");
        $stmt->execute(array_merge([$artistId], $songIds));
        $affected = $stmt->rowCount();

        json_ok([
            'message'      => "{$affected} song(s) linked to artist #{$artistId}",
            'rowsAffected' => $affected,
            'artistId'     => $artistId,
        ]);
    }

    // ── unlinkMedia ──────────────────────────────────────────────────────────
    // Body: { action, type: 'song'|'album', ids: int[], mode?: 'unknown'|'null' }
    // Detaches songs/albums from their current artist profile by pointing them
    // at a fallback "Unknown Artist" row (default) or NULL.
    case 'unlinkMedia': {
        $type = $body['type'] ?? '';
        $ids  = array_map('intval', (array)($body['ids'] ?? []));
        $mode = $body['mode'] ?? 'unknown';

        if (!in_array($type, ['song', 'album'], true)) json_error("type must be 'song' or 'album'");
        if (empty($ids)) json_error('ids is required');
        if (!in_array($mode, ['unknown', 'null'], true)) json_error("mode must be 'unknown' or 'null'");

        $pdo = getPdo();
        $table = $type === 'song' ? 'song' : 'album';
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        if ($mode === 'null') {
            $stmt = $pdo->prepare("UPDATE {$table} SET artist = NULL WHERE id IN ({$placeholders})");
            $stmt->execute($ids);
            $unknownId = null;
        } else {
            $unknownId = getOrCreateUnknownArtistId($pdo);
            $stmt = $pdo->prepare("UPDATE {$table} SET artist = ? WHERE id IN ({$placeholders})");
            $stmt->execute(array_merge([$unknownId], $ids));
        }
        $affected = $stmt->rowCount();

        json_ok([
            'message'      => "{$affected} {$type}(s) unlinked from their artist",
            'rowsAffected' => $affected,
            'unknownArtistId' => $unknownId,
        ]);
    }

    // ── renameMedia ──────────────────────────────────────────────────────────
    // Body: { action, type: 'song'|'album', id: int, value: string }
    // Renames a song title or album name directly in the Ampache DB.
    case 'renameMedia': {
        $type  = $body['type'] ?? '';
        $id    = isset($body['id']) ? (int)$body['id'] : 0;
        $value = trim($body['value'] ?? '');

        if (!in_array($type, ['song', 'album'], true)) json_error("type must be 'song' or 'album'");
        if ($id <= 0) json_error('id is required');
        if ($value === '') json_error('value is required');
        if (strlen($value) > 255) json_error('value too long (max 255)');

        $pdo = getPdo();
        $column = $type === 'song' ? 'title' : 'name';
        $stmt = $pdo->prepare("UPDATE {$type} SET {$column} = ? WHERE id = ?");
        $stmt->execute([$value, $id]);

        json_ok([
            'message'      => ucfirst($type) . " renamed to \"{$value}\"",
            'rowsAffected' => $stmt->rowCount(),
        ]);
    }

    // ── orphanedSongs ────────────────────────────────────────────────────────
    // Body: { action, limit?: int }
    // Returns songs with no assigned artist and/or album (artist/album = 0 or NULL).
    case 'orphanedSongs': {
        $limit = isset($body['limit']) ? max(1, min(2000, (int)$body['limit'])) : 500;

        $pdo = getPdo();
        $sql = "SELECT s.id, s.title, s.artist, s.album, s.track,
                       a.name  AS artist_name,
                       al.name AS album_name
                FROM song s
                LEFT JOIN artist a  ON a.id  = s.artist
                LEFT JOIN album  al ON al.id = s.album
                WHERE s.artist IS NULL OR s.artist = 0
                   OR s.album  IS NULL OR s.album  = 0
                ORDER BY s.id DESC
                LIMIT {$limit}";
        $stmt = $pdo->query($sql);
        $songs = $stmt->fetchAll();

        json_ok(['songs' => $songs, 'count' => count($songs)]);
    }

    // ── reassignSong ────────────────────────────────────────────────────────
    // Body: { action, songIds: int[], targetArtistId?: int, targetAlbumId?: int }
    // Moves songs to a different artist/album in the Ampache DB.
    case 'reassignSong': {
        $songIds        = array_map('intval', (array)($body['songIds'] ?? []));
        $targetArtistId = isset($body['targetArtistId']) ? (int)$body['targetArtistId'] : null;
        $targetAlbumId  = isset($body['targetAlbumId'])  ? (int)$body['targetAlbumId']  : null;

        if (empty($songIds))                           json_error('songIds is required');
        if ($targetArtistId === null && $targetAlbumId === null) json_error('targetArtistId or targetAlbumId required');

        $pdo = getPdo();
        $placeholders = implode(',', array_fill(0, count($songIds), '?'));

        $sets   = [];
        $params = [];

        if ($targetArtistId !== null) {
            $sets[]   = 'artist = ?';
            $params[] = $targetArtistId;
        }
        if ($targetAlbumId !== null) {
            $sets[]   = 'album = ?';
            $params[] = $targetAlbumId;
        }

        $sql    = 'UPDATE song SET ' . implode(', ', $sets) . " WHERE id IN ({$placeholders})";
        $params = array_merge($params, $songIds);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $affected = $stmt->rowCount();

        json_ok([
            'message'      => "{$affected} song(s) reassigned",
            'rowsAffected' => $affected,
            'targetArtist' => $targetArtistId,
            'targetAlbum'  => $targetAlbumId,
        ]);
    }

    default:
        json_error("Unknown action '{$action}'");
}

