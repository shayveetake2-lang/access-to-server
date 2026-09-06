<?php
// auto_debug.php

$results = [];
$autoFixes = [];

// 1. Git & Deployer Check
$gitCheck = [
    'name' => 'Git & Deployer Check',
    'status' => 'pass',
    'message' => 'shell_exec and popen are available, and htdocs is accessible.'
];

if (!function_exists('shell_exec') || !function_exists('popen')) {
    $gitCheck['status'] = 'fail';
    $gitCheck['message'] = 'shell_exec or popen is disabled in PHP configuration.';
    $autoFixes[] = "Remove 'shell_exec' and 'popen' from 'disable_functions' in your php.ini.";
} else {
    $testCmd = @shell_exec('cd ' . escapeshellarg(__DIR__) . ' && /usr/bin/git status 2>&1');
    if ($testCmd === null || (strpos($testCmd, 'fatal') !== false && strpos($testCmd, 'not a git repository') === false)) {
        $gitCheck['status'] = 'fail';
        $gitCheck['message'] = 'Cannot execute git commands in ' . __DIR__ . '. Output: ' . htmlspecialchars($testCmd);
        $autoFixes[] = "Check folder permissions or ensure git is installed and accessible to the web server.";
    }
}
$results[] = $gitCheck;


// 2. Database Connector Check
$dbCheck = [
    'name' => 'Database Architecture Check',
    'status' => 'pass',
    'message' => 'Successfully connected to MySQL via centralized config/db_connect.php'
];

if (!file_exists(__DIR__ . '/config/db_connect.php')) {
    $dbCheck['status'] = 'fail';
    $dbCheck['message'] = 'config/db_connect.php is missing.';
    $autoFixes[] = "Create config/db_connect.php to match the new backend architecture.";
} else {
    try {
        require_once __DIR__ . '/config/db_connect.php';
        if (!isset($pdo)) {
            throw new Exception("PDO object not instantiated by db_connect.php");
        }
        $pdo->query("SELECT 1");
        $usedPort = isset($port) ? $port : 'default';
        $dbCheck['status'] = 'pass';
        $dbCheck['message'] = "Successfully connected to MySQL via centralized config/db_connect.php (port $usedPort).";
    } catch (Exception $e) {
        $dbCheck['status'] = 'fail';
        $dbCheck['message'] = 'DB Connection failed via db_connect.php: ' . $e->getMessage();
        $autoFixes[] = "Check config/db_connect.php credentials or run db_setup.php if tables are missing.";
    }
}
$results[] = $dbCheck;

// 3. SSE Deployment Engine Check
$deployCheck = [
    'name' => 'SSE Deployer Engine Check',
    'status' => 'pass',
    'message' => 'api/system/deploy.php exists and is ready.'
];

$deployFile = __DIR__ . '/api/system/deploy.php';
if (!file_exists($deployFile)) {
    $deployCheck['status'] = 'fail';
    $deployCheck['message'] = "api/system/deploy.php was not found.";
    $autoFixes[] = "Ensure the api/system/deploy.php file exists for the SSE terminal feature.";
} else {
    // Check if PIN security is implemented
    $deploySource = file_get_contents($deployFile);
    if (strpos($deploySource, '$deployPin') === false) {
        $deployCheck['status'] = 'fail';
        $deployCheck['message'] = "PIN security logic is missing in deploy.php.";
        $autoFixes[] = "Add secure PIN validation to api/system/deploy.php.";
    }
}
$results[] = $deployCheck;

// 4. Plex Server Check
$plexCheck = [
    'name' => 'Plex Server Check',
    'status' => 'pass',
    'message' => 'Plex Media Server is reachable on localhost:32400.'
];

$plexSock = @fsockopen('localhost', 32400, $errno, $errstr, 2);
if (!$plexSock) {
    $plexCheck['status'] = 'fail';
    $plexCheck['message'] = "Cannot connect to Plex on port 32400: $errstr ($errno)";
    $autoFixes[] = "Start Plex Media Server, or verify it is listening on localhost:32400. Check firewall rules blocking port 32400.";
} else {
    fclose($plexSock);
}
$results[] = $plexCheck;

// 5. Deployment API Check
$deployApiCheck = [
    'name' => 'Deployment API Check',
    'status' => 'pass',
    'message' => 'api/process_deployment.php exists and is ready.'
];

$deployApiFile = __DIR__ . '/api/process_deployment.php';
if (!file_exists($deployApiFile)) {
    $deployApiCheck['status'] = 'fail';
    $deployApiCheck['message'] = "api/process_deployment.php was not found.";
    $autoFixes[] = "Ensure the api/process_deployment.php file exists for the Host Website feature.";
}
$results[] = $deployApiCheck;

