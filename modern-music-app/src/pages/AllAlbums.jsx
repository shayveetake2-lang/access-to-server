import { useAuth } from '../context/AuthContext';
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Play } from 'lucide-react';

export default function AllAlbums() {
  const [albums, setAlbums] = useState([]);
  const { user, getAuthParams } = useAuth();
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchAlbums = async () => {
      try {
        const response = await fetch(`/ampache/public/rest/index.php?action=getAlbumList&type=alphabeticalByArtist&size=500&${getAuthParams(user)}`);
        const data = await response.json();
        if (data?.['subsonic-response']?.status === 'ok') {
          setAlbums(data['subsonic-response'].albumList.album || []);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchAlbums();
  }, []);

  return (
    <div className="pb-28 max-w-7xl mx-auto">
      <div className="flex items-center justify-between mb-5 sm:mb-8 px-1">
        <h1 className="text-xl sm:text-3xl font-bold text-white">All Albums</h1>
        <span className="text-xs text-slate-400 font-medium">{albums.length} albums</span>
      </div>
      
      {loading ? (
        <div className="flex justify-center py-16"><div className="w-8 h-8 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>
      ) : (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4 md:gap-6">
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
                  <button className="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center text-white shadow-lg transform translate-y-3 group-hover:translate-y-0 transition-all duration-200">
                    <Play fill="currentColor" size={16} className="ml-0.5" />
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
  );
}
