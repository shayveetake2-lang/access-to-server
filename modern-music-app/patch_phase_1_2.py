import re

# 1. Update AllSongs.jsx
with open('src/pages/AllSongs.jsx', 'r') as f:
    content = f.read()

# Remove fetchLibrarySongs from useEffect dependency
content = content.replace(
    '}, [debouncedQuery, sortBy, activeTab, fetchLibrarySongs]);',
    '  libraryOffsetRef.current = 0;\n    fetchLibrarySongs(true);\n  }, [debouncedQuery, sortBy, activeTab]);'
)
content = content.replace(
    '      fetchLibrarySongs(true);\n  libraryOffsetRef.current = 0;',
    '      libraryOffsetRef.current = 0;\n      fetchLibrarySongs(true);'
)
# Make sure we don't duplicate libraryOffsetRef
content = re.sub(r'setHasMoreLibrary\(true\);\s*fetchLibrarySongs\(true\);', r'setHasMoreLibrary(true);\n      libraryOffsetRef.current = 0;\n      fetchLibrarySongs(true);', content)

# Change TTL to 600000
content = content.replace('300000', '600000')

with open('src/pages/AllSongs.jsx', 'w') as f:
    f.write(content)


# 2. Update api.js
with open('src/utils/api.js', 'r') as f:
    content = f.read()

# Change all 300000 to 600000
content = content.replace('300000', '600000')

# Update fetchFeaturedLibrary to add cache and strict filtering
featured_lib_old = """export async function fetchFeaturedLibrary(user = null, albumsPromise = null) {
  const auth = getSubsonicAuthParams(user);
  const cacheBust = `_t=${Date.now()}`;
  const [albumsResult, artistsResult, songsRes, unknownArtistId] = await Promise.all([
    albumsPromise || fetchAllAlbums(user),
    fetchAllArtists(user),
    fetch(getAmpacheUrl(`action=search3&query=%2A&songCount=200&albumCount=0&artistCount=0&${auth}&${cacheBust}`), { cache: 'no-store' }).then(r => r.json()).catch(() => ({})),
    resolveUnknownArtistId(user)
  ]);
  const albums = (albumsResult.albums || []).filter(album => album.hasArt === true || album.hasArt === 1 || album.hasArt === '1');
  const artists = (artistsResult.artists || []).filter(artist => artist.coverArt);
  let songs = songsRes?.['subsonic-response']?.searchResult3?.song || [];
  if (!songs.length) {
    try {
      const fallback = await fetch(getAmpacheUrl(`action=getRandomSongs&size=200&${auth}&${cacheBust}`), { cache: 'no-store' });
      const fallbackData = await fallback.json();
      songs = fallbackData?.['subsonic-response']?.randomSongs?.song || [];
    } catch (error) {
      console.debug('Featured song fallback unavailable:', error);
    }
  }
  return {
    albums: applyUnknownArtistFallback(Array.isArray(albums) ? albums : [albums].filter(Boolean), unknownArtistId),
    artists,
    songs: applyUnknownArtistFallback(
      (Array.isArray(songs) ? songs : [songs].filter(Boolean)).filter(song => song.coverArt),
      unknownArtistId
    )
  };
}"""

