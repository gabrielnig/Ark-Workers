import { useState } from 'react';
import AppShell from '../components/AppShell.jsx';
import Dropdown from '../components/Dropdown.jsx';
import ConfirmDialog from '../components/ConfirmDialog.jsx';
import { useCurrentUser } from '../hooks/useCurrentUser.js';
import {
  useStaff,
  useDepartmentOptions,
  useUpdateAdminStatus,
  useJoinDepartment,
  useLeaveDepartment,
} from '../hooks/useStaff.js';
import './StaffScreen.css';

/**
 * BUILD-PLAN.md Phase 4's staff directory. Viewing is manager+,
 * editing (Admin toggle, department/role assignment) is Admin-only,
 * matching StaffController's own gate, who has company-wide access
 * isn't a department manager's call, even for their own department.
 */
export default function StaffScreen() {
  const { data: currentUser } = useCurrentUser();
  const { data: staff, isLoading } = useStaff();
  const [search, setSearch] = useState('');
  const [editingId, setEditingId] = useState(null);

  const isAdminViewer = !!currentUser?.is_admin;

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
          <StaffRow
            key={worker.id}
            worker={worker}
            isAdminViewer={isAdminViewer}
            isSelf={worker.id === currentUser?.id}
            editing={editingId === worker.id}
            onToggleEdit={() => setEditingId((id) => (id === worker.id ? null : worker.id))}
          />
        ))}
      </div>
    </AppShell>
  );
}

function StaffRow({ worker, isAdminViewer, isSelf, editing, onToggleEdit }) {
  return (
    <div className="staff-row">
      <div className="staff-avatar">
        {worker.name.split(' ').filter(Boolean).slice(0, 2).map((p) => p[0].toUpperCase()).join('')}
      </div>
      <div className="staff-info">
        <div className="staff-name">
          {worker.title ? `${worker.title} ` : ''}{worker.name}
          {worker.display_name ? ` (${worker.display_name})` : ''}
          {worker.is_admin && <span className="admin-badge">Admin</span>}
          {isAdminViewer && (
            <button type="button" className="staff-edit-link" onClick={onToggleEdit}>
              {editing ? 'Done' : 'Edit'}
            </button>
          )}
        </div>
        <div className="staff-meta">{worker.email}{worker.phone ? ` \u00b7 ${worker.phone}` : ''}</div>
        <div className="staff-dept-list">
          {worker.departments.length === 0 && !editing && <span className="staff-no-dept">No department</span>}
          {worker.departments.map((d) => (
            <span key={d.id} className="staff-dept-badge">
              {d.name}{d.role ? ` \u00b7 ${d.role}` : ''}
            </span>
          ))}
        </div>

        {editing && <StaffEditPanel worker={worker} isSelf={isSelf} />}
      </div>
    </div>
  );
}

function StaffEditPanel({ worker, isSelf }) {
  const { data: departmentOptions } = useDepartmentOptions(true);
  const updateAdmin = useUpdateAdminStatus();
  const joinDept = useJoinDepartment();
  const leaveDept = useLeaveDepartment();

  const [selectedDeptId, setSelectedDeptId] = useState('');
  const [selectedRoleId, setSelectedRoleId] = useState('');
  const [confirmRemove, setConfirmRemove] = useState(null);
  const [errorMessage, setErrorMessage] = useState(null);

  const selectedDept = departmentOptions?.find((d) => String(d.id) === selectedDeptId);
  const availableDepts = (departmentOptions ?? []).filter(
    (d) => !worker.departments.some((wd) => wd.id === d.id)
  );

  function handleAdminToggle(next) {
    setErrorMessage(null);
    updateAdmin.mutate(
      { userId: worker.id, isAdmin: next },
      { onError: (err) => setErrorMessage(err.body?.message || 'Could not update Admin status.') }
    );
  }

  function handleAddDepartment() {
    if (!selectedDeptId || !selectedRoleId) return;
    setErrorMessage(null);
    joinDept.mutate(
      { userId: worker.id, departmentId: Number(selectedDeptId), roleId: Number(selectedRoleId) },
      {
        onSuccess: () => {
          setSelectedDeptId('');
          setSelectedRoleId('');
        },
        onError: (err) => setErrorMessage(err.body?.message || 'Could not add to department.'),
      }
    );
  }

  return (
    <div className="staff-edit-panel">
      <label className="staff-edit-admin-toggle">
        <input
          type="checkbox"
          checked={worker.is_admin}
          disabled={isSelf || updateAdmin.isPending}
          onChange={(e) => handleAdminToggle(e.target.checked)}
        />
        Admin
        {isSelf && <span className="staff-edit-hint">(can't change your own)</span>}
      </label>

      {worker.departments.length > 0 && (
        <div className="staff-edit-dept-rows">
          {worker.departments.map((d) => (
            <div key={d.id} className="staff-edit-dept-row">
              <span>{d.name} &middot; {d.role}</span>
              <button
                type="button"
                className="staff-edit-remove"
                onClick={() => setConfirmRemove(d)}
              >
                Remove
              </button>
            </div>
          ))}
        </div>
      )}

      {availableDepts.length > 0 && (
        <div className="staff-edit-add-row">
          <Dropdown
            value={selectedDeptId}
            onChange={(v) => { setSelectedDeptId(v); setSelectedRoleId(''); }}
            options={[
              { value: '', label: 'Add to department...' },
              ...availableDepts.map((d) => ({ value: String(d.id), label: d.name })),
            ]}
            placeholder="Add to department..."
          />
          {selectedDept && (
            <Dropdown
              value={selectedRoleId}
              onChange={setSelectedRoleId}
              options={[
                { value: '', label: 'Role...' },
                ...selectedDept.roles.map((r) => ({ value: String(r.id), label: r.name })),
              ]}
              placeholder="Role..."
            />
          )}
          <button
            type="button"
            className="btn-small"
            disabled={!selectedDeptId || !selectedRoleId || joinDept.isPending}
            onClick={handleAddDepartment}
          >
            Add
          </button>
        </div>
      )}

      {errorMessage && <div className="form-error">{errorMessage}</div>}

      <ConfirmDialog
        open={!!confirmRemove}
        title="Remove from department?"
        message={confirmRemove ? `Remove ${worker.name} from ${confirmRemove.name}?` : ''}
        confirmLabel="Remove"
        danger
        onConfirm={() => {
          leaveDept.mutate({ userId: worker.id, departmentId: confirmRemove.id });
          setConfirmRemove(null);
        }}
        onCancel={() => setConfirmRemove(null)}
      />
    </div>
  );
}
