import { useMemo } from 'react';
import { Heart, Play, Shuffle, Music } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useLikedSongs } from '../context/LikedSongsContext';
import { usePlayer } from '../context/PlayerContext';
import { useAuth } from '../context/AuthContext';
import { getCoverArtUrl, getSubsonicAuthParams, DEFAULT_COVER_ART } from '../utils/api';
import HeartButton from './HeartButton';

function formatDuration(seconds) {
  if (!seconds || isNaN(seconds)) return '0:00';
  const m = Math.floor(seconds / 60);
  const s = Math.floor(seconds % 60);
  return `${m}:${s.toString().padStart(2, '0')}`;
}

export default function LikedSongsPlaylist() {
  const { likedSongs, loadingStarred } = useLikedSongs();
  const { playQueue } = usePlayer();
  const { user } = useAuth();
  const navigate = useNavigate();

  const authParams = useMemo(() => getSubsonicAuthParams(user), [user]);

  // Only show the 6 most recently liked as a preview strip
  const preview = useMemo(() => likedSongs.slice(0, 6), [likedSongs]);

  if (loadingStarred && likedSongs.length === 0) {
    return (
      <section aria-label="Liked Songs" className="space-y-4">
        <div className="flex items-center gap-2 px-1">
          <Heart size={20} className="text-rose-400 fill-rose-400" />
          <h3 className="text-lg font-bold text-white">Liked Songs</h3>
        </div>
        <div className="flex items-center justify-center py-10">
          <div className="w-6 h-6 border-2 border-rose-400 border-t-transparent rounded-full animate-spin" />
        </div>
      </section>
    );
  }

  if (likedSongs.length === 0) {
    return (
      <section aria-label="Liked Songs" className="space-y-3">
        <div className="flex items-center gap-2 px-1">
          <Heart size={20} className="text-rose-400 fill-rose-400" />
          <h3 className="text-lg font-bold text-white">Liked Songs</h3>
        </div>
        <div className="rounded-2xl border border-white/5 bg-slate-900/60 px-6 py-8 text-center">
          <Heart size={36} className="text-slate-700 fill-slate-700 mx-auto mb-3" />
          <p className="text-slate-400 text-sm">No liked songs yet.</p>
          <p className="text-slate-600 text-xs mt-1">Tap ❤️ on any track to save it here.</p>
        </div>
      </section>
    );
  }

  const handlePlayAll = (e) => {
    e.stopPropagation();
    if (likedSongs.length > 0) playQueue(likedSongs, 0);
  };

  const handleShuffle = (e) => {
    e.stopPropagation();
    const shuffled = [...likedSongs].sort(() => Math.random() - 0.5);
    if (shuffled.length > 0) playQueue(shuffled, 0);
  };

  const handlePlayFromTrack = (index) => {
    playQueue(likedSongs, index);
  };

  return (
    <section aria-label="Liked Songs" className="space-y-4">
      {/* Section Header */}
      <div className="flex items-center justify-between px-1">
        <div className="flex items-center gap-2">
          <Heart size={20} className="text-rose-400 fill-rose-400" />
          <h3 className="text-lg sm:text-2xl font-bold text-white">Liked Songs</h3>
          <span className="text-xs text-slate-500 font-medium ml-1">
            {likedSongs.length} {likedSongs.length === 1 ? 'track' : 'tracks'}
          </span>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={handleShuffle}
            className="p-2 rounded-full text-slate-400 hover:text-white hover:bg-white/5 transition-all active:scale-90"
            title="Shuffle Liked Songs"
          >
            <Shuffle size={16} />
          </button>
          <button
            onClick={handlePlayAll}
            className="flex items-center gap-1.5 px-4 py-1.5 rounded-full bg-rose-500/90 hover:bg-rose-400 text-white text-xs font-semibold shadow-[0_0_16px_rgba(244,63,94,0.35)] active:scale-95 transition-all"
          >
            <Play size={13} fill="currentColor" />
            Play All
          </button>
        </div>
      </div>

      {/* Preview track list (up to 6) */}
      <div className="rounded-2xl border border-white/5 bg-slate-900/60 backdrop-blur-sm overflow-hidden divide-y divide-white/5">
        {preview.map((song, idx) => {
          const cover = getCoverArtUrl(song.coverArt || song.albumId, authParams);
          return (
            <div
              key={song.id}
              onClick={() => handlePlayFromTrack(idx)}
              className="flex items-center gap-3 px-3 sm:px-4 py-2.5 hover:bg-white/5 cursor-pointer group transition-colors"
            >
              {/* Cover thumb */}
              <div className="w-9 h-9 sm:w-10 sm:h-10 rounded-lg overflow-hidden shrink-0 bg-slate-800 relative">
                <img
                  src={cover}
                  alt={song.album}
                  className="w-full h-full object-cover"
                  onError={(e) => { if (e.currentTarget.src !== DEFAULT_COVER_ART) e.currentTarget.src = DEFAULT_COVER_ART; }}
                />
                <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity rounded-lg">
                  <Play size={14} fill="white" className="text-white ml-0.5" />
                </div>
              </div>

              {/* Track info */}
              <div className="flex-1 min-w-0">
                <div className="text-sm font-semibold text-white truncate leading-tight">
                  {song.title}
                </div>
                <div className="text-xs text-slate-400 truncate">
                  <button
                    onClick={(e) => {
                      e.stopPropagation();
                      const artistId = song.artistId || song.artist_id;
                      if (artistId) navigate(`/artists/${artistId}`);
                    }}
                    className="hover:text-purple-400 hover:underline transition-colors"
                  >
                    {song.artist}
                  </button>
                  {song.album && (
                    <span className="text-slate-600"> · {song.album}</span>
                  )}
                </div>
              </div>

              {/* Duration + heart */}
              <div className="flex items-center gap-2 shrink-0">
                <span className="text-xs text-slate-500 font-mono hidden sm:block">
                  {formatDuration(song.duration)}
                </span>
                <HeartButton song={song} size={16} compact />
              </div>
            </div>
          );
        })}

        {/* "See all" row when there are more than 6 */}
        {likedSongs.length > 6 && (
          <div className="px-4 py-3 flex items-center justify-between bg-slate-900/40">
            <span className="text-xs text-slate-500">
              +{likedSongs.length - 6} more liked tracks
            </span>
            <button
              onClick={() => navigate('/liked')}
              className="text-xs font-semibold text-purple-400 hover:text-purple-300 transition-colors"
            >
              View all →
            </button>
          </div>
        )}
      </div>
    </section>
  );
}

