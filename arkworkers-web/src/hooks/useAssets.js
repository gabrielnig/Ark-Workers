import { useQuery } from '@tanstack/react-query';
import { fetchAssets } from '../api/assets.js';

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
