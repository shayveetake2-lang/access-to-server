import { useEffect, useState, useMemo } from 'react';
import { Play, Search, Filter, SlidersHorizontal, LayoutGrid, Layers, ChevronRight, X, Disc } from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { usePlayer } from '../context/PlayerContext';
import { useToast } from '../context/ToastContext';
import { getAmpacheUrl, getCoverArtUrl, DEFAULT_COVER_ART } from '../utils/api';

const GENRE_CATEGORIES = [
  { id: 'all', label: 'All Genres' },
  { id: 'pop', label: 'Pop', match: ['pop'] },
  { id: 'rap', label: 'Hip-Hop & Rap', match: ['hip', 'rap', 'trap'] },
  { id: 'rnb', label: 'R&B & Soul', match: ['r&b', 'rnb', 'soul', 'urban'] },
  { id: 'electronic', label: 'Electronic & Dance', match: ['electr', 'dance', 'house', 'techno', 'edm', 'club'] },
  { id: 'rock', label: 'Rock & Alternative', match: ['rock', 'metal', 'punk', 'grunge', 'alt'] },
  { id: 'indie', label: 'Indie & Acoustic', match: ['indie', 'folk', 'acoustic'] },
];

const DECADES = [
  { id: 'all', label: 'All Eras' },
  { id: '2020s', label: '2020s', min: 2020, max: 2029 },
  { id: '2010s', label: '2010s', min: 2010, max: 2019 },
  { id: '2000s', label: '2000s', min: 2000, max: 2009 },
  { id: '1990s', label: '1990s', min: 1990, max: 1999 },
  { id: 'classics', label: 'Classics (<1990)', min: 0, max: 1989 },
];

const SORT_OPTIONS = [
  { id: 'popular', label: '🔥 Most Popular' },
  { id: 'album_asc', label: '🔤 Album (A → Z)' },
  { id: 'album_desc', label: '🔤 Album (Z → A)' },
  { id: 'artist_asc', label: '🎤 Artist (A → Z)' },
  { id: 'year_desc', label: '📅 Year (Newest)' },
  { id: 'year_asc', label: '📅 Year (Oldest)' },
  { id: 'tracks_desc', label: '🎵 Most Tracks' },
];

