<?php
/**
 * api/phase5_audit.php — Non-destructive Phase 5 self-test diagnostic.
 *
 * Verifies:
 *  1. PHP error suppression is actually configured (display_errors/log_errors sanity)
 *  2. Network mount / drive paths can be probed with @-suppression without hanging or warning
 *  3. JSON responses round-trip cleanly with zero stray output or PHP notices
 *  4. Sibling API scripts still have valid PHP syntax (read-only `php -l`, nothing is executed)
 *  5. Database connectivity is reachable (best-effort; failure here is reported, not throw)
 *
 * Every check is wrapped so that its own warnings/notices/stray echoes are captured instead of
 * leaking into this script's JSON output, and its wall-clock time is measured — any check over
 * SLOW_CHECK_THRESHOLD_SEC is flagged as a risk of hanging an Apache worker thread (relevant on
 * the 2011 MacBook Pro's network mounts, which can stall on disk_free_space()/is_dir() calls).
 *
 * This script makes no writes, no deletes, and issues no destructive commands — safe to run
 * repeatedly against production.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

const SLOW_CHECK_THRESHOLD_SEC = 1.5;

/**
 * Runs a single named check in isolation: captures stray echoed output and PHP
 * notices/warnings/deprecations raised inside $fn, and times its execution.
 */
function runAuditCheck(string $name, callable $fn): array {
    $start = microtime(true);
    $collectedErrors = [];

    set_error_handler(function ($errno, $errstr) use (&$collectedErrors) {
        $collectedErrors[] = $errstr;
        return true; // swallow so it never reaches the real output stream
    });

    ob_start();
    $status = 'pass';
    $detail = null;
    try {
        $detail = $fn();
    } catch (Throwable $e) {
        $status = 'fail';
        $detail = $e->getMessage();
    }
    $strayOutput = trim((string)ob_get_clean());
    restore_error_handler();

    $elapsedMs = round((microtime(true) - $start) * 1000, 2);

    if ($strayOutput !== '') {
        $status = 'fail';
    }
    if (!empty($collectedErrors)) {
        $status = 'fail';
    }
    if ($elapsedMs / 1000 > SLOW_CHECK_THRESHOLD_SEC && $status === 'pass') {
        $status = 'warn';
    }

    return [
        'check'                 => $name,
        'status'                => $status,
        'elapsed_ms'            => $elapsedMs,
        'slow'                  => $elapsedMs / 1000 > SLOW_CHECK_THRESHOLD_SEC,
        'detail'                => $detail,
        'stray_output'          => $strayOutput !== '' ? mb_substr($strayOutput, 0, 200) : null,
        'php_notices_detected'  => $collectedErrors,
    ];
}

$results = [];

// 1. PHP error suppression / display_errors sanity — production must never echo raw errors.
$results[] = runAuditCheck('php_error_display_config', function () {
    return [
        'display_errors' => (string)ini_get('display_errors'),
        'log_errors'      => (string)ini_get('log_errors'),
        'error_reporting' => error_reporting(),
        'production_safe' => in_array((string)ini_get('display_errors'), ['', '0', 'Off', 'off'], true),
    ];
});

// 2. Network mount / drive path probing — must be @-suppressed and fast, never hang.
$results[] = runAuditCheck('network_mount_probe', function () {
    $candidates = [
        '/Applications/MAMP/htdocs',
        '/Volumes/Music',
    ];
    $probe = [];
    foreach ($candidates as $path) {
        $probe[$path] = @is_dir($path);
    }
    return $probe;
});

// 3. JSON response integrity — encode/decode round-trip with zero stray output.
$results[] = runAuditCheck('json_response_integrity', function () {
    $payload = ['ok' => true, 'nested' => ['a' => 1, 'list' => [1, 2, 3]]];
    $encoded = json_encode($payload);
    if ($encoded === false) {
        throw new RuntimeException('json_encode failed: ' . json_last_error_msg());
    }
    $decoded = json_decode($encoded, true);
    if ($decoded !== $payload) {
        throw new RuntimeException('JSON round-trip mismatch');
    }
    return ['encoded_bytes' => strlen($encoded)];
});

// 4. Sibling API script syntax sanity (read-only `php -l`; nothing is executed or written).
$results[] = runAuditCheck('api_scripts_syntax_check', function () {
    $targets = ['drive_monitor.php', 'manage_content.php', 'favorites.php'];
    $report = [];
    foreach ($targets as $file) {
        $path = __DIR__ . '/' . $file;
        if (!file_exists($path)) {
            $report[$file] = 'not_found';
            continue;
        }
        $output = @shell_exec('php -l ' . escapeshellarg($path) . ' 2>&1');
        $report[$file] = (strpos((string)$output, 'No syntax errors') !== false) ? 'ok' : trim((string)$output);
    }
    foreach ($report as $file => $result) {
        if ($result !== 'ok' && $result !== 'not_found') {
            throw new RuntimeException("$file: $result");
        }
    }
    return $report;
});

// 5. Database connectivity (best-effort; a failure here is reported, not thrown as fatal).
$results[] = runAuditCheck('database_connectivity', function () {
    $configPath = dirname(__DIR__) . '/config/config.php';
    if (!file_exists($configPath)) {
        return 'config.php not found — skipped';
    }
    require_once $configPath;
    if (!function_exists('getDBConnection')) {
        return 'getDBConnection() not defined — skipped';
    }
    $pdo = getDBConnection();
    return ($pdo instanceof PDO) ? 'connected' : 'connection returned unexpected type';
});

$overall = 'pass';
foreach ($results as $r) {
    if ($r['status'] === 'fail') { $overall = 'fail'; break; }
    if ($r['status'] === 'warn') $overall = 'warn';
}

$totalElapsedMs = round(array_sum(array_column($results, 'elapsed_ms')), 2);

echo json_encode([
    'status'              => 'success',
    'overall'             => $overall,
    'timestamp'           => time(),
    'time_str'            => date('H:i:s'),
    'slow_threshold_ms'   => SLOW_CHECK_THRESHOLD_SEC * 1000,
    'total_elapsed_ms'    => $totalElapsedMs,
    'checks'              => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
