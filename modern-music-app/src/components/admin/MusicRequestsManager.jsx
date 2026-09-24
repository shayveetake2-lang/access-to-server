import { useState, useEffect, useCallback } from 'react';
import { Music, CheckCircle2, XCircle, RefreshCw, User } from 'lucide-react';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { getMediaRequestsUrl, getSubsonicAuthParams } from '../../utils/api';

/**
 * MusicRequestsManager — Admin panel widget for the end-to-end media request
 * pipeline. Fetches requests submitted from either the Aether "Request a Song"
 * form or ServerFlow's media portal, and lets an admin toggle their status.
 */
export default function MusicRequestsManager() {
  const { user } = useAuth();
  const { showToast } = useToast();
  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [updatingId, setUpdatingId] = useState(null);

  const authBody = useCallback(() => {
    const authParams = new URLSearchParams(getSubsonicAuthParams(user, true));
    return {
      u: authParams.get('u') || user?.username || '',
      t: authParams.get('t') || '',
      s: authParams.get('s') || '',
    };
  }, [user]);

  const fetchRequests = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(getMediaRequestsUrl('get_requests.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(authBody()),
      });
      const data = await res.json();
      if (data?.status === 'success') {
        setRequests(data.requests || []);
      } else {
        setError(data?.message || 'Failed to load media requests.');
      }
    } catch (err) {
      setError('Network error loading media requests.');
    } finally {
      setLoading(false);
    }
  }, [authBody]);

  useEffect(() => {
    fetchRequests();
  }, [fetchRequests]);

  const handleUpdateStatus = async (id, status) => {
    setUpdatingId(id);
    try {
      const res = await fetch(getMediaRequestsUrl('update_request_status.php'), {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${user?.token || ''}`
        },
        body: JSON.stringify({ ...authBody(), id, status }),
      });
      const data = await res.json();
      if (data?.status === 'success') {
        setRequests(prev => prev.map(r => (String(r.id) === String(id) ? { ...r, status } : r)));
        showToast(`Request marked as ${status}`, 'success');
      } else {
        showToast(data?.message || 'Failed to update request', 'error');
      }
    } catch (err) {
      showToast('Network error updating request', 'error');
    } finally {
      setUpdatingId(null);
    }
  };

  const statusColor = (status) => {
    if (status === 'Fulfilled') return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
    if (status === 'Dismissed') return 'bg-slate-500/10 text-slate-400 border-slate-500/20';
    if (status === 'Approved') return 'bg-blue-500/10 text-blue-400 border-blue-500/20';
    return 'bg-amber-500/10 text-amber-400 border-amber-500/20';
  };

  const pendingCount = requests.filter(r => r.status === 'Pending').length;

  return (
    <div className="bg-slate-900/50 backdrop-blur-sm border border-white/5 rounded-2xl overflow-hidden">
      <div className="p-6 border-b border-white/5 flex items-center justify-between flex-wrap gap-3">
        <h3 className="text-xl font-semibold text-white flex items-center gap-2">
          <Music size={20} className="text-purple-400" /> Music &amp; Media Requests
        </h3>
        <div className="flex items-center gap-2">
          <span className="text-sm font-medium px-3 py-1 bg-amber-500/10 text-amber-300 border border-amber-500/20 rounded-full">
            {pendingCount} Pending
          </span>
          <button
            onClick={fetchRequests}
            className="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors"
            title="Refresh"
          >
            <RefreshCw size={14} className={loading ? 'animate-spin' : ''} />
          </button>
        </div>
      </div>

      {loading ? (
        <div className="p-12 flex justify-center"><div className="w-8 h-8 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>
      ) : error ? (
        <div className="p-6 text-red-400 text-center">{error}</div>
      ) : requests.length === 0 ? (
        <div className="p-10 text-center text-slate-400 text-sm">No media requests submitted yet.</div>
      ) : (
        <div className="divide-y divide-white/5">
          {requests.map((req) => (
            <div key={req.id} className="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                  <h4 className="font-semibold text-white truncate">{req.track_title}</h4>
                  {req.artist_name && (
                    <span className="text-xs text-purple-300 truncate">by {req.artist_name}</span>
                  )}
                  <span className="text-[10px] uppercase tracking-wide px-2 py-0.5 rounded-full bg-white/5 text-slate-400 border border-white/10">
                    {req.media_type || 'Song'}
                  </span>
                </div>
                {req.notes && <p className="text-xs text-slate-400 mt-1 truncate">{req.notes}</p>}
                <div className="flex items-center gap-1.5 text-[11px] text-slate-500 mt-1.5">
                  <User size={11} />
                  <span>{req.username}</span>
                  <span>•</span>
                  <span>{req.created_at ? new Date(req.created_at).toLocaleString() : ''}</span>
                </div>
              </div>

              <span className={`text-xs font-semibold px-2.5 py-1 rounded-full border shrink-0 ${statusColor(req.status)}`}>
                {req.status}
              </span>

              <div className="flex items-center gap-2 shrink-0">
                <button
                  onClick={() => handleUpdateStatus(req.id, 'Fulfilled')}
                  disabled={updatingId === req.id || req.status === 'Fulfilled'}
                  className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 text-xs font-semibold transition-colors disabled:opacity-40"
                >
                  <CheckCircle2 size={14} /> Fulfilled
                </button>
                <button
                  onClick={() => handleUpdateStatus(req.id, 'Dismissed')}
                  disabled={updatingId === req.id || req.status === 'Dismissed'}
                  className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-500/10 hover:bg-slate-500/20 text-slate-300 border border-slate-500/20 text-xs font-semibold transition-colors disabled:opacity-40"
                >
                  <XCircle size={14} /> Dismiss
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
