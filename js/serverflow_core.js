/**
 * js/serverflow_core.js — ServerFlow Unified UI Core Module
 * Provides unified theme management, navigation state, notification bell,
 * user profile dropdown, and interactive ServerFlow Help AI Chatbot.
 */

// Initialize Theme on Load
(function initServerFlowTheme() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light') {
        document.documentElement.classList.remove('dark');
    } else {
        document.documentElement.classList.add('dark');
    }
})();

document.addEventListener('DOMContentLoaded', () => {
    updateTimestampDisplays();
    initThemeSegmentedControl();
    initChatWidgetListeners();
    initNotificationDropdown();
    initProfileDropdown();
    initGlobalKeyboardListeners();
});

// Mobile Sidebar Navigation Handlers
function toggleMobileSidebar() {
    const sidebar = document.querySelector('aside');
    const backdrop = document.getElementById('sf-mobile-backdrop');
    if (sidebar) {
        sidebar.classList.toggle('-translate-x-full');
    }
    if (backdrop) {
        backdrop.classList.toggle('hidden');
    }
}

function closeMobileSidebar() {
    const sidebar = document.querySelector('aside');
    const backdrop = document.getElementById('sf-mobile-backdrop');
    if (sidebar && !sidebar.classList.contains('-translate-x-full')) {
        sidebar.classList.add('-translate-x-full');
    }
    if (backdrop && !backdrop.classList.contains('hidden')) {
        backdrop.classList.add('hidden');
    }
}

function initGlobalKeyboardListeners() {
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const notifMenu = document.getElementById('sf-notification-menu');
            const profileMenu = document.getElementById('sf-profile-menu');
            const chatWidget = document.getElementById('sf-chat-widget');
            if (notifMenu && !notifMenu.classList.contains('hidden')) notifMenu.classList.add('hidden');
            if (profileMenu && !profileMenu.classList.contains('hidden')) profileMenu.classList.add('hidden');
            if (chatWidget && !chatWidget.classList.contains('hidden')) toggleServerFlowChat();
            closeMobileSidebar();
        }
    });
}

// Update live time checks (e.g. "Checked 14:32")
function updateTimestampDisplays() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
    document.querySelectorAll('.live-time-display').forEach(el => {
        el.textContent = `Checked ${timeStr}`;
    });
}

// Theme Segmented Control Handler
function initThemeSegmentedControl() {
    const isDark = document.documentElement.classList.contains('dark');
    updateThemeUI(isDark ? 'dark' : 'light');
}

function setServerFlowTheme(theme) {
    if (theme === 'dark') {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
    updateThemeUI(theme);
}

function updateThemeUI(theme) {
    const lightBtn = document.getElementById('sf-theme-light-btn');
    const darkBtn = document.getElementById('sf-theme-dark-btn');
    if (!lightBtn || !darkBtn) return;

    if (theme === 'light') {
        lightBtn.className = 'flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded-lg bg-white text-slate-900 shadow-sm transition-all';
        darkBtn.className = 'flex items-center gap-1 px-3 py-1 text-xs font-medium rounded-lg text-slate-400 hover:text-slate-200 transition-all';
    } else {
        lightBtn.className = 'flex items-center gap-1 px-3 py-1 text-xs font-medium rounded-lg text-slate-400 hover:text-slate-200 transition-all';
        darkBtn.className = 'flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded-lg bg-slate-800 text-cyan-400 shadow-sm transition-all';
    }
}

// Notification Bell Dropdown
function initNotificationDropdown() {
    const bellBtn = document.getElementById('sf-notification-btn');
    const notifMenu = document.getElementById('sf-notification-menu');
    if (!bellBtn || !notifMenu) return;

    bellBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        notifMenu.classList.toggle('hidden');
        const profileMenu = document.getElementById('sf-profile-menu');
        if (profileMenu) profileMenu.classList.add('hidden');
    });

    document.addEventListener('click', (e) => {
        if (!notifMenu.contains(e.target) && e.target !== bellBtn) {
            notifMenu.classList.add('hidden');
        }
    });
}

// User Profile Dropdown
function initProfileDropdown() {
    const profileBtn = document.getElementById('sf-profile-btn');
    const profileMenu = document.getElementById('sf-profile-menu');
    if (!profileBtn || !profileMenu) return;

    profileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        profileMenu.classList.toggle('hidden');
        const notifMenu = document.getElementById('sf-notification-menu');
        if (notifMenu) notifMenu.classList.add('hidden');
    });

    document.addEventListener('click', (e) => {
        if (!profileMenu.contains(e.target) && e.target !== profileBtn) {
            profileMenu.classList.add('hidden');
        }
    });
}

// ServerFlow Help AI Chatbot Widget
function initChatWidgetListeners() {
    const chatInput = document.getElementById('sf-chat-input');
    if (chatInput) {
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                submitServerFlowChat();
            }
        });
    }
}

function toggleServerFlowChat() {
    const widget = document.getElementById('sf-chat-widget');
    if (!widget) return;
    widget.classList.toggle('hidden');
    widget.classList.toggle('flex');
    if (!widget.classList.contains('hidden')) {
        const chatInput = document.getElementById('sf-chat-input');
        if (chatInput) chatInput.focus();
    }
}

