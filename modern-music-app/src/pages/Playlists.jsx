import { useEffect, useState } from 'react';
import { ListMusic, Play, Trash2 } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { usePlaylistModal } from '../context/PlaylistModalContext';
import { useToast } from '../context/ToastContext';

export default function Playlists() {
  const { user, getAuthParams } = useAuth();
  const [playlists, setPlaylists] = useState([]);
  const [loading, setLoading] = useState(true);
  const { openCreatePlaylistModal } = usePlaylistModal();
  const { showToast } = useToast();

  const fetchPlaylists = async () => {
    try {
      const response = await fetch(`/ampache/public/rest/index.php?action=getPlaylists&${getAuthParams(user)}`);
      const data = await response.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        let allPlaylists = data['subsonic-response'].playlists?.playlist || [];
        allPlaylists = Array.isArray(allPlaylists) ? allPlaylists : [allPlaylists];
        
        // Filter out read-only System (Smart) playlists
        const userPlaylists = allPlaylists.filter(p => p.owner !== 'System' && !p.id.startsWith('400000'));
        setPlaylists(userPlaylists.slice(0, 9));
      } else {
        setPlaylists([]);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (user) {
      fetchPlaylists();
    }
  }, [user]);

  const handleCreatePlaylist = () => {
    openCreatePlaylistModal(fetchPlaylists);
  };

  const handleDeletePlaylist = async (id, e) => {
    e.preventDefault();
    e.stopPropagation();
    if (!confirm("Are you sure you want to delete this playlist entirely?")) return;
    
    try {
      const res = await fetch(`/ampache/public/rest/index.php?action=deletePlaylist&id=${id}&${getAuthParams(user)}`);
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        fetchPlaylists();
        showToast("Playlist deleted successfully!", "success");
      } else {
        showToast("Failed to delete playlist.", "error");
      }
    } catch (err) {
      showToast("Network error.", "error");
    }
  };

  return (
    <div className="pb-24">
      <div className="flex items-center justify-between mb-8 mt-4">
        <h1 className="text-3xl font-bold text-white">My Playlists</h1>
        {playlists.length < 9 ? (
          <button onClick={handleCreatePlaylist} className="bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-lg font-medium transition-colors border border-white/5">
            + New Playlist
          </button>
        ) : (
          <span className="text-slate-400 text-sm">Playlist Limit Reached (9/9)</span>
        )}
      </div>
      
      {loading ? (
        <div className="flex justify-center py-12"><div className="w-8 h-8 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>
      ) : playlists.length === 0 ? (
        <div className="text-center py-20 bg-slate-900/40 rounded-xl border border-white/5">
          <ListMusic size={48} className="mx-auto text-slate-500 mb-4" />
          <h3 className="text-xl font-semibold text-white mb-2">No Playlists Yet</h3>
          <p className="text-slate-400 mb-6">Create your first playlist to start collecting your favorite tracks.</p>
          <button onClick={handleCreatePlaylist} className="bg-purple-500 hover:bg-purple-400 text-white px-6 py-2 rounded-lg font-medium transition-colors shadow-lg">
            Create Playlist
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
          {playlists.map((playlist) => (
            <Link to={`/playlists/${playlist.id}`} key={playlist.id} className="group flex flex-col bg-slate-800/20 hover:bg-slate-800/40 p-5 rounded-xl transition-all border border-transparent hover:border-white/5 backdrop-blur-sm relative">
              <button 
                onClick={(e) => handleDeletePlaylist(playlist.id, e)}
                className="absolute top-2 right-2 p-2 bg-red-500/80 hover:bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity z-20 shadow-md"
                title="Delete Playlist"
              >
                <Trash2 size={14} />
              </button>

              <div className="relative aspect-video rounded-lg overflow-hidden mb-4 shadow-lg bg-gradient-to-br from-indigo-900 to-purple-900 flex items-center justify-center">
                <ListMusic size={32} className="text-white/30" />
                <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                  <div className="w-12 h-12 rounded-full bg-purple-500 flex items-center justify-center text-white shadow-lg transform translate-y-4 group-hover:translate-y-0 transition-all duration-300">
                    <Play fill="currentColor" size={20} className="ml-1" />
                  </div>
                </div>
              </div>
              <h4 className="font-semibold text-slate-200 group-hover:text-purple-400 transition-colors text-lg">{playlist.name}</h4>
              <p className="text-sm text-slate-400 mt-1">{playlist.songCount || 0} tracks • {playlist.duration ? Math.floor(playlist.duration/60) + ' mins' : 'Empty'}</p>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
