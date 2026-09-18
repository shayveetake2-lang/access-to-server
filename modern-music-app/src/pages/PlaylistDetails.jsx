import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Play, Clock, ListMusic, Trash2, ListPlus, Volume2, Globe, Lock, BookmarkPlus, User } from 'lucide-react';
import { usePlayer } from '../context/PlayerContext';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import { getAmpacheUrl, getCoverArtUrl, getApiProxyUrl } from '../utils/api';

export default function PlaylistDetails() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [playlist, setPlaylist] = useState(null);
  const [loading, setLoading] = useState(true);
  const [isUpdatingVisibility, setIsUpdatingVisibility] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const { playQueue, addToQueue, currentTrack, isPlaying } = usePlayer();
  const { user, getAuthParams } = useAuth();
  const { showToast } = useToast();

  const fetchPlaylistDetails = async () => {
    try {
      const response = await fetch(getAmpacheUrl(`action=getPlaylist&id=${id}&_t=${Date.now()}&${getAuthParams(user)}`));
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
        showToast("Track removed from playlist", "success");
        fetchPlaylistDetails();
      } else {
        showToast("Failed to remove track", "error");
      }
    } catch (err) {
      showToast("Network error", "error");
    }
  };

  const handleDeletePlaylist = async () => {
    if (!confirm("Are you sure you want to delete this entire playlist?")) return;
    
    try {
      const res = await fetch(getAmpacheUrl(`action=deletePlaylist&id=${id}&${getAuthParams(user)}`));
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        showToast("Playlist deleted", "success");
        navigate('/playlists');
      } else {
        showToast("Failed to delete playlist", "error");
      }
    } catch (err) {
      showToast("Network error", "error");
    }
  };

  if (loading) return <div className="flex justify-center py-20"><div className="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>;
  if (!playlist) return <div className="text-white text-center py-20">Playlist not found.</div>;

  // Convert single object to array if only 1 song is returned by XML to JSON converter
  const tracks = Array.isArray(playlist.entry) ? playlist.entry : (playlist.entry ? [playlist.entry] : []);

  const isOwner = user && (playlist.owner === user.username || !playlist.owner || playlist.owner === '');
  const isPublic = playlist.public === 'true' || playlist.public === true;

  const handleToggleVisibility = async () => {
    if (!isOwner || isUpdatingVisibility) return;
    const nextPublic = !isPublic;
    setIsUpdatingVisibility(true);

    // 1. Optimistic UI update: flip state immediately for zero-lag user feedback
    setPlaylist(prev => prev ? { ...prev, public: nextPublic } : prev);

    try {
      // 2. Direct MySQL database update via proxy (guaranteed persistence)
      try {
        await fetch(`${getApiProxyUrl()}?action=togglePlaylistVisibility&id=${id}&public=${nextPublic ? 'true' : 'false'}`);
      } catch (pe) {
        console.debug("Proxy toggle notice:", pe);
      }

      // 3. Also notify Subsonic API for internal cache consistency
      try {
        await fetch(getAmpacheUrl(`action=updatePlaylist&playlistId=${id}&public=${nextPublic ? 'true' : 'false'}&${getAuthParams(user)}`));
      } catch (se) {
        console.debug("Subsonic toggle notice:", se);
      }

      showToast(nextPublic ? "🌐 Playlist is now Public!" : "🔒 Playlist is now Private!", "success");

      // 4. Refresh playlist details with cache-buster
      await fetchPlaylistDetails();
    } catch (err) {
      // Revert on failure
      setPlaylist(prev => prev ? { ...prev, public: !nextPublic } : prev);
      showToast("Network error updating visibility.", "error");
    } finally {
      setIsUpdatingVisibility(false);
    }
  };

  const handleSaveToMyPlaylists = async () => {
    if (!user || isSaving) return;
    if (tracks.length === 0) {
      showToast("This playlist is empty", "warning");
      return;
    }
    setIsSaving(true);
    try {
      const copyName = `${playlist.name} (Saved)`;
      const res = await fetch(getAmpacheUrl(`action=createPlaylist&name=${encodeURIComponent(copyName)}&${getAuthParams(user)}`));
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        const newId = data['subsonic-response']?.playlist?.id;
        if (newId) {
          const songIds = tracks.map(t => t.id).join(',');
          await fetch(getAmpacheUrl(`action=updatePlaylist&playlistId=${newId}&songIdToAdd=${songIds}&${getAuthParams(user)}`));
        }
        showToast(`Saved "${playlist.name}" to My Playlists!`, "success");
      } else {
        showToast("Could not save playlist", "error");
      }
    } catch (err) {
      showToast("Network error", "error");
    } finally {
      setIsSaving(false);
    }
  };

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
            <span>{playlist.songCount || tracks.length} tracks</span>
            <span>•</span>
            <span>{Math.floor((playlist.duration || 0) / 60)} minutes</span>
            {playlist.owner && (
              <>
                <span>•</span>
                <span className="flex items-center gap-1 text-slate-400">
                  <User size={13} className="text-purple-400" />
                  <span>@{playlist.owner}</span>
                </span>
              </>
            )}
            <span className={`px-2 py-0.5 rounded-full text-[11px] font-semibold flex items-center gap-1 ${
              isPublic ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-slate-800 text-slate-400 border border-white/5'
            }`}>
              {isPublic ? <Globe size={11} /> : <Lock size={11} />}
              {isPublic ? 'Public' : 'Private'}
            </span>
          </div>
        </div>
      </div>

      {/* Controls */}
      <div className="flex flex-wrap items-center justify-center sm:justify-start gap-3 sm:gap-4 mb-6 sm:mb-8 px-1">
        <button 
          onClick={playEntirePlaylist} 
          disabled={tracks.length === 0}
          className="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-purple-500 disabled:opacity-40 flex items-center justify-center text-white hover:bg-purple-400 transition-all shadow-[0_0_20px_rgba(168,85,247,0.4)] active:scale-95 hover:scale-105 shrink-0"
          aria-label="Play Playlist"
        >
          <Play fill="currentColor" size={22} className="ml-0.5" />
        </button>

        {isOwner ? (
          <>
            <button 
              onClick={handleToggleVisibility}
              disabled={isUpdatingVisibility}
              className={`px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-full font-medium text-xs sm:text-sm flex items-center gap-1.5 transition-all border active:scale-95 ${
                isPublic 
                  ? 'bg-purple-500/15 border-purple-500/40 text-purple-300 hover:bg-purple-500/25' 
                  : 'bg-slate-800/80 border-white/10 text-slate-300 hover:bg-slate-700/80'
              }`}
              title="Toggle public/private visibility"
            >
              {isPublic ? <Globe size={15} className="text-purple-400" /> : <Lock size={15} className="text-slate-400" />}
              <span>{isPublic ? "Public (Shared)" : "Private (Only You)"}</span>
            </button>
            <button 
              onClick={handleDeletePlaylist} 
              className="px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-full border border-red-500/40 text-red-400 hover:bg-red-500/10 font-medium text-xs sm:text-sm flex items-center gap-1.5 transition-all active:scale-95"
            >
              <Trash2 size={15} /> Delete Playlist
            </button>
          </>
        ) : (
          <button 
            onClick={handleSaveToMyPlaylists}
            disabled={isSaving}
            className="px-4 py-2 sm:px-5 sm:py-2.5 rounded-full bg-indigo-600/90 hover:bg-indigo-500 text-white font-medium text-xs sm:text-sm flex items-center gap-2 transition-all shadow-[0_0_16px_rgba(99,102,241,0.3)] active:scale-95 disabled:opacity-50"
          >
            <BookmarkPlus size={16} />
            <span>{isSaving ? "Saving..." : "Save to My Playlists"}</span>
          </button>
        )}
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
                      showToast(`Added "${song.title}" to play next`, 'success');
                    }} 
                    className="w-8 h-8 flex items-center justify-center rounded-lg bg-white/5 hover:bg-purple-500/25 text-slate-300 hover:text-purple-300 active:scale-95 transition-all shadow-sm"
                    title="Add to Queue (Play Next)"
                    aria-label="Add to Queue"
                  >
                    <ListPlus size={16} />
                  </button>
                  {isOwner && (
                    <button 
                      onClick={(e) => handleRemoveTrack(index, e)} 
                      className="w-8 h-8 flex items-center justify-center rounded-lg bg-white/5 hover:bg-red-500/25 text-slate-300 hover:text-red-400 active:scale-95 transition-all shadow-sm"
                      title="Remove from Playlist"
                      aria-label="Remove from Playlist"
                    >
                      <Trash2 size={15} />
                    </button>
                  )}
                </div>
              </div>
            );
          })
        )}
      </div>

      {/* Tracklist Desktop Table */}
      <div className="hidden sm:block bg-slate-900/40 backdrop-blur-sm rounded-2xl border border-white/5 overflow-hidden">
        <table className="w-full text-left border-collapse table-fixed">
          <thead>
            <tr className="text-slate-400 border-b border-white/10 text-xs uppercase tracking-wider">
              <th className="font-semibold px-4 sm:px-5 py-4 w-12 text-center">#</th>
              <th className="font-semibold px-4 py-4 w-auto">Title</th>
              <th className="font-semibold px-4 py-4 hidden lg:table-cell w-44 xl:w-56">Artist</th>
              <th className="font-semibold px-4 py-4 hidden xl:table-cell w-44 xl:w-56">Album</th>
              <th className="font-semibold px-4 sm:px-6 py-4 text-right w-36 sm:w-44 shrink-0">
                <div className="flex items-center justify-end gap-2 text-slate-400">
                  <Clock size={16} />
                  <span className="text-[11px] normal-case tracking-normal">Actions</span>
                </div>
              </th>
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
                  <td className="px-4 sm:px-5 py-3 text-xs text-slate-400 text-center w-12">
                    {isCurrent ? <Volume2 size={15} className={`text-purple-400 mx-auto ${isPlaying ? 'animate-pulse' : ''}`} /> : index + 1}
                  </td>
                  <td className="px-4 py-3 min-w-0">
                    <div className="flex items-center gap-3 min-w-0">
                      <img src={getCoverArtUrl(song.coverArt, getAuthParams(user))} className="w-10 h-10 rounded-lg bg-slate-800 object-cover shrink-0 border border-white/5 shadow-sm" alt="" loading="lazy" />
                      <div className="min-w-0 flex-1">
                        <div className={`font-medium transition-colors truncate ${
                          isCurrent ? 'text-purple-300 font-semibold' : 'text-white group-hover:text-purple-400'
                        }`}>
                          {song.title}
                        </div>
                        <div className="text-xs text-slate-400 truncate lg:hidden mt-0.5">
                          {song.artist}{song.album ? ` • ${song.album}` : ''}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-slate-300 truncate hidden lg:table-cell">{song.artist}</td>
                  <td className="px-4 py-3 text-slate-400 truncate hidden xl:table-cell">{song.album}</td>
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
                      {isOwner && (
                        <button 
                          onClick={(e) => handleRemoveTrack(index, e)} 
                          className="w-8 h-8 flex items-center justify-center rounded-lg bg-white/5 hover:bg-red-500/25 text-slate-300 hover:text-red-400 active:scale-95 transition-all shadow-sm shrink-0"
                          title="Remove from Playlist"
                          aria-label="Remove from Playlist"
                        >
                          <Trash2 size={15} />
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              );
            })}
            {tracks.length === 0 && (
               <tr>
                 <td colSpan={5} className="px-6 py-10 text-center text-slate-400 text-xs">This playlist is empty.</td>
               </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