function sendQuickPrompt(promptText) {
    const chatInput = document.getElementById('sf-chat-input');
    if (chatInput) {
        chatInput.value = promptText;
        submitServerFlowChat();
    }
}


async function submitServerFlowChat() {
    const chatInput = document.getElementById('sf-chat-input');
    const chatMessages = document.getElementById('sf-chat-messages');
    if (!chatInput || !chatMessages) return;

    const userText = chatInput.value.trim();
    if (!userText) return;

    // Load History
    let history = [];
    try {
        const stored = sessionStorage.getItem('sf_chat_history');
        if (stored) history = JSON.parse(stored);
    } catch(e) {}

    appendChatMessage('user', userText);
    chatInput.value = '';

    const typingId = 'typing-' + Date.now();
    const typingHtml = `
        <div id="${typingId}" class="flex items-start gap-2.5">
            <div class="w-7 h-7 rounded-full bg-cyan-600/20 text-cyan-400 flex items-center justify-center text-xs font-bold shrink-0">SF</div>
            <div class="px-3.5 py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 text-xs text-slate-500 dark:text-slate-400 italic">
                ServerFlow Help is thinking...
            </div>
        </div>
    `;
    chatMessages.insertAdjacentHTML('beforeend', typingHtml);
    chatMessages.scrollTop = chatMessages.scrollHeight;

    try {
        const response = await fetch('api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: userText, history: history })
        });
        const data = await response.json();
        
        const typingEl = document.getElementById(typingId);
        if (typingEl) typingEl.remove();

        if (data.status === 'success') {
            history.push({ role: 'user', content: userText });
            history.push({ role: 'assistant', content: data.reply });
            if (history.length > 10) history = history.slice(-10);
            sessionStorage.setItem('sf_chat_history', JSON.stringify(history));

            appendChatMessage('bot', data.reply, data);
        } else {
            appendChatMessage('bot', data.message || 'Sorry, I ran into an error processing your request.', { confidence: 'low', answer_type: 'service_error' });
        }
    } catch (err) {
        const typingEl = document.getElementById(typingId);
        if (typingEl) typingEl.remove();
        appendChatMessage('bot', 'Network error connecting to ServerFlow Help assistant.', { confidence: 'low', answer_type: 'service_error' });
    }
}

async function approveServerAction(actionId, paramsStr, btnId) {
    const btn = document.getElementById(btnId);
    if (btn) {
        btn.innerText = 'Running...';
        btn.disabled = true;
    }
    
    let params = {};
    try { params = JSON.parse(decodeURIComponent(paramsStr)); } catch(e) {}
    
    const token = localStorage.getItem('auth_token') || sessionStorage.getItem('active_session_token') || '';

    try {
        if (actionId === 'deploy_site') {
            const repo = params.repo_url;
            if (!repo) throw new Error('Missing repo_url');
            
            // Wait, SSE needs to be handled if we want to show stream, but we can just use fetch for deployment and let it run, 
            // or we can use EventSource to capture logs and append to chat!
            let outputMsg = '';
            const sseUrl = `api/system/deploy.php?repo=${encodeURIComponent(repo)}&token=${encodeURIComponent(token)}`;
            const es = new EventSource(sseUrl);
            
            appendChatMessage('bot', `Starting deployment for ${repo}... (Check Debug Console for live logs)`);
            
            es.onmessage = (event) => {
                const msg = event.data;
                outputMsg += msg + '\n';
                if (msg.includes('Deployment Complete')) {
                    es.close();
                    appendChatMessage('bot', `Deployment of ${repo} completed successfully!`);
                    if (btn) btn.innerText = 'Completed';
                } else if (msg.includes('Deployment Failed') || msg.includes('Error')) {
                    es.close();
                    appendChatMessage('bot', `Deployment failed: ${msg}`);
                    if (btn) {
                        btn.innerText = 'Failed';
                        btn.classList.add('bg-rose-600');
                    }
                }
            };
            es.onerror = () => {
                es.close();
                if (btn && btn.innerText === 'Running...') {
                    appendChatMessage('bot', `Deployment connection lost.`);
                    btn.innerText = 'Error';
                }
            };
            return;
        } else if (actionId === 'provision_database') {
            const dbName = params.db_name;
            if (!dbName) throw new Error('Missing db_name');
            const res = await fetch('api/system/provision_db.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Authorization': 'Bearer ' + token
                },
                body: `db_name=${encodeURIComponent(dbName)}`
            });
            const text = await res.text();
            if (res.ok) {
                appendChatMessage('bot', `Database provisioned successfully!\n${text}`);
                if (btn) btn.innerText = 'Completed';
            } else {
                appendChatMessage('bot', `Failed to provision database:\n${text}`);
                if (btn) {
                    btn.innerText = 'Failed';
                    btn.classList.add('bg-rose-600');
                }
            }
            return;
        }

        // Standard actions
        const response = await fetch('api/system/actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
            body: JSON.stringify({ action_id: actionId, params: params })
        });
        const data = await response.json();
        
        if (data.status === 'success') {
            appendChatMessage('bot', `Action completed successfully: ${data.message || JSON.stringify(data)}`);
            if (btn) btn.innerText = 'Completed';
        } else {
            appendChatMessage('bot', `Action failed: ${data.message}`);
            if (btn) {
                btn.innerText = 'Failed';
                btn.classList.add('bg-rose-600');
            }
        }
    } catch (err) {
        appendChatMessage('bot', `Error while executing action: ${err.message}`);
        if (btn) {
            btn.innerText = 'Error';
            btn.classList.add('bg-rose-600');
        }
    }
}

