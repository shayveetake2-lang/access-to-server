    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'Menlo', 'Monaco', 'Courier New', 'monospace'],
                    },
                    colors: {
                        brand: {
                            50: '#ecfeff',
                            100: '#cffafe',
                            400: '#22d3ee',
                            500: '#06b6d4',
                            600: '#0891b2',
                        },
                        surface: {
                            base: '#090d16',
                            card: '#0f172a',
                            elevated: '#1e293b',
                            terminal: '#050811',
                            border: '#1e293b'
                        }
                    },
                    animation: {
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    }
                }
            }
        }
    </script>
    <script>
        // Dropdown toggle for tools menu
        function toggleNavMenu() {
            const dropdown = document.getElementById('nav-dropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const wrapper = document.getElementById('nav-dropdown-wrapper');
            const dropdown = document.getElementById('nav-dropdown');
            if (wrapper && !wrapper.contains(e.target) && dropdown && !dropdown.classList.contains('hidden')) {
                dropdown.classList.add('hidden');
            }
        });

        // Server Hardware Node Modal Controls
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

        // Close all modals on Escape key or clicking outside
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeNodeModal();
                closeAdminModal();
                closePopup();
                closeDbPopup();
            }
        });

        document.addEventListener('click', (e) => {
            const adminModal = document.getElementById('admin-auth-modal');
            if (adminModal && !adminModal.classList.contains('hidden') && e.target === adminModal) {
                closeAdminModal();
            }
            const nodeModal = document.getElementById('node-modal');
            if (nodeModal && !nodeModal.classList.contains('hidden') && e.target === nodeModal) {
                closeNodeModal();
            }
            const successPopup = document.getElementById('success-popup');
            if (successPopup && !successPopup.classList.contains('hidden') && e.target === successPopup) {
                closePopup();
            }
            const dbPopup = document.getElementById('db-popup');
            if (dbPopup && !dbPopup.classList.contains('hidden') && e.target === dbPopup) {
                closeDbPopup();
            }
        });

        // Clear terminal window
        function clearTerminal() {
            const output = document.getElementById('output');
            if (output) {
                output.innerHTML = 'Terminal cleared. Awaiting logs...<br>';
            }
        }

        // Popup management
        function showPopup(url) {
            document.getElementById('project-link').href = url;
            const popup = document.getElementById('success-popup');
            popup.classList.remove('hidden');
            popup.classList.add('flex');
        }

        function closePopup() {
            const popup = document.getElementById('success-popup');
            popup.classList.add('hidden');
            popup.classList.remove('flex');
        }
        
        function closeDbPopup() {
            const dbPopup = document.getElementById('db-popup');
            dbPopup.classList.add('hidden');
            dbPopup.classList.remove('flex');
        }

        // Beginner Demo Helper Function
        function loadDemoRepo() {
            const input = document.getElementById('repo-url');
            if (input) {
                input.value = 'https://github.com/mdn/beginner-html-site-styled.git';
                input.focus();
                input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                input.classList.add('ring-2', 'ring-cyan-500');
                setTimeout(() => input.classList.remove('ring-2', 'ring-cyan-500'), 800);
            }
        }

        // Database Helper Functions
        function setDbPreset(name) {
            const input = document.getElementById('db-name');
            if (input) {
                input.value = name;
                input.focus();
                input.classList.add('ring-2', 'ring-indigo-500');
                setTimeout(() => input.classList.remove('ring-2', 'ring-indigo-500'), 600);
            }
        }

        function scrollToDatabase() {
            const section = document.getElementById('database-section');
            if (section) {
                section.scrollIntoView({ behavior: 'smooth' });
                const input = document.getElementById('db-name');
                if (input) {
                    setTimeout(() => {
                        input.focus();
                        input.classList.add('ring-2', 'ring-indigo-500');
                        setTimeout(() => input.classList.remove('ring-2', 'ring-indigo-500'), 800);
                    }, 400);
                }
            }
        }

        // Database Provisioning
        async function provisionDatabase() {
            const dbName = document.getElementById('db-name').value;
            const output = document.getElementById('output');
            
            if (!dbName) {
                alert("Please enter a database name");
                return;
            }

            output.innerHTML += `[DB] Provisioning database: ${dbName}...<br>`;
            output.parentElement.scrollTop = output.parentElement.scrollHeight;
            
            try {
                const formData = new FormData();
                formData.append('db_name', dbName);
                
                const response = await fetch('api/system/provision_db.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    output.innerHTML += `[DB-SUCCESS] ${result.message}<br>`;
                    document.getElementById('created-db-name').innerText = dbName;
                    
                    const codeBlock = `// --- Server PHP Connection Boilerplate ---

$host = '127.0.0.1';
$port = '3307'; // SSH tunnel to MAMP MySQL
$dbname = '${dbName}';
$user = 'root';
$pass = 'root';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    // Connection successful
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}`;
                    
                    document.getElementById('db-instructions').innerText = codeBlock;
                    const dbPopup = document.getElementById('db-popup');
                    dbPopup.classList.remove('hidden');
                    dbPopup.classList.add('flex');
                } else {
                    output.innerHTML += `[DB-FAILED] ${result.message}<br>`;
                }
            } catch (err) {
                output.innerHTML += `[DB-ERROR] Connection to DB provisioning failed.<br>`;
            }
            output.parentElement.scrollTop = output.parentElement.scrollHeight;
        }

        // Deployment Stream (SSE via deploy.php)
        function startDeployment() {
            const repoUrl = document.getElementById('repo-url').value;
            const output = document.getElementById('output');
            const deployBtn = document.getElementById('deploy-btn');
            
            if (!repoUrl) {
                alert("Please enter a repository URL");
                return;
            }

            deployBtn.disabled = true;
            output.innerHTML = '<span class="text-cyan-400 font-semibold">[STREAM] Establishing secure link via ZeroTier tunnel...</span><br>';

            const eventSource = new EventSource('api/system/deploy.php?repo=' + encodeURIComponent(repoUrl));

            eventSource.onmessage = function(event) {
                const message = event.data;
                output.innerHTML += message + '<br>';
                output.parentElement.scrollTop = output.parentElement.scrollHeight;

                if (message.includes('Deployment Complete') || message.includes('Deployment Failed')) {
                    eventSource.close();
                    deployBtn.disabled = false;
                    
                    if (message.includes('Deployment Complete')) {
                        const parts = repoUrl.split('/');
                        let repoName = parts[parts.length - 1].replace('.git', '').replace(/[^a-zA-Z0-9_-]/g, '');
                        const projectUrl = `/sites/${repoName}/`;
                        showPopup(projectUrl);
                    }
                }
            };

            eventSource.onerror = function() {
                output.innerHTML += '<span class="text-rose-400 font-semibold">[ERROR] Connection stream interrupted or lost.</span><br>';
                eventSource.close();
                deployBtn.disabled = false;
            };
        }

        /* -------------------------------------------------------------
           USB Drive Management Panel (Capacity & File Upload)
        ------------------------------------------------------------- */
        let currentUsbState = { connected: false, is_full: false };

        function formatBytes(bytes, decimals = 2) {
            if (bytes <= 0) return '0 B';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        async function loadUsbStatus() {
            const pill    = document.getElementById('usb-status-pill');
            const banner  = document.getElementById('usb-warning-banner');
            const barFill = document.getElementById('usb-bar-fill');
            const pctText = document.getElementById('usb-percent-text');
            const usedTxt = document.getElementById('usb-used-text');
            const freeTxt = document.getElementById('usb-free-text');
            const totlTxt = document.getElementById('usb-total-text');
            const upBtn   = document.getElementById('usb-upload-btn');

            try {
                const res = await fetch('usb_manager.php');
                const data = await res.json();
                currentUsbState = data;

                if (!data.connected) {
                    pill.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-mono font-medium bg-rose-950/60 text-rose-400 border border-rose-500/30';
                    pill.innerText = 'Unplugged';

                    banner.className = 'mb-4 p-3 rounded-xl text-xs border font-medium bg-rose-950/40 border-rose-500/40 text-rose-300 flex items-center gap-2';
                    banner.innerHTML = '<span class="text-base">⚠️</span> <span><strong>Drive Unplugged:</strong> USB Drive is currently disconnected or unmounted at <code>/Volumes/USBDrive</code>.</span>';
                    banner.style.display = 'flex';

                    barFill.style.width = '0%';
                    barFill.className = 'h-full rounded-full bg-slate-700';
                    pctText.innerText = '0%';
                    usedTxt.innerText = '0 B';
                    freeTxt.innerText = '0 B';
                    totlTxt.innerText = '0 B';

                    upBtn.disabled = true;
                    upBtn.innerHTML = '<span>USB Drive Disconnected</span>';
                    return;
                }

                // Drive is connected
                if (data.is_full) {
                    pill.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-mono font-medium bg-amber-950/60 text-amber-400 border border-amber-500/30';
                    pill.innerText = 'Drive Full';

                    banner.className = 'mb-4 p-3 rounded-xl text-xs border font-medium bg-amber-950/40 border-amber-500/40 text-amber-300 flex items-center gap-2';
                    banner.innerHTML = '<span class="text-base">⚠️</span> <span><strong>Storage Full:</strong> USB Drive has less than 5 MB free space. Delete files before uploading.</span>';
                    banner.style.display = 'flex';

                    upBtn.disabled = true;
                    upBtn.innerHTML = '<span>USB Drive Full</span>';
                } else {
                    pill.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-mono font-medium bg-emerald-950/60 text-emerald-400 border border-emerald-500/30';
                    pill.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Connected';
                    banner.style.display = 'none';

                    upBtn.disabled = false;
                    upBtn.innerHTML = '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg> <span>Transfer to USB Drive</span>';
                }

                const pct = Math.min(100, Math.max(0, data.percent_used || 0));
                pctText.innerText = pct + '%';
                barFill.style.width = pct + '%';

                if (pct >= 95 || data.is_full) {
                    barFill.className = 'h-full rounded-full bg-gradient-to-r from-amber-500 to-rose-500 transition-all duration-500';
                } else if (pct >= 80) {
                    barFill.className = 'h-full rounded-full bg-gradient-to-r from-yellow-500 to-amber-500 transition-all duration-500';
                } else {
                    barFill.className = 'h-full rounded-full bg-gradient-to-r from-cyan-500 to-emerald-500 transition-all duration-500';
                }

                usedTxt.innerText = data.used_formatted || '--';
                freeTxt.innerText = data.free_formatted || '--';
                totlTxt.innerText = data.total_formatted || '--';

            } catch (err) {
                pill.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-mono font-medium bg-rose-950/60 text-rose-400 border border-rose-500/30';
                pill.innerText = 'Offline';
                banner.className = 'mb-4 p-3 rounded-xl text-xs border font-medium bg-rose-950/40 border-rose-500/40 text-rose-300 flex items-center gap-2';
                banner.innerHTML = '<span class="text-base">⚠️</span> <span><strong>Connection Error:</strong> Could not retrieve USB storage telemetry.</span>';
                banner.style.display = 'flex';
                upBtn.disabled = true;
            }
        }

        function handleUsbFileSelect(input) {
            const fileNameEl = document.getElementById('usb-file-name');
            const msgEl = document.getElementById('usb-upload-msg');
            msgEl.style.display = 'none';

            if (input.files && input.files[0]) {
                const file = input.files[0];
                fileNameEl.innerHTML = `<span class="text-slate-100 font-semibold">${file.name}</span> <span class="text-cyan-400 font-mono text-[11px]">(${formatBytes(file.size)})</span>`;
            } else {
                fileNameEl.innerText = 'Click or drag file to upload';
            }
        }

        async function uploadUsbFile() {
            const fileInput = document.getElementById('usb-file-input');
            const upBtn = document.getElementById('usb-upload-btn');
            const msgEl = document.getElementById('usb-upload-msg');

            if (!fileInput.files || !fileInput.files[0]) {
                alert('Please select a file to transfer to the USB Drive.');
                return;
            }

            if (!currentUsbState.connected) {
                alert('USB Drive is currently unplugged. Please connect it to the server first.');
                return;
            }

            if (currentUsbState.is_full) {
                alert('USB Drive is full. Please clear space before uploading.');
                return;
            }

            const file = fileInput.files[0];
            const originalBtnHtml = upBtn.innerHTML;
            upBtn.disabled = true;
            upBtn.innerHTML = '<svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <span>Uploading File...</span>';

            msgEl.style.display = 'block';
            msgEl.className = 'mt-3 p-3 rounded-xl text-xs font-mono bg-amber-950/40 border border-amber-500/40 text-amber-300';
            msgEl.innerText = `Uploading ${file.name}...`;

            try {
                const formData = new FormData();
                formData.append('usb_file', file);

                const response = await fetch('usb_manager.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    msgEl.className = 'mt-3 p-3 rounded-xl text-xs font-mono bg-emerald-950/40 border border-emerald-500/40 text-emerald-300';
                    msgEl.innerHTML = `✅ <strong>Transferred:</strong> ${result.message}`;
                    // Reset input
                    fileInput.value = '';
                    document.getElementById('usb-file-name').innerText = 'Click or drag file to upload';
                    // Refresh storage bar
                    loadUsbStatus();
                } else {
                    msgEl.className = 'mt-3 p-3 rounded-xl text-xs font-mono bg-rose-950/40 border border-rose-500/40 text-rose-300';
                    msgEl.innerHTML = `❌ <strong>Upload Failed:</strong> ${result.message}`;
                }
            } catch (err) {
                msgEl.className = 'mt-3 p-3 rounded-xl text-xs font-mono bg-rose-950/40 border border-rose-500/40 text-rose-300';
                msgEl.innerHTML = '❌ <strong>Upload Error:</strong> Network connection lost or server error.';
            } finally {
                upBtn.disabled = false;
                upBtn.innerHTML = originalBtnHtml;
            }
        }

        // Drag & Drop visual feedback
        const dropZone = document.getElementById('usb-drop-zone');
        if (dropZone) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    dropZone.classList.add('dragover');
                }, false);
            });
            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    dropZone.classList.remove('dragover');
                }, false);
            });
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                const fileInput = document.getElementById('usb-file-input');
                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    handleUsbFileSelect(fileInput);
                }
            });
        }

        /* -------------------------------------------------------------
           Server Storage Telemetry Panel
        ------------------------------------------------------------- */
        const SRV_COLOR_MAP = {
            cyan:    { bg: 'bg-cyan-500',    dot: 'bg-cyan-400',    text: 'text-cyan-400'    },
            blue:    { bg: 'bg-blue-500',    dot: 'bg-blue-400',    text: 'text-blue-400'    },
            indigo:  { bg: 'bg-indigo-500',  dot: 'bg-indigo-400',  text: 'text-indigo-400'  },
            amber:   { bg: 'bg-amber-500',   dot: 'bg-amber-400',  text: 'text-amber-400'   },
            emerald: { bg: 'bg-emerald-500', dot: 'bg-emerald-400', text: 'text-emerald-400' },
            violet:  { bg: 'bg-violet-500',  dot: 'bg-violet-400',  text: 'text-violet-400' },
            fuchsia: { bg: 'bg-fuchsia-500', dot: 'bg-fuchsia-400', text: 'text-fuchsia-400'},
            slate:   { bg: 'bg-slate-600',   dot: 'bg-slate-500',   text: 'text-slate-400'  },
        };

        async function loadServerStorage() {
            const pill      = document.getElementById('srv-status-pill');
            const pathEl    = document.getElementById('srv-disk-path');
            const pctText   = document.getElementById('srv-percent-text');
            const usedTxt   = document.getElementById('srv-used-text');
            const freeTxt   = document.getElementById('srv-free-text');
            const totlTxt   = document.getElementById('srv-total-text');
            const segments  = document.getElementById('srv-bar-segments');
            const breakList = document.getElementById('srv-breakdown-list');

            try {
                const res  = await fetch('api/system/server_storage.php');
                const data = await res.json();

                if (data.status !== 'success') throw new Error(data.message || 'API error');

                // Header info
                pathEl.innerText = data.disk_path || '/Volumes/htdocs';

                // Status pill
                const pct = Math.min(100, Math.max(0, data.percent_used || 0));
                if (pct >= 95) {
                    pill.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-mono font-medium bg-rose-950/60 text-rose-400 border border-rose-500/30';
                    pill.innerText = 'Critical';
                } else if (pct >= 80) {
                    pill.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-mono font-medium bg-amber-950/60 text-amber-400 border border-amber-500/30';
                    pill.innerText = 'High Usage';
                } else {
                    pill.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-mono font-medium bg-emerald-950/60 text-emerald-400 border border-emerald-500/30';
                    pill.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Healthy';
                }

                // Metrics
                pctText.innerText = pct + '%';
                usedTxt.innerText = data.used_formatted || '--';
                freeTxt.innerText = data.free_formatted || '--';
                totlTxt.innerText = data.total_formatted || '--';

                // ── Stacked progress bar segments ──
                // Filter to items with non-zero bytes, excluding "Other / OS" for the bar
                const barItems = (data.breakdown || []).filter(b => b.bytes > 0);
                const totalUsed = data.used_bytes || 1;
                let segmentsHtml = '';
                barItems.forEach(item => {
                    const widthPct = Math.max(0.4, (item.bytes / data.total_bytes) * 100); // min width so tiny slices still show
                    const colors = SRV_COLOR_MAP[item.color] || SRV_COLOR_MAP.slate;
                    segmentsHtml += `<div class="${colors.bg} h-full opacity-80" style="width:${widthPct.toFixed(2)}%" title="${item.label}: ${item.formatted}"></div>`;
                });
                segments.innerHTML = segmentsHtml;
                segments.style.width = pct + '%';

                // ── Breakdown list ──
                let listHtml = '';
                (data.breakdown || []).forEach(item => {
                    if (!item.exists && item.bytes === 0 && item.label !== 'Other / OS') return; // skip missing dirs with 0 bytes
                    const colors = SRV_COLOR_MAP[item.color] || SRV_COLOR_MAP.slate;
                    const sharePercent = data.used_bytes > 0
                        ? ((item.bytes / data.used_bytes) * 100).toFixed(1)
                        : '0.0';

                    listHtml += `
                        <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-slate-950/40 border border-slate-800/60 text-xs">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="w-2.5 h-2.5 rounded-full ${colors.dot} flex-shrink-0"></span>
                                <span class="text-slate-200 font-medium truncate">${item.label}</span>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0 ml-2">
                                <span class="font-mono ${colors.text} tabular-nums">${item.formatted}</span>
                                <span class="font-mono text-slate-500 tabular-nums w-12 text-right">${sharePercent}%</span>
                            </div>
                        </div>`;
                });
                breakList.innerHTML = listHtml;

            } catch (err) {
                pill.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-mono font-medium bg-rose-950/60 text-rose-400 border border-rose-500/30';
                pill.innerText = 'Offline';
                breakList.innerHTML = '<div class="flex items-center gap-2.5 px-3 py-2 rounded-lg bg-rose-950/40 border border-rose-500/40 text-xs text-rose-300"><span class="text-base">⚠️</span> Could not load server storage data.</div>';
            }
        }

        // Initialize USB capacity, server storage, and admin auth status on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadUsbStatus();
            loadServerStorage();
        });
    </script>
