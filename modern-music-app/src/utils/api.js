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

/**
 * Dynamically resolves active user credentials from argument, localStorage, or sessionStorage,
 * and generates standard Subsonic REST API token auth parameters: u, t, s, v=1.16.1, c=Aether, f=json.
 * Memoizes token/salt for 15 minutes per user session so browser image caching remains active.
 */
export function getSubsonicAuthParams(user = null, forceNew = false) {
  let credentials = user;

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

  const userKey = `${credentials.username}:${credentials.password || ''}`;
  const now = Date.now();
  if (!forceNew && _cachedAuthParams && _cachedAuthUserKey === userKey && (now - _cachedAuthTimestamp < 15 * 60 * 1000)) {
    return _cachedAuthParams;
  }

  // Phase 1: Secure Pre-Computed Subsonic Hash (No Plaintext Password Required)
  if (credentials.subsonic_token && credentials.subsonic_salt) {
    const params = `u=${encodeURIComponent(credentials.username)}&t=${credentials.subsonic_token}&s=${credentials.subsonic_salt}&v=1.16.1&c=Aether&f=json`;
    _cachedAuthParams = params;
    _cachedAuthUserKey = userKey;
    _cachedAuthTimestamp = now;
    return params;
  }

  // Fallback to legacy random salt and MD5 token (Subsonic Token Auth standard)
  // CRITICAL CACHING FIX: Use a stable static salt for the session so image caching works
  let salt = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('aether_salt') : null;
  if (!salt) {
    salt = Math.random().toString(36).substring(2, 12);
    if (typeof sessionStorage !== 'undefined') sessionStorage.setItem('aether_salt', salt);
  }
  const password = credentials.password || '';
  const token = md5(password + salt);

  // Provide enc:hex password parameter for Ampache backwards-compatibility
  const hexPass = password
    ? Array.from(password).map(c => c.charCodeAt(0).toString(16).padStart(2, '0')).join('')
    : '';
  const pParam = hexPass ? `&p=enc:${hexPass}` : '';

  const params = `u=${encodeURIComponent(credentials.username)}&t=${token}&s=${salt}${pParam}&v=1.16.1&c=Aether&f=json`;
  _cachedAuthParams = params;
  _cachedAuthUserKey = userKey;
  _cachedAuthTimestamp = now;

  return params;
}

export function getCoverArtUrl(coverArtId, authParams = '') {
  if (!coverArtId || String(coverArtId).trim() === '' || String(coverArtId) === '0' || String(coverArtId) === 'unknown') {
    return DEFAULT_COVER_ART;
  }
  const raw = String(coverArtId).trim();
  let normalizedId = raw;

  // Normalize ID into Ampache's canonical Subsonic ranges:
  // Artists: 100000000+, Albums: 200000000+, Songs: 300000000+
  if (raw.startsWith('al-')) {
    const num = parseInt(raw.slice(3), 10);
    if (!isNaN(num)) {
      normalizedId = num < 100000000 ? String(200000000 + num) : String(num);
    }
  } else if (raw.startsWith('ar-')) {
    const num = parseInt(raw.slice(3), 10);
    if (!isNaN(num)) {
      normalizedId = num < 100000000 ? String(100000000 + num) : String(num);
    }
  } else if (raw.startsWith('sg-')) {
    const num = parseInt(raw.slice(3), 10);
    if (!isNaN(num)) {
      normalizedId = num < 100000000 ? String(300000000 + num) : String(num);
    }
  } else {
    const num = parseInt(raw, 10);
    if (!isNaN(num)) {
      // Unprefixed raw ID from album context maps to album offset
      normalizedId = num < 100000000 ? String(200000000 + num) : String(num);
    }
  }

  const auth = authParams || getSubsonicAuthParams();
  if (!auth || !auth.includes('u=')) {
    return DEFAULT_COVER_ART;
  }
  const authClean = auth.startsWith('&') || auth.startsWith('?') ? auth.slice(1) : auth;
  return `${getBaseUrl()}/ampache/public/rest/index.php?action=getCoverArt&id=${encodeURIComponent(normalizedId)}&${authClean}`;
}

