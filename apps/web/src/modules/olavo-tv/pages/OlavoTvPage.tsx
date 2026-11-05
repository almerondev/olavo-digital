import { useQuery } from '@tanstack/react-query'
import { Radio } from 'lucide-react'
import { Link } from 'react-router'
import { MaterialCard } from '@/components/cards/MaterialCard'
import { CAROUSEL_GUTTER, Carousel } from '@/components/carousels/Carousel'
import type { LiveScheduleItem, ScheduleStatus } from '@/types/api'
import { tvApi } from '../api'
import { LiveBadge } from '../components/LiveBadge'
import { LivePlayer } from '../components/LivePlayer'

const REFRESH_MS = 20000

const STATUS_LABELS: Record<ScheduleStatus, string> = {
  scheduled: 'Agendada',
  live: 'Ao vivo',
  ended: 'Encerrada',
  cancelled: 'Cancelada',
}

const dayLabel = (iso: string) => new Intl.DateTimeFormat('pt-BR', { weekday: 'long', day: '2-digit', month: 'long' }).format(new Date(iso))
const timeLabel = (iso: string) => new Intl.DateTimeFormat('pt-BR', { timeStyle: 'short' }).format(new Date(iso))

function ScheduleList({ items }: { items: LiveScheduleItem[] }) {
  const days = new Map<string, LiveScheduleItem[]>()
  for (const item of items) {
    const day = dayLabel(item.starts_at)
    days.set(day, [...(days.get(day) ?? []), item])
  }

  return (
    <div className="grid gap-5">
      {[...days].map(([day, entries]) => (
        <section key={day} aria-label={day}>
          <h3 className="mb-2 text-sm font-semibold text-muted-foreground first-letter:uppercase">{day}</h3>
          <ul className="grid gap-2">
            {entries.map((item) => (
              <li key={item.id} className="flex items-center gap-4 rounded-xl bg-card p-3">
                <span className="w-14 shrink-0 font-heading text-lg font-bold tabular-nums">{timeLabel(item.starts_at)}</span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate font-semibold">{item.title}</span>
                  <span className="block truncate text-xs text-muted-foreground">{[item.teacher?.name, item.category?.name].filter(Boolean).join(' · ') || 'Olavo TV'}</span>
                </span>
                {item.status === 'live' ? <LiveBadge /> : <span className="text-xs text-muted-foreground">{STATUS_LABELS[item.status]}</span>}
              </li>
            ))}
          </ul>
        </section>
      ))}
    </div>
  )
}

export function OlavoTvPage() {
  const now = useQuery({ queryKey: ['tv', 'now'], queryFn: tvApi.now, refetchInterval: REFRESH_MS })
  const schedule = useQuery({ queryKey: ['tv', 'schedule'], queryFn: tvApi.schedule })

  if (now.isPending) return <div role="status" aria-label="Carregando" className="min-h-[60dvh] animate-pulse bg-card/40" />

  if (now.isError) {
    return <div className="grid min-h-[70dvh] place-items-center px-4 text-center"><p role="alert">Não foi possível carregar a Olavo TV. Recarregue a página.</p></div>
  }

  const { live, recordings, upcoming } = now.data

  return (
    <div className="pt-24 pb-16">
      <div className="px-4 sm:px-8 lg:px-12">
        <h1 className="font-heading text-3xl font-bold tracking-tight sm:text-4xl">Olavo TV</h1>

        {live ? (
          <div className="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
            <LivePlayer key={live.stream_id} streamId={live.stream_id} title={live.schedule.title} reconnecting={live.reconnecting} />
            <div>
              <LiveBadge />
              <h2 className="mt-3 font-heading text-2xl font-bold tracking-tight">{live.schedule.title}</h2>
              {live.schedule.teacher && (
                <Link to={`/elenco/${live.schedule.teacher.slug}`} className="mt-1 block text-sm text-brand hover:underline">{live.schedule.teacher.name}</Link>
              )}
              <p className="mt-1 text-sm text-muted-foreground">Começou às {timeLabel(live.started_at)}</p>
              {live.schedule.description && <p className="mt-3 text-foreground/85">{live.schedule.description}</p>}
            </div>
          </div>
        ) : (
          <div className="mt-5 flex items-center gap-4 rounded-xl bg-card p-6">
            <Radio aria-hidden className="size-8 shrink-0 text-muted-foreground" />
            <div>
              <p className="font-semibold">Nenhuma transmissão no momento</p>
              <p className="text-sm text-muted-foreground">
                {upcoming[0] ? `A próxima é ${upcoming[0].title}, ${dayLabel(upcoming[0].starts_at)} às ${timeLabel(upcoming[0].starts_at)}.` : 'Assim que houver uma nova transmissão, ela aparecerá aqui.'}
              </p>
            </div>
          </div>
        )}
      </div>

      {recordings.length > 0 && (
        <section aria-label="Últimas transmissões gravadas" className="mt-10">
          <h2 className={`mb-2 font-heading text-lg font-bold tracking-tight sm:text-xl ${CAROUSEL_GUTTER}`}>Últimas transmissões gravadas</h2>
          <Carousel label="Últimas transmissões gravadas">
            {recordings.map((item) => <MaterialCard key={item.id} material={item} />)}
          </Carousel>
        </section>
      )}

      <section aria-label="Programação" className="mt-10 max-w-3xl px-4 sm:px-8 lg:px-12">
        <h2 className="mb-3 font-heading text-lg font-bold tracking-tight sm:text-xl">Programação</h2>
        {schedule.isError && <p role="alert">Não foi possível carregar a programação.</p>}
        {schedule.data?.length === 0 && <p className="text-muted-foreground">Nenhuma transmissão programada para os próximos dias.</p>}
        {schedule.data && <ScheduleList items={schedule.data} />}
      </section>
    </div>
  )
}
