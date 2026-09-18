import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Play, Clock, ListMusic, Trash2, ListPlus, Volume2 } from 'lucide-react';
import { usePlayer } from '../context/PlayerContext';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import { getAmpacheUrl, getCoverArtUrl } from '../utils/api';

export default function PlaylistDetails() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [playlist, setPlaylist] = useState(null);
  const [loading, setLoading] = useState(true);
  const { playQueue, addToQueue, currentTrack, isPlaying } = usePlayer();
  const { user, getAuthParams } = useAuth();
  const { showToast } = useToast();

  const fetchPlaylistDetails = async () => {
    try {
      const response = await fetch(getAmpacheUrl(`action=getPlaylist&id=${id}&${getAuthParams(user)}`));
      const data = await response.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        setPlaylist(data['subsonic-response'].playlist);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (user) {
      fetchPlaylistDetails();
    }
  }, [id, user]);

  const handleRemoveTrack = async (indexToRemove, e) => {
    e.stopPropagation();
    if (!confirm("Remove this track from the playlist?")) return;
    
    try {
      const res = await fetch(getAmpacheUrl(`action=updatePlaylist&playlistId=${id}&songIndexToRemove=${indexToRemove}&${getAuthParams(user)}`));
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        fetchPlaylistDetails();
      } else {
        alert("Failed to remove track.");
      }
    } catch (err) {
      alert("Network error.");
    }
  };

  const handleDeletePlaylist = async () => {
    if (!confirm("Are you sure you want to delete this entire playlist?")) return;
    
    try {
      const res = await fetch(getAmpacheUrl(`action=deletePlaylist&id=${id}&${getAuthParams(user)}`));
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        navigate('/playlists');
      } else {
        alert("Failed to delete playlist.");
      }
    } catch (err) {
      alert("Network error.");
    }
  };

  if (loading) return <div className="flex justify-center py-20"><div className="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>;
  if (!playlist) return <div className="text-white text-center py-20">Playlist not found.</div>;

  // Convert single object to array if only 1 song is returned by XML to JSON converter
  const tracks = Array.isArray(playlist.entry) ? playlist.entry : (playlist.entry ? [playlist.entry] : []);

  const playEntirePlaylist = () => {
    if (tracks.length > 0) {
      playQueue(tracks, 0);
    }
  };

  const playFromTrack = (index) => {
    if (tracks.length > 0) {
      playQueue(tracks, index);
    }
  };

  return (
    <div className="pb-28 max-w-7xl mx-auto">
      {/* Header */}
      <div className="flex flex-col sm:flex-row gap-5 sm:gap-8 items-center sm:items-end mb-6 sm:mb-8 mt-2 text-center sm:text-left bg-gradient-to-b from-purple-900/20 to-transparent p-4 sm:p-6 rounded-3xl border border-white/5">
        <div className="w-36 h-36 sm:w-48 sm:h-48 md:w-56 md:h-56 rounded-2xl overflow-hidden shadow-[0_16px_40px_rgba(0,0,0,0.7)] shrink-0 bg-gradient-to-br from-indigo-900 to-purple-900 flex items-center justify-center border border-white/10">
          <ListMusic size={56} className="text-white/40" />
        </div>
        <div className="flex-1 min-w-0">
          <span className="text-[11px] sm:text-xs font-semibold uppercase tracking-wider text-purple-400 block mb-0.5">Playlist</span>
          <h1 className="text-2xl sm:text-4xl md:text-5xl font-bold text-white mb-2 leading-tight truncate">{playlist.name}</h1>
          <div className="flex flex-wrap items-center justify-center sm:justify-start gap-2 text-slate-300 text-xs sm:text-sm font-medium">
            <span>{playlist.songCount} tracks</span>
            <span>•</span>
            <span>{Math.floor((playlist.duration || 0) / 60)} minutes</span>
          </div>
        </div>
      </div>

      {/* Controls */}
      <div className="flex items-center justify-center sm:justify-start gap-4 mb-6 sm:mb-8 px-1">
        <button 
          onClick={playEntirePlaylist} 
          disabled={tracks.length === 0}
          className="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-purple-500 disabled:opacity-40 flex items-center justify-center text-white hover:bg-purple-400 transition-all shadow-[0_0_20px_rgba(168,85,247,0.4)] active:scale-95 hover:scale-105"
          aria-label="Play Playlist"
        >
          <Play fill="currentColor" size={22} className="ml-0.5" />
        </button>
        <button 
          onClick={handleDeletePlaylist} 
          className="px-4 py-2 sm:py-2.5 rounded-full border border-red-500/40 text-red-400 hover:bg-red-500/10 font-medium text-xs sm:text-sm flex items-center gap-2 transition-all active:scale-95"
        >
          <Trash2 size={15} /> Delete Playlist
        </button>
      </div>

      {/* Tracklist Mobile View */}
      <div className="sm:hidden space-y-1 bg-slate-900/40 backdrop-blur-sm rounded-2xl p-2 border border-white/5">
        {tracks.length === 0 ? (
          <div className="p-8 text-center text-slate-400 text-xs">This playlist is empty.</div>
        ) : (
          tracks.map((song, index) => {
            const isCurrent = currentTrack?.id === song.id;
            return (
              <div 
                key={`${song.id}-${index}`} 
                onClick={() => playFromTrack(index)} 
                className={`flex items-center justify-between p-2 rounded-xl transition-colors cursor-pointer group ${
                  isCurrent ? 'bg-purple-500/15 border border-purple-500/30' : 'hover:bg-white/5 active:bg-white/10'
                }`}
              >
                <div className="flex items-center gap-3 min-w-0 flex-1 pr-2">
                  <div className="relative w-11 h-11 shrink-0">
                    <img 
                      src={getCoverArtUrl(song.coverArt, getAuthParams(user))} 
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
                <div className="flex items-center gap-1.5 shrink-0">
                  <span className="text-[11px] text-slate-400 font-mono">
                    {Math.floor(song.duration / 60)}:{(song.duration % 60).toString().padStart(2, '0')}
                  </span>
                  <button 
                    onClick={(e) => {
                      e.stopPropagation();
                      addToQueue(song);
                      showToast(`Added "${song.title}" to queue`, 'success');
                    }} 
                    className="w-7 h-7 flex items-center justify-center rounded-full text-slate-400 hover:text-purple-400 active:bg-purple-500/20 transition-colors"
                    title="Add to Queue"
                    aria-label="Add to Queue"
                  >
                    <ListPlus size={15} />
                  </button>
                  <button 
                    onClick={(e) => handleRemoveTrack(index, e)} 
                    className="w-7 h-7 flex items-center justify-center rounded-full text-slate-400 hover:text-red-400 active:bg-red-500/20 transition-colors"
                    title="Remove from Playlist"
                    aria-label="Remove from Playlist"
                  >
                    <Trash2 size={14} />
                  </button>
                </div>
              </div>
            );
          })
        )}
      </div>

      {/* Tracklist Desktop Table */}
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
            {tracks.map((song, index) => {
              const isCurrent = currentTrack?.id === song.id;
              return (
                <tr 
                  onClick={() => playFromTrack(index)} 
                  key={`${song.id}-${index}`} 
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
                      <img src={getCoverArtUrl(song.coverArt, getAuthParams(user))} className="w-9 h-9 rounded-lg bg-slate-800 object-cover" alt="" loading="lazy" />
                      <span className="truncate max-w-xs">{song.title}</span>
                    </div>
                  </td>
                  <td className="px-6 py-3.5 truncate max-w-[180px]">{song.artist}</td>
                  <td className="px-6 py-3.5 text-slate-400 truncate max-w-[180px]">{song.album}</td>
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
                      onClick={(e) => handleRemoveTrack(index, e)} 
                      className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-red-500/20 text-slate-400 hover:text-red-400 transition-colors opacity-0 group-hover:opacity-100"
                      title="Remove from Playlist"
                      aria-label="Remove from Playlist"
                    >
                      <Trash2 size={15} />
                    </button>
                  </td>
                </tr>
              );
            })}
            {tracks.length === 0 && (
               <tr>
                 <td colSpan="5" className="px-6 py-10 text-center text-slate-400 text-xs">This playlist is empty.</td>
               </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
