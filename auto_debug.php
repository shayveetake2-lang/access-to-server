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
    <script>
    if (localStorage.getItem('theme') === 'light') {
        document.documentElement.classList.remove('dark');
    } else {
        document.documentElement.classList.add('dark');
    }
</script>
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
    
        /* ═══════════════════════════════════════════════════════════ */
        /*  NEON PROFESSIONAL LIGHT THEME                              */
        /* ═══════════════════════════════════════════════════════════ */
        html:not(.dark) body {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
            background-image: 
                linear-gradient(to right, rgba(6, 182, 212, 0.06) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(6, 182, 212, 0.06) 1px, transparent 1px) !important;
        }

        /* Top Glow Ambient Accent for Light Mode */
        html:not(.dark) .fixed.top-0.left-1\/2 {
            background: linear-gradient(to right, rgba(6, 182, 212, 0.15), rgba(99, 102, 241, 0.15), rgba(168, 85, 247, 0.15)) !important;
            filter: blur(40px) !important;
        }

        /* Cards & Section Panels */
        html:not(.dark) section,
        html:not(.dark) .bg-slate-900,
        html:not(.dark) .bg-slate-900\/60,
        html:not(.dark) .bg-slate-900\/50,
        html:not(.dark) .bg-slate-900\/40,
        html:not(.dark) .bg-slate-950\/90,
        html:not(.dark) .bg-slate-950\/80,
        html:not(.dark) .bg-slate-950\/60,
        html:not(.dark) .bg-slate-950,
        html:not(.dark) .bg-surface-base,
        html:not(.dark) .bg-indigo-950\/40 {
            background-color: #ffffff !important;
            color: #0f172a !important;
            border-color: rgba(6, 182, 212, 0.25) !important;
            box-shadow: 0 4px 20px -2px rgba(6, 182, 212, 0.08), 0 2px 6px -1px rgba(0, 0, 0, 0.04) !important;
        }

        /* Nav Bar & Header */
        html:not(.dark) header,
        html:not(.dark) nav {
            background-color: rgba(255, 255, 255, 0.9) !important;
            backdrop-filter: blur(12px) !important;
            border-color: rgba(6, 182, 212, 0.2) !important;
        }

        
        html:not(.dark) #terminal-container * {
            color: inherit;
        }

        /* Typography & Headings */
        html:not(.dark) h1, html:not(.dark) h2, html:not(.dark) h3, html:not(.dark) h4 {
            color: #0f172a !important;
        }
        html:not(.dark) .text-slate-100,
        html:not(.dark) .text-slate-200,
        html:not(.dark) .text-slate-300 {
            color: #1e293b !important;
        }
        html:not(.dark) .text-slate-400,
        html:not(.dark) .text-slate-500 {
            color: #475569 !important;
        }

        /* Sub-cards, Containers & Form Blocks */
        html:not(.dark) .bg-slate-800,
        html:not(.dark) .bg-slate-800\/60,
        html:not(.dark) .bg-slate-800\/80,
        html:not(.dark) .bg-slate-800\/90,
        html:not(.dark) .bg-slate-800\/50 {
            background-color: #f8fafc !important;
            color: #0f172a !important;
            border-color: rgba(6, 182, 212, 0.2) !important;
        }

        /* Inputs & Controls */
        html:not(.dark) input,
        html:not(.dark) textarea,
        html:not(.dark) select {
            background-color: #ffffff !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.03) !important;
        }
        html:not(.dark) input:focus,
        html:not(.dark) textarea:focus,
        html:not(.dark) select:focus {
            border-color: #06b6d4 !important;
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.2) !important;
        }

        /* Neon Accent Badges & Buttons */
        html:not(.dark) .bg-cyan-500\/10 {
            background-color: rgba(6, 182, 212, 0.12) !important;
        }
        html:not(.dark) .text-cyan-400 {
            color: #0284c7 !important;
        }
        html:not(.dark) .border-cyan-500\/20,
        html:not(.dark) .border-cyan-500\/30 {
            border-color: rgba(6, 182, 212, 0.4) !important;
        }

        /* Modals in Light Mode */
        html:not(.dark) #admin-auth-modal,
        html:not(.dark) #node-modal {
            background-color: rgba(15, 23, 42, 0.4) !important;
            backdrop-filter: blur(8px) !important;
        }
        html:not(.dark) #admin-auth-modal > div,
        html:not(.dark) #node-modal > div {
            background-color: #ffffff !important;
            border-color: rgba(6, 182, 212, 0.3) !important;
            box-shadow: 0 20px 40px -10px rgba(6, 182, 212, 0.25), 0 0 20px rgba(6, 182, 212, 0.15) !important;
        }

    
        /* ═══════════════════════════════════════════════════════════ */
        /*  LIGHT MODE SPECIFIC VISUAL FIXES                           */
        /* ═══════════════════════════════════════════════════════════ */

        /* 1. Welcome / Hero Banner Fix */
        html:not(.dark) section.mb-8 {
            background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 50%, #e0f2fe 100%) !important;
            border-color: rgba(6, 182, 212, 0.3) !important;
            box-shadow: 0 10px 25px -5px rgba(6, 182, 212, 0.15) !important;
        }
        html:not(.dark) section.mb-8 h1 {
            color: #0f172a !important;
        }
        html:not(.dark) section.mb-8 p {
            color: #334155 !important;
        }
        html:not(.dark) section.mb-8 .border-b {
            border-color: rgba(6, 182, 212, 0.2) !important;
        }
        html:not(.dark) section.mb-8 .grid > div {
            background-color: #ffffff !important;
            border-color: rgba(6, 182, 212, 0.25) !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04) !important;
        }
        html:not(.dark) section.mb-8 .grid > div p {
            color: #475569 !important;
        }
        html:not(.dark) section.mb-8 .grid > div button {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
            border-color: rgba(6, 182, 212, 0.3) !important;
        }
        html:not(.dark) section.mb-8 .grid > div button:hover {
            background-color: rgba(6, 182, 212, 0.15) !important;
            color: #0284c7 !important;
        }

        /* 2. House Rules Fix */
        html:not(.dark) .border-cyan-500\/30.bg-cyan-950\/20,
        html:not(.dark) div.rounded-xl.border-cyan-500\/30 {
            background-color: #ffffff !important;
            border-color: rgba(6, 182, 212, 0.3) !important;
            box-shadow: 0 4px 15px rgba(6, 182, 212, 0.08) !important;
        }
        html:not(.dark) .border-cyan-500\/30.bg-cyan-950\/20 .bg-cyan-900\/30,
        html:not(.dark) div.rounded-xl.border-cyan-500\/30 .bg-cyan-900\/30 {
            background-color: #ecfeff !important;
            border-color: rgba(6, 182, 212, 0.25) !important;
        }
        html:not(.dark) .border-cyan-500\/30.bg-cyan-950\/20 h3,
        html:not(.dark) div.rounded-xl.border-cyan-500\/30 h3 {
            color: #0369a1 !important;
        }
        html:not(.dark) .border-cyan-500\/30.bg-cyan-950\/20 p,
        html:not(.dark) div.rounded-xl.border-cyan-500\/30 p {
            color: #334155 !important;
        }
        html:not(.dark) .border-cyan-500\/30.bg-cyan-950\/20 strong.text-white,
        html:not(.dark) .border-cyan-500\/30.bg-cyan-950\/20 strong,
        html:not(.dark) div.rounded-xl.border-cyan-500\/30 strong {
            color: #0f172a !important;
        }
        html:not(.dark) .border-cyan-500\/30.bg-cyan-950\/20 code,
        html:not(.dark) div.rounded-xl.border-cyan-500\/30 code {
            background-color: #f1f5f9 !important;
            color: #0284c7 !important;
            border: 1px solid #cbd5e1 !important;
        }

        /* 3. Navigation Bar & Buttons Fix */
        html:not(.dark) header {
            background-color: rgba(255, 255, 255, 0.95) !important;
            border-color: rgba(226, 232, 240, 0.8) !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
        }
        html:not(.dark) nav {
            background-color: #f1f5f9 !important;
            border-color: #e2e8f0 !important;
        }
        html:not(.dark) nav a.bg-slate-800,
        html:not(.dark) nav a.bg-slate-200 {
            background-color: #0f172a !important;
            color: #ffffff !important;
            border-color: #0f172a !important;
        }
        html:not(.dark) nav a:not(.bg-slate-800):not(.bg-slate-200) {
            color: #475569 !important;
        }
        html:not(.dark) nav a:not(.bg-slate-800):not(.bg-slate-200):hover {
            color: #0f172a !important;
            background-color: #e2e8f0 !important;
        }
        html:not(.dark) #admin-nav-btn {
            background-color: rgba(6, 182, 212, 0.1) !important;
            border-color: rgba(6, 182, 212, 0.3) !important;
            color: #0284c7 !important;
        }
        html:not(.dark) #admin-nav-btn:hover {
            background-color: rgba(6, 182, 212, 0.2) !important;
            color: #0369a1 !important;
        }
        html:not(.dark) #admin-nav-btn svg {
            color: #0284c7 !important;
        }
        html:not(.dark) #sys-status-btn {
            background-color: rgba(16, 185, 129, 0.1) !important;
            border-color: rgba(16, 185, 129, 0.3) !important;
            color: #047857 !important;
        }
        html:not(.dark) #sys-status-btn span.text-emerald-300 {
            color: #047857 !important;
        }
        html:not(.dark) header a[title="Diagnostic Tool"],
        html:not(.dark) header a[title="Host a Website"],
        html:not(.dark) header #nav-menu-btn {
            background-color: #f8fafc !important;
            border-color: #cbd5e1 !important;
            color: #334155 !important;
        }
        html:not(.dark) header a[title="Diagnostic Tool"]:hover,
        html:not(.dark) header a[title="Host a Website"]:hover,
        html:not(.dark) header #nav-menu-btn:hover {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
        }

    
        /* Live Server Activity Logs in Light Mode: White Background & Green Text */
        html:not(.dark) .bg-surface-terminal,
        html:not(.dark) #terminal-container,
        html:not(.dark) #terminal {
            background-color: #ffffff !important;
            color: #15803d !important;
            border-color: rgba(6, 182, 212, 0.3) !important;
            box-shadow: 0 4px 20px -2px rgba(6, 182, 212, 0.08) !important;
        }
        html:not(.dark) #terminal *,
        html:not(.dark) #output,
        html:not(.dark) #output * {
            color: #15803d !important;
        }
        html:not(.dark) #terminal .text-emerald-400,
        html:not(.dark) #terminal .text-cyan-400,
        html:not(.dark) #terminal .text-slate-400,
        html:not(.dark) #terminal .text-slate-500 {
            color: #16a34a !important;
        }

    </style>
