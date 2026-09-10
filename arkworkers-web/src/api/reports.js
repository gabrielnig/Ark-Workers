import { apiFetch } from './client.js';

export async function fetchDailySummary() {
  const { data } = await apiFetch('/api/reports/daily-summary');
  return data;
}
