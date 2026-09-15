import { apiFetch } from './client.js';

export async function fetchAssets() {
  const { data } = await apiFetch('/api/assets');
  return data;
}

export async function fetchAsset(assetId) {
  const { data } = await apiFetch(`/api/assets/${assetId}`);
  return data;
}

export async function createAsset({ asset_type_id, space_id, name }) {
  const { data } = await apiFetch('/api/assets', {
    method: 'POST',
    body: JSON.stringify({ asset_type_id, space_id, name }),
  });
  return data;
}

export async function updateAsset(assetId, { name }) {
  const { data } = await apiFetch(`/api/assets/${assetId}`, {
    method: 'PATCH',
    body: JSON.stringify({ name }),
  });
  return data;
}

export async function decommissionAsset(assetId) {
  return apiFetch(`/api/assets/${assetId}`, { method: 'DELETE' });
}
