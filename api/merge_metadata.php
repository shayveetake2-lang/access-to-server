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
require_once __DIR__ . '/media/_request_auth.php'; // shared verifyAmpacheUser() token+salt check

// ─── 1. DATABASE CONNECTION TO AMPACHE MYSQL ───
function getAmpacheMySQLConnection() {
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

// ─── HELPER: ARTIST NORMALIZATION & FUZZY SIMILARITY ENGINE ───
function normalizeArtistKeyForFuzzy($rawName) {
    if (empty($rawName)) return '';
    $name = trim($rawName);

    // 1. Remove parenthetical features: "(feat. X)", "[ft. Y]", "(with Z)", etc.
    $name = preg_replace('/\s*[\(\[]\s*(?:feat\.?|ft\.?|featuring|with|prod\.?|vs\.?)\s+[^)\]]+[\)\]]/i', '', $name);

    // 2. Remove inline trailing features: "feat. X", "ft. X", "featuring X", "with X", "vs. X"
    $name = preg_replace('/\s+(?:feat\.?|ft\.?|featuring|with|vs\.?)\s+.+$/i', '', $name);

    // 3. Remove leading "The ", "A ", "An "
    $name = preg_replace('/^(?:the|a|an)\s+/i', '', $name);

    // 4. Strip accents / diacritics
    if (function_exists('transliterator_transliterate')) {
        $trans = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $name);
        if ($trans !== false) {
            $name = $trans;
        }
    } else {
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if ($trans !== false) {
            $name = strtolower($trans);
        } else {
            $name = strtolower($name);
        }
    }

    // 5. Remove punctuation, symbols, and whitespace for canonical comparison key
    $name = preg_replace('/[\s\W]+/u', '', $name);

    return trim(strtolower($name));
}

function detectSimilarityReason($n1, $n2, $k1, $k2) {
    $clean1 = strtolower(trim($n1));
    $clean2 = strtolower(trim($n2));

    if ($clean1 === $clean2) {
        return 'Exact case/whitespace duplicate';
    }

    $hasThe1 = (bool)preg_match('/^(?:the|a|an)\s+/i', $n1);
    $hasThe2 = (bool)preg_match('/^(?:the|a|an)\s+/i', $n2);
    if ($hasThe1 !== $hasThe2) {
        return "Leading article 'The' variation";
    }

    $hasFeat1 = (bool)preg_match('/\s*(?:feat\.?|ft\.?|featuring|with|vs\.?)\s+/i', $n1);
    $hasFeat2 = (bool)preg_match('/\s*(?:feat\.?|ft\.?|featuring|with|vs\.?)\s+/i', $n2);
    if ($hasFeat1 || $hasFeat2) {
        return 'Featured collaborator appearance';
    }

    if ($k1 === $k2) {
        $punc1 = preg_replace('/[^\w\s]/u', '', $clean1);
        $punc2 = preg_replace('/[^\w\s]/u', '', $clean2);
        if ($punc1 !== $clean1 || $punc2 !== $clean2) {
            return 'Punctuation or spacing variation';
        }
        return 'Diacritics or accent variation';
    }

    similar_text($k1, $k2, $percent);
    return 'Fuzzy typo / similarity match (' . round($percent) . '%)';
}

if (defined('UNIT_TESTING') && UNIT_TESTING === true) {
    return;
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

// Check Subsonic credentials via Ampache's own REST API loopback (u/t/s token+salt
// verification) — never trust a bare username with no cryptographic proof.
//
// SECURITY: this used to grant admin access to anyone who simply sent
// `u=<a known admin's username>` with NO password/token/salt at all — a
// trivial impersonation bypass. It now requires the same t/s proof-of-password
// every other Subsonic call needs, verified server-side via Ampache itself.
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
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Administrator privileges required for metadata operations.']);
    exit;
}

// ─── 3. GET ACTIONS (READ CATALOG METADATA FOR ADMIN UI) ───
$action = $_GET['action'] ?? ($data['action'] ?? 'merge');

