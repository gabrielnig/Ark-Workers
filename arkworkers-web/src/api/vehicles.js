import { apiFetch } from './client.js';

export async function fetchVehicles() {
  const { data } = await apiFetch('/api/vehicles');
  return data;
}

export async function fetchVehicle(vehicleId) {
  const { data } = await apiFetch(`/api/vehicles/${vehicleId}`);
  return data;
}

export async function createVehicle({ name, plateNumber, assignedDriverId }) {
  const { data } = await apiFetch('/api/vehicles', {
    method: 'POST',
    body: JSON.stringify({ name, plate_number: plateNumber, assigned_driver_id: assignedDriverId }),
  });
  return data;
}

export async function updateVehicle(vehicleId, payload) {
  const { data } = await apiFetch(`/api/vehicles/${vehicleId}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  });
  return data;
}

export async function deleteVehicle(vehicleId) {
  return apiFetch(`/api/vehicles/${vehicleId}`, { method: 'DELETE' });
}

export async function fetchVehicleLogs(vehicleId) {
  const { data } = await apiFetch(`/api/vehicles/${vehicleId}/logs`);
  return data;
}

export async function createVehicleLog(vehicleId, { type, value }) {
  const { data } = await apiFetch(`/api/vehicles/${vehicleId}/logs`, {
    method: 'POST',
    body: JSON.stringify({ type, value }),
  });
  return data;
}

export async function deleteVehicleLog(logId) {
  return apiFetch(`/api/vehicle-logs/${logId}`, { method: 'DELETE' });
}

export async function fetchVehicleIncidents(vehicleId) {
  const { data } = await apiFetch(`/api/vehicles/${vehicleId}/incidents`);
  return data;
}

export async function createVehicleIncident(vehicleId, { title, description }) {
  const { data } = await apiFetch(`/api/vehicles/${vehicleId}/incidents`, {
    method: 'POST',
    body: JSON.stringify({ title, description }),
  });
  return data;
}

export async function updateVehicleIncident(incidentId, payload) {
  const { data } = await apiFetch(`/api/vehicle-incidents/${incidentId}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  });
  return data;
}

export async function uploadIncidentPhoto(incidentId, stage, file) {
  const form = new FormData();
  form.append('stage', stage);
  form.append('photo', file);
  const { data } = await apiFetch(`/api/vehicle-incidents/${incidentId}/photos`, {
    method: 'POST',
    body: form,
  });
  return data;
}

export async function deleteIncidentPhoto(photoId) {
  return apiFetch(`/api/vehicle-incident-photos/${photoId}`, { method: 'DELETE' });
}
