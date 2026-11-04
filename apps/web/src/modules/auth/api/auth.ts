import { api } from '@/services/http'
import type { User } from '@/types/api'

export interface Credentials {
  email: string
  password: string
  remember: boolean
}

export interface ResetPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export const authApi = {
  me: () => api<{ data: User }>('/me').then((r) => r.data),
  login: (credentials: Credentials) =>
    api<{ data: User }>('/auth/login', { method: 'POST', body: credentials }).then((r) => r.data),
  logout: () => api<void>('/auth/logout', { method: 'POST' }),
  forgotPassword: (email: string) =>
    api<{ message: string }>('/auth/forgot-password', { method: 'POST', body: { email } }),
  resetPassword: (payload: ResetPayload) =>
    api<{ message: string }>('/auth/reset-password', { method: 'POST', body: payload }),
}
