import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { Music, HelpCircle } from 'lucide-react';
import { useAuth } from '../context/AuthContext';

export default function Register() {
  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [isRegistering, setIsRegistering] = useState(false);
  const navigate = useNavigate();
  const { login } = useAuth();

  const handleRegister = async (e) => {
    e.preventDefault();
    setError('');

    if (username.includes('@')) {
      setError('Username cannot contain an @ symbol.');
      return;
    }
    
    if (password.length < 10) {
      setError('Password must be at least 10 characters.');
      return;
    }

    setIsRegistering(true);
    
    try {
      const res = await fetch('/api/auth/register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, email, password })
      });

      let data;
      try {
        data = await res.json();
      } catch (jsonErr) {
        throw new Error("Invalid response received from server registration service.");
      }
      
      if (res.ok && data.status === 'success') {
        // Auto log in
        const loginRes = await login(username, password);
        if (loginRes.success) {
          navigate('/');
        } else {
          navigate('/login');
        }
      } else {
        if (res.status === 409) {
          setError(data.message || 'Username or email already taken.');
        } else {
          setError(data.message || 'Registration failed.');
        }
      }
    } catch (err) {
      setError(err.message || "Network error connecting to server.");
    } finally {
      setIsRegistering(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-950 flex flex-col items-center justify-center relative p-4">
      <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-purple-600/20 rounded-full blur-[120px]"></div>
      <div className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-indigo-600/20 rounded-full blur-[120px]"></div>

      <div className="w-full max-w-md bg-slate-900/60 backdrop-blur-2xl border border-white/10 p-8 rounded-3xl shadow-2xl relative z-10">
        <div className="flex justify-center mb-8">
          <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center shadow-lg">
            <Music size={32} className="text-white" />
          </div>
        </div>
        
        <h2 className="text-3xl font-bold text-white text-center mb-2">Create Account</h2>
        <p className="text-slate-400 text-center mb-8">Join the Aether Audio Server</p>

        {error && (
          <div className="bg-red-500/10 border border-red-500/20 text-red-400 p-3 rounded-lg text-sm mb-6 text-center">
            {error}
          </div>
        )}

        <form onSubmit={handleRegister} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-slate-300 mb-1">Username (No @ symbol)</label>
            <input 
              type="text" 
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              className="w-full bg-slate-800/50 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all placeholder-slate-500"
              placeholder="e.g. musicfan99"
              autoComplete="username"
              required
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-300 mb-1">Email Address</label>
            <input 
              type="email" 
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full bg-slate-800/50 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all placeholder-slate-500"
              placeholder="you@example.com"
              required
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-300 mb-1">Password</label>
            <input 
              type="password" 
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full bg-slate-800/50 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all placeholder-slate-500"
              placeholder="••••••••"
              autoComplete="new-password"
              required
            />
          </div>
          
          <button 
            type="submit" 
            disabled={isRegistering}
            className="w-full bg-gradient-to-r from-purple-500 to-indigo-600 text-white font-medium py-3 rounded-xl hover:opacity-90 transition-opacity flex justify-center mt-6 disabled:opacity-50"
          >
            {isRegistering ? 'Creating...' : 'Sign Up'}
          </button>
        </form>

        <div className="mt-8 text-center text-sm text-slate-400">
          Already have an account? <Link to="/login" className="text-purple-400 hover:text-purple-300 font-medium">Log in</Link>
        </div>
      </div>
    </div>
  );
}
