with open('js/admin_auth.js', 'r', encoding='utf-8') as f:
    content = f.read()

import re

# We will just replace the entire updateAdminUI function with the one from index.html
with open('index.html', 'r', encoding='utf-8') as f:
    index_content = f.read()

js_auth_match = re.search(r'function updateAdminUI\(isLoggedIn, user\) \{.*?(?=\n        })', index_content, re.DOTALL)

if js_auth_match:
    new_func = js_auth_match.group(0) + '\n        }'
    # But wait, index.html inline function does not have `.querySelectorAll` for navBtnTexts
    # Let's write a robust version for js/admin_auth.js

new_js = """function updateAdminUI(isLoggedIn, user) {
    const navBtnTexts = document.querySelectorAll('.admin-nav-text-el');
    const navIconLocks = document.querySelectorAll('.admin-nav-icon-lock-el');
    const navBadges = document.querySelectorAll('.admin-nav-badge-active-el');
    const dropdownTexts = document.querySelectorAll('.admin-dropdown-text-el');

    const adminNavBtn = document.getElementById('admin-nav-btn');
    const adminLoggedInIndicator = document.getElementById('admin-logged-in-indicator');
    const adminLoggedInUsername = document.getElementById('admin-logged-in-username');

    const loginView = document.getElementById('admin-modal-login-view');
    const panelView = document.getElementById('admin-modal-panel-view');
    const userDisplay = document.getElementById('admin-username-display');

    const deployForm = document.getElementById('deploy-form-container');
    const deployLock = document.getElementById('deploy-lock-container');
    const dbForm = document.getElementById('db-form-container');
    const dbLock = document.getElementById('db-lock-container');
    const usbForm = document.getElementById('usb-upload-form');
    const usbLock = document.getElementById('usb-lock-container');
    const navDropdownWrapper = document.getElementById('nav-dropdown-wrapper');

    const adminOnlyTools = document.querySelectorAll('.admin-only-tool');

    if (isLoggedIn) {
        if (adminNavBtn) adminNavBtn.classList.add('hidden');
        if (adminLoggedInIndicator) {
            adminLoggedInIndicator.classList.remove('hidden');
            adminLoggedInIndicator.classList.add('inline-flex');
        }
        if (adminLoggedInUsername) adminLoggedInUsername.innerText = 'Admin: ' + (user || 'admin');

        navBtnTexts.forEach(el => el.innerText = 'Admin Panel');
        navIconLocks.forEach(el => el.classList.add('hidden'));
        navBadges.forEach(el => el.classList.remove('hidden'));
        dropdownTexts.forEach(el => el.innerText = 'Admin Panel (Active)');

        if (loginView) loginView.classList.add('hidden');
        if (panelView) panelView.classList.remove('hidden');
        if (userDisplay) userDisplay.innerText = user || 'admin';
        if (navDropdownWrapper) navDropdownWrapper.classList.remove('hidden');

        // Unlock Admin Action Cards if present on index.html
        if (deployForm) deployForm.classList.remove('hidden');
        if (deployLock) deployLock.classList.add('hidden');
        if (dbForm) dbForm.classList.remove('hidden');
        if (dbLock) dbLock.classList.add('hidden');
        if (usbForm) usbForm.classList.remove('hidden');
        if (usbLock) usbLock.classList.add('hidden');

        // Reveal Admin-Only Tools in dropdown
        adminOnlyTools.forEach(el => el.classList.remove('hidden'));
    } else {
        if (adminNavBtn) adminNavBtn.classList.remove('hidden');
        if (adminLoggedInIndicator) {
            adminLoggedInIndicator.classList.add('hidden');
            adminLoggedInIndicator.classList.remove('inline-flex');
        }

        navBtnTexts.forEach(el => el.innerText = 'Admin Login');
        navIconLocks.forEach(el => el.classList.remove('hidden'));
        navBadges.forEach(el => el.classList.add('hidden'));
        dropdownTexts.forEach(el => el.innerText = 'Admin Login');

        if (loginView) loginView.classList.remove('hidden');
        if (panelView) panelView.classList.add('hidden');
        if (navDropdownWrapper) navDropdownWrapper.classList.add('hidden');

        // Lock Admin Action Cards if present on index.html
        if (deployForm) deployForm.classList.add('hidden');
        if (deployLock) deployLock.classList.remove('hidden');
        if (dbForm) dbForm.classList.add('hidden');
        if (dbLock) dbLock.classList.remove('hidden');
        if (usbForm) usbForm.classList.add('hidden');
        if (usbLock) usbLock.classList.remove('hidden');

        // Hide Admin-Only Tools in dropdown
        adminOnlyTools.forEach(el => el.classList.add('hidden'));
    }
}"""

content = re.sub(r'function updateAdminUI\(isLoggedIn, user\) \{.*?(?=\nfunction openAdminModal)', new_js + '\n', content, flags=re.DOTALL)

with open('js/admin_auth.js', 'w', encoding='utf-8') as f:
    f.write(content)
print("Updated js/admin_auth.js")
