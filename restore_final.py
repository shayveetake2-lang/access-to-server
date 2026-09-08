import re
import subprocess

result = subprocess.run(['git', 'show', 'HEAD:index.html'], capture_output=True, text=True)
old_content = result.stdout

old_script_match = re.search(r'    <!-- ================= JAVASCRIPT LOGIC ================= -->\n    <script>.*?</script>', old_content, re.DOTALL)
old_script = old_script_match.group(0)

# We want everything starting from "// Dropdown toggle for tools menu" to the end of the script tag
dropdown_start = old_script.find('// Dropdown toggle for tools menu')

if dropdown_start == -1:
    print("Could not find dropdown toggle comment")
    exit(1)

rest_of_script = old_script[dropdown_start:]
rest_of_script = "        " + rest_of_script

# Read current index.html
with open('index.html', 'r', encoding='utf-8') as f:
    current = f.read()

# Current has:
#    <!-- ================= JAVASCRIPT LOGIC ================= -->
#    <script>
#</script>

replacement = "    <!-- ================= JAVASCRIPT LOGIC ================= -->\n    <script>\n" + rest_of_script

current = re.sub(r'    <!-- ================= JAVASCRIPT LOGIC ================= -->\n    <script>\n</script>', replacement, current, flags=re.DOTALL)

with open('index.html', 'w', encoding='utf-8') as f:
    f.write(current)

print("SUCCESS: Restored JS to index.html")
