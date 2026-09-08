import re
import subprocess

# Get the original index.html from HEAD
result = subprocess.run(['git', 'show', 'HEAD:index.html'], capture_output=True, text=True)
old_content = result.stdout

# Extract the entire script block from old_content
old_script_match = re.search(r'    <!-- ================= JAVASCRIPT LOGIC ================= -->\n    <script>.*?</script>', old_content, re.DOTALL)
if not old_script_match:
    print("Could not find script block in HEAD:index.html")
    exit(1)
old_script = old_script_match.group(0)

# Now we want to remove ONLY the admin auth block from the old script
# Let's find the start of admin auth
admin_auth_start = old_script.find('// ================= ADMIN AUTHENTICATION LOGIC =================')
# And the end of it (it ends before `// ================= DEPLOYMENT LOGIC =================` or something)
# Let's see what's after admin auth
deploy_logic_start = old_script.find('// ================= DEPLOYMENT LOGIC =================')

if admin_auth_start != -1 and deploy_logic_start != -1:
    cleaned_script = old_script[:admin_auth_start] + old_script[deploy_logic_start:]
else:
    print("Could not find deploy logic start")
    exit(1)

# Now replace the empty script block in current index.html with the cleaned script
with open('index.html', 'r', encoding='utf-8') as f:
    current_content = f.read()

current_content = re.sub(r'    <!-- ================= JAVASCRIPT LOGIC ================= -->\n    <script>\n</script>', cleaned_script, current_content, flags=re.DOTALL)

with open('index.html', 'w', encoding='utf-8') as f:
    f.write(current_content)

print("Restored JS block in index.html")
