import { useAuth } from '../context/AuthContext';
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { User } from 'lucide-react';

export default function AllArtists() {
  const [artists, setArtists] = useState([]);
  const { user, getAuthParams } = useAuth();
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchArtists = async () => {
      try {
        const response = await fetch(`/ampache/public/rest/index.php?action=getArtists&${getAuthParams(user)}`);
        const data = await response.json();
        if (data?.['subsonic-response']?.status === 'ok') {
          const index = data['subsonic-response'].artists.index || [];
          let allArtists = [];
          index.forEach(idx => {
            if (idx.artist) allArtists = [...allArtists, ...idx.artist];
          });
          setArtists(allArtists);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchArtists();
  }, []);

  return (
    <div className="pb-28 max-w-7xl mx-auto">
      <div className="flex items-center justify-between mb-5 sm:mb-8 px-1">
        <h1 className="text-xl sm:text-3xl font-bold text-white">All Artists</h1>
        <span className="text-xs text-slate-400 font-medium">{artists.length} artists</span>
      </div>
      
      {loading ? (
        <div className="flex justify-center py-16"><div className="w-8 h-8 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>
      ) : (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 sm:gap-4 md:gap-6">
          {artists.map((artist) => (
            <Link 
              to={`/artists/${artist.id}`} 
              key={artist.id} 
              className="group flex flex-col items-center bg-slate-900/40 hover:bg-slate-800/60 p-3 sm:p-5 rounded-2xl transition-all border border-white/5 hover:border-white/10 backdrop-blur-sm cursor-pointer active:scale-[0.98]"
            >
              <div className="w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 mb-3 flex items-center justify-center shadow-lg group-hover:scale-105 transition-transform overflow-hidden border border-white/10">
                {artist.coverArt ? (
                  <img 
                    src={`/ampache/public/rest/index.php?action=getCoverArt&id=${artist.coverArt}&${getAuthParams(user)}`} 
                    className="w-full h-full object-cover" 
                    alt={artist.name} 
                    loading="lazy"
                  />
                ) : (
                  <User size={28} className="text-white/50" />
                )}
              </div>
              <h4 className="font-semibold text-slate-100 text-center group-hover:text-purple-400 transition-colors w-full truncate text-xs sm:text-sm">{artist.name}</h4>
              <p className="text-[11px] sm:text-xs text-slate-400 mt-0.5">{artist.albumCount} Albums</p>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