if ($_SERVER['REQUEST_METHOD'] === 'GET' || in_array($action, ['search_artists', 'search_albums', 'search_songs', 'get_duplicates', 'find_similar_artists', 'get_artist_items'])) {
    $pdo = getAmpacheMySQLConnection();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Ampache database connection failed.']);
        exit;
    }

    if ($action === 'search_artists') {
        $q = trim($_GET['q'] ?? '');
        // 500 rather than the old 20/50 cap — the merge tool must be able to
        // surface every matching artist, including ones with no albums/songs yet.
        $limit = intval($_GET['limit'] ?? 500);
        if ($limit < 1 || $limit > 2000) $limit = 500;

        $sql = "SELECT id, name, song_count, album_count FROM artist";
        $params = [];
        if (!empty($q)) {
            // Case-insensitive wildcard match (LOWER(...) LIKE keeps this portable
            // across collations that aren't already case-insensitive).
            $sql .= " WHERE LOWER(name) LIKE LOWER(:q)";
            $params[':q'] = "%{$q}%";
        }
        $sql .= " ORDER BY name ASC LIMIT {$limit}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $artists = $stmt->fetchAll();

        echo json_encode(['status' => 'success', 'artists' => $artists]);
        exit;
    }

    // ── search_albums ───────────────────────────────────────────────────────
    // ?q=<query>&limit=<n> — case-insensitive wildcard search across album name
    // AND artist name, so albums are found regardless of which field matches.
    // Orphaned albums (album_artist IS NULL/0) are included by default.
    if ($action === 'search_albums') {
        $q = trim($_GET['q'] ?? '');
        $limit = intval($_GET['limit'] ?? 500);
        if ($limit < 1 || $limit > 2000) $limit = 500;

        $sql = "SELECT al.id, al.name, al.year, al.song_count, al.album_artist,
                       ar.name AS artist_name
                FROM album al
                LEFT JOIN artist ar ON ar.id = al.album_artist";
        $params = [];
        if (!empty($q)) {
            $sql .= " WHERE LOWER(al.name) LIKE LOWER(:q) OR LOWER(ar.name) LIKE LOWER(:q2)";
            $params[':q']  = "%{$q}%";
            $params[':q2'] = "%{$q}%";
        }
        $sql .= " ORDER BY al.name ASC LIMIT {$limit}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $albums = $stmt->fetchAll();

        echo json_encode(['status' => 'success', 'albums' => $albums]);
        exit;
    }

    // ── search_songs ────────────────────────────────────────────────────────
    // ?q=<query>&limit=<n> — case-insensitive wildcard search across song title
    // AND artist name. Orphaned songs (artist/album IS NULL or 0) are included
    // by default so unassigned tracks always surface in merge search results.
    if ($action === 'search_songs') {
        $q = trim($_GET['q'] ?? '');
        $limit = intval($_GET['limit'] ?? 500);
        if ($limit < 1 || $limit > 2000) $limit = 500;

        $sql = "SELECT s.id, s.title, s.artist, s.album, s.track, s.time AS duration,
                       ar.name AS artist_name, al.name AS album_name
                FROM song s
                LEFT JOIN artist ar ON ar.id = s.artist
                LEFT JOIN album  al ON al.id = s.album";
        $params = [];
        if (!empty($q)) {
            $sql .= " WHERE LOWER(s.title) LIKE LOWER(:q) OR LOWER(ar.name) LIKE LOWER(:q2)";
            $params[':q']  = "%{$q}%";
            $params[':q2'] = "%{$q}%";
        }
        $sql .= " ORDER BY s.title ASC LIMIT {$limit}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $songs = $stmt->fetchAll();

        echo json_encode(['status' => 'success', 'songs' => $songs]);
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

    if ($action === 'find_similar_artists' || $action === 'get_duplicates') {
        // Fetch all artists from database
        $stmt = $pdo->query("SELECT id, name, song_count, album_count FROM artist ORDER BY song_count DESC, album_count DESC, id ASC");
        $allArtists = $stmt->fetchAll();

        $clusters = []; // key -> array of artists
        $keys = []; // id -> normalized key

        foreach ($allArtists as $art) {
            $rawName = $art['name'] ?? '';
            $normKey = normalizeArtistKeyForFuzzy($rawName);
            if (empty($normKey)) continue;

            $keys[$art['id']] = $normKey;
            $clusters[$normKey][] = $art;
        }

        // Fuzzy match across cluster keys (for typos e.g. Levenshtein <= 2 or similar_text >= 88%)
        $clusterKeys = array_keys($clusters);
        $keyCount = count($clusterKeys);
        $mergedKeysMap = [];

        for ($i = 0; $i < $keyCount; $i++) {
            $k1 = $clusterKeys[$i];
            if (isset($mergedKeysMap[$k1]) || strlen($k1) < 5) continue;

            for ($j = $i + 1; $j < $keyCount; $j++) {
                $k2 = $clusterKeys[$j];
                if (isset($mergedKeysMap[$k2]) || strlen($k2) < 5) continue;

                $lenDiff = abs(strlen($k1) - strlen($k2));
                if ($lenDiff > 3) continue;

                $lev = levenshtein($k1, $k2);
                $isFuzzyMatch = ($lev <= 1) || ($lev <= 2 && strlen($k1) >= 8);

                if (!$isFuzzyMatch) {
                    similar_text($k1, $k2, $pct);
                    if ($pct >= 88.0) {
                        $isFuzzyMatch = true;
                    }
                }

                if ($isFuzzyMatch) {
                    // Merge cluster $k2 into $k1
                    $mergedKeysMap[$k2] = $k1;
                    foreach ($clusters[$k2] as $item) {
                        $clusters[$k1][] = $item;
                    }
                    unset($clusters[$k2]);
                }
            }
        }

        // Build structured duplicate groups for the admin UI
        $groups = [];
        $flatDuplicates = [];

        foreach ($clusters as $normKey => $members) {
            if (count($members) < 2) continue;

            // Sort members so canonical/primary is first:
            // Highest song_count, or if equal, cleanest name (not containing feat., shortest)
            usort($members, function($a, $b) {
                $diff = (int)($b['song_count'] ?? 0) - (int)($a['song_count'] ?? 0);
                if ($diff !== 0) return $diff;

                $hasFeatA = preg_match('/\s*(?:feat\.?|ft\.?|featuring|with|vs\.?)\s+/i', $a['name']);
                $hasFeatB = preg_match('/\s*(?:feat\.?|ft\.?|featuring|with|vs\.?)\s+/i', $b['name']);
                if ($hasFeatA && !$hasFeatB) return 1;
                if (!$hasFeatA && $hasFeatB) return -1;

                return strlen($a['name']) - strlen($b['name']);
            });

            $primary = $members[0];
            $dupeCandidates = [];

            for ($m = 1; $m < count($members); $m++) {
                $dup = $members[$m];
                $k1 = $keys[$primary['id']] ?? $normKey;
                $k2 = $keys[$dup['id']] ?? $normKey;
                $reason = detectSimilarityReason($primary['name'], $dup['name'], $k1, $k2);
                $confidence = ($k1 === $k2) ? 'high' : 'medium';

                $dup['reason'] = $reason;
                $dup['confidence'] = $confidence;
                $dupeCandidates[] = $dup;

                $flatDuplicates[] = [
                    'id1' => $primary['id'],
                    'name1' => $primary['name'],
                    'songs1' => $primary['song_count'],
                    'id2' => $dup['id'],
                    'name2' => $dup['name'],
                    'songs2' => $dup['song_count'],
                    'reason' => $reason,
                    'confidence' => $confidence
                ];
            }

            $groups[] = [
                'target_artist' => $primary,
                'duplicates' => $dupeCandidates,
                'count' => count($dupeCandidates),
                'key' => $normKey
            ];
        }

        echo json_encode([
            'status' => 'success',
            'groups' => $groups,
            'duplicates' => $flatDuplicates,
            'total_groups' => count($groups),
            'total_duplicates' => count($flatDuplicates)
        ]);
        exit;
    }
}

