<?php
/**
 * manage_content.php — Secure admin endpoint for custom genre management
 * and song/album reassignment against the Ampache MySQL database.
 *
 * Supported actions (POST only, JSON body):
 *   insertGenre    — Insert a new custom genre tag
 *   assignGenre    — Assign a genre string to songs / albums by ID list
 *   reassignSong   — Move song(s) to a different artist_id or album_id
 *
 * Auth: Requires X-Admin-Token header matching AMPACHE_ADMIN_API_KEY env var.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Token');

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

// ── Auth ──────────────────────────────────────────────────────────────────────

$expectedToken = getenv('AMPACHE_ADMIN_API_KEY') ?: '';
// Fallback: read from .env file adjacent to this script
if (!$expectedToken) {
    $envFile = __DIR__ . '/../modern-music-app/.env';
    if (file_exists($envFile)) {
        foreach (file($envFile) as $line) {
            if (preg_match('/^AMPACHE_ADMIN_API_KEY=(.+)$/', trim($line), $m)) {
                $expectedToken = trim($m[1]);
                break;
            }
        }
    }
}

$token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
if (!$expectedToken || !hash_equals($expectedToken, $token)) {
    json_error('Unauthorized', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('POST required', 405);
}

// ── Parse body ────────────────────────────────────────────────────────────────

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    json_error('Invalid JSON body');
}

$action = $body['action'] ?? '';

// ── DB Connection ─────────────────────────────────────────────────────────────

function getPdo(): PDO {
    $hosts = ['127.0.0.1', '10.247.192.231'];
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
    // Body: { action, genre: string, songIds?: int[], albumIds?: int[] }
    // Updates `song.genre` and `album.name` genre fields directly.
    case 'assignGenre': {
        $genre    = trim($body['genre'] ?? '');
        $songIds  = array_map('intval', (array)($body['songIds'] ?? []));
        $albumIds = array_map('intval', (array)($body['albumIds'] ?? []));

        if ($genre === '') json_error('genre is required');
        if (empty($songIds) && empty($albumIds)) json_error('songIds or albumIds required');

        $pdo = getPdo();
        $affected = 0;

        if (!empty($songIds)) {
            $placeholders = implode(',', array_fill(0, count($songIds), '?'));
            $stmt = $pdo->prepare("UPDATE song SET genre = ? WHERE id IN ({$placeholders})");
            $stmt->execute(array_merge([$genre], $songIds));
            $affected += $stmt->rowCount();
        }

        if (!empty($albumIds)) {
            $placeholders = implode(',', array_fill(0, count($albumIds), '?'));
            $stmt = $pdo->prepare("UPDATE album SET genre = ? WHERE id IN ({$placeholders})");
            $stmt->execute(array_merge([$genre], $albumIds));
            $affected += $stmt->rowCount();
        }

        json_ok(['message' => "Genre '{$genre}' assigned", 'rowsAffected' => $affected]);
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

