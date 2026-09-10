import { apiFetch } from './client.js';

export async function fetchAccessGrants(spaceId) {
  const { data } = await apiFetch(`/api/spaces/${spaceId}/access-grants`);
  return data;
}

export async function createAccessGrant({ spaceId, user_id }) {
  const { data } = await apiFetch(`/api/spaces/${spaceId}/access-grants`, {
    method: 'POST',
    body: JSON.stringify({ user_id }),
  });
  return data;
}

export async function revokeAccessGrant(grantId) {
  return apiFetch(`/api/access-grants/${grantId}`, { method: 'DELETE' });
}

export async function searchUsers(search) {
  const params = search ? `?search=${encodeURIComponent(search)}` : '';
  const { data } = await apiFetch(`/api/users${params}`);
  return data;
}
