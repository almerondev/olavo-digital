import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router'
import { coverSrc } from '@/components/media/cover'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import type { Announcement } from '@/types/api'
import { formatDate } from '@/utils/format'
import { communicationApi } from '../api'

const PRIORITY_LABELS: Record<number, string> = { 1: 'Importante', 2: 'Urgente' }

export function PriorityTag({ priority }: { priority: number }) {
  if (!PRIORITY_LABELS[priority]) return null

  return (
    <span className={cn('rounded px-1.5 py-0.5 text-xs font-semibold', priority === 2 ? 'bg-live text-white' : 'bg-brand text-brand-foreground')}>
      {PRIORITY_LABELS[priority]}
    </span>
  )
}

/** Confirmação registra a leitura; dispensar só tira da home. */
export function AnnouncementAction({ item, readOnly }: { item: Announcement; readOnly?: boolean }) {
  const queryClient = useQueryClient()
  const read = useMutation({
    mutationFn: () => communicationApi.readAnnouncement(item.id),
    onSuccess: () => Promise.all([
      queryClient.invalidateQueries({ queryKey: ['home'] }),
      queryClient.invalidateQueries({ queryKey: ['announcements'] }),
    ]),
  })

  if (readOnly) return null
  if (item.read_at) return item.requires_ack ? <p className="text-xs text-muted-foreground">Leitura confirmada em {formatDate(item.read_at)}</p> : null
  if (!item.requires_ack && item.is_pinned) return null

  return (
    <div>
      <Button size="sm" variant={item.requires_ack ? 'default' : 'outline'} disabled={read.isPending} onClick={() => read.mutate()}>
        {item.requires_ack ? 'Li e confirmo' : 'Dispensar'}
      </Button>
      {read.isError && <p role="alert" className="mt-1 text-xs text-destructive">Não foi possível registrar. Tente novamente.</p>}
    </div>
  )
}

function BannerNotice({ item, readOnly }: { item: Announcement; readOnly?: boolean }) {
  const image = coverSrc(item.image_urls)

  return (
    <article className={cn('relative flex min-h-24 flex-col justify-center gap-2 overflow-hidden rounded-xl border p-4 sm:p-5', item.priority === 2 ? 'border-live' : 'border-brand/50', 'bg-card')}>
      {image && <img src={image} alt="" className="absolute inset-0 size-full object-cover opacity-30" />}
      <div className="relative grid gap-1">
        <div className="flex flex-wrap items-center gap-2">
          <h3 className="font-heading text-lg font-bold">{item.title}</h3>
          <PriorityTag priority={item.priority} />
        </div>
        <p className="max-w-3xl text-sm text-foreground/80">{item.body}</p>
      </div>
      <div className="relative"><AnnouncementAction item={item} readOnly={readOnly} /></div>
    </article>
  )
}

function CardNotice({ item, readOnly }: { item: Announcement; readOnly?: boolean }) {
  const image = coverSrc(item.image_urls)

  return (
    <article className="flex flex-col overflow-hidden rounded-xl border bg-card">
      {image && <img src={image} alt="" className="aspect-video w-full object-cover" />}
      <div className="grid flex-1 content-start gap-2 p-4">
        <div className="flex flex-wrap items-center gap-2">
          <h3 className="font-heading font-bold">{item.title}</h3>
          <PriorityTag priority={item.priority} />
        </div>
        <p className="text-sm text-foreground/80">{item.body}</p>
        <AnnouncementAction item={item} readOnly={readOnly} />
      </div>
    </article>
  )
}

export function AnnouncementsArea({ items, readOnly }: { items: Announcement[]; readOnly?: boolean }) {
  if (items.length === 0) return null

  const banners = items.filter((item) => item.display_mode === 'banner')
  const cards = items.filter((item) => item.display_mode === 'card')
  const section = items.filter((item) => item.display_mode === 'section')

  return (
    <div className="grid gap-4 px-4 sm:px-8 lg:px-12" aria-label="Avisos">
      {banners.map((item) => <BannerNotice key={item.id} item={item} readOnly={readOnly} />)}
      {cards.length > 0 && (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {cards.map((item) => <CardNotice key={item.id} item={item} readOnly={readOnly} />)}
        </div>
      )}
      {section.length > 0 && (
        <section aria-label="Comunicados">
          <div className="mb-2 flex items-baseline justify-between gap-3">
            <h2 className="font-heading text-lg font-bold tracking-tight sm:text-xl">Comunicados</h2>
            <Link to="/avisos" className="text-sm text-muted-foreground underline">Ver todos</Link>
          </div>
          <ul className="overflow-hidden rounded-xl border bg-card">
            {section.map((item) => (
              <li key={item.id} className="grid gap-1 border-b p-4 last:border-0">
                <div className="flex flex-wrap items-center gap-2">
                  <h3 className="font-medium">{item.title}</h3>
                  <PriorityTag priority={item.priority} />
                  <span className="text-xs text-muted-foreground tabular-nums">{formatDate(item.published_at)}</span>
                </div>
                <p className="text-sm text-foreground/80">{item.body}</p>
                <AnnouncementAction item={item} readOnly={readOnly} />
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  )
}
