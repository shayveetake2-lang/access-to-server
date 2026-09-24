<?php
// api/drive_monitor.php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

$cacheFile = __DIR__ . '/storage_cache.json';

if (file_exists($cacheFile)) {
    echo file_get_contents($cacheFile);
} else {
    // If not generated yet, try generating it on-the-fly once
    // Alternatively, just return an error so as not to hang the frontend.
    include_once __DIR__ . '/cron_storage.php';
    if (file_exists($cacheFile)) {
        echo file_get_contents($cacheFile);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Storage cache not available']);
    }
}
