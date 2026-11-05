import { useQuery } from '@tanstack/react-query'
import { useEffect } from 'react'
import { api } from '@/services/http'

export type PublicConfig = Record<string, unknown>

export const CONFIG_KEY = ['config']

export function usePublicConfig() {
  return useQuery({
    queryKey: CONFIG_KEY,
    queryFn: () => api<{ data: PublicConfig }>('/config').then((r) => r.data),
    staleTime: 5 * 60 * 1000,
  })
}

function readableOn(hex: string): string {
  const [r, g, b] = [1, 3, 5].map((start) => parseInt(hex.slice(start, start + 2), 16) / 255)
  return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.5 ? 'oklch(0.2 0.03 70)' : 'oklch(0.98 0 0)'
}

/** Nome e cor definidos no painel valem para a plataforma inteira. */
export function ConfigEffects() {
  const { data } = usePublicConfig()
  const name = data?.['platform.name'] as string | undefined
  const color = data?.['platform.brand_color'] as string | null | undefined

  useEffect(() => {
    if (name) document.title = name
  }, [name])

  useEffect(() => {
    const style = document.documentElement.style

    if (color) {
      style.setProperty('--brand', color)
      style.setProperty('--brand-foreground', readableOn(color))
    } else {
      style.removeProperty('--brand')
      style.removeProperty('--brand-foreground')
    }
  }, [color])

  return null
}
