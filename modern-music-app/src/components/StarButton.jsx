import { useState } from 'react';
import { Heart } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import { toggleStarredItem } from '../utils/api';

/**
 * StarButton — 1-click favorite/unfavorite toggle for albums and artists.
 * Unlike <HeartButton /> (songs only, via LikedSongsContext + `id`), this posts
 * to the Subsonic star/unstar endpoints with the correct `albumId`/`artistId`
 * parameter so the right entity gets favorited instead of a track.
 *
 * Props:
 *   type            — 'album' | 'artist'
 *   id              — the album or artist's Subsonic id
 *   initialStarred  — seed state, e.g. `!!album.starred` from getAlbum/getArtist
 *   size            — icon size (default 20)
 *   className       — extra classes for the outer button
 */
export default function StarButton({ type, id, initialStarred = false, size = 20, className = '' }) {
  const { user } = useAuth();
  const { showToast } = useToast();
  const [starred, setStarred] = useState(!!initialStarred);
  const [busy, setBusy] = useState(false);

  if (!id || (type !== 'album' && type !== 'artist')) return null;

  const handleClick = async (e) => {
    e.stopPropagation();
    e.preventDefault();
    if (busy) return;

    const wasStarred = starred;
    setBusy(true);
    setStarred(!wasStarred); // optimistic

    try {
      const payload = type === 'album' ? { albumId: id } : { artistId: id };
      const ok = await toggleStarredItem(payload, wasStarred, user);
      if (!ok) {
        setStarred(wasStarred); // revert on failure
        showToast(`Failed to ${wasStarred ? 'remove' : 'add'} favorite`, 'error');
      } else {
        showToast(wasStarred ? `Removed ${type} from favorites` : `Added ${type} to favorites`, 'success');
      }
    } catch (err) {
      setStarred(wasStarred);
      showToast('Network error updating favorite', 'error');
    } finally {
      setBusy(false);
    }
  };

  return (
    <button
      onClick={handleClick}
      disabled={busy}
      aria-label={starred ? `Remove ${type} from favorites` : `Add ${type} to favorites`}
      title={starred ? `Remove ${type} from Favorites` : `Add ${type} to Favorites`}
      className={`p-2 rounded-full transition-all active:scale-90 disabled:opacity-50 ${
        starred
          ? 'text-rose-400 bg-rose-500/10 hover:bg-rose-500/20'
          : 'text-slate-500 hover:text-rose-400 hover:bg-white/5'
      } ${className}`}
    >
      <Heart size={size} className={`transition-all duration-200 ${starred ? 'fill-rose-400' : 'fill-transparent'}`} />
    </button>
  );
}
