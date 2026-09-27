import { useAuth } from '../context/AuthContext';
import { useEffect, useState, useMemo, useLayoutEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Search, Tag, Users, Sparkles, Layers, LayoutGrid, ChevronRight, X, Flame, Disc, Music, ArrowDownAZ, Image as ImageIcon } from 'lucide-react';
import { mergeArtists } from '../utils/artistHelper';
import { fetchAllArtists, fetchAllAlbums } from '../utils/api';
import { ArtistGrid, ArtistCard } from '../components/ArtistGrid';

const GENRE_CATEGORIES = [
  { id: 'all', label: 'All Genres' },
  { id: 'pop', label: 'Pop', match: ['pop'] },
  { id: 'rap', label: 'Hip-Hop & Rap', match: ['hip', 'rap', 'trap'] },
  { id: 'rnb', label: 'R&B & Soul', match: ['r&b', 'rnb', 'soul', 'urban'] },
  { id: 'electronic', label: 'Electronic & Dance', match: ['electr', 'dance', 'house', 'techno', 'edm', 'club'] },
  { id: 'rock', label: 'Rock & Alternative', match: ['rock', 'metal', 'punk', 'grunge', 'alt'] },
  { id: 'indie', label: 'Indie & Acoustic', match: ['indie', 'folk', 'acoustic'] },
];

