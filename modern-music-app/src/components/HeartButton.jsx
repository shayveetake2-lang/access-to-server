import { Heart } from 'lucide-react';
import { useLikedSongs } from '../context/LikedSongsContext';

/**
 * HeartButton — 1-click star/unstar button for any track.
 * Props:
 *   song        — full song object (must have .id)
 *   size        — icon size (default 18)
 *   className   — extra classes for the outer button
 *   compact     — if true, renders only the heart icon (no padding ring)
 */
export default function HeartButton({ song, size = 18, className = '', compact = false }) {
  const { isLiked, toggleLike } = useLikedSongs();

  if (!song?.id) return null;

  const liked = isLiked(song.id);

  const handleClick = (e) => {
    e.stopPropagation();
    e.preventDefault();
    toggleLike(song);
  };

  if (compact) {
    return (
      <button
        onClick={handleClick}
        aria-label={liked ? 'Unlike song' : 'Like song'}
        title={liked ? 'Remove from Liked Songs' : 'Add to Liked Songs'}
        className={`group transition-all active:scale-90 ${className}`}
      >
        <Heart
          size={size}
          className={`transition-all duration-200 ${
            liked
              ? 'text-rose-400 fill-rose-400'
              : 'text-slate-500 group-hover:text-rose-400 fill-transparent'
          }`}
        />
      </button>
    );
  }

  return (
    <button
      onClick={handleClick}
      aria-label={liked ? 'Unlike song' : 'Like song'}
      title={liked ? 'Remove from Liked Songs' : 'Add to Liked Songs'}
      className={`p-2 rounded-full transition-all active:scale-90 ${
        liked
          ? 'text-rose-400 bg-rose-500/10 hover:bg-rose-500/20'
          : 'text-slate-500 hover:text-rose-400 hover:bg-white/5'
      } ${className}`}
    >
      <Heart
        size={size}
        className={`transition-all duration-200 ${liked ? 'fill-rose-400' : 'fill-transparent'}`}
      />
    </button>
  );
}

