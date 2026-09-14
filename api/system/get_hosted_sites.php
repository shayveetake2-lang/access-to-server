<?php
// api/system/get_hosted_sites.php — Returns hosted websites deployed by the authenticated user
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = '';
if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $token = trim($matches[1]);
}
if (empty($token) && !empty($_REQUEST['token'])) {
    $token = trim($_REQUEST['token']);
}

// Resolve user from token if session is not yet populated
if (!empty($token) && empty($_SESSION['username'])) {
    try {
        require_once __DIR__ . '/../../config/config.php';
        $dbConn = function_exists('getDBConnection') ? getDBConnection() : null;
        if ($dbConn) {
            $stmt = $dbConn->prepare("SELECT id, username, role FROM sys_users WHERE auth_token = :t LIMIT 1");
            $stmt->execute([':t' => $token]);
            if ($u = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $_SESSION['auth_token'] = $token;
                $_SESSION['username'] = $u['username'];
                $_SESSION['role'] = $u['role'];
                $_SESSION['admin_logged_in'] = ($u['role'] === 'admin');
            }
        }
    } catch (\Exception $e) {}
}

$currentUser = $_SESSION['username'] ?? null;
$userRole = $_SESSION['role'] ?? 'guest';

// Only signed-in users are permitted to access their deployed websites from this endpoint
if (empty($currentUser)) {
    http_response_code(401);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Authentication required. Please sign in to view your deployed websites.',
        'sites'   => []
    ]);
    exit;
}

$sitesDir = realpath(__DIR__ . '/../../sites');
if (!$sitesDir && is_dir(__DIR__ . '/../../sites')) {
    $sitesDir = __DIR__ . '/../../sites';
}

$sites = [];

// Load ownership metadata
$metaFile = $sitesDir ? $sitesDir . '/.site_owners.json' : null;
$owners = [];
if ($metaFile && file_exists($metaFile)) {
    $owners = json_decode(@file_get_contents($metaFile), true) ?: [];
}

if ($sitesDir && is_dir($sitesDir)) {
    $items = scandir($sitesDir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || strpos($item, '.') === 0) continue;

        $fullPath = $sitesDir . '/' . $item;
        if (!is_dir($fullPath)) continue;

        // Determine owner of this project
        $siteOwner = null;
        $ownerFile = $fullPath . '/.serverflow_owner';
        if (file_exists($ownerFile)) {
            $siteOwner = trim(@file_get_contents($ownerFile));
        } elseif (isset($owners[$item])) {
            $siteOwner = $owners[$item];
        } else {
            // Default unassigned legacy sites to admin
            $siteOwner = 'admin';
        }

        // STRICT FILTER: Only show this user's deployed websites
        // Do not allow users to see other users' deployed websites from this page
        if (strcasecmp($siteOwner, $currentUser) !== 0) {
            continue;
        }

        $hasIndex = file_exists($fullPath . '/index.html')
                 || file_exists($fullPath . '/index.php')
                 || file_exists($fullPath . '/index.htm');

        $siteUrl = 'sites/' . rawurlencode($item) . '/';

        // Check common build/distribution subdirectories if not in root
        if (!$hasIndex) {
            foreach (['dist', 'build', 'public', 'out'] as $sub) {
                if (file_exists($fullPath . '/' . $sub . '/index.html') || file_exists($fullPath . '/' . $sub . '/index.php')) {
                    $hasIndex = true;
                    $siteUrl .= $sub . '/';
                    break;
                }
            }
        }

        $title = null;
        foreach (['index.html', 'index.htm', 'index.php', 'dist/index.html', 'public/index.html'] as $idx) {
            $idxPath = $fullPath . '/' . $idx;
            if (file_exists($idxPath)) {
                $content = @file_get_contents($idxPath, false, null, 0, 2000);
                if ($content && preg_match('/<title[^>]*>(.+?)<\/title>/si', $content, $m)) {
                    $title = trim(strip_tags($m[1]));
                }
                break;
            }
        }

        $sites[] = [
            'name'      => $item,
            'title'     => $title ?: 'Web Application',
            'has_index' => $hasIndex,
            'owner'     => $siteOwner,
            'modified'  => filemtime($fullPath),
            'url'       => $siteUrl,
            'date_str'  => date('M j, Y H:i', filemtime($fullPath))
        ];
    }
    usort($sites, function($a, $b) { return $b['modified'] - $a['modified']; });
}

echo json_encode([
    'status' => 'success',
    'user'   => $currentUser,
    'count'  => count($sites),
    'sites'  => $sites
]);