// 6. Sites Directory Check
$sitesCheck = [
    'name'    => 'Hosted Sites Directory',
    'status'  => 'pass',
    'message' => 'sites/ directory exists and is writable. Deployed projects will appear in Hosted Sites.'
];

$sitesDir = __DIR__ . '/sites';
if (!is_dir($sitesDir)) {
    if (@mkdir($sitesDir, 0755, true)) {
        $sitesCheck['status'] = 'pass';
        $sitesCheck['message'] = 'sites/ directory was missing — created automatically. Ready for deployments.';
    } else {
        $sitesCheck['status'] = 'fail';
        $sitesCheck['message'] = 'sites/ directory does not exist and could not be created. Check folder permissions.';
        $autoFixes[] = "Manually create /sites/ inside the access-to-server folder and set permissions to 755.";
    }
} elseif (!is_writable($sitesDir)) {
    $sitesCheck['status'] = 'fail';
    $sitesCheck['message'] = 'sites/ directory exists but is not writable. Deployments will fail.';
    $autoFixes[] = "Run: chmod 755 " . $sitesDir;
}
$results[] = $sitesCheck;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Debugger - System Portal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .debug-container {
            position: relative;
            z-index: 10;
            width: 80%;
            max-width: 800px;
            margin: 5% auto;
            padding: 30px;
            background: rgba(31, 40, 51, 0.9);
            border: 1px solid #45a29e;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(102, 252, 241, 0.2);
            backdrop-filter: blur(10px);
        }
        .check-item {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
            background: rgba(11, 12, 16, 0.8);
            border-left: 5px solid;
        }
        .check-pass {
            border-left-color: #45a29e;
        }
        .check-fail {
            border-left-color: #ff4c4c;
        }
        .check-skip {
            border-left-color: #f59e0b;
        }
        .check-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .check-name {
            font-size: 1.2em;
            font-weight: 600;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            text-transform: uppercase;
            font-weight: bold;
        }
        .status-pass {
            background: rgba(69, 162, 158, 0.2);
            color: #66fcf1;
        }
        .status-fail {
            background: rgba(255, 76, 76, 0.2);
            color: #ff4c4c;
        }
        .status-skip {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }
        .check-message {
            color: #ccc;
            font-size: 0.95em;
        }
        .autofix-panel {
            margin-top: 30px;
            padding: 20px;
            background: rgba(255, 76, 76, 0.1);
            border: 1px solid #ff4c4c;
            border-radius: 10px;
        }
        .autofix-panel h3 {
            color: #ff4c4c;
            margin-top: 0;
        }
        .autofix-panel ul {
            margin: 0;
            padding-left: 20px;
        }
        .autofix-panel li {
            margin-bottom: 10px;
            color: #e0e0e0;
            font-family: monospace;
            background: rgba(0,0,0,0.3);
            padding: 8px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="background">
        <div class="orb orb1"></div>
        <div class="orb orb2"></div>
        <div class="orb orb3"></div>
    </div>
    
    <nav class="global-nav">
        <a href="index.html">Deployer</a>
        <a href="host.php">Host Website</a>
        <a href="sites.php">Hosted Sites</a>
        <a href="movies.html">Movie Portal</a>
        <a href="debug.php">Diagnostics</a>
        <a href="auto_debug.php" class="active">Auto Debug</a>
        <a href="help.html">Help</a>
    </nav>

    <div class="debug-container">
        <h2>System Diagnostics</h2>
        
        <?php foreach ($results as $result): ?>
            <div class="check-item check-<?= $result['status'] ?>">
                <div class="check-header">
                    <span class="check-name"><?= htmlspecialchars($result['name']) ?></span>
                    <span class="status-badge status-<?= $result['status'] ?>">
                        <?= htmlspecialchars($result['status']) ?>
                    </span>
                </div>
                <div class="check-message">
                    <?= htmlspecialchars($result['message']) ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!empty($autoFixes)): ?>
            <div class="autofix-panel">
                <h3>🛠️ Auto-Fix Recommendations</h3>
                <ul>
                    <?php foreach ($autoFixes as $fix): ?>
                        <li><?= htmlspecialchars($fix) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="autofix-panel" style="background: rgba(69, 162, 158, 0.1); border-color: #45a29e;">
                <h3 style="color: #66fcf1;">✅ All Systems Operational</h3>
                <p style="color: #ccc; margin: 0;">No issues detected. Your server environment is ready.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
