import md5 from './md5';
import { dedupeSongs } from './dedupeSongs';

// modern-music-app/src/utils/api.js — Environment-aware API & Asset Path Resolver

export function getBaseUrl() {
  if (typeof window !== 'undefined' && window.__SF_BASE__) {
    return window.__SF_BASE__;
  }
  if (typeof window !== 'undefined' && window.location.pathname.includes('/access-to-server')) {
    return '/access-to-server';
  }
  return '';
}

export function getAmpacheUrl(queryString = '') {
  const qs = queryString.startsWith('&') || queryString.startsWith('?') ? queryString : (queryString ? `?${queryString}` : '');
  return `${getBaseUrl()}/ampache/public/rest/index.php${qs}`;
}

// Premium Dark Violet / Indigo Vinyl Artwork Placeholder (Self-contained Data URI SVG)
export const DEFAULT_COVER_ART = 'data:image/svg+xml;utf8,' + encodeURIComponent(`
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300" width="300" height="300">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#1e1b4b"/>
      <stop offset="50%" stop-color="#090d16"/>
      <stop offset="100%" stop-color="#2e1065"/>
    </linearGradient>
    <linearGradient id="disc" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#1e293b"/>
      <stop offset="100%" stop-color="#0f172a"/>
    </linearGradient>
    <radialGradient id="grooves" cx="50%" cy="50%" r="50%">
      <stop offset="42%" stop-color="transparent"/>
      <stop offset="43%" stop-color="rgba(255,255,255,0.06)"/>
      <stop offset="44%" stop-color="transparent"/>
      <stop offset="62%" stop-color="transparent"/>
      <stop offset="63%" stop-color="rgba(255,255,255,0.05)"/>
      <stop offset="64%" stop-color="transparent"/>
      <stop offset="82%" stop-color="transparent"/>
      <stop offset="83%" stop-color="rgba(255,255,255,0.05)"/>
      <stop offset="84%" stop-color="transparent"/>
    </radialGradient>
  </defs>
  <rect width="300" height="300" rx="16" fill="url(#bg)"/>
  <circle cx="150" cy="150" r="105" fill="url(#disc)" stroke="rgba(255,255,255,0.12)" stroke-width="2"/>
  <circle cx="150" cy="150" r="105" fill="url(#grooves)"/>
  <circle cx="150" cy="150" r="38" fill="#7c3aed" opacity="0.9"/>
  <circle cx="150" cy="150" r="12" fill="#090d16"/>
  <path d="M145 136 L145 156 A7 7 0 1 0 152 163 L152 143 L162 146 L162 138 Z" fill="#ffffff"/>
</svg>
`);

// In-memory cache for Subsonic auth tokens (prevents rapid cache-busting on every component re-render)
let _cachedAuthParams = null;
let _cachedAuthUserKey = '';
let _cachedAuthTimestamp = 0;

// CRITICAL FIX: Static module-level salt generated strictly once per session.
// This prevents infinite render loops and cache-busting on every component mount.
const _staticSessionSalt = Math.random().toString(36).substring(2, 12);

/**
 * Dynamically resolves active user credentials from argument, localStorage, or sessionStorage,
 * and generates standard Subsonic REST API token auth parameters: u, t, s, v=1.16.1, c=Aether, f=json.
 * Memoizes token/salt for 15 minutes per user session so browser image caching remains active.
 */
