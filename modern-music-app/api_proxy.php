<?php
// modern-music-app/api_proxy.php — Public Registration Proxy for Ampache Subsonic Backend
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Helper to load configuration from .env
function getProxyEnv($key, $default = null) {
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                if (trim($name) === $key) return trim($value, " \"'");
            }
        }
    }
    return $default;
}

$input = json_decode(file_get_contents('php://input'), true);

if (($input['action'] ?? '') === 'register') {
    // ── Rate Limiting (Thermal Guard & Anti-Abuse) ───────────────────────────
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $rateKey = 'reg_attempts_' . md5($ip);
    $attempts = $_SESSION[$rateKey] ?? ['count' => 0, 'first_attempt' => time()];
    
    if (time() - $attempts['first_attempt'] > 600) {
        $attempts = ['count' => 0, 'first_attempt' => time()];
    }
    
    if ($attempts['count'] >= 5) {
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => 'Too many registration attempts. Please wait a few minutes.']);
        exit;
    }
    
    $attempts['count']++;
    $_SESSION[$rateKey] = $attempts;

    // ── Input Validation ─────────────────────────────────────────────────────
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Username and password are required.']);
        exit;
    }

    if (!preg_match('/^[a-zA-Z0-9_\-\.]{3,32}$/', $username)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Username must be 3-32 characters (letters, numbers, underscores, dashes, dots).']);
        exit;
    }

    if (strlen($password) < 4) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 4 characters.']);
        exit;
    }

    $newUser = urlencode($username);
    $newPass = urlencode($password);
    $email = urlencode($username . "@local.host");
    
    // Subsonic admin account credentials configured via environment
    $admin_u = getProxyEnv('AMPACHE_ADMIN_USER', 'admin');
    $admin_p = getProxyEnv('AMPACHE_ADMIN_API_KEY', getProxyEnv('AMPACHE_ADMIN_PASS_HASH', '18e499b984c75ad09e233f6d8fe0228d'));
    
    if (empty($admin_p)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Ampache administrative API key not configured in environment.']);
        exit;
    }
    
    // Check internal path (support both root and /access-to-server/ subfolder)
    $hasSubfolder = file_exists(__DIR__ . '/../ampache/public/rest/index.php');
    $subPath = $hasSubfolder ? '/access-to-server' : '';
    $url = "http://127.0.0.1:8888{$subPath}/ampache/public/rest/index.php?action=createUser&username={$newUser}&password={$newPass}&email={$email}&u={$admin_u}&p={$admin_p}&v=1.16.1&c=test&f=json";
    
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        // Try alternate direct root path fallback
        $altUrl = "http://127.0.0.1:8888/ampache/public/rest/index.php?action=createUser&username={$newUser}&password={$newPass}&email={$email}&u={$admin_u}&p={$admin_p}&v=1.16.1&c=test&f=json";
        $response = @file_get_contents($altUrl, false, $ctx);
    }
    
    if ($response === false) {
        http_response_code(502);
        echo json_encode(['status' => 'error', 'message' => 'Ampache backend service unreachable on port 8888.']);
        exit;
    }
    
    echo $response;
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
