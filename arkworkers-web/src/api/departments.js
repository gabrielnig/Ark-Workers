import { apiFetch } from './client.js';

export async function fetchDepartments() {
  const { data } = await apiFetch('/api/departments');
  return data;
}
