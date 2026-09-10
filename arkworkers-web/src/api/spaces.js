import { apiFetch } from './client.js';

export async function fetchSpaces() {
  const { data } = await apiFetch('/api/spaces');
  return data;
}

export async function createSpace({ name, parent_space_id, is_restricted }) {
  const { data } = await apiFetch('/api/spaces', {
    method: 'POST',
    body: JSON.stringify({ name, parent_space_id, is_restricted }),
  });
  return data;
}
