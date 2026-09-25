<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function getAmpachePdo() {
    $host = defined('AMPACHE_DB_HOST') ? AMPACHE_DB_HOST : '127.0.0.1';
    $port = defined('AMPACHE_DB_PORT') ? AMPACHE_DB_PORT : '8889';
    $dbname = defined('AMPACHE_DB_NAME') ? AMPACHE_DB_NAME : 'ampache';
    $user = defined('AMPACHE_DB_USER') ? AMPACHE_DB_USER : 'ampache_user';
    $pass = defined('AMPACHE_DB_PASS') ? AMPACHE_DB_PASS : 'password';

    $hosts = array_unique([$host, '127.0.0.1', 'localhost']);
    $creds = [
        [$user, $pass],
        ['server_app', 'password'],
        ['root', 'root'],
        ['root', '']
    ];

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 2
    ];

    foreach ($hosts as $h) {
        foreach ($creds as [$u, $pwd]) {
            try {
                return new PDO("mysql:host={$h};port={$port};dbname={$dbname};charset=utf8mb4", $u, $pwd, $options);
            } catch (\Exception $e) {}
        }
    }

    $socket = '/Applications/MAMP/tmp/mysql/mysql.sock';
    if (file_exists($socket)) {
        foreach ($creds as [$u, $pwd]) {
            try {
                return new PDO("mysql:unix_socket={$socket};dbname={$dbname};charset=utf8mb4", $u, $pwd, $options);
            } catch (\Exception $e) {}
        }
    }

    return null;
}

try {
    $pdo = getAmpachePdo();
    if (!$pdo) {
        throw new Exception("Unable to connect to Ampache database.");
    }

    $stmt = $pdo->query("
        SELECT s.id, s.title, s.time as duration, s.artist as artistId, ar.name as artistName 
        FROM song s 
        LEFT JOIN artist ar ON s.artist = ar.id 
        WHERE s.album IS NULL OR s.album = 0 OR s.album = ''
        ORDER BY s.title ASC
    ");
    
    $tracks = $stmt->fetchAll();
    
    // Format them to match Subsonic song objects
    $formatted = array_map(function($t) {
        return [
            'id' => $t['id'],
            'title' => $t['title'],
            'artist' => $t['artistName'] ?: 'Unknown Artist',
            'artistId' => $t['artistId'] ?: 0,
            'album' => 'Unknown Album',
            'albumId' => 'unknown',
            'duration' => (int)$t['duration'],
            'coverArt' => 'unknown'
        ];
    }, $tracks);

    echo json_encode(['status' => 'success', 'songs' => $formatted]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