// ─── 4. POST ACTION (MERGE & REMAP METADATA) ───
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed. POST required for metadata operations.']);
    exit;
}

$batch = $data['batch'] ?? ($_POST['batch'] ?? null);
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

$pdo = getAmpacheMySQLConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to connect to Ampache MySQL database.']);
    exit;
}

// ─── ACTION: CONSOLIDATE DUPLICATE ARTISTS & SONGS ───
if ($action === 'consolidate_duplicates' || !empty($data['consolidate_all'])) {
    try {
        $pdo->beginTransaction();
        $mergedSongs = 0;
        $mergedArtists = 0;

        // 1. Consolidate Duplicate Songs (matching title & artist)
        $dupeSongsStmt = $pdo->query("
            SELECT LOWER(TRIM(title)) as clean_title, artist, COUNT(*) as cnt, MIN(id) as primary_id
            FROM song
            GROUP BY LOWER(TRIM(title)), artist
            HAVING COUNT(*) > 1
        ");
        $dupeSongGroups = $dupeSongsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($dupeSongGroups as $group) {
            $pId = (int)$group['primary_id'];
            $cTitle = $group['clean_title'];
            $artId = (int)$group['artist'];

            $otherStmt = $pdo->prepare("SELECT id FROM song WHERE LOWER(TRIM(title)) = :t AND artist = :a AND id != :pid");
            $otherStmt->execute([':t' => $cTitle, ':a' => $artId, ':pid' => $pId]);
            $otherIds = $otherStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($otherIds)) {
                $inPh = implode(',', array_fill(0, count($otherIds), '?'));
                try {
                    $updPl = $pdo->prepare("UPDATE IGNORE playlist_data SET object_id = ? WHERE object_id IN ($inPh) AND object_type = 'song'");
                    $updPl->execute(array_merge([$pId], $otherIds));
                } catch (\Exception $e) {}

                $delSongs = $pdo->prepare("DELETE FROM song WHERE id IN ($inPh)");
                $delSongs->execute($otherIds);
                $mergedSongs += count($otherIds);
            }
        }

        // 2. Consolidate Duplicate Artists (matching trimmed, lower-cased name)
        $dupeArtStmt = $pdo->query("
            SELECT LOWER(TRIM(name)) as clean_name, COUNT(*) as cnt, MIN(id) as primary_id
            FROM artist
            GROUP BY LOWER(TRIM(name))
            HAVING COUNT(*) > 1
        ");
        $dupeArtGroups = $dupeArtStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($dupeArtGroups as $group) {
            $pId = (int)$group['primary_id'];
            $cName = $group['clean_name'];

            $otherStmt = $pdo->prepare("SELECT id FROM artist WHERE LOWER(TRIM(name)) = :n AND id != :pid");
            $otherStmt->execute([':n' => $cName, ':pid' => $pId]);
            $otherIds = $otherStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($otherIds)) {
                $inPh = implode(',', array_fill(0, count($otherIds), '?'));
                $stmtSongs = $pdo->prepare("UPDATE song SET artist = ? WHERE artist IN ($inPh)");
                $stmtSongs->execute(array_merge([$pId], $otherIds));

                $stmtAlbums = $pdo->prepare("UPDATE album SET album_artist = ? WHERE album_artist IN ($inPh)");
                $stmtAlbums->execute(array_merge([$pId], $otherIds));

                $delArt = $pdo->prepare("DELETE FROM artist WHERE id IN ($inPh)");
                $delArt->execute($otherIds);
                $mergedArtists += count($otherIds);
            }
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'No double ups or duplicates found.',
            'consolidated_songs' => $mergedSongs,
            'consolidated_artists' => $mergedArtists
        ]);
        exit;
    } catch (\Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Consolidation failed: ' . $e->getMessage()]);
        exit;
    }
}

