import re

with open('js/serverflow_core.js', 'r') as f:
    js_code = f.read()

submit_chat_code = """
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
    
    try {
        const response = await fetch('api/system/actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action_id: actionId, params: params })
        });
        const data = await response.json();
        
        if (data.status === 'success') {
            appendChatMessage('bot', `Action completed successfully: ${data.message}`);
            if (btn) btn.innerText = 'Completed';
        } else {
            appendChatMessage('bot', `Action failed: ${data.message}`);
            if (btn) {
                btn.innerText = 'Failed';
                btn.classList.add('bg-rose-600');
            }
        }
    } catch (err) {
        appendChatMessage('bot', 'Network error while executing action.');
        if (btn) btn.innerText = 'Error';
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
                    metaHtml += `
                        <div class="flex items-center justify-between p-2 rounded bg-slate-200 dark:bg-slate-700/50">
                            <div>
                                <div class="text-[10px] font-bold text-slate-800 dark:text-slate-200">${escapeHtml(act.label)}</div>
                                <div class="text-[9px] text-slate-500 dark:text-slate-400">${escapeHtml(act.description)}</div>
                            </div>
                            <button id="${btnId}" onclick="approveServerAction('${act.action_id}', '${paramStr}', '${btnId}')" class="px-2 py-1 rounded bg-cyan-600 text-white text-[10px] font-bold hover:bg-cyan-500 transition-colors">Approve</button>
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
"""

new_js = re.sub(
    r"async function submitServerFlowChat\(\).*?function escapeHtml\(text\)",
    submit_chat_code + "\n\nfunction escapeHtml(text)",
    js_code,
    flags=re.DOTALL
)

with open('js/serverflow_core.js', 'w') as f:
    f.write(new_js)
