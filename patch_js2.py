with open('js/admin_auth.js', 'r', encoding='utf-8') as f:
    content = f.read()

admin_funcs = """
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
"""

if "function showManageAdminsView" not in content:
    content += "\n" + admin_funcs

with open('js/admin_auth.js', 'w', encoding='utf-8') as f:
    f.write(content)
print("Updated js/admin_auth.js with account management JS")
