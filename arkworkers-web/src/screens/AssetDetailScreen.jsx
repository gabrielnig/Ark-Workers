import { useState } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import AppShell from '../components/AppShell.jsx';
import Dropdown from '../components/Dropdown.jsx';
import AssigneePicker from '../components/AssigneePicker.jsx';
import ConfirmDialog from '../components/ConfirmDialog.jsx';
import { useAsset, useUpdateAsset, useDecommissionAsset } from '../hooks/useAssets.js';
import { useRoutinesForAsset, useCreateRoutine, useDeleteRoutine } from '../hooks/useRoutines.js';
import { useTasksForRoutine, useCreateTask } from '../hooks/useTasks.js';
import { useCurrentUser } from '../hooks/useCurrentUser.js';
import { imageForAssetType } from '../lib/assetTypeImages.js';
import './AssetDetailScreen.css';

const SCHEDULE_TYPE_OPTIONS = [
  { value: 'calendar', label: 'Calendar interval' },
  { value: 'meter', label: 'Meter-based' },
];

/**
 * Space -> Asset -> Routines/Tasks drill-down, PRD's full spec for
 * this screen (BUILD-PLAN.md Phase 1). No automatic task generation
 * exists yet, a routine here is a saved schedule definition, not a
 * self-driving one, "assign a task" is the manual path
 * (TaskController::store) that already existed for this.
 */
export default function AssetDetailScreen() {
  const { assetId } = useParams();
  const navigate = useNavigate();
  const { data: currentUser } = useCurrentUser();
  const { data: asset, isLoading } = useAsset(assetId);
  const updateAsset = useUpdateAsset(assetId);
  const decommissionAsset = useDecommissionAsset(assetId);
  const { data: routines, isLoading: routinesLoading } = useRoutinesForAsset(assetId);
  const createRoutine = useCreateRoutine(assetId);

  const [renaming, setRenaming] = useState(false);
  const [nameDraft, setNameDraft] = useState('');
  const [showAddRoutine, setShowAddRoutine] = useState(false);
  const [confirmDecommission, setConfirmDecommission] = useState(false);

  const canManage = !!currentUser?.can_manage;
  const canDecommission = !!currentUser?.is_admin;

  function startRename() {
    setNameDraft(asset?.name ?? '');
    setRenaming(true);
  }

  function saveRename(e) {
    e.preventDefault();
    if (!nameDraft.trim()) return;
    updateAsset.mutate({ name: nameDraft.trim() }, { onSuccess: () => setRenaming(false) });
  }

  function handleDecommission() {
    decommissionAsset.mutate(undefined, {
      onSuccess: () => navigate(`/spaces/${asset.space.id}`),
    });
    setConfirmDecommission(false);
  }

  if (isLoading) {
    return (
      <AppShell>
        <div className="asset-detail-screen">
          <p className="empty-state-text">Loading...</p>
        </div>
      </AppShell>
    );
  }

  if (!asset) {
    return (
      <AppShell>
        <div className="asset-detail-screen">
          <p className="empty-state-text">Asset not found, or you don't have access to it.</p>
        </div>
      </AppShell>
    );
  }

  return (
    <AppShell>
      <div className="asset-detail-screen">
        <div className="breadcrumb">
          <Link to="/spaces">Spaces</Link> / <Link to={`/spaces/${asset.space?.id}`}>{asset.space?.name}</Link> / {asset.name}
        </div>

        <div className="asset-header">
          <div className="asset-thumb">
            <img src={imageForAssetType(asset.asset_type?.category)} alt="" />
          </div>
          <div>
            <h1 className="asset-title">{asset.name}</h1>
            <div className="asset-meta">
              {asset.asset_type?.name}{asset.asset_type?.name ? ' \u00b7 ' : ''}{asset.space?.name}
            </div>
          </div>
          {canManage && (
            <div className="asset-header-actions">
              <button className="icon-btn" title="Rename" onClick={startRename}>&#9998;</button>
              {canDecommission && (
                <button
                  className="icon-btn danger"
                  title="Decommission"
                  onClick={() => setConfirmDecommission(true)}
                  disabled={decommissionAsset.isPending}
                >
                  &#128465;
                </button>
              )}
            </div>
          )}
        </div>

        {renaming && (
          <form className="rename-form" onSubmit={saveRename}>
            <input
              className="text-input"
              type="text"
              value={nameDraft}
              onChange={(e) => setNameDraft(e.target.value)}
              autoFocus
            />
            <div className="add-space-actions">
              <button type="submit" className="btn-primary" disabled={updateAsset.isPending || !nameDraft.trim()}>
                {updateAsset.isPending ? 'Saving...' : 'Save'}
              </button>
              <button type="button" className="btn-secondary" onClick={() => setRenaming(false)}>
                Cancel
              </button>
            </div>
          </form>
        )}

        <div className="card">
          <div className="card-title-row">
            <span className="card-title">Routines</span>
            {canManage && !showAddRoutine && (
              <button className="btn-small" onClick={() => setShowAddRoutine(true)}>+ Add routine</button>
            )}
          </div>

          {routinesLoading && <p className="empty-note">Loading...</p>}

          {!routinesLoading && (!routines || routines.length === 0) && !showAddRoutine && (
            <p className="empty-note">No routines yet for this asset.</p>
          )}

          {routines?.map((routine) => (
            <RoutineRow key={routine.id} routine={routine} assetId={assetId} canManage={canManage} />
          ))}

          {showAddRoutine && (
            <AddRoutineForm
              onCancel={() => setShowAddRoutine(false)}
              onCreate={(payload) =>
                createRoutine.mutate(
                  { assetId, ...payload },
                  { onSuccess: () => setShowAddRoutine(false) }
                )
              }
              pending={createRoutine.isPending}
              error={createRoutine.error}
            />
          )}
        </div>

        <ConfirmDialog
          open={confirmDecommission}
          title="Decommission this asset?"
          message={`"${asset.name}" and its history will stay intact for 30 days, then be permanently removed.`}
          confirmLabel="Decommission"
          danger
          onConfirm={handleDecommission}
          onCancel={() => setConfirmDecommission(false)}
        />
      </div>
    </AppShell>
  );
}

