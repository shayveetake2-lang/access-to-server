import { useAuth } from '../context/AuthContext';
import { useEffect, useState } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { Play, Clock, Plus, ListPlus, Volume2, Disc, ArrowLeft } from 'lucide-react';
import { usePlayer } from '../context/PlayerContext';
import { usePlaylistModal } from '../context/PlaylistModalContext';
import { useToast } from '../context/ToastContext';
import { getAmpacheUrl, getCoverArtUrl, DEFAULT_COVER_ART } from '../utils/api';
import HeartButton from '../components/HeartButton';

export default function AlbumDetails() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [album, setAlbum] = useState(null);
  const { user, getAuthParams } = useAuth();
  const [loading, setLoading] = useState(true);
  const { playQueue, addToQueue, currentTrack, isPlaying } = usePlayer();
  const { openAddToPlaylistModal } = usePlaylistModal();
  const { showToast } = useToast();

  useEffect(() => {
    let isMounted = true;

    const fetchAlbumDetails = async () => {
      setLoading(true);
      try {
        const response = await fetch(getAmpacheUrl(`action=getAlbum&id=${id}&${getAuthParams(user)}`));
        const data = await response.json();
        if (data?.['subsonic-response']?.status === 'ok') {
          if (isMounted) setAlbum(data['subsonic-response'].album);
        } else {
          if (isMounted) setAlbum(null);
        }
      } catch (err) {
        console.error('Error fetching album details:', err);
        if (isMounted) setAlbum(null);
      } finally {
        if (isMounted) setLoading(false);
      }
    };

    if (user && id) {
      fetchAlbumDetails();
    }

    return () => {
      isMounted = false;
    };
  }, [id, user]);

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center py-24 gap-3">
        <div className="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
        <p className="text-xs text-slate-400">Loading album...</p>
      </div>
    );
  }

  if (!album) {
    return (
      <div className="text-center py-20 bg-slate-900/40 rounded-3xl border border-white/5 p-6 max-w-md mx-auto my-8">
        <Disc size={44} className="mx-auto text-slate-500 mb-3" />
        <h3 className="text-lg font-semibold text-white mb-1.5">Album Not Found</h3>
        <p className="text-xs text-slate-400 mb-5">This album could not be loaded or was removed from the catalog.</p>
        <button 
          onClick={() => navigate('/albums')} 
          className="bg-purple-500 hover:bg-purple-400 text-white px-5 py-2.5 rounded-full text-xs font-semibold transition-all shadow-lg active:scale-95"
        >
          View All Albums
        </button>
      </div>
    );
  }

  // Normalize single object or array to guaranteed array
  const rawSongs = album.song || [];
  const songs = Array.isArray(rawSongs) ? rawSongs : (rawSongs ? [rawSongs] : []);

  const coverUrl = getCoverArtUrl(album.coverArt || album.id, getAuthParams(user));

  const playEntireAlbum = () => {
    if (songs.length > 0) {
      playQueue(songs, 0);
    }
  };

  const playFromTrack = (index) => {
    if (songs.length > 0) {
      playQueue(songs, index);
    }
  };

  return (
    <div className="pb-28 max-w-7xl mx-auto">
      {/* Header */}
      <div className="flex flex-col sm:flex-row gap-5 sm:gap-8 items-center sm:items-end mb-6 sm:mb-8 mt-2 text-center sm:text-left bg-gradient-to-b from-purple-900/20 to-transparent p-4 sm:p-6 rounded-3xl border border-white/5">
        <div className="w-36 h-36 sm:w-48 sm:h-48 md:w-56 md:h-56 rounded-2xl overflow-hidden shadow-[0_16px_40px_rgba(0,0,0,0.7)] shrink-0 border border-white/10 bg-slate-800 relative">
          <img 
            src={coverUrl} 
            alt={album.name} 
            className="w-full h-full object-cover" 
            onError={(e) => {
              if (e.currentTarget.src !== DEFAULT_COVER_ART) {
                e.currentTarget.src = DEFAULT_COVER_ART;
              }
            }}
          />
        </div>
        <div className="flex-1 min-w-0">
          <span className="text-[11px] sm:text-xs font-semibold uppercase tracking-wider text-purple-400 block mb-0.5">Album</span>
          <h1 className="text-2xl sm:text-4xl md:text-5xl font-bold text-white mb-2 leading-tight truncate">{album.name}</h1>
          <div className="flex flex-wrap items-center justify-center sm:justify-start gap-2 text-slate-300 text-xs sm:text-sm font-medium">
            {album.artistId ? (
              <Link to={`/artists/${album.artistId}`} className="text-white font-semibold hover:text-purple-300 hover:underline transition-colors">
                {album.artist}
              </Link>
            ) : (
              <span className="text-white font-semibold">{album.artist}</span>
            )}
            <span>•</span>
            <span>{album.year || 'Unknown Year'}</span>
            <span>•</span>
            <span>{album.songCount || songs.length} songs</span>
          </div>
        </div>
      </div>

      {/* Controls */}
      <div className="flex items-center justify-center sm:justify-start gap-3 sm:gap-4 mb-6 sm:mb-8 px-1">
        <button 
          onClick={playEntireAlbum} 
          disabled={songs.length === 0}
          className="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-purple-500 disabled:opacity-40 flex items-center justify-center text-white hover:bg-purple-400 transition-all shadow-[0_0_20px_rgba(168,85,247,0.4)] active:scale-95 hover:scale-105 shrink-0"
          aria-label="Play Album"
        >
          <Play fill="currentColor" size={22} className="ml-0.5" />
        </button>
        {songs[0] && (
          <HeartButton song={songs[0]} size={20} className="w-10 h-10 rounded-full border border-white/15 hover:border-white/30 flex items-center justify-center hover:bg-white/5 active:scale-95 transition-all" />
        )}
      </div>

      {/* Tracklist Mobile View */}
      <div className="sm:hidden space-y-1 bg-slate-900/40 backdrop-blur-sm rounded-2xl p-2 border border-white/5">
        {songs.length === 0 ? (
          <div className="p-8 text-center text-slate-400 text-xs">No tracks found for this album.</div>
        ) : (
          songs.map((song, index) => {
            if (!song) return null;
            const isCurrent = currentTrack?.id === song.id;
            const dur = Number(song.duration) || 0;
            return (
              <div 
                key={`${song.id || 'track'}-${index}`} 
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
                      {song.title || 'Untitled Track'}
                    </h4>
                    <p className="text-[11px] text-slate-400 truncate mt-0.5">{song.artist || album.artist}</p>
                  </div>
                </div>
                <div className="flex items-center gap-1.5 shrink-0">
                  <span className="text-[11px] text-slate-400 font-mono">
                    {Math.floor(dur / 60)}:{(dur % 60).toString().padStart(2, '0')}
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
              <th className="font-semibold px-4 sm:px-6 py-4 text-right w-36 sm:w-44 shrink-0">
                <div className="flex items-center justify-end gap-2 text-slate-400">
                  <Clock size={16} />
                  <span className="text-[11px] normal-case tracking-normal">Actions</span>
                </div>
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-white/5 text-sm">
            {songs.map((song, index) => {
              if (!song) return null;
              const isCurrent = currentTrack?.id === song.id;
              const dur = Number(song.duration) || 0;
              return (
                <tr 
                  key={`${song.id || 'track'}-${index}`} 
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
                      {song.title || 'Untitled Track'}
                    </div>
                  </td>
                  <td className="px-4 sm:px-6 py-3 text-right w-36 sm:w-44 shrink-0">
                    <div className="flex items-center justify-end gap-1.5 font-mono text-xs">
                      <span className="text-slate-400 mr-1 text-[11px] sm:text-xs">{Math.floor(dur / 60)}:{(dur % 60).toString().padStart(2, '0')}</span>
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
            {songs.length === 0 && (
              <tr>
                <td colSpan={3} className="px-6 py-10 text-center text-slate-400 text-xs">No tracks found for this album.</td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
