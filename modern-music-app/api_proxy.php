<?php
// modern-music-app/api_proxy.php — Public Registration Proxy for Ampache Subsonic Backend

// CRITICAL PERFORMANCE FIX: Skip session_start() for media requests.
// PHP sessions use file locking — only ONE request at a time can hold the lock.
// With 100+ concurrent getCoverArt requests, they serialize and queue for minutes
// on the 2011 MacBook Pro. Media endpoints don't need sessions at all.
$_action = $_GET['action'] ?? '';
$_mediaActions = ['getCoverArt', 'coverArt', 'stream', 'download', 'search', 'search2', 'search3'];
if (!in_array($_action, $_mediaActions)) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

header('Content-Type: application/json');

// Restricted CORS Allowlist
$allowedOriginsList = [
    'https://serverflow.icu',
    'https://www.serverflow.icu',
    'http://localhost:8888',
    'http://127.0.0.1:8888',
    'http://10.247.192.231:8888',
    'http://localhost:5173',
    'http://127.0.0.1:5173'
];
$envOrigins = getenv('CORS_ALLOWED_ORIGINS');
if (!empty($envOrigins)) {
    $extraOrigins = array_map('trim', explode(',', $envOrigins));
    $allowedOriginsList = array_merge($allowedOriginsList, $extraOrigins);
}

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$corsOrigin = null;

if (!empty($origin)) {
    $originTrimmed = rtrim($origin, '/');
    $originHost = parse_url($origin, PHP_URL_HOST);
    $isAllowed = false;
    foreach ($allowedOriginsList as $allowed) {
        $allowedTrimmed = rtrim($allowed, '/');
        if (strcasecmp($originTrimmed, $allowedTrimmed) === 0 || strcasecmp($originHost, $allowed) === 0) {
            $isAllowed = true;
            break;
        }
    }
    if (!$isAllowed && $originHost) {
        if (strncmp($originHost, '10.', 3) === 0 ||
            strncmp($originHost, '192.168.', 8) === 0 ||
            strncmp($originHost, '172.', 4) === 0 ||
            $originHost === 'localhost' ||
            $originHost === '127.0.0.1') {
            $isAllowed = true;
        }
    }
    if ($isAllowed) {
        $corsOrigin = $origin;
    }
}

if ($corsOrigin !== null) {
    header('Access-Control-Allow-Origin: ' . $corsOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Range, X-Requested-With');
header('Access-Control-Expose-Headers: Content-Range, Accept-Ranges, Content-Length');
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

// Only load heavyweight config.php for non-media requests.
// Media requests (getCoverArt, stream) only need getProxyPdo() which reads
// ampache.cfg.php directly — skipping config.php avoids SQLite setup overhead.
if (!in_array($_action, $_mediaActions)) {
    require_once __DIR__ . '/../config/config.php';
}

function getProxyPdo() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    static $cachedDsn = null;
    static $cachedUser = null;
    static $cachedPass = null;

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 2,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];

    if ($cachedDsn !== null) {
        try {
            $pdo = new PDO($cachedDsn, $cachedUser, $cachedPass, $options);
            return $pdo;
        } catch (\Exception $e) {
            $cachedDsn = null;
        }
    }

    $cfgFile = __DIR__ . '/../ampache/config/ampache.cfg.php';
    $cfg = file_exists($cfgFile) ? @parse_ini_file($cfgFile) : [];

    $host = !empty($cfg['database_hostname']) ? $cfg['database_hostname'] : (defined('AMPACHE_DB_HOST') ? AMPACHE_DB_HOST : getProxyEnv('AMPACHE_DB_HOST', '127.0.0.1'));
    $port = !empty($cfg['database_port']) ? $cfg['database_port'] : (defined('AMPACHE_DB_PORT') ? AMPACHE_DB_PORT : getProxyEnv('AMPACHE_DB_PORT', '8889'));
    $dbname = !empty($cfg['database_name']) ? $cfg['database_name'] : (defined('AMPACHE_DB_NAME') ? AMPACHE_DB_NAME : getProxyEnv('AMPACHE_DB_NAME', 'ampache'));

    $hosts = array_unique(array_filter([
        $host,
        '127.0.0.1',
        'localhost'
    ]));
    $ports = array_unique(array_filter([
        $port,
        '8889',
        '3306',
        '3307'
    ]));

    $configuredPass = defined('AMPACHE_DB_PASS') ? AMPACHE_DB_PASS : getProxyEnv('AMPACHE_DB_PASS', 'ServerAppSecurePass2026!');
    $configuredUser = defined('AMPACHE_DB_USER') ? AMPACHE_DB_USER : getProxyEnv('AMPACHE_DB_USER', 'server_app');

    $creds = [
        [$configuredUser, $configuredPass],
        [!empty($cfg['database_username']) ? $cfg['database_username'] : 'server_app', !empty($cfg['database_password']) ? $cfg['database_password'] : 'password'],
        ['server_app', 'password'],
        ['ampache_user', 'password'],
    ];

    foreach ($hosts as $h) {
        foreach ($ports as $p) {
            foreach ($creds as $cred) {
                $u = $cred[0];
                $pwd = $cred[1];
                $dsn = "mysql:host={$h};port={$p};dbname={$dbname};charset=utf8mb4";
                try {
                    $pdo = new PDO($dsn, $u, $pwd, $options);
                    $cachedDsn = $dsn;
                    $cachedUser = $u;
                    $cachedPass = $pwd;
                    return $pdo;
                } catch (\Exception $e) {}
            }
        }
    }

    $socket = '/Applications/MAMP/tmp/mysql/mysql.sock';
    if (file_exists($socket)) {
        foreach ($creds as $cred) {
            $u = $cred[0];
            $pwd = $cred[1];
            $dsn = "mysql:unix_socket={$socket};dbname={$dbname};charset=utf8mb4";
            try {
                $pdo = new PDO($dsn, $u, $pwd, $options);
                $cachedDsn = $dsn;
                $cachedUser = $u;
                $cachedPass = $pwd;
                return $pdo;
            } catch (\Exception $e) {}
        }
    }

    return null;
}

function getEmbeddedArtwork($pdo, string $objectType, int $objectId): ?array {
    static $artworkCache = [];
    $cacheKey = $objectType . ':' . $objectId;
    if (array_key_exists($cacheKey, $artworkCache)) {
        return $artworkCache[$cacheKey];
    }

    if (!$pdo || $objectId <= 0) {
        return null;
    }

    $sql = $objectType === 'song'
        ? "SELECT file FROM song WHERE id = :id LIMIT 1"
        : "SELECT file FROM song WHERE enabled = 1 AND " .
            ($objectType === 'album' ? 'album' : 'artist') . " = :id ORDER BY id ASC LIMIT 1";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $objectId]);
        $filePath = $stmt->fetchColumn();
        if (!$filePath || !is_readable($filePath)) {
            return $artworkCache[$cacheKey] = null;
        }

        $autoload = __DIR__ . '/../ampache/vendor/autoload.php';
        if (!class_exists('getID3') && file_exists($autoload)) {
            require_once $autoload;
        }
        if (!class_exists('getID3')) {
            return $artworkCache[$cacheKey] = null;
        }

        $reader = new getID3();
        $metadata = $reader->analyze($filePath);
        $format = strtolower((string)($metadata['fileformat'] ?? ''));
        $pictures = $format === 'flac' || $format === 'ogg'
            ? ($metadata['flac']['PICTURE'] ?? [])
            : ($metadata['id3v2']['APIC'] ?? []);
        $picture = is_array($pictures) && isset($pictures[0]) ? $pictures[0] : null;
        $data = $picture['data'] ?? null;
        if (!is_string($data) || $data === '') {
            return $artworkCache[$cacheKey] = null;
        }

        $mime = $picture['image_mime'] ?? ($picture['mime'] ?? 'image/jpeg');
        return $artworkCache[$cacheKey] = ['image' => $data, 'mime' => $mime];
    } catch (\Throwable $e) {
        error_log('[Aether] Embedded artwork lookup failed: ' . $e->getMessage());
        return $artworkCache[$cacheKey] = null;
    }
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? ($input['action'] ?? '');

