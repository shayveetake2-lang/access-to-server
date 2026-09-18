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

export function getCoverArtUrl(coverArtId, authParams = '') {
  if (!coverArtId) return '';
  return `${getBaseUrl()}/ampache/public/rest/index.php?action=getCoverArt&id=${coverArtId}&${authParams}`;
}

export function getStreamUrl(trackId, authParams = '') {
  if (!trackId) return '';
  return `${getBaseUrl()}/ampache/public/rest/index.php?action=stream&id=${trackId}&${authParams}`;
}

export function getApiProxyUrl() {
  return `${getBaseUrl()}/modern-music-app/api_proxy.php`;
}

export function getMediaPortalUrl() {
  return `${getBaseUrl()}/media.html`;
}