export function getSubsonicAuthParams(user = null, forceNew = false) {
  let credentials = user;

  // Aggressive local storage fetch if context is not yet populated
  if ((!credentials || !credentials.username) && typeof window !== 'undefined') {
    const rawStored = localStorage.getItem('ampache_user') || sessionStorage.getItem('ampache_user');
    if (rawStored) {
      try {
        credentials = JSON.parse(rawStored);
      } catch (e) {
        credentials = null;
      }
    }
  }

  if (!credentials || !credentials.username) {
    return 'v=1.16.1&c=Aether&f=json';
  }

  const userKey = `${credentials.username}:${credentials.token || credentials.password || ''}`;
  
  // CRITICAL CACHING FIX: Remove 15 minute expiry to ensure completely stable auth payload 
  // for the session. This guarantees native browser image caching for 10k artwork files.
  if (!forceNew && _cachedAuthParams && _cachedAuthUserKey === userKey) {
    return _cachedAuthParams;
  }

  let params = '';

  // Phase 1: Secure Pre-Computed Subsonic Hash (No Plaintext Password Required)
  if (credentials.subsonic_token && credentials.subsonic_salt) {
    params = `u=${encodeURIComponent(credentials.username)}&t=${credentials.subsonic_token}&s=${credentials.subsonic_salt}&v=1.16.1&c=Aether&f=json`;
  } 
  // Phase 2: Session Bearer Token Fallback (If subsonic hash generation failed backend-side)
  else if (credentials.token) {
    params = `u=${encodeURIComponent(credentials.username)}&p=${encodeURIComponent(credentials.token)}&v=1.16.1&c=Aether&f=json`;
  } 
  // Phase 3: Legacy random salt and MD5 token (requires plaintext password)
  else if (credentials.password) {
    let salt = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('aether_salt') : null;
    if (!salt) {
      salt = _staticSessionSalt;
      if (typeof sessionStorage !== 'undefined') sessionStorage.setItem('aether_salt', salt);
    }
    const token = md5(credentials.password + salt);
    const hexPass = Array.from(credentials.password).map(c => c.charCodeAt(0).toString(16).padStart(2, '0')).join('');
    params = `u=${encodeURIComponent(credentials.username)}&t=${token}&s=${salt}&p=enc:${hexPass}&v=1.16.1&c=Aether&f=json`;
  } 
  // Unauthenticated safe fallback
  else {
    params = `u=${encodeURIComponent(credentials.username)}&v=1.16.1&c=Aether&f=json`;
  }

  _cachedAuthParams = params;
  _cachedAuthUserKey = userKey;
  _cachedAuthTimestamp = Date.now();

  return params;
}

export function getCoverArtUrl(coverArtId) {
  if (!coverArtId || String(coverArtId).trim() === '' || String(coverArtId) === '0' || String(coverArtId) === 'unknown') {
    return DEFAULT_COVER_ART;
  }
  
  const raw = String(coverArtId).trim();
  let normalizedId = raw;

  if (raw.startsWith('al-')) {
    const num = parseInt(raw.slice(3), 10);
    if (!isNaN(num)) normalizedId = num < 100000000 ? String(200000000 + num) : String(num);
  } else if (raw.startsWith('ar-')) {
    const num = parseInt(raw.slice(3), 10);
    if (!isNaN(num)) normalizedId = num < 100000000 ? String(100000000 + num) : String(num);
  } else if (raw.startsWith('sg-')) {
    const num = parseInt(raw.slice(3), 10);
    if (!isNaN(num)) normalizedId = num < 100000000 ? String(600000000 + num) : String(num);
  } else {
    const num = parseInt(raw, 10);
    if (!isNaN(num)) normalizedId = num < 100000000 ? String(200000000 + num) : String(num);
  }

  return `${getApiProxyUrl()}?action=getCoverArt&id=${encodeURIComponent(normalizedId)}`;
}

export function getStreamUrl(trackId) {
  if (!trackId) return '';
  
  const raw = String(trackId).trim();
  let normalizedId = raw;
  if (raw.startsWith('sg-')) {
    normalizedId = raw.slice(3);
  }
  
  const num = parseInt(normalizedId, 10);
  if (!isNaN(num)) {
    normalizedId = num < 100000000 ? String(600000000 + num) : (num >= 600000000 && num < 400000000 ? String(num) : String(600000000 + (num % 100000000)));
  }

  return `${getApiProxyUrl()}?action=stream&id=${encodeURIComponent(normalizedId)}`;
}

function getSearchVariants(query) {
  const words = query.toLowerCase().split(/\s+/).filter(word => word.length >= 2);
  const variants = new Set([query]);

  words.forEach(word => {
    variants.add(`${word}*`);
    if (word.length >= 4) {
      for (let index = 0; index < word.length; index += 1) {
        variants.add(`${word.slice(0, index)}${word.slice(index + 1)}*`);
      }
    }
  });

  return [...variants].slice(0, 18);
}

