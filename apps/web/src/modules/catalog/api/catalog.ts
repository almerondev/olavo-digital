import { api } from '@/services/http'
import type { CardMaterial, HistoryEntry, HomeData, MaterialDetail, Option, Paginated, PlaybackSource, SectionPage } from '@/types/api'

export type Surface = 'home' | 'materials'

export const catalogApi = {
  home: (surface: Surface) => api<{ data: HomeData }>(`/home?surface=${surface}`).then((r) => r.data),
  sectionItems: (id: number, page: number) =>
    api<{ data: SectionPage }>(`/home/sections/${id}/items?page=${page}`).then((r) => r.data),
  materials: (params: URLSearchParams, page: number) => {
    const query = new URLSearchParams(params)
    query.set('page', String(page))
    return api<Paginated<CardMaterial>>(`/materials?${query}`)
  },
  filters: () => api<{ data: { categories: Option[]; teachers: Option[] } }>('/catalog/filters').then((r) => r.data),
  material: (slug: string) => api<{ data: MaterialDetail }>(`/materials/${slug}`).then((r) => r.data),
  related: (slug: string) =>
    api<{ data: { related: CardMaterial[]; from_teacher: CardMaterial[] } }>(`/materials/${slug}/related`).then((r) => r.data),
  playback: (slug: string) => api<{ data: PlaybackSource }>(`/materials/${slug}/playback`).then((r) => r.data),
  saveProgress: (slug: string, body: { position: number; duration: number | null; watched: number }, keepalive = false) =>
    api<void>(`/materials/${slug}/progress`, { method: 'PUT', body, keepalive }),
  complete: (slug: string) => api<void>(`/materials/${slug}/complete`, { method: 'POST' }),
  access: (slug: string) => api<void>(`/materials/${slug}/access`, { method: 'POST' }),
  favorites: (category: string) => api<Paginated<CardMaterial>>(`/me/favorites${category ? `?category=${category}` : ''}`),
  addFavorite: (slug: string) => api<void>(`/me/favorites/${slug}`, { method: 'PUT' }),
  removeFavorite: (slug: string) => api<void>(`/me/favorites/${slug}`, { method: 'DELETE' }),
  history: () => api<Paginated<HistoryEntry>>('/me/history'),
  clearHistory: () => api<void>('/me/history', { method: 'DELETE' }),
  removeHistoryEntry: (id: number) => api<void>(`/me/history/${id}`, { method: 'DELETE' }),
  file: (slug: string, download = false) =>
    api<{ data: { url: string; name: string; mime_type: string } }>(`/materials/${slug}/file${download ? '?download=1' : ''}`).then((r) => r.data),
}
