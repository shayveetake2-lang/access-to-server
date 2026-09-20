<?php
// modern-music-app/api_proxy.php — Public Registration Proxy for Ampache Subsonic Backend
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Helper to load configuration from .env
function getProxyEnv($key, $default = null) {
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                if (trim($name) === $key) return trim($value, " \"'");
            }
        }
    }
    return $default;
}

function getProxyPdo() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $hosts = ['127.0.0.1', '10.247.192.231'];
    $creds = [
        ['ampache_user', 'password'],
        ['root', 'root']
    ];

    foreach ($hosts as $h) {
        foreach ($creds as $c) {
            try {
                $pdo = new PDO("mysql:host={$h};port=8889;dbname=ampache;charset=utf8mb4", $c[0], $c[1], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 2
                ]);
                return $pdo;
            } catch (\Exception $e) {}
        }
    }
    return null;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? ($input['action'] ?? '');

// ── Playlist Visibility Toggle Endpoint (Guaranteed DB Persistence) ─────────
if ($action === 'togglePlaylistVisibility' || $action === 'updatePlaylistVisibility') {
    $rawId = $_GET['id'] ?? ($input['id'] ?? ($_GET['playlistId'] ?? ($input['playlistId'] ?? 0)));
    $cleanId = intval($rawId);
    if ($cleanId >= 800000000) {
        $cleanId = $cleanId % 100000000;
    }

    if ($cleanId <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Valid playlist ID required.']);
        exit;
    }

    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }

    $publicVal = $_GET['public'] ?? ($input['public'] ?? null);
    if ($publicVal === null) {
        // Auto-toggle current database state
        $q = $pdo->prepare("SELECT type FROM playlist WHERE id = :id LIMIT 1");
        $q->execute([':id' => $cleanId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        $currentType = $row['type'] ?? 'private';
        $isPublic = ($currentType !== 'public');
    } else {
        $isPublic = ($publicVal === true || $publicVal === 1 || $publicVal === '1' || $publicVal === 'true');
    }
    $typeStr = $isPublic ? 'public' : 'private';

    try {
        $stmt = $pdo->prepare("UPDATE playlist SET type = :typ WHERE id = :id");
        $stmt->execute([':typ' => $typeStr, ':id' => $cleanId]);

        echo json_encode([
            'status' => 'ok',
            'id' => $cleanId,
            'subsonicId' => (string)($cleanId + 800000000),
            'public' => $isPublic,
            'type' => $typeStr
        ]);
        exit;
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// ── Top 100 Songs Endpoint (Hardware-Optimized Daily & Trending Aggregation) ──
if ($action === 'getTopSongs') {
    $limit = intval($_GET['size'] ?? $_GET['count'] ?? ($input['size'] ?? 100));
    if ($limit < 1 || $limit > 200) $limit = 100;
    $period = strtolower(trim($_GET['period'] ?? ($input['period'] ?? 'daily')));
    if (!in_array($period, ['daily', 'weekly', 'alltime'])) {
        $period = 'daily';
    }

    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }

    try {
        $dailySince = time() - 86400; // Past 24 hours
        $weeklySince = time() - (7 * 86400); // Past 7 days

        if ($period === 'alltime') {
            $orderBy = "s.total_count DESC, s.played DESC, s.id DESC";
        } elseif ($period === 'weekly') {
            $orderBy = "COALESCE(oc_weekly.weekly_plays, 0) DESC, s.total_count DESC, s.id DESC";
        } else {
            // 'daily' default: rank primarily by streams in the past 24 hours across all users,
            // with a composite score blend so today's streamed songs surge to the top while keeping a full 100 chart.
            $orderBy = "(COALESCE(oc_daily.daily_plays, 0) * 1000 + COALESCE(oc_weekly.weekly_plays, 0) * 50 + s.total_count) DESC, s.played DESC, s.id DESC";
        }

        $sql = "
            SELECT s.id, s.title, s.time as duration, s.track, s.size, s.bitrate,
                   s.total_count as playCount,
                   COALESCE(oc_daily.daily_plays, 0) as dailyPlays,
                   COALESCE(oc_weekly.weekly_plays, 0) as weeklyPlays,
                   art.name as artist, art.id as artistId,
                   alb.name as album, alb.id as albumId
            FROM song s
            LEFT JOIN artist art ON s.artist = art.id
            LEFT JOIN album alb ON s.album = alb.id
            LEFT JOIN (
                SELECT object_id, COUNT(*) as daily_plays
                FROM object_count
                WHERE object_type = 'song' AND count_type = 'stream' AND date >= :daily_since
                GROUP BY object_id
            ) oc_daily ON oc_daily.object_id = s.id
            LEFT JOIN (
                SELECT object_id, COUNT(*) as weekly_plays
                FROM object_count
                WHERE object_type = 'song' AND count_type = 'stream' AND date >= :weekly_since
                GROUP BY object_id
            ) oc_weekly ON oc_weekly.object_id = s.id
            WHERE s.enabled = 1
            ORDER BY {$orderBy}
            LIMIT :lim
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':daily_since', $dailySince, PDO::PARAM_INT);
        $stmt->bindValue(':weekly_since', $weeklySince, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $songs = [];
        $rank = 1;
        foreach ($rows as $r) {
            $subId = (string)(300000000 + (int)$r['id']);
            $subAlbId = (string)(200000000 + (int)($r['albumId'] ?? 0));
            $subArtId = (string)(100000000 + (int)($r['artistId'] ?? 0));
            $songs[] = [
                'id' => $subId,
                'parent' => $subAlbId,
                'title' => $r['title'] ?: 'Unknown Track',
                'isDir' => false,
                'isVideo' => false,
                'type' => 'music',
                'albumId' => $subAlbId,
                'album' => $r['album'] ?: 'Unknown Album',
                'artistId' => $subArtId,
                'artist' => $r['artist'] ?: 'Unknown Artist',
                'coverArt' => 'al-' . $subAlbId,
                'duration' => (int)$r['duration'],
                'bitRate' => (int)($r['bitrate'] ? round($r['bitrate'] / 1000) : 320),
                'track' => (int)$r['track'],
                'size' => (int)$r['size'],
                'playCount' => (int)$r['playCount'],
                'dailyPlays' => (int)$r['dailyPlays'],
                'weeklyPlays' => (int)$r['weeklyPlays'],
                'rank' => $rank++,
                'contentType' => 'audio/mpeg',
                'suffix' => 'mp3'
            ];
        }

        echo json_encode([
            'status' => 'ok',
            'period' => $period,
            'count' => count($songs),
            'songs' => $songs
        ]);
        exit;
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// ── Playback Stream Logging Endpoint (Cross-User Daily Play Tracker) ─────────
if ($action === 'recordPlay' || $action === 'scrobble') {
    $rawSongId = $_GET['id'] ?? ($input['id'] ?? ($_GET['songId'] ?? ($input['songId'] ?? 0)));
    $cleanSongId = intval($rawSongId);
    if ($cleanSongId >= 300000000) {
        $cleanSongId = $cleanSongId % 100000000;
    }

    if ($cleanSongId <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Valid song ID required.']);
        exit;
    }

    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }

    $username = trim($_GET['u'] ?? ($input['u'] ?? ''));
    $userId = 1; // Default fallback to system primary user
    if (!empty($username)) {
        try {
            $uStmt = $pdo->prepare("SELECT id FROM user WHERE username = :u LIMIT 1");
            $uStmt->execute([':u' => $username]);
            $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
            if ($uRow && !empty($uRow['id'])) {
                $userId = (int)$uRow['id'];
            }
        } catch (\Exception $e) {}
    }

    try {
        $now = time();
        // 1. Insert stream event into Ampache's object_count table
        $ins = $pdo->prepare("
            INSERT INTO object_count (object_type, object_id, date, user, agent, count_type)
            VALUES ('song', :sid, :ts, :uid, 'Aether', 'stream')
        ");
        $ins->execute([
            ':sid' => $cleanSongId,
            ':ts' => $now,
            ':uid' => $userId
        ]);

        // 2. Increment song total_count and ensure played is marked true
        $upd = $pdo->prepare("
            UPDATE song 
            SET total_count = total_count + 1, played = 1
            WHERE id = :sid
        ");
        $upd->execute([':sid' => $cleanSongId]);

        echo json_encode([
            'status' => 'ok',
            'songId' => $cleanSongId,
            'user' => $userId,
            'timestamp' => $now
        ]);
        exit;
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// ── Pass-through to Metadata / Deduplication Engine ──────────────────────────
if (in_array($action, ['find_similar_artists', 'get_duplicates', 'merge_artists', 'search_artists', 'get_artist_items'])) {
    $mergeScript = __DIR__ . '/../api/merge_metadata.php';
    if (file_exists($mergeScript)) {
        require $mergeScript;
        exit;
    }
}

// ── Recently Added Endpoint (New Songs & New Albums) ───────────────────────
if ($action === 'getRecentlyAdded' || $action === 'getRecent') {
    $songLimit = intval($_GET['songLimit'] ?? $_GET['songCount'] ?? ($input['songLimit'] ?? 20));
    $albumLimit = intval($_GET['albumLimit'] ?? $_GET['albumCount'] ?? ($input['albumLimit'] ?? 16));
    if ($songLimit < 1 || $songLimit > 100) $songLimit = 20;
    if ($albumLimit < 1 || $albumLimit > 50) $albumLimit = 16;

    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }

    try {
        // 1. Fetch recent songs (ordered by newest addition_time)
        $stmtSongs = $pdo->prepare("
            SELECT s.id, s.title, s.time as duration, s.track, s.size, s.bitrate,
                   s.total_count as playCount, s.addition_time as additionTime,
                   art.name as artist, art.id as artistId,
                   alb.name as album, alb.id as albumId, alb.year as year
            FROM song s
            LEFT JOIN artist art ON s.artist = art.id
            LEFT JOIN album alb ON s.album = alb.id
            WHERE s.enabled = 1
            ORDER BY s.addition_time DESC, s.id DESC
            LIMIT :lim
        ");
        $stmtSongs->bindValue(':lim', $songLimit, PDO::PARAM_INT);
        $stmtSongs->execute();
        $songRows = $stmtSongs->fetchAll(PDO::FETCH_ASSOC);

        $recentSongs = [];
        foreach ($songRows as $r) {
            $subId = (string)(300000000 + (int)$r['id']);
            $subAlbId = (string)(200000000 + (int)($r['albumId'] ?? 0));
            $subArtId = (string)(100000000 + (int)($r['artistId'] ?? 0));
            $recentSongs[] = [
                'id' => $subId,
                'parent' => $subAlbId,
                'title' => $r['title'] ?: 'Unknown Track',
                'isDir' => false,
                'isVideo' => false,
                'type' => 'music',
                'albumId' => $subAlbId,
                'album' => $r['album'] ?: 'Unknown Album',
                'artistId' => $subArtId,
                'artist' => $r['artist'] ?: 'Unknown Artist',
                'coverArt' => 'al-' . $subAlbId,
                'duration' => (int)$r['duration'],
                'bitRate' => (int)($r['bitrate'] ? round($r['bitrate'] / 1000) : 320),
                'track' => (int)$r['track'],
                'size' => (int)$r['size'],
                'playCount' => (int)$r['playCount'],
                'created' => $r['additionTime'] ? date('c', (int)$r['additionTime']) : null,
                'year' => (int)$r['year'],
                'contentType' => 'audio/mpeg',
                'suffix' => 'mp3'
            ];
        }

        // 2. Fetch recent albums (ordered by newest addition_time)
        $stmtAlbums = $pdo->prepare("
            SELECT alb.id, alb.name, alb.year, alb.addition_time as additionTime,
                   art.name as artist, art.id as artistId,
                   COUNT(s.id) as songCount
            FROM album alb
            LEFT JOIN artist art ON alb.album_artist = art.id
            LEFT JOIN song s ON s.album = alb.id AND s.enabled = 1
            GROUP BY alb.id, alb.name, alb.year, alb.addition_time, art.name, art.id
            ORDER BY alb.addition_time DESC, alb.id DESC
            LIMIT :alim
        ");
        $stmtAlbums->bindValue(':alim', $albumLimit, PDO::PARAM_INT);
        $stmtAlbums->execute();
        $albumRows = $stmtAlbums->fetchAll(PDO::FETCH_ASSOC);

        $recentAlbums = [];
        foreach ($albumRows as $a) {
            $subAlbId = (string)(200000000 + (int)$a['id']);
            $subArtId = (string)(100000000 + (int)($a['artistId'] ?? 0));
            $recentAlbums[] = [
                'id' => $subAlbId,
                'name' => $a['name'] ?: 'Unknown Album',
                'title' => $a['name'] ?: 'Unknown Album',
                'artist' => $a['artist'] ?: 'Unknown Artist',
                'artistId' => $subArtId,
                'coverArt' => 'al-' . $subAlbId,
                'songCount' => (int)$a['songCount'],
                'year' => (int)$a['year'],
                'created' => $a['additionTime'] ? date('c', (int)$a['additionTime']) : null,
                'isDir' => true
            ];
        }

        echo json_encode([
            'status' => 'ok',
            'recentSongs' => $recentSongs,
            'recentAlbums' => $recentAlbums
        ]);
        exit;
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

if ($action === 'register') {
    // ── Rate Limiting (Thermal Guard & Anti-Abuse) ───────────────────────────
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $rateKey = 'reg_attempts_' . md5($ip);
    $attempts = $_SESSION[$rateKey] ?? ['count' => 0, 'first_attempt' => time()];
    
    if (time() - $attempts['first_attempt'] > 600) {
        $attempts = ['count' => 0, 'first_attempt' => time()];
    }
    
    if ($attempts['count'] >= 5) {
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => 'Too many registration attempts. Please wait a few minutes.']);
        exit;
    }
    
    $attempts['count']++;
    $_SESSION[$rateKey] = $attempts;

    // ── Input Validation ─────────────────────────────────────────────────────
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Username and password are required.']);
        exit;
    }

    if (!preg_match('/^[a-zA-Z0-9_\-\.]{3,32}$/', $username)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Username must be 3-32 characters (letters, numbers, underscores, dashes, dots).']);
        exit;
    }

    if (strlen($password) < 4) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 4 characters.']);
        exit;
    }

    $newUser = urlencode($username);
    $newPass = urlencode($password);
    $email = urlencode($username . "@local.host");
    
    // Subsonic admin account credentials configured via environment
    $admin_u = getProxyEnv('AMPACHE_ADMIN_USER', 'admin');
    $admin_p = getProxyEnv('AMPACHE_ADMIN_API_KEY', getProxyEnv('AMPACHE_ADMIN_PASS_HASH', '18e499b984c75ad09e233f6d8fe0228d'));
    
    if (empty($admin_p)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Ampache administrative API key not configured in environment.']);
        exit;
    }
    
    $candidates = [
        "http://127.0.0.1:8888/ampache/public/rest/index.php",
        "http://10.247.192.231:8888/ampache/public/rest/index.php",
        "http://127.0.0.1:8888/access-to-server/ampache/public/rest/index.php",
        "http://10.247.192.231:8888/access-to-server/ampache/public/rest/index.php"
    ];

    $response = false;
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);

    foreach ($candidates as $baseUrl) {
        $url = "{$baseUrl}?action=createUser&username={$newUser}&password={$newPass}&email={$email}&u={$admin_u}&p={$admin_p}&v=1.16.1&c=Aether&f=json";
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp !== false) {
            $parsed = @json_decode($resp, true);
            if (is_array($parsed) && isset($parsed['subsonic-response'])) {
                $response = $resp;
                break;
            }
        }
    }

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['status' => 'error', 'message' => 'Ampache backend service unreachable on port 8888.']);
        exit;
    }

    $parsed = @json_decode($response, true);
    if (($parsed['subsonic-response']['status'] ?? '') === 'ok') {
        // Automatically provision an apikey for the new user so Subsonic token auth succeeds
        $pdo = getProxyPdo();
        if ($pdo) {
            try {
                $userApiKey = md5($username . '_' . bin2hex(random_bytes(8)));
                $stmt = $pdo->prepare("UPDATE user SET apikey = :k WHERE username = :u AND (apikey IS NULL OR apikey = '')");
                $stmt->execute([':k' => $userApiKey, ':u' => $username]);
            } catch (\Exception $e) {}
        }
    }

    echo $response;
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
