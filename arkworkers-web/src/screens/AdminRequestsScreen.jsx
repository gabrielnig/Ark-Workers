import { useMemo, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
  fetchPendingAccountRequests,
  approveAccountRequest,
  rejectAccountRequest,
} from '../api/accountRequests.js';
import './AdminRequestsScreen.css';

const SIDEBAR_ITEMS = ['Dashboard', 'Pending requests', 'Workers', 'Spaces', 'Tasks', 'Settings'];

function timeAgo(dateString) {
  const seconds = Math.floor((Date.now() - new Date(dateString).getTime()) / 1000);
  if (seconds < 60) return 'just now';
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`;
  const days = Math.floor(hours / 24);
  return `${days} day${days === 1 ? '' : 's'} ago`;
}

function initialsFor(name) {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join('');
}

function downloadCsv(rows) {
  const header = ['Name', 'Email', 'Phone', 'Departments', 'Requested'];
  const lines = rows.map((r) => [
    r.name,
    r.email,
    r.phone || '',
    r.departments.map((d) => d.name).join('; '),
    r.created_at,
  ]);

  const csv = [header, ...lines]
    .map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(','))
    .join('\n');

  const blob = new Blob([csv], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = 'pending-requests.csv';
  link.click();
  URL.revokeObjectURL(url);
}

export default function AdminRequestsScreen() {
  const queryClient = useQueryClient();
  const { data: requests, isLoading } = useQuery({
    queryKey: ['accountRequests'],
    queryFn: fetchPendingAccountRequests,
  });

  const [search, setSearch] = useState('');
  const [activeDepartment, setActiveDepartment] = useState('All departments');
  const [selectedIds, setSelectedIds] = useState([]);
  const [busyIds, setBusyIds] = useState([]);

  const departmentNames = useMemo(() => {
    if (!requests) return [];
    const names = new Set();
    requests.forEach((r) => r.departments.forEach((d) => names.add(d.name)));
    return Array.from(names).sort();
  }, [requests]);

  const filtered = useMemo(() => {
    if (!requests) return [];
    return requests.filter((r) => {
      const matchesSearch =
        search.trim() === '' ||
        r.name.toLowerCase().includes(search.toLowerCase()) ||
        r.email.toLowerCase().includes(search.toLowerCase());
      const matchesDepartment =
        activeDepartment === 'All departments' ||
        r.departments.some((d) => d.name === activeDepartment);
      return matchesSearch && matchesDepartment;
    });
  }, [requests, search, activeDepartment]);

  async function handleApprove(id) {
    setBusyIds((ids) => [...ids, id]);
    try {
      await approveAccountRequest(id);
      await queryClient.invalidateQueries({ queryKey: ['accountRequests'] });
      setSelectedIds((ids) => ids.filter((i) => i !== id));
    } finally {
      setBusyIds((ids) => ids.filter((i) => i !== id));
    }
  }

  async function handleReject(id) {
    setBusyIds((ids) => [...ids, id]);
    try {
      await rejectAccountRequest(id);
      await queryClient.invalidateQueries({ queryKey: ['accountRequests'] });
      setSelectedIds((ids) => ids.filter((i) => i !== id));
    } finally {
      setBusyIds((ids) => ids.filter((i) => i !== id));
    }
  }

  async function handleApproveSelected() {
    setBusyIds((ids) => [...ids, ...selectedIds]);
    try {
      await Promise.all(selectedIds.map((id) => approveAccountRequest(id)));
      await queryClient.invalidateQueries({ queryKey: ['accountRequests'] });
      setSelectedIds([]);
    } finally {
      setBusyIds([]);
    }
  }

  function toggleSelected(id) {
    setSelectedIds((ids) => (ids.includes(id) ? ids.filter((i) => i !== id) : [...ids, id]));
  }

  function toggleSelectAll() {
    if (selectedIds.length === filtered.length) {
      setSelectedIds([]);
    } else {
      setSelectedIds(filtered.map((r) => r.id));
    }
  }

  return (
    <div className="admin-shell">
      <aside className="admin-sidebar">
        <div className="admin-sidebar-brand">
          <img src="/images/logo-icon.png" alt="ArkWorkers" />
          <span>ArkWorkers</span>
        </div>
        {SIDEBAR_ITEMS.map((item) => (
          <div
            key={item}
            className={item === 'Pending requests' ? 'admin-nav-item active' : 'admin-nav-item disabled'}
          >
            {item}
          </div>
        ))}
      </aside>

      <main className="admin-main">
        <div className="admin-topbar">
          <div>
            <h1 className="admin-page-title">Pending requests</h1>
            <p className="admin-page-sub">
              Review and approve or reject sign-up requests. Approving sends an email invite link.
            </p>
          </div>
        </div>

        <div className="admin-stat-row">
          <div className="admin-stat-card">
            <p className="admin-stat-num">{requests?.length ?? '-'}</p>
            <p className="admin-stat-label">Pending</p>
          </div>
        </div>

        <div className="admin-controls-bar">
          <input
            className="admin-search-input"
            type="text"
            placeholder="Search by name or email"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          <div className="admin-filter-chips">
            <span
              className={activeDepartment === 'All departments' ? 'admin-filter-chip active' : 'admin-filter-chip'}
              onClick={() => setActiveDepartment('All departments')}
            >
              All departments
            </span>
            {departmentNames.map((name) => (
              <span
                key={name}
                className={activeDepartment === name ? 'admin-filter-chip active' : 'admin-filter-chip'}
                onClick={() => setActiveDepartment(name)}
              >
                {name}
              </span>
            ))}
          </div>
        </div>

        <div className="admin-panel">
          <div className="admin-panel-head">
            <h2>Requests</h2>
            <div className="admin-panel-actions">
              <button className="admin-bulk-btn" onClick={() => downloadCsv(filtered)} disabled={filtered.length === 0}>
                Export CSV
              </button>
              <button
                className="admin-bulk-btn primary"
                onClick={handleApproveSelected}
                disabled={selectedIds.length === 0}
              >
                Approve selected ({selectedIds.length})
              </button>
            </div>
          </div>

          {isLoading && (
            <table className="admin-req-table" aria-hidden="true">
              <tbody>
                {[0, 1, 2].map((row) => (
                  <tr key={row} className="admin-skeleton-row">
                    <td style={{ width: 24 }}><div className="admin-skeleton-block" style={{ width: 16 }} /></td>
                    <td><div className="admin-skeleton-block" style={{ width: 160 }} /></td>
                    <td><div className="admin-skeleton-block" style={{ width: 90 }} /></td>
                    <td><div className="admin-skeleton-block" style={{ width: 120 }} /></td>
                    <td><div className="admin-skeleton-block" style={{ width: 70 }} /></td>
                    <td><div className="admin-skeleton-block" style={{ width: 50 }} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}

          {!isLoading && filtered.length === 0 && requests?.length > 0 && (
            <div className="admin-empty">
              <p>No requests match your current search or department filter.</p>
              <button
                className="admin-empty-action"
                onClick={() => {
                  setSearch('');
                  setActiveDepartment('All departments');
                }}
              >
                Clear filters
              </button>
            </div>
          )}

          {!isLoading && filtered.length === 0 && !requests?.length && (
            <p className="admin-empty">No pending requests right now, everything caught up.</p>
          )}

          {!isLoading && filtered.length > 0 && (
            <>
              <table className="admin-req-table">
                <thead>
                  <tr>
                    <th>
                      <input
                        type="checkbox"
                        checked={selectedIds.length === filtered.length}
                        onChange={toggleSelectAll}
                      />
                    </th>
                    <th>Worker</th>
                    <th>Phone</th>
                    <th>Departments</th>
                    <th>Requested</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map((r) => (
                    <tr key={r.id}>
                      <td>
                        <input
                          type="checkbox"
                          checked={selectedIds.includes(r.id)}
                          onChange={() => toggleSelected(r.id)}
                        />
                      </td>
                      <td>
                        <div className="admin-name-cell">
                          <div className="admin-avatar">{initialsFor(r.name)}</div>
                          <div>
                            <p className="admin-t-name">{r.name}</p>
                            <p className="admin-t-sub">{r.email}</p>
                          </div>
                        </div>
                      </td>
                      <td className="admin-muted">{r.phone || '\u2014'}</td>
                      <td>
                        <div className="admin-dept-list">
                          {r.departments.map((d) => (
                            <span key={d.id} className="admin-dept-badge">{d.name}</span>
                          ))}
                        </div>
                      </td>
                      <td className="admin-muted">{timeAgo(r.created_at)}</td>
                      <td className="admin-actions-cell">
                        <button
                          className="admin-icon-btn reject"
                          onClick={() => handleReject(r.id)}
                          disabled={busyIds.includes(r.id)}
                        >
                          &#10005;
                        </button>
                        <button
                          className="admin-icon-btn approve"
                          onClick={() => handleApprove(r.id)}
                          disabled={busyIds.includes(r.id)}
                        >
                          &#10003;
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>

              <div className="admin-req-cards">
                {filtered.map((r) => (
                  <div key={r.id} className="admin-req-card">
                    <div className="admin-req-top">
                      <div className="admin-avatar">{initialsFor(r.name)}</div>
                      <div className="admin-req-id">
                        <p className="admin-t-name">{r.name}</p>
                        <p className="admin-t-sub">{r.email}</p>
                      </div>
                      <span className="admin-req-date">{timeAgo(r.created_at)}</span>
                    </div>
                    <div className="admin-dept-list">
                      {r.departments.map((d) => (
                        <span key={d.id} className="admin-dept-badge">{d.name}</span>
                      ))}
                    </div>
                    <div className="admin-action-row">
                      <button
                        className="admin-reject-btn"
                        onClick={() => handleReject(r.id)}
                        disabled={busyIds.includes(r.id)}
                      >
                        Reject
                      </button>
                      <button
                        className="admin-approve-btn"
                        onClick={() => handleApprove(r.id)}
                        disabled={busyIds.includes(r.id)}
                      >
                        Approve
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </>
          )}
        </div>
      </main>
    </div>
  );
}
