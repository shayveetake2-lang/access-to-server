import re

with open('js/admin_auth.js', 'r', encoding='utf-8') as f:
    content = f.read()

# Replace checkAdminAuth
new_check = """async function checkAdminAuth() {
    const token = localStorage.getItem('auth_token');
    const role = localStorage.getItem('auth_role');
    const user = localStorage.getItem('auth_user');

    if (token && role && user) {
        currentAdminState.logged_in = true;
        currentAdminState.user = user;
        updateAdminUI(true, user, role);
    } else {
        currentAdminState.logged_in = false;
        currentAdminState.user = null;
        updateAdminUI(false, null, null);
    }
}
"""
content = re.sub(r'async function checkAdminAuth\(\) \{.*?\n\}\n', new_check, content, flags=re.DOTALL)

# Modify updateAdminUI signature and logic
new_updateUI = """function updateAdminUI(isLoggedIn, user, role) {
    const navBtnTexts = document.querySelectorAll('.admin-nav-text-el');
    const navIconLocks = document.querySelectorAll('.admin-nav-icon-lock-el');
    const navBadges = document.querySelectorAll('.admin-nav-badge-active-el');
    const dropdownTexts = document.querySelectorAll('.admin-dropdown-text-el');

    const adminNavBtn = document.getElementById('admin-nav-btn');
    const adminLoggedInIndicator = document.getElementById('admin-logged-in-indicator');
    const adminLoggedInUsername = document.getElementById('admin-logged-in-username');
    const adminLogoutBtn = document.getElementById('admin-logout-btn');

    const loginView = document.getElementById('admin-modal-login-view');
    const panelView = document.getElementById('admin-modal-panel-view');
    const userDisplay = document.getElementById('admin-username-display');

    const standardUserPortal = document.getElementById('standard-user-portal');
    const adminPortal = document.getElementById('admin-portal');
    const navDropdownWrapper = document.getElementById('nav-dropdown-wrapper');
    const manageAdminsBtn = document.getElementById('manage-admins-btn');

    if (isLoggedIn) {
        if (loginView) loginView.classList.add('hidden');
        
        if (standardUserPortal) standardUserPortal.classList.remove('hidden');

        if (role === 'admin') {
            if (adminPortal) adminPortal.classList.remove('hidden');
            if (navDropdownWrapper) navDropdownWrapper.classList.remove('hidden');
            if (manageAdminsBtn) manageAdminsBtn.classList.remove('hidden');
        } else {
            if (adminPortal) adminPortal.classList.add('hidden');
            if (navDropdownWrapper) navDropdownWrapper.classList.add('hidden');
            if (manageAdminsBtn) manageAdminsBtn.classList.add('hidden');
        }
        
        if (adminNavBtn) adminNavBtn.classList.add('hidden');
        if (adminLoggedInIndicator) adminLoggedInIndicator.classList.remove('hidden');
        if (adminLoggedInIndicator) adminLoggedInIndicator.classList.add('flex');
        if (adminLoggedInUsername) adminLoggedInUsername.innerText = user;
        if (adminLogoutBtn) adminLogoutBtn.classList.remove('hidden');

    } else {
        if (loginView) loginView.classList.remove('hidden');
        
        if (standardUserPortal) standardUserPortal.classList.add('hidden');
        if (adminPortal) adminPortal.classList.add('hidden');
        if (navDropdownWrapper) navDropdownWrapper.classList.add('hidden');
        if (manageAdminsBtn) manageAdminsBtn.classList.add('hidden');

        if (adminNavBtn) adminNavBtn.classList.remove('hidden');
        if (adminLoggedInIndicator) adminLoggedInIndicator.classList.add('hidden');
        if (adminLoggedInIndicator) adminLoggedInIndicator.classList.remove('flex');
        if (adminLogoutBtn) adminLogoutBtn.classList.add('hidden');
        
        navBtnTexts.forEach(el => el.innerText = 'Login');
        dropdownTexts.forEach(el => el.innerText = 'Login');
        navIconLocks.forEach(el => el.classList.remove('hidden'));
        navBadges.forEach(el => el.classList.add('hidden'));
    }
}"""
content = re.sub(r'function updateAdminUI\(isLoggedIn, user\) \{.*?\n\}\n', new_updateUI, content, flags=re.DOTALL)

# Modify submitAdminLogin to use api/auth/login.php
new_login = """async function submitAdminLogin() {
    const errBanner = document.getElementById('admin-login-error');
    const submitBtn = document.getElementById('admin-login-btn');
    const username = document.getElementById('admin-user').value.trim();
    const pass = document.getElementById('admin-pass').value.trim();

    if (!username || !pass) {
        errBanner.innerText = 'Please enter both username and password.';
        errBanner.classList.remove('hidden');
        return;
    }

    errBanner.classList.add('hidden');
    submitBtn.innerText = 'Verifying...';
    submitBtn.disabled = true;

    try {
        const res = await fetch('api/auth/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: username, password: pass })
        });
        
        const data = await res.json();
        
        if (data.status === 'success') {
            localStorage.setItem('auth_token', data.token);
            localStorage.setItem('auth_role', data.role);
            localStorage.setItem('auth_user', username);
            
            checkAdminAuth();
            
            setTimeout(() => { closeAdminModal(); }, 1500);
            
            submitBtn.innerText = 'Authentication Successful';
            submitBtn.classList.replace('bg-indigo-600', 'bg-emerald-600');
            submitBtn.classList.replace('hover:bg-indigo-500', 'hover:bg-emerald-500');
        } else {
            errBanner.innerText = data.message || 'Login failed.';
            errBanner.classList.remove('hidden');
            submitBtn.innerText = 'Access System';
            submitBtn.disabled = false;
        }
    } catch (err) {
        errBanner.innerText = 'Network error. Please try again.';
        errBanner.classList.remove('hidden');
        submitBtn.innerText = 'Access System';
        submitBtn.disabled = false;
    }
}
"""
content = re.sub(r'async function submitAdminLogin\(\) \{.*?\n\}\n', new_login, content, flags=re.DOTALL)


new_logout = """async function submitLogout() {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('auth_role');
    localStorage.removeItem('auth_user');
    
    // hit the legacy API just to clear PHP session if needed
    try { await fetch('api/system/admin_auth.php?action=logout'); } catch(e){}
    
    checkAdminAuth();
}"""
content = re.sub(r'async function submitLogout\(\) \{.*?\n\}\n', new_logout, content, flags=re.DOTALL)

with open('js/admin_auth.js', 'w', encoding='utf-8') as f:
    f.write(content)
print("js/admin_auth.js patched")
