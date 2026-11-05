import { api } from '@/services/http'
import type { Course } from '@/types/api'

export interface CourseInput {
  name: string
  description: string
  is_active: boolean
}

export const coursesApi = {
  list: () => api<{ data: Course[] }>('/admin/courses').then((r) => r.data),
  create: (body: CourseInput) => api<{ data: Course }>('/admin/courses', { method: 'POST', body }),
  update: (id: number, body: CourseInput) => api<{ data: Course }>(`/admin/courses/${id}`, { method: 'PUT', body }),
  remove: (id: number) => api<void>(`/admin/courses/${id}`, { method: 'DELETE' }),
}
