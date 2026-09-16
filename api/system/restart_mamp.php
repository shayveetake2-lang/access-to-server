<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=UTF-8');

$pidFile = '/Applications/MAMP/Library/logs/httpd.pid';
if (!file_exists($pidFile)) {
    $pidFile = '/Applications/MAMP/logs/httpd.pid';
}

$output = [];
$reloaded = false;
$pid = 0;

if (file_exists($pidFile)) {
    $pid = (int)trim((string)file_get_contents($pidFile));
    if ($pid > 0) {
        if (function_exists('posix_kill')) {
            // 30 is SIGUSR1 on Darwin/macOS
            $reloaded = @posix_kill($pid, 30);
        }
        if (!$reloaded) {
            exec("kill -USR1 {$pid} 2>&1", $output, $ret);
            $reloaded = ($ret === 0);
        }
    }
}

if (!$reloaded) {
    exec('/Applications/MAMP/Library/bin/apachectl graceful 2>&1', $output, $ret);
    $reloaded = ($ret === 0);
}

echo json_encode([
    'status' => 'success',
    'reloaded' => $reloaded,
    'apache_pid' => $pid,
    'message' => 'Apache graceful reload signal dispatched.',
    'output' => $output,
    'current_ini' => [
        'loaded_ini_file' => php_ini_loaded_file(),
        'php_version' => PHP_VERSION,
        'max_execution_time' => ini_get('max_execution_time'),
        'max_input_time' => ini_get('max_input_time'),
        'memory_limit' => ini_get('memory_limit'),
        'opcache_loaded' => extension_loaded('Zend OPcache'),
        'opcache_enabled' => ini_get('opcache.enable'),
    ]
], JSON_PRETTY_PRINT);

