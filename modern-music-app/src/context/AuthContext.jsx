import { createContext, useState, useEffect, useContext } from 'react';
import { getAmpacheUrl, getSubsonicAuthParams, getBaseUrl } from '../utils/api';

const AuthContext = createContext();

export function useAuth() {
  return useContext(AuthContext);
}

// Resolves the authoritative admin flag straight from the Ampache server's
// `adminRole` field (falling back to the reserved-username allowlist only if
// the getUser lookup fails outright), so a role promotion/demotion made on
// the server is always honored — never just trusted from a stale local cache.
async function resolveIsAdmin(username, authParams) {
  let isAdmin = ['admin', 'musicadmin', 'serveradmin'].includes((username || '').toLowerCase());
  try {
    const userRes = await fetch(getAmpacheUrl(`action=getUser&username=${encodeURIComponent(username)}&${authParams}`));
    const userData = await userRes.json();
    const userObj = userData?.['subsonic-response']?.user;
    if (userObj) {
      isAdmin = userObj.adminRole === true || userObj.adminRole === 'true' || userObj.adminRole === 1;
    }
  } catch (e) {}
  return isAdmin;
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Dynamic credential resolution across devices/reloads:
    // Check localStorage first, fallback to sessionStorage
    const storedUser = localStorage.getItem('ampache_user') || sessionStorage.getItem('ampache_user');
    if (storedUser) {
      try {
        const parsed = JSON.parse(storedUser);
        verifyToken(parsed).then(async (isValid) => {
          if (isValid) {
            // Re-check adminRole against the server on every reload instead of
            // trusting the cached flag — otherwise a role change made after the
            // user's last full login never takes effect until they log out/in.
            const authParams = getSubsonicAuthParams(parsed);
            const isAdmin = await resolveIsAdmin(parsed.username, authParams);
            const updated = { ...parsed, isAdmin, role: isAdmin ? 'admin' : 'user' };
            setUser(updated);
            // Ensure persisted across all browser sessions/tabs
            localStorage.setItem('ampache_user', JSON.stringify(updated));
            sessionStorage.setItem('ampache_user', JSON.stringify(updated));
          } else {
            sessionStorage.removeItem('ampache_user');
            localStorage.removeItem('ampache_user');
          }
          setLoading(false);
        });
      } catch (e) {
        sessionStorage.removeItem('ampache_user');
        localStorage.removeItem('ampache_user');
        setLoading(false);
      }
    } else {
      setLoading(false);
    }
  }, []);

  const getAuthParams = (credentials) => {
    return getSubsonicAuthParams(credentials || user);
  };

  const verifyToken = async (credentials) => {
    try {
      const authParams = getSubsonicAuthParams(credentials);
      const res = await fetch(getAmpacheUrl(`action=ping&${authParams}`));
      const data = await res.json();
      return data?.['subsonic-response']?.status === 'ok';
    } catch (err) {
      return false;
    }
  };

  const login = async (username, password) => {
    try {
      const res = await fetch(`${getBaseUrl()}/api/auth/login.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password })
      });
      const data = await res.json();
      if (data.status === 'success') {
        const credentials = {
          username: data.username,
          token: data.token,
          role: data.role,
          isAdmin: data.role === 'admin',
          subsonic_token: data.subsonic_token,
          subsonic_salt: data.subsonic_salt
        };
        setUser(credentials);
        localStorage.setItem('ampache_user', JSON.stringify(credentials));
        sessionStorage.setItem('ampache_user', JSON.stringify(credentials));
        return { success: true };
      } else {
        return { success: false, error: data.message || 'Invalid username or password' };
      }
    } catch (err) {
      return { success: false, error: 'Network error connecting to auth server' };
    }
  };

  const logout = () => {
    setUser(null);
    sessionStorage.removeItem('ampache_user');
    localStorage.removeItem('ampache_user');
  };

  return (
    <AuthContext.Provider value={{ user, login, logout, loading, getAuthParams, getSubsonicAuthParams }}>
      {children}
    </AuthContext.Provider>
  );
}
