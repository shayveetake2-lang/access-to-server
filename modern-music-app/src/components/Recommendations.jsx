import { useState, useEffect } from 'react';
import { Play, Sparkles } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { usePlayer } from '../context/PlayerContext';
import { getAmpacheUrl, getCoverArtUrl, DEFAULT_COVER_ART } from '../utils/api';
import { Link } from 'react-router-dom';

export default function Recommendations() {
  const { user, getAuthParams } = useAuth();
  const { playQueue } = usePlayer();
  const [recommendations, setRecommendations] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadRecommendations() {
      if (!user) return;
      
      const CACHE_KEY = `aether_recs_${user.username}`;
      const CACHE_EXPIRY = 1000 * 60 * 60 * 12; // 12 hours
      
      const cached = sessionStorage.getItem(CACHE_KEY);
      if (cached) {
        try {
          const parsed = JSON.parse(cached);
          if (Date.now() - parsed.timestamp < CACHE_EXPIRY) {
            setRecommendations(parsed.data);
            setLoading(false);
            return;
          }
        } catch (e) {}
      }

      setLoading(true);
      try {
        const auth = getAuthParams(user);
        
        // 1. Fetch user's top/frequent albums to determine favorite genres
        const freqRes = await fetch(getAmpacheUrl(`action=getAlbumList2&type=frequent&size=15&${auth}`));
        const freqData = await freqRes.json();
        const topAlbums = freqData?.['subsonic-response']?.albumList2?.album || [];
        const albums = Array.isArray(topAlbums) ? topAlbums : (topAlbums ? [topAlbums] : []);
        
        let targetGenre = '';
        if (albums.length > 0) {
          const genres = albums.map(a => a.genre).filter(Boolean);
          if (genres.length > 0) {
            // Find most frequent genre
            const counts = genres.reduce((acc, g) => ({ ...acc, [g]: (acc[g] || 0) + 1 }), {});
            targetGenre = Object.keys(counts).reduce((a, b) => counts[a] > counts[b] ? a : b);
          }
        }
        
        // 2. Fetch random songs of that genre as "unplayed/discovery" recommendations
        const query = targetGenre ? `&genre=${encodeURIComponent(targetGenre)}` : '';
        const recRes = await fetch(getAmpacheUrl(`action=getRandomSongs&size=12${query}&${auth}`));
        const recData = await recRes.json();
        const rawSongs = recData?.['subsonic-response']?.randomSongs?.song || [];
        const recommendedSongs = Array.isArray(rawSongs) ? rawSongs : (rawSongs ? [rawSongs] : []);
        
        setRecommendations(recommendedSongs);
        sessionStorage.setItem(CACHE_KEY, JSON.stringify({
          timestamp: Date.now(),
          data: recommendedSongs
        }));
      } catch (err) {
        console.error("Failed to fetch recommendations:", err);
      } finally {
        setLoading(false);
      }
    }
    loadRecommendations();
  }, [user, getAuthParams]);

  if (loading) {
    return (
      <section className="mb-10 animate-pulse">
        <div className="h-8 w-48 bg-slate-800 rounded-lg mb-4"></div>
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
          {[1,2,3,4,5,6].map(i => (
            <div key={i} className="aspect-square bg-slate-800 rounded-xl"></div>
          ))}
        </div>
      </section>
    );
  }

  if (recommendations.length === 0) return null;

  return (
    <section className="mb-10">
      <div className="flex items-center gap-2 mb-4">
        <Sparkles className="text-purple-400" size={24} />
        <h2 className="text-xl font-bold text-white tracking-tight">Recommended For You</h2>
      </div>
      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        {recommendations.slice(0, 6).map(song => (
          <div key={song.id} className="group relative bg-slate-900/40 hover:bg-slate-800/80 p-3 rounded-2xl transition-all cursor-pointer">
            <div className="aspect-square w-full rounded-xl overflow-hidden mb-3 relative">
              <img 
                src={song.coverArt ? getCoverArtUrl(song.coverArt, getAuthParams(user)) : DEFAULT_COVER_ART}
                className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                alt={song.title}
                onError={(e) => { e.currentTarget.src = DEFAULT_COVER_ART; }}
              />
              <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                <button 
                  onClick={(e) => { e.preventDefault(); e.stopPropagation(); playQueue([song], 0); }}
                  className="w-12 h-12 rounded-full bg-purple-500 hover:bg-purple-400 text-white flex items-center justify-center shadow-xl hover:scale-105 transition-transform"
                >
                  <Play size={24} className="ml-1" fill="currentColor" />
                </button>
              </div>
            </div>
            <h3 className="font-semibold text-sm text-white truncate">{song.title}</h3>
            <Link to={`/artists/${song.artistId}`} className="text-xs text-slate-400 hover:text-purple-300 truncate block mt-0.5" onClick={e => e.stopPropagation()}>
              {song.artist}
            </Link>
          </div>
        ))}
      </div>
    </section>
  );
}

