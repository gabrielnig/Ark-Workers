import { useState } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import AppShell from '../components/AppShell.jsx';
import Dropdown from '../components/Dropdown.jsx';
import AssigneePicker from '../components/AssigneePicker.jsx';
import ConfirmDialog from '../components/ConfirmDialog.jsx';
import { useCurrentUser } from '../hooks/useCurrentUser.js';
import {
  useVehicle,
  useUpdateVehicle,
  useDeleteVehicle,
  useVehicleLogs,
  useCreateVehicleLog,
  useDeleteVehicleLog,
  useVehicleIncidents,
  useCreateVehicleIncident,
  useUpdateVehicleIncident,
  useUploadIncidentPhoto,
  useDeleteIncidentPhoto,
} from '../hooks/useVehicles.js';
import './VehicleDetailScreen.css';

const DOCUMENT_TYPES = [
  { key: 'insurance', label: 'Insurance' },
  { key: 'roadworthiness', label: 'Roadworthiness' },
  { key: 'license', label: 'License' },
  { key: 'registration', label: 'Registration' },
];

const LOG_TYPE_OPTIONS = [
  { value: 'fuel', label: 'Fuel' },
  { value: 'mileage', label: 'Mileage' },
  { value: 'service', label: 'Service' },
];

const INCIDENT_STATUS_OPTIONS = [
  { value: 'reported', label: 'Reported' },
  { value: 'in_repair', label: 'In repair' },
  { value: 'completed', label: 'Completed' },
];

export default function VehicleDetailScreen() {
  const { vehicleId } = useParams();
  const navigate = useNavigate();
  const { data: currentUser } = useCurrentUser();
  const { data: vehicle, isLoading } = useVehicle(vehicleId);
  const updateVehicle = useUpdateVehicle(vehicleId);
  const deleteVehicle = useDeleteVehicle();

  const [editingHeader, setEditingHeader] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(false);

  const canManage = !!currentUser?.can_manage;
  const isDriver = vehicle?.assigned_driver_id === currentUser?.id;
  const canActOnVehicle = canManage || isDriver;

  if (isLoading) {
    return (
      <AppShell>
        <div className="vehicle-detail-screen"><p className="empty-note">Loading...</p></div>
      </AppShell>
    );
  }

  if (!vehicle) {
    return (
      <AppShell>
        <div className="vehicle-detail-screen">
          <p className="empty-note">Vehicle not found, or you don't have access to it.</p>
        </div>
      </AppShell>
    );
  }

  return (
    <AppShell>
      <div className="vehicle-detail-screen">
        <div className="breadcrumb">
          <Link to="/vehicles">Vehicles</Link> / {vehicle.name || vehicle.plate_number}
        </div>

        <div className="vehicle-header">
          <div className="vehicle-header-icon">&#128663;</div>
          <div>
            <h1 className="vehicle-header-title">{vehicle.name || vehicle.plate_number}</h1>
            <div className="vehicle-header-meta">
              {vehicle.plate_number}
              {vehicle.assigned_driver ? ` \u00b7 Driver: ${vehicle.assigned_driver.name}` : ' \u00b7 No driver assigned'}
            </div>
          </div>
          {canManage && (
            <div className="vehicle-header-actions">
              <button className="icon-btn" title="Edit" onClick={() => setEditingHeader((v) => !v)}>&#9998;</button>
              <button className="icon-btn danger" title="Delete" onClick={() => setConfirmDelete(true)}>&#128465;</button>
            </div>
          )}
        </div>

        {editingHeader && (
          <EditVehicleHeaderForm
            vehicle={vehicle}
            onSave={(payload) => updateVehicle.mutate(payload, { onSuccess: () => setEditingHeader(false) })}
            onCancel={() => setEditingHeader(false)}
            pending={updateVehicle.isPending}
          />
        )}

        <ConfirmDialog
          open={confirmDelete}
          title="Delete this vehicle?"
          message={`"${vehicle.name || vehicle.plate_number}" and its logs/incidents will be permanently removed.`}
          confirmLabel="Delete"
          danger
          onConfirm={() => {
            deleteVehicle.mutate(vehicle.id, { onSuccess: () => navigate('/vehicles') });
            setConfirmDelete(false);
          }}
          onCancel={() => setConfirmDelete(false)}
        />

        <DocumentsCard vehicle={vehicle} canManage={canManage} onSave={(doc) => updateVehicle.mutate({ document_expiry: doc })} />
        <LogsCard vehicleId={vehicle.id} canAct={canActOnVehicle} canManage={canManage} />
        <IncidentsCard vehicleId={vehicle.id} canAct={canActOnVehicle} canManage={canManage} />
      </div>
    </AppShell>
  );
}

