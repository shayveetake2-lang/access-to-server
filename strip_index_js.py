import re

with open('index.html', 'r', encoding='utf-8') as f:
    content = f.read()

content = re.sub(r'\s*// ================= ADMIN AUTHENTICATION LOGIC =================.*?(?=// ================= END ADMIN AUTHENTICATION LOGIC =================|</script>)', '\n', content, flags=re.DOTALL)

with open('index.html', 'w', encoding='utf-8') as f:
    f.write(content)
print("Stripped inline JS from index.html")
