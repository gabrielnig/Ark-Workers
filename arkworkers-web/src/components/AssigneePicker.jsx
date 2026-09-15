import { useEffect, useRef, useState } from 'react';
import { useUserSearch } from '../hooks/useAccessGrants.js';
import './AssigneePicker.css';

/**
 * A click-to-open dropdown, not a bare search box, opening it shows
 * the default staff list immediately (UserController::index with no
 * search term returns everyone, up to the cap), typing narrows it.
 * Never a native <select>, same reasoning as Dropdown.jsx.
 */
export default function AssigneePicker({ value, onSelect }) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const rootRef = useRef(null);
  const { data: results, isFetching } = useUserSearch(search, open);

  useEffect(() => {
    if (!open) return;

    function handleOutsideClick(event) {
      if (rootRef.current && !rootRef.current.contains(event.target)) {
        setOpen(false);
      }
    }

    function handleEscape(event) {
      if (event.key === 'Escape') setOpen(false);
    }

    document.addEventListener('pointerdown', handleOutsideClick);
    document.addEventListener('keydown', handleEscape);
    return () => {
      document.removeEventListener('pointerdown', handleOutsideClick);
      document.removeEventListener('keydown', handleEscape);
    };
  }, [open]);

  function selectUser(user) {
    onSelect(user);
    setSearch('');
    setOpen(false);
  }

  return (
    <div className="assignee-picker" ref={rootRef}>
      <button
        type="button"
        className="dropdown-trigger"
        aria-haspopup="listbox"
        aria-expanded={open}
        onClick={() => setOpen((current) => !current)}
      >
        <span className={value ? '' : 'dropdown-placeholder'}>
          {value ? value.name : 'Select staff'}
        </span>
        <span className="dropdown-chevron" aria-hidden="true">&#9662;</span>
      </button>

      {open && (
        <div className="assignee-panel">
          <input
            className="text-input assignee-search-input"
            type="text"
            placeholder="Type to search by name..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            autoFocus
          />

          <div className="assignee-results">
            {isFetching && <p className="assignee-status">Searching...</p>}
            {!isFetching && results?.length === 0 && (
              <p className="assignee-status">No one matches that search.</p>
            )}
            {!isFetching && results?.map((user) => (
              <button
                key={user.id}
                type="button"
                className="assignee-result-row"
                onClick={() => selectUser(user)}
              >
                <span className="assignee-name">{user.name}</span>
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
