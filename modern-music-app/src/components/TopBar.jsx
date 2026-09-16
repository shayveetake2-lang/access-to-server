import { Search, User, LogOut } from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { usePlayer } from '../context/PlayerContext';
import { useAuth } from '../context/AuthContext';

export default function TopBar() {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState(null);
  const [loading, setLoading] = useState(false);
  const [showDropdown, setShowDropdown] = useState(false);
  const [showProfileMenu, setShowProfileMenu] = useState(false);
  
  const dropdownRef = useRef(null);
  const profileRef = useRef(null);
  
  const navigate = useNavigate();
  const { playQueue } = usePlayer();
  const { user, logout, getAuthParams } = useAuth();

  useEffect(() => {
    const fetchResults = async () => {
      if (query.length < 2) {
        setResults(null);
        return;
      }
      setLoading(true);
      try {
        const response = await fetch(`/ampache/public/rest/index.php?action=search3&query=${encodeURIComponent(query)}&songCount=5&albumCount=5&artistCount=5&${getAuthParams(user)}`);
        const data = await response.json();
        if (data?.['subsonic-response']?.status === 'ok') {
          setResults(data['subsonic-response'].searchResult3);
          setShowDropdown(true);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };

    const debounce = setTimeout(() => {
      fetchResults();
    }, 300);

    return () => clearTimeout(debounce);
  }, [query, user]);

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setShowDropdown(false);
      }
      if (profileRef.current && !profileRef.current.contains(event.target)) {
        setShowProfileMenu(false);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const handleResultClick = () => {
    setShowDropdown(false);
    setQuery('');
  };

  const playTrack = (song) => {
    playQueue([song], 0);
  };

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <header className="h-20 flex items-center justify-between px-4 md:px-8 border-b border-white/5 bg-slate-950/50 backdrop-blur-md sticky top-0 z-40">
      
      {/* Search Bar */}
      <div className="flex-1 max-w-xl relative" ref={dropdownRef}>
        <div className="relative group">
          <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-purple-400 transition-colors" size={20} />
          <input 
            type="text" 
            placeholder="Search for songs, artists, or albums..." 
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onFocus={() => { if(results) setShowDropdown(true); }}
            className="w-full bg-slate-900/50 border border-white/10 rounded-full py-2.5 pl-12 pr-4 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition-all placeholder:text-slate-500"
          />
          {loading && (
            <div className="absolute right-4 top-1/2 -translate-y-1/2">
              <div className="w-4 h-4 border-2 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
            </div>
          )}
        </div>

        {/* Dropdown Results */}
        {showDropdown && results && (
          <div className="absolute top-full mt-2 w-full bg-slate-900 border border-white/10 rounded-xl shadow-2xl overflow-hidden max-h-[70vh] overflow-y-auto">
            {!results.song && !results.album && !results.artist ? (
              <div className="p-4 text-center text-slate-400 text-sm">No results found for "{query}"</div>
            ) : (
              <div className="py-2">
                  {/* Songs */}
                  {results.song && results.song.length > 0 && (
                    <div className="mb-2">
                      <div className="px-4 py-1 text-xs font-semibold text-purple-400 uppercase tracking-wider bg-white/5">Songs</div>
                      {results.song.map(song => (
                        <div key={song.id} onClick={() => { playTrack(song); handleResultClick(); }} className="flex items-center gap-3 px-4 py-2 hover:bg-white/10 cursor-pointer transition-colors group">
                           <img src={`/ampache/public/rest/index.php?action=getCoverArt&id=${song.coverArt}&${getAuthParams(user)}`} className="w-10 h-10 rounded bg-slate-800 object-cover" alt="" />
                           <div className="min-w-0">
                             <div className="text-sm font-medium text-slate-200 truncate group-hover:text-purple-400 transition-colors">{song.title}</div>
                             <div className="text-xs text-slate-400 truncate">{song.artist}</div>
                           </div>
                        </div>
                      ))}
                    </div>
                  )}

                  {/* Albums */}
                  {results.album && results.album.length > 0 && (
                    <div className="mb-2">
                      <div className="px-4 py-1 text-xs font-semibold text-purple-400 uppercase tracking-wider bg-white/5">Albums</div>
                      {results.album.map(album => (
                        <Link to={`/albums/${album.id}`} key={album.id} onClick={handleResultClick} className="flex items-center gap-3 px-4 py-2 hover:bg-white/10 cursor-pointer transition-colors group">
                           <img src={`/ampache/public/rest/index.php?action=getCoverArt&id=${album.coverArt}&${getAuthParams(user)}`} className="w-10 h-10 rounded bg-slate-800 object-cover" alt="" />
                           <div className="min-w-0">
                             <div className="text-sm font-medium text-slate-200 truncate group-hover:text-purple-400 transition-colors">{album.name}</div>
                             <div className="text-xs text-slate-400 truncate">{album.artist}</div>
                           </div>
                        </Link>
                      ))}
                    </div>
                  )}

                  {/* Artists */}
                  {results.artist && results.artist.length > 0 && (
                    <div className="mb-2">
                      <div className="px-4 py-1 text-xs font-semibold text-purple-400 uppercase tracking-wider bg-white/5">Artists</div>
                      {results.artist.map(artist => (
                        <Link to={`/artists/${artist.id}`} key={artist.id} onClick={handleResultClick} className="flex items-center gap-3 px-4 py-2 hover:bg-white/10 cursor-pointer transition-colors group">
                           <div className="min-w-0">
                             <div className="text-sm font-medium text-slate-200 truncate group-hover:text-purple-400 transition-colors">{artist.name}</div>
                           </div>
                        </Link>
                      ))}
                    </div>
                  )}
              </div>
            )}
          </div>
        )}
      </div>

      {/* User Actions */}
      <div className="flex items-center gap-4 ml-4" ref={profileRef}>
        <div className="relative">
          <button 
            onClick={() => setShowProfileMenu(!showProfileMenu)}
            className="flex items-center gap-3 hover:bg-white/5 p-2 rounded-xl transition-colors"
          >
            <div className="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
              <User size={16} className="text-white" />
            </div>
            <div className="hidden md:flex flex-col items-start text-left">
              <span className="text-sm font-medium text-slate-200">{user?.username}</span>
              <span className="text-xs text-purple-400 uppercase tracking-wider font-semibold">
                [{user?.username?.toLowerCase() === 'admin' ? 'Admin' : 'Standard'}]
              </span>
            </div>
          </button>
          
          {showProfileMenu && (
            <div className="absolute right-0 top-full mt-2 w-48 bg-slate-900 border border-white/10 rounded-xl shadow-2xl py-1 overflow-hidden z-50">
              <div className="px-4 py-3 border-b border-white/10 md:hidden">
                <p className="text-sm font-medium text-white">{user?.username}</p>
                <p className="text-xs text-purple-400 mt-1">[{user?.username?.toLowerCase() === 'admin' ? 'Admin' : 'Standard'}]</p>
              </div>
              <button 
                onClick={handleLogout}
                className="w-full flex items-center gap-2 px-4 py-3 text-sm text-red-400 hover:bg-white/5 transition-colors text-left"
              >
                <LogOut size={16} /> Log Out
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
