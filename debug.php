<?php
// debug.php

// 1. Test Database Connection
$db_status = "Waiting...";
$db_class  = "pending";

try {
    require_once __DIR__ . '/config/db_connect.php';
    if (isset($pdo)) {
        $usedPort = isset($port) ? $port : 'default';
        $db_status = "Connected successfully to access_db (port $usedPort).";
        $db_class  = "success";
    }
} catch (\Exception $e) {
    $db_status = "Connection Failed: " . $e->getMessage();
    $db_class  = "error";
}

// 2. Test Shell Execution for Git in htdocs
$git_status = "Waiting...";
$git_class = "pending";
$output = [];
$return_var = 0;

// Test if we can run git status in the current directory
exec("cd " . escapeshellarg(__DIR__) . " && /usr/bin/git status 2>&1", $output, $return_var);
if ($return_var === 0) {
    $git_status = "Shell exec successful. " . implode(" ", array_slice($output, 0, 1));
    $git_class = "success";
} else {
    // If git status fails, it might be due to git not being in the MAMP PATH, or permission issues.
    // Let's at least check if we can run `pwd` and `ls`
    $pwd_output = [];
    exec("pwd 2>&1", $pwd_output, $pwd_return);
    if ($pwd_return === 0) {
        $git_status = "Shell exec works (pwd: " . $pwd_output[0] . ") but Git returned code $return_var. Ensure Git is in the server's PATH.";
        $git_class = "warning";
    } else {
        $git_status = "Shell exec failed completely. Check PHP disable_functions in php.ini.";
        $git_class = "error";
    }
}

// Helper classes based on state
$css_classes = [
    "success" => "border-left-color: #38b000;",
    "error" => "border-left-color: #d00000;",
    "warning" => "border-left-color: #f77f00;",
    "pending" => "border-left-color: #45a29e;"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Diagnostics</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .diagnostic-panel {
            text-align: left;
            margin-top: 20px;
        }
        .status-item {
            background: rgba(11, 12, 16, 0.6);
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
        .checklist {
            list-style: none;
            padding: 0;
            text-align: left;
        }
        .checklist li {
            margin-bottom: 15px;
        }
        .checklist label {
            cursor: pointer;
            display: flex;
            align-items: center;
            font-size: 1.1em;
            color: #c5c6c7;
            transition: color 0.3s ease;
        }
        .checklist label:hover {
            color: #66fcf1;
        }
        .checklist input[type="checkbox"] {
            margin-right: 15px;
            width: 20px;
            height: 20px;
            accent-color: #66fcf1;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <nav class="global-nav">
        <a href="index.html">Deployer</a>
        <a href="host.php">Host Website</a>
        <a href="sites.php">Hosted Sites</a>
        <a href="movies.html">Movie Portal</a>
        <a href="debug.php" class="active">Diagnostics</a>
        <a href="auto_debug.php">Auto Debug</a>
        <a href="help.html">Help</a>
    </nav>

    <div class="background">
        <div class="orb orb1"></div>
        <div class="orb orb2"></div>
        <div class="orb orb3"></div>
    </div>
    
    <div class="form-container" style="width: 90%; max-width: 550px; margin-top: 120px;">
        <h2>System Diagnostics</h2>
        
        <div class="diagnostic-panel">
            <div class="status-item" style="<?php echo $css_classes[$db_class]; ?>">
                <h4>MySQL Database (Port 8889)</h4>
                <p><?php echo htmlspecialchars($db_status); ?></p>
            </div>
            
            <div class="status-item" style="<?php echo $css_classes[$git_class]; ?>">
                <h4>Shell Execution & Git</h4>
                <p><?php echo htmlspecialchars($git_status); ?></p>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid rgba(69, 162, 158, 0.3); margin: 30px 0;">

        <h3 style="color: #66fcf1; font-size: 1.4em; margin-bottom: 20px; text-align: center;">Manual Verification</h3>
        <ul class="checklist">
            <li>
                <label>
                    <input type="checkbox"> ZeroTier network is ACTIVE and connected
                </label>
            </li>
            <li>
                <label>
                    <input type="checkbox"> Plex Media Server is routing correctly
                </label>
            </li>
        </ul>
    </div>
</body>
</html>

