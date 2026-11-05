import { categoriesApi } from '@/modules/categories/api/categories'
import { teacherPanelApi } from '@/modules/teachers/api'
import type { Material, Option } from '@/types/api'
import { materialsApi, type MaterialAction, type MaterialInput } from './api/materials'

export type EditorCategory = Option & { parent_id: number | null; course_name?: string | null }

/** O editor é o mesmo para administrador e professor; muda só de onde lê e o que pode fazer. */
export interface MaterialBackend {
  scope: 'admin' | 'teacher'
  basePath: string
  listLabel: string
  get: (id: number) => Promise<Material>
  create: (input: MaterialInput) => Promise<Material>
  update: (id: number, input: MaterialInput) => Promise<Material>
  remove: (id: number) => Promise<void>
  transition: (id: number, action: MaterialAction, body?: { note: string }) => Promise<Material>
  categories: () => Promise<EditorCategory[]>
  teachers: () => Promise<Option[]>
  groups: () => Promise<Option[]>
}

export const adminBackend: MaterialBackend = {
  scope: 'admin',
  basePath: '/admin/materiais',
  listLabel: 'Biblioteca de materiais',
  get: materialsApi.get,
  create: materialsApi.create,
  update: materialsApi.update,
  remove: materialsApi.remove,
  transition: materialsApi.transition,
  categories: categoriesApi.list,
  teachers: materialsApi.teachers,
  groups: materialsApi.groups,
}

export const teacherBackend: MaterialBackend = {
  scope: 'teacher',
  basePath: '/professor/materiais',
  listLabel: 'Minhas publicações',
  get: teacherPanelApi.material,
  create: teacherPanelApi.create,
  update: teacherPanelApi.update,
  remove: teacherPanelApi.remove,
  transition: (id) => teacherPanelApi.release(id),
  categories: teacherPanelApi.categories,
  teachers: teacherPanelApi.teachers,
  groups: teacherPanelApi.groups,
}
