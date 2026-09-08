with open('js/admin_auth.js', 'r', encoding='utf-8') as f:
    content = f.read()

# I will add `const standardUserBanner = document.getElementById('standard-user-banner');` right after standardUserPortal
content = content.replace("const standardUserPortal = document.querySelectorAll('.standard-user-portal');", 
                          "const standardUserPortal = document.querySelectorAll('.standard-user-portal');\n    const standardUserBanner = document.getElementById('standard-user-banner');")

# In `if (isLoggedIn) {`, I will add `if (standardUserBanner) standardUserBanner.classList.add('hidden');`
content = content.replace("standardUserPortal.forEach(el => el.classList.remove('hidden'));", 
                          "standardUserPortal.forEach(el => el.classList.remove('hidden'));\n        if (standardUserBanner) standardUserBanner.classList.add('hidden');")

# In `} else {`, I will add `if (standardUserBanner) standardUserBanner.classList.remove('hidden');`
content = content.replace("standardUserPortal.forEach(el => el.classList.add('hidden'));",
                          "standardUserPortal.forEach(el => el.classList.add('hidden'));\n        if (standardUserBanner) standardUserBanner.classList.remove('hidden');")

with open('js/admin_auth.js', 'w', encoding='utf-8') as f:
    f.write(content)
