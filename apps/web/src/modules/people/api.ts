import { api } from '@/services/http'
import type { Paginated, StudentDetail, StudentGroupItem, StudentSummary } from '@/types/api'

export interface StudentInput {
  name: string
  email: string
  phone: string | null
  enrollment_code: string | null
  birth_date: string | null
  guardian_name: string | null
  guardian_contact: string | null
  course_ids: number[]
  group_ids: number[]
}

export interface GroupInput {
  name: string
  kind: string
  year: number | null
  is_active: boolean
}

const STUDENTS = '/admin/students'

export const peopleApi = {
  students: (params: URLSearchParams) => api<Paginated<StudentSummary>>(`${STUDENTS}?${params}`),
  student: (id: number) => api<{ data: StudentDetail }>(`${STUDENTS}/${id}`).then((r) => r.data),
  create: (body: StudentInput) => api<{ data: StudentDetail }>(STUDENTS, { method: 'POST', body }).then((r) => r.data),
  update: (id: number, body: StudentInput) => api<{ data: StudentDetail }>(`${STUDENTS}/${id}`, { method: 'PUT', body }).then((r) => r.data),
  block: (id: number) => api<void>(`${STUDENTS}/${id}/block`, { method: 'POST' }),
  unblock: (id: number) => api<void>(`${STUDENTS}/${id}/unblock`, { method: 'POST' }),
  resetPassword: (id: number) => api<void>(`${STUDENTS}/${id}/reset-password`, { method: 'POST' }),
  exportData: (id: number) => api<{ data: unknown }>(`${STUDENTS}/${id}/export`).then((r) => r.data),
  anonymize: (id: number) => api<void>(`${STUDENTS}/${id}/anonymize`, { method: 'POST' }),
  groups: () => api<{ data: StudentGroupItem[] }>('/admin/groups').then((r) => r.data),
  createGroup: (body: GroupInput) => api<unknown>('/admin/groups', { method: 'POST', body }),
  updateGroup: (id: number, body: GroupInput) => api<unknown>(`/admin/groups/${id}`, { method: 'PUT', body }),
  deleteGroup: (id: number) => api<void>(`/admin/groups/${id}`, { method: 'DELETE' }),
}
