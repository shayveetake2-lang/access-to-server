with open('index.html', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update text "Admin Authentication" -> "System Login"
content = content.replace('<h3 class="text-base font-bold text-slate-100">Admin Authentication</h3>',
                          '<h3 class="text-base font-bold text-slate-100">System Login</h3>')
content = content.replace('<p class="text-xs text-slate-400">Database-backed access control for access_db</p>',
                          '<p class="text-xs text-slate-400">Secure access to the Server Console</p>')

# 2. Re-inject the register view if it's missing
register_html = """
            <!-- Registration View (Hidden by default) -->
            <div id="admin-modal-register-view" class="space-y-4 hidden">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-100">Create an Account</h3>
                        <p class="text-xs text-slate-400">Register a new standard user account</p>
                    </div>
                </div>

                <!-- Error/Success Banner -->
                <div id="register-message-banner" class="hidden p-3 rounded-xl text-xs font-medium border"></div>

                <form onsubmit="submitRegister(event);" class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Username</label>
                        <input type="text" id="register-username-input" required class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-500 font-mono text-xs focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors" placeholder="Choose a username">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Password</label>
                        <input type="password" id="register-password-input" required minlength="6" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-100 placeholder-slate-500 font-mono text-xs focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors" placeholder="Create a password (min 6 chars)">
                    </div>
                    
                    <button type="submit" id="register-submit-btn" class="w-full py-2.5 px-4 rounded-xl font-semibold text-sm text-white bg-indigo-600 hover:bg-indigo-500 transition-colors shadow-lg shadow-indigo-500/20">
                        Create Account
                    </button>
                    
                    <div class="text-center pt-2">
                        <button type="button" onclick="toggleAuthView('login')" class="text-xs text-slate-400 hover:text-white transition-colors">
                            Already have an account? Log In
                        </button>
                    </div>
                </form>
            </div>
"""

# Check if register view is already there
if 'id="admin-modal-register-view"' not in content:
    content = content.replace('<!-- Admin Portal View (Shown after login) -->', register_html + '\n            <!-- Admin Portal View (Shown after login) -->')

# 3. Add toggle button inside the login form. 
login_toggle_html = """
                    <div class="text-center pt-3">
                        <button type="button" onclick="toggleAuthView('register')" class="text-xs font-medium text-cyan-500 hover:text-cyan-400 transition-colors cursor-pointer">
                            Don't have an account? Create one
                        </button>
                    </div>
                </form>
"""

# I will replace `</form>` for the login form with the toggle button + `</form>`
# The form starts at `<form onsubmit="submitAdminLogin(event);" class="space-y-3.5">`
# I'll just find that specific form block and append the toggle before the closing tag.
parts = content.split('<form onsubmit="submitAdminLogin(event);" class="space-y-3.5">')
if len(parts) == 2:
    # find the FIRST </form> in parts[1]
    subparts = parts[1].split('</form>', 1)
    if "Don't have an account" not in subparts[0] and "Need an account" not in subparts[0]:
        new_part1 = subparts[0] + login_toggle_html + subparts[1]
        content = parts[0] + '<form onsubmit="submitAdminLogin(event);" class="space-y-3.5">' + new_part1

with open('index.html', 'w', encoding='utf-8') as f:
    f.write(content)
print("Fixed login UI and injected register view")
