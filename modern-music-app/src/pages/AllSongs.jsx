import { useAuth } from '../context/AuthContext';
import { useEffect, useState } from 'react';
import { Play, Clock, Plus, ListPlus, Volume2, Shuffle } from 'lucide-react';
import { usePlayer } from '../context/PlayerContext';
import { usePlaylistModal } from '../context/PlaylistModalContext';
import { useToast } from '../context/ToastContext';
import { getApiProxyUrl } from '../utils/api';

export default function AllSongs() {
  const [songs, setSongs] = useState([]);
  const { user, getAuthParams } = useAuth();
  const [loading, setLoading] = useState(true);
  const { playQueue, addToQueue, currentTrack, isPlaying } = usePlayer();
  const { openAddToPlaylistModal } = usePlaylistModal();
  const { showToast } = useToast();

  useEffect(() => {
    const fetchSongs = async () => {
      try {
        // Fetch top 100 songs from proxy API, fallback to Subsonic randomSongs
        let loadedSongs = [];
        try {
          const proxyRes = await fetch(`${getApiProxyUrl()}?action=getTopSongs&size=100`);
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
    fetchSongs();
  }, [user]);

  const playAll = () => {
    if (songs.length > 0) {
      playQueue(songs, 0);
      showToast("▶ Playing Top 100 tracks in order", "success");
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
      <div className="flex flex-col sm:flex-row sm:items-center justify-between mb-5 sm:mb-8 mt-1 px-1 gap-3">
        <div className="min-w-0 pr-2">
          <h1 className="text-xl sm:text-3xl font-bold text-white mb-0.5">Top 100 Songs</h1>
          <p className="text-xs sm:text-sm text-slate-400 truncate">Most played tracks across your library</p>
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
            <Shuffle size={15} /> <span>Shuffle Play</span>
          </button>
        </div>
      </div>

      {loading ? (
        <div className="flex justify-center py-20"><div className="w-8 h-8 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>
      ) : (
        <>
          {/* Mobile Song Rows */}
          <div className="sm:hidden space-y-1 bg-slate-900/40 backdrop-blur-sm rounded-2xl p-2 border border-white/5">
            {songs.map((song, index) => {
              const isCurrent = currentTrack?.id === song.id;
              return (
                <div 
                  key={song.id} 
                  onClick={() => playFromTrack(index)} 
                  className={`flex items-center justify-between p-2 rounded-xl transition-colors cursor-pointer group ${
                    isCurrent ? 'bg-purple-500/15 border border-purple-500/30' : 'hover:bg-white/5 active:bg-white/10'
                  }`}
                >
                  <div className="flex items-center gap-3 min-w-0 flex-1 pr-2">
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
                      <p className="text-[11px] text-slate-400 truncate mt-0.5">{song.artist}{song.album ? ` • ${song.album}` : ''}</p>
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
                  <th className="font-semibold px-4 sm:px-5 py-4 w-12 text-center">#</th>
                  <th className="font-semibold px-4 py-4 w-auto">Title</th>
                  <th className="font-semibold px-4 py-4 hidden lg:table-cell w-44 xl:w-56">Artist</th>
                  <th className="font-semibold px-4 py-4 hidden xl:table-cell w-44 xl:w-56">Album</th>
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
                  return (
                    <tr 
                      key={song.id} 
                      onClick={() => playFromTrack(index)}
                      className={`transition-colors group cursor-pointer ${
                        isCurrent ? 'bg-purple-500/10 text-purple-300' : 'text-slate-300 hover:bg-white/5'
                      }`}
                    >
                      <td className="px-4 sm:px-5 py-3 text-xs text-slate-400 text-center w-12">
                        {isCurrent ? <Volume2 size={15} className={`text-purple-400 mx-auto ${isPlaying ? 'animate-pulse' : ''}`} /> : index + 1}
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
