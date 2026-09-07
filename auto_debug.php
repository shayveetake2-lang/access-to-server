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
    $deploySource = file_get_contents($deployFile);
    if (strpos($deploySource, '$repoUrl') === false || strpos($deploySource, 'git') === false) {
        $deployCheck['status'] = 'fail';
        $deployCheck['message'] = "Deployment execution logic is missing or incomplete in deploy.php.";
        $autoFixes[] = "Ensure api/system/deploy.php contains repository URL handling and git deployment logic.";
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
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Auto Debug — Server Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
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
                        brand: { 50: '#ecfeff', 100: '#cffafe', 400: '#22d3ee', 500: '#06b6d4', 600: '#0891b2' },
                        surface: { base: '#090d16', card: '#0f172a', elevated: '#1e293b', terminal: '#050811', border: '#1e293b' }
                    },
                    animation: { 'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite' }
                }
            }
        }
    </script>
    <style>
        .bg-grid-pattern {
            background-size: 32px 32px;
            background-image: linear-gradient(to right, rgba(255,255,255,0.03) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.03) 1px, transparent 1px);
        }
    </style>
</head>
<body class="bg-surface-base text-slate-200 font-sans min-h-screen flex flex-col antialiased selection:bg-cyan-500/30 selection:text-cyan-200 bg-grid-pattern relative">
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-3/4 max-w-4xl h-36 bg-gradient-to-r from-cyan-500/10 via-blue-500/10 to-indigo-500/10 blur-3xl pointer-events-none -z-10"></div>
    <header class="sticky top-0 z-40 w-full border-b border-slate-800/80 bg-slate-950/75 backdrop-blur-xl transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3.5 shrink-0 min-w-0">
                <a href="index.html" class="flex items-center gap-3 shrink-0 group select-none cursor-pointer" title="Go to Server Console Dashboard">
                    <div class="h-9 w-9 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center shadow-lg shadow-cyan-500/20 ring-1 ring-white/15 group-hover:scale-105 transition-transform shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.75 5.1a1.5 1.5 0 011.2-.6h10.1a1.5 1.5 0 011.2.6l2.1 3.45a4.5 4.5 0 01.9 2.7" /></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 whitespace-nowrap">
                            <span class="font-bold text-slate-100 tracking-tight text-base sm:text-lg group-hover:text-white transition-colors">Server Console</span>
                            <span class="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-slate-800/90 text-cyan-400 border border-slate-700/60 uppercase tracking-wider">MBP 2011</span>
                        </div>
                        <p class="text-[11px] text-slate-400 font-mono hidden lg:block truncate whitespace-nowrap">ZeroTier Tunnel &bull; MAMP Apache &bull; Port 3307</p>
                    </div>
                </a>
                <nav class="hidden sm:flex items-center gap-1 ml-1 px-1.5 py-1 rounded-xl bg-slate-900/80 border border-slate-800">
                    <a href="debug.php" class="px-2.5 py-1 rounded-lg text-xs font-medium text-slate-400 hover:text-white hover:bg-slate-800/60 transition-colors">Diagnostics</a>
                    <a href="auto_debug.php" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-800 text-white shadow-sm border border-slate-700/60">Auto Debug</a>
                </nav>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <div class="inline-flex items-center gap-1.5 sm:gap-2 px-2 sm:px-3 py-1.5 rounded-full bg-emerald-950/60 border border-emerald-500/30 text-emerald-400 text-xs font-medium shadow-sm shadow-emerald-900/20">
                    <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span></span>
                    <span class="hidden sm:inline tracking-wide text-emerald-300 font-mono text-[11px] font-semibold">System Online</span>
                </div>
                <a href="sites.php" class="hidden sm:inline-flex group relative items-center gap-2 px-3.5 py-1.5 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 hover:border-cyan-400/50 text-cyan-300 hover:text-cyan-200 text-xs font-semibold transition-all duration-200 shadow-sm shadow-cyan-950/20">
                    <svg class="w-4 h-4 text-cyan-400 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                    <span>Hosted Sites</span>
                </a>
                <a href="movies.html" class="hidden md:inline-flex group relative items-center gap-2 px-3.5 py-1.5 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 hover:border-amber-400/50 text-amber-300 hover:text-amber-200 text-xs font-semibold transition-all duration-200 shadow-sm shadow-amber-950/20">
                    <svg class="w-4 h-4 text-amber-400 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <span>Plex Portal</span>
                </a>
                <div class="relative" id="nav-dropdown-wrapper">
                    <button onclick="toggleNavMenu()" id="nav-menu-btn" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-slate-300 text-xs font-medium transition-colors">
                        <span>Tools</span>
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                    <div id="nav-dropdown" class="hidden absolute right-0 mt-2 w-52 rounded-xl bg-slate-900/95 border border-slate-800 shadow-2xl backdrop-blur-xl p-1.5 z-50 divide-y divide-slate-800/60">
                        <div class="py-1">
                            <a href="sites.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors"><span class="w-2 h-2 rounded-full bg-cyan-400"></span> Hosted Sites</a>
                            <a href="host.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors"><span class="w-2 h-2 rounded-full bg-blue-400"></span> Host Website</a>
                            <a href="access.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors"><span class="w-2 h-2 rounded-full bg-indigo-400"></span> Access Request</a>
                        </div>
                        <div class="py-1">
                            <a href="debug.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors"><span class="w-2 h-2 rounded-full bg-amber-400"></span> Diagnostics</a>
                            <a href="auto_debug.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors"><span class="w-2 h-2 rounded-full bg-purple-400"></span> Auto Debug</a>
                            <a href="help.html" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors"><span class="w-2 h-2 rounded-full bg-emerald-400"></span> Server Docs & Help</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
        <div class="rounded-2xl bg-slate-900/60 border border-slate-800/90 shadow-xl overflow-hidden">
            <div class="h-1 bg-gradient-to-r from-purple-500 via-violet-500 to-indigo-500"></div>
            <div class="p-6 sm:p-8">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-white tracking-tight">System Health Monitor</h2>
                        <p class="text-sm text-slate-400 mt-1">Automated diagnostic checks with auto-fix recommendations</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-slate-800 text-slate-300 border border-slate-700"><?= count($results) ?> checks</span>
                </div>

                <div class="space-y-3">
                    <?php foreach ($results as $result): ?>
                    <div class="rounded-xl p-4 <?php
                        if ($result['status'] === 'pass') echo 'bg-emerald-500/5 border border-emerald-500/20';
                        else echo 'bg-rose-500/5 border border-rose-500/20';
                    ?>">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-semibold text-white"><?= htmlspecialchars($result['name']) ?></h3>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?php
                                if ($result['status'] === 'pass') echo 'bg-emerald-500/10 text-emerald-400';
                                else echo 'bg-rose-500/10 text-rose-400';
                            ?>"><?= strtoupper($result['status']) ?></span>
                        </div>
                        <p class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($result['message']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="border-t border-slate-800 mt-6 pt-6">
                    <?php if (!empty($autoFixes)): ?>
                    <div class="rounded-xl p-5 bg-rose-500/5 border border-rose-500/20">
                        <h3 class="text-sm font-semibold text-rose-400 mb-3 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.048.58.024 1.194-.14 1.743" /></svg>
                            Auto-Fix Recommendations
                        </h3>
                        <ul class="space-y-2">
                            <?php foreach ($autoFixes as $fix): ?>
                            <li class="text-xs font-mono text-slate-400 bg-slate-900/60 rounded-lg px-3 py-2 border border-slate-800"><?= htmlspecialchars($fix) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php else: ?>
                    <div class="rounded-xl p-5 bg-emerald-500/5 border border-emerald-500/20">
                        <h3 class="text-sm font-semibold text-emerald-400 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            All Systems Operational
                        </h3>
                        <p class="text-xs text-slate-400 mt-1">No issues detected. Your server environment is ready.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleNavMenu() {
            document.getElementById('nav-dropdown').classList.toggle('hidden');
        }
        document.addEventListener('click', (e) => {
            const wrapper = document.getElementById('nav-dropdown-wrapper');
            const dropdown = document.getElementById('nav-dropdown');
            if (wrapper && !wrapper.contains(e.target) && dropdown && !dropdown.classList.contains('hidden')) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
