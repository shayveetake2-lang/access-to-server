import { useAuth } from '../context/AuthContext';
import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { Play, Clock, Heart, Plus, ListPlus } from 'lucide-react';
import { usePlayer } from '../context/PlayerContext';
import { usePlaylistModal } from '../context/PlaylistModalContext';

export default function AlbumDetails() {
  const { id } = useParams();
  const [album, setAlbum] = useState(null);
  const { user, getAuthParams } = useAuth();
  const [loading, setLoading] = useState(true);
  const { playQueue, addToQueue } = usePlayer();
  const { openAddToPlaylistModal } = usePlaylistModal();

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
    <div className="pb-24">
      {/* Header */}
      <div className="flex flex-col md:flex-row gap-8 items-end mb-8 mt-4">
        <div className="w-48 h-48 md:w-64 md:h-64 rounded-xl overflow-hidden shadow-[0_10px_30px_rgba(0,0,0,0.5)] flex-shrink-0">
          <img src={coverUrl} alt={album.name} className="w-full h-full object-cover" />
        </div>
        <div className="flex-1">
          <span className="text-sm font-semibold uppercase tracking-wider text-purple-400">Album</span>
          <h1 className="text-4xl md:text-6xl font-bold text-white mt-2 mb-4">{album.name}</h1>
          <div className="flex items-center gap-2 text-slate-300">
            <span className="font-medium text-white">{album.artist}</span>
            <span>•</span>
            <span>{album.year || 'Unknown Year'}</span>
            <span>•</span>
            <span>{album.songCount} songs</span>
          </div>
        </div>
      </div>

      {/* Controls */}
      <div className="flex items-center gap-4 mb-8">
        <button onClick={playEntireAlbum} className="w-14 h-14 rounded-full bg-purple-500 flex items-center justify-center text-white hover:bg-purple-400 transition-all shadow-[0_0_20px_rgba(168,85,247,0.4)] hover:scale-105">
          <Play fill="currentColor" size={24} className="ml-1" />
        </button>
        <button className="w-10 h-10 rounded-full border border-white/20 flex items-center justify-center text-slate-300 hover:text-white hover:border-white transition-all">
          <Heart size={20} />
        </button>
      </div>

      {/* Tracklist */}
      <div className="bg-slate-900/40 backdrop-blur-sm rounded-xl overflow-hidden border border-white/5">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="text-slate-400 border-b border-white/10 text-sm">
              <th className="font-medium px-6 py-4 w-12">#</th>
              <th className="font-medium px-6 py-4">Title</th>
              <th className="font-medium px-6 py-4 flex justify-end gap-4">
                <Clock size={16} />
                <span className="w-8"></span>
              </th>
            </tr>
          </thead>
          <tbody>
            {(album.song || []).map((song, index) => (
              <tr key={song.id} className="text-slate-300 hover:bg-white/5 transition-colors group">
                <td className="px-6 py-4 cursor-pointer" onClick={() => playFromTrack(index)}>{index + 1}</td>
                <td className="px-6 py-4 cursor-pointer font-medium text-white group-hover:text-purple-400 transition-colors" onClick={() => playFromTrack(index)}>{song.title}</td>
                <td className="px-6 py-4 text-right flex justify-end items-center gap-4">
                  <span className="cursor-pointer" onClick={() => playFromTrack(index)}>{Math.floor(song.duration / 60)}:{(song.duration % 60).toString().padStart(2, '0')}</span>
                  <button 
                    onClick={(e) => { e.stopPropagation(); addToQueue(song); }} 
                    className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-purple-500/20 text-slate-400 hover:text-purple-400 transition-colors"
                    title="Add to Queue"
                  >
                    <ListPlus size={16} />
                  </button>
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
    </div>
  );
}
