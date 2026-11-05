import { api } from '@/services/http'
import type { PlatformSettings } from '@/types/api'

export type SettingsInput = Omit<PlatformSettings, 'logo_urls' | 'legal_version' | 'environment'>

export const settingsApi = {
  show: () => api<{ data: PlatformSettings }>('/admin/settings').then((r) => r.data),
  update: (body: SettingsInput) => api<{ data: PlatformSettings }>('/admin/settings', { method: 'PUT', body }).then((r) => r.data),
}
