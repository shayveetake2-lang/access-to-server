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
    <div className="pb-24">
      {/* Header */}
      <div className="flex flex-col md:flex-row gap-8 items-end mb-12 mt-4">
        <div className="w-32 h-32 md:w-48 md:h-48 rounded-full overflow-hidden shadow-[0_10px_30px_rgba(0,0,0,0.5)] flex-shrink-0 bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
          {artist.coverArt ? (
             <img src={`/ampache/public/rest/index.php?action=getCoverArt&id=${artist.coverArt}&${getAuthParams(user)}`} className="w-full h-full object-cover" alt={artist.name} />
          ) : (
             <User size={64} className="text-white/50" />
          )}
        </div>
        <div className="flex-1">
          <span className="text-sm font-semibold uppercase tracking-wider text-purple-400">Artist</span>
          <h1 className="text-4xl md:text-6xl font-bold text-white mt-2 mb-4">{artist.name}</h1>
          <div className="flex items-center gap-2 text-slate-300">
            <span>{artist.albumCount} Albums</span>
          </div>
        </div>
      </div>

      {/* Albums Grid */}
      <h2 className="text-2xl font-bold text-white mb-6 flex items-center gap-2"><Disc size={24} className="text-purple-400" /> Albums</h2>
      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 md:gap-6">
        {(artist.album || []).map((album) => (
          <Link to={`/albums/${album.id}`} key={album.id} className="group flex flex-col bg-slate-800/20 hover:bg-slate-800/40 p-4 rounded-xl transition-all border border-transparent hover:border-white/5 backdrop-blur-sm">
            <div className="relative aspect-square rounded-lg overflow-hidden mb-4 shadow-lg">
              <img 
                src={`/ampache/public/rest/index.php?action=getCoverArt&id=${album.coverArt}&${getAuthParams(user)}`} 
                alt={album.name} 
                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
              />
              <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                <button className="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center text-white shadow-lg transform translate-y-4 group-hover:translate-y-0 transition-all duration-300">
                  <Play fill="currentColor" size={16} className="ml-1" />
                </button>
              </div>
            </div>
            <h4 className="font-semibold text-slate-200 truncate group-hover:text-purple-400 transition-colors text-sm">{album.name}</h4>
            <p className="text-xs text-slate-400 truncate mt-1">{album.year || 'Unknown Year'}</p>
          </Link>
        ))}
      </div>
    </div>
  );
}
