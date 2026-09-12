import os

with open('music.html', 'r') as f:
    content = f.read()

# 1. Title
content = content.replace('<title>Media Player (Plex) — ServerFlow</title>', '<title>Music Portal (Ampache) — ServerFlow</title>')

# 2. Main Header
content = content.replace(
    '<h2 class="text-2xl font-bold text-slate-900 dark:text-white">Media Player (Plex)</h2>\n                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Stream movies, TV shows, and video libraries hosted on your server.</p>',
    '<h2 class="text-2xl font-bold text-slate-900 dark:text-white">Music Portal (Ampache)</h2>\n                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Stream your music library hosted on your server.</p>'
)

# 3. Action Buttons
content = content.replace(
    '<a href="#" onclick="event.preventDefault(); window.open(\'http://\' + window.location.hostname + \':32400/web\', \'_blank\');" target="_blank" class="auth-required-btn hidden px-3.5 py-2 rounded-xl bg-purple-600 text-white text-xs font-bold shadow-md hover:bg-purple-500 min-h-[44px] inline-flex items-center focus:ring-2 focus:ring-purple-400 transition-all">Launch Plex Web ↗</a>',
    '<a href="http://10.247.192.231:8888/ampache/public/" target="_blank" class="auth-required-btn hidden px-3.5 py-2 rounded-xl bg-green-600 text-white text-xs font-bold shadow-md hover:bg-green-500 min-h-[44px] inline-flex items-center focus:ring-2 focus:ring-green-400 transition-all">Launch Ampache ↗</a>'
)

content = content.replace(
    '<button type="button" onclick="openAdminModal(event)" class="admin-login-trigger logged-out-prompt px-3.5 py-2 rounded-xl bg-purple-600/20 text-purple-400 border border-purple-500/30 text-xs font-bold shadow-md hover:bg-purple-600/30 min-h-[44px] inline-flex items-center transition-all">🔒 Sign In to Stream</button>',
    '<button type="button" onclick="openAdminModal(event)" class="admin-login-trigger logged-out-prompt px-3.5 py-2 rounded-xl bg-green-600/20 text-green-400 border border-green-500/30 text-xs font-bold shadow-md hover:bg-green-600/30 min-h-[44px] inline-flex items-center transition-all">🔒 Sign In to Stream</button>'
)


# 4. Status Card
content = content.replace(
    '<h3 class="text-sm font-bold text-slate-900 dark:text-white">Plex Media Status</h3>\n                        <span class="px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 text-xs font-semibold font-mono border border-emerald-500/20">● Online (Port 32400)</span>',
    '<h3 class="text-sm font-bold text-slate-900 dark:text-white">Ampache Server Status</h3>\n                        <span class="px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 text-xs font-semibold font-mono border border-emerald-500/20">● Online (Port 8888)</span>'
)

content = content.replace(
    '<p class="text-sm text-slate-400 leading-relaxed">Plex Media Server is actively streaming tunneled through ZeroTier local host. Media library scan completed.</p>',
    '<p class="text-sm text-slate-400 leading-relaxed">Ampache Music Server is actively running. Audio library ready for streaming.</p>'
)

with open('music.html', 'w') as f:
    f.write(content)

