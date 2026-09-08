with open('js/admin_auth.js', 'r', encoding='utf-8') as f:
    content = f.read()

register_functions = """
// Toggle between Login and Register views inside the modal
function toggleAuthView(view) {
    const loginView = document.getElementById('admin-modal-login-view');
    const registerView = document.getElementById('admin-modal-register-view');
    const loginBanner = document.getElementById('admin-login-error');
    const registerBanner = document.getElementById('register-message-banner');
    
    if (loginBanner) loginBanner.classList.add('hidden');
    if (registerBanner) registerBanner.classList.add('hidden');
    
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
    
    const errBanner = document.getElementById('register-message-banner');
    const submitBtn = document.getElementById('register-submit-btn');
    const username = document.getElementById('register-username-input').value.trim();
    const pass = document.getElementById('register-password-input').value.trim();

    if (!username || !pass) {
        errBanner.innerText = 'Please fill out all fields.';
        errBanner.className = 'p-3 rounded-xl bg-rose-950/60 border border-rose-500/40 text-rose-300 text-xs font-medium';
        return;
    }

    if (pass.length < 6) {
        errBanner.innerText = 'Password must be at least 6 characters.';
        errBanner.className = 'p-3 rounded-xl bg-rose-950/60 border border-rose-500/40 text-rose-300 text-xs font-medium';
        return;
    }

    errBanner.classList.add('hidden');
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
            errBanner.innerText = data.message;
            errBanner.className = 'p-3 rounded-xl bg-emerald-950/60 border border-emerald-500/40 text-emerald-300 text-xs font-medium';
            
            // Clear the form
            document.getElementById('register-username-input').value = '';
            document.getElementById('register-password-input').value = '';
            
            // Switch back to login view after a short delay
            setTimeout(() => {
                toggleAuthView('login');
                // Pre-fill username for convenience
                const loginUserInput = document.getElementById('admin-username-input');
                if (loginUserInput) loginUserInput.value = username;
            }, 2000);
        } else {
            errBanner.innerText = data.message || 'Registration failed.';
            errBanner.className = 'p-3 rounded-xl bg-rose-950/60 border border-rose-500/40 text-rose-300 text-xs font-medium';
        }
    } catch (err) {
        errBanner.innerText = 'Network error. Please try again.';
        errBanner.className = 'p-3 rounded-xl bg-rose-950/60 border border-rose-500/40 text-rose-300 text-xs font-medium';
    } finally {
        submitBtn.innerText = 'Create Account';
        submitBtn.disabled = false;
    }
}
"""

content += "\n" + register_functions

# Need to make sure openAdminModal() also resets the view to 'login' by default
content = content.replace(
"""function openAdminModal() {
    // If already logged in, this button acts as a logout""",
"""function openAdminModal() {
    // Reset view to login
    toggleAuthView('login');
    // If already logged in, this button acts as a logout""")

with open('js/admin_auth.js', 'w', encoding='utf-8') as f:
    f.write(content)

print("JS Updated")
