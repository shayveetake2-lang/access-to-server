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
    <title>Host Node — ServerFlow</title>
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

    <div class="flex flex-1 min-h-screen overflow-hidden">
        <!-- SIDEBAR -->
        <aside class="w-64 bg-[#0b1329] border-r border-slate-800 flex flex-col justify-between shrink-0 z-30 select-none">
            <div>
                <div class="p-5 border-b border-slate-800/80 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center text-white font-bold text-lg shadow-md shadow-cyan-500/20">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                    </div>
                    <div>
                        <h1 class="text-white font-bold text-base tracking-tight leading-none">ServerFlow</h1>
                        <span class="text-[10px] font-mono text-slate-400 tracking-wider uppercase">MacBook Pro 2011</span>
                    </div>
                </div>

                <nav class="p-3 space-y-6">
                    <div>
                        <div class="px-3 mb-2 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Monitor</div>
                        <ul class="space-y-1">
                            <li><a href="/index.html" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>Overview</a></li>
                        </ul>
                    </div>
                    <div>
                        <div class="px-3 mb-2 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Tools & Services</div>
                        <ul class="space-y-1">
                            <li><a href="/sites.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>Hosted Sites</a></li>
                            <li><a href="/movies.html" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg>Plex Media</a></li>
                            <li><a href="/access.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>Access Request</a></li>
                            <li><a href="/host.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold bg-cyan-600/20 text-cyan-400 border border-cyan-500/30 transition-all"><svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>Host Node</a></li>
                            <li><a href="/auto_debug.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4 text-pink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>Auto Debug</a></li>
                            <li><a href="/debug.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>Debug Console</a></li>
                            <li><a href="/help.html" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Help & Docs</a></li>
                        </ul>
                    </div>
                </nav>
            </div>
            <div class="p-4 border-t border-slate-800/80 bg-slate-950/40">
                <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Host</div>
                <div class="text-xs font-bold text-white mb-0.5">MacBook Pro 2011</div>
                <div class="flex items-center gap-1.5 text-[11px] text-emerald-400"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>ZeroTier connected</div>
            </div>
        </aside>

        <!-- MAIN CONTAINER -->
        <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">
            <header class="h-16 px-6 border-b border-slate-200 dark:border-slate-800/80 bg-white/70 dark:bg-slate-900/60 backdrop-blur-md sticky top-0 z-20 flex items-center justify-between gap-4">
                <div class="relative w-72">
                    <input type="text" class="w-full pl-9 pr-4 py-2 rounded-xl text-xs bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:outline-none">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <div class="flex items-center gap-3">
                    <div class="p-1 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 flex items-center gap-1">
                        <button id="sf-theme-light-btn" onclick="setServerFlowTheme('light')" class="flex items-center gap-1 px-3 py-1 text-xs font-medium rounded-lg text-slate-400">☀️ Light</button>
                        <button id="sf-theme-dark-btn" onclick="setServerFlowTheme('dark')" class="flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded-lg bg-slate-800 text-cyan-400">🌙 Dark</button>
                    </div>
                </div>
            </header>

            <div class="p-6 max-w-7xl w-full mx-auto space-y-6 flex-1 pb-28">
                <div class="flex items-end justify-between border-b border-slate-200 dark:border-slate-800/80 pb-4">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">MacBook Pro 2011 Node Info</h2>
                    </div>
                </div>

                <div class="p-6 rounded-2xl bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-slate-800 shadow-xl space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Server Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm font-mono">
                        <div class="p-3.5 rounded-xl bg-slate-100 dark:bg-slate-800"><span class="text-slate-400 block text-[10px]">OS / ARCH</span>macOS (MacBook Pro 2011)</div>
                        <div class="p-3.5 rounded-xl bg-slate-100 dark:bg-slate-800"><span class="text-slate-400 block text-[10px]">ZEROTIER IP</span><span class="text-cyan-400 font-bold">10.247.192.231</span></div>
                        <div class="p-3.5 rounded-xl bg-slate-100 dark:bg-slate-800"><span class="text-slate-400 block text-[10px]">WEB SERVER</span><span class="text-emerald-400 font-bold">Apache 2.4 / PHP 8</span></div>
                        <div class="p-3.5 rounded-xl bg-slate-100 dark:bg-slate-800"><span class="text-slate-400 block text-[10px]">DATABASE</span>MySQL / SQLite PDO Engine</div>
                    </div>
                </div>
            </div>

            <footer class="mt-auto px-6 py-4 border-t border-slate-200 dark:border-slate-800/80 text-xs text-slate-500 dark:text-slate-400 flex items-center justify-between">
                <div>ServerFlow · MacBook Pro 2011 · ZeroTier local host</div>
                <div class="live-time-display font-mono">Checked 14:32</div>
            </footer>
        </main>
    </div>

    <!-- CHATBOT -->
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

    <script src="/js/serverflow_core.js"></script>
</body>
</html>
