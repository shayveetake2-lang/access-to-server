import re

with open('index.html', 'r', encoding='utf-8') as f:
    content = f.read()

admin_manage_btn = """
                        <a href="#" onclick="showManageAdminsView();" class="p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 hover:border-amber-500/40 text-xs font-medium text-slate-300 hover:text-white flex items-center justify-between transition-colors">
                            <span>👥 Manage Admin Accounts</span>
                            <span class="text-amber-400 font-mono text-[11px]">&rarr;</span>
                        </a>
"""

content = content.replace('<span>⚡ Run Auto Debug Diagnostics</span>\n                            <span class="text-purple-400 font-mono text-[11px]">&rarr;</span>\n                        </a>', '<span>⚡ Run Auto Debug Diagnostics</span>\n                            <span class="text-purple-400 font-mono text-[11px]">&rarr;</span>\n                        </a>' + admin_manage_btn)


admin_manage_view = """
            <!-- STATE 3: MANAGE ADMINS VIEW -->
            <div id="admin-modal-manage-view" class="hidden space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-100 flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                        <span>Manage Admins</span>
                    </h3>
                    <button type="button" onclick="showAdminPanelView()" class="text-[10px] text-slate-400 hover:text-white font-semibold flex items-center gap-1 cursor-pointer">
                        <span>&larr; Back</span>
                    </button>
                </div>
                
                <div class="rounded-xl border border-slate-800 bg-slate-950/60 overflow-hidden">
                    <div id="admin-accounts-list" class="divide-y divide-slate-800/80 max-h-40 overflow-y-auto">
                        <!-- Populated by JS -->
                        <div class="p-3 text-center text-xs text-slate-500">Loading admins...</div>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-800/80">
                    <h4 class="text-xs font-semibold text-slate-300 mb-2">Add New Admin</h4>
                    <div class="space-y-2">
                        <input type="text" id="new-admin-user" placeholder="Username" class="w-full bg-slate-950/60 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-cyan-500 transition-colors">
                        <input type="password" id="new-admin-pass" placeholder="Password" class="w-full bg-slate-950/60 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-cyan-500 transition-colors">
                        <button type="button" onclick="submitAddAdmin()" class="w-full py-1.5 px-3 rounded-lg bg-emerald-950/60 hover:bg-emerald-900/80 border border-emerald-500/40 text-emerald-400 font-semibold text-xs transition-colors flex justify-center cursor-pointer">
                            + Create Account
                        </button>
                    </div>
                    <div id="manage-admin-msg" class="hidden mt-2 p-2 rounded-lg text-[11px] font-mono font-medium"></div>
                </div>
            </div>
"""

content = content.replace('<!-- STATE 2: LOGGED IN ADMIN PANEL VIEW -->', admin_manage_view + '\n            <!-- STATE 2: LOGGED IN ADMIN PANEL VIEW -->')

with open('index.html', 'w', encoding='utf-8') as f:
    f.write(content)

print("index.html patched with Account Management UI")
