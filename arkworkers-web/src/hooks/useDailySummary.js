import { useQuery } from '@tanstack/react-query';
import { fetchDailySummary } from '../api/reports.js';

/**
 * Only ever returns data ReportController::dailySummary already
 * scoped through TaskPolicy::view(), a restricted space without a
 * grant is excluded from every aggregate here, not just hidden
 * client-side (SECURITY.md 4.2).
 */
export function useDailySummary() {
  return useQuery({
    queryKey: ['dailySummary'],
    queryFn: fetchDailySummary,
  });
}
