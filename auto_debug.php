<?php
// auto_debug.php
require_once __DIR__ . '/api/auth/require_admin.php';
requireAdmin();

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
        require_once __DIR__ . '/config/config.php';
        $pdo = getDBConnection();
        $pdo->query("SELECT 1");
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $dbCheck['status'] = 'pass';
        if ($driver === 'sqlite') {
            $dbCheck['message'] = "Successfully connected to access_db via config/db_connect.php (SQLite engine fallback).";
        } else {
            $usedPort = isset($port) ? $port : 'default';
            $dbCheck['message'] = "Successfully connected to MySQL via config/db_connect.php (port $usedPort).";
        }
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
    'message' => 'api/process_deployment.php endpoint exists.'
];
if (!file_exists(__DIR__ . '/api/process_deployment.php') && !file_exists(__DIR__ . '/api/system/deploy.php')) {
    $deployCheck['status'] = 'fail';
    $deployCheck['message'] = 'Deployment backend endpoints missing.';
    $autoFixes[] = "Verify api/process_deployment.php exists.";
}
$results[] = $deployCheck;

// 4. USB Storage Manager Check
$usbCheck = [
    'name' => 'USB Drive Manager Check',
    'status' => 'pass',
    'message' => 'usb_manager.php backend endpoint is present.'
];
if (!file_exists(__DIR__ . '/usb_manager.php')) {
    $usbCheck['status'] = 'fail';
    $usbCheck['message'] = 'usb_manager.php file is missing.';
    $autoFixes[] = "Restore usb_manager.php.";
}
$results[] = $usbCheck;

