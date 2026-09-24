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
// 1. Dual-Mode Token Verification (JWT via Header or Query for SSE)
// ==========================================
require_once __DIR__ . '/../auth/jwt_utils.php';

$jwt = '';
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
    $jwt = trim($matches[1]);
} elseif (!empty($_GET['token'])) {
    $jwt = trim($_GET['token']);
} elseif (!empty($_POST['token'])) {
    $jwt = trim($_POST['token']);
}

if (empty($jwt)) {
    http_response_code(401);
    echo "data: Error 401: Unauthorized. Authentication token missing.\n\n";
    exit;
}

$decoded_payload = verifyAndDecodeJwt($jwt);
if ($decoded_payload === null) {
    http_response_code(403);
    echo "data: Error 403: Forbidden. Invalid or expired token signature.\n\n";
    exit;
}

// Strict Role-Based Access Control: Deployment requires admin privileges
$userRole = $decoded_payload['role'] ?? 'user';
if ($userRole !== 'admin') {
    http_response_code(403);
    echo "data: Error 403: Forbidden. Administrator privileges required to execute deployments.\n\n";
    exit;
}

// Set session for downstream quota & ownership handling
$_SESSION['username'] = $decoded_payload['username'] ?? 'admin';
$_SESSION['role'] = 'admin';

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

// Strict URL validation & Argument Injection Defense
if (empty($repoUrl) || str_starts_with($repoUrl, '-') || !preg_match('/^https:\/\/[a-zA-Z0-9_\-\.]+\/[a-zA-Z0-9_\-\.\/]+(\.git)?$/', $repoUrl)) {
    sendMsg("Error: Invalid or disallowed Repository URL. Only standard HTTPS Git URLs are accepted.");
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

// ==========================================
// 3. Storage Quota Middleware Check
// ==========================================
require_once __DIR__ . '/../../config/db_connect.php';

if (!function_exists('getDirectorySizeMB')) {
    function getDirectorySizeMB($dir) {
        if (!is_dir($dir)) return 0.0;
        $size = 0;
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file){
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return round($size / 1048576, 2);
    }
}


$username = $_SESSION['username'] ?? 'admin';
$limitMB = 100.0;
try {
    $stmt = $pdo->prepare("SELECT storage_limit_mb FROM sys_users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $username]);
    if ($row = $stmt->fetch()) {
        $limitMB = (float)($row['storage_limit_mb'] ?? 100);
    }
} catch (\Exception $e) {}

$currentUsedMB = function_exists('getDirectorySizeMB') ? getDirectorySizeMB($targetDir) : 0.0;
// Also measure total user sites directory size
$userBaseDir = rtrim($sitesBase, '/') . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
if (is_dir($userBaseDir)) {
    $currentUsedMB = max($currentUsedMB, getDirectorySizeMB($userBaseDir));
}

if ($currentUsedMB >= $limitMB) {
    http_response_code(403);
    sendMsg("Error 403: Storage limit exceeded. Used: {$currentUsedMB}MB / Limit: {$limitMB}MB.");
    sendMsg("Deployment Failed.");
    exit;
}

// Sanitize inputs strictly using escapeshellarg
$escapedRepoUrl = escapeshellarg($repoUrl);
$escapedTargetDir = escapeshellarg($targetDir);

// Intelligent deployment strategy: git clone vs git pull
if (is_dir($targetDir)) {
    sendMsg("[INFO] Directory exists. Initiating 'git pull'...");
    $cmd = "cd {$escapedTargetDir} && git pull 2>&1";
} else {
    sendMsg("[INFO] Directory does not exist. Initiating 'git clone'...");
    $cmd = "git clone -- {$escapedRepoUrl} {$escapedTargetDir} 2>&1";
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
        // Tag site ownership to current authenticated user
        @file_put_contents($targetDir . '/.serverflow_owner', $username);
        $metaFile = $sitesBase . '/.site_owners.json';
        $owners = [];
        if (file_exists($metaFile)) {
            $owners = json_decode(@file_get_contents($metaFile), true) ?: [];
        }
        $owners[$repoName] = $username;
        @file_put_contents($metaFile, json_encode($owners, JSON_PRETTY_PRINT));
        
        $totalUserMB = 0;
        foreach ($owners as $repo => $owner) {
            if ($owner === $username) {
                $dirPath = $sitesBase . '/' . $repo;
                if (is_dir($dirPath)) {
                    $totalUserMB += getDirectorySizeMB($dirPath);
                }
            }
        }
        
        if ($totalUserMB > $limitMB) {
            sendMsg("[ERROR] Post-deploy quota check failed. Total used: {$totalUserMB}MB / Limit: {$limitMB}MB");
            sendMsg("[INFO] Rolling back deployment (deleting cloned files)...");
            shell_exec("rm -rf " . escapeshellarg($targetDir));
            
            unset($owners[$repoName]);
            @file_put_contents($metaFile, json_encode($owners, JSON_PRETTY_PRINT));
            
            http_response_code(403);
            sendMsg("Deployment Failed: Storage Quota Exceeded.");
            exit;
        } else {
            $upd = $pdo->prepare("UPDATE sys_users SET storage_used_mb = :used WHERE username = :u");
            $upd->execute([':used' => $totalUserMB, ':u' => $username]);
        }

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