function EditVehicleHeaderForm({ vehicle, onSave, onCancel, pending }) {
  const [name, setName] = useState(vehicle.name ?? '');
  const [plateNumber, setPlateNumber] = useState(vehicle.plate_number);
  const [driver, setDriver] = useState(vehicle.assigned_driver ?? null);

  function handleSubmit(e) {
    e.preventDefault();
    onSave({ name: name.trim() || null, plate_number: plateNumber.trim(), assigned_driver_id: driver?.id ?? null });
  }

  return (
    <form className="vehicle-edit-form" onSubmit={handleSubmit}>
      <input className="text-input" type="text" placeholder="Name" value={name} onChange={(e) => setName(e.target.value)} />
      <input className="text-input" type="text" placeholder="Plate number" value={plateNumber} onChange={(e) => setPlateNumber(e.target.value)} />
      <AssigneePicker value={driver} onSelect={setDriver} />
      <div className="add-space-actions">
        <button type="submit" className="btn-primary" disabled={pending}>{pending ? 'Saving...' : 'Save'}</button>
        <button type="button" className="btn-secondary" onClick={onCancel}>Cancel</button>
      </div>
    </form>
  );
}

function documentStatus(vehicle, key) {
  if (vehicle.expired_documents?.[key]) return { label: `Expired ${vehicle.expired_documents[key]}`, className: 'expired' };
  if (vehicle.expiring_soon_documents?.[key]) return { label: `Expires ${vehicle.expiring_soon_documents[key]}`, className: 'soon' };
  if (vehicle.document_expiry?.[key]) return { label: `Valid to ${vehicle.document_expiry[key]}`, className: 'ok' };
  return { label: 'Not set', className: 'unset' };
}

function DocumentsCard({ vehicle, canManage, onSave }) {
  const [editing, setEditing] = useState(false);
  const [dates, setDates] = useState(() =>
    Object.fromEntries(DOCUMENT_TYPES.map((d) => [d.key, vehicle.document_expiry?.[d.key] ?? '']))
  );

  function handleSave() {
    onSave(dates);
    setEditing(false);
  }

  return (
    <div className="card">
      <div className="card-title-row">
        <span className="card-title">Documents</span>
        {canManage && (
          <button className="btn-small" onClick={() => setEditing((v) => !v)}>{editing ? 'Cancel' : 'Edit'}</button>
        )}
      </div>

      {!editing && DOCUMENT_TYPES.map(({ key, label }) => {
        const status = documentStatus(vehicle, key);
        return (
          <div className="doc-row" key={key}>
            <span>{label}</span>
            <span className={`doc-badge ${status.className}`}>{status.label}</span>
          </div>
        );
      })}

      {editing && (
        <div className="doc-edit-form">
          {DOCUMENT_TYPES.map(({ key, label }) => (
            <div className="doc-edit-row" key={key}>
              <span className="doc-edit-label">{label}</span>
              <input
                className="text-input"
                type="date"
                value={dates[key] ?? ''}
                onChange={(e) => setDates((d) => ({ ...d, [key]: e.target.value }))}
              />
            </div>
          ))}
          <button className="btn-primary" onClick={handleSave}>Save documents</button>
        </div>
      )}
    </div>
  );
}

function LogsCard({ vehicleId, canAct }) {
  const { data: logs, isLoading } = useVehicleLogs(vehicleId);
  const createLog = useCreateVehicleLog(vehicleId);
  const deleteLog = useDeleteVehicleLog(vehicleId);
  const [showAdd, setShowAdd] = useState(false);
  const [confirmDeleteId, setConfirmDeleteId] = useState(null);

  return (
    <div className="card">
      <div className="card-title-row">
        <span className="card-title">Logs</span>
        {canAct && !showAdd && <button className="btn-small" onClick={() => setShowAdd(true)}>+ Add log</button>}
      </div>

      {showAdd && (
        <AddLogForm
          onCancel={() => setShowAdd(false)}
          onCreate={(payload) => createLog.mutate(payload, { onSuccess: () => setShowAdd(false) })}
          pending={createLog.isPending}
          error={createLog.error}
        />
      )}

      {isLoading && <p className="empty-note">Loading...</p>}
      {!isLoading && logs?.length === 0 && <p className="empty-note">No logs yet.</p>}

      {logs?.map((log) => (
        <div className="log-row" key={log.id}>
          <span className="log-type">{log.type}</span>
          <span className="log-meta">
            {log.value} {log.type === 'fuel' ? 'L' : log.type === 'mileage' ? 'km' : ''}
            {' \u00b7 '}{new Date(log.logged_at).toLocaleDateString()}
            {log.logged_by_user ? ` \u00b7 ${log.logged_by_user.name}` : ''}
          </span>
          {canAct && (
            <button className="log-delete" onClick={() => setConfirmDeleteId(log.id)}>&#10005;</button>
          )}
        </div>
      ))}

      <ConfirmDialog
        open={!!confirmDeleteId}
        title="Delete this log entry?"
        message="This can't be undone."
        confirmLabel="Delete"
        danger
        onConfirm={() => { deleteLog.mutate(confirmDeleteId); setConfirmDeleteId(null); }}
        onCancel={() => setConfirmDeleteId(null)}
      />
    </div>
  );
}

