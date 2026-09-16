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
    <div className="pb-24">
      <h1 className="text-3xl font-bold text-white mb-8">All Albums</h1>
      
      {loading ? (
        <div className="flex justify-center py-12"><div className="w-8 h-8 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>
      ) : (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 md:gap-6">
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
                  <button className="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center text-white shadow-lg transform translate-y-4 group-hover:translate-y-0 transition-all duration-300">
                    <Play fill="currentColor" size={16} className="ml-1" />
                  </button>
                </div>
              </div>
              <h4 className="font-semibold text-slate-200 truncate group-hover:text-purple-400 transition-colors text-sm">{album.name}</h4>
              <p className="text-xs text-slate-400 truncate mt-1">{album.artist}</p>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
