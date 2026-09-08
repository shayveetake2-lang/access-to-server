import re

with open('js/admin_auth.js', 'r', encoding='utf-8') as f:
    content = f.read()

# Fix the IDs used in submitAdminLogin
content = content.replace("document.getElementById('admin-user')", "document.getElementById('admin-username-input')")
content = content.replace("document.getElementById('admin-pass')", "document.getElementById('admin-password-input')")

# The html has <form onsubmit="submitAdminLogin(event);"> but my JS was async function submitAdminLogin()
# Let's check the signature
content = content.replace("async function submitAdminLogin() {", "async function submitAdminLogin(event) { if (event) event.preventDefault();")

with open('js/admin_auth.js', 'w', encoding='utf-8') as f:
    f.write(content)
print("Fixed login logic")
