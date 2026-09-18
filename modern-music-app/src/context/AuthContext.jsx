import { createContext, useState, useEffect, useContext } from 'react';

import { getAmpacheUrl } from '../utils/api';

const AuthContext = createContext();

export function useAuth() {
  return useContext(AuthContext);
}

// Subsonic hex encoder
function toHex(str) {
  let hex = '';
  for(let i=0; i<str.length; i++) {
    let charHex = str.charCodeAt(i).toString(16);
    if (charHex.length === 1) charHex = '0' + charHex;
    hex += charHex;
  }
  return hex;
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let storedUser = sessionStorage.getItem('ampache_user');
    if (!storedUser && localStorage.getItem('ampache_user')) {
      storedUser = localStorage.getItem('ampache_user');
      localStorage.removeItem('ampache_user');
      if (storedUser) {
        sessionStorage.setItem('ampache_user', storedUser);
      }
    }
    if (storedUser) {
      try {
        const parsed = JSON.parse(storedUser);
        verifyToken(parsed).then(isValid => {
          if (isValid) {
            setUser(parsed);
          } else {
            sessionStorage.removeItem('ampache_user');
          }
          setLoading(false);
        });
      } catch (e) {
        sessionStorage.removeItem('ampache_user');
        setLoading(false);
      }
    } else {
      setLoading(false);
    }
  }, []);

  const getAuthParams = (credentials) => {
    if (!credentials) return '';
    const hexPassword = toHex(credentials.password);
    return `u=${encodeURIComponent(credentials.username)}&p=enc:${hexPassword}&v=1.16.1&c=modern-music-app&f=json`;
  };

  const verifyToken = async (credentials) => {
    try {
      const authParams = getAuthParams(credentials);
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
      sessionStorage.setItem('ampache_user', JSON.stringify(credentials));
      localStorage.removeItem('ampache_user');
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
    <AuthContext.Provider value={{ user, login, logout, loading, getAuthParams }}>
      {children}
    </AuthContext.Provider>
  );
}