</head>
<body class="bg-surface-base text-slate-200 font-sans min-h-screen flex flex-col antialiased selection:bg-cyan-500/30 selection:text-cyan-200 bg-grid-pattern relative">
    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-3/4 max-w-4xl h-36 bg-gradient-to-r from-cyan-500/10 via-blue-500/10 to-indigo-500/10 blur-3xl pointer-events-none -z-10"></div>
    <header class="sticky top-0 z-40 w-full border-b border-slate-800/80 bg-slate-950/80 backdrop-blur-xl transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-3 sm:gap-4">
            
            <!-- Left: Brand / Title & Primary Navigation -->
            <div class="flex items-center gap-3.5 shrink-0 min-w-0">
                <a href="index.html" class="flex items-center gap-3 shrink-0 group select-none cursor-pointer" title="Go to Server Console Dashboard">
                    <div class="h-9 w-9 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center shadow-lg shadow-cyan-500/20 ring-1 ring-white/15 group-hover:scale-105 transition-transform shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.75 5.1a1.5 1.5 0 011.2-.6h10.1a1.5 1.5 0 011.2.6l2.1 3.45a4.5 4.5 0 01.9 2.7" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 whitespace-nowrap">
                            <span class="font-bold text-slate-100 tracking-tight text-base sm:text-lg group-hover:text-white transition-colors">Server Console</span>
                            <span class="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-slate-800/90 text-cyan-400 border border-slate-700/60 uppercase tracking-wider">
                                MBP 2011
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 font-mono hidden lg:block truncate whitespace-nowrap">ZeroTier Tunnel &bull; MAMP Apache &bull; Port 3307</p>
                    </div>
                </a>

                <!-- Navigation Tabs (Deployer / Hosted Sites) -->
                <nav class="hidden sm:flex items-center gap-1 ml-1 px-1.5 py-1 rounded-xl bg-slate-900/80 border border-slate-800">
                    <a href="index.html" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-800 text-white shadow-sm border border-slate-700/60">Deployer</a>
                    <a href="sites.php" class="px-2.5 py-1 rounded-lg text-xs font-medium text-slate-400 hover:text-white hover:bg-slate-800/60 transition-colors">Hosted Sites</a>
                </nav>
            </div>

            <!-- Right: Interactive Status & Quick Actions -->
            <div class="flex items-center gap-2 sm:gap-2.5 shrink-0">
                
                <!-- Admin Panel / Login Button -->
                <button 
                    type="button" 
                    id="admin-nav-btn"
                    onclick="openAdminModal()" 
                    title="Admin Panel & Authentication" 
                    class="group inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-cyan-950/40 hover:bg-cyan-900/60 border border-cyan-500/30 hover:border-cyan-400/60 text-cyan-300 hover:text-white text-xs font-semibold shadow-sm transition-all cursor-pointer focus:outline-none focus:ring-1 focus:ring-cyan-400/50"
                >
                    <svg class="admin-nav-icon-lock-el w-3.5 h-3.5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                    <span class="admin-nav-text-el whitespace-nowrap">Admin Login</span>
                </button>

                <!-- Admin Logged In Indicator (Hidden by default) -->
                <div id="admin-logged-in-indicator" class="hidden items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-950/40 border border-emerald-500/30 text-emerald-400 text-xs font-semibold shadow-sm cursor-default">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="whitespace-nowrap" id="admin-logged-in-username">Admin Active</span>
                </div>

                <!-- Admin Logout Button (Hidden by default) -->
                <button 
                    type="button" 
                    id="admin-logout-btn"
                    onclick="submitAdminLogout()" 
                    title="Log Out" 
                    class="hidden items-center justify-center p-1.5 rounded-xl bg-rose-950/40 hover:bg-rose-900/60 border border-rose-500/30 hover:border-rose-400/60 text-rose-400 hover:text-white transition-all cursor-pointer focus:outline-none focus:ring-1 focus:ring-rose-400/50"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                    </svg>
                </button>

                <!-- Dark/Light Mode Toggle -->
                <button 
                    type="button" 
                    id="theme-toggle-btn"
                    onclick="toggleTheme()" 
                    title="Toggle Dark/Light Mode" 
                    class="flex items-center justify-center p-1.5 rounded-xl bg-slate-800/60 hover:bg-slate-700/80 border border-slate-700/60 hover:border-slate-500/60 text-slate-400 hover:text-slate-200 transition-all cursor-pointer focus:outline-none focus:ring-1 focus:ring-slate-400/50"
                >
                    <!-- Sun icon for dark mode (to switch to light) -->
                    <svg id="theme-icon-sun" class="w-4 h-4 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                    </svg>
                    <!-- Moon icon for light mode (to switch to dark) -->
                    <svg id="theme-icon-moon" class="w-4 h-4 block dark:hidden" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                    </svg>
                </button>

                <!-- Interactive System Online Button (Opens Hardware Node Modal) -->
                <button 
                    type="button" 
                    id="sys-status-btn"
                    onclick="openNodeModal()" 
                    title="Click to view Server Hardware &amp; System Specs" 
                    class="group inline-flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3 py-1.5 rounded-full bg-emerald-950/60 hover:bg-emerald-900/70 border border-emerald-500/30 hover:border-emerald-400/60 text-emerald-400 hover:text-emerald-300 text-xs font-medium shadow-sm shadow-emerald-950/30 transition-all cursor-pointer focus:outline-none focus:ring-1 focus:ring-emerald-400/50"
                >
                    <span class="relative flex h-2 w-2 shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="hidden xs:inline tracking-wide text-emerald-300 font-mono text-[11px] font-semibold whitespace-nowrap">System Online</span>
                    <svg class="w-3.5 h-3.5 text-emerald-400/70 group-hover:text-emerald-300 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                    </svg>
                </button>

                <!-- Diagnostic Tool Button -->
                <a 
                    href="auto_debug.php" 
                    title="Diagnostic Tool" 
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-slate-300 text-xs font-medium transition-colors cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.492-3.053c.24-.294.577-.487.94-.539l2.716-.388a.75.75 0 00.58-1.127l-3.264-5.22a.75.75 0 00-1.11-.157l-1.92 1.92-3.265-5.22a.75.75 0 00-1.11-.157l-1.92 1.92 2.492-3.053c.24-.294.577-.487.94-.539l2.716-.388z" />
                    </svg>
                    <span>Diagnostics</span>
                </a>

                <!-- Host Website Button -->
                <a 
                    href="host.php" 
                    title="Host a Website" 
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-slate-300 text-xs font-medium transition-colors cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                    </svg>
                    <span>Host Site</span>
                </a>

                <!-- Tools Dropdown Menu Button (Admin Only) -->
                <div class="relative hidden" id="nav-dropdown-wrapper">
                    <button 
                        onclick="toggleNavMenu()" 
                        id="nav-menu-btn" 
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-slate-300 text-xs font-medium transition-colors cursor-pointer"
                    >
                        <span>Tools</span>
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <!-- Dropdown Content -->
                    <div id="nav-dropdown" class="hidden absolute right-0 mt-2 w-52 rounded-xl bg-slate-900/95 border border-slate-800 shadow-2xl backdrop-blur-xl p-1.5 z-50 divide-y divide-slate-800/60">
                        <div class="py-1">
                            <button onclick="openAdminModal(); toggleNavMenu();" class="w-full text-left flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold text-cyan-300 hover:text-white hover:bg-slate-800/80 transition-colors cursor-pointer">
                                <span class="w-2 h-2 rounded-full bg-cyan-400"></span> <span class="admin-dropdown-text-el">Admin Login</span>
                            </button>
                            <a href="index.html" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-cyan-400"></span> Deployer Console
                            </a>
                            <a href="sites.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-cyan-400"></span> Hosted Sites
                            </a>
                            <a href="host.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-blue-400"></span> Host Website
                            </a>
                            <a href="movies.html" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-amber-400"></span> Plex Media Portal
                            </a>
                            <a href="access.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-indigo-400"></span> Access Request
                            </a>
                            <a href="help.html" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-cyan-400"></span> Server Docs &amp; Help
                            </a>
                            <button onclick="openNodeModal(); toggleNavMenu();" class="w-full text-left flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800/80 transition-colors cursor-pointer">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Hardware Node Specs
                            </button>
                        </div>
                        <div class="py-1 admin-only-tool hidden">
                            <div class="px-3 py-1 text-[10px] font-mono font-semibold text-slate-500 uppercase tracking-wider">Admin Controls</div>
                            <a href="index.html#database-section" onclick="toggleNavMenu();" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-indigo-300 hover:text-white hover:bg-slate-800/80 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-indigo-400"></span> New Database
                            </a>
                            <a href="debug.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-amber-300 hover:text-white hover:bg-slate-800/80 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-amber-400"></span> Diagnostics
                            </a>
                            <a href="auto_debug.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-purple-300 hover:text-white hover:bg-slate-800/80 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-purple-400"></span> Auto Debug
                            </a>
                        </div>
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

    <script src="js/admin_auth.js"></script>