// Cached lookup of the canonical "Unknown Artist" profile id (created server-side
// by api/group_unknown_artists.php) so client-side fallbacks link to a real artist
// page instead of a dead-end id.
let _cachedUnknownArtistId = null;

export async function resolveUnknownArtistId(user = null) {
  if (_cachedUnknownArtistId) return _cachedUnknownArtistId;
  try {
    const auth = getSubsonicAuthParams(user);
    const res = await fetch(getAmpacheUrl(`action=search3&query=Unknown+Artist&artistCount=5&songCount=0&albumCount=0&${auth}`), { cache: 'no-store' });
    const data = await res.json();
    const found = data?.['subsonic-response']?.searchResult3?.artist;
    const list = Array.isArray(found) ? found : (found ? [found] : []);
    const match = list.find(a => (a.name || '').trim().toLowerCase() === 'unknown artist');
    if (match) _cachedUnknownArtistId = String(match.id);
  } catch (e) {
    console.debug('resolveUnknownArtistId lookup failed:', e);
  }
  return _cachedUnknownArtistId;
}

/**
 * Fallback mapper for Subsonic track/album objects: any item missing its
 * `artist` name or `artistId` is rewritten to point at the shared
 * "Unknown Artist" profile so it still renders and remains clickable.
 */
export function applyUnknownArtistFallback(items = [], unknownArtistId = null) {
  if (!Array.isArray(items)) return items;
  return items.map(item => {
    if (!item) return item;
    const artistName = (item.artist || '').toString().trim();
    const hasValidArtistId = item.artistId !== undefined && item.artistId !== null &&
      String(item.artistId).trim() !== '' && String(item.artistId) !== '0';
    if (artistName && hasValidArtistId) return item;
    return {
      ...item,
      artist: artistName || 'Unknown Artist',
      artistId: hasValidArtistId ? item.artistId : (unknownArtistId || item.artistId)
    };
  });
}

let _searchAbortController = null;

