import { useQuery, useMutation } from '@tanstack/react-query';
import { fetchRoutinesForAsset, createRoutine, deleteRoutine } from '../api/routines.js';
import { queryClient } from '../queryClient.js';

export function useRoutinesForAsset(assetId) {
  return useQuery({
    queryKey: ['routines', assetId],
    queryFn: () => fetchRoutinesForAsset(assetId),
    enabled: !!assetId,
  });
}

export function useCreateRoutine(assetId) {
  return useMutation({
    mutationFn: createRoutine,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['routines', assetId] }),
  });
}

export function useDeleteRoutine(assetId) {
  return useMutation({
    mutationFn: deleteRoutine,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['routines', assetId] }),
  });
}
