import { apiFetch } from './client.js';

export async function submitAccountRequest({ name, email, phone, departmentIds }) {
  return apiFetch('/api/account-requests', {
    method: 'POST',
    body: JSON.stringify({
      name,
      email,
      phone: phone || null,
      department_ids: departmentIds,
    }),
  });
}

export async function fetchPendingAccountRequests() {
  const { data } = await apiFetch('/api/account-requests');
  return data;
}

export async function approveAccountRequest(id) {
  return apiFetch(`/api/account-requests/${id}/approve`, { method: 'POST' });
}

export async function rejectAccountRequest(id) {
  return apiFetch(`/api/account-requests/${id}/reject`, { method: 'POST' });
}
