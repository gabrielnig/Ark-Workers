import { useMutation, useQueryClient } from '@tanstack/react-query';
import { completeTask } from '../api/tasks.js';

/**
 * Completing a task while offline queues it (networkMode: offlineFirst
 * comes from the mutation defaults in queryClient.js) rather than
 * failing immediately, and replays it automatically once connectivity
 * returns. The replayed call goes through the same real endpoint,
 * with the same auth/policy checks, so a grant revoked or a task
 * deleted while this device was offline is caught then, not assumed
 * still valid from when the device went offline (SECURITY.md 6.4).
 *
 * A 409 response means another device already completed this task
 * first, per the backend's first-sync-wins conflict handling, this is
 * an expected outcome to show the worker, not a failure to retry.
 */
export function useCompleteTask() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationKey: ['completeTask'],
    mutationFn: (taskId) => completeTask(taskId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
    },
    onError: (error) => {
      if (error.status === 409) {
        queryClient.invalidateQueries({ queryKey: ['tasks'] });
      }
    },
  });
}
