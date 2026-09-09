import { apiFetch } from './client.js';

export async function fetchTasks() {
  const { data } = await apiFetch('/api/tasks');
  return data;
}

export async function completeTask(taskId) {
  return apiFetch(`/api/tasks/${taskId}/complete`, { method: 'POST' });
}
