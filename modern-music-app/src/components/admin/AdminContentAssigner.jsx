import { useState } from 'react';
import { UserCog, ArrowRight, Check, AlertCircle, Loader2, Disc, User } from 'lucide-react';
import { adminPost } from './adminApi';

/**
 * AdminContentAssigner — admin-only tool to bulk-reassign "stray" songs
 * to the correct artist_id or album_id in the Ampache database.
 *
 * Only renders for users with isAdmin === true.
 */
export default function AdminContentAssigner({ user }) {
  // Guard: only visible to admins
  const isAdmin =
    user?.isAdmin === true ||
    user?.role === 'admin' ||
    ['admin', 'musicadmin', 'serveradmin'].includes((user?.username || '').toLowerCase());

  const [songIdsRaw, setSongIdsRaw]         = useState('');
  const [targetArtistId, setTargetArtistId] = useState('');
  const [targetAlbumId, setTargetAlbumId]   = useState('');
  const [saving, setSaving]                 = useState(false);
  const [result, setResult]                 = useState(null);

  if (!isAdmin) return null;

  const parseIds = (raw) =>
    raw
      .split(/[\s,]+/)
      .map(s => parseInt(s.trim(), 10))
      .filter(n => !isNaN(n) && n > 0);

  const handleSubmit = async (e) => {
    e.preventDefault();
    const songIds = parseIds(songIdsRaw);
    if (songIds.length === 0) {
      setResult({ success: false, message: 'Enter at least one song ID.' });
      return;
    }
    const artistId = targetArtistId.trim() ? parseInt(targetArtistId.trim(), 10) : null;
    const albumId  = targetAlbumId.trim()  ? parseInt(targetAlbumId.trim(),  10) : null;
    if (!artistId && !albumId) {
      setResult({ success: false, message: 'Provide a target Artist ID or Album ID.' });
      return;
    }

    setSaving(true);
    setResult(null);
    try {
      const res = await adminPost('reassignSong', {
        songIds,
        ...(artistId ? { targetArtistId: artistId } : {}),
        ...(albumId  ? { targetAlbumId: albumId }   : {}),
      });
      setResult({
        success: true,
        message: `✅ ${res.message}`,
      });
      setSongIdsRaw('');
      setTargetArtistId('');
      setTargetAlbumId('');
    } catch (err) {
      setResult({ success: false, message: err.message });
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="rounded-2xl border border-amber-500/20 bg-amber-500/5 overflow-hidden">
      {/* Header */}
      <div className="flex items-center gap-3 p-4 border-b border-amber-500/10">
        <div className="w-9 h-9 rounded-xl bg-amber-500/20 flex items-center justify-center">
          <UserCog size={18} className="text-amber-400" />
        </div>
        <div>
          <div className="flex items-center gap-2">
            <h3 className="font-bold text-white text-sm">Content Assigner</h3>
            <span className="text-[10px] px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400 font-semibold uppercase tracking-wide">Admin Only</span>
          </div>
          <p className="text-xs text-slate-400 mt-0.5">Snap stray songs into the correct artist or album profile</p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="p-4 space-y-4">
        {/* Song IDs */}
        <div>
          <label className="flex items-center gap-1.5 text-xs font-semibold text-slate-300 mb-1.5">
            <Disc size={12} className="text-slate-400" />
            Song IDs to Reassign
          </label>
          <textarea
            value={songIdsRaw}
            onChange={e => setSongIdsRaw(e.target.value)}
            placeholder="300000042, 300000099, 300000211..."
            rows={3}
            className="w-full bg-slate-800 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-amber-500 transition-colors resize-none font-mono"
          />
          <p className="text-[11px] text-slate-500 mt-1">
            Subsonic IDs (e.g. 300000042). Find them in the URL or Network tab.
          </p>
        </div>

        {/* Targets: Artist + Album side by side */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label className="flex items-center gap-1.5 text-xs font-semibold text-slate-300 mb-1.5">
              <User size={12} className="text-slate-400" />
              Target Artist ID
              <span className="text-slate-500 font-normal">(optional)</span>
            </label>
            <input
              value={targetArtistId}
              onChange={e => setTargetArtistId(e.target.value)}
              placeholder="100000007"
              type="number"
              className="w-full bg-slate-800 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-amber-500 transition-colors font-mono"
            />
          </div>
          <div>
            <label className="flex items-center gap-1.5 text-xs font-semibold text-slate-300 mb-1.5">
              <Disc size={12} className="text-slate-400" />
              Target Album ID
              <span className="text-slate-500 font-normal">(optional)</span>
            </label>
            <input
              value={targetAlbumId}
              onChange={e => setTargetAlbumId(e.target.value)}
              placeholder="200000003"
              type="number"
              className="w-full bg-slate-800 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-amber-500 transition-colors font-mono"
            />
          </div>
        </div>

        <div className="flex items-center gap-3 pt-1">
          <button
            type="submit"
            disabled={saving || !songIdsRaw.trim()}
            className="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-slate-900 text-sm font-bold transition-all active:scale-95 shadow-[0_0_16px_rgba(245,158,11,0.3)]"
          >
            {saving ? <Loader2 size={15} className="animate-spin" /> : <ArrowRight size={15} />}
            Reassign Songs
          </button>
          <p className="text-[11px] text-slate-500">This directly modifies the database. Trigger a catalog rescan in Ampache afterwards.</p>
        </div>

        {result && (
          <div className={`flex items-start gap-2 text-xs p-3 rounded-xl ${result.success ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'}`}>
            {result.success ? <Check size={14} className="shrink-0 mt-0.5" /> : <AlertCircle size={14} className="shrink-0 mt-0.5" />}
            <span>{result.message}</span>
          </div>
        )}
      </form>
    </div>
  );
}

