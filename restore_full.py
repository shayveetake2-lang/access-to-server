import re
import subprocess

result = subprocess.run(['git', 'show', 'HEAD:index.html'], capture_output=True, text=True)
old_content = result.stdout

old_script_match = re.search(r'    <!-- ================= JAVASCRIPT LOGIC ================= -->\n    <script>.*?</script>', old_content, re.DOTALL)
old_script = old_script_match.group(0)

# We want to remove ONLY the admin auth functions from old_script, since they are in js/admin_auth.js
# But wait, index.html might need some tweaks because we updated the UI for account management in index.html!
# So we just extract the script block, then remove the auth functions carefully, and insert it back.

admin_start = old_script.find('// ================= ADMIN AUTHENTICATION LOGIC =================')
admin_end = old_script.find('// ================= DEPLOYMENT LOGIC =================')

if admin_start != -1 and admin_end != -1:
    cleaned_script = old_script[:admin_start] + old_script[admin_end:]
else:
    print("Could not find delimiters")
    exit(1)

with open('index.html', 'r', encoding='utf-8') as f:
    current = f.read()

current = re.sub(r'    <!-- ================= JAVASCRIPT LOGIC ================= -->\n    <script>\n</script>', cleaned_script, current, flags=re.DOTALL)

with open('index.html', 'w', encoding='utf-8') as f:
    f.write(current)

print("Restored core JS to index.html")