export function searchSubsonic(query, user = null) {
  return new Promise((resolve) => {
    const trimmedQuery = (query || '').trim();
    if (trimmedQuery.length < 2) return resolve({});

    // AbortController cancels the previous in-flight request — caller (TopBar)
    // already debounces keystrokes, so no extra delay is added here.
    if (_searchAbortController) _searchAbortController.abort();
    _searchAbortController = new AbortController();
    const signal = _searchAbortController.signal;

    (async () => {
      const cacheKey = `aether_search_v2_${trimmedQuery.toLowerCase()}`;
      if (typeof window !== 'undefined' && window.sessionStorage) {
        try {
          const cached = sessionStorage.getItem(cacheKey);
          if (cached) {
            const parsed = JSON.parse(cached);
            const hasCachedResults = (parsed.data?.song?.length > 0) || (parsed.data?.album?.length > 0) || (parsed.data?.artist?.length > 0);
            if (hasCachedResults && (Date.now() - parsed.timestamp < 20000)) { // 20-second fresh search cache
              return resolve(parsed.data);
            }
          }
        } catch (e) {}
      }

      let songList = [];
      let albumList = [];
      let artistList = [];

      // 1. Primary: Direct high-performance MySQL pipeline via api_proxy.php
      try {
        const proxyUrl = `${getApiProxyUrl()}?action=search3&query=${encodeURIComponent(trimmedQuery)}&songCount=150&albumCount=20&artistCount=20&_t=${Date.now()}`;
        const proxyRes = await fetch(proxyUrl, { cache: 'no-store', signal });
        const proxyData = await proxyRes.json();
        if (proxyData?.status === 'ok') {
          const rawSong = proxyData.song || proxyData?.['subsonic-response']?.searchResult3?.song || proxyData?.['subsonic-response']?.searchResult2?.song || [];
          const rawAlb = proxyData.album || proxyData?.['subsonic-response']?.searchResult3?.album || proxyData?.['subsonic-response']?.searchResult2?.album || [];
          const rawArt = proxyData.artist || proxyData?.['subsonic-response']?.searchResult3?.artist || proxyData?.['subsonic-response']?.searchResult2?.artist || [];

          songList = Array.isArray(rawSong) ? rawSong : (rawSong ? [rawSong] : []);
          albumList = Array.isArray(rawAlb) ? rawAlb : (rawAlb ? [rawAlb] : []);
          artistList = Array.isArray(rawArt) ? rawArt : (rawArt ? [rawArt] : []);
        }
      } catch (proxyErr) {
        if (proxyErr.name === 'AbortError') return resolve({});
        console.debug("Proxy search notice, trying fallback:", proxyErr);
      }

      // 2. Fallback: Native Ampache Subsonic REST API (if proxy returned 0 items)
      if (songList.length === 0 && albumList.length === 0 && artistList.length === 0) {
        try {
          const auth = getSubsonicAuthParams(user);
          if (auth && auth.includes('u=')) {
            const authClean = auth.startsWith('&') || auth.startsWith('?') ? auth.slice(1) : auth;
            const subRes = await fetch(`${getBaseUrl()}/ampache/public/rest/index.php?action=search3&query=${encodeURIComponent(trimmedQuery)}&songCount=150&albumCount=20&artistCount=20&${authClean}&_t=${Date.now()}`, { cache: 'no-store', signal })
              .then(res => res.json())
              .catch(e => { if (e.name !== 'AbortError') return null; throw e; });

            const found = subRes?.['subsonic-response']?.searchResult3 || subRes?.['subsonic-response']?.searchResult2 || {};
            if (Array.isArray(found.song)) songList = found.song;
            else if (found.song) songList = [found.song];
            if (Array.isArray(found.album)) albumList = found.album;
            else if (found.album) albumList = [found.album];
            if (Array.isArray(found.artist)) artistList = found.artist;
            else if (found.artist) artistList = [found.artist];
          }
        } catch (subErr) {
          if (subErr.name === 'AbortError') return resolve({});
        }
      }

      const unknownArtistId = await resolveUnknownArtistId(user);
      const merged = {
        song: dedupeSongs(applyUnknownArtistFallback(songList, unknownArtistId)),
        album: applyUnknownArtistFallback(albumList, unknownArtistId),
        artist: artistList
      };

      if (typeof window !== 'undefined' && window.sessionStorage) {
        try {
          if (merged.song.length > 0 || merged.album.length > 0 || merged.artist.length > 0) {
            sessionStorage.setItem(cacheKey, JSON.stringify({
              timestamp: Date.now(),
              data: merged
            }));
          }
        } catch (e) {}
      }

      resolve(merged);
    })();
  });
}

export function getApiProxyUrl() {
  return `${getBaseUrl()}/modern-music-app/api_proxy.php`;
}

export async function fetchRecentlyAdded(user = null, limits = { songLimit: 20, albumLimit: 16 }) {
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
}

