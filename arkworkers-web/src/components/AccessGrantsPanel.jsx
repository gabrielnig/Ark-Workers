import { useState } from 'react';
import { useAccessGrants, useCreateAccessGrant, useRevokeAccessGrant, useUserSearch } from '../hooks/useAccessGrants.js';
import './AccessGrantsPanel.css';

/**
 * Only ever rendered for a restricted Space, by an admin (the caller
 * checks both, see SpacesScreen.jsx). Backend enforces the same
 * admin-only rule independently, this is convenience UI, not the
 * actual security boundary.
 */
export default function AccessGrantsPanel({ spaceId }) {
  const { data: grants, isLoading } = useAccessGrants(spaceId, true);
  const createGrant = useCreateAccessGrant(spaceId);
  const revokeGrant = useRevokeAccessGrant(spaceId);

  const [search, setSearch] = useState('');
  const [showSearch, setShowSearch] = useState(false);
  const { data: results, isFetching: searching } = useUserSearch(search, showSearch && search.trim().length >= 2);

  const grantedUserIds = new Set((grants ?? []).map((g) => g.user_id));

  return (
    <div className="access-grants-panel">
      <div className="access-grants-header">
        <h3>Who can access this space</h3>
        {!showSearch && (
          <button className="btn-add-space secondary" onClick={() => setShowSearch(true)}>
            + Grant access
          </button>
        )}
      </div>

      {showSearch && (
        <div className="grant-search">
          <input
            className="text-input"
            type="text"
            placeholder="Search by name or email..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            autoFocus
          />
          <button
            type="button"
            className="btn-secondary"
            onClick={() => {
              setShowSearch(false);
              setSearch('');
            }}
          >
            Cancel
          </button>

          {search.trim().length >= 2 && (
            <div className="grant-search-results">
              {searching && <p className="grant-search-status">Searching...</p>}
              {!searching && results?.length === 0 && (
                <p className="grant-search-status">No one matches that search.</p>
              )}
              {!searching && results?.map((user) => {
                const alreadyGranted = grantedUserIds.has(user.id);
                return (
                  <div key={user.id} className="grant-search-row">
                    <div>
                      <div className="grant-user-name">{user.name}</div>
                      <div className="grant-user-email">{user.email}</div>
                    </div>
                    <button
                      className="btn-primary"
                      disabled={alreadyGranted || createGrant.isPending}
                      onClick={() =>
                        createGrant.mutate(user.id, {
                          onSuccess: () => {
                            setSearch('');
                            setShowSearch(false);
                          },
                        })
                      }
                    >
                      {alreadyGranted ? 'Already has access' : 'Grant access'}
                    </button>
                  </div>
                );
              })}
            </div>
          )}

          {createGrant.isError && (
            <div className="form-error">
              {createGrant.error?.body?.message || 'Could not grant access. Please try again.'}
            </div>
          )}
        </div>
      )}

      {isLoading && <p className="grant-search-status">Loading...</p>}

      {!isLoading && (!grants || grants.length === 0) && (
        <p className="grant-search-status">
          No one has been granted access yet. This space is currently visible to Admins only.
        </p>
      )}

      {!isLoading && grants?.length > 0 && (
        <div className="grant-list">
          {grants.map((grant) => (
            <div key={grant.id} className="grant-row">
              <div>
                <div className="grant-user-name">{grant.user?.name}</div>
                <div className="grant-user-email">
                  {grant.user?.email}, granted by {grant.granted_by_name ?? 'an admin'}
                </div>
              </div>
              <button
                className="btn-secondary"
                disabled={revokeGrant.isPending}
                onClick={() => revokeGrant.mutate(grant.id)}
              >
                Revoke
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
