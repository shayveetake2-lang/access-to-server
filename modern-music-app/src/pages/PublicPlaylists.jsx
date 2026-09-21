import { useEffect, useState, useMemo } from 'react';
import { ListMusic, Play, Globe, Search, BookmarkPlus, User, Plus } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { usePlayer } from '../context/PlayerContext';
import { usePlaylistModal } from '../context/PlaylistModalContext';
import { useToast } from '../context/ToastContext';
import { getAmpacheUrl, getSubsonicAuthParams } from '../utils/api';

export default function PublicPlaylists() {
  const { user } = useAuth();
  const { playQueue } = usePlayer();
  const { openCreatePlaylistModal } = usePlaylistModal();
  const { showToast } = useToast();
  const [playlists, setPlaylists] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [savingId, setSavingId] = useState(null);
  const [playingId, setPlayingId] = useState(null);

  const fetchPublicPlaylists = async () => {
    if (!user) return;
    try {
      const auth = getSubsonicAuthParams(user);
      const response = await fetch(getAmpacheUrl(`action=getPlaylists&${auth}`));
      const data = await response.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        const rawPlaylists = data['subsonic-response'].playlists?.playlist;
        const allPlaylists = Array.isArray(rawPlaylists) ? rawPlaylists : (rawPlaylists ? [rawPlaylists] : []);
        
        // Only public playlists from users (exclude system smartlists)
        const publicList = allPlaylists.filter(p => 
          p &&
          p.owner !== 'System' && 
          (p.id ? !String(p.id).startsWith('400000') : true) && 
          (p.public === 'true' || p.public === true)
        );
        setPlaylists(publicList);
      } else {
        setPlaylists([]);
      }
    } catch (err) {
      console.error("Failed fetching public playlists:", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPublicPlaylists();
  }, [user]);

  const filteredPlaylists = useMemo(() => {
    if (!searchQuery.trim()) return playlists;
    const q = searchQuery.toLowerCase().trim();
    return playlists.filter(p => 
      p && (
        (p.name && p.name.toLowerCase().includes(q)) ||
        (p.owner && p.owner.toLowerCase().includes(q))
      )
    );
  }, [playlists, searchQuery]);

  const handlePlayPlaylist = async (playlist, e) => {
    e.preventDefault();
    e.stopPropagation();
    if (playingId) return;
    setPlayingId(playlist.id);

    try {
      const auth = getSubsonicAuthParams(user);
      const res = await fetch(getAmpacheUrl(`action=getPlaylist&id=${playlist.id}&${auth}`));
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        const rawEntries = data['subsonic-response']?.playlist?.entry;
        const tracks = Array.isArray(rawEntries) ? rawEntries : (rawEntries ? [rawEntries] : []);
        if (tracks.length > 0) {
          playQueue(tracks, 0);
          showToast(`▶ Playing "${playlist.name}"`, 'success');
        } else {
          showToast(`"${playlist.name}" is empty`, 'warning');
        }
      }
    } catch (err) {
      showToast('Could not load playlist tracks', 'error');
    } finally {
      setPlayingId(null);
    }
  };

  const handleSavePlaylist = async (playlist, e) => {
    e.preventDefault();
    e.stopPropagation();
    if (savingId) return;
    setSavingId(playlist.id);

    try {
      const auth = getSubsonicAuthParams(user);
      // 1. Fetch tracks
      const resTracks = await fetch(getAmpacheUrl(`action=getPlaylist&id=${playlist.id}&${auth}`));
      const dataTracks = await resTracks.json();
      const rawEntries = dataTracks?.['subsonic-response']?.playlist?.entry || [];
      const tracks = Array.isArray(rawEntries) ? rawEntries : (rawEntries ? [rawEntries] : []);

      if (tracks.length === 0) {
        showToast('Cannot save an empty playlist', 'warning');
        return;
      }

      // 2. Create personal playlist copy
      const copyName = `${playlist.name} (Saved)`;
      const resCreate = await fetch(getAmpacheUrl(`action=createPlaylist&name=${encodeURIComponent(copyName)}&${auth}`));
      const dataCreate = await resCreate.json();
      
      if (dataCreate?.['subsonic-response']?.status === 'ok') {
        const newId = dataCreate['subsonic-response']?.playlist?.id;
        if (newId) {
          const songIds = tracks.map(t => t.id).join(',');
          await fetch(getAmpacheUrl(`action=updatePlaylist&playlistId=${newId}&songIdToAdd=${songIds}&${auth}`));
        }
        showToast(`Saved "${playlist.name}" to My Playlists!`, 'success');
      } else {
        showToast('Failed to save playlist', 'error');
      }
    } catch (err) {
      showToast('Network error saving playlist', 'error');
    } finally {
      setSavingId(null);
    }
  };

  return (
    <div className="pb-28 max-w-7xl mx-auto">
      {/* Navigation Sub-Tabs */}
      <div className="flex items-center gap-2 mb-6 border-b border-white/10 pb-4">
        <Link 
          to="/playlists"
          className="px-4 py-2 rounded-xl text-xs sm:text-sm font-medium text-slate-400 hover:text-white hover:bg-white/5 transition-all"
        >
          My Playlists
        </Link>
        <div className="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-purple-500/15 border border-purple-500/30 text-purple-300 flex items-center gap-1.5 shadow-sm">
          <Globe size={14} />
          <span>Public Playlists</span>
          <span className="ml-1 text-[10px] px-1.5 py-0.2 bg-purple-500/30 text-purple-200 rounded-full font-bold">
            {playlists.length}
          </span>
        </div>
      </div>

      {/* Header & Controls */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 sm:mb-8 px-1">
        <div>
          <h1 className="text-xl sm:text-3xl font-bold text-white mb-1 flex items-center gap-2">
            <span>Public Playlists</span>
            <span className="text-xs px-2.5 py-0.5 rounded-full bg-purple-500/20 text-purple-300 font-normal border border-purple-500/30 hidden sm:inline">
              Community
            </span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-400">
            Discover and save playlists shared by music enthusiasts across the server.
          </p>
        </div>

        <div className="flex items-center gap-3 w-full md:w-auto">
          <div className="relative flex-1 md:w-64">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none" size={16} />
            <input 
              type="text" 
              placeholder="Search title or creator..." 
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full bg-slate-900/60 border border-white/10 rounded-full pl-9 pr-4 py-2 text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-colors"
            />
          </div>
          <button 
            onClick={() => openCreatePlaylistModal(fetchPublicPlaylists)}
            className="bg-purple-500 hover:bg-purple-400 text-white px-3.5 py-2 sm:px-4 sm:py-2 rounded-full font-medium text-xs sm:text-sm transition-all shadow-[0_0_20px_rgba(168,85,247,0.4)] active:scale-95 flex items-center gap-1.5 shrink-0"
          >
            <Plus size={15} />
            <span className="hidden xs:inline">New Playlist</span>
          </button>
        </div>
      </div>

      {loading ? (
        <div className="flex justify-center py-20">
          <div className="w-8 h-8 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
      ) : filteredPlaylists.length === 0 ? (
        <div className="text-center py-16 sm:py-24 bg-slate-900/40 rounded-3xl border border-white/5 p-6 max-w-xl mx-auto">
          <div className="w-16 h-16 rounded-full bg-purple-500/10 border border-purple-500/20 flex items-center justify-center mx-auto mb-4 text-purple-400">
            <Globe size={32} />
          </div>
          <h3 className="text-lg font-semibold text-white mb-1.5">
            {searchQuery ? "No matching public playlists" : "No Public Playlists Yet"}
          </h3>
          <p className="text-xs sm:text-sm text-slate-400 mb-6 max-w-sm mx-auto">
            {searchQuery 
              ? "Try searching for a different keyword or creator name."
              : "Be the first to share your music taste with others on the server!"}
          </p>
          <button 
            onClick={() => openCreatePlaylistModal(fetchPublicPlaylists)}
            className="bg-purple-500 hover:bg-purple-400 text-white px-5 py-2.5 rounded-full text-xs sm:text-sm font-medium transition-all shadow-lg active:scale-95"
          >
            Create a Public Playlist
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-5 md:gap-6">
          {filteredPlaylists.map((playlist) => {
            const isOwner = user && (playlist.owner === user.username);
            return (
              <Link 
                to={`/playlists/${playlist.id}`} 
                key={playlist.id} 
                className="group flex flex-col bg-slate-900/40 hover:bg-slate-800/60 p-4 rounded-2xl transition-all border border-white/5 hover:border-purple-500/30 backdrop-blur-sm relative active:scale-[0.98] shadow-md hover:shadow-xl"
              >
                {/* Artwork Banner */}
                <div className="relative aspect-video rounded-xl overflow-hidden mb-3.5 shadow-md bg-gradient-to-br from-purple-950 via-slate-900 to-indigo-950 flex items-center justify-center border border-white/5 group-hover:border-purple-500/20 transition-all">
                  <ListMusic size={36} className="text-purple-400/40 group-hover:text-purple-300/60 transition-colors" />
                  
                  {/* Hover Quick Play Button */}
                  <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                    <button 
                      onClick={(e) => handlePlayPlaylist(playlist, e)}
                      disabled={playingId === playlist.id}
                      className="w-12 h-12 rounded-full bg-purple-500 flex items-center justify-center text-white shadow-xl transform translate-y-3 group-hover:translate-y-0 transition-all duration-200 hover:scale-105 active:scale-95"
                      title="Play Playlist Now"
                      aria-label="Play Playlist"
                    >
                      {playingId === playlist.id ? (
                        <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                      ) : (
                        <Play fill="currentColor" size={20} className="ml-0.5" />
                      )}
                    </button>
                  </div>

                  {/* Public Badge */}
                  <div className="absolute top-2.5 left-2.5 px-2 py-0.5 rounded-md bg-black/60 backdrop-blur-md border border-white/10 text-[10px] font-semibold text-purple-300 flex items-center gap-1">
                    <Globe size={11} />
                    <span>Public</span>
                  </div>
                </div>

                {/* Playlist Info */}
                <div className="flex-1 min-w-0">
                  <h3 className="font-bold text-slate-100 group-hover:text-purple-300 transition-colors text-base truncate mb-1">
                    {playlist.name}
                  </h3>

                  {/* Creator */}
                  <div className="flex items-center gap-1.5 text-xs text-slate-400 mb-2 truncate">
                    <User size={13} className="text-purple-400 shrink-0" />
                    <span className="truncate font-medium">@{playlist.owner || 'Community'}</span>
                    {isOwner && (
                      <span className="text-[10px] px-1.5 py-0.2 rounded bg-purple-500/20 text-purple-300 shrink-0">
                        You
                      </span>
                    )}
                  </div>

                  {/* Stats & Actions */}
                  <div className="flex items-center justify-between pt-2 border-t border-white/5 text-xs text-slate-400">
                    <span>
                      {playlist.songCount || 0} tracks • {playlist.duration ? Math.floor(playlist.duration / 60) + 'm' : '0m'}
                    </span>

                    {!isOwner && (
                      <button 
                        onClick={(e) => handleSavePlaylist(playlist, e)}
                        disabled={savingId === playlist.id}
                        className="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-purple-500/20 text-slate-300 hover:text-purple-300 transition-colors flex items-center gap-1 text-[11px] font-medium border border-white/5 active:scale-95"
                        title="Save to My Playlists"
                      >
                        {savingId === playlist.id ? (
                          <div className="w-3.5 h-3.5 border-2 border-purple-400 border-t-transparent rounded-full animate-spin"></div>
                        ) : (
                          <>
                            <BookmarkPlus size={13} />
                            <span>Save</span>
                          </>
                        )}
                      </button>
                    )}
                  </div>
                </div>
              </Link>
            );
          })}
        </div>
      )}
    </div>
  );
}

