import { useState } from 'react';
import { Play, Pause, SkipBack, SkipForward, Repeat, Shuffle, ListVideo, Music, Repeat1, ChevronUp, ChevronDown, Trash2, X } from 'lucide-react';
import { usePlayer } from '../context/PlayerContext';
import { useAuth } from '../context/AuthContext';

export default function StickyPlayer() {
  const [isQueueOpen, setIsQueueOpen] = useState(false);
  const { user, getAuthParams } = useAuth();
  const { 
    currentTrack, isPlaying, progress: currentTime, duration, 
    isShuffled: isShuffle, repeatMode, 
    queue, currentIndex, removeFromQueue, reorderQueue,
    togglePlay, playNext, playPrevious: playPrev, toggleShuffle, toggleRepeat, seek 
  } = usePlayer();

  if (!currentTrack) {
    return (
      <div className="fixed bottom-4 left-4 right-4 md:left-72 md:right-8 h-24 bg-slate-900/70 backdrop-blur-xl border border-white/10 rounded-2xl flex items-center justify-center px-6 shadow-[0_10px_40px_rgba(0,0,0,0.5)] z-50">
        <div className="text-slate-400 font-medium flex items-center gap-2"><Music size={18} /> Select a track to play</div>
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
    <div className="fixed bottom-4 left-4 right-4 md:left-72 md:right-8 h-24 bg-slate-900/80 backdrop-blur-xl border border-white/10 rounded-2xl flex items-center justify-between px-6 shadow-[0_10px_40px_rgba(0,0,0,0.5)] z-50">
      
      {/* Track Info */}
      <div className="flex items-center gap-4 w-1/3">
        <div className="w-14 h-14 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 shadow-lg flex items-center justify-center shrink-0 overflow-hidden">
           {currentTrack.coverArt ? (
             <img src={`/ampache/public/rest/index.php?action=getCoverArt&id=${currentTrack.coverArt}&${getAuthParams(user)}`} className="w-full h-full object-cover" alt="Cover" />
           ) : (
             <Music size={24} className="text-white/50" />
           )}
        </div>
        <div className="min-w-0">
          <h4 className="text-slate-100 font-semibold truncate">{currentTrack.title}</h4>
          <p className="text-slate-400 text-sm truncate">{currentTrack.artist || 'Unknown Artist'}</p>
        </div>
      </div>

      {/* Controls */}
      <div className="flex flex-col items-center flex-1 max-w-md">
        <div className="flex items-center gap-6 mb-2">
          <button onClick={toggleShuffle} className={`transition ${isShuffle ? 'text-purple-400' : 'text-slate-400 hover:text-white'}`}>
            <Shuffle size={18} />
          </button>
          <button onClick={playPrev} className="text-slate-400 hover:text-white transition"><SkipBack size={24} /></button>
          <button onClick={togglePlay} className="w-10 h-10 rounded-full bg-purple-500 hover:bg-purple-400 flex items-center justify-center text-white shadow-[0_0_15px_rgba(168,85,247,0.5)] transition hover:scale-105">
            {isPlaying ? <Pause size={20} /> : <Play size={20} className="ml-1" />}
          </button>
          <button onClick={playNext} className="text-slate-400 hover:text-white transition"><SkipForward size={24} /></button>
          <button onClick={toggleRepeat} className={`transition ${repeatMode > 0 ? 'text-purple-400' : 'text-slate-400 hover:text-white'}`}>
            {repeatMode === 2 ? <Repeat1 size={18} /> : <Repeat size={18} />}
          </button>
        </div>
        <div className="w-full flex items-center gap-3 text-xs text-slate-400">
          <span>{formatTime(currentTime)}</span>
          <div onClick={handleProgressClick} className="h-1.5 flex-1 bg-slate-700 rounded-full overflow-hidden cursor-pointer">
            <div className="h-full bg-gradient-to-r from-purple-500 to-indigo-500 rounded-full relative" style={{ width: `${progressPercent}%` }}></div>
          </div>
          <span>{formatTime(duration)}</span>
        </div>
      </div>

      {/* Actions */}
      <div className="flex items-center justify-end gap-4 w-1/3 text-slate-400 hidden md:flex">
        <ListVideo size={18} className="cursor-pointer hover:text-white" title="Queue" onClick={() => setIsQueueOpen(!isQueueOpen)} />
      </div>

    </div>
    
    {/* Queue Panel */}
    {isQueueOpen && (
      <div className="fixed bottom-32 right-4 md:right-8 w-80 max-h-96 bg-slate-900/90 backdrop-blur-xl border border-white/10 rounded-2xl shadow-2xl z-50 flex flex-col overflow-hidden">
        <div className="flex items-center justify-between p-4 border-b border-white/10">
          <h3 className="text-white font-semibold flex items-center gap-2"><ListVideo size={18} className="text-purple-400"/> Playing Next</h3>
          <button onClick={() => setIsQueueOpen(false)} className="text-slate-400 hover:text-white transition">
            <X size={18} />
          </button>
        </div>
        <div className="flex-1 overflow-y-auto p-2">
          {queue.length === 0 ? (
            <p className="text-slate-400 text-sm text-center py-8">Queue is empty</p>
          ) : (
            queue.map((track, idx) => (
              <div key={idx} className={`flex items-center justify-between p-2 rounded-lg group ${idx === currentIndex ? 'bg-purple-500/20 border border-purple-500/30' : 'hover:bg-white/5'}`}>
                <div className="min-w-0 flex-1 pr-2">
                  <p className={`text-sm font-medium truncate ${idx === currentIndex ? 'text-purple-400' : 'text-white'}`}>{track.title}</p>
                  <p className="text-xs text-slate-400 truncate">{track.artist}</p>
                </div>
                <div className="flex items-center opacity-0 group-hover:opacity-100 transition-opacity">
                  <div className="flex flex-col mr-2">
                    <button onClick={() => reorderQueue(idx, idx - 1)} disabled={idx === 0} className="text-slate-400 hover:text-white disabled:opacity-30 p-1"><ChevronUp size={14}/></button>
                    <button onClick={() => reorderQueue(idx, idx + 1)} disabled={idx === queue.length - 1} className="text-slate-400 hover:text-white disabled:opacity-30 p-1"><ChevronDown size={14}/></button>
                  </div>
                  <button onClick={() => removeFromQueue(idx)} className="text-slate-400 hover:text-red-400 p-1"><Trash2 size={16}/></button>
                </div>
              </div>
            ))
          )}
        </div>
      </div>
    )}
    </>
  );
}
