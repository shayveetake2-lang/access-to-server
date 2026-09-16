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
    <div className="space-y-8 pb-24">
      {/* Hero Section */}
      {featured && (
        <div className="relative h-64 md:h-80 rounded-3xl overflow-hidden group cursor-pointer" onClick={() => navigate(`/albums/${featured.id}`)}>
          <div className="absolute inset-0 bg-gradient-to-r from-purple-900/80 to-indigo-900/80 mix-blend-multiply z-10"></div>
          <img 
            src={`/ampache/public/rest/index.php?action=getCoverArt&id=${featured.coverArt}&${getAuthParams(user)}`} 
            alt={featured.name} 
            className="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" 
          />
          <div className="absolute inset-0 z-20 flex flex-col justify-end p-8 bg-gradient-to-t from-slate-950 via-slate-900/40 to-transparent">
            <span className="text-purple-400 font-semibold mb-2 drop-shadow-md">Newest Release</span>
            <h2 className="text-4xl md:text-5xl font-bold text-white mb-2 drop-shadow-md">{featured.name}</h2>
            <h3 className="text-xl text-slate-300 mb-6 drop-shadow-md">{featured.artist}</h3>
            <div className="flex items-center gap-4">
              <button 
                onClick={(e) => { e.stopPropagation(); navigate(`/albums/${featured.id}`); }}
                className="bg-purple-500 hover:bg-purple-400 text-white px-6 py-2.5 rounded-full font-medium flex items-center gap-2 transition-all shadow-[0_0_20px_rgba(168,85,247,0.4)]"
              >
                <Play fill="currentColor" size={18} /> View Album
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Recently Added */}
      <div>
        <h3 className="text-2xl font-bold text-white mb-6">Recently Added</h3>
        {loading ? (
          <div className="flex justify-center py-12"><div className="w-8 h-8 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>
        ) : error ? (
          <div className="text-red-400 bg-red-400/10 p-4 rounded-xl border border-red-400/20">{error}</div>
        ) : (
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 md:gap-6">
            {albums.map((album) => (
              <Link to={`/albums/${album.id}`} key={album.id} className="group flex flex-col bg-slate-800/20 hover:bg-slate-800/40 p-4 rounded-xl transition-all border border-transparent hover:border-white/5 backdrop-blur-sm">
                <div className="relative aspect-square rounded-lg overflow-hidden mb-4 shadow-lg">
                  <img 
                    src={`/ampache/public/rest/index.php?action=getCoverArt&id=${album.coverArt}&${getAuthParams(user)}`} 
                    alt={album.name} 
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                    onError={(e) => { e.target.src = 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=500&auto=format&fit=crop&q=60'; }}
                  />
                  <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                    <button className="w-12 h-12 rounded-full bg-purple-500 flex items-center justify-center text-white shadow-[0_0_20px_rgba(168,85,247,0.5)] transform translate-y-4 group-hover:translate-y-0 transition-all duration-300">
                      <Play fill="currentColor" size={20} className="ml-1" />
                    </button>
                  </div>
                </div>
                <h4 className="font-semibold text-slate-200 truncate group-hover:text-purple-400 transition-colors">{album.name}</h4>
                <p className="text-sm text-slate-400 truncate">{album.artist}</p>
              </Link>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
