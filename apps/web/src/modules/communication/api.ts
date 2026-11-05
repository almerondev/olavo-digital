import { api } from '@/services/http'
import type { AdminAnnouncement, Announcement, AppNotification, Paginated, SearchResult, SentNotification } from '@/types/api'

export interface AnnouncementInput {
  title: string
  body: string
  image_upload_id: number | null
  display_mode: string
  priority: number
  course_id: number | null
  published_at: string | null
  expires_at: string | null
  is_pinned: boolean
  requires_ack: boolean
  send_notification: boolean
  group_ids: number[]
}

export interface NotificationInput {
  title: string
  body: string
  url: string | null
  target: string
  ids: number[]
}

export const communicationApi = {
  announcements: (page: number) => api<Paginated<Announcement>>(`/announcements?page=${page}`),
  readAnnouncement: (id: number) => api<void>(`/announcements/${id}/read`, { method: 'POST' }),
  notifications: (page: number) => api<Paginated<AppNotification>>(`/me/notifications?page=${page}`),
  unreadCount: () => api<{ data: { count: number } }>('/me/notifications/unread-count').then((r) => r.data.count),
  readNotification: (id: number) => api<void>(`/me/notifications/${id}/read`, { method: 'POST' }),
  readAll: () => api<void>('/me/notifications/read-all', { method: 'POST' }),
  search: (params: URLSearchParams) => api<{ data: SearchResult }>(`/search?${params}`).then((r) => r.data),
}

export const communicationAdminApi = {
  announcements: () => api<{ data: AdminAnnouncement[] }>('/admin/announcements').then((r) => r.data),
  createAnnouncement: (body: AnnouncementInput) => api<unknown>('/admin/announcements', { method: 'POST', body }),
  updateAnnouncement: (id: number, body: AnnouncementInput) => api<unknown>(`/admin/announcements/${id}`, { method: 'PUT', body }),
  deleteAnnouncement: (id: number) => api<void>(`/admin/announcements/${id}`, { method: 'DELETE' }),
  reads: (id: number) => api<{ data: { name: string | null; read_at: string }[] }>(`/admin/announcements/${id}/reads`).then((r) => r.data),
  notifications: () => api<{ data: SentNotification[] }>('/admin/notifications').then((r) => r.data),
  sendNotification: (body: NotificationInput) => api<unknown>('/admin/notifications', { method: 'POST', body }),
  recipients: (q: string) => api<{ data: { id: number; name: string; email: string }[] }>(`/admin/notifications/recipients?q=${encodeURIComponent(q)}`).then((r) => r.data),
}
