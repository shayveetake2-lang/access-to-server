import { useEffect, useState } from 'react';
import { Sparkles, Disc, Music, Play, Plus, ChevronRight } from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { usePlayer } from '../context/PlayerContext';
import { useToast } from '../context/ToastContext';
import { fetchAlbumDetails, getCoverArtUrl, fetchRecentlyAdded, DEFAULT_COVER_ART } from '../utils/api';

function formatDuration(seconds) {
  if (!seconds || isNaN(seconds)) return '0:00';
  const m = Math.floor(seconds / 60);
  const s = Math.floor(seconds % 60);
  return `${m}:${s.toString().padStart(2, '0')}`;
}

/**
 * RecentlyAdded — self-contained widget that fetches the latest ingested
 * albums & tracks from the Ampache backend (Subsonic getAlbumList2/newest)
 * and renders a tabbed carousel/list with quick-play actions.
 */
export default function RecentlyAdded() {
  const [albums, setAlbums] = useState([]);
  const [songs, setSongs] = useState([]);
  const [tab, setTab] = useState('albums'); // 'albums' | 'songs'
  const [loading, setLoading] = useState(true);
  const [playingAlbumId, setPlayingAlbumId] = useState(null);

  const { user, getAuthParams } = useAuth();
  const { playQueue, addToQueue } = usePlayer();
  const { showToast } = useToast();
  const navigate = useNavigate();

  useEffect(() => {
    let isMounted = true;
    if (!user) return;

    (async () => {
      setLoading(true);
      setAlbums([]);
      setSongs([]);
      try {
        const { recentAlbums, recentSongs } = await fetchRecentlyAdded(user, { songLimit: 20, albumLimit: 16 });
        if (!isMounted) return;
        setAlbums(recentAlbums);
        setSongs(recentSongs);
      } catch (err) {
        console.debug('RecentlyAdded fetch error:', err);
      } finally {
        if (isMounted) setLoading(false);
      }
    })();

    return () => { isMounted = false; };
  }, [user]);

  const handlePlayAlbum = async (album, e) => {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    if (playingAlbumId) return;
    setPlayingAlbumId(album.id);
    try {
      const albumData = await fetchAlbumDetails(album.id, user);
      if (albumData?.song) {
        const rawSongs = albumData.song;
        const albumSongs = Array.isArray(rawSongs) ? rawSongs : [rawSongs];
        if (albumSongs.length > 0) {
          playQueue(albumSongs, 0);
          showToast(`▶ Playing album "${album.name || album.title}"`, 'success');
        } else {
          showToast(`No tracks in "${album.name || album.title}"`, 'warning');
        }
      } else {
        showToast(`No tracks in "${album.name || album.title}"`, 'warning');
      }
    } catch (err) {
      showToast('Failed to play album', 'error');
    } finally {
      setPlayingAlbumId(null);
    }
  };

  const handlePlaySong = (song, index, e) => {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    if (songs.length > 0) {
      playQueue(songs, index);
    } else {
      playQueue([song], 0);
    }
    showToast(`▶ Playing "${song.title}" by ${song.artist}`, 'success');
  };

  if (loading || (albums.length === 0 && songs.length === 0)) {
    return null;
  }

  return (
    <section aria-label="Recently Added" className="space-y-4 pt-1">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-1">
        <div>
          <h3 className="text-lg sm:text-2xl font-bold text-white flex items-center gap-2">
            <Sparkles size={20} className="text-purple-400" />
            <span>Recently Added</span>
          </h3>
          <p className="text-xs text-slate-400 mt-0.5">
            Newly added albums and fresh tracks in your library
          </p>
        </div>

        <div className="flex items-center gap-2 self-start sm:self-auto">
          <div className="flex bg-slate-900/80 p-1 rounded-full border border-white/10 shadow-inner">
            <button
              onClick={() => setTab('albums')}
              className={`px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all flex items-center gap-1.5 ${
                tab === 'albums'
                  ? 'bg-purple-600 text-white shadow-[0_0_12px_rgba(168,85,247,0.4)]'
                  : 'text-slate-400 hover:text-white'
              }`}
            >
              <Disc size={13} />
              <span>New Albums</span>
              {albums.length > 0 && <span className="text-[10px] opacity-75">({albums.length})</span>}
            </button>
            <button
              onClick={() => setTab('songs')}
              className={`px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all flex items-center gap-1.5 ${
                tab === 'songs'
                  ? 'bg-purple-600 text-white shadow-[0_0_12px_rgba(168,85,247,0.4)]'
                  : 'text-slate-400 hover:text-white'
              }`}
            >
              <Music size={13} />
              <span>New Songs</span>
              {songs.length > 0 && <span className="text-[10px] opacity-75">({songs.length})</span>}
            </button>
          </div>

          <Link
            to={tab === 'albums' ? '/albums' : '/songs'}
            className="text-xs font-semibold text-purple-400 hover:text-purple-300 flex items-center gap-1 group transition-colors ml-2"
          >
            <span>Explore all</span>
            <ChevronRight size={14} className="group-hover:translate-x-0.5 transition-transform" />
          </Link>
        </div>
      </div>

      {tab === 'albums' && (
        <div className="flex gap-3.5 sm:gap-5 overflow-x-auto pb-3 pt-1 touch-scroll scrollbar-none px-1">
          {albums.map(album => (
            <div
              key={album.id}
              className="group flex flex-col w-36 sm:w-44 md:w-48 shrink-0 bg-slate-900/40 hover:bg-slate-800/60 p-2.5 sm:p-3 rounded-2xl transition-all border border-white/5 hover:border-purple-500/30 backdrop-blur-sm relative active:scale-[0.98] cursor-pointer"
              onClick={() => navigate(`/albums/${album.id}`)}
            >
              <div className="relative aspect-square rounded-xl overflow-hidden mb-2.5 shadow-md bg-slate-800 border border-white/5">
                <img
                  src={getCoverArtUrl(album.coverArt || album.id, getAuthParams(user))}
                  alt={album.name || album.title}
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                  loading="lazy"
                  decoding="async"
                  onError={(e) => {
                    if (e.currentTarget.src !== DEFAULT_COVER_ART) e.currentTarget.src = DEFAULT_COVER_ART;
                  }}
                />
                <div className="hidden sm:flex absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity items-center justify-center">
                  <button
                    onClick={(e) => handlePlayAlbum(album, e)}
                    disabled={playingAlbumId === album.id}
                    className="w-11 h-11 rounded-full bg-purple-500 flex items-center justify-center text-white shadow-xl transform translate-y-2 group-hover:translate-y-0 transition-all duration-200 hover:scale-105 active:scale-95"
                    title="Play Album"
                    aria-label="Play Album"
                  >
                    {playingAlbumId === album.id ? (
                      <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                    ) : (
                      <Play fill="currentColor" size={18} className="ml-0.5" />
                    )}
                  </button>
                </div>
                <div className="absolute top-2 left-2 px-1.5 py-0.5 rounded bg-black/60 backdrop-blur-md border border-purple-500/30 text-[9px] font-bold text-purple-300">
                  NEW
                </div>
              </div>
              <div className="px-0.5 min-w-0">
                <h4 className="font-semibold text-slate-100 group-hover:text-purple-300 transition-colors text-xs sm:text-sm truncate leading-snug">
                  {album.name || album.title}
                </h4>
                <p className="text-[11px] sm:text-xs text-slate-400 truncate mt-0.5">{album.artist}</p>
                <div className="flex items-center gap-1.5 text-[10px] text-slate-500 mt-1">
                  {album.year && <span>{album.year}</span>}
                  {album.year && album.songCount && <span>•</span>}
                  {album.songCount && <span>{album.songCount} tracks</span>}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {tab === 'songs' && (
        songs.length === 0 ? (
          <div className="text-center py-10 bg-slate-900/30 rounded-2xl border border-white/5 text-xs text-slate-400">
            No recently added individual songs found in catalog. Check back after next library rescan.
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-2.5 px-1">
            {songs.map((song, idx) => (
              <div
                key={song.id || idx}
                onClick={(e) => handlePlaySong(song, idx, e)}
                className="group flex items-center justify-between p-2.5 sm:p-3 rounded-2xl bg-slate-900/40 hover:bg-slate-800/60 border border-white/5 hover:border-purple-500/30 transition-all cursor-pointer backdrop-blur-sm active:scale-[0.99]"
              >
                <div className="flex items-center gap-3 min-w-0">
                  <div className="relative w-11 h-11 rounded-xl overflow-hidden bg-slate-800 shrink-0 border border-white/5 shadow-sm">
                    <img
                      src={getCoverArtUrl(song.coverArt || song.parent, getAuthParams(user))}
                      alt={song.title}
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                      loading="lazy"
                      decoding="async"
                      onError={(e) => {
                        if (e.currentTarget.src !== DEFAULT_COVER_ART) e.currentTarget.src = DEFAULT_COVER_ART;
                      }}
                    />
                    <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                      <Play fill="currentColor" size={16} className="text-white ml-0.5" />
                    </div>
                  </div>
                  <div className="min-w-0 pr-2">
                    <h4 className="font-semibold text-slate-100 group-hover:text-purple-300 transition-colors text-xs sm:text-sm truncate">
                      {song.title}
                    </h4>
                    <p className="text-[11px] sm:text-xs text-slate-400 truncate mt-0.5">
                      {song.artist} <span className="opacity-40">•</span> {song.album}
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                  <span className="text-[11px] font-mono text-slate-400">{formatDuration(song.duration)}</span>
                  <button
                    onClick={(e) => {
                      e.stopPropagation();
                      addToQueue(song);
                      showToast(`Added "${song.title}" to queue`, 'success');
                    }}
                    className="w-8 h-8 rounded-full bg-white/5 hover:bg-purple-500/20 text-slate-400 hover:text-purple-300 flex items-center justify-center transition-colors active:scale-95"
                    title="Add to Queue"
                    aria-label="Add to Queue"
                  >
                    <Plus size={14} />
                  </button>
                </div>
              </div>
            ))}
          </div>
        )
      )}
    </section>
  );
}
