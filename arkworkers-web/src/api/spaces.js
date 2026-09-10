import { apiFetch } from './client.js';

export async function fetchSpaces() {
  const { data } = await apiFetch('/api/spaces');
  return data;
}
