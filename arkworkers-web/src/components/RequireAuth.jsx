import { Navigate } from 'react-router-dom';
import { useCurrentUser } from '../hooks/useCurrentUser.js';

export default function RequireAuth({ children }) {
  const { data: user, isLoading, isError } = useCurrentUser();

  if (isLoading) {
    return null;
  }

  if (isError || !user) {
    return <Navigate to="/login" replace />;
  }

  return children;
}
