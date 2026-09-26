import { createContext, useState, useEffect, useCallback, useContext } from 'react';
import { useAuth } from './AuthContext';
import { useToast } from './ToastContext';
import { getAmpacheUrl, getSubsonicAuthParams, getApiProxyUrl } from '../utils/api';

const LikedSongsContext = createContext();

export function useLikedSongs() {
  return useContext(LikedSongsContext);
}

const LS_KEY = 'aether_liked_ids';

export function LikedSongsProvider({ children }) {
  const { user } = useAuth();
  const { showToast } = useToast();

  // Set of subsonic song IDs (strings) that are starred
  const [likedIds, setLikedIds] = useState(() => {
    try {
      const raw = localStorage.getItem(LS_KEY);
      return raw ? new Set(JSON.parse(raw)) : new Set();
    } catch {
      return new Set();
    }
  });

  // Starred songs list (full track objects) for display in the playlist component
  const [likedSongs, setLikedSongs] = useState([]);
  const [loadingStarred, setLoadingStarred] = useState(false);

  // Sync likedIds to localStorage whenever they change
  useEffect(() => {
    try {
      localStorage.setItem(LS_KEY, JSON.stringify([...likedIds]));
    } catch {}
  }, [likedIds]);

  // On user login/change, fetch the full starred list from Subsonic
  useEffect(() => {
    if (!user) {
      setLikedIds(new Set());
      setLikedSongs([]);
      return;
    }
    fetchStarred();
  }, [user]);

  const fetchStarred = useCallback(async () => {
    if (!user) return;
    setLoadingStarred(true);
    const uParam = user.username ? `&u=${encodeURIComponent(user.username)}` : '';
    const cacheBust = `_t=${Date.now()}`;

    // 1. Try high-speed database proxy first
    try {
      const res = await fetch(`${getApiProxyUrl()}?action=getStarred2${uParam}&${cacheBust}`, { cache: 'no-store' });
      const data = await res.json();
      if (data?.status === 'ok') {
        const raw = data.songs || data['subsonic-response']?.starred2?.song;
        const songs = (Array.isArray(raw) ? raw : (raw ? [raw] : [])).filter(Boolean);
        setLikedSongs(songs);
        setLikedIds(new Set(songs.filter(s => s && s.id).map(s => String(s.id))));
        setLoadingStarred(false);
        return;
      }
    } catch (proxyErr) {
      console.debug('fetchStarred proxy fallback:', proxyErr);
    }

    // 2. Subsonic fallback
    try {
      const auth = getSubsonicAuthParams(user, true);
      const res = await fetch(getAmpacheUrl(`action=getStarred2&${auth}`));
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        const raw = data['subsonic-response']?.starred2?.song;
        const songs = (Array.isArray(raw) ? raw : (raw ? [raw] : [])).filter(Boolean);
        setLikedSongs(songs);
        setLikedIds(new Set(songs.filter(s => s && s.id).map(s => String(s.id))));
      }
    } catch (err) {
      console.debug('fetchStarred error:', err);
    } finally {
      setLoadingStarred(false);
    }
  }, [user]);

  const isLiked = useCallback((songId) => {
    if (!songId) return false;
    const strId = String(songId).trim();
    const cleanNum = parseInt(strId.replace(/\D/g, ''), 10);
    const normalized = cleanNum > 0 && cleanNum < 100000000 ? String(300000000 + cleanNum) : strId;
    const rawNum = cleanNum >= 300000000 ? String(cleanNum % 100000000) : strId;
    return likedIds.has(strId) || likedIds.has(normalized) || likedIds.has(rawNum);
  }, [likedIds]);

  const toggleLike = useCallback(async (song) => {
    if (!user || !song) return;
    const id = String(song.id);
    const wasLiked = likedIds.has(id);
    const action = wasLiked ? 'unstar' : 'star';

    // Optimistic update
    setLikedIds(prev => {
      const next = new Set(prev);
      if (wasLiked) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });

    if (!wasLiked) {
      setLikedSongs(prev => {
        if (prev.some(s => String(s.id) === id)) return prev;
        return [song, ...prev];
      });
    } else {
      setLikedSongs(prev => prev.filter(s => String(s.id) !== id));
    }

    let success = false;
    const uParam = user.username ? `&u=${encodeURIComponent(user.username)}` : '';

    // 1. Try high-speed database proxy first (immediate persistence in user_flag)
    try {
      const pRes = await fetch(`${getApiProxyUrl()}?action=${action}&id=${encodeURIComponent(id)}${uParam}`);
      const pData = await pRes.json();
      if (pData?.status === 'ok') {
        success = true;
      }
    } catch (e) {
      console.debug('toggleLike proxy notice:', e);
    }

    // 2. Subsonic fallback if proxy did not succeed
    if (!success) {
      try {
        const auth = getSubsonicAuthParams(user, true);
        const res = await fetch(getAmpacheUrl(`action=${action}&id=${encodeURIComponent(id)}&${auth}`));
        const data = await res.json();
        if (data?.['subsonic-response']?.status === 'ok') {
          success = true;
        }
      } catch (err) {
        console.debug('toggleLike Subsonic fallback notice:', err);
      }
    }

    if (!success) {
      // Revert optimistic update on failure
      setLikedIds(prev => {
        const next = new Set(prev);
        if (wasLiked) {
          next.add(id);
        } else {
          next.delete(id);
        }
        return next;
      });
      if (!wasLiked) {
        setLikedSongs(prev => prev.filter(s => String(s.id) !== id));
      } else {
        setLikedSongs(prev => [song, ...prev]);
      }
      showToast('Failed to update liked songs', 'error');
    } else {
      showToast(wasLiked ? '💔 Removed from Liked Songs' : '❤️ Added to Liked Songs', 'success');
    }
  }, [user, likedIds, showToast]);

  return (
    <LikedSongsContext.Provider value={{ isLiked, toggleLike, likedSongs, likedIds, loadingStarred, fetchStarred }}>
      {children}
    </LikedSongsContext.Provider>
  );
}