export function getStreamUrl(trackId, authParams = '') {
  if (!trackId) return '';
  const raw = String(trackId).trim();
  let normalizedId = raw;
  if (raw.startsWith('sg-')) {
    normalizedId = raw.slice(3);
  }
  const num = parseInt(normalizedId, 10);
  if (!isNaN(num)) {
    // Canonical 300000000 song offset for Ampache Subsonic stream & transcode engine
    normalizedId = num < 100000000 ? String(300000000 + num) : (num >= 300000000 && num < 400000000 ? String(num) : String(300000000 + (num % 100000000)));
  }

  const auth = authParams || getSubsonicAuthParams();
  if (!auth || !auth.includes('u=')) {
    return '';
  }
  const authClean = auth.startsWith('&') || auth.startsWith('?') ? auth.slice(1) : auth;
  return `${getBaseUrl()}/ampache/public/rest/index.php?action=stream&id=${encodeURIComponent(normalizedId)}&${authClean}`;
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

export async function searchSubsonic(query, user = null) {
  const trimmedQuery = (query || '').trim();
  if (trimmedQuery.length < 2) return {};

  const auth = getSubsonicAuthParams(user);
  if (!auth || !auth.includes('u=')) return {};
  const authClean = auth.startsWith('&') || auth.startsWith('?') ? auth.slice(1) : auth;

  try {
    const [searchRes, unknownArtistId] = await Promise.all([
      fetch(`${getBaseUrl()}/ampache/public/rest/index.php?action=search3&query=${encodeURIComponent(trimmedQuery)}&songCount=50&albumCount=15&artistCount=15&${authClean}&_t=${Date.now()}`, { cache: 'no-store' })
        .then(res => res.json())
        .catch(() => null),
      resolveUnknownArtistId(user)
    ]);

    const found = searchRes?.['subsonic-response']?.searchResult3 || {};
    let songList = Array.isArray(found.song) ? found.song : (found.song ? [found.song] : []);
    let albumList = Array.isArray(found.album) ? found.album : (found.album ? [found.album] : []);
    let artistList = Array.isArray(found.artist) ? found.artist : (found.artist ? [found.artist] : []);

    // If exact query returns empty, perform a single wildcard fallback (* suffix)
    if (songList.length === 0 && albumList.length === 0 && artistList.length === 0 && !trimmedQuery.endsWith('*')) {
      try {
        const fallbackRes = await fetch(`${getBaseUrl()}/ampache/public/rest/index.php?action=search3&query=${encodeURIComponent(trimmedQuery + '*')}&songCount=50&albumCount=15&artistCount=15&${authClean}&_t=${Date.now()}`, { cache: 'no-store' });
        const fallbackData = await fallbackRes.json();
        const fbFound = fallbackData?.['subsonic-response']?.searchResult3 || {};
        if (Array.isArray(fbFound.song)) songList = fbFound.song;
        else if (fbFound.song) songList = [fbFound.song];
        if (Array.isArray(fbFound.album)) albumList = fbFound.album;
        else if (fbFound.album) albumList = [fbFound.album];
        if (Array.isArray(fbFound.artist)) artistList = fbFound.artist;
        else if (fbFound.artist) artistList = [fbFound.artist];
      } catch (fbErr) {}
    }

    const merged = {
      song: dedupeSongs(applyUnknownArtistFallback(songList, unknownArtistId)),
      album: applyUnknownArtistFallback(albumList, unknownArtistId),
      artist: artistList
    };

    return merged;
  } catch (err) {
    console.error("searchSubsonic error:", err);
    return {};
  }
}

export function getApiProxyUrl() {
  return `${getBaseUrl()}/modern-music-app/api_proxy.php`;
}

export async function fetchRecentlyAdded(user = null, limits = { songLimit: 20, albumLimit: 16 }) {
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
}

export async function fetchAllArtists(user = null) {
  const cacheBust = `_t=${Date.now()}`;
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
      if (all.length > 0) return { artists: all, rawResponse: data };
    }
  } catch (e) {
    console.debug("Proxy fetchAllArtists fallback:", e);
  }

  // 2. Subsonic fallback
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
      return { artists: all, rawResponse: data };
    }
  } catch (e) {
    console.debug("Subsonic getArtists fallback:", e);
  }

  return { artists: [], rawResponse: null };
}

export async function fetchAllAlbums(user = null) {
  const cacheBust = `_t=${Date.now()}`;
  // 1. Try high-speed database proxy
  try {
    const res = await fetch(`${getApiProxyUrl()}?action=getAlbums&${cacheBust}`, { cache: 'no-store' });
    const data = await res.json();
    if (data?.status === 'ok') {
      const albumList = data.albums || data['subsonic-response']?.albumList?.album || [];
      const list = Array.isArray(albumList) ? albumList : (albumList ? [albumList] : []);
      if (list.length > 0) return { albums: list, rawResponse: data };
    }
  } catch (e) {
    console.debug("Proxy fetchAllAlbums fallback:", e);
  }

  // 2. Subsonic fallback
  try {
    const auth = getSubsonicAuthParams(user);
    const res = await fetch(getAmpacheUrl(`action=getAlbumList&type=alphabeticalByArtist&size=500&${auth}&${cacheBust}`), { cache: 'no-store' });
    const data = await res.json();
    if (data?.['subsonic-response']?.status === 'ok') {
      const raw = data['subsonic-response'].albumList?.album || [];
      const list = Array.isArray(raw) ? raw : (raw ? [raw] : []);
      return { albums: list, rawResponse: data };
    }
  } catch (e) {
    console.debug("Subsonic getAlbumList fallback:", e);
  }

  return { albums: [], rawResponse: null };
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

export async function fetchFeaturedLibrary(user = null) {
  const auth = getSubsonicAuthParams(user);
  const cacheBust = `_t=${Date.now()}`;
  const [albumsResult, artistsResult, songsRes, unknownArtistId] = await Promise.all([
    fetchAllAlbums(user),
    fetchAllArtists(user),
    fetch(getAmpacheUrl(`action=search3&query=%2A&songCount=200&albumCount=0&artistCount=0&${auth}&${cacheBust}`), { cache: 'no-store' }).then(r => r.json()).catch(() => ({})),
    resolveUnknownArtistId(user)
  ]);
  const albums = albumsResult.albums || [];
  const artists = artistsResult.artists || [];
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
    songs: applyUnknownArtistFallback(Array.isArray(songs) ? songs : [songs].filter(Boolean), unknownArtistId)
  };
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


