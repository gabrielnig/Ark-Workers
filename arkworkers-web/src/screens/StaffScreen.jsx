import { useState } from 'react';
import AppShell from '../components/AppShell.jsx';
import { useStaff } from '../hooks/useStaff.js';
import './StaffScreen.css';

/**
 * BUILD-PLAN.md Phase 4's staff directory, built quickly per an
 * urgent request 2026-09-16, reusing already-approved visual
 * patterns (card rows, badges) rather than a fresh mockup round.
 */
export default function StaffScreen() {
  const { data: staff, isLoading } = useStaff();
  const [search, setSearch] = useState('');

  const filtered = (staff ?? []).filter((worker) => {
    const q = search.trim().toLowerCase();
    if (q === '') return true;
    return (
      worker.name.toLowerCase().includes(q) ||
      worker.email.toLowerCase().includes(q) ||
      worker.departments.some((d) => d.name.toLowerCase().includes(q))
    );
  });

  return (
    <AppShell>
      <div className="staff-screen">
        <h1 className="staff-title">Staff</h1>
        <p className="staff-sub">{staff?.length ?? '...'} active worker{staff?.length === 1 ? '' : 's'}</p>

        <input
          className="text-input"
          type="text"
          placeholder="Search by name, email, or department..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />

        {isLoading && <p className="empty-note">Loading...</p>}

        {!isLoading && filtered.length === 0 && (
          <p className="empty-note">No one matches that search.</p>
        )}

        {filtered.map((worker) => (
          <div key={worker.id} className="staff-row">
            <div className="staff-avatar">
              {worker.name.split(' ').filter(Boolean).slice(0, 2).map((p) => p[0].toUpperCase()).join('')}
            </div>
            <div className="staff-info">
              <div className="staff-name">
                {worker.title ? `${worker.title} ` : ''}{worker.name}
                {worker.display_name ? ` (${worker.display_name})` : ''}
                {worker.is_admin && <span className="admin-badge">Admin</span>}
              </div>
              <div className="staff-meta">{worker.email}{worker.phone ? ` \u00b7 ${worker.phone}` : ''}</div>
              <div className="staff-dept-list">
                {worker.departments.length === 0 && <span className="staff-no-dept">No department</span>}
                {worker.departments.map((d) => (
                  <span key={d.id} className="staff-dept-badge">
                    {d.name}{d.role ? ` \u00b7 ${d.role}` : ''}
                  </span>
                ))}
              </div>
            </div>
          </div>
        ))}
      </div>
    </AppShell>
  );
}
