import { useEffect, useState, useCallback } from 'react';
import { Star, Disc, Mic2 } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import { getCoverArtUrl, fetchStarredAlbumsAndArtists, toggleStarredItem, DEFAULT_COVER_ART } from '../utils/api';

/**
 * FavoritesGrid — self-contained widget that fetches the active user's
 * starred albums & artists directly from the Ampache backend (Subsonic
 * getStarred2) and renders a filterable, un-starrable grid.
 */
export default function FavoritesGrid() {
  const [albums, setAlbums] = useState([]);
  const [artists, setArtists] = useState([]);
  const [filter, setFilter] = useState('all'); // 'all' | 'album' | 'artist'
  const [loading, setLoading] = useState(true);

  const { user, getAuthParams } = useAuth();
  const { showToast } = useToast();
  const navigate = useNavigate();

  const load = useCallback(async () => {
    if (!user) return;
    setLoading(true);
    try {
      const { albums: starredAlbums, artists: starredArtists } = await fetchStarredAlbumsAndArtists(user);
      setAlbums(starredAlbums);
      setArtists(starredArtists);
    } catch (err) {
      console.debug('FavoritesGrid fetch error:', err);
    } finally {
      setLoading(false);
    }
  }, [user]);

  useEffect(() => {
    load();
  }, [load]);

  const handleUnstar = async (item, type, e) => {
    e.stopPropagation();
    try {
      const ok = await toggleStarredItem(
        type === 'album' ? { albumId: item.id } : { artistId: item.id },
        true,
        user
      );
      if (ok) {
        if (type === 'album') {
          setAlbums(prev => prev.filter(a => a.id !== item.id));
        } else {
          setArtists(prev => prev.filter(a => a.id !== item.id));
        }
        showToast('💔 Removed from Favorites', 'success');
      } else {
        showToast('Failed to update favorites', 'error');
      }
    } catch (err) {
      showToast('Network error — try again', 'error');
    }
  };

  const items = [
    ...(filter !== 'artist' ? albums.map(a => ({ ...a, __type: 'album' })) : []),
    ...(filter !== 'album' ? artists.map(a => ({ ...a, __type: 'artist' })) : [])
  ];

  return (
    <section aria-label="Favorite Albums & Artists" className="space-y-4 pt-1">
      <div className="flex items-center justify-between px-1 flex-wrap gap-2">
        <div>
          <h3 className="text-lg sm:text-2xl font-bold text-white flex items-center gap-2">
            <Star size={20} className="text-amber-400" fill="currentColor" />
            <span>Favorite Albums &amp; Artists</span>
            <span className="text-xs px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-300 font-medium">
              {albums.length + artists.length}
            </span>
          </h3>
          <p className="text-xs text-slate-400 mt-0.5">Starred items synced from your account on this server</p>
        </div>

        <div className="flex items-center gap-1.5 p-1 rounded-full bg-slate-900/80 border border-white/10 text-xs shrink-0">
          <button
            onClick={() => setFilter('all')}
            className={`px-3 py-1 rounded-full font-semibold transition-all ${filter === 'all' ? 'bg-purple-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'}`}
          >
            All
          </button>
          <button
            onClick={() => setFilter('album')}
            className={`px-3 py-1 rounded-full font-medium transition-all ${filter === 'album' ? 'bg-purple-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'}`}
          >
            Albums
          </button>
          <button
            onClick={() => setFilter('artist')}
            className={`px-3 py-1 rounded-full font-medium transition-all ${filter === 'artist' ? 'bg-purple-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'}`}
          >
            Artists
          </button>
        </div>
      </div>

      {loading ? (
        <div className="text-center py-10 bg-slate-900/30 rounded-2xl border border-white/5 text-xs text-slate-400">
          Loading your favorite albums and artists...
        </div>
      ) : items.length === 0 ? (
        <div className="text-center py-10 bg-slate-900/30 rounded-2xl border border-white/5 text-xs text-slate-400">
          No favorite {filter === 'all' ? 'albums or artists' : filter + 's'} saved yet.
        </div>
      ) : (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3.5 px-1">
          {items.map(item => {
            const isAlbum = item.__type === 'album';
            return (
              <div
                key={`${item.__type}-${item.id}`}
                onClick={() => navigate(isAlbum ? `/albums/${item.id}` : `/artists/${item.id}`)}
                className="group flex flex-col bg-slate-900/40 hover:bg-slate-800/60 p-2.5 sm:p-3 rounded-2xl transition-all border border-white/5 hover:border-amber-500/30 backdrop-blur-sm relative active:scale-[0.98] cursor-pointer"
              >
                <div className="relative aspect-square mb-2.5">
                  <div className={`h-full w-full overflow-hidden shadow-md bg-slate-800 border border-white/5 ${isAlbum ? 'rounded-xl' : 'rounded-full'}`}>
                    <img
                      src={getCoverArtUrl(item.coverArt || item.id, getAuthParams(user))}
                      alt={item.name || item.title}
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                      loading="lazy"
                      decoding="async"
                      onError={(e) => {
                        if (e.currentTarget.src !== DEFAULT_COVER_ART) e.currentTarget.src = DEFAULT_COVER_ART;
                      }}
                    />
                  </div>
                  <button
                    onClick={(e) => handleUnstar(item, item.__type, e)}
                    className="absolute -top-1.5 -right-1.5 z-20 w-7 h-7 rounded-full bg-amber-500 text-white shadow-md shadow-amber-500/30 hover:scale-110 flex items-center justify-center transition-all cursor-pointer"
                    title="Remove from Favorites"
                    aria-label="Remove from Favorites"
                  >
                    <Star size={14} fill="currentColor" />
                  </button>
                  <div className="absolute top-2 left-2 z-10 px-1.5 py-0.5 rounded bg-black/60 backdrop-blur-md border border-white/10 text-[9px] font-bold uppercase tracking-wide text-slate-300 flex items-center gap-1">
                    {isAlbum ? <Disc size={10} /> : <Mic2 size={10} />}
                    <span>{item.__type}</span>
                  </div>
                </div>
                <div className="px-0.5 min-w-0">
                  <h4 className="font-semibold text-slate-100 group-hover:text-amber-300 transition-colors text-xs sm:text-sm truncate leading-snug">
                    {item.name || item.title}
                  </h4>
                  <p className="text-[11px] sm:text-xs text-slate-400 truncate mt-0.5">
                    {isAlbum ? (item.artist || 'Various Artists') : `${item.albumCount || 0} albums`}
                  </p>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </section>
  );
}
