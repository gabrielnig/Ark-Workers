import { useQuery, useMutation } from '@tanstack/react-query';
import { fetchAssets, fetchAsset, createAsset, updateAsset, decommissionAsset } from '../api/assets.js';
import { queryClient } from '../queryClient.js';

/**
 * Same restriction guarantee as useSpaces: an asset in a restricted
 * space without a grant never arrives in the response to begin with.
 */
export function useAssets() {
  return useQuery({
    queryKey: ['assets'],
    queryFn: fetchAssets,
  });
}

export function useAsset(assetId) {
  return useQuery({
    queryKey: ['assets', assetId],
    queryFn: () => fetchAsset(assetId),
    enabled: !!assetId,
  });
}

export function useCreateAsset() {
  return useMutation({
    mutationFn: createAsset,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['assets'] }),
  });
}

export function useUpdateAsset(assetId) {
  return useMutation({
    mutationFn: (data) => updateAsset(assetId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['assets'] });
      queryClient.invalidateQueries({ queryKey: ['assets', assetId] });
    },
  });
}

export function useDecommissionAsset(assetId) {
  return useMutation({
    mutationFn: () => decommissionAsset(assetId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['assets'] }),
  });
}
