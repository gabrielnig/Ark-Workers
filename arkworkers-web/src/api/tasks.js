import { apiFetch } from './client.js';

export async function fetchTasks() {
  const { data } = await apiFetch('/api/tasks');
  return data;
}

export async function fetchTasksForRoutine(routineId) {
  const { data } = await apiFetch(`/api/tasks?routine_id=${routineId}`);
  return data;
}

export async function createTask({ routineId, assignedUserId, dueAt }) {
  const { data } = await apiFetch('/api/tasks', {
    method: 'POST',
    body: JSON.stringify({
      routine_id: routineId,
      assigned_user_id: assignedUserId,
      due_at: dueAt,
    }),
  });
  return data;
}

export async function completeTask(taskId) {
  return apiFetch(`/api/tasks/${taskId}/complete`, { method: 'POST' });
}
