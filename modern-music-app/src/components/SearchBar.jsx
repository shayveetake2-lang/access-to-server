import { Search, Volume2, ListPlus, Plus } from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import { usePlayer } from '../context/PlayerContext';
import { useAuth } from '../context/AuthContext';
import { usePlaylistModal } from '../context/PlaylistModalContext';
import { useToast } from '../context/ToastContext';
import { searchSubsonic, getCoverArtUrl, DEFAULT_COVER_ART } from '../utils/api';
import useDebounce from '../hooks/useDebounce';

export default function SearchBar({ onResultClick, className = '' }) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState(null);
  const [loading, setLoading] = useState(false);
  const [showDropdown, setShowDropdown] = useState(false);

  const dropdownRef = useRef(null);
  const debouncedQuery = useDebounce(query, 300);

  const { playSong, addToQueue, currentTrack, isPlaying } = usePlayer();
  const { user, getAuthParams } = useAuth();
  const { openAddToPlaylistModal } = usePlaylistModal();
  const { showToast } = useToast();

  const isDebouncing = query.trim().length >= 2 && query !== debouncedQuery;
  const isSearching = loading || isDebouncing;

  useEffect(() => {
    const trimmed = debouncedQuery.trim();
    if (trimmed.length < 2) {
      setResults(null);
      setLoading(false);
      return;
    }

    let isMounted = true;
    setLoading(true);

    searchSubsonic(trimmed, user)
      .then((data) => {
        if (isMounted) {
          setResults(data);
        }
      })
      .catch((err) => {
        console.error("Search fetch error:", err);
      })
      .finally(() => {
        if (isMounted) {
          setLoading(false);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [debouncedQuery, user]);

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setShowDropdown(false);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const handleResultClick = () => {
    setShowDropdown(false);
    setQuery('');
    if (onResultClick) onResultClick();
  };

  const rawSongs = results?.song;
  const songs = (Array.isArray(rawSongs) ? rawSongs : (rawSongs ? [rawSongs] : [])).slice(0, 150);
  const rawAlbums = results?.album;
  const albums = Array.isArray(rawAlbums) ? rawAlbums : (rawAlbums ? [rawAlbums] : []);
  const rawArtists = results?.artist;
  const artists = Array.isArray(rawArtists) ? rawArtists : (rawArtists ? [rawArtists] : []);
  const hasResults = songs.length > 0 || albums.length > 0 || artists.length > 0;

  return (
    <div className={`flex-1 max-w-xl relative min-w-0 ${className}`} ref={dropdownRef}>
      <div className="relative group">
        <Search 
          className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-purple-500 transition-colors" 
          size={17} 
        />
        <input 
          type="text" 
          placeholder="Search music, artists..." 
          value={query}
          onChange={(e) => {
            const val = e.target.value;
            setQuery(val);
            if (val.trim().length >= 2) {
              setShowDropdown(true);
            }
          }}
          onFocus={() => { 
            if (query.trim().length >= 2) {
              setShowDropdown(true);
            }
          }}
          className="w-full bg-slate-100 dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 rounded-full py-2 pl-10 pr-9 text-xs sm:text-sm text-gray-900 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition-all placeholder:text-slate-400 dark:placeholder:text-slate-500"
        />
        {isSearching && (
          <div className="absolute right-3.5 top-1/2 -translate-y-1/2">
            <div className="w-3.5 h-3.5 border-2 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
          </div>
        )}
      </div>

      {/* Dropdown Results */}
      {showDropdown && query.trim().length >= 2 && (
        <div className="absolute top-full mt-2 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-xl shadow-2xl overflow-hidden max-h-[70vh] overflow-y-auto z-50">
          {isSearching ? (
            <div className="p-4 text-center text-slate-500 dark:text-slate-400 text-sm flex items-center justify-center gap-2">
              <div className="w-4 h-4 border-2 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
              <span>Searching...</span>
            </div>
          ) : !hasResults ? (
            <div className="p-4 text-center text-slate-500 dark:text-slate-400 text-sm">
              No results found for &ldquo;{query}&rdquo;
            </div>
          ) : (
            <div className="py-2">
              {/* Songs */}
              {songs.length > 0 && (
                <div className="mb-2">
                  <div className="px-4 py-1 text-xs font-semibold text-purple-600 dark:text-purple-400 uppercase tracking-wider bg-slate-50 dark:bg-white/5">
                    Songs
                  </div>
                  {songs.map((song) => {
                    const isCurrent = currentTrack?.id === song.id;
                    return (
                      <div 
                        key={song.id} 
                        onClick={() => { playSong(song); handleResultClick(); }} 
                        className={`flex items-center justify-between gap-3 px-4 py-2 cursor-pointer transition-colors group ${
                          isCurrent ? 'bg-purple-500/15' : 'hover:bg-slate-100 dark:hover:bg-white/10'
                        }`}
                      >
                        <div className="flex items-center gap-3 min-w-0 flex-1">
                          <div className="relative w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800 shrink-0 overflow-hidden border border-slate-200 dark:border-white/5 flex items-center justify-center">
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
                              isCurrent ? 'text-purple-600 dark:text-purple-300 font-semibold' : 'text-gray-900 dark:text-slate-200 group-hover:text-purple-600 dark:group-hover:text-purple-400'
                            }`}>
                              {song.title}
                            </div>
                            <div className="text-xs text-slate-500 dark:text-slate-400 truncate">{song.artist}</div>
                          </div>
                        </div>

                        <div className="flex items-center gap-1 shrink-0" onClick={(e) => e.stopPropagation()}>
                          <button 
                            onClick={() => { 
                              addToQueue(song); 
                              showToast(`Added "${song.title}" to play next`, 'success'); 
                            }}
                            className="w-7 h-7 flex items-center justify-center rounded-lg text-slate-500 dark:text-slate-400 hover:text-purple-600 dark:hover:text-purple-400 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors"
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
                            className="w-7 h-7 flex items-center justify-center rounded-lg text-slate-500 dark:text-slate-400 hover:text-purple-600 dark:hover:text-purple-400 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors"
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
                  <div className="px-4 py-1 text-xs font-semibold text-purple-600 dark:text-purple-400 uppercase tracking-wider bg-slate-50 dark:bg-white/5">
                    Albums
                  </div>
                  {albums.map((album) => (
                    <Link 
                      to={`/albums/${album.id}`} 
                      key={album.id} 
                      onClick={handleResultClick} 
                      className="flex items-center gap-3 px-4 py-2 hover:bg-slate-100 dark:hover:bg-white/10 cursor-pointer transition-colors group"
                    >
                      <img 
                        src={getCoverArtUrl(album.coverArt || album.id, getAuthParams(user))} 
                        className="w-10 h-10 rounded bg-slate-100 dark:bg-slate-800 object-cover shrink-0" 
                        alt="" 
                        onError={(e) => { if (e.currentTarget.src !== DEFAULT_COVER_ART) { e.currentTarget.src = DEFAULT_COVER_ART; } }}
                      />
                      <div className="min-w-0 flex-1">
                        <div className="text-sm font-medium text-gray-900 dark:text-slate-200 truncate group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors">
                          {album.name}
                        </div>
                        <div className="text-xs text-slate-500 dark:text-slate-400 truncate">{album.artist}</div>
                      </div>
                    </Link>
                  ))}
                </div>
              )}

              {/* Artists */}
              {artists.length > 0 && (
                <div className="mb-2">
                  <div className="px-4 py-1 text-xs font-semibold text-purple-600 dark:text-purple-400 uppercase tracking-wider bg-slate-50 dark:bg-white/5">
                    Artists
                  </div>
                  {artists.map((artist) => (
                    <Link 
                      to={`/artists/${artist.id}`} 
                      key={artist.id} 
                      onClick={handleResultClick} 
                      className="flex items-center gap-3 px-4 py-2 hover:bg-slate-100 dark:hover:bg-white/10 cursor-pointer transition-colors group"
                    >
                      <div className="min-w-0 flex-1">
                        <div className="text-sm font-medium text-gray-900 dark:text-slate-200 truncate group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors">
                          {artist.name}
                        </div>
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
  );
}