featured_lib_new = """export async function fetchFeaturedLibrary(user = null, albumsPromise = null) {
  const cacheKey = 'aether_catalog_featured_songs';
  if (typeof window !== 'undefined' && window.sessionStorage) {
    try {
      const cached = sessionStorage.getItem(cacheKey);
      if (cached) {
        const parsed = JSON.parse(cached);
        if (Date.now() - parsed.timestamp < 600000) {
          return parsed.data;
        }
      }
    } catch (e) {}
  }

  const auth = getSubsonicAuthParams(user);
  const cacheBust = `_t=${Date.now()}`;
  const [albumsResult, artistsResult, songsRes, unknownArtistId] = await Promise.all([
    albumsPromise || fetchAllAlbums(user),
    fetchAllArtists(user),
    fetch(getAmpacheUrl(`action=search3&query=%2A&songCount=200&albumCount=0&artistCount=0&${auth}&${cacheBust}`), { cache: 'no-store' }).then(r => r.json()).catch(() => ({})),
    resolveUnknownArtistId(user)
  ]);

  const isValidArt = (item) => {
    const art = item.coverArt;
    if (!art) return false;
    const strArt = String(art).trim();
    if (strArt === '' || strArt === '0' || strArt === 'unknown') return false;
    if (strArt === DEFAULT_COVER_ART) return false;
    return true;
  };

  const albums = (albumsResult.albums || []).filter(album => isValidArt(album));
  const artists = (artistsResult.artists || []).filter(artist => isValidArt(artist));
  let songs = songsRes?.['subsonic-response']?.searchResult3?.song || [];
  
  if (!songs.length) {
    try {
      const fallback = await fetch(getAmpacheUrl(`action=getRandomSongs&size=200&${auth}&${cacheBust}`), { cache: 'no-store' });
      const fallbackData = await fallback.json();
      songs = fallbackData?.['subsonic-response']?.randomSongs?.song || [];
    } catch (error) {
      console.debug('Featured song fallback unavailable:', error);
    }
  }
  
  const result = {
    albums: applyUnknownArtistFallback(Array.isArray(albums) ? albums : [albums].filter(Boolean), unknownArtistId),
    artists,
    songs: applyUnknownArtistFallback(
      (Array.isArray(songs) ? songs : [songs].filter(Boolean)).filter(song => isValidArt(song)),
      unknownArtistId
    )
  };

  if (typeof window !== 'undefined' && window.sessionStorage) {
    try {
      sessionStorage.setItem(cacheKey, JSON.stringify({ timestamp: Date.now(), data: result }));
    } catch (e) {}
  }
  
  return result;
}"""

content = content.replace(featured_lib_old, featured_lib_new)

recently_added_old = """export async function fetchRecentlyAdded(user = null, limits = { songLimit: 20, albumLimit: 16 }) {
  const cacheBust = `_t=${Date.now()}`;
  // Try proxy first for fastest response
  try {
    const res = await fetch(`${getApiProxyUrl()}?action=getRecentlyAdded&songLimit=${limits.songLimit}&albumLimit=${limits.albumLimit}&${cacheBust}`, { cache: 'no-store' });
    const data = await res.json();
    if (data?.status === 'ok' && (Array.isArray(data.recentAlbums) || Array.isArray(data.recentSongs))) {
      return {
        recentAlbums: data.recentAlbums || [],
        recentSongs: data.recentSongs || []
      };
    }
  } catch (e) {
    console.debug("Proxy getRecentlyAdded fallback:", e);
  }

  // Fallback to native Subsonic API
  const auth = getSubsonicAuthParams(user);
  let recentAlbums = [];
  try {
    const res = await fetch(getAmpacheUrl(`action=getAlbumList2&type=newest&size=${limits.albumLimit}&${auth}&${cacheBust}`), { cache: 'no-store' });
    const data = await res.json();
    if (data?.['subsonic-response']?.status === 'ok') {
      const raw = data['subsonic-response']?.albumList2?.album || data['subsonic-response']?.albumList?.album || [];
      recentAlbums = Array.isArray(raw) ? raw : (raw ? [raw] : []);
    }
  } catch (e) {
    console.debug("Subsonic getAlbumList2 fallback:", e);
  }

  return {
    recentAlbums,
    recentSongs: []
  };
}"""

recently_added_new = """export async function fetchRecentlyAdded(user = null, limits = { songLimit: 20, albumLimit: 16 }) {
  const cacheKey = `aether_recently_added_${limits.songLimit}_${limits.albumLimit}`;
  if (typeof window !== 'undefined' && window.sessionStorage) {
    try {
      const cached = sessionStorage.getItem(cacheKey);
      if (cached) {
        const parsed = JSON.parse(cached);
        if (Date.now() - parsed.timestamp < 600000) {
          return parsed.data;
        }
      }
    } catch (e) {}
  }

  const cacheBust = `_t=${Date.now()}`;
  let result = null;

  try {
    const res = await fetch(`${getApiProxyUrl()}?action=getRecentlyAdded&songLimit=${limits.songLimit}&albumLimit=${limits.albumLimit}&${cacheBust}`, { cache: 'no-store' });
    const data = await res.json();
    if (data?.status === 'ok' && (Array.isArray(data.recentAlbums) || Array.isArray(data.recentSongs))) {
      result = {
        recentAlbums: data.recentAlbums || [],
        recentSongs: data.recentSongs || []
      };
    }
  } catch (e) {
    console.debug("Proxy getRecentlyAdded fallback:", e);
  }

  if (!result) {
    const auth = getSubsonicAuthParams(user);
    let recentAlbums = [];
    try {
      const res = await fetch(getAmpacheUrl(`action=getAlbumList2&type=newest&size=${limits.albumLimit}&${auth}&${cacheBust}`), { cache: 'no-store' });
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        const raw = data['subsonic-response']?.albumList2?.album || data['subsonic-response']?.albumList?.album || [];
        recentAlbums = Array.isArray(raw) ? raw : (raw ? [raw] : []);
      }
    } catch (e) {
      console.debug("Subsonic getAlbumList2 fallback:", e);
    }
    result = { recentAlbums, recentSongs: [] };
  }

  if (typeof window !== 'undefined' && window.sessionStorage) {
    try {
      sessionStorage.setItem(cacheKey, JSON.stringify({ timestamp: Date.now(), data: result }));
    } catch (e) {}
  }

  return result;
}"""

