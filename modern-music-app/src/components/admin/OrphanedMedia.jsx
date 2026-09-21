import { useState, useEffect, useMemo } from 'react';
import { Ghost, RefreshCw, CheckSquare, Square, Copy, Check, AlertCircle, Search } from 'lucide-react';
import { adminPost } from './adminApi';

/**
 * OrphanedMedia — admin data view that surfaces "stray" songs with no
 * assigned artist and/or album (artist/album = 0 or NULL in the Ampache DB).
 *
 * Selected song IDs can be copied straight into the Content Assigner's
 * "Song IDs to Reassign" field to bulk fix them with the existing tools.
 */
export default function OrphanedMedia() {
  const [songs, setSongs] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [selectedIds, setSelectedIds] = useState(new Set());
  const [filterQuery, setFilterQuery] = useState('');
  const [copied, setCopied] = useState(false);

  const fetchOrphans = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await adminPost('orphanedSongs', { limit: 500 });
      setSongs(res.songs || []);
      setSelectedIds(new Set());
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchOrphans();
  }, []);

  const displayedSongs = useMemo(() => {
    const q = filterQuery.toLowerCase().trim();
    if (!q) return songs;
    return songs.filter(s => (s.title || '').toLowerCase().includes(q));
  }, [songs, filterQuery]);

  const toggleSelect = (id) => {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id); else next.add(id);
      return next;
    });
  };

  const selectAll = () => setSelectedIds(new Set(displayedSongs.map(s => s.id)));
  const deselectAll = () => setSelectedIds(new Set());

  const handleCopyIds = async () => {
    const ids = Array.from(selectedIds).join(', ');
    if (!ids) return;
    try {
      await navigator.clipboard.writeText(ids);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch (err) {
      console.warn('Clipboard write failed:', err);
    }
  };

  const missingLabel = (song) => {
    const missingArtist = !song.artist || song.artist === 0;
    const missingAlbum = !song.album || song.album === 0;
    if (missingArtist && missingAlbum) return 'No artist & no album';
    if (missingArtist) return 'No artist';
    return 'No album';
  };

  return (
    <div className="rounded-2xl border border-rose-500/20 bg-rose-500/5 overflow-hidden">
      {/* Header */}
      <div className="flex items-center justify-between gap-3 p-4 border-b border-rose-500/10">
        <div className="flex items-center gap-3">
          <div className="w-9 h-9 rounded-xl bg-rose-500/20 flex items-center justify-center">
            <Ghost size={18} className="text-rose-400" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h3 className="font-bold text-white text-sm">Orphaned Media Finder</h3>
              <span className="text-[10px] px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-400 font-semibold uppercase tracking-wide">Admin Only</span>
            </div>
            <p className="text-xs text-slate-400 mt-0.5">Stray songs with no artist and/or album assignment</p>
          </div>
        </div>
        <button
          onClick={fetchOrphans}
          disabled={loading}
          className="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium flex items-center gap-1.5 transition-colors border border-white/5 disabled:opacity-50 shrink-0"
        >
          <RefreshCw size={14} className={loading ? 'animate-spin' : ''} />
          <span>Rescan</span>
        </button>
      </div>

      <div className="p-4 space-y-3">
        {error && (
          <div className="flex items-start gap-2 text-xs p-3 rounded-xl bg-red-500/10 text-red-400 border border-red-500/20">
            <AlertCircle size={14} className="shrink-0 mt-0.5" />
            {error}
          </div>
        )}

        {/* Filter + selection shortcuts */}
        <div className="flex flex-col sm:flex-row sm:items-center gap-2">
          <div className="relative flex-1">
            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" />
            <input
              type="text"
              placeholder="Filter orphaned songs by title..."
              value={filterQuery}
              onChange={(e) => setFilterQuery(e.target.value)}
              className="w-full bg-slate-950 border border-white/10 rounded-xl pl-9 pr-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-rose-500 transition-colors"
            />
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <button onClick={selectAll} className="text-[11px] px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 transition">Select All</button>
            <button onClick={deselectAll} className="text-[11px] px-2.5 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-slate-300 transition">Clear</button>
          </div>
        </div>

        {/* List */}
        <div className="max-h-[420px] overflow-y-auto divide-y divide-white/5 border border-white/5 rounded-xl bg-slate-950/60 p-1">
          {loading ? (
            <div className="p-12 text-center text-slate-400 text-xs flex flex-col items-center justify-center">
              <div className="w-6 h-6 border-2 border-rose-500 border-t-transparent rounded-full animate-spin mb-2"></div>
              Scanning catalog for orphaned songs...
            </div>
          ) : displayedSongs.length === 0 ? (
            <div className="p-12 text-center text-slate-500 text-xs">
              No orphaned songs found. Your catalog is fully assigned!
            </div>
          ) : (
            displayedSongs.map((song) => {
              const isSelected = selectedIds.has(song.id);
              return (
                <div
                  key={song.id}
                  onClick={() => toggleSelect(song.id)}
                  className={`flex items-center justify-between p-2.5 rounded-lg cursor-pointer transition-all ${isSelected ? 'bg-rose-500/20 border border-rose-500/30 shadow-sm' : 'hover:bg-white/5'}`}
                >
                  <div className="flex items-center gap-3 min-w-0 flex-1 pr-2">
                    <button type="button" className="text-rose-400 shrink-0">
                      {isSelected ? <CheckSquare size={16} /> : <Square size={16} className="text-slate-600" />}
                    </button>
                    <div className="min-w-0 flex-1">
                      <p className={`text-xs font-semibold truncate ${isSelected ? 'text-rose-300' : 'text-slate-200'}`}>
                        {song.title || 'Untitled Track'}
                      </p>
                      <p className="text-[11px] text-slate-400 truncate">
                        {song.artist_name || 'No Artist'} • {song.album_name || 'No Album'}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    <span className="text-[10px] px-2 py-0.5 rounded bg-rose-500/15 text-rose-300 border border-rose-500/25">
                      {missingLabel(song)}
                    </span>
                    <span className="text-[10px] font-mono text-slate-500">ID: {song.id}</span>
                  </div>
                </div>
              );
            })
          )}
        </div>

        {/* Footer / Copy-to-clipboard action */}
        <div className="flex items-center justify-between gap-3">
          <div className="text-[11px] text-slate-400">
            Showing {displayedSongs.length} orphan(s) • <span className="font-semibold text-rose-400">{selectedIds.size} selected</span>
          </div>
          <button
            onClick={handleCopyIds}
            disabled={selectedIds.size === 0}
            className="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-500/15 hover:bg-rose-500/25 border border-rose-500/30 disabled:opacity-40 text-rose-300 text-xs font-semibold transition-all active:scale-95"
            title="Copy selected IDs to paste into Content Assigner"
          >
            {copied ? <Check size={14} /> : <Copy size={14} />}
            <span>{copied ? 'Copied!' : 'Copy IDs to Content Assigner'}</span>
          </button>
        </div>
      </div>
    </div>
  );
}
