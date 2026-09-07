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
<header class="sticky top-0 z-40 w-full border-b border-slate-800/80 bg-slate-950/75 backdrop-blur-xl transition-all">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
            <div class="h-9 w-9 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-600 flex items-center justify-center shadow-lg shadow-cyan-500/20 ring-1 ring-white/15">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.75 5.1a1.5 1.5 0 011.2-.6h10.1a1.5 1.5 0 011.2.6l2.1 3.45a4.5 4.5 0 01.9 2.7" /></svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="font-bold text-slate-100 tracking-tight text-base sm:text-lg">Server Console</span>
                    <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-800 text-slate-400 border border-slate-700/60 uppercase tracking-wider">MBP 2011 Node</span>
                </div>
                <p class="text-[11px] text-slate-400 font-mono hidden md:block">ZeroTier Tunnel &bull; MAMP Apache &bull; Port 3307</p>
            </div>
            <nav class="hidden md:flex items-center gap-1 ml-4 px-1.5 py-1 rounded-xl bg-slate-900/80 border border-slate-800">
                <a href="index.html" class="px-3 py-1 rounded-lg text-xs font-medium text-slate-400 hover:text-white hover:bg-slate-800/60 transition-colors">Deployer</a>
                <a href="host.php" class="px-3 py-1 rounded-lg text-xs font-semibold bg-slate-800 text-white shadow-sm border border-slate-700/60">Host Website</a>
                <a href="sites.php" class="px-3 py-1 rounded-lg text-xs font-medium text-slate-400 hover:text-white hover:bg-slate-800/60 transition-colors">Hosted Sites</a>
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
