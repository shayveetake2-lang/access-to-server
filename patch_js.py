with open('js/admin_auth.js', 'r', encoding='utf-8') as f:
    content = f.read()

import re

# Insert logout button handling in updateAdminUI
content = content.replace(
    "const adminLoggedInUsername = document.getElementById('admin-logged-in-username');",
    "const adminLoggedInUsername = document.getElementById('admin-logged-in-username');\n    const adminLogoutBtn = document.getElementById('admin-logout-btn');"
)

content = content.replace(
    "adminLoggedInIndicator.classList.add('inline-flex');\n        }",
    "adminLoggedInIndicator.classList.add('inline-flex');\n        }\n        if (adminLogoutBtn) {\n            adminLogoutBtn.classList.remove('hidden');\n            adminLogoutBtn.classList.add('inline-flex');\n        }"
)

content = content.replace(
    "adminLoggedInIndicator.classList.remove('inline-flex');\n        }",
    "adminLoggedInIndicator.classList.remove('inline-flex');\n        }\n        if (adminLogoutBtn) {\n            adminLogoutBtn.classList.add('hidden');\n            adminLogoutBtn.classList.remove('inline-flex');\n        }"
)

# Add theme logic
theme_logic = """
// ================= THEME LOGIC =================
function applyTheme() {
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
}

function toggleTheme() {
    if (document.documentElement.classList.contains('dark')) {
        document.documentElement.classList.remove('dark');
        localStorage.theme = 'light';
    } else {
        document.documentElement.classList.add('dark');
        localStorage.theme = 'dark';
    }
}

// Apply on load
applyTheme();
"""

content += theme_logic

with open('js/admin_auth.js', 'w', encoding='utf-8') as f:
    f.write(content)
print("Updated js/admin_auth.js")
