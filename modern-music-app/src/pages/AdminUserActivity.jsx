import { useState, useEffect, useCallback } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import {
  ChevronLeft,
  ShieldAlert,
  Users,
  Headphones,
  Music,
  Play,
  Heart,
  Disc3,
  Clock,
  PlayCircle,
  Sparkles,
  AlertCircle,
  RefreshCw
} from 'lucide-react';
import { fetchUserHistory, fetchUserFavorites } from '../components/admin/adminApi';
import { getCoverArtUrl, DEFAULT_COVER_ART } from '../utils/api';
import { formatDuration } from '../utils/formatters';
import { usePlayer } from '../context/PlayerContext';

function formatRelativeTime(epochSeconds) {
  if (!epochSeconds) return 'Never';
  const now = Math.floor(Date.now() / 1000);
  const diffSec = now - epochSeconds;
  if (diffSec < 60) return 'Just now';
  if (diffSec < 3600) {
    const mins = Math.floor(diffSec / 60);
    return `${mins}m ago`;
  }
  if (diffSec < 86400) {
    const hours = Math.floor(diffSec / 3600);
    return `${hours}h ago`;
  }
  if (diffSec < 86400 * 7) {
    const days = Math.floor(diffSec / 86400);
    return days === 1 ? 'Yesterday' : `${days}d ago`;
  }
  const date = new Date(epochSeconds * 1000);
  return date.toLocaleDateString('en-NZ', { timeZone: 'Pacific/Auckland', month: 'short', day: 'numeric', year: 'numeric' });
}

function formatAucklandFullDate(epochSeconds) {
  if (!epochSeconds) return '';
  const date = new Date(epochSeconds * 1000);
  try {
    return date.toLocaleString('en-NZ', {
      timeZone: 'Pacific/Auckland',
      dateStyle: 'medium',
      timeStyle: 'short'
    }) + ' (NZT)';
  } catch (_e) {
    return date.toLocaleString() + ' (NZT)';
  }
}

