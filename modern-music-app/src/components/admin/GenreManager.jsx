import { useState } from 'react';
import { Tag, Plus, Check, AlertCircle, Loader2 } from 'lucide-react';
import { adminPost } from './adminApi';

/**
 * GenreManager — lets any logged-in user:
 *  1. Create a new custom genre tag in the Ampache DB
 *  2. Assign a genre to a comma-separated list of song or album IDs
 */
export default function GenreManager() {
  const [tab, setTab] = useState('create'); // 'create' | 'assign'

  // Create tab
  const [newGenre, setNewGenre] = useState('');
  const [creating, setCreating] = useState(false);
  const [createResult, setCreateResult] = useState(null);

  // Assign tab
  const [assignGenre, setAssignGenre] = useState('');
  const [songIdsRaw, setSongIdsRaw] = useState('');
  const [albumIdsRaw, setAlbumIdsRaw] = useState('');
  const [assigning, setAssigning] = useState(false);
  const [assignResult, setAssignResult] = useState(null);

  const parseIds = (raw) =>
    raw
      .split(/[\s,]+/)
      .map(s => parseInt(s.trim(), 10))
      .filter(n => !isNaN(n) && n > 0);

  const handleCreate = async (e) => {
    e.preventDefault();
    if (!newGenre.trim()) return;
    setCreating(true);
    setCreateResult(null);
    try {
      const res = await adminPost('insertGenre', { genre: newGenre.trim() });
      setCreateResult({ success: true, message: res.message });
      if (res.created) setNewGenre('');
    } catch (err) {
      setCreateResult({ success: false, message: err.message });
    } finally {
      setCreating(false);
    }
  };

  const handleAssign = async (e) => {
    e.preventDefault();
    if (!assignGenre.trim()) return;
    const songIds  = parseIds(songIdsRaw);
    const albumIds = parseIds(albumIdsRaw);
    if (songIds.length === 0 && albumIds.length === 0) {
      setAssignResult({ success: false, message: 'Enter at least one song ID or album ID.' });
      return;
    }
    setAssigning(true);
    setAssignResult(null);
    try {
      const res = await adminPost('assignGenre', { genre: assignGenre.trim(), songIds, albumIds });
      setAssignResult({ success: true, message: `${res.message} (${res.rowsAffected} row(s) updated)` });
      setSongIdsRaw('');
      setAlbumIdsRaw('');
    } catch (err) {
      setAssignResult({ success: false, message: err.message });
    } finally {
      setAssigning(false);
    }
  };

  return (
    <div className="rounded-2xl border border-white/10 bg-slate-900/80 overflow-hidden">
      {/* Header */}
      <div className="flex items-center gap-3 p-4 border-b border-white/5">
        <div className="w-9 h-9 rounded-xl bg-purple-500/20 flex items-center justify-center">
          <Tag size={18} className="text-purple-400" />
        </div>
        <div>
          <h3 className="font-bold text-white text-sm">Genre Manager</h3>
          <p className="text-xs text-slate-400">Create custom genres and assign them to tracks or albums</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex border-b border-white/5">
        {['create', 'assign'].map(t => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`flex-1 py-2.5 text-xs font-semibold capitalize transition-colors ${
              tab === t
                ? 'text-purple-400 border-b-2 border-purple-500 bg-purple-500/5'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            {t === 'create' ? 'Create Genre' : 'Assign Genre'}
          </button>
        ))}
      </div>

      <div className="p-4">
        {/* Create Genre */}
        {tab === 'create' && (
          <form onSubmit={handleCreate} className="space-y-3">
            <div>
              <label className="text-xs font-semibold text-slate-300 mb-1.5 block">Genre Name</label>
              <input
                value={newGenre}
                onChange={e => setNewGenre(e.target.value)}
                placeholder="e.g. Afrobeats, Lo-Fi Chill, Neo-Soul..."
                className="w-full bg-slate-800 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-purple-500 transition-colors"
                maxLength={128}
              />
            </div>
            <button
              type="submit"
              disabled={creating || !newGenre.trim()}
              className="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-purple-500 hover:bg-purple-400 disabled:opacity-50 text-white text-sm font-semibold transition-all active:scale-95"
            >
              {creating ? <Loader2 size={15} className="animate-spin" /> : <Plus size={15} />}
              Create Genre
            </button>
            {createResult && (
              <div className={`flex items-start gap-2 text-xs p-3 rounded-xl ${createResult.success ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'}`}>
                {createResult.success ? <Check size={14} className="shrink-0 mt-0.5" /> : <AlertCircle size={14} className="shrink-0 mt-0.5" />}
                {createResult.message}
              </div>
            )}
          </form>
        )}

        {/* Assign Genre */}
        {tab === 'assign' && (
          <form onSubmit={handleAssign} className="space-y-3">
            <div>
              <label className="text-xs font-semibold text-slate-300 mb-1.5 block">Genre to Assign</label>
              <input
                value={assignGenre}
                onChange={e => setAssignGenre(e.target.value)}
                placeholder="e.g. Hip-Hop"
                className="w-full bg-slate-800 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-purple-500 transition-colors"
              />
            </div>
            <div>
              <label className="text-xs font-semibold text-slate-300 mb-1.5 block">Song IDs <span className="text-slate-500 font-normal">(comma or space separated)</span></label>
              <textarea
                value={songIdsRaw}
                onChange={e => setSongIdsRaw(e.target.value)}
                placeholder="300000042, 300000099, 300000211..."
                rows={2}
                className="w-full bg-slate-800 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-purple-500 transition-colors resize-none font-mono"
              />
            </div>
            <div>
              <label className="text-xs font-semibold text-slate-300 mb-1.5 block">Album IDs <span className="text-slate-500 font-normal">(optional)</span></label>
              <textarea
                value={albumIdsRaw}
                onChange={e => setAlbumIdsRaw(e.target.value)}
                placeholder="200000005, 200000012..."
                rows={2}
                className="w-full bg-slate-800 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-purple-500 transition-colors resize-none font-mono"
              />
            </div>
            <button
              type="submit"
              disabled={assigning || !assignGenre.trim()}
              className="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-purple-500 hover:bg-purple-400 disabled:opacity-50 text-white text-sm font-semibold transition-all active:scale-95"
            >
              {assigning ? <Loader2 size={15} className="animate-spin" /> : <Tag size={15} />}
              Assign Genre
            </button>
            {assignResult && (
              <div className={`flex items-start gap-2 text-xs p-3 rounded-xl ${assignResult.success ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'}`}>
                {assignResult.success ? <Check size={14} className="shrink-0 mt-0.5" /> : <AlertCircle size={14} className="shrink-0 mt-0.5" />}
                {assignResult.message}
              </div>
            )}
          </form>
        )}
      </div>
    </div>
  );
}

