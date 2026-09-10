import { apiFetch } from './client.js';

export async function fetchAssetTypes() {
  const { data } = await apiFetch('/api/asset-types');
  return data;
}