<!-- Admin Auth & Control Panel Modal -->
<div id="admin-auth-modal" class="fixed inset-0 z-50 bg-slate-50 dark:bg-slate-950/80 backdrop-blur-md hidden items-start sm:items-center justify-center p-4 overflow-y-auto py-8">
        <div class="relative w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-6 overflow-hidden animate-in fade-in zoom-in duration-150 my-auto">
            <!-- Top Gradient Accent Bar -->
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500"></div>

            <!-- Close Button -->
            <button onclick="closeAdminModal()" class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-500 dark:text-slate-400 hover:text-white hover:bg-slate-200 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- STATE 1: LOGGED OUT LOGIN FORM -->
            <div id="admin-modal-login-view" class="space-y-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-400">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">System Login</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Secure access to the Server Console</p>
                    </div>
                </div>

                <!-- Error Banner -->
                <div id="admin-login-error" class="hidden p-3 rounded-xl bg-rose-950/60 border border-rose-500/40 text-rose-300 text-xs font-medium"></div>
                <!-- Success Banner -->
                <div id="admin-login-success" class="hidden p-3 rounded-xl bg-emerald-950/60 border border-emerald-500/40 text-emerald-300 text-xs font-medium"></div>

                <form onsubmit="submitAdminLogin(event);" class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Username</label>
                        <input 
                            type="text" 
                            id="admin-username-input" 
                            name="username"
                            autocomplete="off"
                            required 
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-950/80 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-500 font-mono text-xs focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-colors"
                            placeholder="Enter username"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Password</label>
                        <input 
                            type="password" 
                            id="admin-password-input" 
                            name="password"
                            autocomplete="off"
                            required 
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-950/80 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-500 font-mono text-xs focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-colors"
                            placeholder="••••••••"
                        >
                    </div>

                    <div class="pt-1">
                        <button 
                            type="submit" 
                            id="admin-login-btn"
                            class="w-full py-3 px-4 rounded-xl font-semibold text-xs text-white bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 transition-all duration-200 shadow-lg shadow-cyan-500/25 flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span>Authenticate &amp; Unlock Controls</span>
                        </button>
                    </div>
                
                    <div class="text-center pt-3">
                        <button type="button" onclick="toggleAuthView('register')" class="text-xs font-medium text-cyan-500 hover:text-cyan-400 transition-colors cursor-pointer">
                            Don't have an account? Create one
                        </button>
                    </div>
                </form>


                <div class="p-3 rounded-xl bg-slate-100 dark:bg-slate-950/60 border border-slate-300 dark:border-slate-800/80 text-[11px] text-slate-500 dark:text-slate-400 space-y-1">
                    <div class="font-semibold text-slate-600 dark:text-slate-300">🔐 Database Security Notice:</div>
                    <p>Reads and writes credentials directly to <code>access_db.sys_users</code> table using BCrypt hashes.</p>
                </div>
            </div>

            
            
            <!-- Registration View (Hidden by default) -->
            <div id="admin-modal-register-view" class="space-y-4 hidden">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Create an Account</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Register a new standard user account</p>
                    </div>
                </div>

                <!-- Error/Success Banner -->
                <div id="register-message-banner" class="hidden p-3 rounded-xl text-xs font-medium border"></div>

                <form onsubmit="submitRegister(event);" class="space-y-3.5" autocomplete="off">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Username</label>
                        <input type="text" id="register-username-input" autocomplete="off" name="new-username" autocomplete="off" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-950/80 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-500 font-mono text-xs focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors" placeholder="Choose a username">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Password</label>
                        <input type="password" id="register-password-input" autocomplete="off" name="new-password" autocomplete="new-password" required minlength="6" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-950/80 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-500 font-mono text-xs focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors" placeholder="Create a password (min 6 chars)">
                    </div>
                    
                    <button type="submit" id="register-submit-btn" class="w-full py-3 px-4 rounded-xl font-semibold text-xs text-white bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-400 hover:to-purple-500 transition-all duration-200 shadow-lg shadow-indigo-500/25 flex items-center justify-center cursor-pointer">
                        Create Account
                    </button>
                    
                    <div class="text-center pt-3">
                        <button type="button" onclick="toggleAuthView('login')" class="text-xs font-medium text-slate-500 dark:text-slate-400 hover:text-white transition-colors cursor-pointer">
                            Already have an account? Log In
                        </button>
                    </div>
                </form>
            </div>

            <!-- STATE 3: MANAGE ADMINS VIEW -->
            <div id="admin-modal-manage-view" class="hidden space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                        <span>Manage Admins</span>
                    </h3>
                    <button type="button" onclick="showAdminPanelView()" class="text-[10px] text-slate-500 dark:text-slate-400 hover:text-white font-semibold flex items-center gap-1 cursor-pointer">
                        <span>&larr; Back</span>
                    </button>
                </div>
                
                <div class="rounded-xl border border-slate-300 dark:border-slate-800 bg-slate-100 dark:bg-slate-950/60 overflow-hidden">
                    <div id="admin-accounts-list" class="divide-y divide-slate-300 dark:divide-slate-800/80 max-h-40 overflow-y-auto">
                        <!-- Populated by JS -->
                        <div class="p-3 text-center text-xs text-slate-500">Loading admins...</div>
                    </div>
                </div>

