import { useAuth } from '../context/AuthContext';
import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { User, Disc, Play } from 'lucide-react';
import { usePlayer } from '../context/PlayerContext';

export default function ArtistDetails() {
  const { id } = useParams();
  const [artist, setArtist] = useState(null);
  const { user, getAuthParams } = useAuth();
  const [loading, setLoading] = useState(true);
  const { playQueue } = usePlayer();

  useEffect(() => {
    const fetchArtistDetails = async () => {
      try {
        const response = await fetch(`/ampache/public/rest/index.php?action=getArtist&id=${id}&${getAuthParams(user)}`);
        const data = await response.json();
        if (data?.['subsonic-response']?.status === 'ok') {
          setArtist(data['subsonic-response'].artist);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchArtistDetails();
  }, [id]);

  if (loading) return <div className="flex justify-center py-20"><div className="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>;
  if (!artist) return <div className="text-white text-center py-20">Artist not found.</div>;

  return (
    <div className="pb-28 max-w-7xl mx-auto">
      {/* Header */}
      <div className="flex flex-col sm:flex-row gap-5 sm:gap-8 items-center sm:items-end mb-8 mt-2 text-center sm:text-left bg-gradient-to-b from-purple-900/20 to-transparent p-4 sm:p-6 rounded-3xl border border-white/5">
        <div className="w-28 h-28 sm:w-36 sm:h-36 md:w-44 md:h-44 rounded-full overflow-hidden shadow-[0_12px_36px_rgba(0,0,0,0.6)] shrink-0 bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center border-2 border-white/10">
          {artist.coverArt ? (
             <img src={`/ampache/public/rest/index.php?action=getCoverArt&id=${artist.coverArt}&${getAuthParams(user)}`} className="w-full h-full object-cover" alt={artist.name} />
          ) : (
             <User size={48} className="text-white/50" />
          )}
        </div>
        <div className="flex-1 min-w-0">
          <span className="text-[11px] sm:text-xs font-semibold uppercase tracking-wider text-purple-400 block mb-0.5">Artist</span>
          <h1 className="text-2xl sm:text-4xl md:text-5xl font-bold text-white truncate mb-2">{artist.name}</h1>
          <div className="flex items-center justify-center sm:justify-start gap-2 text-slate-300 text-xs sm:text-sm font-medium">
            <span>{artist.albumCount || (artist.album ? artist.album.length : 0)} Albums</span>
          </div>
        </div>
      </div>

      {/* Albums Grid */}
      <h2 className="text-lg sm:text-2xl font-bold text-white mb-4 sm:mb-6 flex items-center gap-2 px-1">
        <Disc size={20} className="text-purple-400 sm:w-6 sm:h-6" /> Albums
      </h2>
      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4 md:gap-6">
        {(artist.album || []).map((album) => (
          <Link 
            to={`/albums/${album.id}`} 
            key={album.id} 
            className="group flex flex-col bg-slate-900/40 hover:bg-slate-800/60 p-2 sm:p-3 rounded-2xl transition-all border border-white/5 hover:border-white/10 backdrop-blur-sm active:scale-[0.98]"
          >
            <div className="relative aspect-square rounded-xl overflow-hidden mb-2.5 shadow-md bg-slate-800">
              <img 
                src={`/ampache/public/rest/index.php?action=getCoverArt&id=${album.coverArt}&${getAuthParams(user)}`} 
                alt={album.name} 
                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                loading="lazy"
              />
              <div className="hidden sm:flex absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity items-center justify-center">
                <button className="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center text-white shadow-lg transform translate-y-3 group-hover:translate-y-0 transition-all duration-200">
                  <Play fill="currentColor" size={16} className="ml-0.5" />
                </button>
              </div>
            </div>
            <div className="px-1 min-w-0">
              <h4 className="font-semibold text-slate-100 truncate group-hover:text-purple-400 transition-colors text-xs sm:text-sm leading-snug">{album.name}</h4>
              <p className="text-[11px] sm:text-xs text-slate-400 truncate mt-0.5">{album.year || 'Unknown Year'}</p>
            </div>
          </Link>
        ))}
      </div>
    </div>
  );
}
