import { api } from '@/services/http'
import type { Material, Option, Paginated, TeacherAdmin, TeacherOverview, TeacherSocial } from '@/types/api'
import type { MaterialInput } from '@/modules/materials/api/materials'

export interface TeacherProfileInput {
  short_bio: string
  bio: string
  specialties: string[]
  photo_upload_id: number | null
  banner_upload_id: number | null
  socials: TeacherSocial[]
}

export interface TeacherInput extends TeacherProfileInput {
  name: string
  email?: string | null
  publish_mode: TeacherAdmin['publish_mode']
  show_in_cast: boolean
  status: TeacherAdmin['status']
  category_ids: number[]
}

export const adminTeachersApi = {
  list: () => api<{ data: TeacherAdmin[] }>('/admin/teachers').then((r) => r.data),
  create: (body: TeacherInput) => api<{ data: TeacherAdmin }>('/admin/teachers', { method: 'POST', body }).then((r) => r.data),
  update: (id: number, body: TeacherInput) => api<{ data: TeacherAdmin }>(`/admin/teachers/${id}`, { method: 'PUT', body }).then((r) => r.data),
  remove: (id: number) => api<void>(`/admin/teachers/${id}`, { method: 'DELETE' }),
  policy: () => api<{ data: { mode: 'immediate' | 'approval' } }>('/admin/teachers/publish-policy').then((r) => r.data.mode),
  setPolicy: (mode: string) => api<unknown>('/admin/teachers/publish-policy', { method: 'PUT', body: { mode } }),
}

export const teacherPanelApi = {
  overview: () => api<{ data: TeacherOverview }>('/teacher/overview').then((r) => r.data),
  materials: (params: URLSearchParams) => api<Paginated<Material>>(`/teacher/materials?${params}`),
  material: (id: number) => api<{ data: Material }>(`/teacher/materials/${id}`).then((r) => r.data),
  create: (body: MaterialInput) => api<{ data: Material }>('/teacher/materials', { method: 'POST', body }).then((r) => r.data),
  update: (id: number, body: MaterialInput) => api<{ data: Material }>(`/teacher/materials/${id}`, { method: 'PUT', body }).then((r) => r.data),
  remove: (id: number) => api<void>(`/teacher/materials/${id}`, { method: 'DELETE' }),
  release: (id: number) => api<{ data: Material }>(`/teacher/materials/${id}/release`, { method: 'POST' }).then((r) => r.data),
  categories: () => api<{ data: (Option & { parent_id: number | null })[] }>('/teacher/categories').then((r) => r.data),
  teachers: () => api<{ data: Option[] }>('/teacher/options/teachers').then((r) => r.data),
  groups: () => api<{ data: Option[] }>('/teacher/options/groups').then((r) => r.data),
  profile: () => api<{ data: TeacherAdmin }>('/teacher/profile').then((r) => r.data),
  updateProfile: (body: { short_bio: string; bio: string; specialties: string[]; photo_upload_id: number | null; banner_upload_id: number | null; socials: TeacherSocial[] }) =>
    api<{ data: TeacherAdmin }>('/teacher/profile', { method: 'PUT', body }).then((r) => r.data),
}
