import { coverSrc } from '@/components/media/cover'
import { usePublicConfig } from '@/modules/settings/config'
import logo from '@/assets/logo-olavo-tv.png'
import { cn } from '@/lib/utils'

export function Wordmark({ className }: { className?: string }) {
  const { data } = usePublicConfig()
  const custom = coverSrc((data?.['platform.logo_urls'] ?? {}) as Record<string, string>)
  const name = (data?.['platform.name'] as string | undefined) ?? 'Olavo TV+'

  // self-start: dentro de colunas flex, o navegador esticaria a imagem até a largura do pai.
  return <img src={custom ?? logo} alt={name} className={cn('h-12 w-auto max-w-full shrink-0 self-start object-contain', className)} />
}
