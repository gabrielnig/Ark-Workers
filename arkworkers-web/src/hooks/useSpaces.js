import { useQuery } from '@tanstack/react-query';
import { fetchSpaces } from '../api/spaces.js';

/**
 * Only ever returns spaces SpacePolicy already scoped to this user —
 * a restricted space without a grant is excluded from the response
 * entirely, not just hidden client-side (SECURITY.md 4.2). Same "sync
 * as little as possible" rule as useTasks.
 */
export function useSpaces() {
  return useQuery({
    queryKey: ['spaces'],
    queryFn: fetchSpaces,
  });
}
