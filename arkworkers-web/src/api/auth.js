import { apiFetch } from './client.js';

export async function login(email, password) {
  return apiFetch('/api/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });
}

export async function logout() {
  return apiFetch('/api/auth/logout', { method: 'POST' });
}

export async function fetchCurrentUser() {
  return apiFetch('/api/user');
}
