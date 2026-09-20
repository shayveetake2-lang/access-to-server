import { createContext, useState, useEffect, useContext } from 'react';
import { useAuth } from './AuthContext';
import { useToast } from './ToastContext';
import { X, Plus, ListMusic, Globe, Lock } from 'lucide-react';
import { getApiProxyUrl, getAmpacheUrl, getSubsonicAuthParams } from '../utils/api';

const PlaylistModalContext = createContext();

export function usePlaylistModal() {
  return useContext(PlaylistModalContext);
}

export function PlaylistModalProvider({ children }) {
  const [isOpen, setIsOpen] = useState(false);
  const [songIdToAdd, setSongIdToAdd] = useState(null);
  const [playlists, setPlaylists] = useState([]);
  const [loading, setLoading] = useState(false);
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [createPlaylistName, setCreatePlaylistName] = useState("");
  const [isPublic, setIsPublic] = useState(false);
  const [isCreating, setIsCreating] = useState(false);
  const [onPlaylistCreated, setOnPlaylistCreated] = useState(null);
  const { user, getAuthParams } = useAuth();
  const { showToast } = useToast();

  const openAddToPlaylistModal = (songId) => {
    setSongIdToAdd(songId);
    setIsOpen(true);
    fetchPlaylists();
  };

  const openCreatePlaylistModal = (callback = null) => {
    setOnPlaylistCreated(() => callback);
    setCreatePlaylistName("");
    setIsPublic(false);
    setIsCreateOpen(true);
  };
  
  const handleCreatePlaylist = async (e) => {
    e.preventDefault();
    if (!createPlaylistName.trim() || !user) return;
    
    setIsCreating(true);
    try {
      const auth = getSubsonicAuthParams(user);
      const res = await fetch(getAmpacheUrl(`action=createPlaylist&name=${encodeURIComponent(createPlaylistName.trim())}&${auth}`));
      const data = await res.json();
      if (data?.["subsonic-response"]?.status === "ok") {
        const createdPlaylist = data["subsonic-response"]?.playlist;
        const newId = createdPlaylist?.id;
        if (newId) {
          // Explicitly set public or private flag based on user selection in Subsonic API
          try {
            await fetch(getAmpacheUrl(`action=updatePlaylist&playlistId=${newId}&public=${isPublic ? 'true' : 'false'}&${auth}`));
          } catch (pe) {
            console.debug("Failed setting playlist visibility via Subsonic:", pe);
          }
          // Direct DB persistence via API proxy
          try {
            await fetch(`${getApiProxyUrl()}?action=togglePlaylistVisibility&id=${newId}&public=${isPublic ? 'true' : 'false'}`);
          } catch (pe) {
            console.debug("Proxy visibility notice:", pe);
          }
        }
        setIsCreateOpen(false);
        setCreatePlaylistName("");
        setIsPublic(false);
        showToast(isPublic ? "Public playlist created!" : "Private playlist created!", "success");
        if (onPlaylistCreated) {
          onPlaylistCreated();
        }
        if (isOpen) {
          fetchPlaylists(); // Refresh add-to-playlist list
        }
      } else {
        showToast("Failed to create playlist", "error");
      }
    } catch (err) {
      showToast("Network error", "error");
    } finally {
      setIsCreating(false);
    }
  };

  const closePlaylistModal = () => {
    setIsOpen(false);
    setSongIdToAdd(null);
  };

  const fetchPlaylists = async () => {
    if (!user) return;
    setLoading(true);
    try {
      const auth = getSubsonicAuthParams(user);
      const res = await fetch(getAmpacheUrl(`action=getPlaylists&${auth}`));
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        let allPlaylists = data['subsonic-response'].playlists?.playlist || [];
        allPlaylists = Array.isArray(allPlaylists) ? allPlaylists : [allPlaylists];
        const userPlaylists = allPlaylists.filter(p => 
          p.owner !== 'System' && 
          !p.id.startsWith('400000') &&
          (!p.owner || p.owner === '' || p.owner.toLowerCase() === user.username.toLowerCase())
        );
        setPlaylists(userPlaylists.slice(0, 9));
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const addToPlaylist = async (playlistId) => {
    if (!user || !songIdToAdd) return;
    try {
      const auth = getSubsonicAuthParams(user);
      const res = await fetch(getAmpacheUrl(`action=updatePlaylist&playlistId=${playlistId}&songIdToAdd=${songIdToAdd}&${auth}`));
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        showToast("Added to playlist!", "success");
        closePlaylistModal();
      } else {
        showToast("Failed to add to playlist.", "error");
      }
    } catch (err) {
      showToast("Network error", "error");
    }
  };

  return (
    <PlaylistModalContext.Provider value={{ openAddToPlaylistModal, openCreatePlaylistModal }}>
      {children}
      {isOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className="bg-slate-900 border border-white/10 rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="flex items-center justify-between p-4 border-b border-white/5">
              <h3 className="font-semibold text-white">Add to Playlist</h3>
              <button onClick={closePlaylistModal} className="text-slate-400 hover:text-white transition-colors">
                <X size={20} />
              </button>
            </div>
            <div className="p-2 max-h-[60vh] overflow-y-auto">
              {loading ? (
                <div className="p-8 flex justify-center"><div className="w-6 h-6 border-2 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>
              ) : playlists.length === 0 ? (
                <div className="p-6 flex flex-col items-center justify-center text-center">
                  <div className="w-16 h-16 rounded-full bg-slate-800 flex items-center justify-center mb-4">
                    <ListMusic className="text-slate-400 opacity-50" size={32} />
                  </div>
                  <h4 className="text-white font-medium mb-1">No playlists</h4>
                  <p className="text-slate-400 text-sm mb-4">Create a playlist to add this song.</p>
                  <button onClick={() => openCreatePlaylistModal()} className="px-4 py-2 bg-purple-500 hover:bg-purple-400 text-white rounded-lg text-sm font-medium transition-colors w-full">
                    Create Playlist
                  </button>
                </div>
              ) : (
                playlists.map(p => (
                  <button 
                    key={p.id}
                    onClick={() => addToPlaylist(p.id)}
                    className="w-full flex items-center gap-3 p-3 rounded-xl hover:bg-white/5 transition-colors text-left group"
                  >
                    <div className="w-10 h-10 rounded bg-slate-800 flex items-center justify-center group-hover:bg-purple-500/20 transition-colors">
                      <ListMusic size={16} className="text-slate-400 group-hover:text-purple-400" />
                    </div>
                    <div className="flex-1">
                      <div className="text-sm font-medium text-slate-200 group-hover:text-white">{p.name}</div>
                      <div className="text-xs text-slate-500">{p.songCount || 0} tracks</div>
                    </div>
                    <Plus size={16} className="text-slate-600 group-hover:text-purple-400" />
                  </button>
                ))
              )}
            </div>
          </div>
        </div>
      )}
      
      {isCreateOpen && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className="bg-slate-900 border border-white/10 rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="flex items-center justify-between p-4 border-b border-white/5">
              <h3 className="font-semibold text-white">Create Playlist</h3>
              <button onClick={() => setIsCreateOpen(false)} className="text-slate-400 hover:text-white transition-colors">
                <X size={20} />
              </button>
            </div>
            <form onSubmit={handleCreatePlaylist} className="p-4">
              <input 
                type="text" 
                value={createPlaylistName}
                onChange={(e) => setCreatePlaylistName(e.target.value)}
                placeholder="Playlist name..."
                className="w-full bg-slate-800 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-colors mb-3"
                autoFocus
              />

              <label className="flex items-start gap-3 p-3 rounded-xl bg-slate-800/60 border border-white/5 cursor-pointer hover:bg-slate-800 transition-colors mb-4 select-none group">
                <input 
                  type="checkbox" 
                  checked={isPublic}
                  onChange={(e) => setIsPublic(e.target.checked)}
                  className="mt-0.5 rounded border-white/20 bg-slate-900 text-purple-500 focus:ring-purple-500/40 w-4 h-4 cursor-pointer accent-purple-500"
                />
                <div className="flex-1 min-w-0">
                  <div className="text-xs font-semibold text-white flex items-center gap-1.5">
                    {isPublic ? <Globe size={13} className="text-purple-400" /> : <Lock size={13} className="text-slate-400" />}
                    <span>{isPublic ? "Public Playlist" : "Private Playlist"}</span>
                    <span className={`text-[10px] px-1.5 py-0.2 rounded font-medium ${isPublic ? 'bg-purple-500/20 text-purple-300' : 'bg-slate-700/60 text-slate-400'}`}>
                      {isPublic ? "Visible to Everyone" : "Only You"}
                    </span>
                  </div>
                  <p className="text-[11px] text-slate-400 mt-0.5 leading-relaxed">
                    {isPublic 
                      ? "Anyone on this server can discover, listen to, and save this playlist." 
                      : "Only you can see and play this playlist."}
                  </p>
                </div>
              </label>

              <button 
                type="submit" 
                disabled={isCreating || !createPlaylistName.trim()}
                className="w-full py-3 bg-purple-500 hover:bg-purple-400 disabled:opacity-50 disabled:hover:bg-purple-500 text-white rounded-xl font-medium transition-colors flex items-center justify-center gap-2"
              >
                {isCreating ? <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div> : "Create"}
              </button>
            </form>
          </div>
        </div>
      )}
      
    </PlaylistModalContext.Provider>
  );
}
