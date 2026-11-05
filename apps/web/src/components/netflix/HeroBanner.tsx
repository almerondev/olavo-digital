import { Info, Play, Volume2, VolumeX } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router'
import { STUDENT_TYPE_LABELS } from '@/components/cards/typeLabels'
import { coverSrc, coverSrcSet } from '@/components/media/cover'
import { cn } from '@/lib/utils'
import { LiveBadge } from '@/modules/olavo-tv/components/LiveBadge'
import type { HomeHero } from '@/types/api'

const WATCHABLE = ['video', 'external_video']
const PREVIEW_DELAY_MS = 1200
const PREVIEW_LIMIT_MS = 30000

function prefersStillImage(): boolean {
  const connection = (navigator as Navigator & { connection?: { saveData?: boolean } }).connection

  return window.matchMedia('(prefers-reduced-motion: reduce)').matches || connection?.saveData === true
}

/** Imagem primeiro; a prévia entra por cima depois de um instante e sai ao acabar. */
function HeroPreview({ hero }: { hero: HomeHero }) {
  const [started, setStarted] = useState(false)
  const [visible, setVisible] = useState(false)
  const [muted, setMuted] = useState(true)
  const [offScreen, setOffScreen] = useState(false)
  const container = useRef<HTMLDivElement>(null)
  const video = useRef<HTMLVideoElement>(null)
  const preview = hero.preview

  useEffect(() => {
    if (!preview || prefersStillImage()) return
    const timer = window.setTimeout(() => setStarted(true), PREVIEW_DELAY_MS)
    return () => window.clearTimeout(timer)
  }, [preview])

  useEffect(() => {
    if (!visible || preview?.loop) return
    const timer = window.setTimeout(() => setVisible(false), PREVIEW_LIMIT_MS)
    return () => window.clearTimeout(timer)
  }, [visible, preview?.loop])

  useEffect(() => {
    if (!container.current) return
    const observer = new IntersectionObserver(([entry]) => setOffScreen(!entry.isIntersecting), { threshold: 0.3 })
    observer.observe(container.current)
    return () => observer.disconnect()
  }, [])

  useEffect(() => {
    if (!video.current) return
    if (offScreen || !visible) video.current.pause()
    else video.current.play().catch(() => undefined)
  }, [offScreen, visible])

  if (!preview || !started) return <div ref={container} aria-hidden className="absolute inset-0" />

  return (
    <div ref={container} className="absolute inset-0">
      <div aria-hidden className={cn('absolute inset-0 overflow-hidden transition-opacity duration-1000', visible ? 'opacity-100' : 'opacity-0')}>
        {preview.kind === 'file' ? (
          <video
            ref={video}
            src={preview.url}
            muted={muted}
            autoPlay
            loop={preview.loop}
            playsInline
            preload="auto"
            onPlaying={() => setVisible(true)}
            onEnded={() => setVisible(false)}
            className="size-full object-cover"
          />
        ) : (
          <iframe
            src={preview.url}
            title=""
            tabIndex={-1}
            allow="autoplay; encrypted-media"
            onLoad={() => setVisible(true)}
            className="pointer-events-none absolute top-1/2 left-1/2 aspect-video min-h-full w-full min-w-[178vh] -translate-x-1/2 -translate-y-1/2"
          />
        )}
      </div>
      {preview.kind === 'file' && visible && (
        <button
          type="button"
          aria-label={muted ? 'Ativar som da prévia' : 'Desativar som da prévia'}
          onClick={() => setMuted(!muted)}
          className="absolute right-4 bottom-24 z-10 grid size-10 place-items-center rounded-full border border-white/50 bg-black/30 text-white transition hover:bg-black/60 sm:right-8 lg:right-12"
        >
          {muted ? <VolumeX className="size-5" /> : <Volume2 className="size-5" />}
        </button>
      )}
    </div>
  )
}

export function HeroBanner({ hero }: { hero: HomeHero }) {
  const src = coverSrc(hero.image_urls)
  const meta = [hero.category, hero.teacher, hero.material_type && STUDENT_TYPE_LABELS[hero.material_type]].filter(Boolean)
  const label = hero.cta_label ?? (hero.material_type && WATCHABLE.includes(hero.material_type) ? 'Assistir agora' : 'Acessar agora')

  return (
    <section aria-label="Destaque" className="relative flex min-h-[68dvh] items-end overflow-hidden px-4 pt-24 pb-12 sm:min-h-[80dvh] sm:px-8 sm:pb-20 lg:px-12">
      {src && <img src={src} srcSet={coverSrcSet(hero.image_urls)} sizes="100vw" alt="" className="absolute inset-0 size-full object-cover" />}
      <HeroPreview key={hero.id} hero={hero} />
      <div aria-hidden className="pointer-events-none absolute inset-0 bg-gradient-to-r from-black/85 via-black/35 to-transparent" />
      <div aria-hidden className="pointer-events-none absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-background to-transparent" />

      <div className="relative max-w-2xl">
        {hero.is_live && <div className="mb-3"><LiveBadge /></div>}
        {meta.length > 0 && <p className="mb-3 text-sm font-medium text-white/80">{meta.join(' · ')}</p>}
        <h1 className="font-heading text-4xl leading-[1.04] font-bold tracking-tight text-balance text-white sm:text-6xl">{hero.title}</h1>
        {hero.description && <p className="mt-4 line-clamp-3 max-w-xl text-base text-white/85 sm:text-lg">{hero.description}</p>}

        <div className="mt-6 flex flex-wrap gap-3">
          {hero.is_live ? (
            <Link to="/ao-vivo" className="inline-flex items-center gap-2 rounded-lg bg-white px-6 py-2.5 font-bold text-black transition hover:bg-white/85">
              <Play className="size-5 fill-current" />
              {label}
            </Link>
          ) : hero.material_slug ? (
            <>
              <Link
                to={`/materiais/${hero.material_slug}?abrir=1`}
                className="inline-flex items-center gap-2 rounded-lg bg-white px-6 py-2.5 font-bold text-black transition hover:bg-white/85"
              >
                <Play className="size-5 fill-current" />
                {label}
              </Link>
              <Link
                to={`/materiais/${hero.material_slug}`}
                className="inline-flex items-center gap-2 rounded-lg bg-white/20 px-6 py-2.5 font-bold text-white backdrop-blur transition hover:bg-white/30"
              >
                <Info className="size-5" />
                Mais informações
              </Link>
            </>
          ) : hero.external_url && (
            <a href={hero.external_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 rounded-lg bg-white px-6 py-2.5 font-bold text-black transition hover:bg-white/85">
              {hero.cta_label ?? 'Saiba mais'}
            </a>
          )}
        </div>
      </div>
    </section>
  )
}
