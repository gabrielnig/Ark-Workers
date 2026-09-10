import { useQuery } from '@tanstack/react-query';
import { fetchAssetTypes } from '../api/assetTypes.js';

/**
 * Not space-scoped, a type definition is not restricted data (see
 * AssetTypeController's own docblock) - every authenticated user gets
 * the full list.
 */
export function useAssetTypes() {
  return useQuery({
    queryKey: ['assetTypes'],
    queryFn: fetchAssetTypes,
  });
}
