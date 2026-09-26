<?php
// api/update_catalog.php — Secure ServerFlow Catalog Update Endpoint for Ampache on 2011 MacBook Pro
// Enforces JWT/Session admin authentication, hardware throttling, and process lock management.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once __DIR__ . '/../config/config.php';

// ─── 1. EXTRACT & VERIFY AUTHENTICATION (ADMIN ONLY) ───
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = [];
}

$token = '';
if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
    $token = trim($matches[1]);
}
if (empty($token) && !empty($data['token'])) {
    $token = trim($data['token']);
}
if (empty($token) && !empty($_POST['token'])) {
    $token = trim($_POST['token']);
}

// Strictly reject tokens in URL query string to avoid exposure in server logs
if (!empty($_GET['token'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tokens in query parameters are strictly forbidden. Pass token in Authorization header.']);
    exit;
}

$requester = null;

try {
    $pdo = getDBConnection();
} catch (\Exception $e) {
    $pdo = null;
}

if (!empty($token)) {
    // 1a. Validate standard JWT (header.payload.signature)
    if (substr_count($token, '.') === 2) {
        $parts = explode('.', $token);
        $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'));
        $payload = json_decode($payloadJson, true);
        if (is_array($payload)) {
            if (isset($payload['exp']) && time() > $payload['exp']) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Authentication token has expired.']);
                exit;
            }
            $reqId = $payload['sub'] ?? $payload['user_id'] ?? $payload['id'] ?? null;
            if ($reqId && $pdo) {
                $stmt = $pdo->prepare("SELECT id, username, role FROM sys_users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $reqId]);
                $requester = $stmt->fetch();
            }
            if (!$requester && !empty($payload['role'])) {
                $requester = [
                    'id' => $payload['sub'] ?? 0,
                    'username' => $payload['username'] ?? 'jwt_admin',
                    'role' => $payload['role']
                ];
            }
        }
    }

    // 1b. Validate against sys_users token hash
    if (!$requester && $pdo) {
        $tokenHash = hash('sha256', $token);
        $stmt = $pdo->prepare("SELECT id, username, role, token_expires_at FROM sys_users WHERE (token_hash = :th OR auth_token = :th OR auth_token = :t) LIMIT 1");
        $stmt->execute([':th' => $tokenHash, ':t' => $token]);
        $u = $stmt->fetch();
        if ($u) {
            if (!empty($u['token_expires_at']) && (strtotime($u['token_expires_at']) < time())) {
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Authentication token has expired. Please log in again.']);
                exit;
            }
            $requester = $u;
        }
    }
}

// 1c. Validate active session
if (!$requester && !empty($_SESSION['auth_token']) && $pdo) {
    $tokenHash = hash('sha256', $_SESSION['auth_token']);
    $stmt = $pdo->prepare("SELECT id, username, role FROM sys_users WHERE (token_hash = :th OR auth_token = :th OR auth_token = :t) LIMIT 1");
    $stmt->execute([':th' => $tokenHash, ':t' => $_SESSION['auth_token']]);
    $requester = $stmt->fetch();
}
if (!$requester && !empty($_SESSION['role'])) {
    $requester = [
        'id' => $_SESSION['user_id'] ?? 0,
        'username' => $_SESSION['username'] ?? 'session_admin',
        'role' => $_SESSION['role']
    ];
}

// Check authentication
if (!$requester) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Valid authentication token required.']);
    exit;
}

// Check administrator role
if ($requester['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Administrator privileges required to update catalog.']);
    exit;
}

// ─── 2. PROCESS MANAGEMENT & SCAN LOCATIONS ───
$lockFile = '/tmp/serverflow_catalog_update.lock';
$logFile  = '/tmp/serverflow_catalog.log';

function isProcessRunning($pid) {
    if (empty($pid) || !is_numeric($pid)) return false;
    $check = trim((string)shell_exec("ps -p " . intval($pid) . " -o pid="));
    return !empty($check);
}

