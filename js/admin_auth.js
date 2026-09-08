// js/admin_auth.js — Centralized Admin Authentication & Dynamic Tools Menu Manager

let currentAdminState = { logged_in: false, user: null };

async function checkAdminAuth() {
    // User requested no auto-login on page load/refresh
    currentAdminState.logged_in = false;
    currentAdminState.user = null;
    updateAdminUI(false, null, null);
}

function updateAdminUI(isLoggedIn, user, role) {
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

    const standardUserPortal = document.querySelectorAll('.standard-user-portal');
    const standardUserBanner = document.getElementById('standard-user-banner');
    const adminPortal = document.getElementById('admin-portal');
    const navDropdownWrapper = document.getElementById('nav-dropdown-wrapper');
    const manageAdminsBtn = document.getElementById('manage-admins-btn');

    if (isLoggedIn) {
        if (loginView) loginView.classList.add('hidden');
        
        standardUserPortal.forEach(el => el.classList.remove('hidden'));
        if (standardUserBanner) standardUserBanner.classList.add('hidden');

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
        
        standardUserPortal.forEach(el => el.classList.add('hidden'));
        if (standardUserBanner) standardUserBanner.classList.remove('hidden');
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
}
function openAdminModal() {
    // Clear input fields
    clearAuthInputs();
    
    // Reset view to login
    toggleAuthView('login');
    
    // If already logged in, this button acts as a logout
    if (currentAdminState.logged_in) {
        submitAdminLogout();
        return;
    }
    
    const modal = document.getElementById('admin-auth-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function clearAuthInputs() {
    const adminUser = document.getElementById('admin-username-input');
    const adminPass = document.getElementById('admin-password-input');
    const regUser = document.getElementById('register-username-input');
    const regPass = document.getElementById('register-password-input');
    
    if (adminUser) adminUser.value = '';
    if (adminPass) adminPass.value = '';
    if (regUser) regUser.value = '';
    if (regPass) regPass.value = '';
}

function closeAdminModal() {
    const modal = document.getElementById('admin-auth-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

async function submitAdminLogin(event) {
    if (event) event.preventDefault();
    const errBanner = document.getElementById('admin-login-error');
    const successBanner = document.getElementById('admin-login-success');
    const submitBtn = document.getElementById('admin-login-btn');
    const username = document.getElementById('admin-username-input').value.trim();
    const pass = document.getElementById('admin-password-input').value.trim();

    if (!username || !pass) {
        if (errBanner) {
            errBanner.innerText = 'Please enter both username and password.';
            errBanner.classList.remove('hidden');
        }
        if (successBanner) successBanner.classList.add('hidden');
        return;
    }

    if (errBanner) errBanner.classList.add('hidden');
    if (successBanner) successBanner.classList.add('hidden');
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
            currentAdminState.logged_in = true;
            currentAdminState.user = username;
            
            if (data.token) {
                localStorage.setItem('auth_token', data.token);
            }
            
            if (successBanner) {
                successBanner.innerText = `✓ Login successful! Welcome back, ${username}.`;
                successBanner.classList.remove('hidden');
            }
            
            submitBtn.innerText = 'Access Granted!';
            updateAdminUI(true, username, data.role);
            
            setTimeout(() => {
                closeAdminModal();
                submitBtn.innerText = 'Access System';
                submitBtn.disabled = false;
                if (successBanner) successBanner.classList.add('hidden');
            }, 1200);
        } else {
            if (errBanner) {
                errBanner.innerText = data.message || 'Invalid username or password.';
                errBanner.classList.remove('hidden');
            }
            submitBtn.innerText = 'Access System';
            submitBtn.disabled = false;
        }
    } catch (err) {
        if (errBanner) {
            errBanner.innerText = 'Network error. Please try again.';
            errBanner.classList.remove('hidden');
        }
        submitBtn.innerText = 'Access System';
        submitBtn.disabled = false;
    }
}

async function submitAdminLogout() {
    try {
        await fetch('api/system/admin_auth.php?action=logout', { method: 'POST' });
        currentAdminState.logged_in = false;
        currentAdminState.user = null;
        updateAdminUI(false, null);
        closeAdminModal();

        const output = document.getElementById('output');
        if (output) {
            output.innerHTML += `<span class="text-amber-400 font-semibold">[AUTH] Admin session terminated. Controls locked.</span><br>`;
            output.parentElement.scrollTop = output.parentElement.scrollHeight;
        }
    } catch (err) {
        console.error('Logout error:', err);
    }
}

function toggleNavMenu() {
    const dropdown = document.getElementById('nav-dropdown');
    if (dropdown) dropdown.classList.toggle('hidden');
}

function openNodeModal() {
    const modal = document.getElementById('node-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeNodeModal() {
    const modal = document.getElementById('node-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Global Event Listeners
document.addEventListener('click', (e) => {
    const wrapper = document.getElementById('nav-dropdown-wrapper');
    const dropdown = document.getElementById('nav-dropdown');
    if (wrapper && !wrapper.contains(e.target) && dropdown && !dropdown.classList.contains('hidden')) {
        dropdown.classList.add('hidden');
    }

    const adminModal = document.getElementById('admin-auth-modal');
    if (adminModal && !adminModal.classList.contains('hidden') && e.target === adminModal) {
        closeAdminModal();
    }
    const nodeModal = document.getElementById('node-modal');
    if (nodeModal && !nodeModal.classList.contains('hidden') && e.target === nodeModal) {
        closeNodeModal();
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeNodeModal();
        closeAdminModal();
        if (typeof closePopup === 'function') closePopup();
        if (typeof closeDbPopup === 'function') closeDbPopup();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    checkAdminAuth();
});
// ================= THEME LOGIC =================
function applyTheme() {
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
}

function toggleTheme() {
    const html = document.documentElement;
    if (html.classList.contains('dark')) {
        html.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    } else {
        html.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    }
}

function applyTheme() {
    const saved = localStorage.getItem('theme');
    if (saved === 'light') {
        document.documentElement.classList.remove('dark');
    } else {
        document.documentElement.classList.add('dark');
    }
}
applyTheme();

// Apply on load
applyTheme();


// ================= ADMIN ACCOUNT MANAGEMENT =================
function showManageAdminsView() {
    document.getElementById('admin-modal-panel-view').classList.add('hidden');
    document.getElementById('admin-modal-manage-view').classList.remove('hidden');
    loadAdminsList();
}

function showAdminPanelView() {
    document.getElementById('admin-modal-manage-view').classList.add('hidden');
    document.getElementById('admin-modal-panel-view').classList.remove('hidden');
    const msg = document.getElementById('manage-admin-msg');
    if (msg) msg.classList.add('hidden');
}

async function loadAdminsList() {
    const listEl = document.getElementById('admin-accounts-list');
    listEl.innerHTML = '<div class="p-3 text-center text-xs text-slate-500">Loading...</div>';
    
    try {
        const res = await fetch('api/system/admin_accounts.php?action=list');
        const data = await res.json();
        if (res.ok && data.status === 'success') {
            listEl.innerHTML = '';
            data.admins.forEach(admin => {
                const isSelf = (admin.username === currentAdminState.user);
                listEl.innerHTML += `
                    <div class="flex items-center justify-between p-2 hover:bg-slate-800/40 transition-colors">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-xs text-slate-200 font-medium truncate">${admin.username}</span>
                            ${isSelf ? '<span class="px-1.5 py-0.5 rounded text-[9px] bg-cyan-900/50 text-cyan-400">You</span>' : ''}
                        </div>
                        <button type="button" onclick="deleteAdmin(${admin.id}, '${admin.username}')" class="text-rose-400 hover:text-rose-300 p-1 cursor-pointer" ${isSelf ? 'disabled style="opacity:0.3; cursor:not-allowed;"' : 'title="Delete Account"'}>
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </button>
                    </div>
                `;
            });
        } else {
            listEl.innerHTML = `<div class="p-3 text-center text-xs text-rose-400">Error loading admins.</div>`;
        }
    } catch (err) {
        listEl.innerHTML = `<div class="p-3 text-center text-xs text-rose-400">Network error.</div>`;
    }
}

async function submitAddAdmin() {
    const userEl = document.getElementById('new-admin-user');
    const passEl = document.getElementById('new-admin-pass');
    const msg = document.getElementById('manage-admin-msg');
    
    if (!userEl.value || !passEl.value) {
        msg.className = 'mt-2 p-2 rounded-lg text-[11px] font-mono font-medium bg-rose-950/60 text-rose-400 border border-rose-500/40';
        msg.innerText = 'Username and password required.';
        msg.classList.remove('hidden');
        return;
    }
    
    try {
        const res = await fetch('api/system/admin_accounts.php?action=add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: userEl.value, password: passEl.value })
        });
        const result = await res.json();
        
        if (res.ok && result.status === 'success') {
            msg.className = 'mt-2 p-2 rounded-lg text-[11px] font-mono font-medium bg-emerald-950/60 text-emerald-400 border border-emerald-500/40';
            msg.innerText = 'Admin account created successfully!';
            msg.classList.remove('hidden');
            userEl.value = '';
            passEl.value = '';
            loadAdminsList();
        } else {
            msg.className = 'mt-2 p-2 rounded-lg text-[11px] font-mono font-medium bg-rose-950/60 text-rose-400 border border-rose-500/40';
            msg.innerText = result.message || 'Error creating account.';
            msg.classList.remove('hidden');
        }
    } catch (err) {
        msg.className = 'mt-2 p-2 rounded-lg text-[11px] font-mono font-medium bg-rose-950/60 text-rose-400 border border-rose-500/40';
        msg.innerText = 'Network error.';
        msg.classList.remove('hidden');
    }
}

async function deleteAdmin(id, username) {
    if (!confirm(`Are you sure you want to permanently delete the admin account '${username}'?`)) return;
    
    const msg = document.getElementById('manage-admin-msg');
    try {
        const res = await fetch('api/system/admin_accounts.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const result = await res.json();
        
        if (res.ok && result.status === 'success') {
            msg.className = 'mt-2 p-2 rounded-lg text-[11px] font-mono font-medium bg-emerald-950/60 text-emerald-400 border border-emerald-500/40';
            msg.innerText = 'Account deleted successfully!';
            msg.classList.remove('hidden');
            loadAdminsList();
        } else {
            msg.className = 'mt-2 p-2 rounded-lg text-[11px] font-mono font-medium bg-rose-950/60 text-rose-400 border border-rose-500/40';
            msg.innerText = result.message || 'Error deleting account.';
            msg.classList.remove('hidden');
        }
    } catch (err) {
        msg.className = 'mt-2 p-2 rounded-lg text-[11px] font-mono font-medium bg-rose-950/60 text-rose-400 border border-rose-500/40';
        msg.innerText = 'Network error.';
        msg.classList.remove('hidden');
    }
}


// Toggle between Login and Register views inside the modal
function toggleAuthView(view) {
    const loginView = document.getElementById('admin-modal-login-view');
    const registerView = document.getElementById('admin-modal-register-view');
    const loginBanner = document.getElementById('admin-login-error');
    const loginSuccessBanner = document.getElementById('admin-login-success');
    const registerBanner = document.getElementById('register-message-banner');
    
    if (loginBanner) loginBanner.classList.add('hidden');
    if (loginSuccessBanner) loginSuccessBanner.classList.add('hidden');
    if (registerBanner) registerBanner.classList.add('hidden');

    // Keep both forms blank, never pre-filled
    const loginUserInput = document.getElementById('admin-username-input');
    const loginPassInput = document.getElementById('admin-password-input');
    const regUserInput = document.getElementById('register-username-input');
    const regPassInput = document.getElementById('register-password-input');
    if (loginUserInput) loginUserInput.value = '';
    if (loginPassInput) loginPassInput.value = '';
    if (regUserInput) regUserInput.value = '';
    if (regPassInput) regPassInput.value = '';
    
    if (view === 'register') {
        if (loginView) loginView.classList.add('hidden');
        if (registerView) registerView.classList.remove('hidden');
    } else {
        if (registerView) registerView.classList.add('hidden');
        if (loginView) loginView.classList.remove('hidden');
    }
}

// Handle Registration Submission
async function submitRegister(event) {
    if (event) event.preventDefault();
    
    const banner = document.getElementById('register-message-banner');
    const submitBtn = document.getElementById('register-submit-btn');
    const username = document.getElementById('register-username-input').value.trim();
    const pass = document.getElementById('register-password-input').value.trim();

    if (!username || !pass) {
        if (banner) {
            banner.innerText = 'Please fill out all fields.';
            banner.className = 'p-3 rounded-xl bg-rose-950/60 border border-rose-500/40 text-rose-300 text-xs font-medium';
            banner.classList.remove('hidden');
        }
        return;
    }

    if (pass.length < 6) {
        if (banner) {
            banner.innerText = 'Password must be at least 6 characters.';
            banner.className = 'p-3 rounded-xl bg-rose-950/60 border border-rose-500/40 text-rose-300 text-xs font-medium';
            banner.classList.remove('hidden');
        }
        return;
    }

    if (banner) banner.classList.add('hidden');
    submitBtn.innerText = 'Creating Account...';
    submitBtn.disabled = true;

    try {
        const res = await fetch('api/auth/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: username, password: pass })
        });
        
        const data = await res.json();
        
        if (res.ok && data.status === 'success') {
            currentAdminState.logged_in = true;
            currentAdminState.user = username;
            
            if (banner) {
                banner.innerText = `✓ Account created! Welcome, ${username}. Logging you in...`;
                banner.className = 'p-3 rounded-xl bg-emerald-950/60 border border-emerald-500/40 text-emerald-300 text-xs font-medium';
                banner.classList.remove('hidden');
            }
            
            updateAdminUI(true, username, data.role || 'user');
            clearAuthInputs();
            
            setTimeout(() => {
                closeAdminModal();
                submitBtn.innerText = 'Create Account';
                submitBtn.disabled = false;
                if (banner) banner.classList.add('hidden');
            }, 1200);
        } else {
            if (banner) {
                banner.innerText = data.message || 'Registration failed.';
                banner.className = 'p-3 rounded-xl bg-rose-950/60 border border-rose-500/40 text-rose-300 text-xs font-medium';
                banner.classList.remove('hidden');
            }
            submitBtn.innerText = 'Create Account';
            submitBtn.disabled = false;
        }
    } catch (err) {
        if (banner) {
            banner.innerText = 'Network error. Please try again.';
            banner.className = 'p-3 rounded-xl bg-rose-950/60 border border-rose-500/40 text-rose-300 text-xs font-medium';
            banner.classList.remove('hidden');
        }
        submitBtn.innerText = 'Create Account';
        submitBtn.disabled = false;
    }
}