const ALPHABET = ['All', '#', ...'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('')];

export default function AllAlbums() {
  const [albums, setAlbums] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedGenre, setSelectedGenre] = useState('all');
  const [selectedDecade, setSelectedDecade] = useState('all');
  const [selectedLetter, setSelectedLetter] = useState('All');
  const [sortBy, setSortBy] = useState('popular');
  const [onlyWithCovers, setOnlyWithCovers] = useState(false);
  const [viewMode, setViewMode] = useState('showcases'); // 'showcases' | 'grid'
  const [playingAlbumId, setPlayingAlbumId] = useState(null);

  const { user, getAuthParams } = useAuth();
  const { playQueue } = usePlayer();
  const { showToast } = useToast();
  const navigate = useNavigate();

  useEffect(() => {
    const fetchAlbums = async () => {
      try {
        const response = await fetch(getAmpacheUrl(`action=getAlbumList&type=alphabeticalByArtist&size=500&${getAuthParams(user)}`));
        const data = await response.json();
        if (data?.['subsonic-response']?.status === 'ok') {
          const raw = data['subsonic-response'].albumList?.album || [];
          setAlbums(Array.isArray(raw) ? raw : [raw]);
        }
      } catch (err) {
        console.error("Browse fetch error:", err);
      } finally {
        setLoading(false);
      }
    };
    fetchAlbums();
  }, [user]);

  // Play album directly on card hover/touch
  const handlePlayAlbum = async (album, e) => {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    if (playingAlbumId) return;
    setPlayingAlbumId(album.id);

    try {
      const res = await fetch(getAmpacheUrl(`action=getAlbum&id=${album.id}&${getAuthParams(user)}`));
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        const rawSongs = data['subsonic-response']?.album?.song || [];
        const songs = Array.isArray(rawSongs) ? rawSongs : [rawSongs];
        if (songs.length > 0) {
          playQueue(songs, 0);
          showToast(`▶ Playing album "${album.name}"`, 'success');
        } else {
          showToast(`No tracks in "${album.name}"`, 'warning');
        }
      }
    } catch (err) {
      showToast('Failed to play album', 'error');
    } finally {
      setPlayingAlbumId(null);
    }
  };

  // Filter and sort albums based on user criteria
  const filteredAlbums = useMemo(() => {
    return albums.filter(album => {
      // 1. Cover Art Filter
      if (onlyWithCovers) {
        const hasCover = album.coverArt && String(album.coverArt).trim() !== '' && String(album.coverArt) !== '0';
        if (!hasCover) return false;
      }

      // 2. Search Query Filter (name or artist)
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase().trim();
        const matchName = album.name && album.name.toLowerCase().includes(q);
        const matchArtist = album.artist && album.artist.toLowerCase().includes(q);
        if (!matchName && !matchArtist) return false;
      }

      // 3. Genre Filter
      if (selectedGenre !== 'all') {
        const cat = GENRE_CATEGORIES.find(c => c.id === selectedGenre);
        if (!cat) return false;
        if (!album.genre) return false;
        const g = album.genre.toLowerCase();
        if (!cat.match.some(m => g.includes(m))) return false;
      }

      // 4. Decade / Era Filter
      if (selectedDecade !== 'all') {
        const dec = DECADES.find(d => d.id === selectedDecade);
        if (dec) {
          const yr = parseInt(album.year, 10);
          if (isNaN(yr)) return false;
          if (yr < dec.min || yr > dec.max) return false;
        }
      }

      // 5. Alphabetical Letter Jump Filter
      if (selectedLetter !== 'All') {
        const firstChar = (album.name || '').trim().charAt(0).toUpperCase();
        if (selectedLetter === '#') {
          if (/[A-Z]/i.test(firstChar)) return false;
        } else {
          if (firstChar !== selectedLetter) return false;
        }
      }

      return true;
    });
  }, [albums, onlyWithCovers, searchQuery, selectedGenre, selectedDecade, selectedLetter]);

  // Sorted list for Grid View
  const sortedAlbums = useMemo(() => {
    const list = [...filteredAlbums];
    switch (sortBy) {
      case 'popular':
        list.sort((a, b) => {
          const pDiff = (b.playCount || 0) - (a.playCount || 0);
          if (pDiff !== 0) return pDiff;
          return (b.songCount || 0) - (a.songCount || 0);
        });
        break;
      case 'album_asc':
        list.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
        break;
      case 'album_desc':
        list.sort((a, b) => (b.name || '').localeCompare(a.name || ''));
        break;
      case 'artist_asc':
        list.sort((a, b) => (a.artist || '').localeCompare(b.artist || ''));
        break;
      case 'year_desc':
        list.sort((a, b) => (parseInt(b.year, 10) || 0) - (parseInt(a.year, 10) || 0));
        break;
      case 'year_asc':
        list.sort((a, b) => (parseInt(a.year, 10) || 9999) - (parseInt(b.year, 10) || 9999));
        break;
      case 'tracks_desc':
        list.sort((a, b) => (b.songCount || 0) - (a.songCount || 0));
        break;
      default:
        break;
    }
    return list;
  }, [filteredAlbums, sortBy]);

  // Group albums by genre showcases for Carousel mode
  const genreShowcases = useMemo(() => {
    const showcases = [];
    for (const cat of GENRE_CATEGORIES) {
      if (cat.id === 'all') continue;
      // If user specifically picked a genre, show only that category or all
      if (selectedGenre !== 'all' && selectedGenre !== cat.id) continue;

      const matched = filteredAlbums.filter(album => {
        if (!album.genre) return false;
        const g = album.genre.toLowerCase();
        return cat.match.some(m => g.includes(m));
      });

      // Sort showcase items by popularity
      matched.sort((a, b) => (b.playCount || 0) - (a.playCount || 0));

      if (matched.length > 0) {
        showcases.push({
          ...cat,
          albums: matched
        });
      }
    }

    // Uncategorized / Miscellaneous group
    if (selectedGenre === 'all') {
      const uncategorized = filteredAlbums.filter(album => {
        if (!album.genre) return true;
        const g = album.genre.toLowerCase();
        return !GENRE_CATEGORIES.some(cat => cat.id !== 'all' && cat.match.some(m => g.includes(m)));
      });

      if (uncategorized.length > 0) {
        uncategorized.sort((a, b) => (b.playCount || 0) - (a.playCount || 0));
        showcases.push({
          id: 'misc',
          label: 'Trending & Essential Releases',
          albums: uncategorized
        });
      }
    }

    return showcases;
  }, [filteredAlbums, selectedGenre]);

  const hasActiveFilters = searchQuery || selectedGenre !== 'all' || selectedDecade !== 'all' || selectedLetter !== 'All' || onlyWithCovers;

  const resetFilters = () => {
    setSearchQuery('');
    setSelectedGenre('all');
    setSelectedDecade('all');
    setSelectedLetter('All');
    setOnlyWithCovers(false);
  };

  return (
    <div className="pb-28 max-w-7xl mx-auto space-y-6 sm:space-y-8">
      
      {/* Header & View Mode Switcher */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 px-1">
        <div>
          <h1 className="text-xl sm:text-3xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
            <span>Browse Catalog</span>
            <span className="text-xs px-2.5 py-0.5 rounded-full bg-purple-500/15 text-purple-300 font-medium border border-purple-500/25">
              {filteredAlbums.length} albums
            </span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-400 mt-0.5">
            Explore releases by genre carousels, release era, popularity, or detailed grid view.
          </p>
        </div>

        {/* View Mode Toggle */}
        <div className="flex items-center gap-1.5 p-1 bg-slate-900/80 rounded-2xl border border-white/10 shrink-0 self-start md:self-auto shadow-inner">
          <button
            onClick={() => setViewMode('showcases')}
            className={`px-3.5 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all ${
              viewMode === 'showcases'
                ? 'bg-purple-500 text-white shadow-md'
                : 'text-slate-400 hover:text-white'
            }`}
            title="Browse by Genre Carousels"
          >
            <Layers size={14} />
            <span>Showcases</span>
          </button>
          <button
            onClick={() => setViewMode('grid')}
            className={`px-3.5 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all ${
              viewMode === 'grid'
                ? 'bg-purple-500 text-white shadow-md'
                : 'text-slate-400 hover:text-white'
            }`}
            title="Browse All in Grid View"
          >
            <LayoutGrid size={14} />
            <span>Grid View</span>
          </button>
        </div>
      </div>

      {/* Filter & Search Bar */}
      <div className="bg-slate-900/50 backdrop-blur-md p-4 sm:p-5 rounded-3xl border border-white/5 space-y-4 shadow-xl">
        
        {/* Row 1: Search & Controls */}
        <div className="flex flex-col sm:flex-row items-center gap-3">
          
          {/* Search Box */}
          <div className="relative flex-1 w-full">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none" size={16} />
            <input
              type="text"
              placeholder="Search albums by title or artist..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full bg-slate-950/70 border border-white/10 rounded-2xl pl-10 pr-10 py-2.5 text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-colors"
            />
            {searchQuery && (
              <button
                onClick={() => setSearchQuery('')}
                className="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white"
              >
                <X size={14} />
              </button>
            )}
          </div>

          {/* Decade Dropdown */}
          <div className="flex items-center gap-2 w-full sm:w-auto">
            <select
              value={selectedDecade}
              onChange={(e) => setSelectedDecade(e.target.value)}
              className="bg-slate-950/70 border border-white/10 rounded-2xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-200 focus:outline-none focus:border-purple-500 transition-colors w-full sm:w-auto cursor-pointer"
            >
              {DECADES.map(dec => (
                <option key={dec.id} value={dec.id} className="bg-slate-900 text-white">
                  {dec.label}
                </option>
              ))}
            </select>

            {/* Sort Dropdown */}
            <select
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value)}
              className="bg-slate-950/70 border border-white/10 rounded-2xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-200 focus:outline-none focus:border-purple-500 transition-colors w-full sm:w-auto cursor-pointer"
            >
              {SORT_OPTIONS.map(opt => (
                <option key={opt.id} value={opt.id} className="bg-slate-900 text-white">
                  {opt.label}
                </option>
              ))}
            </select>
          </div>

          {/* Covers Only Toggle Button */}
          <button
            onClick={() => setOnlyWithCovers(prev => !prev)}
            className={`px-3.5 py-2.5 rounded-2xl text-xs font-semibold whitespace-nowrap transition-all border flex items-center gap-1.5 shrink-0 w-full sm:w-auto justify-center ${
              onlyWithCovers
                ? 'bg-purple-500/20 border-purple-500/40 text-purple-300'
                : 'bg-slate-950/70 border-white/10 text-slate-400 hover:text-white'
            }`}
            title="Toggle between albums with album art only and all albums"
          >
            <span>🖼️</span>
            <span>Covers Only</span>
          </button>
        </div>

        {/* Row 2: Genre Filter Chips */}
        <div className="flex items-center gap-2 overflow-x-auto pb-1 touch-scroll scrollbar-none">
          {GENRE_CATEGORIES.map(cat => {
            const isActive = selectedGenre === cat.id;
            return (
              <button
                key={cat.id}
                onClick={() => setSelectedGenre(cat.id)}
                className={`px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all shrink-0 ${
                  isActive
                    ? 'bg-purple-500 text-white shadow-[0_0_16px_rgba(168,85,247,0.4)]'
                    : 'bg-slate-950/60 hover:bg-slate-800 text-slate-400 hover:text-white border border-white/5'
                }`}
              >
                {cat.label}
              </button>
            );
          })}
        </div>

        {/* Row 3: A-Z Jump Bar (Visible when in Grid View or alphabetical sort) */}
        {viewMode === 'grid' && (
          <div className="flex items-center gap-1 overflow-x-auto pb-1 pt-1 touch-scroll scrollbar-none border-t border-white/5">
            {ALPHABET.map(char => {
              const isActive = selectedLetter === char;
              return (
                <button
                  key={char}
                  onClick={() => setSelectedLetter(char)}
                  className={`w-7 h-7 rounded-lg text-xs font-bold transition-all shrink-0 flex items-center justify-center ${
                    isActive
                      ? 'bg-purple-500 text-white shadow-sm'
                      : 'text-slate-400 hover:text-white hover:bg-white/5'
                  }`}
                >
                  {char}
                </button>
              );
            })}
          </div>
        )}

        {/* Active Filter Tags */}
        {hasActiveFilters && (
          <div className="flex flex-wrap items-center gap-2 pt-2 border-t border-white/5 text-xs text-slate-400">
            <span className="font-semibold text-slate-300">Active Filters:</span>
            {searchQuery && (
              <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30">
                Search: "{searchQuery}"
                <button onClick={() => setSearchQuery('')}><X size={12} /></button>
              </span>
            )}
            {selectedGenre !== 'all' && (
              <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30">
                Genre: {GENRE_CATEGORIES.find(c => c.id === selectedGenre)?.label}
                <button onClick={() => setSelectedGenre('all')}><X size={12} /></button>
              </span>
            )}
            {selectedDecade !== 'all' && (
              <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30">
                Era: {DECADES.find(d => d.id === selectedDecade)?.label}
                <button onClick={() => setSelectedDecade('all')}><X size={12} /></button>
              </span>
            )}
            {selectedLetter !== 'All' && (
              <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30">
                Starts with: {selectedLetter}
                <button onClick={() => setSelectedLetter('All')}><X size={12} /></button>
              </span>
            )}
            {onlyWithCovers && (
              <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30">
                Covers Only
                <button onClick={() => setOnlyWithCovers(false)}><X size={12} /></button>
              </span>
            )}
            <button
              onClick={resetFilters}
              className="text-slate-400 hover:text-white underline text-[11px] ml-1"
            >
              Reset all
            </button>
          </div>
        )}
      </div>

      {loading ? (
        <div className="flex justify-center py-24">
          <div className="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
      ) : filteredAlbums.length === 0 ? (
        <div className="text-center py-20 bg-slate-900/40 rounded-3xl border border-white/5 p-8 max-w-md mx-auto">
          <Disc size={44} className="mx-auto text-slate-500 mb-3" />
          <h3 className="text-lg font-bold text-white mb-1.5">No albums found</h3>
          <p className="text-xs sm:text-sm text-slate-400 mb-5">
            No releases match your current search or filter combination.
          </p>
          <button
            onClick={resetFilters}
            className="px-5 py-2 rounded-full bg-purple-500 hover:bg-purple-400 text-white text-xs font-semibold shadow-md active:scale-95 transition-all"
          >
            Clear Filters
          </button>
        </div>
      ) : viewMode === 'showcases' ? (
        /* 🌟 Mode 1: Genre Showcases & Horizontal Carousels */
        <div className="space-y-8 sm:space-y-10">
          {genreShowcases.map(showcase => (
            <section key={showcase.id} className="space-y-3.5">
              <div className="flex items-center justify-between px-1">
                <div>
                  <h3 className="text-lg sm:text-2xl font-bold text-white flex items-center gap-2">
                    <span>{showcase.label}</span>
                    <span className="text-xs px-2 py-0.5 rounded-full bg-purple-500/15 text-purple-300 font-medium">
                      {showcase.albums.length}
                    </span>
                  </h3>
                </div>
                <button 
                  onClick={() => {
                    setSelectedGenre(showcase.id === 'misc' ? 'all' : showcase.id);
                    setViewMode('grid');
                  }} 
                  className="text-xs font-semibold text-purple-400 hover:text-purple-300 flex items-center gap-1 group transition-colors"
                >
                  <span>View all in grid</span>
                  <ChevronRight size={14} className="group-hover:translate-x-0.5 transition-transform" />
                </button>
              </div>

              {/* Horizontal Scrollable Carousel */}
              <div className="flex gap-3.5 sm:gap-5 overflow-x-auto pb-3 pt-1 touch-scroll scrollbar-none px-1">
                {showcase.albums.map(album => (
                  <div 
                    key={album.id}
                    className="group flex flex-col w-36 sm:w-44 md:w-48 shrink-0 bg-slate-900/40 hover:bg-slate-800/60 p-2.5 sm:p-3 rounded-2xl transition-all border border-white/5 hover:border-purple-500/30 backdrop-blur-sm relative active:scale-[0.98] cursor-pointer"
                    onClick={() => navigate(`/albums/${album.id}`)}
                  >
                    {/* Square Cover Art */}
                    <div className="relative aspect-square rounded-xl overflow-hidden mb-2.5 shadow-md bg-slate-800 border border-white/5">
                      <img 
                        src={getCoverArtUrl(album.coverArt || album.id, getAuthParams(user))} 
                        alt={album.name}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                        loading="lazy"
                        decoding="async"
                        onError={(e) => {
                          if (e.currentTarget.src !== DEFAULT_COVER_ART) {
                            e.currentTarget.src = DEFAULT_COVER_ART;
                          }
                        }}
                      />
                      {/* Quick Play Overlay */}
                      <div className="hidden sm:flex absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity items-center justify-center">
                        <button 
                          onClick={(e) => handlePlayAlbum(album, e)}
                          disabled={playingAlbumId === album.id}
                          className="w-11 h-11 rounded-full bg-purple-500 flex items-center justify-center text-white shadow-xl transform translate-y-2 group-hover:translate-y-0 transition-all duration-200 hover:scale-105 active:scale-95"
                          title="Play Album"
                          aria-label="Play Album"
                        >
                          {playingAlbumId === album.id ? (
                            <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                          ) : (
                            <Play fill="currentColor" size={18} className="ml-0.5" />
                          )}
                        </button>
                      </div>
                    </div>

                    {/* Title & Artist */}
                    <div className="px-0.5 min-w-0">
                      <h4 className="font-semibold text-slate-100 group-hover:text-purple-300 transition-colors text-xs sm:text-sm truncate leading-snug">
                        {album.name}
                      </h4>
                      <p className="text-[11px] sm:text-xs text-slate-400 truncate mt-0.5">
                        {album.artist}
                      </p>
                      <div className="flex items-center gap-1.5 text-[10px] text-slate-500 mt-1">
                        {album.year && <span>{album.year}</span>}
                        {album.year && album.songCount && <span>•</span>}
                        {album.songCount && <span>{album.songCount} tracks</span>}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </section>
          ))}
        </div>
      ) : (
        /* ▦ Mode 2: Full Responsive Grid View */
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3.5 sm:gap-4 md:gap-5">
          {sortedAlbums.map(album => (
            <div 
              key={album.id}
              onClick={() => navigate(`/albums/${album.id}`)}
              className="group flex flex-col bg-slate-900/40 hover:bg-slate-800/60 p-2.5 sm:p-3 rounded-2xl transition-all border border-white/5 hover:border-purple-500/30 backdrop-blur-sm relative active:scale-[0.98] cursor-pointer"
            >
              {/* Square Cover Art */}
              <div className="relative aspect-square rounded-xl overflow-hidden mb-2.5 shadow-md bg-slate-800 border border-white/5">
                <img 
                  src={getCoverArtUrl(album.coverArt || album.id, getAuthParams(user))} 
                  alt={album.name}
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                  loading="lazy"
                  decoding="async"
                  onError={(e) => {
                    if (e.currentTarget.src !== DEFAULT_COVER_ART) {
                      e.currentTarget.src = DEFAULT_COVER_ART;
                    }
                  }}
                />
                {/* Quick Play Overlay */}
                <div className="hidden sm:flex absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity items-center justify-center">
                  <button 
                    onClick={(e) => handlePlayAlbum(album, e)}
                    disabled={playingAlbumId === album.id}
                    className="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center text-white shadow-xl transform translate-y-2 group-hover:translate-y-0 transition-all duration-200 hover:scale-105 active:scale-95"
                    title="Play Album"
                    aria-label="Play Album"
                  >
                    {playingAlbumId === album.id ? (
                      <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                    ) : (
                      <Play fill="currentColor" size={16} className="ml-0.5" />
                    )}
                  </button>
                </div>
              </div>

              {/* Title & Artist */}
              <div className="px-0.5 min-w-0">
                <h4 className="font-semibold text-slate-100 group-hover:text-purple-300 transition-colors text-xs sm:text-sm truncate leading-snug">
                  {album.name}
                </h4>
                <p className="text-[11px] sm:text-xs text-slate-400 truncate mt-0.5">
                  {album.artist}
                </p>
                <div className="flex items-center gap-1.5 text-[10px] text-slate-500 mt-1">
                  {album.year && <span>{album.year}</span>}
                  {album.year && album.songCount && <span>•</span>}
                  {album.songCount && <span>{album.songCount} tracks</span>}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
