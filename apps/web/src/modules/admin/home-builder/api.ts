import { api } from '@/services/http'
import type { BuilderBanner, BuilderLayout, BuilderSection, HomeData, LayoutVersion } from '@/types/api'

export interface SectionInput {
  title: string
  description: string
  source_type: string
  category_id: number | null
  teacher_id: number | null
  layout: string
  max_items: number
  is_active: boolean
  starts_at: string | null
  ends_at: string | null
  material_ids: number[]
  group_ids: number[]
}

export interface BannerInput {
  title: string
  description: string
  cta_label: string
  image_upload_id: number | null
  material_id: number | null
  external_url: string | null
  is_active: boolean
  starts_at: string | null
  ends_at: string | null
  group_ids: number[]
}

const BASE = '/admin/home'

export const homeBuilderApi = {
  draft: (surface: string) => api<{ data: BuilderLayout }>(`${BASE}/draft`, { method: 'POST', body: { surface } }).then((r) => r.data),
  versions: (surface: string) => api<{ data: LayoutVersion[] }>(`${BASE}/versions?surface=${surface}`).then((r) => r.data),
  publish: (id: number) => api<{ data: BuilderLayout }>(`${BASE}/layouts/${id}/publish`, { method: 'POST' }),
  restore: (id: number) => api<{ data: BuilderLayout }>(`${BASE}/layouts/${id}/restore`, { method: 'POST' }),
  discard: (id: number) => api<void>(`${BASE}/layouts/${id}`, { method: 'DELETE' }),
  preview: (id: number, group: string) => api<{ data: HomeData }>(`${BASE}/layouts/${id}/preview${group ? `?group=${group}` : ''}`).then((r) => r.data),
  createSection: (layout: number, body: SectionInput) => api<{ data: BuilderSection }>(`${BASE}/layouts/${layout}/sections`, { method: 'POST', body }),
  updateSection: (id: number, body: SectionInput) => api<{ data: BuilderSection }>(`${BASE}/sections/${id}`, { method: 'PUT', body }),
  removeSection: (id: number) => api<void>(`${BASE}/sections/${id}`, { method: 'DELETE' }),
  orderSections: (layout: number, ids: number[]) => api<void>(`${BASE}/layouts/${layout}/sections/order`, { method: 'PUT', body: { ids } }),
  createBanner: (layout: number, body: BannerInput) => api<{ data: BuilderBanner }>(`${BASE}/layouts/${layout}/banners`, { method: 'POST', body }),
  updateBanner: (id: number, body: BannerInput) => api<{ data: BuilderBanner }>(`${BASE}/banners/${id}`, { method: 'PUT', body }),
  removeBanner: (id: number) => api<void>(`${BASE}/banners/${id}`, { method: 'DELETE' }),
}
