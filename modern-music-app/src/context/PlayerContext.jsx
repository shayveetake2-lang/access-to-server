import { createContext, useState, useEffect, useContext, useRef } from 'react';
import { useAuth } from './AuthContext';
import { getStreamUrl, getCoverArtUrl, getApiProxyUrl } from '../utils/api';

const PlayerContext = createContext();

export function usePlayer() {
  return useContext(PlayerContext);
}

export function PlayerProvider({ children }) {
  const [currentTrack, setCurrentTrack] = useState(null);
  const [queue, setQueue] = useState([]);
  const [currentIndex, setCurrentIndex] = useState(-1);
  const [isPlaying, setIsPlaying] = useState(false);
  const [progress, setProgress] = useState(0);
  const [volume, setVolume] = useState(1);
  const [duration, setDuration] = useState(0);
  const [repeatMode, setRepeatMode] = useState('off'); // off, all, one
  const [isShuffled, setIsShuffled] = useState(false);
  const [errorToast, setErrorToast] = useState(null);
  
  const audioRef = useRef(null);
  const prefetchBufferRef = useRef({ audio: null, track: null, index: -1 });
  const { user, getAuthParams } = useAuth();
  const playNextRef = useRef();
  const recordedTracksRef = useRef(new Set());

  const recordPlayEvent = (track) => {
    if (!track || !track.id) return;
    const trackKey = `${track.id}_${Math.floor(Date.now() / 60000)}`;
    if (recordedTracksRef.current.has(trackKey)) return;
    recordedTracksRef.current.add(trackKey);
    try {
      const uParam = user?.username ? `&u=${encodeURIComponent(user.username)}` : '';
      fetch(`${getApiProxyUrl()}?action=recordPlay&id=${encodeURIComponent(track.id)}${uParam}`).catch(() => {});
    } catch (e) {}
  };

  const updateProgress = () => {
    if (audioRef.current) {
      const cur = audioRef.current.currentTime;
      if (cur > 0) {
        lastKnownTimeRef.current = cur;
      }
      setProgress(cur);
      setDuration(audioRef.current.duration || currentTrackRef.current?.duration || 0);
    }
  };

  // ── Stream Dropout Recovery System ──
  // Prevents premature track advancing when HTML5 <audio> encounters idle timeouts
  // or buffer stalls from the 2011 Mac transcoding stream.

  const lastKnownTimeRef     = useRef(0);
  const reconnectTimerRef    = useRef(null);
  const isReconnectingRef    = useRef(false);
  const stalledCountRef      = useRef(0);
  const currentTrackRef      = useRef(null);
  const userRef              = useRef(null);

  useEffect(() => { currentTrackRef.current = currentTrack; }, [currentTrack]);
  useEffect(() => { userRef.current = user; }, [user]);

  /**
   * Reconnect: retains currentTime, recreates Subsonic stream URL,
   * seeks back to the last known position, and resumes playback instead of skipping.
   */
  const reconnectStream = () => {
    const audio = audioRef.current;
    const track = currentTrackRef.current;
    const activeUser = userRef.current;

    if (!audio || !track || !activeUser || isReconnectingRef.current) return;
    isReconnectingRef.current = true;

    const savedTime = (audio.currentTime && audio.currentTime > 0)
      ? audio.currentTime
      : lastKnownTimeRef.current;

    console.log(`[Aether Audio] Recovering stream for "${track.title}" at ${savedTime.toFixed(1)}s (no track skip)`);

    const freshAuth = getAuthParams(activeUser);
    const streamBase = getStreamUrl(track.id, freshAuth);
    const cacheBuster = `&_retry=${Date.now()}`;
    const newSrc = streamBase.includes('?') ? `${streamBase}${cacheBuster}` : `${streamBase}?${cacheBuster}`;

    audio.pause();
    audio.src = newSrc;
    audio.load();

    const onCanPlay = () => {
      audio.removeEventListener('canplay', onCanPlay);
      audio.removeEventListener('error', onCanPlayError);
      if (savedTime > 0.5) {
        audio.currentTime = savedTime;
      }
      audio.play().then(() => {
        setIsPlaying(true);
        isReconnectingRef.current = false;
        stalledCountRef.current = 0;
        console.log(`[Aether Audio] Resumed playback at ${savedTime.toFixed(1)}s`);
      }).catch(e => {
        console.warn('[Aether Audio] Resume play notice:', e);
        isReconnectingRef.current = false;
      });
    };

    const onCanPlayError = () => {
      audio.removeEventListener('canplay', onCanPlay);
      audio.removeEventListener('error', onCanPlayError);
      isReconnectingRef.current = false;
      console.warn('[Aether Audio] Reconnect failed, retrying in 3s without dropping track...');
      clearTimeout(reconnectTimerRef.current);
      reconnectTimerRef.current = setTimeout(reconnectStream, 3000);
    };

    audio.addEventListener('canplay', onCanPlay, { once: true });
    audio.addEventListener('error', onCanPlayError, { once: true });
  };

  /**
   * 'ended' handler: ONLY advance queue if currentTime >= duration - 1.
   * Premature ended events caused by network disconnects reconnect instead of skipping.
   */
  const handleEnded = () => {
    const audio = audioRef.current;
    if (!audio) return;

    const track = currentTrackRef.current;
    const dur = (audio.duration && isFinite(audio.duration) && audio.duration > 0)
      ? audio.duration
      : (track?.duration || 0);
    const cur = audio.currentTime || 0;

    // Check audio.currentTime against audio.duration. Only advance if currentTime >= duration - 1
    if (dur > 0 && cur < dur - 1) {
      console.warn(`[Aether Audio] Premature 'ended' event at ${cur.toFixed(1)}s / ${dur.toFixed(1)}s. Reconnecting at last position...`);
      lastKnownTimeRef.current = cur;
      reconnectStream();
      return;
    }

    if (playNextRef.current) {
      playNextRef.current();
    }
  };

  /**
   * 'error' handler: Do NOT automatically call nextTrack(). Retain position and reconnect.
   */
  const handleError = () => {
    const audio = audioRef.current;
    if (!audio || !audio.src || audio.src === window.location.href) return;

    const cur = audio.currentTime || 0;
    const track = currentTrackRef.current;
    const dur = (audio.duration && isFinite(audio.duration) && audio.duration > 0)
      ? audio.duration
      : (track?.duration || 0);

    // If genuinely at the end of the song, advance normally
    if (dur > 0 && cur >= dur - 1) {
      if (playNextRef.current) playNextRef.current();
      return;
    }

    console.warn(`[Aether Audio] Stream error mid-song at ${cur.toFixed(1)}s. Retaining position and recovering...`);
    if (cur > 0) {
      lastKnownTimeRef.current = cur;
    }
    clearTimeout(reconnectTimerRef.current);
    reconnectTimerRef.current = setTimeout(reconnectStream, 1200);
  };

  /**
   * 'stalled' handler: Do NOT advance track. Debounce and reconnect if stalled persistently.
   */
  const handleStalled = () => {
    const audio = audioRef.current;
    if (!audio) return;

    const cur = audio.currentTime || 0;
    if (cur > 0) {
      lastKnownTimeRef.current = cur;
    }
    stalledCountRef.current += 1;
    console.log(`[Aether Audio] Stream stalled (count ${stalledCountRef.current}) at ${cur.toFixed(1)}s`);

    if (stalledCountRef.current >= 2) {
      clearTimeout(reconnectTimerRef.current);
      reconnectTimerRef.current = setTimeout(reconnectStream, 2000);
    }
  };

  const handleWaiting = () => {
    const audio = audioRef.current;
    if (audio && audio.currentTime > 0) {
      lastKnownTimeRef.current = audio.currentTime;
    }
    stalledCountRef.current = 0;
  };

  const handlePlaying = () => {
    clearTimeout(reconnectTimerRef.current);
    stalledCountRef.current = 0;
    isReconnectingRef.current = false;
  };

  const attachAudioListeners = (audio) => {
    if (!audio) return;
    audio.addEventListener('timeupdate', updateProgress);
    audio.addEventListener('ended',      handleEnded);
    audio.addEventListener('error',      handleError);
    audio.addEventListener('stalled',    handleStalled);
    audio.addEventListener('waiting',    handleWaiting);
    audio.addEventListener('playing',    handlePlaying);
  };

  const removeAudioListeners = (audio) => {
    if (!audio) return;
    audio.removeEventListener('timeupdate', updateProgress);
    audio.removeEventListener('ended',      handleEnded);
    audio.removeEventListener('error',      handleError);
    audio.removeEventListener('stalled',    handleStalled);
    audio.removeEventListener('waiting',    handleWaiting);
    audio.removeEventListener('playing',    handlePlaying);
  };

  useEffect(() => {
    if (!audioRef.current) {
      audioRef.current = new Audio();
      audioRef.current.volume = volume;
    }

    attachAudioListeners(audioRef.current);

    return () => {
      removeAudioListeners(audioRef.current);
      clearTimeout(reconnectTimerRef.current);
    };
  }, []);

  const swapAudio = (newAudio, nextTrack, nextIndex) => {
    if (!newAudio || !nextTrack) return;

    const oldAudio = audioRef.current;
    if (oldAudio) {
      oldAudio.pause();
      removeAudioListeners(oldAudio);
      oldAudio.src = '';
    }

    audioRef.current = newAudio;
    audioRef.current.volume = volume;
    attachAudioListeners(audioRef.current);

    setCurrentTrack(nextTrack);
    setCurrentIndex(nextIndex);
    setIsPlaying(true);
    setProgress(newAudio.currentTime || 0);
    setDuration(newAudio.duration || nextTrack.duration || 0);

    const playPromise = newAudio.play();
    if (playPromise !== undefined) {
      playPromise.catch(e => console.warn("[Aether Audio] Play notice on swapped audio:", e));
    }
    recordPlayEvent(nextTrack);
  };

  const setPrefetchBuffer = (audio, track, index) => {
    prefetchBufferRef.current = { audio, track, index };
  };

  // Lock-screen / Media Session integration for iOS Safari and mobile browsers
  useEffect(() => {
    if (typeof window !== 'undefined' && 'mediaSession' in navigator && currentTrack) {
      try {
        const coverUrl = currentTrack.coverArt ? getCoverArtUrl(currentTrack.coverArt, getAuthParams(user)) : '';
        navigator.mediaSession.metadata = new window.MediaMetadata({
          title: currentTrack.title || 'Unknown Track',
          artist: currentTrack.artist || 'Unknown Artist',
          album: currentTrack.album || 'Aether Audio',
          artwork: coverUrl ? [{ src: coverUrl, sizes: '512x512', type: 'image/jpeg' }] : []
        });

        navigator.mediaSession.setActionHandler('play', () => {
          if (audioRef.current) {
            audioRef.current.play().catch(() => {});
            setIsPlaying(true);
          }
        });
        navigator.mediaSession.setActionHandler('pause', () => {
          if (audioRef.current) {
            audioRef.current.pause();
            setIsPlaying(false);
          }
        });
        navigator.mediaSession.setActionHandler('previoustrack', () => {
          playPrevious();
        });
        navigator.mediaSession.setActionHandler('nexttrack', () => {
          playNext();
        });
      } catch (err) {
        console.debug('MediaSession error:', err);
      }
    }
  }, [currentTrack, user]);

  const loadTrack = (track) => {
    if (!track || !user) return;
    setCurrentTrack(track);
    if (!audioRef.current) {
      audioRef.current = new Audio();
      audioRef.current.volume = volume;
      attachAudioListeners(audioRef.current);
    }
    lastKnownTimeRef.current = 0;
    stalledCountRef.current = 0;
    isReconnectingRef.current = false;
    audioRef.current.src = getStreamUrl(track.id, getAuthParams(user));
    audioRef.current.play().catch(e => console.log("Autoplay blocked or error"));
    setIsPlaying(true);
    recordPlayEvent(track);
  };

  const playQueue = (tracks, index = 0) => {
    setQueue(tracks);
    setCurrentIndex(index);
    loadTrack(tracks[index]);
  };

  const togglePlay = () => {
    if (audioRef.current) {
      if (isPlaying) {
        audioRef.current.pause();
      } else {
        audioRef.current.play().catch(e => console.log("Play error"));
      }
      setIsPlaying(!isPlaying);
    }
  };

  const playNext = () => {
    if (queue.length === 0) return;
    
    if (repeatMode === 'one') {
      if (audioRef.current) {
        audioRef.current.currentTime = 0;
        audioRef.current.play().catch(() => {});
      }
      return;
    }

    let nextIndex;
    if (isShuffled) {
      nextIndex = Math.floor(Math.random() * queue.length);
    } else {
      nextIndex = currentIndex + 1;
      if (nextIndex >= queue.length) {
        if (repeatMode === 'all') {
          nextIndex = 0;
        } else {
          setIsPlaying(false);
          return;
        }
      }
    }
    
    const nextTrack = queue[nextIndex];
    if (!nextTrack) return;

    // Zero-latency instant swap if pre-fetched buffer matches next track
    const prefetched = prefetchBufferRef.current;
    if (prefetched.audio && prefetched.track && prefetched.track.id === nextTrack.id) {
      console.log(`[Aether Audio] Zero-latency track transition using pre-fetched buffer for: "${nextTrack.title}"`);
      const readyAudio = prefetched.audio;
      prefetchBufferRef.current = { audio: null, track: null, index: -1 };
      swapAudio(readyAudio, nextTrack, nextIndex);
      return;
    }

    setCurrentIndex(nextIndex);
    loadTrack(nextTrack);
  };

  playNextRef.current = playNext;

  const playPrevious = () => {
    if (queue.length === 0) return;
    
    if (audioRef.current.currentTime > 3) {
      audioRef.current.currentTime = 0;
      return;
    }

    let prevIndex = currentIndex - 1;
    if (prevIndex < 0) {
      if (repeatMode === 'all') {
        prevIndex = queue.length - 1;
      } else {
        prevIndex = 0;
      }
    }

    setCurrentIndex(prevIndex);
    loadTrack(queue[prevIndex]);
  };

  const seek = (time) => {
    if (audioRef.current) {
      audioRef.current.currentTime = time;
      setProgress(time);
    }
  };

  const toggleRepeat = () => {
    setRepeatMode(prev => prev === 'off' ? 'all' : prev === 'all' ? 'one' : 'off');
  };

  const toggleShuffle = () => {
    setIsShuffled(!isShuffled);
  };

  const addToQueue = (track) => {
    if (!currentTrack || queue.length === 0) {
      playQueue([track], 0);
    } else {
      const newQueue = [...queue];
      const insertIndex = currentIndex + 1;
      newQueue.splice(insertIndex, 0, track);
      setQueue(newQueue);
    }
  };

  const removeFromQueue = (index) => {
    if (index === currentIndex) {
      playNext();
    }
    const newQueue = [...queue];
    newQueue.splice(index, 1);
    setQueue(newQueue);
    if (index < currentIndex) {
      setCurrentIndex(currentIndex - 1);
    }
  };

  const reorderQueue = (startIndex, endIndex) => {
    const result = Array.from(queue);
    const [removed] = result.splice(startIndex, 1);
    result.splice(endIndex, 0, removed);

    if (startIndex === currentIndex) {
      setCurrentIndex(endIndex);
    } else if (startIndex < currentIndex && endIndex >= currentIndex) {
      setCurrentIndex(currentIndex - 1);
    } else if (startIndex > currentIndex && endIndex <= currentIndex) {
      setCurrentIndex(currentIndex + 1);
    }

    setQueue(result);
  };

  const clearQueue = () => {
    if (currentTrack) {
      setQueue([currentTrack]);
      setCurrentIndex(0);
    } else {
      setQueue([]);
      setCurrentIndex(-1);
    }
  };

  const skipToQueueIndex = (index) => {
    if (index >= 0 && index < queue.length) {
      setCurrentIndex(index);
      loadTrack(queue[index]);
    }
  };

  const setAudioVolume = (val) => {
    const clamped = Math.max(0, Math.min(1, val));
    setVolume(clamped);
    if (audioRef.current) {
      audioRef.current.volume = clamped;
    }
  };

  // Desktop / Laptop Keyboard Shortcuts
  useEffect(() => {
    const handleKeyDown = (e) => {
      const target = e.target;
      if (
        target.tagName === 'INPUT' ||
        target.tagName === 'TEXTAREA' ||
        target.isContentEditable
      ) {
        return;
      }

      if (e.code === 'Space') {
        e.preventDefault();
        togglePlay();
      } else if (e.key === 'm' || e.key === 'M') {
        e.preventDefault();
        setAudioVolume(volume === 0 ? 0.8 : 0);
      } else if (e.key === 'ArrowRight') {
        if (e.shiftKey) {
          e.preventDefault();
          playNext();
        } else if (audioRef.current) {
          e.preventDefault();
          seek(Math.min((duration || 0), (audioRef.current.currentTime || 0) + 5));
        }
      } else if (e.key === 'ArrowLeft') {
        if (e.shiftKey) {
          e.preventDefault();
          playPrevious();
        } else if (audioRef.current) {
          e.preventDefault();
          seek(Math.max(0, (audioRef.current.currentTime || 0) - 5));
        }
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        setAudioVolume(Math.min(1, volume + 0.05));
      } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        setAudioVolume(Math.max(0, volume - 0.05));
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isPlaying, volume, duration, currentIndex, queue, repeatMode]);

  return (
    <PlayerContext.Provider 
      value={{ 
        currentTrack, 
        isPlaying, 
        progress, 
        volume, 
        setVolume: setAudioVolume,
        duration, 
        repeatMode,
        isShuffled,
        queue,
        currentIndex,
        togglePlay, 
        playNext, 
        playPrevious, 
        seek,
        playQueue,
        toggleRepeat,
        toggleShuffle,
        addToQueue,
        removeFromQueue,
        reorderQueue,
        clearQueue,
        skipToQueueIndex,
        audioRef,
        swapAudio,
        setPrefetchBuffer
      }}
    >
      {children}
      {errorToast && (
        <div className="fixed top-24 left-1/2 -translate-x-1/2 bg-red-500 text-white px-6 py-3 rounded-full shadow-2xl z-50 animate-in slide-in-from-top-4 font-medium flex items-center gap-2">
          {errorToast}
        </div>
      )}
    </PlayerContext.Provider>
  );
}
