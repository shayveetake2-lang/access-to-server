import { useState, useEffect } from 'react';
import { ShieldAlert, Users, KeyRound, ArrowUpCircle, Trash2 } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { getAmpacheUrl } from '../utils/api';

export default function AdminSettings() {
  const { user: currentUser, getAuthParams } = useAuth();
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const fetchUsers = async () => {
    try {
      const res = await fetch(getAmpacheUrl(`action=getUsers&${getAuthParams(currentUser)}`));
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        const u = data['subsonic-response'].users?.user || [];
        // Ensure it's an array if only 1 user returned
        setUsers(Array.isArray(u) ? u : [u]);
      } else {
        setError("Failed to load users. Ensure you have Admin privileges.");
      }
    } catch (err) {
      setError("Network error loading users.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchUsers();
  }, [currentUser]);

  const handleResetPassword = async (username) => {
    const newPass = prompt(`Enter new password for ${username}:`);
    if (!newPass) return;

    try {
      const res = await fetch(getAmpacheUrl(`action=updateUser&username=${encodeURIComponent(username)}&password=${encodeURIComponent(newPass)}&${getAuthParams(currentUser)}`));
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        alert(`Password for ${username} has been reset successfully!`);
      } else {
        alert("Failed to reset password.");
      }
    } catch (err) {
      alert("Network error.");
    }
  };

  const handleToggleRole = async (u) => {
    const isAdmin = u.adminRole === true;
    const confirm = window.confirm(`Are you sure you want to ${isAdmin ? 'DEMOTE' : 'PROMOTE'} ${u.username}?`);
    if (!confirm) return;

    try {
      const newRole = !isAdmin ? 'true' : 'false';
      const res = await fetch(getAmpacheUrl(`action=updateUser&username=${encodeURIComponent(u.username)}&adminRole=${newRole}&${getAuthParams(currentUser)}`));
      const data = await res.json();
      if (data?.['subsonic-response']?.status === 'ok') {
        alert(`${u.username} role updated!`);
        fetchUsers();
      } else {
        alert("Failed to update role.");
      }
    } catch (err) {
      alert("Network error.");
    }
  };

  const isUserAdmin = currentUser?.isAdmin || currentUser?.username?.toLowerCase() === 'admin';
  if (!isUserAdmin) {
    return <div className="p-8 text-center text-red-400">Access Denied. Administrator privileges required.</div>;
  }

  return (
    <div className="max-w-5xl mx-auto pb-24">
      <div className="flex items-center gap-4 mb-8 mt-4">
        <div className="w-12 h-12 rounded-xl bg-red-500/20 flex items-center justify-center text-red-400">
          <ShieldAlert size={24} />
        </div>
        <div>
          <h1 className="text-3xl font-bold text-white">Admin Panel</h1>
          <p className="text-slate-400">Manage users, permissions, and server settings.</p>
        </div>
      </div>

      <div className="bg-slate-900/50 backdrop-blur-sm border border-white/5 rounded-2xl overflow-hidden">
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
                        onClick={() => handleResetPassword(u.username)}
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
    </div>
  );
}
