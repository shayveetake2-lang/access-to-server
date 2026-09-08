import os
import re

# 1. Read the ideal header from index.html
with open('index.html', 'r', encoding='utf-8') as f:
    index_content = f.read()

# Extract header
header_match = re.search(r'<header class="sticky top-0.*?</header>', index_content, re.DOTALL)
if not header_match:
    print("Could not find header in index.html")
    exit(1)
header_html = header_match.group(0)

# Extract Admin Modal UI so we can propagate it if needed? 
# Wait, the admin modal is only in index.html! 
# We don't need the admin modal in sites.php because you don't manage admins from sites.php, you do it from the dashboard.
# But we DO need the navbar sync across all pages.

# 2. Iterate over all HTML/PHP files and replace their header
files_to_update = ['help.html', 'movies.html', 'access.php', 'auto_debug.php', 'debug.php', 'host.php', 'sites.php']

for file in files_to_update:
    if not os.path.exists(file):
        continue
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Replace header
    content = re.sub(r'<header class="sticky top-0.*?</header>', header_html, content, flags=re.DOTALL)
    
    # Ensure <script src="js/admin_auth.js"></script> is at the bottom
    if '<script src="js/admin_auth.js"></script>' not in content:
        content = re.sub(r'</body>', '    <script src="js/admin_auth.js"></script>\n</body>', content)
        
    # Remove the large inline JS block if it exists (since we now have js/admin_auth.js for everyone)
    # The block started with `// ================= ADMIN AUTHENTICATION LOGIC =================`
    content = re.sub(r'<script>\s*// ================= ADMIN AUTHENTICATION LOGIC =================.*?</script>', '', content, flags=re.DOTALL)
    
    with open(file, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"Updated {file}")

print("Done!")
