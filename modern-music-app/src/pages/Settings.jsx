import { useState, useEffect } from 'react';
import { Moon, Sun, Mail, Music, HelpCircle } from 'lucide-react';
import { useToast } from '../context/ToastContext';

export default function Settings() {
  const [darkMode, setDarkMode] = useState(true);
  const { showToast } = useToast();
  
  useEffect(() => {
    const stored = localStorage.getItem('aether_theme');
    if (stored === 'light') {
      setDarkMode(false);
      document.documentElement.classList.remove('dark');
    } else {
      document.documentElement.classList.add('dark');
    }
  }, []);

  const toggleTheme = () => {
    const newMode = !darkMode;
    setDarkMode(newMode);
    localStorage.setItem('aether_theme', newMode ? 'dark' : 'light');
    if (newMode) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
    showToast(`Theme updated to ${newMode ? 'Dark' : 'Light'} Mode!`, 'success');
  };

  return (
    <div className="max-w-4xl mx-auto pb-24">
      <h1 className="text-3xl font-bold text-white mb-8 mt-4">Account Settings</h1>

      <div className="space-y-6">
        {/* Appearance */}
        <div className="bg-slate-900/50 backdrop-blur-sm border border-white/5 rounded-2xl p-6">
          <h3 className="text-xl font-semibold text-white mb-4">Appearance</h3>
          <div className="flex items-center justify-between">
            <div>
              <p className="text-slate-300 font-medium">Dark Mode</p>
              <p className="text-slate-500 text-sm">Switch between light and dark themes</p>
            </div>
            <button 
              onClick={toggleTheme}
              className={`relative inline-flex h-7 w-14 items-center rounded-full transition-colors ${darkMode ? 'bg-purple-500' : 'bg-slate-600'}`}
            >
              <span className={`inline-block h-5 w-5 transform rounded-full bg-white transition-transform ${darkMode ? 'translate-x-8' : 'translate-x-1'}`} />
            </button>
          </div>
        </div>

        {/* Request a Song */}
        <div className="bg-slate-900/50 backdrop-blur-sm border border-white/5 rounded-2xl p-6">
          <h3 className="text-xl font-semibold text-white mb-4 flex items-center gap-2"><Music size={20} className="text-purple-400" /> Request a Song</h3>
          <p className="text-slate-400 mb-4">Can't find your favorite track? Request the Server Admin to add it to the library.</p>
          <a 
            href="mailto:admin@local.host?subject=Song Request for Aether Audio"
            className="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white px-6 py-3 rounded-xl font-medium transition-colors border border-white/5"
          >
            <Mail size={18} /> Email Admin
          </a>
        </div>

        {/* Contact Admin */}
        <div className="bg-slate-900/50 backdrop-blur-sm border border-white/5 rounded-2xl p-6">
          <h3 className="text-xl font-semibold text-white mb-4 flex items-center gap-2"><HelpCircle size={20} className="text-indigo-400" /> Need Help?</h3>
          <p className="text-slate-400 mb-4">Having trouble with your account or finding a bug? Contact the server administrator for support.</p>
          <a 
            href="mailto:admin@local.host?subject=Aether Audio Support Request"
            className="inline-flex items-center gap-2 bg-indigo-500/20 hover:bg-indigo-500/30 text-indigo-300 px-6 py-3 rounded-xl font-medium transition-colors border border-indigo-500/20"
          >
            <Mail size={18} /> Contact Support
          </a>
        </div>

      </div>
    </div>
  );
}
