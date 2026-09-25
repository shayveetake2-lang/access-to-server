import { useState, useEffect } from 'react';
import { Moon, Sun, Mail, Music, HelpCircle, Send } from 'lucide-react';
import { useToast } from '../context/ToastContext';
import { useAuth } from '../context/AuthContext';
import { submitSongRequest } from '../utils/api';

export default function Settings() {
  const [darkMode, setDarkMode] = useState(true);
  const { showToast } = useToast();
  const { user } = useAuth();

  const [trackTitle, setTrackTitle] = useState('');
  const [artistName, setArtistName] = useState('');
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);
  
  useEffect(() => {
    const stored = localStorage.getItem('aether_theme') || 'dark';
    setDarkMode(stored === 'dark');
  }, []);

  const toggleTheme = () => {
    const newMode = !darkMode;
    setDarkMode(newMode);
    localStorage.setItem('aether_theme', newMode ? 'dark' : 'light');
    if (newMode) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
    showToast(`Theme updated to ${newMode ? 'Dark' : 'Light'} Mode!`, 'success');
  };

  const handleSubmitRequest = async (e) => {
    e.preventDefault();
    if (!trackTitle.trim() || submitting) return;
    setSubmitting(true);
    try {
      const authParams = new URLSearchParams(getSubsonicAuthParams(user, true));
      const res = await fetch(getMediaRequestsUrl('submit_request.php'), {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${user?.token || ''}`
        },
        body: JSON.stringify({
          trackTitle: trackTitle.trim(),
          artistName: artistName.trim(),
          notes: notes.trim(),
          mediaType: 'Song',
          u: authParams.get('u') || user?.username || '',
          t: authParams.get('t') || '',
          s: authParams.get('s') || '',
        }),
      });
      const data = await res.json();
      if (data?.status === 'success') {
        showToast('🎵 Song request submitted!', 'success');
        setTrackTitle('');
        setArtistName('');
        setNotes('');
      } else {
        showToast(data?.message || 'Failed to submit request', 'error');
      }
    } catch (err) {
      showToast('Network error submitting request', 'error');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="max-w-4xl mx-auto pb-24">
      <h1 className="text-3xl font-bold text-white mb-8 mt-4">Account Settings</h1>

      <div className="space-y-6">
        {/* Appearance */}
        <div className="bg-slate-900/50 backdrop-blur-sm border border-white/5 rounded-2xl p-6">
          <h3 className="text-xl font-semibold text-white mb-4">Appearance</h3>
          <div className="flex items-center justify-between">
            <div>
              <p className="text-slate-300 font-medium">Dark Mode</p>
              <p className="text-slate-500 text-sm">Switch between light and dark themes</p>
            </div>
            <button 
              onClick={toggleTheme}
              className={`relative inline-flex h-7 w-14 items-center rounded-full transition-colors ${darkMode ? 'bg-purple-500' : 'bg-slate-600'}`}
            >
              <span className={`inline-block h-5 w-5 transform rounded-full bg-white transition-transform ${darkMode ? 'translate-x-8' : 'translate-x-1'}`} />
            </button>
          </div>
        </div>

        {/* Request a Song */}
        <div className="bg-slate-900/50 backdrop-blur-sm border border-white/5 rounded-2xl p-6">
          <h3 className="text-xl font-semibold text-white mb-4 flex items-center gap-2"><Music size={20} className="text-purple-400" /> Request a Song</h3>
          <p className="text-slate-400 mb-4">Can't find your favorite track? Send a request straight to the server admin's queue.</p>
          <form onSubmit={handleSubmitRequest} className="space-y-3">
            <input
              type="text"
              value={trackTitle}
              onChange={(e) => setTrackTitle(e.target.value)}
              placeholder="Song title *"
              required
              className="w-full bg-slate-800/80 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-purple-500"
            />
            <input
              type="text"
              value={artistName}
              onChange={(e) => setArtistName(e.target.value)}
              placeholder="Artist name (optional)"
              className="w-full bg-slate-800/80 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-purple-500"
            />
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="Notes (optional)"
              rows={2}
              className="w-full bg-slate-800/80 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-purple-500 resize-none"
            />
            <button
              type="submit"
              disabled={submitting || !trackTitle.trim()}
              className="inline-flex items-center gap-2 bg-purple-500 hover:bg-purple-400 disabled:opacity-50 text-white px-6 py-3 rounded-xl font-medium transition-colors"
            >
              <Send size={18} /> {submitting ? 'Submitting...' : 'Submit Request'}
            </button>
          </form>
        </div>

        {/* Contact Admin */}
        <div className="bg-slate-900/50 backdrop-blur-sm border border-white/5 rounded-2xl p-6">
          <h3 className="text-xl font-semibold text-white mb-4 flex items-center gap-2"><HelpCircle size={20} className="text-indigo-400" /> Need Help?</h3>
          <p className="text-slate-400 mb-4">Having trouble with your account or finding a bug? Contact the server administrator for support.</p>
          <a 
            href="mailto:admin@local.host?subject=Aether Audio Support Request"
            className="inline-flex items-center gap-2 bg-indigo-500/20 hover:bg-indigo-500/30 text-indigo-300 px-6 py-3 rounded-xl font-medium transition-colors border border-indigo-500/20"
          >
            <Mail size={18} /> Contact Support
          </a>
        </div>

      </div>
    </div>
  );
}