function scheduleLabel(routine) {
  if (routine.calendar_interval_days) {
    const n = routine.calendar_interval_days;
    return `Every ${n} day${n === 1 ? '' : 's'}`;
  }
  if (routine.meter_threshold) {
    return `Every ${routine.meter_threshold} uses`;
  }
  return '';
}

function statusLabel(task) {
  if (task.status === 'completed') return 'completed';
  const dueAt = new Date(task.due_at);
  return dueAt < new Date() ? 'overdue' : 'pending';
}

function RoutineRow({ routine, assetId, canManage }) {
  const { data: tasks } = useTasksForRoutine(routine.id);
  const deleteRoutine = useDeleteRoutine(assetId);
  const createTask = useCreateTask(routine.id);
  const [showAssign, setShowAssign] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(false);

  return (
    <div className="routine-row">
      <div className="routine-top">
        <span className="routine-name">
          {routine.name}
          {routine.requires_proof && <span className="proof-badge">Proof required</span>}
        </span>
        <span className="routine-schedule">{scheduleLabel(routine)}</span>
        {canManage && (
          <button
            className="icon-btn small danger"
            title="Delete routine"
            disabled={deleteRoutine.isPending}
            onClick={() => setConfirmDelete(true)}
          >
            &#10005;
          </button>
        )}
      </div>

      {(!tasks || tasks.length === 0) && <p className="empty-note">No tasks yet.</p>}

      {tasks?.map((task) => (
        <div className="task-row" key={task.id}>
          <span>Assigned to {task.assigned_user?.name ?? 'someone'}</span>
          <span className="task-due">{task.status === 'completed' ? 'Completed' : `Due ${new Date(task.due_at).toLocaleDateString()}`}</span>
          <span className={`status-pill ${statusLabel(task)}`}>{statusLabel(task)}</span>
        </div>
      ))}

      {canManage && !showAssign && (
        <button className="add-task-link" onClick={() => setShowAssign(true)}>+ Assign a task</button>
      )}

      {showAssign && (
        <AssignTaskForm
          onCancel={() => setShowAssign(false)}
          onCreate={(payload) => createTask.mutate(
            { routineId: routine.id, ...payload },
            { onSuccess: () => setShowAssign(false) }
          )}
          pending={createTask.isPending}
          error={createTask.error}
        />
      )}

      <ConfirmDialog
        open={confirmDelete}
        title="Delete this routine?"
        message={`Existing task history for "${routine.name}" stays intact, only the schedule itself is removed.`}
        confirmLabel="Delete"
        danger
        onConfirm={() => {
          deleteRoutine.mutate(routine.id);
          setConfirmDelete(false);
        }}
        onCancel={() => setConfirmDelete(false)}
      />
    </div>
  );
}

