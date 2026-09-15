import { useEffect } from 'react';
import './Toast.css';

/**
 * Our own confirmation surface, same reasoning as ConfirmDialog, not
 * a native browser alert(). Auto-dismisses, doesn't block input.
 */
export default function Toast({ message, onDismiss, duration = 4000 }) {
  useEffect(() => {
    const timer = setTimeout(onDismiss, duration);
    return () => clearTimeout(timer);
  }, [onDismiss, duration]);

  if (!message) return null;

  return (
    <div className="toast" role="status">
      <span className="toast-icon" aria-hidden="true">&#10003;</span>
      <span>{message}</span>
    </div>
  );
}
