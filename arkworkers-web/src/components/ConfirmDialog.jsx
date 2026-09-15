import { useEffect } from 'react';
import './ConfirmDialog.css';

/**
 * The app's own confirmation modal, never window.confirm/alert, those
 * are native browser chrome we don't control the look of and can't
 * theme, same reasoning as Dropdown.jsx for native <select>.
 */
export default function ConfirmDialog({ open, title, message, confirmLabel = 'Confirm', danger, onConfirm, onCancel }) {
  useEffect(() => {
    if (!open) return;

    function handleEscape(event) {
      if (event.key === 'Escape') onCancel();
    }

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [open, onCancel]);

  if (!open) return null;

  return (
    <div className="confirm-backdrop" onClick={onCancel}>
      <div className="confirm-dialog" onClick={(e) => e.stopPropagation()}>
        {title && <h3 className="confirm-title">{title}</h3>}
        <p className="confirm-message">{message}</p>
        <div className="confirm-actions">
          <button type="button" className="btn-secondary" onClick={onCancel}>Cancel</button>
          <button
            type="button"
            className={danger ? 'btn-primary danger' : 'btn-primary'}
            onClick={onConfirm}
          >
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
