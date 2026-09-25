import { formatDuration } from '../utils/formatters';
import { useState, useRef, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Play, Pause, SkipBack, SkipForward, Repeat, Shuffle, ListVideo, Music, Repeat1, ChevronUp, ChevronDown, Trash2, X, Volume2, Volume1, VolumeX, Plus } from 'lucide-react';
import { usePlayer } from '../context/PlayerContext';
import { useAuth } from '../context/AuthContext';
import { usePlaylistModal } from '../context/PlaylistModalContext';
import { getCoverArtUrl, getStreamUrl, getSubsonicAuthParams, DEFAULT_COVER_ART } from '../utils/api';
import OutputMenu from './OutputSelector';
import HeartButton from './HeartButton';

const getArtistId = (track) => {
  return track?.artistId || track?.artist_id || null;
};

const getAlbumId = (track) => {
  if (track?.albumId) return track.albumId;
  if (track?.album_id) return track.album_id;
  if (track?.parent) return track.parent;
  if (typeof track?.coverArt === 'string' && track.coverArt.startsWith('al-')) {
    return track.coverArt.replace(/^al-/, '');
  }
  return null;
};

export default function StickyPlayer() {
  const navigate = useNavigate();
  const [isQueueOpen, setIsQueueOpen] = useState(false);
  const [isExpanded, setIsExpanded] = useState(false);
  const { user, getAuthParams } = useAuth();
  const { openAddToPlaylistModal } = usePlaylistModal();
  const { 
    currentTrack, isPlaying, progress: currentTime, duration, 
    isShuffled: isShuffle, repeatMode, 
    queue, currentIndex, removeFromQueue, reorderQueue, clearQueue,
    skipToQueueIndex,
    togglePlay, playNext, playPrevious: playPrev, toggleShuffle, toggleRepeat, seek,
    volume, setVolume,
    swapAudio, setPrefetchBuffer, configureAudioElement, audioRef
  } = usePlayer();
  const prevVolumeRef = useRef(volume || 0.8);

  // ── Web Audio Loudness Normalizer ──────────────────────────────────────────
  // Routes the <audio> element through a DynamicsCompressorNode (+ makeup gain
  // + safety limiter) so the 10k+ song library plays back at a consistent
  // perceived volume regardless of how the source files were originally mastered.
  const audioContextRef   = useRef(null);
  const compressorRef     = useRef(null);
  const makeupGainRef     = useRef(null);
  const limiterRef        = useRef(null);
  const sourceNodeRef     = useRef(null);
  const hookedAudioElRef  = useRef(null);

  const setupNormalizerGraph = () => {
    const audioEl = audioRef?.current;
    if (!audioEl) return;

    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (!AudioContextClass || isIOS) return; // Web Audio unsupported or bypassed on iOS (due to physical mute switch & createMediaElementSource bugs)

    if (!window._aetherAudioContext || window._aetherAudioContext.state === 'closed') {
      const ctx = new AudioContextClass();
      window._aetherAudioContext = ctx;

      // Apply saved output device to the newly created AudioContext if supported
      const savedSinkId = typeof window !== 'undefined' ? localStorage.getItem('aether_audio_sink_id') : null;
      if (savedSinkId && typeof ctx.setSinkId === 'function') {
        ctx.setSinkId(savedSinkId === 'default' ? '' : savedSinkId).catch(() => {});
      }
    }
    const ctx = window._aetherAudioContext;
    audioContextRef.current = ctx;

    if (!compressorRef.current) {
      // Stage 1: leveling compressor — aggressively narrows the dynamic range so
      // quiet passages and loud passages end up much closer in output level.
      const compressor = ctx.createDynamicsCompressor();
      compressor.threshold.setValueAtTime(-50, ctx.currentTime);
      compressor.knee.setValueAtTime(30, ctx.currentTime);
      compressor.ratio.setValueAtTime(12, ctx.currentTime);
      compressor.attack.setValueAtTime(0.003, ctx.currentTime);
      compressor.release.setValueAtTime(0.25, ctx.currentTime);

      // Stage 2: makeup gain — since the compressor pulls the whole signal down,
      // boost it back up so quiet tracks are actually audible at a normal level.
      const makeupGain = ctx.createGain();
      makeupGain.gain.setValueAtTime(1.6, ctx.currentTime);

      // Stage 3: brick-wall safety limiter — prevents makeup gain from clipping
      // on already-loud, poorly-mastered tracks.
      const limiter = ctx.createDynamicsCompressor();
      limiter.threshold.setValueAtTime(-3, ctx.currentTime);
      limiter.knee.setValueAtTime(0, ctx.currentTime);
      limiter.ratio.setValueAtTime(20, ctx.currentTime);
      limiter.attack.setValueAtTime(0.001, ctx.currentTime);
      limiter.release.setValueAtTime(0.1, ctx.currentTime);

      compressor.connect(makeupGain);
      makeupGain.connect(limiter);
      limiter.connect(ctx.destination);

      compressorRef.current = compressor;
      makeupGainRef.current = makeupGain;
      limiterRef.current = limiter;
    }

    // Re-bind whenever the active <audio> element instance changes (e.g. the
    // zero-latency prefetch-buffer swap creates a brand new HTMLAudioElement).
    if (hookedAudioElRef.current !== audioEl) {
      try {
        // Disconnect the previous element's source node first so swapped-out
        // <audio> elements (and their nodes) don't linger and leak memory.
        if (sourceNodeRef.current) {
          sourceNodeRef.current.disconnect();
          sourceNodeRef.current = null;
        }
        // An HTMLMediaElement can only be bound to createMediaElementSource once in its lifetime.
        // Cache it on the audio element instance so React Strict Mode or navigation does not recreate it.
        if (!audioEl._sourceNode) {
          audioEl._sourceNode = ctx.createMediaElementSource(audioEl);
        }
        audioEl._sourceNode.disconnect();
        audioEl._sourceNode.connect(compressorRef.current);
        sourceNodeRef.current = audioEl._sourceNode;
        hookedAudioElRef.current = audioEl;
      } catch (err) {
        console.debug('[Aether Audio] Normalizer graph bind notice:', err);
      }
    }

    if (ctx.state === 'suspended') {
      ctx.resume().catch(() => {});
    }
  };

  // (Re)attach the normalizer graph whenever the active track/audio element changes
  useEffect(() => {
    setupNormalizerGraph();
  }, [currentTrack?.id]);

  // Browser autoplay policies require the AudioContext to resume from a real user
  // gesture — wire up one-time listeners that resume it on the first interaction.
  useEffect(() => {
    const resumeOnGesture = () => {
      setupNormalizerGraph();
      const ctx = audioContextRef.current || window._aetherAudioContext;
      if (ctx && ctx.state === 'suspended') {
        ctx.resume().catch(() => {});
      }
    };
    
    // Expose for synchronous calls in PlayerContext (e.g. from track rows)
    window.resumeAetherAudio = resumeOnGesture;
    
    const gestureEvents = ['pointerdown', 'keydown', 'touchstart'];
    gestureEvents.forEach(evt => window.addEventListener(evt, resumeOnGesture, { passive: true }));
    return () => {
      gestureEvents.forEach(evt => window.removeEventListener(evt, resumeOnGesture));
      delete window.resumeAetherAudio;
    };
  }, []);

  // Ensure iOS lock screen displays previous/next track icons instead of 10s skip icons
  useEffect(() => {
    if ('mediaSession' in navigator) {
      navigator.mediaSession.setActionHandler('previoustrack', () => playPrev());
      navigator.mediaSession.setActionHandler('nexttrack', () => playNext());
      
      // Explicitly unbind seek handlers to force iOS to show the track skip icons
      navigator.mediaSession.setActionHandler('seekbackward', null);
      navigator.mediaSession.setActionHandler('seekforward', null);
    }
  }, [playNext, playPrev]);

  // Tear down node connections on unmount but preserve AudioContext so it survives remounts
  useEffect(() => {
    return () => {
      try {
        sourceNodeRef.current?.disconnect();
        compressorRef.current?.disconnect();
        makeupGainRef.current?.disconnect();
        limiterRef.current?.disconnect();
      } catch (err) {
        console.debug('[Aether Audio] Normalizer graph teardown notice:', err);
      }
    };
  }, []);

  // Hidden in-memory Audio prefetch buffer refs
  const prefetchAudioRef = useRef(null);
  const prefetchedTrackIdRef = useRef(null);

  const getNextTrackInfo = () => {
    if (!queue || queue.length === 0) return null;
    if (repeatMode === 'one') {
      return { track: currentTrack, index: currentIndex };
    }
    let nextIndex;
    if (isShuffle) {
      if (queue.length > 1) {
        do {
          nextIndex = Math.floor(Math.random() * queue.length);
        } while (nextIndex === currentIndex);
      } else {
        nextIndex = 0;
      }
    } else {
      nextIndex = currentIndex + 1;
      if (nextIndex >= queue.length) {
        if (repeatMode === 'all') {
          nextIndex = 0;
        } else {
          return null;
        }
      }
    }
    return { track: queue[nextIndex], index: nextIndex };
  };

  // ── Background In-Memory Audio Pre-fetch Buffer (MacBook Pro 2011 Transcoding Eliminator) ──
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

        const streamUrl = getStreamUrl(nextTrack.id, getSubsonicAuthParams(user));
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
  }, [currentTime, duration, currentTrack?.id, queue, currentIndex, repeatMode, isShuffle, user]);

  // Clean up stale prefetch buffer when track changes or component unmounts
  useEffect(() => {
    if (prefetchedTrackIdRef.current && String(prefetchedTrackIdRef.current) !== String(currentTrack?.id)) {
      // If we manually seek/skip to a track OTHER than what we buffered, nuke the buffer
      const nextInfo = getNextTrackInfo();
      if (!nextInfo || String(nextInfo.track?.id) !== String(prefetchedTrackIdRef.current)) {
        if (prefetchAudioRef.current) {
          prefetchAudioRef.current.pause();
          prefetchAudioRef.current.src = '';
          prefetchAudioRef.current = null;
        }
        prefetchedTrackIdRef.current = null;
        if (setPrefetchBuffer) setPrefetchBuffer(null, null, -1);
      }
    }
  }, [currentTrack?.id]);

  useEffect(() => {
    return () => {
      if (prefetchAudioRef.current) {
        prefetchAudioRef.current.pause();
        prefetchAudioRef.current.src = '';
        prefetchAudioRef.current = null;
      }
    };
  }, []);

  const handleSkipNext = () => {
    const nextInfo = getNextTrackInfo();
    if (!nextInfo || !nextInfo.track) {
      playNext();
      return;
    }

    const bufferedAudio = prefetchAudioRef.current;
    if (bufferedAudio && String(prefetchedTrackIdRef.current) === String(nextInfo.track.id)) {
      console.log(`[Aether Pre-fetch Buffer] Instant zero-latency Skip triggered for: "${nextInfo.track.title}"`);
      prefetchAudioRef.current = null;
      prefetchedTrackIdRef.current = null;
      if (setPrefetchBuffer) setPrefetchBuffer(null, null, -1);
      swapAudio(bufferedAudio, nextInfo.track, nextInfo.index);
    } else {
      playNext();
    }
  };

  const handlePlayPause = () => {
    if (audioContextRef.current && audioContextRef.current.state === 'suspended') {
      audioContextRef.current.resume().catch(() => {});
    }
    togglePlay();
  };

  const handleToggleMute = () => {
    if (volume > 0) {
      prevVolumeRef.current = volume;
      setVolume(0);
    } else {
      setVolume(prevVolumeRef.current || 0.8);
    }
  };

  const handleVolumeChange = (e) => {
    const rect = e.currentTarget.getBoundingClientRect();
    const pos = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
    setVolume(pos);
    if (pos > 0) prevVolumeRef.current = pos;
  };

  if (!currentTrack) {
    return (
      <div className="hidden md:flex fixed bottom-4 left-72 right-8 h-20 bg-slate-900/70 backdrop-blur-xl border border-white/10 rounded-2xl items-center justify-center px-6 shadow-[0_10px_40px_rgba(0,0,0,0.5)] z-30">
        <div className="text-slate-400 font-medium flex items-center gap-2 text-sm">
          <Music size={16} /> Select a track to start listening
        </div>
      </div>
    );
  }

  const formatTime = (time) => {
    if (!time || isNaN(time)) return "0:00";
    const min = Math.floor(time / 60);
    const sec = Math.floor(time % 60).toString().padStart(2, '0');
    return `${min}:${sec}`;
  };

  const handleProgressClick = (e) => {
    const rect = e.currentTarget.getBoundingClientRect();
    const pos = (e.clientX - rect.left) / rect.width;
    seek(pos * duration);
  };

  const artistId = getArtistId(currentTrack);
  const albumId = getAlbumId(currentTrack);

  const handleNavigateArtist = (e) => {
    e?.stopPropagation?.();
    if (artistId) {
      setIsExpanded(false);
      navigate(`/artists/${artistId}`);
    }
  };

  const handleNavigateAlbum = (e) => {
    e?.stopPropagation?.();
    if (albumId) {
      setIsExpanded(false);
      navigate(`/albums/${albumId}`);
    }
  };

  const progressPercent = duration > 0 ? (currentTime / duration) * 100 : 0;

  return (
    <>
      {/* ========================================================================= */}
      {/* MOBILE MINI-PLAYER (Docked above MobileNav)                               */}
      {/* ========================================================================= */}
      <div 
        onClick={() => setIsExpanded(true)}
        className="md:hidden fixed bottom-[calc(max(env(safe-area-inset-bottom,0px),0.5rem)+4.0rem)] left-2.5 right-2.5 min-h-[80px] bg-slate-900/95 backdrop-blur-2xl border border-white/10 rounded-2xl flex items-center justify-between px-3 shadow-[0_8px_30px_rgba(0,0,0,0.7)] z-40 cursor-pointer select-none active:scale-[0.99] transition-transform"
      >
        {/* Top hairline progress bar */}
        <div className="absolute top-0 left-0 right-0 h-[2.5px] bg-white/10 rounded-t-2xl overflow-hidden">
          <div 
            className="h-full bg-gradient-to-r from-purple-500 to-indigo-400 transition-all duration-150" 
            style={{ width: `${progressPercent}%` }} 
          />
        </div>

        {/* Thumbnail + Titles */}
        <div className="flex items-center gap-2.5 min-w-0 flex-1 pr-2">
          {/* Clickable Album Cover Art */}
          <div 
            onClick={albumId ? handleNavigateAlbum : undefined}
            className={`w-10 h-10 min-w-[40px] flex-shrink-0 rounded-xl bg-slate-800 shrink-0 overflow-hidden shadow-sm flex items-center justify-center border border-white/5 relative group ${albumId ? 'cursor-pointer active:scale-95 transition-transform' : ''}`}
            title={albumId ? `View Album (${currentTrack.album || 'Tracklist'})` : 'Cover Art'}
          >
            {currentTrack.coverArt ? (
              <img 
                src={getCoverArtUrl(currentTrack.coverArt, getAuthParams(user))} 
                className="w-full h-full object-cover" 
                alt="" 
                onError={(e) => {
                  if (e.currentTarget.src !== DEFAULT_COVER_ART) {
                    e.currentTarget.src = DEFAULT_COVER_ART;
                  }
                }}
              />
            ) : (
              <Music size={18} className="text-purple-400" />
            )}
          </div>

          <div className="min-w-0 flex-1">
            <div className="text-xs font-semibold text-white truncate">{currentTrack.title}</div>
            {artistId ? (
              <div 
                onClick={handleNavigateArtist}
                className="text-[11px] text-purple-300 hover:text-purple-200 active:underline truncate mt-0.5 cursor-pointer font-medium"
                title="Go to Artist Profile"
              >
                {currentTrack.artist || 'Unknown Artist'}
              </div>
            ) : (
              <div className="text-[11px] text-slate-400 truncate mt-0.5">
                {currentTrack.artist || 'Unknown Artist'}
              </div>
            )}
          </div>
        </div>

        {/* Action Buttons: Output Selector + Like + Add to Playlist + Play/Pause & Next Button */}
        <div className="flex items-center gap-1 shrink-0 flex-shrink-0 min-w-[120px]" onClick={(e) => e.stopPropagation()}>
          <OutputMenu compact align="right" />
          <HeartButton song={currentTrack} size={18} compact className="hidden sm:flex w-12 h-12 rounded-full hover:bg-white/5 items-center justify-center" />
          <button 
            onClick={() => openAddToPlaylistModal(currentTrack.id)} 
            className="hidden sm:flex w-12 h-12 text-slate-300 hover:text-purple-300 active:scale-90 transition-all rounded-full hover:bg-white/5 items-center justify-center"
            title="Add to Playlist"
            aria-label="Add to Playlist"
          >
            <Plus size={18} />
          </button>
          <button 
            onClick={handlePlayPause} 
            className="w-12 h-12 rounded-full bg-purple-500 active:bg-purple-400 text-white flex items-center justify-center shadow-md active:scale-95 transition-all"
            aria-label={isPlaying ? 'Pause' : 'Play'}
          >
            {isPlaying ? <Pause size={17} /> : <Play size={17} className="ml-0.5" />}
          </button>
          <button 
            onClick={handleSkipNext} 
            className="w-12 h-12 text-slate-400 hover:text-white active:scale-90 transition-all flex items-center justify-center"
            aria-label="Next track"
          >
            <SkipForward size={19} />
          </button>
        </div>
      </div>

      {/* ========================================================================= */}
      {/* MOBILE FULL-SCREEN NOW PLAYING SHEET                                      */}
      {/* ========================================================================= */}
      {isExpanded && (
        <div className="md:hidden fixed inset-0 z-50 bg-slate-950/98 backdrop-blur-3xl flex flex-col justify-between px-6 pt-[max(env(safe-area-inset-top,0px),1rem)] pb-[max(env(safe-area-inset-bottom,0px),1.75rem)] animate-in slide-in-from-bottom-6 duration-200">
          
          {/* Top Bar with Album Navigation & Output Selector */}
          <div className="flex items-center justify-between py-2">
            <button 
              onClick={() => setIsExpanded(false)}
              className="w-12 h-12 -ml-2 rounded-full text-slate-300 hover:text-white active:scale-90 transition-all flex items-center justify-center"
              aria-label="Close player view"
            >
              <ChevronDown size={28} />
            </button>
            
            <div 
              onClick={albumId ? handleNavigateAlbum : undefined}
              className={`text-center px-2 min-w-0 ${albumId ? 'cursor-pointer' : ''}`}
            >
              <span className="text-[10px] uppercase tracking-widest text-slate-400 font-semibold block">Now Playing</span>
              <p className="text-xs font-semibold text-purple-300 hover:text-white truncate max-w-[190px]">
                {currentTrack.album || 'Aether Audio'}
              </p>
            </div>

            <div className="flex items-center gap-1">
              {/* Mobile Output Destination Selector */}
              <OutputMenu compact align="right" />
              <button 
                onClick={() => setIsQueueOpen(!isQueueOpen)}
                className="w-12 h-12 -mr-2 rounded-full text-slate-300 hover:text-white active:scale-90 transition-all flex items-center justify-center"
                aria-label="Toggle Queue"
              >
                <ListVideo size={22} className={isQueueOpen ? 'text-purple-400' : ''} />
              </button>
            </div>
          </div>

          {/* Large Album Artwork (Clickable -> routes to Album Tracklist) */}
          <div className="my-auto py-4 flex items-center justify-center">
            <div 
              onClick={albumId ? handleNavigateAlbum : undefined}
              className={`w-[74vw] max-w-[320px] aspect-square rounded-3xl overflow-hidden shadow-[0_25px_60px_rgba(0,0,0,0.85)] border border-white/10 bg-slate-900 flex items-center justify-center relative group ${albumId ? 'cursor-pointer active:scale-98 transition-transform' : ''}`}
              title={albumId ? 'View Album Tracklist' : 'Cover Art'}
            >
              {currentTrack.coverArt ? (
                <img 
                  src={getCoverArtUrl(currentTrack.coverArt, getAuthParams(user))} 
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" 
                  alt="" 
                  onError={(e) => {
                    if (e.currentTarget.src !== DEFAULT_COVER_ART) {
                      e.currentTarget.src = DEFAULT_COVER_ART;
                    }
                  }}
                />
              ) : (
                <Music size={72} className="text-purple-400/50" />
              )}
              {albumId && (
                <div className="absolute bottom-2.5 right-2.5 px-2.5 py-1 rounded-full bg-black/60 backdrop-blur-md text-[10px] text-purple-200 border border-white/10 flex items-center gap-1">
                  <span>View Album</span>
                </div>
              )}
            </div>
          </div>

          {/* Bottom Track Details, Scrubber & Controls */}
          <div className="space-y-5 pb-2">
            {/* Title & Clickable Artist & Add to Playlist Button */}
            <div className="flex items-center justify-between min-w-0 pr-1">
              <div className="min-w-0 flex-1 mr-3">
                <h2 className="text-xl sm:text-2xl font-bold text-white truncate">{currentTrack.title}</h2>
                {artistId ? (
                  <button 
                    onClick={handleNavigateArtist} 
                    className="text-sm sm:text-base text-purple-300 hover:text-purple-200 font-semibold truncate mt-0.5 text-left block hover:underline"
                    title="Go to Artist Profile"
                  >
                    {currentTrack.artist || 'Unknown Artist'}
                  </button>
                ) : (
                  <p className="text-sm sm:text-base text-purple-300 font-medium truncate mt-0.5">
                    {currentTrack.artist || 'Unknown Artist'}
                  </p>
                )}
              </div>

              {/* Like + Add to Playlist buttons directly injected into Mobile View */}
              <div className="flex items-center gap-2 shrink-0">
                <HeartButton song={currentTrack} size={22} className="w-12 h-12 rounded-2xl bg-white/10 hover:bg-rose-500/20 border border-white/10 shadow-md flex items-center justify-center" />
                <button 
                  onClick={() => openAddToPlaylistModal(currentTrack.id)} 
                  className="w-12 h-12 rounded-2xl bg-white/10 hover:bg-purple-500/30 active:bg-purple-500/50 text-slate-200 hover:text-purple-300 active:scale-95 flex items-center justify-center transition-all shrink-0 border border-white/10 shadow-md"
                  title="Add to Playlist"
                  aria-label="Add to Playlist"
                >
                  <Plus size={22} />
                </button>
              </div>
            </div>

            {/* Scrubber Bar */}
            <div className="space-y-1.5">
              <div 
                onClick={handleProgressClick} 
                className="h-4 flex items-center cursor-pointer relative"
              >
                <div className="w-full h-1.5 bg-slate-800 rounded-full overflow-hidden">
                  <div 
                    className="h-full bg-gradient-to-r from-purple-500 to-indigo-400 rounded-full relative" 
                    style={{ width: `${progressPercent}%` }} 
                  />
                </div>
              </div>
              <div className="flex justify-between text-[11px] text-slate-400 font-mono">
                <span>{formatTime(currentTime)}</span>
                <span>{formatTime(duration)}</span>
              </div>
            </div>

            {/* Playback Controls Row */}
            <div className="flex items-center justify-between px-2 pt-1">
              <button 
                onClick={toggleShuffle} 
                className={`w-12 h-12 rounded-full transition-colors active:scale-90 flex items-center justify-center ${isShuffle ? 'text-purple-400' : 'text-slate-400'}`}
                aria-label="Toggle Shuffle"
              >
                <Shuffle size={20} />
              </button>
              <button 
                onClick={playPrev} 
                className="w-12 h-12 text-slate-300 active:scale-90 transition-transform flex items-center justify-center"
                aria-label="Previous Track"
              >
                <SkipBack size={26} fill="currentColor" />
              </button>
              <button 
                onClick={handlePlayPause} 
                className="w-16 h-16 rounded-full bg-purple-500 active:bg-purple-400 text-white flex items-center justify-center shadow-[0_0_30px_rgba(168,85,247,0.5)] active:scale-95 transition-all"
                aria-label={isPlaying ? 'Pause' : 'Play'}
              >
                {isPlaying ? <Pause size={28} /> : <Play size={28} className="ml-1" />}
              </button>
              <button 
                onClick={handleSkipNext} 
                className="w-12 h-12 text-slate-300 active:scale-90 transition-transform flex items-center justify-center"
                aria-label="Next Track"
              >
                <SkipForward size={26} fill="currentColor" />
              </button>
              <button 
                onClick={toggleRepeat} 
                className={`w-12 h-12 rounded-full transition-colors active:scale-90 flex items-center justify-center ${repeatMode !== 'off' ? 'text-purple-400' : 'text-slate-400'}`}
                aria-label="Toggle Repeat"
              >
                {repeatMode === 'one' ? <Repeat1 size={20} /> : <Repeat size={20} />}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* DESKTOP STICKY PLAYER BAR                                                 */}
      {/* ========================================================================= */}
      <div className="hidden md:flex fixed bottom-4 left-72 right-8 h-20 bg-slate-900/90 backdrop-blur-2xl border border-white/10 rounded-2xl items-center justify-between px-6 shadow-[0_10px_40px_rgba(0,0,0,0.6)] z-30">
        
        {/* Track Info (Clickable Cover Art & Clickable Artist Name) */}
        <div className="flex items-center gap-4 w-1/3 min-w-0">
          <div 
            onClick={albumId ? handleNavigateAlbum : () => setIsExpanded(true)}
            className={`w-12 h-12 min-w-[48px] rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 shadow-lg flex items-center justify-center shrink-0 overflow-hidden border border-white/10 cursor-pointer group relative ${albumId ? 'hover:ring-2 hover:ring-purple-400 transition-all' : ''}`}
            title={albumId ? `View Album (${currentTrack.album || 'Tracklist'})` : "Expand Fullscreen View"}
          >
             {currentTrack.coverArt ? (
               <img 
                 src={getCoverArtUrl(currentTrack.coverArt, getAuthParams(user))} 
                 className="w-full h-full object-cover group-hover:scale-105 transition-transform" 
                 alt="Cover" 
                 onError={(e) => {
                   if (e.currentTarget.src !== DEFAULT_COVER_ART) {
                     e.currentTarget.src = DEFAULT_COVER_ART;
                   }
                 }}
               />
             ) : (
               <Music size={20} className="text-white/50" />
             )}
             <div className="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
               <ChevronUp size={18} className="text-white" />
             </div>
          </div>
          <div className="min-w-0 flex-1">
            <h4 
              onClick={() => setIsExpanded(true)}
              className="text-slate-100 font-semibold truncate text-sm hover:text-purple-400 transition-colors cursor-pointer"
              title="Expand Player"
            >
              {currentTrack.title}
            </h4>
            <div className="text-xs truncate mt-0.5 flex items-center gap-1.5">
              {artistId ? (
                <span
                  onClick={handleNavigateArtist}
                  className="text-purple-300 hover:text-purple-200 hover:underline cursor-pointer font-medium truncate"
                  title="Go to Artist Profile"
                >
                  {currentTrack.artist || 'Unknown Artist'}
                </span>
              ) : (
                <span className="text-slate-400 truncate">{currentTrack.artist || 'Unknown Artist'}</span>
              )}
              {currentTrack.album && (
                <>
                  <span className="text-slate-500">•</span>
                  {albumId ? (
                    <span 
                      onClick={handleNavigateAlbum}
                      className="text-slate-400 hover:text-slate-200 hover:underline cursor-pointer truncate"
                      title="View Album Tracklist"
                    >
                      {currentTrack.album}
                    </span>
                  ) : (
                    <span className="text-slate-400 truncate">{currentTrack.album}</span>
                  )}
                </>
              )}
            </div>
          </div>
          <HeartButton song={currentTrack} size={16} compact className="p-2 rounded-full hover:bg-white/5 shrink-0" />
          <button 
            onClick={() => openAddToPlaylistModal(currentTrack.id)} 
            className="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-purple-400 hover:bg-white/5 active:bg-purple-500/20 transition-colors shrink-0"
            title="Add to Playlist"
            aria-label="Add to Playlist"
          >
            <Plus size={16} />
          </button>
        </div>

        {/* Controls */}
        <div className="flex flex-col items-center flex-1 max-w-md px-4 shrink-0 min-w-[200px]">
          <div className="flex items-center gap-5 mb-1.5">
            <button 
              onClick={toggleShuffle} 
              className={`p-1.5 rounded-lg transition-colors active:scale-90 ${isShuffle ? 'text-purple-400 bg-purple-500/15' : 'text-slate-400 hover:text-white'}`}
              title={isShuffle ? "Shuffle On" : "Shuffle Off"}
            >
              <Shuffle size={16} />
            </button>
            <button 
              onClick={playPrev} 
              className="p-1.5 text-slate-400 hover:text-white transition-all active:scale-90"
              title="Previous Track (Shift + ←)"
            >
              <SkipBack size={20} fill="currentColor" />
            </button>
            <button 
              onClick={handlePlayPause} 
              className="w-10 h-10 rounded-full bg-purple-500 hover:bg-purple-400 flex items-center justify-center text-white shadow-[0_0_20px_rgba(168,85,247,0.5)] transition hover:scale-105 active:scale-95"
              title={isPlaying ? "Pause (Space)" : "Play (Space)"}
            >
              {isPlaying ? <Pause size={19} /> : <Play size={19} className="ml-0.5" />}
            </button>
            <button 
              onClick={handleSkipNext} 
              className="p-1.5 text-slate-400 hover:text-white transition-all active:scale-90"
              title="Next Track (Shift + →)"
            >
              <SkipForward size={20} fill="currentColor" />
            </button>
            <button 
              onClick={toggleRepeat} 
              className={`p-1.5 rounded-lg transition-colors active:scale-90 ${repeatMode !== 'off' ? 'text-purple-400 bg-purple-500/15' : 'text-slate-400 hover:text-white'}`}
              title={`Repeat: ${repeatMode}`}
            >
              {repeatMode === 'one' ? <Repeat1 size={16} /> : <Repeat size={16} />}
            </button>
          </div>
          <div className="w-full flex items-center gap-2.5 text-[11px] text-slate-400 font-mono">
            <span>{formatTime(currentTime)}</span>
            <div onClick={handleProgressClick} className="h-1.5 flex-1 bg-slate-700/60 hover:bg-slate-700 rounded-full overflow-hidden cursor-pointer group relative">
              <div className="h-full bg-gradient-to-r from-purple-500 to-indigo-400 rounded-full relative group-hover:from-purple-400 group-hover:to-indigo-300 transition-all" style={{ width: `${progressPercent}%` }}></div>
            </div>
            <span>{formatTime(duration)}</span>
          </div>
        </div>

        {/* Actions (Right): Output Selector + Queue Button + Interactive Volume Control */}
        <div className="flex items-center justify-end gap-2.5 w-1/3 min-w-[260px]">
          {/* Audio Output Destination Selector Component */}
          <OutputMenu compact align="right" />

          {/* Queue Button */}
          <button 
            onClick={() => setIsQueueOpen(!isQueueOpen)} 
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl transition-all text-xs font-medium ${
              isQueueOpen 
                ? 'bg-purple-500/25 text-purple-300 border border-purple-500/40 shadow-sm' 
                : 'text-slate-400 hover:text-white hover:bg-white/5'
            }`}
            title="Toggle Queue"
          >
            <ListVideo size={17} />
            <span className="hidden xl:inline">Queue</span>
            {queue.length > 0 && (
              <span className="text-[10px] px-1.5 py-0.2 bg-purple-500/30 rounded-full text-purple-300 font-semibold font-mono">
                {queue.length}
              </span>
            )}
          </button>

          {/* Volume Control */}
          <div className="flex items-center gap-2 group/vol">
            <button 
              onClick={handleToggleMute} 
              className="text-slate-400 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-white/5 active:scale-95"
              title={volume === 0 ? "Unmute (M)" : "Mute (M)"}
            >
              {volume === 0 ? <VolumeX size={18} className="text-red-400" /> : volume < 0.5 ? <Volume1 size={18} /> : <Volume2 size={18} />}
            </button>
            <div 
              className="w-20 lg:w-28 h-5 flex items-center cursor-pointer group/bar relative" 
              onClick={handleVolumeChange}
              title={`Volume: ${Math.round(volume * 100)}% (↑/↓)`}
            >
              <div className="w-full h-1.5 bg-slate-700/60 group-hover/vol:bg-slate-700 rounded-full overflow-hidden">
                <div 
                  className="h-full bg-gradient-to-r from-purple-500 to-indigo-400 group-hover/vol:from-purple-400 group-hover/vol:to-indigo-300 rounded-full transition-all" 
                  style={{ width: `${volume * 100}%` }}
                />
              </div>
            </div>
          </div>
        </div>

      </div>
      
      {/* ========================================================================= */}
      {/* QUEUE DRAWER / PANEL (Enhanced Mobile Dark Mode Contrast & Accessibility)  */}
      {/* ========================================================================= */}
      {isQueueOpen && (
        <>
          {/* Mobile Backdrop Overlay */}
          <div 
            onClick={() => setIsQueueOpen(false)} 
            className="md:hidden fixed inset-0 bg-black/75 backdrop-blur-sm z-[55] animate-in fade-in duration-150" 
          />

          <div className="fixed inset-x-0 bottom-0 max-h-[80vh] md:max-h-96 md:bottom-28 md:right-8 md:inset-x-auto md:w-96 bg-slate-950/98 backdrop-blur-3xl border-t md:border border-slate-700/80 rounded-t-3xl md:rounded-2xl shadow-[0_15px_60px_rgba(0,0,0,0.9)] z-[60] flex flex-col overflow-hidden animate-in slide-in-from-bottom-6 md:zoom-in-95 duration-150">
            {/* Mobile swipe indicator */}
            <div className="md:hidden pt-2 pb-1 flex justify-center">
              <div className="w-12 h-1.5 rounded-full bg-slate-600" />
            </div>

            <div className="flex items-center justify-between p-3.5 border-b border-slate-800 bg-slate-900/80">
              <h3 className="text-white font-bold text-sm flex items-center gap-2">
                <ListVideo size={18} className="text-purple-400"/> Playing Next
                {queue.length > 0 && <span className="text-xs text-slate-300 font-normal">({queue.length})</span>}
              </h3>
              <div className="flex items-center gap-2">
                {queue.length > 1 && (
                  <button 
                    onClick={clearQueue} 
                    className="text-xs text-slate-300 hover:text-red-400 active:text-red-300 font-medium px-2.5 py-1 rounded-lg hover:bg-white/10 transition"
                    title="Clear upcoming queue"
                  >
                    Clear
                  </button>
                )}
                <button 
                  onClick={() => setIsQueueOpen(false)} 
                  className="p-1.5 rounded-lg text-slate-300 hover:text-white transition hover:bg-white/10"
                  aria-label="Close Queue"
                >
                  <X size={18} />
                </button>
              </div>
            </div>

            <div className="flex-1 overflow-y-auto p-2.5 touch-scroll divide-y divide-white/5">
              {queue.length === 0 ? (
                <div className="text-center py-10">
                  <Music size={28} className="text-slate-600 mx-auto mb-2" />
                  <p className="text-slate-300 text-sm font-medium">Queue is empty</p>
                  <p className="text-slate-500 text-xs mt-0.5">Add songs to play them next</p>
                </div>
              ) : (
                queue.map((track, idx) => {
                  const isCurrent = idx === currentIndex;
                  return (
                    <div 
                      key={`${track.id || 'track'}-${idx}`} 
                      onClick={() => skipToQueueIndex(idx)}
                      className={`flex items-center justify-between p-2.5 rounded-xl group cursor-pointer transition-all ${
                        isCurrent 
                          ? 'bg-purple-900/40 border border-purple-400/50 shadow-md text-white' 
                          : 'hover:bg-white/10 active:bg-white/15 text-slate-100'
                      }`}
                      title={isCurrent ? `Now playing "${track.title}"` : `Skip to "${track.title}"`}
                    >
                      <div className="flex items-center gap-2.5 min-w-0 flex-1 pr-2">
                        <div className="relative w-10 h-10 rounded-lg bg-slate-800 shrink-0 overflow-hidden flex items-center justify-center border border-white/10 shadow-sm">
                          {track.coverArt ? (
                            <img 
                              src={getCoverArtUrl(track.coverArt, getAuthParams(user))} 
                              className="w-full h-full object-cover" 
                              alt="" 
                              loading="lazy" 
                              onError={(e) => {
                                if (e.currentTarget.src !== DEFAULT_COVER_ART) {
                                  e.currentTarget.src = DEFAULT_COVER_ART;
                                }
                              }}
                            />
                          ) : (
                            <Music size={16} className="text-slate-300" />
                          )}
                          {isCurrent ? (
                            <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
                              <Volume2 size={16} className={`text-purple-300 ${isPlaying ? 'animate-pulse' : ''}`} />
                            </div>
                          ) : (
                            <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                              <Play size={15} fill="currentColor" className="text-white ml-0.5" />
                            </div>
                          )}
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className={`text-xs sm:text-sm font-semibold truncate transition-colors ${
                            isCurrent ? 'text-purple-200' : 'text-white group-hover:text-purple-300'
                          }`}>
                            {track.title}
                          </p>
                          <p className="text-[11px] sm:text-xs text-slate-300 truncate mt-0.5 font-medium">
                            {track.artist || 'Unknown Artist'}
                          </p>
                        </div>
                      </div>

                      <div className="flex items-center shrink-0" onClick={(e) => e.stopPropagation()}>
                        {track.duration > 0 && (
                          <span className="text-[11px] text-slate-300 font-mono mr-2 hidden sm:inline-block">
                            {formatDuration(track.duration)}
                          </span>
                        )}
                        <div className="flex flex-col mr-1">
                          <button 
                            onClick={() => reorderQueue(idx, idx - 1)} 
                            disabled={idx === 0} 
                            className="text-slate-300 hover:text-white disabled:opacity-20 p-1 transition-colors"
                            title="Move up in queue"
                            aria-label="Move up"
                          >
                            <ChevronUp size={14}/>
                          </button>
                          <button 
                            onClick={() => reorderQueue(idx, idx + 1)} 
                            disabled={idx === queue.length - 1} 
                            className="text-slate-300 hover:text-white disabled:opacity-20 p-1 transition-colors"
                            title="Move down in queue"
                            aria-label="Move down"
                          >
                            <ChevronDown size={14}/>
                          </button>
                        </div>
                        <button 
                          onClick={() => removeFromQueue(idx)} 
                          className="text-slate-300 hover:text-red-400 active:scale-90 p-2 rounded-lg hover:bg-white/10 transition-colors"
                          title="Remove from queue"
                          aria-label="Remove from queue"
                        >
                          <Trash2 size={16}/>
                        </button>
                      </div>
                    </div>
                  );
                })
              )}
            </div>
          </div>
        </>
      )}
    </>
  );
}
