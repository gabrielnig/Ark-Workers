import { apiFetch } from './client.js';

export async function fetchRoutinesForAsset(assetId) {
  const { data } = await apiFetch(`/api/routines?asset_id=${assetId}`);
  return data;
}

export async function createRoutine({ assetId, name, calendarIntervalDays, meterThreshold, requiresProof }) {
  const { data } = await apiFetch('/api/routines', {
    method: 'POST',
    body: JSON.stringify({
      asset_id: assetId,
      name,
      calendar_interval_days: calendarIntervalDays || null,
      meter_threshold: meterThreshold || null,
      requires_proof: requiresProof,
    }),
  });
  return data;
}

export async function deleteRoutine(routineId) {
  return apiFetch(`/api/routines/${routineId}`, { method: 'DELETE' });
}
