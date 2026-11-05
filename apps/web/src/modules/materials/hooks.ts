import { useQuery } from '@tanstack/react-query'
import { categoriesApi } from '@/modules/categories/api/categories'
import type { Category } from '@/types/api'
import { materialsApi } from './api/materials'

const FIVE_MINUTES = 5 * 60 * 1000

export const CATEGORIES_KEY = ['admin', 'categories']

export function useCategories() {
  return useQuery({ queryKey: CATEGORIES_KEY, queryFn: categoriesApi.list })
}

export function useTeacherOptions() {
  return useQuery({ queryKey: ['admin', 'options', 'teachers'], queryFn: materialsApi.teachers, staleTime: FIVE_MINUTES })
}

export function useGroupOptions() {
  return useQuery({ queryKey: ['admin', 'options', 'groups'], queryFn: materialsApi.groups, staleTime: FIVE_MINUTES })
}

/** Ordena pais seguidos dos filhos, para exibir a hierarquia em listas e seletores. */
export function flattenTree<T extends Pick<Category, 'id' | 'parent_id'>>(categories: T[]): { category: T; depth: number }[] {
  const byParent = new Map<number | null, T[]>()
  for (const category of categories) {
    byParent.set(category.parent_id, [...(byParent.get(category.parent_id) ?? []), category])
  }

  const walk = (parent: number | null, depth: number): { category: T; depth: number }[] =>
    (byParent.get(parent) ?? []).flatMap((category) => [{ category, depth }, ...walk(category.id, depth + 1)])

  return walk(null, 0)
}
