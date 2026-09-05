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

$repoUrl = isset($_GET['repo']) ? $_GET['repo'] : '';

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

// Target Directory for macOS High Sierra MAMP
$targetDir = "/Applications/MAMP/htdocs/" . preg_replace('/[^a-zA-Z0-9_-]/', '', $repoName);
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

