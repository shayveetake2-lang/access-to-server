import { createContext, useState, useContext, useCallback } from 'react';
import { CheckCircle2, XCircle, AlertCircle } from 'lucide-react';

const ToastContext = createContext();

export function useToast() {
  return useContext(ToastContext);
}

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);

  const showToast = useCallback((message, type = 'success') => {
    const id = Date.now();
    setToasts(prev => [...prev, { id, message, type }]);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
      setToasts(prev => prev.filter(t => t.id !== id));
    }, 3000);
  }, []);

  return (
    <ToastContext.Provider value={{ showToast }}>
      {children}
      
      {/* Toast Container */}
      <div className="fixed bottom-48 md:bottom-28 left-1/2 -translate-x-1/2 z-[200] flex flex-col gap-2 pointer-events-none w-max max-w-[90vw] items-center">
        {toasts.map(toast => (
          <div 
            key={toast.id}
            className={`flex items-center gap-3 px-4 py-3 rounded-xl shadow-2xl backdrop-blur-xl border pointer-events-auto animate-in slide-in-from-bottom-5 fade-in duration-300
              ${toast.type === 'success' ? 'bg-emerald-500/20 border-emerald-500/30 text-emerald-200' : 
                toast.type === 'error' ? 'bg-red-500/20 border-red-500/30 text-red-200' : 
                'bg-slate-800/80 border-slate-600 text-white'}`}
          >
            {toast.type === 'success' && <CheckCircle2 size={18} className="text-emerald-400" />}
            {toast.type === 'error' && <XCircle size={18} className="text-red-400" />}
            {toast.type === 'info' && <AlertCircle size={18} className="text-blue-400" />}
            <span className="font-medium text-sm">{toast.message}</span>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  );
}
