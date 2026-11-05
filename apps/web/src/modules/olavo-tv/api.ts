import { api } from '@/services/http'
import type { LiveScheduleItem, LiveStreamItem, TvNow } from '@/types/api'

export interface ScheduleInput {
  title: string
  description: string
  youtube_url: string | null
  teacher_id: number | null
  category_id: number | null
  course_id: number | null
  starts_at: string | null
  ends_at: string | null
  notify_on_start: boolean
  group_ids: number[]
}

export const tvApi = {
  now: () => api<{ data: TvNow }>('/tv/now').then((r) => r.data),
  schedule: () => api<{ data: LiveScheduleItem[] }>('/tv/schedule').then((r) => r.data),
}

const BASE = '/admin/live'

export const liveAdminApi = {
  settings: () => api<{ data: { schedule_enabled: boolean } }>(`${BASE}/settings`).then((r) => r.data),
  updateSettings: (schedule_enabled: boolean) => api<unknown>(`${BASE}/settings`, { method: 'PUT', body: { schedule_enabled } }),
  goLive: (body: ScheduleInput) => api<unknown>(`${BASE}/go-live`, { method: 'POST', body }),
  schedules: () => api<{ data: LiveScheduleItem[] }>(`${BASE}/schedules`).then((r) => r.data),
  createSchedule: (body: ScheduleInput) => api<unknown>(`${BASE}/schedules`, { method: 'POST', body }),
  updateSchedule: (id: number, body: ScheduleInput) => api<unknown>(`${BASE}/schedules/${id}`, { method: 'PUT', body }),
  cancelSchedule: (id: number) => api<unknown>(`${BASE}/schedules/${id}/cancel`, { method: 'POST' }),
  startSchedule: (id: number) => api<unknown>(`${BASE}/schedules/${id}/start`, { method: 'POST' }),
  streams: () => api<{ data: LiveStreamItem[] }>(`${BASE}/streams`).then((r) => r.data),
  endStream: (id: number) => api<void>(`${BASE}/streams/${id}/end`, { method: 'POST' }),
}
