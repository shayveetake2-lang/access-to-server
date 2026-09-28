import { User, LogOut, ArrowLeft, Globe, Settings as SettingsIcon, ShieldAlert } from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { getMediaPortalUrl } from '../utils/api';
import SearchBar from './SearchBar';

export default function TopBar() {
  const [showProfileMenu, setShowProfileMenu] = useState(false);
  const profileRef = useRef(null);
  
  const navigate = useNavigate();
  const location = useLocation();
  const { user, logout, isAdmin } = useAuth();
  
  const PRIMARY_PATHS = new Set(['/', '/albums', '/artists', '/songs', '/liked', '/playlists', '/public-playlists', '/settings', '/help', '/admin']);
  const canGoBack = !PRIMARY_PATHS.has(location.pathname);

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (profileRef.current && !profileRef.current.contains(event.target)) {
        setShowProfileMenu(false);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <header className="min-h-16 md:min-h-20 flex items-center justify-between px-3 sm:px-4 md:px-8 border-b border-slate-200 dark:border-white/5 bg-white/80 dark:bg-slate-950/80 backdrop-blur-xl sticky top-0 z-50 pointer-events-auto pt-[max(env(safe-area-inset-top),16px)] pb-2 md:pb-0 gap-2 sm:gap-3 transition-colors">
      
      {/* Mobile Brand Logo */}
      <Link to="/" className="md:hidden flex items-center gap-2 shrink-0 group focus:outline-none" title="Aether Audio Home">
        <div className="w-8 h-8 rounded-xl bg-gradient-to-br from-purple-500 via-indigo-500 to-purple-600 flex items-center justify-center shadow-[0_0_12px_rgba(168,85,247,0.4)] shrink-0">
          <svg className="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round">
            <path d="M4 10v4" />
            <path d="M8 6v12" />
            <path d="M12 3v18" />
            <path d="M16 7v10" />
            <path d="M20 10v4" />
          </svg>
        </div>
        <span className="text-base font-bold bg-gradient-to-r from-purple-600 dark:from-purple-300 via-indigo-700 dark:via-white to-purple-600 dark:to-indigo-200 bg-clip-text text-transparent tracking-tight hidden xs:inline">
          Aether
        </span>
      </Link>

      {/* Optional Mobile Back Button */}
      {canGoBack && (
        <button 
          onClick={() => navigate(-1)} 
          className="p-2 -ml-1 rounded-xl text-slate-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/5 active:scale-95 transition-all shrink-0"
          title="Go Back"
          aria-label="Go Back"
        >
          <ArrowLeft size={19} />
        </button>
      )}

      {/* Modular Search Bar with 300ms Debounce and "Searching..." UI */}
      <SearchBar />

      {/* User Profile Actions */}
      <div className="flex items-center gap-2 shrink-0 ml-1" ref={profileRef}>
        <div className="relative">
          <button 
            onClick={() => setShowProfileMenu(!showProfileMenu)}
            className="flex items-center gap-2 hover:bg-slate-100 dark:hover:bg-white/5 p-1.5 rounded-xl transition-colors active:scale-95"
            aria-label="User profile menu"
          >
            <div className="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shrink-0 shadow-md">
              <User size={15} className="text-white" />
            </div>
            <div className="hidden md:flex flex-col items-start text-left">
              <span className="text-sm font-medium text-gray-900 dark:text-slate-200">{user?.username}</span>
              <span className="text-xs text-purple-600 dark:text-purple-400 uppercase tracking-wider font-semibold">
                [{isAdmin ? 'Admin' : 'Standard'}]
              </span>
            </div>
          </button>
          
          {showProfileMenu && (
            <div className="absolute right-0 top-full mt-2 w-52 bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl border border-slate-200 dark:border-white/10 rounded-2xl shadow-2xl py-1.5 overflow-hidden z-50 animate-in fade-in zoom-in-95 duration-150">
              <div className="px-4 py-2.5 border-b border-slate-100 dark:border-white/10">
                <p className="text-sm font-semibold text-gray-900 dark:text-white truncate">{user?.username}</p>
                <p className="text-[11px] text-purple-600 dark:text-purple-400 uppercase tracking-wider mt-0.5 font-medium">
                  {isAdmin ? 'Administrator' : 'Standard User'}
                </p>
              </div>

              {isAdmin && (
                <Link 
                  to="/admin" 
                  onClick={() => setShowProfileMenu(false)}
                  className="flex items-center gap-2.5 px-4 py-2.5 text-xs font-medium text-purple-700 dark:text-purple-300 hover:text-purple-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/5 transition-colors"
                >
                  <ShieldAlert size={15} className="text-purple-600 dark:text-purple-400" /> Admin Panel
                </Link>
              )}
              
              <Link 
                to="/settings" 
                onClick={() => setShowProfileMenu(false)}
                className="flex items-center gap-2.5 px-4 py-2.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:text-gray-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/5 transition-colors"
              >
                <SettingsIcon size={15} className="text-slate-500 dark:text-slate-400" /> Settings
              </Link>
              
              <a 
                href={getMediaPortalUrl()} 
                className="flex items-center justify-between px-4 py-2.5 text-xs font-medium text-purple-700 dark:text-purple-300 hover:text-purple-900 dark:hover:text-purple-200 hover:bg-slate-100 dark:hover:bg-white/5 transition-colors border-t border-slate-100 dark:border-white/5"
              >
                <span className="flex items-center gap-2.5">
                  <Globe size={15} className="text-purple-600 dark:text-purple-400" /> Media Portal
                </span>
                <span className="text-slate-400 dark:text-slate-500 text-[10px]">↗</span>
              </a>

              <div className="border-t border-slate-100 dark:border-white/10 pt-1">
                <button 
                  onClick={handleLogout}
                  className="w-full flex items-center gap-2.5 px-4 py-2.5 text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors text-left"
                >
                  <LogOut size={15} /> Log Out
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
