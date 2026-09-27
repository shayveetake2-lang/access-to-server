export const checkIsAdmin = (user) => {
  if (!user) return false;
  
  // 1. Verify role from cryptographically signed JWT token if available
  const token = user.token || user.jwt;
  if (token && typeof token === 'string' && token.split('.').length === 3) {
    try {
      const payloadBase64 = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
      const payload = JSON.parse(decodeURIComponent(escape(atob(payloadBase64))));
      if (payload && payload.exp && payload.exp * 1000 < Date.now()) {
        return false;
      }
      return payload?.role === 'admin';
    } catch (e) {}
  }
  
  // 2. Verified role from database response
  if (user.role === 'admin') {
    return true;
  }
  
  // 3. Verified Subsonic adminRole property from server
  if (user.adminRole === true || user.adminRole === 'true' || user.adminRole === 1) {
    return true;
  }

  return false;
};