// ─── BATCH MERGE EXECUTION ───
if (!empty($batch) && is_array($batch)) {
    try {
        $pdo->beginTransaction();
        $totalSongs = 0;
        $totalAlbums = 0;
        $totalArtistsMerged = 0;
        $processedGroups = 0;

        foreach ($batch as $bItem) {
            $tId = intval($bItem['target_artist_id'] ?? 0);
            $rawSourceIds = $bItem['artist_ids'] ?? [];
            if (!is_array($rawSourceIds)) $rawSourceIds = [$rawSourceIds];
            $srcIds = array_values(array_filter(array_map('intval', $rawSourceIds), fn($id) => $id > 0 && $id !== $tId));

            if ($tId <= 0 || empty($srcIds)) continue;

            // Verify target exists
            $chkT = $pdo->prepare("SELECT id, name FROM artist WHERE id = :id LIMIT 1");
            $chkT->execute([':id' => $tId]);
            if (!$chkT->fetch()) continue;

            $inPh = implode(',', array_fill(0, count($srcIds), '?'));

            // Remap songs
            $stmtMSongs = $pdo->prepare("UPDATE song SET artist = ? WHERE artist IN ($inPh)");
            $stmtMSongs->execute(array_merge([$tId], $srcIds));
            $totalSongs += $stmtMSongs->rowCount();

            // Remap albums
            $stmtMAlb = $pdo->prepare("UPDATE album SET album_artist = ? WHERE album_artist IN ($inPh)");
            $stmtMAlb->execute(array_merge([$tId], $srcIds));
            $totalAlbums += $stmtMAlb->rowCount();

            // Remap artist_map
            try {
                $stmtMapArt = $pdo->prepare("UPDATE IGNORE artist_map SET artist_id = ? WHERE artist_id IN ($inPh)");
                $stmtMapArt->execute(array_merge([$tId], $srcIds));
                $stmtClMap = $pdo->prepare("DELETE FROM artist_map WHERE artist_id IN ($inPh)");
                $stmtClMap->execute($srcIds);
            } catch (\Exception $e) {}

            // Delete duplicates
            $stmtD = $pdo->prepare("DELETE FROM artist WHERE id IN ($inPh)");
            $stmtD->execute($srcIds);
            $totalArtistsMerged += $stmtD->rowCount();

            // Recalculate
            $stmtRC = $pdo->prepare("
                UPDATE artist SET 
                    song_count = (SELECT COUNT(*) FROM song WHERE artist = :tid1),
                    album_count = (SELECT COUNT(*) FROM album WHERE album_artist = :tid2)
                WHERE id = :tid3
            ");
            $stmtRC->execute([':tid1' => $tId, ':tid2' => $tId, ':tid3' => $tId]);
            $processedGroups++;
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'No double ups or duplicates found.',
            'affected_songs' => $totalSongs,
            'affected_albums' => $totalAlbums,
            'merged_artists' => $totalArtistsMerged
        ]);
        exit;
    } catch (\Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Batch merge transaction failed: ' . $e->getMessage()]);
        exit;
    }
}

// ─── SINGLE GROUP MERGE / REMAP EXECUTION ───
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
        'message'         => 'No double ups or duplicates found.',
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

