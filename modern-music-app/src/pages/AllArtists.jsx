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
    <div className="pb-24">
      <h1 className="text-3xl font-bold text-white mb-8">All Artists</h1>
      
      {loading ? (
        <div className="flex justify-center py-12"><div className="w-8 h-8 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>
      ) : (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 md:gap-6">
          {artists.map((artist) => (
            <Link to={`/artists/${artist.id}`} key={artist.id} className="group flex flex-col items-center bg-slate-800/20 hover:bg-slate-800/40 p-6 rounded-xl transition-all border border-transparent hover:border-white/5 backdrop-blur-sm cursor-pointer">
              <div className="w-24 h-24 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 mb-4 flex items-center justify-center shadow-lg group-hover:scale-105 transition-transform overflow-hidden">
                {artist.coverArt ? (
                  <img src={`/ampache/public/rest/index.php?action=getCoverArt&id=${artist.coverArt}&${getAuthParams(user)}`} className="w-full h-full object-cover" alt={artist.name} />
                ) : (
                  <User size={32} className="text-white/50" />
                )}
              </div>
              <h4 className="font-semibold text-slate-200 text-center group-hover:text-purple-400 transition-colors w-full truncate">{artist.name}</h4>
              <p className="text-xs text-slate-400 mt-1">{artist.albumCount} Albums</p>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
