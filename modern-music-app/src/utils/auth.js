export const checkIsAdmin = (user) => {
  if (!user) return false;
  
  // Check if role is admin or isAdmin flag is explicitly true
  if (user.role === 'admin' || user.isAdmin === true) {
    return true;
  }
  
  // Check for admin properties from Subsonic/Ampache API
  if (user.adminRole === true || user.adminRole === 'true' || user.adminRole === 1) {
    return true;
  }

  // Fallback for hardcoded standard admin usernames if missing role
  const username = (user.username || '').toLowerCase();
  if (['admin', 'musicadmin', 'serveradmin'].includes(username)) {
    return true;
  }

  return false;
};
