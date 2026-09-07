// js/admin_auth.js — Centralized Admin Authentication & Dynamic Tools Menu Manager

let currentAdminState = { logged_in: false, user: null };

async function checkAdminAuth() {
    try {
        const res = await fetch('api/system/admin_auth.php?action=status');
        const data = await res.json();
        if (data.status === 'success') {
            currentAdminState.logged_in = data.logged_in;
            currentAdminState.user = data.user;
            updateAdminUI(data.logged_in, data.user);
        }
    } catch (err) {
        console.error('Failed to check admin auth status:', err);
    }
}

function updateAdminUI(isLoggedIn, user) {
    const navBtnTexts = document.querySelectorAll('.admin-nav-text-el');
    const navIconLocks = document.querySelectorAll('.admin-nav-icon-lock-el');
    const navBadges = document.querySelectorAll('.admin-nav-badge-active-el');
    const dropdownTexts = document.querySelectorAll('.admin-dropdown-text-el');

    const loginView = document.getElementById('admin-modal-login-view');
    const panelView = document.getElementById('admin-modal-panel-view');
    const userDisplay = document.getElementById('admin-username-display');

    const deployForm = document.getElementById('deploy-form-container');
    const deployLock = document.getElementById('deploy-lock-container');
    const dbForm = document.getElementById('db-form-container');
    const dbLock = document.getElementById('db-lock-container');
    const usbForm = document.getElementById('usb-upload-form');
    const usbLock = document.getElementById('usb-lock-container');

    const adminOnlyTools = document.querySelectorAll('.admin-only-tool');

    if (isLoggedIn) {
        navBtnTexts.forEach(el => el.innerText = 'Admin Panel');
        navIconLocks.forEach(el => el.classList.add('hidden'));
        navBadges.forEach(el => el.classList.remove('hidden'));
        dropdownTexts.forEach(el => el.innerText = 'Admin Panel (Active)');

        if (loginView) loginView.classList.add('hidden');
        if (panelView) panelView.classList.remove('hidden');
        if (userDisplay) userDisplay.innerText = user || 'admin';

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
        navBtnTexts.forEach(el => el.innerText = 'Admin Login');
        navIconLocks.forEach(el => el.classList.remove('hidden'));
        navBadges.forEach(el => el.classList.add('hidden'));
        dropdownTexts.forEach(el => el.innerText = 'Admin Login');

        if (loginView) loginView.classList.remove('hidden');
        if (panelView) panelView.classList.add('hidden');

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
}

function openAdminModal() {
    const modal = document.getElementById('admin-auth-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeAdminModal() {
    const modal = document.getElementById('admin-auth-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

async function submitAdminLogin() {
    const userInputEl = document.getElementById('admin-username-input');
    const passInputEl = document.getElementById('admin-password-input');
    const errBanner = document.getElementById('admin-login-error');
    const submitBtn = document.getElementById('admin-login-btn');

    const userInput = userInputEl ? userInputEl.value : '';
    const passInput = passInputEl ? passInputEl.value : '';

    if (!userInput || !passInput) return;

    if (errBanner) errBanner.classList.add('hidden');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerText = 'Authenticating with access_db...';
    }

    try {
        const res = await fetch('api/system/admin_auth.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: userInput, password: passInput })
        });

        const result = await res.json();
        if (res.ok && result.status === 'success') {
            currentAdminState.logged_in = true;
            currentAdminState.user = result.username;
            updateAdminUI(true, result.username);

            const output = document.getElementById('output');
            if (output) {
                output.innerHTML += `<span class="text-emerald-400 font-semibold">[AUTH-SUCCESS] Authenticated as '${result.username}'. Admin features unlocked.</span><br>`;
                output.parentElement.scrollTop = output.parentElement.scrollHeight;
            }
        } else {
            if (errBanner) {
                errBanner.innerText = result.message || 'Authentication failed.';
                errBanner.classList.remove('hidden');
            }
        }
    } catch (err) {
        if (errBanner) {
            errBanner.innerText = 'Network error during authentication.';
            errBanner.classList.remove('hidden');
        }
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerText = 'Authenticate & Unlock Controls';
        }
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