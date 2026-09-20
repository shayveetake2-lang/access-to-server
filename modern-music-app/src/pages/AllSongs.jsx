import { useAuth } from '../context/AuthContext';
import { useEffect, useState } from 'react';
import { Play, Clock, Plus, ListPlus, Volume2, Shuffle, Flame, TrendingUp, Sparkles, RefreshCw } from 'lucide-react';
import { usePlayer } from '../context/PlayerContext';
import { usePlaylistModal } from '../context/PlaylistModalContext';
import { useToast } from '../context/ToastContext';
import { getApiProxyUrl } from '../utils/api';

export default function AllSongs() {
  const [songs, setSongs] = useState([]);
  const [period, setPeriod] = useState('daily'); // 'daily' | 'weekly' | 'alltime'
  const { user, getAuthParams } = useAuth();
  const [loading, setLoading] = useState(true);
  const { playQueue, addToQueue, currentTrack, isPlaying } = usePlayer();
  const { openAddToPlaylistModal } = usePlaylistModal();
  const { showToast } = useToast();

  const fetchSongs = async (activePeriod = period) => {
    setLoading(true);
    try {
      // Fetch top 100 songs from proxy API with daily/weekly/alltime aggregation
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
          // Sort by playCount if available
          loadedSongs.sort((a, b) => (b.playCount || 0) - (a.playCount || 0));
        }
      }

      setSongs(loadedSongs);
    } catch (err) {
      console.error("Failed to fetch songs:", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchSongs(period);
  }, [user, period]);

  const playAll = () => {
    if (songs.length > 0) {
      playQueue(songs, 0);
      showToast(`▶ Playing Top 100 ${period === 'daily' ? 'Daily' : period === 'weekly' ? 'Weekly' : 'All-Time'} tracks`, "success");
    }
  };

  const shuffleAll = () => {
    if (songs.length === 0) return;
    // Authentic Fisher-Yates array randomization
    const shuffled = [...songs];
    for (let i = shuffled.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
    }
    playQueue(shuffled, 0);
    showToast("🔀 Shuffled & playing Top 100 tracks!", "success");
  };
  
  const playFromTrack = (index) => {
    playQueue(songs, index);
  };

  return (
    <div className="pb-28 max-w-7xl mx-auto">
      {/* Header with Title and Global Play Buttons */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between mb-4 mt-1 px-1 gap-3">
        <div className="min-w-0 pr-2">
          <div className="flex items-center gap-2 mb-1">
            <span className="px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wider uppercase bg-purple-500/20 text-purple-300 border border-purple-500/30 flex items-center gap-1">
              <Flame size={12} className="text-amber-400" /> Live Cross-User Chart
            </span>
            <span className="text-xs text-slate-500">Updated Daily</span>
          </div>
          <h1 className="text-xl sm:text-3xl font-bold text-white mb-0.5">Top 100 Songs</h1>
          <p className="text-xs sm:text-sm text-slate-400">
            {period === 'daily' && "Ranked by today's most streamed tracks across all listeners on the server"}
            {period === 'weekly' && "Hottest tracks played across all listeners over the past 7 days"}
            {period === 'alltime' && "All-time most played songs in your server catalog"}
          </p>
        </div>
        <div className="flex items-center gap-2 sm:gap-3 shrink-0">
          <button 
            onClick={playAll} 
            className="bg-purple-500 hover:bg-purple-400 text-white px-3.5 py-2 sm:px-5 sm:py-2.5 rounded-full font-medium text-xs sm:text-sm flex items-center gap-1.5 sm:gap-2 transition-all shadow-[0_0_20px_rgba(168,85,247,0.4)] active:scale-95"
            title="Play all tracks from #1 down"
          >
            <Play fill="currentColor" size={15} /> <span>Play All</span>
          </button>
          <button 
            onClick={shuffleAll} 
            className="bg-slate-900/80 hover:bg-slate-800 text-purple-300 hover:text-white px-3.5 py-2 sm:px-5 sm:py-2.5 rounded-full font-medium text-xs sm:text-sm flex items-center gap-1.5 sm:gap-2 border border-purple-500/30 transition-all shadow-md active:scale-95"
            title="Shuffle and play Top 100 tracks"
          >
            <Shuffle size={15} /> <span>Shuffle</span>
          </button>
          <button
            onClick={() => fetchSongs(period)}
            className="p-2 sm:p-2.5 rounded-full bg-slate-900/80 hover:bg-slate-800 text-slate-400 hover:text-white border border-white/5 transition-all"
            title="Refresh Rankings"
            aria-label="Refresh rankings"
          >
            <RefreshCw size={15} className={loading ? "animate-spin" : ""} />
          </button>
        </div>
      </div>

      {/* Period Filter Tabs */}
      <div className="flex items-center gap-1.5 p-1 rounded-xl bg-slate-900/80 border border-white/10 w-fit mb-6">
        <button
          onClick={() => setPeriod('daily')}
          className={`px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all ${
            period === 'daily'
              ? 'bg-purple-500 text-white shadow-md shadow-purple-500/20'
              : 'text-slate-400 hover:text-white'
          }`}
        >
          <Flame size={14} className={period === 'daily' ? 'text-amber-300' : ''} />
          <span>Daily (Past 24h)</span>
        </button>
        <button
          onClick={() => setPeriod('weekly')}
          className={`px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all ${
            period === 'weekly'
              ? 'bg-purple-500 text-white shadow-md shadow-purple-500/20'
              : 'text-slate-400 hover:text-white'
          }`}
        >
          <TrendingUp size={14} />
          <span>Weekly Hot</span>
        </button>
        <button
          onClick={() => setPeriod('alltime')}
          className={`px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all ${
            period === 'alltime'
              ? 'bg-purple-500 text-white shadow-md shadow-purple-500/20'
              : 'text-slate-400 hover:text-white'
          }`}
        >
          <Sparkles size={14} />
          <span>All-Time Legends</span>
        </button>
      </div>

      {loading ? (
        <div className="flex justify-center py-20"><div className="w-8 h-8 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>
      ) : (
        <>
          {/* Mobile Song Rows */}
          <div className="sm:hidden space-y-1 bg-slate-900/40 backdrop-blur-sm rounded-2xl p-2 border border-white/5">
            {songs.map((song, index) => {
              const isCurrent = currentTrack?.id === song.id;
              const rankNum = song.rank || index + 1;
              return (
                <div 
                  key={song.id} 
                  onClick={() => playFromTrack(index)} 
                  className={`flex items-center justify-between p-2 rounded-xl transition-colors cursor-pointer group ${
                    isCurrent ? 'bg-purple-500/15 border border-purple-500/30' : 'hover:bg-white/5 active:bg-white/10'
                  }`}
                >
                  <div className="flex items-center gap-2.5 min-w-0 flex-1 pr-2">
                    <span className="w-5 text-center font-mono text-[11px] font-bold text-slate-500 shrink-0">
                      {rankNum <= 3 ? ['🥇', '🥈', '🥉'][rankNum - 1] : `#${rankNum}`}
                    </span>
                    <div className="relative w-11 h-11 shrink-0">
                      <img 
                        src={`/ampache/public/rest/index.php?action=getCoverArt&id=${song.coverArt}&${getAuthParams(user)}`} 
                        className="w-11 h-11 rounded-xl bg-slate-800 object-cover shadow-sm border border-white/5" 
                        alt="" 
                        loading="lazy"
                      />
                      {isCurrent && (
                        <div className="absolute inset-0 bg-black/40 backdrop-blur-[1px] rounded-xl flex items-center justify-center">
                          <Volume2 size={16} className={`text-purple-400 ${isPlaying ? 'animate-pulse' : ''}`} />
                        </div>
                      )}
                    </div>
                    <div className="min-w-0 flex-1">
                      <h4 className={`text-xs font-semibold truncate transition-colors leading-snug ${
                        isCurrent ? 'text-purple-400 font-bold' : 'text-white group-hover:text-purple-400'
                      }`}>
                        {song.title}
                      </h4>
                      <p className="text-[11px] text-slate-400 truncate mt-0.5 flex items-center gap-1.5">
                        <span className="truncate">{song.artist}</span>
                        {song.dailyPlays > 0 && period === 'daily' && (
                          <span className="px-1.5 py-0.5 rounded text-[9px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30 shrink-0">
                            {song.dailyPlays} today
                          </span>
                        )}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-1 shrink-0">
                    <span className="text-[11px] text-slate-400 font-mono mr-1">
                      {Math.floor(song.duration / 60)}:{(song.duration % 60).toString().padStart(2, '0')}
                    </span>
                    <button 
                      onClick={(e) => { 
                        e.stopPropagation(); 
                        addToQueue(song); 
                        showToast(`Added "${song.title}" to play next`, 'success');
                      }} 
                      className="w-8 h-8 flex items-center justify-center rounded-lg bg-white/5 hover:bg-purple-500/25 text-slate-300 hover:text-purple-300 active:scale-95 transition-all shadow-sm"
                      title="Add to Queue (Play Next)"
                      aria-label="Add to Queue"
                    >
                      <ListPlus size={16} />
                    </button>
                    <button 
                      onClick={(e) => { e.stopPropagation(); openAddToPlaylistModal(song.id); }} 
                      className="w-8 h-8 flex items-center justify-center rounded-lg bg-white/5 hover:bg-purple-500/25 text-slate-300 hover:text-purple-300 active:scale-95 transition-all shadow-sm"
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

          {/* Desktop Table View */}
          <div className="hidden sm:block bg-slate-900/40 backdrop-blur-sm rounded-2xl border border-white/5 overflow-hidden">
            <table className="w-full text-left border-collapse table-fixed">
              <thead>
                <tr className="text-slate-400 border-b border-white/10 text-xs uppercase tracking-wider">
                  <th className="font-semibold px-4 sm:px-5 py-4 w-14 text-center">#</th>
                  <th className="font-semibold px-4 py-4 w-auto">Title</th>
                  <th className="font-semibold px-4 py-4 hidden lg:table-cell w-40 xl:w-52">Artist</th>
                  <th className="font-semibold px-4 py-4 hidden xl:table-cell w-40 xl:w-48">Album</th>
                  <th className="font-semibold px-4 py-4 w-32 md:w-36 text-center">Plays</th>
                  <th className="font-semibold px-4 sm:px-6 py-4 text-right w-36 sm:w-44 shrink-0">
                    <div className="flex items-center justify-end gap-2 text-slate-400">
                      <Clock size={16} />
                      <span className="text-[11px] normal-case tracking-normal">Actions</span>
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5 text-sm">
                {songs.map((song, index) => {
                  const isCurrent = currentTrack?.id === song.id;
                  const rankNum = song.rank || index + 1;
                  return (
                    <tr 
                      key={song.id} 
                      onClick={() => playFromTrack(index)}
                      className={`transition-colors group cursor-pointer ${
                        isCurrent ? 'bg-purple-500/10 text-purple-300' : 'text-slate-300 hover:bg-white/5'
                      }`}
                    >
                      <td className="px-4 sm:px-5 py-3 text-xs text-slate-400 text-center w-14">
                        {isCurrent ? (
                          <Volume2 size={15} className={`text-purple-400 mx-auto ${isPlaying ? 'animate-pulse' : ''}`} />
                        ) : rankNum <= 3 ? (
                          <span className="text-sm">{['🥇', '🥈', '🥉'][rankNum - 1]}</span>
                        ) : (
                          <span className="font-mono">{rankNum}</span>
                        )}
                      </td>
                      <td className="px-4 py-3 min-w-0">
                        <div className="flex items-center gap-3 min-w-0">
                          <img 
                            src={`/ampache/public/rest/index.php?action=getCoverArt&id=${song.coverArt}&${getAuthParams(user)}`} 
                            className="w-10 h-10 rounded-lg bg-slate-800 object-cover shrink-0 border border-white/5 shadow-sm" 
                            alt="" 
                            loading="lazy" 
                          />
                          <div className="min-w-0 flex-1">
                            <div className={`font-medium transition-colors truncate ${
                              isCurrent ? 'text-purple-300 font-semibold' : 'text-white group-hover:text-purple-400'
                            }`}>
                              {song.title}
                            </div>
                            <div className="text-xs text-slate-400 truncate lg:hidden mt-0.5">
                              {song.artist}{song.album ? ` • ${song.album}` : ''}
                            </div>
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-slate-300 truncate hidden lg:table-cell">{song.artist}</td>
                      <td className="px-4 py-3 text-slate-400 truncate hidden xl:table-cell">{song.album}</td>
                      <td className="px-4 py-3 text-center">
                        {period === 'daily' ? (
                          song.dailyPlays > 0 ? (
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30">
                              <Flame size={12} className="text-amber-400" />
                              {song.dailyPlays} {song.dailyPlays === 1 ? 'play' : 'plays'}
                            </span>
                          ) : (
                            <span className="text-xs text-slate-500 font-mono">{song.playCount || 0} total</span>
                          )
                        ) : period === 'weekly' ? (
                          song.weeklyPlays > 0 ? (
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                              <TrendingUp size={12} />
                              {song.weeklyPlays} this week
                            </span>
                          ) : (
                            <span className="text-xs text-slate-500 font-mono">{song.playCount || 0} total</span>
                          )
                        ) : (
                          <span className="text-xs text-slate-300 font-mono font-medium">{song.playCount || 0} plays</span>
                        )}
                      </td>
                      <td className="px-4 sm:px-6 py-3 text-right w-36 sm:w-44 shrink-0">
                        <div className="flex items-center justify-end gap-1.5 font-mono text-xs">
                          <span className="text-slate-400 mr-1 text-[11px] sm:text-xs">
                            {Math.floor(song.duration / 60)}:{(song.duration % 60).toString().padStart(2, '0')}
                          </span>
                          <button 
                            onClick={(e) => { 
                              e.stopPropagation(); 
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
                            onClick={(e) => { 
                              e.stopPropagation(); 
                              openAddToPlaylistModal(song.id); 
                            }} 
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
        </>
      )}
    </div>
  );
}
