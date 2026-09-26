<?php
// modern-music-app/api_proxy.php — Public Registration Proxy for Ampache Subsonic Backend
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Range, X-Requested-With');
header('Access-Control-Expose-Headers: Content-Range, Accept-Ranges, Content-Length');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
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

require_once __DIR__ . '/../config/config.php';

function getProxyPdo() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

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

    $creds = [
        [!empty($cfg['database_username']) ? $cfg['database_username'] : 'server_app', !empty($cfg['database_password']) ? $cfg['database_password'] : 'password'],
        ['root', 'root'],
        ['server_app', 'password'],
        ['ampache_user', 'password'],
        ['root', '']
    ];

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 2
    ];

    foreach ($hosts as $h) {
        foreach ($ports as $p) {
            foreach ($creds as [$u, $pwd]) {
                try {
                    $pdo = new PDO("mysql:host={$h};port={$p};dbname={$dbname};charset=utf8mb4", $u, $pwd, $options);
                    return $pdo;
                } catch (\Exception $e) {}
            }
        }
    }

    $socket = '/Applications/MAMP/tmp/mysql/mysql.sock';
    if (file_exists($socket)) {
        foreach ($creds as [$u, $pwd]) {
            try {
                $pdo = new PDO("mysql:unix_socket={$socket};dbname={$dbname};charset=utf8mb4", $u, $pwd, $options);
                return $pdo;
            } catch (\Exception $e) {}
        }
    }

    return null;
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
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300"><rect width="300" height="300" fill="#131722"/><circle cx="150" cy="150" r="50" fill="#1e2230"/><path d="M145 136 L145 156 A7 7 0 1 0 152 163 L152 143 L162 146 L162 138 Z" fill="#6366f1"/></svg>';
    exit;
}

// ── Starred Items Retrieval Endpoint (Subsonic getStarred2 Compatibility) ───
if ($action === 'getStarred' || $action === 'getStarred2' || $action === 'getStarredSongs') {
    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit;
    }

    $username = trim($_GET['u'] ?? ($input['u'] ?? ''));
    $userId = 1;
    if (!empty($username)) {
        try {
            $uStmt = $pdo->prepare("SELECT id FROM user WHERE username = :u LIMIT 1");
            $uStmt->execute([':u' => $username]);
            $foundUid = $uStmt->fetchColumn();
            if ($foundUid) $userId = (int)$foundUid;
        } catch (\Exception $e) {}
    }

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

    $username = trim($_GET['u'] ?? ($input['u'] ?? ''));
    $userId = 1;
    if (!empty($username)) {
        try {
            $uStmt = $pdo->prepare("SELECT id FROM user WHERE username = :u LIMIT 1");
            $uStmt->execute([':u' => $username]);
            $foundUid = $uStmt->fetchColumn();
            if ($foundUid) $userId = (int)$foundUid;
        } catch (\Exception $e) {}
    }

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
function verifyProxyAdminJwt() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
        $parts = explode('.', trim($m[1]));
        if (count($parts) === 3) {
            $secret = getProxyEnv('JWT_SECRET', 'default-secret-key-change-me');
            $expected = base64_encode(hash_hmac('sha256', "{$parts[0]}.{$parts[1]}", $secret, true));
            if (hash_equals($expected, $parts[2])) {
                $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                if (isset($payload['role']) && $payload['role'] === 'admin') return true;
            }
        }
    }
    return false;
}

if ($action === 'updateUserRole') {
    if (!verifyProxyAdminJwt()) {
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

    $pdo = getProxyPdo();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
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
                   img.object_id as hasArt,
                   GROUP_CONCAT(DISTINCT t.name SEPARATOR '||') as genres
            FROM artist art
            LEFT JOIN album alb ON alb.album_artist = art.id
            LEFT JOIN image img ON img.object_type = 'artist' AND img.object_id = art.id AND img.size = 'original'
            LEFT JOIN song s ON s.artist = art.id AND s.enabled = 1
            LEFT JOIN tag_map tm ON tm.object_type = 'song' AND tm.object_id = s.id
            LEFT JOIN tag t ON t.id = tm.tag_id
            WHERE art.name IS NOT NULL AND TRIM(art.name) != ''
            GROUP BY art.id, art.name, art.album_count, img.object_id
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
            SELECT art.id, art.name, img.object_id as hasArt
            FROM artist art
            LEFT JOIN image img ON img.object_type = 'artist' AND img.object_id = art.id AND img.size = 'original'
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
