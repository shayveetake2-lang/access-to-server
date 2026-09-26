/**
 * js/serverflow_core.js — ServerFlow Unified UI Core Module
 * Provides unified theme management, navigation state,
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
            const profileMenu = document.getElementById('sf-profile-menu');
            if (profileMenu && !profileMenu.classList.contains('hidden')) profileMenu.classList.add('hidden');
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

// User Profile Dropdown
function initProfileDropdown() {
    const profileBtn = document.getElementById('sf-profile-btn');
    const profileMenu = document.getElementById('sf-profile-menu');
    if (!profileBtn || !profileMenu) return;

    profileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        profileMenu.classList.toggle('hidden');
    });

    document.addEventListener('click', (e) => {
        if (!profileMenu.contains(e.target) && e.target !== profileBtn) {
            profileMenu.classList.add('hidden');
        }
    });
}

function handlePlexRouting(e) {
    if (e && e.preventDefault) e.preventDefault();
    const host = window.location.hostname;
    const isLocal = host === "127.0.0.1" || host === "localhost";
    if (!isLocal || host.includes('serverflow') || host.includes('cloudflare') || host.startsWith("10.") || host.startsWith("192.168.")) {
        window.open("https://app.plex.tv/web", "_blank");
    } else {
        window.open("http://" + host + ":32400/web", "_blank");
    }
}
window.handlePlexRouting = handlePlexRouting;
