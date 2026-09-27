import os

path = "/Volumes/htdocs/access-to-server/modern-music-app/src/components/StickyPlayer.jsx"
with open(path, "r") as f:
    content = f.read()

target1 = """  const handlePlayPause = () => {
    if (audioContextRef.current && audioContextRef.current.state === 'suspended') {
      audioContextRef.current.resume().catch(() => {});
    }
    togglePlay();
  };"""

replacement1 = """  const handlePlayPause = () => {
    // PERFORMANCE/UX PATCH: Fix iOS AudioContext suspension
    const ctx = audioContextRef.current || window._aetherAudioContext;
    if (ctx && ctx.state === 'suspended') {
      ctx.resume().catch(() => {});
    }
    togglePlay();
  };"""

content = content.replace(target1, replacement1)

target2 = """  // ── Background In-Memory Audio Pre-fetch Buffer (MacBook Pro 2011 Transcoding Eliminator) ──
  useEffect(() => {
    if (!currentTrack || !duration || duration <= 15) return;
    const remainingTime = duration - currentTime;

    // When 15 seconds or less remain on the active track, spawn the hidden in-memory Audio buffer
    if (remainingTime <= 15 && remainingTime > 0) {
      const nextInfo = getNextTrackInfo();
      if (!nextInfo || !nextInfo.track) return;

      const nextTrack = nextInfo.track;

      // Ensure we only trigger once per track transition
      if (String(prefetchedTrackIdRef.current) !== String(nextTrack.id)) {
        console.log(`[Aether Pre-fetch Buffer] 15s remaining (${Math.round(remainingTime)}s). Spawning pre-fetch buffer for next track: "${nextTrack.title}" (ID: ${nextTrack.id})`);
        
        // Discard any previous buffer
        if (prefetchAudioRef.current) {
          prefetchAudioRef.current.pause();
          prefetchAudioRef.current.src = '';
          prefetchAudioRef.current = null;
        }

        const streamUrl = getStreamUrl(nextTrack.id, getAuthParams(user));
        const prefetchAudio = configureAudioElement ? configureAudioElement(new Audio()) : new Audio();
        prefetchAudio.preload = 'auto';
        prefetchAudio.src = streamUrl;

        prefetchAudio.onerror = (e) => {
          console.warn(`[Aether Pre-fetch Buffer] Pre-buffering encountered error for "${nextTrack.title}":`, e);
          prefetchAudioRef.current = null;
          prefetchedTrackIdRef.current = null;
          if (setPrefetchBuffer) setPrefetchBuffer(null, null, -1);
        };

        // Kicks off FLAC on-the-fly transcoding on the 2011 MacBook Pro ahead of time
        prefetchAudio.load();

        prefetchAudioRef.current = prefetchAudio;
        prefetchedTrackIdRef.current = nextTrack.id;

        // Register in PlayerContext so that natural track conclusion triggers 0ms swap
        if (setPrefetchBuffer) {
          setPrefetchBuffer(prefetchAudio, nextTrack, nextInfo.index);
        }
      }
    }
  }, [currentTime, duration, currentTrack?.id, queue, currentIndex, repeatMode, isShuffle, user]);"""

replacement2 = """  // ── Background In-Memory Audio Pre-fetch Buffer (MacBook Pro 2011 Transcoding Eliminator) ──
  // PERFORMANCE PATCH: Keep track of currentTime via Ref to avoid React re-renders every second
  const currentTimeRef = useRef(currentTime);
  useEffect(() => { currentTimeRef.current = currentTime; }, [currentTime]);

  useEffect(() => {
    if (!currentTrack || !duration || duration <= 15) return;

    const intervalId = setInterval(() => {
      const remainingTime = duration - currentTimeRef.current;
      // When 15 seconds or less remain on the active track, spawn the hidden in-memory Audio buffer
      if (remainingTime <= 15 && remainingTime > 0) {
        const nextInfo = getNextTrackInfo();
        if (!nextInfo || !nextInfo.track) return;

        const nextTrack = nextInfo.track;

        // Ensure we only trigger once per track transition
        if (String(prefetchedTrackIdRef.current) !== String(nextTrack.id)) {
          console.log(`[Aether Pre-fetch Buffer] 15s remaining (${Math.round(remainingTime)}s). Spawning pre-fetch buffer for next track: "${nextTrack.title}" (ID: ${nextTrack.id})`);
          
          // Discard any previous buffer
          if (prefetchAudioRef.current) {
            prefetchAudioRef.current.pause();
            prefetchAudioRef.current.src = '';
            prefetchAudioRef.current = null;
          }

          const streamUrl = getStreamUrl(nextTrack.id, getAuthParams(user));
          const prefetchAudio = configureAudioElement ? configureAudioElement(new Audio()) : new Audio();
          prefetchAudio.preload = 'auto';
          prefetchAudio.src = streamUrl;

          prefetchAudio.onerror = (e) => {
            console.warn(`[Aether Pre-fetch Buffer] Pre-buffering encountered error for "${nextTrack.title}":`, e);
            prefetchAudioRef.current = null;
            prefetchedTrackIdRef.current = null;
            if (setPrefetchBuffer) setPrefetchBuffer(null, null, -1);
          };

          // Kicks off FLAC on-the-fly transcoding on the 2011 MacBook Pro ahead of time
          prefetchAudio.load();

          prefetchAudioRef.current = prefetchAudio;
          prefetchedTrackIdRef.current = nextTrack.id;

          // Register in PlayerContext so that natural track conclusion triggers 0ms swap
          if (setPrefetchBuffer) {
            setPrefetchBuffer(prefetchAudio, nextTrack, nextInfo.index);
          }
        }
      }
    }, 1000); // Check once per second via interval instead of React reconcile engine

    return () => clearInterval(intervalId);
  }, [duration, currentTrack?.id, queue, currentIndex, repeatMode, isShuffle, user, getAuthParams, configureAudioElement, setPrefetchBuffer]);"""

content = content.replace(target2, replacement2)

with open(path, "w") as f:
    f.write(content)
print("StickyPlayer.jsx patched.")
