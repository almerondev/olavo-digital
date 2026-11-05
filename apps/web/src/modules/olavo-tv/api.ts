import { api } from '@/services/http'
import type { LiveChannelItem, LiveScheduleItem, LiveStreamItem, TvNow } from '@/types/api'

export interface ScheduleInput {
  live_channel_id: number | null
  title: string
  description: string
  teacher_id: number | null
  category_id: number | null
  starts_at: string | null
  ends_at: string | null
  notify_on_start: boolean
  group_ids: number[]
}

export const tvApi = {
  now: () => api<{ data: TvNow }>('/tv/now').then((r) => r.data),
  schedule: () => api<{ data: LiveScheduleItem[] }>('/tv/schedule').then((r) => r.data),
  playback: (id: number) => api<{ data: { kind: string; url: string } }>(`/tv/streams/${id}/playback`).then((r) => r.data),
}

const BASE = '/admin/live'

export const liveAdminApi = {
  channels: () => api<{ data: LiveChannelItem[]; meta: { simulated: boolean } }>(`${BASE}/channels`),
  createChannel: (name: string) => api<unknown>(`${BASE}/channels`, { method: 'POST', body: { name } }),
  key: (id: number) => api<{ data: { stream_key: string } }>(`${BASE}/channels/${id}/key`).then((r) => r.data.stream_key),
  rotateKey: (id: number) => api<{ data: { stream_key: string } }>(`${BASE}/channels/${id}/key`, { method: 'POST' }).then((r) => r.data.stream_key),
  simulate: (id: number, type: string, session?: string) => api<void>(`${BASE}/channels/${id}/simulate`, { method: 'POST', body: { type, session } }),
  schedules: () => api<{ data: LiveScheduleItem[] }>(`${BASE}/schedules`).then((r) => r.data),
  createSchedule: (body: ScheduleInput) => api<unknown>(`${BASE}/schedules`, { method: 'POST', body }),
  updateSchedule: (id: number, body: ScheduleInput) => api<unknown>(`${BASE}/schedules/${id}`, { method: 'PUT', body }),
  cancelSchedule: (id: number) => api<unknown>(`${BASE}/schedules/${id}/cancel`, { method: 'POST' }),
  streams: () => api<{ data: LiveStreamItem[] }>(`${BASE}/streams`).then((r) => r.data),
  endStream: (id: number) => api<void>(`${BASE}/streams/${id}/end`, { method: 'POST' }),
}
