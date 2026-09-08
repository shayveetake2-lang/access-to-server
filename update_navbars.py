import os
import re

# 1. Read the ideal header and JS from index.html
with open('index.html', 'r', encoding='utf-8') as f:
    index_content = f.read()

# Extract header
header_match = re.search(r'<header class="sticky top-0.*?</header>', index_content, re.DOTALL)
if not header_match:
    print("Could not find header in index.html")
    exit(1)
header_html = header_match.group(0)

# Extract JS auth logic
# We need to find checkAdminAuth() and updateAdminUI() and the state variables
js_auth_match = re.search(r'// ================= ADMIN AUTHENTICATION LOGIC =================.*?function updateAdminUI.*?}\n\s*}', index_content, re.DOTALL)
if not js_auth_match:
    print("Could not find JS logic in index.html")
    exit(1)
js_auth_logic = js_auth_match.group(0)

print("Found header and JS logic.")

# 2. Iterate over all HTML/PHP files and replace their header and JS
files_to_update = ['help.html', 'movies.html', 'access.php', 'auto_debug.php', 'debug.php', 'host.php', 'sites.php']

for file in files_to_update:
    if not os.path.exists(file):
        continue
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Replace header
    content = re.sub(r'<header class="sticky top-0.*?</header>', header_html, content, flags=re.DOTALL)
    
    # Inject JS if not present
    if 'checkAdminAuth' not in content:
        # insert before </head> or </body>? The script in index.html is at the end.
        # let's inject before </body>
        script_tag = f"""
    <script>
        {js_auth_logic}

        // Auto-run on load
        document.addEventListener('DOMContentLoaded', () => {{
            checkAdminAuth();
        }});
    </script>
"""
        content = re.sub(r'</body>', f'{script_tag}</body>', content)
    else:
        # It's already there (maybe), let's replace it
        content = re.sub(r'// ================= ADMIN AUTHENTICATION LOGIC =================.*?function updateAdminUI.*?}\n\s*}', js_auth_logic, content, flags=re.DOTALL)
    
    with open(file, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"Updated {file}")

print("Done!")
