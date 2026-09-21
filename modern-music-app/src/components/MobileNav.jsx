import { Home, Disc, Music, Heart, ListMusic } from 'lucide-react';
import { NavLink } from 'react-router-dom';

export default function MobileNav() {
  const tabs = [
    { label: 'Home', path: '/', icon: Home },
    { label: 'Albums', path: '/albums', icon: Disc },
    { label: 'Songs', path: '/songs', icon: Music },
    { label: 'Liked', path: '/liked', icon: Heart },
    { label: 'Playlists', path: '/playlists', icon: ListMusic },
  ];

  return (
    <nav 
      aria-label="Mobile Navigation"
      className="md:hidden fixed bottom-0 left-0 right-0 z-30 bg-slate-950/95 backdrop-blur-2xl border-t border-white/10 px-2 pt-1 pb-[max(env(safe-area-inset-bottom,0px),0.5rem)] select-none shadow-[0_-8px_24px_rgba(0,0,0,0.6)] h-[calc(3.4rem+max(env(safe-area-inset-bottom,0px),0.5rem))]"
    >
      <div className="flex items-center justify-around max-w-lg mx-auto h-11">
        {tabs.map(({ label, path, icon: Icon }) => (
          <NavLink
            key={path}
            to={path}
            end={path === '/'}
            className={({ isActive }) =>
              `flex flex-col items-center justify-center flex-1 py-1.5 px-2 rounded-xl transition-all duration-200 active:scale-90 ${
                isActive
                  ? 'text-purple-400 font-semibold'
                  : 'text-slate-400 hover:text-slate-200'
              }`
            }
          >
            {({ isActive }) => (
              <>
                <div className={`relative p-1 rounded-full transition-colors ${isActive ? 'bg-purple-500/15' : ''}`}>
                  <Icon size={20} strokeWidth={isActive ? 2.5 : 1.8} />
                </div>
                <span className="text-[10px] tracking-tight mt-0.5 leading-tight">{label}</span>
              </>
            )}
          </NavLink>
        ))}
      </div>
    </nav>
  );
}

