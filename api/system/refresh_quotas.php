<?php
// api/system/refresh_quotas.php
require_once __DIR__ . '/../auth/require_admin.php';
requireAdmin(); // This enforces the RBAC check for admin only and returns 403 if not admin.

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db_connect.php';

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

try {
    $sitesBase = realpath(__DIR__ . '/../../sites');
    if (!$sitesBase) {
        $sitesBase = realpath(__DIR__ . '/../../') . '/sites';
    }

    $stmt = $pdo->query("SELECT id, username FROM sys_users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updated = 0;
    foreach ($users as $u) {
        $username = $u['username'];
        $userDir = rtrim($sitesBase, '/') . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
        $usedMB = getDirectorySizeMB($userDir);
        
        $upd = $pdo->prepare("UPDATE sys_users SET storage_used_mb = :used WHERE id = :id");
        $upd->execute([':used' => $usedMB, ':id' => $u['id']]);
        $updated++;
    }

    echo json_encode([
        'status' => 'success',
        'message' => "Successfully refreshed quotas for {$updated} users."
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