export default function AllArtists() {
  const [rawArtists, setRawArtists] = useState([]);
  const [rawAlbums, setRawAlbums] = useState([]);
  const { user, getAuthParams } = useAuth();
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();
  const isAdmin = user?.role === 'admin' || user?.isAdmin === true || ['admin', 'musicadmin'].includes(user?.username?.toLowerCase());

  // View mode and filtering state
  const [viewMode, setViewMode] = useState('showcases'); // 'showcases' | 'grid'
  const [activeTab, setActiveTab] = useState('name'); // 'name' | 'genre'
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedLetter, setSelectedLetter] = useState(() => sessionStorage.getItem('allArtists_letter') || 'All');
  const [sortBy, setSortBy] = useState('plays'); // 'plays' | 'albums' | 'songs' | 'alphabetical'
  const [minFilter, setMinFilter] = useState('all'); // 'all' | '2albums' | '5albums' | '10songs'
  const [onlyWithPhotos, setOnlyWithPhotos] = useState(false);
  const [selectedGenre, setSelectedGenre] = useState('All');

  useEffect(() => {
    sessionStorage.setItem('allArtists_letter', selectedLetter);
  }, [selectedLetter]);

  useLayoutEffect(() => {
    const mainEl = document.querySelector('main');
    if (!mainEl) return;
    const savedScroll = sessionStorage.getItem('allArtists_scroll');
    if (savedScroll) {
      mainEl.scrollTop = parseInt(savedScroll, 10);
    }
    const handleScroll = () => {
      sessionStorage.setItem('allArtists_scroll', mainEl.scrollTop.toString());
    };
    mainEl.addEventListener('scroll', handleScroll);
    return () => mainEl.removeEventListener('scroll', handleScroll);
  }, []);

  useEffect(() => {
    let isMounted = true;
    const fetchData = async () => {
      try {
        const [artistsData, albumsData] = await Promise.all([
          fetchAllArtists(user),
          fetchAllAlbums(user)
        ]);

        if (isMounted) {
          setRawArtists(artistsData.artists || []);
          setRawAlbums(albumsData.albums || []);
        }
      } catch (err) {
        console.error("Failed to load artists:", err);
      } finally {
        if (isMounted) setLoading(false);
      }
    };

    fetchData();
    return () => {
      isMounted = false;
    };
  }, [user]);

  // Merge similar & featured artists into canonical profiles with aggregated stats
  const canonicalArtists = useMemo(() => {
    return mergeArtists(rawArtists, rawAlbums);
  }, [rawArtists, rawAlbums]);

  // Extract all unique genres present across all artists
  const allGenres = useMemo(() => {
    const genreCounts = new Map();
    canonicalArtists.forEach(artist => {
      (artist.genres || []).forEach(genre => {
        const clean = genre.trim();
        if (clean) {
          genreCounts.set(clean, (genreCounts.get(clean) || 0) + 1);
        }
      });
    });

    const sorted = Array.from(genreCounts.entries())
      .sort((a, b) => b[1] - a[1])
      .map(([genre]) => genre);

    return ['All', ...sorted];
  }, [canonicalArtists]);

  // Available letters for the A-Z jump bar
  const alphabet = useMemo(() => {
    const letters = new Set();
    canonicalArtists.forEach(a => {
      const first = a.name.trim().charAt(0).toUpperCase();
      if (/[A-Z]/.test(first)) {
        letters.add(first);
      } else {
        letters.add('#');
      }
    });
    return ['All', '#', ...'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('')];
  }, [canonicalArtists]);

  // Filtered artists based on search, tab, letter, genre, and thresholds
  const filteredArtists = useMemo(() => {
    let result = canonicalArtists.filter(artist => {
      // 1. Photos Only
      if (onlyWithPhotos) {
        const art = artist.coverArt;
        if (!art || String(art).trim() === '' || String(art) === '0' || String(art) === 'unknown') return false;
      }

      // 2. Minimum threshold filter
      if (minFilter === '2albums' && (artist.albumCount || 0) < 2) return false;
      if (minFilter === '5albums' && (artist.albumCount || 0) < 5) return false;
      if (minFilter === '10songs' && (artist.songCount || 0) < 10) return false;

      // 3. Search query
      if (searchQuery.trim()) {
        const query = searchQuery.toLowerCase();
        const matchesName = artist.name.toLowerCase().includes(query);
        const matchesAliases = (artist.aliasNames || []).some(alias => alias.toLowerCase().includes(query));
        const matchesGenre = (artist.genres || []).some(g => g.toLowerCase().includes(query));
        if (!matchesName && !matchesAliases && !matchesGenre) return false;
      }

      // 4. Tab / letter / genre
      if (activeTab === 'name') {
        if (selectedLetter !== 'All') {
          const first = artist.name.trim().charAt(0).toUpperCase();
          if (selectedLetter === '#') {
            if (/[A-Z]/.test(first)) return false;
          } else if (first !== selectedLetter) {
            return false;
          }
        }
      } else if (activeTab === 'genre') {
        if (selectedGenre !== 'All') {
          if (!artist.genres || !artist.genres.includes(selectedGenre)) return false;
        }
      }

      return true;
    });

    if (sortBy !== 'alphabetical') {
      const sorted = [...result];
      if (sortBy === 'plays') {
        sorted.sort((a, b) => (b.playCount || 0) - (a.playCount || 0) || (b.albumCount || 0) - (a.albumCount || 0));
      } else if (sortBy === 'albums') {
        sorted.sort((a, b) => (b.albumCount || 0) - (a.albumCount || 0) || a.name.localeCompare(b.name));
      } else if (sortBy === 'songs') {
        sorted.sort((a, b) => (b.songCount || 0) - (a.songCount || 0) || a.name.localeCompare(b.name));
      }
      return sorted;
    }

    return result;
  }, [canonicalArtists, searchQuery, activeTab, selectedLetter, selectedGenre, sortBy, minFilter, onlyWithPhotos]);

  // Grouped by letter for alphabetical grid mode
  const groupedByName = useMemo(() => {
    if (activeTab !== 'name' || selectedLetter !== 'All' || searchQuery.trim() || sortBy !== 'alphabetical' || minFilter !== 'all' || onlyWithPhotos) {
      return null;
    }

    const groups = new Map();
    filteredArtists.forEach(artist => {
      const first = artist.name.trim().charAt(0).toUpperCase();
      const key = /[A-Z]/.test(first) ? first : '#';
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push(artist);
    });

    return Array.from(groups.entries()).sort((a, b) => {
      if (a[0] === '#') return 1;
      if (b[0] === '#') return -1;
      return a[0].localeCompare(b[0]);
    });
  }, [filteredArtists, activeTab, selectedLetter, searchQuery, sortBy, minFilter, onlyWithPhotos]);

  // Carousels for Showcases View
  const showcasesData = useMemo(() => {
    if (canonicalArtists.length === 0) return [];

    const showcases = [];

    // 1. Top Streamed Artists
    const topPlayed = [...canonicalArtists]
      .filter(a => (a.playCount || 0) > 0)
      .sort((a, b) => (b.playCount || 0) - (a.playCount || 0))
      .slice(0, 16);
    if (topPlayed.length > 0) {
      showcases.push({
        id: 'top-played',
        title: '🔥 Most Streamed Artists',
        subtitle: 'Ranked by total stream plays across their discography',
        artists: topPlayed
      });
    }

    // 2. Most Prolific (Most Albums)
    const mostAlbums = [...canonicalArtists]
      .filter(a => (a.albumCount || 0) >= 2)
      .sort((a, b) => (b.albumCount || 0) - (a.albumCount || 0))
      .slice(0, 16);
    if (mostAlbums.length > 0) {
      showcases.push({
        id: 'most-albums',
        title: '💿 Most Albums in Catalog',
        subtitle: 'Artists with the deepest album releases in your library',
        artists: mostAlbums
      });
    }

    // 3. Deep Catalogs (Most Songs)
    const mostSongs = [...canonicalArtists]
      .filter(a => (a.songCount || 0) >= 5)
      .sort((a, b) => (b.songCount || 0) - (a.songCount || 0))
      .slice(0, 16);
    if (mostSongs.length > 0) {
      showcases.push({
        id: 'most-songs',
        title: '🎵 Deepest Song Catalogs',
        subtitle: 'Artists with the highest track count available to stream',
        artists: mostSongs
      });
    }

    // 4. Genre-based Showcases
    GENRE_CATEGORIES.forEach(cat => {
      if (cat.id === 'all') return;
      const matched = canonicalArtists.filter(artist => {
        if (!artist.genres || artist.genres.length === 0) return false;
        return artist.genres.some(g => {
          const lower = g.toLowerCase();
          return cat.match.some(m => lower.includes(m));
        });
      });

      if (matched.length > 0) {
        matched.sort((a, b) => (b.playCount || 0) - (a.playCount || 0) || (b.albumCount || 0) - (a.albumCount || 0));
        showcases.push({
          id: `genre-${cat.id}`,
          title: `${cat.label} Artists`,
          subtitle: `Featured artists in ${cat.label}`,
          artists: matched.slice(0, 16),
          genreName: cat.label
        });
      }
    });

    return showcases;
  }, [canonicalArtists]);

  const resetFilters = () => {
    setSearchQuery('');
    setSelectedLetter('All');
    setSelectedGenre('All');
    setMinFilter('all');
    setOnlyWithPhotos(false);
    setSortBy('plays');
  };

  return (
    <div className="pb-28 max-w-7xl mx-auto space-y-6 sm:space-y-8">
      {/* Header & View Mode Switcher */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 px-1 pt-1">
        <div>
          <h1 className="text-xl sm:text-3xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
            <Users className="text-purple-400" size={28} />
            <span>All Artists</span>
            <span className="text-xs px-2.5 py-0.5 rounded-full bg-purple-500/15 text-purple-300 font-medium border border-purple-500/25">
              {filteredArtists.length} artists
            </span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-400 mt-1">
            Explore artists by showcases, catalog volume, most played streams, or detailed grid view.
            {rawArtists.length > canonicalArtists.length && (
              <span className="ml-2 inline-flex items-center gap-1 text-[11px] text-purple-400 font-medium bg-purple-500/10 px-2 py-0.5 rounded-full border border-purple-500/20">
                <Sparkles size={11} /> Merged {rawArtists.length - canonicalArtists.length} featured appearances
              </span>
            )}
          </p>
        </div>

        {/* View Mode Switcher & Admin Shortcuts */}
        <div className="flex flex-wrap items-center gap-2">
          {isAdmin && (
            <Link
              to="/admin"
              className="px-3.5 py-1.5 rounded-xl bg-purple-500/15 hover:bg-purple-500/25 text-purple-300 hover:text-white border border-purple-500/30 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-sm active:scale-95"
              title="Clean and merge similar artist profiles"
            >
              <Sparkles size={13} className="text-purple-400" />
              <span>Merge Similar Artists</span>
            </Link>
          )}

          <div className="flex items-center gap-1.5 p-1 bg-slate-900/80 rounded-2xl border border-white/10 shrink-0 self-start md:self-auto shadow-inner">
            <button
              onClick={() => setViewMode('showcases')}
              className={`px-3.5 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all ${
                viewMode === 'showcases'
                  ? 'bg-purple-500 text-white shadow-md'
                  : 'text-slate-400 hover:text-white'
              }`}
              title="Browse by Artist Showcases & Carousels"
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
              title="Browse All Artists in Grid View"
            >
              <LayoutGrid size={14} />
              <span>Grid View</span>
            </button>
          </div>
        </div>
      </div>

      {/* Control Panel: Quick Filter Buttons, Search & Filter Bar */}
      <div className="bg-slate-900/50 backdrop-blur-md p-4 sm:p-5 rounded-3xl border border-white/5 space-y-4 shadow-xl">
        
        {/* Row 1: Search Box & Sort Controls */}
        <div className="flex flex-col sm:flex-row items-center gap-3">
          {/* Search Box */}
          <div className="relative flex-1 w-full">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none" size={16} />
            <input
              type="text"
              placeholder="Search artists by name, alias, or genre..."
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

          {/* Photos Only Toggle Button */}
          <button
            onClick={() => setOnlyWithPhotos(prev => !prev)}
            className={`px-3.5 py-2.5 rounded-2xl text-xs font-semibold whitespace-nowrap transition-all border flex items-center gap-1.5 shrink-0 w-full sm:w-auto justify-center ${
              onlyWithPhotos
                ? 'bg-purple-500/20 border-purple-500/40 text-purple-300'
                : 'bg-slate-950/70 border-white/10 text-slate-400 hover:text-white'
            }`}
            title="Toggle between artists with photos only and all artists"
          >
            <span>🖼️</span>
            <span>Photos Only</span>
          </button>
        </div>

        {/* Row 2: Quick Filter Buttons (One-Tap Pills on Top of Screen) */}
        <div className="flex flex-wrap items-center gap-2 pt-1 border-t border-white/5">
          <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1">Sort & Filter:</span>
          
          <button
            onClick={() => { setSortBy('plays'); if (viewMode !== 'grid') setViewMode('grid'); }}
            className={`px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all ${
              sortBy === 'plays'
                ? 'bg-purple-500 text-white shadow-[0_0_12px_rgba(168,85,247,0.4)]'
                : 'bg-slate-950/70 text-slate-300 hover:text-white border border-white/10'
            }`}
          >
            <Flame size={13} className={sortBy === 'plays' ? 'text-amber-300' : 'text-purple-400'} />
            <span>Most Played</span>
          </button>

          <button
            onClick={() => { setSortBy('albums'); if (viewMode !== 'grid') setViewMode('grid'); }}
            className={`px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all ${
              sortBy === 'albums'
                ? 'bg-purple-500 text-white shadow-[0_0_12px_rgba(168,85,247,0.4)]'
                : 'bg-slate-950/70 text-slate-300 hover:text-white border border-white/10'
            }`}
          >
            <Disc size={13} className={sortBy === 'albums' ? 'text-indigo-300' : 'text-purple-400'} />
            <span>Most Albums</span>
          </button>

          <button
            onClick={() => { setSortBy('songs'); if (viewMode !== 'grid') setViewMode('grid'); }}
            className={`px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all ${
              sortBy === 'songs'
                ? 'bg-purple-500 text-white shadow-[0_0_12px_rgba(168,85,247,0.4)]'
                : 'bg-slate-950/70 text-slate-300 hover:text-white border border-white/10'
            }`}
          >
            <Music size={13} className={sortBy === 'songs' ? 'text-emerald-300' : 'text-purple-400'} />
            <span>Most Songs</span>
          </button>

          <button
            onClick={() => { setSortBy('alphabetical'); if (viewMode !== 'grid') setViewMode('grid'); }}
            className={`px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all ${
              sortBy === 'alphabetical'
                ? 'bg-purple-500 text-white shadow-[0_0_12px_rgba(168,85,247,0.4)]'
                : 'bg-slate-950/70 text-slate-300 hover:text-white border border-white/10'
            }`}
          >
            <ArrowDownAZ size={13} className={sortBy === 'alphabetical' ? 'text-sky-300' : 'text-purple-400'} />
            <span>Alphabetical (A→Z)</span>
          </button>

          {/* Threshold Filter Chips */}
          <div className="h-4 w-[1px] bg-white/10 mx-1 hidden sm:block" />

          <button
            onClick={() => setMinFilter(prev => prev === '2albums' ? 'all' : '2albums')}
            className={`px-2.5 py-1.5 rounded-xl text-[11px] font-medium transition-all ${
              minFilter === '2albums'
                ? 'bg-indigo-500/25 border-indigo-400 text-indigo-200 border shadow-sm'
                : 'bg-white/5 text-slate-400 hover:text-white border border-white/5'
            }`}
          >
            2+ Albums
          </button>

          <button
            onClick={() => setMinFilter(prev => prev === '5albums' ? 'all' : '5albums')}
            className={`px-2.5 py-1.5 rounded-xl text-[11px] font-medium transition-all ${
              minFilter === '5albums'
                ? 'bg-indigo-500/25 border-indigo-400 text-indigo-200 border shadow-sm'
                : 'bg-white/5 text-slate-400 hover:text-white border border-white/5'
            }`}
          >
            5+ Albums
          </button>

          <button
            onClick={() => setMinFilter(prev => prev === '10songs' ? 'all' : '10songs')}
            className={`px-2.5 py-1.5 rounded-xl text-[11px] font-medium transition-all ${
              minFilter === '10songs'
                ? 'bg-indigo-500/25 border-indigo-400 text-indigo-200 border shadow-sm'
                : 'bg-white/5 text-slate-400 hover:text-white border border-white/5'
            }`}
          >
            10+ Songs
          </button>
        </div>

        {/* Row 3: View Mode Sub-tabs (By Name with A-Z vs By Genre with genre chips) */}
        {viewMode === 'grid' && (
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 border-t border-white/5">
            <div className="bg-slate-950/70 p-1 rounded-xl border border-white/10 flex items-center shrink-0 self-start">
              <button
                onClick={() => { setActiveTab('name'); setSelectedGenre('All'); }}
                className={`px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all ${
                  activeTab === 'name' 
                    ? 'bg-purple-500 text-white shadow-md font-semibold' 
                    : 'text-slate-400 hover:text-white'
                }`}
              >
                By Name
              </button>
              <button
                onClick={() => { setActiveTab('genre'); setSelectedLetter('All'); }}
                className={`px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all ${
                  activeTab === 'genre' 
                    ? 'bg-purple-500 text-white shadow-md font-semibold' 
                    : 'text-slate-400 hover:text-white'
                }`}
              >
                By Genre
              </button>
            </div>

            {/* Alphabet or Genre selection */}
            {activeTab === 'name' ? (
              <div className="flex items-center gap-1 overflow-x-auto pb-1 touch-scroll scrollbar-none flex-1">
                {alphabet.map(letter => {
                  const isSelected = selectedLetter === letter;
                  return (
                    <button
                      key={letter}
                      onClick={() => setSelectedLetter(letter)}
                      className={`min-w-[28px] h-7 px-2 flex items-center justify-center rounded-lg text-xs font-semibold transition-all shrink-0 ${
                        isSelected
                          ? 'bg-purple-500 text-white shadow-[0_0_10px_rgba(168,85,247,0.4)] scale-105'
                          : 'text-slate-400 hover:text-white hover:bg-white/5 active:scale-95'
                      }`}
                    >
                      {letter}
                    </button>
                  );
                })}
              </div>
            ) : (
              <div className="flex items-center gap-1.5 overflow-x-auto pb-1 touch-scroll scrollbar-none flex-1">
                {allGenres.map(genre => {
                  const isSelected = selectedGenre === genre;
                  return (
                    <button
                      key={genre}
                      onClick={() => setSelectedGenre(genre)}
                      className={`px-3 py-1 rounded-full text-xs font-medium transition-all shrink-0 flex items-center gap-1 border ${
                        isSelected
                          ? 'bg-purple-500 text-white border-purple-400 font-semibold shadow-sm'
                          : 'bg-slate-950/60 text-slate-400 border-white/5 hover:text-white'
                      }`}
                    >
                      <Tag size={11} className={isSelected ? 'text-white' : 'text-purple-400'} />
                      <span>{genre}</span>
                    </button>
                  );
                })}
              </div>
            )}
          </div>
        )}
      </div>

      {/* Content Area: Showcases Mode vs Grid View Mode */}
      {loading ? (
        <div className="flex flex-col items-center justify-center py-20 gap-3">
          <div className="w-8 h-8 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
          <span className="text-xs text-slate-400 font-medium uppercase tracking-wider">Loading Artists Catalog...</span>
        </div>
      ) : filteredArtists.length === 0 ? (
        <div className="text-center py-16 bg-slate-900/20 rounded-3xl border border-white/5">
          <p className="text-slate-400 text-sm">No artists found matching your filter.</p>
          <button
            onClick={resetFilters}
            className="mt-3 px-4 py-2 rounded-full bg-purple-500 hover:bg-purple-400 text-white text-xs font-semibold shadow-md active:scale-95 transition-all"
          >
            Clear filters
          </button>
        </div>
      ) : viewMode === 'showcases' ? (
        /* 🌟 Showcases / Carousels Mode */
        <div className="space-y-8 sm:space-y-10">
          {showcasesData.map(showcase => (
            <section key={showcase.id} className="space-y-3.5">
              <div className="flex items-center justify-between px-1">
                <div>
                  <h3 className="text-lg sm:text-2xl font-bold text-white flex items-center gap-2">
                    <span>{showcase.title}</span>
                    <span className="text-xs px-2 py-0.5 rounded-full bg-purple-500/15 text-purple-300 font-medium">
                      {showcase.artists.length}
                    </span>
                  </h3>
                  <p className="text-xs text-slate-400 mt-0.5">{showcase.subtitle}</p>
                </div>
                <button
                  onClick={() => {
                    if (showcase.id === 'top-played') setSortBy('plays');
                    else if (showcase.id === 'most-albums') setSortBy('albums');
                    else if (showcase.id === 'most-songs') setSortBy('songs');
                    else if (showcase.genreName) {
                      setActiveTab('genre');
                      setSelectedGenre(showcase.genreName);
                    }
                    setViewMode('grid');
                  }}
                  className="text-xs font-semibold text-purple-400 hover:text-purple-300 flex items-center gap-1 group transition-colors"
                >
                  <span>View all in grid</span>
                  <ChevronRight size={14} className="group-hover:translate-x-0.5 transition-transform" />
                </button>
              </div>

              {/* Horizontal Scrollable Carousel */}
              <div className="flex gap-3 sm:gap-4 overflow-x-auto pb-3 pt-1 touch-scroll scrollbar-none px-1">
                {showcase.artists.map(artist => (
                  <div key={artist.id} className="w-32 sm:w-36 md:w-40 shrink-0">
                    <ArtistCard artist={artist} user={user} getAuthParams={getAuthParams} />
                  </div>
                ))}
              </div>
            </section>
          ))}
        </div>
      ) : groupedByName ? (
        /* ▦ Mode 2A: Alphabetical Grouped Grid View */
        <div className="space-y-8">
          {groupedByName.map(([letter, artistsInGroup]) => (
            <div key={letter} className="space-y-3">
              <div className="flex items-center gap-3 border-b border-white/10 pb-2 px-1">
                <span className="text-lg font-bold text-purple-400">{letter}</span>
                <span className="text-xs text-slate-400 font-mono">({artistsInGroup.length})</span>
              </div>
              <ArtistGrid artists={artistsInGroup} user={user} getAuthParams={getAuthParams} />
            </div>
          ))}
        </div>
      ) : (
        /* ▦ Mode 2B: Flat Filtered/Sorted Grid View */
        <ArtistGrid artists={filteredArtists} user={user} getAuthParams={getAuthParams} />
      )}
    </div>
  );
}
