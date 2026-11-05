import { api } from '@/services/http'
import type { Category, CategoryType } from '@/types/api'

export interface CategoryInput {
  name: string
  category_type_id: number
  parent_id: number | null
  course_id: number | null
  description: string
  icon: string
  cover_upload_id: number | null
  is_active: boolean
  visibility: Category['visibility']
  teacher_ids: number[]
  group_ids: number[]
}

export const categoriesApi = {
  list: () => api<{ data: Category[] }>('/admin/categories').then((r) => r.data),
  create: (input: CategoryInput) => api<{ data: Category }>('/admin/categories', { method: 'POST', body: input }).then((r) => r.data),
  update: (id: number, input: CategoryInput) =>
    api<{ data: Category }>(`/admin/categories/${id}`, { method: 'PUT', body: input }).then((r) => r.data),
  remove: (id: number) => api<void>(`/admin/categories/${id}`, { method: 'DELETE' }),
  reorder: (ids: number[]) => api<void>('/admin/categories/reorder', { method: 'POST', body: { ids } }),
  types: () => api<{ data: CategoryType[] }>('/admin/category-types').then((r) => r.data),
  createType: (name: string) => api<{ data: CategoryType }>('/admin/category-types', { method: 'POST', body: { name } }),
  removeType: (id: number) => api<void>(`/admin/category-types/${id}`, { method: 'DELETE' }),
}