export async function fetchAllArtists(user = null) {
  const cacheKey = 'aether_catalog_artists';
  if (typeof window !== 'undefined' && window.sessionStorage) {
    try {
      const cached = sessionStorage.getItem(cacheKey);
      if (cached) {
        const parsed = JSON.parse(cached);
        if (Date.now() - parsed.timestamp < 600000) { // 5-minute TTL
          return parsed.data;
        }
      }
    } catch (e) {}
  }

  const cacheBust = `_t=${Date.now()}`;
  let result = null;

  // 1. Try high-speed database proxy
  try {
    const res = await fetch(`${getApiProxyUrl()}?action=getArtists&${cacheBust}`, { cache: 'no-store' });
    const data = await res.json();
    if (data?.status === 'ok') {
      const rawIndex = data['subsonic-response']?.artists?.index || [];
      const index = Array.isArray(rawIndex) ? rawIndex : (rawIndex ? [rawIndex] : []);
      let all = [];
      index.forEach(idx => {
        if (idx.artist) {
          const artList = Array.isArray(idx.artist) ? idx.artist : [idx.artist];
          all = [...all, ...artList];
        }
      });
      if (all.length === 0 && Array.isArray(data.artists)) {
        all = data.artists;
      }
      if (all.length > 0) result = { artists: all, rawResponse: data };
    }
  } catch (e) {
    console.debug("Proxy fetchAllArtists fallback:", e);
  }

  // 2. Subsonic fallback
  if (!result) {
    try {
      const auth = getSubsonicAuthParams(user);
      const res = await fetch(getAmpacheUrl(`action=getArtists&${auth}&${cacheBust}`), { cache: 'no-store' });
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        const rawIndex = data['subsonic-response'].artists?.index;
        const index = Array.isArray(rawIndex) ? rawIndex : (rawIndex ? [rawIndex] : []);
        let all = [];
        index.forEach(idx => {
          if (idx.artist) {
            const artList = Array.isArray(idx.artist) ? idx.artist : [idx.artist];
            all = [...all, ...artList];
          }
        });
        if (all.length === 0 && data['subsonic-response'].artists?.artist) {
          const rawList = data['subsonic-response'].artists.artist;
          all = Array.isArray(rawList) ? rawList : (rawList ? [rawList] : []);
        }
        result = { artists: all, rawResponse: data };
      }
    } catch (e) {
      console.debug("Subsonic getArtists fallback:", e);
    }
  }

  const finalResult = result || { artists: [], rawResponse: null };
  if (finalResult.artists.length > 0 && typeof window !== 'undefined' && window.sessionStorage) {
    try {
      sessionStorage.setItem(cacheKey, JSON.stringify({
        timestamp: Date.now(),
        data: finalResult
      }));
    } catch (e) {}
  }
  return finalResult;
}

export async function fetchAllAlbums(user = null) {
  const cacheKey = 'aether_catalog_albums';
  if (typeof window !== 'undefined' && window.sessionStorage) {
    try {
      const cached = sessionStorage.getItem(cacheKey);
      if (cached) {
        const parsed = JSON.parse(cached);
        if (Date.now() - parsed.timestamp < 600000) { // 5-minute TTL
          return parsed.data;
        }
      }
    } catch (e) {}
  }

  const cacheBust = `_t=${Date.now()}`;
  let result = null;

  // 1. Try high-speed database proxy
  try {
    const res = await fetch(`${getApiProxyUrl()}?action=getAlbums&${cacheBust}`, { cache: 'no-store' });
    const data = await res.json();
    if (data?.status === 'ok') {
      const albumList = data.albums || data['subsonic-response']?.albumList?.album || [];
      const list = Array.isArray(albumList) ? albumList : (albumList ? [albumList] : []);
      if (list.length > 0) result = { albums: list, rawResponse: data };
    }
  } catch (e) {
    console.debug("Proxy fetchAllAlbums fallback:", e);
  }

  // 2. Subsonic fallback
  if (!result) {
    try {
      const auth = getSubsonicAuthParams(user);
      const res = await fetch(getAmpacheUrl(`action=getAlbumList&type=alphabeticalByArtist&size=500&${auth}&${cacheBust}`), { cache: 'no-store' });
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        const raw = data['subsonic-response'].albumList?.album || [];
        const list = Array.isArray(raw) ? raw : (raw ? [raw] : []);
        result = { albums: list, rawResponse: data };
      }
    } catch (e) {
      console.debug("Subsonic getAlbumList fallback:", e);
    }
  }

  const finalResult = result || { albums: [], rawResponse: null };
  if (finalResult.albums.length > 0 && typeof window !== 'undefined' && window.sessionStorage) {
    try {
      sessionStorage.setItem(cacheKey, JSON.stringify({
        timestamp: Date.now(),
        data: finalResult
      }));
    } catch (e) {}
  }
  return finalResult;
}

