import { useAuth } from '../context/AuthContext';
import { useEffect, useState } from 'react';
import { Play, Clock, Plus, ListPlus, Volume2 } from 'lucide-react';
import { usePlayer } from '../context/PlayerContext';
import { usePlaylistModal } from '../context/PlaylistModalContext';
import { useToast } from '../context/ToastContext';

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
        const response = await fetch(`/ampache/public/rest/index.php?action=getRandomSongs&size=100&${getAuthParams(user)}`);
        const data = await response.json();
        if (data?.['subsonic-response']?.status === 'ok') {
          setSongs(data['subsonic-response'].randomSongs.song || []);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchSongs();
  }, []);

  const playAll = () => {
    if (songs.length > 0) playQueue(songs, 0);
  };
  
  const playFromTrack = (index) => {
    playQueue(songs, index);
  };

  return (
    <div className="pb-28 max-w-7xl mx-auto">
      <div className="flex items-center justify-between mb-5 sm:mb-8 mt-1 px-1">
        <div className="min-w-0 pr-3">
          <h1 className="text-xl sm:text-3xl font-bold text-white mb-0.5">All Songs</h1>
          <p className="text-xs sm:text-sm text-slate-400 truncate">100 tracks from your library</p>
        </div>
        <button 
          onClick={playAll} 
          className="bg-purple-500 hover:bg-purple-400 text-white px-4 py-2 sm:px-6 sm:py-2.5 rounded-full font-medium text-xs sm:text-sm flex items-center gap-2 transition-all shadow-[0_0_20px_rgba(168,85,247,0.4)] active:scale-95 shrink-0"
        >
          <Play fill="currentColor" size={16} /> Play All
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
                        showToast(`Added "${song.title}" to queue`, 'success');
                      }} 
                      className="w-8 h-8 flex items-center justify-center rounded-full text-slate-400 hover:text-purple-400 active:bg-purple-500/20 transition-colors"
                      title="Add to Queue"
                      aria-label="Add to Queue"
                    >
                      <ListPlus size={16} />
                    </button>
                    <button 
                      onClick={(e) => { e.stopPropagation(); openAddToPlaylistModal(song.id); }} 
                      className="w-8 h-8 flex items-center justify-center rounded-full text-slate-400 hover:text-purple-400 active:bg-purple-500/20 transition-colors"
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
          <div className="hidden sm:block bg-slate-900/40 backdrop-blur-sm rounded-2xl overflow-hidden border border-white/5">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="text-slate-400 border-b border-white/10 text-xs uppercase tracking-wider">
                  <th className="font-semibold px-6 py-4 w-12">#</th>
                  <th className="font-semibold px-6 py-4">Title</th>
                  <th className="font-semibold px-6 py-4">Artist</th>
                  <th className="font-semibold px-6 py-4">Album</th>
                  <th className="font-semibold px-6 py-4 text-right flex justify-end gap-4"><Clock size={16} /><span className="w-16"></span></th>
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
                      <td className="px-6 py-3.5 text-xs text-slate-400">
                        {isCurrent ? <Volume2 size={15} className={`text-purple-400 ${isPlaying ? 'animate-pulse' : ''}`} /> : index + 1}
                      </td>
                      <td className="px-6 py-3.5">
                        <div className={`font-medium transition-colors flex items-center gap-3 ${
                          isCurrent ? 'text-purple-300' : 'text-white group-hover:text-purple-400'
                        }`}>
                          <img src={`/ampache/public/rest/index.php?action=getCoverArt&id=${song.coverArt}&${getAuthParams(user)}`} className="w-9 h-9 rounded-lg bg-slate-800 object-cover" alt="" loading="lazy" />
                          <span className="truncate max-w-xs">{song.title}</span>
                        </div>
                      </td>
                      <td className="px-6 py-3.5 truncate max-w-[180px]">{song.artist}</td>
                      <td className="px-6 py-3.5 text-slate-400 truncate max-w-[180px]">
                        {song.album}
                      </td>
                      <td className="px-6 py-3.5 text-right flex justify-end items-center gap-2 font-mono text-xs">
                        <span className="mr-1">{Math.floor(song.duration / 60)}:{(song.duration % 60).toString().padStart(2, '0')}</span>
                        <button 
                          onClick={(e) => { 
                            e.stopPropagation(); 
                            addToQueue(song); 
                            showToast(`Added "${song.title}" to queue`, 'success');
                          }} 
                          className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-purple-500/20 text-slate-400 hover:text-purple-400 transition-colors"
                          title="Add to Queue"
                          aria-label="Add to Queue"
                        >
                          <ListPlus size={16} />
                        </button>
                        <button 
                          onClick={(e) => { e.stopPropagation(); openAddToPlaylistModal(song.id); }} 
                          className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-purple-500/20 text-slate-400 hover:text-purple-400 transition-colors"
                          title="Add to Playlist"
                          aria-label="Add to Playlist"
                        >
                          <Plus size={16} />
                        </button>
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
