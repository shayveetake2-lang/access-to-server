import { useAuth } from '../context/AuthContext';
import { useEffect, useState, useRef, useCallback, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Play, Clock, Plus, ListPlus, Volume2, Shuffle, Flame, TrendingUp, Sparkles, RefreshCw, Search, X, Music, Layers, ListMusic } from 'lucide-react';
import { usePlayer } from '../context/PlayerContext';
import { usePlaylistModal } from '../context/PlaylistModalContext';
import { useToast } from '../context/ToastContext';
import { getApiProxyUrl, getCoverArtUrl, DEFAULT_COVER_ART } from '../utils/api';
import useInfiniteScroll from '../hooks/useInfiniteScroll';

const SORT_OPTIONS = [
  { id: 'title_asc', label: '🔤 Title (A → Z)' },
  { id: 'title_desc', label: '🔤 Title (Z → A)' },
  { id: 'artist_asc', label: '🎤 Artist (A → Z)' },
  { id: 'artist_desc', label: '🎤 Artist (Z → A)' },
  { id: 'album_asc', label: '💿 Album (A → Z)' },
  { id: 'plays_desc', label: '🔥 Most Streamed' },
  { id: 'duration_desc', label: '⏱️ Duration (Longest)' },
  { id: 'duration_asc', label: '⏱️ Duration (Shortest)' },
  { id: 'newest', label: '✨ Recently Added' },
];

