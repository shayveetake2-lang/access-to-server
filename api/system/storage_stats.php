<?php
// api/system/storage_stats.php - Unified Storage Stats
require_once __DIR__ . '/../auth/require_admin.php';
// Let's not require admin since the frontend tries to load it on page load for guests too, or maybe guests can see it?
// In the original, server_storage.php has requireAdmin(), but checkAuthOnLoad handles if it's admin or not. Let's just make it public like usb_manager.php's GET request (which is public).

header('Content-Type: application/json; charset=UTF-8');

function formatBytes($bytes, $precision = 2) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

$htdocsPath = realpath(__DIR__ . '/../../../'); // /Volumes/htdocs
$htdocsTotal = @disk_total_space($htdocsPath) ?: @disk_total_space(__DIR__);
$htdocsFree  = @disk_free_space($htdocsPath) ?: @disk_free_space(__DIR__);
$htdocsUsed  = $htdocsTotal - $htdocsFree;
$htdocsPct   = $htdocsTotal > 0 ? round(($htdocsUsed / $htdocsTotal) * 100, 1) : 0;

$usbPath = '/Volumes/USBDrive';
$usbTotal = @disk_total_space($usbPath) ?: 0;
$usbFree  = @disk_free_space($usbPath) ?: 0;
$usbUsed  = $usbTotal - $usbFree;

echo json_encode([
    'status' => 'success',
    'ssd' => [
        'path' => $htdocsPath,
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
        'total_formatted' => formatBytes($usbTotal),
        'free_formatted' => formatBytes($usbFree),
        'used_formatted' => formatBytes($usbUsed)
    ]
]);
