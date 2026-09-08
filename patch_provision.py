import re

with open('api/system/provision_db.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Remove admin auth check
content = re.sub(r'// Ensure session admin authentication.*?(?=^\$dbName)', '', content, flags=re.DOTALL|re.MULTILINE)

with open('api/system/provision_db.php', 'w', encoding='utf-8') as f:
    f.write(content)
print("Patched provision_db.php")