export default function AdminUserActivity() {
  const { username } = useParams();
  const navigate = useNavigate();
  const { playQueue, currentTrack, isPlaying } = usePlayer();

  const [activeTab, setActiveTab] = useState('history');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // History state
  const [historyData, setHistoryData] = useState({
    user: null,
    summary: { totalPlays: 0, uniqueSongs: 0, lastPlayedAt: null },
    history: [],
    topSongs: [],
    hasMore: false,
    offset: 0
  });
  const [loadingMore, setLoadingMore] = useState(false);

  // Favorites state
  const [favoritesData, setFavoritesData] = useState({
    user: null,
    songs: [],
    albums: [],
    artists: [],
    counts: { songs: 0, albums: 0, artists: 0 }
  });
  const [favSubTab, setFavSubTab] = useState('songs');

  const loadData = useCallback(async () => {
    if (!username) return;
    setLoading(true);
    setError(null);
    try {
      const [histRes, favRes] = await Promise.all([
        fetchUserHistory(username, { limit: 50, offset: 0 }),
        fetchUserFavorites(username)
      ]);
      setHistoryData(histRes);
      setFavoritesData(favRes);
    } catch (err) {
      console.error('Failed to load user activity:', err);
      setError(err.message || 'Could not load user activity');
    } finally {
      setLoading(false);
    }
  }, [username]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleLoadMorePlays = async () => {
    if (loadingMore || !historyData.hasMore) return;
    setLoadingMore(true);
    try {
      const nextOffset = (historyData.history?.length || 0);
      const res = await fetchUserHistory(username, { limit: 50, offset: nextOffset });
      setHistoryData(prev => ({
        ...prev,
        history: [...(prev.history || []), ...(res.history || [])],
        hasMore: res.hasMore,
        offset: nextOffset
      }));
    } catch (err) {
      console.error('Failed to load more plays:', err);
    } finally {
      setLoadingMore(false);
    }
  };

  const handlePlaySong = (songs, index) => {
    if (!songs || songs.length === 0) return;
    playQueue(songs, index);
  };

  const userMeta = historyData.user || favoritesData.user || { username, role: 'member', ampacheLinked: true };
  const isAdminPeer = userMeta.role === 'admin';
  const totalFavsCount = (favoritesData.counts?.songs || 0) + (favoritesData.counts?.albums || 0) + (favoritesData.counts?.artists || 0);

  return (
    <div className="max-w-6xl mx-auto pb-24 animate-in fade-in">
      {/* Breadcrumb Navigation */}
      <div className="flex items-center justify-between gap-4 mb-6">
        <Link
          to="/admin?tab=users"
          className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-medium text-slate-400 hover:text-white transition-colors"
        >
          <ChevronLeft size={16} />
          <span>Back to User Accounts</span>
        </Link>
        <button
          onClick={loadData}
          disabled={loading}
          className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-900 border border-white/10 text-xs font-medium text-slate-300 hover:text-white hover:bg-slate-800 transition-colors disabled:opacity-50"
          title="Refresh activity data"
        >
          <RefreshCw size={13} className={loading ? 'animate-spin' : ''} />
          <span>Refresh</span>
        </button>
      </div>

      {/* Header Profile Card */}
      <div className="bg-slate-900/60 backdrop-blur-md border border-white/10 rounded-3xl p-6 sm:p-8 mb-6 shadow-xl relative overflow-hidden">
        <div className="absolute top-0 right-0 w-96 h-96 bg-purple-500/10 rounded-full blur-3xl pointer-events-none -mr-20 -mt-20" />
        <div className="relative flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
          <div className="flex items-center gap-5">
            <div className="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-br from-purple-600 to-indigo-600 flex items-center justify-center text-white font-bold text-2xl sm:text-3xl shadow-lg shadow-purple-500/20 shrink-0">
              {username ? username.charAt(0).toUpperCase() : 'U'}
            </div>
            <div>
              <div className="flex items-center gap-3 flex-wrap">
                <h1 className="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">{username}</h1>
                {isAdminPeer ? (
                  <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-500/15 text-rose-400 border border-rose-500/30 shadow-sm shadow-rose-500/10">
                    <ShieldAlert size={13} /> Admin
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-500/15 text-blue-400 border border-blue-500/30">
                    <Users size={13} /> Standard User
                  </span>
                )}
                {!userMeta.ampacheLinked && (
                  <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-500/10 text-amber-300 border border-amber-500/20">
                    <AlertCircle size={12} /> Not Linked in Ampache
                  </span>
                )}
              </div>
              <p className="text-xs sm:text-sm text-slate-400 mt-1">
                Listening activity, streaming analytics, and saved catalog favorites.
              </p>
            </div>
          </div>
        </div>

        {/* Quick Stats Bar */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mt-6 pt-6 border-t border-white/5">
          <div className="bg-slate-800/40 rounded-2xl p-3.5 border border-white/5">
            <div className="flex items-center gap-2 text-purple-400 text-xs font-medium mb-1">
              <PlayCircle size={15} /> Total Stream Plays
            </div>
            <div className="text-xl sm:text-2xl font-bold text-white">
              {historyData.summary?.totalPlays?.toLocaleString() || 0}
            </div>
          </div>
          <div className="bg-slate-800/40 rounded-2xl p-3.5 border border-white/5">
            <div className="flex items-center gap-2 text-blue-400 text-xs font-medium mb-1">
              <Music size={15} /> Unique Tracks
            </div>
            <div className="text-xl sm:text-2xl font-bold text-white">
              {historyData.summary?.uniqueSongs?.toLocaleString() || 0}
            </div>
          </div>
          <div className="bg-slate-800/40 rounded-2xl p-3.5 border border-white/5">
            <div className="flex items-center gap-2 text-rose-400 text-xs font-medium mb-1">
              <Heart size={15} /> Catalog Favorites
            </div>
            <div className="text-xl sm:text-2xl font-bold text-white">
              {totalFavsCount.toLocaleString()}
            </div>
          </div>
          <div className="bg-slate-800/40 rounded-2xl p-3.5 border border-white/5">
            <div className="flex items-center gap-2 text-emerald-400 text-xs font-medium mb-1">
              <Clock size={15} /> Last Stream Event
            </div>
            <div
              className="text-sm sm:text-base font-semibold text-white truncate"
              title={formatAucklandFullDate(historyData.summary?.lastPlayedAt)}
            >
              {formatRelativeTime(historyData.summary?.lastPlayedAt)}
            </div>
          </div>
        </div>
      </div>

      {/* Main Tabs (History vs Favourites) */}
      <div className="flex items-center gap-2 p-1.5 rounded-2xl bg-slate-900/80 border border-white/10 mb-6">
        <button
          onClick={() => setActiveTab('history')}
          className={`flex-1 py-2.5 rounded-xl text-xs sm:text-sm font-semibold flex items-center justify-center gap-2 transition-all ${
            activeTab === 'history'
              ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/25'
              : 'text-slate-400 hover:text-white hover:bg-white/5'
          }`}
        >
          <Headphones size={16} />
          <span>Listening History</span>
          <span className="px-2 py-0.5 rounded-full text-xs bg-white/15">
            {historyData.summary?.totalPlays || 0}
          </span>
        </button>
        <button
          onClick={() => setActiveTab('favorites')}
          className={`flex-1 py-2.5 rounded-xl text-xs sm:text-sm font-semibold flex items-center justify-center gap-2 transition-all ${
            activeTab === 'favorites'
              ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/25'
              : 'text-slate-400 hover:text-white hover:bg-white/5'
          }`}
        >
          <Heart size={16} />
          <span>Catalog Favorites</span>
          <span className="px-2 py-0.5 rounded-full text-xs bg-white/15">
            {totalFavsCount}
          </span>
        </button>
      </div>

      {/* Error Banner */}
      {error && (
        <div className="p-4 mb-6 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm flex items-center gap-3">
          <AlertCircle size={18} className="shrink-0" />
          <span>{error}</span>
        </div>
      )}

      {/* Loading State */}
      {loading ? (
        <div className="p-20 flex flex-col items-center justify-center gap-3">
          <div className="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin" />
          <p className="text-xs text-slate-400 font-medium">Loading user activity and favorites...</p>
        </div>
      ) : (
        <>
          {/* TAB 1: LISTENING HISTORY */}
          {activeTab === 'history' && (
            <div className="space-y-6">
              {/* Logging disclaimer notice */}
              <div className="px-4 py-3 rounded-xl bg-slate-900/40 border border-white/5 text-xs text-slate-400 flex items-center justify-between flex-wrap gap-2">
                <span>
                  Streams are recorded when a track is played for 30+ seconds or 50% duration.
                </span>
                <span className="text-slate-500 font-mono text-[11px]">
                  Times displayed relative to Pacific/Auckland
                </span>
              </div>

              {/* Top 10 Most Played Songs Strip */}
              {historyData.topSongs && historyData.topSongs.length > 0 && (
                <div className="bg-slate-900/50 backdrop-blur-sm border border-white/5 rounded-3xl p-6">
                  <div className="flex items-center gap-2 mb-4">
                    <Sparkles size={18} className="text-amber-400" />
                    <h2 className="text-lg font-bold text-white">Top Played Songs</h2>
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    {historyData.topSongs.map((song, idx) => {
                      const isCurrentPlaying = isPlaying && currentTrack?.id === song.id;
                      return (
                        <div
                          key={`top-${song.id}-${idx}`}
                          onClick={() => handlePlaySong(historyData.topSongs, idx)}
                          className="flex items-center gap-3 p-3 rounded-2xl bg-white/[0.02] hover:bg-white/[0.06] border border-white/5 transition-all group cursor-pointer"
                        >
                          <span className="w-5 text-center text-xs font-bold text-slate-500 group-hover:text-purple-400 transition-colors">
                            {idx + 1}
                          </span>
                          <div className="w-11 h-11 rounded-xl bg-slate-800 overflow-hidden shrink-0 relative">
                            <img
                              src={getCoverArtUrl(song.coverArt || song.albumId)}
                              alt={song.title}
                              className="w-full h-full object-cover"
                              onError={(e) => { e.currentTarget.src = DEFAULT_COVER_ART; }}
                            />
                            <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                              <Play size={14} fill="white" className="text-white ml-0.5" />
                            </div>
                          </div>
                          <div className="flex-1 min-w-0">
                            <div className={`text-sm font-semibold truncate ${isCurrentPlaying ? 'text-purple-400' : 'text-white'}`}>
                              {song.title}
                            </div>
                            <div className="text-xs text-slate-400 truncate">
                              {song.artist}
                            </div>
                          </div>
                          <div className="text-right shrink-0">
                            <span className="inline-block px-2 py-0.5 rounded-full text-[11px] font-bold bg-purple-500/15 text-purple-300 border border-purple-500/20">
                              {song.plays} {song.plays === 1 ? 'play' : 'plays'}
                            </span>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              )}

              {/* Chronological Recent Stream Plays Timeline */}
              <div className="bg-slate-900/50 backdrop-blur-sm border border-white/5 rounded-3xl overflow-hidden">
                <div className="p-6 border-b border-white/5 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Clock size={18} className="text-purple-400" />
                    <h2 className="text-lg font-bold text-white">Playback Timeline</h2>
                  </div>
                  <span className="text-xs font-medium text-slate-400">
                    {historyData.history?.length || 0} shown
                  </span>
                </div>

                {(!historyData.history || historyData.history.length === 0) ? (
                  <div className="p-16 text-center">
                    <Headphones size={36} className="text-slate-600 mx-auto mb-3" />
                    <p className="text-slate-400 text-sm font-medium">No listening history recorded yet.</p>
                    <p className="text-slate-600 text-xs mt-1">Plays longer than 30s will automatically appear here.</p>
                  </div>
                ) : (
                  <div className="divide-y divide-white/5">
                    {historyData.history.map((item, index) => {
                      const isCurrentPlaying = isPlaying && currentTrack?.id === item.id;
                      return (
                        <div
                          key={`hist-${item.eventId || item.id}-${index}`}
                          onClick={() => handlePlaySong(historyData.history, index)}
                          className="flex items-center gap-3 sm:gap-4 px-4 sm:px-6 py-3.5 hover:bg-white/[0.04] transition-colors group cursor-pointer"
                        >
                          {/* Artwork with play overlay */}
                          <div className="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-slate-800 overflow-hidden shrink-0 relative">
                            <img
                              src={getCoverArtUrl(item.coverArt || item.albumId)}
                              alt={item.title}
                              className="w-full h-full object-cover"
                              onError={(e) => { e.currentTarget.src = DEFAULT_COVER_ART; }}
                            />
                            <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                              <Play size={14} fill="white" className="text-white ml-0.5" />
                            </div>
                          </div>

                          {/* Song Title & Artist/Album */}
                          <div className="flex-1 min-w-0">
                            <div className={`text-sm font-semibold truncate ${isCurrentPlaying ? 'text-purple-400' : 'text-white'}`}>
                              {item.title}
                            </div>
                            <div className="text-xs text-slate-400 truncate">
                              <span>{item.artist}</span>
                              {item.album && (
                                <span className="text-slate-600"> · {item.album}</span>
                              )}
                            </div>
                          </div>

                          {/* Duration */}
                          <div className="text-xs text-slate-400 font-mono hidden md:block shrink-0">
                            {formatDuration(item.duration)}
                          </div>

                          {/* Auckland Timestamp */}
                          <div
                            className="text-right shrink-0"
                            title={formatAucklandFullDate(item.playedAt)}
                          >
                            <div className="text-xs font-semibold text-slate-300">
                              {formatRelativeTime(item.playedAt)}
                            </div>
                            <div className="text-[10px] text-slate-500 font-mono hidden sm:block">
                              {new Date(item.playedAt * 1000).toLocaleTimeString('en-NZ', {
                                timeZone: 'Pacific/Auckland',
                                hour: '2-digit',
                                minute: '2-digit'
                              })}
                            </div>
                          </div>
                        </div>
                      );
                    })}

                    {/* Load More Button */}
                    {historyData.hasMore && (
                      <div className="p-4 flex justify-center bg-slate-900/40">
                        <button
                          onClick={handleLoadMorePlays}
                          disabled={loadingMore}
                          className="px-6 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold shadow-md transition-all disabled:opacity-50 flex items-center gap-2"
                        >
                          {loadingMore && <div className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />}
                          <span>{loadingMore ? 'Loading More...' : 'Load Older Plays'}</span>
                        </button>
                      </div>
                    )}
                  </div>
                )}
              </div>
            </div>
          )}

          {/* TAB 2: FAVORITES */}
          {activeTab === 'favorites' && (
            <div className="space-y-6">
              {/* Favorites Sub-tabs switcher */}
              <div className="flex items-center gap-2 border-b border-white/5 pb-3">
                <button
                  onClick={() => setFavSubTab('songs')}
                  className={`px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition-all ${
                    favSubTab === 'songs'
                      ? 'bg-purple-600 text-white shadow-md'
                      : 'text-slate-400 hover:text-white hover:bg-white/5'
                  }`}
                >
                  <Music size={14} />
                  <span>Liked Songs</span>
                  <span className="px-2 py-0.5 rounded-full text-[10px] bg-white/15">
                    {favoritesData.counts?.songs || 0}
                  </span>
                </button>
                <button
                  onClick={() => setFavSubTab('albums')}
                  className={`px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition-all ${
                    favSubTab === 'albums'
                      ? 'bg-purple-600 text-white shadow-md'
                      : 'text-slate-400 hover:text-white hover:bg-white/5'
                  }`}
                >
                  <Disc3 size={14} />
                  <span>Favorite Albums</span>
                  <span className="px-2 py-0.5 rounded-full text-[10px] bg-white/15">
                    {favoritesData.counts?.albums || 0}
                  </span>
                </button>
                <button
                  onClick={() => setFavSubTab('artists')}
                  className={`px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition-all ${
                    favSubTab === 'artists'
                      ? 'bg-purple-600 text-white shadow-md'
                      : 'text-slate-400 hover:text-white hover:bg-white/5'
                  }`}
                >
                  <Users size={14} />
                  <span>Favorite Artists</span>
                  <span className="px-2 py-0.5 rounded-full text-[10px] bg-white/15">
                    {favoritesData.counts?.artists || 0}
                  </span>
                </button>
              </div>

              {/* Sub-tab: Liked Songs */}
              {favSubTab === 'songs' && (
                <div className="bg-slate-900/50 backdrop-blur-sm border border-white/5 rounded-3xl overflow-hidden">
                  {(!favoritesData.songs || favoritesData.songs.length === 0) ? (
                    <div className="p-16 text-center">
                      <Heart size={36} className="text-slate-700 mx-auto mb-3" />
                      <p className="text-slate-400 text-sm font-medium">No liked songs found for this user.</p>
                    </div>
                  ) : (
                    <div className="divide-y divide-white/5">
                      {favoritesData.songs.map((song, idx) => {
                        const isCurrentPlaying = isPlaying && currentTrack?.id === song.id;
                        return (
                          <div
                            key={`fav-song-${song.id}-${idx}`}
                            onClick={() => handlePlaySong(favoritesData.songs, idx)}
                            className="flex items-center gap-3 sm:gap-4 px-4 sm:px-6 py-3 hover:bg-white/[0.04] transition-colors group cursor-pointer"
                          >
                            <div className="w-10 h-10 rounded-xl bg-slate-800 overflow-hidden shrink-0 relative">
                              <img
                                src={getCoverArtUrl(song.coverArt || song.albumId)}
                                alt={song.title}
                                className="w-full h-full object-cover"
                                onError={(e) => { e.currentTarget.src = DEFAULT_COVER_ART; }}
                              />
                              <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                <Play size={14} fill="white" className="text-white ml-0.5" />
                              </div>
                            </div>
                            <div className="flex-1 min-w-0">
                              <div className={`text-sm font-semibold truncate ${isCurrentPlaying ? 'text-purple-400' : 'text-white'}`}>
                                {song.title}
                              </div>
                              <div className="text-xs text-slate-400 truncate">
                                {song.artist} {song.album && ` · ${song.album}`}
                              </div>
                            </div>
                            <div className="text-xs text-slate-500 font-mono hidden sm:block">
                              {formatDuration(song.duration)}
                            </div>
                            <div className="text-rose-400 shrink-0">
                              <Heart size={16} fill="currentColor" />
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </div>
              )}

              {/* Sub-tab: Favorite Albums */}
              {favSubTab === 'albums' && (
                <div>
                  {(!favoritesData.albums || favoritesData.albums.length === 0) ? (
                    <div className="bg-slate-900/50 border border-white/5 rounded-3xl p-16 text-center">
                      <Disc3 size={36} className="text-slate-700 mx-auto mb-3" />
                      <p className="text-slate-400 text-sm font-medium">No favorite albums starred by this user.</p>
                    </div>
                  ) : (
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                      {favoritesData.albums.map((album) => (
                        <div
                          key={`fav-alb-${album.id}`}
                          onClick={() => navigate(`/albums/${album.id}`)}
                          className="bg-slate-900/60 border border-white/5 rounded-2xl p-3 hover:bg-slate-800/80 transition-all cursor-pointer group"
                        >
                          <div className="aspect-square rounded-xl overflow-hidden bg-slate-800 mb-2.5 relative">
                            <img
                              src={getCoverArtUrl(album.coverArt || album.id)}
                              alt={album.name}
                              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                              onError={(e) => { e.currentTarget.src = DEFAULT_COVER_ART; }}
                            />
                          </div>
                          <div className="text-sm font-semibold text-white truncate">{album.name}</div>
                          <div className="text-xs text-slate-400 truncate mt-0.5">{album.artist}</div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}

              {/* Sub-tab: Favorite Artists */}
              {favSubTab === 'artists' && (
                <div>
                  {(!favoritesData.artists || favoritesData.artists.length === 0) ? (
                    <div className="bg-slate-900/50 border border-white/5 rounded-3xl p-16 text-center">
                      <Users size={36} className="text-slate-700 mx-auto mb-3" />
                      <p className="text-slate-400 text-sm font-medium">No favorite artists starred by this user.</p>
                    </div>
                  ) : (
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                      {favoritesData.artists.map((artist) => (
                        <div
                          key={`fav-art-${artist.id}`}
                          onClick={() => navigate(`/artists/${artist.id}`)}
                          className="bg-slate-900/60 border border-white/5 rounded-2xl p-4 text-center hover:bg-slate-800/80 transition-all cursor-pointer group"
                        >
                          <div className="w-20 h-20 rounded-full mx-auto overflow-hidden bg-slate-800 mb-3 relative">
                            <img
                              src={getCoverArtUrl(artist.coverArt || artist.id)}
                              alt={artist.name}
                              className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                              onError={(e) => { e.currentTarget.src = DEFAULT_COVER_ART; }}
                            />
                          </div>
                          <div className="text-sm font-semibold text-white truncate">{artist.name}</div>
                          <div className="text-xs text-purple-400 mt-1">Artist</div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </div>
          )}
        </>
      )}
    </div>
  );
}
