import { apiFetch } from './client.js';

export async function fetchAssetTypes() {
  const { data } = await apiFetch('/api/asset-types');
  return data;
}

export async function createAssetType({ name, category }) {
  const { data } = await apiFetch('/api/asset-types', {
    method: 'POST',
    body: JSON.stringify({ name, category: category || null }),
  });
  return data;
}
