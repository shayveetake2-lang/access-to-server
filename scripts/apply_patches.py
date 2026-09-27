import os

def patch_deploy():
    path = "/Volumes/htdocs/access-to-server/api/system/deploy.php"
    with open(path, "r") as f:
        content = f.read()
    
    target = "    if ($returnCode === 0) {\n        // Tag site ownership"
    replacement = """    if ($returnCode === 0) {
        // SECURITY PATCH: HARDEN WEBROOT (Prevent PHP RCE in deployed sites)
        $htaccessPath = $targetDir . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            @file_put_contents($htaccessPath, "<IfModule mod_php7.c>\\n    php_flag engine off\\n</IfModule>\\nOptions -Indexes\\n");
        }

        // Tag site ownership"""
    content = content.replace(target, replacement)
    
    with open(path, "w") as f:
        f.write(content)

def patch_drive_monitor():
    path = "/Volumes/htdocs/access-to-server/api/drive_monitor.php"
    with open(path, "r") as f:
        content = f.read()
        
    target = """    // If not generated yet, try generating it on-the-fly once
    // Alternatively, just return an error so as not to hang the frontend.
    include_once __DIR__ . '/cron_storage.php';
    if (file_exists($cacheFile)) {
        echo file_get_contents($cacheFile);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Storage cache not available']);
    }"""
    
    replacement = """    // SECURITY/PERFORMANCE PATCH: Async spawn to prevent worker blocking
    $cronScript = escapeshellarg(__DIR__ . '/cron_storage.php');
    exec("php {$cronScript} > /dev/null 2>&1 &");
    
    http_response_code(202);
    echo json_encode([
        'status' => 'processing', 
        'message' => 'Storage cache is generating in the background. Please retry shortly.',
        'drives' => [] 
    ]);"""
    
    content = content.replace(target, replacement)
    
    with open(path, "w") as f:
        f.write(content)

def patch_manage_content():
    path = "/Volumes/htdocs/access-to-server/api/manage_content.php"
    with open(path, "r") as f:
        content = f.read()
    content = content.replace("        ['root', 'root'],\n", "")
    with open(path, "w") as f:
        f.write(content)

def patch_api_proxy():
    path = "/Volumes/htdocs/access-to-server/modern-music-app/api_proxy.php"
    with open(path, "r") as f:
        content = f.read()
    content = content.replace("        ['root', 'root'],\n", "")
    content = content.replace("        ['root', '']\n", "")
    content = content.replace(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300"><rect',
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300" width="100%" height="100%" preserveAspectRatio="xMidYMid slice"><rect'
    )
    with open(path, "w") as f:
        f.write(content)

patch_deploy()
patch_drive_monitor()
patch_manage_content()
patch_api_proxy()
print("PHP Backend Patches applied successfully.")
