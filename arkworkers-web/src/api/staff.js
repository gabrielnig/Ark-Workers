import { apiFetch } from './client.js';

export async function fetchStaff() {
  const { data } = await apiFetch('/api/staff');
  return data;
}
