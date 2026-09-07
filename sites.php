<?php
// sites.php — Lists all websites deployed into the /sites/ subfolder
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

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

        // Try to read a title from index.html or index.php
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
            'relPath'  => '/sites/' . $item . '/',
        ];
    }
    usort($sites, function($a, $b) { return $b['modified'] - $a['modified']; });
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Hosted Sites — Server Console</title>
    
    <!-- Google Fonts: Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'Menlo', 'Monaco', 'Courier New', 'monospace'],
                    },
                    colors: {
                        brand: {
                            50: '#ecfeff',
                            100: '#cffafe',
                            400: '#22d3ee',
                            500: '#06b6d4',
                            600: '#0891b2',
                        },
                        surface: {
                            base: '#090d16',
                            card: '#0f172a',
                            elevated: '#1e293b',
                            terminal: '#050811',
                            border: '#1e293b'
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        /* Modern subtle grid background */
        .bg-grid-pattern {
            background-size: 32px 32px;
            background-image: 
                linear-gradient(to right, rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
        }
    </style>
</head>

<body class="bg-surface-base text-slate-200 font-sans min-h-screen flex flex-col antialiased selection:bg-cyan-500/30 selection:text-cyan-200 bg-grid-pattern relative">

    <!-- Top Glow Accent -->
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-3/4 max-w-4xl h-36 bg-gradient-to-r from-cyan-500/10 via-blue-500/10 to-indigo-500/10 blur-3xl pointer-events-none -z-10"></div>

    <!-- 1. Top Navigation Bar (Identical Modern SaaS Console Header) -->
    <header class="sticky top-0 z-40 w-full border-b border-slate-800/80 bg-slate-950/75 backdrop-blur-xl transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            
            <!-- Left: Brand / Title & Navigation Tabs -->
            <div class="flex items-center gap-3.5">
                <a href="index.html" class="flex items-center gap-3.5 group">
                    <div class="h-9 w-9 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center shadow-lg shadow-cyan-500/20 ring-1 ring-white/15 group-hover:scale-105 transition-transform">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.75 5.1a1.5 1.5 0 011.2-.6h10.1a1.5 1.5 0 011.2.6l2.1 3.45a4.5 4.5 0 01.9 2.7" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-100 tracking-tight text-base sm:text-lg">Server Console</span>
                            <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-800 text-slate-400 border border-slate-700/60 uppercase tracking-wider">
                                MBP 2011 Node
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 font-mono hidden md:block">ZeroTier Tunnel &bull; MAMP Apache &bull; Port 3307</p>
                    </div>
                </a>

                <!-- Navigation Tabs (Deployer / Hosted Sites) -->
                <nav class="hidden md:flex items-center gap-1 ml-4 px-1.5 py-1 rounded-xl bg-slate-900/80 border border-slate-800">
                    <a href="index.html" class="px-3 py-1 rounded-lg text-xs font-medium text-slate-400 hover:text-white hover:bg-slate-800/60 transition-colors">Deployer</a>
                    <a href="sites.php" class="px-3 py-1 rounded-lg text-xs font-semibold bg-slate-800 text-white shadow-sm border border-slate-700/60">Hosted Sites</a>
                </nav>
            </div>

            <!-- Center/Right: Navigation & Status -->
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Glowing Green System Online Badge -->
                <div class="inline-flex items-center gap-1.5 sm:gap-2 px-2 sm:px-3 py-1.5 rounded-full bg-emerald-950/60 border border-emerald-500/30 text-emerald-400 text-xs font-medium shadow-sm shadow-emerald-900/20">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="hidden sm:inline tracking-wide text-emerald-300 font-mono text-[11px] font-semibold">System Online</span>
                </div>

                <!-- Deployer Quick Return Button -->
                <a href="index.html" class="hidden sm:inline-flex group relative items-center gap-2 px-3.5 py-1.5 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 hover:border-cyan-400/50 text-cyan-300 hover:text-cyan-200 text-xs font-semibold transition-all duration-200 shadow-sm shadow-cyan-950/20">
                    <svg class="w-4 h-4 text-cyan-400 group-hover:-translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    <span>Deployer Console</span>
                </a>

                <!-- Plex / Movie Portal Button -->
                <a href="movies.html" class="hidden md:inline-flex group relative items-center gap-2 px-3.5 py-1.5 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 hover:border-amber-400/50 text-amber-300 hover:text-amber-200 text-xs font-semibold transition-all duration-200 shadow-sm shadow-amber-950/20">
                    <svg class="w-4 h-4 text-amber-400 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    <span>Plex Portal</span>
                </a>

                <!-- Extra Quick Links Menu Button (Dropdown Toggle) -->
                <div class="relative" id="nav-dropdown-wrapper">
                    <button onclick="toggleNavMenu()" id="nav-menu-btn" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-slate-300 text-xs font-medium transition-colors cursor-pointer">
                        <span>Tools</span>
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <!-- Dropdown Content -->
                    <div id="nav-dropdown" class="hidden absolute right-0 mt-2 w-52 rounded-xl bg-slate-900/95 border border-slate-800 shadow-2xl backdrop-blur-xl p-1.5 z-50 divide-y divide-slate-800/60">
                        <div class="py-1">
                            <a href="index.html" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-cyan-400"></span> Deployer Console
                            </a>
                            <a href="host.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-blue-400"></span> Host Website
                            </a>
                            <a href="access.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-indigo-400"></span> Access Request
                            </a>
                        </div>
                        <div class="py-1">
                            <a href="debug.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-amber-400"></span> Diagnostics
                            </a>
                            <a href="auto_debug.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-purple-400"></span> Auto Debug
                            </a>
                            <a href="help.html" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Server Docs &amp; Help
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-6">
        
        <!-- Header Banner & Action Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 rounded-2xl bg-slate-900/60 border border-slate-800/90 shadow-xl shadow-black/40 backdrop-blur-md">
            <div>
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-lg bg-cyan-500/10 border border-cyan-500/20 text-cyan-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                        </svg>
                    </div>
                    <h1 class="text-xl font-bold tracking-tight text-slate-100">Hosted Websites &amp; Projects</h1>
                </div>
                <p class="text-xs text-slate-400 mt-1">
                    All applications and repositories currently hosted in <code class="text-cyan-400 font-mono">/sites/</code> on the 2011 MacBook Pro node.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-300 font-mono text-xs tabular-nums">
                    <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                    <span><?= count($sites) ?> <?= count($sites) === 1 ? 'Project' : 'Projects' ?> Live</span>
                </span>
                
                <a href="index.html" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-semibold text-white bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 shadow-md shadow-cyan-500/20 active:scale-[0.99] transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>Deploy New Site</span>
                </a>
            </div>
        </div>

        <!-- Hosted Sites List / Grid -->
        <?php if (empty($sites)): ?>
            <!-- Empty State -->
            <div class="p-12 text-center rounded-2xl bg-slate-900/60 border border-slate-800/90 shadow-xl shadow-black/40 backdrop-blur-md space-y-4">
                <div class="w-16 h-16 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-center mx-auto text-slate-500">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-slate-200">No Deployed Websites Found</h3>
                    <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                        No projects exist in the <code class="text-cyan-400 font-mono">/sites/</code> directory yet. Deploy a GitHub repository using the Deployer Console to host your first web application.
                    </p>
                </div>
                <div class="pt-2">
                    <a href="index.html" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold text-white bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 shadow-lg shadow-cyan-500/20 transition-all">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.58-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                        </svg>
                        <span>Open Deployer Console</span>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Grid of Modern Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <?php foreach ($sites as $site): ?>
                    <div class="rounded-2xl bg-slate-900/60 border border-slate-800/90 shadow-xl shadow-black/40 backdrop-blur-md overflow-hidden flex flex-col justify-between hover:border-slate-700/90 transition-all duration-200 group relative">
                        
                        <!-- Top Hairline Accent -->
                        <div class="h-1 w-full bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500 opacity-60 group-hover:opacity-100 transition-opacity"></div>

                        <div class="p-5 sm:p-6 space-y-4 flex-1 flex flex-col justify-between">
                            
                            <!-- Top: Status & Metadata -->
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-3">
                                    <?php if ($site['hasIndex']): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-mono font-medium bg-emerald-950/60 text-emerald-400 border border-emerald-500/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                            LIVE
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-mono font-medium bg-amber-950/60 text-amber-400 border border-amber-500/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                            NO INDEX
                                        </span>
                                    <?php endif; ?>

                                    <span class="text-[11px] font-mono text-slate-400 tabular-nums">
                                        <?= date('M j, Y &bull; g:ia', $site['modified']) ?>
                                    </span>
                                </div>

                                <!-- Project Title & Folder Name -->
                                <div>
                                    <h2 class="text-base font-bold text-slate-100 tracking-tight group-hover:text-cyan-300 transition-colors break-all">
                                        <?= htmlspecialchars($site['name']) ?>
                                    </h2>
                                    <?php if ($site['title'] && $site['title'] !== $site['name']): ?>
                                        <p class="text-xs text-slate-400 italic mt-0.5 line-clamp-1">
                                            "<?= htmlspecialchars($site['title']) ?>"
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <!-- Path and URL badge -->
                                <div class="mt-3 p-2.5 rounded-xl bg-slate-950/80 border border-slate-800/80 flex items-center justify-between gap-2 font-mono text-xs">
                                    <span class="text-slate-400 truncate text-[11px] select-all">
                                        <?= htmlspecialchars($site['relPath']) ?>
                                    </span>
                                    <button onclick="copyToClipboard('<?= htmlspecialchars($site['url'], ENT_QUOTES) ?>', this)" class="px-2 py-0.5 rounded text-[10px] text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition-colors whitespace-nowrap cursor-pointer" title="Copy full URL">
                                        Copy
                                    </button>
                                </div>
                            </div>

                            <!-- Bottom Action Button -->
                            <div class="pt-2">
                                <?php if ($site['hasIndex']): ?>
                                    <a 
                                        href="<?= htmlspecialchars($site['url']) ?>" 
                                        target="_blank"
                                        class="w-full py-2.5 px-4 rounded-xl font-semibold text-xs text-white bg-gradient-to-r from-cyan-500 via-sky-500 to-blue-600 hover:from-cyan-400 hover:via-sky-400 hover:to-blue-500 active:scale-[0.99] transition-all duration-200 shadow-md shadow-cyan-500/15 flex items-center justify-center gap-1.5 group/btn"
                                    >
                                        <span>Visit Live Site</span>
                                        <svg class="w-3.5 h-3.5 text-white group-hover/btn:translate-x-0.5 group-hover/btn:-translate-y-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                                        </svg>
                                    </a>
                                <?php else: ?>
                                    <button disabled class="w-full py-2.5 px-4 rounded-xl text-xs font-semibold text-slate-500 bg-slate-950/50 border border-slate-800/80 cursor-not-allowed flex items-center justify-center gap-1.5">
                                        <span>No Entry Point (index.html missing)</span>
                                    </button>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Server Telemetry / Node Info Banner -->
        <div class="p-4 rounded-2xl bg-slate-900/40 border border-slate-800/70 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs text-slate-400">
            <div class="flex items-center gap-2">
                <span class="p-1 rounded bg-cyan-500/10 text-cyan-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                    </svg>
                </span>
                <span><strong>Architecture:</strong> Sites are served via MAMP Apache from <code class="text-slate-300 font-mono">/Applications/MAMP/htdocs/access-to-server/sites/</code>.</span>
            </div>
            <div class="flex items-center gap-3 font-mono text-[11px]">
                <span>Node: <strong class="text-slate-200">MacBookPro8,1 (2011)</strong></span>
                <span>Tunnel: <strong class="text-emerald-400">ZeroTier</strong></span>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800/60 bg-slate-950/40 py-6 text-center text-xs text-slate-400 font-mono">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <span>MacBook Pro 2011 Home Server &bull; ZeroTier Mesh Network</span>
            <div class="flex items-center gap-4 text-[11px]">
                <a href="index.html" class="hover:text-cyan-400 transition-colors">Deployer</a>
                <a href="movies.html" class="hover:text-amber-400 transition-colors">Plex</a>
                <a href="debug.php" class="hover:text-cyan-400 transition-colors">Diagnostics</a>
                <a href="help.html" class="hover:text-cyan-400 transition-colors">Help</a>
            </div>
        </div>
    </footer>

    <!-- JavaScript Helpers -->
    <script>
        // Dropdown toggle for tools menu
        function toggleNavMenu() {
            const dropdown = document.getElementById('nav-dropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const wrapper = document.getElementById('nav-dropdown-wrapper');
            const dropdown = document.getElementById('nav-dropdown');
            if (wrapper && !wrapper.contains(e.target) && dropdown && !dropdown.classList.contains('hidden')) {
                dropdown.classList.add('hidden');
            }
        });

        // One-click URL copy
        function copyToClipboard(text, btn) {
            navigator.clipboard.writeText(text).then(() => {
                const orig = btn.innerText;
                btn.innerText = 'Copied!';
                btn.classList.add('text-emerald-400');
                setTimeout(() => {
                    btn.innerText = orig;
                    btn.classList.remove('text-emerald-400');
                }, 1500);
            }).catch(() => {
                prompt("Copy URL:", text);
            });
        }
    </script>
</body>
</html>
