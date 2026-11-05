import { Download } from 'lucide-react'
import { usePublicConfig } from '@/modules/settings/config'
import { isNativeApp } from './push'

/** Quem já está no app não precisa ver o convite. */
export function AppDownload() {
  const { data } = usePublicConfig()
  const url = data?.['app.android_url'] as string | null | undefined

  if (!url || isNativeApp()) return null

  return (
    <a href={url} download className="mt-8 flex items-center gap-3 rounded-xl border border-white/15 bg-white/5 p-3 transition hover:bg-white/10">
      <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-brand text-brand-foreground"><Download aria-hidden className="size-5" /></span>
      <span className="min-w-0">
        <span className="block text-sm font-semibold">Baixe o app para Android</span>
        <span className="block text-xs text-muted-foreground">Receba avisos de materiais e transmissões no celular.</span>
      </span>
    </a>
  )
}
