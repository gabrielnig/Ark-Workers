import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { fetchAccessGrants, createAccessGrant, revokeAccessGrant, searchUsers } from '../api/accessGrants.js';

/**
 * Only ever called for a restricted space. Enabled/false lets the
 * caller skip the request entirely for a non-restricted space or
 * a non-admin viewer, rather than firing a request that will just
 * 403.
 */
export function useAccessGrants(spaceId, enabled) {
  return useQuery({
    queryKey: ['accessGrants', spaceId],
    queryFn: () => fetchAccessGrants(spaceId),
    enabled: !!spaceId && enabled,
  });
}

export function useCreateAccessGrant(spaceId) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (user_id) => createAccessGrant({ spaceId, user_id }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['accessGrants', spaceId] }),
  });
}

export function useRevokeAccessGrant(spaceId) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: revokeAccessGrant,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['accessGrants', spaceId] }),
  });
}

export function useUserSearch(search, enabled) {
  return useQuery({
    queryKey: ['userSearch', search],
    queryFn: () => searchUsers(search),
    enabled,
  });
}
