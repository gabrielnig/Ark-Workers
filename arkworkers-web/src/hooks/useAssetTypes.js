import { useQuery, useMutation } from '@tanstack/react-query';
import { fetchAssetTypes, createAssetType } from '../api/assetTypes.js';
import { queryClient } from '../queryClient.js';

/**
 * Not space-scoped, a type definition is not restricted data (see
 * AssetTypeController's own docblock). Every authenticated user gets
 * the full list.
 */
export function useAssetTypes() {
  return useQuery({
    queryKey: ['assetTypes'],
    queryFn: fetchAssetTypes,
  });
}

export function useCreateAssetType() {
  return useMutation({
    mutationFn: createAssetType,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['assetTypes'] }),
  });
}
