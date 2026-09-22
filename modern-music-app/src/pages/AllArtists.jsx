import { useAuth } from '../context/AuthContext';
import { useEffect, useState, useMemo, useLayoutEffect } from 'react';
import { Link } from 'react-router-dom';
import { Search, Tag, Users, Sparkles } from 'lucide-react';
import { mergeArtists } from '../utils/artistHelper';
import { getAmpacheUrl } from '../utils/api';
import { ArtistGrid } from '../components/ArtistGrid';

export default function AllArtists() {
  const [rawArtists, setRawArtists] = useState([]);
  const [rawAlbums, setRawAlbums] = useState([]);
  const { user, getAuthParams } = useAuth();
  const [loading, setLoading] = useState(true);
  const isAdmin = user?.role === 'admin' || user?.isAdmin === true || ['admin', 'musicadmin'].includes(user?.username?.toLowerCase());

  // Grouping and filtering state
  const [activeTab, setActiveTab] = useState('name'); // 'name' | 'genre'
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedLetter, setSelectedLetter] = useState(() => sessionStorage.getItem('allArtists_letter') || 'All');

  useEffect(() => {
    sessionStorage.setItem('allArtists_letter', selectedLetter);
  }, [selectedLetter]);

  useLayoutEffect(() => {
    const savedScroll = sessionStorage.getItem('allArtists_scroll');
    if (savedScroll) {
      window.scrollTo(0, parseInt(savedScroll, 10));
    }
    const handleScroll = () => {
      sessionStorage.setItem('allArtists_scroll', window.scrollY.toString());
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);
  const [selectedGenre, setSelectedGenre] = useState('All');

  useEffect(() => {
    let isMounted = true;
    const fetchData = async () => {
      try {
        const auth = getAuthParams(user);
        // Fetch artists and albums in parallel to extract genres and album associations
        const [artistsRes, albumsRes] = await Promise.all([
          fetch(getAmpacheUrl(`action=getArtists&${auth}`)).then(r => r.json()).catch(() => null),
          fetch(getAmpacheUrl(`action=getAlbumList&type=alphabeticalByArtist&size=500&${auth}`)).then(r => r.json()).catch(() => null)
        ]);

        if (isMounted) {
          if (artistsRes?.['subsonic-response']?.status === 'ok') {
            const index = artistsRes['subsonic-response'].artists?.index || [];
            let all = [];
            index.forEach(idx => {
              if (idx.artist) {
                const artList = Array.isArray(idx.artist) ? idx.artist : [idx.artist];
                all = [...all, ...artList];
              }
            });
            setRawArtists(all);
          }

          if (albumsRes?.['subsonic-response']?.status === 'ok') {
            const albumList = albumsRes['subsonic-response'].albumList?.album || [];
            setRawAlbums(Array.isArray(albumList) ? albumList : (albumList ? [albumList] : []));
          }
        }
      } catch (err) {
        console.error("Failed to load artists:", err);
      } finally {
        if (isMounted) setLoading(false);
      }
    };

    fetchData();
    return () => {
      isMounted = false;
    };
  }, [user]);

  // Merge similar & featured artists into canonical profiles
  const canonicalArtists = useMemo(() => {
    return mergeArtists(rawArtists, rawAlbums);
  }, [rawArtists, rawAlbums]);

  // Extract all unique genres present across all artists
  const allGenres = useMemo(() => {
    const genreCounts = new Map();
    canonicalArtists.forEach(artist => {
      (artist.genres || []).forEach(genre => {
        const clean = genre.trim();
        if (clean) {
          genreCounts.set(clean, (genreCounts.get(clean) || 0) + 1);
        }
      });
    });

    // Sort by popularity (count descending)
    const sorted = Array.from(genreCounts.entries())
      .sort((a, b) => b[1] - a[1])
      .map(([genre]) => genre);

    return ['All', ...sorted];
  }, [canonicalArtists]);

  // Available letters for the A-Z jump bar
  const alphabet = useMemo(() => {
    const letters = new Set();
    canonicalArtists.forEach(a => {
      const first = a.name.trim().charAt(0).toUpperCase();
      if (/[A-Z]/.test(first)) {
        letters.add(first);
      } else {
        letters.add('#');
      }
    });
    return ['All', '#', ...'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('')];
  }, [canonicalArtists]);

  // Filtered artists based on search, tab, letter, and genre
  const filteredArtists = useMemo(() => {
    return canonicalArtists.filter(artist => {
      // Search filter
      if (searchQuery.trim()) {
        const query = searchQuery.toLowerCase();
        const matchesName = artist.name.toLowerCase().includes(query);
        const matchesAliases = (artist.aliasNames || []).some(alias => alias.toLowerCase().includes(query));
        const matchesGenre = (artist.genres || []).some(g => g.toLowerCase().includes(query));
        if (!matchesName && !matchesAliases && !matchesGenre) return false;
      }

      // Tab-specific filters
      if (activeTab === 'name') {
        if (selectedLetter !== 'All') {
          const first = artist.name.trim().charAt(0).toUpperCase();
          if (selectedLetter === '#') {
            if (/[A-Z]/.test(first)) return false;
          } else if (first !== selectedLetter) {
            return false;
          }
        }
      } else if (activeTab === 'genre') {
        if (selectedGenre !== 'All') {
          if (!artist.genres || !artist.genres.includes(selectedGenre)) return false;
        }
      }

      return true;
    });
  }, [canonicalArtists, searchQuery, activeTab, selectedLetter, selectedGenre]);

  // Grouped by letter for the "By Name" view
  const groupedByName = useMemo(() => {
    if (activeTab !== 'name' || selectedLetter !== 'All' || searchQuery.trim()) {
      return null; // Don't show nested sections if filtered or in genre mode
    }

    const groups = new Map();
    filteredArtists.forEach(artist => {
      const first = artist.name.trim().charAt(0).toUpperCase();
      const key = /[A-Z]/.test(first) ? first : '#';
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push(artist);
    });

    return Array.from(groups.entries()).sort((a, b) => {
      if (a[0] === '#') return 1;
      if (b[0] === '#') return -1;
      return a[0].localeCompare(b[0]);
    });
  }, [filteredArtists, activeTab, selectedLetter, searchQuery]);

  return (
    <div className="pb-28 max-w-7xl mx-auto space-y-6">
      {/* Header & Main Controls */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 px-1 pt-1">
        <div>
          <h1 className="text-2xl sm:text-3xl font-bold text-white flex items-center gap-2.5">
            <Users className="text-purple-400" size={28} />
            All Artists
          </h1>
          <p className="text-xs sm:text-sm text-slate-400 mt-1">
            {canonicalArtists.length} {canonicalArtists.length === 1 ? 'artist' : 'artists'}
            {rawArtists.length > canonicalArtists.length && (
              <span className="ml-2 inline-flex items-center gap-1 text-[11px] text-purple-400 font-medium bg-purple-500/10 px-2 py-0.5 rounded-full border border-purple-500/20">
                <Sparkles size={11} /> Merged {rawArtists.length - canonicalArtists.length} featured appearances
              </span>
            )}
          </p>
        </div>

        {/* View Mode Toggle & Admin Shortcuts */}
        <div className="flex flex-wrap items-center gap-2">
          {isAdmin && (
            <Link
              to="/admin"
              className="px-3.5 py-1.5 rounded-xl bg-purple-500/15 hover:bg-purple-500/25 text-purple-300 hover:text-white border border-purple-500/30 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-sm active:scale-95"
              title="Clean and merge similar artist profiles"
            >
              <Sparkles size={13} className="text-purple-400" />
              <span>Merge Similar Artists</span>
            </Link>
          )}

          <div className="bg-slate-900/80 p-1 rounded-xl border border-white/10 flex items-center">
            <button
              onClick={() => { setActiveTab('name'); setSelectedGenre('All'); }}
              className={`px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all ${
                activeTab === 'name' 
                  ? 'bg-purple-500 text-white shadow-md font-semibold' 
                  : 'text-slate-400 hover:text-white'
              }`}
            >
              By Name
            </button>
            <button
              onClick={() => { setActiveTab('genre'); setSelectedLetter('All'); }}
              className={`px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all ${
                activeTab === 'genre' 
                  ? 'bg-purple-500 text-white shadow-md font-semibold' 
                  : 'text-slate-400 hover:text-white'
              }`}
            >
              By Genre
            </button>
          </div>

          {/* Search Box */}
          <div className="relative flex-1 sm:w-56">
            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              placeholder="Search artists..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full bg-slate-900/60 border border-white/10 rounded-xl pl-8 pr-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 transition-colors"
            />
          </div>
        </div>
      </div>

      {/* Sub-Navigation: Alphabet Jump Bar (Name Tab) or Genre Chips (Genre Tab) */}
      {activeTab === 'name' && (
        <div className="flex items-center gap-1 overflow-x-auto pb-1 touch-scroll scrollbar-none px-1">
          {alphabet.map(letter => {
            const isSelected = selectedLetter === letter;
            return (
              <button
                key={letter}
                onClick={() => setSelectedLetter(letter)}
                className={`min-w-[28px] h-7 px-2 flex items-center justify-center rounded-lg text-xs font-semibold transition-all shrink-0 ${
                  isSelected
                    ? 'bg-purple-500 text-white shadow-[0_0_10px_rgba(168,85,247,0.4)] scale-105'
                    : 'text-slate-400 hover:text-white hover:bg-white/5 active:scale-95'
                }`}
              >
                {letter}
              </button>
            );
          })}
        </div>
      )}

      {activeTab === 'genre' && (
        <div className="flex items-center gap-2 overflow-x-auto pb-1 touch-scroll scrollbar-none px-1">
          {allGenres.map(genre => {
            const isSelected = selectedGenre === genre;
            return (
              <button
                key={genre}
                onClick={() => setSelectedGenre(genre)}
                className={`px-3 py-1.5 rounded-full text-xs font-medium transition-all shrink-0 flex items-center gap-1.5 border ${
                  isSelected
                    ? 'bg-purple-500 text-white border-purple-400 shadow-[0_0_12px_rgba(168,85,247,0.4)] font-semibold'
                    : 'bg-slate-900/40 text-slate-400 border-white/5 hover:border-white/20 hover:text-white active:scale-95'
                }`}
              >
                <Tag size={12} className={isSelected ? 'text-white' : 'text-purple-400'} />
                {genre}
              </button>
            );
          })}
        </div>
      )}

      {/* Artists Grid / Grouped Sections */}
      {loading ? (
        <div className="flex justify-center py-20">
          <div className="w-8 h-8 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
      ) : filteredArtists.length === 0 ? (
        <div className="text-center py-16 bg-slate-900/20 rounded-3xl border border-white/5">
          <p className="text-slate-400 text-sm">No artists found matching your filter.</p>
          <button
            onClick={() => { setSearchQuery(''); setSelectedLetter('All'); setSelectedGenre('All'); }}
            className="mt-3 text-xs text-purple-400 hover:text-purple-300 font-medium"
          >
            Reset filters
          </button>
        </div>
      ) : groupedByName ? (
        // Alphabetically sectioned view
        <div className="space-y-8">
          {groupedByName.map(([letter, artistsInGroup]) => (
            <div key={letter} className="space-y-3">
              <div className="flex items-center gap-3 border-b border-white/10 pb-2 px-1">
                <span className="text-lg font-bold text-purple-400">{letter}</span>
                <span className="text-xs text-slate-400 font-mono">({artistsInGroup.length})</span>
              </div>
              <ArtistGrid artists={artistsInGroup} user={user} getAuthParams={getAuthParams} />
            </div>
          ))}
        </div>
      ) : (
        // Flat filtered grid
        <ArtistGrid artists={filteredArtists} user={user} getAuthParams={getAuthParams} />
      )}
    </div>
  );
}