function AssignTaskForm({ onCancel, onCreate, pending, error }) {
  const [assignee, setAssignee] = useState(null);
  const [dueAt, setDueAt] = useState('');

  function handleSubmit(e) {
    e.preventDefault();
    if (!assignee || !dueAt) return;
    onCreate({ assignedUserId: assignee.id, dueAt: new Date(dueAt).toISOString() });
  }

  return (
    <form className="assign-task-form" onSubmit={handleSubmit}>
      <AssigneePicker value={assignee} onSelect={setAssignee} />
      <div className="date-field">
        <input
          className="text-input"
          type="date"
          value={dueAt}
          onChange={(e) => setDueAt(e.target.value)}
          required
        />
        {!dueAt && (
          <span className="date-field-placeholder">
            <span className="date-field-icon" aria-hidden="true">&#128197;</span>
            Select due date
          </span>
        )}
      </div>
      {error && <div className="form-error">{error.body?.message || 'Could not assign the task.'}</div>}
      <div className="add-space-actions">
        <button type="submit" className="btn-primary" disabled={pending || !assignee || !dueAt}>
          {pending ? 'Assigning...' : 'Assign'}
        </button>
        <button type="button" className="btn-secondary" onClick={onCancel}>Cancel</button>
      </div>
    </form>
  );
}

function AddRoutineForm({ onCancel, onCreate, pending, error }) {
  const [name, setName] = useState('');
  const [scheduleType, setScheduleType] = useState('calendar');
  const [value, setValue] = useState('');
  const [requiresProof, setRequiresProof] = useState(false);

  function handleSubmit(e) {
    e.preventDefault();
    if (!name.trim() || !value) return;
    onCreate({
      name: name.trim(),
      calendarIntervalDays: scheduleType === 'calendar' ? Number(value) : null,
      meterThreshold: scheduleType === 'meter' ? Number(value) : null,
      requiresProof,
    });
  }

  return (
    <form className="add-routine-form" onSubmit={handleSubmit}>
      <input
        className="text-input"
        type="text"
        placeholder="Routine name, e.g. Filter cleaning"
        value={name}
        onChange={(e) => setName(e.target.value)}
        autoFocus
      />
      <Dropdown
        value={scheduleType}
        onChange={(v) => { setScheduleType(v); setValue(''); }}
        options={SCHEDULE_TYPE_OPTIONS}
      />
      <input
        className="text-input"
        type="number"
        min="1"
        placeholder={scheduleType === 'calendar' ? 'Every N days' : 'Every N uses'}
        value={value}
        onChange={(e) => setValue(e.target.value)}
      />
      <label className="requires-proof-check">
        <input type="checkbox" checked={requiresProof} onChange={(e) => setRequiresProof(e.target.checked)} />
        Requires photo proof
      </label>
      {error && <div className="form-error">{error.body?.message || 'Could not create the routine.'}</div>}
      <div className="add-space-actions">
        <button type="submit" className="btn-primary" disabled={pending || !name.trim() || !value}>
          {pending ? 'Creating...' : 'Create routine'}
        </button>
        <button type="button" className="btn-secondary" onClick={onCancel}>Cancel</button>
      </div>
    </form>
  );
}
