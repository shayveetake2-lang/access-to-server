import { useAuth } from '../context/AuthContext';
import { useEffect, useState } from 'react';
import { Play } from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';

export default function Dashboard() {
  const [albums, setAlbums] = useState([]);
  const { user, getAuthParams } = useAuth();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const navigate = useNavigate();

  useEffect(() => {
    const fetchAlbums = async () => {
      try {
        const response = await fetch(`/ampache/public/rest/index.php?action=getAlbumList&type=newest&size=10&${getAuthParams(user)}`);
        const data = await response.json();
        
        if (data?.['subsonic-response']?.status === 'ok') {
          setAlbums(data['subsonic-response'].albumList.album || []);
        } else {
          throw new Error('API returned an error');
        }
      } catch (err) {
        console.error("Failed to fetch albums:", err);
        setError("Could not load recently added albums.");
      } finally {
        setLoading(false);
      }
    };

    fetchAlbums();
  }, []);

  const featured = albums.length > 0 ? albums[0] : null;

  return (
    <div className="space-y-6 sm:space-y-8 pb-28 max-w-7xl mx-auto">
      {/* Hero Section */}
      {featured && (
        <div 
          className="relative h-52 sm:h-64 md:h-80 rounded-3xl overflow-hidden group cursor-pointer shadow-xl border border-white/5 active:scale-[0.99] transition-transform" 
          onClick={() => navigate(`/albums/${featured.id}`)}
        >
          <div className="absolute inset-0 bg-gradient-to-r from-purple-950/90 via-indigo-950/70 to-transparent z-10"></div>
          <img 
            src={`/ampache/public/rest/index.php?action=getCoverArt&id=${featured.coverArt}&${getAuthParams(user)}`} 
            alt={featured.name} 
            className="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" 
          />
          <div className="absolute inset-0 z-20 flex flex-col justify-end p-5 sm:p-8 bg-gradient-to-t from-slate-950 via-slate-950/50 to-transparent">
            <span className="text-purple-400 font-semibold text-xs sm:text-sm mb-1 drop-shadow-md uppercase tracking-wider">Newest Release</span>
            <h2 className="text-2xl sm:text-4xl md:text-5xl font-bold text-white mb-1 drop-shadow-md truncate">{featured.name}</h2>
            <h3 className="text-sm sm:text-xl text-slate-300 mb-4 drop-shadow-md truncate">{featured.artist}</h3>
            <div className="flex items-center gap-3">
              <button 
                onClick={(e) => { e.stopPropagation(); navigate(`/albums/${featured.id}`); }}
                className="bg-purple-500 hover:bg-purple-400 text-white px-5 py-2 sm:px-6 sm:py-2.5 rounded-full font-medium text-xs sm:text-sm flex items-center gap-2 transition-all shadow-[0_0_20px_rgba(168,85,247,0.4)] active:scale-95"
              >
                <Play fill="currentColor" size={16} /> View Album
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Recently Added */}
      <div>
        <div className="flex items-center justify-between mb-4 sm:mb-6 px-1">
          <h3 className="text-lg sm:text-2xl font-bold text-white">Recently Added</h3>
          <Link to="/albums" className="text-xs font-semibold text-purple-400 hover:text-purple-300 transition-colors">
            See all →
          </Link>
        </div>

        {loading ? (
          <div className="flex justify-center py-16"><div className="w-8 h-8 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>
        ) : error ? (
          <div className="text-red-400 bg-red-400/10 p-4 rounded-xl border border-red-400/20">{error}</div>
        ) : (
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4 md:gap-6">
            {albums.map((album) => (
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
                    onError={(e) => { e.target.src = 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=500&auto=format&fit=crop&q=60'; }}
                  />
                  <div className="hidden sm:flex absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity items-center justify-center">
                    <button className="w-11 h-11 rounded-full bg-purple-500 flex items-center justify-center text-white shadow-lg transform translate-y-3 group-hover:translate-y-0 transition-all duration-200">
                      <Play fill="currentColor" size={18} className="ml-0.5" />
                    </button>
                  </div>
                </div>
                <div className="px-1 min-w-0">
                  <h4 className="font-semibold text-slate-100 truncate group-hover:text-purple-400 transition-colors text-xs sm:text-sm leading-snug">{album.name}</h4>
                  <p className="text-[11px] sm:text-xs text-slate-400 truncate mt-0.5">{album.artist}</p>
                </div>
              </Link>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
