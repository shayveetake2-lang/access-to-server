<?php
// auto_debug.php

$results = [];
$auto_fixes = [];

// 1. Git & Deployer Check
$git_output = @shell_exec('cd /Applications/MAMP/htdocs/access-to-server && git status 2>&1');
if ($git_output !== null && strpos($git_output, 'branch') !== false) {
    $results['git'] = ['status' => 'success', 'message' => 'Git execution successful. Output: ' . htmlspecialchars(substr($git_output, 0, 50)) . '...'];
} else {
    $results['git'] = ['status' => 'error', 'message' => 'Git execution failed or not a repo. Output: ' . htmlspecialchars($git_output)];
    $auto_fixes[] = "<strong>Git Auto-Fix:</strong> If git is not found, you must add Git to the MAMP Apache PATH. If permission denied, run: <code>sudo chown -R _www:_www /Applications/MAMP/htdocs/</code> (or whatever user MAMP runs as).";
}

// 2. Database Check
try {
    $pdo = new PDO("mysql:host=localhost;port=8889;dbname=access_db;charset=utf8mb4", 'root', 'root', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $results['db'] = ['status' => 'success', 'message' => 'Connected successfully to access_db on localhost:8889.'];
} catch (PDOException $e) {
    $results['db'] = ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
    $auto_fixes[] = "<strong>Database Auto-Fix:</strong> Open MAMP and ensure the MySQL Server is started. Verify the port is exactly 8889. If the database 'access_db' doesn't exist, create it via phpMyAdmin.";
}

// 3. Plex Server Check
$plex_socket = @fsockopen('127.0.0.1', 32400, $errno, $errstr, 2);
if ($plex_socket) {
    fclose($plex_socket);
    $results['plex'] = ['status' => 'success', 'message' => 'Plex Media Server socket is listening on port 32400.'];
} else {
    $results['plex'] = ['status' => 'error', 'message' => 'Could not connect to Plex on 127.0.0.1:32400. Error: ' . $errstr];
    $auto_fixes[] = "<strong>Plex Auto-Fix:</strong> Ensure Plex Media Server is launched on this machine. If it is running on a different machine, update the IP from 127.0.0.1 to the correct host.";
}

// 4. SSE Event Stream Check
$sse_url = 'http://127.0.0.1:8888/access-to-server/deploy.php';
$headers = @get_headers($sse_url);
$sse_success = false;
if ($headers) {
    foreach ($headers as $header) {
        if (stripos($header, 'Content-Type: text/event-stream') !== false) {
            $sse_success = true;
            break;
        }
    }
}

if ($sse_success) {
    $results['sse'] = ['status' => 'success', 'message' => 'deploy.php successfully returns text/event-stream headers.'];
} else {
    if (!$headers || strpos($headers[0], '404') !== false) {
        $results['sse'] = ['status' => 'error', 'message' => 'deploy.php was not found (404).'];
        $auto_fixes[] = "<strong>SSE Auto-Fix:</strong> Create the `deploy.php` file in your project root with the correct SSE headers.";
    } else {
        $results['sse'] = ['status' => 'error', 'message' => 'deploy.php exists but does not output text/event-stream headers.'];
        $auto_fixes[] = "<strong>SSE Auto-Fix:</strong> Add <code>header('Content-Type: text/event-stream');</code> and <code>header('Cache-Control: no-cache');</code> to deploy.php. Ensure output buffering is disabled with <code>ob_end_flush();</code>.";
    }
}

function getCssClass($status) {
    return $status === 'success' ? 'border-left-color: #38b000;' : 'border-left-color: #d00000;';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Diagnostics</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .diagnostic-panel {
            text-align: left;
            margin-top: 20px;
        }
        .status-item {
            background: rgba(31, 40, 51, 0.8);
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            border-left: 4px solid #45a29e;
        }
        .status-item h4 {
            margin: 0 0 10px 0;
            color: #66fcf1;
            font-size: 1.2em;
        }
        .status-item p {
            margin: 0;
            font-size: 1em;
            color: #c5c6c7;
            word-wrap: break-word;
        }
        .auto-fix-panel {
            background: rgba(208, 0, 0, 0.2);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #d00000;
            margin-top: 30px;
            text-align: left;
        }
        .auto-fix-panel h3 {
            color: #ff4d4d;
            margin-top: 0;
        }
        .auto-fix-panel ul {
            color: #fff;
            padding-left: 20px;
        }
        .auto-fix-panel li {
            margin-bottom: 10px;
        }
        .auto-fix-panel code {
            background: rgba(0,0,0,0.5);
            padding: 2px 6px;
            border-radius: 4px;
            color: #66fcf1;
        }
    </style>
</head>
<body>
    <nav class="global-nav">
        <a href="index.html">Deployer</a>
        <a href="movies.html">Movie Portal</a>
        <a href="debug.php">Diagnostics</a>
        <a href="auto_debug.php" class="active">Auto Debug</a>
    </nav>

    <div class="background">
        <div class="orb orb1"></div>
        <div class="orb orb2"></div>
        <div class="orb orb3"></div>
    </div>
    
    <div class="form-container" style="width: 650px; margin-top: 80px;">
        <h2>Automated Diagnostics</h2>
        
        <div class="diagnostic-panel">
            <div class="status-item" style="<?php echo getCssClass($results['git']['status']); ?>">
                <h4>1. Git & Deployer Check</h4>
                <p><?php echo $results['git']['message']; ?></p>
            </div>
            
            <div class="status-item" style="<?php echo getCssClass($results['db']['status']); ?>">
                <h4>2. Database Check</h4>
                <p><?php echo $results['db']['message']; ?></p>
            </div>
            
            <div class="status-item" style="<?php echo getCssClass($results['plex']['status']); ?>">
                <h4>3. Plex Server Check</h4>
                <p><?php echo $results['plex']['message']; ?></p>
            </div>

            <div class="status-item" style="<?php echo getCssClass($results['sse']['status']); ?>">
                <h4>4. SSE Event Stream Check</h4>
                <p><?php echo $results['sse']['message']; ?></p>
            </div>
        </div>

        <?php if (!empty($auto_fixes)): ?>
        <div class="auto-fix-panel">
            <h3>Auto-Fix Recommendations</h3>
            <p style="font-size: 0.9em; margin-bottom: 15px;">Copy the error below and paste it back into Copilot/Agent with: <em>"Apply the auto-fix patch for this error directly to my code."</em></p>
            <ul>
                <?php foreach ($auto_fixes as $fix): ?>
                    <li><?php echo $fix; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php else: ?>
        <div class="status-item" style="border-left-color: #38b000; text-align: center; margin-top: 30px;">
            <h4 style="margin: 0; color: #38b000;">All Systems Operational</h4>
            <p>No auto-fixes required.</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
