import { useAuth } from '../context/AuthContext';
import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { Play, Clock, Heart, Plus, ListPlus, Volume2 } from 'lucide-react';
import { usePlayer } from '../context/PlayerContext';
import { usePlaylistModal } from '../context/PlaylistModalContext';
import { useToast } from '../context/ToastContext';

export default function AlbumDetails() {
  const { id } = useParams();
  const [album, setAlbum] = useState(null);
  const { user, getAuthParams } = useAuth();
  const [loading, setLoading] = useState(true);
  const { playQueue, addToQueue, currentTrack, isPlaying } = usePlayer();
  const { openAddToPlaylistModal } = usePlaylistModal();
  const { showToast } = useToast();

  useEffect(() => {
    const fetchAlbumDetails = async () => {
      try {
        const response = await fetch(`/ampache/public/rest/index.php?action=getAlbum&id=${id}&${getAuthParams(user)}`);
        const data = await response.json();
        if (data?.['subsonic-response']?.status === 'ok') {
          setAlbum(data['subsonic-response'].album);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchAlbumDetails();
  }, [id]);

  if (loading) return <div className="flex justify-center py-20"><div className="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>;
  if (!album) return <div className="text-white text-center py-20">Album not found.</div>;

  const coverUrl = `/ampache/public/rest/index.php?action=getCoverArt&id=${album.coverArt}&${getAuthParams(user)}`;

  const playEntireAlbum = () => {
    if (album.song && album.song.length > 0) {
      playQueue(album.song, 0);
    }
  };

  const playFromTrack = (index) => {
    if (album.song) {
      playQueue(album.song, index);
    }
  };

  return (
    <div className="pb-28 max-w-7xl mx-auto">
      {/* Header */}
      <div className="flex flex-col sm:flex-row gap-5 sm:gap-8 items-center sm:items-end mb-6 sm:mb-8 mt-2 text-center sm:text-left bg-gradient-to-b from-purple-900/20 to-transparent p-4 sm:p-6 rounded-3xl border border-white/5">
        <div className="w-36 h-36 sm:w-48 sm:h-48 md:w-56 md:h-56 rounded-2xl overflow-hidden shadow-[0_16px_40px_rgba(0,0,0,0.7)] shrink-0 border border-white/10 bg-slate-800">
          <img src={coverUrl} alt={album.name} className="w-full h-full object-cover" />
        </div>
        <div className="flex-1 min-w-0">
          <span className="text-[11px] sm:text-xs font-semibold uppercase tracking-wider text-purple-400 block mb-0.5">Album</span>
          <h1 className="text-2xl sm:text-4xl md:text-5xl font-bold text-white mb-2 leading-tight truncate">{album.name}</h1>
          <div className="flex flex-wrap items-center justify-center sm:justify-start gap-2 text-slate-300 text-xs sm:text-sm font-medium">
            <span className="text-white font-semibold">{album.artist}</span>
            <span>•</span>
            <span>{album.year || 'Unknown Year'}</span>
            <span>•</span>
            <span>{album.songCount} songs</span>
          </div>
        </div>
      </div>

      {/* Controls */}
      <div className="flex items-center justify-center sm:justify-start gap-4 mb-6 sm:mb-8 px-1">
        <button 
          onClick={playEntireAlbum} 
          className="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-purple-500 flex items-center justify-center text-white hover:bg-purple-400 transition-all shadow-[0_0_20px_rgba(168,85,247,0.4)] active:scale-95 hover:scale-105"
          aria-label="Play Album"
        >
          <Play fill="currentColor" size={22} className="ml-0.5" />
        </button>
        <button 
          className="w-9 h-9 sm:w-10 sm:h-10 rounded-full border border-white/20 flex items-center justify-center text-slate-300 hover:text-white hover:border-white transition-all active:scale-95"
          aria-label="Favorite Album"
        >
          <Heart size={18} />
        </button>
      </div>

      {/* Tracklist Mobile View */}
      <div className="sm:hidden space-y-1 bg-slate-900/40 backdrop-blur-sm rounded-2xl p-2 border border-white/5">
        {(album.song || []).map((song, index) => {
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
                <span className="w-5 text-center text-xs font-mono text-slate-400 shrink-0 flex items-center justify-center">
                  {isCurrent ? <Volume2 size={14} className={`text-purple-400 ${isPlaying ? 'animate-pulse' : ''}`} /> : index + 1}
                </span>
                <div className="min-w-0 flex-1">
                  <h4 className={`text-xs font-semibold truncate transition-colors leading-snug ${
                    isCurrent ? 'text-purple-400 font-bold' : 'text-white group-hover:text-purple-400'
                  }`}>
                    {song.title}
                  </h4>
                  <p className="text-[11px] text-slate-400 truncate mt-0.5">{song.artist || album.artist}</p>
                </div>
              </div>
              <div className="flex items-center gap-1.5 shrink-0">
                <span className="text-[11px] text-slate-400 font-mono">
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

      {/* Tracklist Desktop Table */}
      <div className="hidden sm:block bg-slate-900/40 backdrop-blur-sm rounded-2xl border border-white/5 overflow-hidden">
        <table className="w-full text-left border-collapse table-fixed">
          <thead>
            <tr className="text-slate-400 border-b border-white/10 text-xs uppercase tracking-wider">
              <th className="font-semibold px-4 sm:px-5 py-4 w-12 text-center">#</th>
              <th className="font-semibold px-4 py-4 w-auto">Title</th>
              <th className="font-semibold px-4 sm:px-6 py-4 text-right w-36 sm:w-44 shrink-0">
                <div className="flex items-center justify-end gap-2 text-slate-400">
                  <Clock size={16} />
                  <span className="text-[11px] normal-case tracking-normal">Actions</span>
                </div>
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-white/5 text-sm">
            {(album.song || []).map((song, index) => {
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
                    <div className={`font-medium transition-colors truncate ${
                      isCurrent ? 'text-purple-300 font-semibold' : 'text-white group-hover:text-purple-400'
                    }`}>
                      {song.title}
                    </div>
                  </td>
                  <td className="px-4 sm:px-6 py-3 text-right w-36 sm:w-44 shrink-0">
                    <div className="flex items-center justify-end gap-1.5 font-mono text-xs">
                      <span className="text-slate-400 mr-1 text-[11px] sm:text-xs">{Math.floor(song.duration / 60)}:{(song.duration % 60).toString().padStart(2, '0')}</span>
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
    </div>
  );
}
