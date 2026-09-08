import os
import re

with open('index.html', 'r', encoding='utf-8') as f:
    index_content = f.read()

header_match = re.search(r'<header class="sticky top-0.*?</header>', index_content, re.DOTALL)
header_html = header_match.group(0)

files_to_update = ['help.html', 'movies.html', 'access.php', 'auto_debug.php', 'debug.php', 'host.php', 'sites.php']

for file in files_to_update:
    if not os.path.exists(file):
        continue
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Replace header
    content = re.sub(r'<header class="sticky top-0.*?</header>', header_html, content, flags=re.DOTALL)
    
    # We will remove the inline admin auth logic completely
    content = re.sub(r'// ================= ADMIN AUTHENTICATION LOGIC =================.*?// Auto-run on load', '// Auto-run on load', content, flags=re.DOTALL)
    
    # Wait, the DOMContentLoaded calls checkAdminAuth(), which IS in js/admin_auth.js. So that's perfectly fine to keep!
    
    with open(file, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"Updated {file}")

print("Done!")
