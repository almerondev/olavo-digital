import { ArrowRight, Info } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { Link } from 'react-router'
import { coverSrc, coverSrcSet } from '@/components/media/cover'
import { FavoriteButton } from '@/modules/favorites/FavoriteButton'
import type { CardMaterial } from '@/types/api'
import { STUDENT_TYPE_LABELS } from './typeLabels'

const HOVER_DELAY_MS = 320
const PREVIEW_WIDTH = 320
const EDGE = 8

interface PreviewBox {
  left: number
  top: number
  width: number
}

function Cover({ urls, sizes }: { urls: Record<string, string>; sizes: string }) {
  const src = coverSrc(urls)

  return src ? (
    <img
      src={src}
      srcSet={coverSrcSet(urls)}
      sizes={sizes}
      alt=""
      loading="lazy"
      className="absolute inset-0 size-full object-cover"
    />
  ) : (
    <div aria-hidden className="absolute inset-0 bg-gradient-to-br from-neutral-700 to-neutral-900" />
  )
}

function remainingLabel(seconds: number | null): string | null {
  if (seconds === null) return null
  return seconds < 60 ? 'menos de 1 min restante' : `${Math.round(seconds / 60)} min restantes`
}

function NewBadge() {
  return <span className="rounded-sm bg-brand px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-brand-foreground">NOVO</span>
}

/**
 * A versão expandida sai em um portal porque o carrossel precisa de overflow escondido,
 * o que cortaria o card. Em telas de toque não há hover: o toque abre os detalhes.
 */
export function MaterialCard({ material }: { material: CardMaterial }) {
  const [box, setBox] = useState<PreviewBox | null>(null)
  const timer = useRef<number | undefined>(undefined)
  const anchor = useRef<HTMLAnchorElement>(null)

  useEffect(() => () => window.clearTimeout(timer.current), [])

  useEffect(() => {
    if (!box) return
    const close = () => setBox(null)
    window.addEventListener('scroll', close, { passive: true })
    return () => window.removeEventListener('scroll', close)
  }, [box])

  function scheduleOpen() {
    if (!window.matchMedia('(hover: hover)').matches) return

    timer.current = window.setTimeout(() => {
      const rect = anchor.current?.getBoundingClientRect()
      if (!rect) return

      const width = Math.max(PREVIEW_WIDTH, rect.width)
      const centered = rect.left - (width - rect.width) / 2
      const left = Math.min(Math.max(EDGE, centered), document.documentElement.clientWidth - width - EDGE)

      setBox({ left: left + window.scrollX, top: rect.top + window.scrollY + rect.height * 0.08, width })
    }, HOVER_DELAY_MS)
  }

  const meta = [material.teacher, material.category].filter(Boolean).join(' · ')
  const progress = material.progress
  const watching = progress !== null && !progress.completed && progress.percent > 0

  return (
    <>
      <Link
        ref={anchor}
        to={`/materiais/${material.slug}`}
        onMouseEnter={scheduleOpen}
        onMouseLeave={() => window.clearTimeout(timer.current)}
        className="group relative block aspect-[2/3] overflow-hidden rounded-xl bg-card transition duration-300 hover:ring-2 hover:ring-white/70 focus-visible:ring-2 focus-visible:ring-brand"
      >
        <Cover urls={material.image_urls} sizes="(min-width: 1024px) 16vw, (min-width: 640px) 26vw, 44vw" />
        <div aria-hidden className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/15 to-transparent" />
        <div className="absolute inset-x-0 bottom-0 p-2.5">
          <div className="mb-1 flex items-center gap-1.5">
            {material.is_new && <NewBadge />}
            <span className="text-[10px] font-semibold tracking-wide text-white/80 uppercase">{STUDENT_TYPE_LABELS[material.type]}</span>
          </div>
          <p className="line-clamp-3 text-sm leading-snug font-semibold text-white">{material.title}</p>
          {meta && <p className="mt-0.5 truncate text-xs text-white/70">{meta}</p>}
          {watching && (
            <p className="mt-1 truncate text-[11px] text-white/80">
              {[`${progress.percent}%`, remainingLabel(progress.remaining_seconds)].filter(Boolean).join(' · ')}
            </p>
          )}
        </div>
        {watching && (
          <div role="progressbar" aria-valuenow={progress.percent} aria-valuemin={0} aria-valuemax={100} aria-label="Progresso" className="absolute inset-x-0 bottom-0 h-1 bg-white/25">
            <div className="h-full bg-brand" style={{ width: `${progress.percent}%` }} />
          </div>
        )}
      </Link>

      {box && createPortal(
        <div
          className="dark absolute z-40 animate-in overflow-hidden rounded-xl bg-card text-foreground shadow-2xl shadow-black/70 duration-200 fade-in zoom-in-90"
          style={{ left: box.left, top: box.top, width: box.width }}
          onMouseLeave={() => setBox(null)}
        >
          <Link to={`/materiais/${material.slug}`} className="relative block aspect-video" onClick={() => setBox(null)}>
            <Cover urls={Object.keys(material.banner_urls).length ? material.banner_urls : material.image_urls} sizes="320px" />
          </Link>
          <div className="grid gap-2 p-3">
            <div className="flex items-center gap-2">
              <Link
                to={`/materiais/${material.slug}?abrir=1`}
                onClick={() => setBox(null)}
                className="inline-flex items-center gap-1.5 rounded bg-white px-3 py-1.5 text-xs font-bold text-black transition hover:bg-white/85"
              >
                Acessar
                <ArrowRight className="size-3.5" />
              </Link>
              <Link
                to={`/materiais/${material.slug}`}
                onClick={() => setBox(null)}
                aria-label={`Mais informações sobre ${material.title}`}
                className="grid size-7 place-items-center rounded-full border border-white/40 transition hover:border-white"
              >
                <Info className="size-3.5" />
              </Link>
              <FavoriteButton slug={material.slug} title={material.title} initial={material.is_favorite} />
            </div>
            <p className="line-clamp-2 text-sm font-semibold">{material.title}</p>
            <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
              {material.is_new && <NewBadge />}
              <span>{STUDENT_TYPE_LABELS[material.type]}</span>
              {meta && <span>{meta}</span>}
            </div>
            {material.description && <p className="line-clamp-2 text-xs text-muted-foreground">{material.description}</p>}
          </div>
        </div>,
        document.body,
      )}
    </>
  )
}