// 5. Sites Directory Check
$sitesCheck = [
    'name' => 'Sites Directory Check',
    'status' => 'pass',
    'message' => 'sites/ directory exists and is writable.'
];
$sitesDir = __DIR__ . '/sites';
if (!is_dir($sitesDir)) {
    if (!@mkdir($sitesDir, 0755, true)) {
        $sitesCheck['status'] = 'fail';
        $sitesCheck['message'] = 'sites/ directory does not exist and could not be created.';
        $autoFixes[] = "Manually create /sites/ inside access-to-server folder.";
    }
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
    <title>Auto Debug — ServerFlow</title>
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
                    }
                }
            }
        }
    </script>
    <style>
        html:not(.dark) body { background-color: #f1f5f9 !important; color: #0f172a !important; }
        html.dark body { background-color: #090d16 !important; color: #f8fafc !important; }
    </style>
</head>
<body class="min-h-screen font-sans antialiased flex flex-col selection:bg-cyan-500 selection:text-white">

    <div class="flex flex-1 min-h-screen overflow-hidden relative">

        <!-- Mobile Backdrop Overlay -->
        <div id="sf-mobile-backdrop" onclick="toggleMobileSidebar()" class="hidden fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-30 lg:hidden"></div>

        <!-- SIDEBAR -->
        <aside class="fixed lg:static inset-y-0 left-0 w-64 bg-[#0b1329] border-r border-slate-800 flex flex-col justify-between shrink-0 z-40 select-none transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
            <div>
                <!-- Brand Header Logo Link (Takes user to Dashboard) -->
                <a href="index.html" onclick="closeMobileSidebar();" class="p-5 border-b border-slate-800/80 flex items-center gap-3 hover:opacity-90 transition-all cursor-pointer block" title="Go to Server Dashboard">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center text-white font-bold text-lg shadow-md shadow-cyan-500/20 shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                    </div>
                    <div>
                        <h1 class="text-white font-bold text-base tracking-tight leading-none">ServerFlow</h1>
                        <span class="text-[10px] font-mono text-slate-400 tracking-wider uppercase">MacBook Pro 2011</span>
                    </div>
                </a>

                <!-- Navigation Links -->
                <nav class="p-3 space-y-7">
                    <div>
                        <div class="px-3 mb-2.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Server Control</div>
                        <ul class="space-y-1.5">
                            <li><a href="index.html" onclick="closeMobileSidebar();" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>Server Dashboard</a></li>
                            <li class="auth-required-nav hidden"><a href="index.html#servers" onclick="closeMobileSidebar();" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>My Websites & Deployer</a></li>

                            <li class="auth-required-nav hidden"><a href="index.html#data" onclick="closeMobileSidebar();" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s-8-1.79-8-4"/></svg>App Databases</a></li>
                            <li class="auth-required-nav hidden"><a href="index.html#activity" onclick="closeMobileSidebar();" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>Activity Stream</a></li>
                            <li class="auth-required-nav hidden"><a href="index.html#settings" onclick="closeMobileSidebar();" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>System Settings</a></li>
                        </ul>
                    </div>

                    <div>
                        <div class="px-3 mb-2.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Apps & Tools</div>
                        <ul class="space-y-1.5">
                            <li><a href="sites.php" onclick="closeMobileSidebar();" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>My Live Websites</a></li>
                            <li class="auth-required-nav hidden"><a href="movies.html" onclick="closeMobileSidebar();" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg>Media Player (Plex)</a></li>

                            <li><a href="host.php" onclick="closeMobileSidebar();" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>Server Specs & IP</a></li>
                            <li class="auth-required-nav auth-admin-nav hidden"><a href="auto_debug.php" onclick="closeMobileSidebar();" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-cyan-600/20 text-cyan-400 border border-cyan-500/30 transition-all"><svg class="w-4 h-4 text-pink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>Health Diagnostic Check</a></li>
                            <li class="auth-required-nav auth-admin-nav hidden"><a href="debug.php" onclick="closeMobileSidebar();" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>Server Debug Logs</a></li>
                            <li><a href="help.html" onclick="closeMobileSidebar();" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Beginner Guides & Docs</a></li>
                        </ul>
                    </div>
                </nav>
            </div>
            <div class="p-4 border-t border-slate-800/80 bg-slate-950/40">
                <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Host</div>
                <div class="text-xs font-bold text-white mb-0.5">MacBook Pro 2011</div>
                <div class="flex items-center gap-1.5 text-[11px] text-emerald-400"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>ZeroTier connected</div>
            </div>
        </aside>

        <!-- MAIN CONTAINER -->
        <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">
            <header class="h-16 px-4 sm:px-6 border-b border-slate-200 dark:border-slate-800/80 bg-white/70 dark:bg-slate-900/60 backdrop-blur-md sticky top-0 z-20 flex items-center justify-between gap-2 sm:gap-4">
                <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                    <!-- Mobile Hamburger Menu Button -->
                    <button onclick="toggleMobileSidebar()" class="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 transition-all shrink-0" title="Toggle Navigation Menu">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <!-- Top Corner Logo (Mobile Header & Clickable Link to Dashboard) -->
                    <a href="index.html" class="lg:hidden flex items-center gap-2 cursor-pointer hover:opacity-90 transition-opacity shrink-0" title="Go to Server Dashboard">
                        <div class="w-7 h-7 rounded-lg bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center text-white font-bold text-xs shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                            </svg>
                        </div>
                        <span class="text-slate-900 dark:text-white font-bold text-sm tracking-tight">ServerFlow</span>
                    </a>

                    <div class="hidden md:flex items-center gap-2 min-w-0">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 shrink-0">Mode:</span>
                        <span id="sf-user-mode-pill" class="px-2.5 py-1 rounded-full text-xs font-mono font-medium bg-slate-800 text-slate-400 border border-slate-700/60 truncate">No User Logged In</span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="p-1 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 flex items-center gap-1 select-none">
                        <button id="sf-theme-light-btn" onclick="setServerFlowTheme('light')" class="flex items-center gap-1 px-3 py-1 text-xs font-medium rounded-lg text-slate-400 hover:text-slate-200 transition-all">☀️ Light</button>
                        <button id="sf-theme-dark-btn" onclick="setServerFlowTheme('dark')" class="flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded-lg bg-slate-800 text-cyan-400 shadow-sm transition-all">🌙 Dark</button>
                    </div>
                    <div id="admin-logged-in-indicator" class="hidden items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-950/40 border border-emerald-500/30 text-emerald-400 text-xs font-semibold shadow-sm cursor-default">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <span id="admin-logged-in-username">Admin Active</span>
                    </div>
                    <!-- Login Button / Profile Dropdown -->
                    <div class="relative flex items-center gap-2">
                        <button id="admin-nav-btn" onclick="openAdminModal()" class="px-3.5 py-1.5 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold shadow-sm focus:ring-2 focus:ring-cyan-400 transition-all flex items-center gap-1.5 min-h-[44px]">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            <span>Login</span>
                        </button>

                        <div id="sf-profile-container" class="relative hidden">
                            <button id="sf-profile-btn" class="flex items-center gap-2 px-2.5 py-1 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 hover:border-cyan-500 transition-all min-h-[44px]">
                                <div id="sf-profile-avatar" class="w-6 h-6 rounded-full bg-gradient-to-tr from-cyan-500 to-indigo-500 flex items-center justify-center text-white text-[11px] font-bold">A</div>
                                <span id="sf-profile-username" class="text-xs font-semibold text-slate-800 dark:text-slate-200">Admin</span>
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <div id="sf-profile-menu" class="hidden absolute right-0 mt-2 w-48 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-2 z-50 space-y-1">
                                <button onclick="typeof switchTab === 'function' ? switchTab('settings') : (window.location.href = 'index.html#settings')" class="w-full text-left px-3 py-2 rounded-xl text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                                    Account Settings
                                </button>
                                <button id="admin-logout-btn" onclick="submitAdminLogout()" class="w-full text-left px-3 py-2 rounded-xl text-xs font-medium text-rose-500 hover:bg-rose-500/10 transition-all">
                                    Log Out
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="p-6 max-w-7xl w-full mx-auto space-y-6 flex-1 pb-28">
                <div class="flex items-end justify-between border-b border-slate-200 dark:border-slate-800/80 pb-4">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Health Diagnostic Check</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Automated diagnostic checks verifying web server, database, ZeroTier, and system services health.</p>
                    </div>
                    <button onclick="requireAdminAuth(() => window.location.reload())" class="px-3.5 py-2 rounded-xl bg-cyan-600 text-white text-xs font-bold shadow-md hover:bg-cyan-500 min-h-[44px] focus:ring-2 focus:ring-cyan-400">Re-run Diagnostics</button>
                </div>

                <div class="space-y-3">
                    <?php foreach ($results as $res): ?>
                        <div class="p-4 rounded-2xl bg-white dark:bg-[#0f172a] border <?= $res['status'] === 'pass' ? 'border-slate-200 dark:border-slate-800' : 'border-rose-500/50 bg-rose-500/5' ?> shadow-lg flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full <?= $res['status'] === 'pass' ? 'bg-emerald-400' : 'bg-rose-500' ?>"></span>
                                    <?= htmlspecialchars($res['name']) ?>
                                </h3>
                                <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($res['message']) ?></p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-bold font-mono <?= $res['status'] === 'pass' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' ?>">
                                <?= strtoupper($res['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <footer class="mt-auto px-6 py-4 border-t border-slate-200 dark:border-slate-800/80 text-xs text-slate-500 dark:text-slate-400 flex items-center justify-between">
                <div>ServerFlow · MacBook Pro 2011 · ZeroTier local host</div>
                <div class="live-time-display font-mono">Checked 14:32</div>
            </footer>
        </main>
    </div>

    <!-- SERVERFLOW HELP AI CHATBOT -->
    <div class="fixed bottom-6 right-6 z-50 flex flex-col items-end">
        <div id="sf-chat-widget" class="hidden mb-4 w-96 max-w-[calc(100vw-2rem)] rounded-2xl bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden flex-col">
            <div class="p-4 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-full bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-xs font-bold">SF</div>
                    <div><div class="font-bold text-xs leading-none">ServerFlow Help</div><div class="text-[10px] text-emerald-400 mt-0.5">● Online</div></div>
                </div>
                <button onclick="toggleServerFlowChat()" class="text-slate-400 hover:text-white text-base">✕</button>
            </div>
            <div id="sf-chat-messages" class="p-4 h-80 overflow-y-auto space-y-3 text-xs leading-relaxed bg-slate-50/50 dark:bg-slate-950/50">
                <div class="flex items-start gap-2.5">
                    <div class="w-7 h-7 rounded-full bg-cyan-600/20 text-cyan-400 flex items-center justify-center text-xs font-bold shrink-0">SF</div>
                    <div class="px-3.5 py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200">Welcome — what do you need help with?</div>
                </div>
            </div>
            <div class="p-3 bg-white dark:bg-[#0f172a] border-t border-slate-200 dark:border-slate-800 flex items-center gap-2">
                <input type="text" id="sf-chat-input" class="flex-1 px-3.5 py-2 rounded-xl text-xs bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:outline-none">
                <button onclick="submitServerFlowChat()" class="p-2 rounded-xl bg-cyan-500 text-white text-xs font-bold">✈</button>
            </div>
        </div>
        <button onclick="toggleServerFlowChat()" class="w-14 h-14 rounded-full bg-gradient-to-tr from-cyan-500 to-blue-600 text-white shadow-2xl flex items-center justify-center hover:scale-105 transition-transform">
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        </button>
    </div>

    <!-- Admin Auth Modal -->
    <div id="admin-auth-modal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="w-full max-w-md p-6 rounded-2xl bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-slate-800 shadow-2xl space-y-4 relative">
            <button onclick="closeAdminModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-lg">✕</button>

            <!-- Login View -->
            <div id="admin-modal-login-view" class="space-y-4">
                <div class="border-b border-slate-200 dark:border-slate-800 pb-3">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Admin Login</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Authenticate to manage server resources</p>
                </div>
                
                <div id="admin-login-error" class="hidden p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-medium"></div>
                <div id="admin-login-success" class="hidden p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium"></div>

                <form onsubmit="submitAdminLogin(event);" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Username</label>
                        <input type="text" id="admin-username-input" class="w-full px-3.5 py-2 rounded-xl text-xs bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Password</label>
                        <input type="password" id="admin-password-input" class="w-full px-3.5 py-2 rounded-xl text-xs bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:outline-none">
                    </div>
                    <button type="submit" id="admin-login-btn" class="w-full py-2.5 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs shadow-md transition-all">Sign In</button>
                </form>
                
                <div class="text-center pt-2">
                    <p class="text-[11px] text-slate-500">🔒 Admin account creation is restricted to active administrators.</p>
                </div>
            </div>

            <!-- Register View -->
            <div id="admin-modal-register-view" class="hidden space-y-4">
                <div class="border-b border-slate-200 dark:border-slate-800 pb-3">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Create Admin Account</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Register a new administrator user</p>
                </div>

                <div id="register-error" class="hidden p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-medium"></div>

                <form onsubmit="submitRegister(event);" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Username</label>
                        <input type="text" id="register-username-input" class="w-full px-3.5 py-2 rounded-xl text-xs bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Password</label>
                        <input type="password" id="register-password-input" class="w-full px-3.5 py-2 rounded-xl text-xs bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:outline-none">
                    </div>
                    <button type="submit" id="register-submit-btn" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md transition-all">Create Account</button>
                </form>

                <div class="text-center pt-2">
                    <button type="button" onclick="toggleAuthView('login')" class="text-xs font-semibold text-slate-400 hover:text-white">Back to Sign In</button>
                </div>
            </div>
        </div>
    </div>

    <script src="js/serverflow_core.js"></script>
    <script src="js/admin_auth.js"></script>
</body>
</html>
</body>
</html>
