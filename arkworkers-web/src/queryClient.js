import { QueryClient, onlineManager } from '@tanstack/react-query';
import { completeTask } from './api/tasks.js';

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60 * 5,
      gcTime: 1000 * 60 * 60 * 24,
    },
    mutations: {
      // Pauses instead of erroring when offline, rather than
      // rejecting immediately. A paused mutation sits in the queue
      // until connectivity returns.
      networkMode: 'offlineFirst',
    },
  },
});

/**
 * Registered separately from the component that calls useMutation,
 * because a persisted/resumed mutation (one that survives an app
 * reload while still offline) can't carry a closure, only the
 * mutationKey and variables are ever written to IndexedDB. On resume,
 * React Query looks up the mutationFn to run by matching this
 * registered default against the persisted key, so this has to be in
 * place before persisted state is restored or resumePausedMutations()
 * runs, see main.jsx.
 */
queryClient.setMutationDefaults(['completeTask'], {
  mutationFn: (taskId) => completeTask(taskId),
});

/**
 * Replays every mutation that was paused while offline (including
 * ones restored from a previous session via the IndexedDB persister)
 * the moment connectivity returns. Each replayed mutation goes
 * through the real API again, so the backend's normal auth/policy/
 * conflict checks re-validate everything fresh per SECURITY.md 6.4,
 * nothing here trusts the locally-queued state as final.
 */
export function resumeQueuedMutationsOnReconnect() {
  onlineManager.subscribe((isOnline) => {
    if (isOnline) {
      queryClient.resumePausedMutations();
    }
  });
}