// ── Audio Streaming Endpoint (Hardware-Accelerated Seekable Stream with HTTP 206) ──
if ($action === 'stream' || $action === 'download') {
    $rawSongId = $_GET['id'] ?? ($input['id'] ?? ($_GET['songId'] ?? ($input['songId'] ?? 0)));
    $cleanSongId = intval(preg_replace('/^[^0-9]+/', '', (string)$rawSongId));
    if ($cleanSongId >= 300000000) {
        $cleanSongId = $cleanSongId % 100000000;
    } elseif ($cleanSongId >= 100000000) {
        $cleanSongId = $cleanSongId % 100000000;
    }

    if ($cleanSongId <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Valid song ID required for streaming.']);
        exit;
    }

    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT file, size, bitrate, time, title FROM song WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $cleanSongId]);
    $song = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$song || empty($song['file'])) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Song not found in library.']);
        exit;
    }

    $filePath = $song['file'];
    if (!file_exists($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Audio file missing from server storage disk.']);
        exit;
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimes = [
        'mp3' => 'audio/mpeg',
        'flac' => 'audio/flac',
        'm4a' => 'audio/mp4',
        'aac' => 'audio/aac',
        'ogg' => 'audio/ogg',
        'oga' => 'audio/ogg',
        'opus' => 'audio/opus',
        'wav' => 'audio/wav',
        'wma' => 'audio/x-ms-wma'
    ];
    $mime = $mimes[$ext] ?? 'audio/mpeg';

    $size = filesize($filePath);
    $start = 0;
    $end = $size - 1;
    $isRange = false;

    if (isset($_SERVER['HTTP_RANGE'])) {
        $range = trim($_SERVER['HTTP_RANGE']);
        if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $range, $matches)) {
            $start = intval($matches[1]);
            if (!empty($matches[2])) {
                $end = intval($matches[2]);
            }
            if ($start > $end || $start >= $size) {
                while (ob_get_level()) { ob_end_clean(); }
                header('HTTP/1.1 416 Range Not Satisfiable');
                header("Content-Range: bytes */$size");
                exit;
            }
            $isRange = true;
        }
    }

    $length = $end - $start + 1;
    while (ob_get_level()) { ob_end_clean(); }

    if ($isRange) {
        header('HTTP/1.1 206 Partial Content');
        header("Content-Range: bytes $start-$end/$size");
    } else {
        header('HTTP/1.1 200 OK');
    }

    header('Content-Type: ' . $mime, true);
    header('Content-Length: ' . $length, true);
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=604800');

    $fp = @fopen($filePath, 'rb');
    if ($fp) {
        if ($start > 0) {
            fseek($fp, $start);
        }
        $bytesToSend = $length;
        $bufferSize = 64 * 1024;
        while (!feof($fp) && $bytesToSend > 0 && (connection_status() === 0)) {
            $readLength = ($bytesToSend > $bufferSize) ? $bufferSize : $bytesToSend;
            $data = fread($fp, $readLength);
            if ($data === false) break;
            echo $data;
            flush();
            $bytesToSend -= strlen($data);
        }
        fclose($fp);
    }
    exit;
}

