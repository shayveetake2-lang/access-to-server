import { createContext, useState, useEffect, useContext } from 'react';
import { getAmpacheUrl, getSubsonicAuthParams } from '../utils/api';

const AuthContext = createContext();

export function useAuth() {
  return useContext(AuthContext);
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
        verifyToken(parsed).then(isValid => {
          if (isValid) {
            setUser(parsed);
            // Ensure persisted across all browser sessions/tabs
            localStorage.setItem('ampache_user', storedUser);
            sessionStorage.setItem('ampache_user', storedUser);
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
    const credentials = { 
      username, 
      password, 
      isAdmin: username.toLowerCase() === 'admin' 
    };
    const isValid = await verifyToken(credentials);
    if (isValid) {
      setUser(credentials);
      // Persist across devices and browser sessions
      localStorage.setItem('ampache_user', JSON.stringify(credentials));
      sessionStorage.setItem('ampache_user', JSON.stringify(credentials));
      return { success: true };
    }
    return { success: false, error: 'Invalid username or password' };
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
