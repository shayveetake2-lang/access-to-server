import md5 from './md5';

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

  // Generate random salt and MD5 token (Subsonic Token Auth standard: t = md5(password + salt))
  const salt = Math.random().toString(36).substring(2, 12);
  const password = credentials.password || '';
  const token = md5(password + salt);

  // Provide enc:hex password parameter for Ampache backwards-compatibility & users without dedicated API keys
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
  if (!coverArtId || String(coverArtId).trim() === '' || String(coverArtId) === '0') {
    return DEFAULT_COVER_ART;
  }
  const cleanId = String(coverArtId).trim();
  const auth = authParams || getSubsonicAuthParams();
  return `${getBaseUrl()}/ampache/public/rest/index.php?action=getCoverArt&id=${encodeURIComponent(cleanId)}&${auth}`;
}

export function getStreamUrl(trackId, authParams = '') {
  if (!trackId) return '';
  const auth = authParams || getSubsonicAuthParams();
  return `${getBaseUrl()}/ampache/public/rest/index.php?action=stream&id=${trackId}&${auth}`;
}

export function getApiProxyUrl() {
  return `${getBaseUrl()}/modern-music-app/api_proxy.php`;
}

export async function fetchRecentlyAdded(user = null, limits = { songLimit: 20, albumLimit: 16 }) {
  // Try proxy first for fastest response
  try {
    const res = await fetch(`${getApiProxyUrl()}?action=getRecentlyAdded&songLimit=${limits.songLimit}&albumLimit=${limits.albumLimit}`);
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
    const res = await fetch(getAmpacheUrl(`action=getAlbumList2&type=newest&size=${limits.albumLimit}&${auth}`));
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

export function getMergeMetadataUrl() {
  return `${getBaseUrl()}/api/merge_metadata.php`;
}

export function getMediaPortalUrl() {
  return `${getBaseUrl()}/media.html`;
}

/**
 * Subsonic Playlist Helpers
 */

export async function fetchSubsonicPlaylists(user = null) {
  const auth = getSubsonicAuthParams(user);
  const res = await fetch(getAmpacheUrl(`action=getPlaylists&${auth}`));
  return await res.json();
}

export async function createSubsonicPlaylist(name, songIds = [], isPublic = false, user = null) {
  const auth = getSubsonicAuthParams(user);
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
    // Explicitly set public / private visibility
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
  const auth = getSubsonicAuthParams(user);
  let url = getAmpacheUrl(`action=updatePlaylist&playlistId=${encodeURIComponent(playlistId)}&${auth}`);
  if (name !== undefined) url += `&name=${encodeURIComponent(name)}`;
  if (isPublic !== undefined) url += `&public=${isPublic ? 'true' : 'false'}`;
  if (songIdToAdd !== undefined) url += `&songIdToAdd=${encodeURIComponent(songIdToAdd)}`;
  if (songIndexToRemove !== undefined) url += `&songIndexToRemove=${encodeURIComponent(songIndexToRemove)}`;

  const res = await fetch(url);
  return await res.json();
}

export async function deleteSubsonicPlaylist(playlistId, user = null) {
  const auth = getSubsonicAuthParams(user);
  const res = await fetch(getAmpacheUrl(`action=deletePlaylist&id=${encodeURIComponent(playlistId)}&${auth}`));
  return await res.json();
}