// ── Cover Art Endpoint (Instant Direct MySQL Binary Blob with SVG Fallback) ──
if ($action === 'getCoverArt' || $action === 'coverArt') {
    $rawId = trim($_GET['id'] ?? ($_GET['coverArt'] ?? ($input['id'] ?? '')));
    $typeHint = null;
    if (strpos($rawId, 'al-') === 0) {
        $typeHint = 'album';
        $num = intval(substr($rawId, 3));
    } elseif (strpos($rawId, 'ar-') === 0) {
        $typeHint = 'artist';
        $num = intval(substr($rawId, 3));
    } elseif (strpos($rawId, 'sg-') === 0) {
        $typeHint = 'song';
        $num = intval(substr($rawId, 3));
    } else {
        $num = intval($rawId);
    }

    if ($num >= 300000000) {
        if (!$typeHint) $typeHint = 'song';
        $cleanId = $num % 100000000;
    } elseif ($num >= 200000000) {
        if (!$typeHint) $typeHint = 'album';
        $cleanId = $num % 100000000;
    } elseif ($num >= 100000000) {
        if (!$typeHint) $typeHint = 'artist';
        $cleanId = $num % 100000000;
    } else {
        $cleanId = $num;
    }

    $pdo = getProxyPdo();
    $imgRow = null;

    if ($pdo && $cleanId > 0) {
        try {
            if ($typeHint === 'album') {
                $stmt = $pdo->prepare("SELECT image, mime FROM image WHERE object_type = 'album' AND object_id = :id ORDER BY id DESC LIMIT 1");
                $stmt->execute([':id' => $cleanId]);
                $imgRow = $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif ($typeHint === 'artist') {
                $stmt = $pdo->prepare("SELECT image, mime FROM image WHERE object_type = 'artist' AND object_id = :id ORDER BY id DESC LIMIT 1");
                $stmt->execute([':id' => $cleanId]);
                $imgRow = $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif ($typeHint === 'song') {
                // Find song's album
                $sStmt = $pdo->prepare("SELECT album, artist FROM song WHERE id = :id LIMIT 1");
                $sStmt->execute([':id' => $cleanId]);
                $sRow = $sStmt->fetch(PDO::FETCH_ASSOC);
                if ($sRow && !empty($sRow['album'])) {
                    $stmt = $pdo->prepare("SELECT image, mime FROM image WHERE object_type = 'album' AND object_id = :id ORDER BY id DESC LIMIT 1");
                    $stmt->execute([':id' => $sRow['album']]);
                    $imgRow = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                if (!$imgRow && $sRow && !empty($sRow['artist'])) {
                    $stmt = $pdo->prepare("SELECT image, mime FROM image WHERE object_type = 'artist' AND object_id = :id ORDER BY id DESC LIMIT 1");
                    $stmt->execute([':id' => $sRow['artist']]);
                    $imgRow = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            } else {
                // Untyped fallback cascade: album -> artist -> image.id -> song
                $stmt = $pdo->prepare("SELECT image, mime FROM image WHERE object_type = 'album' AND object_id = :id ORDER BY id DESC LIMIT 1");
                $stmt->execute([':id' => $cleanId]);
                $imgRow = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$imgRow) {
                    $stmt = $pdo->prepare("SELECT image, mime FROM image WHERE object_type = 'artist' AND object_id = :id ORDER BY id DESC LIMIT 1");
                    $stmt->execute([':id' => $cleanId]);
                    $imgRow = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                if (!$imgRow) {
                    $stmt = $pdo->prepare("SELECT image, mime FROM image WHERE id = :id LIMIT 1");
                    $stmt->execute([':id' => $cleanId]);
                    $imgRow = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                if (!$imgRow) {
                    $sStmt = $pdo->prepare("SELECT album FROM song WHERE id = :id LIMIT 1");
                    $sStmt->execute([':id' => $cleanId]);
                    $sRow = $sStmt->fetch(PDO::FETCH_ASSOC);
                    if ($sRow && !empty($sRow['album'])) {
                        $stmt = $pdo->prepare("SELECT image, mime FROM image WHERE object_type = 'album' AND object_id = :id ORDER BY id DESC LIMIT 1");
                        $stmt->execute([':id' => $sRow['album']]);
                        $imgRow = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                }
            }

            if (!$imgRow && in_array($typeHint, ['album', 'artist', 'song'], true)) {
                $imgRow = getEmbeddedArtwork($pdo, $typeHint, $cleanId);
            }
        } catch (\Exception $e) {}
    }

    if ($imgRow && !empty($imgRow['image'])) {
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: ' . ($imgRow['mime'] ?: 'image/jpeg'), true);
        header('Content-Length: ' . strlen($imgRow['image']), true);
        header('Cache-Control: public, max-age=2592000, immutable');
        echo $imgRow['image'];
        exit;
    }

    // Default SVG placeholder fallback
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: image/svg+xml', true);
    header('Cache-Control: public, max-age=86400');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300" width="100%" height="100%" preserveAspectRatio="xMidYMid slice"><rect width="300" height="300" fill="#131722"/><circle cx="150" cy="150" r="50" fill="#1e2230"/><path d="M145 136 L145 156 A7 7 0 1 0 152 163 L152 143 L162 146 L162 138 Z" fill="#6366f1"/></svg>';
    exit;
}

// ── Starred Items Retrieval Endpoint (Subsonic getStarred2 Compatibility) ───
// ── Shared JWT Authentication Helpers ────────────────────────────────────────
// Loaded here (after the media endpoints, which never need auth) so every
// user-scoped endpoint below can identify the caller from a signed JWT instead
// of trusting a spoofable `u=` query parameter.
require_once __DIR__ . '/../api/auth/jwt_utils.php';

function getProxyAuthorizationHeader() {
    $candidates = [];
    if (function_exists('getallheaders')) {
        foreach ((array)getallheaders() as $name => $value) {
            if (strtolower($name) === 'authorization') {
                $candidates[] = $value;
            }
        }
    }
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) $candidates[] = $_SERVER['HTTP_AUTHORIZATION'];
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $candidates[] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    foreach ($candidates as $c) {
        if (is_string($c) && trim($c) !== '') return trim($c);
    }
    return '';
}

function getVerifiedProxyUser() {
    static $resolved = false;
    static $payload = null;
    if ($resolved) return $payload;
    $resolved = true;

    $authHeader = getProxyAuthorizationHeader();
    $jwt = '';
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
        $jwt = trim($m[1]);
    } elseif (!empty($_POST['token'])) {
        // POST body token only — never accept JWTs in the query string (leaks into logs/history).
        $jwt = trim($_POST['token']);
    }
    if ($jwt === '') return null;
    $payload = verifyAndDecodeJwt($jwt);
    return $payload;
}

/**
 * Maps the verified JWT (ServerFlow sys_users identity) to the matching Ampache
 * `user` row. The JWT `user_id` is a ServerFlow ID, NOT an Ampache ID, so the
 * username is the only reliable join key between the two databases.
 * Returns ['id' => int, 'username' => string, 'role' => string] or null.
 */
function resolveProxyAmpacheUser($pdo) {
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;

    $payload = getVerifiedProxyUser();
    if (!$payload || !$pdo) return null;
    $username = trim((string)($payload['username'] ?? ''));
    if ($username === '') return null;

    try {
        $stmt = $pdo->prepare("SELECT id, username FROM user WHERE LOWER(username) = LOWER(:u) LIMIT 1");
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && (int)$row['id'] > 0) {
            $cache = [
                'id' => (int)$row['id'],
                'username' => $row['username'],
                'role' => (isset($payload['role']) && $payload['role'] === 'admin') ? 'admin' : 'user'
            ];
        }
    } catch (\Exception $e) {
        error_log('[Aether] resolveProxyAmpacheUser failed: ' . $e->getMessage());
    }
    return $cache;
}

// ── Auth Diagnostic (no secrets) ────────────────────────────────────────────
// Lets the frontend / admin confirm the Authorization header survives
// Cloudflare + Apache and that the JWT maps to an Ampache account.
if ($action === 'whoami') {
    $payload = getVerifiedProxyUser();
    $ampUser = $payload ? resolveProxyAmpacheUser(getProxyPdo()) : null;
    echo json_encode([
        'status' => 'ok',
        'headerReceived' => getProxyAuthorizationHeader() !== '',
        'authenticated' => $payload !== null,
        'username' => $payload['username'] ?? null,
        'ampacheLinked' => $ampUser !== null
    ]);
    exit;
}

if ($action === 'getStarred' || $action === 'getStarred2' || $action === 'getStarredSongs') {
    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }

    $ampUser = resolveProxyAmpacheUser($pdo);
    if (!$ampUser) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized or user not found.']);
        exit;
    }
    $userId = $ampUser['id'];

    try {
        // 1. Starred Songs
        $sStmt = $pdo->prepare("
            SELECT s.id, s.title, s.time as duration, s.track, s.size, s.bitrate,
                   s.total_count as playCount,
                   art.name as artist, art.id as artistId,
                   alb.name as album, alb.id as albumId,
                   uf.date as starredDate
            FROM user_flag uf
            JOIN song s ON uf.object_id = s.id
            LEFT JOIN artist art ON s.artist = art.id
            LEFT JOIN album alb ON s.album = alb.id
            WHERE uf.object_type = 'song' AND uf.user = :uid AND s.enabled = 1
            ORDER BY uf.date DESC
        ");
        $sStmt->execute([':uid' => $userId]);
        $sRows = $sStmt->fetchAll(PDO::FETCH_ASSOC);

        $songs = [];
        foreach ($sRows as $r) {
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
                'starred' => date('c', (int)$r['starredDate']),
                'contentType' => 'audio/mpeg',
                'suffix' => 'mp3'
            ];
        }

        // 2. Starred Albums
        $aStmt = $pdo->prepare("
            SELECT alb.id, alb.name, alb.year, alb.song_count as songCount,
                   art.name as artist, art.id as artistId,
                   uf.date as starredDate
            FROM user_flag uf
            JOIN album alb ON uf.object_id = alb.id
            LEFT JOIN artist art ON alb.album_artist = art.id
            WHERE uf.object_type = 'album' AND uf.user = :uid
            ORDER BY uf.date DESC
        ");
        $aStmt->execute([':uid' => $userId]);
        $aRows = $aStmt->fetchAll(PDO::FETCH_ASSOC);

        $albums = [];
        foreach ($aRows as $r) {
            $subAlbId = (string)(200000000 + (int)$r['id']);
            $subArtId = (string)(100000000 + (int)($r['artistId'] ?? 0));
            $albums[] = [
                'id' => $subAlbId,
                'name' => $r['name'] ?: 'Unknown Album',
                'title' => $r['name'] ?: 'Unknown Album',
                'artist' => $r['artist'] ?: 'Unknown Artist',
                'artistId' => $subArtId,
                'coverArt' => 'al-' . $subAlbId,
                'songCount' => (int)($r['songCount'] ?? 0),
                'year' => (int)($r['year'] ?? 0),
                'starred' => date('c', (int)$r['starredDate'])
            ];
        }

        // 3. Starred Artists
        $arStmt = $pdo->prepare("
            SELECT art.id, art.name,
                   uf.date as starredDate
            FROM user_flag uf
            JOIN artist art ON uf.object_id = art.id
            WHERE uf.object_type = 'artist' AND uf.user = :uid
            ORDER BY uf.date DESC
        ");
        $arStmt->execute([':uid' => $userId]);
        $arRows = $arStmt->fetchAll(PDO::FETCH_ASSOC);

        $artists = [];
        foreach ($arRows as $r) {
            $subArtId = (string)(100000000 + (int)$r['id']);
            $artists[] = [
                'id' => $subArtId,
                'name' => $r['name'] ?: 'Unknown Artist',
                'coverArt' => 'ar-' . $subArtId,
                'starred' => date('c', (int)$r['starredDate'])
            ];
        }

        echo json_encode([
            'status' => 'ok',
            'subsonic-response' => [
                'status' => 'ok',
                'version' => '1.16.1',
                'type' => 'ampache',
                'serverVersion' => '6.6.7',
                'starred2' => [
                    'song' => $songs,
                    'album' => $albums,
                    'artist' => $artists
                ]
            ],
            'songs' => $songs,
            'albums' => $albums,
            'artists' => $artists,
            'count' => count($songs)
        ]);
        exit;
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// ── Star / Unstar / Toggle Endpoint (Immediate DB Persistence) ─────────────
if ($action === 'star' || $action === 'unstar' || $action === 'toggleStar') {
    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }

    $ampUser = resolveProxyAmpacheUser($pdo);
    if (!$ampUser) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized or user not found.']);
        exit;
    }
    $userId = $ampUser['id'];

    $rawSongId = $_GET['id'] ?? ($input['id'] ?? ($_GET['songId'] ?? ($input['songId'] ?? null)));
    $rawAlbumId = $_GET['albumId'] ?? ($input['albumId'] ?? null);
    $rawArtistId = $_GET['artistId'] ?? ($input['artistId'] ?? null);

    $targetType = 'song';
    $rawTargetId = $rawSongId;

    if ($rawAlbumId !== null && empty($rawSongId)) {
        $targetType = 'album';
        $rawTargetId = $rawAlbumId;
    } elseif ($rawArtistId !== null && empty($rawSongId)) {
        $targetType = 'artist';
        $rawTargetId = $rawArtistId;
    }

    $cleanTargetId = intval(preg_replace('/^[^0-9]+/', '', (string)$rawTargetId));
    if ($cleanTargetId >= 100000000) {
        $cleanTargetId = $cleanTargetId % 100000000;
    }

    if ($cleanTargetId <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Valid object ID required to star/unstar.']);
        exit;
    }

    try {
        $checkStmt = $pdo->prepare("SELECT id FROM user_flag WHERE user = :uid AND object_id = :oid AND object_type = :typ LIMIT 1");
        $checkStmt->execute([':uid' => $userId, ':oid' => $cleanTargetId, ':typ' => $targetType]);
        $existingFlagId = $checkStmt->fetchColumn();

        $shouldBeStarred = ($action === 'star');
        if ($action === 'toggleStar') {
            $shouldBeStarred = !$existingFlagId;
        }

        if ($shouldBeStarred) {
            if (!$existingFlagId) {
                $ins = $pdo->prepare("INSERT INTO user_flag (user, object_id, object_type, date) VALUES (:uid, :oid, :typ, :dt)");
                $ins->execute([
                    ':uid' => $userId,
                    ':oid' => $cleanTargetId,
                    ':typ' => $targetType,
                    ':dt' => time()
                ]);
            }
        } else {
            $del = $pdo->prepare("DELETE FROM user_flag WHERE user = :uid AND object_id = :oid AND object_type = :typ");
            $del->execute([':uid' => $userId, ':oid' => $cleanTargetId, ':typ' => $targetType]);
        }

        echo json_encode([
            'status' => 'ok',
            'subsonic-response' => [
                'status' => 'ok',
                'version' => '1.16.1'
            ],
            'action' => $action,
            'starred' => $shouldBeStarred,
            'type' => $targetType,
            'id' => $cleanTargetId
        ]);
        exit;
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

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

    $ampUser = resolveProxyAmpacheUser($pdo);
    if (!$ampUser) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized or user not found.']);
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

// ── User Role Toggle Endpoint (Guaranteed Ampache Database Persistence) ─────
if ($action === 'updateUserRole') {
    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }

    $ampUser = resolveProxyAmpacheUser($pdo);
    if (!$ampUser || $ampUser['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Admin Bearer JWT required.']);
        exit;
    }

    $targetUsername = trim($_POST['username'] ?? ($_GET['username'] ?? ($input['username'] ?? '')));
    $isAdminVal = $_POST['adminRole'] ?? ($_GET['adminRole'] ?? ($input['adminRole'] ?? null));

    if (empty($targetUsername)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Username parameter required.']);
        exit;
    }

    $isAdmin = ($isAdminVal === true || $isAdminVal === 'true' || $isAdminVal === 1 || $isAdminVal === '1');
    $accessLevel = $isAdmin ? 100 : 25; // 100 = admin, 25 = standard user in Ampache

    try {
        $stmt = $pdo->prepare("UPDATE user SET access = :access WHERE username = :username");
        $stmt->execute([':access' => $accessLevel, ':username' => $targetUsername]);

        echo json_encode([
            'status' => 'ok',
            'username' => $targetUsername,
            'adminRole' => $isAdmin,
            'access' => $accessLevel
        ]);
        exit;
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// ── Top 100 Songs Shared Caching Helpers ─────────────────────────────────────
function getAetherChartCache($period, $limit) {
    $dir = sys_get_temp_dir() . '/aether_chart_cache';
    $file = $dir . "/chart_{$period}_{$limit}.json";
    if (!file_exists($file)) return null;
    $mtime = @filemtime($file);
    if ($mtime === false || (time() - $mtime > 60)) {
        @unlink($file);
        return null;
    }
    $content = @file_get_contents($file);
    if (!$content) return null;
    $data = @json_decode($content, true);
    return is_array($data) ? $data : null;
}

function setAetherChartCache($period, $limit, array $data) {
    $dir = sys_get_temp_dir() . '/aether_chart_cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . "/chart_{$period}_{$limit}.json";
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

function bustAetherChartCache() {
    $dir = sys_get_temp_dir() . '/aether_chart_cache';
    if (is_dir($dir)) {
        $files = @glob($dir . '/*.json');
        if (is_array($files)) {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
    }
}

// ── Top 100 Songs Endpoint (Auckland Midnight Calendar Day & Pure Play Ranking) ──
if ($action === 'getTopSongs') {
    $limit = intval($_GET['size'] ?? $_GET['count'] ?? ($input['size'] ?? 100));
    if ($limit < 1 || $limit > 200) $limit = 100;
    $period = strtolower(trim($_GET['period'] ?? ($input['period'] ?? 'daily')));
    if (!in_array($period, ['daily', 'weekly', 'alltime'])) {
        $period = 'daily';
    }

    $cachedChart = getAetherChartCache($period, $limit);
    if ($cachedChart !== null) {
        header('Cache-Control: public, max-age=60');
        header('X-Aether-Chart-Cache: HIT');
        echo json_encode($cachedChart);
        exit;
    }

    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }

    try {
        $tz = new DateTimeZone('Pacific/Auckland');
        $dt = new DateTime('today midnight', $tz);
        $dailySince = $dt->getTimestamp();

        $weeklyDt = clone $dt;
        $weeklyDt->modify('-7 days');
        $weeklySince = $weeklyDt->getTimestamp();

        if ($period === 'alltime') {
            $orderBy = "s.total_count DESC, s.played DESC, s.id DESC";
        } elseif ($period === 'weekly') {
            $orderBy = "COALESCE(oc_weekly.weekly_plays, 0) DESC, s.total_count DESC, s.id DESC";
        } else {
            // Daily: Rank STRICTLY by plays within the current calendar day!
            // All-time count is used strictly as a secondary tie-breaker.
            $orderBy = "COALESCE(oc_daily.daily_plays, 0) DESC, s.total_count DESC, s.id DESC";
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
            $cleanAlbId = (!empty($r['albumId']) && is_numeric($r['albumId'])) ? (int)$r['albumId'] : 0;
            $cleanArtId = (!empty($r['artistId']) && is_numeric($r['artistId'])) ? (int)$r['artistId'] : 0;
            $subId = (string)(300000000 + (int)$r['id']);
            $subAlbId = (string)(200000000 + $cleanAlbId);
            $subArtId = (string)(100000000 + $cleanArtId);

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

        $result = [
            'status' => 'ok',
            'period' => $period,
            'count' => count($songs),
            'generatedAt' => time(),
            'windowStart' => ($period === 'daily' ? $dailySince : ($period === 'weekly' ? $weeklySince : 0)),
            'timezone' => 'Pacific/Auckland',
            'songs' => $songs
        ];

        setAetherChartCache($period, $limit, $result);

        header('Cache-Control: public, max-age=60');
        echo json_encode($result);
        exit;
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// ── Paginated Catalog Songs Endpoint (Hardware-Optimized for 10,000+ Library) ──
if ($action === 'getLibrarySongs' || $action === 'getAllSongs') {
    $offset = max(0, intval($_GET['offset'] ?? ($input['offset'] ?? 0)));
    $limit = intval($_GET['limit'] ?? ($input['limit'] ?? 50));
    if ($limit < 1 || $limit > 200) $limit = 50;

    $query = trim($_GET['query'] ?? ($input['query'] ?? ''));
    $sort = strtolower(trim($_GET['sort'] ?? ($input['sort'] ?? 'title_asc')));

    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }

    try {
        $where = "s.enabled = 1";
        $params = [];

        if (!empty($query)) {
            $where .= " AND (s.title LIKE :q OR art.name LIKE :q OR alb.name LIKE :q)";
            $params[':q'] = '%' . $query . '%';
        }

        switch ($sort) {
            case 'title_desc':
                $orderBy = "s.title DESC, s.id DESC";
                break;
            case 'artist_asc':
                $orderBy = "art.name ASC, s.title ASC";
                break;
            case 'artist_desc':
                $orderBy = "art.name DESC, s.title ASC";
                break;
            case 'album_asc':
                $orderBy = "alb.name ASC, s.track ASC";
                break;
            case 'duration_desc':
                $orderBy = "s.time DESC";
                break;
            case 'duration_asc':
                $orderBy = "s.time ASC";
                break;
            case 'plays_desc':
                $orderBy = "s.total_count DESC, s.id DESC";
                break;
            case 'newest':
                $orderBy = "s.id DESC";
                break;
            case 'title_asc':
            default:
                $orderBy = "s.title ASC, s.id ASC";
                break;
        }

        $countSql = "
            SELECT COUNT(*)
            FROM song s
            LEFT JOIN artist art ON s.artist = art.id
            LEFT JOIN album alb ON s.album = alb.id
            WHERE {$where}
        ";
        $countStmt = $pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT s.id, s.title, s.time as duration, s.track, s.size, s.bitrate,
                   s.total_count as playCount,
                   art.name as artist, art.id as artistId,
                   alb.name as album, alb.id as albumId
            FROM song s
            LEFT JOIN artist art ON s.artist = art.id
            LEFT JOIN album alb ON s.album = alb.id
            WHERE {$where}
            ORDER BY {$orderBy}
            LIMIT :lim OFFSET :off
        ";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $songs = [];
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
                'contentType' => 'audio/mpeg',
                'suffix' => 'mp3'
            ];
        }

        echo json_encode([
            'status' => 'ok',
            'offset' => $offset,
            'limit' => $limit,
            'total' => $total,
            'hasMore' => ($offset + count($songs)) < $total,
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
        error_log('[Aether] recordPlay skipped: database connection failed.');
        echo json_encode(['status' => 'skipped', 'message' => 'Playback logging unavailable.']);
        exit;
    }

    // Authenticate user via JWT and map to Ampache user. Unauthenticated requests are silently skipped to protect leaderboard.
    $ampUser = resolveProxyAmpacheUser($pdo);
    if (!$ampUser) {
        echo json_encode(['status' => 'skipped', 'message' => 'Unauthenticated or unresolved play skipped.']);
        exit;
    }
    $userId = $ampUser['id'];

    try {
        $now = time();

        // Server-side debounce: ignore duplicate stream events for same (user, song) within 30 seconds
        $checkRecent = $pdo->prepare("
            SELECT id FROM object_count
            WHERE object_type = 'song' AND count_type = 'stream' AND object_id = :sid AND user = :uid AND date >= :since
            LIMIT 1
        ");
        $checkRecent->execute([
            ':sid' => $cleanSongId,
            ':uid' => $userId,
            ':since' => $now - 30
        ]);
        if ($checkRecent->fetch()) {
            echo json_encode([
                'status' => 'ok',
                'debounced' => true,
                'message' => 'Play already recorded within threshold',
                'songId' => $cleanSongId
            ]);
            exit;
        }

        // 1. Insert stream event into Ampache's object_count table
        $ins = $pdo->prepare("
            INSERT IGNORE INTO object_count (object_type, object_id, date, user, agent, count_type)
            VALUES ('song', :sid, :ts, :uid, 'Aether', 'stream')
        ");
        $ins->execute([
            ':sid' => $cleanSongId,
            ':ts' => $now,
            ':uid' => $userId
        ]);

        // Increment totals only when this play event was newly recorded.
        if ($ins->rowCount() > 0) {
            $upd = $pdo->prepare("
                UPDATE song
                SET total_count = total_count + 1, played = 1
                WHERE id = :sid
            ");
            $upd->execute([':sid' => $cleanSongId]);

            // Bust chart cache so next chart view reflects this stream
            bustAetherChartCache();
        }

        echo json_encode([
            'status' => 'ok',
            'songId' => $cleanSongId,
            'user' => $userId,
            'timestamp' => $now
        ]);
        exit;
    } catch (\Exception $e) {
        error_log('[Aether] recordPlay failed: ' . $e->getMessage());
        echo json_encode(['status' => 'skipped', 'message' => 'Playback logging unavailable.']);
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

// ── Direct Database Fetch for Artists (Bypasses Subsonic Token Auth Issues) ──
if ($action === 'getArtists' || $action === 'getAllArtists') {
    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT art.id, art.name,
                   COALESCE(art.album_count, COUNT(DISTINCT alb.id)) as albumCount,
                   MAX(img.object_id) as hasArt,
                   GROUP_CONCAT(DISTINCT t.name SEPARATOR '||') as genres
            FROM artist art
            LEFT JOIN album alb ON alb.album_artist = art.id
            LEFT JOIN image img ON img.object_type = 'artist' AND img.object_id = art.id AND LENGTH(img.image) > 0
            LEFT JOIN song s ON s.artist = art.id AND s.enabled = 1
            LEFT JOIN tag_map tm ON tm.object_type = 'song' AND tm.object_id = s.id
            LEFT JOIN tag t ON t.id = tm.tag_id
            WHERE art.name IS NOT NULL AND TRIM(art.name) != ''
            GROUP BY art.id, art.name, art.album_count
            ORDER BY art.name ASC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $artists = [];
        $indexMap = [];

        foreach ($rows as $r) {
            $subArtId = (string)(100000000 + (int)$r['id']);
            $genres = !empty($r['genres']) ? explode('||', $r['genres']) : [];
            $artObj = [
                'id' => $subArtId,
                'name' => $r['name'],
                'coverArt' => $r['hasArt'] ? ('ar-' . $subArtId) : null,
                'albumCount' => (int)$r['albumCount'],
                'genres' => $genres
            ];
            $artists[] = $artObj;

            $firstChar = strtoupper(mb_substr(trim($r['name']), 0, 1, 'UTF-8'));
            $indexLetter = preg_match('/^[A-Z]$/', $firstChar) ? $firstChar : '#';
            if (!isset($indexMap[$indexLetter])) {
                $indexMap[$indexLetter] = [];
            }
            $indexMap[$indexLetter][] = $artObj;
        }

        $indexList = [];
        foreach ($indexMap as $letter => $artList) {
            $indexList[] = [
                'name' => $letter,
                'artist' => $artList
            ];
        }

        echo json_encode([
            'status' => 'ok',
            'artists' => $artists,
            'subsonic-response' => [
                'status' => 'ok',
                'version' => '1.16.1',
                'artists' => [
                    'ignoredArticles' => 'The An A',
                    'index' => $indexList
                ]
            ]
        ]);
        exit;
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// ── Direct Database Fetch for Albums (Bypasses Subsonic Token Auth Issues) ──
if ($action === 'getAlbums' || $action === 'getAllAlbums' || $action === 'getAlbumList') {
    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT alb.id, alb.name, alb.year, alb.disk_count, alb.song_count, alb.total_count as playCount,
                   COALESCE(art.name, 'Various Artists') as artist, COALESCE(art.id, 0) as artistId,
                     EXISTS(
                      SELECT 1 FROM image art_img
                      WHERE art_img.object_type = 'album'
                        AND art_img.object_id = alb.id
                        AND LENGTH(art_img.image) > 0
                     ) as hasArt,
                   GROUP_CONCAT(DISTINCT t.name SEPARATOR '||') as genres
            FROM album alb
            LEFT JOIN artist art ON (alb.album_artist = art.id OR (alb.album_artist = 0 AND art.id = (SELECT s2.artist FROM song s2 WHERE s2.album = alb.id LIMIT 1)))
            LEFT JOIN song s ON s.album = alb.id AND s.enabled = 1
            LEFT JOIN tag_map tm ON tm.object_type = 'song' AND tm.object_id = s.id
            LEFT JOIN tag t ON t.id = tm.tag_id
            WHERE alb.name IS NOT NULL AND TRIM(alb.name) != ''
            GROUP BY alb.id, alb.name, alb.year, alb.disk_count, alb.song_count, alb.total_count, art.name, art.id
            ORDER BY alb.name ASC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $albums = [];
        foreach ($rows as $r) {
            $subAlbId = (string)(200000000 + (int)$r['id']);
            $subArtId = (string)(100000000 + (int)($r['artistId'] ?? 0));
            $genres = !empty($r['genres']) ? explode('||', $r['genres']) : [];
            $albums[] = [
                'id' => $subAlbId,
                'name' => $r['name'],
                'title' => $r['name'],
                'artist' => $r['artist'] ?: 'Various Artists',
                'artistId' => $subArtId,
                'coverArt' => 'al-' . $subAlbId,
                'hasArt' => (bool)$r['hasArt'],
                'songCount' => (int)($r['song_count'] ?: 0),
                'playCount' => (int)($r['playCount'] ?: 0),
                'year' => (int)$r['year'],
                'genre' => !empty($genres) ? $genres[0] : ''
            ];
        }

        echo json_encode([
            'status' => 'ok',
            'albums' => $albums,
            'subsonic-response' => [
                'status' => 'ok',
                'version' => '1.16.1',
                'albumList' => [
                    'album' => $albums
                ]
            ]
        ]);
        exit;
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// ── Direct Database Fetch for Single Album Details ─────────────────────────
if ($action === 'getAlbum') {
    $rawId = $_GET['id'] ?? ($input['id'] ?? 0);
    $cleanId = intval(preg_replace('/\D/', '', (string)$rawId));
    if ($cleanId >= 200000000) {
        $cleanId = $cleanId % 100000000;
    }

    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }

    try {
        $stmtAlb = $pdo->prepare("
            SELECT alb.id, alb.name, alb.year,
                   COALESCE(art.name, 'Various Artists') as artist,
                   COALESCE(art.id, 0) as artistId
            FROM album alb
            LEFT JOIN artist art ON (alb.album_artist = art.id OR (alb.album_artist = 0 AND art.id = (SELECT s2.artist FROM song s2 WHERE s2.album = alb.id LIMIT 1)))
            WHERE alb.id = :id
            LIMIT 1
        ");
        $stmtAlb->execute([':id' => $cleanId]);
        $albumRow = $stmtAlb->fetch(PDO::FETCH_ASSOC);

        if (!$albumRow) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Album not found.']);
            exit;
        }

        $stmtSongs = $pdo->prepare("
            SELECT s.id, s.title, s.time as duration, s.track, s.size, s.bitrate,
                   s.total_count as playCount, art.name as artist, art.id as artistId,
                   alb.name as album, alb.id as albumId, alb.year as year
            FROM song s
            LEFT JOIN artist art ON s.artist = art.id
            LEFT JOIN album alb ON s.album = alb.id
            WHERE s.album = :id AND s.enabled = 1
            ORDER BY s.track ASC, s.title ASC
        ");
        $stmtSongs->execute([':id' => $cleanId]);
        $songRows = $stmtSongs->fetchAll(PDO::FETCH_ASSOC);

        $songs = [];
        foreach ($songRows as $s) {
            $sSubId = (string)(300000000 + (int)$s['id']);
            $sSubAlbId = (string)(200000000 + (int)$s['albumId']);
            $sSubArtId = (string)(100000000 + (int)($s['artistId'] ?? 0));
            $songs[] = [
                'id' => $sSubId,
                'parent' => $sSubAlbId,
                'title' => $s['title'],
                'artist' => $s['artist'] ?: 'Unknown Artist',
                'artistId' => $sSubArtId,
                'album' => $s['album'],
                'albumId' => $sSubAlbId,
                'duration' => (int)$s['duration'],
                'track' => (int)$s['track'],
                'coverArt' => 'al-' . $sSubAlbId,
                'year' => (int)$s['year'],
                'playCount' => (int)$s['playCount']
            ];
        }

        $subAlbId = (string)(200000000 + (int)$albumRow['id']);
        $albumData = [
            'id' => $subAlbId,
            'name' => $albumRow['name'],
            'artist' => $albumRow['artist'] ?: 'Various Artists',
            'artistId' => (string)(100000000 + (int)($albumRow['artistId'] ?? 0)),
            'coverArt' => 'al-' . $subAlbId,
            'songCount' => count($songs),
            'year' => (int)$albumRow['year'],
            'song' => $songs
        ];

        echo json_encode([
            'status' => 'ok',
            'album' => $albumData,
            'subsonic-response' => [
                'status' => 'ok',
                'version' => '1.16.1',
                'album' => $albumData
            ]
        ]);
        exit;
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// ── Direct Database Fetch for Single Artist Details ────────────────────────
if ($action === 'getArtist') {
    $rawId = $_GET['id'] ?? ($input['id'] ?? 0);
    $cleanId = intval(preg_replace('/\D/', '', (string)$rawId));
    if ($cleanId >= 100000000) {
        $cleanId = $cleanId % 100000000;
    }

    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }

    try {
        $stmtArt = $pdo->prepare("
            SELECT art.id, art.name, MAX(img.object_id) as hasArt
            FROM artist art
            LEFT JOIN image img ON img.object_type = 'artist' AND img.object_id = art.id AND LENGTH(img.image) > 0
            WHERE art.id = :id
            LIMIT 1
        ");
        $stmtArt->execute([':id' => $cleanId]);
        $artRow = $stmtArt->fetch(PDO::FETCH_ASSOC);

        if (!$artRow) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Artist not found.']);
            exit;
        }

        $stmtAlbs = $pdo->prepare("
            SELECT alb.id, alb.name, alb.year, COUNT(s.id) as songCount
            FROM album alb
            LEFT JOIN song s ON s.album = alb.id AND s.enabled = 1
            WHERE alb.album_artist = :id OR alb.id IN (SELECT DISTINCT album FROM song WHERE artist = :id AND enabled = 1)
            GROUP BY alb.id, alb.name, alb.year
            ORDER BY alb.year DESC, alb.name ASC
        ");
        $stmtAlbs->execute([':id' => $cleanId]);
        $albRows = $stmtAlbs->fetchAll(PDO::FETCH_ASSOC);

        $albums = [];
        foreach ($albRows as $a) {
            $aSubAlbId = (string)(200000000 + (int)$a['id']);
            $aSubArtId = (string)(100000000 + (int)$artRow['id']);
            $albums[] = [
                'id' => $aSubAlbId,
                'name' => $a['name'],
                'artist' => $artRow['name'],
                'artistId' => $aSubArtId,
                'coverArt' => 'al-' . $aSubAlbId,
                'songCount' => (int)$a['songCount'],
                'year' => (int)$a['year']
            ];
        }

        $subArtId = (string)(100000000 + (int)$artRow['id']);
        $artistData = [
            'id' => $subArtId,
            'name' => $artRow['name'],
            'coverArt' => $artRow['hasArt'] ? ('ar-' . $subArtId) : null,
            'albumCount' => count($albums),
            'album' => $albums
        ];

        echo json_encode([
            'status' => 'ok',
            'artist' => $artistData,
            'subsonic-response' => [
                'status' => 'ok',
                'version' => '1.16.1',
                'artist' => $artistData
            ]
        ]);
        exit;
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// ── High-Speed Search File Cache with Probabilistic GC (SSD Safe) ─────────────
function getAetherSearchCache($cacheKey) {
    $dir = sys_get_temp_dir() . '/aether_search_cache';
    $file = $dir . '/' . $cacheKey . '.json';
    if (!file_exists($file)) return null;
    $mtime = @filemtime($file);
    if ($mtime === false || (time() - $mtime > 60)) {
        @unlink($file);
        return null;
    }
    $content = @file_get_contents($file);
    if (!$content) return null;
    $data = @json_decode($content, true);
    return is_array($data) ? $data : null;
}

function setAetherSearchCache($cacheKey, array $data) {
    $dir = sys_get_temp_dir() . '/aether_search_cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    // Probabilistic Garbage Collection: 1 in 50 requests
    if (mt_rand(1, 50) === 1) {
        $files = @glob($dir . '/*.json');
        if (is_array($files)) {
            $now = time();
            $count = 0;
            foreach ($files as $f) {
                $m = @filemtime($f);
                if ($m !== false && ($now - $m > 300)) {
                    @unlink($f);
                } else {
                    $count++;
                }
            }
            if ($count > 500) {
                $fileTimes = [];
                foreach ($files as $f) {
                    if (file_exists($f)) {
                        $fileTimes[$f] = @filemtime($f) ?: 0;
                    }
                }
                asort($fileTimes);
                $toDelete = count($fileTimes) - 500;
                foreach (array_slice(array_keys($fileTimes), 0, $toDelete) as $delFile) {
                    @unlink($delFile);
                }
            }
        }
    }
    $file = $dir . '/' . $cacheKey . '.json';
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

if ($action === 'search' || $action === 'search2' || $action === 'search3') {
    $q = trim($_GET['query'] ?? ($input['query'] ?? ($_GET['q'] ?? ($input['q'] ?? ''))));
    $q = rtrim($q, '*');

    if (mb_strlen($q) < 2) {
        echo json_encode([
            'status' => 'ok',
            'song' => [],
            'album' => [],
            'artist' => [],
            'subsonic-response' => [
                'status' => 'ok',
                'version' => '1.16.1',
                'searchResult3' => ['song' => [], 'album' => [], 'artist' => []],
                'searchResult2' => ['song' => [], 'album' => [], 'artist' => []]
            ]
        ]);
        exit;
    }

    $songCount = intval($_GET['songCount'] ?? ($input['songCount'] ?? 150));
    if ($songCount < 1 || $songCount > 300) $songCount = 150;

    $albumCount = intval($_GET['albumCount'] ?? ($input['albumCount'] ?? 20));
    if ($albumCount < 1 || $albumCount > 100) $albumCount = 20;

    $artistCount = intval($_GET['artistCount'] ?? ($input['artistCount'] ?? 20));
    if ($artistCount < 1 || $artistCount > 100) $artistCount = 20;

    // Check fast SSD file cache (60s TTL)
    $cacheKey = md5(mb_strtolower($q) . "_{$songCount}_{$albumCount}_{$artistCount}");
    $cached = getAetherSearchCache($cacheKey);
    if ($cached !== null) {
        header('X-Aether-Cache: HIT');
        echo json_encode($cached);
        exit;
    }

    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }

    try {
        $likeQ = '%' . $q . '%';
        $exactQ = $q;
        $startQ = $q . '%';

        // Fulltext setup (InnoDB default min token size is 3; 2-letter inputs like 'Ye', 'U2' use B-tree LIKE)
        $useFulltext = (mb_strlen($q) >= 3);
        $ftsQuery = '';
        if ($useFulltext) {
            $cleanQ = preg_replace('/[+\-><()~*\"@]/u', ' ', $q);
            $words = array_filter(explode(' ', trim($cleanQ)), function($w) { return mb_strlen($w) >= 2; });
            if (!empty($words)) {
                $ftsQuery = implode(' ', array_map(function($w) { return '+' . $w . '*'; }, $words));
            } else {
                $useFulltext = false;
            }
        }

        // =========================================================
        // 1. Search Songs (Tiered: Prefix -> Fulltext -> Substring)
        // =========================================================
        $songMap = []; // id => song array

        // Tier 1: Instant B-Tree Prefix Search (sub-10ms via idx_song_enabled_title)
        $pfxStmt = $pdo->prepare("
            SELECT s.id, s.title, s.time as duration, s.track, s.size, s.bitrate,
                   s.total_count as playCount,
                   COALESCE(art.name, 'Unknown Artist') as artist, COALESCE(art.id, 0) as artistId,
                   COALESCE(alb.name, 'Unknown Album') as album, COALESCE(alb.id, 0) as albumId
            FROM song s
            LEFT JOIN artist art ON s.artist = art.id
            LEFT JOIN album alb ON s.album = alb.id
            WHERE s.enabled = 1
              AND (s.title LIKE :start1 OR art.name LIKE :start2)
            ORDER BY 
              CASE 
                WHEN s.title = :exact1 THEN 0
                WHEN s.title LIKE :start3 THEN 1
                WHEN art.name LIKE :start4 THEN 2
                ELSE 3 
              END,
              s.total_count DESC, s.title ASC
            LIMIT :lim
        ");
        $pfxStmt->bindValue(':start1', $startQ, PDO::PARAM_STR);
        $pfxStmt->bindValue(':start2', $startQ, PDO::PARAM_STR);
        $pfxStmt->bindValue(':start3', $startQ, PDO::PARAM_STR);
        $pfxStmt->bindValue(':start4', $startQ, PDO::PARAM_STR);
        $pfxStmt->bindValue(':exact1', $exactQ, PDO::PARAM_STR);
        $pfxStmt->bindValue(':lim', $songCount, PDO::PARAM_INT);
        $pfxStmt->execute();
        $pfxRows = $pfxStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($pfxRows as $r) {
            $songMap[(int)$r['id']] = $r;
        }

        // Tier 2: FULLTEXT Search (if query >= 3 chars and we need more songs)
        if ($useFulltext && count($songMap) < $songCount) {
            $needed = $songCount - count($songMap);
            $ftsStmt = $pdo->prepare("
                SELECT s.id, s.title, s.time as duration, s.track, s.size, s.bitrate,
                       s.total_count as playCount,
                       COALESCE(art.name, 'Unknown Artist') as artist, COALESCE(art.id, 0) as artistId,
                       COALESCE(alb.name, 'Unknown Album') as album, COALESCE(alb.id, 0) as albumId
                FROM song s
                LEFT JOIN artist art ON s.artist = art.id
                LEFT JOIN album alb ON s.album = alb.id
                WHERE s.enabled = 1
                  AND (MATCH(s.title) AGAINST(:fts IN BOOLEAN MODE) OR MATCH(art.name) AGAINST(:fts IN BOOLEAN MODE))
                ORDER BY s.total_count DESC, s.title ASC
                LIMIT :lim
            ");
            $ftsStmt->bindValue(':fts', $ftsQuery, PDO::PARAM_STR);
            $ftsStmt->bindValue(':lim', $needed + count($songMap), PDO::PARAM_INT);
            try {
                $ftsStmt->execute();
                $ftsRows = $ftsStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($ftsRows as $r) {
                    $sid = (int)$r['id'];
                    if (!isset($songMap[$sid])) {
                        $songMap[$sid] = $r;
                        if (count($songMap) >= $songCount) break;
                    }
                }
            } catch (\Exception $fe) {
                // If fulltext index not yet built, skip gracefully to Tier 3
            }
        }

        // Tier 3: Substring LIKE Search (if still fewer than requested limit)
        if (count($songMap) < $songCount) {
            $needed = $songCount - count($songMap);
            $likeStmt = $pdo->prepare("
                SELECT s.id, s.title, s.time as duration, s.track, s.size, s.bitrate,
                       s.total_count as playCount,
                       COALESCE(art.name, 'Unknown Artist') as artist, COALESCE(art.id, 0) as artistId,
                       COALESCE(alb.name, 'Unknown Album') as album, COALESCE(alb.id, 0) as albumId
                FROM song s
                LEFT JOIN artist art ON s.artist = art.id
                LEFT JOIN album alb ON s.album = alb.id
                WHERE s.enabled = 1
                  AND (s.title LIKE :q1 OR art.name LIKE :q2 OR alb.name LIKE :q3)
                ORDER BY s.total_count DESC, s.title ASC
                LIMIT :lim
            ");
            $likeStmt->bindValue(':q1', $likeQ, PDO::PARAM_STR);
            $likeStmt->bindValue(':q2', $likeQ, PDO::PARAM_STR);
            $likeStmt->bindValue(':q3', $likeQ, PDO::PARAM_STR);
            $likeStmt->bindValue(':lim', $needed + count($songMap), PDO::PARAM_INT);
            $likeStmt->execute();
            $likeRows = $likeStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($likeRows as $r) {
                $sid = (int)$r['id'];
                if (!isset($songMap[$sid])) {
                    $songMap[$sid] = $r;
                    if (count($songMap) >= $songCount) break;
                }
            }
        }

        $songs = [];
        foreach ($songMap as $r) {
            $cleanAlbId = (!empty($r['albumId']) && is_numeric($r['albumId'])) ? (int)$r['albumId'] : 0;
            $cleanArtId = (!empty($r['artistId']) && is_numeric($r['artistId'])) ? (int)$r['artistId'] : 0;
            $subId = (string)(300000000 + (int)$r['id']);
            $subAlbId = (string)(200000000 + $cleanAlbId);
            $subArtId = (string)(100000000 + $cleanArtId);

            $songs[] = [
                'id' => $subId,
                'parent' => $subAlbId,
                'title' => !empty($r['title']) ? $r['title'] : 'Unknown Track',
                'isDir' => false,
                'isVideo' => false,
                'type' => 'music',
                'albumId' => $subAlbId,
                'album' => !empty($r['album']) ? $r['album'] : 'Unknown Album',
                'artistId' => $subArtId,
                'artist' => !empty($r['artist']) ? $r['artist'] : 'Unknown Artist',
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

        // =========================================================
        // 2. Search Albums (Prefix First -> Fulltext -> Contains)
        // =========================================================
        $albMap = [];
        $albPfxStmt = $pdo->prepare("
            SELECT alb.id, alb.name, alb.year, alb.song_count, alb.total_count as playCount,
                   COALESCE(art.name, 'Various Artists') as artist, COALESCE(art.id, 0) as artistId
            FROM album alb
            LEFT JOIN artist art ON alb.album_artist = art.id
            WHERE alb.name IS NOT NULL AND TRIM(alb.name) != ''
              AND alb.name LIKE :start1
            ORDER BY 
              CASE WHEN alb.name = :exact1 THEN 0 ELSE 1 END,
              alb.name ASC
            LIMIT :lim
        ");
        $albPfxStmt->bindValue(':start1', $startQ, PDO::PARAM_STR);
        $albPfxStmt->bindValue(':exact1', $exactQ, PDO::PARAM_STR);
        $albPfxStmt->bindValue(':lim', $albumCount, PDO::PARAM_INT);
        $albPfxStmt->execute();
        foreach ($albPfxStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $albMap[(int)$r['id']] = $r;
        }

        if (count($albMap) < $albumCount) {
            $needed = $albumCount - count($albMap);
            $albLikeStmt = $pdo->prepare("
                SELECT alb.id, alb.name, alb.year, alb.song_count, alb.total_count as playCount,
                       COALESCE(art.name, 'Various Artists') as artist, COALESCE(art.id, 0) as artistId
                FROM album alb
                LEFT JOIN artist art ON alb.album_artist = art.id
                WHERE alb.name IS NOT NULL AND TRIM(alb.name) != ''
                  AND (alb.name LIKE :q1 OR art.name LIKE :q2)
                ORDER BY alb.name ASC
                LIMIT :lim
            ");
            $albLikeStmt->bindValue(':q1', $likeQ, PDO::PARAM_STR);
            $albLikeStmt->bindValue(':q2', $likeQ, PDO::PARAM_STR);
            $albLikeStmt->bindValue(':lim', $needed + count($albMap), PDO::PARAM_INT);
            $albLikeStmt->execute();
            foreach ($albLikeStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $aid = (int)$r['id'];
                if (!isset($albMap[$aid])) {
                    $albMap[$aid] = $r;
                    if (count($albMap) >= $albumCount) break;
                }
            }
        }

        $albums = [];
        foreach ($albMap as $r) {
            $subAlbId = (string)(200000000 + (int)$r['id']);
            $subArtId = (string)(100000000 + (int)($r['artistId'] ?? 0));
            $albums[] = [
                'id' => $subAlbId,
                'name' => $r['name'],
                'title' => $r['name'],
                'artist' => !empty($r['artist']) ? $r['artist'] : 'Various Artists',
                'artistId' => $subArtId,
                'coverArt' => 'al-' . $subAlbId,
                'songCount' => (int)($r['song_count'] ?: 0),
                'playCount' => (int)($r['playCount'] ?: 0),
                'year' => (int)$r['year']
            ];
        }

        // =========================================================
        // 3. Search Artists (Prefix First -> Fulltext -> Contains)
        // =========================================================
        $artMap = [];
        $artPfxStmt = $pdo->prepare("
            SELECT art.id, art.name,
                   COALESCE(art.album_count, COUNT(DISTINCT alb.id)) as albumCount
            FROM artist art
            LEFT JOIN album alb ON alb.album_artist = art.id
            WHERE art.name IS NOT NULL AND TRIM(art.name) != ''
              AND art.name LIKE :start1
            GROUP BY art.id, art.name, art.album_count
            ORDER BY 
              CASE WHEN art.name = :exact1 THEN 0 ELSE 1 END,
              art.name ASC
            LIMIT :lim
        ");
        $artPfxStmt->bindValue(':start1', $startQ, PDO::PARAM_STR);
        $artPfxStmt->bindValue(':exact1', $exactQ, PDO::PARAM_STR);
        $artPfxStmt->bindValue(':lim', $artistCount, PDO::PARAM_INT);
        $artPfxStmt->execute();
        foreach ($artPfxStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $artMap[(int)$r['id']] = $r;
        }

        if (count($artMap) < $artistCount) {
            $needed = $artistCount - count($artMap);
            $artLikeStmt = $pdo->prepare("
                SELECT art.id, art.name,
                       COALESCE(art.album_count, COUNT(DISTINCT alb.id)) as albumCount
                FROM artist art
                LEFT JOIN album alb ON alb.album_artist = art.id
                WHERE art.name IS NOT NULL AND TRIM(art.name) != ''
                  AND art.name LIKE :q
                GROUP BY art.id, art.name, art.album_count
                ORDER BY art.name ASC
                LIMIT :lim
            ");
            $artLikeStmt->bindValue(':q', $likeQ, PDO::PARAM_STR);
            $artLikeStmt->bindValue(':lim', $needed + count($artMap), PDO::PARAM_INT);
            $artLikeStmt->execute();
            foreach ($artLikeStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $arid = (int)$r['id'];
                if (!isset($artMap[$arid])) {
                    $artMap[$arid] = $r;
                    if (count($artMap) >= $artistCount) break;
                }
            }
        }

        $artists = [];
        foreach ($artMap as $r) {
            $subArtId = (string)(100000000 + (int)$r['id']);
            $artists[] = [
                'id' => $subArtId,
                'name' => $r['name'],
                'coverArt' => 'ar-' . $subArtId,
                'albumCount' => (int)$r['albumCount']
            ];
        }

        // =========================================================
        // 4. Bounded Typo-Tolerant Fallback (Runs ONLY if 0 matches)
        // =========================================================
        if (empty($songs) && empty($albums) && empty($artists) && mb_strlen($q) >= 3) {
            $qLower = mb_strtolower($q);
            $qLen = mb_strlen($qLower);
            $maxDist = $qLen <= 4 ? 1 : ($qLen <= 8 ? 2 : 3);
            $candPfx = mb_substr($qLower, 0, 2) . '%';

            // Fetch candidate artists matching first 2 characters (bounded to 40)
            $candArtStmt = $pdo->prepare("
                SELECT id, name, album_count 
                FROM artist 
                WHERE name IS NOT NULL AND name LIKE :pfx
                LIMIT 40
            ");
            $candArtStmt->bindValue(':pfx', $candPfx);
            $candArtStmt->execute();
            $candArtists = $candArtStmt->fetchAll(PDO::FETCH_ASSOC);

            $fuzzyArtists = [];
            foreach ($candArtists as $a) {
                $dist = levenshtein($qLower, mb_strtolower($a['name']));
                if ($dist <= $maxDist) {
                    $a['_dist'] = $dist;
                    $fuzzyArtists[] = $a;
                }
            }
            usort($fuzzyArtists, function($x, $y) { return $x['_dist'] - $y['_dist']; });
            $fuzzyArtists = array_slice($fuzzyArtists, 0, $artistCount);

            foreach ($fuzzyArtists as $r) {
                $subArtId = (string)(100000000 + (int)$r['id']);
                $artists[] = [
                    'id' => $subArtId,
                    'name' => $r['name'],
                    'coverArt' => 'ar-' . $subArtId,
                    'albumCount' => (int)($r['album_count'] ?? 0)
                ];
            }

            // Fetch candidate albums matching first 2 characters (bounded to 40)
            $candAlbStmt = $pdo->prepare("
                SELECT alb.id, alb.name, alb.year, alb.song_count, alb.total_count as playCount,
                       COALESCE(art.name, 'Various Artists') as artist, COALESCE(art.id, 0) as artistId
                FROM album alb
                LEFT JOIN artist art ON alb.album_artist = art.id
                WHERE alb.name IS NOT NULL AND alb.name LIKE :pfx
                LIMIT 40
            ");
            $candAlbStmt->bindValue(':pfx', $candPfx);
            $candAlbStmt->execute();
            $candAlbums = $candAlbStmt->fetchAll(PDO::FETCH_ASSOC);

            $fuzzyAlbums = [];
            foreach ($candAlbums as $a) {
                $dist = levenshtein($qLower, mb_strtolower($a['name']));
                if ($dist <= $maxDist) {
                    $a['_dist'] = $dist;
                    $fuzzyAlbums[] = $a;
                }
            }
            usort($fuzzyAlbums, function($x, $y) { return $x['_dist'] - $y['_dist']; });
            $fuzzyAlbums = array_slice($fuzzyAlbums, 0, $albumCount);

            foreach ($fuzzyAlbums as $r) {
                $subAlbId = (string)(200000000 + (int)$r['id']);
                $subArtId = (string)(100000000 + (int)($r['artistId'] ?? 0));
                $albums[] = [
                    'id' => $subAlbId,
                    'name' => $r['name'],
                    'title' => $r['name'],
                    'artist' => !empty($r['artist']) ? $r['artist'] : 'Various Artists',
                    'artistId' => $subArtId,
                    'coverArt' => 'al-' . $subAlbId,
                    'songCount' => (int)($r['song_count'] ?: 0),
                    'playCount' => (int)($r['playCount'] ?: 0),
                    'year' => (int)$r['year']
                ];
            }

            // Fetch candidate songs matching first 2 characters (bounded to 80)
            $candSongStmt = $pdo->prepare("
                SELECT s.id, s.title, s.time as duration, s.track, s.size, s.bitrate,
                       s.total_count as playCount,
                       COALESCE(art.name, 'Unknown Artist') as artist, COALESCE(art.id, 0) as artistId,
                       COALESCE(alb.name, 'Unknown Album') as album, COALESCE(alb.id, 0) as albumId
                FROM song s
                LEFT JOIN artist art ON s.artist = art.id
                LEFT JOIN album alb ON s.album = alb.id
                WHERE s.enabled = 1
                  AND (s.title LIKE :pfx OR SOUNDEX(s.title) = SOUNDEX(:sdx))
                LIMIT 80
            ");
            $candSongStmt->bindValue(':pfx', $candPfx);
            $candSongStmt->bindValue(':sdx', $qLower);
            $candSongStmt->execute();
            $candSongs = $candSongStmt->fetchAll(PDO::FETCH_ASSOC);

            $fuzzySongs = [];
            foreach ($candSongs as $r) {
                $title = $r['title'] ?? '';
                $dist = levenshtein($qLower, mb_strtolower($title));
                $words = preg_split('/\s+/', $title);
                if (is_array($words)) {
                    foreach ($words as $word) {
                        if ($word === '') continue;
                        $dist = min($dist, levenshtein($qLower, mb_strtolower($word)));
                    }
                }
                if ($dist <= $maxDist) {
                    $r['_dist'] = $dist;
                    $fuzzySongs[] = $r;
                }
            }
            usort($fuzzySongs, function($x, $y) { return $x['_dist'] - $y['_dist']; });
            $fuzzySongs = array_slice($fuzzySongs, 0, $songCount);

            foreach ($fuzzySongs as $r) {
                $cleanAlbId = (!empty($r['albumId']) && is_numeric($r['albumId'])) ? (int)$r['albumId'] : 0;
                $cleanArtId = (!empty($r['artistId']) && is_numeric($r['artistId'])) ? (int)$r['artistId'] : 0;
                $subId = (string)(300000000 + (int)$r['id']);
                $subAlbId = (string)(200000000 + $cleanAlbId);
                $subArtId = (string)(100000000 + $cleanArtId);

                $songs[] = [
                    'id' => $subId,
                    'parent' => $subAlbId,
                    'title' => !empty($r['title']) ? $r['title'] : 'Unknown Track',
                    'isDir' => false,
                    'isVideo' => false,
                    'type' => 'music',
                    'albumId' => $subAlbId,
                    'album' => !empty($r['album']) ? $r['album'] : 'Unknown Album',
                    'artistId' => $subArtId,
                    'artist' => !empty($r['artist']) ? $r['artist'] : 'Unknown Artist',
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
        }

        $payload = [
            'status' => 'ok',
            'song' => $songs,
            'album' => $albums,
            'artist' => $artists,
            'subsonic-response' => [
                'status' => 'ok',
                'version' => '1.16.1',
                'searchResult3' => [
                    'song' => $songs,
                    'album' => $albums,
                    'artist' => $artists
                ],
                'searchResult2' => [
                    'song' => $songs,
                    'album' => $albums,
                    'artist' => $artists
                ]
            ]
        ];

        // Store in short-TTL search file cache if any items found
        if (!empty($songs) || !empty($albums) || !empty($artists)) {
            setAetherSearchCache($cacheKey, $payload);
        }

        echo json_encode($payload);
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
        "http://127.0.0.1:8888/access-to-server/ampache/public/rest/index.php"
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
