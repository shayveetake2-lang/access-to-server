import { useState, useRef, useEffect } from 'react';
import { Play, Pause, SkipBack, SkipForward, Repeat, Shuffle, ListVideo, Music, Repeat1, ChevronUp, ChevronDown, Trash2, X, Volume2, Volume1, VolumeX, Plus } from 'lucide-react';
import { usePlayer } from '../context/PlayerContext';
import { useAuth } from '../context/AuthContext';
import { usePlaylistModal } from '../context/PlaylistModalContext';
import { getCoverArtUrl, getStreamUrl, getSubsonicAuthParams } from '../utils/api';

export default function StickyPlayer() {
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
    swapAudio, setPrefetchBuffer
  } = usePlayer();
  const prevVolumeRef = useRef(volume || 0.8);

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
      nextIndex = Math.floor(Math.random() * queue.length);
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
      if (prefetchedTrackIdRef.current !== nextTrack.id) {
        console.log(`[Aether Pre-fetch Buffer] 15s remaining (${Math.round(remainingTime)}s). Spawning pre-fetch buffer for next track: "${nextTrack.title}" (ID: ${nextTrack.id})`);
        
        // Discard any previous buffer
        if (prefetchAudioRef.current) {
          prefetchAudioRef.current.pause();
          prefetchAudioRef.current.src = '';
          prefetchAudioRef.current = null;
        }

        const streamUrl = getStreamUrl(nextTrack.id, getSubsonicAuthParams(user));
        const prefetchAudio = new Audio();
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
    if (prefetchedTrackIdRef.current && prefetchedTrackIdRef.current !== currentTrack?.id) {
      const nextInfo = getNextTrackInfo();
      if (!nextInfo || nextInfo.track?.id !== prefetchedTrackIdRef.current) {
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
    if (bufferedAudio && prefetchedTrackIdRef.current === nextInfo.track.id) {
      console.log(`[Aether Pre-fetch Buffer] Instant zero-latency Skip triggered for: "${nextInfo.track.title}"`);
      prefetchAudioRef.current = null;
      prefetchedTrackIdRef.current = null;
      if (setPrefetchBuffer) setPrefetchBuffer(null, null, -1);
      swapAudio(bufferedAudio, nextInfo.track, nextInfo.index);
    } else {
      playNext();
    }
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

  const progressPercent = duration > 0 ? (currentTime / duration) * 100 : 0;

  return (
    <>
      {/* ========================================================================= */}
      {/* MOBILE MINI-PLAYER (Docked above MobileNav)                               */}
      {/* ========================================================================= */}
      <div 
        onClick={() => setIsExpanded(true)}
        className="md:hidden fixed bottom-[calc(max(env(safe-area-inset-bottom,0px),0.5rem)+4.0rem)] left-2.5 right-2.5 h-14 bg-slate-900/95 backdrop-blur-2xl border border-white/10 rounded-2xl flex items-center justify-between px-3 shadow-[0_8px_30px_rgba(0,0,0,0.7)] z-40 cursor-pointer select-none active:scale-[0.99] transition-transform overflow-hidden"
      >
        {/* Top hairline progress bar */}
        <div className="absolute top-0 left-0 right-0 h-[2.5px] bg-white/10">
          <div 
            className="h-full bg-gradient-to-r from-purple-500 to-indigo-400 transition-all duration-150" 
            style={{ width: `${progressPercent}%` }} 
          />
        </div>

        {/* Thumbnail + Titles */}
        <div className="flex items-center gap-2.5 min-w-0 flex-1 pr-2">
          <div className="w-10 h-10 rounded-xl bg-slate-800 shrink-0 overflow-hidden shadow-sm flex items-center justify-center border border-white/5">
            {currentTrack.coverArt ? (
              <img src={getCoverArtUrl(currentTrack.coverArt, getAuthParams(user))} className="w-full h-full object-cover" alt="" />
            ) : (
              <Music size={18} className="text-purple-400" />
            )}
          </div>
          <div className="min-w-0 flex-1">
            <div className="text-xs font-semibold text-white truncate">{currentTrack.title}</div>
            <div className="text-[11px] text-slate-400 truncate mt-0.5">{currentTrack.artist || 'Unknown Artist'}</div>
          </div>
        </div>

        {/* Play/Pause & Next Button */}
        <div className="flex items-center gap-1 shrink-0" onClick={(e) => e.stopPropagation()}>
          <button 
            onClick={togglePlay} 
            className="w-9 h-9 rounded-full bg-purple-500 active:bg-purple-400 text-white flex items-center justify-center shadow-md active:scale-95 transition-all"
            aria-label={isPlaying ? 'Pause' : 'Play'}
          >
            {isPlaying ? <Pause size={17} /> : <Play size={17} className="ml-0.5" />}
          </button>
          <button 
            onClick={handleSkipNext} 
            className="p-2 text-slate-400 hover:text-white active:scale-90 transition-all"
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
          
          {/* Top Bar */}
          <div className="flex items-center justify-between py-2">
            <button 
              onClick={() => setIsExpanded(false)}
              className="p-2 -ml-2 rounded-full text-slate-400 hover:text-white active:scale-90 transition-all"
              aria-label="Close player view"
            >
              <ChevronDown size={28} />
            </button>
            <div className="text-center px-4 min-w-0">
              <span className="text-[10px] uppercase tracking-widest text-slate-400 font-semibold block">Now Playing</span>
              <p className="text-xs font-medium text-white truncate max-w-[220px]">{currentTrack.album || 'Aether Audio'}</p>
            </div>
            <button 
              onClick={() => setIsQueueOpen(!isQueueOpen)}
              className="p-2 -mr-2 rounded-full text-slate-400 hover:text-white active:scale-90 transition-all"
              aria-label="Toggle Queue"
            >
              <ListVideo size={22} className={isQueueOpen ? 'text-purple-400' : ''} />
            </button>
          </div>

          {/* Large Album Artwork */}
          <div className="my-auto py-4 flex items-center justify-center">
            <div className="w-[74vw] max-w-[320px] aspect-square rounded-3xl overflow-hidden shadow-[0_25px_60px_rgba(0,0,0,0.85)] border border-white/10 bg-slate-900 flex items-center justify-center">
              {currentTrack.coverArt ? (
                <img src={getCoverArtUrl(currentTrack.coverArt, getAuthParams(user))} className="w-full h-full object-cover" alt="" />
              ) : (
                <Music size={72} className="text-purple-400/50" />
              )}
            </div>
          </div>

          {/* Bottom Track Details, Scrubber & Controls */}
          <div className="space-y-5 pb-2">
            {/* Title & Artist */}
            <div className="min-w-0 pr-2">
              <h2 className="text-xl sm:text-2xl font-bold text-white truncate">{currentTrack.title}</h2>
              <p className="text-sm sm:text-base text-purple-400 font-medium truncate mt-0.5">{currentTrack.artist || 'Unknown Artist'}</p>
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
                className={`p-2.5 rounded-full transition-colors active:scale-90 ${isShuffle ? 'text-purple-400' : 'text-slate-400'}`}
                aria-label="Toggle Shuffle"
              >
                <Shuffle size={20} />
              </button>
              <button 
                onClick={playPrev} 
                className="p-2 text-slate-300 active:scale-90 transition-transform"
                aria-label="Previous Track"
              >
                <SkipBack size={26} fill="currentColor" />
              </button>
              <button 
                onClick={togglePlay} 
                className="w-16 h-16 rounded-full bg-purple-500 active:bg-purple-400 text-white flex items-center justify-center shadow-[0_0_30px_rgba(168,85,247,0.5)] active:scale-95 transition-all"
                aria-label={isPlaying ? 'Pause' : 'Play'}
              >
                {isPlaying ? <Pause size={28} /> : <Play size={28} className="ml-1" />}
              </button>
              <button 
                onClick={handleSkipNext} 
                className="p-2 text-slate-300 active:scale-90 transition-transform"
                aria-label="Next Track"
              >
                <SkipForward size={26} fill="currentColor" />
              </button>
              <button 
                onClick={toggleRepeat} 
                className={`p-2.5 rounded-full transition-colors active:scale-90 ${repeatMode !== 'off' ? 'text-purple-400' : 'text-slate-400'}`}
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
        
        {/* Track Info */}
        <div className="flex items-center gap-4 w-1/3 min-w-0">
          <div 
            onClick={() => setIsExpanded(true)}
            className="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 shadow-lg flex items-center justify-center shrink-0 overflow-hidden border border-white/10 cursor-pointer group relative"
            title="Expand Fullscreen View"
          >
             {currentTrack.coverArt ? (
               <img src={getCoverArtUrl(currentTrack.coverArt, getAuthParams(user))} className="w-full h-full object-cover group-hover:scale-105 transition-transform" alt="Cover" />
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
            >
              {currentTrack.title}
            </h4>
            <p className="text-slate-400 text-xs truncate mt-0.5">{currentTrack.artist || 'Unknown Artist'}{currentTrack.album ? ` • ${currentTrack.album}` : ''}</p>
          </div>
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
        <div className="flex flex-col items-center flex-1 max-w-md px-4">
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
              onClick={togglePlay} 
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

        {/* Actions (Right): Queue Button + Interactive Volume Control */}
        <div className="flex items-center justify-end gap-3 w-1/3 min-w-[200px]">
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
      {/* QUEUE DRAWER / PANEL (Responsive)                                         */}
      {/* ========================================================================= */}
      {isQueueOpen && (
        <div className="fixed inset-x-3 bottom-[calc(max(env(safe-area-inset-bottom,0px),0.5rem)+4.2rem)] md:inset-x-auto md:bottom-28 md:right-8 md:w-84 max-h-[65vh] md:max-h-96 bg-slate-900/98 backdrop-blur-2xl border border-white/10 rounded-2xl shadow-2xl z-[60] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
          <div className="flex items-center justify-between p-3.5 border-b border-white/10">
            <h3 className="text-white font-semibold text-sm flex items-center gap-2">
              <ListVideo size={17} className="text-purple-400"/> Playing Next
              {queue.length > 0 && <span className="text-[11px] text-slate-400 font-normal">({queue.length})</span>}
            </h3>
            <div className="flex items-center gap-2">
              {queue.length > 1 && (
                <button 
                  onClick={clearQueue} 
                  className="text-xs text-slate-400 hover:text-red-400 active:text-red-300 font-medium px-2 py-1 rounded-lg hover:bg-white/5 transition"
                  title="Clear upcoming queue"
                >
                  Clear
                </button>
              )}
              <button 
                onClick={() => setIsQueueOpen(false)} 
                className="p-1 rounded-lg text-slate-400 hover:text-white transition"
                aria-label="Close Queue"
              >
                <X size={18} />
              </button>
            </div>
          </div>
          <div className="flex-1 overflow-y-auto p-2 touch-scroll">
            {queue.length === 0 ? (
              <p className="text-slate-400 text-xs text-center py-8">Queue is empty</p>
            ) : (
              queue.map((track, idx) => {
                const isCurrent = idx === currentIndex;
                return (
                  <div 
                    key={`${track.id || 'track'}-${idx}`} 
                    onClick={() => skipToQueueIndex(idx)}
                    className={`flex items-center justify-between p-2 rounded-xl group cursor-pointer transition-all ${
                      isCurrent 
                        ? 'bg-purple-500/20 border border-purple-500/40 shadow-[0_0_15px_rgba(168,85,247,0.15)]' 
                        : 'hover:bg-white/10 active:bg-white/15'
                    }`}
                    title={isCurrent ? `Now playing "${track.title}"` : `Skip to "${track.title}"`}
                  >
                    <div className="flex items-center gap-2.5 min-w-0 flex-1 pr-2">
                      <div className="relative w-9 h-9 rounded-lg bg-slate-800 shrink-0 overflow-hidden flex items-center justify-center border border-white/5">
                        {track.coverArt ? (
                          <img 
                            src={getCoverArtUrl(track.coverArt, getAuthParams(user))} 
                            className="w-full h-full object-cover" 
                            alt="" 
                            loading="lazy" 
                          />
                        ) : (
                          <Music size={15} className="text-slate-400" />
                        )}
                        {isCurrent ? (
                          <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
                            <Volume2 size={15} className={`text-purple-400 ${isPlaying ? 'animate-pulse' : ''}`} />
                          </div>
                        ) : (
                          <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                            <Play size={14} fill="currentColor" className="text-white ml-0.5" />
                          </div>
                        )}
                      </div>
                      <div className="min-w-0 flex-1">
                        <p className={`text-xs font-medium truncate transition-colors ${
                          isCurrent ? 'text-purple-300 font-semibold' : 'text-white group-hover:text-purple-300'
                        }`}>
                          {track.title}
                        </p>
                        <p className="text-[11px] text-slate-400 truncate">{track.artist}</p>
                      </div>
                    </div>
                    <div className="flex items-center shrink-0" onClick={(e) => e.stopPropagation()}>
                      {track.duration > 0 && (
                        <span className="text-[10px] text-slate-400 font-mono mr-1.5 hidden sm:inline-block">
                          {Math.floor(track.duration / 60)}:{(track.duration % 60).toString().padStart(2, '0')}
                        </span>
                      )}
                      <div className="flex flex-col mr-1">
                        <button 
                          onClick={() => reorderQueue(idx, idx - 1)} 
                          disabled={idx === 0} 
                          className="text-slate-400 hover:text-white disabled:opacity-20 p-1 transition-colors"
                          title="Move up in queue"
                          aria-label="Move up"
                        >
                          <ChevronUp size={13}/>
                        </button>
                        <button 
                          onClick={() => reorderQueue(idx, idx + 1)} 
                          disabled={idx === queue.length - 1} 
                          className="text-slate-400 hover:text-white disabled:opacity-20 p-1 transition-colors"
                          title="Move down in queue"
                          aria-label="Move down"
                        >
                          <ChevronDown size={13}/>
                        </button>
                      </div>
                      <button 
                        onClick={() => removeFromQueue(idx)} 
                        className="text-slate-400 hover:text-red-400 active:scale-90 p-1.5 rounded-lg hover:bg-white/10 transition-colors"
                        title="Remove from queue"
                        aria-label="Remove from queue"
                      >
                        <Trash2 size={14}/>
                      </button>
                    </div>
                  </div>
                );
              })
            )}
          </div>
        </div>
      )}
    </>
  );
}
