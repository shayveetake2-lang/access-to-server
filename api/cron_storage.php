<?php
// api/cron_storage.php - Run via cron to update storage_cache.json
// Example cron: * * * * * php /Volumes/htdocs/access-to-server/api/cron_storage.php

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

function isDriveResponsive($path, $timeoutSeconds = 1) {
    $desc = [ 0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"] ];
    $proc = @proc_open("ls -d " . escapeshellarg($path) . " 2>/dev/null", $desc, $pipes);
    if (!is_resource($proc)) return false;
    
    stream_set_blocking($pipes[1], 0);
    $start = microtime(true);
    $isResponsive = false;
    
    while (microtime(true) - $start < $timeoutSeconds) {
        $status = proc_get_status($proc);
        if (!$status['running']) {
            $isResponsive = ($status['exitcode'] === 0);
            break;
        }
        usleep(50000); // 50ms
    }
    
    $status = proc_get_status($proc);
    if ($status['running']) {
        @proc_terminate($proc, 9);
    }
    foreach ($pipes as $p) @fclose($p);
    @proc_close($proc);
    
    return $isResponsive;
}

function getVolumeStats($name, $path, $type, $isMounted = null) {
    if ($type === 'network' || $type === 'usb') {
        if (!isDriveResponsive($path, 1)) {
            $isMounted = false;
        }
    }

    if ($isMounted === null) {
        $isMounted = @is_dir($path);
    }

    $freeBytes  = 0;
    $totalBytes = 0;
    $usedBytes  = 0;
    $percent    = 0.0;
    $statusText = 'Unmounted / Offline';

    if ($isMounted) {
        $free  = @disk_free_space($path);
        $total = @disk_total_space($path);
        if ($free !== false && $total !== false && $total > 0) {
            $freeBytes  = (float)$free;
            $totalBytes = (float)$total;
            $usedBytes  = max(0, $totalBytes - $freeBytes);
            $percent    = round(($usedBytes / $totalBytes) * 100, 1);
            $statusText = 'Mounted / Connected';
        } else {
            $isMounted = false;
        }
    }
    
    if (!$isMounted) {
        if ($type === 'network') {
            $statusText = 'Offline/Sleeping';
        } elseif ($type === 'usb') {
            $statusText = 'Empty';
        }
        $formattedFallback = $statusText;
    }

    return [
        'name'            => $name,
        'path'            => $path,
        'type'            => $type,
        'mounted'         => (bool)$isMounted,
        'status'          => $isMounted ? 'online' : 'offline',
        'status_text'     => $statusText,
        'free'            => $freeBytes,
        'total'           => $totalBytes,
        'used'            => $usedBytes,
        'percent_used'    => $percent,
        'free_formatted'  => $isMounted ? formatBytes($freeBytes) : $formattedFallback,
        'total_formatted' => $isMounted ? formatBytes($totalBytes) : $formattedFallback,
        'used_formatted'  => $isMounted ? formatBytes($usedBytes) : $formattedFallback
    ];
}

// 1. Primary Internal SSD
$ssdPath = '/';
$ssdStats = getVolumeStats('Internal SSD', $ssdPath, 'internal', true);

// 2. Multi-Port USB Detection
$detectedUsbVolumes = [];
$currentUser = get_current_user();
$systemExcluded = array_filter(['Macintosh HD', 'Macintosh', 'Recovery', 'htdocs', 'Music', 'Movies', 'Movie', $currentUser, 'akshayveerasamy']);

if (@is_dir('/Volumes')) {
    $volumes = @scandir('/Volumes');
    if (is_array($volumes)) {
        foreach ($volumes as $vol) {
            if ($vol === '.' || $vol === '..' || strpos($vol, '.') === 0) continue;
            if (in_array($vol, $systemExcluded, true)) continue;

            $fullPath = '/Volumes/' . $vol;
            if (@is_dir($fullPath)) {
                if (is_link($fullPath) && realpath($fullPath) === '/') continue;
                $detectedUsbVolumes[] = [
                    'label' => $vol,
                    'path'  => $fullPath
                ];
            }
        }
    }
}

$usb1Path = null; $usb1Label = null; $usb1Mounted = false;
$usb2Path = null; $usb2Label = null; $usb2Mounted = false;

if (@is_dir('/Volumes/USBDrive')) {
    $usb1Path = '/Volumes/USBDrive'; $usb1Label = 'USBDrive'; $usb1Mounted = true;
}
if (@is_dir('/Volumes/USBDrive 1')) {
    $usb2Path = '/Volumes/USBDrive 1'; $usb2Label = 'USBDrive 1'; $usb2Mounted = true;
} elseif (@is_dir('/Volumes/USBDrive2')) {
    $usb2Path = '/Volumes/USBDrive2'; $usb2Label = 'USBDrive 2'; $usb2Mounted = true;
}

foreach ($detectedUsbVolumes as $volInfo) {
    if ($usb1Mounted && $usb1Path === $volInfo['path']) continue;
    if ($usb2Mounted && $usb2Path === $volInfo['path']) continue;

    if (!$usb1Mounted) {
        $usb1Path = $volInfo['path']; $usb1Label = $volInfo['label']; $usb1Mounted = true;
    } elseif (!$usb2Mounted) {
        $usb2Path = $volInfo['path']; $usb2Label = $volInfo['label']; $usb2Mounted = true;
    }
}

$usbPort1 = getVolumeStats($usb1Mounted && $usb1Label ? "USB Port 1 ({$usb1Label})" : 'USB Port 1', $usb1Path ?: '/Volumes/USBDrive', 'usb', $usb1Mounted);
$usbPort1['port_number'] = 1;
$usbPort1['volume_label'] = $usb1Mounted ? $usb1Label : null;

$usbPort2 = getVolumeStats($usb2Mounted && $usb2Label ? "USB Port 2 ({$usb2Label})" : 'USB Port 2', $usb2Path ?: '/Volumes/USBDrive 1', 'usb', $usb2Mounted);
$usbPort2['port_number'] = 2;
$usbPort2['volume_label'] = $usb2Mounted ? $usb2Label : null;

// 3. External Network Nodes (Grouped into Unified Network Storage)
$mac2Mounted = @is_dir('/Volumes/Music');
$mac2Stats = getVolumeStats('mac2 (Music Node)', '/Volumes/Music', 'network', $mac2Mounted);

$moviePath = @is_dir('/Volumes/Movies') ? '/Volumes/Movies' : '/Volumes/Movie';
$movieMounted = @is_dir('/Volumes/Movies') || @is_dir('/Volumes/Movie');
$movieStats = getVolumeStats('Movie Storage Drive', $moviePath, 'network', $movieMounted);

// Grouped Network Storage
$networkFree = $mac2Stats['free'] + $movieStats['free'];
$networkTotal = $mac2Stats['total'] + $movieStats['total'];
$networkUsed = $mac2Stats['used'] + $movieStats['used'];
$networkPercent = $networkTotal > 0 ? round(($networkUsed / $networkTotal) * 100, 1) : 0;

$networkStorage = [
    'name' => 'Unified Network Storage',
    'status' => ($mac2Mounted || $movieMounted) ? 'online' : 'offline',
    'mounted' => ($mac2Mounted || $movieMounted),
    'free' => $networkFree,
    'total' => $networkTotal,
    'used' => $networkUsed,
    'percent_used' => $networkPercent,
    'free_formatted' => ($mac2Mounted || $movieMounted) ? formatBytes($networkFree) : 'Offline/Sleeping',
    'total_formatted' => ($mac2Mounted || $movieMounted) ? formatBytes($networkTotal) : 'Offline/Sleeping',
    'used_formatted' => ($mac2Mounted || $movieMounted) ? formatBytes($networkUsed) : 'Offline/Sleeping',
    'nodes' => [
        'mac2' => $mac2Stats,
        'movies' => $movieStats
    ]
];

$totalConnectedDrives = ($ssdStats['mounted'] ? 1 : 0) + ($usbPort1['mounted'] ? 1 : 0) + ($usbPort2['mounted'] ? 1 : 0) + ($mac2Mounted ? 1 : 0) + ($movieMounted ? 1 : 0);

$data = [
    'status'    => 'success',
    'timestamp' => time(),
    'time_str'  => date('H:i:s'),
    'summary'   => [
        'total_drives_monitored' => 5,
        'total_connected'        => $totalConnectedDrives,
        'usb_connected_count'    => ($usbPort1['mounted'] ? 1 : 0) + ($usbPort2['mounted'] ? 1 : 0),
        'network_nodes_online'   => ($mac2Mounted ? 1 : 0) + ($movieMounted ? 1 : 0),
        'detected_usb_list'      => $detectedUsbVolumes
    ],
    'drives'    => [
        'ssd'       => $ssdStats,
        'usb_port1' => $usbPort1,
        'usb_port2' => $usbPort2,
        'network'   => $networkStorage // Unified Network Storage
    ]
];

file_put_contents(__DIR__ . '/storage_cache.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Cache updated successfully.\n";

