<?php
// api/drive_monitor.php — Multi-Port Live Drive & Storage Telemetry API
// Accessible publicly (no login required) to ensure clean dashboard loading

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

if (!function_exists('formatBytes')) {
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

function getVolumeStats($name, $path, $type, $isMounted = null) {
    if ($isMounted === null) {
        $isMounted = is_dir($path);
    }

    $freeBytes  = 0;
    $totalBytes = 0;
    $usedBytes  = 0;
    $percent    = 0.0;

    if ($isMounted) {
        $free  = @disk_free_space($path);
        $total = @disk_total_space($path);
        if ($free !== false && $total !== false && $total > 0) {
            $freeBytes  = (float)$free;
            $totalBytes = (float)$total;
            $usedBytes  = max(0, $totalBytes - $freeBytes);
            $percent    = round(($usedBytes / $totalBytes) * 100, 1);
        }
    }

    return [
        'name'            => $name,
        'path'            => $path,
        'type'            => $type, // 'internal', 'usb', 'network'
        'mounted'         => (bool)$isMounted,
        'status'          => $isMounted ? 'online' : 'offline',
        'status_text'     => $isMounted ? 'Mounted / Connected' : 'Unmounted / Offline',
        'free'            => $freeBytes,
        'total'           => $totalBytes,
        'used'            => $usedBytes,
        'percent_used'    => $percent,
        'free_formatted'  => formatBytes($freeBytes),
        'total_formatted' => formatBytes($totalBytes),
        'used_formatted'  => formatBytes($usedBytes)
    ];
}

// 1. Primary Internal SSD (MacBook Pro 2011 Boot Disk)
$ssdPath = '/';
$ssdStats = getVolumeStats('Internal SSD', $ssdPath, 'internal', true);

// 2. Multi-Port USB Detection (MacBook Pro 2011 physical USB ports)
// Detect all attached external volumes in /Volumes/ excluding system/network shares
$detectedUsbVolumes = [];
$currentUser = get_current_user();
$systemExcluded = array_filter(['Macintosh HD', 'Macintosh', 'Recovery', 'htdocs', 'Music', 'Movies', 'Movie', $currentUser, 'akshayveerasamy']);

if (is_dir('/Volumes')) {
    $volumes = @scandir('/Volumes');
    if (is_array($volumes)) {
        foreach ($volumes as $vol) {
            if ($vol === '.' || $vol === '..' || strpos($vol, '.') === 0) continue;
            if (in_array($vol, $systemExcluded, true)) continue;

            $fullPath = '/Volumes/' . $vol;
            if (is_dir($fullPath)) {
                // Ensure it's not a symlink pointing back to root
                if (is_link($fullPath) && realpath($fullPath) === '/') continue;
                $detectedUsbVolumes[] = [
                    'label' => $vol,
                    'path'  => $fullPath
                ];
            }
        }
    }
}

// Map detected volumes to physical USB Port 1 and USB Port 2
// Port 1 defaults to /Volumes/USBDrive if present, or first detected USB volume
$usb1Path = null;
$usb1Label = null;
$usb1Mounted = false;

// Port 2 defaults to /Volumes/USBDrive 1 or /Volumes/USBDrive2 or second detected volume
$usb2Path = null;
$usb2Label = null;
$usb2Mounted = false;

// Check explicit default mount points first
if (is_dir('/Volumes/USBDrive')) {
    $usb1Path = '/Volumes/USBDrive';
    $usb1Label = 'USBDrive';
    $usb1Mounted = true;
}

if (is_dir('/Volumes/USBDrive 1')) {
    $usb2Path = '/Volumes/USBDrive 1';
    $usb2Label = 'USBDrive 1';
    $usb2Mounted = true;
} elseif (is_dir('/Volumes/USBDrive2')) {
    $usb2Path = '/Volumes/USBDrive2';
    $usb2Label = 'USBDrive 2';
    $usb2Mounted = true;
}

// Allocate from dynamically detected USB volumes if not already matched
foreach ($detectedUsbVolumes as $volInfo) {
    if ($usb1Mounted && $usb1Path === $volInfo['path']) continue;
    if ($usb2Mounted && $usb2Path === $volInfo['path']) continue;

    if (!$usb1Mounted) {
        $usb1Path = $volInfo['path'];
        $usb1Label = $volInfo['label'];
        $usb1Mounted = true;
    } elseif (!$usb2Mounted) {
        $usb2Path = $volInfo['path'];
        $usb2Label = $volInfo['label'];
        $usb2Mounted = true;
    }
}

$usbPort1 = getVolumeStats(
    $usb1Mounted && $usb1Label ? "USB Port 1 ({$usb1Label})" : 'USB Port 1',
    $usb1Path ?: '/Volumes/USBDrive',
    'usb',
    $usb1Mounted
);
$usbPort1['port_number'] = 1;
$usbPort1['volume_label'] = $usb1Mounted ? $usb1Label : null;

$usbPort2 = getVolumeStats(
    $usb2Mounted && $usb2Label ? "USB Port 2 ({$usb2Label})" : 'USB Port 2',
    $usb2Path ?: '/Volumes/USBDrive 1',
    'usb',
    $usb2Mounted
);
$usbPort2['port_number'] = 2;
$usbPort2['volume_label'] = $usb2Mounted ? $usb2Label : null;

// 3. External Network Node: mac2 (Windows Laptop / Music Node)
$mac2Mounted = is_dir('/Volumes/Music');
$mac2Stats = getVolumeStats(
    'mac2 (Music Node)',
    '/Volumes/Music',
    'network',
    $mac2Mounted
);
$mac2Stats['node_type'] = 'Windows Laptop (SMB)';

// 4. External Network Node: Movie Drive (Primary Mac/Windows Movie Drive)
$moviePath = is_dir('/Volumes/Movies') ? '/Volumes/Movies' : '/Volumes/Movie';
$movieMounted = is_dir('/Volumes/Movies') || is_dir('/Volumes/Movie');
$movieStats = getVolumeStats(
    'Movie Storage Drive',
    $moviePath,
    'network',
    $movieMounted
);
$movieStats['node_type'] = 'Primary Movie Drive';

// Overall summary
$totalConnectedDrives = ($ssdStats['mounted'] ? 1 : 0)
                      + ($usbPort1['mounted'] ? 1 : 0)
                      + ($usbPort2['mounted'] ? 1 : 0)
                      + ($mac2Stats['mounted'] ? 1 : 0)
                      + ($movieStats['mounted'] ? 1 : 0);

echo json_encode([
    'status'    => 'success',
    'timestamp' => time(),
    'time_str'  => date('H:i:s'),
    'summary'   => [
        'total_drives_monitored' => 5,
        'total_connected'        => $totalConnectedDrives,
        'usb_connected_count'    => ($usbPort1['mounted'] ? 1 : 0) + ($usbPort2['mounted'] ? 1 : 0),
        'network_nodes_online'   => ($mac2Stats['mounted'] ? 1 : 0) + ($movieStats['mounted'] ? 1 : 0),
        'detected_usb_list'      => $detectedUsbVolumes
    ],
    'drives'    => [
        'ssd'       => $ssdStats,
        'usb_port1' => $usbPort1,
        'usb_port2' => $usbPort2,
        'mac2'      => $mac2Stats,
        'movies'    => $movieStats
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
