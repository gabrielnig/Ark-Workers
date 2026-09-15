import { useQuery, useMutation } from '@tanstack/react-query';
import { fetchTasks, fetchTasksForRoutine, createTask } from '../api/tasks.js';
import { queryClient } from '../queryClient.js';

/**
 * Only ever returns tasks the backend's SpacePolicy/TaskPolicy already
 * scoped to this worker (their assigned spaces, restricted spaces
 * excluded without a grant), per SECURITY.md 6.1's "sync as little as
 * possible" rule. Nothing extra is stripped client-side, because
 * nothing extra ever arrives in the response to begin with.
 */
export function useTasks() {
  return useQuery({
    queryKey: ['tasks'],
    queryFn: fetchTasks,
  });
}

export function useTasksForRoutine(routineId) {
  return useQuery({
    queryKey: ['tasks', 'routine', routineId],
    queryFn: () => fetchTasksForRoutine(routineId),
    enabled: !!routineId,
  });
}

export function useCreateTask(routineId) {
  return useMutation({
    mutationFn: createTask,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['tasks', 'routine', routineId] }),
  });
}
