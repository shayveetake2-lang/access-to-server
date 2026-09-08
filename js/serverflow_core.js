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

function initGlobalKeyboardListeners() {
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const notifMenu = document.getElementById('sf-notification-menu');
            const profileMenu = document.getElementById('sf-profile-menu');
            const chatWidget = document.getElementById('sf-chat-widget');
            if (notifMenu && !notifMenu.classList.contains('hidden')) notifMenu.classList.add('hidden');
            if (profileMenu && !profileMenu.classList.contains('hidden')) profileMenu.classList.add('hidden');
            if (chatWidget && !chatWidget.classList.contains('hidden')) toggleServerFlowChat();
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

    // Append User Message Bubble
    appendChatMessage('user', userText);
    chatInput.value = '';

    // Show Typing Indicator
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
        const response = await fetch('/api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: userText })
        });
        const data = await response.json();
        
        const typingEl = document.getElementById(typingId);
        if (typingEl) typingEl.remove();

        if (data.status === 'success' && data.reply) {
            appendChatMessage('bot', data.reply);
        } else {
            appendChatMessage('bot', data.message || 'Sorry, I ran into an error processing your request.');
        }
    } catch (err) {
        const typingEl = document.getElementById(typingId);
        if (typingEl) typingEl.remove();
        appendChatMessage('bot', 'Network error connecting to ServerFlow Help assistant.');
    }
}

function appendChatMessage(sender, messageText) {
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
        msgHtml = `
            <div class="flex items-start gap-2.5">
                <div class="w-7 h-7 rounded-full bg-cyan-600/20 text-cyan-400 flex items-center justify-center text-xs font-bold shrink-0">SF</div>
                <div class="px-3.5 py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 text-xs text-slate-800 dark:text-slate-200 leading-relaxed max-w-[85%]">
                    ${escapeHtml(messageText)}
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