function AddLogForm({ onCancel, onCreate, pending, error }) {
  const [type, setType] = useState('fuel');
  const [value, setValue] = useState('');

  function handleSubmit(e) {
    e.preventDefault();
    if (!value) return;
    onCreate({ type, value: Number(value) });
  }

  return (
    <form className="add-log-form" onSubmit={handleSubmit}>
      <Dropdown value={type} onChange={setType} options={LOG_TYPE_OPTIONS} />
      <input
        className="text-input"
        type="number"
        step="0.01"
        min="0"
        placeholder={type === 'fuel' ? 'Litres' : type === 'mileage' ? 'Odometer reading' : 'Cost or description number'}
        value={value}
        onChange={(e) => setValue(e.target.value)}
      />
      {error && <div className="form-error">{error.body?.message || 'Could not add the log.'}</div>}
      <div className="add-space-actions">
        <button type="submit" className="btn-primary" disabled={pending || !value}>{pending ? 'Adding...' : 'Add'}</button>
        <button type="button" className="btn-secondary" onClick={onCancel}>Cancel</button>
      </div>
    </form>
  );
}

function IncidentsCard({ vehicleId, canAct, canManage }) {
  const { data: incidents, isLoading } = useVehicleIncidents(vehicleId);
  const createIncident = useCreateVehicleIncident(vehicleId);
  const [showAdd, setShowAdd] = useState(false);

  return (
    <div className="card">
      <div className="card-title-row">
        <span className="card-title">Repairs &amp; problems</span>
        {canAct && !showAdd && <button className="btn-small" onClick={() => setShowAdd(true)}>+ Report a problem</button>}
      </div>

      {showAdd && (
        <ReportProblemForm
          onCancel={() => setShowAdd(false)}
          onCreate={(payload) => createIncident.mutate(payload, { onSuccess: () => setShowAdd(false) })}
          pending={createIncident.isPending}
          error={createIncident.error}
        />
      )}

      {isLoading && <p className="empty-note">Loading...</p>}
      {!isLoading && incidents?.length === 0 && <p className="empty-note">No repairs or reported problems.</p>}

      {incidents?.map((incident) => (
        <IncidentRow key={incident.id} incident={incident} vehicleId={vehicleId} canManage={canManage} canAct={canAct} />
      ))}
    </div>
  );
}

function ReportProblemForm({ onCancel, onCreate, pending, error }) {
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');

  function handleSubmit(e) {
    e.preventDefault();
    if (!title.trim()) return;
    onCreate({ title: title.trim(), description: description.trim() || null });
  }

  return (
    <form className="report-problem-form" onSubmit={handleSubmit}>
      <input className="text-input" type="text" placeholder="What's wrong? e.g. AC not blowing cold" value={title} onChange={(e) => setTitle(e.target.value)} autoFocus />
      <textarea className="text-input textarea" placeholder="More detail (optional)" value={description} onChange={(e) => setDescription(e.target.value)} rows={3} />
      {error && <div className="form-error">{error.body?.message || 'Could not report this.'}</div>}
      <div className="add-space-actions">
        <button type="submit" className="btn-primary" disabled={pending || !title.trim()}>{pending ? 'Reporting...' : 'Report'}</button>
        <button type="button" className="btn-secondary" onClick={onCancel}>Cancel</button>
      </div>
    </form>
  );
}

