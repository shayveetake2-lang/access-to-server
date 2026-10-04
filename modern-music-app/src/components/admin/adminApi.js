import { getBaseUrl, getSubsonicAuthParams } from '../../utils/api';

// Reads the logged-in admin's Ampache credentials so every mutating call can
// be verified server-side against Ampache itself (see manage_content.php).
// No static/shared secret is embedded in the client bundle anymore.
function getStoredCredentials() {
  try {
    const raw = localStorage.getItem('ampache_user') || sessionStorage.getItem('ampache_user');
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

export async function adminPost(action, payload = {}) {
  const user = getStoredCredentials();
  // Force a fresh salt/token pair per call (forceNew=true) rather than reusing a cached one.
  const authParams = new URLSearchParams(getSubsonicAuthParams(user, true));

  const url = `${getBaseUrl()}/api/manage_content.php`;
  const res = await fetch(url, {
    method: 'POST',
    headers: { 
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${user?.token || ''}`
    },
    body: JSON.stringify({
      action,
      ...payload,
      token: user?.token || '',
      u: authParams.get('u') || user?.username || '',
      t: authParams.get('t') || '',
      s: authParams.get('s') || '',
    }),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({ error: `HTTP ${res.status}` }));
    throw new Error(err.error || `HTTP ${res.status}`);
  }
  const data = await res.json();
  if (!data.ok) throw new Error(data.error || 'Unknown error');
  return data;
}

export async function fetchUserHistory(username, { limit = 50, offset = 0 } = {}) {
  const user = getStoredCredentials();
  const token = user?.token || user?.jwt || '';
  const url = `${getBaseUrl()}/modern-music-app/api_proxy.php?action=adminGetUserHistory&username=${encodeURIComponent(username)}&limit=${limit}&offset=${offset}&_t=${Date.now()}`;
  const res = await fetch(url, {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({ message: `HTTP ${res.status}` }));
    throw new Error(err.message || `HTTP ${res.status}`);
  }
  const data = await res.json();
  if (data.status !== 'ok') throw new Error(data.message || 'Failed to fetch user history');
  return data;
}

export async function fetchUserFavorites(username) {
  const user = getStoredCredentials();
  const token = user?.token || user?.jwt || '';
  const url = `${getBaseUrl()}/modern-music-app/api_proxy.php?action=adminGetUserFavorites&username=${encodeURIComponent(username)}&_t=${Date.now()}`;
  const res = await fetch(url, {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({ message: `HTTP ${res.status}` }));
    throw new Error(err.message || `HTTP ${res.status}`);
  }
  const data = await res.json();
  if (data.status !== 'ok') throw new Error(data.message || 'Failed to fetch user favorites');
  return data;
}