export async function fetchAlbumDetails(albumId, user = null) {
  if (!albumId) return null;

  // Virtual route handling for unassigned / missing album tags
  if (albumId === 'unknown') {
    try {
      const headers = user?.token ? { 'Authorization': `Bearer ${user.token}` } : {};
      const res = await fetch(`${getBaseUrl()}/api/get_unknown_album_tracks.php`, { headers });
      const data = await res.json();
      if (data?.status === 'success' && Array.isArray(data.songs)) {
        return {
          id: 'unknown',
          name: 'Unknown Album',
          artist: 'Various Artists',
          songCount: data.songs.length,
          song: data.songs,
          coverArt: 'unknown'
        };
      }
    } catch (e) {
      console.debug("fetchAlbumDetails unknown album fallback:", e);
    }
    return null;
  }

  const cacheBust = `_t=${Date.now()}`;
  // 1. Try proxy first
  try {
    const res = await fetch(`${getApiProxyUrl()}?action=getAlbum&id=${encodeURIComponent(albumId)}&${cacheBust}`, { cache: 'no-store' });
    const data = await res.json();
    if (data?.status === 'ok' && data.album) {
      return data.album;
    }
  } catch (e) {
    console.debug("Proxy fetchAlbumDetails fallback:", e);
  }

  // 2. Subsonic fallback
  try {
    const auth = getSubsonicAuthParams(user);
    const res = await fetch(getAmpacheUrl(`action=getAlbum&id=${encodeURIComponent(albumId)}&${auth}&${cacheBust}`), { cache: 'no-store' });
    const data = await res.json();
    if (data?.['subsonic-response']?.status === 'ok') {
      return data['subsonic-response'].album;
    }
  } catch (e) {
    console.debug("Subsonic getAlbum fallback:", e);
  }

  return null;
}

export async function fetchArtistDetails(artistId, user = null) {
  if (!artistId) return null;
  const cacheBust = `_t=${Date.now()}`;
  // 1. Try proxy first
  try {
    const res = await fetch(`${getApiProxyUrl()}?action=getArtist&id=${encodeURIComponent(artistId)}&${cacheBust}`, { cache: 'no-store' });
    const data = await res.json();
    if (data?.status === 'ok' && data.artist) {
      return data.artist;
    }
  } catch (e) {
    console.debug("Proxy fetchArtistDetails fallback:", e);
  }

  // 2. Subsonic fallback
  try {
    const auth = getSubsonicAuthParams(user);
    const res = await fetch(getAmpacheUrl(`action=getArtist&id=${encodeURIComponent(artistId)}&${auth}&${cacheBust}`), { cache: 'no-store' });
    const data = await res.json();
    if (data?.['subsonic-response']?.status === 'ok') {
      return data['subsonic-response'].artist;
    }
  } catch (e) {
    console.debug("Subsonic getArtist fallback:", e);
  }

  return null;
}

export async function fetchFeaturedLibrary(user = null, albumsPromise = null) {
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

  // 1. Only include albums that explicitly have verified binary artwork in the database
  const validAlbums = (albumsResult.albums || []).filter(album => album && album.hasArt === true);

  // Build lookup Set of album IDs that have real artwork
  const validAlbumIdsWithArt = new Set();
  validAlbums.forEach(a => {
    if (a.id) {
      validAlbumIdsWithArt.add(String(a.id));
      const clean = String(a.id).replace(/\D/g, '');
      if (clean) validAlbumIdsWithArt.add(clean);
    }
  });

  // 2. Only include artists that have real profile art
  const validArtists = (artistsResult.artists || []).filter(artist => {
    if (!artist) return false;
    if (artist.hasArt === false) return false;
    const art = artist.coverArt;
    if (!art || String(art).trim() === '' || String(art) === '0' || String(art) === 'unknown') return false;
    return true;
  });

  // 3. Only include songs whose parent album has verified artwork
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

  const rawSongList = Array.isArray(songs) ? songs : [songs].filter(Boolean);
  const validSongs = rawSongList.filter(song => {
    if (!song) return false;
    if (song.hasArt === false) return false;
    const albId = String(song.albumId || song.parent || '').replace(/\D/g, '');
    const rawAlb = String(song.albumId || song.parent || '');
    return validAlbumIdsWithArt.has(rawAlb) || (albId && validAlbumIdsWithArt.has(albId));
  }).map(s => ({ ...s, hasArt: true }));

  const result = {
    albums: applyUnknownArtistFallback(validAlbums, unknownArtistId),
    artists: validArtists,
    songs: applyUnknownArtistFallback(validSongs, unknownArtistId)
  };

  if (typeof window !== 'undefined' && window.sessionStorage) {
    try {
      sessionStorage.setItem(cacheKey, JSON.stringify({ timestamp: Date.now(), data: result }));
    } catch (e) {}
  }
  
  return result;
}

