import { useEffect, useState } from 'react';
import { ListMusic, Play, Trash2, Globe } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { usePlaylistModal } from '../context/PlaylistModalContext';
import { useToast } from '../context/ToastContext';
import { getAmpacheUrl, getSubsonicAuthParams } from '../utils/api';

export default function Playlists() {
  const { user } = useAuth();
  const [playlists, setPlaylists] = useState([]);
  const [loading, setLoading] = useState(true);
  const { openCreatePlaylistModal } = usePlaylistModal();
  const { showToast } = useToast();

  const fetchPlaylists = async (isMounted = true) => {
    if (!user) return;
    try {
      const auth = getSubsonicAuthParams(user);
      const response = await fetch(getAmpacheUrl(`action=getPlaylists&${auth}`));
      const data = await response.json();
      if (isMounted) {
        if (data?.['subsonic-response']?.status === 'ok') {
          const rawPlaylists = data['subsonic-response'].playlists?.playlist;
          const allPlaylists = Array.isArray(rawPlaylists) ? rawPlaylists : (rawPlaylists ? [rawPlaylists] : []);
          
          // Filter out read-only System (Smart) playlists and ensure only playlists owned by user
          const userPlaylists = allPlaylists.filter(p => 
            p &&
            p.owner !== 'System' && 
            (p.id ? !String(p.id).startsWith('400000') : true) && 
            (!p.owner || p.owner === '' || (user?.username && p.owner.toLowerCase() === user.username.toLowerCase()))
          );
          setPlaylists(userPlaylists.slice(0, 9));
        } else {
          setPlaylists([]);
        }
      }
    } catch (err) {
      console.error(err);
      if (isMounted) setPlaylists([]);
    } finally {
      if (isMounted) setLoading(false);
    }
  };

  useEffect(() => {
    let isMounted = true;
    if (user) {
      fetchPlaylists(isMounted);
    }
    return () => {
      isMounted = false;
    };
  }, [user]);

  const handleCreatePlaylist = () => {
    openCreatePlaylistModal(fetchPlaylists);
  };

  const handleDeletePlaylist = async (id, e) => {
    e.preventDefault();
    e.stopPropagation();
    if (!confirm("Are you sure you want to delete this playlist entirely?")) return;
    
    try {
      const auth = getSubsonicAuthParams(user);
      const res = await fetch(getAmpacheUrl(`action=deletePlaylist&id=${id}&${auth}`));
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
    <div className="pb-28 max-w-7xl mx-auto">
      {/* Navigation Sub-Tabs */}
      <div className="flex items-center gap-2 mb-6 border-b border-white/10 pb-4">
        <div className="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-purple-500/15 border border-purple-500/30 text-purple-300 flex items-center gap-1.5 shadow-sm">
          <span>My Playlists</span>
          <span className="ml-1 text-[10px] px-1.5 py-0.2 bg-purple-500/30 text-purple-200 rounded-full font-bold">
            {playlists.length}
          </span>
        </div>
        <Link 
          to="/public-playlists"
          className="px-4 py-2 rounded-xl text-xs sm:text-sm font-medium text-slate-400 hover:text-white hover:bg-white/5 transition-all flex items-center gap-1.5"
        >
          <Globe size={14} />
          <span>Public Playlists</span>
        </Link>
      </div>

      <div className="flex items-center justify-between mb-5 sm:mb-8 mt-1 px-1">
        <div>
          <h1 className="text-xl sm:text-3xl font-bold text-white mb-0.5">My Playlists</h1>
          <p className="text-xs text-slate-400">{playlists.length} / 9 playlists used</p>
        </div>
        {playlists.length < 9 && (
          <button 
            onClick={handleCreatePlaylist} 
            className="bg-purple-500 hover:bg-purple-400 text-white px-3.5 py-2 sm:px-5 sm:py-2.5 rounded-full font-medium text-xs sm:text-sm transition-all shadow-[0_0_20px_rgba(168,85,247,0.4)] active:scale-95 flex items-center gap-1.5"
          >
            <span>+</span> New Playlist
          </button>
        )}
      </div>
      
      {loading ? (
        <div className="flex justify-center py-16"><div className="w-8 h-8 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>
      ) : playlists.length === 0 ? (
        <div className="text-center py-16 sm:py-20 bg-slate-900/40 rounded-3xl border border-white/5 p-6">
          <ListMusic size={44} className="mx-auto text-slate-500 mb-3" />
          <h3 className="text-lg font-semibold text-white mb-1.5">No Playlists Yet</h3>
          <p className="text-xs sm:text-sm text-slate-400 mb-5 max-w-sm mx-auto">Create your first playlist to organize your favorite music.</p>
          <button onClick={handleCreatePlaylist} className="bg-purple-500 hover:bg-purple-400 text-white px-5 py-2.5 rounded-full text-xs sm:text-sm font-medium transition-all shadow-lg active:scale-95">
            Create Playlist
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3.5 sm:gap-4 md:gap-6">
          {playlists.map((playlist) => {
            const isPublic = playlist.public === 'true' || playlist.public === true;
            return (
              <Link 
                to={`/playlists/${playlist.id}`} 
                key={playlist.id} 
                className="group flex flex-col bg-slate-900/40 hover:bg-slate-800/60 p-3.5 sm:p-4 rounded-2xl transition-all border border-white/5 hover:border-white/10 backdrop-blur-sm relative active:scale-[0.98]"
              >
                <button 
                  onClick={(e) => handleDeletePlaylist(playlist.id, e)}
                  className="absolute top-2.5 right-2.5 p-1.5 bg-red-500/80 hover:bg-red-500 text-white rounded-full opacity-80 sm:opacity-0 group-hover:opacity-100 transition-opacity z-20 shadow-md active:scale-90"
                  title="Delete Playlist"
                  aria-label="Delete Playlist"
                >
                  <Trash2 size={13} />
                </button>

                <div className="relative aspect-video rounded-xl overflow-hidden mb-3 shadow-md bg-gradient-to-br from-indigo-950 to-purple-950 flex items-center justify-center border border-white/5">
                  <ListMusic size={32} className="text-white/30" />
                  
                  {isPublic && (
                    <div className="absolute top-2.5 left-2.5 px-2 py-0.5 rounded-md bg-black/60 backdrop-blur-md border border-white/10 text-[10px] font-semibold text-purple-300 flex items-center gap-1 z-10">
                      <Globe size={11} />
                      <span>Public</span>
                    </div>
                  )}

                  <div className="hidden sm:flex absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity items-center justify-center">
                    <div className="w-11 h-11 rounded-full bg-purple-500 flex items-center justify-center text-white shadow-lg transform translate-y-3 group-hover:translate-y-0 transition-all duration-200">
                      <Play fill="currentColor" size={18} className="ml-0.5" />
                    </div>
                  </div>
                </div>
                <h4 className="font-semibold text-slate-100 group-hover:text-purple-400 transition-colors text-sm sm:text-base truncate">{playlist.name}</h4>
                <p className="text-[11px] sm:text-xs text-slate-400 mt-0.5">{playlist.songCount || 0} tracks • {playlist.duration ? Math.floor(playlist.duration/60) + ' mins' : 'Empty'}</p>
              </Link>
            );
          })}
        </div>
      )}
    </div>
  );
}
