import { api } from '@/services/http'
import type { CastProfile, TeacherCard } from '@/types/api'

export const castApi = {
  list: () => api<{ data: TeacherCard[] }>('/teachers').then((r) => r.data),
  profile: (slug: string) => api<{ data: CastProfile }>(`/teachers/${slug}`).then((r) => r.data),
}