/**
 * Fetches the active user's starred (favorite) albums and artists directly
 * from the Ampache backend via the Subsonic getStarred2 endpoint.
 */
export async function fetchStarredAlbumsAndArtists(user = null) {
  const uParam = user?.username ? `&u=${encodeURIComponent(user.username)}` : '';
  const cacheBust = `_t=${Date.now()}`;

  // 1. Try high-speed database proxy first
  try {
    const res = await fetch(`${getApiProxyUrl()}?action=getStarred2${uParam}&${cacheBust}`, { cache: 'no-store' });
    const data = await res.json();
    if (data?.status === 'ok') {
      const rawAlbums = data.albums || data['subsonic-response']?.starred2?.album || [];
      const rawArtists = data.artists || data['subsonic-response']?.starred2?.artist || [];
      return {
        albums: Array.isArray(rawAlbums) ? rawAlbums : (rawAlbums ? [rawAlbums] : []),
        artists: Array.isArray(rawArtists) ? rawArtists : (rawArtists ? [rawArtists] : [])
      };
    }
  } catch (e) {
    console.debug('fetchStarredAlbumsAndArtists proxy notice:', e);
  }

  // 2. Subsonic fallback
  try {
    const auth = getSubsonicAuthParams(user, true);
    const res = await fetch(getAmpacheUrl(`action=getStarred2&${auth}`));
    const data = await res.json();

    if (data?.['subsonic-response']?.status !== 'ok') {
      return { albums: [], artists: [] };
    }

    const starred2 = data['subsonic-response']?.starred2 || {};
    const rawAlbums = starred2.album;
    const rawArtists = starred2.artist;

    return {
      albums: Array.isArray(rawAlbums) ? rawAlbums : (rawAlbums ? [rawAlbums] : []),
      artists: Array.isArray(rawArtists) ? rawArtists : (rawArtists ? [rawArtists] : [])
    };
  } catch (e) {
    return { albums: [], artists: [] };
  }
}

export async function toggleStarredItem({ albumId, artistId, songId } = {}, starred, user = null) {
  const action = starred ? 'unstar' : 'star';
  const uParam = user?.username ? `&u=${encodeURIComponent(user.username)}` : '';

  // 1. Try high-speed database proxy first
  try {
    let pUrl = `${getApiProxyUrl()}?action=${action}${uParam}`;
    if (songId) {
      pUrl += `&id=${encodeURIComponent(songId)}`;
    } else {
      if (albumId) pUrl += `&albumId=${encodeURIComponent(albumId)}`;
      if (artistId) pUrl += `&artistId=${encodeURIComponent(artistId)}`;
    }

    const res = await fetch(pUrl);
    const data = await res.json();
    if (data?.status === 'ok') return true;
  } catch (e) {
    console.debug('toggleStarredItem proxy notice:', e);
  }

  // 2. Subsonic fallback
  try {
    const auth = getSubsonicAuthParams(user, true);
    let url = getAmpacheUrl(`action=${action}&${auth}`);
    if (songId) {
      url += `&id=${encodeURIComponent(songId)}`;
    } else {
      if (albumId) url += `&albumId=${encodeURIComponent(albumId)}`;
      if (artistId) url += `&artistId=${encodeURIComponent(artistId)}`;
    }
    const res = await fetch(url);
    const data = await res.json();
    return data?.['subsonic-response']?.status === 'ok';
  } catch (e) {
    return false;
  }
}

export function getMergeMetadataUrl() {
  return `${getBaseUrl()}/api/merge_metadata.php`;
}

