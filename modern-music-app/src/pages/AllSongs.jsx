import { useAuth } from '../context/AuthContext';
import { useEffect, useState } from 'react';
import { Play, Clock, Plus } from 'lucide-react';
import { usePlayer } from '../context/PlayerContext';
import { usePlaylistModal } from '../context/PlaylistModalContext';

export default function AllSongs() {
  const [songs, setSongs] = useState([]);
  const { user, getAuthParams } = useAuth();
  const [loading, setLoading] = useState(true);
  const { playQueue } = usePlayer();
  const { openAddToPlaylistModal } = usePlaylistModal();

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
    <div className="pb-24 max-w-7xl mx-auto">
      <div className="flex items-center justify-between mb-8 mt-4">
        <div>
          <h1 className="text-3xl font-bold text-white mb-2">All Songs</h1>
          <p className="text-slate-400">Random 100 tracks from your library</p>
        </div>
        <button onClick={playAll} className="bg-purple-500 hover:bg-purple-400 text-white px-6 py-2.5 rounded-full font-medium flex items-center gap-2 transition-all shadow-[0_0_20px_rgba(168,85,247,0.4)] hover:scale-105">
          <Play fill="currentColor" size={18} /> Play All
        </button>
      </div>

      {loading ? (
        <div className="flex justify-center py-20"><div className="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>
      ) : (
        <div className="bg-slate-900/40 backdrop-blur-sm rounded-xl overflow-hidden border border-white/5">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="text-slate-400 border-b border-white/10 text-sm">
                <th className="font-medium px-6 py-4 w-12">#</th>
                <th className="font-medium px-6 py-4">Title</th>
                <th className="font-medium px-6 py-4 hidden sm:table-cell">Artist</th>
                <th className="font-medium px-6 py-4 hidden md:table-cell">Album</th>
                <th className="font-medium px-6 py-4 flex justify-end gap-4"><Clock size={16} /><span className="w-8"></span></th>
              </tr>
            </thead>
            <tbody>
              {songs.map((song, index) => (
                <tr key={song.id} className="text-slate-300 hover:bg-white/5 transition-colors group">
                  <td className="px-6 py-4 cursor-pointer" onClick={() => playFromTrack(index)}>{index + 1}</td>
                  <td className="px-6 py-4 cursor-pointer" onClick={() => playFromTrack(index)}>
                    <div className="font-medium text-white group-hover:text-purple-400 transition-colors flex items-center gap-3">
                      <img src={`/ampache/public/rest/index.php?action=getCoverArt&id=${song.coverArt}&${getAuthParams(user)}`} className="w-8 h-8 rounded bg-slate-800" alt="" />
                      <span className="truncate max-w-[150px] sm:max-w-xs">{song.title}</span>
                    </div>
                  </td>
                  <td className="px-6 py-4 hidden sm:table-cell cursor-pointer" onClick={() => playFromTrack(index)}>{song.artist}</td>
                  <td className="px-6 py-4 text-slate-400 hidden md:table-cell cursor-pointer" onClick={() => playFromTrack(index)}>
                    <span className="truncate max-w-[150px] block">{song.album}</span>
                  </td>
                  <td className="px-6 py-4 text-right flex justify-end items-center gap-4">
                    <span className="cursor-pointer" onClick={() => playFromTrack(index)}>{Math.floor(song.duration / 60)}:{(song.duration % 60).toString().padStart(2, '0')}</span>
                    <button 
                      onClick={(e) => { e.stopPropagation(); openAddToPlaylistModal(song.id); }} 
                      className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-purple-500/20 text-slate-400 hover:text-purple-400 transition-colors"
                      title="Add to Playlist"
                    >
                      <Plus size={16} />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