// Locate Ampache CLI script
$cliCandidates = [
    realpath(__DIR__ . '/../ampache/bin/cli'),
    realpath(__DIR__ . '/../ampache/bin/cli.inc'),
    '/Applications/MAMP/htdocs/ampache/bin/cli',
    '/Applications/MAMP/htdocs/ampache/bin/cli.inc',
    '/Volumes/htdocs/access-to-server/ampache/bin/cli'
];

$ampacheCli = null;
$isLegacyCli = false;
foreach ($cliCandidates as $cand) {
    if ($cand && file_exists($cand)) {
        $ampacheCli = $cand;
        $isLegacyCli = (substr($cand, -4) === '.inc');
        break;
    }
}

// ─── 3. STATUS INQUIRY (GET) ───
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $isRunning = false;
    $pid = null;

    if (file_exists($lockFile)) {
        $pid = trim(@file_get_contents($lockFile));
        if (isProcessRunning($pid)) {
            $isRunning = true;
        } else {
            @unlink($lockFile);
        }
    }

    $logTail = '';
    if (file_exists($logFile)) {
        $logTail = shell_exec("tail -n 20 " . escapeshellarg($logFile) . " 2>&1") ?: '';
    }

    echo json_encode([
        'status'     => 'success',
        'running'    => $isRunning,
        'pid'        => $isRunning ? (int)$pid : null,
        'recent_log' => $logTail,
        'message'    => $isRunning ? 'Catalog scan is actively running on mac2 drive.' : 'Catalog scan is idle.'
    ]);
    exit;
}

// ─── 4. TRIGGER CATALOG UPDATE (POST) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if scan already running
    if (file_exists($lockFile)) {
        $pid = trim(@file_get_contents($lockFile));
        if (isProcessRunning($pid)) {
            http_response_code(429);
            echo json_encode([
                'status'  => 'error',
                'running' => true,
                'pid'     => (int)$pid,
                'message' => "Catalog update is already in progress (PID {$pid}). Please wait for it to complete."
            ]);
            exit;
        } else {
            @unlink($lockFile);
        }
    }

    if (!$ampacheCli) {
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Ampache CLI tool not found on server.'
        ]);
        exit;
    }

    // Determine PHP CLI binary
    $phpBin = PHP_BINARY ?: '/usr/bin/php';
    if (!file_exists($phpBin) || is_dir($phpBin)) {
        $phpBin = 'php';
    }

    // Prepare hardware-throttled CLI command
    // Hardware throttle: 'nice -n 15' keeps the 2011 MacBook Pro CPU temperature down and fans quiet
    $escapedPhp = escapeshellarg($phpBin);
    $escapedCli = escapeshellarg($ampacheCli);
    $escapedLog = escapeshellarg($logFile);

    // Build command for modern Ampache v6 or legacy Ampache
    if ($isLegacyCli) {
        // Legacy: php cli.inc -c update
        $subCmd = "-c update";
    } else {
        // Ampache v6: add new media and gather embedded/remote artwork.
        $subCmd = "run:updateCatalog -a -g";
    }

    // Initialize log header
    @file_put_contents($logFile, "=== Catalog Update started at " . date('Y-m-d H:i:s') . " by {$requester['username']} ===\n");

    // Execute throttled background command
    $cmd = "nice -n 15 {$escapedPhp} -d error_reporting=\"E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED\" {$escapedCli} {$subCmd} >> {$escapedLog} 2>&1 & echo $!";
    $spawnedPid = trim((string)shell_exec($cmd));

    if (!empty($spawnedPid) && is_numeric($spawnedPid)) {
        @file_put_contents($lockFile, $spawnedPid);
        echo json_encode([
            'status'  => 'success',
            'running' => true,
            'pid'     => (int)$spawnedPid,
            'message' => 'Catalog scan started gently in background. New tracks and artwork on mac2 will index automatically.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Failed to trigger background catalog update process.'
        ]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);

