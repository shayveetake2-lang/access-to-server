<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/media/_request_auth.php'; // Verify JWT

header('Content-Type: application/json; charset=UTF-8');

try {
    $host = defined('AMPACHE_DB_HOST') ? AMPACHE_DB_HOST : '127.0.0.1';
    $port = defined('AMPACHE_DB_PORT') ? AMPACHE_DB_PORT : '8889';
    $dbname = defined('AMPACHE_DB_NAME') ? AMPACHE_DB_NAME : 'ampache';
    $user = defined('AMPACHE_DB_USER') ? AMPACHE_DB_USER : 'ampache_user';
    $pass = defined('AMPACHE_DB_PASS') ? AMPACHE_DB_PASS : 'password';

    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->query("
        SELECT s.id, s.title, s.time as duration, s.artist as artistId, ar.name as artistName 
        FROM song s 
        LEFT JOIN artist ar ON s.artist = ar.id 
        WHERE s.album IS NULL OR s.album = 0 OR s.album = ''
        ORDER BY s.title ASC
    ");
    
    $tracks = $stmt->fetchAll();
    
    // Format them to match subsonic song objects
    $formatted = array_map(function($t) {
        return [
            'id' => $t['id'],
            'title' => $t['title'],
            'artist' => $t['artistName'] ?: 'Unknown Artist',
            'artistId' => $t['artistId'] ?: 0,
            'album' => 'Unknown Album',
            'duration' => $t['duration'],
            // Add coverArt mapping if necessary, or let it fall back
        ];
    }, $tracks);

    echo json_encode(['status' => 'success', 'songs' => $formatted]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

