<?php
// sites.php — Lists all websites deployed into the /sites/ subfolder

// Sites are deployed into /sites/ which is inside the document root
// so they're accessible at: http://host:port/sites/{repoName}/
$sitesDir = __DIR__ . '/sites';

// Build the base URL for hosted sites using the current request info
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost:8888';
$baseUrl  = $scheme . '://' . $host . '/sites';

$sites = [];

if (is_dir($sitesDir)) {
    $items = scandir($sitesDir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (strpos($item, '.') === 0) continue;

        $fullPath = $sitesDir . '/' . $item;
        if (!is_dir($fullPath)) continue;

        $hasIndex = file_exists($fullPath . '/index.html')
                 || file_exists($fullPath . '/index.php')
                 || file_exists($fullPath . '/index.htm');

        $modified = filemtime($fullPath);

        // Try to read a title from index.html
        $title = null;
        foreach (['index.html', 'index.htm', 'index.php'] as $idx) {
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
            'name'     => $item,
            'title'    => $title,
            'hasIndex' => $hasIndex,
            'modified' => $modified,
            'url'      => $baseUrl . '/' . rawurlencode($item) . '/',
        ];
    }
    usort($sites, function($a, $b) { return $b['modified'] - $a['modified']; });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hosted Sites – System Portal</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .sites-container {
            position: relative;
            z-index: 10;
            width: 90%;
            max-width: 900px;
            margin: 120px auto 60px auto;
            padding: 40px;
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .sites-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .sites-header h2 { margin: 0; text-align: left; font-size: 1.5rem; }

        .site-count { font-size: 0.9rem; color: #64748b; }

        .divider {
            border: none;
            border-top: 1px solid rgba(255,255,255,0.08);
            margin: 16px 0 28px;
        }

        .sites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 16px;
        }

        .site-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .site-card:hover {
            border-color: rgba(102, 252, 241, 0.35);
            box-shadow: 0 0 24px rgba(102, 252, 241, 0.07);
            transform: translateY(-2px);
        }

        .site-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, #3b82f6, #66fcf1);
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .site-card:hover::before { opacity: 1; }

        .site-badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 9999px;
            letter-spacing: 0.04em;
            margin-bottom: 2px;
        }

        .badge-live {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(52, 211, 153, 0.3);
        }

        .badge-no-index {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        .site-name {
            font-size: 1rem;
            font-weight: 700;
            color: #f1f5f9;
            word-break: break-all;
        }

        .site-title {
            font-size: 0.82rem;
            color: #94a3b8;
            margin-top: -4px;
            font-style: italic;
        }

        .site-meta { font-size: 0.78rem; color: #64748b; }

        .site-url {
            font-size: 0.75rem;
            color: #475569;
            word-break: break-all;
            font-family: monospace;
            background: rgba(0,0,0,0.3);
            padding: 4px 8px;
            border-radius: 6px;
        }

        .site-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: auto;
            padding: 9px 14px;
            background: linear-gradient(135deg, rgba(59,130,246,0.2), rgba(102,252,241,0.1));
            border: 1px solid rgba(59, 130, 246, 0.35);
            border-radius: 8px;
            color: #93c5fd;
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .site-link:hover {
            background: linear-gradient(135deg, rgba(59,130,246,0.4), rgba(102,252,241,0.2));
            color: #fff;
            border-color: rgba(102, 252, 241, 0.5);
        }

        .site-link-disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }

        .empty-state {
            text-align: center;
            padding: 60px 0;
            color: #64748b;
        }

        .empty-state .emoji { font-size: 3rem; display: block; margin-bottom: 16px; }

        .server-note {
            margin-top: 30px;
            padding: 14px 18px;
            background: rgba(59,130,246,0.08);
            border: 1px solid rgba(59,130,246,0.2);
            border-radius: 10px;
            font-size: 0.84rem;
            color: #94a3b8;
            line-height: 1.6;
        }

        .server-note strong { color: #66fcf1; }

        @media (max-width: 600px) {
            .sites-container { padding: 24px; margin-top: 140px; }
            .sites-grid { grid-template-columns: 1fr; }
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
        <a href="sites.php" class="active">Hosted Sites</a>
        <a href="movies.html">Movie Portal</a>
        <a href="debug.php">Diagnostics</a>
        <a href="auto_debug.php">Auto Debug</a>
        <a href="help.html">Help</a>
    </nav>

    <div class="sites-container">
        <div class="sites-header">
            <h2>🌐 Hosted Sites</h2>
            <span class="site-count"><?= count($sites) ?> site<?= count($sites) !== 1 ? 's' : '' ?> deployed</span>
        </div>
        <hr class="divider">

        <?php if (empty($sites)): ?>
            <div class="empty-state">
                <span class="emoji">📭</span>
                <p style="font-size:1.1rem; color:#94a3b8;">No sites deployed yet.</p>
                <p>Use the <a href="index.html" style="color:#66fcf1; text-decoration:none; font-weight:600;">Deployer</a> or <a href="host.php" style="color:#66fcf1; text-decoration:none; font-weight:600;">Host Website</a> tab to deploy your first project.</p>
                <p style="font-size:0.85rem; color:#475569;">Deployed sites appear here automatically once pushed.</p>
            </div>
        <?php else: ?>
            <div class="sites-grid">
                <?php foreach ($sites as $site): ?>
                    <div class="site-card">
                        <div>
                            <span class="site-badge <?= $site['hasIndex'] ? 'badge-live' : 'badge-no-index' ?>">
                                <?= $site['hasIndex'] ? '● LIVE' : '⚠ NO INDEX' ?>
                            </span>
                            <div class="site-name"><?= htmlspecialchars($site['name']) ?></div>
                            <?php if ($site['title'] && $site['title'] !== $site['name']): ?>
                                <div class="site-title">"<?= htmlspecialchars($site['title']) ?>"</div>
                            <?php endif; ?>
                        </div>
                        <div class="site-meta">
                            🕒 Updated <?= date('d M Y, g:ia', $site['modified']) ?>
                        </div>
                        <div class="site-url"><?= htmlspecialchars($site['url']) ?></div>
                        <a href="<?= htmlspecialchars($site['url']) ?>"
                           target="_blank"
                           class="site-link <?= $site['hasIndex'] ? '' : 'site-link-disabled' ?>">
                            <?= $site['hasIndex'] ? 'Visit Live Site ↗' : 'No Entry Point' ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="server-note">
            <strong>ℹ️ Note:</strong> Sites are hosted on the <strong>2011 MacBook Pro</strong> via MAMP (port 8888).
            Links open directly in a new tab. Sites may go offline if the Mac sleeps or the ZeroTier tunnel drops.
            New deployments appear here automatically — no refresh of this list is needed.
        </div>
    </div>
</body>
</html>
