import re

with open('index.html', 'r') as f:
    content = f.read()

# Remove CARD 2 and CARD 3
# Regex to match <!-- CARD 2: Traffic --> to <!-- CARD 4: Primary SSD Storage & Network Nodes -->
pattern = re.compile(r'<!-- CARD 2: Traffic -->.*?<!-- CARD 4: Primary SSD Storage & Network Nodes -->', re.DOTALL)
replacement = '<!-- CARD 4: Primary SSD Storage & Network Nodes -->'

content = pattern.sub(replacement, content)

# Find Ampache button and add hidden and id
ampache_old = 'class="p-5 rounded-2xl bg-gradient-to-r from-cyan-500/10 via-slate-900/90 to-blue-950/20'
ampache_new = 'id="ampache-launch-card" class="hidden p-5 rounded-2xl bg-gradient-to-r from-cyan-500/10 via-slate-900/90 to-blue-950/20'

content = content.replace(ampache_old, ampache_new)

with open('index.html', 'w') as f:
    f.write(content)
