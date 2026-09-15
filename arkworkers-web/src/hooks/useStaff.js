import { useQuery } from '@tanstack/react-query';
import { fetchStaff } from '../api/staff.js';

export function useStaff() {
  return useQuery({
    queryKey: ['staff'],
    queryFn: fetchStaff,
  });
}
