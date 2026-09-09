import { Navigate } from 'react-router-dom';
import { useCurrentUser } from '../hooks/useCurrentUser.js';

export default function RequireAdmin({ children }) {
  const { data: user, isLoading, isError } = useCurrentUser();

  if (isLoading) {
    return null;
  }

  if (isError || !user) {
    return <Navigate to="/login" replace />;
  }

  if (!user.is_admin) {
    return <p style={{ padding: 24, fontFamily: 'var(--font-family-body)' }}>You don't have access to this page.</p>;
  }

  return children;
}
