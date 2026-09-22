import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

// Route wrapper that redirects non-admins away from admin-only pages.
// Reads the same isAdmin/role flags AuthContext refreshes from the server
// on every load, so it never blocks (or leaks access to) a stale local state.
export default function AdminRoute({ children }) {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <div className="flex justify-center py-24">
        <div className="w-10 h-10 border-4 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
      </div>
    );
  }

  const isAdmin = user?.isAdmin === true || user?.role === 'admin';
  if (!isAdmin) {
    return <Navigate to="/" replace />;
  }

  return children;
}