function IncidentRow({ incident, vehicleId, canManage, canAct }) {
  const [expanded, setExpanded] = useState(false);
  const [editingResolution, setEditingResolution] = useState(false);
  const updateIncident = useUpdateVehicleIncident(vehicleId);
  const uploadPhoto = useUploadIncidentPhoto(vehicleId);
  const deletePhoto = useDeleteIncidentPhoto(vehicleId);

  const beforePhotos = incident.photos?.filter((p) => p.stage === 'before') ?? [];
  const afterPhotos = incident.photos?.filter((p) => p.stage === 'after') ?? [];

  function handlePhotoChange(stage, e) {
    const file = e.target.files?.[0];
    if (!file) return;
    uploadPhoto.mutate({ incidentId: incident.id, stage, file });
    e.target.value = '';
  }

  return (
    <div className="incident-row">
      <button type="button" className="incident-summary" onClick={() => setExpanded((v) => !v)}>
        <span className="incident-title">{incident.title}</span>
        <span className={`incident-status ${incident.status}`}>{incident.status.replace('_', ' ')}</span>
      </button>

      {expanded && (
        <div className="incident-detail">
          {incident.description && <p className="incident-description">{incident.description}</p>}

          {(incident.mechanic_name || incident.parts_used || incident.cost) && (
            <div className="incident-resolution-summary">
              {incident.mechanic_name && <div>Mechanic: {incident.mechanic_name}</div>}
              {incident.parts_used && <div>Parts: {incident.parts_used}</div>}
              {incident.cost && <div>Cost: {incident.cost}</div>}
            </div>
          )}

          <PhotoSection
            label="Before"
            photos={beforePhotos}
            canAct={canAct}
            onUpload={(e) => handlePhotoChange('before', e)}
            onDelete={(id) => deletePhoto.mutate(id)}
          />
          <PhotoSection
            label="After"
            photos={afterPhotos}
            canAct={canAct}
            onUpload={(e) => handlePhotoChange('after', e)}
            onDelete={(id) => deletePhoto.mutate(id)}
          />

          {canManage && !editingResolution && (
            <button className="btn-small" onClick={() => setEditingResolution(true)}>Update repair details</button>
          )}

          {canManage && editingResolution && (
            <ResolutionForm
              incident={incident}
              onSave={(payload) =>
                updateIncident.mutate(
                  { incidentId: incident.id, ...payload },
                  { onSuccess: () => setEditingResolution(false) }
                )
              }
              onCancel={() => setEditingResolution(false)}
              pending={updateIncident.isPending}
            />
          )}
        </div>
      )}
    </div>
  );
}

function PhotoSection({ label, photos, canAct, onUpload, onDelete }) {
  return (
    <div className="photo-section">
      <div className="photo-section-label">{label} photos</div>
      <div className="photo-grid">
        {photos.map((photo) => (
          <div className="photo-thumb" key={photo.id}>
            <span className="photo-thumb-placeholder">&#128247;</span>
            <button type="button" className="photo-remove" onClick={() => onDelete(photo.id)}>&#10005;</button>
          </div>
        ))}
        {canAct && (
          <label className="photo-upload-btn">
            +
            <input type="file" accept="image/jpeg,image/png" onChange={onUpload} hidden />
          </label>
        )}
      </div>
    </div>
  );
}

function ResolutionForm({ incident, onSave, onCancel, pending }) {
  const [status, setStatus] = useState(incident.status);
  const [mechanicName, setMechanicName] = useState(incident.mechanic_name ?? '');
  const [partsUsed, setPartsUsed] = useState(incident.parts_used ?? '');
  const [cost, setCost] = useState(incident.cost ?? '');

  function handleSubmit(e) {
    e.preventDefault();
    onSave({
      status,
      mechanic_name: mechanicName.trim() || null,
      parts_used: partsUsed.trim() || null,
      cost: cost === '' ? null : Number(cost),
    });
  }

  return (
    <form className="resolution-form" onSubmit={handleSubmit}>
      <Dropdown value={status} onChange={setStatus} options={INCIDENT_STATUS_OPTIONS} />
      <input className="text-input" type="text" placeholder="Mechanic name" value={mechanicName} onChange={(e) => setMechanicName(e.target.value)} />
      <textarea className="text-input textarea" placeholder="Parts used" value={partsUsed} onChange={(e) => setPartsUsed(e.target.value)} rows={2} />
      <input className="text-input" type="number" step="0.01" min="0" placeholder="Cost" value={cost} onChange={(e) => setCost(e.target.value)} />
      <div className="add-space-actions">
        <button type="submit" className="btn-primary" disabled={pending}>{pending ? 'Saving...' : 'Save'}</button>
        <button type="button" className="btn-secondary" onClick={onCancel}>Cancel</button>
      </div>
    </form>
  );
}
