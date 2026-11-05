import { api } from '@/services/http'
import type { AuditEntry, DashboardData, Paginated } from '@/types/api'

export const reportsApi = {
  dashboard: () => api<{ data: DashboardData }>('/admin/dashboard').then((r) => r.data),
  audit: (params: URLSearchParams) => api<Paginated<AuditEntry> & { actions: string[] }>(`/admin/audit?${params}`),
}
