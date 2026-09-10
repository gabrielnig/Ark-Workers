import { useQuery, useMutation } from '@tanstack/react-query';
import { fetchAssets, createAsset } from '../api/assets.js';
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

export function useCreateAsset() {
  return useMutation({
    mutationFn: createAsset,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['assets'] }),
  });
}
