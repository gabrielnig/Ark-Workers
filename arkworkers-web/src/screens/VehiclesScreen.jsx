import { useState } from 'react';
import { Link } from 'react-router-dom';
import AppShell from '../components/AppShell.jsx';
import AssigneePicker from '../components/AssigneePicker.jsx';
import { useCurrentUser } from '../hooks/useCurrentUser.js';
import { useVehicles, useCreateVehicle } from '../hooks/useVehicles.js';
import './VehiclesScreen.css';

export default function VehiclesScreen() {
  const { data: currentUser } = useCurrentUser();
  const { data: vehicles, isLoading } = useVehicles();
  const [showAdd, setShowAdd] = useState(false);
  const createVehicle = useCreateVehicle();

  const canManage = !!currentUser?.can_manage;

  return (
    <AppShell>
      <div className="vehicles-screen">
        <div className="vehicles-top-row">
          <h1 className="vehicles-title">Vehicles</h1>
          {canManage && !showAdd && (
            <button className="btn-small" onClick={() => setShowAdd(true)}>+ Add vehicle</button>
          )}
        </div>

        {showAdd && (
          <AddVehicleForm
            onCancel={() => setShowAdd(false)}
            onCreate={(payload) => createVehicle.mutate(payload, { onSuccess: () => setShowAdd(false) })}
            pending={createVehicle.isPending}
            error={createVehicle.error}
          />
        )}

        {isLoading && <p className="empty-note">Loading...</p>}
        {!isLoading && vehicles?.length === 0 && <p className="empty-note">No vehicles yet.</p>}

        {vehicles?.map((vehicle) => (
          <VehicleCard key={vehicle.id} vehicle={vehicle} />
        ))}
      </div>
    </AppShell>
  );
}

function VehicleCard({ vehicle }) {
  const expiredCount = Object.keys(vehicle.expired_documents ?? {}).length;
  const soonCount = Object.keys(vehicle.expiring_soon_documents ?? {}).length;

  let badge = { label: 'All documents current', className: 'ok' };
  if (expiredCount > 0) {
    badge = { label: `${expiredCount} document${expiredCount === 1 ? '' : 's'} expired`, className: 'expired' };
  } else if (soonCount > 0) {
    badge = { label: `${soonCount} document${soonCount === 1 ? '' : 's'} expiring soon`, className: 'soon' };
  }

  return (
    <Link to={`/vehicles/${vehicle.id}`} className="vehicle-card">
      <div className="vehicle-icon">&#128663;</div>
      <div className="vehicle-card-info">
        <div className="vehicle-name">{vehicle.name || vehicle.plate_number}</div>
        <div className="vehicle-plate">{vehicle.plate_number}</div>
        <div className="vehicle-driver">
          {vehicle.assigned_driver ? `Driver: ${vehicle.assigned_driver.name}` : 'No driver assigned'}
        </div>
      </div>
      <span className={`vehicle-badge ${badge.className}`}>{badge.label}</span>
    </Link>
  );
}

function AddVehicleForm({ onCancel, onCreate, pending, error }) {
  const [name, setName] = useState('');
  const [plateNumber, setPlateNumber] = useState('');
  const [driver, setDriver] = useState(null);

  function handleSubmit(e) {
    e.preventDefault();
    if (!plateNumber.trim()) return;
    onCreate({ name: name.trim() || null, plateNumber: plateNumber.trim(), assignedDriverId: driver?.id ?? null });
  }

  return (
    <form className="add-vehicle-form" onSubmit={handleSubmit}>
      <input
        className="text-input"
        type="text"
        placeholder="Name, e.g. Church Bus (optional)"
        value={name}
        onChange={(e) => setName(e.target.value)}
        autoFocus
      />
      <input
        className="text-input"
        type="text"
        placeholder="Plate number"
        value={plateNumber}
        onChange={(e) => setPlateNumber(e.target.value)}
      />
      <AssigneePicker value={driver} onSelect={setDriver} />
      {error && <div className="form-error">{error.body?.message || 'Could not create the vehicle.'}</div>}
      <div className="add-space-actions">
        <button type="submit" className="btn-primary" disabled={pending || !plateNumber.trim()}>
          {pending ? 'Creating...' : 'Create vehicle'}
        </button>
        <button type="button" className="btn-secondary" onClick={onCancel}>Cancel</button>
      </div>
    </form>
  );
}
