<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Host Website — Server Console</title>
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
    <div class="space-y-6">
        <div class="bg-blue-500/5 border border-blue-500/20 rounded-xl p-4 text-sm text-slate-400">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
                <div class="space-y-2">
                    <p class="font-semibold text-slate-300">House Rules</p>
                    <ul class="list-disc pl-4 space-y-1">
                        <li><strong>Rule 1:</strong> Give your GitHub repository a unique name so you do not overwrite another person's folder.</li>
                        <li><strong>Rule 2:</strong> Always use relative paths for images and CSS (e.g., <code>images/pic.jpg</code>).</li>
                        <li><strong>Rule 3:</strong> Downtime is normal! If the 2011 Mac sleeps or the tunnel drops, the site will temporarily go offline.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div id="success-message" class="hidden bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-6 text-center space-y-4">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-400 mb-2">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
            </div>
            <h3 class="text-xl font-semibold text-emerald-400">Deployment Successful!</h3>
            <p id="success-text" class="text-slate-300"></p>
            <div class="flex items-center justify-center gap-4 pt-2">
                <a href="#" id="live-link" target="_blank" class="px-5 py-2.5 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 font-medium transition-colors border border-emerald-500/30">Visit Live Site</a>
                <button onclick="location.reload()" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium transition-colors border border-slate-700">Deploy Another</button>
            </div>
        </div>

        <div class="rounded-2xl bg-slate-900/60 border border-slate-800/90 shadow-xl overflow-hidden relative">
            <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-blue-500 via-cyan-500 to-indigo-500"></div>
            <div class="p-6 sm:p-8">
                <div class="mb-8">
                    <h2 class="text-xl font-bold text-white mb-1">Host Website</h2>
                    <p class="text-slate-400 text-sm">Deploy a website from GitHub or upload a ZIP archive</p>
                </div>
                
                <form id="deploy-form" class="space-y-6">
                    <div class="space-y-2">
                        <label for="project_name" class="block text-sm font-medium text-slate-300">Project Name</label>
                        <input type="text" id="project_name" name="project_name" required placeholder="e.g. my-app" pattern="[a-zA-Z0-9-_]+" title="Only letters, numbers, dashes, and underscores allowed" class="bg-surface-terminal border border-slate-700 rounded-xl px-4 py-3 text-sm font-mono text-slate-200 placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500/30 w-full outline-none transition">
                    </div>

                    <div class="space-y-4">
                        <div class="flex p-1 bg-slate-800/50 rounded-xl border border-slate-700/50 w-full md:w-max gap-1">
                            <button type="button" onclick="switchTab('github')" id="tab-github" class="flex-1 px-4 py-2 rounded-lg text-sm transition-all px-3 py-1 rounded-lg text-xs font-semibold bg-slate-800 text-white shadow-sm border border-slate-700/60">GitHub Repo</button>
                            <button type="button" onclick="switchTab('zip')" id="tab-zip" class="flex-1 px-4 py-2 rounded-lg text-sm transition-all px-3 py-1 rounded-lg text-xs font-medium text-slate-400 hover:text-white hover:bg-slate-800/60 transition-colors">Upload .zip</button>
                        </div>

                        <input type="hidden" id="deploy_method" name="deploy_method" value="github">

                        <div id="github-content" class="block space-y-2">
                            <label for="github_url" class="block text-sm font-medium text-slate-300">Repository URL</label>
                            <input type="url" id="github_url" name="github_url" placeholder="https://github.com/user/repo.git" class="bg-surface-terminal border border-slate-700 rounded-xl px-4 py-3 text-sm font-mono text-slate-200 placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500/30 w-full outline-none transition">
                        </div>

                        <div id="zip-content" class="hidden space-y-2">
                            <label class="block text-sm font-medium text-slate-300">ZIP Archive</label>
                            <div class="relative group cursor-pointer border-2 border-dashed border-slate-700 rounded-xl p-8 text-center hover:border-cyan-500/50 hover:bg-cyan-500/5 transition">
                                <span id="file-name" class="text-sm text-slate-400 group-hover:text-slate-300">Click or drag a .zip file here</span>
                                <input type="file" id="zip_file" name="zip_file" accept=".zip" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                            </div>
                        </div>
                    </div>

                    <button type="submit" id="submit-btn" class="w-full py-3 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-semibold text-sm hover:from-cyan-400 hover:to-blue-500 transition-all shadow-lg shadow-cyan-500/20">Deploy Now</button>
                </form>
            </div>
        </div>
    </div>
