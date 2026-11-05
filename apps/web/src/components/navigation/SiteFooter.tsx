import { Link } from 'react-router'
import { usePublicConfig } from '@/modules/settings/config'

const LABELS: Record<string, string> = { instagram: 'Instagram', youtube: 'YouTube', facebook: 'Facebook', tiktok: 'TikTok' }

export function SiteFooter() {
  const { data } = usePublicConfig()
  const footer = data?.['platform.footer'] as string | null | undefined
  const socials = Object.entries((data?.['platform.socials'] ?? {}) as Record<string, string | null>).filter(([, url]) => url)

  return (
    <footer className="border-t border-white/10 px-4 py-6 text-xs text-muted-foreground sm:px-8 lg:px-12">
      <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
        <span>{footer || (data?.['platform.name'] as string | undefined)}</span>
        {socials.map(([key, url]) => <a key={key} href={url!} target="_blank" rel="noopener noreferrer" className="hover:underline">{LABELS[key] ?? key}</a>)}
        <span className="ml-auto flex gap-4">
          <Link to="/termos" className="hover:underline">Termos de uso</Link>
          <Link to="/privacidade" className="hover:underline">Privacidade</Link>
        </span>
      </div>
    </footer>
  )
}
