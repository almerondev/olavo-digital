import { api } from '@/services/http'
import type { Material, MaterialType, MaterialVisibility, Option, Paginated } from '@/types/api'

export interface MaterialInput {
  title: string
  description: string
  type: MaterialType
  visibility: MaterialVisibility
  teacher_id: number | null
  cover_upload_id: number | null
  banner_upload_id: number | null
  subtitle_upload_id: number | null
  file_upload_id: number | null
  external_url: string | null
  publish_at: string | null
  allow_download: boolean
  is_featured: boolean
  category_ids: number[]
  primary_category_id: number | null
  tags: string[]
  collaborator_ids: number[]
  group_ids: number[]
}

export type MaterialAction = 'release' | 'approve' | 'reject' | 'unschedule' | 'archive' | 'restore'
export type BulkAction = 'publish' | 'archive' | 'delete' | 'set_teacher' | 'add_category'

export interface BulkResult {
  processed: number
  failed: { id: number; title: string; reason: string }[]
}

export const materialsApi = {
  list: (params: URLSearchParams) => api<Paginated<Material>>(`/admin/materials?${params}`),
  get: (id: number) => api<{ data: Material }>(`/admin/materials/${id}`).then((r) => r.data),
  create: (input: MaterialInput) => api<{ data: Material }>('/admin/materials', { method: 'POST', body: input }).then((r) => r.data),
  update: (id: number, input: MaterialInput) =>
    api<{ data: Material }>(`/admin/materials/${id}`, { method: 'PUT', body: input }).then((r) => r.data),
  remove: (id: number) => api<void>(`/admin/materials/${id}`, { method: 'DELETE' }),
  duplicate: (id: number) => api<{ data: Material }>(`/admin/materials/${id}/duplicate`, { method: 'POST' }).then((r) => r.data),
  transition: (id: number, action: MaterialAction, body?: { note: string }) =>
    api<{ data: Material }>(`/admin/materials/${id}/${action}`, { method: 'POST', body }).then((r) => r.data),
  bulk: (payload: { action: BulkAction; ids: number[]; teacher_id?: number; category_id?: number }) =>
    api<{ data: BulkResult }>('/admin/materials/bulk', { method: 'POST', body: payload }).then((r) => r.data),
  teachers: () => api<{ data: Option[] }>('/admin/options/teachers').then((r) => r.data),
  groups: () => api<{ data: Option[] }>('/admin/options/groups').then((r) => r.data),
}
