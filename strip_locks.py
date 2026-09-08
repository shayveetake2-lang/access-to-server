import re

with open('index.html', 'r', encoding='utf-8') as f:
    content = f.read()

# Remove deploy-lock-container
content = re.sub(r'<!-- Locked State Banner \(Shown when not logged in\) -->\s*<div id="deploy-lock-container".*?</div>\s*</div>', '</div>', content, flags=re.DOTALL)

# Remove db-lock-container
content = re.sub(r'<!-- Locked State Banner \(DB\) -->\s*<div id="db-lock-container".*?</div>\s*</div>', '</div>', content, flags=re.DOTALL)

# Remove usb-lock-container
content = re.sub(r'<!-- Locked State Banner \(USB\) -->\s*<div id="usb-lock-container".*?</div>\s*</div>', '</div>', content, flags=re.DOTALL)

# Also let's remove "(Admin Only)" from the HTML comments if present, to be clean
content = content.replace('(Admin Only)', '')

with open('index.html', 'w', encoding='utf-8') as f:
    f.write(content)
print("Stripped locks from index.html")
