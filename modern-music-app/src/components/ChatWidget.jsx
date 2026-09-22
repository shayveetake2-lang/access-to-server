import { useState, useRef, useEffect } from 'react';
import { MessageSquare, X, Send, Bot, Sparkles, Wrench, RefreshCw, Server, Database, Globe, Film } from 'lucide-react';
import { getBaseUrl } from '../utils/api';

function getCandidateChatEndpoints() {
  const base = getBaseUrl();
  const host = (typeof window !== 'undefined' && window.location.hostname) ? window.location.hostname : '10.247.192.231';
  const endpoints = [`${base}/api/chat.php`];
  if (host && host !== 'localhost') {
    endpoints.push(`http://${host}:5005/api/chat`);
  }
  endpoints.push('http://10.247.192.231:5005/api/chat');
  return Array.from(new Set(endpoints));
}

const QUICK_ACTIONS = [
  { label: '!fixplex', desc: 'Fix Plex', icon: Film, command: '!fixplex' },
  { label: '!fixweb', desc: 'Fix Web', icon: Globe, command: '!fixweb' },
  { label: '!fixdb', desc: 'Fix DB', icon: Database, command: '!fixdb' },
];

export default function ChatWidget() {
  const [isOpen, setIsOpen] = useState(false);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [messages, setMessages] = useState([
    {
      id: 'welcome',
      role: 'assistant',
      content: "👋 Hi! I'm Aether AI on your MacBook Pro 2011 home server. Ask me anything about your music library or use the quick repair commands below!",
      time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    }
  ]);

  const messagesEndRef = useRef(null);
  const inputRef = useRef(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    if (isOpen) {
      scrollToBottom();
      setTimeout(() => inputRef.current?.focus(), 150);
    }
  }, [isOpen, messages]);

  const sendMessage = async (textToSend) => {
    const text = (textToSend || input).trim();
    if (!text || loading) return;

    const userMessage = {
      id: `user-${Date.now()}`,
      role: 'user',
      content: text,
      time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    };

    setMessages(prev => [...prev, userMessage]);
    setInput('');
    setLoading(true);

    const endpoints = getCandidateChatEndpoints();
    let replyContent = null;
    let lastError = null;

    for (const endpoint of endpoints) {
      try {
        const response = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            message: text,
            prompt: text,
            history: messages.map(m => ({ role: m.role, content: m.content }))
          })
        });

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        replyContent = 
          data.response || 
          data.reply || 
          data.answer ||
          data.message || 
          data.content || 
          (typeof data === 'string' ? data : JSON.stringify(data));

        if (replyContent) {
          break; // Success!
        }
      } catch (err) {
        lastError = err;
        console.debug(`[Aether ChatWidget] Candidate ${endpoint} failed:`, err);
      }
    }

    if (replyContent) {
      const botMessage = {
        id: `bot-${Date.now()}`,
        role: 'assistant',
        content: replyContent,
        time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
      };
      setMessages(prev => [...prev, botMessage]);
    } else {
      const errorMessage = {
        id: `err-${Date.now()}`,
        role: 'assistant',
        isError: true,
        content: `⚠️ Could not reach Aether AI (${lastError?.message || 'Connection failed'}). Ensure the server web service or Python bot is running.`,
        time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
      };
      setMessages(prev => [...prev, errorMessage]);
    }

    setLoading(false);
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  };

  return (
    <>
      {/* Floating Action Button (FAB) — sits above the sticky bottom player */}
      <button
        onClick={() => setIsOpen(prev => !prev)}
        className="fixed bottom-[130px] md:bottom-8 right-4 sm:right-6 z-[70] w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-gradient-to-tr from-purple-600 via-indigo-600 to-purple-500 text-white shadow-[0_0_24px_rgba(168,85,247,0.55)] hover:shadow-[0_0_36px_rgba(168,85,247,0.8)] flex items-center justify-center transition-all duration-300 hover:scale-105 active:scale-95 border border-purple-400/30 group"
        title={isOpen ? "Close Aether AI" : "Chat with Aether AI"}
        aria-label="Toggle Aether AI Chat"
      >
        {isOpen ? (
          <X size={22} className="transition-transform duration-200 group-hover:rotate-90" />
        ) : (
          <>
            <Bot size={24} className="transition-transform duration-200 group-hover:scale-110" />
            <span className="absolute -top-1 -right-1 w-3.5 h-3.5 bg-emerald-400 border-2 border-slate-950 rounded-full animate-pulse"></span>
          </>
        )}
      </button>

      {/* Glassmorphic Chat Window */}
      {isOpen && (
        <div className="fixed bottom-[190px] md:bottom-24 right-3 sm:right-6 z-[70] w-[calc(100vw-1.5rem)] sm:w-96 max-w-sm h-[480px] sm:h-[530px] rounded-3xl bg-slate-950/90 backdrop-blur-2xl border border-white/10 shadow-[0_20px_60px_rgba(0,0,0,0.85)] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
          
          {/* Header */}
          <div className="p-3.5 sm:p-4 border-b border-white/10 bg-gradient-to-r from-purple-950/40 via-slate-900/60 to-indigo-950/40 flex items-center justify-between shrink-0">
            <div className="flex items-center gap-2.5">
              <div className="w-8 h-8 rounded-xl bg-purple-500/20 border border-purple-500/30 flex items-center justify-center text-purple-300 shadow-inner">
                <Bot size={18} />
              </div>
              <div>
                <div className="flex items-center gap-1.5">
                  <h3 className="text-sm font-bold text-white tracking-tight">Aether AI</h3>
                  <span className="inline-flex items-center px-1.5 py-0.2 rounded-full text-[9px] font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/30">
                    Groq Llama 3
                  </span>
                </div>
                <div className="flex items-center gap-1.5 text-[11px] text-slate-400">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                  <span>{typeof window !== 'undefined' ? (window.location.hostname || 'Local Server') : 'Server'}:5005</span>
                </div>
              </div>
            </div>

            <button
              onClick={() => setIsOpen(false)}
              className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
              aria-label="Close chat"
            >
              <X size={18} />
            </button>
          </div>

          {/* Quick Action Buttons (Header Bar) */}
          <div className="px-3 py-2 border-b border-white/5 bg-slate-900/40 flex items-center gap-1.5 overflow-x-auto no-scrollbar shrink-0">
            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider pl-1 flex items-center gap-1 shrink-0">
              <Wrench size={10} /> Quick Fix:
            </span>
            {QUICK_ACTIONS.map(action => {
              const Icon = action.icon;
              return (
                <button
                  key={action.command}
                  onClick={() => sendMessage(action.command)}
                  disabled={loading}
                  className="px-2.5 py-1 rounded-full bg-white/5 hover:bg-purple-500/20 border border-white/10 hover:border-purple-500/30 text-[11px] font-medium text-slate-300 hover:text-purple-300 flex items-center gap-1.5 transition-all shrink-0 active:scale-95 disabled:opacity-50"
                  title={`Trigger ${action.command}`}
                >
                  <Icon size={11} className="text-purple-400" />
                  <span>{action.label}</span>
                </button>
              );
            })}
          </div>

          {/* Message History Feed */}
          <div className="flex-1 p-3.5 sm:p-4 overflow-y-auto space-y-3 touch-scroll">
            {messages.map(msg => {
              const isUser = msg.role === 'user';
              return (
                <div
                  key={msg.id}
                  className={`flex flex-col ${isUser ? 'items-end' : 'items-start'}`}
                >
                  <div
                    className={`max-w-[85%] rounded-2xl px-3.5 py-2.5 text-xs sm:text-[13px] leading-relaxed break-words shadow-sm ${
                      isUser
                        ? 'bg-purple-600 text-white rounded-br-xs shadow-[0_4px_12px_rgba(168,85,247,0.3)]'
                        : msg.isError
                        ? 'bg-red-950/60 text-red-200 border border-red-500/30 rounded-bl-xs'
                        : 'bg-slate-900/90 text-slate-200 border border-white/10 rounded-bl-xs'
                    }`}
                  >
                    {msg.content}
                  </div>
                  <span className="text-[10px] text-slate-500 mt-1 px-1">
                    {msg.time}
                  </span>
                </div>
              );
            })}

            {loading && (
              <div className="flex items-center gap-2 text-xs text-purple-400 py-1 px-2">
                <RefreshCw size={13} className="animate-spin" />
                <span className="text-[11px] text-slate-400 animate-pulse">Aether is thinking...</span>
              </div>
            )}
            <div ref={messagesEndRef} />
          </div>

          {/* Input & Footer Bar */}
          <div className="p-3 border-t border-white/10 bg-slate-900/80 shrink-0">
            <form
              onSubmit={(e) => {
                e.preventDefault();
                sendMessage();
              }}
              className="flex items-center gap-2"
            >
              <input
                ref={inputRef}
                type="text"
                value={input}
                onChange={(e) => setInput(e.target.value)}
                onKeyDown={handleKeyDown}
                placeholder="Ask Aether or type !fix..."
                disabled={loading}
                className="flex-1 bg-slate-950/80 border border-white/10 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-colors disabled:opacity-50"
              />
              <button
                type="submit"
                disabled={loading || !input.trim()}
                className="w-9 h-9 rounded-xl bg-purple-500 hover:bg-purple-400 active:scale-95 text-white flex items-center justify-center transition-all disabled:opacity-40 disabled:hover:bg-purple-500 shadow-md shadow-purple-500/20 shrink-0"
                aria-label="Send message"
              >
                <Send size={15} />
              </button>
            </form>
          </div>

        </div>
      )}
    </>
  );
}

