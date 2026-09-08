import re

with open('index.html', 'r', encoding='utf-8') as f:
    content = f.read()

banner_html = """
                <!-- Login Banner (Shown when not authenticated) -->
                <div id="standard-user-banner" class="p-5 rounded-xl bg-slate-950/80 border border-cyan-500/20 text-center space-y-3 mb-6">
                    <div class="inline-flex p-3 rounded-full bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-200">Authentication Required</h3>
                        <p class="text-xs text-slate-400 mt-1">Please log in to deploy websites and manage databases.</p>
                    </div>
                    <button type="button" onclick="openAdminModal()" class="mt-2 inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-semibold shadow-lg shadow-cyan-900/20 transition-all cursor-pointer">
                        Login to Access
                    </button>
                </div>
"""

# Inject before the standard-user-portal deployment section
content = content.replace('<!-- 3. Deployment Engine Card -->', banner_html + '\n                <!-- 3. Deployment Engine Card -->')

with open('index.html', 'w', encoding='utf-8') as f:
    f.write(content)
print("Banner added")
