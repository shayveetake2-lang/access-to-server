import { Home, Search, Library, ListMusic, Mic2, Settings, ShieldAlert, Music, Volume2, Globe } from 'lucide-react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { usePlayer } from '../context/PlayerContext';
import { getMediaPortalUrl, getCoverArtUrl, DEFAULT_COVER_ART } from '../utils/api';

export default function Sidebar() {
  const { user, getAuthParams } = useAuth();
  const { currentTrack, isPlaying } = usePlayer();
  
  const navItems = [
    { icon: <Home size={19} />, label: 'Home', path: '/' },
    { icon: <Search size={19} />, label: 'Browse', path: '/albums' },
    { icon: <Mic2 size={19} />, label: 'Artists', path: '/artists' },
    { icon: <Library size={19} />, label: 'Top 100 Songs', path: '/songs' },
    { icon: <ListMusic size={19} />, label: 'My Playlists', path: '/playlists' },
    { icon: <Globe size={19} />, label: 'Public Playlists', path: '/public-playlists' },
    { icon: <Settings size={19} />, label: 'Settings', path: '/settings' },
  ];

  const isAdmin = user?.role === 'admin' || user?.isAdmin === true || ['admin', 'musicadmin'].includes(user?.username?.toLowerCase());
  if (isAdmin) {
    navItems.push({ icon: <ShieldAlert size={19} />, label: 'Admin Panel', path: '/admin' });
  }

  return (
    <div className="w-64 bg-slate-950/80 backdrop-blur-xl border-r border-white/5 flex flex-col h-full sticky top-0 hidden md:flex select-none">
      {/* Brand Header */}
      <div className="p-6 pb-4">
        <NavLink to="/" className="flex items-center gap-3 group focus:outline-none" title="Aether Audio Home">
          <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-500 via-indigo-500 to-purple-600 flex items-center justify-center shadow-[0_0_16px_rgba(168,85,247,0.45)] shrink-0 transition-transform duration-300 group-hover:scale-105">
            <svg className="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round">
              <path d="M4 10v4" />
              <path d="M8 6v12" />
              <path d="M12 3v18" />
              <path d="M16 7v10" />
              <path d="M20 10v4" />
            </svg>
          </div>
          <span className="text-2xl font-bold bg-gradient-to-r from-purple-300 via-white to-indigo-200 bg-clip-text text-transparent tracking-tight leading-none group-hover:from-purple-200 group-hover:to-white transition-all">
            Aether
          </span>
        </NavLink>
      </div>
      
      {/* Navigation */}
      <nav className="flex-1 px-3 space-y-1.5 mt-3 overflow-y-auto">
        <div className="px-3 py-1 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Menu</div>
        {navItems.map((item) => (
          <NavLink 
            key={item.label}
            to={item.path}
            className={({ isActive }) => 
              `flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all duration-200 text-xs font-medium ${
                isActive 
                  ? 'bg-purple-500/15 text-purple-300 font-semibold shadow-[inset_3px_0_0_0_rgba(168,85,247,1)]' 
                  : 'text-slate-400 hover:text-slate-200 hover:bg-white/5'
              }`
            }
          >
            {item.icon}
            {item.label}
          </NavLink>
        ))}
      </nav>

      {/* Now Playing Mini Widget on Desktop Sidebar */}
      {currentTrack && (
        <div className="px-3 pb-2">
          <div className="p-2.5 rounded-2xl bg-slate-900/60 border border-white/5 flex items-center gap-3 backdrop-blur-md shadow-lg">
            <div className="w-10 h-10 rounded-xl bg-slate-800 shrink-0 overflow-hidden relative shadow-sm border border-white/5 flex items-center justify-center">
              {currentTrack.coverArt ? (
                <img 
                  src={getCoverArtUrl(currentTrack.coverArt, getAuthParams(user))} 
                  className="w-full h-full object-cover" 
                  alt="" 
                  onError={(e) => {
                    if (e.currentTarget.src !== DEFAULT_COVER_ART) {
                      e.currentTarget.src = DEFAULT_COVER_ART;
                    }
                  }}
                />
              ) : (
                <Music size={16} className="text-purple-400" />
              )}
              {isPlaying && (
                <div className="absolute inset-0 bg-black/40 flex items-center justify-center">
                  <Volume2 size={14} className="text-purple-400 animate-pulse" />
                </div>
              )}
            </div>
            <div className="min-w-0 flex-1">
              <div className="text-xs font-semibold text-white truncate leading-tight">{currentTrack.title}</div>
              <div className="text-[10px] text-slate-400 truncate mt-0.5">{currentTrack.artist || 'Unknown Artist'}</div>
            </div>
          </div>
        </div>
      )}

      {/* Media Portal Link & Shortcuts Footer */}
      <div className="p-3 pt-0 space-y-2">
        <a 
          href={getMediaPortalUrl()} 
          className="flex items-center justify-between p-3 rounded-2xl bg-gradient-to-br from-purple-900/30 to-indigo-900/30 border border-purple-500/20 hover:border-purple-500/40 transition-all group"
        >
          <div>
            <h4 className="text-xs font-semibold text-purple-300 group-hover:text-purple-200 flex items-center gap-1.5">
              <span>←</span> Media Portal
            </h4>
            <p className="text-[10px] text-slate-400 mt-0.5">Movies, TV &amp; Server Flow</p>
          </div>
          <span className="text-slate-500 group-hover:text-purple-300 transition-colors text-xs font-mono">↗</span>
        </a>

        <div className="px-2 py-1 text-[10px] text-slate-500 font-mono flex items-center justify-between">
          <span>Space: Play/Pause</span>
          <span>M: Mute</span>
        </div>
      </div>
    </div>
  );
}
