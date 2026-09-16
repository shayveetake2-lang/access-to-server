import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Play, Clock, ListMusic, Trash2 } from 'lucide-react';
import { usePlayer } from '../context/PlayerContext';
import { useAuth } from '../context/AuthContext';

export default function PlaylistDetails() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [playlist, setPlaylist] = useState(null);
  const [loading, setLoading] = useState(true);
  const { playQueue } = usePlayer();
  const { user, getAuthParams } = useAuth();

  const fetchPlaylistDetails = async () => {
    try {
      const response = await fetch(`/ampache/public/rest/index.php?action=getPlaylist&id=${id}&${getAuthParams(user)}`);
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
      const res = await fetch(`/ampache/public/rest/index.php?action=updatePlaylist&playlistId=${id}&songIndexToRemove=${indexToRemove}&${getAuthParams(user)}`);
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
      const res = await fetch(`/ampache/public/rest/index.php?action=deletePlaylist&id=${id}&${getAuthParams(user)}`);
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

  const playEntirePlaylist = () => {
    if (playlist.entry && playlist.entry.length > 0) {
      playQueue(playlist.entry, 0);
    }
  };

  const playFromTrack = (index) => {
    if (playlist.entry) {
      playQueue(playlist.entry, index);
    }
  };

  // Convert single object to array if only 1 song is returned by XML to JSON converter
  const tracks = Array.isArray(playlist.entry) ? playlist.entry : (playlist.entry ? [playlist.entry] : []);

  return (
    <div className="pb-24">
      {/* Header */}
      <div className="flex flex-col md:flex-row gap-8 items-end mb-8 mt-4">
        <div className="w-48 h-48 md:w-64 md:h-64 rounded-xl overflow-hidden shadow-[0_10px_30px_rgba(0,0,0,0.5)] flex-shrink-0 bg-gradient-to-br from-indigo-900 to-purple-900 flex items-center justify-center">
          <ListMusic size={64} className="text-white/30" />
        </div>
        <div className="flex-1">
          <span className="text-sm font-semibold uppercase tracking-wider text-purple-400">Playlist</span>
          <h1 className="text-4xl md:text-6xl font-bold text-white mt-2 mb-4">{playlist.name}</h1>
          <div className="flex items-center gap-2 text-slate-300">
            <span>{playlist.songCount} tracks</span>
            <span>•</span>
            <span>{Math.floor((playlist.duration || 0) / 60)} minutes</span>
          </div>
        </div>
      </div>

      {/* Controls */}
      <div className="flex items-center gap-4 mb-8">
        <button onClick={playEntirePlaylist} className="w-14 h-14 rounded-full bg-purple-500 flex items-center justify-center text-white hover:bg-purple-400 transition-all shadow-[0_0_20px_rgba(168,85,247,0.4)] hover:scale-105">
          <Play fill="currentColor" size={24} className="ml-1" />
        </button>
        <button onClick={handleDeletePlaylist} className="px-4 py-2.5 rounded-full border border-red-500/50 text-red-400 hover:bg-red-500/10 font-medium flex items-center gap-2 transition-all">
          <Trash2 size={16} /> Delete Playlist
        </button>
      </div>

      {/* Tracklist */}
      <div className="bg-slate-900/40 backdrop-blur-sm rounded-xl overflow-hidden border border-white/5">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="text-slate-400 border-b border-white/10 text-sm">
              <th className="font-medium px-6 py-4 w-12">#</th>
              <th className="font-medium px-6 py-4">Title</th>
              <th className="font-medium px-6 py-4">Artist</th>
              <th className="font-medium px-6 py-4">Album</th>
              <th className="font-medium px-6 py-4 flex justify-end gap-4"><Clock size={16} /><span className="w-8"></span></th>
            </tr>
          </thead>
          <tbody>
            {tracks.map((song, index) => (
              <tr onClick={() => playFromTrack(index)} key={`${song.id}-${index}`} className="text-slate-300 hover:bg-white/5 transition-colors group cursor-pointer">
                <td className="px-6 py-4">{index + 1}</td>
                <td className="px-6 py-4">
                  <div className="font-medium text-white group-hover:text-purple-400 transition-colors flex items-center gap-3">
                    <img src={`/ampache/public/rest/index.php?action=getCoverArt&id=${song.coverArt}&${getAuthParams(user)}`} className="w-8 h-8 rounded bg-slate-800" alt="" />
                    <span className="truncate max-w-[150px] md:max-w-xs">{song.title}</span>
                  </div>
                </td>
                <td className="px-6 py-4 truncate max-w-[100px]">{song.artist}</td>
                <td className="px-6 py-4 text-slate-400 truncate max-w-[100px]">{song.album}</td>
                <td className="px-6 py-4 text-right flex justify-end items-center gap-4">
                  {Math.floor(song.duration / 60)}:{(song.duration % 60).toString().padStart(2, '0')}
                  <button 
                    onClick={(e) => handleRemoveTrack(index, e)} 
                    className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-red-500/20 text-slate-400 hover:text-red-400 transition-colors opacity-0 group-hover:opacity-100"
                    title="Remove from Playlist"
                  >
                    <Trash2 size={16} />
                  </button>
                </td>
              </tr>
            ))}
            {tracks.length === 0 && (
               <tr>
                 <td colSpan="5" className="px-6 py-8 text-center text-slate-400">This playlist is empty.</td>
               </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
