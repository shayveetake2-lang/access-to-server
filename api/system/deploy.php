<?php
// deploy.php — Production Deployment Engine with Token Authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper function to send SSE stream message
function sendMsg($msg) {
    echo "data: " . $msg . "\n\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

// ==========================================
// 1. Token Verification Check (Token Auth)
// ==========================================
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

// Extract token from Bearer header, URL query param (?token=), or POST body
$token = $_GET['token'] ?? $_POST['token'] ?? '';
if (empty($token) && preg_match('/Bearer\s(\S+)/i', $authHeader, $matches)) {
    $token = $matches[1];
}

$sessionToken = $_SESSION['auth_token'] ?? null;

// Validate incoming token against active session token
$validToken = false;
if (!empty($token) && !empty($sessionToken)) {
    $validToken = hash_equals($sessionToken, $token);
} elseif (!empty($sessionToken) && empty($token)) {
    // Cookie-backed session fallback
    $validToken = true;
}

if (!$validToken) {
    http_response_code(401);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'status'  => 'error',
        'code'    => 401,
        'message' => 'HTTP 401 Unauthorized: Invalid, missing, or expired authentication token.'
    ]);
    exit;
}

// ==========================================
// 2. Set SSE Headers for Streaming Output
// ==========================================
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable buffering for real-time streams



$repoUrl = isset($_POST['repo']) ? trim($_POST['repo']) : (isset($_GET['repo']) ? trim($_GET['repo']) : '');

if (empty($repoUrl)) {
    sendMsg("Error: Repository URL is missing.");
    sendMsg("Deployment Failed.");
    exit;
}

// Basic URL validation
if (!filter_var($repoUrl, FILTER_VALIDATE_URL) && !strpos($repoUrl, 'git@')) {
    sendMsg("Error: Invalid Repository URL format.");
    sendMsg("Deployment Failed.");
    exit;
}

// Extract repo name to create the target folder in MAMP htdocs
$pathParts = explode('/', parse_url($repoUrl, PHP_URL_PATH));
$repoName = end($pathParts);
$repoName = preg_replace('/\.git$/', '', $repoName);

if (empty($repoName)) {
    sendMsg("Error: Could not parse repository name.");
    sendMsg("Deployment Failed.");
    exit;
}

// Target Directory — inside the document root so sites are publicly accessible
$sitesBase = realpath(__DIR__ . '/../../sites');
if (!$sitesBase) {
    $sitesBase = realpath(__DIR__ . '/../../') . '/sites';
    if (!is_dir($sitesBase)) {
        @mkdir($sitesBase, 0755, true);
    }
}
$targetDir = rtrim($sitesBase, '/') . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $repoName);
sendMsg("[INFO] Target Directory: " . $targetDir);

// Sanitize inputs strictly using escapeshellarg
$escapedRepoUrl = escapeshellarg($repoUrl);
$escapedTargetDir = escapeshellarg($targetDir);

// Intelligent deployment strategy: git clone vs git pull
if (is_dir($targetDir)) {
    sendMsg("[INFO] Directory exists. Initiating 'git pull'...");
    $cmd = "cd {$escapedTargetDir} && git pull 2>&1";
} else {
    sendMsg("[INFO] Directory does not exist. Initiating 'git clone'...");
    $cmd = "git clone {$escapedRepoUrl} {$escapedTargetDir} 2>&1";
}

sendMsg("[EXEC] " . htmlspecialchars($cmd));

// Execute the command, reading the output stream in real-time
$handle = popen($cmd, 'r');

if (is_resource($handle)) {
    while (!feof($handle)) {
        $buffer = fgets($handle);
        if ($buffer !== false) {
            sendMsg(htmlspecialchars(rtrim($buffer)));
        }
    }
    $returnCode = pclose($handle);
    
    if ($returnCode === 0) {
        sendMsg("");
        sendMsg("====== Deployment Complete ======");
    } else {
        sendMsg("");
        sendMsg("Command exited with code: $returnCode.");
        sendMsg("Deployment Failed.");
    }
} else {
    sendMsg("Error: Unable to execute shell command.");
    sendMsg("Deployment Failed.");
}
?>
