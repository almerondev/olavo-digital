import { api } from '@/services/http'
import type { ActiveSession, User } from '@/types/api'

export const profileApi = {
  update: (body: { name?: string; phone?: string | null; avatar_upload_id?: number | null; preferences?: Record<string, unknown> }) =>
    api<{ data: User }>('/me', { method: 'PATCH', body }).then((r) => r.data),
  password: (body: { current_password: string; password: string; password_confirmation: string }) =>
    api<void>('/me/password', { method: 'PUT', body }),
  sessions: () => api<{ data: ActiveSession[] }>('/me/sessions').then((r) => r.data),
  revokeSession: (id: string) => api<void>(`/me/sessions/${id}`, { method: 'DELETE' }),
}
