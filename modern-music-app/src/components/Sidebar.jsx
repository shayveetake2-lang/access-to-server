import { Home, Search, Library, ListMusic, Mic2, Settings, ShieldAlert } from 'lucide-react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export default function Sidebar() {
  const { user } = useAuth();
  
  const navItems = [
    { icon: <Home size={20} />, label: 'Home', path: '/' },
    { icon: <Search size={20} />, label: 'Browse', path: '/albums' },
    { icon: <Mic2 size={20} />, label: 'Artists', path: '/artists' },
    { icon: <Library size={20} />, label: 'All Songs', path: '/songs' },
    { icon: <ListMusic size={20} />, label: 'My Playlists', path: '/playlists' },
    { icon: <Settings size={20} />, label: 'Settings', path: '/settings' },
  ];

  if (user?.username?.toLowerCase() === 'admin') {
    navItems.push({ icon: <ShieldAlert size={20} />, label: 'Admin Panel', path: '/admin' });
  }

  return (
    <div className="w-64 bg-slate-950/80 backdrop-blur-xl border-r border-white/5 flex flex-col h-full sticky top-0 hidden md:flex">
      <div className="p-6">
        <h1 className="text-2xl font-bold bg-gradient-to-r from-purple-400 to-indigo-400 bg-clip-text text-transparent flex items-center gap-2">
          <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center">
            <div className="w-3 h-3 bg-white rounded-full"></div>
          </div>
          Aether
        </h1>
      </div>
      
      <nav className="flex-1 px-4 space-y-2 mt-4">
        {navItems.map((item) => (
          <NavLink 
            key={item.label}
            to={item.path}
            className={({ isActive }) => 
              `flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 ${
                isActive 
                  ? 'bg-purple-500/10 text-purple-400 font-medium' 
                  : 'text-slate-400 hover:text-slate-200 hover:bg-white/5'
              }`
            }
          >
            {item.icon}
            {item.label}
          </NavLink>
        ))}
      </nav>

      <div className="p-4">
        <a 
          href="/access-to-server/media.html" 
          className="flex items-center justify-between p-3 rounded-2xl bg-gradient-to-br from-purple-900/30 to-indigo-900/30 border border-purple-500/20 hover:border-purple-500/40 transition-all group"
        >
          <div>
            <h4 className="text-xs font-semibold text-purple-300 group-hover:text-purple-200 flex items-center gap-1.5">
              <span>←</span> Media Portal
            </h4>
            <p className="text-[11px] text-slate-400">Movies, TV &amp; Network</p>
          </div>
          <span className="text-slate-500 group-hover:text-purple-300 transition-colors text-xs">↗</span>
        </a>
      </div>
    </div>
  );
}
