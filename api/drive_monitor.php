<?php
// api/drive_monitor.php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

function formatBytes($bytes, $precision = 2) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Volume labels that are never candidate matches for our monitored drives (system/reserved).
const RESERVED_VOLUME_LABELS = ['Macintosh HD', 'Recovery', 'com.apple.TimeMachine.localsnapshots', 'htdocs'];

/**
 * Lists mountable volume labels under /Volumes, excluding reserved system entries.
 * Returns [] (never throws) if /Volumes itself is unreadable.
 */
function listAvailableVolumes() {
    $labels = [];
    $entries = @scandir('/Volumes');
    if ($entries === false) return $labels;
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (in_array($entry, RESERVED_VOLUME_LABELS, true)) continue;
        if (@is_dir('/Volumes/' . $entry)) $labels[] = $entry;
    }
    return $labels;
}

/**
 * Resolves a drive's real mount path. If the hardcoded $preferredPath doesn't resolve,
 * dynamically scans /Volumes for a label matching $labelPattern (case-insensitive regex)
 * instead of failing outright. Returns ['path' => ..., 'fallback' => bool].
 */
function resolveVolumePath($preferredPath, $labelPattern, array $excludeLabels = [], $allowGenericFallback = false) {
    if (@is_dir($preferredPath)) {
        return ['path' => $preferredPath, 'fallback' => false];
    }
    $available = listAvailableVolumes();
    foreach ($available as $label) {
        if (in_array($label, $excludeLabels, true)) continue;
        if ($labelPattern !== null && preg_match($labelPattern, $label)) {
            return ['path' => '/Volumes/' . $label, 'fallback' => true];
        }
    }
    // No label matched the expected naming pattern (e.g. a USB drive with a custom name).
    // As a last resort, claim the first unclaimed, non-reserved volume so the drive still
    // shows as connected instead of silently reporting "offline".
    if ($allowGenericFallback) {
        foreach ($available as $label) {
            if (in_array($label, $excludeLabels, true)) continue;
            return ['path' => '/Volumes/' . $label, 'fallback' => true];
        }
    }
    return ['path' => $preferredPath, 'fallback' => false];
}

function getVolumeStats($name, $path, $type) {
    // strict error suppression to prevent hanging on sleeping/unmounted drives
    $isMounted = @is_dir($path);
    
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
            $statusText = 'Offline';
        } elseif ($type === 'usb') {
            $statusText = 'Empty';
        } else {
            $statusText = 'Unmounted';
        }
        
        return [
            'name'            => $name,
            'path'            => $path,
            'type'            => $type,
            'mounted'         => false,
            'status'          => 'offline',
            'status_text'     => $statusText,
            'free'            => 0,
            'total'           => 0,
            'used'            => 0,
            'percent_used'    => 0,
            'free_formatted'  => $statusText,
            'total_formatted' => $statusText,
            'used_formatted'  => $statusText
        ];
    }

    return [
        'name'            => $name,
        'path'            => $path,
        'type'            => $type,
        'mounted'         => true,
        'status'          => 'online',
        'status_text'     => $statusText,
        'free'            => $freeBytes,
        'total'           => $totalBytes,
        'used'            => $usedBytes,
        'percent_used'    => $percent,
        'free_formatted'  => formatBytes($freeBytes),
        'total_formatted' => formatBytes($totalBytes),
        'used_formatted'  => formatBytes($usedBytes)
    ];
}

// Hardcoded defaults, with dynamic /Volumes fallback if the expected mount point isn't present
// (e.g. macOS assigned a suffixed name like "Music 1" because of a stale prior mount).
$ssdStats = getVolumeStats('Internal SSD', '/Applications/MAMP/htdocs', 'internal');

$mac2Resolved = resolveVolumePath('/Volumes/Music', '/^music/i');
$mac2Stats = getVolumeStats('mac2 (Music Node)', $mac2Resolved['path'], 'network');
$mac2Stats['resolved_via_fallback'] = $mac2Resolved['fallback'];
$mac2Label = $mac2Stats['mounted'] ? basename($mac2Resolved['path']) : '';

// USB Port 1: Physical USB Port (strictly target USBDrive1 or explicit USB label, no generic fallback)
$usb1Resolved = resolveVolumePath('/Volumes/USBDrive1', '/^usb(drive)?1?$/i', array_filter([$mac2Label, 'Music', 'htdocs']), false);
$usbPort1 = getVolumeStats('USB Port 1', $usb1Resolved['path'], 'usb');
$usbPort1['port_number'] = 1;
$usbPort1['resolved_via_fallback'] = $usb1Resolved['fallback'];
$usbPort1['volume_label'] = $usbPort1['mounted'] ? basename($usb1Resolved['path']) : null;

// USB Port 2: Physical USB Port (strictly target USBDrive2 or explicit USB2 label, never claim mac2 or network mounts)
$usb2Resolved = resolveVolumePath('/Volumes/USBDrive2', '/^usb(drive)?2?$/i', array_filter([$mac2Label, 'Music', 'htdocs', $usbPort1['volume_label'] ?: '']), false);
$usbPort2 = getVolumeStats('USB Port 2', $usb2Resolved['path'], 'usb');
$usbPort2['port_number'] = 2;
$usbPort2['resolved_via_fallback'] = $usb2Resolved['fallback'];
$usbPort2['volume_label'] = $usbPort2['mounted'] ? basename($usb2Resolved['path']) : null;

// The unified network storage is now just mac2
$networkStorage = $mac2Stats;
$networkStorage['name'] = 'Unified Network Storage';
$networkStorage['nodes'] = [
    'mac2' => $mac2Stats
];

$totalConnectedDrives = ($ssdStats['mounted'] ? 1 : 0) + 
                        ($usbPort1['mounted'] ? 1 : 0) + 
                        ($usbPort2['mounted'] ? 1 : 0) + 
                        ($mac2Stats['mounted'] ? 1 : 0);

$usbConnectedCount = ($usbPort1['mounted'] ? 1 : 0) + ($usbPort2['mounted'] ? 1 : 0);

$data = [
    'status'    => 'success',
    'timestamp' => time(),
    'time_str'  => date('H:i:s'),
    'summary'   => [
        'total_drives_monitored' => 4,
        'total_connected'        => $totalConnectedDrives,
        'usb_connected_count'    => $usbConnectedCount,
        'network_nodes_online'   => $mac2Stats['mounted'] ? 1 : 0,
        'detected_usb_list'      => array_values(array_filter([$usbPort1['volume_label'], $usbPort2['volume_label']]))
    ],
    'drives'    => [
        'ssd'       => $ssdStats,
        'usb_port1' => $usbPort1,
        'usb_port2' => $usbPort2,
        'network'   => $networkStorage
    ]
];

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
