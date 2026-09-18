<?php
// api/system/restart_mamp.php — Hard restart Apache service (Restricted to Admin via POST)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../auth/require_admin.php';
requireAdmin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit;
}

$output = [];
if (file_exists('/Applications/MAMP/bin/stopApache.sh')) {
    exec('/Applications/MAMP/bin/stopApache.sh 2>&1', $output);
    sleep(2);
    exec('/Applications/MAMP/bin/startApache.sh 2>&1', $output);
} elseif (file_exists(__DIR__ . '/../../restart_web.sh')) {
    exec('bash ' . __DIR__ . '/../../restart_web.sh 2>&1', $output);
} else {
    exec('/usr/sbin/apachectl graceful 2>&1', $output);
}

echo json_encode([
    'status' => 'success',
    'reloaded' => true,
    'message' => 'Apache web service restarted.',
    'output' => $output
]);
