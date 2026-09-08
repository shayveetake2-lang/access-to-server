import re

with open('api/system/deploy.php', 'r', encoding='utf-8') as f:
    content = f.read()

# We can just remove the if (!$isSessionAdmin && !$isValidPin) block
content = re.sub(r'if \(!\$isSessionAdmin && !\$isValidPin\) \{.*?\}', '', content, flags=re.DOTALL)

with open('api/system/deploy.php', 'w', encoding='utf-8') as f:
    f.write(content)
print("Patched deploy.php")
