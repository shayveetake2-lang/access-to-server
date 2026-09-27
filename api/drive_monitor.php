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
    // SECURITY/PERFORMANCE PATCH: Async spawn to prevent worker blocking
    $cronScript = escapeshellarg(__DIR__ . '/cron_storage.php');
    exec("php {$cronScript} > /dev/null 2>&1 &");
    
    http_response_code(202);
    echo json_encode([
        'status' => 'processing', 
        'message' => 'Storage cache is generating in the background. Please retry shortly.',
        'drives' => [] 
    ]);
}
