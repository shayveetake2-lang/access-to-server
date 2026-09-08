import re

with open('js/admin_auth.js', 'r', encoding='utf-8') as f:
    content = f.read()

# We want to remove these blocks from the JS
lines = content.split('\n')
new_lines = []
skip = False
for line in lines:
    if '// Unlock Admin Forms' in line or '// Lock Admin Forms for non-logged-in users' in line:
        pass # We will skip these and the following lines
    
    if any(x in line for x in ['deployForm', 'deployLock', 'dbForm', 'dbLock', 'usbForm', 'usbLock']):
        # If it's the declaration or the class manipulation, skip it
        continue
    
    new_lines.append(line)

with open('js/admin_auth.js', 'w', encoding='utf-8') as f:
    f.write('\n'.join(new_lines))
print("Patched js/admin_auth.js")
