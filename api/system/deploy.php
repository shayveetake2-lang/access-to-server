<?php require_once __DIR__ . "/../auth/require_auth.php"; requireAuth(); ?>
<?php
// deploy.php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable buffering for real-time streams

// Non-blocking output flush
function sendMsg($msg) {
    echo "data: " . $msg . "\n\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

// ==========================================
// 1. IP Restriction Check (ZeroTier 10.247.192.x)
// ==========================================
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if (strpos($clientIp, ',') !== false) {
    $clientIp = trim(explode(',', $clientIp)[0]);
}

$zeroTierRange = '10.247.192.';
$isZeroTier = strncmp($clientIp, $zeroTierRange, strlen($zeroTierRange)) === 0;
$isLocalhost = ($clientIp === '127.0.0.1' || $clientIp === '::1');

if (!$isZeroTier && !$isLocalhost) {
    http_response_code(403);
    sendMsg("HTTP 403 Forbidden: Access denied. Requests must originate from the ZeroTier private network (10.247.192.x).");
    sendMsg("Deployment Aborted.");
    exit;
}

// ==========================================
// 2. Admin Authentication (Session or PIN)
// ==========================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$requestPin = $_SERVER['HTTP_X_DEPLOY_PIN']
    ?? $headers['X-Deploy-PIN']
    ?? $headers['x-deploy-pin']
    ?? ($_POST['pin'] ?? '');

$expectedPin = getenv('DEPLOY_PIN') ?: 'Secur3D3pl0yP1n!2026';
$isSessionAdmin = !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$isValidPin = !empty($requestPin) && hash_equals($expectedPin, $requestPin);



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
