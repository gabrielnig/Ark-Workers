import { useState } from 'react';
import { useUserSearch } from '../hooks/useAccessGrants.js';
import './AssigneePicker.css';

/**
 * Same search endpoint AccessGrantsPanel already uses
 * (UserController::index), now also gated at manager level rather
 * than admin-only, since assigning a task is a manager action
 * (TaskPolicy::create). See UserController's docblock.
 */
export default function AssigneePicker({ value, onSelect }) {
  const [search, setSearch] = useState('');
  const { data: results, isFetching } = useUserSearch(search, search.trim().length >= 2);

  return (
    <div className="assignee-picker">
      <input
        className="text-input"
        type="text"
        placeholder="Search by name or email..."
        value={value?.name ?? search}
        onChange={(e) => {
          onSelect(null);
          setSearch(e.target.value);
        }}
      />

      {!value && search.trim().length >= 2 && (
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
              onClick={() => {
                onSelect(user);
                setSearch('');
              }}
            >
              <span className="assignee-name">{user.name}</span>
              <span className="assignee-email">{user.email}</span>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
