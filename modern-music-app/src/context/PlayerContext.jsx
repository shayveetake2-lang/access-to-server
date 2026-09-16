import { createContext, useState, useEffect, useContext, useRef } from 'react';
import { useAuth } from './AuthContext';

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
  const { user, getAuthParams } = useAuth();

  useEffect(() => {
    if (!audioRef.current) {
      audioRef.current = new Audio();
      audioRef.current.volume = volume;
    }

    const audio = audioRef.current;

    const updateProgress = () => {
      setProgress(audio.currentTime);
      setDuration(audio.duration || 0);
    };

    const handleEnded = () => {
      playNext();
    };

    const handleError = () => {
      if (audio.src) {
        setErrorToast(`Song unavailable: Skipping track...`);
        setTimeout(() => setErrorToast(null), 3000);
        // Add a slight delay before skipping to prevent rapid error loops
        setTimeout(() => {
          playNext();
        }, 1000);
      }
    };

    audio.addEventListener('timeupdate', updateProgress);
    audio.addEventListener('ended', handleEnded);
    audio.addEventListener('error', handleError);

    return () => {
      audio.removeEventListener('timeupdate', updateProgress);
      audio.removeEventListener('ended', handleEnded);
      audio.removeEventListener('error', handleError);
    };
  }, [currentIndex, queue, repeatMode, isShuffled]);

  const loadTrack = (track) => {
    if (!track || !user) return;
    setCurrentTrack(track);
    audioRef.current.src = `/ampache/public/rest/index.php?action=stream&id=${track.id}&${getAuthParams(user)}`;
    audioRef.current.play().catch(e => console.log("Autoplay blocked or error"));
    setIsPlaying(true);
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
      audioRef.current.currentTime = 0;
      audioRef.current.play();
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
    
    setCurrentIndex(nextIndex);
    loadTrack(queue[nextIndex]);
  };

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
    if (!currentTrack) {
      playQueue([track], 0);
    } else {
      setQueue([...queue, track]);
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

  return (
    <PlayerContext.Provider 
      value={{ 
        currentTrack, 
        isPlaying, 
        progress, 
        volume, 
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
        reorderQueue
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