function appendChatMessage(sender, messageText, metadata = null) {
    const chatMessages = document.getElementById('sf-chat-messages');
    if (!chatMessages) return;

    let msgHtml = '';
    if (sender === 'user') {
        msgHtml = `
            <div class="flex items-end justify-end gap-2.5">
                <div class="px-3.5 py-2 rounded-2xl bg-cyan-500 text-white text-xs font-medium max-w-[82%] shadow-sm">
                    ${escapeHtml(messageText)}
                </div>
            </div>
        `;
    } else {
        let metaHtml = '';
        if (metadata) {
            let badgeClass = 'bg-slate-200 dark:bg-slate-700 text-slate-500';
            if (metadata.confidence === 'high') badgeClass = 'bg-emerald-500/20 text-emerald-500';
            if (metadata.confidence === 'low' || metadata.answer_type === 'service_error') badgeClass = 'bg-rose-500/20 text-rose-500';
            
            metaHtml = `<div class="flex gap-2 mt-2">`;
            if (metadata.confidence) {
                metaHtml += `<span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider ${badgeClass}">Conf: ${metadata.confidence}</span>`;
            }
            if (metadata.diagnostics_used) {
                metaHtml += `<span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-cyan-500/20 text-cyan-500">Live Diag</span>`;
            }
            metaHtml += `</div>`;
            
            if (metadata.proposed_actions && metadata.proposed_actions.length > 0) {
                metaHtml += `<div class="mt-3 space-y-2 border-t border-slate-200 dark:border-slate-700 pt-2">`;
                metadata.proposed_actions.forEach((act, idx) => {
                                        const btnId = 'action-btn-' + Date.now() + '-' + idx;
                    const paramStr = encodeURIComponent(JSON.stringify(act.params || {}));
                    
                    let extraInputs = '';
                    if (act.action_id === 'deploy_site') {
                        let currentVal = (act.params && act.params.repo_url && !act.params.repo_url.includes('...')) ? act.params.repo_url : '';
                        extraInputs = `<input type="text" id="input-${btnId}-repo_url" class="mt-1 w-full text-[10px] p-1.5 rounded bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:border-cyan-500" placeholder="Paste GitHub URL here..." value="${currentVal}" />`;
                    } else if (act.action_id === 'provision_database') {
                        let currentVal = (act.params && act.params.db_name && !act.params.db_name.includes('example')) ? act.params.db_name : '';
                        extraInputs = `<input type="text" id="input-${btnId}-db_name" class="mt-1 w-full text-[10px] p-1.5 rounded bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:border-cyan-500" placeholder="Type safe database_name here..." value="${currentVal}" />`;
                    }
                    
                    metaHtml += `
                        <div class="flex flex-col p-2 rounded bg-slate-200 dark:bg-slate-700/50">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-[10px] font-bold text-slate-800 dark:text-slate-200">${escapeHtml(act.label)}</div>
                                    <div class="text-[9px] text-slate-500 dark:text-slate-400">${escapeHtml(act.description)}</div>
                                </div>
                                <button id="${btnId}" onclick="approveServerAction('${act.action_id}', '${paramStr}', '${btnId}')" class="px-2 py-1 rounded bg-cyan-600 text-white text-[10px] font-bold hover:bg-cyan-500 transition-colors ml-2 flex-shrink-0">Approve</button>
                            </div>
                            ${extraInputs ? `<div class="mt-2">${extraInputs}</div>` : ''}
                        </div>
                    `;
                });
                metaHtml += `</div>`;
            }
        }
        
        let containerClass = "bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60";
        if (metadata && (metadata.confidence === 'low' || metadata.answer_type === 'service_error')) {
            containerClass = "bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/50";
        }
        
        msgHtml = `
            <div class="flex items-start gap-2.5">
                <div class="w-7 h-7 rounded-full bg-cyan-600/20 text-cyan-400 flex items-center justify-center text-xs font-bold shrink-0">SF</div>
                <div class="px-3.5 py-2.5 rounded-2xl ${containerClass} text-xs text-slate-800 dark:text-slate-200 leading-relaxed max-w-[85%]">
                    ${escapeHtml(messageText)}
                    ${metaHtml}
                </div>
            </div>
        `;
    }
    chatMessages.insertAdjacentHTML('beforeend', msgHtml);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}


function escapeHtml(text) {
    return text.replace(/[&<>"']/g, function(m) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[m];
    });
}