<!-- STATE 1: LOGGED OUT LOGIN FORM -->
        <div id="admin-modal-login-view" class="space-y-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-400">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-100">System Login</h3>
                    <p class="text-xs text-slate-400">Secure access to the Server Console</p>
                </div>
            </div>

            <div id="admin-login-error" class="hidden p-3 rounded-xl bg-rose-950/60 border border-rose-500/40 text-rose-300 text-xs font-medium"></div>

            <form onsubmit="event.preventDefault(); submitAdminLogin();" class="space-y-3.5">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Username</label>
                    <input 
                        type="text" 
                        id="admin-username-input" 
                        value="admin" 
                        required 
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-500 font-mono text-xs focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-colors"
                        placeholder="Enter username"
                    >
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Password</label>
                    <input 
                        type="password" 
                        id="admin-password-input" 
                        value="123456789"
                        required 
                        class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-500 font-mono text-xs focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-colors"
                        placeholder="••••••••"
                    >
                </div>

                <div class="pt-1">
                    <button 
                        type="submit" 
                        id="admin-login-btn"
                        class="w-full py-3 px-4 rounded-xl font-semibold text-xs text-white bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 transition-all duration-200 shadow-lg shadow-cyan-500/25 flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <span>Authenticate &amp; Unlock Controls</span>
                    </button>
                </div>
            </form>

            <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 text-[11px] text-slate-400 space-y-1">
                <div class="font-semibold text-slate-300">🔐 Database Security Notice:</div>
                <p>Reads and writes credentials directly to <code>access_db.sys_users</code> table using BCrypt hashes.</p>
            </div>
        </div>

        <!-- STATE 2: LOGGED IN ADMIN PANEL VIEW -->
        <div id="admin-modal-panel-view" class="hidden space-y-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-100 flex items-center gap-2">
                        <span>Admin Control Panel</span>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-mono bg-emerald-950 text-emerald-400 border border-emerald-500/30">Active</span>
                    </h3>
                    <p class="text-xs text-slate-400">Authenticated as <span id="admin-username-display" class="font-mono text-cyan-400 font-semibold">admin</span></p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 text-xs font-mono">
                <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800">
                    <span class="text-[10px] text-slate-500 uppercase block">Privileges</span>
                    <span class="text-emerald-400 font-semibold">Full Read &amp; Write</span>
                </div>
                <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800">
                    <span class="text-[10px] text-slate-500 uppercase block">Database</span>
                    <span class="text-indigo-400 font-semibold">access_db</span>
                </div>
            </div>

            <div class="space-y-2">
                <span class="text-xs font-semibold text-slate-300 block">Quick Admin Shortcuts:</span>
                <div class="grid grid-cols-1 gap-2">
                    <a href="index.html#database-section" onclick="closeAdminModal();" class="p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 hover:border-cyan-500/40 text-xs font-medium text-slate-300 hover:text-white flex items-center justify-between transition-colors">
                        <span>✨ Create New Database</span>
                        <span class="text-cyan-400 font-mono text-[11px]">&rarr;</span>
                    </a>
                    <a href="auto_debug.php" class="p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 hover:border-purple-500/40 text-xs font-medium text-slate-300 hover:text-white flex items-center justify-between transition-colors">
                        <span>⚡ Run Auto Debug Diagnostics</span>
                        <span class="text-purple-400 font-mono text-[11px]">&rarr;</span>
                    </a>
                </div>
            </div>

            <div class="pt-2">
                <button 
                    type="button" 
                    onclick="submitAdminLogout()" 
                    class="w-full py-2.5 px-4 rounded-xl font-semibold text-xs text-rose-300 hover:text-rose-200 bg-rose-950/50 hover:bg-rose-950/80 border border-rose-500/40 transition-all flex items-center justify-center gap-2 cursor-pointer"
                >
                    <svg class="w-4 h-4 text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                    </svg>
                    <span>Log Out of Admin Panel</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hardware Node & Telemetry Specs Popup Modal -->
