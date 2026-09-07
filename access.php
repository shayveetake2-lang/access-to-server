<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Access Request — Server Console</title>
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
                <a href="access.php" class="px-3 py-1 rounded-lg text-xs font-semibold bg-slate-800 text-white shadow-sm border border-slate-700/60">Access Request</a>
            </nav>
        </div>
        <div class="flex items-center gap-2.5 sm:gap-3">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-950/60 border border-emerald-500/30 text-emerald-400 text-xs font-medium shadow-sm shadow-emerald-900/20">
                <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span></span>
                <span class="tracking-wide text-emerald-300 font-mono text-[11px] font-semibold">System Online</span>
            </div>
            <a href="sites.php" class="group relative inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 hover:border-cyan-400/50 text-cyan-300 hover:text-cyan-200 text-xs font-semibold transition-all duration-200 shadow-sm shadow-cyan-950/20">
                <svg class="w-4 h-4 text-cyan-400 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                <span>Hosted Sites</span>
            </a>
            <a href="movies.html" class="group relative inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 hover:border-amber-400/50 text-amber-300 hover:text-amber-200 text-xs font-semibold transition-all duration-200 shadow-sm shadow-amber-950/20">
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
<main class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
    <div class="rounded-2xl bg-slate-900/60 border border-slate-800/90 shadow-xl overflow-hidden relative">
        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
        <div class="p-6 sm:p-8">
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-white">Server Access</h2>
                <p class="text-sm text-slate-400 mt-1">Submit a request to connect to the server</p>
            </div>
            
            <div id="success-message" class="hidden py-8 text-center space-y-4">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-500/10 mb-2">
                    <svg class="w-8 h-8 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <h3 class="text-xl font-bold text-emerald-400">Transmission Received</h3>
                <p id="success-text" class="text-sm text-slate-300"></p>
                <div class="pt-4">
                    <a href="access.php" class="inline-flex items-center gap-2 text-sm text-indigo-400 hover:text-indigo-300 font-medium">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                        Return to Terminal
                    </a>
                </div>
            </div>
            
            <form id="access-form" class="space-y-4">
                <div>
                    <label for="name" class="block text-xs font-medium text-slate-400 mb-1.5">Name / Callsign</label>
                    <input type="text" id="name" name="name" required placeholder="Enter your identifier..." class="bg-surface-terminal border border-slate-700 rounded-xl px-4 py-3 text-sm font-mono text-slate-200 placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500/30 w-full outline-none transition">
                </div>
                <div>
                    <label for="contact" class="block text-xs font-medium text-slate-400 mb-1.5">Contact Vector</label>
                    <input type="text" id="contact" name="contact" required placeholder="Email or comms channel..." class="bg-surface-terminal border border-slate-700 rounded-xl px-4 py-3 text-sm font-mono text-slate-200 placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500/30 w-full outline-none transition">
                </div>
                <div>
                    <label for="message" class="block text-xs font-medium text-slate-400 mb-1.5">Transmission Payload</label>
                    <textarea id="message" name="message" rows="4" required placeholder="State your purpose..." class="bg-surface-terminal border border-slate-700 rounded-xl px-4 py-3 text-sm font-mono text-slate-200 placeholder-slate-500 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500/30 w-full outline-none transition"></textarea>
                </div>
                <button type="submit" id="submit-btn" class="w-full py-3 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold text-sm hover:from-indigo-400 hover:to-purple-500 transition-all shadow-lg shadow-indigo-500/20 mt-2">
                    Initialize Sequence
                </button>
            </form>
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

    document.getElementById('access-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('submit-btn');
        const originalText = btn.innerHTML;
        btn.innerHTML = 'Transmitting...';
        btn.disabled = true;

        const data = {
            name: document.getElementById('name').value,
            contact: document.getElementById('contact').value,
            message: document.getElementById('message').value
        };

        fetch('insert.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.text())
        .then(result => {
            if (result.includes("successfully")) {
                document.getElementById('access-form').classList.add('hidden');
                document.getElementById('success-message').classList.remove('hidden');
                document.getElementById('success-text').textContent = 'Welcome to the server, ' + data.name;
            } else {
                alert('Error: ' + result);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Transmission failed...');
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    });
</script>
</body>
</html>