export function getDeleteSongUrl() {
  return `${getBaseUrl()}/api/delete_song.php`;
}

export function getMergeSongsUrl() {
  return `${getBaseUrl()}/api/merge_songs.php`;
}

export function getMediaPortalUrl() {
  return `${getBaseUrl()}/media.html`;
}

export async function submitSongRequest({ title, artist, notes }, user) {
  if (!user) throw new Error("Must be logged in to request a song.");
  const authParams = new URLSearchParams(getSubsonicAuthParams(user, true));
  
  const res = await fetch(getMediaRequestsUrl('submit_request.php'), {
    method: 'POST',
    headers: { 
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${user?.token || ''}`
    },
    body: JSON.stringify({
      trackTitle: (title || '').trim(),
      artistName: (artist || '').trim(),
      notes: (notes || '').trim(),
      mediaType: 'Song',
      u: authParams.get('u') || user.username || '',
      t: authParams.get('t') || '',
      s: authParams.get('s') || '',
    }),
  });
  return await res.json();
}

export function getMediaRequestsUrl(endpoint) {
  return `${getBaseUrl()}/api/media/${endpoint}`;
}

/**
 * Subsonic Playlist Helpers
 */

export async function fetchSubsonicPlaylists(user = null) {
  // Read operations: cached auth is fine
  const auth = getSubsonicAuthParams(user);
  const res = await fetch(getAmpacheUrl(`action=getPlaylists&${auth}`));
  return await res.json();
}

export async function createSubsonicPlaylist(name, songIds = [], isPublic = false, user = null) {
  // Write operations: always force fresh token so multi-device / multi-user calls don't collide
  const auth = getSubsonicAuthParams(user, true);
  let url = getAmpacheUrl(`action=createPlaylist&name=${encodeURIComponent(name)}&${auth}`);
  if (Array.isArray(songIds) && songIds.length > 0) {
    url += '&' + songIds.map(id => `songId=${encodeURIComponent(id)}`).join('&');
  } else if (songIds && typeof songIds === 'string') {
    url += `&songId=${encodeURIComponent(songIds)}`;
  }
  const res = await fetch(url);
  const data = await res.json();

  const createdId = data?.['subsonic-response']?.playlist?.id;
  if (createdId) {
    // Explicitly set public / private visibility (also force-fresh for this write)
    try {
      await updateSubsonicPlaylist(createdId, { public: isPublic }, user);
    } catch (e) {
      console.debug("Subsonic visibility sync notice:", e);
    }
    // Also guarantee persistence via database proxy
    try {
      await fetch(`${getApiProxyUrl()}?action=togglePlaylistVisibility&id=${createdId}&public=${isPublic ? 'true' : 'false'}`);
    } catch (pe) {
      console.debug("Proxy visibility sync notice:", pe);
    }
  }

  return data;
}

export async function updateSubsonicPlaylist(playlistId, { name, public: isPublic, songIdToAdd, songIndexToRemove } = {}, user = null) {
  // Always force-fresh token for every playlist mutation to guarantee binding to the active user's DB profile
  const auth = getSubsonicAuthParams(user, true);
  let url = getAmpacheUrl(`action=updatePlaylist&playlistId=${encodeURIComponent(playlistId)}&${auth}`);
  if (name !== undefined) url += `&name=${encodeURIComponent(name)}`;
  if (isPublic !== undefined) url += `&public=${isPublic ? 'true' : 'false'}`;
  if (songIdToAdd !== undefined) url += `&songIdToAdd=${encodeURIComponent(songIdToAdd)}`;
  if (songIndexToRemove !== undefined) url += `&songIndexToRemove=${encodeURIComponent(songIndexToRemove)}`;

  const res = await fetch(url);
  return await res.json();
}

export async function deleteSubsonicPlaylist(playlistId, user = null) {
  // Force-fresh token for deletions
  const auth = getSubsonicAuthParams(user, true);
  const res = await fetch(getAmpacheUrl(`action=deletePlaylist&id=${encodeURIComponent(playlistId)}&${auth}`));
  return await res.json();
}