</main>
<script>
    function switchTab(method) {
        const githubBtn = document.getElementById('tab-github');
        const zipBtn = document.getElementById('tab-zip');
        
        const activeClasses = ['font-semibold', 'bg-slate-800', 'text-white', 'shadow-sm', 'border', 'border-slate-700/60'];
        const inactiveClasses = ['font-medium', 'text-slate-400', 'hover:text-white', 'hover:bg-slate-800/60', 'border-transparent'];

        if (method === 'github') {
            githubBtn.classList.remove(...inactiveClasses);
            githubBtn.classList.add(...activeClasses);
            zipBtn.classList.remove(...activeClasses);
            zipBtn.classList.add(...inactiveClasses);
            
            document.getElementById('github-content').classList.remove('hidden');
            document.getElementById('github-content').classList.add('block');
            document.getElementById('zip-content').classList.remove('block');
            document.getElementById('zip-content').classList.add('hidden');
            
            document.getElementById('github_url').required = true;
            document.getElementById('zip_file').required = false;
        } else {
            zipBtn.classList.remove(...inactiveClasses);
            zipBtn.classList.add(...activeClasses);
            githubBtn.classList.remove(...activeClasses);
            githubBtn.classList.add(...inactiveClasses);
            
            document.getElementById('zip-content').classList.remove('hidden');
            document.getElementById('zip-content').classList.add('block');
            document.getElementById('github-content').classList.remove('block');
            document.getElementById('github-content').classList.add('hidden');
            
            document.getElementById('github_url').required = false;
            document.getElementById('zip_file').required = true;
        }
        
        document.getElementById('deploy_method').value = method;
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('github_url').required = true;
    });

    document.getElementById('zip_file').addEventListener('change', function(e) {
        if (this.files[0]) {
            document.getElementById('file-name').innerText = this.files[0].name;
        }
    });

    document.getElementById('deploy-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('submit-btn');
        btn.innerText = 'Deploying...';
        btn.disabled = true;
        btn.classList.add('opacity-75', 'cursor-not-allowed');

        const formData = new FormData(this);

        fetch('api/process_deployment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('deploy-form').closest('.rounded-2xl').style.display = 'none';
                document.getElementById('success-message').classList.remove('hidden');
                document.getElementById('success-message').classList.add('block');
                document.getElementById('success-text').innerText = data.message;
                document.getElementById('live-link').href = data.url;
            } else {
                alert('Error: ' + data.message);
                btn.innerText = 'Deploy Now';
                btn.disabled = false;
                btn.classList.remove('opacity-75', 'cursor-not-allowed');
            }
        })
        .catch(error => {
            alert('Deployment failed. Please check the network connection and server logs.');
            console.error('Error:', error);
            btn.innerText = 'Deploy Now';
            btn.disabled = false;
            btn.classList.remove('opacity-75', 'cursor-not-allowed');
        });
    });
</script>
<script src="js/admin_auth.js"></script>

