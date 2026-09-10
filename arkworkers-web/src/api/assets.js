import { apiFetch } from './client.js';

export async function fetchAssets() {
  const { data } = await apiFetch('/api/assets');
  return data;
}
