import os

path = "/Volumes/htdocs/access-to-server/modern-music-app/src/utils/api.js"
with open(path, "r") as f:
    content = f.read()

target = """export async function searchSubsonic(query, user = null) {
  const trimmedQuery = (query || '').trim();
  if (trimmedQuery.length < 2) return {};

  const cacheKey = `aether_search_${trimmedQuery.toLowerCase()}`;
  if (typeof window !== 'undefined' && window.sessionStorage) {
    try {
      const cached = sessionStorage.getItem(cacheKey);
      if (cached) {
        const parsed = JSON.parse(cached);
        if (Date.now() - parsed.timestamp < 300000) { // 5-minute TTL
          return parsed.data;
        }
      }
    } catch (e) {}
  }

  const auth = getSubsonicAuthParams(user);
  if (!auth || !auth.includes('u=')) return {};
  const authClean = auth.startsWith('&') || auth.startsWith('?') ? auth.slice(1) : auth;

  try {
    const [searchRes, unknownArtistId] = await Promise.all([
      fetch(`${getBaseUrl()}/ampache/public/rest/index.php?action=search3&query=${encodeURIComponent(trimmedQuery)}&songCount=150&albumCount=20&artistCount=20&${authClean}&_t=${Date.now()}`, { cache: 'no-store' })
        .then(res => res.json())
        .catch(() => null),
      resolveUnknownArtistId(user)
    ]);"""

replacement = """let _searchTimeout = null;
let _searchAbortController = null;

export function searchSubsonic(query, user = null) {
  return new Promise((resolve) => {
    const trimmedQuery = (query || '').trim();
    if (trimmedQuery.length < 2) return resolve({});

    // PERFORMANCE PATCH: 300ms Debounce & AbortController to prevent API hammering
    if (_searchTimeout) clearTimeout(_searchTimeout);
    if (_searchAbortController) _searchAbortController.abort();
    
    _searchAbortController = new AbortController();
    const signal = _searchAbortController.signal;

    _searchTimeout = setTimeout(async () => {
      const cacheKey = `aether_search_${trimmedQuery.toLowerCase()}`;
      if (typeof window !== 'undefined' && window.sessionStorage) {
        try {
          const cached = sessionStorage.getItem(cacheKey);
          if (cached) {
            const parsed = JSON.parse(cached);
            if (Date.now() - parsed.timestamp < 300000) { // 5-minute TTL
              return resolve(parsed.data);
            }
          }
        } catch (e) {}
      }

      const auth = getSubsonicAuthParams(user);
      if (!auth || !auth.includes('u=')) return resolve({});
      const authClean = auth.startsWith('&') || auth.startsWith('?') ? auth.slice(1) : auth;

      try {
        const [searchRes, unknownArtistId] = await Promise.all([
          fetch(`${getBaseUrl()}/ampache/public/rest/index.php?action=search3&query=${encodeURIComponent(trimmedQuery)}&songCount=150&albumCount=20&artistCount=20&${authClean}&_t=${Date.now()}`, { cache: 'no-store', signal })
            .then(res => res.json())
            .catch((e) => { if (e.name !== 'AbortError') return null; throw e; }),
          resolveUnknownArtistId(user)
        ]);"""

content = content.replace(target, replacement)

target2 = """      // If exact query returns empty, perform a single wildcard fallback (* suffix)
    if (songList.length === 0 && albumList.length === 0 && artistList.length === 0 && !trimmedQuery.endsWith('*')) {
      try {
        const fallbackRes = await fetch(`${getBaseUrl()}/ampache/public/rest/index.php?action=search3&query=${encodeURIComponent(trimmedQuery + '*')}&songCount=150&albumCount=20&artistCount=20&${authClean}&_t=${Date.now()}`, { cache: 'no-store' });"""

replacement2 = """      // If exact query returns empty, perform a single wildcard fallback (* suffix)
    if (songList.length === 0 && albumList.length === 0 && artistList.length === 0 && !trimmedQuery.endsWith('*')) {
      try {
        const fallbackRes = await fetch(`${getBaseUrl()}/ampache/public/rest/index.php?action=search3&query=${encodeURIComponent(trimmedQuery + '*')}&songCount=150&albumCount=20&artistCount=20&${authClean}&_t=${Date.now()}`, { cache: 'no-store', signal });"""

content = content.replace(target2, replacement2)

target3 = """    if (typeof window !== 'undefined' && window.sessionStorage) {
      try {
        sessionStorage.setItem(cacheKey, JSON.stringify({
          timestamp: Date.now(),
          data: merged
        }));
      } catch (e) {}
    }

    return merged;
  } catch (err) {
    console.error("searchSubsonic error:", err);
    return {};
  }
}"""

replacement3 = """    if (typeof window !== 'undefined' && window.sessionStorage) {
      try {
        sessionStorage.setItem(cacheKey, JSON.stringify({
          timestamp: Date.now(),
          data: merged
        }));
      } catch (e) {}
    }

    resolve(merged);
  } catch (err) {
    if (err.name !== 'AbortError') {
      console.error("searchSubsonic error:", err);
    }
    resolve({});
  }
    }, 300);
  });
}"""

content = content.replace(target3, replacement3)

with open(path, "w") as f:
    f.write(content)
print("api.js patched.")
