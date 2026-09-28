import { useMemo } from 'react';
import { Heart, Play, Shuffle, ArrowLeft } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useLikedSongs } from '../context/LikedSongsContext';
import { usePlayer } from '../context/PlayerContext';
import { useAuth } from '../context/AuthContext';
import { getCoverArtUrl, getSubsonicAuthParams, DEFAULT_COVER_ART } from '../utils/api';
import HeartButton from '../components/HeartButton';

function formatDuration(seconds) {
  if (!seconds || isNaN(seconds)) return '0:00';
  const m = Math.floor(seconds / 60);
  const s = Math.floor(seconds % 60);
  return `${m}:${s.toString().padStart(2, '0')}`;
}

export default function LikedSongsPage() {
  const { likedSongs, loadingStarred } = useLikedSongs();
  const { playQueue } = usePlayer();
  const { user } = useAuth();
  const navigate = useNavigate();

  const authParams = useMemo(() => getSubsonicAuthParams(user), [user]);

  const handlePlayAll = () => {
    if (likedSongs.length > 0) playQueue(likedSongs, 0);
  };
  const handleShuffle = () => {
    const shuffled = [...likedSongs].sort(() => Math.random() - 0.5);
    if (shuffled.length > 0) playQueue(shuffled, 0);
  };

  return (
    <div className="space-y-6 pb-28 max-w-4xl mx-auto">
      {/* Header */}
      <div className="flex items-center gap-4">
        <button
          onClick={() => navigate(-1)}
          className="p-2 rounded-full hover:bg-white/5 text-slate-400 hover:text-white transition-colors"
        >
          <ArrowLeft size={20} />
        </button>
        <div className="flex-1 flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-rose-500/20 flex items-center justify-center">
            <Heart size={22} className="text-rose-400 fill-rose-400" />
          </div>
          <div>
            <h1 className="text-xl sm:text-3xl font-extrabold text-white tracking-tight">Liked Songs</h1>
            <p className="text-xs text-slate-400 mt-0.5">{likedSongs.length} {likedSongs.length === 1 ? 'track' : 'tracks'}</p>
          </div>
        </div>
        {likedSongs.length > 0 && (
          <div className="flex items-center gap-2">
            <button
              onClick={handleShuffle}
              className="p-2.5 rounded-full bg-white/5 hover:bg-white/10 text-slate-300 transition-all active:scale-90"
              title="Shuffle"
            >
              <Shuffle size={18} />
            </button>
            <button
              onClick={handlePlayAll}
              className="flex items-center gap-2 px-5 py-2.5 rounded-full bg-rose-500 hover:bg-rose-400 text-white font-semibold text-sm shadow-[0_0_20px_rgba(244,63,94,0.4)] active:scale-95 transition-all"
            >
              <Play size={15} fill="currentColor" />
              Play All
            </button>
          </div>
        )}
      </div>

      {/* Content */}
      {loadingStarred && likedSongs.length === 0 ? (
        <div className="flex items-center justify-center py-24">
          <div className="w-10 h-10 border-4 border-rose-500 border-t-transparent rounded-full animate-spin" />
        </div>
      ) : likedSongs.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-24 gap-4 text-center">
          <Heart size={56} className="text-slate-700 fill-slate-700" />
          <h3 className="text-white font-bold text-xl">No liked songs yet</h3>
          <p className="text-slate-400 text-sm max-w-sm">
            Tap the ❤️ button on any track in the player, library, or album view to save it here.
          </p>
          <button
            onClick={() => navigate('/songs')}
            className="mt-2 px-6 py-2.5 rounded-full bg-purple-500 hover:bg-purple-400 text-white font-semibold text-sm transition-all"
          >
            Browse Library
          </button>
        </div>
      ) : (
        <div className="rounded-2xl border border-white/5 bg-slate-900/60 overflow-hidden divide-y divide-white/5">
          {likedSongs.map((song, idx) => {
            const cover = getCoverArtUrl(song.coverArt || song.albumId, authParams);
            return (
              <div
                key={song.id}
                onClick={() => playQueue(likedSongs, idx)}
                className="flex items-center gap-3 px-3 sm:px-5 py-3 hover:bg-white/5 cursor-pointer group transition-colors"
              >
                {/* Index */}
                <span className="text-xs text-slate-600 font-mono w-5 text-right shrink-0 group-hover:hidden">
                  {idx + 1}
                </span>
                <Play size={14} fill="white" className="text-white shrink-0 hidden group-hover:block" />

                {/* Cover */}
                <div className="w-10 h-10 rounded-lg overflow-hidden shrink-0 bg-slate-800">
                  <img
                    src={cover}
                    alt={song.album}
                    className="w-full h-full object-cover"
                    onError={(e) => { if (e.currentTarget.src !== DEFAULT_COVER_ART) { e.currentTarget.src = DEFAULT_COVER_ART; } }}
/>
                </div>

                {/* Track info */}
                <div className="flex-1 min-w-0">
                  <div className="text-sm font-semibold text-white truncate">{song.title}</div>
                  <div className="text-xs text-slate-400 truncate">
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        const aid = song.artistId || song.artist_id;
                        if (aid) navigate(`/artists/${aid}`);
                      }}
                      className="hover:text-purple-400 hover:underline transition-colors"
                    >
                      {song.artist}
                    </button>
                    {song.album && <span className="text-slate-600"> · {song.album}</span>}
                  </div>
                </div>

                {/* Duration + heart */}
                <div className="flex items-center gap-2 shrink-0">
                  <span className="text-xs text-slate-500 font-mono hidden sm:block">{formatDuration(song.duration)}</span>
                  <HeartButton song={song} size={16} compact />
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}

