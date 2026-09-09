<?php
// server_storage.php — Server-Wide Storage Telemetry API
require_once __DIR__ . '/../auth/require_admin.php';
requireAdmin();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if (!function_exists('formatBytes')) {
    /**
     * Format bytes to human-readable units.
     */
    function formatBytes($bytes, $precision = 2) {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

/**
 * Recursively calculate the total size of a directory in bytes.
 * Returns 0 if the directory doesn't exist or isn't readable.
 */
function dirSize($path) {
    if (!is_dir($path) || !is_readable($path)) return 0;
    $size = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    return $size;
}

// ── Configuration ──────────────────────────────────────────
// Root of the web server document root (where access-to-server lives)
$serverRoot  = realpath(__DIR__ . '/../../');        // /Volumes/htdocs/access-to-server
$htdocsRoot  = realpath(__DIR__ . '/../../../');     // /Volumes/htdocs  (MAMP web root)

// The disk partition we measure capacity against
$diskPath = $htdocsRoot ?: $serverRoot;

// ── Overall disk capacity ──────────────────────────────────
$totalBytes = @disk_total_space($diskPath);
$freeBytes  = @disk_free_space($diskPath);

if ($totalBytes === false || $freeBytes === false || $totalBytes <= 0) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Unable to read disk capacity for ' . $diskPath
    ]);
    exit;
}

$usedBytes   = $totalBytes - $freeBytes;
$percentUsed = round(($usedBytes / $totalBytes) * 100, 1);

// ── Asset breakdown ────────────────────────────────────────
// Each entry: [label, path, tailwind-color-key]
$assetDefs = [
    ['Deployed Sites',  $serverRoot . '/sites',    'cyan'],
    ['API & System',    $serverRoot . '/api',       'indigo'],
    ['Configuration',   $serverRoot . '/config',    'violet'],
    ['Console App',     $serverRoot,                'slate'],   // top-level files only
];

// Include MySQL Data if directory exists
if (is_dir('/Applications/MAMP/db/mysql')) {
    $assetDefs[] = ['MySQL Data', '/Applications/MAMP/db/mysql', 'amber'];
}

$breakdown = [];
$accountedBytes = 0;

foreach ($assetDefs as [$label, $path, $color]) {
    // For "Console App" we only count top-level files (not subdirs already counted)
    if ($label === 'Console App') {
        $bytes = 0;
        foreach (glob($path . '/*') as $item) {
            if (is_file($item)) {
                $bytes += filesize($item);
            }
        }
    } else {
        $bytes = dirSize($path);
    }

    $breakdown[] = [
        'label'     => $label,
        'path'      => $path,
        'bytes'     => $bytes,
        'formatted' => formatBytes($bytes),
        'color'     => $color,
        'exists'    => is_dir($path),
    ];
    $accountedBytes += $bytes;
}

// "Other / OS" = used space we can't attribute to tracked dirs
$otherBytes = max(0, $usedBytes - $accountedBytes);
$breakdown[] = [
    'label'     => 'Other / OS',
    'path'      => null,
    'bytes'     => $otherBytes,
    'formatted' => formatBytes($otherBytes),
    'color'     => 'slate',
    'exists'    => true,
];

// ── Response ───────────────────────────────────────────────
echo json_encode([
    'status'          => 'success',
    'disk_path'       => $diskPath,
    'total_bytes'     => $totalBytes,
    'free_bytes'      => $freeBytes,
    'used_bytes'      => $usedBytes,
    'percent_used'    => $percentUsed,
    'total_gb'        => round($totalBytes / (1024 * 1024 * 1024), 2),
    'free_gb'         => round($freeBytes / (1024 * 1024 * 1024), 2),
    'used_gb'         => round($usedBytes / (1024 * 1024 * 1024), 2),
    'used'            => formatBytes($usedBytes),
    'free'            => formatBytes($freeBytes),
    'total'           => formatBytes($totalBytes),
    'total_formatted' => formatBytes($totalBytes),
    'free_formatted'  => formatBytes($freeBytes),
    'used_formatted'  => formatBytes($usedBytes),
    'breakdown'       => $breakdown,
]);