export default function AllSongs() {
  const [activeTab, setActiveTab] = useState('library'); // 'library' | 'charts'
  
  // Library Infinite Scroll State (Handles 10k+ Songs)
  const [librarySongs, setLibrarySongs] = useState([]);
  const [libraryTotal, setLibraryTotal] = useState(0);
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const [sortBy, setSortBy] = useState('title_asc');
  const [isLibraryLoading, setIsLibraryLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMoreLibrary, setHasMoreLibrary] = useState(true);

  // Top 100 Charts State
  const [chartSongs, setChartSongs] = useState([]);
  const [period, setPeriod] = useState('daily'); // 'daily' | 'weekly' | 'alltime'
  const [isChartLoading, setIsChartLoading] = useState(true);

  const { user, getAuthParams } = useAuth();
  const { playQueue, addToQueue, currentTrack, isPlaying } = usePlayer();
  const { openAddToPlaylistModal } = usePlaylistModal();
  const { showToast } = useToast();
  const navigate = useNavigate();

  // Debounce search query input (300ms)
  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedQuery(searchQuery.trim());
    }, 300);
    return () => clearTimeout(handler);
  }, [searchQuery]);

  // ── 1. Fetch Paginated Library Songs (Hardware-Optimized for 10,000+ Items) ──
  const fetchLibrarySongs = useCallback(async (reset = false, offset = 0) => {
    if (reset) {
      setIsLibraryLoading(true);
    } else {
      setIsLoadingMore(true);
    }

    try {
      const limit = 50;
      const url = `${getApiProxyUrl()}?action=getLibrarySongs&offset=${offset}&limit=${limit}&query=${encodeURIComponent(debouncedQuery)}&sort=${sortBy}`;
      
      let fetchedSongs = [];
      let totalCount = 0;
      let hasMore = false;

      try {
        const res = await fetch(url);
        const data = await res.json();
        if (data?.status === 'ok' && Array.isArray(data.songs)) {
          fetchedSongs = data.songs;
          totalCount = data.total || 0;
          hasMore = data.hasMore || false;
        }
      } catch (pe) {
        console.debug("Proxy library fetch notice:", pe);
      }

      // Fallback to Subsonic search3 if proxy endpoint unavailable
      if (fetchedSongs.length === 0 && offset === 0) {
        try {
          const auth = getAuthParams(user);
          const subUrl = debouncedQuery
            ? `/ampache/public/rest/index.php?action=search3&query=${encodeURIComponent(debouncedQuery)}&songOffset=${offset}&songCount=${limit}&${auth}`
            : `/ampache/public/rest/index.php?action=getRandomSongs&size=${limit}&${auth}`;
          const subRes = await fetch(subUrl);
          const subData = await subRes.json();
          const raw = subData?.['subsonic-response']?.searchResult3?.song || subData?.['subsonic-response']?.randomSongs?.song || [];
          fetchedSongs = Array.isArray(raw) ? raw : [raw];
          totalCount = fetchedSongs.length;
          hasMore = fetchedSongs.length >= limit;
        } catch (se) {
          console.debug("Subsonic fallback error:", se);
        }
      }

      setLibrarySongs(prev => reset ? fetchedSongs : [...prev, ...fetchedSongs]);
      setLibraryTotal(totalCount);
      setHasMoreLibrary(hasMore);
    } catch (err) {
      console.error("Failed to load library tracks:", err);
    } finally {
      setIsLibraryLoading(false);
      setIsLoadingMore(false);
    }
  }, [debouncedQuery, sortBy, user, getAuthParams]);

  // Trigger initial fetch or reset on query/sort changes
  useEffect(() => {
    if (activeTab === 'library') {
      fetchLibrarySongs(true, 0);
    }
  }, [debouncedQuery, sortBy, activeTab, fetchLibrarySongs]);

  // Infinite Scroll Callback: load next page
  const loadMoreLibrarySongs = useCallback(() => {
    if (!hasMoreLibrary || isLibraryLoading || isLoadingMore) return;
    fetchLibrarySongs(false, librarySongs.length);
  }, [hasMoreLibrary, isLibraryLoading, isLoadingMore, librarySongs.length, fetchLibrarySongs]);

  const librarySentinelRef = useInfiniteScroll(
    loadMoreLibrarySongs,
    hasMoreLibrary,
    isLoadingMore || isLibraryLoading,
    '450px'
  );

  // ── 2. Fetch Top 100 Charts ──
  const fetchChartSongs = useCallback(async (activePeriod = period) => {
    setIsChartLoading(true);
    try {
      let loadedSongs = [];
      try {
        const proxyRes = await fetch(`${getApiProxyUrl()}?action=getTopSongs&size=100&period=${activePeriod}`);
        const proxyData = await proxyRes.json();
        if (proxyData?.status === 'ok' && Array.isArray(proxyData.songs) && proxyData.songs.length > 0) {
          loadedSongs = proxyData.songs;
        }
      } catch (pe) {
        console.debug("Proxy top songs fallback:", pe);
      }

      if (loadedSongs.length === 0) {
        const response = await fetch(`/ampache/public/rest/index.php?action=getRandomSongs&size=100&${getAuthParams(user)}`);
        const data = await response.json();
        if (data?.['subsonic-response']?.status === 'ok') {
          const raw = data['subsonic-response'].randomSongs?.song || [];
          loadedSongs = Array.isArray(raw) ? raw : [raw];
          loadedSongs.sort((a, b) => (b.playCount || 0) - (a.playCount || 0));
        }
      }

      setChartSongs(loadedSongs);
    } catch (err) {
      console.error("Failed to fetch chart songs:", err);
    } finally {
      setIsChartLoading(false);
    }
  }, [period, user, getAuthParams]);

  useEffect(() => {
    if (activeTab === 'charts') {
      fetchChartSongs(period);
    }
  }, [activeTab, period, fetchChartSongs]);

  const currentActiveSongs = activeTab === 'library' ? librarySongs : chartSongs;

  const playAll = () => {
    if (currentActiveSongs.length > 0) {
      playQueue(currentActiveSongs, 0);
      showToast(`▶ Playing ${currentActiveSongs.length} tracks`, "success");
    }
  };

  const shuffleAll = () => {
    if (currentActiveSongs.length === 0) return;
    const shuffled = [...currentActiveSongs];
    for (let i = shuffled.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
    }
    playQueue(shuffled, 0);
    showToast(`🔀 Shuffled & playing ${currentActiveSongs.length} tracks!`, "success");
  };

  const playFromTrack = (index) => {
    playQueue(currentActiveSongs, index);
  };

  const navigateToArtist = (artistId, e) => {
    if (e) e.stopPropagation();
    if (artistId) navigate(`/artists/${artistId}`);
  };

  const navigateToAlbum = (albumId, e) => {
    if (e) e.stopPropagation();
    if (albumId) navigate(`/albums/${albumId}`);
  };

  return (
    <div className="pb-28 max-w-7xl mx-auto space-y-5">
      {/* Header with Title and Global Play Buttons */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between mt-1 px-1 gap-4">
        <div className="min-w-0 pr-2">
          <div className="flex items-center gap-2 mb-1">
            <span className="px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase bg-purple-500/20 text-purple-300 border border-purple-500/30 flex items-center gap-1">
              <Music size={12} className="text-purple-400" />
              <span>Catalog Browser</span>
            </span>
            {libraryTotal > 0 && activeTab === 'library' && (
              <span className="text-xs text-slate-400 font-medium">
                {libraryTotal.toLocaleString()} total songs
              </span>
            )}
          </div>
          <h1 className="text-xl sm:text-3xl font-extrabold text-white tracking-tight">
            {activeTab === 'library' ? 'All Songs Library' : 'Top 100 Rankings'}
          </h1>
          <p className="text-xs sm:text-sm text-slate-400 mt-0.5">
            {activeTab === 'library' 
              ? 'Infinite-scrolling high performance browser across your entire 10k+ music library.' 
              : 'Cross-user trending leaderboard ranked by daily, weekly, and all-time stream count.'}
          </p>
        </div>

        {/* Global Play, Shuffle & Refresh Buttons */}
        <div className="flex items-center gap-2 sm:gap-3 shrink-0">
          <button 
            onClick={playAll} 
            disabled={currentActiveSongs.length === 0}
            className="bg-purple-500 hover:bg-purple-400 disabled:opacity-50 text-white px-3.5 py-2 sm:px-5 sm:py-2.5 rounded-full font-medium text-xs sm:text-sm flex items-center gap-1.5 sm:gap-2 transition-all shadow-[0_0_20px_rgba(168,85,247,0.4)] active:scale-95"
            title="Play tracks from top down"
          >
            <Play fill="currentColor" size={15} /> <span>Play All</span>
          </button>
          <button 
            onClick={shuffleAll} 
            disabled={currentActiveSongs.length === 0}
            className="bg-slate-900/80 hover:bg-slate-800 disabled:opacity-50 text-purple-300 hover:text-white px-3.5 py-2 sm:px-5 sm:py-2.5 rounded-full font-medium text-xs sm:text-sm flex items-center gap-1.5 sm:gap-2 border border-purple-500/30 transition-all shadow-md active:scale-95"
            title="Shuffle and play loaded tracks"
          >
            <Shuffle size={15} /> <span>Shuffle</span>
          </button>
          <button
            onClick={() => activeTab === 'library' ? fetchLibrarySongs(true, 0) : fetchChartSongs(period)}
            className="p-2 sm:p-2.5 rounded-full bg-slate-900/80 hover:bg-slate-800 text-slate-400 hover:text-white border border-white/5 transition-all"
            title="Refresh List"
            aria-label="Refresh list"
          >
            <RefreshCw size={15} className={(isLibraryLoading || isChartLoading) ? "animate-spin" : ""} />
          </button>
        </div>
      </div>

      {/* Main Tab Switcher: Full Library vs Top 100 Charts */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-900/60 backdrop-blur-md p-3 rounded-2xl border border-white/5">
        <div className="flex items-center gap-1.5 p-1 rounded-xl bg-slate-950/80 border border-white/10 w-fit">
          <button
            onClick={() => setActiveTab('library')}
            className={`px-3.5 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all ${
              activeTab === 'library'
                ? 'bg-purple-500 text-white shadow-md shadow-purple-500/20'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            <ListMusic size={14} />
            <span>Full Library ({libraryTotal > 0 ? libraryTotal.toLocaleString() : '10k+'})</span>
          </button>
          <button
            onClick={() => setActiveTab('charts')}
            className={`px-3.5 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all ${
              activeTab === 'charts'
                ? 'bg-purple-500 text-white shadow-md shadow-purple-500/20'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            <Flame size={14} className="text-amber-400" />
            <span>Top 100 Charts</span>
          </button>
        </div>

        {/* Filters for Active Tab */}
        {activeTab === 'library' ? (
          <div className="flex items-center gap-2.5 flex-1 sm:justify-end">
            {/* Real-time Search Box */}
            <div className="relative w-full sm:w-64">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none" size={15} />
              <input
                type="text"
                placeholder="Filter 10k+ songs by title or artist..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full bg-slate-950/70 border border-white/10 rounded-xl pl-9 pr-8 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-colors"
              />
              {searchQuery && (
                <button
                  onClick={() => setSearchQuery('')}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white"
                >
                  <X size={13} />
                </button>
              )}
            </div>

            {/* Sort Selector */}
            <select
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value)}
              className="bg-slate-950/70 border border-white/10 rounded-xl px-3 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-purple-500 transition-colors cursor-pointer shrink-0"
            >
              {SORT_OPTIONS.map(opt => (
                <option key={opt.id} value={opt.id} className="bg-slate-900 text-white">
                  {opt.label}
                </option>
              ))}
            </select>
          </div>
        ) : (
          /* Period Filter Tabs for Charts */
          <div className="flex items-center gap-1.5 p-1 rounded-xl bg-slate-950/70 border border-white/10 w-fit">
            <button
              onClick={() => setPeriod('daily')}
              className={`px-3 py-1 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all ${
                period === 'daily'
                  ? 'bg-purple-500 text-white'
                  : 'text-slate-400 hover:text-white'
              }`}
            >
              <Flame size={13} className={period === 'daily' ? 'text-amber-300' : ''} />
              <span>Daily</span>
            </button>
            <button
              onClick={() => setPeriod('weekly')}
              className={`px-3 py-1 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all ${
                period === 'weekly'
                  ? 'bg-purple-500 text-white'
                  : 'text-slate-400 hover:text-white'
              }`}
            >
              <TrendingUp size={13} />
              <span>Weekly</span>
            </button>
            <button
              onClick={() => setPeriod('alltime')}
              className={`px-3 py-1 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all ${
                period === 'alltime'
                  ? 'bg-purple-500 text-white'
                  : 'text-slate-400 hover:text-white'
              }`}
            >
              <Sparkles size={13} />
              <span>All-Time</span>
            </button>
          </div>
        )}
      </div>

      {/* Main Content: Songs List */}
      {(isLibraryLoading && activeTab === 'library') || (isChartLoading && activeTab === 'charts') ? (
        <div className="flex justify-center py-24">
          <div className="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin" />
        </div>
      ) : currentActiveSongs.length === 0 ? (
        <div className="text-center py-20 bg-slate-900/40 rounded-3xl border border-white/5 p-8 max-w-md mx-auto">
          <Music size={40} className="mx-auto text-slate-500 mb-3" />
          <h3 className="text-lg font-bold text-white mb-1">No songs found</h3>
          <p className="text-xs sm:text-sm text-slate-400 mb-4">
            {searchQuery ? `No tracks matched "${searchQuery}".` : 'No songs available in this view.'}
          </p>
          {searchQuery && (
            <button
              onClick={() => setSearchQuery('')}
              className="px-4 py-2 rounded-full bg-purple-500 hover:bg-purple-400 text-white text-xs font-semibold"
            >
              Clear Search
            </button>
          )}
        </div>
      ) : (
        <>
          {/* Mobile Song Rows (High-Contrast for Dark Mode) */}
          <div className="sm:hidden space-y-1 bg-slate-900/40 backdrop-blur-sm rounded-2xl p-2 border border-white/5">
            {currentActiveSongs.map((song, index) => {
              const isCurrent = currentTrack?.id === song.id;
              const rankNum = song.rank || index + 1;
              const artistId = song.artistId || song.artist_id;
              const albumId = song.albumId || song.album_id || song.parent;

              return (
                <div 
                  key={`${song.id}-${index}`} 
                  onClick={() => playFromTrack(index)} 
                  className={`flex items-center justify-between p-2.5 rounded-xl transition-colors cursor-pointer group ${
                    isCurrent ? 'bg-purple-900/40 border border-purple-400/50 text-white' : 'hover:bg-white/5 active:bg-white/10'
                  }`}
                >
                  <div className="flex items-center gap-2.5 min-w-0 flex-1 pr-2">
                    <span className="w-6 text-center font-mono text-[11px] font-bold text-slate-400 shrink-0">
                      {activeTab === 'charts' && rankNum <= 3 
                        ? ['🥇', '🥈', '🥉'][rankNum - 1] 
                        : `#${rankNum}`}
                    </span>

                    {/* Clickable Album Art Thumbnail */}
                    <div 
                      onClick={(e) => navigateToAlbum(albumId, e)}
                      className={`relative w-11 h-11 shrink-0 rounded-xl overflow-hidden bg-slate-800 border border-white/5 ${albumId ? 'active:scale-95 transition-transform' : ''}`}
                      title={albumId ? `View Album (${song.album || 'Tracklist'})` : ''}
                    >
                      <img 
                        src={getCoverArtUrl(song.coverArt || song.id, getAuthParams(user))} 
                        className="w-full h-full object-cover" 
                        alt="" 
                        loading="lazy" 
                        onError={(e) => {
                          if (e.currentTarget.src !== DEFAULT_COVER_ART) {
                            e.currentTarget.src = DEFAULT_COVER_ART;
                          }
                        }}
                      />
                      {isCurrent && (
                        <div className="absolute inset-0 bg-black/40 backdrop-blur-[1px] flex items-center justify-center">
                          <Volume2 size={16} className={`text-purple-400 ${isPlaying ? 'animate-pulse' : ''}`} />
                        </div>
                      )}
                    </div>

                    <div className="min-w-0 flex-1">
                      <h4 className={`text-xs font-semibold truncate leading-snug ${
                        isCurrent ? 'text-purple-200 font-bold' : 'text-white group-hover:text-purple-400'
                      }`}>
                        {song.title}
                      </h4>
                      <p className="text-[11px] text-slate-300 truncate mt-0.5 flex items-center gap-1.5 font-medium">
                        {artistId ? (
                          <span 
                            onClick={(e) => navigateToArtist(artistId, e)}
                            className="hover:text-purple-300 hover:underline cursor-pointer truncate"
                          >
                            {song.artist || 'Unknown Artist'}
                          </span>
                        ) : (
                          <span className="truncate">{song.artist || 'Unknown Artist'}</span>
                        )}
                        {song.dailyPlays > 0 && period === 'daily' && activeTab === 'charts' && (
                          <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30 shrink-0">
                            {song.dailyPlays} today
                          </span>
                        )}
                      </p>
                    </div>
                  </div>

                  <div className="flex items-center gap-1 shrink-0" onClick={(e) => e.stopPropagation()}>
                    <span className="text-[11px] text-slate-400 font-mono mr-1">
                      {Math.floor(song.duration / 60)}:{(song.duration % 60).toString().padStart(2, '0')}
                    </span>
                    <button 
                      onClick={() => { 
                        addToQueue(song); 
                        showToast(`Added "${song.title}" to queue`, 'success');
                      }} 
                      className="w-8 h-8 flex items-center justify-center rounded-lg bg-white/5 hover:bg-purple-500/25 text-slate-300 hover:text-purple-300 active:scale-95 transition-all"
                      title="Add to Queue (Play Next)"
                      aria-label="Add to Queue"
                    >
                      <ListPlus size={16} />
                    </button>
                    <button 
                      onClick={() => openAddToPlaylistModal(song.id)} 
                      className="w-8 h-8 flex items-center justify-center rounded-lg bg-white/5 hover:bg-purple-500/25 text-slate-300 hover:text-purple-300 active:scale-95 transition-all"
                      title="Add to Playlist"
                      aria-label="Add to Playlist"
                    >
                      <Plus size={16} />
                    </button>
                  </div>
                </div>
              );
            })}
          </div>

          {/* Desktop Responsive Table View */}
          <div className="hidden sm:block bg-slate-900/40 backdrop-blur-sm rounded-2xl border border-white/5 overflow-hidden shadow-xl">
            <table className="w-full text-left border-collapse table-fixed">
              <thead>
                <tr className="text-slate-400 border-b border-white/10 text-xs uppercase tracking-wider bg-slate-950/40">
                  <th className="font-semibold px-4 sm:px-5 py-4 w-14 text-center">#</th>
                  <th className="font-semibold px-4 py-4 w-auto">Title</th>
                  <th className="font-semibold px-4 py-4 hidden lg:table-cell w-40 xl:w-52">Artist</th>
                  <th className="font-semibold px-4 py-4 hidden xl:table-cell w-40 xl:w-48">Album</th>
                  <th className="font-semibold px-4 py-4 w-28 md:w-36 text-center">Plays</th>
                  <th className="font-semibold px-4 sm:px-6 py-4 text-right w-36 sm:w-44 shrink-0">
                    <div className="flex items-center justify-end gap-2 text-slate-400">
                      <Clock size={16} />
                      <span className="text-[11px] normal-case tracking-normal">Actions</span>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5 text-sm">
                {currentActiveSongs.map((song, index) => {
                  const isCurrent = currentTrack?.id === song.id;
                  const rankNum = song.rank || index + 1;
                  const artistId = song.artistId || song.artist_id;
                  const albumId = song.albumId || song.album_id || song.parent;

                  return (
                    <tr 
                      key={`${song.id}-${index}`} 
                      onClick={() => playFromTrack(index)}
                      className={`transition-colors group cursor-pointer ${
                        isCurrent ? 'bg-purple-900/30 text-purple-200' : 'text-slate-200 hover:bg-white/5'
                      }`}
                    >
                      <td className="px-4 sm:px-5 py-3 text-xs text-slate-400 text-center w-14">
                        {isCurrent ? (
                          <Volume2 size={15} className={`text-purple-400 mx-auto ${isPlaying ? 'animate-pulse' : ''}`} />
                        ) : activeTab === 'charts' && rankNum <= 3 ? (
                          <span className="text-sm">{['🥇', '🥈', '🥉'][rankNum - 1]}</span>
                        ) : (
                          <span className="font-mono">{rankNum}</span>
                        )}
                      </td>

                      <td className="px-4 py-3 min-w-0">
                        <div className="flex items-center gap-3 min-w-0">
                          {/* Clickable Album Art */}
                          <div
                            onClick={(e) => navigateToAlbum(albumId, e)}
                            className={`w-10 h-10 rounded-lg overflow-hidden bg-slate-800 shrink-0 border border-white/5 shadow-sm group/art relative ${albumId ? 'hover:ring-2 hover:ring-purple-400 transition-all' : ''}`}
                            title={albumId ? `View Album (${song.album || 'Tracklist'})` : ''}
                          >
                            <img 
                              src={getCoverArtUrl(song.coverArt || song.id, getAuthParams(user))} 
                              className="w-full h-full object-cover" 
                              alt="" 
                              loading="lazy" 
                              onError={(e) => {
                                if (e.currentTarget.src !== DEFAULT_COVER_ART) {
                                  e.currentTarget.src = DEFAULT_COVER_ART;
                                }
                              }}
                            />
                          </div>

                          <div className="min-w-0 flex-1">
                            <div className={`font-semibold transition-colors truncate ${
                              isCurrent ? 'text-purple-200' : 'text-white group-hover:text-purple-400'
                            }`}>
                              {song.title}
                            </div>
                            <div className="text-xs text-slate-400 truncate lg:hidden mt-0.5">
                              {artistId ? (
                                <span onClick={(e) => navigateToArtist(artistId, e)} className="hover:text-purple-300 hover:underline">
                                  {song.artist}
                                </span>
                              ) : (
                                song.artist
                              )}
                              {song.album ? ` • ${song.album}` : ''}
                            </div>
                          </div>
                        </div>
                      </td>

                      {/* Artist Column (Clickable Dynamic Routing) */}
                      <td className="px-4 py-3 text-slate-300 truncate hidden lg:table-cell">
                        {artistId ? (
                          <span
                            onClick={(e) => navigateToArtist(artistId, e)}
                            className="hover:text-purple-300 hover:underline cursor-pointer font-medium"
                            title="Go to Artist Profile"
                          >
                            {song.artist || 'Unknown Artist'}
                          </span>
                        ) : (
                          <span>{song.artist || 'Unknown Artist'}</span>
                        )}
                      </td>

                      {/* Album Column (Clickable Dynamic Routing) */}
                      <td className="px-4 py-3 text-slate-400 truncate hidden xl:table-cell">
                        {albumId ? (
                          <span
                            onClick={(e) => navigateToAlbum(albumId, e)}
                            className="hover:text-slate-200 hover:underline cursor-pointer"
                            title="View Album Tracklist"
                          >
                            {song.album || 'Unknown Album'}
                          </span>
                        ) : (
                          <span>{song.album || 'Unknown Album'}</span>
                        )}
                      </td>

                      {/* Plays Column */}
                      <td className="px-4 py-3 text-center">
                        {activeTab === 'charts' && period === 'daily' && song.dailyPlays > 0 ? (
                          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30">
                            <Flame size={12} className="text-amber-400" />
                            {song.dailyPlays} {song.dailyPlays === 1 ? 'play' : 'plays'}
                          </span>
                        ) : activeTab === 'charts' && period === 'weekly' && song.weeklyPlays > 0 ? (
                          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                            <TrendingUp size={12} />
                            {song.weeklyPlays} this week
                          </span>
                        ) : (
                          <span className="text-xs text-slate-400 font-mono font-medium">
                            {(song.playCount || 0).toLocaleString()} plays
                          </span>
                        )}
                      </td>

                      {/* Actions Column */}
                      <td className="px-4 sm:px-6 py-3 text-right w-36 sm:w-44 shrink-0">
                        <div className="flex items-center justify-end gap-1.5 font-mono text-xs" onClick={(e) => e.stopPropagation()}>
                          <span className="text-slate-400 mr-1 text-[11px] sm:text-xs">
                            {Math.floor(song.duration / 60)}:{(song.duration % 60).toString().padStart(2, '0')}
                          </span>
                          <button 
                            onClick={() => { 
                              addToQueue(song); 
                              showToast(`Added "${song.title}" to play next`, 'success');
                            }} 
                            className="w-8 h-8 flex items-center justify-center rounded-lg bg-white/5 hover:bg-purple-500/25 text-slate-300 hover:text-purple-300 active:scale-95 transition-all shadow-sm shrink-0"
                            title="Add to Queue (Play Next)"
                            aria-label="Add to Queue"
                          >
                            <ListPlus size={16} />
                          </button>
                          <button 
                            onClick={() => openAddToPlaylistModal(song.id)} 
                            className="w-8 h-8 flex items-center justify-center rounded-lg bg-white/5 hover:bg-purple-500/25 text-slate-300 hover:text-purple-300 active:scale-95 transition-all shadow-sm shrink-0"
                            title="Add to Playlist"
                            aria-label="Add to Playlist"
                          >
                            <Plus size={16} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* Infinite Scroll Sentinel for 10k+ Songs */}
          {activeTab === 'library' && hasMoreLibrary && (
            <div ref={librarySentinelRef} className="py-8 flex justify-center items-center text-xs text-slate-400 gap-2.5">
              <div className="w-5 h-5 border-2 border-purple-500 border-t-transparent rounded-full animate-spin" />
              <span>
                {isLoadingMore 
                  ? `Loading more songs (${librarySongs.length} of ${libraryTotal.toLocaleString()})...` 
                  : `Scroll down to load more tracks (${librarySongs.length} loaded)`}
              </span>
            </div>
          )}

          {activeTab === 'library' && !hasMoreLibrary && librarySongs.length > 50 && (
            <div className="py-8 text-center text-xs text-slate-500">
              ✓ All {librarySongs.length.toLocaleString()} songs loaded from server catalog.
            </div>
          )}
        </>
      )}
    </div>
  );
}
