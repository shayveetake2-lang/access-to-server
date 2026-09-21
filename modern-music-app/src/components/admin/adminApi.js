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
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action,
      ...payload,
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

