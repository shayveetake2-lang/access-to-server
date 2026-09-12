import os
import glob

# The HTML snippet for the active and inactive Plex links:
# In movies.html (active):
plex_active = '<li class="auth-required-nav hidden"><a href="movies.html" onclick="closeMobileSidebar();" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-cyan-600/20 text-cyan-400 border border-cyan-500/30 transition-all"><svg class="w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg>Media Player (Plex)</a></li>'

# In other files (inactive):
plex_inactive = '<li class="auth-required-nav hidden">\n                                <a href="movies.html" onclick="closeMobileSidebar();" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all">\n                                    <svg class="w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg>\n                                    Media Player (Plex)\n                                </a>\n                            </li>'

# Also inline inactive from music.html/movies.html if it exists:
plex_inactive_inline = '<li class="auth-required-nav hidden"><a href="movies.html" onclick="closeMobileSidebar();" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg>Media Player (Plex)</a></li>'

music_inactive = '<li class="auth-required-nav hidden"><a href="music.html" onclick="closeMobileSidebar();" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all"><svg class="w-4 h-4 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>Music Portal (Ampache)</a></li>'

music_inactive_multiline = '''<li class="auth-required-nav hidden">
                                <a href="music.html" onclick="closeMobileSidebar();" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition-all">
                                    <svg class="w-4 h-4 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                                    Music Portal (Ampache)
                                </a>
                            </li>'''

music_active = '<li class="auth-required-nav hidden"><a href="music.html" onclick="closeMobileSidebar();" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-cyan-600/20 text-cyan-400 border border-cyan-500/30 transition-all"><svg class="w-4 h-4 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>Music Portal (Ampache)</a></li>'

for file in glob.glob("*.html") + glob.glob("*.php"):
    with open(file, 'r') as f:
        content = f.read()

    # Make sure we don't duplicate if already added
    if "Music Portal (Ampache)" in content:
        continue
    
    if file == "music.html":
        # First fix Plex active -> inactive in music.html
        content = content.replace(plex_active, plex_inactive_inline)
        content = content.replace(plex_inactive_inline, plex_inactive_inline + "\n                            " + music_active)
    else:
        # In other files, find plex_inactive or plex_inactive_inline or plex_active (if movies.html)
        if file == "movies.html":
            content = content.replace(plex_active, plex_active + "\n                            " + music_inactive)
        elif plex_inactive in content:
            content = content.replace(plex_inactive, plex_inactive + "\n                            " + music_inactive_multiline)
        elif plex_inactive_inline in content:
            content = content.replace(plex_inactive_inline, plex_inactive_inline + "\n                            " + music_inactive)
        else:
            print(f"Could not find Plex link in {file}")

    with open(file, 'w') as f:
        f.write(content)

