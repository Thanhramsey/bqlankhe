import { api } from '@/api'
import type { DashboardData, DashboardFilters } from '@/types/dashboard'

export async function fetchDashboard(filters: DashboardFilters, signal?: AbortSignal) {
  const params = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== null && value !== '') params.set(key, String(value))
  })
  return (await api<DashboardData>(`/dashboard?${params.toString()}`, { signal })).data
}
