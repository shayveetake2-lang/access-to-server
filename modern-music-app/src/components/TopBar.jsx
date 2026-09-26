import { Search, User, LogOut, ArrowLeft, Globe, Settings as SettingsIcon, ShieldAlert, ListPlus, Plus, Volume2 } from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import { usePlayer } from '../context/PlayerContext';
import { useAuth } from '../context/AuthContext';
import { usePlaylistModal } from '../context/PlaylistModalContext';
import { useToast } from '../context/ToastContext';
import { getMediaPortalUrl, searchSubsonic, getCoverArtUrl, DEFAULT_COVER_ART } from '../utils/api';

export default function TopBar() {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState(null);
  const [loading, setLoading] = useState(false);
  const [showDropdown, setShowDropdown] = useState(false);
  const [showProfileMenu, setShowProfileMenu] = useState(false);
  
  const dropdownRef = useRef(null);
  const profileRef = useRef(null);
  
  const navigate = useNavigate();
  const location = useLocation();
  const { playQueue, playSong, addToQueue, currentTrack, isPlaying } = usePlayer();
  const { user, logout, isAdmin, getAuthParams } = useAuth();
  const { openAddToPlaylistModal } = usePlaylistModal();
  const { showToast } = useToast();
  
  const PRIMARY_PATHS = new Set(['/', '/albums', '/artists', '/songs', '/liked', '/playlists', '/public-playlists', '/settings', '/help', '/admin']);
  const canGoBack = !PRIMARY_PATHS.has(location.pathname);

  useEffect(() => {
    const fetchResults = async () => {
      if (query.length < 2) {
        setResults(null);
        return;
      }
      setLoading(true);
      try {
        const data = await searchSubsonic(query, user);
        setResults(data);
        setShowDropdown(true);
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
    playSong(song);
  };

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <header className="min-h-16 md:min-h-20 flex items-center justify-between px-3 sm:px-4 md:px-8 border-b border-white/5 bg-slate-950/80 backdrop-blur-xl sticky top-0 z-50 pointer-events-auto pt-[max(env(safe-area-inset-top),16px)] pb-2 md:pb-0 gap-2 sm:gap-3">
      
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
        <span className="text-base font-bold bg-gradient-to-r from-purple-300 via-white to-indigo-200 bg-clip-text text-transparent tracking-tight hidden xs:inline">
          Aether
        </span>
      </Link>

      {/* Optional Mobile Back Button */}
      {canGoBack && (
        <button 
          onClick={() => navigate(-1)} 
          className="p-2 -ml-1 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 active:scale-95 transition-all shrink-0"
          title="Go Back"
          aria-label="Go Back"
        >
          <ArrowLeft size={19} />
        </button>
      )}

      {/* Search Bar */}
      <div className="flex-1 max-w-xl relative min-w-0" ref={dropdownRef}>
        <div className="relative group">
          <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-purple-400 transition-colors" size={17} />
          <input 
            type="text" 
            placeholder="Search music, artists..." 
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onFocus={() => { if(results) setShowDropdown(true); }}
            className="w-full bg-slate-900/60 border border-white/10 rounded-full py-2 pl-10 pr-4 text-xs sm:text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition-all placeholder:text-slate-500"
          />
          {loading && (
            <div className="absolute right-3.5 top-1/2 -translate-y-1/2">
              <div className="w-3.5 h-3.5 border-2 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
            </div>
          )}
        </div>

        {/* Dropdown Results */}
        {showDropdown && results && (() => {
          const rawSongs = results.song;
          const songs = (Array.isArray(rawSongs) ? rawSongs : (rawSongs ? [rawSongs] : [])).slice(0, 150);
          const rawAlbums = results.album;
          const albums = Array.isArray(rawAlbums) ? rawAlbums : (rawAlbums ? [rawAlbums] : []);
          const rawArtists = results.artist;
          const artists = Array.isArray(rawArtists) ? rawArtists : (rawArtists ? [rawArtists] : []);
          const hasResults = songs.length > 0 || albums.length > 0 || artists.length > 0;

          return (
            <div className="absolute top-full mt-2 w-full bg-slate-900 border border-white/10 rounded-xl shadow-2xl overflow-hidden max-h-[70vh] overflow-y-auto z-50">
              {!hasResults ? (
                <div className="p-4 text-center text-slate-400 text-sm">No results found for "{query}"</div>
              ) : (
                <div className="py-2">
                  {/* Songs */}
                  {songs.length > 0 && (
                    <div className="mb-2">
                      <div className="px-4 py-1 text-xs font-semibold text-purple-400 uppercase tracking-wider bg-white/5">Songs</div>
                      {songs.map(song => {
                        const isCurrent = currentTrack?.id === song.id;
                        return (
                          <div 
                            key={song.id} 
                            onClick={() => { playTrack(song); handleResultClick(); }} 
                            className={`flex items-center justify-between gap-3 px-4 py-2 cursor-pointer transition-colors group ${
                              isCurrent ? 'bg-purple-500/15' : 'hover:bg-white/10'
                            }`}
                          >
                             <div className="flex items-center gap-3 min-w-0 flex-1">
                               <div className="relative w-10 h-10 rounded-lg bg-slate-800 shrink-0 overflow-hidden border border-white/5 flex items-center justify-center">
                                 <img 
                                   src={getCoverArtUrl(song.coverArt || song.id, getAuthParams(user))} 
                                   className="w-full h-full object-cover" 
                                   alt="" 
                                   onError={(e) => { if (e.currentTarget.src !== DEFAULT_COVER_ART) { e.currentTarget.src = DEFAULT_COVER_ART; } }}
/>
                                 {isCurrent && (
                                   <div className="absolute inset-0 bg-black/40 flex items-center justify-center">
                                     <Volume2 size={14} className={`text-purple-400 ${isPlaying ? 'animate-pulse' : ''}`} />
                                   </div>
                                 )}
                                </div>
                                <div className="min-w-0 flex-1">
                                  <div className={`text-sm font-medium truncate transition-colors ${
                                    isCurrent ? 'text-purple-300 font-semibold' : 'text-slate-200 group-hover:text-purple-400'
                                  }`}>
                                    {song.title}
                                  </div>
                                  <div className="text-xs text-slate-400 truncate">{song.artist}</div>
                                </div>
                             </div>

                             <div className="flex items-center gap-1 shrink-0" onClick={(e) => e.stopPropagation()}>
                                <button 
                                  onClick={() => { 
                                    addToQueue(song); 
                                    showToast(`Added "${song.title}" to play next`, 'success'); 
                                  }}
                                  className="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:text-purple-400 hover:bg-white/10 transition-colors"
                                  title="Add to Queue (Play Next)"
                                  aria-label="Add to Queue"
                                >
                                  <ListPlus size={15} />
                                </button>
                               <button 
                                 onClick={() => { 
                                   openAddToPlaylistModal(song.id); 
                                   handleResultClick(); 
                                 }}
                                 className="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:text-purple-400 hover:bg-white/10 transition-colors"
                                 title="Add to Playlist"
                                 aria-label="Add to Playlist"
                               >
                                 <Plus size={15} />
                               </button>
                             </div>
                          </div>
                        );
                      })}
                    </div>
                  )}

                  {/* Albums */}
                  {albums.length > 0 && (
                    <div className="mb-2">
                      <div className="px-4 py-1 text-xs font-semibold text-purple-400 uppercase tracking-wider bg-white/5">Albums</div>
                      {albums.map(album => (
                        <Link to={`/albums/${album.id}`} key={album.id} onClick={handleResultClick} className="flex items-center gap-3 px-4 py-2 hover:bg-white/10 cursor-pointer transition-colors group">
                           <img 
                             src={getCoverArtUrl(album.coverArt || album.id, getAuthParams(user))} 
                             className="w-10 h-10 rounded bg-slate-800 object-cover shrink-0" 
                             alt="" 
                             onError={(e) => { if (e.currentTarget.src !== DEFAULT_COVER_ART) { e.currentTarget.src = DEFAULT_COVER_ART; } }}
/>
                           <div className="min-w-0 flex-1">
                             <div className="text-sm font-medium text-slate-200 truncate group-hover:text-purple-400 transition-colors">{album.name}</div>
                             <div className="text-xs text-slate-400 truncate">{album.artist}</div>
                           </div>
                        </Link>
                      ))}
                    </div>
                  )}

                  {/* Artists */}
                  {artists.length > 0 && (
                    <div className="mb-2">
                      <div className="px-4 py-1 text-xs font-semibold text-purple-400 uppercase tracking-wider bg-white/5">Artists</div>
                      {artists.map(artist => (
                        <Link to={`/artists/${artist.id}`} key={artist.id} onClick={handleResultClick} className="flex items-center gap-3 px-4 py-2 hover:bg-white/10 cursor-pointer transition-colors group">
                           <div className="min-w-0 flex-1">
                             <div className="text-sm font-medium text-slate-200 truncate group-hover:text-purple-400 transition-colors">{artist.name}</div>
                           </div>
                        </Link>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </div>
          );
        })()}
      </div>

      {/* User Profile Actions */}
      <div className="flex items-center gap-2 shrink-0 ml-1" ref={profileRef}>
        <div className="relative">
          <button 
            onClick={() => setShowProfileMenu(!showProfileMenu)}
            className="flex items-center gap-2 hover:bg-white/5 p-1.5 rounded-xl transition-colors active:scale-95"
            aria-label="User profile menu"
          >
            <div className="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shrink-0 shadow-md">
              <User size={15} className="text-white" />
            </div>
            <div className="hidden md:flex flex-col items-start text-left">
              <span className="text-sm font-medium text-slate-200">{user?.username}</span>
              <span className="text-xs text-purple-400 uppercase tracking-wider font-semibold">
                [{isAdmin ? 'Admin' : 'Standard'}]
              </span>
            </div>
          </button>
          
          {showProfileMenu && (
            <div className="absolute right-0 top-full mt-2 w-52 bg-slate-900/95 backdrop-blur-xl border border-white/10 rounded-2xl shadow-2xl py-1.5 overflow-hidden z-50 animate-in fade-in zoom-in-95 duration-150">
              <div className="px-4 py-2.5 border-b border-white/10">
                <p className="text-sm font-semibold text-white truncate">{user?.username}</p>
                <p className="text-[11px] text-purple-400 uppercase tracking-wider mt-0.5">
                  {isAdmin ? 'Administrator' : 'Standard User'}
                </p>
              </div>

              {(isAdmin) && (
                <Link 
                  to="/admin" 
                  onClick={() => setShowProfileMenu(false)}
                  className="flex items-center gap-2.5 px-4 py-2.5 text-xs font-medium text-purple-300 hover:text-white hover:bg-white/5 transition-colors"
                >
                  <ShieldAlert size={15} className="text-purple-400" /> Admin Panel
                </Link>
              )}
              
              <Link 
                to="/settings" 
                onClick={() => setShowProfileMenu(false)}
                className="flex items-center gap-2.5 px-4 py-2.5 text-xs font-medium text-slate-300 hover:text-white hover:bg-white/5 transition-colors"
              >
                <SettingsIcon size={15} className="text-slate-400" /> Settings
              </Link>
              
              <a 
                href={getMediaPortalUrl()} 
                className="flex items-center justify-between px-4 py-2.5 text-xs font-medium text-purple-300 hover:text-purple-200 hover:bg-white/5 transition-colors border-t border-white/5"
              >
                <span className="flex items-center gap-2.5">
                  <Globe size={15} className="text-purple-400" /> Media Portal
                </span>
                <span className="text-slate-500 text-[10px]">↗</span>
              </a>

              <div className="border-t border-white/10 pt-1">
                <button 
                  onClick={handleLogout}
                  className="w-full flex items-center gap-2.5 px-4 py-2.5 text-xs font-medium text-rose-400 hover:bg-rose-500/10 transition-colors text-left"
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