content = content.replace(recently_added_old, recently_added_new)

with open('src/utils/api.js', 'w') as f:
    f.write(content)

# 3. Update AllArtists.jsx for Phase 2 Sorting
with open('src/pages/AllArtists.jsx', 'r') as f:
    content = f.read()

# Add sortBy state
content = content.replace(
    "const [selectedLetter, setSelectedLetter] = useState(() => sessionStorage.getItem('allArtists_letter') || 'All');",
    "const [selectedLetter, setSelectedLetter] = useState(() => sessionStorage.getItem('allArtists_letter') || 'All');\n  const [sortBy, setSortBy] = useState('alphabetical');"
)

# Update dropdown UI
dropdown_ui = """          <div className="bg-slate-900/80 p-1 rounded-xl border border-white/10 flex items-center">
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

          {/* Sort Dropdown */}
          <select
            value={sortBy}
            onChange={(e) => setSortBy(e.target.value)}
            className="bg-slate-900/60 border border-white/10 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500 transition-colors cursor-pointer"
          >
            <option value="alphabetical" className="bg-slate-900 text-white">Alphabetical</option>
            <option value="albums" className="bg-slate-900 text-white">Most Albums</option>
            <option value="songs" className="bg-slate-900 text-white">Most Songs</option>
            <option value="plays" className="bg-slate-900 text-white">Most Played</option>
          </select>"""

content = content.replace("""          <div className="bg-slate-900/80 p-1 rounded-xl border border-white/10 flex items-center">
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
          </div>""", dropdown_ui)

# Apply filter logic
filter_old = """  const filteredArtists = useMemo(() => {
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
  }, [canonicalArtists, searchQuery, activeTab, selectedLetter, selectedGenre]);"""

filter_new = """  const filteredArtists = useMemo(() => {
    let result = canonicalArtists.filter(artist => {
      if (searchQuery.trim()) {
        const query = searchQuery.toLowerCase();
        const matchesName = artist.name.toLowerCase().includes(query);
        const matchesAliases = (artist.aliasNames || []).some(alias => alias.toLowerCase().includes(query));
        const matchesGenre = (artist.genres || []).some(g => g.toLowerCase().includes(query));
        if (!matchesName && !matchesAliases && !matchesGenre) return false;
      }

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

    if (sortBy !== 'alphabetical') {
      const sorted = [...result];
      if (sortBy === 'albums') {
        sorted.sort((a, b) => (b.albumCount || 0) - (a.albumCount || 0));
      } else if (sortBy === 'songs') {
        sorted.sort((a, b) => (b.songCount || b.trackCount || 0) - (a.songCount || a.trackCount || 0));
      } else if (sortBy === 'plays') {
        sorted.sort((a, b) => (b.playCount || 0) - (a.playCount || 0));
      }
      return sorted;
    }

    return result;
  }, [canonicalArtists, searchQuery, activeTab, selectedLetter, selectedGenre, sortBy]);"""

content = content.replace(filter_old, filter_new)

group_old = """  const groupedByName = useMemo(() => {
    if (activeTab !== 'name' || selectedLetter !== 'All' || searchQuery.trim()) {
      return null; // Don't show nested sections if filtered or in genre mode
    }"""

group_new = """  const groupedByName = useMemo(() => {
    if (activeTab !== 'name' || selectedLetter !== 'All' || searchQuery.trim() || sortBy !== 'alphabetical') {
      return null; // Don't show nested sections if filtered, in genre mode, or custom sorted
    }"""

content = content.replace(group_old, group_new)

with open('src/pages/AllArtists.jsx', 'w') as f:
    f.write(content)

