import re

with open('index.html', 'r', encoding='utf-8') as f:
    content = f.read()

# Add standard-user-portal hidden to Deploy section
content = re.sub(r'<!-- 3\. Deployment Engine Card -->\s*<section class="rounded-2xl', '<!-- 3. Deployment Engine Card -->\n                <section class="rounded-2xl standard-user-portal hidden', content)

# Add standard-user-portal hidden to Database section
content = re.sub(r'<!-- 4\. Backend & Database Engine Card \(Prominent Dedicated Card\) -->\s*<section id="database-section" class="rounded-2xl', '<!-- 4. Backend & Database Engine Card (Prominent Dedicated Card) -->\n                <section id="database-section" class="rounded-2xl standard-user-portal hidden', content)

admin_portal_html = """
                <!-- Admin Portal Card -->
                <section id="admin-portal" class="rounded-2xl bg-indigo-950/40 border border-indigo-800/80 shadow-xl shadow-black/40 backdrop-blur-md overflow-hidden relative mb-6 hidden">
                    <div class="h-1 w-full bg-gradient-to-r from-red-500 via-orange-500 to-amber-500"></div>
                    <div class="p-5">
                        <div class="flex items-center gap-3 mb-4">
                            <span class="text-xl">🛡️</span>
                            <h2 class="text-xl font-bold tracking-tight text-white">Admin Portal</h2>
                        </div>
                        <p class="text-sm text-slate-300 mb-4">Welcome to the central administrative controls. From here, you can manage system settings, control user access, and oversee server telemetry.</p>
                        <div class="grid grid-cols-2 gap-3">
                            <button onclick="openAdminModal()" class="py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-sm font-medium transition-colors border border-slate-700">Manage Accounts</button>
                            <button onclick="window.location.href='auto_debug.php'" class="py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-sm font-medium transition-colors border border-slate-700">System Logs</button>
                        </div>
                    </div>
                </section>
"""

# Inject admin portal before the Deployment Engine Card
content = content.replace('<!-- 3. Deployment Engine Card -->', admin_portal_html + '\n                <!-- 3. Deployment Engine Card -->')

with open('index.html', 'w', encoding='utf-8') as f:
    f.write(content)
print("Injected portals into index.html")