<div id="node-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="w-full max-w-lg rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-150">
        <div class="h-1 w-full bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500"></div>
        <div class="p-6">
            <div class="flex items-center justify-between border-b border-slate-800/80 pb-4 mb-4">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.75 5.1a1.5 1.5 0 011.2-.6h10.1a1.5 1.5 0 011.2.6l2.1 3.45a4.5 4.5 0 01.9 2.7" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-slate-100 text-base tracking-tight">Server Hardware Node</h3>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-950/80 border border-emerald-500/30 text-emerald-300 font-mono text-[10px] font-semibold">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                Online
                            </span>
                        </div>
                        <p class="text-xs text-slate-400">MacBook Pro (13-inch, Late 2011) Host Node</p>
                    </div>
                </div>
                <button type="button" onclick="closeNodeModal()" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-200 hover:bg-slate-800/80 transition-colors cursor-pointer" title="Close dialog">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="space-y-2 mb-5 text-xs font-mono">
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800/80">
                    <span class="text-slate-400">Host Device</span>
                    <span class="text-slate-200">MacBook Pro (Late 2011)</span>
                </div>
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800/80">
                    <span class="text-slate-400">Network Tunnel</span>
                    <span class="text-cyan-400 font-semibold flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        ZeroTier Mesh (10.247.192.231)
                    </span>
                </div>
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800/80">
                    <span class="text-slate-400">Web Engine (HTTP)</span>
                    <span class="text-slate-200">Apache 2.4 &bull; MAMP Pro</span>
                </div>
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800/80">
                    <span class="text-slate-400">Database Engine</span>
                    <span class="text-indigo-400 font-semibold">MySQL 5.7 &bull; Port 3307 / 8889</span>
                </div>
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800/80">
                    <span class="text-slate-400">Media Server</span>
                    <span class="text-amber-400 font-semibold">Plex Server &bull; Port 32400</span>
                </div>
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800/80">
                    <span class="text-slate-400">Web Storage Root</span>
                    <span class="text-slate-300 truncate max-w-[210px]">/Volumes/htdocs</span>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-2.5 pt-1">
                <a href="debug.php" class="flex-1 py-2.5 px-4 rounded-xl text-xs font-semibold text-white bg-slate-800 hover:bg-slate-700 border border-slate-700 transition-colors flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    <span>Open Diagnostics</span>
                </a>
                <button type="button" onclick="closeNodeModal()" class="py-2.5 px-5 rounded-xl text-xs font-semibold text-slate-300 hover:text-white bg-slate-950 hover:bg-slate-800 border border-slate-800 transition-colors cursor-pointer">
                    Dismiss
                </button>
            </div>
        </div>
    </div>
</div>

    <script>
        // Auto-run on load
        document.addEventListener('DOMContentLoaded', () => {
            checkAdminAuth();
        });
    </script>
</body>
</html>
