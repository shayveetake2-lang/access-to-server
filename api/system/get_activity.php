<?php
// api/system/get_activity.php — Unified server activity stream feed
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../auth/require_admin.php';
requireAdmin();

require_once __DIR__ . '/../../config/config.php';

$events = [];

try {
    $pdo = getDBConnection();

    // 1. Fetch recent access transmissions
    try {
        $stmt = $pdo->query("SELECT id, name, email, message, created_at FROM user_inputs ORDER BY id DESC LIMIT 15");
        $inputs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($inputs as $row) {
            $ts = !empty($row['created_at']) ? strtotime($row['created_at']) : time();
            $events[] = [
                'type' => 'access',
                'category' => 'Access Request',
                'title' => 'Access Request from ' . htmlspecialchars($row['name'] ?: 'Guest'),
                'description' => htmlspecialchars(substr($row['message'] ?: 'No details provided', 0, 120)),
                'meta' => htmlspecialchars($row['email'] ?: ''),
                'timestamp' => $ts ?: time(),
                'time_str' => !empty($row['created_at']) ? date('M j, H:i', $ts) : 'Just now',
                'badge_color' => 'emerald'
            ];
        }
    } catch (Exception $e) {
        // Table may be empty or not yet seeded
    }

    // 2. Fetch recent admin accounts
    try {
        $stmt = $pdo->query("SELECT id, username, created_at FROM admin_users ORDER BY id DESC LIMIT 5");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($admins as $adm) {
            $ts = !empty($adm['created_at']) ? strtotime($adm['created_at']) : time();
            $events[] = [
                'type' => 'auth',
                'category' => 'Admin Security',
                'title' => 'Admin Registered: ' . htmlspecialchars($adm['username']),
                'description' => 'System administrative account created with root privileges.',
                'meta' => 'ID #' . $adm['id'],
                'timestamp' => $ts ?: time(),
                'time_str' => !empty($adm['created_at']) ? date('M j, H:i', $ts) : 'Recent',
                'badge_color' => 'indigo'
            ];
        }
    } catch (Exception $e) {}

} catch (Exception $e) {}

// 3. Fetch recent Git commits
if (function_exists('shell_exec')) {
    $gitOutput = @shell_exec('cd ' . escapeshellarg(realpath(__DIR__ . '/../../')) . ' && /usr/bin/git log -n 5 --pretty=format:"%h|%s|%cr|%an" 2>/dev/null');
    if ($gitOutput) {
        $lines = explode("\n", trim($gitOutput));
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $parts = explode('|', $line);
            if (count($parts) >= 4) {
                $hash = trim($parts[0]);
                $msg  = trim($parts[1]);
                $relTime = trim($parts[2]);
                $author = trim($parts[3]);

                $events[] = [
                    'type' => 'deploy',
                    'category' => 'Git Deploy',
                    'title' => 'Commit ' . $hash . ': ' . htmlspecialchars($msg),
                    'description' => 'Committed to repository main branch by ' . htmlspecialchars($author),
                    'meta' => $hash,
                    'timestamp' => time() - 300, // estimated
                    'time_str' => $relTime,
                    'badge_color' => 'cyan'
                ];
            }
        }
    }
}

// 4. Fetch live hosted sites updates
$sitesDir = realpath(__DIR__ . '/../../sites');
if ($sitesDir && is_dir($sitesDir)) {
    $items = scandir($sitesDir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || strpos($item, '.') === 0) continue;
        $full = $sitesDir . '/' . $item;
        if (is_dir($full)) {
            $mtime = filemtime($full);
            $events[] = [
                'type' => 'system',
                'category' => 'Web App',
                'title' => 'Site Hosted: ' . htmlspecialchars($item),
                'description' => 'Directory active in MAMP htdocs /sites/' . htmlspecialchars($item),
                'meta' => 'Active Folder',
                'timestamp' => $mtime,
                'time_str' => date('M j, H:i', $mtime),
                'badge_color' => 'purple'
            ];
        }
    }
}

// Sort all events newest first
usort($events, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

echo json_encode([
    'status' => 'success',
    'count' => count($events),
    'events' => array_slice($events, 0, 30)
]);

