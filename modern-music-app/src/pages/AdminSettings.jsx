import { useState, useEffect, useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import { ShieldAlert, Users, KeyRound, ArrowUpCircle, Trash2, Sparkles, Database, Music, X, Check } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import { getAmpacheUrl, getApiProxyUrl } from '../utils/api';
import AdminMetadataEditor from '../components/AdminMetadataEditor';
import GenreManager from '../components/admin/GenreManager';
import AdminContentAssigner from '../components/admin/AdminContentAssigner';
import OrphanedMedia from '../components/admin/OrphanedMedia';
import MusicRequestsManager from '../components/admin/MusicRequestsManager';

export default function AdminSettings() {
  const [searchParams] = useSearchParams();
  const initialTab = searchParams.get('tab') || 'metadata';
  
  const { user: currentUser, getAuthParams } = useAuth();
  const { showToast } = useToast();
  const [adminTab, setAdminTab] = useState(initialTab);
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Password reset modal state
  const [resetModalUser, setResetModalUser] = useState(null);
  const [newPasswordInput, setNewPasswordInput] = useState('');
  const [isResetting, setIsResetting] = useState(false);

  const fetchUsers = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(getAmpacheUrl(`action=getUsers&${getAuthParams(currentUser)}`));
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        const u = data['subsonic-response'].users?.user || [];
        setUsers(Array.isArray(u) ? u : [u]);
      } else {
        setError("Failed to load users. Ensure you have Admin privileges.");
      }
    } catch (err) {
      setError("Network error loading users.");
    } finally {
      setLoading(false);
    }
  }, [currentUser, getAuthParams]);

  useEffect(() => {
    if (adminTab === 'users') {
      fetchUsers();
    }
  }, [adminTab, fetchUsers]);

  const handleOpenResetPassword = (username) => {
    setResetModalUser(username);
    setNewPasswordInput('');
  };

  const handleConfirmResetPassword = async (e) => {
    e.preventDefault();
    if (!resetModalUser || !newPasswordInput.trim()) return;

    setIsResetting(true);
    try {
      const res = await fetch(getAmpacheUrl(`action=updateUser&username=${encodeURIComponent(resetModalUser)}&password=${encodeURIComponent(newPasswordInput.trim())}&${getAuthParams(currentUser)}`));
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        showToast(`Password for ${resetModalUser} reset successfully!`, 'success');
        setResetModalUser(null);
        setNewPasswordInput('');
      } else {
        showToast("Failed to reset password.", 'error');
      }
    } catch (err) {
      showToast("Network error resetting password.", 'error');
    } finally {
      setIsResetting(false);
    }
  };

  const handleToggleRole = async (u) => {
    const isAdmin = u.adminRole === true;
    const confirm = window.confirm(`Are you sure you want to ${isAdmin ? 'DEMOTE' : 'PROMOTE'} ${u.username}?`);
    if (!confirm) return;

    try {
      const newRole = !isAdmin ? 'true' : 'false';
      
      // 1. Subsonic API Update
      const res = await fetch(getAmpacheUrl(`action=updateUser&username=${encodeURIComponent(u.username)}&adminRole=${newRole}&${getAuthParams(currentUser)}`));
      const data = await res.json();

      // 2. Direct Ampache DB Persistence Proxy
      try {
        await fetch(`${getApiProxyUrl()}?action=updateUserRole&username=${encodeURIComponent(u.username)}&adminRole=${newRole}`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${currentUser?.token || ''}`
          }
        });
      } catch (pe) {
        console.debug("Role proxy sync:", pe);
      }

      if (data?.['subsonic-response']?.status === 'ok') {
        showToast(`${u.username} role updated to ${!isAdmin ? 'Admin' : 'Standard User'}!`, 'success');
        fetchUsers();
      } else {
        showToast("Failed to update user role.", 'error');
      }
    } catch (err) {
      showToast("Network error updating role.", 'error');
    }
  };

  const isUserAdmin = currentUser?.isAdmin || currentUser?.role === 'admin' || ['admin', 'musicadmin'].includes(currentUser?.username?.toLowerCase());
  if (!isUserAdmin) {
    return <div className="p-8 text-center text-red-400">Access Denied. Administrator privileges required.</div>;
  }

  return (
    <div className="max-w-6xl mx-auto pb-24">
      {/* Admin Header */}
      <div className="flex items-center justify-between flex-wrap gap-4 mb-6 mt-4">
        <div className="flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-purple-500/20 border border-purple-500/30 flex items-center justify-center text-purple-400 shadow-lg shadow-purple-500/10">
            <ShieldAlert size={24} />
          </div>
          <div>
            <h1 className="text-2xl sm:text-3xl font-bold text-white">Admin Control Center</h1>
            <p className="text-xs sm:text-sm text-slate-400">Manage catalog metadata, ID3 deduplication, and user privileges.</p>
          </div>
        </div>

        {/* Tab Switcher */}
        <div className="flex items-center gap-1.5 p-1 rounded-xl bg-slate-900 border border-white/10">
          <button
            onClick={() => setAdminTab('metadata')}
            className={`px-3.5 py-2 rounded-lg text-xs font-semibold flex items-center gap-2 transition-all ${
              adminTab === 'metadata'
                ? 'bg-purple-500 text-white shadow-md'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            <Sparkles size={14} />
            <span>Metadata & Deduplication</span>
          </button>
          <button
            onClick={() => setAdminTab('users')}
            className={`px-3.5 py-2 rounded-lg text-xs font-semibold flex items-center gap-2 transition-all ${
              adminTab === 'users'
                ? 'bg-purple-500 text-white shadow-md'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            <Users size={14} />
            <span>User Accounts</span>
          </button>
          <button
            onClick={() => setAdminTab('content')}
            className={`px-3.5 py-2 rounded-lg text-xs font-semibold flex items-center gap-2 transition-all ${
              adminTab === 'content'
                ? 'bg-purple-500 text-white shadow-md'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            <Database size={14} />
            <span>Content &amp; Genres</span>
          </button>
          <button
            onClick={() => setAdminTab('requests')}
            className={`px-3.5 py-2 rounded-lg text-xs font-semibold flex items-center gap-2 transition-all ${
              adminTab === 'requests'
                ? 'bg-purple-500 text-white shadow-md'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            <Music size={14} />
            <span>Requests</span>
          </button>
        </div>
      </div>

      {/* Tab 1: Metadata Editor Component */}
      {adminTab === 'metadata' && (
        <AdminMetadataEditor />
      )}

      {/* Tab 3: Content & Genre Management */}
      {adminTab === 'content' && (
        <div className="space-y-6 animate-in fade-in">
          <GenreManager />
          <OrphanedMedia />
          <AdminContentAssigner user={currentUser} />
        </div>
      )}

      {/* Tab 4: Music & Media Requests */}
      {adminTab === 'requests' && (
        <div className="animate-in fade-in">
          <MusicRequestsManager />
        </div>
      )}

      {/* Tab 2: User Accounts Table */}
      {adminTab === 'users' && (
        <div className="bg-slate-900/50 backdrop-blur-sm border border-white/5 rounded-2xl overflow-hidden animate-in fade-in">
          <div className="p-6 border-b border-white/5 flex items-center justify-between">
            <h3 className="text-xl font-semibold text-white flex items-center gap-2">
              <Users size={20} className="text-blue-400" /> Server Users
            </h3>
            <span className="text-sm font-medium px-3 py-1 bg-white/5 rounded-full text-slate-300">
              {users.length} Users
            </span>
          </div>

        {loading ? (
          <div className="p-12 flex justify-center"><div className="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div></div>
        ) : error ? (
          <div className="p-6 text-red-400 text-center">{error}</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="text-slate-400 text-sm border-b border-white/5">
                  <th className="font-medium px-6 py-4">Username</th>
                  <th className="font-medium px-6 py-4">Role</th>
                  <th className="font-medium px-6 py-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {users.map((u) => (
                  <tr key={u.username} className="border-b border-white/5 hover:bg-white/5 transition-colors">
                    <td className="px-6 py-4">
                      <div className="font-medium text-white">{u.username}</div>
                      <div className="text-xs text-slate-400">{u.email || 'No email'}</div>
                    </td>
                    <td className="px-6 py-4">
                      {u.adminRole ? (
                        <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">
                          <ShieldAlert size={12} /> Admin
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-blue-500/10 text-blue-400 border border-blue-500/20">
                          <Users size={12} /> Standard
                        </span>
                      )}
                    </td>
                    <td className="px-6 py-4 text-right space-x-2">
                      <button 
                        onClick={() => handleOpenResetPassword(u.username)}
                        className="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors"
                        title="Reset Password"
                      >
                        <KeyRound size={16} />
                      </button>
                      <button 
                        onClick={() => handleToggleRole(u)}
                        disabled={u.username === currentUser.username}
                        className="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors disabled:opacity-30"
                        title="Toggle Admin Role"
                      >
                        <ArrowUpCircle size={16} />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    )}

    {/* Reset Password Glassmorphic Modal */}
    {resetModalUser && (
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-md animate-in fade-in">
        <div className="w-full max-w-md bg-slate-900 border border-white/10 rounded-3xl p-6 shadow-2xl space-y-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2 text-white font-bold text-lg">
              <KeyRound size={20} className="text-purple-400" />
              <span>Reset Password</span>
            </div>
            <button
              onClick={() => setResetModalUser(null)}
              className="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/10"
            >
              <X size={18} />
            </button>
          </div>
          <p className="text-xs text-slate-400">
            Set a new account password for <strong className="text-purple-300">{resetModalUser}</strong>.
          </p>
          <form onSubmit={handleConfirmResetPassword} className="space-y-4">
            <input
              type="password"
              placeholder="Enter new password"
              value={newPasswordInput}
              onChange={(e) => setNewPasswordInput(e.target.value)}
              className="w-full bg-slate-800/80 border border-white/10 rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
              required
              autoFocus
            />
            <div className="flex items-center justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setResetModalUser(null)}
                className="px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white rounded-xl hover:bg-white/5"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={isResetting || !newPasswordInput.trim()}
                className="px-5 py-2 text-xs font-semibold bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white rounded-xl shadow-lg transition-all"
              >
                {isResetting ? 'Saving...' : 'Save Password'}
              </button>
            </div>
          </form>
        </div>
      </div>
    )}
  </div>
);
}
