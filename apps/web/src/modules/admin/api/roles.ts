import { api } from '@/services/http'
import type { Permission, Role } from '@/types/api'

export interface RoleInput {
  name: string
  description: string
  permissions: string[]
}

export const rolesApi = {
  list: () => api<{ data: Role[] }>('/admin/roles').then((r) => r.data),
  permissions: () => api<{ data: Permission[] }>('/admin/permissions').then((r) => r.data),
  create: (input: RoleInput) => api<{ data: Role }>('/admin/roles', { method: 'POST', body: input }).then((r) => r.data),
  update: (id: number, input: RoleInput) =>
    api<{ data: Role }>(`/admin/roles/${id}`, { method: 'PUT', body: input }).then((r) => r.data),
  remove: (id: number) => api<void>(`/admin/roles/${id}`, { method: 'DELETE' }),
}