<!-- Admin Auth & Control Panel Modal -->
<div id="admin-auth-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md hidden items-center justify-center p-4">
    <div class="relative w-full max-w-md rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl p-6 overflow-hidden animate-in fade-in zoom-in duration-150">
        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 via-blue-500 to-indigo-500"></div>

        <button onclick="closeAdminModal()" class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors cursor-pointer">
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
                    <h3 class="text-base font-bold text-slate-100">Admin Authentication</h3>
                    <p class="text-xs text-slate-400">Database-backed access control for access_db</p>
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
                <p>Reads and writes credentials directly to <code>access_db.admin_users</code> table using BCrypt hashes.</p>
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
        // ================= ADMIN AUTHENTICATION LOGIC =================
        let currentAdminState = { logged_in: false, user: null };

        async function checkAdminAuth() {
            try {
                const res = await fetch('api/system/admin_auth.php?action=status');
                const data = await res.json();
                if (data.status === 'success') {
                    currentAdminState.logged_in = data.logged_in;
                    currentAdminState.user = data.user;
                    updateAdminUI(data.logged_in, data.user);
                }
            } catch (err) {
                console.error('Failed to check admin auth status', err);
            }
        }

        function updateAdminUI(isLoggedIn, user) {
            const navBtnText = document.getElementById('admin-nav-text');
            const navIconLock = document.getElementById('admin-nav-icon-lock');
            const navBadgeActive = document.getElementById('admin-nav-badge-active');
            const dropdownText = document.getElementById('admin-dropdown-text');
            
            const adminNavBtn = document.getElementById('admin-nav-btn');
            const adminLoggedInIndicator = document.getElementById('admin-logged-in-indicator');
            const adminLoggedInUsername = document.getElementById('admin-logged-in-username');

            const loginView = document.getElementById('admin-modal-login-view');
            const panelView = document.getElementById('admin-modal-panel-view');
            const userDisplay = document.getElementById('admin-username-display');

            const deployForm = document.getElementById('deploy-form-container');
            const deployLock = document.getElementById('deploy-lock-container');
            const dbForm = document.getElementById('db-form-container');
            const dbLock = document.getElementById('db-lock-container');
            const usbForm = document.getElementById('usb-upload-form');
            const usbLock = document.getElementById('usb-lock-container');
            const navDropdownWrapper = document.getElementById('nav-dropdown-wrapper');

            if (isLoggedIn) {
                if (adminNavBtn) adminNavBtn.classList.add('hidden');
                if (adminLoggedInIndicator) {
                    adminLoggedInIndicator.classList.remove('hidden');
                    adminLoggedInIndicator.classList.add('inline-flex');
                }
                if (adminLoggedInUsername) adminLoggedInUsername.innerText = 'Admin: ' + (user || 'admin');

                if (loginView) loginView.classList.add('hidden');
                if (panelView) panelView.classList.remove('hidden');
                if (userDisplay) userDisplay.innerText = user || 'admin';
                if (navDropdownWrapper) navDropdownWrapper.classList.remove('hidden');

                // Unlock Admin Forms
                if (deployForm) deployForm.classList.remove('hidden');
                if (deployLock) deployLock.classList.add('hidden');
                if (dbForm) dbForm.classList.remove('hidden');
                if (dbLock) dbLock.classList.add('hidden');
                if (usbForm) usbForm.classList.remove('hidden');
                if (usbLock) usbLock.classList.add('hidden');
            } else {
                if (adminNavBtn) adminNavBtn.classList.remove('hidden');
                if (adminLoggedInIndicator) {
                    adminLoggedInIndicator.classList.add('hidden');
                    adminLoggedInIndicator.classList.remove('inline-flex');
                }

                if (loginView) loginView.classList.remove('hidden');
                if (panelView) panelView.classList.add('hidden');
                if (navDropdownWrapper) navDropdownWrapper.classList.add('hidden');

                // Lock Admin Forms for non-logged-in users
                if (deployForm) deployForm.classList.add('hidden');
                if (deployLock) deployLock.classList.remove('hidden');
                if (dbForm) dbForm.classList.add('hidden');
                if (dbLock) dbLock.classList.remove('hidden');
                if (usbForm) usbForm.classList.add('hidden');
                if (usbLock) usbLock.classList.remove('hidden');
            }
        }

        // Auto-run on load
        document.addEventListener('DOMContentLoaded', () => {
            checkAdminAuth();
        });
    </script>
</body>
</html>
