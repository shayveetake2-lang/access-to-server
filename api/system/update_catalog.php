<?php
// api/system/update_catalog.php
// Secure, Hardware-Throttled Music Catalog Update Service for 2011 MacBook Pro

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../auth/require_admin.php';
requireAdmin();

$lockFile = '/tmp/serverflow_catalog_update.lock';
$logFile  = '/tmp/serverflow_catalog.log';
$ampacheCli = realpath(__DIR__ . '/../../ampache/bin/cli');

// Check if a scan process is currently active
function isProcessRunning($pid) {
    if (empty($pid) || !is_numeric($pid)) return false;
    $check = trim((string)shell_exec("ps -p " . intval($pid) . " -o pid="));
    return !empty($check);
}

// 1. Status Check Mode (GET ?status=1)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $isRunning = false;
    $pid = null;

    if (file_exists($lockFile)) {
        $pid = trim(@file_get_contents($lockFile));
        if (isProcessRunning($pid)) {
            $isRunning = true;
        } else {
            // Clean up stale lockfile
            @unlink($lockFile);
        }
    }

    $logTail = '';
    if (file_exists($logFile)) {
        $logTail = shell_exec("tail -n 15 " . escapeshellarg($logFile) . " 2>&1") ?: '';
    }

    echo json_encode([
        'status'     => 'success',
        'running'    => $isRunning,
        'pid'        => $isRunning ? (int)$pid : null,
        'recent_log' => $logTail,
        'message'    => $isRunning ? 'Catalog scan is currently in progress.' : 'No catalog scan running.'
    ]);
    exit;
}

// 2. Trigger Scan Mode (POST)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (file_exists($lockFile)) {
        $pid = trim(@file_get_contents($lockFile));
        if (isProcessRunning($pid)) {
            http_response_code(429); // Too Many Requests
            echo json_encode([
                'status'  => 'error',
                'message' => 'Music catalog update is already running in background (PID ' . intval($pid) . '). Please wait for it to finish.'
            ]);
            exit;
        } else {
            @unlink($lockFile);
        }
    }

    if (!$ampacheCli || !file_exists($ampacheCli)) {
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Ampache CLI utility not found at expected path.'
        ]);
        exit;
    }

    // Hardware-throttled execution for 2011 MacBook:
    // - nice -n 15: lowest CPU scheduling priority to keep temperature low and fans quiet
    // - -a: incremental add only (does NOT re-verify all 10,000 files or re-scrape art)
    // - suppress deprecation output from old Ampache libs to prevent disk I/O thrashing
    $escapedCli = escapeshellarg($ampacheCli);
    $escapedLog = escapeshellarg($logFile);
    
    // Clear old log file
    @file_put_contents($logFile, "=== Starting Catalog Update at " . date('Y-m-d H:i:s') . " ===\n");

    $cmd = "nice -n 15 php -d error_reporting=\"E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED\" " .
           "$escapedCli run:updateCatalog -a >> $escapedLog 2>&1 & echo $!";
    
    $spawnedPid = trim((string)shell_exec($cmd));

    if (!empty($spawnedPid) && is_numeric($spawnedPid)) {
        @file_put_contents($lockFile, $spawnedPid);
        echo json_encode([
            'status'  => 'success',
            'pid'     => (int)$spawnedPid,
            'message' => 'Music catalog scan started gently in background. New tracks will appear once indexed.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Failed to spawn background catalog scan process.'
        ]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);

