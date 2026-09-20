<?php
// api/system/storage_stats.php — Unified Storage Stats (Authenticated)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// Public storage telemetry (read-only)

function formatBytes($bytes, $precision = 2) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

$htdocsPath = realpath(__DIR__ . '/../../../') ?: __DIR__;
$htdocsTotal = @disk_total_space($htdocsPath) ?: 0;
$htdocsFree  = @disk_free_space($htdocsPath) ?: 0;
$htdocsUsed  = max(0, $htdocsTotal - $htdocsFree);
$htdocsPct   = $htdocsTotal > 0 ? round(($htdocsUsed / $htdocsTotal) * 100, 1) : 0;

$usbPath = '/Volumes/Music';
if (!is_dir($usbPath)) {
    $usbPath = '/Volumes/USBDrive';
}
$usbTotal = is_dir($usbPath) ? (@disk_total_space($usbPath) ?: 0) : 0;
$usbFree  = is_dir($usbPath) ? (@disk_free_space($usbPath) ?: 0) : 0;
$usbUsed  = max(0, $usbTotal - $usbFree);
$usbPct   = $usbTotal > 0 ? round(($usbUsed / $usbTotal) * 100, 1) : 0;

echo json_encode([
    'status' => 'success',
    'ssd' => [
        'total' => $htdocsTotal,
        'free' => $htdocsFree,
        'used' => $htdocsUsed,
        'percent_used' => $htdocsPct,
        'total_formatted' => formatBytes($htdocsTotal),
        'free_formatted' => formatBytes($htdocsFree),
        'used_formatted' => formatBytes($htdocsUsed)
    ],
    'usb' => [
        'connected' => $usbTotal > 0,
        'total' => $usbTotal,
        'free' => $usbFree,
        'used' => $usbUsed,
        'percent_used' => $usbPct,
        'total_formatted' => formatBytes($usbTotal),
        'free_formatted' => formatBytes($usbFree),
        'used_formatted' => formatBytes($usbUsed)
    ]
]);
