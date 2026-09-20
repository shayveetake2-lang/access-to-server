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

// ── Top 100 Songs Endpoint (Hardware-Optimized Query) ───────────────────────
if ($action === 'getTopSongs') {
    $limit = intval($_GET['size'] ?? $_GET['count'] ?? ($input['size'] ?? 100));
    if ($limit < 1 || $limit > 200) $limit = 100;

    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT s.id, s.title, s.time as duration, s.track, s.size, s.bitrate,
                   s.total_count as playCount,
                   art.name as artist, art.id as artistId,
                   alb.name as album, alb.id as albumId
            FROM song s
            LEFT JOIN artist art ON s.artist = art.id
            LEFT JOIN album alb ON s.album = alb.id
            WHERE s.enabled = 1
            ORDER BY s.total_count DESC, s.played DESC, s.id DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $songs = [];
        foreach ($rows as $r) {
            $subId = '30000' . str_pad((string)$r['id'], 4, '0', STR_PAD_LEFT);
            $subAlbId = '20000' . str_pad((string)($r['albumId'] ?? 0), 4, '0', STR_PAD_LEFT);
            $subArtId = '10000' . str_pad((string)($r['artistId'] ?? 0), 4, '0', STR_PAD_LEFT);
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
                'contentType' => 'audio/mpeg',
                'suffix' => 'mp3'
            ];
        }

        echo json_encode([
            'status' => 'ok',
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
