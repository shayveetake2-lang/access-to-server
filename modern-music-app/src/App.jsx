import { HashRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from './context/AuthContext';
import Sidebar from './components/Sidebar';
import TopBar from './components/TopBar';
import StickyPlayer from './components/StickyPlayer';
import Dashboard from './components/Dashboard';
import AlbumDetails from './pages/AlbumDetails';
import ArtistDetails from './pages/ArtistDetails';
import PlaylistDetails from './pages/PlaylistDetails';
import AllAlbums from './pages/AllAlbums';
import AllArtists from './pages/AllArtists';
import AllSongs from './pages/AllSongs';
import Playlists from './pages/Playlists';
import Help from './pages/Help';
import Login from './pages/Login';
import Register from './pages/Register';
import Settings from './pages/Settings';
import AdminSettings from './pages/AdminSettings';

import MobileNav from './components/MobileNav';

function ProtectedLayout() {
  const { user, loading } = useAuth();
  
  if (loading) {
    return <div className="min-h-[100dvh] bg-slate-950 flex items-center justify-center"><div className="w-12 h-12 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div></div>;
  }
  
  if (!user) {
    return <Navigate to="/login" replace />;
  }

  return (
    <div className="flex h-[100dvh] min-h-[100dvh] max-h-[100dvh] overflow-hidden text-slate-200 bg-slate-950">
      <Sidebar />
      <div className="flex-1 flex flex-col min-w-0 h-full overflow-hidden">
        <TopBar />
        <main className="flex-1 overflow-y-auto touch-scroll px-3.5 py-4 sm:px-6 sm:py-6 md:p-8 relative pb-52 md:pb-32">
          <Routes>
            <Route path="/" element={<Dashboard />} />
            <Route path="/albums/:id" element={<AlbumDetails />} />
            <Route path="/artists/:id" element={<ArtistDetails />} />
            <Route path="/playlists/:id" element={<PlaylistDetails />} />
            <Route path="/albums" element={<AllAlbums />} />
            <Route path="/songs" element={<AllSongs />} />
            <Route path="/artists" element={<AllArtists />} />
            <Route path="/playlists" element={<Playlists />} />
            <Route path="/help" element={<Help />} />
            <Route path="/settings" element={<Settings />} />
            <Route path="/admin" element={<AdminSettings />} />
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </main>
      </div>
      <StickyPlayer />
      <MobileNav />
    </div>
  );
}

function App() {
  return (
    <Router>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/*" element={<ProtectedLayout />} />
      </Routes>
    </Router>
  );
}

export default App;
