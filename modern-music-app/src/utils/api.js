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

/**
 * Dynamically resolves active user credentials from argument, localStorage, or sessionStorage,
 * and generates standard Subsonic REST API token auth parameters: u, t, s, v=1.16.1, c=Aether, f=json.
 */
export function getSubsonicAuthParams(user = null) {
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

  // Generate random salt and MD5 token (Subsonic Token Auth standard: t = md5(password + salt))
  const salt = Math.random().toString(36).substring(2, 12);
  const password = credentials.password || '';
  const token = md5(password + salt);

  return `u=${encodeURIComponent(credentials.username)}&t=${token}&s=${salt}&v=1.16.1&c=Aether&f=json`;
}

export function getCoverArtUrl(coverArtId, authParams = '') {
  if (!coverArtId) return '';
  const auth = authParams || getSubsonicAuthParams();
  return `${getBaseUrl()}/ampache/public/rest/index.php?action=getCoverArt&id=${coverArtId}&${auth}`;
}

export function getStreamUrl(trackId, authParams = '') {
  if (!trackId) return '';
  const auth = authParams || getSubsonicAuthParams();
  return `${getBaseUrl()}/ampache/public/rest/index.php?action=stream&id=${trackId}&${auth}`;
}

export function getApiProxyUrl() {
  return `${getBaseUrl()}/modern-music-app/api_proxy.php`;
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


